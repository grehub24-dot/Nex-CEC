<?php
require_once 'includes/db.php';
requireAccess('fees_debt');

$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$school_name = $settings['school_name'] ?? 'Nex CEC';
$current_year = $settings['current_academic_year'] ?? date('Y') . '/' . (date('Y') + 1);
$current_term = $settings['current_term'] ?? '1';

$student_id = (int)($_GET['student_id'] ?? 0);
$filter_year = isset($_GET['year']) ? sanitize($_GET['year']) : $current_year;
$filter_term = isset($_GET['term']) ? sanitize($_GET['term']) : $current_term;

// Redirect back here after save
$redirect = isset($_GET['redirect']) ? sanitize($_GET['redirect']) : 'fees_debt.php';

// Fetch student
$student = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
} catch (Exception $e) {}
if (!$student) { die("Invalid student."); }

// Fetch classes
$classes = $pdo->query("SELECT * FROM classes")->fetchAll();
$classIdToName = [];
foreach ($classes as $c) { $classIdToName[(int)$c['id']] = $c['name']; }

// Fetch fee_structures for this class/year/term
$available_fees = [];
// First resolve the class_id from the student's class name
$student_class_id = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE name = ?");
    $stmt->execute([$student['class_name']]);
    $row = $stmt->fetch();
    if ($row) $student_class_id = (int)$row['id'];
} catch (Exception $e) {}

try {
    // 1) Class-specific fees for this student's class
    if ($student_class_id) {
        $stmt = $pdo->prepare("SELECT * FROM fee_structures 
            WHERE academic_year = ? AND term = ? 
            AND class_id = ? 
            ORDER BY is_mandatory DESC, fee_type, title");
        $stmt->execute([$filter_year, $filter_term, $student_class_id]);
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

// Determine if student is "new" (admitted in this term of this academic year)
// A student is new only in the exact term they enrolled; once the term advances they become returning
$is_new_student = ($student['academic_year'] ?? '') === $filter_year && (string)($student['admission_term'] ?? '1') === (string)$filter_term;

// Fetch existing bill items for this student
$existing_items = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM student_bill_items WHERE student_id = ? AND academic_year = ? AND term = ?");
    $stmt->execute([$student_id, $filter_year, $filter_term]);
    $existing_items = $stmt->fetchAll();
} catch (Exception $e) {}

// Build lookup of existing fee_structure_ids
$existing_fs_ids = [];
foreach ($existing_items as $ei) {
    if ($ei['fee_structure_id']) {
        $existing_fs_ids[$ei['fee_structure_id']] = true;
    }
}

// Handle save
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_bill'])) {
    validate_request_csrf();
    $selected_ids = $_POST['fee_items'] ?? [];
    if (!is_array($selected_ids)) $selected_ids = [];

    try {
        $pdo->beginTransaction();

        // Delete existing bill items
        $stmt = $pdo->prepare("DELETE FROM student_bill_items WHERE student_id = ? AND academic_year = ? AND term = ?");
        $stmt->execute([$student_id, $filter_year, $filter_term]);

        // Insert selected items
        $inserted = 0;
        $user_id = $_SESSION['user_id'];
        $stmtStaff = $pdo->prepare("SELECT id FROM staff WHERE user_id = ?");
        $stmtStaff->execute([$user_id]);
        $staffRow = $stmtStaff->fetch();

        $insStmt = $pdo->prepare("INSERT INTO student_bill_items (student_id, fee_structure_id, academic_year, term, title, amount, fee_type, is_optional, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($available_fees as $af) {
            $fs_id = $af['id'];
            $is_selected = in_array($fs_id, $selected_ids);
            if (!$is_selected) continue;

            $is_optional = empty($af['is_mandatory']) ? true : false;
            $insStmt->execute([
                $student_id, $fs_id, $filter_year, $filter_term,
                $af['title'], $af['amount'], $af['fee_type'] ?? 'General',
                $is_optional ? 1 : 0,
                $staffRow ? (int)$staffRow['id'] : null
            ]);
            $inserted++;
        }

        $pdo->commit();
        $message = "Bill updated: $inserted fee item(s) saved.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error saving bill: " . $e->getMessage();
    }
}

// Re-fetch existing items after save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM student_bill_items WHERE student_id = ? AND academic_year = ? AND term = ?");
        $stmt->execute([$student_id, $filter_year, $filter_term]);
        $existing_items = $stmt->fetchAll();
    } catch (Exception $e) {}
    $existing_fs_ids = [];
    foreach ($existing_items as $ei) {
        if ($ei['fee_structure_id']) {
            $existing_fs_ids[$ei['fee_structure_id']] = true;
        }
    }
}

// Calculate totals from existing bill items
$billed_total = array_sum(array_column($existing_items, 'amount'));

// Auto-check logic:
function isAutoChecked($fee, $is_new_student) {
    $title = strtolower($fee['title']);
    $is_termly = strpos($title, 'termly') !== false;
    $is_admission = strpos($title, 'admission') !== false;
    // Termly → always checked
    if ($is_termly) return true;
    // Admission → checked only for new students
    if ($is_admission) return $is_new_student;
    // Others → unchecked by default
    return false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Bill — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .bill-container { max-width: 800px; margin: 20px auto; padding: 0 20px; }
        .bill-card { background: white; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.08); padding: 24px; margin-bottom: 16px; }
        .bill-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .student-info { font-size: 14px; }
        .student-info h3 { margin: 0 0 4px; font-size: 18px; }
        .student-info p { margin: 2px 0; color: #666; }
        .badge-new { display: inline-block; background: #d5f5e3; color: #1e8449; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-old { display: inline-block; background: #eaecee; color: #7f8c8d; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .fee-item { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #f0f0f0; gap: 12px; }
        .fee-item:last-child { border-bottom: none; }
        .fee-item input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; flex-shrink: 0; }
        .fee-item .fee-info { flex: 1; }
        .fee-item .fee-title { font-weight: 600; font-size: 14px; }
        .fee-item .fee-meta { font-size: 12px; color: #888; margin-top: 2px; }
        .fee-item .fee-amount { font-weight: 700; font-size: 15px; color: #2c3e50; white-space: nowrap; }
        .fee-item .fee-auto { font-size: 11px; background: #d6eaf8; color: #2471a3; padding: 1px 6px; border-radius: 8px; margin-left: 6px; }
        .fee-item .fee-new-only { font-size: 11px; background: #d5f5e3; color: #1e8449; padding: 1px 6px; border-radius: 8px; margin-left: 6px; }
        .fee-item.mandatory { background: #f8f9fa; border-radius: 6px; padding: 12px; }
        .summary-bar { display: flex; justify-content: space-between; align-items: center; background: #1a5276; color: white; padding: 14px 20px; border-radius: 8px; margin-top: 16px; }
        .summary-bar .total { font-size: 20px; font-weight: 700; }
        .btn-save { padding: 10px 28px; background: #27ae60; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; }
        .btn-save:hover { background: #219a52; }
        .btn-back { padding: 10px 20px; background: #95a5a6; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; }
        .btn-back:hover { background: #7f8c8d; }
        .actions-top { display: flex; gap: 10px; margin-bottom: 16px; }
        .fee-checkbox-group { margin: 4px 0; }
        .select-all-row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 2px solid #e9ecef; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555; }
        .select-all-row input { width: 18px; height: 18px; cursor: pointer; }
        @media print { .no-print { display: none !important; } }
        .fee-total-display { font-size: 16px; }
    </style>
</head>
<body>
<div class="dashboard-container no-print">
    <?php echo renderSidebar('fees_debt', $school_name); ?>
    <main class="main-content">
        <div class="bill-container">
            <div class="actions-top">
                <a href="<?php echo htmlspecialchars($redirect); ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Fee Debt</a>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

            <!-- Student Info -->
            <div class="bill-card">
                <div class="bill-header">
                    <div class="student-info">
                        <h3><?php echo htmlspecialchars($student['full_name'] ?? ''); ?></h3>
                        <p>Class: <strong><?php echo htmlspecialchars($student['class_name'] ?? ''); ?></strong> 
                           | Admission No: <?php echo htmlspecialchars($student['admission_number'] ?? $student['enrollment_id'] ?? ''); ?>
                           | Admitted: <?php echo htmlspecialchars($student['academic_year'] ?? 'N/A'); ?> / T<?php echo htmlspecialchars($student['admission_term'] ?? '1'); ?>
                           <span class="<?php echo $is_new_student ? 'badge-new' : 'badge-old'; ?>" style="margin-left:8px;">
                               <?php echo $is_new_student ? '★ New Admission' : 'Returning'; ?>
                           </span>
                        </p>
                        <p style="color:#888;font-size:12px;"><?php echo htmlspecialchars($filter_year); ?> — Term <?php echo htmlspecialchars($filter_term); ?></p>
                    </div>
                    <div style="text-align:right;">
                        <?php if (!empty($existing_items)): ?>
                            <div style="font-size:13px;color:#555;">Current Bill Total</div>
                            <div style="font-size:24px;font-weight:700;color:#2c3e50;">GHS <?php echo number_format($billed_total, 2); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Fee Selection -->
            <form method="POST" class="bill-card">
                <?php csrf_field(); ?>

                <div class="select-all-row">
                    <input type="checkbox" id="selectAll" onchange="toggleAll(this.checked)">
                    <label for="selectAll" style="cursor:pointer;">Select / Deselect All</label>
                </div>

                <?php if (empty($available_fees)): ?>
                    <p style="color:#999;padding:20px;text-align:center;">No fee structures defined for <?php echo htmlspecialchars($student['class_name']); ?> in <?php echo htmlspecialchars($filter_year); ?> Term <?php echo htmlspecialchars($filter_term); ?>.</p>
                <?php else: $prev_type = ''; ?>
                    <?php foreach ($available_fees as $fee): 
                        $fs_id = $fee['id'];
                        $is_checked = isset($existing_fs_ids[$fs_id]) || isAutoChecked($fee, $is_new_student);
                        $is_termly = stripos($fee['title'], 'termly') !== false;
                        $is_admission = stripos($fee['title'], 'admission') !== false;
                        $is_mandatory_flag = !empty($fee['is_mandatory']);
                    ?>
                        <?php if ($fee['fee_type'] !== $prev_type && $prev_type !== ''): ?>
                            <div style="border-top:2px solid #e9ecef;margin:4px 0;"></div>
                        <?php endif; ?>
                        <?php $prev_type = $fee['fee_type']; ?>
                        <div class="fee-item <?php echo $is_termly ? 'mandatory' : ''; ?>">
                            <input type="checkbox" name="fee_items[]" value="<?php echo htmlspecialchars($fs_id); ?>" 
                                id="fee_<?php echo htmlspecialchars($fs_id); ?>"
                                <?php echo $is_checked ? 'checked' : ''; ?>
                                <?php echo $is_termly ? 'onclick="return false;"' : ''; ?>
                                onchange="updateTotal()">
                            <div class="fee-info">
                                <div>
                                    <span class="fee-title"><?php echo htmlspecialchars($fee['title']); ?></span>
                                    <?php if ($is_termly): ?>
                                        <span class="fee-auto">Mandatory (all students)</span>
                                    <?php endif; ?>
                                    <?php if ($is_admission && !$is_termly): ?>
                                        <span class="fee-new-only">New students only</span>
                                    <?php endif; ?>
                                </div>
                                <div class="fee-meta">
                                    <?php echo htmlspecialchars($fee['fee_type'] ?? 'General'); ?>
                                    <?php if (!empty($fee['class_id'])): ?>
                                        | <?php echo htmlspecialchars($classIdToName[(int)$fee['class_id']] ?? 'All Classes'); ?>
                                    <?php else: ?>
                                        | All Classes
                                    <?php endif; ?>
                                    <?php if (empty($fee['is_mandatory'])): ?>
                                        | <span style="color:#f39c12;">Optional</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="fee-amount">GHS <?php echo number_format((float)$fee['amount'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Summary -->
                <div class="summary-bar">
                    <div>
                        <div style="font-size:12px;opacity:.85;">Bill Total</div>
                        <div class="total" id="totalDisplay">GHS <?php echo number_format($billed_total, 2); ?></div>
                    </div>
                    <button type="submit" name="save_bill" value="1" class="btn-save"><i class="fas fa-save"></i> Save Bill</button>
                </div>
            </form>

            <!-- Current Bill Items Summary -->
            <?php if (!empty($existing_items)): ?>
            <div class="bill-card">
                <h4 style="margin:0 0 12px;font-size:15px;">Current Bill Breakdown</h4>
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead><tr style="border-bottom:2px solid #e9ecef;">
                        <th style="text-align:left;padding:8px 4px;">Item</th>
                        <th style="text-align:left;padding:8px 4px;">Type</th>
                        <th style="text-align:right;padding:8px 4px;">Amount</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($existing_items as $ei): ?>
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:8px 4px;"><?php echo htmlspecialchars($ei['title']); ?></td>
                            <td style="padding:8px 4px;color:#888;"><?php echo htmlspecialchars($ei['fee_type']); ?></td>
                            <td style="padding:8px 4px;text-align:right;font-weight:600;">GHS <?php echo number_format((float)$ei['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr style="border-top:2px solid #333;">
                        <td style="padding:8px 4px;font-weight:700;">Total</td>
                        <td></td>
                        <td style="padding:8px 4px;text-align:right;font-weight:700;font-size:16px;">GHS <?php echo number_format($billed_total, 2); ?></td>
                    </tr></tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
function toggleAll(checked) {
    var boxes = document.querySelectorAll('input[name="fee_items[]"]');
    for (var i = 0; i < boxes.length; i++) {
        // Skip mandatory (locked) checkboxes
        if (boxes[i].getAttribute('onclick') === 'return false;') continue;
        boxes[i].checked = checked;
    }
    updateTotal();
}

function updateTotal() {
    var total = 0;
    var rows = document.querySelectorAll('.fee-item');
    rows.forEach(function(row) {
        var cb = row.querySelector('input[type="checkbox"]');
        var amountEl = row.querySelector('.fee-amount');
        if (cb && cb.checked && amountEl) {
            var amt = parseFloat(amountEl.textContent.replace(/[^0-9.]/g, ''));
            if (!isNaN(amt)) total += amt;
        }
    });
    document.getElementById('totalDisplay').textContent = 'GHS ' + total.toFixed(2);
}
</script>
</body>
</html>
