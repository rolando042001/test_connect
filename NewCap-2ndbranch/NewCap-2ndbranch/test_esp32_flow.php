<?php
/**
 * End-to-end ESP32 3-factor authentication flow test.
 *
 * Simulates exactly what the ESP32 firmware does:
 *   1) Admin arms enrollment via the Settings backend
 *   2) ESP32 polls check_enroll.php
 *   3) ESP32 walks RFID -> fingerprint -> passcode, reporting steps
 *   4) ESP32 POSTs the captured factors to esp_enroll_api.php
 *   5) ESP32 calls reset_enroll_request.php
 *   6) On a subsequent card tap, ESP32 POSTs hardware_verify.php
 *      with the right (and wrong) factors
 *
 * After each phase the script reads the DB directly to confirm the
 * server state actually changed in the right way. Anything red = bug.
 *
 * Run from CLI:
 *     c:\xampp\php\php.exe c:\xampp\htdocs\CFM\test_esp32_flow.php
 */

require __DIR__ . '/Backend/db.php';

const BASE        = 'http://localhost/CFM';
const ADMIN_EMAIL = 'vsatisfying30@cfm.local';
const ADMIN_PWD   = 'Password123!';
const TEST_USER_EMAIL = 'hr.user@cfm.local';
const TEST_RFID   = 'DEADBEEF01';
const TEST_PIN    = '654321';
const COOKIE_JAR  = __DIR__ . '/test_cookies.txt';

// ===== ANSI colors ========================================================
function pass($msg) { echo "  \033[32m✓\033[0m  $msg\n"; }
function fail($msg) { echo "  \033[31m✗\033[0m  $msg\n"; global $FAIL; $FAIL++; }
function info($msg) { echo "  \033[36m·\033[0m  $msg\n"; }
function head($msg) { echo "\n\033[1m=== $msg ===\033[0m\n"; }

$FAIL = 0;

// ===== HTTP helpers =======================================================
function http($method, $path, $fields = null, $useCookies = false, $rawJsonBody = null) {
    $ch = curl_init(BASE . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    if ($useCookies) {
        curl_setopt($ch, CURLOPT_COOKIEFILE, COOKIE_JAR);
        curl_setopt($ch, CURLOPT_COOKIEJAR,  COOKIE_JAR);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($rawJsonBody !== null) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rawJsonBody);
        } elseif ($fields !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
    }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $body];
}

// ===== DB helpers =========================================================
function dbUserId(mysqli $conn, string $email): int {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['id'] : 0;
}
function dbUser(mysqli $conn, int $uid): ?array {
    $stmt = $conn->prepare("SELECT id, rfid_uid, fingerprint_id, passcode, has_rfid, has_fingerprint, has_passcode FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $u ?: null;
}
function dbEnroll(mysqli $conn, int $uid): array {
    $stmt = $conn->prepare("SELECT step, active FROM enroll_requests WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r ?: ['step' => 0, 'active' => 0];
}
function dbLatestAuthLog(mysqli $conn, int $uid): ?array {
    $stmt = $conn->prepare("SELECT * FROM hardware_auth_log WHERE user_id = ? OR user_id IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r ?: null;
}
function dbMaxAuthLogId(mysqli $conn): int {
    $r = $conn->query("SELECT IFNULL(MAX(id), 0) m FROM hardware_auth_log")->fetch_assoc();
    return (int)$r['m'];
}
function dbAuthLogCountSince(mysqli $conn, int $sinceId): int {
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM hardware_auth_log WHERE id > ?");
    $stmt->bind_param("i", $sinceId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)$r['c'];
}
function dbRelay(mysqli $conn): array {
    $r = $conn->query("SELECT active, fired_at FROM relay WHERE id = 1")->fetch_assoc();
    return $r ?: ['active' => 0, 'fired_at' => null];
}

// ===== SAVE original user state so we can restore at the end ==============
$uid = dbUserId($conn, TEST_USER_EMAIL);
if (!$uid) { echo "Test user not found: " . TEST_USER_EMAIL . "\n"; exit(1); }
$savedUser = dbUser($conn, $uid);
$conn->query("DELETE FROM hardware_auth_log WHERE rfid_uid = '" . TEST_RFID . "'");

echo "Test user: " . TEST_USER_EMAIL . "  (id=$uid)\n";

// ===== PHASE 0: ADMIN LOGIN ===============================================
head("PHASE 0 — Admin login (gets the Settings UI session cookie)");
@unlink(COOKIE_JAR);
$r = http('POST', '/Backend/login.php', null, true,
    json_encode(['email' => ADMIN_EMAIL, 'password' => ADMIN_PWD]));
$loginJson = json_decode($r['body'], true);
if ($r['code'] === 200 && ($loginJson['status'] ?? '') === 'success') {
    pass("Admin logged in (role=" . $loginJson['role'] . ")");
} else {
    fail("Admin login failed: HTTP {$r['code']} body=" . substr($r['body'], 0, 200));
    exit(1);
}

// ===== PHASE 1: ARM ENROLLMENT (admin Settings UI action) =================
head("PHASE 1 — Admin arms ESP32 enrollment from Settings");
$r = http('POST', '/Settings/manage_settings.php',
    ['action' => 'start_esp32_enroll', 'user_id' => $uid], true);
$j = json_decode($r['body'], true);
if (($j['status'] ?? '') === 'success') {
    pass("manage_settings start_esp32_enroll → " . $j['message']);
} else {
    fail("manage_settings start failed: " . $r['body']);
}
$state = dbEnroll($conn, $uid);
if ($state['active'] == 1 && $state['step'] == 0) pass("DB enroll_requests row: active=1 step=0");
else fail("DB enroll_requests wrong: " . json_encode($state));

// ===== PHASE 2: ESP32 SIDE — POLL CHECK_ENROLL ============================
head("PHASE 2 — ESP32 polls check_enroll.php");
$r = http('GET', '/Backend/check_enroll.php');
$expected = "1,$uid";
if (trim($r['body']) === $expected) {
    pass("check_enroll returned '$expected'");
} else {
    fail("check_enroll returned '" . trim($r['body']) . "' expected '$expected'");
}

// ===== PHASE 3: ESP32 WALKS THROUGH STEPS =================================
head("PHASE 3 — ESP32 reports each enrollment step");
foreach ([1 => 'RFID', 2 => 'Fingerprint', 3 => 'Passcode', 4 => 'Saving'] as $step => $label) {
    $r = http('POST', '/Backend/update_step.php', ['user_id' => $uid, 'step' => $step]);
    $state = dbEnroll($conn, $uid);
    if (trim($r['body']) === 'STEP_OK' && $state['step'] == $step) {
        pass("step=$step ($label) ack + persisted");
    } else {
        fail("step=$step body='{$r['body']}' state=" . json_encode($state));
    }
}

// ===== PHASE 4: ESP32 POSTS CAPTURED FACTORS =============================
head("PHASE 4 — ESP32 POSTs RFID + fingerprint slot + passcode");
$fp_slot = $uid; // sketch uses the user id as the fingerprint sensor slot
$r = http('POST', '/Backend/esp_enroll_api.php', [
    'user_id'        => $uid,
    'rfid'           => TEST_RFID,
    'fingerprint_id' => $fp_slot,
    'keypad'         => TEST_PIN,
]);
if (trim($r['body']) === 'ENROLL_OK') {
    pass("esp_enroll_api → ENROLL_OK");
} else {
    fail("esp_enroll_api body='{$r['body']}' code={$r['code']}");
}

$u = dbUser($conn, $uid);
if ($u['rfid_uid'] === TEST_RFID)            pass("users.rfid_uid persisted = " . TEST_RFID);
else                                          fail("users.rfid_uid = '{$u['rfid_uid']}' expected " . TEST_RFID);

if ((int)$u['fingerprint_id'] === $fp_slot)  pass("users.fingerprint_id persisted = $fp_slot");
else                                          fail("users.fingerprint_id = {$u['fingerprint_id']} expected $fp_slot");

if (!empty($u['passcode']) && password_verify(TEST_PIN, $u['passcode']))
    pass("users.passcode bcrypted and password_verify() matches '" . TEST_PIN . "'");
else
    fail("users.passcode does NOT verify against '" . TEST_PIN . "' (raw=" . substr((string)$u['passcode'], 0, 20) . "...)");

if ($u['has_rfid'] && $u['has_fingerprint'] && $u['has_passcode'])
    pass("has_rfid=has_fingerprint=has_passcode=1");
else
    fail("has_* flags wrong: rfid={$u['has_rfid']} fp={$u['has_fingerprint']} pin={$u['has_passcode']}");

// ===== PHASE 5: ESP32 RESETS THE ENROLLMENT FLAG ==========================
head("PHASE 5 — ESP32 calls reset_enroll_request.php");
$r = http('GET', '/Backend/reset_enroll_request.php?user_id=' . $uid);
if (trim($r['body']) === 'RESET_OK') pass("reset_enroll_request → RESET_OK");
else                                  fail("reset_enroll_request body='{$r['body']}'");
$state = dbEnroll($conn, $uid);
if ($state['active'] == 0) pass("enroll_requests.active = 0 after reset");
else                       fail("enroll_requests still active: " . json_encode($state));

// ===== PHASE 6: SETTINGS UI ENROLLMENT POLL ===============================
head("PHASE 6 — Settings UI polls enroll_status (post-enrollment)");
$r = http('GET', '/Settings/manage_settings.php?action=enroll_status&user_id=' . $uid, null, true);
$j = json_decode($r['body'], true);
if (($j['status'] ?? '') === 'success' && isset($j['data'])) {
    pass("enroll_status → step=" . $j['data']['step'] . " active=" . $j['data']['active']);
} else {
    fail("enroll_status response malformed: " . $r['body']);
}

// ===== PHASE 7: VERIFICATION — happy path =================================
head("PHASE 7 — ESP32 verification with all 3 correct factors");
$logBeforeId = dbMaxAuthLogId($conn);
$r = http('POST', '/Backend/hardware_verify.php', [
    'rfid'           => TEST_RFID,
    'fingerprint_id' => $fp_slot,
    'passcode'       => TEST_PIN,
]);
$j = json_decode($r['body'], true);
if (($j['status'] ?? '') === 'granted'
    && $j['rfid_ok'] === true
    && $j['fingerprint_ok'] === true
    && $j['passcode_ok'] === true) {
    pass("hardware_verify → granted (R✓ F✓ P✓)");
} else {
    fail("hardware_verify happy path failed: " . $r['body']);
}
$relay = dbRelay($conn);
if ($relay['active'] == 1 && $relay['fired_at'] !== null)
    pass("relay armed: active=1 fired_at=" . $relay['fired_at']);
else
    fail("relay not armed after grant: " . json_encode($relay));

// ===== PHASE 8: VERIFICATION — wrong passcode =============================
head("PHASE 8 — Verification with WRONG passcode");
$r = http('POST', '/Backend/hardware_verify.php', [
    'rfid'           => TEST_RFID,
    'fingerprint_id' => $fp_slot,
    'passcode'       => '000000',
]);
$j = json_decode($r['body'], true);
if (($j['status'] ?? '') === 'denied' && $j['rfid_ok'] && $j['fingerprint_ok'] && !$j['passcode_ok'])
    pass("denied with R✓ F✓ P✗");
else
    fail("wrong-passcode test produced unexpected result: " . $r['body']);

// ===== PHASE 9: VERIFICATION — wrong fingerprint slot =====================
head("PHASE 9 — Verification with WRONG fingerprint slot");
$r = http('POST', '/Backend/hardware_verify.php', [
    'rfid'           => TEST_RFID,
    'fingerprint_id' => 9999,
    'passcode'       => TEST_PIN,
]);
$j = json_decode($r['body'], true);
if (($j['status'] ?? '') === 'denied' && $j['rfid_ok'] && !$j['fingerprint_ok'] && $j['passcode_ok'])
    pass("denied with R✓ F✗ P✓");
else
    fail("wrong-fingerprint test produced unexpected result: " . $r['body']);

// ===== PHASE 10: VERIFICATION — unknown RFID ==============================
head("PHASE 10 — Verification with UNKNOWN RFID");
$r = http('POST', '/Backend/hardware_verify.php', [
    'rfid'           => 'CAFEBABE99',
    'fingerprint_id' => $fp_slot,
    'passcode'       => TEST_PIN,
]);
$j = json_decode($r['body'], true);
if (($j['status'] ?? '') === 'denied' && !$j['rfid_ok'] && !$j['fingerprint_ok'] && !$j['passcode_ok'])
    pass("denied with R✗ F✗ P✗ (user not resolved)");
else
    fail("unknown-rfid test produced unexpected result: " . $r['body']);

// ===== PHASE 11: AUDIT LOG ROW COUNT ======================================
head("PHASE 11 — hardware_auth_log captured every attempt");
$delta = dbAuthLogCountSince($conn, $logBeforeId);
if ($delta === 4) {
    pass("hardware_auth_log gained $delta rows (expected 4: 1 grant + 3 denies)");
} else {
    fail("hardware_auth_log delta = $delta (expected 4)");
}

// ===== PHASE 12: RELAY AUTO-DISARM ========================================
head("PHASE 12 — Relay auto-disarm via check_relay.php");
// Force fired_at into the past so check_relay.php sees the relay as stale.
$conn->query("UPDATE relay SET active=1, fired_at = (NOW() - INTERVAL 30 SECOND) WHERE id=1");
$r = http('GET', '/Backend/check_relay.php');
$relay = dbRelay($conn);
if ($relay['active'] == 0) pass("check_relay auto-disarmed stale relay (returned '" . trim($r['body']) . "')");
else                        fail("check_relay did NOT auto-disarm (relay still active): " . json_encode($relay));

// ===== CLEANUP ============================================================
head("CLEANUP — restoring test user record");
$stmt = $conn->prepare("UPDATE users SET rfid_uid=?, fingerprint_id=?, passcode=?, has_rfid=?, has_fingerprint=?, has_passcode=? WHERE id=?");
$rfidOrig = $savedUser['rfid_uid']; $fpOrig = $savedUser['fingerprint_id'];
$pcOrig   = $savedUser['passcode']; $hrOrig = (int)$savedUser['has_rfid'];
$hfOrig   = (int)$savedUser['has_fingerprint']; $hpOrig = (int)$savedUser['has_passcode'];
$stmt->bind_param("sissiii", $rfidOrig, $fpOrig, $pcOrig, $hrOrig, $hfOrig, $hpOrig, $uid);
$stmt->execute();
$stmt->close();
$conn->query("DELETE FROM hardware_auth_log WHERE rfid_uid IN ('" . TEST_RFID . "','CAFEBABE99')");
$conn->query("UPDATE relay SET active=0, fired_at=NULL WHERE id=1");
@unlink(COOKIE_JAR);
pass("Test user restored, audit log cleaned, relay reset");

// ===== SUMMARY ============================================================
echo "\n";
if ($FAIL === 0) echo "\033[1;32mALL CHECKS PASSED — ESP32 3-factor flow is wired correctly.\033[0m\n";
else             echo "\033[1;31m$FAIL FAILURE(S) — see ✗ marks above.\033[0m\n";

$conn->close();
exit($FAIL ? 1 : 0);
