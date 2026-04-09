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
    $stmtFolder = $conn->prepare("UPDATE folders SET is_trash = 0 WHERE id = ? AND user_id = ? AND dept_id IS NULL");
    $stmtFile = $conn->prepare("UPDATE files SET is_trash = 0 WHERE id = ? AND user_id = ? AND dept_id IS NULL");

    foreach ($data['items'] as $item) {
        if ($item['type'] === 'folder') {
            $stmtFolder->bind_param("ii", $item['id'], $user_id);
            $stmtFolder->execute();
        } else if ($item['type'] === 'file') {
            $stmtFile->bind_param("ii", $item['id'], $user_id);
            $stmtFile->execute();
        }
    }

    $conn->commit();
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "error" => "Database operation failed."]);
}
?>