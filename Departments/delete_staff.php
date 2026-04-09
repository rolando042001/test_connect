<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: application/json');

include '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');

$id = $_POST['id'] ?? null;
$admin_id = $_SESSION['user_id'] ?? null; // FIX

if (!$id) {
    echo json_encode(["success" => false, "message" => "Invalid ID."]);
    exit;
}

try {

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {

        // Log only if admin exists
        if ($admin_id !== null) {
            logActivity($conn, $admin_id, 'Delete Personnel', "User ID: $id", 'User Management');
        }

        echo json_encode(["success" => true]);

    } else {
        throw new Exception($stmt->error);
    }

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Check foreign keys. Personnel might have existing files."
    ]);
}

$conn->close();
?>