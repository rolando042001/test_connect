<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_login();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['dept_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit;
}

$user_dept_id = $_SESSION['dept_id'];
$parent_id = (isset($_GET['parent_id']) && $_GET['parent_id'] !== "" && $_GET['parent_id'] !== "null") ? $_GET['parent_id'] : NULL;
$dept_id = (isset($_GET['dept_id']) && $_GET['dept_id'] !== "" && $_GET['dept_id'] !== "null") ? $_GET['dept_id'] : NULL;

$response = ["folders" => [], "files" => []];

try {
    // ==========================================
    // ROOT LEVEL: Ipakita lahat ng Departments
    // ==========================================
    if ($parent_id === NULL && $dept_id === NULL) {
        $stmt = $conn->prepare("SELECT id, name FROM departments ORDER BY name ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // I-lock kung hindi ito ang department ng user
            $is_locked = ($row['id'] != $user_dept_id); 
            
            $response["folders"][] = [
                "id" => "dept_" . $row['id'], // Special ID format para ma-detect sa JS
                "name" => $row['name'],
                "type" => "folder",
                "is_locked" => $is_locked,
                "date" => "System Directory"
            ];
        }
        $stmt->close();
    } 
    // ==========================================
    // INSIDE A DEPARTMENT: Ipakita ang Folders & Files
    // ==========================================
    else {
        // Security Check: Siguraduhing ang dept_id na ina-access ay ang dept_id ng user
        if ($dept_id != $user_dept_id) {
             throw new Exception("Access Denied. Folder is locked.");
        }

        // Fetch Folders
        if ($parent_id === NULL) {
            $stmt = $conn->prepare("SELECT id, name, created_at as date FROM folders WHERE parent_id IS NULL AND dept_id = ? AND is_trash = 0 AND is_archived = 0");
            $stmt->bind_param("i", $dept_id);
        } else {
            $stmt = $conn->prepare("SELECT id, name, created_at as date FROM folders WHERE parent_id = ? AND dept_id = ? AND is_trash = 0 AND is_archived = 0");
            $stmt->bind_param("ii", $parent_id, $dept_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['is_locked'] = false; // Wala ng lock kapag nasa loob na ng sariling dept
            $row['type'] = 'folder';
            $response["folders"][] = $row;
        }
        $stmt->close();

        // Fetch Files
        if ($parent_id === NULL) {
            $stmt = $conn->prepare("SELECT id, display_name as name, storage_name, file_size as size, uploaded_at as date FROM files WHERE folder_id IS NULL AND dept_id = ? AND is_trash = 0 AND is_archived = 0");
            $stmt->bind_param("i", $dept_id);
        } else {
            $stmt = $conn->prepare("SELECT id, display_name as name, storage_name, file_size as size, uploaded_at as date FROM files WHERE folder_id = ? AND dept_id = ? AND is_trash = 0 AND is_archived = 0");
            $stmt->bind_param("ii", $parent_id, $dept_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['is_locked'] = false;
            $row['type'] = 'file';
            $response["files"][] = $row;
        }
        $stmt->close();
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>