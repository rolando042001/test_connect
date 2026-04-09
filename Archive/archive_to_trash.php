<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');

$data = json_decode(file_get_contents('php://input'), true);
$items = $data['items'] ?? [];

if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'No items selected.']);
    exit;
}

$success = true;
foreach ($items as $item) {
    $id = intval($item['id']);
    $type = $item['type'];
    $table = ($type === 'folder') ? 'folders' : 'files';
    
    // Ang pag-lipat sa Trash ay pag-set ng is_trash = 1 at is_archived = 0
    $query = "UPDATE $table SET is_trash = 1, is_archived = 0, deleted_at = NOW() WHERE id = $id";
    if (!$conn->query($query)) { $success = false; }
}

echo json_encode(['success' => $success]);
$conn->close();
?>