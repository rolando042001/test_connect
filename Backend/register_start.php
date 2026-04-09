<?php
// LEGACY shim — superseded by Settings/manage_settings.php?action=start_esp32_enroll
// Kept so older callers don't break.
session_start();
include "db.php";
require __DIR__ . "/rbac.php";
require_role('admin');

$user_id = isset($_REQUEST['user_id']) ? (int)$_REQUEST['user_id'] : 0;
if (!$user_id) { echo "MISSING_USER"; exit; }

$stmt = $conn->prepare(
    "INSERT INTO enroll_requests (user_id, step, active)
     VALUES (?, 0, 1)
     ON DUPLICATE KEY UPDATE step = 0, active = 1"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

echo "REGISTER_STARTED";
