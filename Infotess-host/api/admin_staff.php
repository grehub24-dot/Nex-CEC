<?php
require_once 'includes/db.php';

// Enforce access control
requireAccess('staff');

$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
$school_name = $settings['school_name'] ?? 'Nex CEC';
// sendStaffInvite() now loads school name via fetchSettings() internally

$message = '';
$error = '';

// Handle Add Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_staff') {
    validate_request_csrf();
    $full_name = sanitize($_POST['full_name']);
    $position = sanitize($_POST['position']);
    $department = sanitize($_POST['department'] ?? '');
    $qualification = sanitize($_POST['qualification'] ?? '');
    $phone = sanitize($_POST['phone']);
    $email = sanitize($_POST['email']);
    $gender = sanitize($_POST['gender'] ?? '');
    $date_of_birth = sanitize($_POST['date_of_birth'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $hire_date = sanitize($_POST['hire_date']);
    $bank_name = sanitize($_POST['bank_name'] ?? '');
    $account_number = sanitize($_POST['account_number'] ?? '');
    $send_invite = isset($_POST['send_invite']) && $_POST['send_invite'] === '1';

    // Validate Ghana phone number (MoMo/Ghana Pay compatible)
    // Accepts: 024, 025, 026, 027, 054, 055, 056, 057, 050, 059 (MTN), 020, 050 (Vodafone/Telecel), 026, 056 (AirtelTigo)
    $phone_digits = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone_digits) === 10 && in_array(substr($phone_digits, 0, 3), [
        '024','025','054','055','056','059', // MTN
        '020','050',                          // Telecel (formerly Vodafone)
        '026','057',                          // AirtelTigo
        '027','053'                           // AT/Glo
    ])) {
        $network = '';
        if (in_array(substr($phone_digits, 0, 3), ['024','025','054','055','059'])) $network = 'MTN';
        elseif (in_array(substr($phone_digits, 0, 3), ['020','050'])) $network = 'Telecel';
        elseif (in_array(substr($phone_digits, 0, 3), ['026','057'])) $network = 'AirtelTigo';
        elseif (in_array(substr($phone_digits, 0, 3), ['027','053'])) $network = 'AT/Glo';
        else $network = 'Unknown';
    } else {
        $error = "Invalid phone number. Must be a valid Ghana mobile number (e.g. 024XXXXXXX, 050XXXXXXX, 054XXXXXXX).";
        $network = null;
    }

    if (empty($error)) {
        // Auto-generate staff ID (bridge doesn't support COUNT(*) — count in PHP)
        $allStaffForCount = $pdo->query("SELECT id FROM staff");
        $allStaffForCount = $allStaffForCount ? $allStaffForCount->fetchAll() : [];
        $count = count($allStaffForCount);
        $staff_id = 'NXC-STF-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("SELECT id FROM staff WHERE staff_id = ?");
        $stmt->execute([$staff_id]);
        if ($stmt->fetch()) {
            $error = "Staff ID $staff_id already exists.";
        } else {
            $pdo->beginTransaction();
            try {
                // Determine role: teaching positions get 'teacher', others get 'staff'
                $teaching_keywords = ['teacher', 'instructor', 'tutor', 'lecturer', 'facilitator', 'coach'];
                $position_lower = strtolower($position);
                $is_teaching = false;
                foreach ($teaching_keywords as $kw) {
                    if (strpos($position_lower, $kw) !== false) {
                        $is_teaching = true;
                        break;
                    }
                }
                $role = $is_teaching ? 'teacher' : 'staff';

                if ($send_invite) {
                    // Staff self-registration mode: no auto-password, status=inactive
                    $placeholder_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (email, password, role, status) VALUES (?, ?, ?, 'inactive')");
                    $stmt->execute([$email, $placeholder_hash, $role]);
                    $user_id = $pdo->lastInsertId();

                    $stmt = $pdo->prepare("INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, gender, date_of_birth, address, hire_date, bank_name, account_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $staff_id, $full_name, $position, $department, $qualification, $phone, $email, $gender, $date_of_birth, $address, $hire_date, $bank_name, $account_number]);
                    $staff_id_inserted = (int)$pdo->lastInsertId();
                    
                    $pdo->commit();

                    // Send invite
                    $inviteResult = sendStaffInvite($staff_id_inserted, $user_id, (int)$_SESSION['user_id'], $email, $phone, $full_name);
                    if ($inviteResult['success']) {
                        $message = "Staff member added. " . $inviteResult['message'];
                    } else {
                        $message = "Staff member added! However, invite sending failed: " . $inviteResult['message'];
                    }
                } else {
                    // Legacy mode: auto-password, status=active (default)
                    $auto_password = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);
                    $password_hash = password_hash($auto_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
                    $stmt->execute([$email, $password_hash, $role]);
                    $user_id = $pdo->lastInsertId();

                    $stmt = $pdo->prepare("INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, gender, date_of_birth, address, hire_date, bank_name, account_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $staff_id, $full_name, $position, $department, $qualification, $phone, $email, $gender, $date_of_birth, $address, $hire_date, $bank_name, $account_number]);
                    
                    $pdo->commit();
                    $message = "Staff member added successfully! ID: $staff_id | Temp password: <strong>$auto_password</strong>";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Handle Delete Staff
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $staff_id = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT user_id FROM staff WHERE id = ?");
        $stmt->execute([$staff_id]);
        $staff = $stmt->fetch();
        $pdo->prepare("DELETE FROM salary_structures WHERE staff_id = ?")->execute([$staff_id]);
        $pdo->prepare("DELETE FROM deductions WHERE staff_id = ?")->execute([$staff_id]);
        $pdo->prepare("DELETE FROM payroll WHERE staff_id = ?")->execute([$staff_id]);
        $pdo->prepare("DELETE FROM staff_attendance WHERE staff_id = ?")->execute([$staff_id]);
        $pdo->prepare("DELETE FROM staff_invites WHERE staff_id = ?")->execute([$staff_id]);
        $pdo->prepare("UPDATE subjects SET teacher_id = NULL WHERE teacher_id = ?")->execute([$staff_id]);
        $pdo->prepare("DELETE FROM staff WHERE id = ?")->execute([$staff_id]);
        if ($staff && $staff['user_id']) {
            $uid = (int)$staff['user_id'];
            // Reassign payments / attendance recorded by this user to first admin
            $adminStmt = $pdo->prepare("SELECT id FROM users WHERE role IN ('admin','super_admin') AND id != ? ORDER BY id ASC LIMIT 1");
            $adminStmt->execute([$uid]);
            $fallbackAdmin = $adminStmt->fetchColumn();
            if ($fallbackAdmin) {
                $pdo->prepare("UPDATE payments SET recorded_by = ? WHERE recorded_by = ?")->execute([(int)$fallbackAdmin, $uid]);
                $pdo->prepare("UPDATE student_attendance SET recorded_by = ? WHERE recorded_by = ?")->execute([(int)$fallbackAdmin, $uid]);
            }
            // Clean up all records that FK-reference users.id
            $pdo->prepare("DELETE FROM messages WHERE sender_id = ?")->execute([$uid]);
            $pdo->prepare("DELETE FROM messages WHERE receiver_id = ?")->execute([$uid]);
            $pdo->prepare("DELETE FROM message_reads WHERE user_id = ?")->execute([$uid]);
            $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$uid]);
            $pdo->prepare("DELETE FROM executives WHERE user_id = ?")->execute([$uid]);
            $pdo->prepare("DELETE FROM parent_students WHERE parent_user_id = ?")->execute([$uid]);
            $pdo->prepare("UPDATE staff_invites SET invited_by = NULL WHERE invited_by = ?")->execute([$uid]);
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
        }
        $pdo->commit();
        $message = "Staff member deleted successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error deleting staff: " . $e->getMessage();
    }
    header("Location: staff.php?msg=" . urlencode($message));
    exit;
}

// Handle Bulk Delete Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete_staff') {
    validate_request_csrf();
    $ids = $_POST['staff_ids'] ?? [];
    if (!empty($ids) && is_array($ids)) {
        $deleted_count = 0;
        $error_count = 0;
        foreach ($ids as $rawId) {
            $staffId = (int)$rawId;
            if ($staffId <= 0) continue;
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT user_id FROM staff WHERE id = ?");
                $stmt->execute([$staffId]);
                $staff = $stmt->fetch();
                $pdo->prepare("DELETE FROM salary_structures WHERE staff_id = ?")->execute([$staffId]);
                $pdo->prepare("DELETE FROM deductions WHERE staff_id = ?")->execute([$staffId]);
                $pdo->prepare("DELETE FROM payroll WHERE staff_id = ?")->execute([$staffId]);
                $pdo->prepare("DELETE FROM staff_attendance WHERE staff_id = ?")->execute([$staffId]);
                $pdo->prepare("DELETE FROM staff_invites WHERE staff_id = ?")->execute([$staffId]);
                $pdo->prepare("UPDATE subjects SET teacher_id = NULL WHERE teacher_id = ?")->execute([$staffId]);
                $pdo->prepare("DELETE FROM staff WHERE id = ?")->execute([$staffId]);
                if ($staff && $staff['user_id']) {
                    $uid = (int)$staff['user_id'];
                    // Reassign payments / attendance to first available admin
                    $adminStmt = $pdo->prepare("SELECT id FROM users WHERE role IN ('admin','super_admin') AND id != ? ORDER BY id ASC LIMIT 1");
                    $adminStmt->execute([$uid]);
                    $fallbackAdmin = $adminStmt->fetchColumn();
                    if ($fallbackAdmin) {
                        $pdo->prepare("UPDATE payments SET recorded_by = ? WHERE recorded_by = ?")->execute([(int)$fallbackAdmin, $uid]);
                        $pdo->prepare("UPDATE student_attendance SET recorded_by = ? WHERE recorded_by = ?")->execute([(int)$fallbackAdmin, $uid]);
                    }
                    $pdo->prepare("DELETE FROM messages WHERE sender_id = ?")->execute([$uid]);
                    $pdo->prepare("DELETE FROM messages WHERE receiver_id = ?")->execute([$uid]);
                    $pdo->prepare("DELETE FROM message_reads WHERE user_id = ?")->execute([$uid]);
                    $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$uid]);
                    $pdo->prepare("DELETE FROM executives WHERE user_id = ?")->execute([$uid]);
                    $pdo->prepare("DELETE FROM parent_students WHERE parent_user_id = ?")->execute([$uid]);
                    $pdo->prepare("UPDATE staff_invites SET invited_by = NULL WHERE invited_by = ?")->execute([$uid]);
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
                }
                $pdo->commit();
                $deleted_count++;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_count++;
                $errMsg = $e->getMessage();
                error_log("Bulk delete failed for staff_id=$staffId: $errMsg");
            }
        }
        $message = "$deleted_count staff member(s) deleted successfully.";
        if ($error_count > 0) {
            $message .= " $error_count failed.";
        }
    }
}

// Handle Resend Invite
if (isset($_GET['resend_invite']) && is_numeric($_GET['resend_invite'])) {
    validate_request_csrf();
    $staffId = (int)$_GET['resend_invite'];
    try {
        $stmt = $pdo->prepare("SELECT s.*, u.id as uid FROM staff s JOIN users u ON u.id = s.user_id WHERE s.id = ?");
        $stmt->execute([$staffId]);
        $staffRow = $stmt->fetch();
        if ($staffRow) {
            $result = sendStaffInvite($staffId, (int)$staffRow['user_id'], (int)$_SESSION['user_id'], $staffRow['email'] ?? '', $staffRow['phone'] ?? '', $staffRow['full_name'] ?? '');
            if ($result['success']) {
                $message = $result['message']; // Show actual delivery result (success details or delivery failure)
            } else {
                $error = "Failed to resend invite: " . $result['message'];
            }
        } else {
            $error = "Staff member not found.";
        }
    } catch (Exception $e) {
        $error = "Error resending invite: " . $e->getMessage();
    }
    $redirectParam = $message ? "msg=" . urlencode($message) : "err=" . urlencode($error);
    header("Location: staff.php?" . $redirectParam);
    exit;
}

// Handle Activate Staff
if (isset($_GET['activate']) && is_numeric($_GET['activate'])) {
    validate_request_csrf();
    $staffId = (int)$_GET['activate'];
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT user_id FROM staff WHERE id = ?");
        $stmt->execute([$staffId]);
        $staffRow = $stmt->fetch();
        if ($staffRow && $staffRow['user_id']) {
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([(int)$staffRow['user_id']]);
            $pdo->prepare("UPDATE staff SET status = 'active' WHERE id = ?")->execute([$staffId]);
            $pdo->commit();
            $message = "Staff account activated successfully.";
        } else {
            $pdo->rollBack();
            $error = "Staff member not found.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error activating staff: " . $e->getMessage();
    }
    $redirectParam = $message ? "msg=" . urlencode($message) : "err=" . urlencode($error);
    header("Location: staff.php?" . $redirectParam);
    exit;
}

// Pagination
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
// Fetch all staff (bridge only handles simple WHERE col = ?)
// Complex search filtering is done in PHP.
// Select ONLY columns needed for the listing — avoids pulling large fields (cv_path, documents)
// that bloat the Vercel serverless response beyond the 4.5MB limit (413 PAYLOAD_TOO_LARGE).
// NOTE: Supabase REST bridge cannot do JOINs — we fetch users separately.
$all_staff = $pdo->query("SELECT id, staff_id, full_name, position, department, phone, status AS staff_status, user_id, cv_path, documents, created_at FROM staff")->fetchAll();

// Build a user-status lookup for linked user accounts
$userStatusMap = [];
$userIds = array_filter(array_column($all_staff, 'user_id'));
if (!empty($userIds)) {
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $userStmt = $pdo->prepare("SELECT id, status FROM users WHERE id IN ($placeholders)");
    $userStmt->execute(array_values($userIds));
    foreach ($userStmt->fetchAll() as $u) {
        $userStatusMap[(int)$u['id']] = $u['status'] ?? 'inactive';
    }
}
// Merge user_status into each staff row
foreach ($all_staff as &$staff) {
    $uid = (int)($staff['user_id'] ?? 0);
    $staff['user_status'] = ($uid && isset($userStatusMap[$uid])) ? $userStatusMap[$uid] : 'inactive';
}
unset($staff);

// Apply search filter in PHP (matches full_name, staff_id, and position)
if ($search !== '') {
    $staff_list = array_filter($all_staff, function($s) use ($search) {
        return stripos($s['full_name'] ?? '', $search) !== false
            || stripos($s['staff_id'] ?? '', $search) !== false
            || stripos($s['position'] ?? '', $search) !== false;
    });
} else {
    $staff_list = $all_staff;
}

// Sort by created_at DESC and apply pagination in PHP
usort($staff_list, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
$total_rows = count($staff_list);
$staff_list = array_slice($staff_list, $offset, $limit);
$total_pages = $total_rows > 0 ? (int)ceil($total_rows / $limit) : 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* === MOBILE-FIRST RESPONSIVE — admin_staff.php === */

        /* --- Modal --- */
        .modal { display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 700px; border-radius: 8px; position: relative; max-height: 90vh; overflow-y: auto; }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover { color: black; }
        .section-divider { grid-column: span 2; border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px; }
        .section-divider h4 { font-size: 15px; color: #1a5276; margin: 0 0 10px 0; }

        /* --- Top bar: heading + button stack on mobile --- */
        .top-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 20px; }
        .top-bar h2 { margin: 0; font-size: clamp(1.1rem, 4vw, 1.5rem); }

        /* --- Stat cards: 2-column grid on mobile --- */
        .stat-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
        .stat-card { display: flex; align-items: center; gap: 12px; padding: 15px; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }

        /* --- Add-staff form: single column on mobile --- */
        form[action="staff.php"]:not(#staffBulkForm) { display: grid; grid-template-columns: 1fr; gap: 12px; margin-top: 15px; }
        @media (min-width: 641px) {
            form[action="staff.php"]:not(#staffBulkForm) { grid-template-columns: 1fr 1fr; }
            .section-divider, form[action="staff.php"]:not(#staffBulkForm) > div[style*="grid-column: span 2"] { grid-column: span 2; }
        }

        /* --- Staff table: card layout on tiny screens --- */
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table { width: 100%; border-collapse: collapse; min-width: 700px; }
        .table th, .table td { padding: 10px 8px; font-size: clamp(0.75rem, 2.5vw, 0.9rem); white-space: nowrap; }

        @media (max-width: 640px) {
            /* Convert table rows to card-like layout */
            .table { min-width: unset; }
            .table thead { display: none; }
            .table tbody tr {
                display: block;
                margin-bottom: 12px;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 12px;
                background: #fff;
                box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            }
            .table tbody tr td {
                display: block;
                padding: 6px 0 !important;
                border: none !important;
                white-space: normal;
                font-size: 0.85rem;
                text-align: left !important;
            }
            .table tbody tr td::before {
                content: attr(data-label);
                display: inline-block;
                font-weight: 700;
                color: #555;
                min-width: 90px;
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }
            .table tbody tr td:first-child::before,
            .table tbody tr td[colspan]::before { content: none; }
            /* Bulky inline buttons stack vertically */
            .table tbody tr td:last-child { display: flex; flex-wrap: wrap; gap: 6px; padding-top: 8px !important; }
            .table tbody tr td:last-child::before { display: none; }
        }

        /* --- Pagination compact on mobile --- */
        @media (max-width: 480px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar button { width: 100%; }
            .stat-cards { grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .stat-card { padding: 10px; gap: 8px; }
            .stat-card h3 { font-size: 1rem; }
            .modal-content { margin: 2% auto; padding: 14px; width: 95%; }
            form[action="staff.php"]:not(#staffBulkForm) label { font-size: 0.85rem; }
            form[action="staff.php"]:not(#staffBulkForm) input,
            form[action="staff.php"]:not(#staffBulkForm) select { font-size: 16px !important; padding: 10px !important; } /* prevent iOS zoom */
            .section-divider { grid-column: span 1; }
            form[action="staff.php"]:not(#staffBulkForm) > div[style*="grid-column: span 2"] { grid-column: span 1; }
        }

        /* === Bulk action toolbar: wrap on small screens === */
        #staffBulkActions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .btn-bulk-delete {
            background: #e74c3c !important;
            color: #fff !important;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }
        .btn-bulk-delete:hover {
            background: #c0392b !important;
        }
        @media (max-width: 480px) {
            #staffBulkActions {
                flex-direction: column;
                align-items: stretch;
            }
            .btn-bulk-delete {
                width: 100%;
                justify-content: center;
                text-align: center;
            }
            #staffSelectedCount {
                text-align: center;
                display: block;
            }
        }

        /* === Pagination: compact on mobile === */
        @media (max-width: 480px) {
            .pagination-links a {
                padding: 6px 10px !important;
                min-width: 32px !important;
                font-size: 0.8rem !important;
            }
        }

        /* === Alert messages: responsive === */
        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.92rem;
            line-height: 1.5;
        }
        @media (max-width: 480px) {
            .alert {
                padding: 10px 14px;
                font-size: 0.85rem;
                margin-bottom: 15px;
            }
        }

        /* === Empty state: card-style when no staff found === */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: #888;
        }
        .empty-state .empty-icon {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 12px;
        }
        .empty-state p {
            font-size: 1rem;
            margin: 0 0 6px 0;
        }
        .empty-state .empty-sub {
            font-size: 0.85rem;
            color: #aaa;
        }
        @media (max-width: 640px) {
            .empty-state {
                padding: 24px 16px;
            }
            .empty-state .empty-icon {
                font-size: 2.5rem;
            }
        }

        /* === Modal: extra-tight on very small screens === */
        @media (max-width: 360px) {
            .modal-content {
                padding: 12px 10px !important;
                margin: 1% auto !important;
            }
            .modal-content h3 { font-size: 1.05rem; }
            .modal-content label { font-size: 0.82rem; }
            form[action="staff.php"]:not(#staffBulkForm) input,
            form[action="staff.php"]:not(#staffBulkForm) select {
                padding: 8px !important;
            }
        }

    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
            <?php echo renderSidebar('staff', $school_name); ?>

        <main class="main-content">
            <div class="top-bar">
                <h2>Staff Management</h2>
                <button id="openModalBtn" class="btn-primary"><i class="fas fa-plus"></i> Add Staff Member</button>
            </div>

            <?php 
            // Support both direct $message/$error and redirected msg/err query params
            $displayMsg = $message ?: (isset($_GET['msg']) ? $_GET['msg'] : '');
            $displayErr = $error ?: (isset($_GET['err']) ? $_GET['err'] : '');
            ?>
            <?php if ($displayMsg): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($displayMsg); ?> <a href="staff.php" style="color:#27ae60;font-weight:600;">&larr; Back to staff list</a></div>
            <?php endif; ?>
            <?php if ($displayErr): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($displayErr); ?></div>
            <?php endif; ?>

            <!-- Staff Stats -->
            <div class="stat-cards" style="margin-bottom: 30px;">
                <?php
                // Clean up duplicate staff rows (keep oldest per staff_id), run once per page load for safety
                try {
                    $dupStmt = $pdo->query("SELECT id, staff_id FROM staff ORDER BY staff_id ASC, id ASC");
                    $rows = $dupStmt ? $dupStmt->fetchAll() : [];
                    $seen = [];
                    $del = $pdo->prepare("DELETE FROM staff WHERE id = ?");
                    foreach ($rows as $r) {
                        $sid = $r['staff_id'] ?? null;
                        $id = $r['id'] ?? null;
                        if (!$sid || !$id) continue;
                        if (isset($seen[$sid])) {
                            $del->execute([(int)$id]);
                        } else {
                            $seen[$sid] = true;
                        }
                    }
                } catch (Exception $e) {
                    // ignore cleanup errors
                }
                // Reuse the already-fetched $all_staff for stats (avoids duplicating the entire query)
                $total_staff = count($all_staff);
                $active_staff = count(array_filter($all_staff, fn($s) => ($s['status'] ?? '') === 'active'));
                $non_teachers = count(array_filter($all_staff, function($s) {
                    $pos = strtolower($s['position'] ?? '');
                    return strpos($pos, 'teacher') === false && strpos($pos, 'instructor') === false && strpos($pos, 'head') === false;
                }));
                $teachers = $total_staff - $non_teachers;
                ?>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-details"><h3><?php echo $total_staff; ?></h3><p>Total Staff</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-check" style="color: green;"></i></div>
                    <div class="stat-details"><h3><?php echo $active_staff; ?></h3><p>Active Staff</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chalkboard" style="color: #f39c12;"></i></div>
                    <div class="stat-details"><h3><?php echo $teachers; ?></h3><p>Teachers</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-cog" style="color: #8e44ad;"></i></div>
                    <div class="stat-details"><h3><?php echo $non_teachers; ?></h3><p>Non-Teaching Staff</p></div>
                </div>
            </div>

            <!-- Add Staff Modal -->
            <div id="staffModal" class="modal">
                <div class="modal-content">
                    <span class="close-btn">&times;</span>
                    <h3>Add New Staff Member</h3>
                    <form action="staff.php" method="POST" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top: 15px;">
                        <input type="hidden" name="action" value="add_staff">
                        <?php csrf_field(); ?>
                        
                        <div>
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control" required placeholder="e.g. Mr. Kwame Asante">
                        </div>
                        <div>
                            <label>Staff ID</label>
                            <input type="text" id="autoStaffId" class="form-control" readonly placeholder="Auto-generated" style="background: #f0f2f5; cursor: not-allowed;">
                            <small style="color: #666; font-size: 0.8rem;">Auto-generated on submit</small>
                        </div>
                        <div>
                            <label>Position</label>
                            <select name="position" class="form-control" required>
                                <option value="">-- Select Position --</option>
                                <option value="Head Teacher">Head Teacher</option>
                                <option value="Assistant Head Teacher">Assistant Head Teacher</option>
                                <option value="Class Teacher">Class Teacher</option>
                                <option value="Subject Teacher">Subject Teacher</option>
                                <option value="Teaching Assistant">Teaching Assistant</option>
                                <option value="School Administrator">School Administrator</option>
                                <option value="Finance Officer">Finance Officer</option>
                                <option value="Secretary">Secretary</option>
                                <option value="Cleaner">Cleaner</option>
                                <option value="Security">Security</option>
                                <option value="Cook">Cook</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label>Department</label>
                            <input type="text" name="department" class="form-control" placeholder="e.g. Early Childhood, Primary, JHS">
                        </div>
                        <div>
                            <label>Qualification</label>
                            <input type="text" name="qualification" class="form-control" placeholder="e.g. B.Ed, Diploma, Certificate">
                        </div>
                        <div>
                            <label>Gender</label>
                            <select name="gender" class="form-control">
                                <option value="">-- Select --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div>
                            <label>Phone <span style="color:red;">(MoMo/Ghana Pay)</span></label>
                            <input type="text" name="phone" id="staffPhone" class="form-control" required placeholder="e.g. 0241234567" maxlength="10" pattern="[0-9]{10}" oninput="validatePhone(this)">
                            <div id="phoneBadge" style="margin-top:5px; display:none;">
                                <span id="phoneBadgeSpan" style="display:inline-block; padding:3px 10px; border-radius:12px; font-size:0.75rem; font-weight:bold;"></span>
                            </div>
                        </div>
                        <div>
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required placeholder="staff@email.com">
                        </div>
                        <div>
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control">
                        </div>
                        <div>
                            <label>Hire Date</label>
                            <input type="date" name="hire_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div>
                            <label>Address</label>
                            <input type="text" name="address" class="form-control" placeholder="Residential address">
                        </div>

                        <div style="grid-column: span 2; border-top: 1px solid #eee; padding-top: 15px;">
                            <h4 style="font-size:15px; color:#1a5276; margin:0 0 5px 0;"><i class="fas fa-envelope"></i> Staff Invitation</h4>
                            <p style="font-size:12px; color:#666; margin-bottom:10px;">
                                Check the box below to send a self-registration invite. The staff member will receive an email/SMS with a secure link to complete their profile and set their own password. Their account will remain <strong>inactive</strong> until you activate it.
                            </p>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:14px;">
                                <input type="checkbox" name="send_invite" value="1" checked>
                                <span><strong>Send invite to staff</strong> (email + SMS)</span>
                            </label>
                        </div>

                        <div style="grid-column: span 2; margin-top: 10px;">
                            <button type="submit" class="btn-submit" style="width:100%;">
                                <i class="fas fa-user-plus"></i> Add Staff Member
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Staff List -->
            <div class="section">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                    <h3>All Staff Members</h3>
                    <form action="staff.php" method="GET" style="display:flex; gap:10px;">
                        <input type="text" name="search" placeholder="Search name, ID, or position..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-login"><i class="fas fa-search"></i></button>
                    </form>
                </div>

                <!-- Bulk Delete Toolbar -->
                <form method="POST" action="staff.php" id="staffBulkForm">
                    <input type="hidden" name="action" value="bulk_delete_staff">
                    <?php csrf_field(); ?>
                    <div id="staffBulkActions">
                        <button type="button" onclick="confirmStaffBulkDelete()" class="btn-login btn-bulk-delete">
                            <i class="fas fa-trash"></i> Delete Selected
                        </button>
                        <span id="staffSelectedCount" style="color:#666; font-size:0.85rem;">0 selected</span>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:40px;"><input type="checkbox" id="staffSelectAll" onchange="toggleStaffAll(this)"></th>
                                    <th>Staff ID</th>
                                    <th>Name</th>
                                    <th>Position</th>
                                    <th>Department</th>
                                    <th>Phone</th>
                                    <th>Account Status</th>
                                    <th>Invite Status</th>
                                    <th>Docs</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($staff_list)): ?>
                                    <tr><td colspan="9" style="padding:0;">
                                        <div class="empty-state">
                                            <div class="empty-icon"><i class="fas fa-users-slash"></i></div>
                                            <p>No staff members found</p>
                                            <p class="empty-sub">Click <strong>Add Staff Member</strong> above to add your first staff record.</p>
                                        </div>
                                    </td></tr>
                                <?php else: ?>
                                    <?php foreach ($staff_list as $staff):
                                        $inviteStatus = getStaffInviteStatus((int)$staff['id']);
                                        $csrfAttr = 'csrf_token=' . urlencode(generate_csrf_token());
                                    ?>
                                    <tr>
                                        <td style="text-align:center;"><input type="checkbox" name="staff_ids[]" value="<?php echo $staff['id']; ?>" class="staff-checkbox" onchange="updateStaffSelectedCount()"></td>
                                        <td data-label="Staff ID"><strong><?php echo htmlspecialchars($staff['staff_id']); ?></strong></td>
                                        <td data-label="Name"><?php echo htmlspecialchars($staff['full_name']); ?></td>
                                        <td data-label="Position"><?php echo htmlspecialchars($staff['position']); ?></td>
                                        <td data-label="Department"><?php echo htmlspecialchars($staff['department'] ?? '-'); ?></td>
                                        <td data-label="Phone"><?php echo htmlspecialchars($staff['phone'] ?? '-'); ?></td>
                                        <td data-label="Status">
                                            <span style="color: <?php echo ($staff['user_status'] ?? 'inactive') === 'active' ? 'green' : '#e74c3c'; ?>; font-weight: bold;">
                                                <?php echo ucfirst($staff['user_status'] ?? 'inactive'); ?>
                                            </span>
                                        </td>
                                        <td data-label="Invite">
                                            <?php if ($inviteStatus === 'accepted'): ?>
                                                <span style="color: #27ae60; font-weight: bold; font-size: 0.85rem;">
                                                    <i class="fas fa-check-circle"></i> Registered
                                                </span>
                                            <?php elseif ($inviteStatus === 'pending'): ?>
                                                <span style="color: #f39c12; font-weight: bold; font-size: 0.85rem;">
                                                    <i class="fas fa-clock"></i> Pending
                                                </span>
                                            <?php elseif ($inviteStatus === 'expired'): ?>
                                                <span style="color: #e74c3c; font-weight: bold; font-size: 0.85rem;">
                                                    <i class="fas fa-hourglass-end"></i> Expired
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #999; font-size: 0.85rem;">
                                                    <i class="fas fa-minus-circle"></i> Not Invited
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Docs" style="text-align:center;">
                                            <?php
                                            $hasCv = !empty($staff['cv_path']);
                                            $hasDocs = !empty($staff['documents']) && json_decode($staff['documents'], true);
                                            if ($hasCv || $hasDocs):
                                            ?>
                                                <a href="edit_staff.php?id=<?php echo $staff['id']; ?>" title="View documents" style="color:#1a5276;text-decoration:none;">
                                                    <?php if ($hasCv): ?>
                                                        <i class="fas fa-file-pdf" style="color:#e74c3c;font-size:16px;" title="CV Uploaded"></i>
                                                    <?php endif; ?>
                                                    <?php if ($hasDocs): ?>
                                                        <i class="fas fa-folder-open" style="color:#f39c12;font-size:16px;margin-left:4px;" title="Additional Documents"></i>
                                                    <?php endif; ?>
                                                </a>
                                            <?php else: ?>
                                                <span style="color:#ccc;font-size:12px;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Actions" style="white-space: nowrap;">
                                            <a href="edit_staff.php?id=<?php echo $staff['id']; ?>" class="btn-login" style="background:#f0ad4e; padding: 5px 10px; font-size: 0.8rem; text-decoration:none; display:inline-block; margin-bottom:2px;" title="Edit staff details">Edit</a>
                                            <?php if (($staff['user_status'] ?? 'inactive') !== 'active' && $inviteStatus === 'accepted'): ?>
                                                <a href="staff.php?activate=<?php echo $staff['id']; ?>&<?php echo $csrfAttr; ?>" class="btn-login" style="background:#27ae60; padding: 5px 10px; font-size: 0.8rem; text-decoration:none; display:inline-block; margin-bottom:2px;" onclick="return confirm('Activate this staff account? They will be able to log in immediately.');">Activate</a>
                                            <?php endif; ?>
                                            <?php if ($inviteStatus === 'pending' || $inviteStatus === 'expired'): ?>
                                                <a href="staff.php?resend_invite=<?php echo $staff['id']; ?>&<?php echo $csrfAttr; ?>" class="btn-login" style="background:#3498db; padding: 5px 10px; font-size: 0.8rem; text-decoration:none; display:inline-block; margin-bottom:2px;" title="Resend invite email/SMS">Resend</a>
                                            <?php endif; ?>
                                            <?php if ($inviteStatus === 'not_invited'): ?>
                                                <a href="staff.php?resend_invite=<?php echo $staff['id']; ?>&<?php echo $csrfAttr; ?>" class="btn-login" style="background:#3498db; padding: 5px 10px; font-size: 0.8rem; text-decoration:none; display:inline-block; margin-bottom:2px;">Send Invite</a>
                                            <?php endif; ?>
                                            <a href="staff.php?delete=<?php echo $staff['id']; ?>" class="btn-login" style="background:#e74c3c; padding: 5px 10px; font-size: 0.8rem; text-decoration:none; display:inline-block; margin-bottom:2px;" onclick="return confirm('Are you sure you want to delete this staff member?');">Delete</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-links" style="display:flex; justify-content:center; gap:5px; margin-top:20px; flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" style="display:inline-flex; align-items:center; gap:5px; padding:8px 16px; background:#f8f9fa; color:#000; border:1px solid #ddd; border-radius:6px; text-decoration:none; font-size:14px;">&laquo; Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" style="display:inline-flex; align-items:center; justify-content:center; min-width:38px; padding:8px 12px; background:<?php echo $i == $page ? '#1a5276' : '#f8f9fa'; ?>; color:<?php echo $i == $page ? '#fff' : '#000'; ?>; border:1px solid <?php echo $i == $page ? '#1a5276' : '#ddd'; ?>; border-radius:6px; text-decoration:none; font-size:14px; font-weight:<?php echo $i == $page ? '700' : '400'; ?>;"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" style="display:inline-flex; align-items:center; gap:5px; padding:8px 16px; background:#f8f9fa; color:#000; border:1px solid #ddd; border-radius:6px; text-decoration:none; font-size:14px;">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Bulk delete functions
        function toggleStaffAll(source) {
            var checkboxes = document.querySelectorAll('.staff-checkbox');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
            updateStaffSelectedCount();
        }

        function updateStaffSelectedCount() {
            var checkboxes = document.querySelectorAll('.staff-checkbox:checked');
            document.getElementById('staffSelectedCount').textContent = checkboxes.length + ' selected';
        }

        function confirmStaffBulkDelete() {
            var checkboxes = document.querySelectorAll('.staff-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one staff member to delete.');
                return;
            }
            if (confirm('Are you sure you want to delete ' + checkboxes.length + ' selected staff member(s)? This action cannot be undone.')) {
                document.getElementById('staffBulkForm').submit();
            }
        }

        // Force close any stuck modal on load
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById("staffModal");
            if (modal) modal.style.display = "none";
        });

        const modal = document.getElementById("staffModal");
        const btn = document.getElementById("openModalBtn");
        const span = document.getElementsByClassName("close-btn")[0];
        
        if (btn) btn.onclick = function() { modal.style.display = "block"; }
        if (span) span.onclick = function() { modal.style.display = "none"; }
        if (modal) {
            window.onclick = function(event) { if (event.target == modal) { modal.style.display = "none"; } }
        }
        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal && modal.style.display === 'block') {
                modal.style.display = 'none';
            }
        });

        // Auto-generate Staff ID preview (uses $total_staff computed from PHP)
        function updateStaffIdPreview() {
            const count = <?php echo (int)$total_staff; ?>;
            const el = document.getElementById('autoStaffId');
            if (el) el.value = 'NXC-STF-' + String(count + 1).padStart(4, '0');
        }
        updateStaffIdPreview();

        // Ghana MoMo/Ghana Pay phone validation
        function validatePhone(input) {
            let phone = input.value.replace(/[^0-9]/g, '');
            input.value = phone;
            const badge = document.getElementById('phoneBadge');
            const badgeSpan = document.getElementById('phoneBadgeSpan');
            
            if (phone.length !== 10) {
                badge.style.display = 'none';
                return;
            }
            
            const prefix = phone.substring(0, 3);
            const networks = {
                '024': { name: 'MTN MoMo', color: '#ffcc00', bg: '#fff8e1', text: '#333' },
                '025': { name: 'MTN MoMo', color: '#ffcc00', bg: '#fff8e1', text: '#333' },
                '054': { name: 'MTN MoMo', color: '#ffcc00', bg: '#fff8e1', text: '#333' },
                '055': { name: 'MTN MoMo', color: '#ffcc00', bg: '#fff8e1', text: '#333' },
                '059': { name: 'MTN MoMo', color: '#ffcc00', bg: '#fff8e1', text: '#333' },
                '056': { name: 'MTN MoMo', color: '#ffcc00', bg: '#fff8e1', text: '#333' },
                '020': { name: 'Telecel Cash', color: '#e4002b', bg: '#fde8ec', text: '#fff' },
                '050': { name: 'Telecel Cash', color: '#e4002b', bg: '#fde8ec', text: '#fff' },
                '026': { name: 'AirtelTigo Money', color: '#0066cc', bg: '#e6f0ff', text: '#fff' },
                '057': { name: 'AirtelTigo Money', color: '#0066cc', bg: '#e6f0ff', text: '#fff' },
                '027': { name: 'Glo/Ghana Pay', color: '#ff6600', bg: '#fff3e0', text: '#333' },
                '053': { name: 'Glo/Ghana Pay', color: '#ff6600', bg: '#fff3e0', text: '#333' }
            };
            
            if (networks[prefix]) {
                const net = networks[prefix];
                badge.style.display = 'block';
                badgeSpan.textContent = '✅ ' + net.name;
                badgeSpan.style.color = net.text;
                badgeSpan.style.background = net.bg;
                badgeSpan.style.border = '2px solid ' + net.color;
                input.style.borderColor = net.color;
            } else {
                badge.style.display = 'block';
                badgeSpan.textContent = '❌ Invalid network prefix';
                badgeSpan.style.color = '#fff';
                badgeSpan.style.background = '#f8d7da';
                badgeSpan.style.border = '2px solid #e74c3c';
                input.style.borderColor = '#e74c3c';
            }
        }
    </script>
</body>
</html>
