<?php
session_start();
include "db.php";

$username = $_POST['username'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE username='$username'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();

    // 1️⃣ Check if account is approved
    if ($row['status'] != 'approved') {
        echo "<script>
                alert('Your account is not yet approved.');
                window.location='login.php';
              </script>";
        exit();
    }

    // 2️⃣ Check if login is enabled
    if ($row['login_enabled'] == 0) {
        echo "<script>
                alert('Your account is disabled.');
                window.location='login.php';
              </script>";
        exit();
    }

    // 3️⃣ Verify password
    if (password_verify($password, $row['password'])) {

        $_SESSION['user'] = $row['username'];
        $_SESSION['user_id'] = $row['id'];

        // 4️⃣ CHECK MULTI-AUTH ENROLLMENT HERE 👇
        if ($row['rfid_uid'] == NULL || 
            $row['fingerprint_id'] == NULL || 
            $row['keypad_password'] == NULL) {

            header("Location: multi_auth.php");
            exit();
        }

        // 5️⃣ If already enrolled → go to dashboard
        header("Location: user_dashboard.php");
        exit();

    } else {
        echo "Incorrect password.";
    }

} else {
    echo "User not found.";
}
?>