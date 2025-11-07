<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !isset($_SESSION['admin_hospital_id'])) {
    header("Location: login.php"); exit();
}
$admin_username = $_SESSION['username'] ?? '';

$conn = new mysqli("localhost","root","","miniproject");
if ($conn->connect_error) die("DB Error");

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$current || !$new || !$confirm) {
        $message = 'All fields are required.';
    } elseif ($new !== $confirm) {
        $message = 'New passwords do not match.';
    } else {
        // fetch admin data
        $stmt = $conn->prepare("SELECT id,password FROM users WHERE username=? AND role='admin'");
        $stmt->bind_param("s", $admin_username);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($current, $user['password'])) {
            $message = 'Current password is incorrect.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $ustmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $ustmt->bind_param("si",$hash,$user['id']);
            $ustmt->execute();
            $ustmt->close();
            $message = 'Password updated successfully.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Change Password - Admin</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="logo">Medi<span>Co</span></div>
    <nav class="nav">
      <a href="admin.php"><i class="fa fa-house"></i> Dashboard</a>
      <a class="active" href="#"><i class="fa fa-key"></i> Change Password</a>
      <form method="POST" style="margin-top:12px;">
        <button name="logout" formaction="admin.php" class="btn ghost" style="width:100%;display:flex;align-items:center;gap:8px"><i class="fa fa-right-from-bracket"></i> Back</button>
      </form>
    </nav>
  </aside>

  <main class="main">
    <div class="header-row">
      <div>
        <div class="header-title">Change Password</div>
        <div class="small hint">Securely change your admin password</div>
      </div>
    </div>

    <div class="card" style="max-width:520px;">
      <?php if($message): ?><p class="hint"><?= htmlspecialchars($message) ?></p><?php endif; ?>
      <form method="POST">
        <div class="form-field"><label>Current Password</label><input type="password" name="current_password" required></div>
        <div class="form-field"><label>New Password</label><input type="password" name="new_password" required></div>
        <div class="form-field"><label>Confirm New Password</label><input type="password" name="confirm_password" required></div>
        <div style="display:flex;gap:8px">
          <button class="btn primary" type="submit">Change Password</button>
          <a href="admin.php" class="btn ghost" style="text-decoration:none;display:inline-flex;align-items:center">Cancel</a>
        </div>
      </form>
    </div>

  </main>
</div>
</body>
</html>
