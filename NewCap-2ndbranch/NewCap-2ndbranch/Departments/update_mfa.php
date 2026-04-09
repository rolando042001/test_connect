<?php
session_start();
require_once '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');
header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);
$type = $_POST['type'] ?? ''; // 'rfid' o 'fingerprint'
$value = $_POST['value'] ?? '';
$admin_id = $_SESSION['user_id'] ?? null;

if (!$admin_id || $id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized or invalid ID.']);
    exit;
}

try {
    if ($type === 'rfid') {
        // Gamitin ang column names mula sa iyong schema
        $stmt = $conn->prepare("UPDATE users SET rfid_uid = ?, has_rfid = 1 WHERE id = ?");
        $stmt->bind_param("si", $value, $id);
    } elseif ($type === 'fingerprint') {
        $stmt = $conn->prepare("UPDATE users SET fingerprint_template = ?, has_fingerprint = 1 WHERE id = ?");
        $stmt->bind_param("si", $value, $id);
    } else {
        throw new Exception("Invalid MFA type.");
    }

    if ($stmt->execute()) {
        // I-log ang action para sa audit trail
        $action = "Updated MFA: " . strtoupper($type);
        logActivity($conn, $admin_id, $action, "User ID: $id", "Security");
        
        echo json_encode(['success' => true]);
    } else {
        throw new Exception($stmt->error);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>