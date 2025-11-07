<?php
// admin.php (modern UI)
error_reporting(E_ALL);
ini_set('display_errors',1);
session_start();
if (!isset($_SESSION['admin_logged_in']) || !isset($_SESSION['admin_hospital_id'])) {
    header("Location: login.php"); exit();
}
$hospital_id = $_SESSION['admin_hospital_id'];

$conn = new mysqli("localhost","root","", "miniproject");
if ($conn->connect_error) die("DB Error");

// handle logout or export
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['logout'])) {
        session_destroy(); header("Location: login.php"); exit();
    }
    if (isset($_POST['export_excel'])) {
        $dept = $_POST['excel_department'] ?? '';
        if ($dept) {
            $stmt = $conn->prepare("SELECT * FROM patients WHERE hospital_id=? AND department=? ORDER BY id DESC");
            $stmt->bind_param("is",$hospital_id,$dept);
            $filename = "patients_{$dept}.xls";
        } else {
            $stmt = $conn->prepare("SELECT * FROM patients WHERE hospital_id=? ORDER BY id DESC");
            $stmt->bind_param("i",$hospital_id);
            $filename = "patients_all.xls";
        }
        $stmt->execute();
        $res = $stmt->get_result();
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename={$filename}");
        echo "Patient UID\tName\tAge\tGender\tPhone\tPlace\tDepartment\tToken\tDate\n";
        while($r=$res->fetch_assoc()){
            echo "{$r['patient_uid']}\t{$r['name']}\t{$r['age']}\t{$r['gender']}\t{$r['phone']}\t{$r['place']}\t{$r['department']}\t{$r['token']}\t{$r['token_date']}\n";
        }
        exit();
    }
}

// get hospital name
$stmt = $conn->prepare("SELECT name FROM hospitals WHERE id=?");
$stmt->bind_param("i",$hospital_id);
$stmt->execute();
$hospital_name = $stmt->get_result()->fetch_assoc()['name'] ?? 'Hospital';
$stmt->close();

// department list (dynamic from patients for this hospital)
$departments = [];
$dstmt = $conn->prepare("SELECT DISTINCT department FROM patients WHERE hospital_id=?");
$dstmt->bind_param("i",$hospital_id);
$dstmt->execute();
$dres = $dstmt->get_result();
while($d=$dres->fetch_assoc()) $departments[] = $d['department'];
$dstmt->close();

// filter by GET
$departmentFilter = $_GET['department'] ?? '';

// fetch patients
if ($departmentFilter) {
    $pstmt = $conn->prepare("SELECT * FROM patients WHERE hospital_id=? AND department=? ORDER BY id DESC");
    $pstmt->bind_param("is",$hospital_id,$departmentFilter);
} else {
    $pstmt = $conn->prepare("SELECT * FROM patients WHERE hospital_id=? ORDER BY id DESC");
    $pstmt->bind_param("i",$hospital_id);
}
$pstmt->execute();
$patients = $pstmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin • <?= htmlspecialchars($hospital_name) ?></title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="logo">Medi<span>Co</span></div>
    <nav class="nav">
      <a class="active" href="#"><i class="fa fa-house"></i> Dashboard</a>
      <a href="#"><i class="fa fa-user-injured"></i> Patients</a>
      <a href="#"><i class="fa fa-list"></i> Departments</a>
      <a href="change_password.php"><i class="fa fa-key"></i> Change Password</a>
      <form method="POST" style="margin-top:12px;">
        <button name="logout" class="btn ghost" style="width:100%;display:flex;align-items:center;gap:8px"><i class="fa fa-right-from-bracket"></i> Logout</button>
      </form>
    </nav>
  </aside>

  <main class="main">
    <div class="header-row">
      <div>
        <div class="header-title"><?= htmlspecialchars($hospital_name) ?> - Patients</div>
        <div class="small hint">Manage your hospital patient records and exports</div>
      </div>
      <div class="controls">
        <form method="GET">
          <select name="department" class="select">
            <option value="">All Departments</option>
            <?php foreach($departments as $dep): ?>
              <option value="<?= htmlspecialchars($dep) ?>" <?= ($departmentFilter===$dep)?'selected':'' ?>><?= htmlspecialchars($dep) ?></option>
            <?php endforeach; ?>
            <!-- fallback fixed options -->
            <option value="Cardiology" <?= $departmentFilter==='Cardiology'?'selected':'' ?>>Cardiology</option>
            <option value="Neurology" <?= $departmentFilter==='Neurology'?'selected':'' ?>>Neurology</option>
            <option value="Orthopedics" <?= $departmentFilter==='Orthopedics'?'selected':'' ?>>Orthopedics</option>
          </select>
          <button class="btn ghost" type="submit" style="margin-left:8px">Filter</button>
        </form>

        <form method="POST" style="margin-left:12px;">
          <input type="hidden" name="excel_department" value="<?= htmlspecialchars($departmentFilter) ?>">
          <button name="export_excel" class="btn primary"><i class="fa fa-download"></i>&nbsp;Export</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>UID</th><th>Name</th><th>Age</th><th>Gender</th><th>Phone</th><th>Place</th><th>Dept</th><th>Token</th><th>Date</th></tr>
          </thead>
          <tbody>
            <?php while($r = $patients->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($r['patient_uid']) ?></td>
                <td><?= htmlspecialchars($r['name']) ?></td>
                <td><?= htmlspecialchars($r['age']) ?></td>
                <td><?= htmlspecialchars($r['gender']) ?></td>
                <td><?= htmlspecialchars($r['phone']) ?></td>
                <td><?= htmlspecialchars($r['place']) ?></td>
                <td><?= htmlspecialchars($r['department']) ?></td>
                <td><?= htmlspecialchars(str_pad($r['token'],2,"0",STR_PAD_LEFT)) ?></td>
                <td><?= htmlspecialchars($r['token_date']) ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<script>
  // Mobile sidebar toggle
  document.addEventListener('keydown', e=>{
    if(e.key==='m'){ document.getElementById('sidebar').classList.toggle('open'); }
  });
</script>
</body>
</html>
