<?php
session_start();
$host = "localhost";
$user = "root";
$password = "";
$dbname = "miniproject";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) die("Database Error");

$error = "";

// Hardcoded Superadmin
$superadmin_username = 'superadmin';
$superadmin_password = 'super123';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password_input = trim($_POST['password']);

    // Super Admin Login
    if ($username === $superadmin_username) {
        if ($password_input === $superadmin_password) {
            $_SESSION['superadmin_logged_in'] = true;
            $_SESSION['role'] = 'superadmin';
            $_SESSION['username'] = $superadmin_username;
            header("Location: superadmin.php");
            exit();
        } else {
            $error = "Incorrect password!";
        }
    } else {
        // Hospital Admin Login
        $stmt = $conn->prepare("SELECT * FROM users WHERE username=? AND role='admin'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($user = $res->fetch_assoc()) {
            if (password_verify($password_input, $user['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['role'] = 'admin';
                $_SESSION['username'] = $user['username'];
                $_SESSION['admin_hospital_id'] = $user['hospital_id'];
                header("Location: admin.php");
                exit();
            } else {
                $error = "Incorrect password!";
            }
        } else {
            $error = "User not found!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediCo • Login</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',sans-serif;}
body{
  background:#eaf3f8;
  height:100vh;
  display:flex;
  justify-content:center;
  align-items:center;
}
.login-box{
  background:#fff;
  border-radius:12px;
  box-shadow:0 5px 20px rgba(0,0,0,0.08);
  width:100%;
  max-width:360px;
  padding:40px 30px;
}
.logo{
  font-size:28px;
  font-weight:700;
  color:#0078b7;
  text-align:center;
  margin-bottom:10px;
}
.logo span{color:#00bcd4;}
h2{
  font-size:20px;
  text-align:center;
  color:#333;
  margin-bottom:20px;
  font-weight:500;
}
form{
  display:flex;
  flex-direction:column;
  gap:14px;
}
input{
  padding:12px 14px;
  border:1px solid #ccc;
  border-radius:8px;
  font-size:15px;
  outline:none;
  transition:0.2s;
}
input:focus{
  border-color:#00bcd4;
  box-shadow:0 0 0 2px rgba(0,188,212,0.2);
}
button{
  background:#00bcd4;
  border:none;
  color:#fff;
  padding:12px;
  border-radius:8px;
  font-size:15px;
  cursor:pointer;
  transition:background 0.2s;
}
button:hover{
  background:#009bb0;
}
.error{
  color:#d9534f;
  background:#fdecec;
  border:1px solid #f5c2c0;
  padding:10px;
  border-radius:8px;
  margin-bottom:10px;
  text-align:center;
  font-size:14px;
}
.footer-links{
  text-align:center;
  margin-top:20px;
  font-size:13px;
  color:#777;
}
.footer-links a{
  color:#0078b7;
  text-decoration:none;
}
.footer-links a:hover{
  text-decoration:underline;
}
</style>
</head>
<body>
  <div class="login-box">
    <a href="index.php" style="text-decoration:none;">
      <div class="logo">Medi<span>Co</span></div>
    </a>
    <h2>Admin Login</h2>

    <?php if(!empty($error)): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="text" name="username" placeholder="Username" required>
      <input type="password" name="password" placeholder="Password" required>
      <button type="submit"><i class="fa fa-right-to-bracket"></i> Login</button>
    </form>

    <div class="footer-links">
      <a href="index.php"><i class="fa fa-arrow-left"></i> Back to Home</a>
    </div>
  </div>
</body>
</html>
