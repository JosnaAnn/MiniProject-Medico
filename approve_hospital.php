<?php
$conn = new mysqli("localhost", "root", "", "miniproject");
$id = $_POST['id'] ?? 0;

$req = $conn->query("SELECT * FROM hospital_requests WHERE id=$id")->fetch_assoc();
if (!$req) die("Invalid request ID");

// 1️⃣ Create hospital record
$stmt = $conn->prepare("INSERT INTO hospitals (hospital_name, address, created_at) VALUES (?, ?, NOW())");
$stmt->bind_param("ss", $req['hospital_name'], $req['address']);
$stmt->execute();
$hospital_id = $stmt->insert_id;
$stmt->close();

// 2️⃣ Generate admin credentials
$username = strtolower(str_replace(' ', '', $req['hospital_name'])) . "_admin";
$password_plain = substr(md5(time()), 0, 8);
$password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);

// 3️⃣ Create admin user
$stmt = $conn->prepare("INSERT INTO admins (hospital_id, username, password) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $hospital_id, $username, $password_hashed);
$stmt->execute();
$stmt->close();

// 4️⃣ Mark request as approved
$conn->query("UPDATE hospital_requests SET status='approved' WHERE id=$id");

// 5️⃣ (Optional) Send WhatsApp or Email notification
// You can integrate Twilio / SMTP mail here
$message = "Your hospital registration has been approved.\n\nAdmin Login:\nUsername: $username\nPassword: $password_plain";
echo "<pre>$message</pre>";
echo "<script>alert('Hospital approved! Admin credentials generated.');window.location='superadmin.php';</script>";
?>
