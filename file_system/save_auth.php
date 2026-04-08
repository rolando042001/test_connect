<?php
session_start();
include "db.php";

$id = $_SESSION['user_id'];

$rfid = $_POST['rfid'];
$fingerprint = $_POST['fingerprint'];
$keypad = $_POST['keypad'];

$keypad_hash = password_hash($keypad, PASSWORD_DEFAULT);

$conn->query("UPDATE users SET 
rfid_uid='$rfid',
fingerprint_id='$fingerprint',
keypad_password='$keypad_hash'
WHERE id=$id");
?>