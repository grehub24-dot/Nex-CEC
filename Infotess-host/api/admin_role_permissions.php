<?php
require_once 'includes/db.php';

// Enforce access control
requireAccess('role_permissions');

// Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

// --- Auto-create class_teachers table (resilient) ---
try {
    require_once __DIR__ . '/lib/Supabase.php';
    $supabase = new SupabaseClient();
    $supabase->executeSql("
        CREATE TABLE IF NOT EXISTS class_teachers (
            id           BIGSERIAL    PRIMARY KEY,
            staff_id     BIGINT       NOT NULL UNIQUE REFERENCES staff(id) ON DELETE CASCADE,
            class_id     BIGINT       NOT NULL       REFERENCES classes(id) ON DELETE CASCADE,
            assigned_at  TIMESTAMPTZ  DEFAULT NOW()
        );
        CREATE INDEX IF NOT EXISTS idx_class_teachers_class ON class_teachers(class_id);
        CREATE INDEX IF NOT EXISTS idx_class_teachers_staff ON class_teachers(staff_id);
    ");
} catch (Exception $e) {
    // Table may have been created manually, or executeSql unavailable.
    // Either way, the PG bridge queries below still work if the table exists.
    error_log("class_teachers auto-create skipped: " . $e->getMessage());
}

// --- Fetch all classes for the Class Teacher assignment dropdown ---
$allClasses = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM classes ORDER BY sort_order, name");
    $allClasses = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error fetching classes: " . $e->getMessage();
}

// --- Fetch class_teachers assignments (staff_id => class_id) ---
$classTeacherMap = [];
try {
    $stmt = $pdo->query("SELECT staff_id, class_id FROM class_teachers");
    foreach ($stmt->fetchAll() as $ctRow) {
        $classTeacherMap[(int)$ctRow['staff_id']] = (int)$ctRow['class_id'];
    }
} catch (Exception $e) {
    // Table may not exist yet — that's OK
    error_log("class_teachers fetch skipped: " . $e->getMessage());
}

// Handle bulk role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_role') {
    validate_request_csrf();

    $staff_id = (int)($_POST['staff_id'] ?? 0);
    $new_access = $_POST['access_level'] ?? '';

    if ($staff_id <= 0 || !in_array($new_access, ['staff', 'teacher', 'bursar', 'admin'])) {
        $error = "Invalid staff ID or access level.";
    } else {
        try {
            // Fetch the staff member
            $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
            $stmt->execute([$staff_id]);
            $staff = $stmt->fetch();

            if (!$staff) {
                $error = "Staff member not found.";
            } elseif (empty($staff['user_id'])) {
                $error = "This staff member has no linked user account. Create a user account first.";
            } else {
                $user_id = (int)$staff['user_id'];
                $position = $staff['position'] ?? '';
                $name = $staff['full_name'];

                // Start by determining new values
                $new_role = '';   // users.role
                $new_position = $position; // staff.position

                switch ($new_access) {
                    case 'teacher':
                        $new_role = 'staff'; // Class teachers stay as 'staff' in users.role
                        // Prepend "Class Teacher" marker if not already present
                        if (strpos($position, 'Class Teacher') === false) {
                            if (trim($position) === '') {
                                $new_position = 'Class Teacher';
                            } else {
                                $new_position = 'Class Teacher / ' . ltrim($position);
                            }
                        }
                        break;

                    case 'bursar':
                        $new_role = 'bursar';
                        // Remove Class Teacher marker if present
                        $new_position = removeClassTeacherMarker($position);
                        break;

                    case 'admin':
                        $new_role = 'admin';
                        // Remove Class Teacher marker if present
                        $new_position = removeClassTeacherMarker($position);
                        break;

                    case 'staff':
                    default:
                        $new_role = 'staff';
                        // Remove Class Teacher marker if present
                        $new_position = removeClassTeacherMarker($position);
                        break;
                }

                // Update users.role
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_role, $user_id]);

                // Update staff.position if it changed
                if ($new_position !== $position) {
                    $stmt = $pdo->prepare("UPDATE staff SET position = ? WHERE id = ?");
                    $stmt->execute([$new_position, $staff_id]);
                }

                // Handle class_teachers assignment
                if ($new_access === 'teacher') {
                    $class_id = (int)($_POST['class_id'] ?? 0);
                    if ($class_id > 0) {
                        // Upsert: remove any existing assignment, then insert
                        $pdo->prepare("DELETE FROM class_teachers WHERE staff_id = ?")->execute([$staff_id]);
                        $pdo->prepare("INSERT INTO class_teachers (staff_id, class_id) VALUES (?, ?)")->execute([$staff_id, $class_id]);
                        $message = "Role updated to Class Teacher. Assigned to class #{$class_id}.";
                    } else {
                        // No class selected — just remove any previous assignment
                        $pdo->prepare("DELETE FROM class_teachers WHERE staff_id = ?")->execute([$staff_id]);
                        $message = "Role updated to Class Teacher. No class assigned yet.";
                    }
                } else {
                    // Not a teacher anymore — remove class_teacher assignment
                    $pdo->prepare("DELETE FROM class_teachers WHERE staff_id = ?")->execute([$staff_id]);
                }

                // Log the change
                $old_level_label = getAccessLevelLabel($position, $staff['user_role'] ?? $new_role);
                $new_level_label = getAccessLevelLabel($new_position, $new_role);
                error_log("Role permission change: Staff #$staff_id ($name) from '$old_level_label' to '$new_level_label' by user #{$_SESSION['user_id']}");

                $redirectPage = isset($_POST['page']) ? '?page=' . (int)$_POST['page'] : '';
                header("Location: role_permissions.php{$redirectPage}");
                exit;
            }
        } catch (Exception $e) {
            $error = "Error updating role: " . $e->getMessage();
        }
    }
}

// Handle toggle account status (approve / suspend)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_account_status') {
    validate_request_csrf();
    $staff_id = (int)($_POST['staff_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

    if ($staff_id <= 0 || !in_array($new_status, ['active', 'inactive'])) {
        $error = "Invalid request.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM staff WHERE id = ?");
            $stmt->execute([$staff_id]);
            $staffRow = $stmt->fetch();

            if (!$staffRow || !$staffRow['user_id']) {
                $error = "Staff member has no linked user account.";
            } else {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new_status, (int)$staffRow['user_id']]);
                $pdo->prepare("UPDATE staff SET status = ? WHERE id = ?")->execute([$new_status, $staff_id]);
                $pdo->commit();

                $message = "Account status updated to " . ($new_status === 'active' ? 'Approved' : 'Suspended') . ".";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error updating account status: " . $e->getMessage();
        }
    }
    $redirectPage = isset($_POST['page']) ? '?page=' . (int)$_POST['page'] : '';
    header("Location: role_permissions.php{$redirectPage}");
    exit;
}

// Fetch ALL staff with their linked user accounts
// NOTE: Supabase REST bridge cannot do JOINs — we fetch users separately
$allStaff = [];
try {
    // 1. Fetch all staff
    $stmt = $pdo->query("SELECT * FROM staff");
    $allStaff = $stmt->fetchAll();
    
    // Sort by department, full_name in PHP
    usort($allStaff, function($a, $b) {
        $cmp = strcmp($a['department'] ?? '', $b['department'] ?? '');
        return $cmp !== 0 ? $cmp : strcmp($a['full_name'] ?? '', $b['full_name'] ?? '');
    });
    
    // 2. Collect user_ids that exist
    $userIds = array_filter(array_column($allStaff, 'user_id'));
    
    // 3. Fetch matching users' role, email, status
    $userMap = [];
    if (!empty($userIds)) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $userStmt = $pdo->prepare("SELECT id, role, email, is_password_reset, status FROM users WHERE id IN ($placeholders)");
        $userStmt->execute(array_values($userIds));
        foreach ($userStmt->fetchAll() as $u) {
            $userMap[(int)$u['id']] = $u;
        }
    }
    
    // 4. Merge user data into each staff row
    foreach ($allStaff as &$staff) {
        $uid = (int)($staff['user_id'] ?? 0);
        if ($uid && isset($userMap[$uid])) {
            $staff['user_role'] = $userMap[$uid]['role'] ?? '';
            $staff['user_email'] = $userMap[$uid]['email'] ?? '';
            $staff['user_status'] = $userMap[$uid]['status'] ?? 'inactive';
            $staff['is_password_reset'] = $userMap[$uid]['is_password_reset'] ?? false;
        } else {
            $staff['user_role'] = '';
            $staff['user_email'] = '';
            $staff['user_status'] = 'inactive';
            $staff['is_password_reset'] = false;
        }
    }
    unset($staff);
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Pagination for staff table
$staff_limit = 15;
$staff_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$staff_offset = ($staff_page - 1) * $staff_limit;
$staff_total = count($allStaff);
$staff_total_pages = max(1, ceil($staff_total / $staff_limit));
$allStaffPaginated = array_slice($allStaff, $staff_offset, $staff_limit);

/**
 * Get human-readable access level label for a staff member.
 */
function getAccessLevelLabel($position, $userRole) {
    if (in_array($userRole, ['admin', 'super_admin'])) {
        return 'Admin';
    }
    if ($userRole === 'bursar') {
        return 'Bursar';
    }
    if ($userRole === 'teacher') {
        return 'Teacher';
    }
    // Staff role — check if Class Teacher
    if (strpos($position, 'Class Teacher') !== false) {
        return 'Teacher';
    }
    return 'Staff';
}

/**
 * Remove the "Class Teacher" marker from a position string.
 * Handles "Class Teacher / Driver" → "Driver", "Class Teacher" → "", etc.
 */
function removeClassTeacherMarker($position) {
    $position = trim($position);
    // Remove "Class Teacher / " prefix
    if (strpos($position, 'Class Teacher / ') === 0) {
        $position = substr($position, strlen('Class Teacher / '));
    } elseif ($position === 'Class Teacher') {
        $position = '';
    }
    return trim($position);
}

/**
 * Get a CSS badge class for the access level.
 */
function badgeClass($level) {
    return [
        'Admin' => 'badge badge-primary',
        'Bursar' => 'badge badge-warning',
        'Teacher' => 'badge badge-success',
        'Staff' => 'badge badge-secondary',
    ][$level] ?? 'badge badge-secondary';
}

/**
 * Get icon for access level.
 */
function levelIcon($level) {
    return [
        'Admin' => 'fas fa-shield-alt',
        'Bursar' => 'fas fa-coins',
        'Teacher' => 'fas fa-chalkboard-teacher',
        'Staff' => 'fas fa-user',
    ][$level] ?? 'fas fa-user';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Permissions — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .role-badge i {
            font-size: 0.75rem;
        }
        .role-badge-admin { background: #1a5276; color: #fff; }
        .role-badge-bursar { background: #d4a017; color: #fff; }
        .role-badge-teacher { background: #27ae60; color: #fff; }
        .role-badge-staff { background: #95a5a6; color: #fff; }
        .role-badge-no-user { background: #e74c3c; color: #fff; }

        .level-select {
            padding: 5px 8px;
            border-radius: 6px;
            border: 1px solid #d0d7de;
            font-size: 0.85rem;
            background: #fff;
            cursor: pointer;
        }
        .level-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.12);
        }
        .level-select.teacher { border-color: #27ae60; }
        .level-select.bursar { border-color: #d4a017; }
        .level-select.admin { border-color: #1a5276; }

        .update-btn {
            padding: 5px 14px;
            border-radius: 6px;
            border: none;
            background: var(--primary-color);
            color: #fff;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
        }
        .update-btn:hover { opacity: 0.85; }

        .legend-box {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            margin: 16px 0 20px;
            padding: 14px 18px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            color: #555;
        }
        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .status-toggle-wrap {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .toggle-switch {
            position: relative;
            width: 52px;
            height: 26px;
            flex-shrink: 0;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            border-radius: 26px;
            transition: background 0.35s ease, box-shadow 0.35s ease;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.2);
        }
        .toggle-slider.enabled {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.2), 0 0 8px rgba(39,174,96,0.35);
        }
        .toggle-slider.disabled {
            background: #e8e8e8;
            cursor: not-allowed;
            box-shadow: none;
        }
        .toggle-slider::before {
            content: "";
            position: absolute;
            left: 3px;
            bottom: 3px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 4px rgba(0,0,0,0.25);
            z-index: 2;
        }
        /* Knob inner dot — subtle presence indicator */
        .toggle-slider::after {
            content: "";
            position: absolute;
            left: 9px;
            bottom: 9px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 3;
            pointer-events: none;
        }
        .toggle-switch input:checked + .toggle-slider::before {
            transform: translateX(26px);
        }
        .toggle-switch input:checked + .toggle-slider::after {
            transform: translateX(26px);
        }
        .toggle-status-label {
            font-weight: 600;
            font-size: 0.85rem;
            white-space: nowrap;
        }
        .toggle-status-label.enabled {
            color: #27ae60;
        }
        .toggle-status-label.disabled {
            color: #b0b0b0;
        }
        .toggle-status-label.pending {
            color: #856404;
        }
        .toggle-status-label.no-account {
            color: #999;
        }

        @media (max-width: 768px) {
            .table-wrap { overflow-x: auto; }
            .legend-box { flex-direction: column; gap: 6px; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php echo renderSidebar('role_permissions', $school_name); ?>

        <main class="main-content">
            <div class="top-bar">
                <h2><i class="fas fa-user-shield"></i> Role &amp; Permission Assignment</h2>
                <span style="font-size:0.85rem; color:#666;">
                    <i class="fas fa-info-circle"></i> Set what each staff member can access
                </span>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Legend -->
            <div class="legend-box">
                <div class="legend-item">
                    <span class="legend-dot" style="background:#1a5276;"></span>
                    <strong>Admin</strong> — Full system access
                </div>
                <div class="legend-item">
                    <span class="legend-dot" style="background:#d4a017;"></span>
                    <strong>Bursar</strong> — Financial access (payments, fees, reports)
                </div>
                <div class="legend-item">
                    <span class="legend-dot" style="background:#27ae60;"></span>
                    <strong>Class Teacher</strong> — Mark attendance, enter grades
                </div>
                <div class="legend-item">
                    <span class="legend-dot" style="background:#95a5a6;"></span>
                    <strong>Staff</strong> — Limited default access
                </div>
            </div>

            <!-- Quick summary -->
            <?php
            $counts = ['Admin' => 0, 'Bursar' => 0, 'Teacher' => 0, 'Staff' => 0];
            foreach ($allStaff as $s) {
                $lvl = getAccessLevelLabel($s['position'] ?? '', $s['user_role'] ?? '');
                $counts[$lvl] = ($counts[$lvl] ?? 0) + 1;
            }
            ?>
            <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:24px;">
                <div style="flex:1; min-width:140px; background:#1a5276; color:#fff; padding:16px 20px; border-radius:10px; text-align:center;">
                    <div style="font-size:1.6rem; font-weight:700;"><?php echo $counts['Admin']; ?></div>
                    <div style="font-size:0.85rem; opacity:0.85;">Admins</div>
                </div>
                <div style="flex:1; min-width:140px; background:#d4a017; color:#fff; padding:16px 20px; border-radius:10px; text-align:center;">
                    <div style="font-size:1.6rem; font-weight:700;"><?php echo $counts['Bursar']; ?></div>
                    <div style="font-size:0.85rem; opacity:0.85;">Bursars</div>
                </div>
                <div style="flex:1; min-width:140px; background:#27ae60; color:#fff; padding:16px 20px; border-radius:10px; text-align:center;">
                    <div style="font-size:1.6rem; font-weight:700;"><?php echo $counts['Teacher']; ?></div>
                    <div style="font-size:0.85rem; opacity:0.85;">Class Teachers</div>
                </div>
                <div style="flex:1; min-width:140px; background:#95a5a6; color:#fff; padding:16px 20px; border-radius:10px; text-align:center;">
                    <div style="font-size:1.6rem; font-weight:700;"><?php echo $counts['Staff']; ?></div>
                    <div style="font-size:0.85rem; opacity:0.85;">Staff</div>
                </div>
            </div>

            <div class="card">
                <h3><i class="fas fa-users"></i> All Staff Members</h3>
                <p style="color:#666; margin-bottom: 16px;">
                    Total: <strong><?php echo $staff_total; ?></strong> staff members.
                    <?php if ($staff_total_pages > 1): ?>
                        (Page <?php echo $staff_page; ?> of <?php echo $staff_total_pages; ?>)
                    <?php endif; ?>
                    Changes take effect on <strong>next login</strong>.
                </p>

                <div class="table-wrap">
                    <table class="table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Staff Name</th>
                                <th>Position (Job)</th>
                                <th>Department</th>
                                <th>Current Access</th>
                                <th>Set Access Level</th>
                                <th>Account Status</th>
                                <th style="width:80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allStaffPaginated as $staff): 
                                $hasUser = !empty($staff['user_id']);
                                $userRole = $staff['user_role'] ?? '';
                                $position = $staff['position'] ?? '';
                                $level = getAccessLevelLabel($position, $userRole);
                                $badgeClass = badgeClass($level);
                                $icon = levelIcon($level);
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($staff['full_name']); ?></strong>
                                        <br><small style="color:#888;"><?php echo htmlspecialchars($staff['staff_id'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($position ?: '—'); ?></td>
                                    <td><?php echo htmlspecialchars($staff['department'] ?? '—'); ?></td>
                                    <td>
                                        <?php if ($hasUser): ?>
                                            <span class="role-badge role-badge-<?php echo strtolower($level); ?>">
                                                <i class="<?php echo $icon; ?>"></i> <?php echo $level; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="role-badge role-badge-no-user">
                                                <i class="fas fa-exclamation-triangle"></i> No User
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($hasUser): ?>
                                            <form method="POST" class="role-form" style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                                <input type="hidden" name="action" value="update_role">
                                                <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                                <input type="hidden" name="page" value="<?php echo $staff_page; ?>">
                                                <?php csrf_field(); ?>
                                                <select name="access_level" class="level-select <?php echo strtolower($level); ?>" onchange="highlightSelect(this); toggleClassSelect(this, 'class-select-<?php echo $staff['id']; ?>')">
                                                    <option value="staff" <?php echo $level === 'Staff' ? 'selected' : ''; ?>>Staff</option>
                                                    <option value="teacher" <?php echo $level === 'Teacher' ? 'selected' : ''; ?>>Class Teacher</option>
                                                    <option value="bursar" <?php echo $level === 'Bursar' ? 'selected' : ''; ?>>Bursar</option>
                                                    <option value="admin" <?php echo $level === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                                </select>

                                                <?php
                                                $currentClassId = $classTeacherMap[(int)$staff['id']] ?? 0;
                                                $isTeacherLevel = ($level === 'Teacher');
                                                ?>
                                                <select name="class_id" class="class-select class-select-<?php echo $staff['id']; ?> level-select"
                                                        style="<?php echo $isTeacherLevel ? '' : 'display:none;'; ?>">
                                                    <option value="">— No class —</option>
                                                    <?php foreach ($allClasses as $c): ?>
                                                        <option value="<?php echo $c['id']; ?>" <?php echo ((int)$c['id'] === $currentClassId) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($c['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <button type="submit" class="update-btn"><i class="fas fa-check"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color:#999;font-size:0.85rem;">No account</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $userStatus = $staff['user_status'] ?? 'inactive';
                                        $isActive = $userStatus === 'active';
                                        $inviteSt = $hasUser ? getStaffInviteStatus((int)$staff['id']) : 'not_invited';
                                        $canToggle = $hasUser && $inviteSt === 'accepted';
                                        ?>
                                        <div class="status-toggle-wrap">
                                            <?php if ($canToggle): ?>
                                                <?php /* Click the status text to toggle */ ?>
                                                <form method="POST" style="margin:0;">
                                                    <input type="hidden" name="action" value="toggle_account_status">
                                                    <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                                    <input type="hidden" name="new_status" value="<?php echo $isActive ? 'inactive' : 'active'; ?>">
                                                    <input type="hidden" name="page" value="<?php echo $staff_page; ?>">
                                                    <?php csrf_field(); ?>
                                                    <span class="toggle-switch" style="display:inline-block;">
                                                        <span class="toggle-slider <?php echo $isActive ? 'enabled' : 'disabled'; ?>"></span>
                                                    </span>
                                                    <button type="submit" class="toggle-status-label <?php echo $isActive ? 'enabled' : 'disabled'; ?>"
                                                            style="border:none; background:transparent; padding:0; cursor:pointer; font:inherit;"
                                                            onclick="return confirm('<?php echo $isActive ? 'Suspend' : 'Approve'; ?> this staff account?');">
                                                        <?php echo $isActive ? 'Active' : 'Suspended'; ?>
                                                    </button>
                                                </form>
                                            <?php elseif ($hasUser && !$canToggle): ?>
                                                <?php /* Has a user account but hasn't accepted invite yet */ ?>
                                                <span class="toggle-switch" style="display:inline-block;">
                                                    <span class="toggle-slider disabled"></span>
                                                </span>
                                                <span class="toggle-status-label pending">
                                                    <i class="fas fa-clock" style="font-size:0.7rem;"></i> Pending Registration
                                                </span>
                                            <?php else: ?>
                                                <?php /* No linked user account at all */ ?>
                                                <span class="toggle-switch" style="display:inline-block;">
                                                    <span class="toggle-slider disabled"></span>
                                                </span>
                                                <span class="toggle-status-label no-account">
                                                    <i class="fas fa-user-slash" style="font-size:0.7rem;"></i> No Account
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($hasUser): ?>
                                            <a href="edit_staff.php?id=<?php echo $staff['id']; ?>" class="btn-admin-action btn-admin-sm" title="Edit staff details">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($allStaff)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:40px; color:#888;">
                                        <i class="fas fa-users" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                                        No staff members found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if ($staff_total_pages > 1): ?>
                    <div style="display:flex; justify-content:center; gap:5px; margin-top:20px; flex-wrap:wrap;">
                        <?php if ($staff_page > 1): ?>
                            <a href="?page=<?php echo $staff_page - 1; ?>" style="display:inline-flex; align-items:center; gap:5px; padding:8px 16px; background:#f8f9fa; color:#000; border:1px solid #ddd; border-radius:6px; text-decoration:none; font-size:14px;">&laquo; Prev</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $staff_total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" style="display:inline-flex; align-items:center; justify-content:center; min-width:38px; padding:8px 12px; background:<?php echo $i == $staff_page ? '#1a5276' : '#f8f9fa'; ?>; color:<?php echo $i == $staff_page ? '#fff' : '#000'; ?>; border:1px solid <?php echo $i == $staff_page ? '#1a5276' : '#ddd'; ?>; border-radius:6px; text-decoration:none; font-size:14px; font-weight:<?php echo $i == $staff_page ? '700' : '400'; ?>;"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($staff_page < $staff_total_pages): ?>
                            <a href="?page=<?php echo $staff_page + 1; ?>" style="display:inline-flex; align-items:center; gap:5px; padding:8px 16px; background:#f8f9fa; color:#000; border:1px solid #ddd; border-radius:6px; text-decoration:none; font-size:14px;">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function highlightSelect(select) {
            select.className = 'level-select ' + select.value;
        }

        function toggleClassSelect(levelSelect, classSelectClass) {
            var classSelect = document.querySelector('.' + classSelectClass);
            if (!classSelect) return;
            if (levelSelect.value === 'teacher') {
                classSelect.style.display = '';
            } else {
                classSelect.style.display = 'none';
            }
        }
    </script>
</body>
</html>
