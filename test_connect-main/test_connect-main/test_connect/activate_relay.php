<?php
// Admin-driven cabinet unlock. Called by register.html (or any other
// trusted UI) with POST relay=1 / relay=2 to fire one of the two
// solenoids. The ESP32 firmware polls check_relay.php to discover the
// armed channel.
//
// Fixes vs original:
//   - Prepared statement (was string interpolation)
//   - Validates that $relay is 1 or 2 (the only channels we wired)
//   - Returns RELAY_NOT_FOUND if seed data is missing, instead of
//     reporting RELAY_ENABLED on a query that affected zero rows
include "db.php";
header('Content-Type: text/plain');

if (!isset($_POST['relay'])) {
    echo "MISSING_RELAY";
    exit;
}

$relay = (int) $_POST['relay'];
if ($relay !== 1 && $relay !== 2) {
    echo "INVALID_RELAY";
    exit;
}

// Pre-check that the row exists. Using affected_rows alone isn't enough
// because mysqli reports 0 affected rows when the value is unchanged
// (e.g. relay was already armed) — we'd incorrectly report RELAY_NOT_FOUND.
$stmt = $conn->prepare("SELECT id FROM relay WHERE relay_in = ? LIMIT 1");
$stmt->bind_param("i", $relay);
$stmt->execute();
$exists = (bool) $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exists) {
    echo "RELAY_NOT_FOUND";
    exit;
}

$stmt = $conn->prepare("UPDATE relay SET active = 1 WHERE relay_in = ?");
$stmt->bind_param("i", $relay);
$stmt->execute();
$stmt->close();

echo "RELAY_ENABLED";
