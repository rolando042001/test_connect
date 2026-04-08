<?php
session_start();
include "db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
}
?>

<h2>Admin Dashboard</h2>
<a href="add_user.php">Add New User</a> |
<a href="logout.php">Logout</a>

<hr>

<h3>Pending Approvals</h3>

<?php
$result = $conn->query("SELECT * FROM users WHERE status='pending'");

while($row = $result->fetch_assoc()) {
    echo $row['first_name']." ".$row['last_name']." - ".$row['department'];
    echo " <a href='approve_user.php?id=".$row['id']."'>Approve</a>";
    echo " <a href='delete_user.php?id=".$row['id']."'>Reject</a>";
    echo "<br><br>";
}
?>

<hr>

<h3>All Users</h3>

<?php
$result = $conn->query("SELECT * FROM users");

while($row = $result->fetch_assoc()) {
    echo "ID: ".$row['id']." | ".$row['username']." | ".$row['department']." | Status: ".$row['status']." | Enabled: ".$row['login_enabled']."<br>";

    echo "<a href='edit_user.php?id=".$row['id']."'>Edit</a> | ";
    echo "<a href='delete_user.php?id=".$row['id']."'>Delete</a> | ";
    echo "<a href='disable_user.php?id=".$row['id']."'>Disable/Enable</a>";
    echo "<hr>";
}
?>