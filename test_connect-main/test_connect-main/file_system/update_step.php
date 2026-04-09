<?php
include "db.php";

$user_id = $_POST['user_id'];
$step = $_POST['step'];

$conn->query("UPDATE users SET enroll_step='$step' WHERE id='$user_id'");

echo "OK";
?>