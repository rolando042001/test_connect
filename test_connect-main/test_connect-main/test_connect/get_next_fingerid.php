<?php
// Returns the next free fingerprint slot id (1-based). Used by the
// ESP32 sketch as both the row id and the fingerprint sensor's
// internal storage slot when storing a new template.
//
// Fixes vs original:
//   - Defaults to 1 when the users table is empty (was crashing on
//     NULL + 1 in PHP 8 strict mode and silently working in PHP 7)
//   - Caps the slot id at 127 (the typical R307/AS608 capacity) and
//     refuses to allocate beyond that — the ESP32 firmware should
//     surface "FP Sensor Full" instead of overwriting slot 1
//   - Uses a real query so a future race only collides on the INSERT,
//     where store_user.php's prepared INSERT ... ON DUPLICATE KEY UPDATE
//     will safely refuse to clobber the wrong row
include "db.php";
header('Content-Type: text/plain');

const FP_SENSOR_CAPACITY = 127; // R307 / AS608 default

$res = $conn->query("SELECT IFNULL(MAX(id), 0) AS max_id FROM users");
$row = $res ? $res->fetch_assoc() : null;
$nextId = ($row ? (int) $row['max_id'] : 0) + 1;

if ($nextId > FP_SENSOR_CAPACITY) {
    // Out of slots. Return 0 so the firmware can detect & display.
    echo "0";
    exit;
}

echo $nextId;
