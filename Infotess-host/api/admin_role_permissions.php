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

                // Log the change
                $old_level_label = getAccessLevelLabel($position, $staff['user_role'] ?? $new_role);
                $new_level_label = getAccessLevelLabel($new_position, $new_role);
                error_log("Role permission change: Staff #$staff_id ($name) from '$old_level_label' to '$new_level_label' by user #{$_SESSION['user_id']}");

                $message = "Updated <strong>" . htmlspecialchars($name) . "</strong> → <strong>" . ucfirst($new_access) . "</strong> access.";
            }
        } catch (Exception $e) {
            $error = "Error updating role: " . $e->getMessage();
        }
    }
}

// Fetch ALL staff with their linked user accounts
$allStaff = [];
try {
    $stmt = $pdo->query("
        SELECT s.*, u.role AS user_role, u.email AS user_email, u.is_password_reset
        FROM staff s
        LEFT JOIN users u ON s.user_id = u.id
        ORDER BY s.department, s.full_name
    ");
    $allStaff = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

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

            <div class="card">
                <h3><i class="fas fa-users"></i> All Staff Members</h3>
                <p style="color:#666; margin-bottom: 16px;">
                    Total: <strong><?php echo count($allStaff); ?></strong> staff members.
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
                                <th style="width:80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allStaff as $staff): 
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
                                                <?php csrf_field(); ?>
                                                <select name="access_level" class="level-select <?php echo strtolower($level); ?>" onchange="highlightSelect(this)">
                                                    <option value="staff" <?php echo $level === 'Staff' ? 'selected' : ''; ?>>Staff</option>
                                                    <option value="teacher" <?php echo $level === 'Teacher' ? 'selected' : ''; ?>>Class Teacher</option>
                                                    <option value="bursar" <?php echo $level === 'Bursar' ? 'selected' : ''; ?>>Bursar</option>
                                                    <option value="admin" <?php echo $level === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                                </select>
                                                <button type="submit" class="update-btn"><i class="fas fa-check"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color:#999;font-size:0.85rem;">No account</span>
                                        <?php endif; ?>
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
                                    <td colspan="6" style="text-align:center; padding:40px; color:#888;">
                                        <i class="fas fa-users" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                                        No staff members found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
            <div style="display:flex; gap:16px; flex-wrap:wrap; margin-top:20px;">
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
        </main>
    </div>

    <script>
        function highlightSelect(select) {
            select.className = 'level-select ' + select.value;
        }
    </script>
</body>
</html>
