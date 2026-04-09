<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');

$data = json_decode(file_get_contents('php://input'), true);
$items = [];

// Check kung bulk (array) o individual (single id/type)
if (isset($data['items'])) {
    $items = $data['items'];
} elseif (isset($data['id']) && isset($data['type'])) {
    $items[] = ['id' => $data['id'], 'type' => $data['type']];
}

if (empty($items)) {
    echo json_encode(['success' => false, 'error' => 'Missing ID or Type.']);
    exit;
}

$success = true;
foreach ($items as $item) {
    $id = intval($item['id']);
    $type = $item['type'];
    $table = ($type === 'folder') ? 'folders' : 'files';
    
    $query = "UPDATE $table SET is_archived = 0 WHERE id = $id";
    if (!$conn->query($query)) { $success = false; }
}

echo json_encode(['success' => $success]);
$conn->close();
?>