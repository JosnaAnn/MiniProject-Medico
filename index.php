<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>MediCo • Home</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', sans-serif;
    }

    body {
      background: #eaf3f8;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .container {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      padding: 50px 60px;
      text-align: center;
      max-width: 420px;
      width: 90%;
      transition: all 0.3s ease;
    }

    .logo {
      font-size: 36px;
      font-weight: 700;
      color: #0078b7;
      margin-bottom: 10px;
      letter-spacing: 1px;
    }

    .logo span {
      color: #00bcd4;
    }

    h2 {
      font-size: 20px;
      color: #333;
      font-weight: 500;
      margin-bottom: 30px;
    }

    .btn-group {
      display: flex;
      flex-direction: column;
      gap: 12px;
      align-items: center;
    }

    .btn-group a {
      text-decoration: none;
      color: #fff;
      background: #00bcd4;
      padding: 12px 20px;
      border-radius: 8px;
      width: 80%;
      text-align: center;
      font-size: 15px;
      font-weight: 500;
      transition: background 0.3s;
    }

    .btn-group a:hover {
      background: #009bb0;
    }

    footer {
      text-align: center;
      margin-top: 25px;
      color: #777;
      font-size: 13px;
    }

    .footer-links {
      margin-top: 10px;
      font-size: 13px;
    }

    .footer-links a {
      color: #0078b7;
      text-decoration: none;
      margin: 0 5px;
    }

    .footer-links a:hover {
      text-decoration: underline;
    }

    @media(max-width:480px) {
      .container {
        padding: 35px 25px;
      }
      .logo {
        font-size: 30px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="logo">Medi<span>Co</span></div>
    <h2>Welcome to MediCo Hospital Token System</h2>

    <div class="btn-group">
      <a href="register.php"><i class="fa fa-user"></i> User Registration</a>
      <a href="login.php"><i class="fa fa-lock"></i> Admin Login</a>
    </div>

    <footer>
      <div>© <?= date("Y") ?> MediCo Hospital System</div>
      <div class="footer-links">
        <a href="about.html"><i class="fa fa-info-circle"></i> About</a> |
        <a href="contact.html"><i class="fa fa-envelope"></i> Contact</a>
      </div>
    </footer>
  </div>
</body>
</html>
