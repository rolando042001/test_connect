<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_login();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $response = ["requests" => [], "departments" => []];
    try {
        // Idinagdag ang LEFT JOIN para makuha ang f.storage_name gamit ang r.file_id
        $stmt = $conn->prepare("
            SELECT r.target_file_name, d.name as dept_name, r.request_date, r.reason, r.status, f.storage_name 
            FROM access_requests r
            JOIN departments d ON r.dept_id = d.id
            LEFT JOIN files f ON r.file_id = f.id
            WHERE r.user_id = ?
            ORDER BY r.request_date DESC
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['status'] = ucfirst($row['status']); 
            $response["requests"][] = $row;
        }
        $stmt->close();

        // Departments for Dropdown (Optional na lang ito dahil auto-fill na ang Modal, pero maganda panatilihin para sa manual request form kung sakali)
        $stmt = $conn->prepare("SELECT id, name FROM departments ORDER BY name ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $response["departments"][] = $row;
        }
        $stmt->close();

        echo json_encode(["status" => "success", "data" => $response]);
    } catch (Exception $e) { 
        echo json_encode(["status" => "error", "message" => $e->getMessage()]); 
    }
} 
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dept_id = isset($_POST['dept_id']) ? (int)$_POST['dept_id'] : 0;
    $file_id = (isset($_POST['file_id']) && $_POST['file_id'] !== "") ? (int)$_POST['file_id'] : NULL; // CAPTURE FILE ID
    $file_name = trim($_POST['file_name'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if (empty($dept_id) || empty($file_name) || empty($reason)) {
        echo json_encode(["status" => "error", "message" => "Please fill in all required fields."]);
        exit;
    }

    try {
        // Insert query isinama ang file_id
        $stmt = $conn->prepare("INSERT INTO access_requests (user_id, dept_id, file_id, target_file_name, reason, status, request_date) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("iiiss", $user_id, $dept_id, $file_id, $file_name, $reason);
        
        if ($stmt->execute()) { 
            echo json_encode(["status" => "success", "message" => "Request submitted successfully!"]); 
        } else { 
            throw new Exception("Failed to save request."); 
        }
        $stmt->close();
    } catch (Exception $e) { 
        echo json_encode(["status" => "error", "message" => $e->getMessage()]); 
    }
}
?>