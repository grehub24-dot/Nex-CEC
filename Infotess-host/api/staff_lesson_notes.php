<?php
/**
 * Staff Lesson Notes — teachers view and create lesson notes
 * for their assigned classes/subjects.
 */
require_once 'includes/db.php';

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$user_id = $_SESSION['user_id'];
$staff_name = $_SESSION['fullname'] ?? 'Staff';

// Fetch staff record
$stmt = $pdo->prepare("SELECT * FROM staff WHERE user_id = ?");
$stmt->execute([$user_id]);
$staff = $stmt->fetch();
if (!$staff) {
    echo '<div class="container" style="padding:100px 0;text-align:center;"><h2>Staff record not found</h2><a href="login.php">Login</a></div>';
    exit;
}
$staff_id = (int)$staff['id'];

// --- Handle POST: Create lesson ---
$message = '';
$msg_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $key_concepts = trim($_POST['key_concepts'] ?? '');
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $class_id = (int)($_POST['class_id'] ?? 0);
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if (empty($title)) {
        $message = 'Title is required.';
        $msg_type = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO lesson_notes (title, content, key_concepts, subject_id, class_id, created_by, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $content, $key_concepts, $subject_id > 0 ? $subject_id : null, $class_id > 0 ? $class_id : null, $staff_id, $sort_order]);
            $lesson_id = $pdo->lastInsertId();

            // Attach selected resources
            if (!empty($_POST['resource_ids']) && is_array($_POST['resource_ids'])) {
                $attachStmt = $pdo->prepare("INSERT INTO lesson_note_resources (lesson_note_id, resource_link_id, label, sort_order) VALUES (?, ?, ?, 0)");
                foreach ($_POST['resource_ids'] as $rid) {
                    $rid = (int)$rid;
                    if ($rid > 0) $attachStmt->execute([$lesson_id, $rid]);
                }
            }

            $message = 'Lesson note created successfully!';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $msg_type = 'danger';
        }
    }
}

// Get teacher's class IDs
$teacher_class_ids = getTeacherClassIds($pdo);

// Get teacher's subjects
$teacher_subjects = [];
$teacher_classes = [];
if (!empty($teacher_class_ids)) {
    $placeholders = implode(',', array_fill(0, count($teacher_class_ids), '?'));
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT s.id, s.name FROM subjects s WHERE s.teacher_id = ? AND s.class_id IN ($placeholders) ORDER BY s.name");
        $stmt->execute(array_merge([$staff_id], $teacher_class_ids));
        $teacher_subjects = $stmt->fetchAll();
    } catch (Exception $e) {}
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM classes WHERE id IN ($placeholders) AND is_active = 1 ORDER BY name");
        $stmt->execute($teacher_class_ids);
        $teacher_classes = $stmt->fetchAll();
    } catch (Exception $e) {}
}

// Get available resources (for attaching)
$all_resources = [];
try {
    $stmt = $pdo->query("SELECT id, title, source FROM resource_links WHERE is_active = 1 ORDER BY source, title");
    $all_resources = $stmt->fetchAll();
} catch (Exception $e) {}

// Load lesson notes: teacher's own + shared (all classes) for their subjects
$lesson_notes = [];
try {
    $class_conditions = empty($teacher_class_ids) ? '1=0' : 'ln.class_id IN (' . implode(',', array_map('intval', $teacher_class_ids)) . ')';
    $stmt = $pdo->query("
        SELECT ln.*, s.name AS subject_name, c.name AS class_name, st.fullname AS author_name
        FROM lesson_notes ln
        LEFT JOIN subjects s ON ln.subject_id = s.id
        LEFT JOIN classes c ON ln.class_id = c.id
        LEFT JOIN staff st ON ln.created_by = st.id
        WHERE ln.is_active = 1
          AND (ln.class_id IS NULL OR $class_conditions)
        ORDER BY ln.sort_order ASC, ln.updated_at DESC
    ");
    $lesson_notes = $stmt->fetchAll();
} catch (Exception $e) {}

// Attached resources lookup
$attached_map = [];
try {
    $stmt = $pdo->query("SELECT lnr.lesson_note_id, rl.id, rl.title, rl.source FROM lesson_note_resources lnr JOIN resource_links rl ON lnr.resource_link_id = rl.id ORDER BY lnr.sort_order");
    while ($row = $stmt->fetch()) {
        $attached_map[$row['lesson_note_id']][] = $row;
    }
} catch (Exception $e) {}

function sanitize($s) { return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lesson Notes — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
    <style>
        .container { max-width: 1100px; margin: 0 auto; padding: 20px; }
        .page-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; }
        .page-header h1 { font-size: 1.4rem; color: var(--primary-color); margin: 0; }
        .filter-bar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }
        .filter-bar input, .filter-bar select { padding: 8px 14px; border: 2px solid #ddd; border-radius: 6px; font-size: 0.9rem; }
        .filter-bar input:focus, .filter-bar select:focus { border-color: var(--secondary-color); outline: none; }
        
        .lesson-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 14px;
            padding: 18px 22px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            transition: box-shadow 0.2s;
            cursor: pointer;
        }
        .lesson-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
        .lesson-card .info { flex: 1; min-width: 0; }
        .lesson-card .info h3 { font-size: 1rem; margin-bottom: 5px; color: #333; }
        .lesson-card .info .meta { font-size: 0.8rem; color: #888; display: flex; flex-wrap: wrap; gap: 10px; }
        .lesson-card .info .meta i { width: 13px; }
        .content-preview { font-size: 0.83rem; color: #999; margin-top: 5px; max-height: 32px; overflow: hidden; }
        .badge-all { background: #e8f5e9; color: #2e7d32; padding: 2px 10px; border-radius: 10px; font-size: 0.7rem; font-weight: 600; }
        .badge-class { background: #e3f2fd; color: #0d47a1; padding: 2px 10px; border-radius: 10px; font-size: 0.7rem; font-weight: 600; }
        .res-tag { display: inline-block; padding: 1px 7px; border-radius: 8px; font-size: 0.65rem; background: #f5f5f5; color: #666; margin: 2px 2px 0 0; }
        .res-picker { max-height: 180px; overflow-y: auto; border: 1px solid #ddd; border-radius: 6px; padding: 8px; }
        .res-picker label { display: flex; align-items: center; gap: 8px; padding: 4px 6px; font-weight: 400; font-size: 0.84rem; cursor: pointer; border-radius: 4px; }
        .res-picker label:hover { background: #f5f5f5; }
        .res-picker label input { width: auto; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; font-size: 0.87rem; margin-bottom: 5px; color: #555; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 9px 13px; border: 2px solid #ddd; border-radius: 6px; font-size: 0.9rem; font-family: inherit; }
        .form-group input:focus, .form-group select:focus { border-color: var(--secondary-color); outline: none; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        #editor-container { height: 300px; }
        .ql-editor { min-height: 260px; font-size: 0.95rem; line-height: 1.6; }

        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .container { padding: 15px; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php renderStaffSidebar(); ?>
        <main class="main-content">
            <div class="container">
                <div class="page-header">
                    <h1><i class="fas fa-book-open"></i> Lesson Notes</h1>
                    <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('active')">
                        <i class="fas fa-plus"></i> Create Lesson Note
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $msg_type; ?>"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <!-- Filter -->
                <div class="filter-bar">
                    <input type="text" id="searchInput" placeholder="Search..." onkeyup="filterCards()">
                    <select id="subjectFilter" onchange="filterCards()">
                        <option value="">All Subjects</option>
                        <?php foreach ($teacher_subjects as $s): ?>
                            <option value="s<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (count($lesson_notes) === 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <h3>No lesson notes yet</h3>
                        <p>Create your first lesson note for your class, or view shared notes.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($lesson_notes as $note): 
                        $class_label = $note['class_id'] ? htmlspecialchars($note['class_name']) : 'All Classes';
                        $class_badge = $note['class_id'] ? 'badge-class' : 'badge-all';
                    ?>
                    <a href="../lesson_note_view.php?id=<?php echo $note['id']; ?>" style="text-decoration:none;color:inherit;display:block;">
                    <div class="lesson-card" data-search="<?php echo strtolower(htmlspecialchars($note['title'] . ' ' . ($note['subject_name'] ?? ''))); ?>" data-subject="<?php echo $note['subject_id'] ? 's'.$note['subject_id'] : ''; ?>">
                        <div class="info">
                            <h3><?php echo htmlspecialchars($note['title']); ?></h3>
                            <div class="meta">
                                <span><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($note['subject_name'] ?? 'General'); ?></span>
                                <span><i class="fas fa-users"></i> <span class="<?php echo $class_badge; ?>"><?php echo $class_label; ?></span></span>
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($note['author_name'] ?? 'Unknown'); ?></span>
                                <?php if (!empty($attached_map[$note['id']])): ?>
                                    <span><i class="fas fa-link"></i> <?php echo count($attached_map[$note['id']]); ?> resource<?php echo count($attached_map[$note['id']]) > 1 ? 's' : ''; ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($attached_map[$note['id']])): ?>
                                <div style="margin-top:4px;">
                                    <?php foreach (array_slice($attached_map[$note['id']], 0, 3) as $res): ?>
                                        <span class="res-tag"><?php echo htmlspecialchars($res['source'] ?? ''); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($attached_map[$note['id']]) > 3): ?>
                                        <span class="res-tag">+<?php echo count($attached_map[$note['id']]) - 3; ?> more</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="content-preview"><?php echo strip_tags($note['content']); ?></div>
                        </div>
                    </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- ===== CREATE MODAL ===== -->
    <div class="modal" id="createModal">
        <div class="modal-box" style="max-width:750px;">
            <button class="modal-close" onclick="closeModal('createModal')">&times;</button>
            <h2><i class="fas fa-plus-circle"></i> Create Lesson Note</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="form-row">
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" required>
                    </div>
                    <div class="form-group">
                        <label>Subject</label>
                        <select name="subject_id">
                            <option value="0">— Select —</option>
                            <?php foreach ($teacher_subjects as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Class</label>
                        <select name="class_id">
                            <option value="0">🌍 All Classes (shared)</option>
                            <?php foreach ($teacher_classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" value="0" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label>Key Concepts (one per line)</label>
                    <textarea name="key_concepts" rows="3" placeholder="Fractions&#10;Numerator&#10;Denominator"></textarea>
                </div>
                <div class="form-group">
                    <label>Lesson Content</label>
                    <div id="editor-container"></div>
                    <textarea name="content" id="content-hidden" style="display:none;"></textarea>
                </div>
                <div class="form-group">
                    <label>Attach Resources (optional)</label>
                    <div class="res-picker">
                        <?php if (count($all_resources) === 0): ?>
                            <p style="color:#999;font-size:0.85rem;">No resources available.</p>
                        <?php else: ?>
                            <?php foreach ($all_resources as $r): ?>
                                <label><input type="checkbox" name="resource_ids[]" value="<?php echo $r['id']; ?>"> <?php echo htmlspecialchars($r['source']); ?>: <?php echo htmlspecialchars($r['title']); ?></label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="text-align:right;padding-top:16px;border-top:1px solid #eee;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" onclick="document.getElementById('content-hidden').value = quill.root.innerHTML"><i class="fas fa-save"></i> Create</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
    <script>
        var quill = new Quill('#editor-container', {
            theme: 'snow',
            placeholder: 'Write your lesson content here...',
            modules: {
                toolbar: [
                    [{ header: [1,2,3,false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link', 'image', 'video'],
                    ['blockquote', 'code-block'],
                    ['clean']
                ]
            }
        });

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        document.querySelectorAll('.modal').forEach(function(m) {
            m.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('active');
            });
        });

        function filterCards() {
            var q = document.getElementById('searchInput').value.toLowerCase();
            var subj = document.getElementById('subjectFilter').value;
            document.querySelectorAll('.lesson-card').forEach(function(card) {
                var show = true;
                if (q && !card.dataset.search.includes(q)) show = false;
                if (subj && card.dataset.subject !== subj) show = false;
                card.parentElement.style.display = show ? '' : 'none';
            });
        }
    </script>
</body>
</html>
