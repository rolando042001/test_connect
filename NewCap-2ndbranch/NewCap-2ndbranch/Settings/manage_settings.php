<?php
// Settings backend — covers the "Settings" branch of the architecture:
//   * Configure MFA / ESP32
//   * Update RBAC Permission
//   * Log Activity
//
// All actions are admin-only.

session_start();
header('Content-Type: application/json');
require '../Backend/db.php';
require '../Backend/rbac.php';

require_role('admin');
$me = current_user_id();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        // ---- 1. List users with their role + MFA + hardware status ---
        case 'list_users': {
            $sql = "SELECT id, first_name, last_name, email, role,
                           mfa_enabled, mfa_method,
                           has_rfid, has_fingerprint, has_passcode
                    FROM users
                    WHERE status = 'Active'
                    ORDER BY role DESC, last_name ASC";
            $rows = [];
            $res  = $conn->query($sql);
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            echo json_encode(['status' => 'success', 'data' => $rows]);
            break;
        }

        // ---- 2. Update RBAC role (Admin / Staff) ---------------------
        case 'update_role': {
            $uid  = (int)($_POST['user_id'] ?? 0);
            $role = $_POST['role'] ?? '';
            if (!$uid || !in_array($role, ['Admin', 'Staff'], true)) {
                throw new Exception('Invalid user or role.');
            }
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $role, $uid);
            $stmt->execute();
            $stmt->close();
            logActivity($conn, $me, 'Update RBAC', "User #$uid -> $role", 'Settings');
            echo json_encode(['status' => 'success', 'message' => 'Role updated.']);
            break;
        }

        // ---- 3. Toggle MFA on/off and choose method -----------------
        case 'update_mfa': {
            $uid     = (int)($_POST['user_id'] ?? 0);
            $enabled = (int)($_POST['enabled'] ?? 0) ? 1 : 0;
            $method  = $_POST['method'] ?? 'none';
            if (!in_array($method, ['none', 'esp32', 'passcode'], true)) {
                throw new Exception('Invalid MFA method.');
            }
            if (!$uid) throw new Exception('Invalid user.');
            $stmt = $conn->prepare("UPDATE users SET mfa_enabled = ?, mfa_method = ? WHERE id = ?");
            $stmt->bind_param("isi", $enabled, $method, $uid);
            $stmt->execute();
            $stmt->close();
            logActivity($conn, $me, 'Configure MFA',
                "User #$uid mfa=" . ($enabled ? 'on' : 'off') . " method=$method", 'Settings');
            echo json_encode(['status' => 'success', 'message' => 'MFA updated.']);
            break;
        }

        // ---- 4. Trigger ESP32 enrollment for a user -----------------
        case 'start_esp32_enroll': {
            $uid = (int)($_POST['user_id'] ?? 0);
            if (!$uid) throw new Exception('Invalid user.');

            // Single-device assumption: there is only one cabinet, so any
            // previously armed enrollment must be cleared atomically before
            // we arm a new one. Otherwise the ESP32 would still see the
            // older user when it polls check_enroll.php.
            $conn->query("UPDATE enroll_requests SET active = 0, step = 0 WHERE active = 1");

            $stmt = $conn->prepare(
                "INSERT INTO enroll_requests (user_id, step, active)
                 VALUES (?, 0, 1)
                 ON DUPLICATE KEY UPDATE step = 0, active = 1"
            );
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $stmt->close();
            logActivity($conn, $me, 'ESP32 Enrollment Started', "User #$uid", 'Settings');
            echo json_encode(['status' => 'success', 'message' => 'Enrollment armed. Tap card on the device.']);
            break;
        }

        // ---- 6. Hardware authentication audit log -------------------
        case 'get_hardware_log': {
            $limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));
            $sql = "SELECT h.id, h.user_id, h.rfid_uid, h.fingerprint_id,
                           h.passcode_ok, h.rfid_ok, h.fingerprint_ok,
                           h.result, h.device_ip, h.created_at,
                           CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS user_name
                    FROM hardware_auth_log h
                    LEFT JOIN users u ON u.id = h.user_id
                    ORDER BY h.id DESC
                    LIMIT $limit";
            $rows = [];
            $res  = $conn->query($sql);
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            echo json_encode(['status' => 'success', 'data' => $rows]);
            break;
        }

        // ---- 5. Poll enrollment progress (UI status) ----------------
        case 'enroll_status': {
            $uid = (int)($_GET['user_id'] ?? 0);
            if (!$uid) throw new Exception('Invalid user.');
            $stmt = $conn->prepare("SELECT step, active FROM enroll_requests WHERE user_id = ?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: ['step' => 0, 'active' => 0];
            $stmt->close();
            echo json_encode(['status' => 'success', 'data' => $row]);
            break;
        }

        default:
            throw new Exception('Unknown action.');
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
