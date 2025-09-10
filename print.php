<?php
session_start();
$host = "localhost";
$user = "root";
$password = "";
$dbname = "miniproject";
$conn = new mysqli($host, $user, $password, $dbname);

if (!isset($_SESSION['temp_form'])) {
  header("Location: register.php");
  exit();
}

$form = $_SESSION['temp_form'];
$paid = isset($_SESSION['paid']);
$age = intval($form['age']);

if ($age >= 18 && !$paid) {
  header("Location: payment.php");
  exit();
}

// 1. Generate patient UID
$res = $conn->query("SELECT last_number FROM patient_counter WHERE id = 1 FOR UPDATE");
$row = $res->fetch_assoc();
$newNumber = $row['last_number'] + 1;
$patientUid = str_pad($newNumber, 6, '0', STR_PAD_LEFT);
$conn->query("UPDATE patient_counter SET last_number = $newNumber WHERE id = 1");

// 2. Get token
$tokenDate = date("Y-m-d");
$department = $form['department'];
$stmt = $conn->prepare("SELECT MAX(token) AS maxToken FROM patients WHERE department = ? AND token_date = ?");
$stmt->bind_param("ss", $department, $tokenDate);
$stmt->execute();
$resultQuery = $stmt->get_result();
$tempResult = $resultQuery->fetch_assoc();
$result = $tempResult;
$nextToken = ($result['maxToken'] ?? 0) + 1;
$stmt->close();

// 3. Insert into DB
$gender = ($form['gender'] === "Others") ? $form['specifyGender'] : $form['gender'];

$name = strtoupper(trim($form['name']));
$phone = $form['phone'];
$place = $form['place'];
$stmt = $conn->prepare("INSERT INTO patients (patient_uid, name, age, gender, phone, place, department, token, token_date)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssissssis",
  $patientUid, $name, $age, $gender,
  $phone, $place, $department, $nextToken, $tokenDate
);
$stmt->execute();

// 4. Clear session
unset($_SESSION['temp_form']);
unset($_SESSION['paid']);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Token Issued</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .print-btn {
      margin-top: 20px;
      padding: 10px 20px;
      background-color: #3498db;
      color: white;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }
    .print-btn:hover {
      background-color: #2980b9;
    }

    @media print {
      body {
        font-family: monospace;
        font-size: 12px;
      }
      .container {
        width: 58mm;
        padding: 0;
        margin: 0;
      }
      .header, .print-btn {
        display: none;
      }
      .result {
        padding: 0;
        margin: 0;
      }
      .result p {
        margin: 2px 0;
      }
    }
  </style>
</head>
<body onload="window.print()">
  <div class="container">
    <div class="header">
      <div class="logo">Medi<span>Co</span></div>
      <h2 class="title">Registration Complete</h2>
    </div>
    <div class="result" id="printSection">
      <h3>✅ Registration Successful</h3>
      <p><strong>Patient ID:</strong> <?= $patientUid ?></p>
      <p><strong>Name:</strong> <?= htmlspecialchars($form['name']) ?></p>
      <p><strong>Age:</strong> <?= $age ?></p>
      <p><strong>Gender:</strong> <?= htmlspecialchars($gender) ?></p>
      <p><strong>Phone:</strong> <?= htmlspecialchars($form['phone']) ?></p>
      <p><strong>Place:</strong> <?= htmlspecialchars($form['place']) ?></p>
      <p><strong>Department:</strong> <?= htmlspecialchars($department) ?></p>
      <p><strong>Token Number:</strong> <?= str_pad($nextToken, 2, '0', STR_PAD_LEFT) ?></p>
      <p><strong>Date:</strong> <?= $tokenDate ?></p>
      <button class="print-btn" onclick="window.print()">Print</button>
    </div>
  </div>
</body>
</html>