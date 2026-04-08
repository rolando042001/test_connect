<?php
include "db.php";

$id = $_GET['id'];
$result = $conn->query("SELECT * FROM users WHERE id=$id");
$row = $result->fetch_assoc();
$conn->query("UPDATE users SET enroll_request=1 WHERE id=$id");
?>

<form action="update_user.php" method="POST">
<input type="hidden" name="id" value="<?php echo $row['id']; ?>">

Username: <input type="text" name="username" value="<?php echo $row['username']; ?>"><br><br>
New Password: <input type="password" name="password"><br><br>

RFID UID: <input type="text" name="rfid_uid" value="<?php echo $row['rfid_uid']; ?>"><br><br>
Fingerprint ID: <input type="number" name="fingerprint_id" value="<?php echo $row['fingerprint_id']; ?>"><br><br>
Keypad Password: <input type="text" name="keypad_password" value="<?php echo $row['keypad_password']; ?>"><br><br>

Authentication Mode:
<select name="auth_mode">
    <option value="RFID">RFID</option>
    <option value="FINGERPRINT">FINGERPRINT</option>
    <option value="KEYPAD">KEYPAD</option>
    <option value="MULTI">MULTI</option>
</select><br><br>

<button type="submit">Update</button>
</form>