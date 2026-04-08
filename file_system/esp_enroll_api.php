<?php
include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $rfid = $_POST['rfid'];
    $keypad = $_POST['keypad'];

    // For now we assign to latest logged user
    // Later we will improve this with session tokens

    $result = $conn->query("SELECT id FROM users ORDER BY id DESC LIMIT 1");
    $row = $result->fetch_assoc();
    $id = $row['id'];

    $keypad_hash = password_hash($keypad, PASSWORD_DEFAULT);

    $conn->query("UPDATE users SET 
        rfid_uid='$rfid',
        keypad_password='$keypad_hash'
        WHERE id=$id");

    echo "SUCCESS";
}
?>