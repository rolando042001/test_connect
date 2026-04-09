<?php
session_start();
include "db.php";
require __DIR__ . "/rbac.php";
require_role('admin');

$sql = "SELECT MAX(id) AS max_id FROM users";
$result = $conn->query($sql);

if ($result && $row = $result->fetch_assoc()) {
    $next_id = ($row['max_id'] == null) ? 1 : $row['max_id'] + 1;
    echo $next_id;
} else {
    // Default sa 1 kung walang laman o may error
    echo "1"; 
}
?>