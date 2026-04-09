<?php
// ESP32 POSTs the captured RFID UID, fingerprint slot id, and keypad
// passcode. We persist them on the user record and clear the enrollment
// flag. Matches the "Generate 3 authentication" branch in the diagram.
include "db.php";
header('Content-Type: text/plain');

$user_id        = isset($_POST['user_id'])        ? (int)$_POST['user_id'] : 0;
$rfid           = $_POST['rfid']                  ?? '';
$fingerprint_id = isset($_POST['fingerprint_id']) ? (int)$_POST['fingerprint_id'] : 0;
$passcode       = $_POST['keypad']                ?? '';

if (!$user_id) { echo "MISSING_USER"; exit; }

// Basic passcode shape (matches store_user.php contract: 6-8 digits)
if ($passcode !== '' && !preg_match('/^[0-9]{6,8}$/', $passcode)) {
    echo "INVALID_PASSCODE";
    exit;
}

$has_rfid   = $rfid !== '' ? 1 : 0;
$has_finger = $fingerprint_id > 0 ? 1 : 0;
$has_pass   = $passcode !== '' ? 1 : 0;

// Bcrypt the passcode so it matches the format used by update_passcode.php
// and verify_mfa.php / hardware_verify.php (which call password_verify).
$passcode_hash = $passcode !== '' ? password_hash($passcode, PASSWORD_BCRYPT) : '';

$stmt = $conn->prepare(
    "UPDATE users SET
        rfid_uid        = COALESCE(NULLIF(?, ''), rfid_uid),
        fingerprint_id  = IF(? > 0, ?, fingerprint_id),
        passcode        = COALESCE(NULLIF(?, ''), passcode),
        has_rfid        = GREATEST(has_rfid, ?),
        has_fingerprint = GREATEST(has_fingerprint, ?),
        has_passcode    = GREATEST(has_passcode, ?)
     WHERE id = ?"
);
$stmt->bind_param("siisiiii",
    $rfid,
    $fingerprint_id, $fingerprint_id,
    $passcode_hash,
    $has_rfid, $has_finger, $has_pass,
    $user_id
);
$stmt->execute();
$stmt->close();

// Clear enrollment flag (prepared statement)
$stmt = $conn->prepare("UPDATE enroll_requests SET active = 0, step = 4 WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

logActivity($conn, $user_id, 'ESP32 Enrollment Completed', "user #$user_id", 'Hardware');

echo "ENROLL_OK";
$conn->close();
