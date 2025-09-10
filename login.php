<?php
session_start();
$host = "localhost";
$user = "root";
$password = "";
$dbname = "miniproject";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = $_POST['username'];
  $role = $_POST['role'];
  $password_input = $_POST['password'];

  if ($role === 'superadmin') {
    // Hardcoded superadmin credentials
    $superadmin_username = 'superadmin';
    $superadmin_password = 'super123'; // Change as needed

    if ($username !== $superadmin_username) {
      $error = "Wrong username for Super Admin!";
    } elseif ($password_input !== $superadmin_password) {
      $error = "Wrong password for Super Admin!";
    } else {
      $_SESSION['username'] = $superadmin_username;
      $_SESSION['role'] = 'superadmin';
      header("Location: superadmin.php");
      exit();
    }
  } else {
    // Admin login
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
      if (!password_verify($password_input, $user['password'])) {
        $error = "Wrong password for Admin!";
      } else {
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['hospital_id'] = $user['hospital_id']; // ✅ Add this line
        header("Location: admin.php");
        exit();
      }
    } else {
      $error = "Wrong username for Admin!";
    }
    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
    <style>
      .error {
        color: red;
        margin: 10px 0;
      }
    </style>
</head>
<body>
  <div class="login-box">
    <div class="logo">Medi<span>Co</span></div>
    <h2>Login</h2>
    <?php if (!empty($error)): ?>
      <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post">
      <input type="text" name="username" placeholder="Username" required />
      <input type="password" name="password" placeholder="Password" required />
      <select name="role" required>
        <option value="admin">Admin</option>
        <option value="superadmin">Super Admin</option>
      </select>
      <button type="submit">Login</button>
    </form>
  </div>
</body>
</html>
