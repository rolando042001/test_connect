<?php
// Centralised RBAC + MFA gate. Include AFTER session_start() and db.php.
// Usage:
//   require __DIR__ . '/../Backend/rbac.php';
//   require_login();             // any authenticated user
//   require_role('admin');       // admin only
//
// All admin endpoints in this project should call require_role('admin')
// instead of duplicating ad-hoc session checks.

if (!function_exists('require_login')) {
    function require_login() {
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Not authenticated.']);
            exit;
        }
        if (!empty($_SESSION['mfa_enabled']) && empty($_SESSION['mfa_passed'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'MFA verification required.']);
            exit;
        }
    }

    function require_role($role) {
        require_login();
        $have = strtolower($_SESSION['role'] ?? '');
        if ($have !== strtolower($role)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Insufficient permissions.']);
            exit;
        }
    }

    function current_user_id() {
        return (int)($_SESSION['user_id'] ?? 0);
    }
}
