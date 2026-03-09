<?php

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "test_connect";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset (important for ESP32 communication)
$conn->set_charset("utf8");

?>