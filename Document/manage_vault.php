<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function recursiveUpdateDept($conn, $folder_id, $new_dept_id) {
    $stmt = $conn->prepare("UPDATE files SET dept_id = ? WHERE folder_id = ?");
    $stmt->bind_param("ii", $new_dept_id, $folder_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("SELECT id FROM folders WHERE parent_id = ?");
    $stmt->bind_param("i", $folder_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($sub = $result->fetch_assoc()) {
        $sub_id = $sub['id'];
        $upd = $conn->prepare("UPDATE folders SET dept_id = ? WHERE id = ?");
        $upd->bind_param("ii", $new_dept_id, $sub_id);
        $upd->execute();
        $upd->close();
        recursiveUpdateDept($conn, $sub_id, $new_dept_id);
    }
    $stmt->close();
}

function recursiveCopy($conn, $source_id, $target_parent, $target_dept, $user_id, $is_top = false) {
    $stmt = $conn->prepare("SELECT name FROM folders WHERE id = ?");
    $stmt->bind_param("i", $source_id);
    $stmt->execute();
    $folder = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$folder) return;

    $name = $is_top ? $folder['name'] . " - Copy" : $folder['name'];
    $stmt = $conn->prepare("INSERT INTO folders (parent_id, dept_id, name, created_at, is_trash, is_archived) VALUES (?, ?, ?, NOW(), 0, 0)");
    $stmt->bind_param("iis", $target_parent, $target_dept, $name);
    $stmt->execute();
    $new_id = $conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("
        INSERT INTO files (dept_id, folder_id, uploader_id, display_name, storage_name, file_type, file_size, is_trash, is_archived) 
        SELECT ?, ?, IF(? > 0, ?, uploader_id), display_name, storage_name, file_type, file_size, 0, 0 
        FROM files WHERE folder_id = ? AND is_trash = 0
    ");
    $stmt->bind_param("iiiii", $target_dept, $new_id, $user_id, $user_id, $source_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("SELECT id FROM folders WHERE parent_id = ? AND is_trash = 0");
    $stmt->bind_param("i", $source_id);
    $stmt->execute();
    $subs = $stmt->get_result();
    while ($sub = $subs->fetch_assoc()) {
        recursiveCopy($conn, $sub['id'], $new_id, $target_dept, $user_id, false);
    }
    $stmt->close();
}

try {
    $action = $_POST['action'] ?? '';
    $parent_id = (isset($_POST['parent_id']) && $_POST['parent_id'] !== '') ? intval($_POST['parent_id']) : null;
    $dept_id = (isset($_POST['dept_id']) && $_POST['dept_id'] !== '') ? intval($_POST['dept_id']) : null;
    $target_folder_id = (isset($_POST['target_folder_id']) && $_POST['target_folder_id'] !== '') ? intval($_POST['target_folder_id']) : null;
    
    // Kunin ang array ng items na ipinasa mula sa JS (para sa Batch Actions)
    $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] == 0) {
        throw new Exception("Session expired. Please log in again.");
    }
    $user_id = $_SESSION['user_id'];

    if (!$action) throw new Exception("Action is required.");

    // 1. CREATE FOLDER
    if ($action === 'create_folder') {
        $name = trim($_POST['name'] ?? '');
        if (!$name) throw new Exception("Folder name required.");
        $stmt = $conn->prepare("INSERT INTO folders (parent_id, dept_id, name, created_at, is_trash, is_archived) VALUES (?, ?, ?, NOW(), 0, 0)");
        $stmt->bind_param("iis", $parent_id, $dept_id, $name);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success']);
    }

    // 2. UPLOAD FILE
    elseif ($action === 'upload_file') {
        if (!isset($_FILES['files'])) throw new Exception("No files selected.");
        $upload_dir = '../uploads/vault/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        foreach ($_FILES['files']['name'] as $i => $fname) {
            if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
            $storage_name = time() . '_' . uniqid() . '.' . $ext;
            
            if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $upload_dir . $storage_name)) {
                $stmt = $conn->prepare("INSERT INTO files (dept_id, folder_id, uploader_id, display_name, storage_name, file_type, file_size, is_trash, is_archived) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0)");
                $stmt->bind_param("iiisssi", $dept_id, $parent_id, $user_id, $fname, $storage_name, $ext, $_FILES['files']['size'][$i]);
                $stmt->execute();
                $stmt->close();
            }
        }
        echo json_encode(['status' => 'success']);
    }

    // 3. BATCH DELETE (Kasama rin dito ang Single Delete)
    elseif ($action === 'delete') {
        if (empty($items)) throw new Exception("No items provided for deletion.");
        
        foreach ($items as $item) {
            $id = intval($item['id']);
            $table = ($item['type'] === 'folder') ? 'folders' : 'files';
            $stmt = $conn->prepare("UPDATE $table SET is_trash = 1 WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['status' => 'success']);
    }

    // 4. BATCH PASTE (CUT OR COPY)
    elseif ($action === 'paste_cut' || $action === 'paste_copy') {
        if (empty($items)) throw new Exception("No items to paste.");
        if ($dept_id === null) throw new Exception("Target Department ID is missing.");

        foreach ($items as $item) {
            $id = intval($item['id']);
            $type = $item['type'];

            if ($action === 'paste_cut') {
                if ($type === 'folder') {
                    $stmt = $conn->prepare("UPDATE folders SET parent_id = ?, dept_id = ? WHERE id = ?");
                    $stmt->bind_param("iii", $target_folder_id, $dept_id, $id);
                    $stmt->execute();
                    $stmt->close();
                    recursiveUpdateDept($conn, $id, $dept_id);
                } else {
                    $stmt = $conn->prepare("UPDATE files SET folder_id = ?, dept_id = ? WHERE id = ?");
                    $stmt->bind_param("iii", $target_folder_id, $dept_id, $id);
                    $stmt->execute();
                    $stmt->close();
                }
            } 
            elseif ($action === 'paste_copy') {
                if ($type === 'folder') {
                    recursiveCopy($conn, $id, $target_folder_id, $dept_id, $user_id, true);
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO files (dept_id, folder_id, uploader_id, display_name, storage_name, file_type, file_size, is_trash, is_archived) 
                        SELECT ?, ?, IF(? > 0, ?, uploader_id), CONCAT(display_name, ' - Copy'), storage_name, file_type, file_size, 0, 0 
                        FROM files WHERE id = ?
                    ");
                    $stmt->bind_param("iiiii", $dept_id, $target_folder_id, $user_id, $user_id, $id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        echo json_encode(['status' => 'success', 'message' => 'Items pasted successfully.']);
    } 
    else {
        throw new Exception("Invalid action.");
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}