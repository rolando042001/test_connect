<?php
// Second-factor verification. Called after a successful login when
// users.mfa_enabled = 1. Two methods are supported:
//
//   passcode -> compare against users.passcode (numeric, set by ESP32 enrollment)
//   esp32    -> the user must tap their RFID on the cabinet; the latest
//               'granted' row in hardware_auth_log within MFA_WINDOW seconds
//               counts as a pass
//
// On success, $_SESSION['mfa_passed'] = 1 and rbac.php stops blocking.

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: application/json');
require 'db.php';

const MFA_WINDOW = 60; // seconds — how recent an ESP32 grant must be

if (empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Not logged in.']);
    exit;
}
if (!empty($_SESSION['mfa_passed'])) {
    echo json_encode(['status' => 'success', 'msg' => 'Already verified.']);
    exit;
}

$uid    = (int)$_SESSION['user_id'];
$method = $_POST['method'] ?? 'passcode';

// Load user
$stmt = $conn->prepare("SELECT id, passcode, mfa_enabled, mfa_method FROM users WHERE id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['status' => 'error', 'msg' => 'User not found.']);
    exit;
}

$ok = false;

if ($method === 'passcode') {
    $entered = $_POST['passcode'] ?? '';
    if ($entered === '' || !preg_match('/^[0-9]{4,8}$/', $entered)) {
        echo json_encode(['status' => 'error', 'msg' => 'Enter a 4–8 digit passcode.']);
        exit;
    }
    // Passcode column is bcrypt-hashed (see Departments/update_passcode.php
    // and esp_enroll_api.php). Use password_verify for constant-time check.
    $ok = !empty($user['passcode']) && password_verify($entered, $user['passcode']);
}
elseif ($method === 'esp32') {
    // Did this user successfully tap their card recently?
    $stmt = $conn->prepare(
        "SELECT id FROM hardware_auth_log
         WHERE user_id = ? AND result = 'granted'
           AND created_at >= (NOW() - INTERVAL ? SECOND)
         ORDER BY id DESC LIMIT 1"
    );
    $window = MFA_WINDOW;
    $stmt->bind_param("ii", $uid, $window);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
}
else {
    echo json_encode(['status' => 'error', 'msg' => 'Unknown MFA method.']);
    exit;
}

if (!$ok) {
    logActivity($conn, $uid, 'MFA Failed', $method, 'Session');
    echo json_encode(['status' => 'error', 'msg' => 'Verification failed.']);
    exit;
}

$_SESSION['mfa_passed'] = 1;
logActivity($conn, $uid, 'MFA Passed', $method, 'Session');

echo json_encode(['status' => 'success', 'msg' => 'Verified.']);
$conn->close();
