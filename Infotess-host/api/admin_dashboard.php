<?php
require_once 'includes/db.php';

// Ensure Admin Access
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Fetch Current Settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$school_name = $settings['school_name'] ?? 'Nex CEC';
$current_year = $settings['current_academic_year'] ?? date('Y') . '/' . (date('Y') + 1);
$required_dues = isset($settings['annual_dues_amount']) ? (float)$settings['annual_dues_amount'] : 500.00;

// ==========================================
// UPDATED: Fetch Stats from the new VIEW
// ==========================================
try {
    $stmt = $pdo->query("SELECT * FROM admin_dashboard_stats");
    $stats = $stmt->fetch();
    $total_students = $stats['total_students'] ?? 0;
    $total_revenue = $stats['total_revenue'] ?? 0;
    $payments_today = $stats['payments_today'] ?? 0;
    $students_paid = $stats['compliant_students'] ?? 0;
} catch (Exception $e) {
    $total_students = 0; $total_revenue = 0; $payments_today = 0; $students_paid = 0;
}

$compliance_rate = $total_students > 0 ? round(($students_paid / (int)$total_students) * 100, 1) : 0;
$outstanding_students = max(0, (int)$total_students - $students_paid);

// ==========================================
// UPDATED: Recent Payments from VIEW
// ==========================================
try {
    $stmt = $pdo->query("SELECT * FROM recent_payments_view");
    $recent_payments = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_payments = [];
}

// ==========================================
// UPDATED: Chart Data (Simplified)
// ==========================================
// We fetch raw payments and calculate chart data in PHP to avoid complex SQL issues
try {
    $stmt = $pdo->query("SELECT amount, payment_date FROM payments ORDER BY payment_date ASC");
    $raw_payments = $stmt->fetchAll();
    
    $monthly_totals = [];
    foreach ($raw_payments as $row) {
        $date = date('M Y', strtotime($row['payment_date']));
        $monthly_totals[$date] = ($monthly_totals[$date] ?? 0) + (float)$row['amount'];
    }
    
    $chart_labels = array_keys($monthly_totals);
    $chart_data = array_values($monthly_totals);
} catch (Exception $e) {
    $chart_labels = [date('M Y')];
    $chart_data = [0];
}

if (empty($chart_labels)) {
    $chart_labels = [date('M Y')];
    $chart_data = [0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header" style="text-align: center; padding: 20px 10px;">
                <img src="../images/school-logo.png" alt="Logo" style="width: 80px; height: 80px; margin-bottom: 10px; border-radius: 50%; background: #fff; padding: 5px;" onerror="this.src='../images/aamusted.jpg'">
                <h3><?php echo htmlspecialchars($school_name); ?> Admin</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="admin_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="admin_students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="admin_staff.php"><i class="fas fa-chalkboard-teacher"></i> Staff</a></li>
                <li><a href="admin_payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="admin_fees.php"><i class="fas fa-list-alt"></i> Fee Structure</a></li>
                <li><a href="admin_payroll.php"><i class="fas fa-file-invoice-dollar"></i> Payroll</a></li>
                <li><a href="admin_grades.php"><i class="fas fa-clipboard-list"></i> SBA / Grades</a></li>
                <li><a href="admin_attendance.php"><i class="fas fa-user-check"></i> Attendance</a></li>
                <li><a href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="admin_verify.php"><i class="fas fa-qrcode"></i> Verify Receipt</a></li>
                <li><a href="admin_users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="admin_messaging.php"><i class="fas fa-envelope"></i> Messaging</a></li>
                <li><a href="admin_inbox.php"><i class="fas fa-inbox"></i> Inbox</a></li>
                <li><a href="admin_module_settings.php"><i class="fas fa-cogs"></i> Module Settings</a></li>
                <li><a href="admin_settings.php"><i class="fas fa-tools"></i> System Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <h2>Dashboard Overview</h2>
                <div class="user-info"><span>Welcome, Admin</span></div>
            </div>

            <!-- Stats -->
            <div class="stat-cards">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-details"><h3><?php echo number_format($total_students); ?></h3><p>Total Students</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                    <div class="stat-details"><h3>GHS <?php echo number_format($total_revenue, 2); ?></h3><p>Total Revenue</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                    <div class="stat-details"><h3><?php echo $payments_today; ?></h3><p>Payments Today</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-details"><h3><?php echo $compliance_rate; ?>%</h3><p>Compliance Rate</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
                    <div class="stat-details"><h3><?php echo number_format($outstanding_students); ?></h3><p>Outstanding Students</p></div>
                </div>
            </div>

            <!-- Recent Payments Table -->
            <div class="section">
                <h3>Recent Payments</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Receipt #</th><th>Student</th><th>Amount (GHS)</th><th>Date</th><th>Method</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_payments)): ?>
                                <tr><td colspan="6" style="text-align:center;">No payments recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['full_name']); ?><br><small><?php echo htmlspecialchars($payment['index_number']); ?></small></td>
                                    <td><?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td><a href="../receipts/receipt_<?php echo $payment['receipt_number']; ?>.html" target="_blank" class="btn-login" style="padding: 5px 10px; font-size: 0.8rem;">View</a></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Charts -->
            <div class="section">
                <h3>Revenue Analytics</h3>
                <canvas id="revenueChart" width="400" height="150"></canvas>
            </div>
        </main>
    </div>

    <script>
        const ctx = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Revenue (GHS)',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: 'rgba(0, 51, 102, 0.7)',
                    borderColor: 'rgba(0, 51, 102, 1)',
                    borderWidth: 1
                }]
            },
            options: { scales: { y: { beginAtZero: true } } }
        });
    </script>
</body>
</html>
