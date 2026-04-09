<?php
include "db.php";

$id = $_POST['id'];
$username = $_POST['username'];
$password = $_POST['password'];
$rfid = $_POST['rfid_uid'];
$fprint = $_POST['fingerprint_id'];
$keypad = $_POST['keypad_password'];
$auth = $_POST['auth_mode'];

if(!empty($password)){
    $password = password_hash($password, PASSWORD_DEFAULT);
    $conn->query("UPDATE users SET password='$password' WHERE id=$id");
}

$conn->query("UPDATE users SET 
username='$username',
rfid_uid='$rfid',
fingerprint_id='$fprint',
keypad_password='$keypad',
auth_mode='$auth'
WHERE id=$id");

header("Location: admin_dashboard.php");
?>