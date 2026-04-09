<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_login();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "error" => "Unauthorized access."]);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['items'])) {
    echo json_encode(["success" => false, "error" => "No items selected."]);
    exit;
}

$conn->begin_transaction();
try {
    $stmtGetFile = $conn->prepare("SELECT storage_name FROM files WHERE id = ? AND user_id = ? AND dept_id IS NULL AND is_trash = 1");
    $stmtDeleteFile = $conn->prepare("DELETE FROM files WHERE id = ? AND user_id = ? AND dept_id IS NULL AND is_trash = 1");
    $stmtDeleteFolder = $conn->prepare("DELETE FROM folders WHERE id = ? AND user_id = ? AND dept_id IS NULL AND is_trash = 1");

    foreach ($data['items'] as $item) {
        if ($item['type'] === 'file') {
            $stmtGetFile->bind_param("ii", $item['id'], $user_id);
            $stmtGetFile->execute();
            $res = $stmtGetFile->get_result();
            
            if ($row = $res->fetch_assoc()) {
                $filePath = '../uploads/private_vault/' . $row['storage_name'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            $stmtDeleteFile->bind_param("ii", $item['id'], $user_id);
            $stmtDeleteFile->execute();
        } else if ($item['type'] === 'folder') {
            $stmtDeleteFolder->bind_param("ii", $item['id'], $user_id);
            $stmtDeleteFolder->execute();
        }
    }

    $conn->commit();
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "error" => "Database operation failed."]);
}
?>