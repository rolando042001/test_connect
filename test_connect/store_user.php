<?php
include "db.php";

if(
    isset($_POST['id']) &&
    isset($_POST['rfid_uid']) &&
    isset($_POST['passcode'])
){

$id = intval($_POST['id']);
$rfid = $_POST['rfid_uid'];
$passcode = $_POST['passcode'];

if(!preg_match('/^[0-9]{6,8}$/',$passcode)){
    echo "INVALID_PASSCODE";
    exit();
}

$sql = "INSERT INTO users 
(id, passcode, rfid_uid, has_passcode, has_rfid, has_fingerprint)
VALUES
('$id','$passcode','$rfid',1,1,1)
ON DUPLICATE KEY UPDATE
passcode='$passcode',
rfid_uid='$rfid',
has_passcode=1,
has_rfid=1,
has_fingerprint=1";

if($conn->query($sql)){
    echo "USER_SAVED";
}else{
    echo "ERROR";
}

}else{

echo "MISSING_DATA";

}

?>