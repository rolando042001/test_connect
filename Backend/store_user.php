<?php
// LEGACY shim — superseded by Backend/esp_enroll_api.php.
// Kept so the older ESP32 firmware that POSTs id/rfid_uid/passcode here
// still works. Bcrypts the passcode and uses prepared statements.
session_start();
include "db.php";
require __DIR__ . "/rbac.php";
require_role('admin');

header('Content-Type: text/plain');

if (!isset($_POST['id'], $_POST['rfid_uid'], $_POST['passcode'])) {
    echo "MISSING_DATA";
    exit;
}

$id       = (int)$_POST['id'];
$rfid     = trim($_POST['rfid_uid']);
$passcode = $_POST['passcode'];

if (!preg_match('/^[0-9]{6,8}$/', $passcode)) {
    echo "INVALID_PASSCODE";
    exit;
}

// Bcrypt to match the format the rest of the system expects.
$passcode_hash = password_hash($passcode, PASSWORD_BCRYPT);

$stmt = $conn->prepare(
    "UPDATE users SET
        passcode        = ?,
        rfid_uid        = ?,
        has_passcode    = 1,
        has_rfid        = 1,
        has_fingerprint = 1
     WHERE id = ?"
);
$stmt->bind_param("ssi", $passcode_hash, $rfid, $id);
$stmt->execute();
$ok = $stmt->affected_rows >= 0; // affected_rows can be 0 if values unchanged
$stmt->close();

echo $ok ? "USER_SAVED" : "ERROR";
$conn->close();
