<?php
/**
 * Staff/Teacher Portal — My Resource Assignments
 *
 * Shows all resources the teacher has assigned to their classes,
 * with ability to deactivate/reactivate or delete assignments.
 *
 * Access: staff, teacher
 * URL:    staff/resource_assignments.php
 */
require_once 'includes/db.php';

if (!isLoggedIn() || (!isStaff() && !isTeacher())) {
    redirect('../login.php');
}

$settings = fetchSettings($pdo);
$school_name = $settings['school_name'] ?? 'Nex CEC';

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM staff WHERE user_id = ?");
$stmt->execute([$user_id]);
$staff = $stmt->fetch();

if (!$staff) {
    echo '<div class="container" style="padding:100px 0;text-align:center;"><h2>Staff record not found</h2><a href="../logout.php" class="btn-primary">Logout</a></div>';
    exit;
}

$staff_id = (int)$staff['id'];

// ===== Handle actions =====
$msg = '';
$msg_type = '';

// Toggle active status
if (isset($_GET['toggle']) && (int)$_GET['toggle'] > 0) {
    $aid = (int)$_GET['toggle'];
    try {
        $stmt = $pdo->prepare("SELECT is_active FROM resource_assignments WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$aid, $staff_id]);
        $row = $stmt->fetch();
        if ($row) {
            $new_status = $row['is_active'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE resource_assignments SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$new_status, $aid, $staff_id]);
            $msg = $new_status ? 'Assignment re-activated.' : 'Assignment deactivated.';
            $msg_type = 'success';
        }
    } catch (Exception $e) {
        $msg = 'Error updating assignment.';
        $msg_type = 'error';
        error_log("toggle assignment error: " . $e->getMessage());
    }
}

// Delete assignment
if (isset($_GET['delete']) && (int)$_GET['delete'] > 0) {
    $aid = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM resource_assignments WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$aid, $staff_id]);
        if ($stmt->rowCount() > 0) {
            $msg = 'Assignment deleted.';
            $msg_type = 'success';
        }
    } catch (Exception $e) {
        $msg = 'Error deleting assignment.';
        $msg_type = 'error';
        error_log("delete assignment error: " . $e->getMessage());
    }
}

// Fetch all assignments for this teacher
$assignments = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            a.id, a.resource_id, a.class_id, a.subject_id,
            a.due_date, a.instructions, a.is_active, a.created_at,
            r.title AS resource_title, r.source AS resource_source,
            r.embed_type, r.url,
            c.name AS class_name,
            s.name AS subject_name
        FROM resource_assignments a
        LEFT JOIN resource_links r ON r.id = a.resource_id
        LEFT JOIN classes c ON c.id = a.class_id
        LEFT JOIN subjects s ON s.id = a.subject_id
        WHERE a.teacher_id = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$staff_id]);
    $assignments = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("fetch assignments error: " . $e->getMessage());
}

// Fetch unread count
$unread_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM messages m WHERE (m.receiver_id = ? OR m.is_broadcast = 1) AND NOT EXISTS (SELECT 1 FROM message_reads mr WHERE mr.message_id = m.id AND mr.user_id = ?)");
    $stmt->execute([$user_id, $user_id]);
    $unread_count = (int)$stmt->fetch()['cnt'];
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content { padding: 24px; }
        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 15px; margin-bottom: 24px;
        }
        .page-header h2 { margin: 0; font-size: 1.4rem; }
        .page-header h2 i { color: var(--primary-color); margin-right: 8px; }
        .page-header .btn-back {
            text-decoration: none; color: #666; font-size: 0.85rem;
            padding: 6px 14px; border: 1px solid #ddd; border-radius: 8px;
        }
        .page-header .btn-back:hover { background: #f5f5f5; }

        .flash-msg {
            padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;
            font-size: 0.85rem;
        }
        .flash-msg.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .flash-msg.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .assign-table {
            width: 100%; border-collapse: collapse; background: #fff;
            border-radius: 10px; overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .assign-table th {
            text-align: left; padding: 12px 16px; font-size: 0.8rem;
            font-weight: 600; color: #666; text-transform: uppercase;
            letter-spacing: 0.5px; background: #fafafa;
            border-bottom: 1px solid #eee;
        }
        .assign-table td {
            padding: 12px 16px; font-size: 0.88rem; border-bottom: 1px solid #f5f5f5;
            vertical-align: middle;
        }
        .assign-table tr:last-child td { border-bottom: none; }
        .assign-table tr:hover td { background: #fafbff; }

        .resource-title {
            font-weight: 600; color: #222; text-decoration: none;
        }
        .resource-title:hover { color: var(--primary-color); }

        .class-badge {
            display: inline-block; padding: 2px 10px; border-radius: 10px;
            font-size: 0.75rem; font-weight: 500; background: #eef2ff;
            color: #4f46e5;
        }
        .subject-tag {
            font-size: 0.78rem; color: #888;
        }
        .status-active {
            display: inline-block; padding: 2px 10px; border-radius: 10px;
            font-size: 0.7rem; font-weight: 600; background: #ecfdf5;
            color: #065f46;
        }
        .status-inactive {
            display: inline-block; padding: 2px 10px; border-radius: 10px;
            font-size: 0.7rem; font-weight: 600; background: #f5f5f5;
            color: #999;
        }
        .actions-cell a {
            text-decoration: none; font-size: 0.78rem; padding: 4px 10px;
            border-radius: 6px; margin-right: 4px;
        }
        .actions-cell .btn-toggle {
            color: #e65100; border: 1px solid #ffe0b2;
        }
        .actions-cell .btn-toggle:hover { background: #fff3e0; }
        .actions-cell .btn-delete {
            color: #e00; border: 1px solid #fecaca;
        }
        .actions-cell .btn-delete:hover { background: #fef2f2; }

        .empty-state {
            text-align: center; padding: 60px 20px; color: #888;
        }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; color: #ccc; }
        .empty-state h3 { color: #555; margin-bottom: 8px; }
        .empty-state .btn-primary {
            display: inline-block; margin-top: 12px; text-decoration: none;
        }

        @media (max-width: 768px) {
            .assign-table { font-size: 0.8rem; }
            .assign-table th, .assign-table td { padding: 8px 10px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .hide-mobile { display: none; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php echo renderStaffSidebar('resource_assignments', $school_name, $unread_count, $staff['profile_picture'] ?? '', $staff['full_name'] ?? ''); ?>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <a href="resources.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Resources</a>
                    <h2 style="margin-top:12px;"><i class="fas fa-tasks"></i> My Assignments</h2>
                </div>
                <span style="font-size:0.85rem; color:#888;">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($staff['full_name'] ?? ''); ?>
                </span>
            </div>

            <?php if ($msg): ?>
                <div class="flash-msg <?php echo $msg_type; ?>">
                    <i class="fas <?php echo $msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($assignments)): ?>
                <div class="empty-state">
                    <i class="fas fa-tasks"></i>
                    <h3>No assignments yet</h3>
                    <p>You haven't assigned any resources yet. Browse the resource library to get started.</p>
                    <a href="resources.php" class="btn-primary"><i class="fas fa-bookmark"></i> Browse Resources</a>
                </div>
            <?php else: ?>
                <p style="font-size:0.85rem;color:#888;margin-bottom:12px;">
                    <i class="fas fa-info-circle"></i>
                    You have <?php echo count($assignments); ?> assignment<?php echo count($assignments) !== 1 ? 's' : ''; ?>.
                    Students and parents can view active assignments.
                </p>
                <div style="overflow-x:auto;">
                    <table class="assign-table">
                        <thead>
                            <tr>
                                <th>Resource</th>
                                <th>Class</th>
                                <th class="hide-mobile">Subject</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $a):
                                $due = !empty($a['due_date']) ? date('M j, Y', strtotime($a['due_date'])) : '—';
                            ?>
                            <tr>
                                <td>
                                    <a href="../resource_redirect.php?id=<?php echo (int)$a['resource_id']; ?>" target="_blank" class="resource-title">
                                        <?php echo htmlspecialchars($a['resource_title'] ?? 'Untitled'); ?>
                                    </a>
                                    <div style="font-size:0.75rem;color:#999;margin-top:2px;">
                                        <i class="fas fa-globe"></i> <?php echo htmlspecialchars($a['resource_source'] ?: 'Resource'); ?>
                                    </div>
                                    <?php if (!empty($a['instructions'])): ?>
                                        <div style="font-size:0.78rem;color:#666;margin-top:4px;font-style:italic;">
                                            <?php echo htmlspecialchars(substr($a['instructions'], 0, 80)) . (strlen($a['instructions'] ?? '') > 80 ? '…' : ''); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="class-badge"><?php echo htmlspecialchars($a['class_name'] ?? '—'); ?></span></td>
                                <td class="hide-mobile"><span class="subject-tag"><?php echo htmlspecialchars($a['subject_name'] ?? 'Any'); ?></span></td>
                                <td style="font-size:0.85rem;"><?php echo $due; ?></td>
                                <td>
                                    <?php if ($a['is_active']): ?>
                                        <span class="status-active"><i class="fas fa-check-circle"></i> Active</span>
                                    <?php else: ?>
                                        <span class="status-inactive"><i class="fas fa-pause-circle"></i> Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <a href="?toggle=<?php echo (int)$a['id']; ?>" class="btn-toggle"
                                       onclick="return confirm('<?php echo $a['is_active'] ? 'Deactivate' : 'Activate'; ?> this assignment?')">
                                        <i class="fas <?php echo $a['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                        <?php echo $a['is_active'] ? 'Pause' : 'Activate'; ?>
                                    </a>
                                    <a href="?delete=<?php echo (int)$a['id']; ?>" class="btn-delete"
                                       onclick="return confirm('Delete this assignment permanently?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
