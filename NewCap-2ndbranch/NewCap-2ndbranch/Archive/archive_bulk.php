<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');

$data = json_decode(file_get_contents('php://input'), true);
$items = $data['items'] ?? [];

try {
    if (!empty($items)) {
        foreach ($items as $item) {
            $id = $item['id'];
            $table = ($item['type'] === 'folder') ? 'folders' : 'files';
            
            // Set is_archived flag and record the timestamp
            $stmt = $conn->prepare("UPDATE $table SET is_archived = 1, archived_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['success' => true, 'message' => 'Items archived successfully.']);
    } else {
        throw new Exception("No items selected.");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}