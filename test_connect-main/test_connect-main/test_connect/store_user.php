<?php
// Persists a freshly enrolled user (called by the ESP32 firmware after
// it captures RFID + fingerprint slot id + keypad PIN).
//
// Fixes vs original:
//   - Prepared statement (was string interpolation; rfid_uid was an
//     unfiltered SQL injection vector)
//   - Validates rfid_uid format (uppercase hex, 4-20 chars) before write
//   - Bcrypts the passcode at rest instead of storing plaintext
//   - Returns explicit ASCII tokens the ESP32 firmware can branch on
include "db.php";
header('Content-Type: text/plain');

if (!isset($_POST['id'], $_POST['rfid_uid'], $_POST['passcode'])) {
    echo "MISSING_DATA";
    exit;
}

$id       = (int) $_POST['id'];
$rfid     = strtoupper(trim($_POST['rfid_uid']));
$passcode = $_POST['passcode'];

if ($id <= 0) {
    echo "INVALID_ID";
    exit;
}
if (!preg_match('/^[0-9A-F]{4,20}$/', $rfid)) {
    echo "INVALID_RFID";
    exit;
}
if (!preg_match('/^[0-9]{6,8}$/', $passcode)) {
    echo "INVALID_PASSCODE";
    exit;
}

// Bcrypt at rest. The plaintext PIN is never stored.
$passcode_hash = password_hash($passcode, PASSWORD_BCRYPT);

$stmt = $conn->prepare(
    "INSERT INTO users
        (id, passcode, rfid_uid, has_passcode, has_rfid, has_fingerprint)
     VALUES (?, ?, ?, 1, 1, 1)
     ON DUPLICATE KEY UPDATE
        passcode = VALUES(passcode),
        rfid_uid = VALUES(rfid_uid),
        has_passcode = 1,
        has_rfid = 1,
        has_fingerprint = 1"
);
$stmt->bind_param("iss", $id, $passcode_hash, $rfid);
$stmt->execute();
$stmt->close();

echo "USER_SAVED";
