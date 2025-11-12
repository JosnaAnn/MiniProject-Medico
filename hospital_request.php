<?php
// hospital_register.php — Hospitals request access to MediCo system
session_start();

// Database connection
$mysqli = new mysqli("localhost", "root", "", "miniproject");
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

$success = "";
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hospital_name  = trim($_POST['hospital_name'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $reg_number     = trim($_POST['reg_number'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $email          = trim($_POST['email'] ?? '');

    // Upload document if provided
    $documents = "";
    if (!empty($_FILES['documents']['name'])) {
        $file = $_FILES['documents'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowed)) {
            $errors[] = "Document must be PDF, JPG, or PNG.";
        } else {
            $folder = __DIR__ . "/uploads/requests";
            if (!is_dir($folder)) mkdir($folder, 0755, true);
            $filename = preg_replace('/\s+/', '_', $hospital_name) . "_" . time() . "." . $ext;
            $path = $folder . "/" . $filename;
            if (move_uploaded_file($file['tmp_name'], $path)) {
                $documents = "uploads/requests/" . $filename;
            } else {
                $errors[] = "Failed to upload file.";
            }
        }
    }

    // Validation
    if ($hospital_name === "" || $contact_person === "" || $phone === "" || $email === "") {
        $errors[] = "Please fill all required fields.";
    }
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = "Phone number must be 10 digits.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Insert if no errors
    if (empty($errors)) {
        $stmt = $mysqli->prepare("
            INSERT INTO hospital_requests
            (hospital_name, address, reg_number, contact_person, phone, email, documents, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->bind_param("sssssss", $hospital_name, $address, $reg_number, $contact_person, $phone, $email, $documents);
        if ($stmt->execute()) {
            $success = "✅ Your request has been submitted successfully! Our team will verify and contact you soon.";
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Hospital Registration Request • MediCo</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{box-sizing:border-box;font-family:'Segoe UI',sans-serif}
body{margin:0;background:#eef7fb;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px}
.card{background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.08);padding:30px;width:100%;max-width:600px}
h2{color:#0078b7;text-align:center;margin-top:0;margin-bottom:20px;}
label{font-weight:500;display:block;margin-top:10px;margin-bottom:5px;color:#333}
input,textarea{width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:15px;}
.btn{margin-top:16px;width:100%;padding:12px;background:#00bcd4;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:15px;font-weight:500;}
.btn:hover{background:#009bb0;}
.alert{padding:10px;border-radius:8px;margin-bottom:10px;}
.alert.error{background:#ffecec;color:#a20000;}
.alert.success{background:#e8f8f2;color:#006a39;}
.back-btn{
  display:inline-block;
  margin-top:20px;
  text-decoration:none;
  color:#0078b7;
  font-size:14px;
  transition:color 0.2s ease;
}
.back-btn:hover{color:#009bb0;text-decoration:underline;}
</style>
</head>
<body>
<div class="card">
  <h2><i class="fa fa-hospital"></i> Hospital Access Request</h2>

  <?php if ($success): ?>
    <div class="alert success"><?= $success ?></div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert error"><?php foreach ($errors as $e) echo htmlspecialchars($e)."<br>"; ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <label>Hospital Name *</label>
    <input type="text" name="hospital_name" required>

    <label>Address *</label>
    <textarea name="address" rows="2" required></textarea>

    <label>Registration / License Number</label>
    <input type="text" name="reg_number">

    <label>Contact Person *</label>
    <input type="text" name="contact_person" required>

    <label>Contact Phone *</label>
    <input type="text" name="phone" required placeholder="10-digit number">

    <label>Email *</label>
    <input type="email" name="email" required>

    <label>Upload License or Document (pdf/jpg/png)</label>
    <input type="file" name="documents" accept=".pdf,.jpg,.jpeg,.png">

    <button class="btn" type="submit"><i class="fa fa-paper-plane"></i> Submit Request</button>
  </form>

  <div style="text-align:center;">
    <a href="index.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
  </div>
</div>
</body>
</html>
