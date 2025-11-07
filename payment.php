<?php
session_start();
require('vendor/autoload.php');
require('config.php');

use Razorpay\Api\Api;

// ✅ Ensure the required session values exist
if (!isset($_SESSION['patient_id']) || !isset($_SESSION['hospital_id'])) {
    header("Location: register.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];
$hospital_id = $_SESSION['hospital_id'];

// ✅ Connect to database
$conn = new mysqli("localhost", "root", "", "miniproject");
if ($conn->connect_error) die("DB Connection Failed");

// ✅ Fetch hospital info
$stmt = $conn->prepare("SELECT name FROM hospitals WHERE id=?");
$stmt->bind_param("i", $hospital_id);
$stmt->execute();
$hospital = $stmt->get_result()->fetch_assoc();
$hospital_name = $hospital['name'] ?? "Unknown Hospital";
$stmt->close();

// ✅ Fetch patient info
$stmt = $conn->prepare("SELECT name, department FROM patients WHERE id=? AND hospital_id=?");
$stmt->bind_param("ii", $patient_id, $hospital_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    die("❌ Patient not found. Please register again.");
}

// ✅ Razorpay Setup
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
<html>
<head>
  <title>Payment - OP Ticket</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .box { text-align: center; padding: 30px; background: #f9f9f9; border-radius: 10px; max-width: 400px; margin: 40px auto; }
    .paid-btn { padding: 10px 20px; background-color: #008CFF; color: white; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; }
    .paid-btn:hover { background-color: #006fe0; }
    .title { text-align: center; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <a href="index.php"><div class="logo">Medi<span>Co</span></div></a>
      <h2 class="title">Pay ₹<?= $displayAmount ?> OP Ticket Fee - <?= htmlspecialchars($hospital_name) ?></h2>
    </div>

    <div class="box">
      <p><strong>Patient:</strong> <?= htmlspecialchars($patient['name']) ?></p>
      <p><strong>Department:</strong> <?= htmlspecialchars($patient['department']) ?></p>
      <p>Click below to pay ₹5 securely using Razorpay.</p>

      <!-- ✅ Razorpay Payment Button -->
      <form action="verify.php" method="POST">
        <script
          src="https://checkout.razorpay.com/v1/checkout.js"
          data-key="<?= RAZORPAY_KEY_ID ?>"
          data-amount="<?= $orderData['amount'] ?>"
          data-currency="<?= RAZORPAY_CURRENCY ?>"
          data-order_id="<?= $razorpayOrderId ?>"
          data-buttontext="Pay ₹<?= $displayAmount ?>"
          data-name="MediCo"
          data-description="OP Ticket Payment"
          data-image="https://upload.wikimedia.org/wikipedia/commons/1/1a/Razorpay_logo.svg"
          data-prefill.name="<?= htmlspecialchars($patient['name']) ?>"
          data-theme.color="#008CFF">
        </script>
        <input type="hidden" name="hidden" value="Hidden Element">
      </form>
    </div>
  </div>
</body>
</html>
