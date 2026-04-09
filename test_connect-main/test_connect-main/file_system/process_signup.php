<?php
include "db.php";

$first = $_POST['first_name'];
$last = $_POST['last_name'];
$email = $_POST['email'];
$dept = $_POST['department'];
$user = $_POST['username'];
$pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

$sql = "INSERT INTO users (first_name, last_name, email, department, username, password)
        VALUES ('$first','$last','$email','$dept','$user','$pass')";

if ($conn->query($sql) === TRUE) {
    echo "<script>
            alert('Signup successful! Please wait for admin approval.');
            window.location='index.php';
          </script>";
} else {
    echo "Error: " . $conn->error;
}
?>