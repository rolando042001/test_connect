<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: application/json');
ini_set('display_errors', 0);

try {
    include '../Backend/db.php';
    require '../Backend/rbac.php';
    require_role('admin');

    $first_name = $_POST['first_name'] ?? null;
    $last_name  = $_POST['last_name'] ?? null;
    $email      = $_POST['email'] ?? null;
    $dept_id    = $_POST['dept_id'] ?? null;

    if (!$first_name || !$last_name || !$email || !$dept_id) {
        throw new Exception("Missing required fields. Please fill up the form.");
    }

    // Default Pass & Role
    $default_pass = password_hash("EuFile2026", PASSWORD_DEFAULT);
    $imagePath = "../uploads/profiles/default.png";

    // Image Upload Handling
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $targetDir = "../uploads/profiles/";
        if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
        
        $fileName = time() . "_" . basename($_FILES['profile_picture']['name']);
        $targetFilePath = $targetDir . $fileName;
        
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetFilePath)) {
            $imagePath = $targetFilePath;
        }
    }

    // SQL INSERT base sa users table schema
    $sql = "INSERT INTO users (first_name, last_name, email, password, dept_id, profile_picture, role) 
            VALUES (?, ?, ?, ?, ?, ?, 'Staff')";

    $stmt = $conn->prepare($sql);
    // Bind as: s (string), s, s, s, i (integer), s
    $stmt->bind_param("ssssis", $first_name, $last_name, $email, $default_pass, $dept_id, $imagePath);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        // I-return ang exact SQL error para sa debugging
        throw new Exception($stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>