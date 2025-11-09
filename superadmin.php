<?php
// superadmin.php — MediCo
session_start();

// Allow only superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php"); exit();
}

// DB
$mysqli = new mysqli("localhost", "root", "", "miniproject");
if ($mysqli->connect_error) die("DB Error");

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$CSRF = $_SESSION['csrf_token'];

// Helpers
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function ok_phone($p){ return preg_match('/^[0-9]{10}$/', $p); }
function is_email($e){ return filter_var($e, FILTER_VALIDATE_EMAIL); }

$errors = [];
$success = "";

// ---------- POST actions ----------
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $CSRF) {
        $errors[] = "Invalid request. Please refresh and try again.";
    } else {

        // ADD HOSPITAL
        if (isset($_POST['action']) && $_POST['action']==='add') {
            $name            = trim($_POST['name'] ?? '');
            $hospital_code   = strtoupper(trim($_POST['hospital_code'] ?? ''));
            $location        = trim($_POST['location'] ?? '');
            $contact         = trim($_POST['contact'] ?? '');
            $address         = trim($_POST['address'] ?? '');
            $contact_person  = trim($_POST['contact_person'] ?? '');
            $contact_phone   = trim($_POST['contact_phone'] ?? '');
            $contact_email   = trim($_POST['contact_email'] ?? '');
            $license_no      = trim($_POST['license_no'] ?? '');
            $dlt_registered  = isset($_POST['dlt_registered']) ? 1 : 0;
            $admin_username  = trim($_POST['admin_username'] ?? '');
            $admin_password  = $_POST['admin_password'] ?? '';

            // Validation
            if ($name==='' || $hospital_code==='' || $admin_username==='' || $admin_password==='') {
                $errors[] = "Please fill required fields: Name, Hospital Code, Admin username & password.";
            }
            if (!preg_match('/^[A-Z0-9\-]+$/', $hospital_code)) {
                $errors[] = "Hospital code can contain only A–Z, 0–9 and hyphen.";
            }
            if ($contact_phone!=='' && !ok_phone($contact_phone)) $errors[] = "Contact phone must be 10 digits.";
            if ($contact_email!=='' && !is_email($contact_email)) $errors[] = "Invalid contact email.";
            if ($contact!=='' && !ok_phone($contact)) $errors[] = "Main contact (contact) must be 10 digits.";

            // Unique code
            $stmt = $mysqli->prepare("SELECT id FROM hospitals WHERE hospital_code=? LIMIT 1");
            $stmt->bind_param("s", $hospital_code);
            $stmt->execute();
            $ex = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($ex) $errors[] = "Hospital code already exists.";

            // Upload license doc (optional)
            $license_doc = null;
            if (!empty($_FILES['license_doc']['name'])) {
                $u = $_FILES['license_doc'];
                if ($u['error']===0) {
                    $ext = strtolower(pathinfo($u['name'], PATHINFO_EXTENSION));
                    $allowed = ['pdf','jpg','jpeg','png'];
                    if (!in_array($ext,$allowed)) {
                        $errors[] = "License document must be pdf/jpg/jpeg/png.";
                    } else {
                        $dir = __DIR__ . "/uploads/licenses";
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        $fname = $hospital_code . "_" . time() . "." . $ext;
                        $path = $dir . "/" . $fname;
                        if (!move_uploaded_file($u['tmp_name'],$path)) {
                            $errors[] = "Failed to save uploaded file.";
                        } else {
                            $license_doc = "uploads/licenses/" . $fname;
                        }
                    }
                } else {
                    $errors[] = "Upload error (code {$u['error']}).";
                }
            }

            if (empty($errors)) {
                $mysqli->begin_transaction();
                try {
                    // Create admin user (role=admin). Hospital link will be set after hospital insert.
                    $hash = password_hash($admin_password, PASSWORD_DEFAULT);
                    $u = $mysqli->prepare("INSERT INTO users (username, password, role, hospital_id) VALUES (?, ?, 'admin', 0)");
                    $u->bind_param("ss", $admin_username, $hash);
                    $u->execute();
                    $admin_user_id = $u->insert_id;
                    $u->close();

                    // Insert hospital (approved=0 by default, deleted=0)
                    $q = $mysqli->prepare("
                        INSERT INTO hospitals
                        (name, hospital_code, location, contact, address, contact_person, contact_phone, contact_email, license_no, license_doc, dlt_registered, admin_user_id, approved, deleted)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)
                    ");
                    $q->bind_param(
                        "ssssssssssii",
                        $name, $hospital_code, $location, $contact, $address,
                        $contact_person, $contact_phone, $contact_email,
                        $license_no, $license_doc, $dlt_registered, $admin_user_id
                    );
                    $q->execute();
                    $hid = $q->insert_id;
                    $q->close();

                    // link user to hospital
                    $uu = $mysqli->prepare("UPDATE users SET hospital_id=? WHERE id=?");
                    $uu->bind_param("ii", $hid, $admin_user_id);
                    $uu->execute();
                    $uu->close();

                    $mysqli->commit();
                    $success = "Hospital added. Status: Pending approval.";
                } catch (Exception $e) {
                    $mysqli->rollback();
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
        }

        // EDIT
        if (isset($_POST['action']) && $_POST['action']==='edit') {
            $hid = (int)($_POST['hid'] ?? 0);
            if ($hid<=0) $errors[] = "Invalid hospital id.";
            $name            = trim($_POST['name'] ?? '');
            $location        = trim($_POST['location'] ?? '');
            $contact         = trim($_POST['contact'] ?? '');
            $address         = trim($_POST['address'] ?? '');
            $contact_person  = trim($_POST['contact_person'] ?? '');
            $contact_phone   = trim($_POST['contact_phone'] ?? '');
            $contact_email   = trim($_POST['contact_email'] ?? '');
            $license_no      = trim($_POST['license_no'] ?? '');
            $dlt_registered  = isset($_POST['dlt_registered']) ? 1 : 0;
            $approved        = isset($_POST['approved']) ? 1 : 0;

            if ($contact!=='' && !ok_phone($contact)) $errors[] = "Main contact must be 10 digits.";
            if ($contact_phone!=='' && !ok_phone($contact_phone)) $errors[] = "Contact phone must be 10 digits.";
            if ($contact_email!=='' && !is_email($contact_email)) $errors[] = "Invalid email.";

            // Optional license replace
            if (empty($errors) && !empty($_FILES['license_doc_edit']['name'])) {
                $u = $_FILES['license_doc_edit'];
                if ($u['error']===0) {
                    $ext = strtolower(pathinfo($u['name'], PATHINFO_EXTENSION));
                    $allowed = ['pdf','jpg','jpeg','png'];
                    if (!in_array($ext,$allowed)) {
                        $errors[] = "License document must be pdf/jpg/jpeg/png.";
                    } else {
                        $dir = __DIR__ . "/uploads/licenses";
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        $fname = "H{$hid}_" . time() . "." . $ext;
                        $path = $dir . "/" . $fname;
                        if (move_uploaded_file($u['tmp_name'],$path)) {
                            $license_doc = "uploads/licenses/" . $fname;
                            $s = $mysqli->prepare("UPDATE hospitals SET license_doc=? WHERE id=?");
                            $s->bind_param("si", $license_doc, $hid);
                            $s->execute(); $s->close();
                        } else {
                            $errors[] = "Failed to save uploaded file.";
                        }
                    }
                } else {
                    $errors[] = "Upload error (code {$u['error']}).";
                }
            }

            if (empty($errors)) {
                $q = $mysqli->prepare("
                    UPDATE hospitals
                    SET name=?, location=?, contact=?, address=?, contact_person=?, contact_phone=?, contact_email=?, license_no=?, dlt_registered=?, approved=?
                    WHERE id=? AND deleted=0
                ");
                $q->bind_param("ssssssssiii",
                    $name, $location, $contact, $address, $contact_person, $contact_phone, $contact_email, $license_no, $dlt_registered, $approved, $hid
                );
                $q->execute(); $q->close();
                $success = "Hospital updated.";
            }
        }

        // DELETE (soft)
        if (isset($_POST['action']) && $_POST['action']==='delete') {
            $hid = (int)($_POST['hid'] ?? 0);
            if ($hid>0) {
                $q = $mysqli->prepare("UPDATE hospitals SET deleted=1 WHERE id=?");
                $q->bind_param("i", $hid);
                $q->execute(); $q->close();
                $success = "Hospital deleted (soft).";
            }
        }

        // APPROVE TOGGLE
        if (isset($_POST['action']) && $_POST['action']==='toggle_approve') {
            $hid = (int)($_POST['hid'] ?? 0);
            if ($hid>0) {
                // Read current status
                $s = $mysqli->prepare("SELECT approved FROM hospitals WHERE id=? AND deleted=0");
                $s->bind_param("i", $hid);
                $s->execute();
                $cur = $s->get_result()->fetch_assoc();
                $s->close();
                if ($cur) {
                    $new = $cur['approved'] ? 0 : 1;
                    $u = $mysqli->prepare("UPDATE hospitals SET approved=? WHERE id=?");
                    $u->bind_param("ii", $new, $hid);
                    $u->execute(); $u->close();
                    $success = $new ? "Hospital approved." : "Hospital unapproved.";
                }
            }
        }

        // EXPORT CSV
        if (isset($_POST['action']) && $_POST['action']==='export') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=hospitals.csv');
            $out = fopen('php://output','w');
            fputcsv($out, ['ID','Name','Code','Location','Contact','Approved','Registered At']);
            $rs = $mysqli->query("SELECT id,name,hospital_code,location,contact,approved,registered_at FROM hospitals WHERE deleted=0 ORDER BY registered_at DESC");
            while($r=$rs->fetch_assoc()){
                fputcsv($out, [$r['id'],$r['name'],$r['hospital_code'],$r['location'],$r['contact'],$r['approved'],$r['registered_at']]);
            }
            fclose($out);
            exit();
        }
    }
}

// ---------- Fetch list (search + pagination) ----------
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 12;
$off    = ($page-1)*$per;

$where = "WHERE deleted=0";
$params = [];
$types  = "";

if ($search !== "") {
    $where .= " AND (name LIKE ? OR hospital_code LIKE ? OR contact_person LIKE ? OR contact_phone LIKE ? OR contact_email LIKE ? OR location LIKE ?)";
    $like = "%{$search}%";
    $params = [$like,$like,$like,$like,$like,$like];
    $types  = "ssssss";
}

// total
$total_sql = "SELECT COUNT(*) AS c FROM hospitals {$where}";
$st = $mysqli->prepare($total_sql);
if ($types!=="") $st->bind_param($types, ...$params);
$st->execute();
$total = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
$st->close();

// list
$list_sql = "SELECT id,name,hospital_code,location,contact,approved,registered_at,contact_person,contact_phone,contact_email,dlt_registered,license_doc
             FROM hospitals {$where}
             ORDER BY registered_at DESC
             LIMIT ?, ?";
$st = $mysqli->prepare($list_sql);
if ($types==="") {
    $st->bind_param("ii", $off, $per);
} else {
    $bindTypes = $types . "ii";
    $bindParams = $params;
    $bindParams[] = $off;
    $bindParams[] = $per;
    $st->bind_param($bindTypes, ...$bindParams);
}
$st->execute();
$list = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

$pages = max(1, (int)ceil($total/$per));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MediCo • Superadmin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{box-sizing:border-box;font-family:'Segoe UI',sans-serif}
body{margin:0;background:#eef7fb;padding:24px}
.container{max-width:1100px;margin:0 auto}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.logo{font-size:28px;font-weight:800;color:#0078b7}.logo span{color:#00bcd4}
.small{color:#607d8b;font-size:13px}
.btn{background:#0078b7;color:#fff;border:none;border-radius:8px;padding:10px 14px;cursor:pointer}
.btn.ghost{background:#fff;color:#0078b7;border:1px solid #cfeffb}
.card{background:#fff;border-radius:12px;padding:16px;box-shadow:0 8px 24px rgba(0,0,0,.06);margin-bottom:16px}
.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
input,select,textarea{width:100%;padding:10px;border:1px solid #e0e0e0;border-radius:8px}
label{font-size:13px;color:#333;margin-bottom:6px;display:block}
.list{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:12px}
.item{border:1px solid #e9f3f8;border-radius:10px;padding:12px}
.item h4{margin:0 0 4px 0;color:#0078b7}
.meta{font-size:13px;color:#444}
.badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px}
.badge.ok{background:#e7fbef;color:#0a7f46}
.badge.no{background:#fff2f2;color:#a30000}
.alert{padding:10px;border-radius:8px;margin-bottom:10px}
.alert.error{background:#ffecec;color:#a20000}
.alert.success{background:#e8f8f2;color:#006a39}
.controls{display:flex;gap:10px;align-items:center}
.search{padding:9px 12px;border:1px solid #e0e0e0;border-radius:8px;width:260px}
.pager{display:flex;gap:8px;align-items:center;margin-top:10px}
.file-link{display:inline-block;padding:6px 8px;border:1px solid #e1f5fb;background:#f3fbff;color:#0078b7;border-radius:6px;text-decoration:none}
@media(max-width:1000px){.list{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.grid{grid-template-columns:1fr}.list{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div>
      <a href="index.php" style="text-decoration:none"><div class="logo">Medi<span>Co</span></div></a>
      <div class="small">Superadmin — manage hospitals</div>
    </div>
    <div class="controls">
      <form method="GET" style="display:flex;gap:8px">
        <input class="search" name="search" placeholder="Search by name, code, person…" value="<?=h($search)?>">
        <button class="btn ghost" type="submit">Search</button>
      </form>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?=$CSRF?>">
        <input type="hidden" name="action" value="export">
        <button class="btn"><i class="fa fa-download"></i>&nbsp;Export CSV</button>
      </form>
      <form method="POST" action="logout.php">
        <button class="btn ghost">Logout</button>
      </form>
    </div>
  </div>

  <?php if($errors): ?>
    <div class="alert error"><?php foreach($errors as $e) echo h($e)."<br>"; ?></div>
  <?php endif; ?>
  <?php if($success): ?>
    <div class="alert success"><?=h($success)?></div>
  <?php endif; ?>

  <!-- Add Hospital -->
  <div class="card">
    <h3>Add Hospital</h3>
    <form method="POST" enctype="multipart/form-data" style="margin-top:10px">
      <input type="hidden" name="csrf" value="<?=$CSRF?>">
      <input type="hidden" name="action" value="add">
      <div class="grid">
        <div><label>Hospital Name *</label><input name="name" required></div>
        <div><label>Hospital Code (unique, e.g., CCH, MC-001) *</label><input name="hospital_code" required></div>

        <div><label>Location</label><input name="location" placeholder="Town / City"></div>
        <div><label>Main Contact No.</label><input name="contact" placeholder="10-digit"></div>

        <div><label>Address</label><textarea name="address" rows="2"></textarea></div>
        <div><label>Contact Person</label><input name="contact_person"></div>

        <div><label>Contact Phone</label><input name="contact_phone" placeholder="10-digit"></div>
        <div><label>Contact Email</label><input name="contact_email" type="email"></div>

        <div><label>License / Reg. No</label><input name="license_no"></div>
        <div><label>Upload License (pdf/jpg/png)</label><input type="file" name="license_doc" accept=".pdf,.jpg,.jpeg,.png"></div>

        <div><label>DLT Registered?</label>
          <select name="dlt_registered"><option value="0">No</option><option value="1">Yes</option></select>
        </div>
        <div><label>Admin Username *</label><input name="admin_username" required></div>

        <div><label>Admin Password *</label><input type="password" name="admin_password" required></div>
      </div>
      <div style="margin-top:10px">
        <button class="btn"><i class="fa fa-plus"></i>&nbsp;Add Hospital</button>
        <span class="small">New hospitals start as <b>Pending</b>. Toggle approve after verifying.</span>
      </div>
    </form>
  </div>

  <!-- List -->
  <div class="card">
    <h3>Registered Hospitals (<?= (int)$total ?>)</h3>
    <?php if(!$list): ?>
      <div class="small">No hospitals found.</div>
    <?php else: ?>
      <div class="list">
        <?php foreach($list as $row): ?>
          <div class="item">
            <h4><?=h($row['name'])?> <span class="small"> (<?=h($row['hospital_code'])?>)</span></h4>
            <div class="meta"><b>Location:</b> <?=h($row['location'])?> &nbsp; • &nbsp; <b>Contact:</b> <?=h($row['contact'])?></div>
            <div class="meta"><b>Person:</b> <?=h($row['contact_person'])?> (<?=h($row['contact_phone'])?>) • <?=h($row['contact_email'])?></div>
            <div class="meta"><b>DLT:</b> <?= $row['dlt_registered'] ? 'Yes' : 'No' ?> &nbsp; • &nbsp; <b>Status:</b>
              <span class="badge <?= $row['approved']?'ok':'no' ?>"><?= $row['approved']?'Approved':'Pending' ?></span>
            </div>
            <div class="meta"><b>Registered:</b> <?=h($row['registered_at'])?></div>

            <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
              <!-- Approve toggle -->
              <form method="POST" style="margin:0">
                <input type="hidden" name="csrf" value="<?=$CSRF?>">
                <input type="hidden" name="action" value="toggle_approve">
                <input type="hidden" name="hid" value="<?= (int)$row['id'] ?>">
                <button class="btn"><?= $row['approved'] ? 'Unapprove' : 'Approve' ?></button>
              </form>

              <!-- Edit toggle -->
              <button class="btn ghost" onclick="toggleEdit(<?= (int)$row['id'] ?>)">Edit</button>

              <!-- Delete -->
              <form method="POST" style="margin:0" onsubmit="return confirm('Delete this hospital? (soft delete)')">
                <input type="hidden" name="csrf" value="<?=$CSRF?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="hid" value="<?= (int)$row['id'] ?>">
                <button class="btn"><i class="fa fa-trash"></i></button>
              </form>

              <?php if(!empty($row['license_doc'])): ?>
                <a class="file-link" href="<?=h($row['license_doc'])?>" target="_blank"><i class="fa fa-file"></i> License</a>
              <?php endif; ?>
            </div>

            <!-- Inline edit box -->
            <div id="editBox<?= (int)$row['id'] ?>" style="display:none;margin-top:10px">
              <?php
              $hid = (int)$row['id'];
              $e = $mysqli->prepare("SELECT * FROM hospitals WHERE id=? LIMIT 1");
              $e->bind_param("i", $hid);
              $e->execute(); $full = $e->get_result()->fetch_assoc(); $e->close();
              ?>
              <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?=$CSRF?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="hid" value="<?=$hid?>">
                <div class="grid">
                  <div><label>Name</label><input name="name" value="<?=h($full['name'])?>"></div>
                  <div><label>Location</label><input name="location" value="<?=h($full['location'])?>"></div>

                  <div><label>Main Contact</label><input name="contact" value="<?=h($full['contact'])?>"></div>
                  <div><label>Address</label><input name="address" value="<?=h($full['address'])?>"></div>

                  <div><label>Contact Person</label><input name="contact_person" value="<?=h($full['contact_person'])?>"></div>
                  <div><label>Contact Phone</label><input name="contact_phone" value="<?=h($full['contact_phone'])?>"></div>

                  <div><label>Contact Email</label><input name="contact_email" value="<?=h($full['contact_email'])?>"></div>
                  <div><label>License No</label><input name="license_no" value="<?=h($full['license_no'])?>"></div>

                  <div><label>DLT Registered?</label>
                    <select name="dlt_registered">
                      <option value="0" <?= !$full['dlt_registered']?'selected':'' ?>>No</option>
                      <option value="1" <?= $full['dlt_registered']?'selected':'' ?>>Yes</option>
                    </select>
                  </div>
                  <div><label>Approved?</label>
                    <select name="approved">
                      <option value="0" <?= !$full['approved']?'selected':'' ?>>No</option>
                      <option value="1" <?= $full['approved']?'selected':'' ?>>Yes</option>
                    </select>
                  </div>

                  <div><label>Replace License (optional)</label><input type="file" name="license_doc_edit" accept=".pdf,.jpg,.jpeg,.png"></div>
                </div>
                <div style="margin-top:8px">
                  <button class="btn">Save</button>
                  <button type="button" class="btn ghost" onclick="toggleEdit(<?=$hid?>)">Cancel</button>
                </div>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="pager">
        <?php if($page>1): ?>
          <a class="btn ghost" href="?search=<?=urlencode($search)?>&page=<?=$page-1?>">Prev</a>
        <?php endif; ?>
        <div class="small">Page <?=$page?> / <?=$pages?></div>
        <?php if($page<$pages): ?>
          <a class="btn ghost" href="?search=<?=urlencode($search)?>&page=<?=$page+1?>">Next</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="small">Note: Superadmin cannot access patient data. Approve only after verifying license & DLT status.</div>
</div>

<script>
function toggleEdit(id){
  const el = document.getElementById('editBox'+id);
  if(!el) return;
  el.style.display = (el.style.display==='none' || el.style.display==='') ? 'block' : 'none';
  el.scrollIntoView({behavior:'smooth', block:'center'});
}
</script>
</body>
</html>
