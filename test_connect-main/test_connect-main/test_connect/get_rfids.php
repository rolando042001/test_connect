<?php
// Returns the RFID UIDs of every enrolled user as a JSON array. Used
// by register.html's "Select RFID UID" dropdown.
//
// Fixes vs original:
//   - Object-oriented mysqli style for consistency
//   - Explicit application/json Content-Type
//   - Skips empty strings (was including them and breaking the dropdown)
include "db.php";
header('Content-Type: application/json');

$result = $conn->query(
    "SELECT rfid_uid FROM users
      WHERE rfid_uid IS NOT NULL AND rfid_uid <> ''
   ORDER BY id ASC"
);

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

echo json_encode($rows);
