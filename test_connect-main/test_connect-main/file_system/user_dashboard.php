<?php
session_start();
include "db.php";

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
}
?>

<h2>User Dashboard</h2>
<p>Welcome <?php echo $_SESSION['user']; ?></p>

<a href="update_auth_request.php">
    <button>Update Authentication</button>
</a>
<a href="logout.php">Logout</a>