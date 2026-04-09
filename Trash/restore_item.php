<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');
$data = json_decode(file_get_contents('php://input'), true);

$id = $data['id'] ?? null;
$type = $data['type'] ?? null;

try {
    if ($id && $type) {
        $table = ($type === 'folder') ? 'folders' : 'files';
        // I-reset ang is_trash sa 0 at gawing null ang deleted_at
        $stmt = $conn->prepare("UPDATE $table SET is_trash = 0, deleted_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Failed to execute restore.");
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}