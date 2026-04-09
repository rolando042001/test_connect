<?php
// LEGACY shim — superseded by Backend/reset_enroll_request.php
session_start();
include "db.php";
require __DIR__ . "/rbac.php";
require_role('admin');

$user_id = isset($_REQUEST['user_id']) ? (int)$_REQUEST['user_id'] : 0;
if ($user_id) {
    $stmt = $conn->prepare("UPDATE enroll_requests SET active = 0, step = 0 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

echo "REGISTER_FINISHED";
