<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');

try {
    $id = $_POST['id'] ?? null;
    $type = $_POST['type'] ?? null; // 'folder' o 'file'

    if (!$id || !$type) {
        throw new Exception("Missing ID or Type.");
    }

    if ($type === 'folder') {
        $stmt = $conn->prepare("UPDATE folders SET is_trash = 1 WHERE id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE files SET is_trash = 1 WHERE id = ?");
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Item moved to Trash.']);
    } else {
        throw new Exception("Item not found or already in Trash.");
    }

    $stmt->close();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}