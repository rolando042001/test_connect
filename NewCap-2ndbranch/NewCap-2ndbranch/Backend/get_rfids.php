<?php
session_start();
include "db.php";
require __DIR__ . "/rbac.php";
require_role('admin');

$sql="SELECT rfid_uid FROM users WHERE rfid_uid IS NOT NULL";

$result=$conn->query($sql);

$rows=array();

while($row=$result->fetch_assoc()){
$rows[]=$row;
}

echo json_encode($rows);
?>