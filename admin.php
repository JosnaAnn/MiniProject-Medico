<?php
session_start();

// Logout Logic
if (isset($_POST['logout'])) {
  session_destroy();
  header("Location: index.php");
  exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  die("Access denied.");
}

$conn = new mysqli("localhost", "root", "", "miniproject");
$result = $conn->query("SELECT * FROM patients ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin - Patient Records</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .logout-btn {
      background-color: #e74c3c;
      color: white;
      border: none;
      padding: 8px 14px;
      border-radius: 6px;
      cursor: pointer;
      margin-left: auto;
    }
    .logout-btn:hover {
      background-color: #c0392b;
    }
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
  </style>
</head>
<body class="admin-body">
  <div class="container-admin">
    <div class="header">
      <div class="logo">Med<span>Co</span></div>
      <h2 class="title">All Patient Records</h2>
      <form method="POST">
        <button type="submit" name="logout" class="logout-btn">Logout</button>
      </form>
    </div>

    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Patient ID</th>
            <th>Name</th>
            <th>Age</th>
            <th>Gender</th>
            <th>Phone</th>
            <th>Place</th>
            <th>Department</th>
            <th>Token</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['patient_uid']) ?></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['age']) ?></td>
            <td><?= htmlspecialchars($row['gender']) ?></td>
            <td><?= htmlspecialchars($row['phone']) ?></td>
            <td><?= htmlspecialchars($row['place']) ?></td>
            <td><?= htmlspecialchars($row['department']) ?></td>
            <td><?= htmlspecialchars($row['token']) ?></td>
            <td><?= htmlspecialchars($row['token_date']) ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
