<?php
include "db.php";

$user_id = $_GET['user_id'];

$result = $conn->query("SELECT enroll_request FROM users WHERE id='$user_id'");
$row = $result->fetch_assoc();

echo $row['enroll_request'];
?>