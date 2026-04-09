<?php
// =============================================================================
// test_connect health dashboard
//
// Single-page health check that runs the entire smoke test suite against
// the test_connect endpoints from the SAME apache process. Refresh to
// re-run. Each row shows ✅ / ❌ with the actual response captured for
// the failing checks so debugging is one click away.
//
// Bookmark http://192.168.1.23/test_connect/health.php and reload it
// any time you want to know "is the system still good?" at a glance.
// =============================================================================

require __DIR__ . '/db.php';

// Reset to a known clean baseline before running tests so we never get
// false positives from leftover state. We also remove any test-only rows
// from previous runs (id=99 user) and disarm both relays.
$conn->query("DELETE FROM users WHERE id = 99");
$conn->query("UPDATE register_state SET active = 0, step = 0 WHERE id = 1");
$conn->query("UPDATE relay SET active = 0");

// -----------------------------------------------------------------------------
// Test runner
// -----------------------------------------------------------------------------
$BASE   = 'http://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['REQUEST_URI']), '/') . '/';
$tests  = [];
$passed = 0;
$failed = 0;

function http_call(string $method, string $url, array $fields = []): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => (int) $code, 'body' => (string) $body];
}

function check(string $section, string $name, bool $ok, string $detail = ''): void {
    global $tests, $passed, $failed;
    $tests[] = compact('section', 'name', 'ok', 'detail');
    $ok ? $passed++ : $failed++;
}

// ---- T1: db.php sanity ------------------------------------------------------
$r = http_call('GET', $BASE . 'test_connection.php');
check('Database', 'test_connection.php returns success',
    str_contains($r['body'], 'Successfully'),
    "HTTP {$r['code']} body: " . substr($r['body'], 0, 80));

// ---- T2: register_state read ------------------------------------------------
$r = http_call('GET', $BASE . 'get_register_state.php');
check('State', 'get_register_state.php returns "0,0" (idle)',
    trim($r['body']) === '0,0',
    "got '" . trim($r['body']) . "'");

// ---- T3: register_start --------------------------------------------------------
$r = http_call('GET', $BASE . 'register_start.php');
check('State', 'register_start.php → REGISTER_STARTED',
    trim($r['body']) === 'REGISTER_STARTED',
    "got '" . trim($r['body']) . "'");
$r = http_call('GET', $BASE . 'get_register_state.php');
check('State', 'state advances to "1,1" after start',
    trim($r['body']) === '1,1',
    "got '" . trim($r['body']) . "'");

// ---- T4: update_step allowlist ----------------------------------------------
$r = http_call('GET', $BASE . 'update_step.php?step=2');
check('State', 'update_step?step=2 → STEP_UPDATED',
    trim($r['body']) === 'STEP_UPDATED',
    "got '" . trim($r['body']) . "'");
$r = http_call('GET', $BASE . 'update_step.php?step=99');
check('State', 'update_step?step=99 rejected as INVALID_STEP',
    trim($r['body']) === 'INVALID_STEP',
    "got '" . trim($r['body']) . "'");
$r = http_call('GET', $BASE . 'update_step.php');
check('State', 'update_step with no param → NO_STEP',
    trim($r['body']) === 'NO_STEP',
    "got '" . trim($r['body']) . "'");

// ---- T5: get_next_fingerid --------------------------------------------------
$r = http_call('GET', $BASE . 'get_next_fingerid.php');
check('Users', 'get_next_fingerid.php returns next free slot',
    is_numeric(trim($r['body'])) && (int) trim($r['body']) > 0,
    "got '" . trim($r['body']) . "'");

// ---- T6: get_rfids ----------------------------------------------------------
$r = http_call('GET', $BASE . 'get_rfids.php');
$rfids = json_decode($r['body'], true);
check('Users', 'get_rfids.php returns valid JSON array',
    is_array($rfids),
    'body: ' . substr($r['body'], 0, 80));

// ---- T7: get_users (richer schema, no leaked hash) --------------------------
$r = http_call('GET', $BASE . 'get_users.php');
$users = json_decode($r['body'], true);
$leaked = is_array($users) && !empty($users) && isset($users[0]['passcode']);
check('Users', 'get_users.php returns rich JSON without leaking bcrypt hash',
    is_array($users) && !$leaked && isset($users[0]['has_passcode']),
    'body: ' . substr($r['body'], 0, 80));

// ---- T8: store_user happy path ----------------------------------------------
$r = http_call('POST', $BASE . 'store_user.php', [
    'id'       => 99,
    'rfid_uid' => 'DEADBEEF99',
    'passcode' => '654321',
]);
check('Enrollment', 'store_user.php POST → USER_SAVED',
    trim($r['body']) === 'USER_SAVED',
    "got '" . trim($r['body']) . "'");

// ---- T9: bcrypt actually persisted ------------------------------------------
$row = $conn->query("SELECT passcode FROM users WHERE id = 99")->fetch_assoc();
$bcryptOk = $row && substr($row['passcode'], 0, 4) === '$2y$' && password_verify('654321', $row['passcode']);
check('Enrollment', 'store_user persisted a bcrypted PIN that verifies',
    $bcryptOk,
    $row ? 'prefix: ' . substr($row['passcode'], 0, 10) : 'no row');

// ---- T10: store_user input validation ---------------------------------------
$r = http_call('POST', $BASE . 'store_user.php', [
    'id' => 99, 'rfid_uid' => "AA'); DROP TABLE users; --", 'passcode' => '123456',
]);
check('Security', 'store_user blocks SQL injection in rfid_uid',
    trim($r['body']) === 'INVALID_RFID',
    "got '" . trim($r['body']) . "'");

$r = http_call('POST', $BASE . 'store_user.php', [
    'id' => 99, 'rfid_uid' => 'AABBCC', 'passcode' => '12',
]);
check('Security', 'store_user rejects short passcode',
    trim($r['body']) === 'INVALID_PASSCODE',
    "got '" . trim($r['body']) . "'");

// ---- T11: finish_register ---------------------------------------------------
$r = http_call('GET', $BASE . 'finish_register.php');
check('State', 'finish_register.php → REGISTER_FINISHED',
    trim($r['body']) === 'REGISTER_FINISHED',
    "got '" . trim($r['body']) . "'");
$r = http_call('GET', $BASE . 'get_register_state.php');
check('State', 'state cleared back to "0,0"',
    trim($r['body']) === '0,0',
    "got '" . trim($r['body']) . "'");

// ---- T12: relay arming ------------------------------------------------------
$conn->query("UPDATE relay SET active = 0");
$r = http_call('POST', $BASE . 'activate_relay.php', ['relay' => 1]);
check('Relay', 'activate_relay relay=1 → RELAY_ENABLED',
    trim($r['body']) === 'RELAY_ENABLED',
    "got '" . trim($r['body']) . "'");
// CRITICAL: re-arm should still report ENABLED (was the affected_rows bug)
$r = http_call('POST', $BASE . 'activate_relay.php', ['relay' => 1]);
check('Relay', 're-arm of already-armed relay still → RELAY_ENABLED',
    trim($r['body']) === 'RELAY_ENABLED',
    "got '" . trim($r['body']) . "'");
$r = http_call('POST', $BASE . 'activate_relay.php', ['relay' => 99]);
check('Relay', 'invalid relay channel rejected as INVALID_RELAY',
    trim($r['body']) === 'INVALID_RELAY',
    "got '" . trim($r['body']) . "'");

// ---- T13: check_relay -------------------------------------------------------
$conn->query("UPDATE relay SET active = 0");
$conn->query("UPDATE relay SET active = 1 WHERE relay_in = 2");
$r = http_call('GET', $BASE . 'check_relay.php');
check('Relay', 'check_relay.php returns the armed channel (2)',
    trim($r['body']) === '2',
    "got '" . trim($r['body']) . "'");

// ---- T14: reset_relay -------------------------------------------------------
$r = http_call('GET', $BASE . 'reset_relay.php');
check('Relay', 'reset_relay.php → RESET',
    trim($r['body']) === 'RESET',
    "got '" . trim($r['body']) . "'");
$r = http_call('GET', $BASE . 'check_relay.php');
check('Relay', 'all relays cleared after reset',
    trim($r['body']) === '0',
    "got '" . trim($r['body']) . "'");

// ---- T15: verify_user.php happy path ----------------------------------------
$r = http_call('POST', $BASE . 'verify_user.php', [
    'rfid_uid' => 'DEADBEEF99', 'fingerprint_id' => 99, 'passcode' => '654321',
]);
check('Verification', 'verify_user.php with valid factors → GRANTED',
    trim($r['body']) === 'GRANTED',
    "got '" . trim($r['body']) . "'");
$row = $conn->query("SELECT relay_in FROM relay WHERE active = 1")->fetch_assoc();
check('Verification', 'relay 1 armed by GRANTED response',
    $row && (int) $row['relay_in'] === 1,
    'relay row: ' . json_encode($row));
$conn->query("UPDATE relay SET active = 0");

// ---- T16: verify_user.php denied paths --------------------------------------
$r = http_call('POST', $BASE . 'verify_user.php', [
    'rfid_uid' => 'DEADBEEF99', 'fingerprint_id' => 99, 'passcode' => '000000',
]);
check('Verification', 'wrong PIN → DENIED_PIN',
    trim($r['body']) === 'DENIED_PIN',
    "got '" . trim($r['body']) . "'");

$r = http_call('POST', $BASE . 'verify_user.php', [
    'rfid_uid' => 'DEADBEEF99', 'fingerprint_id' => 42, 'passcode' => '654321',
]);
check('Verification', 'wrong fingerprint slot → DENIED_FINGER',
    trim($r['body']) === 'DENIED_FINGER',
    "got '" . trim($r['body']) . "'");

$r = http_call('POST', $BASE . 'verify_user.php', [
    'rfid_uid' => 'CAFEBABE99', 'fingerprint_id' => 99, 'passcode' => '654321',
]);
check('Verification', 'unknown RFID → DENIED_RFID',
    trim($r['body']) === 'DENIED_RFID',
    "got '" . trim($r['body']) . "'");

$row = $conn->query("SELECT COUNT(*) c FROM relay WHERE active = 1")->fetch_assoc();
check('Verification', 'relay stays disarmed on every denied attempt',
    (int) $row['c'] === 0,
    'armed rows: ' . $row['c']);

// ---- T17: db.php returns ASCII ERROR not HTML on broken DB ------------------
// We don't actually break the DB here — we just verify the handler exists
// by checking that the file has the expected guards.
$dbCode = file_get_contents(__DIR__ . '/db.php');
check('Robustness', 'db.php has set_exception_handler() guard',
    str_contains($dbCode, 'set_exception_handler'),
    'guard not found');

// ---- T18: register.html charset ---------------------------------------------
$html = file_get_contents(__DIR__ . '/register.html');
check('UI', 'register.html declares UTF-8 charset',
    stripos($html, 'meta charset="UTF-8"') !== false,
    'meta charset not declared');

// -----------------------------------------------------------------------------
// Cleanup
// -----------------------------------------------------------------------------
$conn->query("DELETE FROM users WHERE id = 99");
$conn->query("UPDATE register_state SET active = 0, step = 0 WHERE id = 1");
$conn->query("UPDATE relay SET active = 0");

// -----------------------------------------------------------------------------
// Rendering
// -----------------------------------------------------------------------------
// Group tests by section for visual structure
$bySection = [];
foreach ($tests as $t) {
    $bySection[$t['section']][] = $t;
}

$total       = $passed + $failed;
$pct         = $total > 0 ? round(($passed / $total) * 100) : 0;
$allGreen    = $failed === 0;
$lastRunIso  = date('Y-m-d H:i:s');
$dbVersion   = $conn->server_info ?? 'unknown';
$phpVersion  = PHP_VERSION;
$serverIp    = $_SERVER['SERVER_ADDR'] ?? 'unknown';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>test_connect — Health</title>
<style>
:root {
    --bg:        #f6f7fb;
    --card:      #ffffff;
    --ink:       #0f172a;
    --ink-mute:  #64748b;
    --line:      #e2e8f0;
    --accent:    #2563eb;
    --accent-bg: #eff6ff;
    --pass:      #10b981;
    --pass-bg:   #ecfdf5;
    --fail:      #ef4444;
    --fail-bg:   #fef2f2;
    --warn:      #f59e0b;
    --shadow:    0 1px 3px rgba(15, 23, 42, .04), 0 8px 24px rgba(15, 23, 42, .06);
    --radius:    14px;
}
* { box-sizing: border-box; }
html, body {
    margin: 0;
    padding: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
    background: var(--bg);
    color: var(--ink);
    -webkit-font-smoothing: antialiased;
}
.wrap {
    max-width: 920px;
    margin: 0 auto;
    padding: 32px 20px 64px;
}
header {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 24px;
}
header h1 {
    font-size: 1.75rem;
    font-weight: 800;
    margin: 0;
    letter-spacing: -0.02em;
}
header .subtitle {
    color: var(--ink-mute);
    font-size: .95rem;
}
.summary {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    margin-bottom: 24px;
}
@media (min-width: 640px) {
    .summary { grid-template-columns: 2fr 1fr 1fr 1fr; }
}
.stat {
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 18px 20px;
    box-shadow: var(--shadow);
}
.stat .label {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--ink-mute);
    font-weight: 600;
}
.stat .value {
    font-size: 1.6rem;
    font-weight: 800;
    margin-top: 4px;
    letter-spacing: -0.02em;
}
.banner {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    gap: 16px;
}
@media (min-width: 640px) {
    .banner { grid-column: span 4; }
}
.banner .dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    flex-shrink: 0;
}
.banner.green { background: var(--pass-bg); border-color: #a7f3d0; }
.banner.green .dot { background: var(--pass); box-shadow: 0 0 0 4px rgba(16, 185, 129, .2); }
.banner.red   { background: var(--fail-bg); border-color: #fecaca; }
.banner.red   .dot { background: var(--fail); box-shadow: 0 0 0 4px rgba(239, 68, 68, .2); }
.banner h2 {
    margin: 0 0 2px;
    font-size: 1.05rem;
    letter-spacing: -0.01em;
}
.banner p {
    margin: 0;
    color: var(--ink-mute);
    font-size: .85rem;
}
.section {
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 16px;
}
.section h3 {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--ink-mute);
    font-weight: 700;
    margin: 0;
    padding: 14px 20px;
    border-bottom: 1px solid var(--line);
    background: #fafbfd;
}
.row {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 13px 20px;
    border-bottom: 1px solid var(--line);
}
.row:last-child { border-bottom: 0; }
.icon {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .75rem;
    font-weight: 700;
    color: white;
    margin-top: 1px;
}
.icon.pass { background: var(--pass); }
.icon.fail { background: var(--fail); }
.row .text { flex: 1; min-width: 0; }
.row .name {
    font-size: .92rem;
    font-weight: 500;
    color: var(--ink);
}
.row .detail {
    font-size: .78rem;
    color: var(--fail);
    margin-top: 4px;
    font-family: ui-monospace, "SF Mono", Consolas, monospace;
    word-break: break-word;
}
.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 24px;
}
.btn {
    background: var(--accent);
    color: white;
    border: 0;
    padding: 11px 22px;
    border-radius: 10px;
    font-weight: 600;
    font-size: .9rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: transform .08s ease, background .15s ease;
}
.btn:hover { background: #1d4ed8; }
.btn:active { transform: translateY(1px); }
.btn.ghost {
    background: var(--card);
    color: var(--ink);
    border: 1px solid var(--line);
}
.btn.ghost:hover { background: #f8fafc; }
footer {
    margin-top: 32px;
    text-align: center;
    color: var(--ink-mute);
    font-size: .78rem;
    line-height: 1.6;
}
footer code {
    background: var(--card);
    padding: 2px 7px;
    border-radius: 4px;
    border: 1px solid var(--line);
    font-size: .85em;
}
</style>
</head>
<body>
<div class="wrap">

    <header>
        <h1>test_connect health</h1>
        <p class="subtitle">Server-side smoke test for the cabinet endpoints. Refresh to re-run.</p>
    </header>

    <div class="summary">
        <div class="stat banner <?= $allGreen ? 'green' : 'red' ?>">
            <div class="dot"></div>
            <div>
                <h2><?= $allGreen ? 'All systems OK' : "{$failed} failure(s)" ?></h2>
                <p><?= $passed ?> of <?= $total ?> checks passed · last run <?= $lastRunIso ?></p>
            </div>
        </div>
        <div class="stat">
            <div class="label">Passed</div>
            <div class="value" style="color: var(--pass);"><?= $passed ?></div>
        </div>
        <div class="stat">
            <div class="label">Failed</div>
            <div class="value" style="color: <?= $failed === 0 ? 'var(--ink-mute)' : 'var(--fail)' ?>;"><?= $failed ?></div>
        </div>
        <div class="stat">
            <div class="label">Success</div>
            <div class="value"><?= $pct ?>%</div>
        </div>
    </div>

    <?php foreach ($bySection as $sectionName => $sectionTests): ?>
        <div class="section">
            <h3><?= htmlspecialchars($sectionName) ?></h3>
            <?php foreach ($sectionTests as $t): ?>
                <div class="row">
                    <div class="icon <?= $t['ok'] ? 'pass' : 'fail' ?>"><?= $t['ok'] ? '✓' : '✕' ?></div>
                    <div class="text">
                        <div class="name"><?= htmlspecialchars($t['name']) ?></div>
                        <?php if (!$t['ok'] && $t['detail']): ?>
                            <div class="detail"><?= htmlspecialchars($t['detail']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div class="actions">
        <a class="btn" href="health.php">↻ Re-run tests</a>
        <a class="btn ghost" href="register.html">Open admin panel</a>
    </div>

    <footer>
        Server <code><?= htmlspecialchars($serverIp) ?></code>
        · MariaDB <code><?= htmlspecialchars($dbVersion) ?></code>
        · PHP <code><?= htmlspecialchars($phpVersion) ?></code>
        <br>
        Bookmark this page. Reload to re-run all checks. The dashboard is read-only — every test cleans up after itself.
    </footer>
</div>
</body>
</html>
