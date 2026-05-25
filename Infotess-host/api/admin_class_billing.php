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

    // --- Staff child detection ---
    $staff_map = []; // Maps staff full_name => true and staff phone => true
    try {
        $stmt = $pdo->query("SELECT full_name, phone, email FROM staff WHERE status = 'active'");
        $active_staff = $stmt->fetchAll();
        foreach ($active_staff as $st) {
            $name = trim($st['full_name'] ?? '');
            $phone = trim($st['phone'] ?? '');
            if ($name !== '') $staff_map[$name] = true;
            if ($phone !== '') $staff_map[$phone] = true;
        }
    } catch (Exception $e) {}

    // --- Sibling grouping (by guardian phone/email) ---
    // Count students per guardian in this class, mark 3rd+
    $guardian_groups = []; // [guardian_key => [student_ids...]]
    $sibling_student_ids = []; // student_id => true for 3rd+ child
    foreach ($students_in_class as $s) {
        $key = trim($s['guardian_phone_primary'] ?? '') ?: trim($s['guardian_email'] ?? '');
        if ($key === '') continue;
        if (!isset($guardian_groups[$key])) $guardian_groups[$key] = [];
        $guardian_groups[$key][] = (int)$s['id'];
    }
    foreach ($guardian_groups as $gkey => $sids) {
        if (count($sids) >= 3) {
            // Sort by student name to keep order deterministic
            $sid_name_map = [];
            foreach ($students_in_class as $s) {
                if (in_array((int)$s['id'], $sids)) {
                    $sid_name_map[(int)$s['id']] = $s['full_name'] ?? '';
                }
            }
            asort($sid_name_map);
            $sorted_sids = array_keys($sid_name_map);
            // 3rd+ children get sibling discount
            for ($i = 2; $i < count($sorted_sids); $i++) {
                $sibling_student_ids[$sorted_sids[$i]] = true;
            }
        }
    }

    // --- Arrears: sum of bill items from previous years/terms minus payments ---
    $arrears_map = []; // student_id => arrears amount
    if (!empty($students_in_class)) {
        $sid_list = array_map(fn($s) => (int)$s['id'], $students_in_class);
        $ph = implode(',', array_fill(0, count($sid_list), '?'));
        try {
            // Fetch bill items not in current year/term
            $stmt = $pdo->prepare("SELECT student_id, amount FROM student_bill_items 
                WHERE student_id IN ($ph) 
                AND (academic_year != ? OR term != ?)");
            $stmt->execute(array_merge($sid_list, [$filter_year, $filter_term]));
            foreach ($stmt->fetchAll() as $bt) {
                $sid = (int)$bt['student_id'];
                if (!isset($arrears_map[$sid])) $arrears_map[$sid] = 0;
                $arrears_map[$sid] += (float)($bt['amount'] ?? 0);
            }
        } catch (Exception $e) {
            error_log("admin_class_billing: arrears bill_items query failed: " . $e->getMessage());
        }
        try {
            // Subtract payments made for those previous periods
            // payments table: student_id, academic_year, term, amount_paid
            $stmt = $pdo->prepare("SELECT student_id, amount_paid FROM payments 
                WHERE student_id IN ($ph) 
                AND (academic_year != ? OR term != ?)");
            $stmt->execute(array_merge($sid_list, [$filter_year, $filter_term]));
            foreach ($stmt->fetchAll() as $pmt) {
                $sid = (int)$pmt['student_id'];
                if (isset($arrears_map[$sid])) {
                    $arrears_map[$sid] -= (float)($pmt['amount_paid'] ?? 0);
                }
            }
        } catch (Exception $e) {
            error_log("admin_class_billing: arrears payments query failed: " . $e->getMessage());
        }
        // Floor arrears at 0 (no negative arrears)
        foreach ($arrears_map as $sid => $amt) {
            if ($amt < 0) $arrears_map[$sid] = 0;
        }
    }
}

// Extract discount settings
$staff_discount = (float)($settings['staff_child_discount'] ?? 150.00);
$sibling_discount = (float)($settings['sibling_discount_amount'] ?? 150.00);

// Build per-student flags
$is_staff_child = []; // student_id => true
foreach ($students_in_class as $s) {
    $gname = trim($s['guardian_name'] ?? '');
    $gphone = trim($s['guardian_phone_primary'] ?? '');
    $gemail = trim($s['guardian_email'] ?? '');
    if (isset($staff_map[$gname]) || isset($staff_map[$gphone]) || isset($staff_map[$gemail])) {
        $is_staff_child[(int)$s['id']] = true;
    }
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

    // Rebuild discount flags with fresh student data
    $is_staff_child = [];
    foreach ($students_in_class as $s) {
        $sid = (int)$s['id'];
        $gname = trim($s['guardian_name'] ?? '');
        $gphone = trim($s['guardian_phone_primary'] ?? '');
        $gemail = trim($s['guardian_email'] ?? '');
        if (isset($staff_map[$gname]) || isset($staff_map[$gphone]) || isset($staff_map[$gemail])) {
            $is_staff_child[$sid] = true;
        }
    }
    $guardian_groups = [];
    $sibling_student_ids = [];
    foreach ($students_in_class as $s) {
        $key = trim($s['guardian_phone_primary'] ?? '') ?: trim($s['guardian_email'] ?? '');
        if ($key === '') continue;
        if (!isset($guardian_groups[$key])) $guardian_groups[$key] = [];
        $guardian_groups[$key][] = (int)$s['id'];
    }
    foreach ($guardian_groups as $gkey => $sids) {
        if (count($sids) >= 3) {
            $sid_name_map = [];
            foreach ($students_in_class as $s) {
                if (in_array((int)$s['id'], $sids)) {
                    $sid_name_map[(int)$s['id']] = $s['full_name'] ?? '';
                }
            }
            asort($sid_name_map);
            $sorted_sids = array_keys($sid_name_map);
            for ($i = 2; $i < count($sorted_sids); $i++) {
                $sibling_student_ids[$sorted_sids[$i]] = true;
            }
        }
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
                try {
                    $insStmt->execute([
                        $sid, $fs_id, $filter_year, $filter_term,
                        $af['title'], $af['amount'], $af['fee_type'] ?? 'General',
                        $is_optional ? 1 : 0,
                        $staff_id
                    ]);
                } catch (Exception $e) {
                    // Ignore 409 duplicate key violations (race condition — already deleted then re-inserted)
                    if (strpos($e->getMessage(), '409') === false && strpos($e->getMessage(), '23505') === false) {
                        throw $e; // Re-throw non-duplicate errors
                    }
                }
            }

            // --- Auto-apply Staff Child Discount ---
            if (isset($is_staff_child[$sid]) && $staff_discount > 0) {
                $discount_title = 'Staff Child Discount';
                // Check if already applied (to avoid duplicates on re-apply)
                $checkStmt = $pdo->prepare("SELECT id FROM student_bill_items WHERE student_id = ? AND academic_year = ? AND term = ? AND title = ?");
                $checkStmt->execute([$sid, $filter_year, $filter_term, $discount_title]);
                if (!$checkStmt->fetch()) {
                    try {
                        $insStmt->execute([
                            $sid, null, $filter_year, $filter_term,
                            $discount_title, (-1 * $staff_discount), 'Discount',
                            0, $staff_id
                        ]);
                    } catch (Exception $e) {
                        if (strpos($e->getMessage(), '409') === false && strpos($e->getMessage(), '23505') === false) throw $e;
                    }
                }
            }

            // --- Auto-apply Sibling Discount (3rd+ child) ---
            if (isset($sibling_student_ids[$sid]) && $sibling_discount > 0) {
                $discount_title = 'Sibling Discount (3rd Child)';
                $checkStmt = $pdo->prepare("SELECT id FROM student_bill_items WHERE student_id = ? AND academic_year = ? AND term = ? AND title = ?");
                $checkStmt->execute([$sid, $filter_year, $filter_term, $discount_title]);
                if (!$checkStmt->fetch()) {
                    try {
                        $insStmt->execute([
                            $sid, null, $filter_year, $filter_term,
                            $discount_title, (-1 * $sibling_discount), 'Discount',
                            0, $staff_id
                        ]);
                    } catch (Exception $e) {
                        if (strpos($e->getMessage(), '409') === false && strpos($e->getMessage(), '23505') === false) throw $e;
                    }
                }
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

// Handle custom fee billing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['custom_fee']) && $filter_class) {
    if (!isAdmin() && !empty($teacher_class_names) && !in_array($filter_class, $teacher_class_names)) {
        $error = "Access denied: you are not authorized to bill this class.";
        goto render_page;
    }
    validate_request_csrf();
    $cf_title = trim(sanitize($_POST['custom_fee_title'] ?? ''));
    $cf_amount = (float)($_POST['custom_fee_amount'] ?? 0);
    $cf_students = $_POST['custom_fee_students'] ?? [];
    $cf_type = trim(sanitize($_POST['custom_fee_type'] ?? 'Custom Fee'));

    if ($cf_title === '' || $cf_amount <= 0 || empty($cf_students)) {
        $error = "Please provide a fee title, amount (>0), and select at least one student.";
        goto render_page;
    }

    try {
        $pdo->beginTransaction();
        $user_id = $_SESSION['user_id'];
        $stmtStaff = $pdo->prepare("SELECT id FROM staff WHERE user_id = ?");
        $stmtStaff->execute([$user_id]);
        $staffRow = $stmtStaff->fetch();
        $staff_id = $staffRow ? (int)$staffRow['id'] : null;

        $insStmt = $pdo->prepare("INSERT INTO student_bill_items (student_id, fee_structure_id, academic_year, term, title, amount, fee_type, is_optional, created_by) VALUES (?, NULL, ?, ?, ?, ?, ?, 1, ?)");
        $applied = 0;
        foreach ($cf_students as $cfsid) {
            $cfsid_int = (int)$cfsid;
            if ($cfsid_int <= 0) continue;
            try {
                $insStmt->execute([$cfsid_int, $filter_year, $filter_term, $cf_title, $cf_amount, $cf_type, $staff_id]);
                $applied++;
            } catch (Exception $e) {
                if (strpos($e->getMessage(), '409') === false && strpos($e->getMessage(), '23505') === false) throw $e;
            }
        }
        $pdo->commit();
        $message = "Custom fee \"$cf_title\" (GHS " . number_format($cf_amount, 2) . ") applied to $applied student(s).";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error applying custom fee: " . $e->getMessage();
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
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 4px; }
        .form-group select, .form-group input { width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; box-sizing: border-box; }
        .btn-secondary { display: inline-flex; align-items: center; gap: 4px; cursor: pointer; font-size: 12px; }
        .btn-secondary:hover { background: #d5dbdb !important; }
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
                    <h4 style="margin:0 0 12px;">Students <span style="font-weight:400;color:#888;font-size:13px;">(bill status & discounts)</span></h4>
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:13px;">
                            <thead><tr style="border-bottom:2px solid #e9ecef;">
                                <th style="text-align:left;padding:8px 6px;">#</th>
                                <th style="text-align:left;padding:8px 6px;">Student</th>
                                <th style="text-align:center;padding:8px 6px;">Admitted</th>
                                <th style="text-align:center;padding:8px 6px;">Status</th>
                                <th style="text-align:right;padding:8px 6px;">Bill Total</th>
                                <th style="text-align:right;padding:8px 6px;">Arrears</th>
                                <th style="text-align:center;padding:8px 6px;">Custom Fee</th>
                                <th style="text-align:center;padding:8px 6px;">Actions</th>
                            </tr></thead>
                            <tbody>
                            <?php 
                            // Pre-fetch bill totals for all students
                            // Bridge doesn't support SUM() — fetch rows, sum in PHP
                            $sid_list = array_map(fn($s) => (int)$s['id'], $students_in_class);
                            $bill_totals = [];
                            if (!empty($sid_list)) {
                                $ph = implode(',', array_fill(0, count($sid_list), '?'));
                                try {
                                    $stmt = $pdo->prepare("SELECT student_id, amount FROM student_bill_items WHERE student_id IN ($ph) AND academic_year = ? AND term = ?");
                                    $stmt->execute(array_merge($sid_list, [$filter_year, $filter_term]));
                                    foreach ($stmt->fetchAll() as $bt) {
                                        $sid = (int)$bt['student_id'];
                                        if (!isset($bill_totals[$sid])) $bill_totals[$sid] = 0;
                                        $bill_totals[$sid] += (float)($bt['amount'] ?? 0);
                                    }
                                } catch (Exception $e) {
                                    error_log("admin_class_billing: bill_totals query failed: " . $e->getMessage());
                                }
                            }
                            $i = 0; 
                            foreach ($students_in_class as $s): $i++; 
                                $sid = (int)$s['id'];
                                $is_new = ($s['academic_year'] ?? '') === $filter_year && (string)($s['admission_term'] ?? '1') === (string)$filter_term;
                                $bt = $bill_totals[$sid] ?? 0;
                                $arr = $arrears_map[$sid] ?? 0;
                                $staff_flag = isset($is_staff_child[$sid]);
                                $sibling_flag = isset($sibling_student_ids[$sid]);
                            ?>
                                <tr style="border-bottom:1px solid #f0f0f0;">
                                    <td style="padding:8px 6px;"><?php echo $i; ?></td>
                                    <td style="padding:8px 6px;">
                                        <strong><?php echo htmlspecialchars($s['full_name'] ?? ''); ?></strong>
                                        <?php if ($staff_flag): ?>
                                            <br><span style="display:inline-block;background:#f4ecf7;color:#8e44ad;padding:1px 6px;border-radius:8px;font-size:10px;font-weight:600;margin-top:2px;"><i class="fas fa-id-badge"></i> Staff Child</span>
                                        <?php endif; ?>
                                        <?php if ($sibling_flag): ?>
                                            <br><span style="display:inline-block;background:#fef9e7;color:#b7950b;padding:1px 6px;border-radius:8px;font-size:10px;font-weight:600;margin-top:2px;"><i class="fas fa-users"></i> 3rd Child</span>
                                        <?php endif; ?>
                                    </td>
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
                                    <td style="padding:8px 6px;text-align:right;font-weight:600;color:<?php echo $arr > 0 ? '#e74c3c' : '#27ae60'; ?>;">
                                        <?php if ($arr > 0): ?>
                                            GHS <?php echo number_format($arr, 2); ?>
                                        <?php else: ?>
                                            <span style="color:#bbb;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:8px 6px;text-align:center;">
                                        <input type="checkbox" name="custom_fee_students[]" value="<?php echo $sid; ?>" form="customFeeForm" style="width:16px;height:16px;cursor:pointer;">
                                    </td>
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

                <!-- Custom Fee Form -->
                <div class="card" id="customFeeSection">
                    <h4 style="margin:0 0 12px;"><i class="fas fa-plus-circle" style="color:#f39c12;"></i> Apply Custom Fee <span style="font-weight:400;color:#888;font-size:13px;">(per-student or bulk)</span></h4>
                    <form method="POST" id="customFeeForm">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="custom_fee" value="1">
                        <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($filter_class); ?>">
                        <input type="hidden" name="year" value="<?php echo htmlspecialchars($filter_year); ?>">
                        <input type="hidden" name="term" value="<?php echo htmlspecialchars($filter_term); ?>">
                        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;">
                            <div class="form-group" style="flex:2;min-width:180px;">
                                <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Fee Title</label>
                                <input type="text" name="custom_fee_title" class="form-control" required placeholder="e.g. Excursion Fee" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                            </div>
                            <div class="form-group" style="flex:1;min-width:100px;">
                                <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Amount (GHS)</label>
                                <input type="number" step="0.01" name="custom_fee_amount" class="form-control" required min="0.01" placeholder="0.00" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                            </div>
                            <div class="form-group" style="flex:1;min-width:120px;">
                                <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Fee Type</label>
                                <select name="custom_fee_type" class="form-control" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                                    <option value="Custom Fee">Custom Fee</option>
                                    <option value="Tuition">Tuition</option>
                                    <option value="PTA Levy">PTA Levy</option>
                                    <option value="Sports & Culture">Sports & Culture</option>
                                    <option value="ICT">ICT</option>
                                    <option value="Examination">Examination</option>
                                    <option value="Development">Development</option>
                                    <option value="Feeding">Feeding</option>
                                    <option value="Transport">Transport</option>
                                    <option value="Uniform">Uniform</option>
                                    <option value="Books & Materials">Books & Materials</option>
                                </select>
                            </div>
                            <div class="form-group" style="flex:0;">
                                <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">&nbsp;</label>
                                <button type="button" onclick="selectAllCustomFee(true)" class="btn-secondary" style="padding:8px 12px;background:#ecf0f1;border:1px solid #ddd;border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-check-double"></i> Select All</button>
                                <button type="button" onclick="selectAllCustomFee(false)" class="btn-secondary" style="padding:8px 12px;background:#ecf0f1;border:1px solid #ddd;border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-times"></i> Clear</button>
                            </div>
                        </div>
                        <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
                            <button type="submit" class="btn-warning" onclick="return confirm('Apply this custom fee to the selected students?');"><i class="fas fa-plus"></i> Apply Custom Fee to Selected</button>
                            <button type="submit" class="btn-primary" onclick="document.querySelectorAll('input[name=\'custom_fee_students[]\']').forEach(cb=>cb.checked=true);return confirm('Apply this custom fee to ALL <?php echo count($students_in_class); ?> students?');"><i class="fas fa-users"></i> Apply to All Students</button>
                        </div>
                        <p style="font-size:11px;color:#999;margin:8px 0 0;"><i class="fas fa-info-circle"></i> Check students individually in the table (Custom Fee column), or use Select All then choose per-student or bulk.</p>
                    </form>
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

function selectAllCustomFee(checked) {
    var boxes = document.querySelectorAll('input[name="custom_fee_students[]"]');
    for (var i = 0; i < boxes.length; i++) {
        boxes[i].checked = checked;
    }
}
</script>
</body>
</html>
