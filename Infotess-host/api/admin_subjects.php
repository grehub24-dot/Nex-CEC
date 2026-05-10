<?php
require_once 'includes/db.php';
requireAccess('settings');

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

// ==========================================
// Ensure level_category column exists
// ==========================================
try {
    $pdo->exec("ALTER TABLE subjects ADD COLUMN IF NOT EXISTS level_category VARCHAR(50) DEFAULT 'primary'");
} catch (Exception $e) {
    // Column may already exist or table doesn't support ALTER via bridge — silently handle
    error_log("subjects level_category migration: " . $e->getMessage());
}

// ==========================================
// Category definitions
// ==========================================
$categories = [
    'creche'       => ['label' => 'Day Care / Creche (Ages 0–2)',       'icon' => 'fa-baby'],
    'nursery'      => ['label' => 'Pre-School / Nursery (Ages 3–4)',    'icon' => 'fa-child'],
    'kindergarten' => ['label' => 'Kindergarten (KG1 – KG2)',           'icon' => 'fa-puzzle-piece'],
    'primary'      => ['label' => 'Primary / Basic School (B1 – B6)',   'icon' => 'fa-book-open'],
    'jhs'          => ['label' => 'Junior High School (JHS: B7 – B9)',  'icon' => 'fa-graduation-cap'],
];

// ==========================================
// Default subject seeds per category
// ==========================================
$default_subjects = [
    'creche' => [
        ['name' => 'Early Learning & Stimulation',      'code' => 'ELS'],
        ['name' => 'Responsive Caregiving',             'code' => 'RCG'],
        ['name' => 'Health & Nutrition',                'code' => 'HLN'],
        ['name' => 'Safety & Security',                 'code' => 'SFS'],
    ],
    'nursery' => [
        ['name' => 'Language & Literacy (Phonics)',      'code' => 'LAN'],
        ['name' => 'Numeracy',                          'code' => 'NUM'],
        ['name' => 'Creative Activities',               'code' => 'CRE'],
        ['name' => 'Pre-Writing / Handwriting Practice', 'code' => 'PWR'],
    ],
    'kindergarten' => [
        ['name' => 'Language and Literacy',              'code' => 'KLAN'],
        ['name' => 'Numeracy',                          'code' => 'KNUM'],
        ['name' => 'Creative Arts',                     'code' => 'KCRE'],
        ['name' => 'Environmental Studies',             'code' => 'KENV'],
        ['name' => 'Our World Our People (OWOP)',       'code' => 'KOWO'],
        ['name' => 'Movement, Music, Drama & PE',       'code' => 'KMMD'],
    ],
    'primary' => [
        ['name' => 'English Language',                  'code' => 'ENG'],
        ['name' => 'Mathematics',                       'code' => 'MATH'],
        ['name' => 'Science',                           'code' => 'SCI'],
        ['name' => 'Ghanaian Language',                 'code' => 'GL'],
        ['name' => 'History of Ghana',                  'code' => 'HOG'],
        ['name' => 'Religious and Moral Education',     'code' => 'RME'],
        ['name' => 'Creative Arts',                     'code' => 'CA'],
        ['name' => 'Computing (ICT)',                   'code' => 'ICT'],
        ['name' => 'French',                            'code' => 'FRE'],
        ['name' => 'Physical Education & Health',       'code' => 'PE'],
    ],
    'jhs' => [
        ['name' => 'English Language',                  'code' => 'ENG'],
        ['name' => 'Mathematics',                       'code' => 'MATH'],
        ['name' => 'Science',                           'code' => 'SCI'],
        ['name' => 'Social Studies',                    'code' => 'SST'],
        ['name' => 'Religious and Moral Education',     'code' => 'RME'],
        ['name' => 'Ghanaian Language',                 'code' => 'GL'],
        ['name' => 'Creative Arts and Design',          'code' => 'CAD'],
        ['name' => 'Career Technology',                 'code' => 'CT'],
        ['name' => 'Computing',                         'code' => 'COMP'],
        ['name' => 'French',                            'code' => 'FRE'],
        ['name' => 'Physical Education & Health',       'code' => 'PE'],
    ],
];

// ==========================================
// Handle POST Actions
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_subject') {
            $name = sanitize($_POST['name']);
            $code = sanitize($_POST['code']);
            $category = sanitize($_POST['category']);

            if (empty($name) || empty($category)) {
                $error = 'Subject name and category are required.';
            } else {
                // Check for duplicate within the same category
                $existing = $pdo->prepare("SELECT id FROM subjects WHERE name = ? AND level_category = ?");
                $existing->execute([$name, $category]);
                if ($existing->fetch()) {
                    $error = "Subject '$name' already exists in this category.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO subjects (name, code, level_category) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $code, $category]);
                    $message = "Subject '$name' added successfully.";
                }
            }

        } elseif ($action === 'edit_subject') {
            $id = (int)$_POST['id'];
            $name = sanitize($_POST['name']);
            $code = sanitize($_POST['code']);
            $category = sanitize($_POST['category']);

            if (empty($name) || empty($category)) {
                $error = 'Subject name and category are required.';
            } else {
                $stmt = $pdo->prepare("UPDATE subjects SET name = ?, code = ?, level_category = ? WHERE id = ?");
                $stmt->execute([$name, $code, $category, $id]);
                $message = "Subject updated successfully.";
            }

        } elseif ($action === 'delete_subject') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Subject deleted successfully.";

        } elseif ($action === 'seed_defaults') {
            $category = sanitize($_POST['seed_category']);
            if (!isset($default_subjects[$category])) {
                $error = 'Invalid category for seeding.';
            } else {
                $inserted = 0;
                $skipped = 0;
                foreach ($default_subjects[$category] as $subj) {
                    $existing = $pdo->prepare("SELECT id FROM subjects WHERE name = ? AND level_category = ?");
                    $existing->execute([$subj['name'], $category]);
                    if (!$existing->fetch()) {
                        $stmt = $pdo->prepare("INSERT INTO subjects (name, code, level_category) VALUES (?, ?, ?)");
                        $stmt->execute([$subj['name'], $subj['code'], $category]);
                        $inserted++;
                    } else {
                        $skipped++;
                    }
                }
                $parts = [];
                if ($inserted > 0) $parts[] = "$inserted subject(s) added";
                if ($skipped > 0) $parts[] = "$skipped duplicate(s) skipped";
                $message = implode(', ', $parts) . " for " . $categories[$category]['label'] . ".";
            }

        } elseif ($action === 'seed_all') {
            $total_inserted = 0;
            $total_skipped = 0;
            foreach ($default_subjects as $cat => $subjects) {
                foreach ($subjects as $subj) {
                    $existing = $pdo->prepare("SELECT id FROM subjects WHERE name = ? AND level_category = ?");
                    $existing->execute([$subj['name'], $cat]);
                    if (!$existing->fetch()) {
                        $stmt = $pdo->prepare("INSERT INTO subjects (name, code, level_category) VALUES (?, ?, ?)");
                        $stmt->execute([$subj['name'], $subj['code'], $cat]);
                        $total_inserted++;
                    } else {
                        $total_skipped++;
                    }
                }
            }
            $message = "Seeded all categories: $total_inserted added, $total_skipped skipped.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// ==========================================
// Fetch all subjects
// ==========================================
$all_subjects = [];
try {
    $stmt = $pdo->query("SELECT * FROM subjects ORDER BY level_category, name");
    $all_subjects = $stmt->fetchAll();
} catch (Exception $e) {
    $all_subjects = [];
}

// Group subjects by category
$grouped = [];
foreach ($categories as $key => $cat) {
    $grouped[$key] = [];
}
foreach ($all_subjects as $s) {
    $cat = $s['level_category'] ?? 'primary';
    if (!isset($grouped[$cat])) $grouped[$cat] = [];
    $grouped[$cat][] = $s;
}

// Get active tab
$active_tab = $_GET['tab'] ?? 'creche';
if (!isset($categories[$active_tab])) $active_tab = 'creche';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Settings — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .subject-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 25px;
            background: var(--white);
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .subject-tab {
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            color: var(--text-color);
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid transparent;
        }
        .subject-tab:hover {
            background: #e3f2fd;
            border-color: var(--primary-color);
        }
        .subject-tab.active {
            background: var(--primary-color);
            color: #fff;
        }
        .subject-tab i {
            font-size: 1rem;
        }
        .subject-count {
            display: inline-block;
            background: rgba(0,0,0,0.1);
            color: inherit;
            border-radius: 10px;
            padding: 1px 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .subject-tab.active .subject-count {
            background: rgba(255,255,255,0.2);
            color: #fff;
        }
        .inline-form { display: inline; }
        .code-badge {
            display: inline-block;
            background: #e3f2fd;
            color: var(--primary-color);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            font-family: monospace;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #888;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ccc;
        }
        .category-info {
            background: #f0f7ff;
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #555;
        }
        .category-info strong {
            color: var(--primary-color);
        }
        .add-row {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .add-row .form-control {
            width: auto;
            min-width: 180px;
        }
        @media (max-width: 768px) {
            .add-row { flex-direction: column; align-items: stretch; }
            .add-row .form-control { width: 100%; min-width: unset; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php echo renderSidebar('settings', $school_name); ?>

        <main class="main-content" id="main-content">
            <div class="top-bar">
                <h2>Subject Settings by Educational Level</h2>
                <form method="POST" class="inline-form" onsubmit="return confirm('Seed ALL categories with Ghana curriculum standard subjects? Duplicates will be skipped.');">
                    <input type="hidden" name="action" value="seed_all">
                    <button type="submit" class="btn-admin-action btn-admin-success"><i class="fas fa-database"></i> Seed All Defaults</button>
                </form>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Category Info -->
            <div class="category-info">
                <strong><i class="fas fa-info-circle"></i> Ghana Education Service (GES) Curriculum:</strong>
                Configure subjects per educational level. These subjects are used across the system — for SBA scores, report cards, and grade entry. Use <strong>"Seed Defaults"</strong> to auto-populate the standard GES curriculum for any level.
            </div>

            <!-- Tabs -->
            <div class="subject-tabs" role="tablist">
                <?php foreach ($categories as $key => $cat):
                    $count = count($grouped[$key] ?? []);
                    $is_active = $active_tab === $key;
                ?>
                    <a href="?tab=<?php echo $key; ?>" class="subject-tab <?php echo $is_active ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>">
                        <i class="fas <?php echo $cat['icon']; ?>"></i>
                        <?php echo htmlspecialchars($cat['label']); ?>
                        <span class="subject-count"><?php echo $count; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Active Category Panel -->
            <?php
            $current_cat = $active_tab;
            $cat_label = $categories[$current_cat]['label'];
            $subjects = $grouped[$current_cat] ?? [];
            ?>

            <div class="card">
                <div class="card-header flex justify-between items-center" style="padding: 20px;">
                    <h3><i class="fas <?php echo $categories[$current_cat]['icon']; ?>"></i> <?php echo htmlspecialchars($cat_label); ?> — Subjects</h3>
                    <form method="POST" class="inline-form" onsubmit="return confirm('Seed default subjects for <?php echo htmlspecialchars($cat_label); ?>?');">
                        <input type="hidden" name="action" value="seed_defaults">
                        <input type="hidden" name="seed_category" value="<?php echo $current_cat; ?>">
                        <button type="submit" class="btn-admin-action btn-admin-sm"><i class="fas fa-magic"></i> Seed Defaults</button>
                    </form>
                </div>
                <div class="card-content">
                    <!-- Quick Add -->
                    <div class="add-row">
                        <form method="POST" class="flex items-center gap-10" style="flex-wrap: wrap;">
                            <input type="hidden" name="action" value="add_subject">
                            <input type="hidden" name="category" value="<?php echo $current_cat; ?>">
                            <div>
                                <label class="fs-small fw-600 color-muted mb-5">Subject Name</label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. English Language" required style="min-width: 200px;">
                            </div>
                            <div>
                                <label class="fs-small fw-600 color-muted mb-5">Code</label>
                                <input type="text" name="code" class="form-control" placeholder="e.g. ENG" style="min-width: 80px; max-width: 100px;">
                            </div>
                            <button type="submit" class="btn-primary" style="padding: 10px 20px; margin-top: 18px;"><i class="fas fa-plus"></i> Add Subject</button>
                        </form>
                    </div>

                    <!-- Subjects Table -->
                    <?php if (empty($subjects)): ?>
                        <div class="empty-state">
                            <i class="fas fa-book"></i>
                            <p>No subjects configured for this level yet.</p>
                            <p class="fs-small color-muted mt-5">Click <strong>"Seed Defaults"</strong> to add the standard GES curriculum, or use the form above to add custom subjects.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">#</th>
                                        <th>Subject Name</th>
                                        <th style="width: 100px;">Code</th>
                                        <th style="width: 180px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subjects as $i => $subj): ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($subj['name']); ?></strong></td>
                                        <td>
                                            <?php if (!empty($subj['code'])): ?>
                                                <span class="code-badge"><?php echo htmlspecialchars($subj['code']); ?></span>
                                            <?php else: ?>
                                                <span class="color-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <!-- Edit Button triggers inline edit form -->
                                            <button onclick="toggleEdit(<?php echo $subj['id']; ?>)" class="btn-admin-action btn-admin-secondary btn-admin-sm"><i class="fas fa-pen"></i> Edit</button>
                                            <form method="POST" class="inline-form" onsubmit="return confirm('Delete subject &quot;<?php echo htmlspecialchars($subj['name'], ENT_QUOTES); ?>&quot;?');">
                                                <input type="hidden" name="action" value="delete_subject">
                                                <input type="hidden" name="id" value="<?php echo $subj['id']; ?>">
                                                <button type="submit" class="btn-admin-action btn-admin-danger btn-admin-sm"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr id="edit-row-<?php echo $subj['id']; ?>" style="display: none;">
                                        <td colspan="4" style="background: #f8f9fa; padding: 10px 15px;">
                                            <form method="POST" class="flex items-center gap-10" style="flex-wrap: wrap;">
                                                <input type="hidden" name="action" value="edit_subject">
                                                <input type="hidden" name="id" value="<?php echo $subj['id']; ?>">
                                                <input type="hidden" name="category" value="<?php echo $current_cat; ?>">
                                                <div>
                                                    <label class="fs-small fw-600 color-muted">Subject Name</label>
                                                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($subj['name']); ?>" required style="min-width: 200px;">
                                                </div>
                                                <div>
                                                    <label class="fs-small fw-600 color-muted">Code</label>
                                                    <input type="text" name="code" class="form-control" value="<?php echo htmlspecialchars($subj['code'] ?? ''); ?>" style="min-width: 80px; max-width: 100px;">
                                                </div>
                                                <button type="submit" class="btn-admin-action btn-admin-sm" style="margin-top: 18px;"><i class="fas fa-save"></i> Save</button>
                                                <button type="button" onclick="toggleEdit(<?php echo $subj['id']; ?>)" class="btn-admin-action btn-admin-secondary btn-admin-sm" style="margin-top: 18px;"><i class="fas fa-times"></i> Cancel</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Summary Card -->
            <div class="section">
                <div class="card">
                    <div class="card-content">
                        <h3 class="mb-15">Curriculum Overview</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
                            <?php foreach ($categories as $key => $cat):
                                $count = count($grouped[$key] ?? []);
                            ?>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; border-left: 4px solid var(--primary-color);">
                                <i class="fas <?php echo $cat['icon']; ?>" style="font-size: 1.5rem; color: var(--primary-color);"></i>
                                <h4 style="font-size: 0.85rem; margin: 8px 0 4px;"><?php echo htmlspecialchars($cat['label']); ?></h4>
                                <span style="font-size: 1.5rem; font-weight: bold; color: var(--primary-color);"><?php echo $count; ?></span>
                                <span class="color-muted fs-small"> subjects</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    function toggleEdit(id) {
        var row = document.getElementById('edit-row-' + id);
        if (row) {
            row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
        }
    }
    </script>
</body>
</html>
