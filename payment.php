<?php
session_start();
if (!isset($_SESSION['temp_form'])) {
  header("Location: register.php");
  exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $_SESSION['paid'] = true;
  header("Location: print.php");
  exit();
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Payment - OP Ticket</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .box {
      text-align: center;
      padding: 30px;
      background: #f9f9f9;
      border-radius: 10px;
      max-width: 400px;
      margin: 0 auto;
    }
    img.qr {
      width: 250px;
      height: 250px;
      margin-bottom: 20px;
    }
    .paid-btn {
      padding: 10px 20px;
      background-color: green;
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 16px;
      cursor: pointer;
    }
    .paid-btn:hover {
      background-color: darkgreen;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo">Medi<span>Co</span></div>
      <h2 class="title">Pay ₹5 OP Ticket Fee</h2>
    </div>
    <div class="box">
      <p>Please scan the QR code below to pay ₹5.</p>
      <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/4/41/QR_Code_Example.svg/1024px-QR_Code_Example.svg.png" alt="Scan to Pay" class="qr" />
      <form method="POST">
        <button type="submit" class="paid-btn">I Have Paid</button>
      </form>
    </div>
  </div>
</body>
</html>