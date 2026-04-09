<?php
/**
 * One-shot seeder: creates dummy folders + files + login users for every
 * department. Idempotent by display_name / email — re-running it won't
 * duplicate rows.
 *
 * Run from CLI:
 *     c:\xampp\php\php.exe c:\xampp\htdocs\CFM\seed_dummy_data.php
 */
require __DIR__ . '/Backend/db.php';

$VAULT_DIR = __DIR__ . '/uploads/vault/';
if (!is_dir($VAULT_DIR)) mkdir($VAULT_DIR, 0777, true);

// ----- 1. CREATE / UPGRADE A LOGIN USER PER DEPARTMENT --------------------
$DEFAULT_PASSWORD = 'Password123!';
$hash = password_hash($DEFAULT_PASSWORD, PASSWORD_DEFAULT);

$users = [
    1 => ['Helen',    'Reyes',     'hr.user@cfm.local',         'Staff'],
    2 => ['Carlos',   'Mendoza',   'finance.user@cfm.local',    'Staff'],
    3 => ['Aira',     'Domingo',   'accounting.user@cfm.local', 'Staff'],
    4 => ['Marco',    'Velasquez', 'marketing.user@cfm.local',  'Staff'],
    5 => ['Liza',     'Santos',    'docs.user@cfm.local',       'Staff'],
    6 => ['Patrick',  'Cruz',      'filing.user@cfm.local',     'Staff'],
];

// Same passcode for every seeded user, bcrypted to match the format the
// rest of the system uses (password_verify in verify_mfa / hardware_verify).
$DEFAULT_PASSCODE = '123456';
$passHash = password_hash($DEFAULT_PASSCODE, PASSWORD_BCRYPT);

echo "=== USERS ===\n";
foreach ($users as $deptId => [$first, $last, $email, $role]) {
    $stmt = $conn->prepare(
        "INSERT INTO users
            (first_name, last_name, dept_id, email, password, role,
             approval_status, status, mfa_enabled, passcode, has_passcode)
         VALUES (?, ?, ?, ?, ?, ?, 'approved', 'Active', 0, ?, 1)
         ON DUPLICATE KEY UPDATE
            dept_id = VALUES(dept_id),
            password = VALUES(password),
            role = VALUES(role),
            approval_status = 'approved',
            status = 'Active',
            passcode = VALUES(passcode),
            has_passcode = 1"
    );
    $stmt->bind_param("ssissss", $first, $last, $deptId, $email, $hash, $role, $passHash);
    $stmt->execute();
    $stmt->close();
    echo str_pad($email, 32) . "  pwd: $DEFAULT_PASSWORD  pin: $DEFAULT_PASSCODE  (dept #$deptId)\n";
}

// Also make sure the existing admin still works.
$adminEmail = 'vsatisfying30@cfm.local';
$stmt = $conn->prepare(
    "INSERT INTO users
        (first_name, last_name, dept_id, email, password, role,
         approval_status, status, mfa_enabled)
     VALUES ('CFM', 'Admin', 1, ?, ?, 'Admin', 'approved', 'Active', 0)
     ON DUPLICATE KEY UPDATE password = VALUES(password)"
);
$stmt->bind_param("ss", $adminEmail, $hash);
$stmt->execute();
$stmt->close();
echo str_pad($adminEmail, 32) . "  pwd: $DEFAULT_PASSWORD  (Admin)\n";

// ----- 2. PER-DEPARTMENT FOLDERS + FILES ----------------------------------
$datasets = [
    1 => [ // HR
        'folders' => ['Employee Records', 'Payroll', 'Onboarding'],
        'files'   => [
            ['Employee Records', 'Employee_Handbook_2026.pdf',         'pdf'],
            ['Employee Records', 'Code_of_Conduct.docx',               'docx'],
            ['Payroll',          'Payroll_March_2026.xlsx',            'xlsx'],
            ['Payroll',          'Tax_Withholding_Summary.pdf',        'pdf'],
            ['Onboarding',       'New_Hire_Checklist.docx',            'docx'],
            [null,               'HR_Policy_Memo.pdf',                 'pdf'],
        ],
    ],
    2 => [ // Collection / Finance
        'folders' => ['Invoices', 'Receipts', 'Bank Statements'],
        'files'   => [
            ['Invoices',        'Invoice_INV-2026-001.pdf',  'pdf'],
            ['Invoices',        'Invoice_INV-2026-002.pdf',  'pdf'],
            ['Receipts',        'Receipt_OR-1023.pdf',       'pdf'],
            ['Bank Statements', 'BPI_March_Statement.pdf',   'pdf'],
            [null,              'AR_Aging_Report.xlsx',      'xlsx'],
        ],
    ],
    3 => [ // Accounting
        'folders' => ['Ledgers', 'Tax Filings', 'Audit Reports'],
        'files'   => [
            ['Ledgers',       'General_Ledger_Q1.xlsx',          'xlsx'],
            ['Ledgers',       'Trial_Balance_March.xlsx',        'xlsx'],
            ['Tax Filings',   'BIR_Form_1701_2025.pdf',          'pdf'],
            ['Tax Filings',   'BIR_Form_2550M_March.pdf',        'pdf'],
            ['Audit Reports', 'External_Audit_2025.pdf',         'pdf'],
            [null,            'Chart_of_Accounts.docx',          'docx'],
        ],
    ],
    4 => [ // Marketing
        'folders' => ['Campaigns', 'Brand Assets', 'Analytics'],
        'files'   => [
            ['Campaigns',    'Q2_Campaign_Brief.docx',       'docx'],
            ['Campaigns',    'Social_Media_Calendar.xlsx',   'xlsx'],
            ['Brand Assets', 'CFM_Logo_Primary.png',         'png'],
            ['Brand Assets', 'Brand_Guidelines.pdf',         'pdf'],
            ['Analytics',    'GA4_Report_March.pdf',         'pdf'],
            [null,           'Press_Release_Draft.docx',     'docx'],
        ],
    ],
    5 => [ // Documentation
        'folders' => ['Manuals', 'SOPs', 'Templates'],
        'files'   => [
            ['Manuals',   'CFM_System_Manual_v3.pdf',       'pdf'],
            ['Manuals',   'ESP32_Cabinet_Quickstart.pdf',   'pdf'],
            ['SOPs',      'SOP_Document_Approval.docx',     'docx'],
            ['SOPs',      'SOP_Access_Request.docx',        'docx'],
            ['Templates', 'Memo_Template.docx',             'docx'],
            [null,        'Glossary_of_Terms.pdf',          'pdf'],
        ],
    ],
    6 => [ // Filing
        'folders' => ['Inbound', 'Outbound', 'Archived Logs'],
        'files'   => [
            ['Inbound',       'Inbound_Log_March.xlsx',       'xlsx'],
            ['Inbound',       'Courier_Receipts_Mar.pdf',     'pdf'],
            ['Outbound',      'Outbound_Log_March.xlsx',      'xlsx'],
            ['Archived Logs', 'Filing_Index_2025.pdf',        'pdf'],
            [null,            'Records_Retention_Schedule.pdf','pdf'],
        ],
    ],
];

echo "\n=== FOLDERS + FILES ===\n";

function folderExists(mysqli $conn, int $deptId, string $name): ?int {
    $stmt = $conn->prepare(
        "SELECT id FROM folders WHERE dept_id = ? AND name = ? AND parent_id IS NULL LIMIT 1"
    );
    $stmt->bind_param("is", $deptId, $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['id'] : null;
}

function fileExists(mysqli $conn, int $deptId, string $displayName): bool {
    $stmt = $conn->prepare(
        "SELECT id FROM files WHERE dept_id = ? AND display_name = ? LIMIT 1"
    );
    $stmt->bind_param("is", $deptId, $displayName);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $exists;
}

$ADMIN_ID = 14; // Satis Viy

foreach ($datasets as $deptId => $set) {
    $deptName = $conn->query("SELECT name FROM departments WHERE id=$deptId")->fetch_assoc()['name'];
    echo "\n-- Dept $deptId: $deptName --\n";

    $folderIds = [];
    foreach ($set['folders'] as $fname) {
        $existing = folderExists($conn, $deptId, $fname);
        if ($existing) {
            $folderIds[$fname] = $existing;
            echo "  folder (exists): $fname\n";
            continue;
        }
        $stmt = $conn->prepare(
            "INSERT INTO folders (parent_id, dept_id, name, created_by, created_at, is_trash, is_archived)
             VALUES (NULL, ?, ?, ?, NOW(), 0, 0)"
        );
        $stmt->bind_param("isi", $deptId, $fname, $ADMIN_ID);
        $stmt->execute();
        $folderIds[$fname] = $conn->insert_id;
        $stmt->close();
        echo "  folder: $fname\n";
    }

    foreach ($set['files'] as [$folder, $display, $ext]) {
        if (fileExists($conn, $deptId, $display)) {
            echo "  file (exists): $display\n";
            continue;
        }
        $folderId = $folder !== null ? ($folderIds[$folder] ?? null) : null;
        $storage  = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $size     = random_int(20_000, 950_000);

        // Create a placeholder file on disk so downloads don't 404.
        file_put_contents(
            $VAULT_DIR . $storage,
            "Dummy seed file: $display\nDept #$deptId\nGenerated " . date('c') . "\n"
        );

        $stmt = $conn->prepare(
            "INSERT INTO files
                (dept_id, folder_id, uploader_id, display_name, storage_name,
                 file_type, file_size, uploaded_at, is_trash, is_archived)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0, 0)"
        );
        $stmt->bind_param("iiissss", $deptId, $folderId, $ADMIN_ID, $display, $storage, $ext, $size);
        $stmt->execute();
        $stmt->close();
        echo "  file:   $display" . ($folder ? " (in $folder)" : " (root)") . "\n";
    }
}

// ----- 3. ACCESS REQUEST SAMPLES (one pending per dept) -------------------
echo "\n=== ACCESS REQUESTS ===\n";
$reqs = [
    [1, 'Employee_Handbook_2026.pdf',  'Need a copy for orientation tomorrow.'],
    [2, 'Invoice_INV-2026-002.pdf',    'Client requested re-issuance.'],
    [3, 'Trial_Balance_March.xlsx',    'For monthly review.'],
    [4, 'Brand_Guidelines.pdf',        'Designing new pamphlet.'],
    [5, 'CFM_System_Manual_v3.pdf',    'For new staff onboarding.'],
    [6, 'Records_Retention_Schedule.pdf', 'Quarterly purge planning.'],
];
foreach ($reqs as [$deptId, $target, $reason]) {
    $exists = $conn->query(
        "SELECT id FROM access_requests WHERE target_file_name='" . $conn->real_escape_string($target) . "' LIMIT 1"
    )->fetch_assoc();
    if ($exists) { echo "  (exists): $target\n"; continue; }

    $stmt = $conn->prepare(
        "INSERT INTO access_requests (user_id, dept_id, target_file_name, reason, status, request_date)
         VALUES (?, ?, ?, ?, 'pending', NOW())"
    );
    $uid = $ADMIN_ID;
    $stmt->bind_param("iiss", $uid, $deptId, $target, $reason);
    $stmt->execute();
    $stmt->close();
    echo "  request: dept $deptId  ->  $target\n";
}

echo "\nDONE.\n";
$conn->close();
