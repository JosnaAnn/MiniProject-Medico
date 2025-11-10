<?php
session_start();
$host = "localhost";
$user = "root";
$password = "";
$dbname = "miniproject";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) die("Database Error");

if (!isset($_SESSION['admin_logged_in']) || !isset($_SESSION['admin_hospital_id'])) {
    header("Location: login.php");
    exit();
}

$hospital_id = $_SESSION['admin_hospital_id'];
$admin_username = $_SESSION['username'] ?? "";

// FETCH HOSPITAL NAME
$hospital_name = "";
$stmt = $conn->prepare("SELECT name FROM hospitals WHERE id=?");
$stmt->bind_param("i", $hospital_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) $hospital_name = $row['name'];
$stmt->close();

// LOGOUT
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

/////////////////////////////
// ✅ TODAY SUMMARY SECTION
/////////////////////////////

$today = date("Y-m-d");
$stmt = $conn->prepare("SELECT COUNT(*) AS total_today FROM patients WHERE hospital_id=? AND token_date=?");
$stmt->bind_param("is", $hospital_id, $today);
$stmt->execute();
$total_today = $stmt->get_result()->fetch_assoc()['total_today'] ?? 0;
$stmt->close();

$dept_today = [];
$stmt = $conn->prepare("
    SELECT department, COUNT(*) AS count
    FROM patients
    WHERE hospital_id=? AND token_date=?
    GROUP BY department
");
$stmt->bind_param("is", $hospital_id, $today);
$stmt->execute();
$rs = $stmt->get_result();
while ($r = $rs->fetch_assoc()) {
    $dept_today[$r['department']] = $r['count'];
}
$stmt->close();

/////////////////////////////
// EXPORT FUNCTIONS
/////////////////////////////

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // EXPORT FILTERED
    if (isset($_POST['export_filtered'])) {
        $dept = $_POST['excel_department'] ?? '';
        if ($dept) {
            $stmt = $conn->prepare("SELECT * FROM patients WHERE hospital_id=? AND department=? ORDER BY id DESC");
            $stmt->bind_param("is", $hospital_id, $dept);
            $filename = "patients_{$dept}.xls";
        } else {
            $stmt = $conn->prepare("SELECT * FROM patients WHERE hospital_id=? ORDER BY id DESC");
            $stmt->bind_param("i", $hospital_id);
            $filename = "patients_all.xls";
        }
        $stmt->execute();
        $res = $stmt->get_result();

        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename={$filename}");
        echo "Patient UID\tName\tAge\tGender\tPhone\tPlace\tDepartment\tToken\tDate\n";
        while ($r = $res->fetch_assoc()) {
            echo "{$r['patient_uid']}\t{$r['name']}\t{$r['age']}\t{$r['gender']}\t{$r['phone']}\t{$r['place']}\t{$r['department']}\t{$r['token']}\t{$r['token_date']}\n";
        }
        exit();
    }

    // EXPORT ALL
    if (isset($_POST['export_all'])) {
        $stmt = $conn->prepare("SELECT * FROM patients WHERE hospital_id=? ORDER BY id DESC");
        $stmt->bind_param("i", $hospital_id);
        $stmt->execute();
        $res = $stmt->get_result();

        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=all_patients.xls");
        echo "Patient UID\tName\tAge\tGender\tPhone\tPlace\tDepartment\tToken\tDate\n";
        while ($r = $res->fetch_assoc()) {
            echo "{$r['patient_uid']}\t{$r['name']}\t{$r['age']}\t{$r['gender']}\t{$r['phone']}\t{$r['place']}\t{$r['department']}\t{$r['token']}\t{$r['token_date']}\n";
        }
        exit();
    }

    // EXPORT SUMMARY
    if (isset($_POST['export_summary'])) {
        $stmt = $conn->prepare("
            SELECT DATE(token_date) AS day, department, COUNT(*) AS count
            FROM patients
            WHERE hospital_id=?
            GROUP BY day, department
            ORDER BY day DESC
        ");
        $stmt->bind_param("i", $hospital_id);
        $stmt->execute();
        $res = $stmt->get_result();

        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=department_summary.xls");
        echo "Date\tDepartment\tRegistrations\n";
        while ($r = $res->fetch_assoc()) {
            echo "{$r['day']}\t{$r['department']}\t{$r['count']}\n";
        }
        exit();
    }
}

/////////////////////////////
// FETCH DEPARTMENTS
/////////////////////////////

$departments = [];
$dq = $conn->prepare("SELECT DISTINCT department FROM patients WHERE hospital_id=?");
$dq->bind_param("i", $hospital_id);
$dq->execute();
$dres = $dq->get_result();
while ($d = $dres->fetch_assoc()) $departments[] = $d['department'];
$dq->close();

/////////////////////////////
// FETCH PATIENTS TABLE
/////////////////////////////

$filter = $_GET['department'] ?? '';
$sql = "SELECT * FROM patients WHERE hospital_id=?";
$params = [$hospital_id];
$types = "i";

if ($filter != "") {
    $sql .= " AND department=?";
    $params[] = $filter;
    $types .= "s";
}

$sql .= " ORDER BY token_date DESC";
$pstmt = $conn->prepare($sql);
$pstmt->bind_param($types, ...$params);
$pstmt->execute();
$patients = $pstmt->get_result();

/////////////////////////////
// DEPT SUMMARY TABLE
/////////////////////////////

$summary = [];
$q = $conn->prepare("
    SELECT DATE(token_date) AS day, department, COUNT(*) AS count
    FROM patients
    WHERE hospital_id=?
    GROUP BY day, department
    ORDER BY day DESC
");
$q->bind_param("i", $hospital_id);
$q->execute();
$res = $q->get_result();
while ($r = $res->fetch_assoc()) {
    $summary[$r['day']][$r['department']] = $r['count'];
}
$q->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($hospital_name) ?> • Admin Panel</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ✅ SAME ORIGINAL UI — DO NOT EDIT */
*{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',sans-serif;}
body{background:#eaf3f8;display:flex;min-height:100vh;}
.sidebar{
  width:230px;background:#fff;border-right:1px solid #ddd;
  display:flex;flex-direction:column;
  position:fixed;top:0;bottom:0;left:0;padding-top:20px;
}
.logo{font-size:26px;font-weight:700;text-align:center;margin-bottom:30px;color:#0078b7;}
.logo span{color:#00bcd4;}
.nav a{
  display:flex;align-items:center;gap:10px;padding:12px 20px;color:#555;text-decoration:none;
  transition:0.2s;font-size:15px;
}
.nav a:hover,.nav a.active{
  background:#00bcd4;color:#fff;border-radius:8px;margin:0 10px;
}
.main{flex:1;margin-left:230px;padding:30px;}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;}
.header h1{font-size:22px;color:#0078b7;}
button{
  background:#00bcd4;color:#fff;border:none;padding:10px 16px;border-radius:8px;cursor:pointer;font-size:14px;
}
button:hover{background:#009bb0;}
.card{background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.05);padding:20px;margin-bottom:25px;}
h2{font-size:18px;color:#0078b7;margin-bottom:15px;}
form{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:15px;}
input,select{padding:10px;border:1px solid #ccc;border-radius:8px;font-size:14px;flex:1;min-width:150px;}
table{width:100%;border-collapse:collapse;margin-top:10px;}
th,td{padding:10px 12px;border-bottom:1px solid #eee;text-align:left;}
th{background:#f8f9fa;color:#333;}
tr:hover{background:#f2faff;}
footer{text-align:center;font-size:12px;color:#777;margin-top:20px;}
.hidden{display:none;}
.no-records{text-align:center;color:#777;padding:20px;}
</style>
</head>
<body>

<div class="sidebar">
  <a href="index.php" style="text-decoration:none;"><div class="logo">Medi<span>Co</span></div></a>
  <nav class="nav">
    <a href="#" class="nav-link active" data-section="dashboard"><i class="fa fa-house"></i> Dashboard</a>
    <a href="#" class="nav-link" data-section="patients"><i class="fa fa-user-injured"></i> Patients</a>
    <a href="#" class="nav-link" data-section="departments"><i class="fa fa-chart-column"></i> Departments</a>
    <a href="change_password.php" class="nav-link"><i class="fa fa-key"></i> Change Password</a>
  </nav>
  <form method="POST" style="margin-top:auto;padding:10px 20px;">
    <button name="logout"><i class="fa fa-right-from-bracket"></i> Logout</button>
  </form>
</div>

<div class="main">
  <div class="header">
    <h1><?= htmlspecialchars($hospital_name) ?> Admin Panel</h1>
  </div>

  <!-- ✅ Dashboard -->
  <section id="dashboard" class="section">

    <!-- ✅ SUMMARY CARDS -->
    <div class="card">
      <div style="display:flex;gap:20px;flex-wrap:wrap;">
        
        <div style="background:#e9f8ff;padding:15px 20px;border-radius:12px;min-width:160px;">
          <h3 style="font-size:15px;color:#0078b7;margin-bottom:5px;">Today Total</h3>
          <div style="font-size:22px;font-weight:600;"><?= $total_today ?></div>
        </div>

        <?php foreach ($dept_today as $d => $count): ?>
        <div style="background:#f9fcff;padding:15px 20px;border-radius:12px;min-width:160px;">
          <h3 style="font-size:15px;color:#0078b7;margin-bottom:5px;"><?= htmlspecialchars($d) ?></h3>
          <div style="font-size:22px;font-weight:600;"><?= $count ?></div>
        </div>
        <?php endforeach; ?>

      </div>
    </div>

    <div class="card">
      <h2>All Patients Overview</h2>
      <form method="POST">
        <button name="export_all"><i class="fa fa-download"></i> Export All Data</button>
      </form>
      <table>
        <thead>
          <tr><th>UID</th><th>Name</th><th>Age</th><th>Gender</th><th>Phone</th>
              <th>Place</th><th>Dept</th><th>Token</th><th>Date</th></tr>
        </thead>
        <tbody>
        <?php if ($patients->num_rows > 0): foreach($patients as $r): ?>
          <tr>
            <td><?= $r['patient_uid'] ?></td>
            <td><?= $r['name'] ?></td>
            <td><?= $r['age'] ?></td>
            <td><?= $r['gender'] ?></td>
            <td><?= $r['phone'] ?></td>
            <td><?= $r['place'] ?></td>
            <td><?= $r['department'] ?></td>
            <td><?= str_pad($r['token'],2,"0",STR_PAD_LEFT) ?></td>
            <td><?= $r['token_date'] ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="9" class="no-records">No records available.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

  </section>

  <!-- ✅ Patients -->
  <section id="patients" class="section hidden">
    <div class="card">
      <h2>Filter by Department</h2>

      <form method="GET">
        <input type="hidden" name="section" value="patients">
        <select name="department">
          <option value="">All Departments</option>
          <?php foreach($departments as $dep): ?>
          <option value="<?= $dep ?>" <?= ($filter==$dep)?'selected':'' ?>><?= $dep ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit"><i class="fa fa-filter"></i> Apply</button>
      </form>

      <form method="POST">
        <input type="hidden" name="excel_department" value="<?= htmlspecialchars($filter) ?>">
        <button name="export_filtered"><i class="fa fa-download"></i> Export Filtered</button>
      </form>

      <table>
        <thead>
          <tr><th>UID</th><th>Name</th><th>Age</th><th>Gender</th><th>Phone</th><th>Place</th><th>Dept</th><th>Token</th><th>Date</th></tr>
        </thead>
        <tbody>
        <?php if ($patients->num_rows > 0): foreach($patients as $r): ?>
          <tr>
            <td><?= $r['patient_uid'] ?></td>
            <td><?= $r['name'] ?></td>
            <td><?= $r['age'] ?></td>
            <td><?= $r['gender'] ?></td>
            <td><?= $r['phone'] ?></td>
            <td><?= $r['place'] ?></td>
            <td><?= $r['department'] ?></td>
            <td><?= str_pad($r['token'],2,"0",STR_PAD_LEFT) ?></td>
            <td><?= $r['token_date'] ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="9" class="no-records">No patients found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- ✅ Departments -->
  <section id="departments" class="section hidden">
    <div class="card">
      <h2>Daily Department Summary</h2>
      <form method="POST">
        <button name="export_summary"><i class="fa fa-download"></i> Export Summary</button>
      </form>

      <table>
        <thead>
          <tr>
            <th>Date</th>
            <?php foreach ($departments as $dep): ?><th><?= $dep ?></th><?php endforeach; ?>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($summary)): ?>
          <tr><td colspan="<?= count($departments)+2 ?>" class="no-records">No data available.</td></tr>
        <?php else: ?>
          <?php foreach($summary as $day => $depts): ?>
            <tr>
              <td><?= $day ?></td>
              <?php
                $total = 0;
                foreach($departments as $dep):
                    $count = $depts[$dep] ?? 0;
                    $total += $count;
              ?>
                <td><?= $amount = $count ?></td>
              <?php endforeach; ?>
              <td><strong><?= $total ?></strong></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>

    </div>
  </section>

  <footer>© <?= date("Y") ?> MediCo Admin Panel</footer>

</div>

<script>
// ✅ Sidebar Menu Switching (fixed)
document.querySelectorAll(".nav-link").forEach(link => {
    link.addEventListener("click", function(e){
        e.preventDefault();

        // Change Password direct link
        if (link.getAttribute("href") && link.getAttribute("href") !== "#") {
            window.location.href = link.getAttribute("href");
            return;
        }

        const target = this.dataset.section;

        // Force reload for Patients to enable filter
        if (target === "patients") {
            window.location.href = "admin.php?section=patients";
            return;
        }

        document.querySelectorAll(".nav-link").forEach(a => a.classList.remove("active"));
        this.classList.add("active");

        document.querySelectorAll(".section").forEach(sec => sec.classList.add("hidden"));
        document.getElementById(target).classList.remove("hidden");
    });
});

// ✅ Auto-open section after reload
window.addEventListener("load", () => {
    const urlParams = new URLSearchParams(window.location.search);
    const section = urlParams.get("section");

    if (section && document.getElementById(section)) {
        document.querySelectorAll(".nav-link").forEach(a => a.classList.remove("active"));
        const link = document.querySelector(`[data-section='${section}']`);
        if (link) link.classList.add("active");

        document.querySelectorAll(".section").forEach(sec => sec.classList.add("hidden"));
        document.getElementById(section).classList.remove("hidden");
    }
});
</script>

</body>
</html>
