<?php
session_start();
$conn = new mysqli("localhost", "root", "", "miniproject");
$hospital_id = $_SESSION['admin_hospital_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dept = $_POST['department_name'];
    $conn->query("INSERT INTO departments (hospital_id, department_name) VALUES ('$hospital_id', '$dept')");
}

$result = $conn->query("SELECT * FROM departments WHERE hospital_id='$hospital_id'");
?>
<h3>Manage Departments</h3>
<form method="POST">
    <input type="text" name="department_name" placeholder="Enter department" required>
    <button type="submit">Add Department</button>
</form>

<table border="1">
<tr><th>Department</th><th>Action</th></tr>
<?php while($row = $result->fetch_assoc()): ?>
<tr><td><?= $row['department_name'] ?></td></tr>
<?php endwhile; ?>
</table>
