<?php
require_once 'includes/db.php';
requireAccess('fees_debt');

$settings = fetchSettings($pdo);
$school_name = $settings['school_name'] ?? 'Nex CEC';
$current_year = $settings['current_academic_year'] ?? date('Y') . '/' . (date('Y') + 1);
$current_term = $settings['current_term'] ?? '1';

$user_id = $_SESSION['user_id'];

// Fetch staff record
$stmt = $pdo->prepare("SELECT * FROM staff WHERE user_id = ?");
$stmt->execute([$user_id]);
$staff = $stmt->fetch();
if (!$staff) { redirect('../logout.php'); }

// Get teacher's assigned class IDs (via subjects table)
$teacher_class_ids = getTeacherClassIds($pdo);

// Fetch classes (only teacher's classes)
$all_classes = $pdo->query("SELECT * FROM classes")->fetchAll();
usort($all_classes, fn($a, $b) => ((int)($a['sort_order'] ?? 0)) - ((int)($b['sort_order'] ?? 0)));
$classIdToName = [];
foreach ($all_classes as $c) { $classIdToName[(int)$c['id']] = $c['name']; }

// Get the class names the teacher can view
$teacher_class_names = [];
foreach ($teacher_class_ids as $tcid) {
    $n = $classIdToName[$tcid] ?? '';
    if ($n) $teacher_class_names[] = $n;
}
$teacher_class_names = array_unique($teacher_class_names);

// Filters
$filter_year = isset($_GET['year']) ? sanitize($_GET['year']) : $current_year;
$filter_term = isset($_GET['term']) ? sanitize($_GET['term']) : $current_term;
$filter_status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 100;
$offset = ($page - 1) * $per_page;

// Fetch fee_structures for selected year/term
$all_fees = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM fee_structures WHERE academic_year = ? AND term = ?");
    $stmt->execute([$filter_year, $filter_term]);
    $all_fees = $stmt->fetchAll();
} catch (Exception $e) { $all_fees = []; }

// Group fee_structures by class name
$fees_by_class = [];
$all_class_fees = [];
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

// Count total students (for pagination)
$total_students = 0;
$all_students = [];
if (!empty($teacher_class_names)) {
    try {
        $placeholders = implode(',', array_fill(0, count($teacher_class_names), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE status = 'active' AND class_name IN ($placeholders)");
        $stmt->execute($teacher_class_names);
        $total_students = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM students WHERE status = 'active' AND class_name IN ($placeholders) ORDER BY class_name, full_name ASC LIMIT $per_page OFFSET $offset");
        $stmt->execute($teacher_class_names);
        $all_students = $stmt->fetchAll();
    } catch (Exception $e) { $all_students = []; }
}

$student_ids = array_map(fn($s) => (int)$s['id'], $all_students);

// Fetch payments
$all_payments = [];
if (!empty($student_ids)) {
    try {
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id IN ($placeholders) AND academic_year = ? AND term = ? AND status = 'completed'");
        $stmt->execute(array_merge($student_ids, [$filter_year, $filter_term]));
        $all_payments = $stmt->fetchAll();
    } catch (Exception $e) { $all_payments = []; }
}

$payments_by_student = [];
foreach ($all_payments as $p) {
    $sid = (int)$p['student_id'];
    if (!isset($payments_by_student[$sid])) $payments_by_student[$sid] = [];
    $payments_by_student[$sid][] = $p;
}

// Fetch student_bill_items for this year/term (filtered by visible student_ids)
$bill_items_by_student = [];
if (!empty($student_ids)) {
    try {
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM student_bill_items WHERE academic_year = ? AND term = ? AND student_id IN ($placeholders)");
        $stmt->execute(array_merge([$filter_year, $filter_term], $student_ids));
        foreach ($stmt->fetchAll() as $bi) {
            $sid = (int)$bi['student_id'];
            if (!isset($bill_items_by_student[$sid])) $bill_items_by_student[$sid] = [];
            $bill_items_by_student[$sid][] = $bi;
        }
    } catch (Exception $e) { $bill_items_by_student = []; }
}

// Fetch exemptions (filtered by student_ids)
$exemptions = [];
if (!empty($student_ids)) {
    try {
        $placeholders_es = implode(',', array_fill(0, count($student_ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM fee_exemptions WHERE academic_year = ? AND (term = ? OR term IS NULL) AND student_id IN ($placeholders_es)");
        $stmt->execute(array_merge([$filter_year, $filter_term], $student_ids));
        foreach ($stmt->fetchAll() as $e) {
            $exemptions[(int)$e['student_id']] = $e;
        }
    } catch (Exception $e) {}
}

// Unread messages (cached in session to avoid 4+ queries per page load)
if (!isset($_SESSION['unread_cache']) || $_SESSION['unread_cache']['expires'] < time()) {
    $stmt = $pdo->prepare("SELECT id FROM messages WHERE receiver_id = ?");
    $stmt->execute([$user_id]);
    $direct_ids = array_map(fn($r) => (int)$r['id'], $stmt->fetchAll());
    $stmt = $pdo->prepare("SELECT id FROM messages WHERE is_broadcast = ?");
    $stmt->execute([1]);
    $broadcast_ids = array_map(fn($r) => (int)$r['id'], $stmt->fetchAll());
    $all_msg_ids = array_unique(array_merge($direct_ids, $broadcast_ids));
    $stmt = $pdo->prepare("SELECT message_id FROM message_reads WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $read_ids = array_map(fn($r) => (int)$r['message_id'], $stmt->fetchAll());
    $unread_message_ids = [];
    foreach ($all_msg_ids as $mid) {
        if (!in_array($mid, $read_ids)) {
            $unread_message_ids[] = $mid;
        }
    }
    // Remove any that have been read_at (catch read timestamp without message_reads entry)
    foreach (array_chunk($unread_message_ids, 50) as $chunk) {
        if (empty($chunk)) continue;
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("SELECT id FROM messages WHERE id IN ($ph) AND read_at IS NOT NULL");
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll() as $r) {
            $unread_message_ids = array_diff($unread_message_ids, [(int)$r['id']]);
        }
    }
    $_SESSION['unread_cache'] = [
        'count' => count($unread_message_ids),
        'expires' => time() + 60 // 60-second cache TTL
    ];
}
$unread_count = $_SESSION['unread_cache']['count'];

// Process fee status
$fee_data = [];
foreach ($all_students as $s) {
    $sid = (int)$s['id'];
    $class_name = $s['class_name'] ?? '';

    $has_bill = isset($bill_items_by_student[$sid]) && !empty($bill_items_by_student[$sid]);
    if ($has_bill) {
        $expected = array_sum(array_map(fn($bi) => (float)$bi['amount'], $bill_items_by_student[$sid]));
    } else {
        $expected = 0;
        foreach ($all_class_fees as $f) { $expected += (float)$f['amount']; }
        if ($class_name && isset($fees_by_class[$class_name])) {
            foreach ($fees_by_class[$class_name] as $f) { $expected += (float)$f['amount']; }
        }
    }

    $paid = 0;
    if (isset($payments_by_student[$sid])) {
        $paid_by_type = [];
        foreach ($payments_by_student[$sid] as $p) {
            $type = $p['fee_type'] ?? 'General';
            if (!isset($paid_by_type[$type])) $paid_by_type[$type] = 0;
            $paid_by_type[$type] += (float)$p['amount'];
        }
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

    if ($is_exempted) $status = 'exempted';
    elseif (!$has_bill) $status = 'no_bill';
    elseif ($expected <= 0) $status = 'no_fees';
    elseif ($balance <= 0) $status = 'paid';
    elseif ($paid > 0) $status = 'partial';
    else $status = 'unpaid';

    $fee_data[] = [
        'id' => $sid,
        'name' => $s['full_name'] ?? '',
        'class_name' => $class_name,
        'admission_number' => $s['admission_number'] ?? '',
        'expected' => $expected,
        'paid' => $paid,
        'balance' => $balance,
        'status' => $status,
        'has_bill' => $has_bill,
    ];
}

// Status filter
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
];

// Handle POST (SMS/Email reminders)
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validate_request_csrf();
    $student_id = (int)($_POST['student_id'] ?? 0);
    $student = current(array_filter($fee_data, fn($d) => $d['id'] === $student_id));

    if ($_POST['action'] === 'send_reminder_sms' && $student) {
        $stmt = $pdo->prepare("SELECT guardian_phone_primary FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $srow = $stmt->fetch();
        $phone = $srow['guardian_phone_primary'] ?? '';
        if ($phone) {
            require_once 'includes/SMSHelper.php';
            $sms = new SMSHelper();
            $msg = "Dear Parent, your child {$student['name']} (Class: {$student['class_name']}) has an outstanding fee balance of GHS {$student['balance']} for {$filter_year} Term {$filter_term}. Please contact the school. - {$school_name}";
            $sent = $sms->send($phone, $msg);
            $message = $sent ? "SMS reminder sent." : "SMS failed.";
        } else {
            $error = "No phone number.";
        }
    } elseif ($_POST['action'] === 'send_reminder_email' && $student) {
        $stmt = $pdo->prepare("SELECT guardian_email FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $srow = $stmt->fetch();
        $email = $srow['guardian_email'] ?? '';
        if ($email) {
            require_once 'includes/Mailer.php';
            $mailer = new Mailer();
            $subject = "Fee Reminder - {$school_name}";
            $html = "<h2>Fee Payment Reminder</h2><p>Dear Parent,</p><p>Your child <strong>{$student['name']}</strong> (Class: {$student['class_name']}) has an outstanding balance of <strong>GHS " . number_format($student['balance'], 2) . "</strong> for {$filter_year} Term {$filter_term}.</p><p>Please clear at your earliest convenience.</p><br><p>Regards,<br>{$school_name}</p>";
            $sent = $mailer->sendHTML($email, $subject, $html);
            $message = $sent ? "Email sent." : "Email failed.";
        } else {
            $error = "No email address.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Debt Report — <?php echo htmlspecialchars($school_name); ?> Staff</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Sidebar (must match other staff pages) */
        .staff-sidebar {
            width: 250px; background: #1a5276; color: white; position: fixed;
            top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 100;
        }
        .staff-sidebar .sidebar-header { padding: 25px 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .staff-sidebar .sidebar-header img.sidebar-profile-img { width: 64px; height: 64px; border-radius: 50%; background: white; padding: 3px; margin-bottom: 10px; object-fit: cover; }
        .staff-sidebar .sidebar-header h3 { font-size: 15px; margin: 0; }
        .staff-sidebar .sidebar-header p { font-size: 12px; opacity: 0.8; margin: 5px 0 0; }
        .staff-sidebar ul { list-style: none; padding: 0; margin: 0; }
        .staff-sidebar ul li { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .staff-sidebar ul li a { display: block; padding: 14px 20px; color: rgba(255,255,255,0.85); text-decoration: none; font-size: 14px; transition: all 0.2s; position: relative; }
        .staff-sidebar ul li a:hover, .staff-sidebar ul li a.active { background: rgba(255,255,255,0.1); color: white; padding-left: 25px; }
        .staff-sidebar ul li a i { width: 22px; text-align: center; margin-right: 8px; }
        .staff-sidebar .msg-count {
            position: absolute; right: 15px; top: 50%; transform: translateY(-50%);
            background: #e74c3c; color: white; padding: 1px 8px;
            border-radius: 10px; font-size: 11px; font-weight: 700; line-height: 1.5;
            min-width: 20px; text-align: center;
        }
        .hamburger-menu { display: none; position: fixed; top: 15px; left: 15px; z-index: 200;
            background: #1a5276; color: white; border: none; width: 40px; height: 40px;
            border-radius: 8px; font-size: 18px; cursor: pointer;
        }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 90; }
        .sidebar-overlay.active { display: block; }
        @media (max-width: 768px) {
            .staff-sidebar { left: -250px; transition: left 0.3s; }
            .staff-sidebar.open { left: 0; }
            .staff-main { margin-left: 0; padding: 20px; }
            .hamburger-menu { display: block; }
        }
        .staff-main { flex:1; padding:20px; margin-left:250px; }
        .stat-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:12px; margin-bottom:20px; }
        .stat-card { background:white; border-radius:10px; padding:16px; text-align:center; box-shadow:0 2px 6px rgba(0,0,0,.08); }
        .stat-card h3 { font-size:26px; margin:0 0 4px; }
        .stat-card p { color:#666; font-size:12px; text-transform:uppercase; }
        .stat-card.paid h3 { color:#2ecc71; }
        .stat-card.partial h3 { color:#f39c12; }
        .stat-card.unpaid h3 { color:#e74c3c; }
        .stat-card.exempted h3 { color:#3498db; }
        .filter-bar { display:flex; gap:12px; flex-wrap:wrap; background:white; padding:16px; border-radius:10px; margin-bottom:16px; }
        .filter-bar .form-group { margin:0; min-width:120px; flex:1; }
        .filter-bar .form-group label { font-size:12px; font-weight:600; color:#555; display:block; margin-bottom:4px; }
        .filter-bar .form-group select { width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:6px; font-size:13px; }
        .filter-bar button { padding:8px 18px; background:#1a5276; color:white; border:none; border-radius:6px; cursor:pointer; }
        .filter-bar .btn-reset { padding:8px 18px; background:#95a5a6; color:white; border:none; border-radius:6px; text-decoration:none; font-size:13px; }
        .status-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600; text-transform:uppercase; }
        .status-badge.paid { background:#d5f5e3; color:#1e8449; }
        .status-badge.partial { background:#fef9e7; color:#b7950b; }
        .status-badge.unpaid { background:#fadbd8; color:#c0392b; }
        .status-badge.exempted { background:#d6eaf8; color:#2471a3; }
        .table-wrap { overflow-x:auto; background:white; border-radius:10px; }
        table.debt-table { width:100%; border-collapse:collapse; font-size:13px; }
        table.debt-table th { background:#f8f9fa; text-align:left; padding:10px 12px; font-size:11px; text-transform:uppercase; color:#555; border-bottom:2px solid #e9ecef; }
        table.debt-table td { padding:10px 12px; border-bottom:1px solid #f0f0f0; }
        .actions-btn { padding:4px 8px; border-radius:4px; border:none; cursor:pointer; font-size:11px; margin:1px; }
        .actions-btn.sms { background:#27ae60; color:white; }
        .actions-btn.email { background:#2980b9; color:white; }
    </style>
</head>
<body>
    <?php echo renderStaffSidebar('fees_debt', $school_name, $unread_count, $staff['profile_picture'] ?? '', $staff['full_name'] ?? ''); ?>
    <div class="staff-main">
        <div class="top-bar" style="padding:0 0 12px 0;">
            <h2><i class="fas fa-file-invoice"></i> Fee Debt — My Classes</h2>
            <span style="font-size:13px;color:#666;"><?php echo htmlspecialchars(implode(', ', $teacher_class_names)); ?> | <?php echo htmlspecialchars($filter_year); ?> Term <?php echo htmlspecialchars($filter_term); ?></span>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

        <div class="stat-cards">
            <div class="stat-card"><h3><?php echo $stats['total']; ?></h3><p>My Students</p></div>
            <div class="stat-card paid"><h3><?php echo $stats['paid']; ?></h3><p>Paid in Full</p></div>
            <div class="stat-card partial"><h3><?php echo $stats['partial']; ?></h3><p>Partial</p></div>
            <div class="stat-card unpaid"><h3><?php echo $stats['unpaid']; ?></h3><p>Not Paid</p></div>
            <div class="stat-card exempted"><h3><?php echo $stats['exempted']; ?></h3><p>Exempted</p></div>
        </div>

        <form method="GET" class="filter-bar">
            <div class="form-group">
                <label>Term</label>
                <select name="term">
                    <option value="1" <?php echo $filter_term==='1'?'selected':'';?>>Term 1</option>
                    <option value="2" <?php echo $filter_term==='2'?'selected':'';?>>Term 2</option>
                    <option value="3" <?php echo $filter_term==='3'?'selected':'';?>>Term 3</option>
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                    <select name="status">
                        <option value="">All</option>
                        <option value="no_bill" <?php echo $filter_status==='no_bill'?'selected':'';?>>No Bill</option>
                        <option value="paid" <?php echo $filter_status==='paid'?'selected':'';?>>Paid</option>
                        <option value="partial" <?php echo $filter_status==='partial'?'selected':'';?>>Partial</option>
                        <option value="unpaid" <?php echo $filter_status==='unpaid'?'selected':'';?>>Unpaid</option>
                        <option value="exempted" <?php echo $filter_status==='exempted'?'selected':'';?>>Exempted</option>
                    </select>
            </div>
            <button type="submit"><i class="fas fa-filter"></i> Filter</button>
            <a href="fees_debt.php" class="btn-reset">Reset</a>
        </form>

        <div class="table-wrap">
            <table class="debt-table">
                <thead><tr>
                    <th>#</th><th>Student</th><th>Class</th>
                    <th>Expected</th><th>Paid</th><th>Balance</th><th>Status</th>
                    <th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if (empty($fee_data)): ?>
                    <tr><td colspan="8" style="text-align:center;padding:30px;color:#999;">No students found.</td></tr>
                <?php else: $i=0; foreach ($fee_data as $d): $i++; ?>
                    <tr>
                        <td><?php echo $i; ?></td>
                        <td><strong><?php echo htmlspecialchars($d['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($d['class_name']); ?></td>
                        <td>GHS <?php echo number_format($d['expected'],2); ?>
                            <?php if ($d['has_bill']): ?>
                                <span style="display:inline-block;background:#d6eaf8;color:#2471a3;font-size:9px;padding:1px 4px;border-radius:4px;margin-left:2px;font-weight:600;">bill</span>
                            <?php endif; ?>
                        </td>
                        <td>GHS <?php echo number_format($d['paid'],2); ?></td>
                        <td><strong style="color:<?php echo $d['balance']>0?'#e74c3c':'#2ecc71';?>;">GHS <?php echo number_format($d['balance'],2); ?></strong></td>
                        <td>
                            <?php if ($d['status']==='paid'): ?><span class="status-badge paid">Paid</span>
                            <?php elseif ($d['status']==='partial'): ?><span class="status-badge partial">Partial</span>
                            <?php elseif ($d['status']==='unpaid'): ?><span class="status-badge unpaid">Not Paid</span>
                            <?php elseif ($d['status']==='exempted'): ?><span class="status-badge exempted">Exempted</span>
                            <?php elseif ($d['status']==='no_bill'): ?><span class="status-badge" style="background:#fef9e7;color:#b7950b;border:1px solid #f9e79f;">No Bill</span>
                            <?php else: ?><span class="status-badge" style="background:#eee;color:#999;">No Fees</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Send SMS?');">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="send_reminder_sms">
                                <input type="hidden" name="student_id" value="<?php echo $d['id']; ?>">
                                <button type="submit" class="actions-btn sms"><i class="fas fa-sms"></i></button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Send Email?');">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="send_reminder_email">
                                <input type="hidden" name="student_id" value="<?php echo $d['id']; ?>">
                                <button type="submit" class="actions-btn email"><i class="fas fa-envelope"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php $total_pages = ceil($total_students / $per_page); if ($total_pages > 1): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;font-size:13px;">
            <span style="color:#666;">Page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_students; ?> students)</span>
            <div style="display:flex;gap:6px;">
                <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" style="padding:6px 12px;background:#f8f9fa;border:1px solid #ddd;border-radius:4px;text-decoration:none;color:#333;">&laquo; Prev</a>
                <?php endif; ?>
                <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>" style="padding:6px 12px;background:<?php echo $p === $page ? '#1a5276' : '#f8f9fa'; ?>;border:1px solid <?php echo $p === $page ? '#1a5276' : '#ddd'; ?>;border-radius:4px;text-decoration:none;color:<?php echo $p === $page ? '#fff' : '#333'; ?>;font-weight:<?php echo $p === $page ? '700' : '400'; ?>;"><?php echo $p; ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" style="padding:6px 12px;background:#f8f9fa;border:1px solid #ddd;border-radius:4px;text-decoration:none;color:#333;">Next &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>
