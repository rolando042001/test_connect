<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');

$data = json_decode(file_get_contents('php://input'), true);
$items = $data['items'] ?? [];

try {
    foreach ($items as $item) {
        $id = $item['id'];
        $type = $item['type'];
        $table = ($type === 'folder') ? 'folders' : 'files';

        if ($type === 'file') {
            // Fetch storage name to delete physical file
            $stmt = $conn->prepare("SELECT storage_name FROM files WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            if ($res) {
                $path = '../uploads/vault/' . $res['storage_name'];
                if (file_exists($path)) unlink($path);
            }
        }

        $conn->query("DELETE FROM $table WHERE id = $id");
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}