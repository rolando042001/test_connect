<?php
// 3-factor walk-up verification for the test_connect cabinet system.
//
// Called by the ESP32 firmware AFTER it captures all three factors at
// the cabinet (user taps card, places enrolled finger, types PIN). The
// server resolves the user by RFID, then verifies the other two factors
// against the stored credentials.
//
// On a full grant we ALSO arm the relay so the existing V3 firmware
// (which polls check_relay.php every loop iteration) will fire the
// solenoid on its next poll. This means the firmware addition is just:
//   "detect card → read fingerprint → read passcode → POST here"
// — the relay-firing path is unchanged from V3's existing logic.
//
// Request:   POST rfid_uid=<HEX> & fingerprint_id=<int> & passcode=<digits>
//            optional: relay=1 or relay=2  (which channel to arm on grant,
//            default 1)
//
// Responses (ASCII so the ESP32 can parse with String.indexOf / equals):
//   GRANTED               — all 3 factors matched, relay armed
//   DENIED_RFID           — no user has that RFID UID
//   DENIED_FINGER         — RFID matched but the fingerprint slot does not
//   DENIED_PIN            — RFID + finger matched but the passcode is wrong
//   DENIED_NOT_ENROLLED   — user found but missing one of the has_* flags
//   MISSING_DATA          — one of the POST fields was not provided

include "db.php";
header('Content-Type: text/plain');

if (!isset($_POST['rfid_uid'], $_POST['fingerprint_id'], $_POST['passcode'])) {
    echo "MISSING_DATA";
    exit;
}

$rfid     = strtoupper(trim($_POST['rfid_uid']));
$finger   = (int) $_POST['fingerprint_id'];
$passcode = $_POST['passcode'];
$relayCh  = isset($_POST['relay']) ? (int) $_POST['relay'] : 1;
if ($relayCh !== 1 && $relayCh !== 2) $relayCh = 1;

// 1) RFID lookup ----------------------------------------------------------
$stmt = $conn->prepare(
    "SELECT id, passcode, has_passcode, has_rfid, has_fingerprint
       FROM users WHERE rfid_uid = ? LIMIT 1"
);
$stmt->bind_param("s", $rfid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo "DENIED_RFID";
    exit;
}

if (!$user['has_rfid'] || !$user['has_fingerprint'] || !$user['has_passcode']) {
    echo "DENIED_NOT_ENROLLED";
    exit;
}

// 2) Fingerprint slot match (V3 system uses users.id == sensor slot) -----
if ($finger <= 0 || (int) $user['id'] !== $finger) {
    echo "DENIED_FINGER";
    exit;
}

// 3) Passcode (bcrypt) ---------------------------------------------------
if (!password_verify($passcode, (string) $user['passcode'])) {
    echo "DENIED_PIN";
    exit;
}

// All three factors matched — arm the relay so the firmware's existing
// check_relay.php poll picks it up and fires the solenoid.
$stmt = $conn->prepare("UPDATE relay SET active = 1 WHERE relay_in = ?");
$stmt->bind_param("i", $relayCh);
$stmt->execute();
$stmt->close();

echo "GRANTED";
