<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = $_POST['status'] ?? null;

// Siguraduhing valid ang data na pumasok
if (!$id || !$status || !in_array($status, ['approved', 'denied'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid required fields.']);
    exit;
}

// 1. I-update ang status sa access_requests table
$sql = "UPDATE access_requests SET status = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    // 2. Kunin ang email ng user para sa logging
    // NOTE: Ginamit ko ang 'personnel' table base sa setup natin kanina. 
    // Kung 'users' ang pangalan ng table mo, palitan ang 'personnel p' ng 'users p'.
    $info_sql = "SELECT u.email FROM users u
                 JOIN access_requests ar ON u.id = ar.user_id
                 WHERE ar.id = ?";
    $info_stmt = $conn->prepare($info_sql);
    $info_stmt->bind_param("i", $id);
    $info_stmt->execute();
    $result = $info_stmt->get_result();
    
    $user_email = 'Unknown User';
    if ($row = $result->fetch_assoc()) {
        $user_email = $row['email'];
    }
    $info_stmt->close();

    // 3. Itala ang aksyon sa audit_logs gamit ang iyong function sa db.php
    $log_msg = "Request " . strtoupper($status);
    
    // Imbes na '0', mas maganda kung kukunin natin ang ID ng admin na nag-approve para mas malinaw sa Audit Log
    $admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; 
    
    // I-trigger ang function kung nag-e-exist ito sa db.php mo
    if (function_exists('logActivity')) {
        logActivity($conn, $admin_id, $log_msg, $user_email, 'Access Request'); 
    }

    echo json_encode(['status' => 'success', 'message' => 'Request updated successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update database: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>