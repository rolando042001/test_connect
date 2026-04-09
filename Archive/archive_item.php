<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');

try {
    // Dahil FormData() ang ginagamit ng executeVaultAction sa JS
    $action = $_POST['action'] ?? '';
    
    // I-decode ang array ng items na ipinasa
    $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

    if ($action !== 'archive') {
        throw new Exception("Invalid action for this endpoint.");
    }

    if (empty($items)) {
        throw new Exception("No items to archive.");
    }

    foreach ($items as $item) {
        $id = intval($item['id']);
        $type = $item['type'];
        $table = ($type === 'folder') ? 'folders' : 'files';
        
        $stmt = $conn->prepare("UPDATE $table SET is_archived = 1, archived_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to archive item ID: " . $id);
        }
        $stmt->close();
    }

    echo json_encode(['success' => true, 'status' => 'success']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
?>