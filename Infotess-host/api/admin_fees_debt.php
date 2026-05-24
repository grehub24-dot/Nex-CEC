<?php
require_once 'includes/db.php';
requireAccess('fees_debt');

// Fetch settings
$settings = fetchSettings($pdo);
$school_name = $settings['school_name'] ?? 'Nex CEC';
$current_year = $settings['current_academic_year'] ?? date('Y') . '/' . (date('Y') + 1);
$current_term = $settings['current_term'] ?? '1';

// Fetch all classes
$classes = [];
try {
    $stmt = $pdo->query("SELECT * FROM classes");
    $classes = $stmt->fetchAll();
    usort($classes, fn($a, $b) => ((int)($a['sort_order'] ?? 0)) - ((int)($b['sort_order'] ?? 0)));
} catch (Exception $e) { $classes = []; }
$classIdToName = [];
foreach ($classes as $c) { $classIdToName[(int)$c['id']] = $c['name']; }

// Build class name → list of class_ids (one name may have multiple IDs)
$classNameToIds = [];
foreach ($classes as $c) {
    $n = $c['name'];
    if (!isset($classNameToIds[$n])) $classNameToIds[$n] = [];
    $classNameToIds[$n][] = (int)$c['id'];
}

// Determine teacher's class restriction
$teacher_class_ids = isTeacher() ? getTeacherClassIds($pdo) : [];

// Filters
$filter_class = isset($_GET['class']) ? sanitize($_GET['class']) : '';
$filter_year = isset($_GET['year']) ? sanitize($_GET['year']) : $current_year;
$filter_term = isset($_GET['term']) ? sanitize($_GET['term']) : $current_term;
$filter_status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Fetch fee_structures for selected year/term
$all_fees = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM fee_structures WHERE academic_year = ? AND term = ?");
    $stmt->execute([$filter_year, $filter_term]);
    $all_fees = $stmt->fetchAll();
} catch (Exception $e) { $all_fees = []; }

// Group fee_structures by resolved class name
// For each fee, resolve class_id to class name, or use 'ALL' for null
$fees_by_class = [];
$all_class_fees = []; // class_id IS NULL → applies to everyone
foreach ($all_fees as $f) {
    if (empty($f['class_id'])) {
        $all_class_fees[] = $f;
    } else {
        $cname = $classIdToName[(int)$f['class_id']] ?? null;
        if ($cname) {
            if (!isset($fees_by_class[$cname])) $fees_by_class[$cname] = [];
            $fees_by_class[$cname][] = $f;
        }
    }
}

// Fetch all students
$all_students = [];
try {
    $sql = "SELECT * FROM students WHERE status = 'active'";
    $params = [];
    if ($filter_class !== '') {
        $sql .= " AND class_name = ?";
        $params[] = $filter_class;
    }
    // Teacher restriction: only show students in their classes
    if (!empty($teacher_class_ids)) {
        $teacher_class_names = [];
        foreach ($teacher_class_ids as $tcid) {
            $n = $classIdToName[$tcid] ?? '';
            if ($n) $teacher_class_names[] = $n;
        }
        $teacher_class_names = array_unique($teacher_class_names);
        if (!empty($teacher_class_names)) {
            $placeholders = implode(',', array_fill(0, count($teacher_class_names), '?'));
            $sql .= " AND class_name IN ($placeholders)";
            $params = array_merge($params, $teacher_class_names);
        }
    }
    $sql .= " ORDER BY class_name, full_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_students = $stmt->fetchAll();
} catch (Exception $e) { $all_students = []; }

$student_ids = array_map(fn($s) => (int)$s['id'], $all_students);

// Fetch all payments for this year/term
$all_payments = [];
if (!empty($student_ids)) {
    try {
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id IN ($placeholders) AND academic_year = ? AND term = ? AND status = 'completed'");
        $stmt->execute(array_merge($student_ids, [$filter_year, $filter_term]));
        $all_payments = $stmt->fetchAll();
    } catch (Exception $e) { $all_payments = []; }
}

// Group payments by student_id
$payments_by_student = [];
foreach ($all_payments as $p) {
    $sid = (int)$p['student_id'];
    if (!isset($payments_by_student[$sid])) $payments_by_student[$sid] = [];
    $payments_by_student[$sid][] = $p;
}

// Fetch student_bill_items for this year/term
$bill_items_by_student = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM student_bill_items WHERE academic_year = ? AND term = ?");
    $stmt->execute([$filter_year, $filter_term]);
    foreach ($stmt->fetchAll() as $bi) {
        $sid = (int)$bi['student_id'];
        if (!isset($bill_items_by_student[$sid])) $bill_items_by_student[$sid] = [];
        $bill_items_by_student[$sid][] = $bi;
    }
} catch (Exception $e) { $bill_items_by_student = []; }

// Fetch fee_exemptions for this year/term
$exemptions = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM fee_exemptions WHERE academic_year = ? AND (term = ? OR term IS NULL)");
    $stmt->execute([$filter_year, $filter_term]);
    $exemptions_raw = $stmt->fetchAll();
    foreach ($exemptions_raw as $e) {
        $exemptions[(int)$e['student_id']] = $e;
    }
} catch (Exception $e) { $exemptions = []; }

// Process each student → fee status
$fee_data = [];
foreach ($all_students as $s) {
    $sid = (int)$s['id'];
    $class_name = $s['class_name'] ?? '';

    // Calculate expected fees: use bill_items if available, otherwise fee_structures
    $has_bill = isset($bill_items_by_student[$sid]) && !empty($bill_items_by_student[$sid]);
    if ($has_bill) {
        $expected = array_sum(array_map(fn($bi) => (float)$bi['amount'], $bill_items_by_student[$sid]));
    } else {
        $expected = 0;
        // All-class fees
        foreach ($all_class_fees as $f) {
            $expected += (float)$f['amount'];
        }
        // Class-specific fees
        if ($class_name && isset($fees_by_class[$class_name])) {
            foreach ($fees_by_class[$class_name] as $f) {
                $expected += (float)$f['amount'];
            }
        }
    }

    // Calculate paid amount
    $paid = 0;
    if (isset($payments_by_student[$sid])) {
        // Group by fee_type and cap per type (same as student_fees.php logic)
        $paid_by_type = [];
        foreach ($payments_by_student[$sid] as $p) {
            $type = $p['fee_type'] ?? 'General';
            if (!isset($paid_by_type[$type])) $paid_by_type[$type] = 0;
            $paid_by_type[$type] += (float)$p['amount'];
        }
        // Cap per fee type
        $type_fee_map = [];
        foreach ($all_fees as $f) {
            $ft = $f['fee_type'] ?? 'General';
            if (!isset($type_fee_map[$ft])) $type_fee_map[$ft] = 0;
            $type_fee_map[$ft] += (float)$f['amount'];
        }
        foreach ($paid_by_type as $type => $amt) {
            $cap = $type_fee_map[$type] ?? 0;
            $paid += ($cap > 0) ? min($amt, $cap) : $amt;
        }
    }

    $balance = max(0, $expected - $paid);
    $is_exempted = isset($exemptions[$sid]);
    $exemption_reason = $is_exempted ? ($exemptions[$sid]['reason'] ?? '') : '';

    // Determine status
    if ($is_exempted) {
        $status = 'exempted';
    } elseif (!$has_bill) {
        $status = 'no_bill'; // No bill created yet
    } elseif ($expected <= 0) {
        $status = 'no_fees'; // No fee structure defined
    } elseif ($balance <= 0) {
        $status = 'paid';
    } elseif ($paid > 0) {
        $status = 'partial';
    } else {
        $status = 'unpaid';
    }

    $fee_data[] = [
        'id' => $sid,
        'name' => $s['full_name'] ?? '',
        'class_name' => $class_name,
        'admission_number' => $s['admission_number'] ?? '',
        'enrollment_id' => $s['enrollment_id'] ?? '',
        'guardian_email' => $s['guardian_email'] ?? '',
        'guardian_phone' => $s['guardian_phone_primary'] ?? '',
        'expected' => $expected,
        'paid' => $paid,
        'balance' => $balance,
        'status' => $status,
        'has_bill' => $has_bill,
        'exemption_reason' => $exemption_reason,
    ];
}

// Apply status filter
if ($filter_status !== '') {
    $fee_data = array_values(array_filter($fee_data, fn($d) => $d['status'] === $filter_status));
}

// Stats
$stats = [
    'total' => count($fee_data),
    'paid' => count(array_filter($fee_data, fn($d) => $d['status'] === 'paid')),
    'partial' => count(array_filter($fee_data, fn($d) => $d['status'] === 'partial')),
    'unpaid' => count(array_filter($fee_data, fn($d) => $d['status'] === 'unpaid')),
    'exempted' => count(array_filter($fee_data, fn($d) => $d['status'] === 'exempted')),
    'no_bill' => count(array_filter($fee_data, fn($d) => $d['status'] === 'no_bill')),
    'no_fees' => count(array_filter($fee_data, fn($d) => $d['status'] === 'no_fees')),
];
$stats['total_expected'] = array_sum(array_column($fee_data, 'expected'));
$stats['total_paid'] = array_sum(array_column($fee_data, 'paid'));
$stats['total_balance'] = array_sum(array_column($fee_data, 'balance'));

// Handle exempt/unexempt via POST
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validate_request_csrf();
    $student_id = (int)($_POST['student_id'] ?? 0);

    if ($_POST['action'] === 'exempt' && $student_id) {
        $reason = sanitize($_POST['reason'] ?? 'Fee exemption');
        try {
            // Upsert: delete existing first then insert (pg-bridge friendly)
            $stmt = $pdo->prepare("DELETE FROM fee_exemptions WHERE student_id = ? AND academic_year = ? AND (term = ? OR term IS NULL)");
            $stmt->execute([$student_id, $filter_year, $filter_term]);
            $stmt = $pdo->prepare("INSERT INTO fee_exemptions (student_id, academic_year, term, reason, exempted_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$student_id, $filter_year, $filter_term, $reason, $_SESSION['user_id']]);
            $message = "Student exempted from fees.";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'unexempt' && $student_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM fee_exemptions WHERE student_id = ? AND academic_year = ? AND (term = ? OR term IS NULL)");
            $stmt->execute([$student_id, $filter_year, $filter_term]);
            $message = "Exemption removed.";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'send_reminder_sms' && $student_id) {
        $student = array_filter($fee_data, fn($d) => $d['id'] === $student_id);
        $student = reset($student);
        if ($student && $student['guardian_phone']) {
            require_once 'includes/SMSHelper.php';
            $sms = new SMSHelper();
            $msg = "Dear Parent, your child {$student['name']} (Class: {$student['class_name']}) has an outstanding fee balance of GHS {$student['balance']} for {$filter_year} Term {$filter_term}. Please clear at the accounts office. - {$school_name}";
            $sent = $sms->send($student['guardian_phone'], $msg);
            $message = $sent ? "SMS reminder sent to {$student['guardian_phone']}." : "SMS sending failed. Check SMS logs.";
        } else {
            $error = "No guardian phone number on record.";
        }
    } elseif ($_POST['action'] === 'send_reminder_email' && $student_id) {
        $student = array_filter($fee_data, fn($d) => $d['id'] === $student_id);
        $student = reset($student);
        if ($student && $student['guardian_email']) {
            require_once 'includes/Mailer.php';
            $mailer = new Mailer();
            $subject = "Fee Reminder - {$school_name}";
            $html = "<h2>Fee Payment Reminder</h2>
                <p>Dear Parent,</p>
                <p>This is a reminder that your child <strong>{$student['name']}</strong> (Class: {$student['class_name']}) has an outstanding fee balance of <strong>GHS " . number_format($student['balance'], 2) . "</strong> for {$filter_year} Term {$filter_term}.</p>
                <p>Please visit the accounts office to clear the balance at your earliest convenience.</p>
                <br><p>Regards,<br>{$school_name}</p>";
            $sent = $mailer->sendHTML($student['guardian_email'], $subject, $html);
            $message = $sent ? "Email reminder sent to {$student['guardian_email']}." : "Email sending failed.";
        } else {
            $error = "No guardian email on record.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Debt Report — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .debt-container { padding: 20px; }
        .stat-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat-card { background: white; border-radius: 10px; padding: 16px; text-align: center; box-shadow: 0 2px 6px rgba(0,0,0,.08); }
        .stat-card h3 { font-size: 28px; margin: 0 0 4px; }
        .stat-card p { color: #666; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; }
        .stat-card.paid h3 { color: #2ecc71; }
        .stat-card.partial h3 { color: #f39c12; }
        .stat-card.unpaid h3 { color: #e74c3c; }
        .stat-card.exempted h3 { color: #3498db; }
        .stat-card.total h3 { color: #2c3e50; }

        .filter-bar { display: flex; gap: 12px; flex-wrap: wrap; align-items: end; background: white; padding: 16px; border-radius: 10px; margin-bottom: 16px; box-shadow: 0 2px 6px rgba(0,0,0,.05); }
        .filter-bar .form-group { margin: 0; min-width: 140px; flex: 1; }
        .filter-bar .form-group label { font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 4px; }
        .filter-bar .form-group select,
        .filter-bar .form-group input { width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
        .filter-bar button { padding: 8px 18px; background: #1a5276; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; white-space: nowrap; }
        .filter-bar .btn-reset { background: #95a5a6; }

        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .3px; }
        .status-badge.paid { background: #d5f5e3; color: #1e8449; }
        .status-badge.partial { background: #fef9e7; color: #b7950b; }
        .status-badge.unpaid { background: #fadbd8; color: #c0392b; }
        .status-badge.exempted { background: #d6eaf8; color: #2471a3; }
        .status-badge.no-fees { background: #eaecee; color: #7f8c8d; }

        .table-wrap { overflow-x: auto; background: white; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,.05); }
        table.debt-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        table.debt-table th { background: #f8f9fa; text-align: left; padding: 10px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: .3px; color: #555; border-bottom: 2px solid #e9ecef; }
        table.debt-table td { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; }
        table.debt-table tr:hover td { background: #f8f9fa; }
        .actions-btn { padding: 4px 8px; border-radius: 4px; border: none; cursor: pointer; font-size: 11px; margin: 1px; }
        .actions-btn.sms { background: #27ae60; color: white; }
        .actions-btn.email { background: #2980b9; color: white; }
        .actions-btn.chit { background: #8e44ad; color: white; }
        .actions-btn.exempt { background: #f39c12; color: white; }
        .actions-btn.unexempt { background: #e74c3c; color: white; }

        .chit-container { display: none; }
        @media print {
            body * { visibility: hidden; }
            .chit-container, .chit-container * { visibility: visible; }
            .chit-container { display: block; position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
        }
        .chit-container { max-width: 700px; margin: 0 auto; padding: 40px; font-size: 14px; }
        .chit-container h2 { text-align: center; margin-bottom: 5px; }
        .chit-container .school-name { text-align: center; font-size: 16px; font-weight: bold; margin-bottom: 20px; }
        .chit-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .chit-table th, .chit-table td { border: 1px solid #333; padding: 8px 10px; text-align: left; }
        .chit-table th { background: #f0f0f0; }
        .chit-footer { margin-top: 30px; display: flex; justify-content: space-between; }
        .chit-footer div { border-top: 1px solid #333; padding-top: 4px; min-width: 150px; text-align: center; }
        .btn-print-chit { display: inline-block; padding: 6px 14px; background: #8e44ad; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
    </style>
</head>
<body>
<div class="dashboard-container no-print">
    <?php echo renderSidebar('fees_debt', $school_name); ?>
    <main class="main-content" id="main-content">
        <div class="top-bar">
            <h2><i class="fas fa-file-invoice"></i> Fee Debt Report</h2>
            <div>
                <span style="font-size:13px;color:#666;"><?php echo htmlspecialchars($filter_year); ?> — Term <?php echo htmlspecialchars($filter_term); ?></span>
            </div>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

        <div class="debt-container">
            <!-- Stats -->
            <div class="stat-cards">
                <div class="stat-card total"><h3><?php echo $stats['total']; ?></h3><p>Total Students</p></div>
                <div class="stat-card paid"><h3><?php echo $stats['paid']; ?></h3><p>Paid in Full</p></div>
                <div class="stat-card partial"><h3><?php echo $stats['partial']; ?></h3><p>Partial Payment</p></div>
                <div class="stat-card unpaid"><h3><?php echo $stats['unpaid']; ?></h3><p>Not Paid</p></div>
                <div class="stat-card exempted"><h3><?php echo $stats['exempted']; ?></h3><p>Exempted</p></div>
                <?php if ($stats['no_bill'] > 0): ?>
                <div class="stat-card" style="background:#fef9e7;"><h3 style="color:#b7950b;"><?php echo $stats['no_bill']; ?></h3><p>No Bill</p></div>
                <?php endif; ?>
                <?php if ($stats['no_fees'] > 0): ?>
                <div class="stat-card" style="background:#f8f9fa;"><h3 style="color:#7f8c8d;"><?php echo $stats['no_fees']; ?></h3><p>No Fee Structure</p></div>
                <?php endif; ?>
            </div>

            <!-- Financial Summary -->
            <div style="background:white;border-radius:10px;padding:14px 18px;margin-bottom:16px;box-shadow:0 2px 6px rgba(0,0,0,.05);display:flex;gap:30px;flex-wrap:wrap;">
                <div><span style="color:#666;font-size:12px;">Total Expected:</span> <strong>GHS <?php echo number_format($stats['total_expected'], 2); ?></strong></div>
                <div><span style="color:#666;font-size:12px;">Total Collected:</span> <strong style="color:#2ecc71;">GHS <?php echo number_format($stats['total_paid'], 2); ?></strong></div>
                <div><span style="color:#666;font-size:12px;">Outstanding Balance:</span> <strong style="color:#e74c3c;">GHS <?php echo number_format($stats['total_balance'], 2); ?></strong></div>
            </div>

            <!-- Filters -->
            <form method="GET" class="filter-bar">
                <div class="form-group">
                    <label>Academic Year</label>
                    <input type="text" name="year" value="<?php echo htmlspecialchars($filter_year); ?>">
                </div>
                <div class="form-group">
                    <label>Term</label>
                    <select name="term">
                        <option value="1" <?php echo $filter_term === '1' ? 'selected' : ''; ?>>Term 1</option>
                        <option value="2" <?php echo $filter_term === '2' ? 'selected' : ''; ?>>Term 2</option>
                        <option value="3" <?php echo $filter_term === '3' ? 'selected' : ''; ?>>Term 3</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Class</label>
                    <select name="class">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?php echo htmlspecialchars($cls['name']); ?>" <?php echo $filter_class === $cls['name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cls['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="no_bill" <?php echo $filter_status === 'no_bill' ? 'selected' : ''; ?>>No Bill</option>
                        <option value="paid" <?php echo $filter_status === 'paid' ? 'selected' : ''; ?>>Paid in Full</option>
                        <option value="partial" <?php echo $filter_status === 'partial' ? 'selected' : ''; ?>>Partial Payment</option>
                        <option value="unpaid" <?php echo $filter_status === 'unpaid' ? 'selected' : ''; ?>>Not Paid</option>
                        <option value="exempted" <?php echo $filter_status === 'exempted' ? 'selected' : ''; ?>>Exempted</option>
                    </select>
                </div>
                <button type="submit"><i class="fas fa-filter"></i> Filter</button>
                <a href="fees_debt.php" class="btn-reset" style="padding:8px 18px;background:#95a5a6;color:white;border:none;border-radius:6px;text-decoration:none;font-size:13px;">Reset</a>
                <a href="class_billing.php" class="btn-reset" style="padding:8px 18px;background:#1a5276;color:white;border:none;border-radius:6px;text-decoration:none;font-size:13px;display:inline-flex;align-items:center;gap:4px;"><i class="fas fa-users"></i> Bulk Bill</a>
            </form>

            <!-- Table -->
            <div class="table-wrap">
                <table class="debt-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Expected (GHS)</th>
                            <th>Paid (GHS)</th>
                            <th>Balance (GHS)</th>
                            <th>Status</th>
                            <th style="min-width:200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fee_data)): ?>
                            <tr><td colspan="8" style="text-align:center;padding:30px;color:#999;">No students found matching the filters.</td></tr>
                        <?php else: $i = 0; foreach ($fee_data as $d): $i++; ?>
                            <tr>
                                <td><?php echo $i; ?></td>
                                <td><strong><?php echo htmlspecialchars($d['name']); ?></strong><br><small style="color:#999;"><?php echo htmlspecialchars($d['admission_number'] ?: $d['enrollment_id']); ?></small></td>
                                <td><?php echo htmlspecialchars($d['class_name']); ?></td>
                                <td>GHS <?php echo number_format($d['expected'], 2); ?>
                                    <?php if ($d['has_bill']): ?>
                                        <span style="display:inline-block;background:#d6eaf8;color:#2471a3;font-size:9px;padding:1px 4px;border-radius:4px;margin-left:2px;font-weight:600;">bill</span>
                                    <?php endif; ?>
                                </td>
                                <td>GHS <?php echo number_format($d['paid'], 2); ?></td>
                                <td><strong style="color:<?php echo $d['balance'] > 0 ? '#e74c3c' : '#2ecc71'; ?>;">GHS <?php echo number_format($d['balance'], 2); ?></strong></td>
                                <td>
                                    <?php if ($d['status'] === 'paid'): ?>
                                        <span class="status-badge paid">Paid</span>
                                    <?php elseif ($d['status'] === 'partial'): ?>
                                        <span class="status-badge partial">Partial</span>
                                    <?php elseif ($d['status'] === 'unpaid'): ?>
                                        <span class="status-badge unpaid">Not Paid</span>
                                    <?php elseif ($d['status'] === 'exempted'): ?>
                                        <span class="status-badge exempted">Exempted</span>
                                        <?php if ($d['exemption_reason']): ?><br><small style="color:#3498db;"><?php echo htmlspecialchars($d['exemption_reason']); ?></small><?php endif; ?>
                                    <?php elseif ($d['status'] === 'no_bill'): ?>
                                        <span class="status-badge" style="background:#fef9e7;color:#b7950b;border:1px solid #f9e79f;">No Bill</span>
                                    <?php else: ?>
                                        <span class="status-badge no-fees">No Fees</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <!-- Bill Button -->
                                    <a href="student_billing.php?student_id=<?php echo $d['id']; ?>&year=<?php echo urlencode($filter_year); ?>&term=<?php echo urlencode($filter_term); ?>&redirect=fees_debt.php" style="text-decoration:none;">
                                        <button type="button" class="actions-btn" style="background:<?php echo $d['has_bill'] ? '#2980b9' : '#f39c12'; ?>;color:white;border:none;padding:4px 8px;border-radius:4px;cursor:pointer;font-size:11px;margin:1px;" title="<?php echo $d['has_bill'] ? 'Edit bill items' : 'Create bill for this student'; ?>">
                                            <i class="fas fa-file-invoice"></i> <?php echo $d['has_bill'] ? 'Bill' : 'Set Bill'; ?>
                                        </button>
                                    </a>
                                    <!-- SMS Reminder -->
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Send SMS reminder to guardian?');">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="send_reminder_sms">
                                        <input type="hidden" name="student_id" value="<?php echo $d['id']; ?>">
                                        <button type="submit" class="actions-btn sms" title="Send SMS reminder" <?php echo !$d['guardian_phone'] ? 'disabled style="opacity:.4;"' : ''; ?>><i class="fas fa-sms"></i> SMS</button>
                                    </form>
                                    <!-- Email Reminder -->
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Send email reminder to guardian?');">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="send_reminder_email">
                                        <input type="hidden" name="student_id" value="<?php echo $d['id']; ?>">
                                        <button type="submit" class="actions-btn email" title="Send email reminder" <?php echo !$d['guardian_email'] ? 'disabled style="opacity:.4;"' : ''; ?>><i class="fas fa-envelope"></i> Email</button>
                                    </form>
                                    <!-- Print Chit -->
                                    <button class="actions-btn chit" onclick="printChit(<?php echo $d['id']; ?>)" title="Print fee debt chit"><i class="fas fa-print"></i> Chit</button>
                                    <!-- Exempt / Unexempt -->
                                    <?php if ($d['status'] === 'exempted'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove exemption for this student?');">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="action" value="unexempt">
                                            <input type="hidden" name="student_id" value="<?php echo $d['id']; ?>">
                                            <button type="submit" class="actions-btn unexempt" title="Remove exemption"><i class="fas fa-undo"></i> Un-exempt</button>
                                        </form>
                                    <?php elseif ($d['status'] !== 'no_fees'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="var r=prompt('Exemption reason:'); if(!r)return false; this.reason.value=r;">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="action" value="exempt">
                                            <input type="hidden" name="student_id" value="<?php echo $d['id']; ?>">
                                            <input type="hidden" name="reason" value="">
                                            <button type="submit" class="actions-btn exempt" title="Exempt from fees"><i class="fas fa-shield-alt"></i> Exempt</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Printable Chit Container (hidden until print) -->
<div class="chit-container" id="chitContainer"></div>

<script>
// Student data for chit generation
var feeData = <?php echo json_encode($fee_data); ?>;

function printChit(studentId) {
    var s = feeData.find(function(d) { return d.id === studentId; });
    if (!s) return;
    var expected = parseFloat(s.expected).toFixed(2);
    var paid = parseFloat(s.paid).toFixed(2);
    var balance = parseFloat(s.balance).toFixed(2);
    var statusLabel = '';
    if (s.status === 'paid') statusLabel = 'PAID IN FULL';
    else if (s.status === 'partial') statusLabel = 'PARTIALLY PAID';
    else if (s.status === 'unpaid') statusLabel = 'NOT PAID';
    else if (s.status === 'exempted') statusLabel = 'EXEMPTED';
    else if (s.status === 'no_bill') statusLabel = 'NO BILL SET';
    else statusLabel = 'NO FEES';

    var html = '<div style="max-width:700px;margin:0 auto;padding:40px;font-size:14px;">';
    html += '<div style="text-align:center;margin-bottom:20px;">';
    html += '<h2 style="margin:0;font-size:22px;"><?php echo htmlspecialchars($school_name); ?></h2>';
    html += '<p style="margin:4px 0;color:#555;">FEE DEBT SLIP — <?php echo htmlspecialchars($filter_year); ?> Term <?php echo htmlspecialchars($filter_term); ?></p>';
    html += '</div>';
    html += '<hr style="border-top:2px solid #333;">';
    html += '<table style="width:100%;border-collapse:collapse;margin:15px 0;">';
    html += '<tr><td style="padding:4px 8px;"><strong>Student:</strong></td><td style="padding:4px 8px;">' + htmlspecialchars(s.name) + '</td>';
    html += '<td style="padding:4px 8px;"><strong>Class:</strong></td><td style="padding:4px 8px;">' + htmlspecialchars(s.class_name) + '</td></tr>';
    html += '<tr><td style="padding:4px 8px;"><strong>Admission No:</strong></td><td style="padding:4px 8px;">' + htmlspecialchars(s.admission_number || s.enrollment_id) + '</td>';
    html += '<td style="padding:4px 8px;"><strong>Status:</strong></td><td style="padding:4px 8px;"><strong>' + statusLabel + '</strong></td></tr>';
    html += '</table>';
    html += '<hr>';
    html += '<table style="width:100%;border-collapse:collapse;">';
    html += '<tr><td style="padding:8px;font-weight:bold;">Total Expected Fees:</td><td style="padding:8px;text-align:right;">GHS ' + expected + '</td></tr>';
    html += '<tr><td style="padding:8px;font-weight:bold;">Amount Paid:</td><td style="padding:8px;text-align:right;color:#2ecc71;">GHS ' + paid + '</td></tr>';
    html += '<tr><td style="padding:8px;font-weight:bold;' + (balance > 0 ? 'color:#e74c3c;' : '') + '">Outstanding Balance:</td><td style="padding:8px;text-align:right;font-weight:bold;' + (balance > 0 ? 'color:#e74c3c;' : 'color:#2ecc71;') + '">GHS ' + balance + '</td></tr>';
    html += '</table>';
    html += '<hr>';
    html += '<div style="display:flex;justify-content:space-between;margin-top:40px;">';
    html += '<div style="border-top:1px solid #333;padding-top:4px;min-width:150px;text-align:center;">Accounts Officer</div>';
    html += '<div style="border-top:1px solid #333;padding-top:4px;min-width:150px;text-align:center;">Parent / Guardian</div>';
    html += '</div>';
    html += '<p style="text-align:center;color:#999;font-size:11px;margin-top:30px;">Generated on <?php echo date('d-m-Y'); ?> | ' + htmlspecialchars(s.admission_number || s.enrollment_id) + '</p>';
    html += '</div>';

    document.getElementById('chitContainer').innerHTML = html;
    setTimeout(function() { window.print(); }, 300);
}

function htmlspecialchars(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}
</script>
</body>
</html>
