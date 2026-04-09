<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_login();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access. Please log in."]);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    // ==========================================
    // ACTION 1: CREATE FOLDER
    // ==========================================
    if ($action === 'create_folder') {
        $name = trim($_POST['name'] ?? '');
        $parent_id = (!empty($_POST['parent_id']) && $_POST['parent_id'] !== 'null') ? (int)$_POST['parent_id'] : NULL;

        if (empty($name)) throw new Exception("Folder name cannot be empty.");

        $stmt = $conn->prepare("INSERT INTO folders (name, parent_id, dept_id, user_id, created_at) VALUES (?, ?, NULL, ?, NOW())");
        $stmt->bind_param("sii", $name, $parent_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Folder created successfully."]);
        } else {
            throw new Exception("Failed to create folder.");
        }
        $stmt->close();
        exit;
    }

    // ==========================================
    // ACTION 2: UPLOAD FILES
    // ==========================================
    if ($action === 'upload_file') {
        $parent_id = (!empty($_POST['parent_id']) && $_POST['parent_id'] !== 'null') ? (int)$_POST['parent_id'] : NULL;
        
        $upload_dir = '../uploads/private_vault/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $successCount = 0;
        $errors = [];

        if (!empty($_FILES['files']['name'][0])) {
            foreach ($_FILES['files']['name'] as $key => $name) {
                $tmp_name = $_FILES['files']['tmp_name'][$key];
                $size = $_FILES['files']['size'][$key];
                $error = $_FILES['files']['error'][$key];

                if ($error === UPLOAD_ERR_OK) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $storage_name = uniqid('priv_') . '_' . time() . '.' . $ext;
                    $target_path = $upload_dir . $storage_name;

                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $stmt = $conn->prepare("INSERT INTO files (display_name, storage_name, folder_id, dept_id, user_id, file_size, uploaded_at) VALUES (?, ?, ?, NULL, ?, ?, NOW())");
                        $stmt->bind_param("ssiii", $name, $storage_name, $parent_id, $user_id, $size);
                        $stmt->execute();
                        $stmt->close();
                        $successCount++;
                    } else {
                        $errors[] = "Failed to move file: $name";
                    }
                } else {
                    $errors[] = "Upload error for file: $name";
                }
            }
        }

        if ($successCount > 0) {
            echo json_encode(["status" => "success", "message" => "$successCount file(s) uploaded successfully."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Upload failed.", "errors" => $errors]);
        }
        exit;
    }

    // ==========================================
    // ACTION 3: RENAME FOLDER / FILE
    // ==========================================
    if ($action === 'rename') {
        $id = (int)$_POST['id'];
        $type = $_POST['type'];
        $new_name = trim($_POST['new_name'] ?? '');

        if (empty($new_name)) throw new Exception("Name cannot be empty.");

        if ($type === 'folder') {
            $stmt = $conn->prepare("UPDATE folders SET name = ? WHERE id = ? AND user_id = ? AND dept_id IS NULL");
        } else {
            $stmt = $conn->prepare("UPDATE files SET display_name = ? WHERE id = ? AND user_id = ? AND dept_id IS NULL");
        }
        
        $stmt->bind_param("sii", $new_name, $id, $user_id);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Renamed successfully."]);
        } else {
            throw new Exception("Failed to rename item.");
        }
        $stmt->close();
        exit;
    }

    // ==========================================
    // ACTION 4: DELETE (Move to Trash) OR ARCHIVE
    // ==========================================
    if ($action === 'delete' || $action === 'archive') {
        $items = json_decode($_POST['items'], true);
        if (empty($items)) throw new Exception("No items selected.");

        $column = ($action === 'delete') ? 'is_trash' : 'is_archived';

        $conn->begin_transaction();
        try {
            $stmtFolder = $conn->prepare("UPDATE folders SET $column = 1 WHERE id = ? AND user_id = ? AND dept_id IS NULL");
            $stmtFile = $conn->prepare("UPDATE files SET $column = 1 WHERE id = ? AND user_id = ? AND dept_id IS NULL");

            foreach ($items as $item) {
                if ($item['type'] === 'folder') {
                    $stmtFolder->bind_param("ii", $item['id'], $user_id);
                    $stmtFolder->execute();
                } else if ($item['type'] === 'file') {
                    $stmtFile->bind_param("ii", $item['id'], $user_id);
                    $stmtFile->execute();
                }
            }
            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Items updated successfully."]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        exit;
    }

    // ==========================================
    // ACTION 5: PASTE (CUT / MOVE ITEMS)
    // ==========================================
    if ($action === 'paste_cut') {
        $items = json_decode($_POST['items'], true);
        $target_folder_id = (!empty($_POST['target_folder_id']) && $_POST['target_folder_id'] !== 'null') ? (int)$_POST['target_folder_id'] : NULL;

        $conn->begin_transaction();
        try {
            $stmtFolder = $conn->prepare("UPDATE folders SET parent_id = ? WHERE id = ? AND user_id = ? AND dept_id IS NULL");
            $stmtFile = $conn->prepare("UPDATE files SET folder_id = ? WHERE id = ? AND user_id = ? AND dept_id IS NULL");

            foreach ($items as $item) {
                if ($item['type'] === 'folder') {
                    $stmtFolder->bind_param("iii", $target_folder_id, $item['id'], $user_id);
                    $stmtFolder->execute();
                } else if ($item['type'] === 'file') {
                    $stmtFile->bind_param("iii", $target_folder_id, $item['id'], $user_id);
                    $stmtFile->execute();
                }
            }
            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Items moved successfully."]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        exit;
    }

    // ==========================================
    // ACTION 6: PASTE (COPY ITEMS)
    // ==========================================
    if ($action === 'paste_copy') {
        $items = json_decode($_POST['items'], true);
        $target_folder_id = (!empty($_POST['target_folder_id']) && $_POST['target_folder_id'] !== 'null') ? (int)$_POST['target_folder_id'] : NULL;

        $conn->begin_transaction();
        try {
            foreach ($items as $item) {
                if ($item['type'] === 'file') {
                    $stmt = $conn->prepare("SELECT display_name, storage_name, file_size FROM files WHERE id = ? AND user_id = ? AND dept_id IS NULL");
                    $stmt->bind_param("ii", $item['id'], $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($orig = $result->fetch_assoc()) {
                        $ext = pathinfo($orig['display_name'], PATHINFO_EXTENSION);
                        $new_storage_name = uniqid('priv_copy_') . '_' . time() . '.' . $ext;
                        $old_path = '../uploads/private_vault/' . $orig['storage_name'];
                        $new_path = '../uploads/private_vault/' . $new_storage_name;
                        
                        if (file_exists($old_path) && copy($old_path, $new_path)) {
                            $new_name = "Copy of " . $orig['display_name'];
                            $insertFile = $conn->prepare("INSERT INTO files (display_name, storage_name, folder_id, dept_id, user_id, file_size, uploaded_at) VALUES (?, ?, ?, NULL, ?, ?, NOW())");
                            $insertFile->bind_param("ssiii", $new_name, $new_storage_name, $target_folder_id, $user_id, $orig['file_size']);
                            $insertFile->execute();
                        }
                    }
                } elseif ($item['type'] === 'folder') {
                    $stmt = $conn->prepare("SELECT name FROM folders WHERE id = ? AND user_id = ? AND dept_id IS NULL");
                    $stmt->bind_param("ii", $item['id'], $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($orig = $result->fetch_assoc()) {
                        $new_name = "Copy of " . $orig['name'];
                        $insertFolder = $conn->prepare("INSERT INTO folders (name, parent_id, dept_id, user_id, created_at) VALUES (?, ?, NULL, ?, NOW())");
                        $insertFolder->bind_param("sii", $new_name, $target_folder_id, $user_id);
                        $insertFolder->execute();
                    }
                }
            }
            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Items copied successfully."]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        exit;
    }

    echo json_encode(["status" => "error", "message" => "Invalid action."]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>