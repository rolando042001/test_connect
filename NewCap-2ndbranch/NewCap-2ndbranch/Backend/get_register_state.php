<?php
// LEGACY shim — superseded by Settings/manage_settings.php?action=enroll_status
// Returns "active,step" for the most recently armed enrollment, like the old API.
session_start();
include "db.php";
require __DIR__ . "/rbac.php";
require_role('admin');

$res = $conn->query(
    "SELECT active, step FROM enroll_requests
     WHERE active = 1
     ORDER BY updated_at DESC
     LIMIT 1"
);
if ($row = $res->fetch_assoc()) {
    echo (int)$row['active'] . "," . (int)$row['step'];
} else {
    echo "0,0";
}
