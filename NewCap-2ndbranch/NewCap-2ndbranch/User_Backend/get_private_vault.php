<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_login();

// I-check kung may naka-login na user
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access. Please log in."]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Kunin ang parent_id mula sa URL (kung pumasok sa loob ng isang folder)
$parent_id = (isset($_GET['parent_id']) && $_GET['parent_id'] !== "" && $_GET['parent_id'] !== "null") ? $_GET['parent_id'] : NULL;

$response = ["folders" => [], "files" => []];

try {
    // ==========================================
    // 1. FETCH PRIVATE FOLDERS
    // Kukunin lang ang folders na ginawa ng naka-login na user (user_id) 
    // at hindi naka-assign sa anumang department (dept_id IS NULL o 0)
    // ==========================================
    if ($parent_id === NULL) {
        $stmt = $conn->prepare("SELECT id, name, created_at as date FROM folders WHERE parent_id IS NULL AND user_id = ? AND (dept_id IS NULL OR dept_id = 0) AND is_trash = 0 AND is_archived = 0");
        $stmt->bind_param("i", $user_id);
    } else {
        $stmt = $conn->prepare("SELECT id, name, created_at as date FROM folders WHERE parent_id = ? AND user_id = ? AND (dept_id IS NULL OR dept_id = 0) AND is_trash = 0 AND is_archived = 0");
        $stmt->bind_param("ii", $parent_id, $user_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $response["folders"][] = $row;
    }
    $stmt->close();

    // ==========================================
    // 2. FETCH PRIVATE FILES
    // Ganito rin sa files, gamit ang folder_id at user_id
    // ==========================================
    if ($parent_id === NULL) {
        $stmt = $conn->prepare("SELECT id, display_name as name, storage_name, file_size as size, uploaded_at as date FROM files WHERE folder_id IS NULL AND user_id = ? AND (dept_id IS NULL OR dept_id = 0) AND is_trash = 0 AND is_archived = 0");
        $stmt->bind_param("i", $user_id);
    } else {
        $stmt = $conn->prepare("SELECT id, display_name as name, storage_name, file_size as size, uploaded_at as date FROM files WHERE folder_id = ? AND user_id = ? AND (dept_id IS NULL OR dept_id = 0) AND is_trash = 0 AND is_archived = 0");
        $stmt->bind_param("ii", $parent_id, $user_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $response["files"][] = $row;
    }
    $stmt->close();

    // Ibato ang data pabalik sa frontend
    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>