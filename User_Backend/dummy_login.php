<?php
// DEV-ONLY tool. Forges a session — disabled outside localhost so it
// cannot be reached from a deployed environment.
session_start();
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('dummy_login is disabled in this environment.');
}
include '../Backend/db.php';

// Kukunin natin ang dept_id sa URL. Kung walang inilagay, default ay Department 1.
$dept_id = isset($_GET['dept']) ? (int)$_GET['dept'] : 1;

// Sine-set natin ang fake session data
$_SESSION['user_id'] = 999; // Fake User ID
$_SESSION['dept_id'] = $dept_id; // Fake Department ID
$_SESSION['role'] = 'user';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test Login</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f1f5f9; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; }
        .btn { display: inline-block; margin-top: 15px; padding: 10px 20px; background: #03acfa; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="color: #10b981;">✅ Dummy Login Successful!</h2>
        <p>You are now acting as a user assigned to:</p>
        <h1 style="color: #0f172a;">Department ID: <?php echo $dept_id; ?></h1>
        
        <p style="color: #64748b; font-size: 0.9rem;">
            Try changing the URL to <b>dummy_login.php?dept=2</b> or <b>dept=3</b> to test other departments.
        </p>

        <a href="../User_Dashboard/User_PrivateVault.html" class="btn">Proceed to Department Vault</a>
    </div>
</body>
</html>