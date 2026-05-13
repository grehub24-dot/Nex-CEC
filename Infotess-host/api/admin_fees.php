<?php
require_once 'includes/db.php';

// Enforce access control
requireAccess('fees');

// Fetch Settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$school_name = $settings['school_name'] ?? 'Nex CEC';
$current_year = $settings['current_academic_year'] ?? date('Y') . '/' . (date('Y') + 1);
$current_term = $settings['current_term'] ?? '1';

$message = '';
$error = '';

// === Build $classes and $level_groups BEFORE POST handler (they are needed by add/edit) ===
$classes = [];
try {
    $stmt = $pdo->query("SELECT * FROM classes");
    $classes = $stmt->fetchAll();
    // NOTE: bridge ignores ORDER BY — sort in PHP.
    usort($classes, function($a, $b) {
        return ((int)($a['sort_order'] ?? 0)) - ((int)($b['sort_order'] ?? 0));
    });
} catch (Exception $e) {
    $classes = [];
}
$group_defs = [
    'creche'        => ['label' => 'Creche',        'names' => ['Creche']],
    'pre_school'    => ['label' => 'Pre-school',    'names' => ['Nursery 1', 'Nursery 2', 'KG 1', 'KG 2']],
    'lower_primary' => ['label' => 'Lower Primary', 'names' => ['Basic 1', 'Basic 2', 'Basic 3']],
    'upper_primary' => ['label' => 'Upper Primary', 'names' => ['Basic 4', 'Basic 5', 'Basic 6']],
    'jhs'           => ['label' => 'JHS',           'names' => ['JHS 1', 'JHS 2', 'JHS 3']],
];
$level_groups = [];
foreach ($group_defs as $gKey => $gDef) {
    $level_groups[$gKey] = [
        'label'   => $gDef['label'],
        'classes' => [],
    ];
    foreach ($classes as $c) {
        if (in_array($c['name'], $gDef['names'])) {
            $level_groups[$gKey]['classes'][] = $c;
        }
    }
}
// === END early definitions ===

// Handle Add/Edit Fee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $fee_title = sanitize($_POST['fee_title'] ?? '');
    $fee_type = sanitize($_POST['fee_type'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $year = sanitize($_POST['academic_year'] ?? '');
    $term = sanitize($_POST['term'] ?? '');
    $class_id = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
    $is_mandatory = isset($_POST['is_mandatory']) ? true : false;

    if ($_POST['action'] === 'add_fee') {
        $fee_group = $_POST['fee_group'] ?? '';
        try {
            // Determine which class_ids to insert for
            $target_class_ids = [];
            if ($fee_group === '__all__') {
                // All Classes: one record with null class_id
                $target_class_ids[] = null;
            } elseif ($fee_group !== '' && isset($level_groups[$fee_group])) {
                // Specific group: one record per class in the group
                foreach ($level_groups[$fee_group]['classes'] as $gc) {
                    $target_class_ids[] = $gc['id'];
                }
            } else {
                // Single class (or empty = All Classes fallback)
                $target_class_ids[] = $class_id;
            }

            // Fetch all existing fee_structures for duplicate check
            $all_fees = $pdo->query("SELECT * FROM fee_structures")->fetchAll();
            $inserted = 0;
            $skipped = 0;

            foreach ($target_class_ids as $tcid) {
                // Check duplicate (normalize term — seed data has "Term 1", form uses "1")
                $is_dup = false;
                foreach ($all_fees as $f) {
                    $fTitle = $f['title'] ?? '';
                    $fYear  = $f['academic_year'] ?? '';
                    $fTerm  = preg_replace('/[^0-9]/', '', (string)($f['term'] ?? ''));
                    $formTerm = preg_replace('/[^0-9]/', '', (string)$term);
                    if ($fTitle === $fee_title && $fYear === $year && $fTerm === $formTerm) {
                        $fClassId = !empty($f['class_id']) ? (int)$f['class_id'] : 0;
                        $tcidInt = !empty($tcid) ? (int)$tcid : 0;
                        if ($fClassId === $tcidInt) { $is_dup = true; break; }
                    }
                }
                if ($is_dup) {
                    $skipped++;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO fee_structures (title, fee_type, amount, academic_year, term, class_id, is_mandatory) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$fee_title, $fee_type, $amount, $year, $term, $tcid, $is_mandatory]);
                    $inserted++;
                }
            }

            if ($inserted > 0) {
                $parts = [];
                if ($inserted > 0) $parts[] = "$inserted fee(s) added";
                if ($skipped > 0) $parts[] = "$skipped duplicate(s) skipped";
                $message = "Fee structure: " . implode(', ', $parts) . ".";
                $message .= " Group: " . ($fee_group ?: 'single') . ".";
            } else {
                $message = "No new fees were added — a fee with this title already exists for the selected group/class.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'edit_fee') {
        $fee_id = $_POST['fee_id']; // UUID string, do NOT cast to int
        $fee_group = $_POST['fee_group'] ?? '';
        try {
            if ($fee_group !== '' && $fee_group !== '__all__' && isset($level_groups[$fee_group])) {
                // Group edit: replace ALL records with same title+year+term
                // with new records for all classes in this group.
                // First, find existing records with same title+year+term (normalized)
                $formTermNum = preg_replace('/[^0-9]/', '', (string)$term);
                $all_existing = $pdo->query("SELECT * FROM fee_structures")->fetchAll();
                $to_delete = [];
                foreach ($all_existing as $ex) {
                    $exTermNum = preg_replace('/[^0-9]/', '', (string)($ex['term'] ?? ''));
                    if ($ex['title'] === $fee_title && $ex['academic_year'] === $year && $exTermNum === $formTermNum) {
                        $to_delete[] = $ex['id'];
                    }
                }
                // Delete them
                foreach ($to_delete as $did) {
                    $pdo->prepare("DELETE FROM fee_structures WHERE id = ?")->execute([$did]);
                }
                // Insert new records for all classes in the group
                $inserted = 0;
                foreach ($level_groups[$fee_group]['classes'] as $gc) {
                    $stmt = $pdo->prepare("INSERT INTO fee_structures (title, fee_type, amount, academic_year, term, class_id, is_mandatory) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$fee_title, $fee_type, $amount, $year, $term, $gc['id'], $is_mandatory]);
                    $inserted++;
                }
                $message = "Fee structure updated: $inserted record(s) replaced for group '" . $level_groups[$fee_group]['label'] . "'.";
            } elseif ($fee_group === '__all__') {
                // "All Classes" group: replace with a single null-class_id record
                $formTermNum = preg_replace('/[^0-9]/', '', (string)$term);
                $all_existing = $pdo->query("SELECT * FROM fee_structures")->fetchAll();
                foreach ($all_existing as $ex) {
                    $exTermNum = preg_replace('/[^0-9]/', '', (string)($ex['term'] ?? ''));
                    if ($ex['title'] === $fee_title && $ex['academic_year'] === $year && $exTermNum === $formTermNum) {
                        $pdo->prepare("DELETE FROM fee_structures WHERE id = ?")->execute([$ex['id']]);
                    }
                }
                $stmt = $pdo->prepare("INSERT INTO fee_structures (title, fee_type, amount, academic_year, term, class_id, is_mandatory) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$fee_title, $fee_type, $amount, $year, $term, null, $is_mandatory]);
                $message = "Fee structure updated: record replaced for All Classes.";
            } else {
                // Single class edit: just update the one record
                $stmt = $pdo->prepare("UPDATE fee_structures SET title = ?, fee_type = ?, amount = ?, academic_year = ?, term = ?, class_id = ?, is_mandatory = ? WHERE id = ?");
                $stmt->execute([$fee_title, $fee_type, $amount, $year, $term, $class_id, $is_mandatory, $fee_id]);
                $message = "Fee structure updated successfully.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete_fee') {
        $fee_id = $_POST['fee_id']; // UUID string, do NOT cast to int
        try {
            $stmt = $pdo->prepare("DELETE FROM fee_structures WHERE id = ?");
            $stmt->execute([$fee_id]);
            $message = "Fee structure deleted successfully.";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'bulk_delete_fees') {
        $ids = $_POST['fee_ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            try {
                $pdo->beginTransaction();
                $deleted = 0;
                foreach ($ids as $fid) {
                    $stmt = $pdo->prepare("DELETE FROM fee_structures WHERE id = ?");
                    $stmt->execute([$fid]);
                    $deleted++;
                }
                $pdo->commit();
                    $message = "$deleted fee item(s) deleted successfully.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error during bulk delete: " . $e->getMessage();
            }
        } else {
            $error = "No fee items selected for deletion.";
        }
    }
}

// Fetch Fee Types from settings
$fee_types_list = explode(',', $settings['fee_types'] ?? 'Tuition,PTA Levy,Sports & Culture,ICT,Examination,Development,Feeding,Transport,Uniform,Books & Materials');

// Fetch All Fees — read from GET or fall back to POST (for bulk delete) or default
$filter_year = $_GET['year'] ?? $_POST['year'] ?? $current_year;
$filter_term = $_GET['term'] ?? $_POST['term'] ?? $current_term;
$filter_class = isset($_GET['class_id']) && $_GET['class_id'] !== '' ? $_GET['class_id'] : null;

// Fetch All Fees (bridge drops complex WHERE — filter in PHP)
// NOTE: Supabase REST returns JSON; types may differ from PHP (int vs string, etc.).
// Cast all filter values to strings for safe comparison.
$fees = array_filter($pdo->query("SELECT * FROM fee_structures")->fetchAll(), function($f) use ($filter_year, $filter_term, $filter_class) {
    if ((string)($f['academic_year'] ?? '') !== (string)$filter_year) return false;
    // NOTE: term may be stored as 'Term 1' (seed data) or '1' (fee form). Normalize by extracting number.
    $fTerm = preg_replace('/[^0-9]/', '', (string)($f['term'] ?? ''));
    $filterTerm = preg_replace('/[^0-9]/', '', (string)$filter_term);
    if ($fTerm !== $filterTerm) return false;
    if ($filter_class !== null) {
        $fClassId = !empty($f['class_id']) ? (string)$f['class_id'] : '';
        if ($fClassId !== (string)$filter_class) return false;
    }
    return true;
});
// Sort in PHP (bridge can't handle ORDER BY with COALESCE)
usort($fees, function($a, $b) {
    $aCid = (string)($a['class_id'] ?? ''); $bCid = (string)($b['class_id'] ?? '');
    if ($aCid !== $bCid) return strcmp($aCid, $bCid);
    $aMand = (bool)($a['is_mandatory'] ?? false);
    $bMand = (bool)($b['is_mandatory'] ?? false);
    if ($bMand !== $aMand) return $bMand ? 1 : -1;
    return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
});

// Enrich with class name
foreach ($fees as &$fee) {
    if (!empty($fee['class_id'])) {
        $cs = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
        $cs->execute([$fee['class_id']]);
        $cname = $cs->fetch();
        $fee['class_name'] = $cname ? $cname['name'] : 'All Classes';
    } else {
        $fee['class_name'] = 'All Classes';
    }
}

// Calculate totals
$total_expected = 0;
$total_mandatory = 0;
foreach ($fees as $f) {
    $total_expected += $f['amount'];
    if ($f['is_mandatory']) $total_mandatory += $f['amount'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Structure — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .modal { display: none; position: fixed; z-index: var(--z-modal-overlay); left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); animation: fadeIn var(--transition-base); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 550px; border-radius: 8px; position: relative; animation: scaleIn 0.3s var(--ease-out-expo); }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; line-height: 1; }
        .close-btn:hover { color: black; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
            <?php echo renderSidebar('fees', $school_name); ?>

        <main class="main-content" id="main-content">
            <div class="top-bar">
                <h2>Fee Structure Management</h2>
                <button id="openAddBtn" class="btn-primary" style="padding: 10px 20px;"><i class="fas fa-plus"></i> Add Fee</button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Filter Bar -->
            <form action="fees.php" method="GET">
                <div class="filter-bar">
                    <div class="filter-group">
                        <label>Academic Year</label>
                        <input type="text" name="year" value="<?php echo htmlspecialchars($filter_year); ?>" style="width: 130px;">
                    </div>
                    <div class="filter-group">
                        <label>Term</label>
                        <select name="term" style="width: 100px;">
                            <option value="1" <?php echo $filter_term == '1' ? 'selected' : ''; ?>>Term 1</option>
                            <option value="2" <?php echo $filter_term == '2' ? 'selected' : ''; ?>>Term 2</option>
                            <option value="3" <?php echo $filter_term == '3' ? 'selected' : ''; ?>>Term 3</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Class</label>
                        <select name="class_id" style="width: 160px;">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $cls): ?>
                                <option value="<?php echo $cls['id']; ?>" <?php echo $filter_class == $cls['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cls['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn-login"><i class="fas fa-filter"></i> Filter</button>
                    </div>
                </div>
            </form>

            <!-- Summary Cards -->
            <div class="stat-cards" style="margin-bottom: 25px;">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-list"></i></div>
                    <div class="stat-details"><h3><?php echo count($fees); ?></h3><p>Total Fee Items</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill"></i></div>
                    <div class="stat-details"><h3>GHS <?php echo number_format($total_expected, 2); ?></h3><p>Total Expected</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-exclamation-circle" style="color: #e74c3c;"></i></div>
                    <div class="stat-details"><h3>GHS <?php echo number_format($total_mandatory, 2); ?></h3><p>Mandatory Fees</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-tag" style="color: #f39c12;"></i></div>
                    <div class="stat-details"><h3>GHS <?php echo number_format($total_expected - $total_mandatory, 2); ?></h3><p>Optional Fees</p></div>
                </div>
            </div>

            <!-- Fees Table -->
            <div class="section">
                <h3>Fee Items — <?php echo htmlspecialchars($filter_year); ?> Term <?php echo htmlspecialchars($filter_term); ?></h3>
                <div style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; font-size: 13px;">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this.checked)">
                        Select All
                    </label>
                    <button id="bulkDeleteBtn" class="btn-admin-sm" style="background: #e74c3c; border: none; display: none;" onclick="confirmBulkDelete()">
                        <i class="fas fa-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
                    </button>
                </div>
                <form id="bulkDeleteForm" action="fees.php" method="POST">
                    <input type="hidden" name="action" value="bulk_delete_fees">
                    <input type="hidden" name="year" value="<?php echo htmlspecialchars($filter_year); ?>">
                    <input type="hidden" name="term" value="<?php echo htmlspecialchars($filter_term); ?>">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 40px;"></th>
                                <th>Fee Title</th>
                                <th>Type</th>
                                <th>Class</th>
                                <th>Amount (GHS)</th>
                                <th>Mandatory</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($fees)): ?>
                                <tr><td colspan="7" style="text-align:center; color: #666;">No fees configured for this filter.</td></tr>
                            <?php else: ?>
                                <?php foreach ($fees as $fee): ?>
                                <tr>
                                    <td><input type="checkbox" name="fee_ids[]" value="<?php echo $fee['id']; ?>" class="fee-checkbox" onchange="updateBulkDeleteButton()"></td>
                                    <td><strong><?php echo htmlspecialchars($fee['title']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($fee['fee_type'] ?? 'General'); ?></td>
                                    <td><?php echo htmlspecialchars($fee['class_name']); ?></td>
                                    <td><strong>GHS <?php echo number_format($fee['amount'], 2); ?></strong></td>
                                    <td>
                                        <?php if ($fee['is_mandatory']): ?>
                                            <span style="color: #e74c3c; font-weight: bold;">Yes</span>
                                        <?php else: ?>
                                            <span style="color: #f39c12;">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn-admin-sm" onclick="openEdit(<?php echo htmlspecialchars(json_encode($fee)); ?>)" style="background: #3498db; border: none; margin-right: 5px;">Edit</button>
                                        <form action="fees.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete this fee item?');">
                                            <input type="hidden" name="action" value="delete_fee">
                                            <input type="hidden" name="fee_id" value="<?php echo $fee['id']; ?>">
                                            <input type="hidden" name="year" value="<?php echo htmlspecialchars($filter_year); ?>">
                                            <input type="hidden" name="term" value="<?php echo htmlspecialchars($filter_term); ?>">
                                            <button type="submit" class="btn-admin-sm" style="background: #e74c3c; border: none;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Add Fee Modal -->
    <div id="addFeeModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeAddModal()">&times;</span>
            <h3>Add New Fee</h3>
            <form action="fees.php" method="POST" class="mt-15">
                <input type="hidden" name="action" value="add_fee">
                
                <div class="form-group">
                    <label>Fee Title</label>
                    <input type="text" name="fee_title" class="form-control" required placeholder="e.g. Tuition Fee, PTA Levy">
                </div>
                <div class="form-group">
                    <label>Fee Type</label>
                    <select name="fee_type" class="form-control" required>
                        <option value="">-- Select Type --</option>
                        <?php foreach ($fee_types_list as $type): ?>
                            <option value="<?php echo htmlspecialchars(trim($type)); ?>"><?php echo htmlspecialchars(trim($type)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount (GHS)</label>
                    <input type="number" step="0.01" name="amount" class="form-control" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Academic Year</label>
                    <input type="text" name="academic_year" class="form-control" value="<?php echo htmlspecialchars($current_year); ?>" required>
                </div>
                <div class="form-group">
                    <label>Term</label>
                    <select name="term" class="form-control" required>
                        <option value="1" <?php echo $current_term == '1' ? 'selected' : ''; ?>>Term 1</option>
                        <option value="2" <?php echo $current_term == '2' ? 'selected' : ''; ?>>Term 2</option>
                        <option value="3" <?php echo $current_term == '3' ? 'selected' : ''; ?>>Term 3</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Group (select to assign to all classes in this level)</label>
                    <select name="fee_group" id="add_fee_group" class="form-control">
                        <option value="">-- None (single class) --</option>
                        <option value="__all__">All Classes</option>
                        <?php foreach ($level_groups as $gKey => $g): ?>
                            <option value="<?php echo htmlspecialchars($gKey); ?>"><?php echo htmlspecialchars($g['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="add_class_group">
                    <label>Class (leave empty for all classes)</label>
                    <select name="class_id" class="form-control">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?php echo $cls['id']; ?>"><?php echo htmlspecialchars($cls['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="flex items-center gap-8">
                        <input type="checkbox" name="is_mandatory" value="1" checked>
                        Mandatory fee (required for all students)
                    </label>
                </div>
                <button type="submit" class="btn-submit w-full">Add Fee</button>
            </form>
        </div>
    </div>

    <!-- Edit Fee Modal -->
    <div id="editFeeModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeEditModal()">&times;</span>
            <h3>Edit Fee</h3>
            <form action="fees.php" method="POST" class="mt-15">
                <input type="hidden" name="action" value="edit_fee">
                <input type="hidden" name="fee_id" id="edit_fee_id">
                
                <div class="form-group">
                    <label>Fee Title</label>
                    <input type="text" name="fee_title" id="edit_fee_title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Fee Type</label>
                    <select name="fee_type" id="edit_fee_type" class="form-control" required>
                        <option value="">-- Select Type --</option>
                        <?php foreach ($fee_types_list as $type): ?>
                            <option value="<?php echo htmlspecialchars(trim($type)); ?>"><?php echo htmlspecialchars(trim($type)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount (GHS)</label>
                    <input type="number" step="0.01" name="amount" id="edit_amount" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Academic Year</label>
                    <input type="text" name="academic_year" id="edit_year" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Term</label>
                    <select name="term" id="edit_term" class="form-control" required>
                        <option value="1">Term 1</option>
                        <option value="2">Term 2</option>
                        <option value="3">Term 3</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Group (select to assign to all classes in this level)</label>
                    <select name="fee_group" id="edit_fee_group" class="form-control">
                        <option value="">-- None (single class) --</option>
                        <option value="__all__">All Classes</option>
                        <?php foreach ($level_groups as $gKey => $g): ?>
                            <option value="<?php echo htmlspecialchars($gKey); ?>"><?php echo htmlspecialchars($g['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="edit_class_group">
                    <label>Class (leave empty for all classes)</label>
                    <select name="class_id" id="edit_class_id" class="form-control">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?php echo $cls['id']; ?>"><?php echo htmlspecialchars($cls['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="flex items-center gap-8">
                        <input type="checkbox" name="is_mandatory" id="edit_is_mandatory" value="1">
                        Mandatory fee
                    </label>
                </div>
                <button type="submit" class="btn-submit w-full">Update Fee</button>
            </form>
        </div>
    </div>

    <script>
        // ====== Bulk Delete Helpers ======
        function toggleSelectAll(checked) {
            document.querySelectorAll('.fee-checkbox').forEach(function(cb) { cb.checked = checked; });
            updateBulkDeleteButton();
        }

        function updateBulkDeleteButton() {
            var checked = document.querySelectorAll('.fee-checkbox:checked');
            var count = checked.length;
            var btn = document.getElementById('bulkDeleteBtn');
            var span = document.getElementById('selectedCount');
            span.textContent = count;
            btn.style.display = count > 0 ? 'inline-block' : 'none';
        }

        function confirmBulkDelete() {
            var checked = document.querySelectorAll('.fee-checkbox:checked');
            if (checked.length === 0) return;
            if (confirm('Delete ' + checked.length + ' selected fee item(s)? This cannot be undone.')) {
                document.getElementById('bulkDeleteForm').submit();
            }
        }

        // Group mapping for JS: groupKey => { label, classIds: [...] }
        var LEVEL_GROUPS = <?php echo json_encode(array_map(function($g) {
            return [
                'label' => $g['label'],
                'classIds' => array_map(function($c) { return $c['id']; }, $g['classes']),
            ];
        }, $level_groups)); ?>;

        var addModal = document.getElementById('addFeeModal');
        var editModal = document.getElementById('editFeeModal');
        var addGroupSelect = document.getElementById('add_fee_group');
        var addClassGroup = document.getElementById('add_class_group');
        var editGroupSelect = document.getElementById('edit_fee_group');
        var editClassGroup = document.getElementById('edit_class_group');

        document.getElementById('openAddBtn').onclick = function() { addModal.style.display = 'block'; };
        function closeAddModal() { addModal.style.display = 'none'; }
        function closeEditModal() { editModal.style.display = 'none'; }
        window.onclick = function(e) { if (e.target == addModal) closeAddModal(); if (e.target == editModal) closeEditModal(); };

        // ====== Add form: show/hide class dropdown based on group ======
        addGroupSelect.addEventListener('change', function() {
            addClassGroup.style.display = this.value === '' ? 'block' : 'none';
        });
        if (addGroupSelect.value !== '') {
            addClassGroup.style.display = 'none';
        }

        // ====== Edit form: show/hide class dropdown based on group ======
        editGroupSelect.addEventListener('change', function() {
            var val = this.value;
            if (val === '') {
                editClassGroup.style.display = 'block';
            } else {
                editClassGroup.style.display = 'none';
                // Auto-select first class in this group into the (hidden) class dropdown
                var groupData = LEVEL_GROUPS[val];
                if (groupData && groupData.classIds.length > 0) {
                    document.getElementById('edit_class_id').value = groupData.classIds[0];
                }
            }
        });

        // ====== Open Edit: auto-detect group from class_id ======
        function openEdit(fee) {
            document.getElementById('edit_fee_id').value = fee.id;
            document.getElementById('edit_fee_title').value = fee.title;
            document.getElementById('edit_fee_type').value = fee.fee_type || '';
            document.getElementById('edit_amount').value = fee.amount;
            document.getElementById('edit_year').value = fee.academic_year;
            document.getElementById('edit_term').value = fee.term || '<?php echo $current_term; ?>';

            var classId = fee.class_id ? String(fee.class_id) : '';
            document.getElementById('edit_class_id').value = classId;

            // Detect group from class_id
            var detectedGroup = '';
            if (!classId) {
                detectedGroup = '__all__';
            } else {
                for (var gk in LEVEL_GROUPS) {
                    if (LEVEL_GROUPS[gk].classIds.indexOf(Number(classId)) !== -1) {
                        detectedGroup = gk;
                        break;
                    }
                }
            }
            editGroupSelect.value = detectedGroup;
            // Apply group show/hide
            if (detectedGroup === '') {
                editClassGroup.style.display = 'block';
            } else {
                editClassGroup.style.display = 'none';
            }

            document.getElementById('edit_is_mandatory').checked = fee.is_mandatory == true || fee.is_mandatory === 1;
            editModal.style.display = 'block';
        }
    </script>
</body>
</html>
