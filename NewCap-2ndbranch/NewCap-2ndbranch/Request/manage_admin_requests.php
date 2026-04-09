<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';

// Admin-only endpoint
require_role('admin');

// ---------------------------------------------------------
// GET: Kukunin lahat ng requests mula sa lahat ng users
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $response = [];

    try {
        // I-join ang personnel table para makuha ang pangalan ng nag-request
        // NOTE: Kung iba ang pangalan ng columns mo sa personnel table (tulad ng 'name' o 'username'), palitan ang 'p.first_name, p.last_name'
        $query = "
            SELECT r.id, p.first_name, p.last_name, r.target_file_name, d.name as dept_name, r.request_date, r.reason, r.status 
            FROM access_requests r
            JOIN users p ON r.user_id = p.id
            JOIN departments d ON r.dept_id = d.id
            ORDER BY r.request_date DESC
        ";
        
        $result = $conn->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['requester'] = $row['first_name'] . ' ' . $row['last_name']; // Pagsamahin ang pangalan
                $row['status_display'] = ucfirst($row['status']); // Capitalize for UI
                $response[] = $row;
            }
        }
        
        echo json_encode(["status" => "success", "data" => $response]);

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} 
// ---------------------------------------------------------
// POST: I-update ang status (Approve / Deny)
// ---------------------------------------------------------
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $new_status = $_POST['status'] ?? ''; // 'approved' o 'denied'

    if (empty($request_id) || !in_array($new_status, ['approved', 'denied'])) {
        echo json_encode(["status" => "error", "message" => "Invalid request parameters."]);
        exit;
    }

    try {
        $stmt = $conn->prepare("UPDATE access_requests SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $request_id);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Request " . ucfirst($new_status) . " successfully."]);
        } else {
            throw new Exception("Failed to update status.");
        }
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
}
?>