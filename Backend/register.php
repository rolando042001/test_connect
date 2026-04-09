<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); 
header('Content-Type: application/json');
require 'db.php'; // Gagamit ng iyong $conn at port 3307

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid data input.']);
    exit;
}

$first_name = $data['first_name'];
$last_name = $data['last_name'];
$dept_name = $data['department'];
$email = $data['email'];
$password = password_hash($data['password'], PASSWORD_DEFAULT);

// Hanapin ang dept_id base sa pangalan
$dept_query = $conn->prepare("SELECT id FROM departments WHERE name = ?");
$dept_query->bind_param("s", $dept_name);
$dept_query->execute();
$result = $dept_query->get_result();
$dept = $result->fetch_assoc();

if (!$dept) {
    echo json_encode(['status' => 'error', 'msg' => 'Department not found.']);
    exit;
}

$dept_id = $dept['id'];

// I-save ang user sa database
$stmt = $conn->prepare("INSERT INTO users (first_name, last_name, dept_id, email, password) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("ssiss", $first_name, $last_name, $dept_id, $email, $password);

if ($stmt->execute()) {
    $new_user_id = $conn->insert_id;
    logActivity($conn, $new_user_id, 'Registration', $email, 'User'); // Gamit ang iyong function
    echo json_encode(['status' => 'success', 'msg' => 'Account created successfully.']);
} else {
    echo json_encode(['status' => 'error', 'msg' => 'Registration failed. Email exists.']);
}

$stmt->close();
$conn->close();
?>