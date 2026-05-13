<?php
require_once 'includes/db.php';

if (!isLoggedIn() || !isParentOrDual()) {
    redirect('../login.php');
}

$parent_user_id = $_SESSION['user_id'];
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$student_id) {
    redirect('parent/dashboard.php');
}

// Verify this parent owns this student
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
$current_academic_year = $settings['current_academic_year'] ?? date('Y') . '/' . (date('Y') + 1);

// Fetch student
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    redirect('parent/dashboard.php');
}

// Fetch payments
$payments = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? ORDER BY payment_date DESC");
    $stmt->execute([$student_id]);
    $payments = $stmt->fetchAll();
} catch (Exception $e) {}
$total_paid = array_sum(array_map(fn($p) => (float)($p['amount'] ?? 0), $payments));

// Fetch attendance
$attendance = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM student_attendance WHERE student_id = ? ORDER BY attendance_date DESC LIMIT 20");
    $stmt->execute([$student_id]);
    $attendance = $stmt->fetchAll();
} catch (Exception $e) {}
$present_count = count(array_filter($attendance, fn($a) => ($a['status'] ?? '') === 'present'));
$absent_count = count(array_filter($attendance, fn($a) => ($a['status'] ?? '') === 'absent'));

$initial = strtoupper(substr($student['full_name'] ?? '?', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?> — Parent Portal</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; color: #333; }
        .top-bar {
            background: #1a5276; color: white; padding: 15px 30px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .top-bar a { color: white; text-decoration: none; font-size: 14px; }
        .top-bar a:hover { text-decoration: underline; }
        .container { max-width: 1000px; margin: 0 auto; padding: 30px 20px; }
        .page-header {
            background: white; border-radius: 12px; padding: 25px 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 25px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .page-header .student-info { display: flex; align-items: center; gap: 18px; }
        .page-header .avatar {
            width: 60px; height: 60px; border-radius: 50%; background: #1a5276;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 26px; font-weight: bold;
        }
        .page-header .student-info h2 { font-size: 22px; color: #1a5276; margin: 0; }
        .page-header .student-info p { font-size: 14px; color: #888; margin: 3px 0 0; }
        .status-badge { padding: 5px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; }
        .status-active { background: #e6f7e6; color: #27ae60; }
        .status-pending { background: #fff3e0; color: #f39c12; }
        .status-rejected { background: #ffe6e6; color: #e74c3c; }
        .card {
            background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 25px; margin-bottom: 25px;
        }
        .card h3 {
            font-size: 16px; color: #1a5276; margin-bottom: 18px;
            padding-bottom: 10px; border-bottom: 2px solid #1a5276;
        }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .info-grid .item { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .info-grid .item .label { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-grid .item .value { font-size: 15px; font-weight: 600; color: #333; margin-top: 2px; }
        .stat-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card {
            background: white; border-radius: 10px; padding: 20px; text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .stat-card .num { font-size: 28px; font-weight: bold; color: #1a5276; }
        .stat-card .lbl { font-size: 13px; color: #888; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        table th { background: #f8f9fa; color: #555; font-weight: 600; font-size: 13px; }
        .text-green { color: #27ae60; }
        .text-red { color: #e74c3c; }
        .btn-back {
            display: inline-block; padding: 10px 20px; background: #1a5276;
            color: white; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 600;
        }
        .btn-back:hover { background: #143c58; }
        .attendance-bar {
            display: flex; gap: 15px; align-items: center; margin-top: 10px;
        }
        .attendance-bar .bar {
            flex: 1; height: 12px; background: #f0f0f0; border-radius: 6px; overflow: hidden;
        }
        .attendance-bar .bar .fill { height: 100%; border-radius: 6px; transition: width 0.3s; }
        .attendance-bar .bar .fill.present { background: #27ae60; }
        .attendance-bar .bar .fill.absent { background: #e74c3c; }
        @media (max-width: 600px) {
            .info-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; text-align: center; gap: 10px; }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div>
            <a href="../parent/dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
        <span>Parent Portal — <?php echo htmlspecialchars($school_name); ?></span>
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="../parent/profile.php" style="color: white; font-size: 13px;" title="My Profile"><i class="fas fa-user-cog"></i></a>
            <?php if (isset($_SESSION['has_children']) && $_SESSION['has_children']): ?>
            <a href="../admin/dashboard.php" style="color: white; font-size: 13px;"><i class="fas fa-chalkboard-teacher"></i> Staff Portal</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="student-info">
                <div class="avatar"><?php echo htmlspecialchars($initial); ?></div>
                <div>
                    <h2><?php echo htmlspecialchars($student['full_name'] ?? 'Unknown'); ?></h2>
                    <p>
                        <?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?>
                        <?php if (!empty($student['admission_number'])): ?>
                            &bull; <?php echo htmlspecialchars($student['admission_number']); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <span class="status-badge status-<?php echo $student['status'] ?? 'pending'; ?>">
                <?php echo ucfirst($student['status'] ?? 'Pending'); ?>
            </span>
        </div>

        <!-- Stats -->
        <div class="stat-cards">
            <div class="stat-card">
                <div class="num">GHS <?php echo number_format($total_paid, 2); ?></div>
                <div class="lbl">Total Fees Paid</div>
            </div>
            <div class="stat-card">
                <div class="num"><?php echo count($payments); ?></div>
                <div class="lbl">Payments Made</div>
            </div>
            <div class="stat-card">
                <div class="num"><?php echo $present_count; ?>/<?php echo count($attendance); ?></div>
                <div class="lbl">Days Present</div>
            </div>
        </div>

        <!-- Student Information -->
        <div class="card">
            <h3><i class="fas fa-user"></i> Student Information</h3>
            <div class="info-grid">
                <div class="item">
                    <div class="label">Full Name</div>
                    <div class="value"><?php echo htmlspecialchars($student['full_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="item">
                    <div class="label">Date of Birth</div>
                    <div class="value"><?php echo htmlspecialchars($student['date_of_birth'] ?? 'N/A'); ?></div>
                </div>
                <div class="item">
                    <div class="label">Gender</div>
                    <div class="value"><?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></div>
                </div>
                <div class="item">
                    <div class="label">Class</div>
                    <div class="value"><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="item">
                    <div class="label">Enrollment Reference</div>
                    <div class="value"><?php echo htmlspecialchars($student['enrollment_id'] ?? 'N/A'); ?></div>
                </div>
                <div class="item">
                    <div class="label">Admission Number</div>
                    <div class="value"><?php echo htmlspecialchars($student['admission_number'] ?? 'N/A'); ?></div>
                </div>
                <div class="item">
                    <div class="label">Academic Year</div>
                    <div class="value"><?php echo htmlspecialchars($student['academic_year'] ?? $current_academic_year); ?></div>
                </div>
                <div class="item">
                    <div class="label">Payment Status</div>
                    <div class="value"><?php echo htmlspecialchars(ucfirst($student['payment_status'] ?? 'Unpaid')); ?></div>
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <div class="card">
            <h3><i class="fas fa-money-bill-wave"></i> Payment History</h3>
            <?php if (empty($payments)): ?>
                <p style="color: #888; font-size: 14px; text-align: center; padding: 20px;">No payments recorded yet.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Receipt</th>
                                <th>Fee Type</th>
                                <th>Amount (GHS)</th>
                                <th>Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($p['payment_date'] ?? $p['created_at'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($p['fee_type'] ?? 'General'); ?></td>
                                    <td><strong><?php echo number_format((float)($p['amount'] ?? 0), 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars($p['payment_method'] ?? 'N/A'); ?></td>
                                    <td><span class="text-green"><?php echo htmlspecialchars($p['status'] ?? 'completed'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Attendance Summary -->
        <div class="card">
            <h3><i class="fas fa-calendar-check"></i> Attendance Summary (Last 20 Records)</h3>
            <?php if (empty($attendance)): ?>
                <p style="color: #888; font-size: 14px; text-align: center; padding: 20px;">No attendance records available.</p>
            <?php else: ?>
                <?php
                $total_att = count($attendance);
                $present_pct = $total_att > 0 ? round(($present_count / $total_att) * 100) : 0;
                $absent_pct = $total_att > 0 ? round(($absent_count / $total_att) * 100) : 0;
                ?>
                <div class="attendance-bar">
                    <span style="font-size: 13px; color: #27ae60; font-weight: 600;">Present: <?php echo $present_count; ?></span>
                    <div class="bar">
                        <div class="fill present" style="width: <?php echo $present_pct; ?>%;"></div>
                    </div>
                    <span style="font-size: 13px; color: #e74c3c; font-weight: 600;">Absent: <?php echo $absent_count; ?></span>
                    <div class="bar">
                        <div class="fill absent" style="width: <?php echo $absent_pct; ?>%;"></div>
                    </div>
                </div>
                <div style="overflow-x: auto; margin-top: 15px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance as $a): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($a['attendance_date'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (($a['status'] ?? '') === 'present'): ?>
                                            <span class="text-green"><i class="fas fa-check-circle"></i> Present</span>
                                        <?php else: ?>
                                            <span class="text-red"><i class="fas fa-times-circle"></i> <?php echo htmlspecialchars(ucfirst($a['status'] ?? 'Absent')); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($a['reason'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div style="display: flex; gap: 15px; justify-content: center; margin-top: 10px;">
            <a href="../parent/fees.php?id=<?php echo $student_id; ?>" class="btn-back" style="background: #27ae60;">
                <i class="fas fa-money-bill"></i> View Full Fee Statement
            </a>
            <a href="../parent/report_card.php?id=<?php echo $student_id; ?>" class="btn-back" style="background: #f39c12;">
                <i class="fas fa-clipboard"></i> View Report Card
            </a>
            <a href="../parent/dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
