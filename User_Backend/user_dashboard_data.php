<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_login();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['dept_id'])) {
    echo json_encode(["error" => "Unauthorized access."]);
    exit;
}

$user_id = $_SESSION['user_id'];
$dept_id = $_SESSION['dept_id'];

$response = [
    "total_my_files" => 0,
    "my_storage_used" => 0,
    "pending_requests" => 0,
    "dept_shared_size" => 0,
    "recent_files" => [],
    "chart_data" => [
        "documents" => 0,
        "spreadsheets" => 0,
        "images" => 0,
        "others" => 0
    ]
];

try {
    // 1. Get User's Total Files and Storage Size (Private & Uploaded by them in Dept)
    $stmt = $conn->prepare("SELECT COUNT(id) as count, IFNULL(SUM(file_size), 0) as total_size FROM files WHERE user_id = ? AND is_trash = 0 AND is_archived = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $response['total_my_files'] = (int)$res['count'];
    $response['my_storage_used'] = (int)$res['total_size']; // In bytes
    $stmt->close();

    // 2. Get Department Shared Size
    $stmt = $conn->prepare("SELECT IFNULL(SUM(file_size), 0) as total_size FROM files WHERE dept_id = ? AND is_trash = 0 AND is_archived = 0");
    $stmt->bind_param("i", $dept_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $response['dept_shared_size'] = (int)$res['total_size'];
    $stmt->close();

    // 3. Get Recent Files (Top 5)
    $stmt = $conn->prepare("SELECT display_name, file_size, uploaded_at, dept_id FROM files WHERE user_id = ? AND is_trash = 0 AND is_archived = 0 ORDER BY uploaded_at DESC LIMIT 5");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['recent_files'][] = $row;
        
        // Categorize for chart
        $ext = strtolower(pathinfo($row['display_name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf', 'doc', 'docx', 'txt'])) $response['chart_data']['documents']++;
        elseif (in_array($ext, ['xls', 'xlsx', 'csv'])) $response['chart_data']['spreadsheets']++;
        elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $response['chart_data']['images']++;
        else $response['chart_data']['others']++;
    }
    $stmt->close();

    // Note: Pending requests is set to 0 for now until you create a requests table
    // $response['pending_requests'] = ...

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>