<?php
/**
 * Admin Lesson Notes — full CRUD for lesson content.
 * 
 * Admin can:
 *   - Create lesson notes with Quill rich text editor
 *   - Assign to a specific class or "All Classes"
 *   - Attach existing resource links (Khan Academy, Blockly, etc.)
 *   - Edit, delete, toggle active/inactive
 */
require_once 'includes/db.php';

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$admin_name = $_SESSION['fullname'] ?? 'Admin';
$admin_id = $_SESSION['staff_id'] ?? 0;

// --- Handle POST actions ---
$message = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CREATE
    if ($action === 'create') {
        $title = sanitize($_POST['title'] ?? '');
        $content = $_POST['content'] ?? ''; // allow HTML from Quill
        $key_concepts = sanitize($_POST['key_concepts'] ?? '');
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $class_id = (int)($_POST['class_id'] ?? 0);
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $created_by = $admin_id;

        // class_id = 0 means "All Classes" -> store as NULL
        $class_col = $class_id > 0 ? $class_id : 'NULL';
        $subj_col = $subject_id > 0 ? $subject_id : 'NULL';

        try {
            $stmt = $pdo->prepare("INSERT INTO lesson_notes (title, content, key_concepts, subject_id, class_id, created_by, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins_class = $class_id > 0 ? $class_id : null;
            $ins_subj = $subject_id > 0 ? $subject_id : null;
            $stmt->execute([$title, $content, $key_concepts, $ins_subj, $ins_class, $created_by, $sort_order]);
            $lesson_id = $pdo->lastInsertId();

            // Attach selected resources
            if (!empty($_POST['resource_ids']) && is_array($_POST['resource_ids'])) {
                $attachStmt = $pdo->prepare("INSERT INTO lesson_note_resources (lesson_note_id, resource_link_id, label, sort_order) VALUES (?, ?, ?, 0)");
                foreach ($_POST['resource_ids'] as $rid) {
                    $rid = (int)$rid;
                    if ($rid > 0) $attachStmt->execute([$lesson_id, $rid]);
                }
            }

            $message = "Lesson note created successfully.";
        } catch (Exception $e) {
            $message = "Error creating lesson note: " . $e->getMessage();
            $msg_type = 'danger';
        }
    }

    // UPDATE
    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $key_concepts = sanitize($_POST['key_concepts'] ?? '');
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $class_id = (int)($_POST['class_id'] ?? 0);
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE lesson_notes SET title=?, content=?, key_concepts=?, subject_id=?, class_id=?, sort_order=?, is_active=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
            $upd_class = $class_id > 0 ? $class_id : null;
            $upd_subj = $subject_id > 0 ? $subject_id : null;
            $stmt->execute([$title, $content, $key_concepts, $upd_subj, $upd_class, $sort_order, $is_active, $id]);

            // Replace resource attachments: delete old, insert new
            $pdo->prepare("DELETE FROM lesson_note_resources WHERE lesson_note_id=?")->execute([$id]);
            if (!empty($_POST['resource_ids']) && is_array($_POST['resource_ids'])) {
                $attachStmt = $pdo->prepare("INSERT INTO lesson_note_resources (lesson_note_id, resource_link_id, label, sort_order) VALUES (?, ?, ?, 0)");
                foreach ($_POST['resource_ids'] as $rid) {
                    $rid = (int)$rid;
                    if ($rid > 0) $attachStmt->execute([$id, $rid]);
                }
            }

            $message = "Lesson note updated successfully.";
        } catch (Exception $e) {
            $message = "Error updating lesson note: " . $e->getMessage();
            $msg_type = 'danger';
        }
    }

    // TOGGLE ACTIVE
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $current = (int)($_POST['current'] ?? 0);
        $new = $current ? 0 : 1;
        try {
            $pdo->prepare("UPDATE lesson_notes SET is_active=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$new, $id]);
            $message = "Lesson note " . ($new ? "activated" : "deactivated") . ".";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $msg_type = 'danger';
        }
    }

    // DELETE
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM lesson_note_resources WHERE lesson_note_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM lesson_notes WHERE id=?")->execute([$id]);
            $message = "Lesson note deleted.";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $msg_type = 'danger';
        }
    }
}

// --- Load data ---
$classes = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM classes WHERE is_active = 1 ORDER BY name");
    $classes = $stmt->fetchAll();
} catch (Exception $e) {}

$subjects = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM subjects ORDER BY name");
    $subjects = $stmt->fetchAll();
} catch (Exception $e) {}

$all_resources = [];
try {
    $stmt = $pdo->query("SELECT id, title, source FROM resource_links WHERE is_active = 1 ORDER BY source, title");
    $all_resources = $stmt->fetchAll();
} catch (Exception $e) {}

$lesson_notes = [];
try {
    $stmt = $pdo->query("
        SELECT ln.*, s.name AS subject_name, c.name AS class_name, st.fullname AS author_name
        FROM lesson_notes ln
        LEFT JOIN subjects s ON ln.subject_id = s.id
        LEFT JOIN classes c ON ln.class_id = c.id
        LEFT JOIN staff st ON ln.created_by = st.id
        ORDER BY ln.updated_at DESC
    ");
    $lesson_notes = $stmt->fetchAll();
} catch (Exception $e) {}

// Build a lookup of attached resources per lesson
$attached_map = [];
try {
    $stmt = $pdo->query("SELECT lnr.lesson_note_id, rl.id, rl.title, rl.source FROM lesson_note_resources lnr JOIN resource_links rl ON lnr.resource_link_id = rl.id ORDER BY lnr.sort_order");
    while ($row = $stmt->fetch()) {
        $attached_map[$row['lesson_note_id']][] = $row;
    }
} catch (Exception $e) {}

function sanitize($s) {
    return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lesson Notes — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Quill.js rich text editor -->
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
    <style>
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .page-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; }
        .page-header h1 { font-size: 1.5rem; color: var(--primary-color); margin: 0; }
        .filter-bar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }
        .filter-bar input, .filter-bar select { padding: 8px 14px; border: 2px solid #ddd; border-radius: 6px; font-size: 0.9rem; }
        .filter-bar input:focus, .filter-bar select:focus { border-color: var(--secondary-color); outline: none; }

        .lesson-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 15px;
            padding: 20px 24px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            transition: box-shadow 0.2s;
        }
        .lesson-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
        .lesson-card .info { flex: 1; min-width: 0; }
        .lesson-card .info h3 { font-size: 1.05rem; margin-bottom: 6px; color: #333; }
        .lesson-card .info .meta { font-size: 0.82rem; color: #888; display: flex; flex-wrap: wrap; gap: 12px; }
        .lesson-card .info .meta span { display: inline-flex; align-items: center; gap: 4px; }
        .lesson-card .info .meta i { width: 14px; }
        .lesson-card .actions { display: flex; gap: 6px; flex-shrink: 0; }
        .badge-all { background: #e8f5e9; color: #2e7d32; padding: 2px 10px; border-radius: 10px; font-size: 0.72rem; font-weight: 600; }
        .badge-class { background: #e3f2fd; color: #0d47a1; padding: 2px 10px; border-radius: 10px; font-size: 0.72rem; font-weight: 600; }
        .badge-inactive { background: #fce4ec; color: #c62828; }
        .res-tag { display: inline-block; padding: 1px 8px; border-radius: 8px; font-size: 0.68rem; background: #f5f5f5; color: #666; margin: 2px 2px 0 0; }
        .content-preview { font-size: 0.85rem; color: #888; margin-top: 6px; max-height: 36px; overflow: hidden; line-height: 1.4; }

        /* Modal */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1300; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-box { background: #fff; border-radius: 12px; padding: 30px; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto; animation: slideDown 0.25s ease; }
        .modal-box h2 { font-size: 1.3rem; margin-bottom: 20px; color: #333; }
        .modal-close { float: right; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #999; }
        .modal-close:hover { color: #333; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 600; font-size: 0.88rem; margin-bottom: 6px; color: #555; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; border: 2px solid #ddd; border-radius: 6px; font-size: 0.92rem; font-family: inherit; }
        .form-group input:focus, .form-group select:focus { border-color: var(--secondary-color); outline: none; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-hint { font-size: 0.78rem; color: #999; margin-top: 4px; }

        /* Quill editor */
        #editor-container { height: 320px; }
        #editor-container-hidden { display: none; }
        .ql-editor { min-height: 280px; font-size: 0.95rem; line-height: 1.6; }
        #edit-editor-container { height: 320px; }

        /* Resource picker */
        .res-picker { max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 6px; padding: 8px; }
        .res-picker label { display: flex; align-items: center; gap: 8px; padding: 4px 6px; font-weight: 400; font-size: 0.85rem; cursor: pointer; border-radius: 4px; }
        .res-picker label:hover { background: #f5f5f5; }
        .res-picker label input { width: auto; }

        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .lesson-card { flex-direction: column; }
            .lesson-card .actions { width: 100%; }
            .modal-box { padding: 20px; width: 95%; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/admin_sidebar.php'; ?>
        <main class="main-content">
            <div class="container">
                <div class="page-header">
                    <h1><i class="fas fa-book-open"></i> Lesson Notes</h1>
                    <button class="btn btn-primary" onclick="openCreateModal()"><i class="fas fa-plus"></i> New Lesson</button>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $msg_type; ?>"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <!-- Filter bar -->
                <div class="filter-bar">
                    <input type="text" id="searchInput" placeholder="Search lesson notes..." onkeyup="filterCards()">
                    <select id="classFilter" onchange="filterCards()">
                        <option value="">All Classes</option>
                        <option value="all">All Classes (shared)</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="c<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="statusFilter" onchange="filterCards()">
                        <option value="">Active & Inactive</option>
                        <option value="1">Active Only</option>
                        <option value="0">Inactive Only</option>
                    </select>
                </div>

                <!-- Lesson list -->
                <?php if (count($lesson_notes) === 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <h3>No lesson notes yet</h3>
                        <p>Create your first lesson note to get started.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($lesson_notes as $note): 
                        $class_label = $note['class_id'] ? htmlspecialchars($note['class_name']) : '<i class="fas fa-globe"></i> All Classes';
                        $class_badge = $note['class_id'] ? 'badge-class' : 'badge-all';
                        $inactive = !$note['is_active'];
                    ?>
                    <div class="lesson-card" data-search="<?php echo strtolower(htmlspecialchars($note['title'] . ' ' . ($note['subject_name'] ?? '') . ' ' . ($note['author_name'] ?? ''))); ?>" data-class="<?php echo $note['class_id'] ? 'c'.$note['class_id'] : 'all'; ?>" data-active="<?php echo $note['is_active'] ? '1' : '0'; ?>">
                        <div class="info">
                            <h3>
                                <?php if ($inactive): ?><span class="badge badge-inactive" style="margin-right: 6px;">Inactive</span><?php endif; ?>
                                <?php echo htmlspecialchars($note['title']); ?>
                            </h3>
                            <div class="meta">
                                <span><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($note['subject_name'] ?? 'No subject'); ?></span>
                                <span><i class="fas fa-users"></i> <span class="<?php echo $class_badge; ?>"><?php echo $class_label; ?></span></span>
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($note['author_name'] ?? 'Unknown'); ?></span>
                                <span><i class="far fa-clock"></i> <?php echo date('M j, Y', strtotime($note['updated_at'])); ?></span>
                                <?php if (!empty($attached_map[$note['id']])): ?>
                                    <span><i class="fas fa-link"></i> <?php echo count($attached_map[$note['id']]); ?> resource<?php echo count($attached_map[$note['id']]) > 1 ? 's' : ''; ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($attached_map[$note['id']])): ?>
                                <div style="margin-top: 6px;">
                                    <?php foreach ($attached_map[$note['id']] as $res): ?>
                                        <span class="res-tag"><?php echo htmlspecialchars($res['source'] ?? ''); ?>: <?php echo htmlspecialchars($res['title']); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="content-preview"><?php echo strip_tags($note['content']); ?></div>
                        </div>
                        <div class="actions">
                            <a href="../lesson_note_view.php?id=<?php echo $note['id']; ?>" class="btn btn-sm btn-info" target="_blank" title="Preview"><i class="fas fa-eye"></i></a>
                            <button class="btn btn-sm btn-secondary" onclick="openEditModal(<?php echo $note['id']; ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?php echo $note['id']; ?>">
                                <input type="hidden" name="current" value="<?php echo $note['is_active'] ? 1 : 0; ?>">
                                <button class="btn btn-sm <?php echo $note['is_active'] ? 'btn-warning' : 'btn-success'; ?>" title="<?php echo $note['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                    <i class="fas <?php echo $note['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                </button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this lesson note?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $note['id']; ?>">
                                <button class="btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- ===== CREATE MODAL ===== -->
    <div class="modal" id="createModal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('createModal')">&times;</button>
            <h2><i class="fas fa-plus-circle"></i> New Lesson Note</h2>
            <form method="POST" id="createForm">
                <input type="hidden" name="action" value="create">
                <div class="form-row">
                    <div class="form-group">
                        <label for="create_title">Title</label>
                        <input type="text" name="title" id="create_title" required>
                    </div>
                    <div class="form-group">
                        <label for="create_subject_id">Subject</label>
                        <select name="subject_id" id="create_subject_id">
                            <option value="0">— No subject —</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="create_class_id">Assign to Class</label>
                        <select name="class_id" id="create_class_id">
                            <option value="0">🌍 All Classes (shared)</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">"All Classes" means every student sees this, filtered by their own class.</div>
                    </div>
                    <div class="form-group">
                        <label for="create_sort_order">Sort Order</label>
                        <input type="number" name="sort_order" id="create_sort_order" value="0" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label for="create_key_concepts">Key Concepts (one per line)</label>
                    <textarea name="key_concepts" id="create_key_concepts" rows="3" placeholder="Fractions
Numerator
Denominator
Equivalent fractions"></textarea>
                </div>
                <div class="form-group">
                    <label>Lesson Content (rich text)</label>
                    <div id="editor-container"></div>
                    <textarea name="content" id="content-hidden" style="display:none;"></textarea>
                </div>
                <div class="form-group">
                    <label>Attach Resources (optional)</label>
                    <div class="res-picker">
                        <?php if (count($all_resources) === 0): ?>
                            <p style="color:#999; font-size:0.85rem;">No resources available. Add resources in the Resources page first.</p>
                        <?php else: ?>
                            <?php foreach ($all_resources as $r): ?>
                                <label>
                                    <input type="checkbox" name="resource_ids[]" value="<?php echo $r['id']; ?>">
                                    <span class="source-badge source-<?php echo htmlspecialchars($r['source']); ?>"><?php echo htmlspecialchars($r['source']); ?></span>
                                    <?php echo htmlspecialchars($r['title']); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="text-align:right; margin-top:20px; padding-top:16px; border-top:1px solid #eee;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" onclick="document.getElementById('content-hidden').value = quill.root.innerHTML"><i class="fas fa-save"></i> Create</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== EDIT MODAL ===== -->
    <div class="modal" id="editModal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
            <h2><i class="fas fa-edit"></i> Edit Lesson Note</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id" value="0">
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_title">Title</label>
                        <input type="text" name="title" id="edit_title" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_subject_id">Subject</label>
                        <select name="subject_id" id="edit_subject_id">
                            <option value="0">— No subject —</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_class_id">Assign to Class</label>
                        <select name="class_id" id="edit_class_id">
                            <option value="0">🌍 All Classes (shared)</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_sort_order">Sort Order</label>
                        <input type="number" name="sort_order" id="edit_sort_order" value="0" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1" checked>
                        Active
                    </label>
                </div>
                <div class="form-group">
                    <label for="edit_key_concepts">Key Concepts (one per line)</label>
                    <textarea name="key_concepts" id="edit_key_concepts" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Lesson Content (rich text)</label>
                    <div id="edit-editor-container"></div>
                    <textarea name="content" id="edit-content-hidden" style="display:none;"></textarea>
                </div>
                <div class="form-group">
                    <label>Attach Resources</label>
                    <div class="res-picker" id="edit-resource-picker">
                        <?php foreach ($all_resources as $r): ?>
                            <label>
                                <input type="checkbox" name="resource_ids[]" value="<?php echo $r['id']; ?>" class="edit-res-check">
                                <span class="source-badge source-<?php echo htmlspecialchars($r['source']); ?>"><?php echo htmlspecialchars($r['source']); ?></span>
                                <?php echo htmlspecialchars($r['title']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div style="text-align:right; margin-top:20px; padding-top:16px; border-top:1px solid #eee;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" onclick="document.getElementById('edit-content-hidden').value = editQuill.root.innerHTML"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quill JS -->
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
    <script>
        // --- Create modal Quill ---
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

        // --- Edit modal Quill (initialized later when modal opens) ---
        var editQuill = null;

        function openCreateModal() {
            document.getElementById('createModal').classList.add('active');
            setTimeout(() => quill.focus(), 100);
        }

        function openEditModal(id) {
            document.getElementById('editModal').classList.add('active');
            document.getElementById('edit_id').value = id;

            // Fetch lesson data via AJAX
            fetch('ajax_get_lesson_note.php?id=' + id)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('edit_title').value = data.title || '';
                    document.getElementById('edit_subject_id').value = data.subject_id || 0;
                    document.getElementById('edit_class_id').value = data.class_id || 0;
                    document.getElementById('edit_sort_order').value = data.sort_order || 0;
                    document.getElementById('edit_is_active').checked = data.is_active == 1;
                    document.getElementById('edit_key_concepts').value = data.key_concepts || '';

                    // Destroy and recreate Quill for edit modal
                    if (editQuill) editQuill.destroy();
                    var editContainer = document.getElementById('edit-editor-container');
                    editContainer.innerHTML = '';
                    editQuill = new Quill(editContainer, {
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
                    editQuill.root.innerHTML = data.content || '';

                    // Check resource checkboxes
                    var attached = data.resource_ids || [];
                    document.querySelectorAll('.edit-res-check').forEach(function(cb) {
                        cb.checked = attached.includes(parseInt(cb.value));
                    });
                })
                .catch(err => {
                    console.error('Error loading lesson:', err);
                    alert('Failed to load lesson data.');
                });
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        // Close modals on overlay click
        document.querySelectorAll('.modal').forEach(function(m) {
            m.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('active');
            });
        });

        // --- Client-side filter ---
        function filterCards() {
            var q = document.getElementById('searchInput').value.toLowerCase();
            var cls = document.getElementById('classFilter').value;
            var status = document.getElementById('statusFilter').value;
            document.querySelectorAll('.lesson-card').forEach(function(card) {
                var show = true;
                if (q && !card.dataset.search.includes(q)) show = false;
                if (cls && card.dataset.class !== cls && !(cls === 'all' && card.dataset.class === 'all')) show = false;
                if (status !== '' && card.dataset.active !== status) show = false;
                card.style.display = show ? '' : 'none';
            });
        }

        // Press Enter in search to filter
        document.getElementById('searchInput').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') filterCards();
        });
    </script>
</body>
</html>
