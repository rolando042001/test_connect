<?php
// Polled by the ESP32 firmware in standby. Returns "active,step" so the
// firmware can decide whether an enrollment is in progress.
//
// Fixes vs original:
//   - Always returns SOMETHING, even when register_state is empty
//     (was returning an empty body, which the ESP32 sketch parsed as
//     active=0 step=0 but only by accident — toInt() returns 0 on empty)
//   - Explicit text/plain header
include "db.php";
header('Content-Type: text/plain');

$result = $conn->query("SELECT active, step FROM register_state WHERE id = 1");
if ($result && ($row = $result->fetch_assoc())) {
    echo $row['active'] . "," . $row['step'];
} else {
    echo "0,0";
}
