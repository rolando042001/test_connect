<?php
session_start();
header('Content-Type: application/json');
include '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');

// Per-department file totals (active files only). Computed once and
// joined back, so the file_count / storage_bytes columns reflect the
// whole department, not just files this particular user uploaded —
// which is what the Departments page actually wants to display.
$sql = "
    SELECT
        u.id, u.first_name, u.last_name, u.email, u.role, u.profile_picture, u.dept_id,
        u.has_passcode, u.has_rfid, u.has_fingerprint,
        d.name AS department_name,
        IFNULL(ds.file_count, 0)    AS file_count,
        IFNULL(ds.storage_bytes, 0) AS storage_bytes
    FROM users u
    JOIN departments d ON u.dept_id = d.id
    LEFT JOIN (
        SELECT dept_id,
               COUNT(*)         AS file_count,
               SUM(file_size)   AS storage_bytes
        FROM files
        WHERE is_trash = 0 AND is_archived = 0
        GROUP BY dept_id
    ) ds ON ds.dept_id = u.dept_id
    WHERE u.status = 'Active'
    ORDER BY d.name, u.last_name, u.first_name
";

$result = $conn->query($sql);
$staffList = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['has_passcode']    = (int)$row['has_passcode'];
        $row['has_rfid']        = (int)$row['has_rfid'];
        $row['has_fingerprint'] = (int)$row['has_fingerprint'];
        $row['file_count']      = (int)$row['file_count'];
        $row['storage_bytes']   = (int)$row['storage_bytes'];
        $staffList[] = $row;
    }
}

echo json_encode($staffList);
$conn->close();
