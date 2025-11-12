<?php
session_start();
error_reporting(0);

$conn = new mysqli("localhost", "root", "", "miniproject");
if ($conn->connect_error) die("DB Error");

if (!isset($_SESSION['admin_logged_in']) || !isset($_SESSION['admin_hospital_id'])) {
    header("Location: login.php");
    exit();
}

$hospital_id = $_SESSION['admin_hospital_id'];
$admin_username = $_SESSION['username'] ?? '';
$section = $_GET['section'] ?? 'dashboard';

// FETCH hospital name
$hospital = $conn->query("SELECT name FROM hospitals WHERE id=$hospital_id")->fetch_assoc()['name'] ?? "Hospital";

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Logout
    if (isset($_POST['logout'])) {
        session_destroy();
        header("Location: login.php");
        exit();
    }

    // Add department
    if (isset($_POST['add_department'])) {
        $dept = trim($_POST['department']);
        if ($dept) {
            $stmt = $conn->prepare("INSERT INTO departments (hospital_id, department_name, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("is", $hospital_id, $dept);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: admin.php?section=departments");
        exit();
    }

    // Delete department
    if (isset($_POST['delete_department'])) {
        $did = (int)$_POST['dept_id'];
        $stmt = $conn->prepare("DELETE FROM departments WHERE id=? AND hospital_id=?");
        $stmt->bind_param("ii", $did, $hospital_id);
        $stmt->execute();
        $stmt->close();
        header("Location: admin.php?section=departments");
        exit();
    }

    // Change password
    if (isset($_POST['change_password'])) {
        $old = $_POST['old_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        $stmt = $conn->prepare("SELECT id, password FROM admins WHERE username=? AND hospital_id=? LIMIT 1");
        $stmt->bind_param("si", $admin_username, $hospital_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$res || !password_verify($old, $res['password'])) {
            $msg = "Incorrect old password.";
        } elseif ($new !== $confirm) {
            $msg = "New passwords do not match.";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE admins SET password=? WHERE id=?");
            $upd->bind_param("si", $hash, $res['id']);
            $upd->execute();
            $upd->close();
            $msg = "Password updated successfully!";
        }
        header("Location: admin.php?section=change_password&msg=" . urlencode($msg));
        exit();
    }
}

// DEPARTMENTS
$departments = [];
$res = $conn->query("SELECT id, department_name, created_at FROM departments WHERE hospital_id=$hospital_id ORDER BY department_name");
while ($r = $res->fetch_assoc()) $departments[] = $r;

// Today’s Summary
$today = date("Y-m-d");
$total_today = $conn->query("SELECT COUNT(*) AS c FROM patients WHERE hospital_id=$hospital_id AND token_date='$today'")->fetch_assoc()['c'] ?? 0;
$dept_today = [];
$rs = $conn->query("SELECT department, COUNT(*) AS c FROM patients WHERE hospital_id=$hospital_id AND token_date='$today' GROUP BY department");
while ($r = $rs->fetch_assoc()) $dept_today[$r['department']] = $r['c'];

// Chart Data
$labels = [];
for ($i = 6; $i >= 0; $i--) $labels[] = date("Y-m-d", strtotime("-$i days"));
$chart_data = [];
$colors = ["#0078b7","#00bcd4","#ff9800","#4caf50","#9c27b0","#f44336"];
$q = $conn->prepare("SELECT department, token_date, COUNT(*) AS c FROM patients WHERE hospital_id=? AND token_date BETWEEN ? AND ? GROUP BY department, token_date");
$start = $labels[0]; $end = end($labels);
$q->bind_param("iss", $hospital_id, $start, $end);
$q->execute();
$res = $q->get_result();
while ($r = $res->fetch_assoc()) $chart_data[$r['department']][$r['token_date']] = $r['c'];
$q->close();
$datasets = [];
$ci = 0;
foreach ($chart_data as $d => $dt) {
    $vals = [];
    foreach ($labels as $l) $vals[] = $dt[$l] ?? 0;
    $datasets[] = ["label"=>$d,"borderColor"=>$colors[$ci++ % count($colors)],"fill"=>false,"data"=>$vals];
}

// Patients
$f_dept = $_GET['department'] ?? '';
$f_date = $_GET['date'] ?? '';
$sql = "SELECT * FROM patients WHERE hospital_id=?";
$params = [$hospital_id];
$types = "i";
if ($f_dept) {$sql .= " AND department=?"; $params[] = $f_dept; $types .= "s";}
if ($f_date) {$sql .= " AND token_date=?"; $params[] = $f_date; $types .= "s";}
$sql .= " ORDER BY token_date DESC, id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($hospital) ?> • Admin Panel</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body{font-family:'Segoe UI',sans-serif;background:#eaf3f8;margin:0;display:flex;}
.sidebar{width:230px;background:#fff;border-right:1px solid #ddd;display:flex;flex-direction:column;position:fixed;top:0;bottom:0;padding-top:20px;}
.logo{font-size:26px;font-weight:700;color:#0078b7;text-align:center;margin-bottom:30px;}
.logo span{color:#00bcd4;}
.nav a{display:block;padding:12px 20px;color:#444;text-decoration:none;border-radius:8px;margin:0 10px;}
.nav a:hover,.nav a.active{background:#00bcd4;color:#fff;}
.main{flex:1;margin-left:230px;padding:30px;}
.card{background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.05);padding:20px;margin-bottom:25px;}
h1{color:#0078b7;}
button{background:#00bcd4;color:#fff;border:none;padding:10px 14px;border-radius:8px;cursor:pointer;}
input,select{padding:10px;border:1px solid #ccc;border-radius:8px;}
table{width:100%;border-collapse:collapse;margin-top:10px;}
th,td{border-bottom:1px solid #eee;padding:10px;text-align:left;}
th{background:#f8f9fa;}
.small{font-size:13px;color:#777;}
</style>
</head>
<body>
<div class="sidebar">
  <div class="logo">Medi<span>Co</span></div>
  <nav class="nav">
    <a href="admin.php?section=dashboard" class="<?=($section=='dashboard')?'active':''?>"><i class="fa fa-chart-line"></i> Dashboard</a>
    <a href="admin.php?section=patients" class="<?=($section=='patients')?'active':''?>"><i class="fa fa-user-injured"></i> Patients</a>
    <a href="admin.php?section=departments" class="<?=($section=='departments')?'active':''?>"><i class="fa fa-building"></i> Departments</a>
    <a href="admin.php?section=change_password" class="<?=($section=='change_password')?'active':''?>"><i class="fa fa-key"></i> Change Password</a>
  </nav>
  <form method="POST" style="padding:10px 20px;margin-top:auto;"><button name="logout"><i class="fa fa-right-from-bracket"></i> Logout</button></form>
</div>

<div class="main">
<h1><?= htmlspecialchars($hospital) ?> Admin Panel</h1>

<!-- DASHBOARD -->
<?php if ($section=="dashboard"): ?>
<div class="card">
  <h3>Today Summary (<?= date("d M Y") ?>)</h3>
  <p>Total Patients: <b><?= $total_today ?></b></p>
  <?php foreach($dept_today as $d=>$c): ?><p><?= $d ?> — <b><?= $c ?></b></p><?php endforeach; ?>
  <canvas id="chart" height="100"></canvas>
  <script>
  new Chart(document.getElementById('chart'), {
    type:'line',
    data:{labels:<?=json_encode($labels)?>,datasets:<?=json_encode($datasets)?>},
    options:{scales:{y:{beginAtZero:true}}}
  });
  </script>
</div>
<?php endif; ?>

<!-- PATIENTS -->
<?php if ($section=="patients"): ?>
<div class="card">
  <h3>Patients</h3>
  <form method="GET" style="display:flex;gap:10px;margin-bottom:10px;">
    <input type="hidden" name="section" value="patients">
    <select name="department" onchange="this.form.submit()">
      <option value="">All Departments</option>
      <?php foreach($departments as $d): ?>
      <option value="<?=$d['department_name']?>" <?=($f_dept==$d['department_name'])?'selected':''?>><?=$d['department_name']?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date" value="<?=$f_date?>" onchange="this.form.submit()">
  </form>
  <table>
    <thead><tr><th>UID</th><th>Name</th><th>Age</th><th>Gender</th><th>Dept</th><th>Token</th><th>Date</th></tr></thead>
    <tbody>
      <?php if($patients): foreach($patients as $p): ?>
      <tr><td><?=$p['patient_uid']?></td><td><?=$p['name']?></td><td><?=$p['age']?></td><td><?=$p['gender']?></td><td><?=$p['department']?></td><td><?=$p['token']?></td><td><?=$p['token_date']?></td></tr>
      <?php endforeach; else: ?><tr><td colspan="7" class="small">No records found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- DEPARTMENTS -->
<?php if ($section=="departments"): ?>
<div class="card">
  <h3>Departments</h3>
  <form method="POST" style="display:flex;gap:10px;margin-bottom:10px;">
    <input type="text" name="department" placeholder="Enter department name" required>
    <button name="add_department"><i class="fa fa-plus"></i> Add</button>
  </form>
  <table>
    <thead><tr><th>Name</th><th>Total Patients</th><th>Today</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach($departments as $d): 
        $n=$d['department_name'];
        $total=$conn->query("SELECT COUNT(*) AS c FROM patients WHERE hospital_id=$hospital_id AND department='$n'")->fetch_assoc()['c'];
        $todayp=$conn->query("SELECT COUNT(*) AS c FROM patients WHERE hospital_id=$hospital_id AND department='$n' AND token_date='$today'")->fetch_assoc()['c'];
      ?>
      <tr><td><?=$n?></td><td><?=$total?></td><td><?=$todayp?></td>
        <td><form method="POST" onsubmit="return confirm('Delete this department?')">
          <input type="hidden" name="dept_id" value="<?=$d['id']?>"><button name="delete_department" style="background:#c62828;">Delete</button></form></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- CHANGE PASSWORD -->
<?php if ($section=="change_password"): ?>
<div class="card" style="max-width:500px;">
  <h3>Change Password</h3>
  <?php if(!empty($_GET['msg'])): ?><p class="small" style="color:#0078b7"><?=htmlspecialchars($_GET['msg'])?></p><?php endif; ?>
  <form method="POST" id="changePassForm" style="display:flex;flex-direction:column;gap:12px;">
    <input type="password" name="old_password" placeholder="Old Password" required>
    <input type="password" name="new_password" id="newPass" placeholder="New Password" required>
    <input type="password" name="confirm_password" id="confirmPass" placeholder="Confirm Password" required>
    <p id="note" class="small" style="color:#d9534f;display:none;">Passwords do not match</p>
    <button name="change_password" id="submitBtn" disabled style="opacity:0.6;">Update</button>
  </form>
</div>
<script>
const np=document.getElementById('newPass'),cp=document.getElementById('confirmPass'),note=document.getElementById('note'),btn=document.getElementById('submitBtn');
function check(){if(np.value&&cp.value&&np.value!==cp.value){note.style.display='block';btn.disabled=true;btn.style.opacity='0.6'}else if(np.value&&cp.value){note.style.display='none';btn.disabled=false;btn.style.opacity='1'}else{note.style.display='none';btn.disabled=true;btn.style.opacity='0.6'}}
np.addEventListener('input',check);cp.addEventListener('input',check);
</script>
<?php endif; ?>
</div>
</body>
</html>
