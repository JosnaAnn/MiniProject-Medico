<?php
session_start();
require_once 'config_local.php'; // ✅ Twilio setup file

// Allow only superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

// Database connection
$mysqli = new mysqli("localhost", "root", "", "miniproject");
if ($mysqli->connect_error) die("DB Connection Error");

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$errors = [];
$success = "";

// ✅ Logout functionality
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// ===========================
// ✅ Approve Hospital
// ===========================
if (isset($_POST['approve_hospital'])) {
    $id = (int)$_POST['id'];
    $req = $mysqli->query("SELECT * FROM hospital_requests WHERE id = $id AND status='pending'")->fetch_assoc();

    if ($req) {
        $name = $req['hospital_name'];
        $base_code = strtoupper(substr(preg_replace('/\s+/', '', $name), 0, 3));
        $hospital_code = $base_code;
        $counter = 1;
        while (true) {
            $chk = $mysqli->prepare("SELECT id FROM hospitals WHERE hospital_code = ? LIMIT 1");
            $chk->bind_param("s", $hospital_code);
            $chk->execute();
            $res = $chk->get_result();
            if ($res->num_rows === 0) { $chk->close(); break; }
            $chk->close();
            $counter++;
            $hospital_code = $base_code . $counter;
        }

        // Generate admin credentials
        $username = strtolower(preg_replace('/\s+/', '_', $hospital_code)) . "_admin";
        $password_plain = substr(md5(uniqid()), 0, 8);
        $password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

        $mysqli->begin_transaction();
        try {
            // Insert hospital
            $stmt = $mysqli->prepare("INSERT INTO hospitals (name, hospital_code, location, contact, address, contact_person, contact_phone, contact_email, license_no, license_doc, dlt_registered, approved, deleted, admin_user_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, 0, 1, 0, NULL, NOW())");
            $stmt->bind_param("ssssssss", $req['hospital_name'], $hospital_code, $req['address'], $req['phone'], $req['address'], $req['contact_person'], $req['phone'], $req['email']);
            $stmt->execute();
            $hospital_id = $stmt->insert_id;
            $stmt->close();

            // Create admin
            $adm = $mysqli->prepare("INSERT INTO admins (hospital_id, username, password) VALUES (?, ?, ?)");
            $adm->bind_param("iss", $hospital_id, $username, $password_hash);
            $adm->execute();
            $adm->close();

            // Update hospital_requests
            $upd = $mysqli->prepare("UPDATE hospital_requests SET status='approved' WHERE id=?");
            $upd->bind_param("i", $id);
            $upd->execute();
            $upd->close();

            $mysqli->commit();

            // ✅ Send WhatsApp via Twilio
            $phone = preg_replace('/\D/', '', $req['phone']);
            if (strlen($phone) == 10) $phone = '+91' . $phone;
            $from = $twilioNumber;

            $msg = "✅ *MediCo Approval Update*\n\n"
                 . "Dear *{$req['contact_person']}*,\n\n"
                 . "Your hospital *{$req['hospital_name']}* has been *approved* for MediCo.\n\n"
                 . "🏥 *Hospital Code:* {$hospital_code}\n"
                 . "👤 *Admin Username:* {$username}\n"
                 . "🔑 *Password:* {$password_plain}\n\n"
                 . "You can now access your MediCo hospital dashboard.\n\n"
                 . "— *MediCo Support Team*";

            $waSent = false;
            if (isset($twilio)) {
                try {
                    $twilio->messages->create("whatsapp:$phone", [
                        "from" => $from,
                        "body" => $msg
                    ]);
                    $waSent = true;
                } catch (Exception $e) {
                    error_log("Twilio WhatsApp Error: " . $e->getMessage());
                }
            }

            $success = "✅ Hospital approved successfully!<br>
                        Admin Username: <b>$username</b><br>
                        Password: <b>$password_plain</b><br>
                        Hospital Code: <b>$hospital_code</b><br>" .
                        ($waSent ? "📱 WhatsApp message sent successfully." : "⚠️ WhatsApp failed (check Twilio config).");

        } catch (Exception $e) {
            $mysqli->rollback();
            $errors[] = "Approval error: " . $e->getMessage();
        }
    }
}

// ===========================
// ❌ Delete Hospital
// ===========================
if (isset($_POST['delete_hospital'])) {
    $hid = (int)$_POST['hid'];
    if ($hid > 0) {
        $mysqli->begin_transaction();
        try {
            $info = $mysqli->query("SELECT name, contact, contact_phone FROM hospitals WHERE id = $hid")->fetch_assoc();
            $hname = $info['name'] ?? '';
            $hphone = preg_replace('/\D/', '', ($info['contact_phone'] ?: $info['contact']));
            if (strlen($hphone) == 10) $hphone = '+91' . $hphone;

            $mysqli->query("UPDATE hospitals SET deleted=1, approved=0 WHERE id=$hid");
            $mysqli->query("DELETE FROM admins WHERE hospital_id=$hid");

            if (!empty($hname)) {
                $rq = $mysqli->prepare("UPDATE hospital_requests SET status='deleted' WHERE hospital_name=?");
                $rq->bind_param("s", $hname);
                $rq->execute();
                $rq->close();
            }

            $mysqli->commit();
            $success = "✅ Hospital deleted successfully and database updated.";

            // WhatsApp deletion notice
            $msg = "⚠️ *MediCo Access Revoked*\n\n"
                 . "Dear Administrator,\n\n"
                 . "Your hospital *$hname* has been removed from the MediCo system.\n"
                 . "Please contact MediCo support if this was unexpected.\n\n"
                 . "— *MediCo Admin Team*";

            if (isset($twilio)) {
                try {
                    $twilio->messages->create("whatsapp:$hphone", [
                        "from" => $twilioNumber,
                        "body" => $msg
                    ]);
                } catch (Exception $e) {
                    error_log("Twilio delete WhatsApp Error: " . $e->getMessage());
                }
            }

        } catch (Exception $e) {
            $mysqli->rollback();
            $errors[] = "Delete error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MediCo • Superadmin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body{font-family:'Segoe UI',sans-serif;background:#eef7fb;margin:0;padding:20px;}
.container{max-width:1100px;margin:auto;}
.card{background:#fff;padding:16px;margin-bottom:16px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.08);}
.logo{font-size:26px;font-weight:700;color:#0078b7;text-align:center;margin-bottom:10px;}
.logo span{color:#00bcd4;}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.table{width:100%;border-collapse:collapse;}
.table th,.table td{border:1px solid #e0e0e0;padding:8px;text-align:left;}
.table th{background:#f3fbff}
.btn{background:#0078b7;color:#fff;border:none;padding:8px 12px;border-radius:6px;cursor:pointer;}
.btn:hover{background:#00639c;}
.btn.danger{background:#c62828;}
.btn.danger:hover{background:#b71c1c;}
.alert{padding:10px;margin-bottom:10px;border-radius:8px;}
.alert.success{background:#e8f8f2;color:#006a39;}
.alert.error{background:#ffecec;color:#a20000;}
button{background:#00bcd4;color:#fff;border:none;padding:10px 14px;border-radius:8px;cursor:pointer;}
button:hover{background:#009bb0;}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div class="logo">Medi<span>Co</span></div>
    <form method="POST">
      <button name="logout"><i class="fa fa-right-from-bracket"></i> Logout</button>
    </form>
  </div>

  <?php if($errors): ?>
  <div class="alert error"><?php foreach($errors as $e) echo h($e)."<br>"; ?></div>
  <?php endif; ?>

  <?php if($success): ?>
  <div class="alert success"><?=$success?></div>
  <?php endif; ?>

  <!-- 🕐 Pending Hospitals -->
  <div class="card">
    <h3>Pending Hospital Requests</h3>
    <?php
    $reqs = $mysqli->query("SELECT * FROM hospital_requests WHERE status='pending' ORDER BY created_at DESC");
    if ($reqs->num_rows == 0): ?>
      <p>No pending hospital requests.</p>
    <?php else: ?>
      <table class="table">
        <tr><th>Name</th><th>Contact</th><th>Email</th><th>Action</th></tr>
        <?php while($r=$reqs->fetch_assoc()): ?>
        <tr>
          <td><?=h($r['hospital_name'])?></td>
          <td><?=h($r['phone'])?></td>
          <td><?=h($r['email'])?></td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="id" value="<?=$r['id']?>">
              <button class="btn" name="approve_hospital"><i class="fa fa-check"></i> Approve</button>
            </form>
          </td>
        </tr>
        <?php endwhile; ?>
      </table>
    <?php endif; ?>
  </div>

  <!-- ✅ Approved Hospitals -->
  <div class="card">
    <h3>Approved Hospitals</h3>
    <?php
    $approved = $mysqli->query("
      SELECT h.*, a.username AS admin_username
      FROM hospitals h
      LEFT JOIN admins a ON a.hospital_id=h.id
      WHERE h.approved=1 AND h.deleted=0
      ORDER BY h.created_at DESC
    ");
    if ($approved->num_rows == 0): ?>
      <p>No approved hospitals.</p>
    <?php else: ?>
      <table class="table">
        <tr><th>Name</th><th>Code</th><th>Admin</th><th>Phone</th><th>Actions</th></tr>
        <?php while($h=$approved->fetch_assoc()): ?>
        <tr>
          <td><?=h($h['name'])?></td>
          <td><?=h($h['hospital_code'])?></td>
          <td><?=h($h['admin_username'])?></td>
          <td><?=h($h['contact_phone'])?></td>
          <td>
            <form method="POST" onsubmit="return confirm('Delete <?=h($h['name'])?>?')">
              <input type="hidden" name="hid" value="<?=$h['id']?>">
              <button class="btn danger" name="delete_hospital"><i class="fa fa-trash"></i> Delete</button>
            </form>
          </td>
        </tr>
        <?php endwhile; ?>
      </table>
    <?php endif; ?>
  </div>

  <!-- 🗂️ Deleted Hospitals -->
  <div class="card">
    <h3>Deleted Hospitals</h3>
    <?php
    $deleted = $mysqli->query("SELECT * FROM hospitals WHERE deleted=1 ORDER BY created_at DESC");
    if ($deleted->num_rows == 0): ?>
      <p>No deleted hospitals.</p>
    <?php else: ?>
      <table class="table">
        <tr><th>Name</th><th>Code</th><th>Status</th></tr>
        <?php while($h=$deleted->fetch_assoc()): ?>
        <tr><td><?=h($h['name'])?></td><td><?=h($h['hospital_code'])?></td><td>Deleted</td></tr>
        <?php endwhile; ?>
      </table>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
