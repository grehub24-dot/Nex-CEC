<?php
require_once 'includes/db.php';
requireAccess('fees_debt');

$settings = fetchSettings($pdo);
$school_name = $settings['school_name'] ?? 'Nex CEC';
$current_year = $settings['current_academic_year'] ?? date('Y') . '/' . (date('Y') + 1);
$current_term = $settings['current_term'] ?? '1';

// Determine teacher's class restriction (for authorization check)
$teacher_class_ids = isTeacher() ? getTeacherClassIds($pdo) : [];
$teacher_class_names = []; // Resolved after classes are loaded below

$classes = [];
try {
    $stmt = $pdo->query("SELECT * FROM classes ORDER BY sort_order, name");
    $classes = $stmt->fetchAll();
} catch (Exception $e) {}

// Build class lookup
$classIdToName = [];
foreach ($classes as $c) { $classIdToName[(int)$c['id']] = $c['name']; }

// Resolve teacher class names now that classes are loaded
if (!empty($teacher_class_ids)) {
    $teacher_class_names = [];
    foreach ($teacher_class_ids as $tcid) {
        $n = $classIdToName[$tcid] ?? '';
        if ($n) $teacher_class_names[] = $n;
    }
    $teacher_class_names = array_unique($teacher_class_names);
}

$filter_class = isset($_GET['class']) ? sanitize($_GET['class']) : ($_POST['class_name'] ?? '');
$filter_year = isset($_GET['year']) ? sanitize($_GET['year']) : ($_POST['year'] ?? $current_year);
$filter_term = isset($_GET['term']) ? sanitize($_GET['term']) : ($_POST['term'] ?? $current_term);

$message = '';
$error = '';
$students_in_class = [];
$available_fees = [];

if ($filter_class) {
    // Fetch students in this class
    try {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE status = 'active' AND class_name = ? ORDER BY full_name ASC");
        $stmt->execute([$filter_class]);
        $students_in_class = $stmt->fetchAll();
    } catch (Exception $e) {}

    // Fetch fee_structures for this class/year/term
    // First resolve the class_id from the class name
    $filter_class_id = null;
    try {
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE name = ?");
        $stmt->execute([$filter_class]);
        $row = $stmt->fetch();
        if ($row) $filter_class_id = (int)$row['id'];
    } catch (Exception $e) {}
    
    // Use separate queries to avoid pg-bridge OR issues with parameters
    $available_fees = [];
    try {
        // 1) Class-specific fees
        if ($filter_class_id) {
            $stmt = $pdo->prepare("SELECT * FROM fee_structures 
                WHERE academic_year = ? AND term = ? 
                AND class_id = ? 
                ORDER BY is_mandatory DESC, fee_type, title");
            $stmt->execute([$filter_year, $filter_term, $filter_class_id]);
            $available_fees = $stmt->fetchAll();
        }
        // 2) Global fees (null class_id)
        $stmt = $pdo->prepare("SELECT * FROM fee_structures 
            WHERE academic_year = ? AND term = ? 
            AND class_id IS NULL 
            ORDER BY is_mandatory DESC, fee_type, title");
        $stmt->execute([$filter_year, $filter_term]);
        $available_fees = array_merge($available_fees, $stmt->fetchAll());
    } catch (Exception $e) {}
    
}

// Handle bulk apply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_bill']) && $filter_class) {
    // Authorization: verify teacher can only bill their assigned classes
    if (!isAdmin() && !empty($teacher_class_names) && !in_array($filter_class, $teacher_class_names)) {
        $error = "Access denied: you are not authorized to bill this class.";
        goto render_page;
    }
    validate_request_csrf();
    $selected_ids = $_POST['fee_items'] ?? [];
    $apply_mode = $_POST['apply_mode'] ?? 'all'; // 'all' or 'new_only'
    if (!is_array($selected_ids)) $selected_ids = [];

    // Re-fetch students inside POST handler to prevent stale data
    try {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE status = 'active' AND class_name = ? ORDER BY full_name ASC");
        $stmt->execute([$filter_class]);
        $students_in_class = $stmt->fetchAll();
    } catch (Exception $e) {
        $students_in_class = [];
    }

    try {
        $pdo->beginTransaction();
        $user_id = $_SESSION['user_id'];
        $stmtStaff = $pdo->prepare("SELECT id FROM staff WHERE user_id = ?");
        $stmtStaff->execute([$user_id]);
        $staffRow = $stmtStaff->fetch();
        $staff_id = $staffRow ? (int)$staffRow['id'] : null;

        $insStmt = $pdo->prepare("INSERT INTO student_bill_items (student_id, fee_structure_id, academic_year, term, title, amount, fee_type, is_optional, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // Bulk DELETE: one query instead of N individual DELETEs
        $delete_sids = [];
        foreach ($students_in_class as $s) {
            $sid = (int)$s['id'];
            $is_new = ($s['academic_year'] ?? '') === $filter_year && (string)($s['admission_term'] ?? '1') === (string)$filter_term;
            if ($apply_mode === 'new_only' && !$is_new) continue;
            $delete_sids[] = $sid;
        }
        if (!empty($delete_sids)) {
            $ph = implode(',', array_fill(0, count($delete_sids), '?'));
            $delParams = array_merge($delete_sids, [$filter_year, $filter_term]);
            $stmt_del = $pdo->prepare("DELETE FROM student_bill_items WHERE student_id IN ($ph) AND academic_year = ? AND term = ?");
            $stmt_del->execute($delParams);
        }

        $processed = 0;
        $skipped = 0;

        foreach ($students_in_class as $s) {
            $sid = (int)$s['id'];
            $is_new = ($s['academic_year'] ?? '') === $filter_year && (string)($s['admission_term'] ?? '1') === (string)$filter_term;

            // In 'new_only' mode, skip returning students
            if ($apply_mode === 'new_only' && !$is_new) {
                $skipped++;
                continue;
            }

            // Insert selected fee items (DELETE already done in bulk above)
            foreach ($available_fees as $af) {
                $fs_id = $af['id'];
                if (!in_array($fs_id, $selected_ids)) continue;

                // Skip Admission Fee for returning students (always)
                $is_admission = stripos($af['title'], 'admission') !== false;
                if ($is_admission && !$is_new) continue;

                $is_optional = empty($af['is_mandatory']) ? true : false;
                $insStmt->execute([
                    $sid, $fs_id, $filter_year, $filter_term,
                    $af['title'], $af['amount'], $af['fee_type'] ?? 'General',
                    $is_optional ? 1 : 0,
                    $staff_id
                ]);
            }

            $processed++;
        }

        $pdo->commit();
        $total = count($students_in_class);
        $message = "Bills created for $processed student(s).";
        if ($apply_mode === 'all') {
            $message .= " (All $total students in $filter_class)";
        } else {
            $message .= " ($skipped returning students skipped, $total total in class)";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Handle clear all bills for this class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_bills']) && $filter_class) {
    // Authorization: verify teacher can only clear bills for their assigned classes
    if (!isAdmin() && !empty($teacher_class_names) && !in_array($filter_class, $teacher_class_names)) {
        $error = "Access denied: you are not authorized to modify bills for this class.";
        goto render_page;
    }
    validate_request_csrf();
    // Re-fetch students to prevent stale data
    try {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE status = 'active' AND class_name = ? ORDER BY full_name ASC");
        $stmt->execute([$filter_class]);
        $fresh_students = $stmt->fetchAll();
    } catch (Exception $e) {
        $fresh_students = [];
    }
    try {
        $student_ids = array_map(fn($s) => (int)$s['id'], $fresh_students);
        if (!empty($student_ids)) {
            $ph = implode(',', array_fill(0, count($student_ids), '?'));
            $params = array_merge($student_ids, [$filter_year, $filter_term]);
            $stmt = $pdo->prepare("DELETE FROM student_bill_items WHERE student_id IN ($ph) AND academic_year = ? AND term = ?");
            $stmt->execute($params);
        }
        $message = "All bills cleared for " . htmlspecialchars($filter_class) . ".";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

render_page:
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Billing — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container { max-width: 900px; margin: 20px auto; padding: 0 20px; }
        .card { background: white; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.08); padding: 24px; margin-bottom: 16px; }
        .flex-row { display: flex; gap: 12px; flex-wrap: wrap; align-items: end; }
        .flex-row .form-group { margin: 0; min-width: 140px; flex: 1; }
        .flex-row .form-group label { font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 4px; }
        .flex-row .form-group select { width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
        .stat-banner { display: flex; gap: 16px; flex-wrap: wrap; padding: 16px; background: #f8f9fa; border-radius: 8px; margin-bottom: 16px; }
        .stat-banner > div { flex: 1; min-width: 100px; text-align: center; }
        .stat-banner h3 { font-size: 24px; margin: 0; }
        .stat-banner p { font-size: 11px; color: #888; text-transform: uppercase; margin: 2px 0 0; }
        .fee-item { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #f0f0f0; gap: 12px; }
        .fee-item:last-child { border-bottom: none; }
        .fee-item input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; flex-shrink: 0; }
        .fee-item .fee-info { flex: 1; }
        .fee-item .fee-title { font-weight: 600; font-size: 14px; }
        .fee-item .fee-meta { font-size: 12px; color: #888; margin-top: 2px; }
        .fee-item .fee-amount { font-weight: 700; font-size: 15px; color: #2c3e50; white-space: nowrap; }
        .fee-item .badge-auto { font-size: 11px; background: #d6eaf8; color: #2471a3; padding: 1px 6px; border-radius: 8px; margin-left: 6px; }
        .fee-item .badge-new-only { font-size: 11px; background: #d5f5e3; color: #1e8449; padding: 1px 6px; border-radius: 8px; margin-left: 6px; }
        .fee-item.mandatory { background: #f8f9fa; border-radius: 6px; padding: 12px; }
        .select-all-row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 2px solid #e9ecef; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555; }
        .select-all-row input { width: 18px; height: 18px; cursor: pointer; }
        .action-buttons { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px; }
        .btn-primary { padding: 10px 24px; background: #1a5276; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .btn-primary:hover { background: #154360; }
        .btn-success { padding: 10px 24px; background: #27ae60; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .btn-success:hover { background: #219a52; }
        .btn-danger { padding: 10px 24px; background: #e74c3c; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; }
        .btn-danger:hover { background: #c0392b; }
        .btn-warning { padding: 10px 24px; background: #f39c12; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; }
        .btn-warning:hover { background: #d68910; }
        .btn-back { padding: 10px 20px; background: #95a5a6; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; }
        .btn-back:hover { background: #7f8c8d; }
        .summary-bar { display: flex; justify-content: space-between; align-items: center; background: #1a5276; color: white; padding: 14px 20px; border-radius: 8px; margin-top: 16px; }
        .summary-bar .total { font-size: 20px; font-weight: 700; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php echo renderSidebar('fees_debt', $school_name); ?>
    <main class="main-content">
        <div class="container">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h2 style="margin:0;"><i class="fas fa-users"></i> Class Billing</h2>
                <a href="fees_debt.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Fee Debt</a>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

            <!-- Class/Year/Term Selector -->
            <form method="GET" class="card">
                <div class="flex-row">
                    <div class="form-group">
                        <label>Class</label>
                        <select name="class" required>
                            <option value="">— Select Class —</option>
                            <?php foreach ($classes as $cls): ?>
                                <option value="<?php echo htmlspecialchars($cls['name']); ?>" <?php echo $filter_class === $cls['name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cls['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Academic Year</label>
                        <select name="year">
                            <?php
                            $base_year = (int)date('Y');
                            for ($y = $base_year - 2; $y <= $base_year + 1; $y++) {
                                $yr = $y . '/' . ($y + 1);
                                $sel = ($filter_year === $yr) ? 'selected' : '';
                                echo "<option value=\"$yr\" $sel>$yr</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Term</label>
                        <select name="term">
                            <option value="1" <?php echo $filter_term === '1' ? 'selected' : ''; ?>>Term 1</option>
                            <option value="2" <?php echo $filter_term === '2' ? 'selected' : ''; ?>>Term 2</option>
                            <option value="3" <?php echo $filter_term === '3' ? 'selected' : ''; ?>>Term 3</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:0;">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Load Class</button>
                    </div>
                </div>
            </form>

            <?php if ($filter_class && !empty($students_in_class)): 
                $new_count = count(array_filter($students_in_class, fn($s) => ($s['academic_year'] ?? '') === $filter_year && (string)($s['admission_term'] ?? '1') === (string)$filter_term));
                $returning_count = count($students_in_class) - $new_count;
            ?>
                <!-- Stats -->
                <div class="stat-banner">
                    <div><h3><?php echo count($students_in_class); ?></h3><p>Students in <?php echo htmlspecialchars($filter_class); ?></p></div>
                    <div><h3 style="color:#1e8449;"><?php echo $new_count; ?></h3><p>New Admissions</p></div>
                    <div><h3 style="color:#7f8c8d;"><?php echo $returning_count; ?></h3><p>Returning</p></div>
                    <div><h3 style="color:#2471a3;"><?php echo count($available_fees); ?></h3><p>Fee Items Available</p></div>
                </div>

                <!-- Fee Selection -->
                <form method="POST" class="card">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($filter_class); ?>">
                    <input type="hidden" name="year" value="<?php echo htmlspecialchars($filter_year); ?>">
                    <input type="hidden" name="term" value="<?php echo htmlspecialchars($filter_term); ?>">


                    <h4 style="margin:0 0 12px;">Select Fee Items to Apply</h4>
                    <p style="font-size:12px;color:#888;margin:0 0 12px;">
                        <span class="badge-auto">Mandatory (all students)</span> — auto-applied to everyone (locked)
                        &nbsp;|&nbsp; <span class="badge-new-only">New students only</span> — only applied to students admitted in <?php echo htmlspecialchars($filter_year); ?> Term <?php echo htmlspecialchars($filter_term); ?>
                        &nbsp;|&nbsp; Others — optional, staff discretion
                    </p>

                    <div class="select-all-row">
                        <input type="checkbox" id="selectAll" onchange="toggleAll(this.checked)">
                        <label for="selectAll" style="cursor:pointer;">Select / Deselect All Optional Items</label>
                    </div>

                    <?php if (empty($available_fees)): ?>
                        <p style="color:#999;padding:20px;text-align:center;">No fee structures defined for <?php echo htmlspecialchars($filter_class); ?>.</p>
                    <?php else: ?>
                        <?php foreach ($available_fees as $fee): 
                            $is_termly = stripos($fee['title'], 'termly') !== false;
                            $is_admission = stripos($fee['title'], 'admission') !== false;
                            $is_optional = empty($fee['is_mandatory']);
                        ?>
                            <div class="fee-item <?php echo $is_termly ? 'mandatory' : ''; ?>">
                                <input type="checkbox" name="fee_items[]" value="<?php echo htmlspecialchars($fee['id']); ?>" 
                                    id="fee_<?php echo htmlspecialchars($fee['id']); ?>"
                                    <?php if ($is_termly) echo 'checked onclick="return false;"'; ?>
                                    <?php if ($is_admission && !$is_termly) echo 'checked'; ?>>
                                <div class="fee-info">
                                    <div>
                                        <span class="fee-title"><?php echo htmlspecialchars($fee['title']); ?></span>
                                        <?php if ($is_termly): ?>
                                            <span class="badge-auto">Mandatory (all students)</span>
                                        <?php endif; ?>
                                        <?php if ($is_admission && !$is_termly): ?>
                                            <span class="badge-new-only">New students only</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fee-meta">
                                        <?php echo htmlspecialchars($fee['fee_type'] ?? 'General'); ?>
                                        <?php if (!empty($fee['class_id'])): ?>
                                            | <?php echo htmlspecialchars($classIdToName[(int)$fee['class_id']] ?? 'All Classes'); ?>
                                        <?php else: ?>
                                            | All Classes
                                        <?php endif; ?>
                                        <?php if ($is_optional): ?> | <span style="color:#f39c12;">Optional</span><?php endif; ?>
                                    </div>
                                </div>
                                <div class="fee-amount">GHS <?php echo number_format((float)$fee['amount'], 2); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="submit" name="apply_bill" value="1" class="btn-success" onclick="document.getElementById('apply_mode').value='all';return confirm('Apply selected fees to ALL <?php echo count($students_in_class); ?> students in <?php echo htmlspecialchars($filter_class); ?>?');">
                            <i class="fas fa-check-circle"></i> Apply to All Students
                        </button>
                        <?php if ($new_count > 0): ?>
                        <button type="submit" name="apply_bill" value="1" class="btn-warning" onclick="document.getElementById('apply_mode').value='new_only';return confirm('Apply selected fees to <?php echo $new_count; ?> NEW students only? Returning students will be skipped.');">
                            <i class="fas fa-user-plus"></i> Apply to New Students Only (<?php echo $new_count; ?>)
                        </button>
                        <?php endif; ?>
                        <input type="hidden" name="apply_mode" id="apply_mode" value="all">
                        <button type="submit" name="clear_bills" value="1" class="btn-danger" onclick="return confirm('DELETE all bills for <?php echo htmlspecialchars($filter_class); ?>? This cannot be undone.');">
                            <i class="fas fa-trash"></i> Clear All Bills
                        </button>
                    </div>
                </form>

                <!-- Student List with Bill Status -->
                <div class="card">
                    <h4 style="margin:0 0 12px;">Students <span style="font-weight:400;color:#888;font-size:13px;">(bill status)</span></h4>
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:13px;">
                            <thead><tr style="border-bottom:2px solid #e9ecef;">
                                <th style="text-align:left;padding:8px 6px;">#</th>
                                <th style="text-align:left;padding:8px 6px;">Student</th>
                                <th style="text-align:center;padding:8px 6px;">Admitted (Year/Term)</th>
                                <th style="text-align:center;padding:8px 6px;">Status</th>
                                <th style="text-align:right;padding:8px 6px;">Bill Total</th>
                                <th style="text-align:center;padding:8px 6px;">Actions</th>
                            </tr></thead>
                            <tbody>
                            <?php 
                            // Pre-fetch bill totals for all students
                            $sid_list = array_map(fn($s) => (int)$s['id'], $students_in_class);
                            $bill_totals = [];
                            if (!empty($sid_list)) {
                                $ph = implode(',', array_fill(0, count($sid_list), '?'));
                                try {
                                    $stmt = $pdo->prepare("SELECT student_id, SUM(amount) as total FROM student_bill_items WHERE student_id IN ($ph) AND academic_year = ? AND term = ? GROUP BY student_id");
                                    $stmt->execute(array_merge($sid_list, [$filter_year, $filter_term]));
                                    foreach ($stmt->fetchAll() as $bt) {
                                        $bill_totals[(int)$bt['student_id']] = (float)$bt['total'];
                                    }
                                } catch (Exception $e) {}
                            }
                            $i = 0; 
                            foreach ($students_in_class as $s): $i++; 
                                $sid = (int)$s['id'];
                                $is_new = ($s['academic_year'] ?? '') === $filter_year && (string)($s['admission_term'] ?? '1') === (string)$filter_term;
                                $bt = $bill_totals[$sid] ?? 0;
                            ?>
                                <tr style="border-bottom:1px solid #f0f0f0;">
                                    <td style="padding:8px 6px;"><?php echo $i; ?></td>
                                    <td style="padding:8px 6px;"><strong><?php echo htmlspecialchars($s['full_name'] ?? ''); ?></strong></td>
                                    <td style="padding:8px 6px;text-align:center;font-size:12px;">
                                        <?php echo htmlspecialchars($s['academic_year'] ?? '-'); ?> / T<?php echo htmlspecialchars($s['admission_term'] ?? '1'); ?>
                                        <?php if ($is_new): ?><span style="display:inline-block;background:#d5f5e3;color:#1e8449;padding:1px 6px;border-radius:8px;font-size:10px;font-weight:600;margin-left:4px;">NEW</span><?php endif; ?>
                                    </td>
                                    <td style="padding:8px 6px;text-align:center;">
                                        <?php if ($bt > 0): ?>
                                            <span style="display:inline-block;background:#d6eaf8;color:#2471a3;padding:2px 8px;border-radius:8px;font-size:11px;font-weight:600;">Billed</span>
                                        <?php else: ?>
                                            <span style="display:inline-block;background:#fadbd8;color:#c0392b;padding:2px 8px;border-radius:8px;font-size:11px;font-weight:600;">No Bill</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:8px 6px;text-align:right;font-weight:600;">GHS <?php echo number_format($bt, 2); ?></td>
                                    <td style="padding:8px 6px;text-align:center;">
                                        <a href="student_billing.php?student_id=<?php echo $sid; ?>&year=<?php echo urlencode($filter_year); ?>&term=<?php echo urlencode($filter_term); ?>&redirect=class_billing.php?class=<?php echo urlencode($filter_class); ?>" style="font-size:12px;color:#2980b9;text-decoration:none;">
                                            <i class="fas fa-edit"></i> Edit Bill
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($filter_class): ?>
                <div class="card" style="text-align:center;padding:40px;color:#999;">
                    <i class="fas fa-user-graduate" style="font-size:48px;margin-bottom:12px;"></i>
                    <p>No active students found in <?php echo htmlspecialchars($filter_class); ?>.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
function toggleAll(checked) {
    var boxes = document.querySelectorAll('input[name="fee_items[]"]');
    for (var i = 0; i < boxes.length; i++) {
        if (boxes[i].getAttribute('onclick') === 'return false;') continue;
        boxes[i].checked = checked;
    }
}
</script>
</body>
</html>
