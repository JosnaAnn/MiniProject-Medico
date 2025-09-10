<?php
session_start();
$conn = new mysqli("localhost", "root", "", "miniproject");

// Logout Logic
if (isset($_POST['logout'])) {
  session_destroy();
  header("Location: index.php");
  exit();
}

// Ensure only superadmin can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
  die("Access denied.");
}

// Add Hospital + Admin
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_hospital_admin'])) {
  $hname = trim($_POST['hospital_name']);
  $location = trim($_POST['location']);
  $contact = trim($_POST['contact']);
  $username = trim($_POST['username']);
  $password = trim($_POST['password']);

  if ($hname && $location && $contact && $username && $password) {
    $stmt1 = $conn->prepare("INSERT INTO hospitals (name, location, contact) VALUES (?, ?, ?)");
    $stmt1->bind_param("sss", $hname, $location, $contact);
    $stmt1->execute();
    $hospital_id = $conn->insert_id;

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt2 = $conn->prepare("INSERT INTO users (username, password, role, hospital_id) VALUES (?, ?, 'admin', ?)");
    $stmt2->bind_param("ssi", $username, $hashedPassword, $hospital_id);
    $stmt2->execute();

    $success = "🏥 Hospital and admin added successfully!";
  } else {
    $error = "⚠️ All fields are required.";
  }
}

// Update Hospital
if (isset($_POST['update_hospital'])) {
  $id = intval($_POST['update_hospital_id']);
  $name = trim($_POST['hospital_name']);
  $location = trim($_POST['location']);
  $contact = trim($_POST['contact']);

  if ($name && $location && $contact) {
    $stmt = $conn->prepare("UPDATE hospitals SET name=?, location=?, contact=? WHERE id=?");
    $stmt->bind_param("sssi", $name, $location, $contact, $id);
    $stmt->execute();
    $success = "🏥 Hospital updated successfully!";
  } else {
    $error = "⚠️ All fields are required to update hospital.";
  }
}

// Update Admin
if (isset($_POST['update_admin'])) {
  $id = intval($_POST['update_admin_id']);
  $username = trim($_POST['username']);
  $password = trim($_POST['password']);

  if ($username) {
    if ($password) {
      $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("UPDATE users SET username=?, password=? WHERE id=? AND role='admin'");
      $stmt->bind_param("ssi", $username, $hashedPassword, $id);
    } else {
      $stmt = $conn->prepare("UPDATE users SET username=? WHERE id=? AND role='admin'");
      $stmt->bind_param("si", $username, $id);
    }
    $stmt->execute();
    $success = "👤 Admin updated successfully!";
  } else {
    $error = "⚠️ Username is required.";
  }
}

// Delete Admin
if (isset($_POST['delete_admin'])) {
  $adminId = $_POST['admin_id'];
  $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
  $stmt->bind_param("i", $adminId);
  $stmt->execute();
  $success = "🗑️ Admin removed successfully!";
}

// Delete Hospital
if (isset($_POST['delete_hospital'])) {
  $hospitalId = intval($_POST['hospital_id']);
  $check = $conn->query("SELECT id FROM users WHERE hospital_id = $hospitalId AND role = 'admin'");
  if ($check->num_rows > 0) {
    $error = "⚠️ Cannot delete hospital with assigned admins.";
  } else {
    $stmt = $conn->prepare("DELETE FROM hospitals WHERE id = ?");
    $stmt->bind_param("i", $hospitalId);
    $stmt->execute();
    $success = "🏥 Hospital removed successfully!";
  }
}

// Fetch data
$admins = $conn->query("
  SELECT users.id, users.username, hospitals.name AS hospital 
  FROM users 
  LEFT JOIN hospitals ON users.hospital_id = hospitals.id 
  WHERE users.role = 'admin'
");

$hospitals = $conn->query("SELECT * FROM hospitals ORDER BY registered_at DESC");
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
    .delete-btn:hover { background-color: #c0392b; }
    .logout-btn {
      background-color: #3498db;
      color: white;
      border: none;
      padding: 8px 14px;
      border-radius: 6px;
      cursor: pointer;
      float: right;
    }
    .logout-btn:hover { background-color: #2980b9; }
    .header { display: flex; justify-content: space-between; align-items: center; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    table, th, td { border: 1px solid #ddd; }
    th, td { padding: 10px; text-align: left; }
    thead { background-color: #f5f5f5; }
    .container { max-width: 1000px; margin: auto; padding: 20px; }
    .logo { font-size: 22px; font-weight: bold; }
    .logo span { color: #2ecc71; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo">Medi<span>Co</span></div>
      <h2 class="title">Superadmin Panel</h2>
      <form method="POST">
        <button type="submit" name="logout" class="logout-btn">Logout</button>
      </form>
    </div>

    <?php if (isset($success)): ?>
      <p class="message"><?= $success ?></p>
    <?php elseif (isset($error)): ?>
      <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <?php if (isset($_POST['edit_hospital'])): ?>
      <h3>Edit Hospital</h3>
      <form method="POST">
        <input type="hidden" name="update_hospital_id" value="<?= $_POST['edit_hospital_id'] ?>">
        <div class="form-group">
          <input type="text" name="hospital_name" value="<?= $_POST['hospital_name'] ?>" required>
        </div>
        <div class="form-group">
          <input type="text" name="location" value="<?= $_POST['location'] ?>" required>
        </div>
        <div class="form-group">
          <input type="text" name="contact" value="<?= $_POST['contact'] ?>" required>
        </div>
        <button type="submit" name="update_hospital">Update Hospital</button>
      </form>
    <?php elseif (isset($_POST['edit_admin'])): ?>
      <h3>Edit Admin</h3>
      <form method="POST">
        <input type="hidden" name="update_admin_id" value="<?= $_POST['edit_admin_id'] ?>">
        <div class="form-group">
          <input type="text" name="username" value="<?= $_POST['admin_username'] ?>" required>
        </div>
        <div class="form-group">
          <input type="password" name="password" placeholder="New Password (optional)">
        </div>
        <button type="submit" name="update_admin">Update Admin</button>
      </form>
    <?php else: ?>
      <!-- Add Hospital + Admin -->
      <h3>Add Hospital & Admin</h3>
      <form method="POST">
        <div class="form-group"><input type="text" name="hospital_name" placeholder="Hospital Name" required></div>
        <div class="form-group"><input type="text" name="location" placeholder="Hospital Location" required></div>
        <div class="form-group"><input type="text" name="contact" placeholder="Hospital Contact" required></div>
        <div class="form-group"><input type="text" name="username" placeholder="Admin Username" required></div>
        <div class="form-group"><input type="password" name="password" placeholder="Admin Password" required></div>
        <button type="submit" name="add_hospital_admin">Add Hospital & Admin</button>
      </form>
    <?php endif; ?>

    <!-- Admins -->
    <h3 style="margin-top: 40px;">Current Admins</h3>
    <table>
      <thead>
        <tr><th>#</th><th>Username</th><th>Hospital</th><th>Edit</th><th>Delete</th></tr>
      </thead>
      <tbody>
        <?php $i = 1; while ($row = $admins->fetch_assoc()): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($row['username']) ?></td>
            <td><?= htmlspecialchars($row['hospital'] ?? 'N/A') ?></td>
            <td>
              <form method="POST">
                <input type="hidden" name="edit_admin_id" value="<?= $row['id'] ?>">
                <input type="hidden" name="admin_username" value="<?= htmlspecialchars($row['username']) ?>">
                <button type="submit" name="edit_admin" class="delete-btn" style="background-color:#f39c12;">Edit</button>
              </form>
            </td>
            <td>
              <form method="POST" onsubmit="return confirm('Delete this admin?');">
                <input type="hidden" name="admin_id" value="<?= $row['id'] ?>">
                <button type="submit" name="delete_admin" class="delete-btn">Remove</button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

    <!-- Hospitals -->
    <h3 style="margin-top: 40px;">Registered Hospitals</h3>
    <table>
      <thead>
        <tr><th>#</th><th>Name</th><th>Location</th><th>Contact</th><th>Registered</th><th>Edit</th><th>Delete</th></tr>
      </thead>
      <tbody>
        <?php $j = 1; while ($row = $hospitals->fetch_assoc()): ?>
          <tr>
            <td><?= $j++ ?></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['location']) ?></td>
            <td><?= htmlspecialchars($row['contact']) ?></td>
            <td><?= date("d M Y, h:i A", strtotime($row['registered_at'])) ?></td>
            <td>
              <form method="POST">
                <input type="hidden" name="edit_hospital_id" value="<?= $row['id'] ?>">
                <input type="hidden" name="hospital_name" value="<?= htmlspecialchars($row['name']) ?>">
                <input type="hidden" name="location" value="<?= htmlspecialchars($row['location']) ?>">
                <input type="hidden" name="contact" value="<?= htmlspecialchars($row['contact']) ?>">
                <button type="submit" name="edit_hospital" class="delete-btn" style="background-color:#f39c12;">Edit</button>
              </form>
            </td>
            <td>
              <form method="POST" onsubmit="return confirm('Delete this hospital?');">
                <input type="hidden" name="hospital_id" value="<?= $row['id'] ?>">
                <button type="submit" name="delete_hospital" class="delete-btn">Delete</button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

  </div>
</body>
</html>
