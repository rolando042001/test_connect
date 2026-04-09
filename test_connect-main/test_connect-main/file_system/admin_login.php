<?php
session_start();
include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM admins WHERE username='$username'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        if (password_verify($password, $row['password'])) {
            $_SESSION['admin'] = $username;
            header("Location: admin_dashboard.php");
        } else {
            echo "Wrong password";
        }
    } else {
        echo "Admin not found";
    }
}
?>

<form method="POST">
<h2>Admin Login</h2>
Username: <input type="text" name="username"><br><br>
Password: <input type="password" name="password"><br><br>
<button type="submit">Login</button>
</form>