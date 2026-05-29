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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/design-tokens.css">
    <link rel="stylesheet" href="../css/typography.css">
    <link rel="stylesheet" href="../css/layout.css">
    <link rel="stylesheet" href="../css/components.css">
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <div class="page-wrapper">
        <!-- Hamburger button (visible on mobile) -->
        <button class="hamburger-btn" id="hamburgerBtn" aria-label="Open menu"><i class="fas fa-bars"></i></button>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close menu"><i class="fas fa-times"></i></button>
                <img src="<?php echo htmlspecialchars($settings['school_logo_url'] ?? '../images/chariot-logo.svg'); ?>" alt="Logo" class="sidebar-logo" style="width:64px;height:64px;border-radius:50%;object-fit:cover;background:var(--color-canvas);padding:3px;margin-bottom:10px;" onerror="this.onerror=null;this.src='../images/aamusted.jpg'">
                <h3 style="font-size:14px;font-weight:600;color:var(--color-charcoal);margin:var(--space-sm) 0 0;">Student Portal</h3>
                <span class="text-micro">Student</span>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="fees.php"><i class="fas fa-list-alt"></i> Fee Structure</a></li>
                <li><a href="report_card.php"><i class="fas fa-clipboard"></i> Report Card</a></li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
                <li><a href="history.php"><i class="fas fa-history"></i> Payment History</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content" id="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="top-bar-left">
                    <img src="../<?php echo !empty($student['profile_picture']) ? htmlspecialchars($student['profile_picture']) : 'images/aamusted.jpg'; ?>" alt="Profile" class="user-avatar" style="width:44px;height:44px;border:2px solid var(--color-hairline);">
                    <div>
                        <h2 style="font-size:var(--text-h4-size);margin:0;">Welcome, <?php echo htmlspecialchars($student['full_name']); ?></h2>
                        <span class="text-caption"><?php echo htmlspecialchars($student['admission_number']); ?> &bull; <?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="stat-cards anim-stagger">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                    <div class="stat-details">
                        <h3>GHS <?php echo number_format($total_paid, 2); ?></h3>
                        <p>Total Paid</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
                    <div class="stat-details">
                        <h3><?php echo count($payments); ?></h3>
                        <p>Receipts Generated</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-balance-scale"></i></div>
                    <div class="stat-details">
                        <h3>GHS <?php echo number_format($outstanding, 2); ?></h3>
                        <p>Outstanding (<?php echo htmlspecialchars($current_academic_year); ?>)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color:<?php echo $status_color;?>;"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-details">
                        <h3 style="color:<?php echo $status_color;?>;"><?php echo $status_text; ?></h3>
                        <p>Status (<?php echo htmlspecialchars($current_academic_year); ?>)</p>
                    </div>
                </div>
            </div>

            <!-- Fee Breakdown -->
            <div class="section-block">
                <h3>Fee Breakdown — <?php echo htmlspecialchars($current_academic_year); ?> Term <?php echo htmlspecialchars($current_term); ?></h3>
                <?php
                $fee_types = explode(',', $settings['fee_types'] ?? 'Tuition,PTA Levy,Sports & Culture,ICT,Examination,Development,Feeding,Transport,Uniform,Books & Materials');
                $payments_by_type = [];
                foreach ($payments as $p) {
                    if ($p['academic_year'] === $current_academic_year) {
                        $type = $p['fee_type'] ?? 'General';
                        if (!isset($payments_by_type[$type])) { $payments_by_type[$type] = 0; }
                        $payments_by_type[$type] += $p['amount'];
                    }
                }
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
                                <td data-label="Fee Type"><?php echo htmlspecialchars($type); ?></td>
                                <td data-label="Estimated"><?php echo number_format($estimated_per_fee, 2); ?></td>
                                <td data-label="Paid"><?php echo number_format($paid, 2); ?></td>
                                <td data-label="Balance" class="<?php echo $bal > 0 ? 'color-warning' : 'color-success'; ?> fw-bold"><?php echo number_format($bal, 2); ?></td>
                                <td data-label="Status">
                                    <span class="<?php echo $is_paid ? 'color-success' : 'color-warning'; ?> fw-bold">
                                        <?php echo $is_paid ? '&#10003; Paid' : 'Pending'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Notifications -->
            <div class="section-block">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-md);">
                    <h3 style="margin:0;">Recent Notifications</h3>
                    <a href="messages.php" class="btn btn-link">View all</a>
                </div>
                <?php
                $recent_notifications = [];
                try {
                    $stmt = $pdo->prepare("SELECT title, message, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
                    $stmt->execute([$_SESSION['user_id']]);
                    $recent_notifications = $stmt->fetchAll();
                } catch (Exception $e) { $recent_notifications = []; }

                if (empty($recent_notifications)) {
                    $uid = (int)$_SESSION['user_id'];
                    $allMsgs = $pdo->query("SELECT title, content AS message, created_at FROM messages ORDER BY created_at DESC")->fetchAll();
                    $recent_notifications = array_slice(array_filter($allMsgs, fn($m) => !empty($m['is_broadcast']) || (int)($m['receiver_id'] ?? 0) === $uid), 0, 3);
                }

                if (empty($recent_notifications)): ?>
                    <div class="card" style="text-align:center;color:var(--color-steel);">No new notifications.</div>
                <?php else: ?>
                    <?php foreach ($recent_notifications as $item): ?>
                        <div class="card" style="border-left:4px solid var(--color-primary);margin-bottom:var(--space-md);">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-xs);">
                                <strong style="color:var(--color-charcoal);"><?php echo htmlspecialchars((string)$item['title']); ?></strong>
                                <small class="text-caption"><?php echo date('M d, H:i', strtotime((string)$item['created_at'])); ?></small>
                            </div>
                            <p style="font-size:var(--text-sm-size);color:var(--color-slate);margin-bottom:var(--space-sm);">
                                <?php echo htmlspecialchars(substr((string)$item['message'], 0, 120)) . (strlen((string)$item['message']) > 120 ? '...' : ''); ?>
                            </p>
                            <a href="messages.php" class="btn btn-link" style="font-size:var(--text-caption-size);">Read Full Message &rarr;</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Payment History -->
            <div class="section-block">
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
                                <td data-label="Receipt"><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                                <td data-label="Date"><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                <td data-label="Fee Type"><?php echo htmlspecialchars($payment['fee_type'] ?? 'General'); ?></td>
                                <td data-label="Amount">GHS <?php echo number_format($payment['amount'], 2); ?></td>
                                <td data-label="Term"><?php echo htmlspecialchars($payment['academic_year'] . ' — T' . $payment['semester']); ?></td>
                                <td data-label="Status"><span class="color-success fw-bold">Paid</span></td>
                                <td data-label="Download">
                                    <a href="view_receipt.php?receipt=<?php echo urlencode($payment['receipt_number']); ?>" target="_blank" class="btn btn-primary" style="padding:4px 12px;font-size:var(--text-caption-size);">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (count($payments) === 0): ?>
                            <tr><td colspan="7" style="text-align:center;">No payment records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Portal Footer -->
            <div class="portal-footer">
                &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($school_name); ?>. All rights reserved.
            </div>
        </main>
    </div>

    <!-- Mobile sidebar toggle script -->
    <script>
    (function() {
        var hamburger = document.getElementById("hamburgerBtn");
        var sidebar = document.getElementById("sidebar");
        var overlay = document.getElementById("sidebarOverlay");
        var closeBtn = document.getElementById("sidebarCloseBtn");
        if (!hamburger || !sidebar || !overlay) return;

        function openSidebar() {
            sidebar.classList.add("open");
            overlay.classList.add("active");
            document.body.style.overflow = "hidden";
        }
        function closeSidebar() {
            sidebar.classList.remove("open");
            overlay.classList.remove("active");
            document.body.style.overflow = "";
        }

        hamburger.addEventListener("click", openSidebar);
        overlay.addEventListener("click", closeSidebar);
        if (closeBtn) closeBtn.addEventListener("click", closeSidebar);

        var links = sidebar.querySelectorAll(".sidebar-menu a");
        for (var i = 0; i < links.length; i++) {
            links[i].addEventListener("click", function() {
                if (window.innerWidth <= 768) closeSidebar();
            });
        }
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") closeSidebar();
        });
        window.addEventListener("resize", function() {
            if (window.innerWidth > 768) closeSidebar();
        });
    })();
    </script>
</body>
</html>
