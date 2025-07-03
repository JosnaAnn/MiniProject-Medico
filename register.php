<?php
$host = "localhost";
$user = "root";
$password = "";
$dbname = "miniproject";
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
  die("❌ Connection failed: " . $conn->connect_error);
}

$submitted = false;
$patientUid = $token = $department = "";
$tokenDate = date("Y-m-d");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  // 1. Generate new patient UID
  $res = $conn->query("SELECT last_number FROM patient_counter WHERE id = 1 FOR UPDATE");
  $row = $res->fetch_assoc();
  $newNumber = $row['last_number'] + 1;
  $patientUid = str_pad($newNumber, 6, '0', STR_PAD_LEFT);
  $conn->query("UPDATE patient_counter SET last_number = $newNumber WHERE id = 1");

  // 2. Read form
  $name = strtoupper(trim($_POST['name']));
  $age = intval($_POST['age']);
  $gender = $_POST['gender'] === "Others" ? $_POST['specifyGender'] : $_POST['gender'];
  $phone = trim($_POST['phone']);
  $place = trim($_POST['place']);
  $department = $_POST['department'];

  // 3. Get next token for department and date
  $stmt = $conn->prepare("SELECT MAX(token) AS maxToken FROM patients WHERE department = ? AND token_date = ?");
  $stmt->bind_param("ss", $department, $tokenDate);
  $stmt->execute();
  $result = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $nextToken = ($result['maxToken'] ?? 0) + 1;
  $token = str_pad($nextToken, 2, '0', STR_PAD_LEFT);

  // 4. Insert into DB
  $stmt = $conn->prepare("INSERT INTO patients (patient_uid, name, age, gender, phone, place, department, token, token_date)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("ssissssis", $patientUid, $name, $age, $gender, $phone, $place, $department, $nextToken, $tokenDate);
  if ($stmt->execute()) {
    $submitted = true;
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Patient Registration</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
  <div class="header">
    <div class="logo">Medi<span>Co</span></div>
    <h2 class="title">Patient Details</h2>
  </div>
  <div class="box">
    <?php if (!$submitted): ?>
      <h2 class="to">Get Your Token</h2>
      <form method="POST">
        <label>Name:</label>
        <input type="text" name="name" required>

        <label>Age:</label>
        <input type="number" name="age" required>

        <label>Gender:</label>
        <select name="gender" id="genderSelect" required>
          <option value="">-- Select --</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
          <option value="Others">Others</option>
        </select>

        <div id="specifyBox" style="display:none;">
          <label>Specify Gender:</label>
          <input type="text" name="specifyGender">
        </div>

        <label>Phone:</label>
        <input type="tel" name="phone" pattern="[0-9]{10}" required>

        <label>Place:</label>
        <input type="text" name="place" required>

        <label>Department:</label>
        <select name="department" required>
          <option value="">-- Select --</option>
          <option value="Cardiology">Cardiology</option>
          <option value="Neurology">Neurology</option>
          <option value="Orthopedics">Orthopedics</option>
        </select>

        <button type="submit">Get Token</button>
      </form>
    </div>
      
    <?php else: ?>
      <div class="result">
        <h3>✅ Registration Successful</h3>
        <p><strong>Patient ID:</strong> <?= $patientUid ?></p>
        <p><strong>Name:</strong> <?=$name ?></p>
        <p><strong>Age:</strong> <?= $age ?></p>
        <p><strong>Gender:</strong> <?=$gender ?></p>
        <p><strong>Phone:</strong> <?= $phone ?></p>
        <p><strong>Place:</strong> <?= $place ?></p>
        <p><strong>Department:</strong> <?= $department ?></p>
        <p><strong>Token Number:</strong> <?= $token ?></p>
        <p><strong>Date:</strong> <?= $tokenDate ?></p>
      </div>
    <?php endif; ?>
  </div>

  <script>
    document.getElementById("genderSelect").addEventListener("change", function () {
      document.getElementById("specifyBox").style.display = this.value === "Others" ? "block" : "none";
    });
  </script>
</body>
</html>
