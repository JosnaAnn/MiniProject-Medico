<?php
$host = "localhost";
$user = "root";
$password = "";
$dbname = "miniproject";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) die("DB Error");

$hospital_id = isset($_GET['hospital_id']) ? intval($_GET['hospital_id']) : 0;

$departments = [];

if ($hospital_id > 0) {
    $stmt = $conn->prepare("SELECT department_name FROM departments WHERE hospital_id=? ORDER BY department_name ASC");
    $stmt->bind_param("i", $hospital_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $departments[] = $row['department_name'];
    }
    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($departments);
?>
