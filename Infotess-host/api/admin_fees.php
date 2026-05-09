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

// Handle Add/Edit Fee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $fee_title = sanitize($_POST['fee_title']);
    $fee_type = sanitize($_POST['fee_type']);
    $amount = floatval($_POST['amount']);
    $year = sanitize($_POST['academic_year']);
    $term = sanitize($_POST['term']);
    $class_id = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
    $is_mandatory = isset($_POST['is_mandatory']) ? true : false;

    if ($_POST['action'] === 'add_fee') {
        try {
            // Check for duplicate (bridge drops COALESCE — do PHP-side filter instead)
            $all_fees = $pdo->query("SELECT * FROM fee_structures")->fetchAll();
            $duplicate = false;
            foreach ($all_fees as $f) {
                if ($f['title'] === $fee_title && $f['academic_year'] === $year && $f['term'] === $term) {
                    $fClassId = !empty($f['class_id']) ? (int)$f['class_id'] : 0;
                    $cClassId = !empty($class_id) ? (int)$class_id : 0;
                    if ($fClassId === $cClassId) { $duplicate = true; break; }
                }
            }
            if ($duplicate) {
                $error = "This fee already exists for the selected class and term.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO fee_structures (title, fee_type, amount, academic_year, term, class_id, is_mandatory) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$fee_title, $fee_type, $amount, $year, $term, $class_id, $is_mandatory]);
                $message = "Fee structure added successfully.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'edit_fee') {
        $fee_id = (int)$_POST['fee_id'];
        try {
            $stmt = $pdo->prepare("UPDATE fee_structures SET title = ?, fee_type = ?, amount = ?, academic_year = ?, term = ?, class_id = ?, is_mandatory = ? WHERE id = ?");
            $stmt->execute([$fee_title, $fee_type, $amount, $year, $term, $class_id, $is_mandatory, $fee_id]);
            $message = "Fee structure updated successfully.";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete_fee') {
        $fee_id = (int)$_POST['fee_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM fee_structures WHERE id = ?");
            $stmt->execute([$fee_id]);
            $message = "Fee structure deleted successfully.";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch Fee Types from settings
$fee_types_list = explode(',', $settings['fee_types'] ?? 'Tuition,PTA Levy,Sports & Culture,ICT,Examination,Development,Feeding,Transport,Uniform,Books & Materials');

// Fetch Classes
$classes = [];
try {
    $stmt = $pdo->query("SELECT * FROM classes ORDER BY sort_order ASC");
    $classes = $stmt->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

// Fetch All Fees
$filter_year = $_GET['year'] ?? $current_year;
$filter_term = $_GET['term'] ?? $current_term;
$filter_class = isset($_GET['class_id']) && $_GET['class_id'] !== '' ? $_GET['class_id'] : null;

// Fetch All Fees (bridge drops complex WHERE — filter in PHP)
// NOTE: Supabase REST returns JSON; types may differ from PHP (int vs string, etc.).
// Cast all filter values to strings for safe comparison.
$fees = array_filter($pdo->query("SELECT * FROM fee_structures")->fetchAll(), function($f) use ($filter_year, $filter_term, $filter_class) {
    if ((string)($f['academic_year'] ?? '') !== (string)$filter_year) return false;
    if ((string)($f['term'] ?? '') !== (string)$filter_term) return false;
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
        $cs = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
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
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 550px; border-radius: 8px; position: relative; }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover { color: black; }
        .filter-bar { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 8px; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { font-size: 0.85rem; font-weight: 600; color: #555; }
        .filter-group select, .filter-group input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
            <?php echo renderSidebar('fees', $school_name); ?>

        <main class="main-content">
            <div class="top-bar">
                <h2>Fee Structure Management</h2>
                <button id="openAddBtn" class="btn-primary" style="padding: 10px 20px;"><i class="fas fa-plus"></i> Add New Fee</button>
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
                        <button type="submit" class="btn-login" style="padding: 8px 16px;"><i class="fas fa-filter"></i> Filter</button>
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
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
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
                                <tr><td colspan="6" style="text-align:center; color: #666;">No fees configured for this filter.</td></tr>
                            <?php else: ?>
                                <?php foreach ($fees as $fee): ?>
                                <tr>
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
                                        <button class="btn-edit" onclick="openEdit(<?php echo htmlspecialchars(json_encode($fee)); ?>)" style="padding: 4px 10px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 5px; font-size: 0.85rem;">Edit</button>
                                        <form action="fees.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete this fee item?');">
                                            <input type="hidden" name="action" value="delete_fee">
                                            <input type="hidden" name="fee_id" value="<?php echo $fee['id']; ?>">
                                            <input type="hidden" name="year" value="<?php echo htmlspecialchars($filter_year); ?>">
                                            <input type="hidden" name="term" value="<?php echo htmlspecialchars($filter_term); ?>">
                                            <button type="submit" style="padding: 4px 10px; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Fee Modal -->
    <div id="addFeeModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeAddModal()">&times;</span>
            <h3>Add New Fee</h3>
            <form action="fees.php" method="POST" style="margin-top: 15px;">
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
                    <label>Class (leave empty for all classes)</label>
                    <select name="class_id" class="form-control">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?php echo $cls['id']; ?>"><?php echo htmlspecialchars($cls['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" name="is_mandatory" value="1" checked>
                        Mandatory fee (required for all students)
                    </label>
                </div>
                <button type="submit" class="btn-submit" style="width:100%;">Add Fee</button>
            </form>
        </div>
    </div>

    <!-- Edit Fee Modal -->
    <div id="editFeeModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeEditModal()">&times;</span>
            <h3>Edit Fee</h3>
            <form action="fees.php" method="POST" style="margin-top: 15px;">
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
                    <label>Class (leave empty for all classes)</label>
                    <select name="class_id" id="edit_class_id" class="form-control">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?php echo $cls['id']; ?>"><?php echo htmlspecialchars($cls['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" name="is_mandatory" id="edit_is_mandatory" value="1">
                        Mandatory fee
                    </label>
                </div>
                <button type="submit" class="btn-submit" style="width:100%;">Update Fee</button>
            </form>
        </div>
    </div>

    <script>
        const addModal = document.getElementById('addFeeModal');
        const editModal = document.getElementById('editFeeModal');

        document.getElementById('openAddBtn').onclick = function() { addModal.style.display = 'block'; };
        function closeAddModal() { addModal.style.display = 'none'; }
        function closeEditModal() { editModal.style.display = 'none'; }
        window.onclick = function(e) { if (e.target == addModal) closeAddModal(); if (e.target == editModal) closeEditModal(); };

        function openEdit(fee) {
            document.getElementById('edit_fee_id').value = fee.id;
            document.getElementById('edit_fee_title').value = fee.title;
            document.getElementById('edit_fee_type').value = fee.fee_type || '';
            document.getElementById('edit_amount').value = fee.amount;
            document.getElementById('edit_year').value = fee.academic_year;
            document.getElementById('edit_term').value = fee.term || '<?php echo $current_term; ?>';
            document.getElementById('edit_class_id').value = fee.class_id || '';
            document.getElementById('edit_is_mandatory').checked = fee.is_mandatory == true || fee.is_mandatory === 1;
            editModal.style.display = 'block';
        }
    </script>
</body>
</html>
