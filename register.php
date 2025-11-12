<?php
session_start();
$host = "localhost";
$user = "root";
$password = "";
$dbname = "miniproject";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ✅ Fetch hospitals
$hospitals = $conn->query("SELECT id, name FROM hospitals WHERE approved=1 AND deleted=0 ORDER BY name ASC");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $hospital_id = intval($_POST['hospital']);
    $name = strtoupper(trim($_POST['name']));
    $age = intval($_POST['age']);
    $gender = ($_POST['gender'] === "Others") ? strtoupper(trim($_POST['specifyGender'])) : $_POST['gender'];
    $phone = $_POST['phone'];
    $place = strtoupper(trim($_POST['place']));
    $department = $_POST['department'];
    $tokenDate = date("Y-m-d");

    $conn->begin_transaction();

    try {
        // ✅ Fetch hospital code & name
        $stmt = $conn->prepare("SELECT hospital_code, name FROM hospitals WHERE id=?");
        $stmt->bind_param("i", $hospital_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) throw new Exception("Invalid hospital selected.");

        $hospital_data = $res->fetch_assoc();
        $hospital_code = $hospital_data['hospital_code'];
        $hospital_name = $hospital_data['name'];
        $stmt->close();

        // ✅ Increment patient UID counter
        $stmt = $conn->prepare("
            INSERT INTO patient_counter (hospital_id, last_number)
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE last_number = last_number + 1
        ");
        $stmt->bind_param("i", $hospital_id);
        $stmt->execute();
        $stmt->close();

        // ✅ Get new UID number
        $stmt = $conn->prepare("SELECT last_number FROM patient_counter WHERE hospital_id=? FOR UPDATE");
        $stmt->bind_param("i", $hospital_id);
        $stmt->execute();
        $newNumber = $stmt->get_result()->fetch_assoc()['last_number'];
        $stmt->close();

        // ✅ Generate patient UID
        $patientUid = $hospital_code . "-" . str_pad($newNumber, 6, "0", STR_PAD_LEFT);

        // ✅ Generate department token (daily)
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total 
            FROM patients 
            WHERE token_date=? AND department=? AND hospital_id=?
        ");
        $stmt->bind_param("ssi", $tokenDate, $department, $hospital_id);
        $stmt->execute();
        $nextToken = $stmt->get_result()->fetch_assoc()['total'] + 1;
        $stmt->close();

        // ✅ Insert patient record
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

        // ✅ Send WhatsApp message for patients below 18
        if ($age < 18) {
            include 'send_sms.php';
            sendTicketMessage(
                $phone,
                $name,
                $hospital_name,
                $patientUid,
                $department,
                $nextToken,
                $tokenDate,
                $age,
                $gender
            );
        }

        // ✅ Redirect based on age
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
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MediCo • Patient Registration</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',sans-serif;}
body{background:#eaf3f8;min-height:100vh;display:flex;justify-content:center;align-items:center;}
.container{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,0.1);width:90%;max-width:750px;padding:45px 60px;display:flex;flex-direction:column;align-items:center;}
.logo{font-size:36px;font-weight:700;color:#0078b7;margin-bottom:8px;}
.logo span{color:#00bcd4;}
.title{font-size:22px;font-weight:500;color:#333;margin-bottom:30px;text-align:center;}
form{width:100%;display:grid;grid-template-columns:1fr 1fr;gap:20px 30px;}
label{font-size:15px;color:#444;margin-bottom:4px;display:block;}
input, select{width:100%;padding:12px 14px;border:1px solid #ccc;border-radius:8px;font-size:15px;transition:0.2s;}
input:focus, select:focus{border-color:#00bcd4;box-shadow:0 0 0 2px rgba(0,188,212,0.2);outline:none;}
#specifyBox{display:none;grid-column:span 2;}
button{grid-column:span 2;background:#00bcd4;border:none;color:#fff;padding:14px;border-radius:8px;font-size:16px;cursor:pointer;margin-top:10px;transition:background 0.2s;}
button:hover{background:#009bb0;}
footer{text-align:center;margin-top:25px;font-size:14px;color:#777;}
footer a{color:#0078b7;text-decoration:none;}
footer a:hover{text-decoration:underline;}
@media(max-width:600px){.container{padding:30px 25px;}form{grid-template-columns:1fr;}button{grid-column:1;}}
</style>
</head>
<body>
<div class="container">
  <a href="index.php" style="text-decoration:none;"><div class="logo">Medi<span>Co</span></div></a>
  <h2 class="title">Patient Registration</h2>

  <form method="POST">
    <!-- ✅ Hospital -->
    <div>
      <label>Hospital:</label>
      <select id="hospitalSelect" name="hospital" required>
        <option value="">-- Select Hospital --</option>
        <?php while ($row = $hospitals->fetch_assoc()): ?>
          <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>

    <div>
      <label>Name:</label>
      <input type="text" name="name" required placeholder="Full Name">
    </div>

    <div>
      <label>Age:</label>
      <input type="number" name="age" required placeholder="Age">
    </div>

    <div>
      <label>Gender:</label>
      <select name="gender" id="genderSelect" required>
        <option value="">-- Select --</option>
        <option value="Male">Male</option>
        <option value="Female">Female</option>
        <option value="Others">Others</option>
      </select>
    </div>

    <div id="specifyBox">
      <label>Specify Gender:</label>
      <input type="text" name="specifyGender" placeholder="Enter Gender Identity">
    </div>

    <div>
      <label>Phone:</label>
      <input type="tel" name="phone" pattern="[0-9]{10}" required placeholder="10-digit number">
    </div>

    <div>
      <label>Place:</label>
      <input type="text" name="place" required placeholder="Place / City">
    </div>

    <!-- ✅ Department (Dynamic based on hospital) -->
    <div>
      <label>Department:</label>
      <select id="departmentSelect" name="department" required>
        <option value="">-- Select Department --</option>
      </select>
    </div>

    <button type="submit"><i class="fa fa-arrow-right"></i> Next</button>
  </form>

  <footer>
    <a href="index.php"><i class="fa fa-arrow-left"></i> Back to Home</a>
  </footer>
</div>

<script>
// ✅ Show specify gender box
document.getElementById("genderSelect").addEventListener("change", function(){
  document.getElementById("specifyBox").style.display = (this.value === "Others") ? "block" : "none";
});

// ✅ Dynamic Department Loader
document.getElementById("hospitalSelect").addEventListener("change", function(){
  const hospitalId = this.value;
  const deptSelect = document.getElementById("departmentSelect");

  deptSelect.innerHTML = '<option value="">Loading...</option>';

  if (!hospitalId) {
    deptSelect.innerHTML = '<option value="">-- Select Department --</option>';
    return;
  }

  fetch('get_departments.php?hospital_id=' + hospitalId)
    .then(res => res.json())
    .then(data => {
      deptSelect.innerHTML = '<option value="">-- Select Department --</option>';
      if (data.length > 0) {
        data.forEach(dep => {
          const opt = document.createElement('option');
          opt.value = dep;
          opt.textContent = dep;
          deptSelect.appendChild(opt);
        });
      } else {
        deptSelect.innerHTML = '<option value="">No departments available</option>';
      }
    })
    .catch(err => {
      deptSelect.innerHTML = '<option value="">Error loading departments</option>';
      console.error(err);
    });
});
</script>
</body>
</html>
