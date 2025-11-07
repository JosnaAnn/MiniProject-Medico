<?php
session_start();
$host = "localhost";
$user = "root";
$password = "";
$dbname = "miniproject";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) die("Database Error");

$error = "";

// ✅ Hardcoded superadmin (change password if needed)
$superadmin_username = 'superadmin';
$superadmin_password = 'super123';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST['username']);
    $password_input = trim($_POST['password']);

    // ✅ Super Admin Check FIRST
    if ($username === $superadmin_username) {

        if ($password_input === $superadmin_password) {
            $_SESSION['superadmin_logged_in'] = true;
            $_SESSION['role'] = 'superadmin';
            $_SESSION['username'] = $superadmin_username;
            header("Location: superadmin.php");
            exit();
        } else {
            $error = "❌ Incorrect Super Admin Password!";
        }

    } else {

        // ✅ Hospital Admin Login
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
                $error = "❌ Wrong Password!";
            }
        } else {
            $error = "❌ Admin Username not found!";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Login - MediCo</title>
<link rel="stylesheet" href="style.css">
<style>.error{color:red;margin:10px 0;}</style>
</head>
<body>
<div class="login-box">
    <a href="index.php"><div class="logo">Medi<span>Co</span></div></a>
    <h2>Login</h2>

    <?php if(!empty($error)): ?>
        <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="username" required placeholder="Username">
        <input type="password" name="password" required placeholder="Password">
        <!-- ✅ No role dropdown -->
        <button type="submit">Login</button>
    </form>
</div>
</body>
</html>
