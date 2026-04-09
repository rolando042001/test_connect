<?php
include "db.php";

$id = $_GET['id'];

$result = $conn->query("SELECT login_enabled FROM users WHERE id=$id");
$row = $result->fetch_assoc();

$newStatus = $row['login_enabled'] == 1 ? 0 : 1;

$conn->query("UPDATE users SET login_enabled=$newStatus WHERE id=$id");

header("Location: admin_dashboard.php");
?>