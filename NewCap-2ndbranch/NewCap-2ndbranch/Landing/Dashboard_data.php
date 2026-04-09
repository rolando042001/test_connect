<?php
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');

// Total vault quota in bytes — change this if you provision more storage.
const VAULT_QUOTA_BYTES = 2 * 1024 * 1024 * 1024; // 2 GB

$response = [
    'access_requests'    => 0,
    'total_size_bytes'   => 0,
    'vault_quota_bytes'  => VAULT_QUOTA_BYTES,
    'archived_count'     => 0,
    'trash_count'        => 0,
    'storage_distribution' => [],
    'weekly_activity'    => ['labels' => [], 'data' => []],
    'recent_uploads'     => []
];

try {
    // 1. PENDING ACCESS REQUESTS (I-adjust ang table name kung iba sa db mo)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM access_requests WHERE status = 'pending'");
    if ($row = $stmt->fetch_assoc()) {
        $response['access_requests'] = (int)$row['count'];
    }

    // 2. TOTAL VAULT CAPACITY USED (in BYTES) - Active files only
    $stmt = $conn->query("SELECT SUM(file_size) as total_size FROM files WHERE is_trash = 0 AND is_archived = 0");
    if ($row = $stmt->fetch_assoc()) {
        $response['total_size_bytes'] = (int)$row['total_size'];
    }

    // 3. ARCHIVED COUNT (Files + Folders)
    $stmt = $conn->query("SELECT (SELECT COUNT(*) FROM files WHERE is_archived = 1) + (SELECT COUNT(*) FROM folders WHERE is_archived = 1) as total_archived");
    if ($row = $stmt->fetch_assoc()) {
        $response['archived_count'] = (int)$row['total_archived'];
    }

    // 4. TRASH COUNT (Files + Folders)
    $stmt = $conn->query("SELECT (SELECT COUNT(*) FROM files WHERE is_trash = 1) + (SELECT COUNT(*) FROM folders WHERE is_trash = 1) as total_trash");
    if ($row = $stmt->fetch_assoc()) {
        $response['trash_count'] = (int)$row['total_trash'];
    }

// 5. STORAGE DISTRIBUTION (Lilitaw lahat ng Departments kahit 0 files)
    $stmt = $conn->query("
        SELECT 
            d.name as dept_name,
            COUNT(f.id) as file_count
        FROM departments d
        LEFT JOIN files f ON d.id = f.dept_id AND f.is_trash = 0 AND f.is_archived = 0
        GROUP BY d.id, d.name
        ORDER BY d.id ASC
    ");
    
    $response['storage_distribution'] = [];
    while ($row = $stmt->fetch_assoc()) {
        $response['storage_distribution'][] = [
            'name' => $row['dept_name'],
            'file_count' => (int)$row['file_count']
        ];
    }

    // 6. WEEKLY UPLOAD ACTIVITY (Bar Chart) - Last 7 Days
    $stmt = $conn->query("
        SELECT DATE(uploaded_at) as upload_date, COUNT(*) as daily_count
        FROM files
        WHERE uploaded_at >= DATE(NOW()) - INTERVAL 7 DAY
        GROUP BY DATE(uploaded_at)
        ORDER BY DATE(uploaded_at) ASC
    ");
    $days = [];
    $counts = [];
    while ($row = $stmt->fetch_assoc()) {
        // Format date to 'Mon', 'Tue', etc.
        $dayName = date('D', strtotime($row['upload_date'])); 
        $days[] = $dayName;
        $counts[] = (int)$row['daily_count'];
    }
    // Kung walang nag-upload in the past 7 days, mag-set ng default labels
    if (empty($days)) {
        $response['weekly_activity']['labels'] = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $response['weekly_activity']['data'] = [0, 0, 0, 0, 0, 0, 0];
    } else {
        $response['weekly_activity']['labels'] = $days;
        $response['weekly_activity']['data'] = $counts;
    }

    // 7. RECENT UPLOADS TABLE (Top 50 latest active files)
    $stmt = $conn->query("
        SELECT f.display_name, f.file_size, f.uploaded_at, u.first_name, d.name as dept_name
        FROM files f
        LEFT JOIN users u ON f.uploader_id = u.id
        LEFT JOIN departments d ON f.dept_id = d.id
        WHERE f.is_trash = 0 AND f.is_archived = 0
        ORDER BY f.uploaded_at DESC
        LIMIT 50
    ");
    while ($row = $stmt->fetch_assoc()) {
        $response['recent_uploads'][] = $row;
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>