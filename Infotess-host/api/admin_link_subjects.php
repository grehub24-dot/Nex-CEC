<?php
/**
 * Admin tool: Link subjects to existing Class Teacher assignments.
 *
 * For each entry in class_teachers, creates per-teacher-class subject records
 * so that staff_grades.php shows subjects in the dropdown.
 *
 * Access: admin only
 * URL:    subjects.php (linked from admin menu) or direct
 */
require_once 'includes/db.php';
requireAccess('subjects');

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

// Handle action: link all
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'link_all') {
    validate_request_csrf();

    try {
        // Fetch all class_teachers entries
        $ctList = $pdo->query("SELECT ct.staff_id, ct.class_id, s.full_name AS staff_name, c.name AS class_name
                                FROM class_teachers ct
                                JOIN staff s ON s.id = ct.staff_id
                                JOIN classes c ON c.id = ct.class_id")->fetchAll();

        if (empty($ctList)) {
            $error = 'No Class Teacher assignments found. Assign teachers via Role Permissions first.';
        } else {
            $total_linked = 0;
            $total_errors = 0;
            $results = [];

            foreach ($ctList as $ct) {
                $staff_id = (int)$ct['staff_id'];
                $class_id = (int)$ct['class_id'];
                $linked = linkTeacherClassSubjects($pdo, $staff_id, $class_id);

                if ($linked > 0) {
                    $total_linked += $linked;
                    $results[] = htmlspecialchars($ct['staff_name']) . " → " . htmlspecialchars($ct['class_name']) . ": $linked subject(s) linked";
                } else {
                    $results[] = htmlspecialchars($ct['staff_name']) . " → " . htmlspecialchars($ct['class_name']) . ": no new subjects (already linked or no matching category)";
                }
            }

            $message = "Linking complete. Total: $total_linked subject(s) linked across " . count($ctList) . " teacher(s).";
            // Store results for display
            $_SESSION['link_results'] = $results;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }

    // Redirect to avoid form re-submission
    $redirect = 'admin/link_subjects.php';
    header("Location: $redirect");
    exit;
}

// Handle action: link single entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'link_one') {
    validate_request_csrf();
    $staff_id = (int)($_POST['staff_id'] ?? 0);
    $class_id = (int)($_POST['class_id'] ?? 0);

    if ($staff_id > 0 && $class_id > 0) {
        $linked = linkTeacherClassSubjects($pdo, $staff_id, $class_id);
        if ($linked > 0) {
            $message = "Linked $linked subject(s) for staff #$staff_id, class #$class_id.";
        } else {
            $message = "No new subjects linked (already done or no matching category).";
        }
    } else {
        $error = "Invalid staff or class ID.";
    }
}

// Handle action: unlink (remove all teacher-linked subject records for a teacher)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unlink') {
    validate_request_csrf();
    $staff_id = (int)($_POST['staff_id'] ?? 0);

    if ($staff_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM subjects WHERE teacher_id = ? AND class_id IS NOT NULL");
            $stmt->execute([$staff_id]);
            $deleted = $stmt->rowCount();
            $message = "Removed $deleted subject link(s) for staff #$staff_id.";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Invalid staff ID.";
    }
}

// Fetch current class_teachers with staff and class info
$assignments = [];
try {
    $stmt = $pdo->query("SELECT ct.staff_id, ct.class_id, ct.assigned_at,
                                s.full_name AS staff_name,
                                c.name AS class_name,
                                (SELECT COUNT(*) FROM subjects WHERE teacher_id = ct.staff_id AND class_id = ct.class_id) AS subject_count
                         FROM class_teachers ct
                         JOIN staff s ON s.id = ct.staff_id
                         JOIN classes c ON c.id = ct.class_id
                         ORDER BY s.full_name");
    $assignments = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error fetching assignments: " . $e->getMessage();
}

// Fetch unlinked teacher subjects count
$orphan_count = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM subjects WHERE teacher_id IS NULL AND class_id IS NULL");
    $orphan_count = (int)$stmt->fetch()['cnt'];
} catch (Exception $e) {}

// Get stored results from session
$link_results = $_SESSION['link_results'] ?? [];
unset($_SESSION['link_results']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Subjects — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            text-align: center;
        }
        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        .stat-card .label {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        .result-item {
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 4px;
            font-size: 0.9rem;
        }
        .result-item.success { background: #d4edda; color: #155724; }
        .result-item.skipped { background: #fff3cd; color: #856404; }
        .action-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-linked { background: #d4edda; color: #155724; }
        .badge-unlinked { background: #f8d7da; color: #721c24; }
        .badge-partial { background: #fff3cd; color: #856404; }
        @media (max-width: 768px) {
            .stat-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php echo renderSidebar('subjects', $school_name); ?>

        <main class="main-content">
            <div class="top-bar">
                <h2><i class="fas fa-link"></i> Link Subjects to Class Teachers</h2>
                <div>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Link subjects for ALL Class Teacher assignments? This will skip already-linked subjects.');">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="link_all">
                        <button type="submit" class="btn-admin-action btn-admin-success">
                            <i class="fas fa-sync"></i> Link All
                        </button>
                    </form>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (!empty($link_results)): ?>
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header"><h3>Last Link Results</h3></div>
                    <div class="card-content">
                        <?php foreach ($link_results as $r): ?>
                            <div class="result-item <?php echo strpos($r, 'linked') !== false ? 'success' : 'skipped'; ?>">
                                <i class="fas <?php echo strpos($r, 'linked') !== false ? 'fa-check-circle' : 'fa-info-circle'; ?>"></i>
                                <?php echo $r; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="number"><?php echo count($assignments); ?></div>
                    <div class="label">Class Teacher Assignments</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $orphan_count; ?></div>
                    <div class="label">Master Subjects (unlinked)</div>
                </div>
                <div class="stat-card">
                    <div class="number">
                        <?php
                        $total_subj = 0;
                        foreach ($assignments as $a) { $total_subj += (int)$a['subject_count']; }
                        echo $total_subj;
                        ?>
                    </div>
                    <div class="label">Linked Subject Records</div>
                </div>
            </div>

            <!-- Assignments Table -->
            <div class="card">
                <h3><i class="fas fa-chalkboard-teacher"></i> Current Class Teacher Assignments</h3>
                <p style="color:#666; margin-bottom:15px;">
                    These entries show which teachers are assigned to which classes.
                    Click <strong>"Link Subjects"</strong> to create per-teacher subject records so they appear in the SBA/Grades page.
                </p>

                <div class="table-responsive">
                    <table class="table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Class</th>
                                <th>Assigned At</th>
                                <th>Subjects Linked</th>
                                <th style="width:200px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($assignments)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:40px; color:#888;">
                                        <i class="fas fa-users" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                                        No Class Teacher assignments found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($assignments as $a):
                                    $subj_count = (int)$a['subject_count'];
                                    $badge_class = $subj_count > 0 ? 'badge-linked' : 'badge-unlinked';
                                    $badge_text = $subj_count > 0 ? "$subj_count linked" : 'Not linked';
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($a['staff_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($a['class_name']); ?></td>
                                    <td><?php echo htmlspecialchars($a['assigned_at'] ?? '—'); ?></td>
                                    <td>
                                        <span class="action-badge <?php echo $badge_class; ?>">
                                            <?php echo $badge_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="action" value="link_one">
                                            <input type="hidden" name="staff_id" value="<?php echo (int)$a['staff_id']; ?>">
                                            <input type="hidden" name="class_id" value="<?php echo (int)$a['class_id']; ?>">
                                            <button type="submit" class="btn-admin-action btn-admin-sm <?php echo $subj_count > 0 ? 'btn-admin-secondary' : 'btn-admin-success'; ?>">
                                                <i class="fas fa-link"></i> Link Subjects
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove all subject links for this teacher? This will NOT affect class_teachers assignment.');">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="action" value="unlink">
                                            <input type="hidden" name="staff_id" value="<?php echo (int)$a['staff_id']; ?>">
                                            <button type="submit" class="btn-admin-action btn-admin-danger btn-admin-sm" <?php echo $subj_count === 0 ? 'disabled' : ''; ?>>
                                                <i class="fas fa-unlink"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Info Card -->
            <div class="card" style="margin-top: 20px;">
                <div class="card-content">
                    <h3><i class="fas fa-info-circle"></i> How It Works</h3>
                    <ol style="margin-top:10px; line-height:1.8; color:#555;">
                        <li><strong>Master Subjects</strong> — The <?php echo $orphan_count; ?> subjects in the database with no teacher/class assignment. These are the curriculum templates.</li>
                        <li><strong>Linking</strong> — Creates copies of master subjects with <code>teacher_id</code> and <code>class_id</code> set, so they appear in the teacher's SBA/Grades dropdown.</li>
                        <li><strong>Auto-Assignment</strong> — When a new Class Teacher is assigned via <strong>Role Permissions</strong>, subjects are linked automatically.</li>
                        <li><strong>Unlinking</strong> — Removes the teacher-specific subject records (but preserves master subjects).</li>
                    </ol>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
