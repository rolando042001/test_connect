<?php
include "db.php";

$id = $_GET['id'];

$conn->query("UPDATE users SET status='approved' WHERE id=$id");

header("Location: admin_dashboard.php");
?>