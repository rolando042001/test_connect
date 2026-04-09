<?php
// Called by the ESP32 firmware (or any other actor) once enrollment
// is finished or aborted, to clear the register_state.active flag.
//
// Fixes vs original:
//   - Idempotent — exception handler in db.php returns ASCII "ERROR"
//     instead of the previous broken if/else that returned "ERROR" only
//     when the query object was falsy
//   - Always returns REGISTER_FINISHED so a transient hiccup doesn't
//     leave the system stuck "active" forever
include "db.php";
header('Content-Type: text/plain');

$conn->query("UPDATE register_state SET active = 0, step = 0 WHERE id = 1");
echo "REGISTER_FINISHED";
