<?php
session_start();
$host = "localhost";
$user = "root";
$password = "";
$dbname = "miniproject";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) die("DB Error");

// ✅ Check required session values
if (!isset($_SESSION['patient_id']) || !isset($_SESSION['hospital_id'])) {
    header("Location: register.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];
$hospital_id = $_SESSION['hospital_id'];

// ✅ Fetch patient details from DB
$stmt = $conn->prepare("SELECT * FROM patients WHERE id=? AND hospital_id=?");
$stmt->bind_param("ii", $patient_id, $hospital_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    die("❌ Patient not found!");
}

// ✅ Payment check for age ≥ 18
if ($patient['age'] >= 18 && (!isset($_SESSION['paid']) || $_SESSION['paid'] == false)) {
    header("Location: payment.php");
    exit();
}

// ✅ Fetch Hospital Name
$stmt = $conn->prepare("SELECT name FROM hospitals WHERE id=?");
$stmt->bind_param("i", $hospital_id);
$stmt->execute();
$hospital_name = $stmt->get_result()->fetch_assoc()['name'];

// ✅ Clear session after printing only patient form data
unset($_SESSION['patient_id']);
unset($_SESSION['temp_form']);
unset($_SESSION['paid']);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Print Token</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .print-btn { margin-top: 10px; padding: 8px 14px; background:#3498db; color:white; border:none; border-radius:6px; cursor:pointer; }
        @media print {
            body { font-family: monospace; font-size: 13px; }
            .container { width: 55mm; padding: 0; margin: 0; }
            .print-btn, .header { display: none; }
        }
        .ticket { text-align: center; }
        .ticket p { margin: 3px 0; }
        .big-token { font-size: 22px; font-weight: bold; }
    </style>
</head>
<body onload="window.print()">

<div class="container">
    <div class="ticket">
        <h3><?= htmlspecialchars($hospital_name) ?></h3>

        <p><strong>Patient ID:</strong> <?= htmlspecialchars($patient['patient_uid']) ?></p>
        <p><strong>Name:</strong> <?= htmlspecialchars($patient['name']) ?></p>
        <p><strong>Age:</strong> <?= $patient['age'] ?></p>
        <p><strong>Gender:</strong> <?= htmlspecialchars($patient['gender']) ?></p>
        <p><strong>Dept:</strong> <?= htmlspecialchars($patient['department']) ?></p>

        <p class="big-token">Token: <?= str_pad($patient['token'], 2, "0", STR_PAD_LEFT) ?></p>
        <p><?= $patient['token_date'] ?></p>

        <button class="print-btn" onclick="window.print()">Re-Print</button>
    </div>
</div>

</body>
</html>
