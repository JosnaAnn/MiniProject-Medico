<?php
session_start();
$conn = new mysqli("localhost", "root", "", "miniproject");

// Ensure only superadmin can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
  die("Access denied.");
}

// Add Admin
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_admin'])) {
  $username = trim($_POST['username']);
  $password = trim($_POST['password']);

  if ($username && $password) {
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $success = "✅ New admin added!";
  } else {
    $error = "⚠️ All fields are required.";
  }
}

// Remove Admin
if (isset($_POST['delete_admin'])) {
  $adminId = $_POST['admin_id'];

  $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
  $stmt->bind_param("i", $adminId);
  $stmt->execute();
  $success = "🗑️ Admin removed successfully!";
}

// Get all admins
$admins = $conn->query("SELECT * FROM users WHERE role = 'admin'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Superadmin - MediCo</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .form-group { margin-bottom: 15px; }
    .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px; }
    .message { margin: 10px 0; color: green; }
    .error { margin: 10px 0; color: red; }
    .delete-btn {
      background-color: #e74c3c;
      color: white;
      border: none;
      padding: 6px 10px;
      border-radius: 6px;
      cursor: pointer;
    }
    .delete-btn:hover {
      background-color: #c0392b;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo">Medi<span>Co</span></div>
      <h2 class="title">Superadmin Panel</h2>
    </div>

    <h3>Add New Admin</h3>
    <form method="POST">
      <div class="form-group">
        <input type="text" name="username" placeholder="Admin Username" required>
      </div>
      <div class="form-group">
        <input type="password" name="password" placeholder="Admin Password" required>
      </div>
      <button type="submit" name="add_admin">Add Admin</button>
    </form>

    <?php if (isset($success)): ?>
      <p class="message"><?= $success ?></p>
    <?php elseif (isset($error)): ?>
      <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <h3 style="margin-top: 30px;">Current Admins</h3>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Username</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; while ($row = $admins->fetch_assoc()): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><?= htmlspecialchars($row['username']) ?></td>
              <td>
                <form method="POST" onsubmit="return confirm('Are you sure you want to remove this admin?');">
                  <input type="hidden" name="admin_id" value="<?= $row['id'] ?>">
                  <button type="submit" name="delete_admin" class="delete-btn">Remove</button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
