<?php
// Disarms the relay after the ESP32 has fired the solenoid.
//
// Fixes vs original:
//   - Optionally accepts ?relay=1 / ?relay=2 to disarm a single channel
//     instead of nuking both. With no parameter, behaviour is unchanged
//     (clear all) for backwards compatibility with the V3 sketch
include "db.php";
header('Content-Type: text/plain');

if (isset($_GET['relay']) || isset($_POST['relay'])) {
    $relay = (int) ($_GET['relay'] ?? $_POST['relay']);
    $stmt = $conn->prepare("UPDATE relay SET active = 0 WHERE relay_in = ?");
    $stmt->bind_param("i", $relay);
    $stmt->execute();
    $stmt->close();
} else {
    $conn->query("UPDATE relay SET active = 0");
}

echo "RESET";
