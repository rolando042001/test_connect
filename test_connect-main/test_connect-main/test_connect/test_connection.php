<?php
// Sanity-check endpoint. Should print the success message AND verify
// the three expected tables exist.
//
// Fixes vs original:
//   - Original always claimed success even on broken connection (the
//     `if ($conn)` check was a tautology — mysqli returns an object
//     even after a failed connect)
//   - Now actually queries the schema; if any expected table is missing
//     it returns a TABLES_MISSING line so the user knows the SQL dump
//     wasn't imported
include "db.php";
header('Content-Type: text/plain');

if ($conn->connect_errno !== 0) {
    echo "Database Connection Failed: " . $conn->connect_error;
    exit;
}

$expected = ['register_state', 'relay', 'users'];
$found    = [];
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_array(MYSQLI_NUM)) {
    $found[] = $row[0];
}

$missing = array_diff($expected, $found);
if (!empty($missing)) {
    echo "Database Connected, TABLES_MISSING: " . implode(',', $missing);
    exit;
}

echo "Database Connected Successfully";
