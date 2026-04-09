<?php
// ESP32 reports its enrollment progress (1=RFID, 2=Fingerprint, 3=Passcode).
// The Settings UI polls get_register_state.php to render progress to the
// admin in real time.
//
// Fixes vs original:
//   - Allowlist of valid step values (was accepting ANY integer
//     including 0, -1, 99, etc.)
//   - Prepared statement
//   - Accepts both GET (V3 sketch) and POST so newer firmware can use POST
include "db.php";
header('Content-Type: text/plain');

$step = $_GET['step'] ?? $_POST['step'] ?? null;
if ($step === null) {
    echo "NO_STEP";
    exit;
}

$step = (int) $step;
if (!in_array($step, [0, 1, 2, 3, 4], true)) {
    echo "INVALID_STEP";
    exit;
}

$stmt = $conn->prepare("UPDATE register_state SET step = ? WHERE id = 1");
$stmt->bind_param("i", $step);
$stmt->execute();
$stmt->close();

echo "STEP_UPDATED";
