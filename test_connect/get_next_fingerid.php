<?php
include "db.php";

$sql = "SELECT MAX(id) AS max_id FROM users";
$result = $conn->query($sql);

$row = $result->fetch_assoc();

echo $row['max_id'] + 1;
?>