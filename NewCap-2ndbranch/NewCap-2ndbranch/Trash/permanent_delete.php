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
        if ($type === 'file') {
            // Kuhanin muna ang storage name bago burahin ang record
            $stmt = $conn->prepare("SELECT storage_name FROM files WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $file = $stmt->get_result()->fetch_assoc();

            if ($file) {
                $path = '../uploads/vault/' . $file['storage_name'];
                if (file_exists($path)) {
                    unlink($path); // Physical deletion sa MacBook
                }
            }
        }

        $table = ($type === 'folder') ? 'folders' : 'files';
        $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}