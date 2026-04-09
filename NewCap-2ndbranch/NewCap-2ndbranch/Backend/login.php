<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: application/json');
require 'db.php';

// ---- Threshold / lockout policy (matches the architecture flowchart) -
const LOGIN_MAX_ATTEMPTS  = 5;     // "Threshold Exceeded?"
const LOGIN_LOCK_SECONDS  = 30;    // "Temporary Account Suspension (30s)"

$data     = json_decode(file_get_contents('php://input'), true);
$email    = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$ip       = $_SERVER['REMOTE_ADDR'] ?? null;

if ($email === '' || $password === '') {
    echo json_encode(['status' => 'error', 'msg' => 'Email and password are required.']);
    exit;
}

// 1) Check if this email is currently locked ---------------------------
$stmt = $conn->prepare("SELECT attempts, locked_until FROM login_attempts WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$lockRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($lockRow && !empty($lockRow['locked_until'])) {
    $remaining = strtotime($lockRow['locked_until']) - time();
    if ($remaining > 0) {
        echo json_encode([
            'status'    => 'locked',
            'msg'       => "Account temporarily suspended. Try again in {$remaining}s.",
            'retry_in'  => $remaining,
        ]);
        exit;
    }
}

// 2) Verify credentials -------------------------------------------------
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$ok = $user && !empty($user['password']) && password_verify($password, $user['password']);

if (!$ok) {
    // -- Increment failure counter, lock if threshold exceeded ---------
    $newAttempts = ($lockRow['attempts'] ?? 0) + 1;
    $lockUntil   = null;
    if ($newAttempts >= LOGIN_MAX_ATTEMPTS) {
        $lockUntil   = date('Y-m-d H:i:s', time() + LOGIN_LOCK_SECONDS);
        $newAttempts = 0; // reset counter after lock
    }

    $up = $conn->prepare(
        "INSERT INTO login_attempts (email, ip, attempts, locked_until)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            ip           = VALUES(ip),
            attempts     = VALUES(attempts),
            locked_until = VALUES(locked_until)"
    );
    $up->bind_param("ssis", $email, $ip, $newAttempts, $lockUntil);
    $up->execute();
    $up->close();

    if ($lockUntil) {
        echo json_encode([
            'status'   => 'locked',
            'msg'      => 'Too many failed attempts. Suspended for ' . LOGIN_LOCK_SECONDS . 's.',
            'retry_in' => LOGIN_LOCK_SECONDS,
        ]);
    } else {
        echo json_encode([
            'status'    => 'error',
            'msg'       => 'Invalid email or password.',
            'remaining' => LOGIN_MAX_ATTEMPTS - $newAttempts,
        ]);
    }
    exit;
}

// 2b) Block accounts that haven't been approved by an admin -----------
$status = strtolower($user['approval_status'] ?? 'pending');
if ($status !== 'approved') {
    $msg = $status === 'rejected'
        ? 'This account has been rejected. Contact an administrator.'
        : 'Your account is awaiting administrator approval.';
    echo json_encode(['status' => 'error', 'msg' => $msg]);
    exit;
}
if (strtolower($user['status'] ?? 'active') !== 'active') {
    echo json_encode(['status' => 'error', 'msg' => 'This account is archived.']);
    exit;
}

// 3) Success — clear lockout, hydrate session --------------------------
$clear = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
$clear->bind_param("s", $email);
$clear->execute();
$clear->close();

$_SESSION['user_id']     = (int)$user['id'];
$_SESSION['dept_id']     = $user['dept_id'];
$_SESSION['role']        = strtolower($user['role'] ?? 'staff'); // 'admin' | 'staff'
$_SESSION['email']       = $user['email'];
$_SESSION['mfa_enabled'] = (int)($user['mfa_enabled'] ?? 0);
$_SESSION['mfa_passed']  = $_SESSION['mfa_enabled'] ? 0 : 1;

logActivity($conn, $user['id'], 'Login', $email, 'Session');

echo json_encode([
    'status'      => 'success',
    'msg'         => 'Welcome back!',
    'role'        => $_SESSION['role'],
    'mfa_required'=> (bool)$_SESSION['mfa_enabled'],
]);

$conn->close();
