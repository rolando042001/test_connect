<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); 
session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');

// Pinalitan natin ang 'personnel p' ng 'users u' base sa database mo.
// NOTE: Siguraduhin na may columns na 'first_name', 'last_name', at 'role' sa loob ng 'users' table mo. 
// Kung ang column mo ay 'name' lang, palitan ang 'u.first_name, u.last_name' ng 'u.name'.
$query = "
    SELECT r.id, u.first_name, u.last_name, u.role, r.target_file_name AS folder, r.reason, DATE_FORMAT(r.request_date, '%b %d, %Y') AS request_date, r.status 
    FROM access_requests r 
    JOIN users u ON r.user_id = u.id 
    ORDER BY r.request_date DESC
";

$result = $conn->query($query);
$requests = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
} else {
    // Para makita mo agad kung may iba pang error sa column names
    echo json_encode(["status" => "error", "message" => $conn->error]);
    exit;
}

echo json_encode($requests);
?>