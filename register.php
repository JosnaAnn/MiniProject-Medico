<?php
session_start();
$host = "localhost";
$user = "root";
$password = "";
$dbname = "miniproject";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ✅ Fetch hospitals for dropdown
$hospitals = $conn->query("SELECT id, name FROM hospitals ORDER BY name ASC");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $hospital_id = intval($_POST['hospital']);
    $name = strtoupper(trim($_POST['name']));
    $age = intval($_POST['age']); // ← fixed: removed extra ')'
    $gender = ($_POST['gender'] === "Others") ? strtoupper(trim($_POST['specifyGender'])) : $_POST['gender'];
    $phone = $_POST['phone'];
    $place = strtoupper(trim($_POST['place']));
    $department = $_POST['department'];
    $tokenDate = date("Y-m-d");

    $conn->begin_transaction();

    try {
        // ✅ Fetch hospital_code
        $stmt = $conn->prepare("SELECT hospital_code FROM hospitals WHERE id=?");
        $stmt->bind_param("i", $hospital_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            throw new Exception("Invalid hospital selected.");
        }
        $hospital_code = $res->fetch_assoc()['hospital_code'];
        $stmt->close();

        // ✅ Increment counter per hospital
        $stmt = $conn->prepare("
            INSERT INTO patient_counter (hospital_id, last_number)
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE last_number = last_number + 1
        ");
        $stmt->bind_param("i", $hospital_id);
        $stmt->execute();
        $stmt->close();

        // ✅ Fetch updated number (locked row)
        $stmt = $conn->prepare("SELECT last_number FROM patient_counter WHERE hospital_id=? FOR UPDATE");
        $stmt->bind_param("i", $hospital_id);
        $stmt->execute();
        $newNumber = $stmt->get_result()->fetch_assoc()['last_number'];
        $stmt->close();

        // ✅ Generate UID like CODE-000001
        $patientUid = $hospital_code . "-" . str_pad($newNumber, 6, "0", STR_PAD_LEFT);

        // ✅ Token number generation (per dept per day per hospital)
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total 
            FROM patients 
            WHERE token_date=? AND department=? AND hospital_id=?
        ");
        $stmt->bind_param("ssi", $tokenDate, $department, $hospital_id);
        $stmt->execute();
        $nextToken = $stmt->get_result()->fetch_assoc()['total'] + 1;
        $stmt->close();

        // ✅ Insert Patient Record
        $stmt = $conn->prepare("
            INSERT INTO patients
            (patient_uid, name, age, gender, phone, place, department, token, token_date, hospital_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssissssisi",
            $patientUid, $name, $age, $gender, $phone, $place,
            $department, $nextToken, $tokenDate, $hospital_id
        );
        $stmt->execute();
        $lastInsertId = $conn->insert_id;
        $stmt->close();

        $conn->commit();

        // ✅ Store session
        $_SESSION['patient_id']  = $lastInsertId;
        $_SESSION['hospital_id'] = $hospital_id;
        $_SESSION['token']       = $nextToken;
        $_SESSION['patient_uid'] = $patientUid;

        if ($age >= 18) {
            $_SESSION['paid'] = false;
            header("Location: payment.php");
        } else {
            header("Location: print.php");
        }
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        die("❌ Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Patient Registration</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <div class="header">
        <a href="index.php"><div class="logo">Medi<span>Co</span></div></a>
        <h2 class="title">Patient Registration</h2>
    </div>
    <div class="box">
        <h2 class="to">Get Your Token</h2>
        <form method="POST">
            <label>Select Hospital:</label>
            <select name="hospital" required>
                <option value="">-- Select Hospital --</option>
                <?php while ($row = $hospitals->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                <?php endwhile; ?>
            </select>

            <label>Name:</label>
            <input type="text" name="name" required>

            <label>Age:</label>
            <input type="number" name="age" required>

            <label>Gender:</label>
            <select name="gender" id="genderSelect" required>
                <option value="">-- Select --</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Others">Others</option>
            </select>
            <div id="specifyBox" style="display:none;">
                <label>Specify Gender:</label>
                <input type="text" name="specifyGender">
            </div>

            <label>Phone:</label>
            <input type="tel" name="phone" pattern="[0-9]{10}" required>

            <label>Place:</label>
            <input type="text" name="place" required>

            <label>Department:</label>
            <select name="department" required>
                <option value="">-- Select --</option>
                <option value="Cardiology">Cardiology</option>
                <option value="Neurology">Neurology</option>
                <option value="Orthopedics">Orthopedics</option>
            </select>

            <button type="submit">Next</button>
        </form>
    </div>
</div>

<script>
document.getElementById("genderSelect").addEventListener("change", function () {
  document.getElementById("specifyBox").style.display =
    (this.value === "Others") ? "block" : "none";
});
</script>
</body>
</html>
