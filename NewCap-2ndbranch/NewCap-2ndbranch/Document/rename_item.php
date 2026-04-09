<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');

$id = $_POST['id'];
$newName = $_POST['new_name'];
$type = $_POST['type']; // 'folder' o 'file'

if ($type === 'folder') {
    $stmt = $conn->prepare("UPDATE folders SET name = ? WHERE id = ?");
} else {
    $stmt = $conn->prepare("UPDATE files SET display_name = ? WHERE id = ?");
}

$stmt->bind_param("si", $newName, $id);

if ($stmt->execute()) {
    logActivity($conn, 0, "Renamed $type", $newName, 'Management'); // Gamit ang iyong function
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'msg' => $conn->error]);
}
$conn->close();
?>