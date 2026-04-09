<?php
// Polled by the ESP32. Returns the relay channel to fire (any non-zero
// value) or "0" if the relay is currently inactive.
//
// Auto-disarms the relay if it has been armed for longer than
// RELAY_HOLD_SECONDS — this is the "auto-disarm" guarantee from the
// architecture, so a missed device poll cannot leave the cabinet open.
include "db.php";

const RELAY_HOLD_SECONDS = 5;

$conn->query(
    "UPDATE relay
        SET active = 0, fired_at = NULL
      WHERE active = 1
        AND fired_at IS NOT NULL
        AND fired_at < (NOW() - INTERVAL " . RELAY_HOLD_SECONDS . " SECOND)"
);

$res = $conn->query("SELECT relay_in FROM relay WHERE active = 1 LIMIT 1");
if ($row = $res->fetch_assoc()) {
    echo $row['relay_in'];
} else {
    echo "0";
}
