<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: application/json');

try {
    include '../Backend/db.php';
    require '../Backend/rbac.php';
    require_role('admin');

    $id = $_POST['id'] ?? null;
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $dept_id = $_POST['dept_id'] ?? null;

    // Admin ID (for audit log)
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$id || !$dept_id) {
        throw new Exception("Missing required ID or Department.");
    }

    $profile_picture = null;
    $image_query = "";

    /* ===============================
       PROFILE PICTURE UPLOAD
    =============================== */
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {

        $targetDir = "../uploads/profiles/";

        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = time() . "_" . basename($_FILES['profile_picture']['name']);
        $targetFilePath = $targetDir . $fileName;

        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetFilePath)) {
            $profile_picture = $targetFilePath;
            $image_query = ", profile_picture = ?";
        }
    }

    /* ===============================
       UPDATE QUERY
    =============================== */

    $sql = "UPDATE users 
            SET first_name = ?, last_name = ?, email = ?, dept_id = ? 
            $image_query
            WHERE id = ?";

    $stmt = $conn->prepare($sql);

    if ($profile_picture) {

        $stmt->bind_param(
            "sssisi",
            $first_name,
            $last_name,
            $email,
            $dept_id,
            $profile_picture,
            $id
        );

    } else {

        $stmt->bind_param(
            "sssii",
            $first_name,
            $last_name,
            $email,
            $dept_id,
            $id
        );
    }

    /* ===============================
       EXECUTE UPDATE
    =============================== */

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    /* ===============================
       AUDIT LOG (SAFE)
    =============================== */

    if ($user_id !== null) {
        logActivity(
            $conn,
            $user_id,
            'Update Staff Profile',
            "$first_name $last_name (ID: $id)",
            'User Management'
        );
    }

    echo json_encode([
        "success" => true,
        "message" => "Staff profile updated successfully."
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>