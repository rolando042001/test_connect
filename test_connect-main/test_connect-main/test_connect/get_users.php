<?php
// Lists every enrolled user with their RFID UID + per-factor flags.
//
// Fixes vs original:
//   - Returns useful columns (was returning JUST the id field which
//     was unusable from any front-end)
//   - Object-oriented mysqli style for consistency with the rest
//   - Explicit application/json header
//   - Never leaks the bcrypt hash
include "db.php";
header('Content-Type: application/json');

$result = $conn->query(
    "SELECT id, rfid_uid, has_passcode, has_rfid, has_fingerprint
       FROM users
   ORDER BY id ASC"
);

$users = [];
while ($row = $result->fetch_assoc()) {
    $row['has_passcode']    = (int) $row['has_passcode'];
    $row['has_rfid']        = (int) $row['has_rfid'];
    $row['has_fingerprint'] = (int) $row['has_fingerprint'];
    $users[] = $row;
}

echo json_encode($users);
