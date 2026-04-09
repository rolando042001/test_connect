<?php
include "db.php";

$user_id = $_GET['user_id'];

$conn->query("UPDATE users SET enroll_request=0 WHERE id='$user_id'");

echo "OK";
?>