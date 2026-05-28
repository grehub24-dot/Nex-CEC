<?php
/**
 * Admin: Manage Resource Links
 *
 * CRUD interface for curating educational resource links (hand2mind,
 * PBS Kids, Kiddoworksheets) that are served under nexcec.com URLs.
 *
 * Access: admin, super_admin
 * URL:    admin/resources.php
 */
require_once 'includes/db.php';
requireAccess('resources');

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error   = '';

// -----------------------------------------------------------------------
// Handle actions
// -----------------------------------------------------------------------

// --- Add Resource ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    validate_request_csrf();
    try {
        $stmt = $pdo->prepare("INSERT INTO resource_links (title, url, source, category, class_id, subject_id, description, embed_type, sort_order, is_active)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([
            sanitize($_POST['title'] ?? ''),
            sanitize($_POST['url'] ?? ''),
            sanitize($_POST['source'] ?? ''),
            sanitize($_POST['category'] ?? ''),
            !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null,
            !empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : null,
            sanitize($_POST['description'] ?? ''),
            sanitize($_POST['embed_type'] ?? 'iframe'),
            (int)($_POST['sort_order'] ?? 0),
        ]);
        $message = "Resource added successfully.";
    } catch (Exception $e) {
        $error = "Error adding resource: " . $e->getMessage();
    }
}

// --- Edit Resource ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    validate_request_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE resource_links SET title=?, url=?, source=?, category=?, class_id=?, subject_id=?, description=?, embed_type=?, sort_order=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
            $stmt->execute([
                sanitize($_POST['title'] ?? ''),
                sanitize($_POST['url'] ?? ''),
                sanitize($_POST['source'] ?? ''),
                sanitize($_POST['category'] ?? ''),
                !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null,
                !empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : null,
                sanitize($_POST['description'] ?? ''),
                sanitize($_POST['embed_type'] ?? 'iframe'),
                (int)($_POST['sort_order'] ?? 0),
                $id,
            ]);
            $message = "Resource updated successfully.";
        } catch (Exception $e) {
            $error = "Error updating resource: " . $e->getMessage();
        }
    } else {
        $error = "Invalid resource ID.";
    }
}

// --- Toggle Active ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle') {
    validate_request_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE resource_links SET is_active = NOT is_active, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Resource status toggled.";
        } catch (Exception $e) {
            $error = "Error toggling resource: " . $e->getMessage();
        }
    }
}

// --- Delete Resource ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    validate_request_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM resource_links WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Resource deleted.";
        } catch (Exception $e) {
            $error = "Error deleting resource: " . $e->getMessage();
        }
    }
}

// -----------------------------------------------------------------------
// Fetch data
// -----------------------------------------------------------------------

// Fetch all resources
$resources = [];
try {
    $stmt = $pdo->query("SELECT r.*,
                                c.name AS class_name,
                                s.name AS subject_name
                         FROM resource_links r
                         LEFT JOIN classes c ON c.id = r.class_id
                         LEFT JOIN subjects s ON s.id = r.subject_id
                         ORDER BY r.sort_order, r.title");
    $resources = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error fetching resources: " . $e->getMessage();
}

// Fetch classes for dropdown
$classes = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM classes ORDER BY name");
    $classes = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch subjects for dropdown (master subjects only)
$subjects = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM subjects WHERE teacher_id IS NULL AND class_id IS NULL ORDER BY name");
    $subjects = $stmt->fetchAll();
} catch (Exception $e) {}

// Sources & Categories for dropdown
$sources = ['hand2mind', 'pbskids', 'kiddoworksheets', 'khanacademy', 'scratch', 'blockly', 'nasa', 'other'];
$categories = ['all', 'math', 'literacy', 'stem', 'coding', 'ece', 'printables', 'sel', 'art'];
$embed_types = [
    'iframe'    => 'Iframe (URL hidden, site shown inline)',
    'redirect'  => 'Redirect (opens in new tab via interstitial)',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Links — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        .stat-card .label {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        .source-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .source-hand2mind { background: #e3f2fd; color: #0d47a1; }
        .source-pbskids { background: #fce4ec; color: #880e4f; }
        .source-kiddoworksheets { background: #e8f5e9; color: #1b5e20; }
        .source-khanacademy { background: #fff3e0; color: #e65100; }
        .source-scratch { background: #fce4ec; color: #c62828; }
        .source-blockly { background: #e8eaf6; color: #283593; }
        .source-nasa { background: #e0f2f1; color: #004d40; }
        .source-other { background: #f5f5f5; color: #616161; }
        .embed-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .embed-iframe { background: #e8eaf6; color: #283593; }
        .embed-redirect { background: #fff3e0; color: #e65100; }
        .res-title { font-weight: 600; }
        .res-url { font-size: 0.75rem; color: #999; word-break: break-all; max-width: 250px; display: inline-block; }
        .res-desc { font-size: 0.8rem; color: #666; max-width: 300px; }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .form-grid .full-width { grid-column: 1 / -1; }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-grid .full-width { grid-column: 1; }
        }
        .form-group { margin-bottom: 0; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 0.85rem; color: #444; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem;
        }
        .form-group textarea { min-height: 60px; resize: vertical; }
        .inline-form { display: inline; }
        .btn-icon {
            background: none; border: none; cursor: pointer; padding: 4px 8px; font-size: 1rem;
            border-radius: 4px; transition: background 0.2s;
        }
        .btn-icon:hover { background: #f0f0f0; }
        .btn-icon.text-success { color: #28a745; }
        .btn-icon.text-danger { color: #dc3545; }
        .btn-icon.text-warning { color: #ffc107; }
        .btn-sm { padding: 4px 12px; font-size: 0.8rem; }
        .filter-bar {
            display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 20px;
        }
        .filter-bar select, .filter-bar input {
            padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.85rem;
        }
        .view-link { font-size: 0.75rem; color: var(--primary-color); text-decoration: none; }
        .view-link:hover { text-decoration: underline; }
        .badge-inactive { opacity: 0.5; }
        /* Modal */
        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4);
            z-index: 1000; align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #fff; border-radius: 12px; padding: 25px; max-width: 600px;
            width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .modal-box h3 { margin-bottom: 15px; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php echo renderSidebar('resources', $school_name); ?>

        <main class="main-content">
            <div class="top-bar">
                <h2><i class="fas fa-bookmark"></i> Resource Links</h2>
                <div>
                    <button class="btn-admin-action btn-admin-success" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add Resource
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Stats -->
            <?php
            $total = count($resources);
            $iframe_count = 0; $redirect_count = 0; $active_count = 0;
            $source_counts = [];
            foreach ($resources as $r) {
                if ($r['embed_type'] === 'iframe') $iframe_count++;
                if ($r['embed_type'] === 'redirect') $redirect_count++;
                if ($r['is_active']) $active_count++;
                $src = $r['source'] ?: 'other';
                $source_counts[$src] = ($source_counts[$src] ?? 0) + 1;
            }
            ?>
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="number"><?php echo $total; ?></div>
                    <div class="label">Total Resources</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $active_count; ?></div>
                    <div class="label">Active</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $iframe_count; ?></div>
                    <div class="label">Iframe (hidden URL)</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $redirect_count; ?></div>
                    <div class="label">Redirect</div>
                </div>
            </div>

            <!-- Resources Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Resource</th>
                                <th>Source</th>
                                <th>Category</th>
                                <th>Class / Subject</th>
                                <th>Embed</th>
                                <th>Active</th>
                                <th style="width:120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($resources)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:40px; color:#888;">
                                        <i class="fas fa-bookmark" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                                        No resources yet. Click <strong>"Add Resource"</strong> to get started.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($resources as $r):
                                    $source_class = $r['source'] ?: 'other';
                                    $row_class = $r['is_active'] ? '' : 'badge-inactive';
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td>
                                        <div class="res-title"><?php echo htmlspecialchars($r['title']); ?></div>
                                        <div class="res-url"><?php echo htmlspecialchars($r['url']); ?></div>
                                        <?php if ($r['description']): ?>
                                            <div class="res-desc"><?php echo htmlspecialchars($r['description']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($r['embed_type'] === 'iframe'): ?>
                                            <a href="../resource.php?id=<?php echo (int)$r['id']; ?>" class="view-link" target="_blank">
                                                <i class="fas fa-external-link-alt"></i> Preview
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="source-badge source-<?php echo htmlspecialchars($source_class); ?>">
                                            <?php echo htmlspecialchars($r['source'] ?: 'Other'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars(ucfirst($r['category'] ?: '—')); ?></td>
                                    <td style="font-size:0.85rem;">
                                        <?php
                                        echo $r['class_name'] ? htmlspecialchars($r['class_name']) : 'All classes';
                                        echo $r['subject_name'] ? ' / ' . htmlspecialchars($r['subject_name']) : '';
                                        ?>
                                    </td>
                                    <td>
                                        <span class="embed-badge embed-<?php echo htmlspecialchars($r['embed_type']); ?>">
                                            <?php echo htmlspecialchars($r['embed_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" class="inline-form">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                                            <button type="submit" class="btn-icon <?php echo $r['is_active'] ? 'text-success' : 'text-warning'; ?>"
                                                    title="<?php echo $r['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas <?php echo $r['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <button class="btn-icon text-primary" title="Edit" onclick="openEditModal(<?php echo (int)$r['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Delete this resource?');">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                                            <button type="submit" class="btn-icon text-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
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
            <div class="card" style="margin-top:20px;">
                <div class="card-content">
                    <h3><i class="fas fa-info-circle"></i> How Resource URLs Work</h3>
                    <ul style="margin-top:10px; line-height:1.8; color:#555;">
                        <li><strong>Iframe</strong> — The external site is shown inside a Nex CEC page. The URL bar always shows <code>nexcec.com/resource.php?id=X</code>. Works for <strong>Khan Academy</strong>, <strong>Blockly Games</strong>, <strong>kiddoworksheets</strong>. <strong>Scratch</strong> project embeds also work if using the <code>/embed</code> URL.</li>
                        <li><strong>Redirect</strong> — Opens in a new tab via a Nex CEC interstitial page. Used for sites that block iframes (<strong>PBS Kids</strong>, <strong>hand2mind</strong>, <strong>NASA</strong>).</li>
                        <li><strong>Category & Class filters</strong> — When viewing in teacher/parent portals, resources are filtered by class and subject for relevance.</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>

    <!-- ===== Add Modal ===== -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-box">
            <h3><i class="fas fa-plus-circle"></i> Add Resource</h3>
            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="add">

                <div class="form-grid">
                    <div class="full-width form-group">
                        <label>Title *</label>
                        <input type="text" name="title" required placeholder="e.g., Hand2Mind — Math Manipulatives">
                    </div>
                    <div class="full-width form-group">
                        <label>URL *</label>
                        <input type="url" name="url" required placeholder="https://www.hand2mind.com/...">
                    </div>
                    <div class="form-group">
                        <label>Source</label>
                        <select name="source">
                            <option value="">— Select —</option>
                            <?php foreach ($sources as $s): ?>
                                <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars(ucfirst($s)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category">
                            <option value="">— Select —</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars(ucfirst($c)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Class (optional)</label>
                        <select name="class_id">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Subject (optional)</label>
                        <select name="subject_id">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Embed Type</label>
                        <select name="embed_type">
                            <?php foreach ($embed_types as $val => $label): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" value="0" min="0">
                    </div>
                    <div class="full-width form-group">
                        <label>Description (optional)</label>
                        <textarea name="description" placeholder="Brief description of this resource..."></textarea>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-admin-action btn-admin-secondary" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn-admin-action btn-admin-success"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== Edit Modal ===== -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-box">
            <h3><i class="fas fa-edit"></i> Edit Resource</h3>
            <form method="POST" id="editForm">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="form-grid">
                    <div class="full-width form-group">
                        <label>Title *</label>
                        <input type="text" name="title" id="edit_title" required placeholder="e.g., Hand2Mind — Math Manipulatives">
                    </div>
                    <div class="full-width form-group">
                        <label>URL *</label>
                        <input type="url" name="url" id="edit_url" required placeholder="https://www.hand2mind.com/...">
                    </div>
                    <div class="form-group">
                        <label>Source</label>
                        <select name="source" id="edit_source">
                            <option value="">— Select —</option>
                            <?php foreach ($sources as $s): ?>
                                <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars(ucfirst($s)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" id="edit_category">
                            <option value="">— Select —</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars(ucfirst($c)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Class (optional)</label>
                        <select name="class_id" id="edit_class_id">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Subject (optional)</label>
                        <select name="subject_id" id="edit_subject_id">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Embed Type</label>
                        <select name="embed_type" id="edit_embed_type">
                            <?php foreach ($embed_types as $val => $label): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" id="edit_sort_order" min="0">
                    </div>
                    <div class="full-width form-group">
                        <label>Description (optional)</label>
                        <textarea name="description" id="edit_description" placeholder="Brief description of this resource..."></textarea>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-admin-action btn-admin-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn-admin-action btn-admin-primary"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Resource data for edit modal
        const resources = <?php echo json_encode($resources); ?>;

        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function openEditModal(id) {
            const r = resources.find(item => parseInt(item.id) === id);
            if (!r) return;

            document.getElementById('edit_id').value = r.id;
            document.getElementById('edit_title').value = r.title || '';
            document.getElementById('edit_url').value = r.url || '';
            document.getElementById('edit_source').value = r.source || '';
            document.getElementById('edit_category').value = r.category || '';
            document.getElementById('edit_class_id').value = r.class_id || '';
            document.getElementById('edit_subject_id').value = r.subject_id || '';
            document.getElementById('edit_embed_type').value = r.embed_type || 'iframe';
            document.getElementById('edit_sort_order').value = r.sort_order || 0;
            document.getElementById('edit_description').value = r.description || '';

            document.getElementById('editModal').classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        // Close modals on overlay click
        document.querySelectorAll('.modal-overlay').forEach(el => {
            el.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('active');
            });
        });
    </script>
</body>
</html>
