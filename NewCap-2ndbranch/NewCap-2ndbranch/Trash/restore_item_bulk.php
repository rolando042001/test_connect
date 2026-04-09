<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');

$data = json_decode(file_get_contents('php://input'), true);
$items = $data['items'] ?? [];

if (!empty($items)) {
    foreach ($items as $item) {
        $id = $item['id'];
        $table = ($item['type'] === 'folder') ? 'folders' : 'files';
        // Reset is_trash and clear deleted_at timestamp
        $stmt = $conn->prepare("UPDATE $table SET is_trash = 0, deleted_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['success' => true]);
}