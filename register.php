<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $_SESSION['temp_form'] = $_POST;
  $age = intval($_POST['age']);

  if ($age >= 18) {
    header("Location: payment.php");
  } else {
    header("Location: print.php");
  }
  exit();
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

        <button type="submit">Next</button>
      </form>
    </div>
  </div>

  <script>
    document.getElementById("genderSelect").addEventListener("change", function () {
      document.getElementById("specifyBox").style.display = this.value === "Others" ? "block" : "none";
    });
  </script>
</body>
</html>
