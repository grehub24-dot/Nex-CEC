<?php
require_once 'includes/db.php';
requireAccess('fees_debt');

$settings = fetchSettings($pdo);
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

        // Delete existing bill items linked to fee_structures (preserve custom fees)
        $stmt = $pdo->prepare("DELETE FROM student_bill_items WHERE student_id = ? AND academic_year = ? AND term = ? AND fee_structure_id IS NOT NULL");
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
            try {
                $insStmt->execute([
                    $student_id, $fs_id, $filter_year, $filter_term,
                    $af['title'], $af['amount'], $af['fee_type'] ?? 'General',
                    $is_optional ? 1 : 0,
                    $staffRow ? (int)$staffRow['id'] : null
                ]);
                $inserted++;
            } catch (Exception $e) {
                // Ignore 409 duplicate key violations (race condition — already deleted then re-inserted)
                if (strpos($e->getMessage(), '409') === false && strpos($e->getMessage(), '23505') === false) {
                    throw $e; // Re-throw non-duplicate errors
                }
            }
        }

        // --- Auto-apply Staff Child Discount ---
        $staff_discount = (float)($settings['staff_child_discount'] ?? 150.00);
        if ($staff_discount > 0) {
            $is_staff_child = false;
            $gname = trim($student['guardian_name'] ?? '');
            $gphone = trim($student['guardian_phone_primary'] ?? '');
            $gemail = trim($student['guardian_email'] ?? '');
            try {
                $stmtSc = $pdo->query("SELECT full_name, phone, email FROM staff WHERE status = 'active'");
                foreach ($stmtSc->fetchAll() as $st) {
                    $sname = trim($st['full_name'] ?? '');
                    $sphone = trim($st['phone'] ?? '');
                    $semail = trim($st['email'] ?? '');
                    if (($gname !== '' && $gname === $sname) ||
                        ($gphone !== '' && $gphone === $sphone) ||
                        ($gemail !== '' && $gemail === $semail)) {
                        $is_staff_child = true;
                        break;
                    }
                }
            } catch (Exception $e) {}
            if ($is_staff_child) {
                $checkStmt = $pdo->prepare("SELECT id FROM student_bill_items WHERE student_id = ? AND academic_year = ? AND term = ? AND title = 'Staff Child Discount'");
                $checkStmt->execute([$student_id, $filter_year, $filter_term]);
                if (!$checkStmt->fetch()) {
                    try {
                        $insStmt->execute([
                            $student_id, null, $filter_year, $filter_term,
                            'Staff Child Discount', (-1 * $staff_discount), 'Discount',
                            0, $staffRow ? (int)$staffRow['id'] : null
                        ]);
                    } catch (Exception $e) {
                        if (strpos($e->getMessage(), '409') === false && strpos($e->getMessage(), '23505') === false) {
                            throw $e;
                        }
                    }
                }
            }
        }

        $pdo->commit();
        if ($is_staff_child ?? false) {
            $message = "Bill updated: $inserted fee item(s) saved. Staff Child Discount of GHS " . number_format($staff_discount, 2) . " applied.";
        } else {
            $message = "Bill updated: $inserted fee item(s) saved.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error saving bill: " . $e->getMessage();
    }
}

// Handle custom fee updates (from either main form or breakdown form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_bill']) || isset($_POST['update_custom_fees']) || isset($_POST['add_custom_fee']))) {
    if (isset($_POST['update_custom_fees']) || isset($_POST['add_custom_fee'])) {
        validate_request_csrf();
    }
    // --- Process custom fees (update amounts & removals) ---
    if (empty($error) && isset($_POST['custom_fee_amount']) && is_array($_POST['custom_fee_amount'])) {
        try {
            foreach ($_POST['custom_fee_amount'] as $item_id => $amount) {
                $item_id = (int)$item_id;
                $amount = (float)$amount;
                // Remove if marked for deletion or amount is zero
                if (isset($_POST['custom_fee_remove'][$item_id]) || $amount <= 0) {
                    $stmt = $pdo->prepare("DELETE FROM student_bill_items WHERE id = ? AND student_id = ? AND fee_structure_id IS NULL");
                    $stmt->execute([$item_id, $student_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE student_bill_items SET amount = ? WHERE id = ? AND student_id = ? AND fee_structure_id IS NULL");
                    $stmt->execute([$amount, $item_id, $student_id]);
                }
            }
        } catch (Exception $e) {
            $error = "Error updating custom fees: " . $e->getMessage();
        }
    }

    // --- Add new custom fee ---
    if (empty($error) && isset($_POST['add_custom_fee']) && !empty(trim($_POST['new_custom_title'] ?? ''))) {
        $new_title = sanitize(trim($_POST['new_custom_title']));
        $new_amount = (float)($_POST['new_custom_amount'] ?? 0);
        $new_type = sanitize(trim($_POST['new_custom_type'] ?? 'Custom Fee'));
        if ($new_amount > 0) {
            try {
                $stmt = $pdo->prepare("INSERT INTO student_bill_items (student_id, fee_structure_id, academic_year, term, title, amount, fee_type, is_optional, created_by) VALUES (?, NULL, ?, ?, ?, ?, ?, 1, ?)");
                $user_id = $_SESSION['user_id'] ?? 0;
                $stmtStaff = $pdo->prepare("SELECT id FROM staff WHERE user_id = ?");
                $stmtStaff->execute([$user_id]);
                $staffRow = $stmtStaff->fetch();
                $stmt->execute([$student_id, $filter_year, $filter_term, $new_title, $new_amount, $new_type, $staffRow ? (int)$staffRow['id'] : null]);
                $message = ($message ? $message . ' ' : '') . "Custom fee \"$new_title\" added.";
            } catch (Exception $e) {
                $error = "Error adding custom fee: " . $e->getMessage();
            }
        }
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

// Check if any manually-added custom fees exist (for breakdown edit controls)
// Auto-applied discounts (fee_type='Discount') are excluded — they're not editable
$has_custom_fees = false;
foreach ($existing_items as $ei) {
    if (empty($ei['fee_structure_id']) && ($ei['fee_type'] ?? '') !== 'Discount') {
        $has_custom_fees = true;
        break;
    }
}

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

                <!-- Custom Fee Section -->
                <div style="background:#fef9e7;border:1px solid #f9e79f;border-radius:8px;padding:16px;margin-top:16px;">
                    <h4 style="margin:0 0 10px;font-size:14px;color:#7d6608;"><i class="fas fa-plus-circle"></i> Add Custom Fee</h4>
                    <div style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
                        <div>
                            <label style="font-size:11px;color:#888;">Title</label>
                            <input type="text" name="new_custom_title" class="form-control" style="width:180px;" placeholder="e.g. Arrears, Sports Fee">
                        </div>
                        <div>
                            <label style="font-size:11px;color:#888;">Type</label>
                            <input type="text" name="new_custom_type" class="form-control" style="width:120px;" value="Custom Fee" placeholder="Fee type">
                        </div>
                        <div>
                            <label style="font-size:11px;color:#888;">Amount (GHS)</label>
                            <input type="number" step="0.01" min="0" name="new_custom_amount" class="form-control" style="width:110px;" placeholder="0.00">
                        </div>
                        <button type="submit" name="add_custom_fee" value="1" style="padding:8px 16px;background:#e8a317;color:white;border:none;border-radius:5px;cursor:pointer;font-size:13px;font-weight:600;">
                            <i class="fas fa-plus"></i> Add Fee
                        </button>
                    </div>
                </div>

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
                <form method="POST" style="display:contents;">
                <?php csrf_field(); ?>
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead><tr style="border-bottom:2px solid #e9ecef;">
                        <th style="text-align:left;padding:8px 4px;">Item</th>
                        <th style="text-align:left;padding:8px 4px;">Type</th>
                        <th style="text-align:right;padding:8px 4px;">Amount</th>
                        <?php if ($has_custom_fees): ?>
                        <th style="text-align:center;padding:8px 4px;width:50px;">Remove</th>
                        <?php endif; ?>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($existing_items as $ei): 
                        $is_custom = empty($ei['fee_structure_id']);
                        // Auto-applied discounts (Staff Child, Sibling) have fee_structure_id=NULL
                        // but are NOT manually added custom fees — differentiate by fee_type
                        $is_auto_discount = $is_custom && ($ei['fee_type'] === 'Discount');
                        $show_as_custom = $is_custom && !$is_auto_discount;
                    ?>
                        <tr style="border-bottom:1px solid #f0f0f0;<?php echo $show_as_custom ? 'background:#fef9e7;' : ($is_auto_discount ? 'background:#f4ecf7;' : ''); ?>">
                            <td style="padding:8px 4px;">
                                <?php echo htmlspecialchars($ei['title']); ?>
                                <?php if ($show_as_custom): ?><span style="font-size:10px;color:#e8a317;margin-left:4px;">(custom)</span><?php endif; ?>
                                <?php if ($is_auto_discount): ?><span style="font-size:10px;color:#8e44ad;margin-left:4px;">(auto)</span><?php endif; ?>
                            </td>
                            <td style="padding:8px 4px;color:#888;"><?php echo htmlspecialchars($ei['fee_type']); ?></td>
                            <td style="padding:8px 4px;text-align:right;font-weight:600;">
                                <?php if ($show_as_custom): ?>
                                    <input type="number" step="0.01" name="custom_fee_amount[<?php echo (int)$ei['id']; ?>]" value="<?php echo number_format((float)$ei['amount'], 2); ?>" style="width:90px;text-align:right;font-weight:600;padding:4px;border:1px solid #f9e79f;border-radius:4px;">
                                <?php else: ?>
                                    GHS <?php echo number_format((float)$ei['amount'], 2); ?>
                                <?php endif; ?>
                            </td>
                            <?php if ($show_as_custom): ?>
                            <td style="padding:8px 4px;text-align:center;">
                                <input type="checkbox" name="custom_fee_remove[<?php echo (int)$ei['id']; ?>]" value="1" title="Remove this custom fee">
                            </td>
                            <?php elseif ($has_custom_fees): ?>
                            <td></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr style="border-top:2px solid #333;">
                        <td style="padding:8px 4px;font-weight:700;">Total</td>
                        <td></td>
                        <td style="padding:8px 4px;text-align:right;font-weight:700;font-size:16px;">GHS <?php echo number_format($billed_total, 2); ?></td>
                        <td></td>
                    </tr></tfoot>
                </table>
                <div style="margin-top:8px;text-align:right;">
                    <button type="submit" name="update_custom_fees" value="1" class="btn-save" style="padding:6px 16px;font-size:12px;"><i class="fas fa-save"></i> Update Custom Fees</button>
                </div>
                </form>
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
