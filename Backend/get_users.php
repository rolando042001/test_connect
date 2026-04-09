<?php
session_start();
include "db.php";
require __DIR__ . "/rbac.php";
require_role('admin');

$result = mysqli_query($conn,"SELECT id FROM users");

$users = array();

while($row = mysqli_fetch_assoc($result)){
    $users[] = $row;
}

echo json_encode($users);

?>