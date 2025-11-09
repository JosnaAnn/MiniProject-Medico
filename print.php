<?php
session_start();
$host = "localhost";
$user = "root";
$password = "";
$dbname = "miniproject";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) die("DB Error");

// ✅ Check session values
if (!isset($_SESSION['patient_id']) || !isset($_SESSION['hospital_id'])) {
    header("Location: register.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];
$hospital_id = $_SESSION['hospital_id'];

// ✅ Fetch patient details
$stmt = $conn->prepare("SELECT * FROM patients WHERE id=? AND hospital_id=?");
$stmt->bind_param("ii", $patient_id, $hospital_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    die("❌ Patient not found!");
}

// ✅ Payment check
if ($patient['age'] >= 18 && (!isset($_SESSION['paid']) || $_SESSION['paid'] == false)) {
    header("Location: payment.php");
    exit();
}

// ✅ Fetch hospital name
$stmt = $conn->prepare("SELECT name FROM hospitals WHERE id=?");
$stmt->bind_param("i", $hospital_id);
$stmt->execute();
$hospital_name = $stmt->get_result()->fetch_assoc()['name'] ?? "Hospital";
$stmt->close();

// ✅ Clear session
unset($_SESSION['patient_id']);
unset($_SESSION['hospital_id']);
unset($_SESSION['paid']);
unset($_SESSION['token']);
unset($_SESSION['patient_uid']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MediCo • Print Token</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',sans-serif;}
body{
  background:#eaf3f8;
  min-height:100vh;
  display:flex;
  justify-content:center;
  align-items:center;
}
.container{
  background:#fff;
  border-radius:16px;
  box-shadow:0 4px 24px rgba(0,0,0,0.1);
  width:90%;
  max-width:420px;
  padding:35px 40px;
  text-align:center;
  position:relative;
}
.logo{
  font-size:32px;
  font-weight:700;
  color:#0078b7;
  margin-bottom:8px;
}
.logo span{color:#00bcd4;}
.title{
  font-size:18px;
  color:#333;
  margin-bottom:20px;
  font-weight:500;
}
.ticket{
  background:#f9fcff;
  border:1px solid #d9e7f1;
  border-radius:12px;
  padding:20px;
  margin-bottom:20px;
  text-align:left;
}
.ticket p{
  font-size:15px;
  color:#444;
  margin:6px 0;
}
.token{
  text-align:center;
  font-size:22px;
  font-weight:700;
  color:#0078b7;
  margin-top:10px;
}
.date{
  text-align:center;
  font-size:14px;
  color:#777;
}
.print-btn{
  margin-top:20px;
  padding:12px 28px;
  background:linear-gradient(135deg,#00bcd4,#0097ff);
  color:white;
  border:none;
  border-radius:8px;
  font-size:15px;
  font-weight:600;
  cursor:pointer;
  transition:all 0.3s ease;
  box-shadow:0 4px 12px rgba(0,150,200,0.3);
}
.print-btn:hover{
  background:linear-gradient(135deg,#00a4c0,#007bff);
  box-shadow:0 6px 18px rgba(0,130,190,0.4);
  transform:translateY(-2px);
}
footer{
  text-align:center;
  color:#777;
  font-size:14px;
  margin-top:20px;
}

/* ✅ Print Mode (Thermal printer) */
@media print{
  body{font-family:monospace;font-size:13px;background:#fff;}
  .container{
    width:55mm;
    padding:0;
    margin:0;
    box-shadow:none;
    border-radius:0;
  }
  .logo,.title,.print-btn,footer,#successPopup{display:none;}
  .ticket{border:none;padding:0;}
  .token{font-size:22px;margin:5px 0;}
}

/* 🌟 Success Popup */
#successPopup{
  position:absolute;
  top:50%;
  left:50%;
  transform:translate(-50%,-50%);
  background:#fff;
  border:1px solid #d9e7f1;
  box-shadow:0 4px 20px rgba(0,0,0,0.15);
  border-radius:12px;
  padding:30px 40px;
  text-align:center;
  z-index:10;
  opacity:0;
  visibility:hidden;
  transition:opacity 0.4s ease,visibility 0.4s ease;
}
#successPopup.show{
  opacity:1;
  visibility:visible;
}
#successPopup i{
  font-size:40px;
  color:#00bcd4;
  margin-bottom:10px;
}
#successPopup h3{
  color:#333;
  font-size:18px;
  margin-bottom:5px;
}
#successPopup p{
  color:#555;
  font-size:14px;
}
</style>
</head>
<body onload="autoPrint()">

<div class="container">
  <div class="logo">Medi<span>Co</span></div>
  <h2 class="title">Patient Token Generated</h2>

  <div class="ticket">
    <p><strong>Hospital:</strong> <?= htmlspecialchars($hospital_name) ?></p>
    <p><strong>Patient ID:</strong> <?= htmlspecialchars($patient['patient_uid']) ?></p>
    <p><strong>Name:</strong> <?= htmlspecialchars($patient['name']) ?></p>
    <p><strong>Age:</strong> <?= htmlspecialchars($patient['age']) ?></p>
    <p><strong>Gender:</strong> <?= htmlspecialchars($patient['gender']) ?></p>
    <p><strong>Department:</strong> <?= htmlspecialchars($patient['department']) ?></p>
    <p class="token">Token: <?= str_pad($patient['token'], 2, "0", STR_PAD_LEFT) ?></p>
    <p class="date"><?= htmlspecialchars($patient['token_date']) ?></p>
  </div>

  <button class="print-btn" onclick="rePrint()"><i class="fa fa-print"></i> Re-Print</button>

  <footer>Window will close automatically after printing...</footer>

  <!-- ✅ Success Popup -->
  <div id="successPopup">
    <i class="fa fa-check-circle"></i>
    <h3>Token Printed Successfully</h3>
    <p>Redirecting to home...</p>
  </div>
</div>

<script>
function autoPrint() {
  setTimeout(() => {
    window.print();

    // Show popup after print
    setTimeout(() => {
      document.getElementById('successPopup').classList.add('show');
    }, 1000);

    // Redirect after 4 seconds
    setTimeout(() => {
      window.location.href = "index.php";
    }, 4000);
  }, 800);
}

function rePrint() {
  window.print();
}
</script>

</body>
</html>
