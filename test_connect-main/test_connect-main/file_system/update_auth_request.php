<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$conn->query("UPDATE users SET enroll_request=1 WHERE id=$user_id");

header("Location: multi_auth.php");
exit();
?>