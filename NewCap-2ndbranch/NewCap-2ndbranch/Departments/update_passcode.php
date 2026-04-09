<?php
session_start();
require_once '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');
header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);
$passcode = $_POST['passcode'] ?? '';
$admin_id = $_SESSION['user_id'] ?? null;

// Validation
if (!$admin_id || $id <= 0 || strlen($passcode) < 4 || strlen($passcode) > 6 || !ctype_digit($passcode)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request or passcode format.']);
    exit;
}

try {
    // I-hash ang passcode para sa security
    $hashed = password_hash($passcode, PASSWORD_BCRYPT);
    
    $conn->begin_transaction();

    // I-update ang passcode at has_passcode status flag
    $stmt = $conn->prepare("UPDATE users SET passcode = ?, has_passcode = 1 WHERE id = ?");
    $stmt->bind_param("si", $hashed, $id);
    $stmt->execute();

    // Audit Log para sa accountability
    $action = "Update Passcode";
    $details = "Admin ID: $admin_id modified MFA passcode for User ID: $id";
    logActivity($conn, $admin_id, $action, $details, "Security");

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>