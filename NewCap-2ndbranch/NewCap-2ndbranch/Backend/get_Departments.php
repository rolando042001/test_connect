<?php
// Backend/get_Departments.php
header('Content-Type: application/json');
include 'db.php'; // Siguraduhing tama ang path

$sql = "SELECT id, name FROM departments"; // Kunin ang primary key
$result = $conn->query($sql);
$depts = [];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $depts[] = $row;
    }
}

echo json_encode($depts);
$conn->close();
?>

