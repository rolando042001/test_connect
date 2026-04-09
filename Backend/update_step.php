<?php
// ESP32 reports its enrollment progress (1=RFID, 2=Fingerprint, 3=Passcode, 4=Done).
// The Settings UI polls enroll_status to render the current step to the admin.
include "db.php";
header('Content-Type: text/plain');

$user_id = isset($_REQUEST['user_id']) ? (int)$_REQUEST['user_id'] : 0;
$step    = isset($_REQUEST['step'])    ? (int)$_REQUEST['step']    : 0;

if (!$user_id) { echo "MISSING_USER"; exit; }

$stmt = $conn->prepare(
    "INSERT INTO enroll_requests (user_id, step, active)
     VALUES (?, ?, 1)
     ON DUPLICATE KEY UPDATE step = VALUES(step)"
);
$stmt->bind_param("ii", $user_id, $step);
$stmt->execute();
$stmt->close();

echo "STEP_OK";
$conn->close();
