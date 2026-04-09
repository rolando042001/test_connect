<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');

try {
    // UNION query para makuha ang folders at files na nasa trash
    $sql = "
        SELECT id, name, dept_id, 'folder' as type, deleted_at 
        FROM folders WHERE is_trash = 1
        UNION
        SELECT id, display_name as name, dept_id, file_type as type, deleted_at 
        FROM files WHERE is_trash = 1
        ORDER BY deleted_at DESC
    ";
    
    $result = $conn->query($sql);
    $items = [];

    while($row = $result->fetch_assoc()) {
        // Countdown Calculation
        $deletedDate = new DateTime($row['deleted_at']);
        $now = new DateTime();
        $interval = $deletedDate->diff($now);
        $daysPassed = $interval->days;
        
        // 30 days grace period logic
        $row['days_remaining'] = max(0, 30 - $daysPassed);
        $items[] = $row;
    }
    
    echo json_encode($items);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}