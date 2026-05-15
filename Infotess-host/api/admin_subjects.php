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
// Subject-category mapping via system_settings
// (DDL cannot be used — Supabase REST bridge
//  skips ALTER TABLE. We store a JSON mapping
//  of category -> subject IDs in system_settings
//  under the key "subject_categories".)
// ==========================================

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
        ['name' => 'Early Stimulation & Sensory Play',   'code' => 'ESS'],
        ['name' => 'Responsive Caregiving & Nurturing',  'code' => 'RCN'],
        ['name' => 'Health, Hygiene & Nutrition',        'code' => 'HHN'],
        ['name' => 'Safety & Security Awareness',        'code' => 'SSA'],
        ['name' => 'Physical & Motor Development',       'code' => 'PMD'],
        ['name' => 'Cognitive Development & Exploration', 'code' => 'CDE'],
        ['name' => 'Language & Communication Skills',    'code' => 'LCS'],
        ['name' => 'Social & Emotional Development',     'code' => 'SED'],
    ],
    'nursery' => [
        ['name' => 'Language & Literacy',                'code' => 'LAN'],
        ['name' => 'Numeracy',                           'code' => 'NUM'],
        ['name' => 'Creative Activities',                'code' => 'CRE'],
        ['name' => 'Environmental Studies',              'code' => 'ENV'],
        ['name' => 'Our World Our People (OWOP)',        'code' => 'OWP'],
        ['name' => 'Movement, Music, Drama & PE',        'code' => 'MMD'],
    ],
    'kindergarten' => [
        ['name' => 'Language and Literacy',              'code' => 'KLAN'],
        ['name' => 'Numeracy',                           'code' => 'KNUM'],
        ['name' => 'Creative Arts',                      'code' => 'KCRE'],
        ['name' => 'Environmental Studies',              'code' => 'KENV'],
        ['name' => 'Our World Our People (OWOP)',        'code' => 'KOWO'],
        ['name' => 'Movement, Music, Drama & PE',        'code' => 'KMMD'],
    ],
    'primary' => [
        ['name' => 'English Language',                   'code' => 'ENG'],
        ['name' => 'Mathematics',                        'code' => 'MATH'],
        ['name' => 'Science',                            'code' => 'SCI'],
        ['name' => 'Ghanaian Language',                  'code' => 'GL'],
        ['name' => 'History of Ghana',                   'code' => 'HOG'],
        ['name' => 'Religious and Moral Education',      'code' => 'RME'],
        ['name' => 'Creative Arts',                      'code' => 'CA'],
        ['name' => 'Computing (ICT)',                    'code' => 'ICT'],
        ['name' => 'French',                             'code' => 'FRE'],
        ['name' => 'Physical Education & Health',        'code' => 'PE'],
    ],
    'jhs' => [
        ['name' => 'English Language',                   'code' => 'ENG'],
        ['name' => 'Mathematics',                        'code' => 'MATH'],
        ['name' => 'Science',                            'code' => 'SCI'],
        ['name' => 'Social Studies',                     'code' => 'SST'],
        ['name' => 'Religious and Moral Education',      'code' => 'RME'],
        ['name' => 'Ghanaian Language',                  'code' => 'GL'],
        ['name' => 'Creative Arts and Design',           'code' => 'CAD'],
        ['name' => 'Career Technology',                  'code' => 'CT'],
        ['name' => 'Computing',                          'code' => 'COMP'],
        ['name' => 'French',                             'code' => 'FRE'],
        ['name' => 'Physical Education & Health',        'code' => 'PE'],
    ],
];

// ==========================================
// Subject-Category Mapping Helpers
// ==========================================

/**
 * Read the subject-to-category mapping from system_settings.
 * Returns an associative array: [ 'creche' => [1,2,3], 'nursery' => [4,5], ... ]
 */
function getSubjectCategoryMapping($pdo): array {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute(['subject_categories']);
        $row = $stmt->fetch();
        if ($row && !empty($row['setting_value'])) {
            $decoded = json_decode($row['setting_value'], true);
            return is_array($decoded) ? $decoded : [];
        }
    } catch (Exception $e) {
        error_log("getSubjectCategoryMapping: " . $e->getMessage());
    }
    return [];
}

/**
 * Save the subject-to-category mapping to system_settings.
 * Creates or updates the "subject_categories" key.
 */
function saveSubjectCategoryMapping($pdo, array $mapping): bool {
    $json = json_encode($mapping);
    try {
        $existing = $pdo->prepare("SELECT setting_key FROM system_settings WHERE setting_key = ?");
        $existing->execute(['subject_categories']);
        if ($existing->fetch()) {
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$json, 'subject_categories']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute(['subject_categories', $json]);
        }
        return true;
    } catch (Exception $e) {
        error_log("saveSubjectCategoryMapping: " . $e->getMessage());
        return false;
    }
}

/**
 * Remove a subject ID from all categories in the mapping.
 */
function removeSubjectIdFromMapping(array &$mapping, int $id): void {
    foreach ($mapping as $cat => &$ids) {
        $ids = array_values(array_filter($ids, function($v) use ($id) {
            return (int)$v !== $id;
        }));
    }
    unset($ids);
}

/**
 * Add a subject ID to a specific category in the mapping (no duplicates).
 */
function addSubjectIdToMapping(array &$mapping, string $category, int $id): void {
    if (!isset($mapping[$category])) {
        $mapping[$category] = [];
    }
    if (!in_array($id, $mapping[$category])) {
        $mapping[$category][] = $id;
    }
}

// ==========================================
// Handle POST Actions
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_request_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_subject') {
            $name = sanitize($_POST['name']);
            $code = sanitize($_POST['code']);
            $category = sanitize($_POST['category']);

            if (empty($name) || empty($category)) {
                $error = 'Subject name and category are required.';
            } else {
                // Check for duplicate by name
                $existing = $pdo->prepare("SELECT id FROM subjects WHERE name = ?");
                $existing->execute([$name]);
                $dupRow = $existing->fetch();
                if ($dupRow) {
                    $error = "Subject '$name' already exists.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO subjects (name, code) VALUES (?, ?)");
                    $stmt->execute([$name, $code]);
                    $newId = (int)$pdo->lastInsertId();

                    // Add to category mapping
                    $mapping = getSubjectCategoryMapping($pdo);
                    addSubjectIdToMapping($mapping, $category, $newId);
                    saveSubjectCategoryMapping($pdo, $mapping);

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
                $stmt = $pdo->prepare("UPDATE subjects SET name = ?, code = ? WHERE id = ?");
                $stmt->execute([$name, $code, $id]);

                // Update category mapping (move subject to new category)
                $mapping = getSubjectCategoryMapping($pdo);
                removeSubjectIdFromMapping($mapping, $id);
                addSubjectIdToMapping($mapping, $category, $id);
                saveSubjectCategoryMapping($pdo, $mapping);

                $message = "Subject updated successfully.";
            }

        } elseif ($action === 'delete_subject') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
            $stmt->execute([$id]);

            // Remove from category mapping
            $mapping = getSubjectCategoryMapping($pdo);
            removeSubjectIdFromMapping($mapping, $id);
            saveSubjectCategoryMapping($pdo, $mapping);

            $message = "Subject deleted successfully.";

        } elseif ($action === 'seed_defaults') {
            $category = sanitize($_POST['seed_category']);
            if (!isset($default_subjects[$category])) {
                $error = 'Invalid category for seeding.';
            } else {
                $mapping = getSubjectCategoryMapping($pdo);
                $inserted = 0;
                $skipped = 0;

                foreach ($default_subjects[$category] as $subj) {
                    // Check if subject already exists by name
                    $existing = $pdo->prepare("SELECT id FROM subjects WHERE name = ?");
                    $existing->execute([$subj['name']]);
                    $row = $existing->fetch();

                    if ($row) {
                        // Subject exists — ensure it's mapped to this category
                        $existingId = (int)$row['id'];
                        if (!in_array($existingId, $mapping[$category] ?? [])) {
                            addSubjectIdToMapping($mapping, $category, $existingId);
                            $inserted++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        // New subject
                        $stmt = $pdo->prepare("INSERT INTO subjects (name, code) VALUES (?, ?)");
                        $stmt->execute([$subj['name'], $subj['code']]);
                        $newId = (int)$pdo->lastInsertId();
                        addSubjectIdToMapping($mapping, $category, $newId);
                        $inserted++;
                    }
                }

                saveSubjectCategoryMapping($pdo, $mapping);

                $parts = [];
                if ($inserted > 0) $parts[] = "$inserted subject(s) added";
                if ($skipped > 0) $parts[] = "$skipped duplicate(s) skipped";
                $message = implode(', ', $parts) . " for " . $categories[$category]['label'] . ".";
            }

        } elseif ($action === 'seed_all') {
            $mapping = getSubjectCategoryMapping($pdo);
            $total_inserted = 0;
            $total_skipped = 0;

            foreach ($default_subjects as $cat => $subjects) {
                foreach ($subjects as $subj) {
                    $existing = $pdo->prepare("SELECT id FROM subjects WHERE name = ?");
                    $existing->execute([$subj['name']]);
                    $row = $existing->fetch();

                    if ($row) {
                        $existingId = (int)$row['id'];
                        if (!in_array($existingId, $mapping[$cat] ?? [])) {
                            addSubjectIdToMapping($mapping, $cat, $existingId);
                            $total_inserted++;
                        } else {
                            $total_skipped++;
                        }
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO subjects (name, code) VALUES (?, ?)");
                        $stmt->execute([$subj['name'], $subj['code']]);
                        $newId = (int)$pdo->lastInsertId();
                        addSubjectIdToMapping($mapping, $cat, $newId);
                        $total_inserted++;
                    }
                }
            }

            saveSubjectCategoryMapping($pdo, $mapping);
            $message = "Seeded all categories: $total_inserted added, $total_skipped skipped.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// ==========================================
// Fetch all subjects and group by category
// using the subject_categories mapping
// ==========================================
$all_subjects = [];
try {
    $stmt = $pdo->query("SELECT * FROM subjects ORDER BY name");
    $all_subjects = $stmt->fetchAll();
} catch (Exception $e) {
    $all_subjects = [];
}

$subject_mapping = getSubjectCategoryMapping($pdo);

// Build ID-based lookup for fast grouping
$subject_by_id = [];
foreach ($all_subjects as $s) {
    $subject_by_id[(int)$s['id']] = $s;
}

// Group subjects by category using the mapping
$grouped = [];
$orphaned_ids = array_keys($subject_by_id); // track which IDs get assigned

foreach ($categories as $key => $cat) {
    $grouped[$key] = [];
    $ids = $subject_mapping[$key] ?? [];
    foreach ($ids as $id) {
        $idInt = (int)$id;
        if (isset($subject_by_id[$idInt])) {
            $grouped[$key][] = $subject_by_id[$idInt];
            // Remove from orphaned set
            $orphaned_ids = array_values(array_filter($orphaned_ids, function($oid) use ($idInt) {
                return $oid !== $idInt;
            }));
        }
    }
}

// Any remaining subjects not in the mapping get assigned to 'primary' as fallback
foreach ($orphaned_ids as $oid) {
    if (isset($subject_by_id[$oid])) {
        $grouped['primary'][] = $subject_by_id[$oid];
    }
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
                    <?php csrf_field(); ?>
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
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="seed_defaults">
                        <input type="hidden" name="seed_category" value="<?php echo $current_cat; ?>">
                        <button type="submit" class="btn-admin-action btn-admin-sm"><i class="fas fa-magic"></i> Seed Defaults</button>
                    </form>
                </div>
                <div class="card-content">
                    <!-- Quick Add -->
                    <div class="add-row">
                        <form method="POST" class="flex items-center gap-10" style="flex-wrap: wrap;">
                            <?php csrf_field(); ?>
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
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_subject">
                                                <input type="hidden" name="id" value="<?php echo $subj['id']; ?>">
                                                <button type="submit" class="btn-admin-action btn-admin-danger btn-admin-sm"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr id="edit-row-<?php echo $subj['id']; ?>" style="display: none;">
                                        <td colspan="4" style="background: #f8f9fa; padding: 10px 15px;">
                                            <form method="POST" class="flex items-center gap-10" style="flex-wrap: wrap;">
                                                <?php csrf_field(); ?>
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
