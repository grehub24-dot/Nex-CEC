<?php
/**
 * Staff/Teacher Portal — Teaching & Learning Resource Library
 *
 * Shows curated educational resources (TLMs, worksheets, games, videos)
 * filtered by the teacher's assigned classes and subjects.
 * Teachers can also assign resources as homework to their classes.
 * All external URLs are masked through resource.php?id=X.
 *
 * Access: staff, teacher
 * URL:    staff/resources.php
 */
require_once 'includes/db.php';

if (!isLoggedIn() || (!isStaff() && !isTeacher())) {
    redirect('../login.php');
}

$settings = fetchSettings($pdo);
$school_name = $settings['school_name'] ?? 'Nex CEC';

$user_id = $_SESSION['user_id'];

// Fetch staff record
$stmt = $pdo->prepare("SELECT * FROM staff WHERE user_id = ?");
$stmt->execute([$user_id]);
$staff = $stmt->fetch();

if (!$staff) {
    echo '<div class="container" style="padding:100px 0;text-align:center;"><h2>Staff record not found</h2><a href="../logout.php" class="btn-primary">Logout</a></div>';
    exit;
}

$staff_id = (int)$staff['id'];
$isTchr = isTeacher();

// ===== Handle POST: Assign resource as homework =====
$assign_success = null;
$assign_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_resource') {
    $resource_id = (int)($_POST['resource_id'] ?? 0);
    $class_id = (int)($_POST['class_id'] ?? 0);
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $due_date = trim($_POST['due_date'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');

    if ($resource_id <= 0 || $class_id <= 0) {
        $assign_error = 'Please select a resource and class.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO resource_assignments (resource_id, teacher_id, class_id, subject_id, instructions, due_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $resource_id, $staff_id, $class_id,
                $subject_id > 0 ? $subject_id : null,
                !empty($instructions) ? $instructions : null,
                !empty($due_date) ? $due_date : null
            ]);
            $assign_success = 'Resource assigned to class successfully!';
        } catch (Exception $e) {
            $assign_error = 'Failed to assign resource: ' . $e->getMessage();
            error_log("assign_resource error: " . $e->getMessage());
        }
    }
}

// Get the teacher's assigned class IDs
$teacher_class_ids = getTeacherClassIds($pdo);

// Get the teacher's assigned subjects (for filtering)
$teacher_subjects = [];
if (!empty($teacher_class_ids)) {
    $placeholders = implode(',', array_fill(0, count($teacher_class_ids), '?'));
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT s.id, s.name, s.code FROM subjects s WHERE s.teacher_id = ? AND s.class_id IN ($placeholders) ORDER BY s.name");
        $stmt->execute(array_merge([$staff_id], $teacher_class_ids));
        $teacher_subjects = $stmt->fetchAll();
    } catch (Exception $e) {}
}

// Fetch class names for display
$class_names = [];
if (!empty($teacher_class_ids)) {
    $placeholders = implode(',', array_fill(0, count($teacher_class_ids), '?'));
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM classes WHERE id IN ($placeholders) ORDER BY name");
        $stmt->execute($teacher_class_ids);
        while ($row = $stmt->fetch()) {
            $class_names[$row['id']] = $row['name'];
        }
    } catch (Exception $e) {}
}

// Fetch resources with class filter
$selected_class = (int)($_GET['class_id'] ?? 0);
$selected_subject = (int)($_GET['subject_id'] ?? 0);

$resources = [];
try {
    $query = "SELECT r.* FROM resource_links r WHERE r.is_active = 1";
    $params = [];

    if (!empty($teacher_class_ids)) {
        $class_placeholders = implode(',', array_fill(0, count($teacher_class_ids), '?'));
        $query .= " AND (r.class_id IN ($class_placeholders) OR r.class_id IS NULL)";
        $params = array_merge($params, $teacher_class_ids);
    }

    // Filter by specific class if selected
    if ($selected_class > 0 && in_array($selected_class, $teacher_class_ids)) {
        $query .= " AND (r.class_id = ? OR r.class_id IS NULL)";
        $params[] = $selected_class;
    }

    // Filter by subject if selected
    if ($selected_subject > 0) {
        $query .= " AND (r.subject_id = ? OR r.subject_id IS NULL)";
        $params[] = $selected_subject;
    }

    $query .= " ORDER BY r.sort_order, r.title";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $resources = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("staff_resources fetch error: " . $e->getMessage());
}

// Fetch unread message count
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
    <title>Teaching Resources — <?php echo htmlspecialchars($school_name); ?></title>
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

        .class-pills {
            display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px;
        }
        .class-pill {
            padding: 6px 16px; border-radius: 20px; font-size: 0.8rem;
            font-weight: 500; background: #eef2ff; color: #4f46e5;
        }
        .class-pill i { margin-right: 4px; }

        .filter-bar {
            display: flex; gap: 12px; flex-wrap: wrap; align-items: center;
            margin-bottom: 24px; padding: 16px; background: #fff;
            border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .filter-bar label { font-weight: 600; font-size: 0.85rem; color: #555; }
        .filter-bar select {
            padding: 6px 12px; border: 1px solid #ddd; border-radius: 6px;
            font-size: 0.85rem; background: #fff;
        }
        .filter-bar .btn-filter {
            padding: 6px 16px; background: var(--primary-color); color: #fff;
            border: none; border-radius: 6px; cursor: pointer; font-size: 0.85rem;
        }
        .filter-bar .btn-filter:hover { opacity: 0.9; }
        .filter-bar .btn-clear {
            padding: 6px 16px; background: #f5f5f5; color: #666;
            border: 1px solid #ddd; border-radius: 6px; cursor: pointer;
            font-size: 0.85rem; text-decoration: none;
        }
        .filter-bar .btn-clear:hover { background: #eee; }

        .resource-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
        }
        .resource-card {
            background: #fff; border-radius: 12px; padding: 20px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            border: 1px solid #f0f0f0;
            transition: box-shadow 0.2s, transform 0.2s;
            display: flex; flex-direction: column;
        }
        .resource-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            transform: translateY(-2px);
        }
        .card-source {
            display: inline-block; padding: 2px 10px; border-radius: 10px;
            font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.5px; margin-bottom: 10px; width: fit-content;
        }
        .card-source.hand2mind { background: #e3f2fd; color: #0d47a1; }
        .card-source.pbskids { background: #fce4ec; color: #880e4f; }
        .card-source.kiddoworksheets { background: #e8f5e9; color: #1b5e20; }
        .card-source.khanacademy { background: #fff3e0; color: #e65100; }
        .card-source.scratch { background: #fce4ec; color: #c62828; }
        .card-source.blockly { background: #e8eaf6; color: #283593; }
        .card-source.nasa { background: #e0f2f1; color: #004d40; }
        .card-source.other { background: #f5f5f5; color: #616161; }

        .card-title {
            font-size: 1rem; font-weight: 600; color: #222;
            margin-bottom: 8px; line-height: 1.4;
        }
        .card-desc {
            font-size: 0.85rem; color: #666; line-height: 1.5;
            margin-bottom: 12px; flex: 1;
        }
        .card-meta {
            display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px;
        }
        .card-tag {
            font-size: 0.75rem; padding: 2px 8px; border-radius: 6px;
            background: #f5f5f5; color: #666;
        }
        .card-tag i { margin-right: 3px; font-size: 0.65rem; }
        .card-actions { margin-top: auto; }
        .btn-open {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 20px; border-radius: 8px; font-size: 0.85rem;
            font-weight: 500; text-decoration: none; cursor: pointer;
            border: none; transition: all 0.2s; width: 100%;
            justify-content: center;
        }
        .btn-open.iframe {
            background: var(--primary-color); color: #fff;
        }
        .btn-open.iframe:hover { opacity: 0.9; }
        .btn-open.redirect {
            background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2;
        }
        .btn-open.redirect:hover { background: #ffe0b2; }

        .btn-assign {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 14px; border-radius: 8px; font-size: 0.8rem;
            font-weight: 500; cursor: pointer; border: 1px solid #ddd;
            background: #f9f9f9; color: #555; transition: all 0.2s;
            text-decoration: none;
        }
        .btn-assign:hover { background: #eef2ff; color: #4f46e5; border-color: #c7d2fe; }
        .btn-assign i { font-size: 0.75rem; }

        .card-actions { display: flex; gap: 8px; margin-top: auto; }
        .card-actions .btn-open { flex: 1; }

        /* Modal */
        .modal-overlay {
            display: none; position: fixed; z-index: 9999;
            inset: 0; background: rgba(0,0,0,0.5);
            align-items: center; justify-content: center;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #fff; border-radius: 14px; padding: 28px;
            max-width: 480px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            max-height: 90vh; overflow-y: auto;
        }
        .modal-box h3 {
            margin: 0 0 6px 0; font-size: 1.15rem;
        }
        .modal-box p.modal-desc {
            font-size: 0.85rem; color: #666; margin: 0 0 20px 0;
        }
        .modal-box .form-group {
            margin-bottom: 16px;
        }
        .modal-box .form-group label {
            display: block; font-weight: 600; font-size: 0.85rem;
            color: #333; margin-bottom: 4px;
        }
        .modal-box .form-group select,
        .modal-box .form-group input,
        .modal-box .form-group textarea {
            width: 100%; padding: 8px 12px; border: 1px solid #ddd;
            border-radius: 8px; font-size: 0.9rem; font-family: inherit;
            box-sizing: border-box;
        }
        .modal-box .form-group textarea {
            resize: vertical; min-height: 60px;
        }
        .modal-actions {
            display: flex; gap: 10px; justify-content: flex-end;
            margin-top: 20px; padding-top: 16px; border-top: 1px solid #f0f0f0;
        }
        .btn-modal-cancel {
            padding: 8px 20px; border: 1px solid #ddd; border-radius: 8px;
            background: #fff; color: #666; cursor: pointer; font-size: 0.85rem;
        }
        .btn-modal-cancel:hover { background: #f5f5f5; }
        .btn-modal-submit {
            padding: 8px 24px; border: none; border-radius: 8px;
            background: var(--primary-color); color: #fff; cursor: pointer;
            font-size: 0.85rem; font-weight: 600;
        }
        .btn-modal-submit:hover { opacity: 0.9; }

        .flash-msg {
            padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;
            font-size: 0.85rem;
        }
        .flash-msg.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .flash-msg.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .empty-state {
            text-align: center; padding: 60px 20px; color: #888;
        }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; color: #ccc; }
        .empty-state h3 { color: #555; margin-bottom: 8px; }

        @media (max-width: 768px) {
            .resource-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .page-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php echo renderStaffSidebar('resources', $school_name, $unread_count, $staff['profile_picture'] ?? '', $staff['full_name'] ?? ''); ?>

        <main class="main-content">
            <div class="page-header">
                <h2><i class="fas fa-bookmark"></i> Teaching & Learning Resources</h2>
                <span style="font-size:0.85rem; color:#888;">
                    <i class="fas fa-graduation-cap"></i>
                    <?php echo htmlspecialchars($staff['full_name'] ?? ''); ?>
                </span>
            </div>

            <!-- Class info pills -->
            <?php if (!empty($class_names)): ?>
            <div class="class-pills">
                <?php foreach ($class_names as $cid => $cname): ?>
                    <span class="class-pill"><i class="fas fa-users"></i> <?php echo htmlspecialchars($cname); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($assign_success): ?>
                <div class="flash-msg success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($assign_success); ?></div>
            <?php elseif ($assign_error): ?>
                <div class="flash-msg error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($assign_error); ?></div>
            <?php endif; ?>

            <!-- Filter bar -->
            <div class="filter-bar">
                <label for="classFilter"><i class="fas fa-filter"></i> Class:</label>
                <select name="class_id" id="classFilter" onchange="applyFilter()">
                    <option value="0">All My Classes</option>
                    <?php foreach ($class_names as $cid => $cname): ?>
                        <option value="<?php echo $cid; ?>" <?php echo $selected_class === $cid ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cname); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="subjectFilter">Subject:</label>
                <select name="subject_id" id="subjectFilter" onchange="applyFilter()">
                    <option value="0">All Subjects</option>
                    <?php foreach ($teacher_subjects as $s): ?>
                        <option value="<?php echo (int)$s['id']; ?>" <?php echo $selected_subject === (int)$s['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if ($selected_class > 0 || $selected_subject > 0): ?>
                    <a href="resources.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>

                <span style="margin-left:auto;font-size:0.8rem;color:#999;">
                    <i class="fas fa-lightbulb"></i> Assign resources for homework or home learning
                </span>
            </div>

            <!-- Resource grid -->
            <?php if (empty($resources)): ?>
                <div class="empty-state">
                    <i class="fas fa-bookmark"></i>
                    <h3>No resources available</h3>
                    <p>There are no resources linked to your classes yet. Check back later.</p>
                </div>
            <?php else: ?>
                <div class="resource-grid">
                    <?php foreach ($resources as $r):
                        $src = $r['source'] ?: 'other';
                        $is_iframe = $r['embed_type'] === 'iframe';
                        $view_url = $is_iframe ? "../resource.php?id=" . (int)$r['id'] : "../resource_redirect.php?id=" . (int)$r['id'];
                    ?>
                    <div class="resource-card">
                        <span class="card-source <?php echo htmlspecialchars($src); ?>">
                            <?php echo htmlspecialchars($r['source'] ?: 'Resource'); ?>
                        </span>
                        <div class="card-title"><?php echo htmlspecialchars($r['title']); ?></div>
                        <?php if ($r['description']): ?>
                            <div class="card-desc"><?php echo htmlspecialchars($r['description']); ?></div>
                        <?php endif; ?>
                        <div class="card-meta">
                            <?php if ($r['category']): ?>
                                <span class="card-tag"><i class="fas fa-tag"></i> <?php echo htmlspecialchars(ucfirst($r['category'])); ?></span>
                            <?php endif; ?>
                            <span class="card-tag">
                                <i class="fas <?php echo $is_iframe ? 'fa-eye' : 'fa-external-link-alt'; ?>"></i>
                                <?php echo $is_iframe ? 'Opens inline' : 'Opens in new tab'; ?>
                            </span>
                        </div>
                        <div class="card-actions">
                            <a href="<?php echo $view_url; ?>" class="btn-open <?php echo $is_iframe ? 'iframe' : 'redirect'; ?>"
                               <?php echo !$is_iframe ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                <i class="fas <?php echo $is_iframe ? 'fa-eye' : 'fa-external-link-alt'; ?>"></i>
                                <?php echo $is_iframe ? 'Open' : 'Launch'; ?>
                            </a>
                            <button type="button" class="btn-assign"
                                    onclick="openAssignModal(<?php echo (int)$r['id']; ?>, '<?php echo htmlspecialchars($r['title'], ENT_QUOTES); ?>')"
                                    title="Assign as homework or home learning">
                                <i class="fas fa-tasks"></i> Assign
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Assign as Homework / Home Learning Modal -->
    <div class="modal-overlay" id="assignModal">
        <div class="modal-box">
            <h3><i class="fas fa-tasks" style="color:var(--primary-color);margin-right:6px;"></i> Assign Resource</h3>
            <p class="modal-desc">
                Assign this resource as homework or home learning activity.
                Students can access it from their dashboard; parents can access it from their portal.
            </p>
            <form method="POST" action="resources.php">
                <input type="hidden" name="action" value="assign_resource">
                <input type="hidden" name="resource_id" id="modalResourceId" value="0">

                <div class="form-group">
                    <label>Resource</label>
                    <p id="modalResourceTitle" style="font-weight:600;color:#222;margin:0;padding:8px 0;font-size:0.95rem;"></p>
                </div>

                <div class="form-group">
                    <label for="modalClassId">Assign to Class <span style="color:#e00;">*</span></label>
                    <select name="class_id" id="modalClassId" required>
                        <option value="">— Select a class —</option>
                        <?php foreach ($class_names as $cid => $cname): ?>
                            <option value="<?php echo $cid; ?>" <?php echo $selected_class === $cid ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cname); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modalSubjectId">Subject (optional)</label>
                    <select name="subject_id" id="modalSubjectId">
                        <option value="0">— Any subject —</option>
                        <?php foreach ($teacher_subjects as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>">
                                <?php echo htmlspecialchars($s['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modalDueDate">Due Date (optional)</label>
                    <input type="date" name="due_date" id="modalDueDate">
                </div>

                <div class="form-group">
                    <label for="modalInstructions">Instructions for students (optional)</label>
                    <textarea name="instructions" id="modalInstructions" placeholder="e.g. Complete the activity and write down three things you learned..."></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-modal-cancel" onclick="closeAssignModal()">Cancel</button>
                    <button type="submit" class="btn-modal-submit"><i class="fas fa-check"></i> Assign Now</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function applyFilter() {
        var classId = document.getElementById('classFilter').value;
        var subjectId = document.getElementById('subjectFilter').value;
        var url = 'resources.php';
        var params = [];
        if (parseInt(classId) > 0) params.push('class_id=' + classId);
        if (parseInt(subjectId) > 0) params.push('subject_id=' + subjectId);
        if (params.length > 0) url += '?' + params.join('&');
        window.location.href = url;
    }

    function openAssignModal(resourceId, resourceTitle) {
        document.getElementById('modalResourceId').value = resourceId;
        document.getElementById('modalResourceTitle').textContent = resourceTitle;
        document.getElementById('assignModal').classList.add('active');
    }

    function closeAssignModal() {
        document.getElementById('assignModal').classList.remove('active');
    }

    // Close modal on overlay click
    document.addEventListener('DOMContentLoaded', function () {
        var overlay = document.getElementById('assignModal');
        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeAssignModal();
            });
        }
    });
    </script>
</body>
</html>
