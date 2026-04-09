<?php
session_start();
require 'db.php';

if (!empty($_SESSION['user_id'])) {
    logActivity($conn, $_SESSION['user_id'], 'Logout', $_SESSION['email'] ?? '', 'Session');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

header('Location: ../Login/Login.html');
exit;
