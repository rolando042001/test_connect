<?php
// Run as a scheduled task (CLI) or by an admin from the dashboard.
require __DIR__ . '/../Backend/db.php';
if (PHP_SAPI !== 'cli') {
    session_start();
    require __DIR__ . '/../Backend/rbac.php';
    require_role('admin');
}
chdir(__DIR__); // so the relative '../uploads/...' paths still resolve

try {
    // 1. Kuhanin ang mga files na lampas 30 days na sa trash
    $sql_files = "SELECT id, storage_name FROM files WHERE is_trash = 1 AND DATEDIFF(NOW(), deleted_at) >= 30";
    $res_files = $conn->query($sql_files);

    while ($file = $res_files->fetch_assoc()) {
        $path = '../uploads/vault/' . $file['storage_name'];
        if (file_exists($path)) {
            unlink($path); // physical deletion sa MacBook storage
        }
        $conn->query("DELETE FROM files WHERE id = " . $file['id']);
    }

    // 2. Kuhanin ang mga folders na lampas 30 days na sa trash
    $conn->query("DELETE FROM folders WHERE is_trash = 1 AND DATEDIFF(NOW(), deleted_at) >= 30");

    // Optional: Log the cleanup result
} catch (Exception $e) {
    error_log("Cleanup Error: " . $e->getMessage());
}