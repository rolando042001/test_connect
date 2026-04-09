<?php
// Legacy stub — superseded by Archive.html + get_archive.php.
// Kept only so old links don't 404.
session_start();
require '../Backend/db.php';
require '../Backend/rbac.php';
require_role('admin');

header('Location: Archive.html');
exit;
