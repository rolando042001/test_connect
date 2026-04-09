<?php
// Called by the ESP32 once enrollment is finished (or aborted).
include "db.php";
header('Content-Type: text/plain');

$user_id = isset($_REQUEST['user_id']) ? (int)$_REQUEST['user_id'] : 0;
if (!$user_id) { echo "MISSING_USER"; exit; }

$stmt = $conn->prepare(
    "UPDATE enroll_requests SET active = 0, step = 0 WHERE user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

echo "RESET_OK";
$conn->close();
