<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');

try {
    // 1. Get all file storage names to unlink them
    $res_files = $conn->query("SELECT storage_name FROM files WHERE is_trash = 1");
    while ($file = $res_files->fetch_assoc()) {
        $path = '../uploads/vault/' . $file['storage_name'];
        if (file_exists($path)) {
            unlink($path); // Physically delete from your MacBook Air
        }
    }

    // 2. Delete all records
    $conn->query("DELETE FROM files WHERE is_trash = 1");
    $conn->query("DELETE FROM folders WHERE is_trash = 1");

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}