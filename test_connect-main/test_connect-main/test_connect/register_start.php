<?php
// Called from register.html when the admin clicks "Start Registration".
// Arms the single shared register_state row so the next ESP32 poll
// kicks off the enrollment flow.
//
// Fixes vs original:
//   - Idempotent (UPDATE always succeeds; the exception handler in
//     db.php catches DB hiccups)
//   - Explicit Content-Type
include "db.php";
header('Content-Type: text/plain');

$conn->query("UPDATE register_state SET active = 1, step = 1 WHERE id = 1");
echo "REGISTER_STARTED";
