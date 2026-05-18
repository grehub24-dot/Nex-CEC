<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn() || !isParentOrDual()) {
    redirect('../login.php');
}

$parent_user_id = $_SESSION['user_id'];
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$student_id) {
    redirect('parent/dashboard.php');
}

// Verify ownership
try {
    $stmt = $pdo->prepare("SELECT student_id FROM parent_students WHERE parent_user_id = ? AND student_id = ?");
    $stmt->execute([$parent_user_id, $student_id]);
    if (!$stmt->fetch()) {
        redirect('parent/dashboard.php');
    }
} catch (Exception $e) {
    redirect('parent/dashboard.php');
}

// Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

// Fetch unread message count for sidebar badge
$unread_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM messages m WHERE (m.receiver_id = ? OR m.is_broadcast = 1) AND NOT EXISTS (SELECT 1 FROM message_reads mr WHERE mr.message_id = m.id AND mr.user_id = ?)");
    $stmt->execute([$parent_user_id, $parent_user_id]);
    $row = $stmt->fetch();
    $unread_count = (int)($row['cnt'] ?? 0);
} catch (Exception $e) {
    error_log("Unread count error: " . $e->getMessage());
}

// Fetch parent profile picture for sidebar
$parent_profile_pic = null;
try {
    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->execute([$parent_user_id]);
    $row = $stmt->fetch();
    if ($row && !empty($row['profile_picture'])) {
        $parent_profile_pic = $row['profile_picture'];
    }
} catch (Exception $e) {}

// Fetch student
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();
if (!$student) redirect('parent/dashboard.php');

// Fetch all payments for this student
$payments = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? ORDER BY payment_date DESC");
    $stmt->execute([$student_id]);
    $payments = $stmt->fetchAll();
} catch (Exception $e) {}
$total_paid = array_sum(array_map(fn($p) => (float)($p['amount'] ?? 0), $payments));

// Fetch fee structure for the student's class (two-step: bridge cannot handle subqueries)
$fee_structure = [];
try {
    // Step 1: Get class id
    $class_id = 0;
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE name = ?");
    $stmt->execute([$student['class_name']]);
    $class_row = $stmt->fetch();
    if ($class_row) {
        $class_id = (int)$class_row['id'];
    }
    // Step 2: Fetch fee structures for that class
    if ($class_id) {
        $stmt = $pdo->prepare("SELECT * FROM fee_structures WHERE class_id = ?");
        $stmt->execute([$class_id]);
        $fee_structure = $stmt->fetchAll();
    }
} catch (Exception $e) {}
$total_expected = array_sum(array_map(fn($f) => (float)($f['amount'] ?? 0), $fee_structure));
$outstanding = max(0, $total_expected - $total_paid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Statement — <?php echo htmlspecialchars($student['full_name'] ?? ''); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; color: #333; }
        .parent-container { display: flex; min-height: 100vh; }
        .parent-main { flex: 1; padding: 30px; background: #f4f6f9; margin-left: 250px; }
        .parent-sidebar {
            width: 250px; background: #1a5276; color: white; position: fixed;
            top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 100;
        }
        .parent-sidebar .sidebar-header { padding: 25px 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .parent-sidebar .sidebar-header img { width: 64px; height: 64px; border-radius: 50%; background: white; padding: 3px; margin-bottom: 10px; }
        .parent-sidebar .sidebar-header h3 { font-size: 15px; margin: 0; }
        .parent-sidebar .sidebar-header p { font-size: 12px; opacity: 0.8; margin: 5px 0 0; }
        .parent-sidebar ul { list-style: none; padding: 0; margin: 0; }
        .parent-sidebar ul li { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .parent-sidebar ul li a {
            display: block; padding: 14px 20px; color: rgba(255,255,255,0.85); text-decoration: none;
            font-size: 14px; transition: all 0.2s; position: relative;
        }
        .parent-sidebar ul li a:hover, .parent-sidebar ul li a.active { background: rgba(255,255,255,0.1); color: white; padding-left: 25px; }
        .parent-sidebar ul li a i { width: 22px; text-align: center; margin-right: 8px; }
        .parent-sidebar .msg-count {
            position: absolute; right: 15px; top: 50%; transform: translateY(-50%);
            background: #e74c3c; color: white; padding: 1px 8px;
            border-radius: 10px; font-size: 11px; font-weight: 700; line-height: 1.5;
            min-width: 20px; text-align: center;
        }
        .hamburger-menu { display: none; position: fixed; top: 15px; left: 15px; z-index: 200;
            background: #1a5276; color: white; border: none; width: 40px; height: 40px;
            border-radius: 8px; font-size: 18px; cursor: pointer;
        }
        @media (max-width: 768px) {
            .parent-sidebar { left: -250px; transition: left 0.3s; }
            .parent-sidebar.open { left: 0; }
            .parent-main { margin-left: 0; padding: 20px; }
            .hamburger-menu { display: block; }
        }
        .parent-main-container { max-width: 900px; margin: 0 auto; }
        .card {
            background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 25px; margin-bottom: 25px;
        }
        .card h3 {
            font-size: 16px; color: #1a5276; margin-bottom: 18px;
            padding-bottom: 10px; border-bottom: 2px solid #1a5276;
        }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px; }
        .summary-item {
            background: white; border-radius: 10px; padding: 20px; text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .summary-item .num { font-size: 26px; font-weight: bold; }
        .summary-item .lbl { font-size: 13px; color: #888; margin-top: 5px; }
        .summary-item .num.green { color: #27ae60; }
        .summary-item .num.red { color: #e74c3c; }
        .summary-item .num.blue { color: #1a5276; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        table th { background: #f8f9fa; color: #555; font-weight: 600; font-size: 13px; }
        .btn {
            display: inline-block; padding: 10px 20px; background: #1a5276;
            color: white; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 600;
        }
        .btn:hover { background: #143c58; }
        .btn-green { background: #27ae60; }
        .btn-green:hover { background: #1e8449; }
        @media (max-width: 600px) {
            .summary-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="parent-container">
    <?php
    $profile_pic_path = $parent_profile_pic ? '../' . htmlspecialchars($parent_profile_pic) : '';
    echo renderParentSidebar('fees', $school_name, $unread_count, $profile_pic_path, !empty($_SESSION['has_children']));
    ?>
    <div class="parent-main">
    <div style="max-width: 900px; margin: 0 auto;">
        <div style="margin-bottom: 20px;">
            <a href="../parent/student.php?id=<?php echo $student_id; ?>" class="btn"><i class="fas fa-eye"></i> View Profile</a>
        </div>

        <!-- Summary -->
        <div class="summary-grid">
            <div class="summary-item">
                <div class="num blue">GHS <?php echo number_format($total_expected, 2); ?></div>
                <div class="lbl">Total Fees Expected</div>
            </div>
            <div class="summary-item">
                <div class="num green">GHS <?php echo number_format($total_paid, 2); ?></div>
                <div class="lbl">Total Paid</div>
            </div>
            <div class="summary-item">
                <div class="num red">GHS <?php echo number_format($outstanding, 2); ?></div>
                <div class="lbl">Outstanding Balance</div>
            </div>
        </div>

        <!-- Fee Structure -->
        <?php if (!empty($fee_structure)): ?>
        <div class="card">
            <h3><i class="fas fa-list-alt"></i> Fee Structure — <?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></h3>
            <table>
                <thead>
                    <tr>
                        <th>Fee Type</th>
                        <th>Amount (GHS)</th>
                        <th>Term</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fee_structure as $fee): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($fee['fee_type'] ?? $fee['name'] ?? 'General'); ?></td>
                            <td><strong><?php echo number_format((float)($fee['amount'] ?? 0), 2); ?></strong></td>
                            <td><?php echo htmlspecialchars($fee['term'] ?? 'All'); ?></td>
                            <td><?php echo htmlspecialchars($fee['description'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Payment History -->
        <div class="card">
            <h3><i class="fas fa-history"></i> Payment History</h3>
            <?php if (empty($payments)): ?>
                <p style="color: #888; text-align: center; padding: 20px;">No payments recorded yet.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Receipt #</th>
                                <th>Fee Type</th>
                                <th>Method</th>
                                <th>Amount (GHS)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($p['payment_date'] ?? $p['created_at'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($p['fee_type'] ?? 'General'); ?></td>
                                    <td><?php echo htmlspecialchars($p['payment_method'] ?? 'N/A'); ?></td>
                                    <td><strong><?php echo number_format((float)($p['amount'] ?? 0), 2); ?></strong></td>
                                    <td><span style="color: #27ae60;"><?php echo htmlspecialchars($p['status'] ?? 'Completed'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 10px;">
            <a href="javascript:window.print()" class="btn btn-green"><i class="fas fa-print"></i> Print Statement</a>
            <a href="dashboard.php" class="btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>
</div>
</div>
</body>
</html>
