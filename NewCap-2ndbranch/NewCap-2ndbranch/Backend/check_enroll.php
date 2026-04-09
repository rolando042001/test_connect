<?php
// Polled by the ESP32 every couple of seconds.
// Returns "1,<user_id>" if an enrollment is armed, "0,0" otherwise.
include "db.php";
header('Content-Type: text/plain');

// MOST RECENTLY armed enrollment wins. start_esp32_enroll also clears any
// older active rows defensively, but ORDER BY updated_at DESC means even if
// stale rows leak in we still pick the right user.
$res = $conn->query(
    "SELECT user_id, step FROM enroll_requests
     WHERE active = 1
     ORDER BY updated_at DESC
     LIMIT 1"
);
if ($res && $row = $res->fetch_assoc()) {
    echo "1," . (int)$row['user_id'];
} else {
    echo "0,0";
}
$conn->close();
