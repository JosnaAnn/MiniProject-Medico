<?php
session_start();
require('vendor/autoload.php');
require('config.php');

use Razorpay\Api\Api;

if (!isset($_SESSION['patient_id']) || !isset($_SESSION['hospital_id'])) {
    header("Location: register.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];
$hospital_id = $_SESSION['hospital_id'];

$conn = new mysqli("localhost", "root", "", "miniproject");
if ($conn->connect_error) die("DB Connection Failed");

$stmt = $conn->prepare("SELECT name FROM hospitals WHERE id=?");
$stmt->bind_param("i", $hospital_id);
$stmt->execute();
$hospital = $stmt->get_result()->fetch_assoc();
$hospital_name = $hospital['name'] ?? "Unknown Hospital";
$stmt->close();

$stmt = $conn->prepare("SELECT name, department FROM patients WHERE id=? AND hospital_id=?");
$stmt->bind_param("ii", $patient_id, $hospital_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) die("❌ Patient not found. Please register again.");

$api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
$orderData = [
    'receipt'         => 'rcpt_' . time(),
    'amount'          => 500, // ₹5 in paise
    'currency'        => RAZORPAY_CURRENCY,
    'payment_capture' => 1
];

$razorpayOrder = $api->order->create($orderData);
$razorpayOrderId = $razorpayOrder['id'];
$_SESSION['razorpay_order_id'] = $razorpayOrderId;
$displayAmount = $orderData['amount'] / 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MediCo • Payment</title>
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
  max-width:600px;
  padding:45px 55px;
  text-align:center;
}
.logo{
  font-size:36px;
  font-weight:700;
  color:#0078b7;
  margin-bottom:8px;
}
.logo span{color:#00bcd4;}
.title{
  font-size:22px;
  color:#333;
  font-weight:500;
  margin-bottom:25px;
}
.details{
  background:#f9fcff;
  border:1px solid #d9e7f1;
  border-radius:10px;
  padding:20px;
  text-align:left;
  margin-bottom:30px;
}
.details p{
  margin:8px 0;
  font-size:15px;
  color:#444;
}
.payment-box{
  background:#fdfdfd;
  border-radius:12px;
  padding:30px 20px;
  border:1px solid #e2e8f0;
}
.payment-box p{
  font-size:15px;
  color:#444;
  margin-bottom:20px;
}
.note{
  color:#777;
  font-size:14px;
  margin-top:12px;
}
footer{
  text-align:center;
  margin-top:25px;
  font-size:14px;
  color:#777;
}
footer a{
  color:#0078b7;
  text-decoration:none;
}
footer a:hover{text-decoration:underline;}

/* 🌟 Custom Pay Button */
.pay-btn {
  background: linear-gradient(135deg, #00bcd4, #0097ff);
  color: #fff;
  border: none;
  font-size: 18px;
  font-weight: 600;
  padding: 14px 60px;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 14px rgba(0, 150, 200, 0.3);
}
.pay-btn:hover {
  background: linear-gradient(135deg, #00a4c0, #007bff);
  box-shadow: 0 6px 18px rgba(0, 150, 200, 0.4);
  transform: translateY(-2px);
}
.pay-btn:focus {
  outline: none;
  box-shadow: 0 0 0 3px rgba(0, 188, 212, 0.4);
}
.secure {
  margin-top: 10px;
  font-size: 13px;
  color: #777;
}
.secure i {
  color: #00bcd4;
  margin-right: 4px;
}
</style>
</head>
<body>
  <div class="container">
    <a href="index.php" style="text-decoration:none;">
      <div class="logo">Medi<span>Co</span></div>
    </a>
    <h2 class="title">Pay ₹<?= $displayAmount ?> OP Ticket Fee</h2>

    <div class="details">
      <p><strong>Hospital:</strong> <?= htmlspecialchars($hospital_name) ?></p>
      <p><strong>Patient:</strong> <?= htmlspecialchars($patient['name']) ?></p>
      <p><strong>Department:</strong> <?= htmlspecialchars($patient['department']) ?></p>
    </div>

    <div class="payment-box">
      <p>Proceed to pay ₹5 securely using Razorpay.</p>

      <!-- 🌟 Custom Button Trigger -->
      <button class="pay-btn" id="payNowBtn">
        <i class="fa fa-lock"></i> Pay ₹<?= $displayAmount ?>
      </button>
      <p class="secure"><i class="fa fa-shield-halved"></i> Secure payment powered by Razorpay</p>

      <form id="razorForm" action="verify.php" method="POST" style="display:none;">
        <script
          src="https://checkout.razorpay.com/v1/checkout.js"
          data-key="<?= RAZORPAY_KEY_ID ?>"
          data-amount="<?= $orderData['amount'] ?>"
          data-currency="<?= RAZORPAY_CURRENCY ?>"
          data-order_id="<?= $razorpayOrderId ?>"
          data-name="MediCo"
          data-description="OP Ticket Payment"
          data-theme.color="#00bcd4">
        </script>
        <input type="hidden" name="hidden" value="Hidden Element">
      </form>

      <p class="note">After payment, your token will be generated automatically.</p>
    </div>

    <footer>
      <a href="register.php"><i class="fa fa-arrow-left"></i> Back to Registration</a>
    </footer>
  </div>

<script>
// 🌟 Custom Pay Button Trigger
document.getElementById('payNowBtn').addEventListener('click', function() {
  document.querySelector('.razorpay-payment-button').click();
});
</script>
</body>
</html>
