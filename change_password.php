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
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MediCo • Change Password</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ✅ MATCHING ADMIN UI */
*{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',sans-serif;}
body{background:#eaf3f8;display:flex;min-height:100vh;}

.sidebar{
  width:230px;background:#fff;border-right:1px solid #ddd;
  display:flex;flex-direction:column;position:fixed;
  top:0;bottom:0;left:0;padding-top:20px;
}
.logo{font-size:26px;font-weight:700;text-align:center;margin-bottom:30px;color:#0078b7;}
.logo span{color:#00bcd4;}

.nav a{
  display:flex;align-items:center;gap:10px;padding:12px 20px;
  color:#555;text-decoration:none;font-size:15px;
  transition:0.2s;
}
.nav a:hover,.nav a.active{
  background:#00bcd4;color:#fff;border-radius:8px;margin:0 10px;
}

.main{
  flex:1;
  margin-left:230px;
  padding:30px;
}

.header-title{
  font-size:22px;
  font-weight:600;
  color:#0078b7;
}

.card{
  background:#fff;
  padding:25px;
  border-radius:12px;
  box-shadow:0 2px 8px rgba(0,0,0,0.05);
  margin-top:20px;
}

.form-field{margin-bottom:18px;}
.form-field label{
  display:block;
  font-size:14px;
  margin-bottom:6px;
  color:#0078b7;
}
.form-field input{
  width:100%;
  padding:10px;
  font-size:14px;
  border-radius:8px;
  border:1px solid #ccc;
}

.btn{
  padding:10px 18px;
  border:none;
  border-radius:8px;
  cursor:pointer;
  font-size:14px;
}
.primary{
  background:#00bcd4;color:#fff;
}
.primary:hover{
  background:#009bb0;
}
.ghost{
  background:#fff;color:#0078b7;border:1px solid #cce8f3;
}
.hint{
  color:#0078b7;
  margin-bottom:10px;
  font-size:14px;
}
</style>

</head>
<body>

<div class="sidebar">
  <a href="index.php" style="text-decoration:none;"><div class="logo">Medi<span>Co</span></div></a>

  <nav class="nav">
    <a href="admin.php"><i class="fa fa-house"></i> Dashboard</a>
    <a class="active" href="#"><i class="fa fa-key"></i> Change Password</a>
  </nav>

  <form method="POST" action="admin.php" style="margin-top:auto;padding:10px 20px;">
    <button name="logout" class="btn ghost" style="width:100%;display:flex;align-items:center;gap:8px;">
      <i class="fa fa-right-from-bracket"></i> Back
    </button>
  </form>
</div>

<div class="main">
  <div class="header-row">
    <div class="header-title">Change Password</div>
  </div>

  <div class="card" style="max-width:480px;">
    <?php if($message): ?>
      <div class="hint"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-field">
        <label>Current Password</label>
        <input type="password" name="current_password" required>
      </div>

      <div class="form-field">
        <label>New Password</label>
        <input type="password" name="new_password" required>
      </div>

      <div class="form-field">
        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" required>
      </div>

      <div style="display:flex;gap:10px;">
        <button class="btn primary" type="submit">Change Password</button>
        <a href="admin.php" class="btn ghost" style="text-decoration:none;display:flex;align-items:center;">
          Cancel
        </a>
      </div>
    </form>
  </div>
</div>

</body>
</html>
