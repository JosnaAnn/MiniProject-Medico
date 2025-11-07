<?php
// superadmin.php (modern UI)
session_start();
$conn = new mysqli("localhost","root","","miniproject");
if ($conn->connect_error) die("DB Error");

// security
if (!isset($_SESSION['superadmin_logged_in']) || $_SESSION['superadmin_logged_in'] !== true) {
    header("Location: login.php"); exit();
}

$success = $error = '';

function clean($v){ return trim($v ?? ''); }

/* Handle form actions: add_hospital_admin, update_hospital, update_admin, delete_admin, delete_hospital */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // logout
    if (isset($_POST['logout'])) { session_destroy(); header("Location: login.php"); exit(); }

    // add
    if (isset($_POST['add_hospital_admin'])) {
        $hname = clean($_POST['hospital_name']);
        $hcode = strtoupper(preg_replace('/\s+/','',clean($_POST['hospital_code'])));
        $location = clean($_POST['location']);
        $contact = clean($_POST['contact']);
        $username = clean($_POST['username']);
        $password = clean($_POST['password']);
        if (!$hname||!$hcode||!$location||!$contact||!$username||!$password) $error="All fields required";
        elseif (!preg_match('/^\d{10}$/',$contact)) $error="Contact must be 10 digits";
        else {
            // unique code check
            $chk = $conn->prepare("SELECT id FROM hospitals WHERE hospital_code=?");
            $chk->bind_param("s",$hcode); $chk->execute();
            if ($chk->get_result()->num_rows>0) { $error="Hospital code exists"; }
            else {
                $conn->begin_transaction();
                try {
                    $s1 = $conn->prepare("INSERT INTO hospitals (name,hospital_code,location,contact,registered_at) VALUES (?,?,?,?,NOW())");
                    $s1->bind_param("ssss",$hname,$hcode,$location,$contact); $s1->execute();
                    $hid = $conn->insert_id;
                    $hash = password_hash($password,PASSWORD_DEFAULT);
                    $s2 = $conn->prepare("INSERT INTO users (username,password,role,hospital_id) VALUES (?,?, 'admin', ?)");
                    $s2->bind_param("ssi",$username,$hash,$hid); $s2->execute();
                    $conn->commit();
                    $success = "Hospital and admin added.";
                } catch(Exception $e) { $conn->rollback(); $error = "Failed: ".$e->getMessage(); }
            }
        }
    }

    // update hospital
    if (isset($_POST['update_hospital'])) {
        $id = intval($_POST['update_hospital_id']);
        $name = clean($_POST['hospital_name']);
        $hcode = strtoupper(preg_replace('/\s+/','',clean($_POST['hospital_code'])));
        $location = clean($_POST['location']);
        $contact = clean($_POST['contact']);
        if (!$name||!$hcode||!$location||!$contact) $error="All fields required";
        else {
            $chk = $conn->prepare("SELECT id FROM hospitals WHERE hospital_code=? AND id<>?");
            $chk->bind_param("si",$hcode,$id); $chk->execute();
            if ($chk->get_result()->num_rows>0) $error="Another hospital uses this code";
            else {
                $u = $conn->prepare("UPDATE hospitals SET name=?, hospital_code=?, location=?, contact=? WHERE id=?");
                $u->bind_param("ssssi",$name,$hcode,$location,$contact,$id); $u->execute(); $success="Hospital updated";
            }
        }
    }

    // update admin
    if (isset($_POST['update_admin'])) {
        $id = intval($_POST['update_admin_id']);
        $username = clean($_POST['username']);
        $password = clean($_POST['password'] ?? '');
        if (!$username) $error="Username required";
        else {
            if ($password) {
                $hash = password_hash($password,PASSWORD_DEFAULT);
                $u = $conn->prepare("UPDATE users SET username=?, password=? WHERE id=? AND role='admin'");
                $u->bind_param("ssi",$username,$hash,$id);
            } else {
                $u = $conn->prepare("UPDATE users SET username=? WHERE id=? AND role='admin'");
                $u->bind_param("si",$username,$id);
            }
            $u->execute(); $success="Admin updated";
        }
    }

    // delete admin
    if (isset($_POST['delete_admin'])) {
        $adminId = intval($_POST['admin_id']);
        $d = $conn->prepare("DELETE FROM users WHERE id=? AND role='admin'");
        $d->bind_param("i",$adminId); $d->execute(); $success="Admin removed";
    }

    // delete hospital (if no patients and no admins)
    if (isset($_POST['delete_hospital'])) {
        $hospitalId = intval($_POST['hospital_id']);
        $ch = $conn->prepare("SELECT id FROM patients WHERE hospital_id=? LIMIT 1"); $ch->bind_param("i",$hospitalId); $ch->execute();
        $hasP = $ch->get_result()->num_rows>0; $ch->close();
        $ca = $conn->prepare("SELECT id FROM users WHERE hospital_id=? AND role='admin' LIMIT 1"); $ca->bind_param("i",$hospitalId); $ca->execute();
        $hasA = $ca->get_result()->num_rows>0; $ca->close();
        if ($hasP) $error="Cannot delete hospital with patient records.";
        elseif ($hasA) $error="Remove admins first.";
        else { $d = $conn->prepare("DELETE FROM hospitals WHERE id=?"); $d->bind_param("i",$hospitalId); $d->execute(); $success="Hospital removed"; }
    }
}

// fetch lists
$admins = $conn->query("SELECT u.id,u.username,h.name AS hospital,h.hospital_code FROM users u LEFT JOIN hospitals h ON u.hospital_id=h.id WHERE u.role='admin' ORDER BY u.id DESC");
$hospitals = $conn->query("SELECT * FROM hospitals ORDER BY registered_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Superadmin • MediCo</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="logo">Medi<span>Co</span></div>
    <nav class="nav">
      <a class="active" href="#"><i class="fa fa-cog"></i> Superadmin</a>
      <a href="#"><i class="fa fa-hospital"></i> Hospitals</a>
      <a href="#"><i class="fa fa-users"></i> Admins</a>
      <form method="POST" style="margin-top:12px;"><button name="logout" class="btn ghost" style="width:100%"><i class="fa fa-right-from-bracket"></i> Logout</button></form>
    </nav>
  </aside>

  <main class="main">
    <div class="header-row">
      <div>
        <div class="header-title">Superadmin Panel</div>
        <div class="small hint">Manage hospitals and hospital admins</div>
      </div>
    </div>

    <?php if($success): ?><div class="card"><strong style="color:green"><?=htmlspecialchars($success)?></strong></div><?php endif; ?>
    <?php if($error): ?><div class="card"><strong style="color:red"><?=htmlspecialchars($error)?></strong></div><?php endif; ?>

    <div class="card">
      <h3>Add Hospital & Admin</h3>
      <form method="POST" style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <input type="text" name="hospital_name" placeholder="Hospital Name" required>
        <input type="text" name="hospital_code" placeholder="Hospital Code (e.g., STH)" required>
        <input type="text" name="location" placeholder="Location" required>
        <input type="text" name="contact" placeholder="Contact (10 digits)" required>
        <input type="text" name="username" placeholder="Admin Username" required>
        <input type="password" name="password" placeholder="Admin Password" required>
        <div style="grid-column:1/3">
          <button class="btn primary" name="add_hospital_admin" type="submit">Add Hospital & Admin</button>
        </div>
      </form>
    </div>

    <div class="card">
      <h3>Current Admins</h3>
      <div class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>Username</th><th>Hospital</th><th>Code</th><th>Actions</th></tr></thead>
          <tbody>
            <?php $i=1; while($row=$admins->fetch_assoc()): ?>
              <tr>
                <td><?=$i++?></td>
                <td><?=htmlspecialchars($row['username'])?></td>
                <td><?=htmlspecialchars($row['hospital'] ?? '-')?></td>
                <td><?=htmlspecialchars($row['hospital_code'] ?? '-')?></td>
                <td>
                  <form method="POST" style="display:inline-block">
                    <input type="hidden" name="edit_admin_id" value="<?=intval($row['id'])?>">
                    <input type="hidden" name="admin_username" value="<?=htmlspecialchars($row['username'])?>">
                    <button name="edit_admin" class="btn ghost" formaction="superadmin.php">Edit</button>
                  </form>
                  <form method="POST" style="display:inline-block" onsubmit="return confirm('Remove admin?')">
                    <input type="hidden" name="admin_id" value="<?=intval($row['id'])?>">
                    <button name="delete_admin" class="btn ghost">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <h3>Registered Hospitals</h3>
      <div class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>Name</th><th>Code</th><th>Location</th><th>Contact</th><th>Actions</th></tr></thead>
          <tbody>
            <?php $j=1; while($row=$hospitals->fetch_assoc()): ?>
              <tr>
                <td><?=$j++?></td>
                <td><?=htmlspecialchars($row['name'])?></td>
                <td><?=htmlspecialchars($row['hospital_code'])?></td>
                <td><?=htmlspecialchars($row['location'])?></td>
                <td><?=htmlspecialchars($row['contact'])?></td>
                <td>
                  <form method="POST" style="display:inline-block">
                    <input type="hidden" name="edit_hospital_id" value="<?=intval($row['id'])?>">
                    <input type="hidden" name="hospital_name" value="<?=htmlspecialchars($row['name'])?>">
                    <input type="hidden" name="hospital_code" value="<?=htmlspecialchars($row['hospital_code'])?>">
                    <input type="hidden" name="location" value="<?=htmlspecialchars($row['location'])?>">
                    <input type="hidden" name="contact" value="<?=htmlspecialchars($row['contact'])?>">
                    <button name="edit_hospital" class="btn ghost">Edit</button>
                  </form>
                  <form method="POST" style="display:inline-block" onsubmit="return confirm('Delete hospital?')">
                    <input type="hidden" name="hospital_id" value="<?=intval($row['id'])?>">
                    <button name="delete_hospital" class="btn ghost">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>
</body>
</html>
