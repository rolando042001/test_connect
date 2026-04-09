<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); 
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
}<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php'; 

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['user_id'];
$items = [];

// Fetch Folders
$stmt = $conn->prepare("SELECT id, name, NULL as dept_id, 'folder' as type, 'N/A' as deleted_at, 30 as days_remaining FROM folders WHERE user_id = ? AND dept_id IS NULL AND is_trash = 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

// Fetch Files
$stmt2 = $conn->prepare("SELECT id, display_name as name, NULL as dept_id, 'file' as type, 'N/A' as deleted_at, 30 as days_remaining, storage_name FROM files WHERE user_id = ? AND dept_id IS NULL AND is_trash = 1");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$res2 = $stmt2->get_result();
while ($row = $res2->fetch_assoc()) {
    $items[] = $row;
}
$stmt2->close();

echo json_encode($items);
?>

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