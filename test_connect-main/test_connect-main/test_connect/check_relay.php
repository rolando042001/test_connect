<?php
// Polled by the ESP32 firmware. Returns the active relay channel number
// (1 or 2), or "0" if no relay is currently armed.
//
// Fixes vs original:
//   - Explicit text/plain Content-Type
//   - Defensive: returns "0" if the query itself fails instead of letting
//     the firmware see an HTML error page
include "db.php";
header('Content-Type: text/plain');

$result = $conn->query("SELECT relay_in FROM relay WHERE active = 1 LIMIT 1");
if ($result && ($row = $result->fetch_assoc())) {
    echo (int) $row['relay_in'];
} else {
    echo "0";
}
