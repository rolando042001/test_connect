<?php
// 3-factor hardware authentication for the Physical Cabinet branch.
// ESP32 POSTs: rfid, fingerprint_id, passcode.
// Server resolves the user via the RFID UID, then matches the other
// two factors and writes a row to hardware_auth_log either way.
//
// On success the relay is armed (re-uses the existing relay table).
include "db.php";
header('Content-Type: application/json');

$rfid     = $_POST['rfid']           ?? '';
$finger   = isset($_POST['fingerprint_id']) ? (int)$_POST['fingerprint_id'] : 0;
$passcode = $_POST['passcode']       ?? '';
$device_ip= $_SERVER['REMOTE_ADDR']  ?? null;

$result   = 'denied';
$user_id  = null;
$rfid_ok  = 0;
$fp_ok    = 0;
$pass_ok  = 0;

if ($rfid !== '') {
    $stmt = $conn->prepare(
        "SELECT id, fingerprint_id, passcode FROM users
         WHERE rfid_uid = ? AND status = 'Active' LIMIT 1"
    );
    $stmt->bind_param("s", $rfid);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        $user_id = (int)$user['id'];
        $rfid_ok = 1;
        $fp_ok   = ($finger > 0 && (int)$user['fingerprint_id'] === $finger) ? 1 : 0;
        // users.passcode is bcrypt-hashed; use password_verify (constant-time).
        $pass_ok = ($passcode !== '' && !empty($user['passcode']) && password_verify($passcode, $user['passcode'])) ? 1 : 0;

        if ($rfid_ok && $fp_ok && $pass_ok) {
            $result = 'granted';
            // Arm the relay; fired_at lets check_relay.php auto-disarm.
            $conn->query("UPDATE relay SET active = 1, fired_at = NOW() WHERE id = 1");
        }
    }
}

// Write the audit row regardless of outcome
$stmt = $conn->prepare(
    "INSERT INTO hardware_auth_log
        (user_id, rfid_uid, fingerprint_id, passcode_ok, rfid_ok, fingerprint_ok, result, device_ip)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("isiiiiss",
    $user_id, $rfid, $finger, $pass_ok, $rfid_ok, $fp_ok, $result, $device_ip
);
$stmt->execute();
$stmt->close();

if ($result === 'granted') {
    logActivity($conn, $user_id, 'Cabinet Access Granted', "rfid=$rfid", 'Hardware');
} else {
    // Log unauthorized attempt (matches "Log Unauthorized Access Attempt")
    logActivity($conn, $user_id, 'Cabinet Access Denied',  "rfid=$rfid", 'Hardware');
}

echo json_encode([
    'status'         => $result,
    'rfid_ok'        => (bool)$rfid_ok,
    'fingerprint_ok' => (bool)$fp_ok,
    'passcode_ok'    => (bool)$pass_ok,
]);
$conn->close();
