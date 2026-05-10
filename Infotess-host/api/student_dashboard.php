<?php
require_once 'includes/db.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../login.php');
}

enforcePasswordReset();

$student_id = $_SESSION['student_id'];

// Fetch Student Data
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // system_settings table may not exist yet
}

$school_name = $settings['school_name'] ?? 'Nex CEC';
$current_academic_year = $settings['current_academic_year'] ?? date('Y') . '/' . (date('Y') + 1);
$current_term = $settings['current_term'] ?? '1';

// Fetch Payments
$payments = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? ORDER BY payment_date DESC");
    $stmt->execute([$student_id]);
    $payments = $stmt->fetchAll();
} catch (Exception $e) {
    $payments = [];
}

// Calculate Total Paid
$total_paid = 0;
foreach ($payments as $p) {
    $total_paid += $p['amount'];
}

// Outstanding balance for current academic year dues
$required_dues = (float)($settings['annual_dues_amount'] ?? 500.00);

$paid_this_year = 0;
try {
    // Bridge doesn't support SUM() or COALESCE — fetch rows, sum in PHP
    $stmt = $pdo->prepare("SELECT amount FROM payments WHERE student_id = ? AND academic_year = ?");
    $stmt->execute([$student_id, $current_academic_year]);
    $all_payments_for_year = $stmt->fetchAll();
    $paid_this_year = array_sum(array_map(fn($r) => (float)($r['amount'] ?? 0), $all_payments_for_year));
} catch (Exception $e) {
    // payments table may not exist yet
}
$outstanding = max(0, $required_dues - $paid_this_year);
$status_color = $outstanding <= 0 ? 'green' : 'red';
$status_text = $outstanding <= 0 ? 'Fully Paid' : 'Outstanding';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <a href="#main-content" class="skip-link" style="position: absolute; top: -100%; left: 0; background: var(--primary-color); color: #fff; padding: 10px 20px; z-index: 9999; transition: top 0.2s;">Skip to main content</a>
    <style>.skip-link:focus { top: 0; }</style>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header text-center" style="padding: 20px 10px;">
                <img src="../images/school-logo.png" alt="Logo" class="rounded-full mb-10" style="width: 60px; height: 60px; background: #fff; padding: 5px;" onerror="this.src='../images/aamusted.jpg'">
                <h3 style="font-size:15px;">My Portal</h3>
            </div>
                        <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="fees.php"><i class="fas fa-list-alt"></i> Fee Structure</a></li>
                <li><a href="report_card.php"><i class="fas fa-clipboard"></i> Report Card</a></li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages 
                    <span class="badge" style="background:#dc3545; color:white; padding:2px 6px; border-radius:50%; font-size:0.7rem;">0</span>
                </a></li>
                <li><a href="history.php"><i class="fas fa-history"></i> Payment History</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content" id="main-content">
            <div class="top-bar">
                <div class="flex items-center gap-15">
                    <img src="../<?php echo !empty($student['profile_picture']) ? htmlspecialchars($student['profile_picture']) : 'images/aamusted.jpg'; ?>" alt="Profile" class="rounded-full object-cover" style="width: 60px; height: 60px; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    <div>
                        <h2>Welcome, <?php echo htmlspecialchars($student['full_name']); ?></h2>
                        <div class="color-muted"><?php echo htmlspecialchars($student['admission_number']); ?> &bull; <?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="stat-cards">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stat-details">
                        <h3>GHS <?php echo number_format($total_paid, 2); ?></h3>
                        <p>Total Paid</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo count($payments); ?></h3>
                        <p>Receipts Generated</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <div class="stat-details">
                        <h3>GHS <?php echo number_format($outstanding, 2); ?></h3>
                        <p>Outstanding (<?php echo htmlspecialchars($current_academic_year); ?>)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle" style="color: <?php echo $status_color; ?>;"></i>
                    </div>
                    <div class="stat-details">
                        <h3 style="color: <?php echo $status_color; ?>;"><?php echo $status_text; ?></h3>
                        <p>Status (<?php echo htmlspecialchars($current_academic_year); ?>)</p>
                    </div>
                </div>
            </div>

            <!-- Term Fee Breakdown -->
            <div class="section" style="margin-bottom: 30px;">
                <h3>Fee Breakdown — <?php echo htmlspecialchars($current_academic_year); ?> Term <?php echo htmlspecialchars($current_term); ?></h3>
                <?php
                $fee_types = explode(',', $settings['fee_types'] ?? 'Tuition,PTA Levy,Sports & Culture,ICT,Examination,Development,Feeding,Transport,Uniform,Books & Materials');
                
                // Group payments by fee type for current year
                $payments_by_type = [];
                foreach ($payments as $p) {
                    if ($p['academic_year'] === $current_academic_year) {
                        $type = $p['fee_type'] ?? 'General';
                        if (!isset($payments_by_type[$type])) {
                            $payments_by_type[$type] = 0;
                        }
                        $payments_by_type[$type] += $p['amount'];
                    }
                }
                
                // Estimate per-type dues (split equally for now, can be customized in settings)
                $num_fees = count($fee_types);
                $estimated_per_fee = $num_fees > 0 ? $required_dues / $num_fees : 0;
                ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fee Type</th>
                                <th>Estimated (GHS)</th>
                                <th>Paid (GHS)</th>
                                <th>Balance (GHS)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fee_types as $type): 
                                $type = trim($type);
                                $paid = isset($payments_by_type[$type]) ? $payments_by_type[$type] : 0;
                                $bal = max(0, $estimated_per_fee - $paid);
                                $is_paid = $paid >= $estimated_per_fee;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($type); ?></td>
                                <td><?php echo number_format($estimated_per_fee, 2); ?></td>
                                <td><?php echo number_format($paid, 2); ?></td>
                                <td class="<?php echo $bal > 0 ? 'color-danger' : 'color-success'; ?> fw-bold"><?php echo number_format($bal, 2); ?></td>
                                <td>
                                    <span class="<?php echo $is_paid ? 'color-success' : 'color-danger'; ?> fw-bold">
                                        <?php echo $is_paid ? '&#10003; Paid' : 'Pending'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Notifications Section -->
            <div class="section mb-30">
                <div class="flex justify-between items-center">
                    <h3>Recent Notifications</h3>
                    <a href="messages.php" class="fs-small color-primary">View all notifications</a>
                </div>
                <?php
                $recent_notifications = [];
                try {
                    $stmt = $pdo->prepare("SELECT title, message, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
                    $stmt->execute([$_SESSION['user_id']]);
                    $recent_notifications = $stmt->fetchAll();
                } catch (Exception $e) { $recent_notifications = []; }

                if (empty($recent_notifications)) {
                    // Bridge drops OR — filter in PHP
                    $uid = (int)$_SESSION['user_id'];
                    $allMsgs = $pdo->query("SELECT title, content AS message, created_at FROM messages ORDER BY created_at DESC")->fetchAll();
                    $recent_notifications = array_slice(array_filter($allMsgs, fn($m) => !empty($m['is_broadcast']) || (int)($m['receiver_id'] ?? 0) === $uid), 0, 3);
                }

                if (empty($recent_notifications)):
                ?>
                    <div class="card p-15 color-muted">No new notifications.</div>
                <?php else: ?>
                    <?php foreach ($recent_notifications as $item): ?>
                        <div class="card p-15 mb-10" style="border-left: 4px solid var(--primary-color);">
                            <div class="flex justify-between items-center">
                                <strong><?php echo htmlspecialchars((string)$item['title']); ?></strong>
                                <small class="color-muted"><?php echo date('M d, H:i', strtotime((string)$item['created_at'])); ?></small>
                            </div>
                            <p class="mt-5" style="font-size: 0.95rem; color: #444;">
                                <?php echo htmlspecialchars(substr((string)$item['message'], 0, 120)) . (strlen((string)$item['message']) > 120 ? '...' : ''); ?>
                            </p>
                            <a href="messages.php" class="color-secondary fw-bold mt-5 inline-block" style="font-size: 0.85rem;">Read Full Message &rarr;</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Recent Payments -->
            <div class="section">
                <h3>My Payment History</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Date</th>
                                <th>Fee Type</th>
                                <th>Amount</th>
                                <th>Term</th>
                                <th>Status</th>
                                <th>Download</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                <td><?php echo htmlspecialchars($payment['fee_type'] ?? 'General'); ?></td>
                                <td>GHS <?php echo number_format($payment['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($payment['academic_year'] . ' — Term ' . $payment['semester']); ?></td>
                                <td><span class="color-success fw-bold">Paid</span></td>
                                <td>
                                    <a href="../receipts/receipt_<?php echo htmlspecialchars($payment['receipt_number']); ?>.html" target="_blank" class="btn-login btn-sm">View Receipt</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (count($payments) === 0): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;">No payment records found.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
