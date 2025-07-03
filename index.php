<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>MediCo Landing</title>
  <link rel="stylesheet" href="style.css" />
  <style>
    body {
      background-color: #FFF5DB;
      font-family: 'Segoe UI', sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .landing-box {
      text-align: center;
      background: #fffef8;
      padding: 40px 60px;
      border-radius: 16px;
      box-shadow: 0 0 12px rgba(0,0,0,0.1);
    }

    .logo {
      font-size: 48px;
      font-family: 'Brush Script MT', cursive;
      margin-bottom: 10px;
      color: #00ac4f;
    }

    .logo span {
      color: #008fc7;
    }

    h2 {
      font-size: 24px;
      margin-bottom: 30px;
    }

    .btn-group a {
      display: inline-block;
      margin: 10px;
      padding: 14px 28px;
      background-color: #00a3cc;
      color: white;
      text-decoration: none;
      border-radius: 10px;
      font-size: 16px;
      transition: background-color 0.3s;
    }

    .btn-group a:hover {
      background-color: #007da1;
    }
  </style>
</head>
<body>
  <div class="landing-box">
    <div class="logo">Medi<span>Co</span></div>
    <h2>Welcome to MediCo Token System</h2>

    <div class="btn-group">
      <a href="register.php">User</a>
      <a href="login.php">Admin</a>
      <a href="login.php">Superadmin</a>
    </div>
  </div>
</body>
</html>
