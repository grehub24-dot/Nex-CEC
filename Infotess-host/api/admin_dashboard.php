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
// Fetch Stats (without complex views)
// ==========================================
try { $total_students = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(); } catch (Exception $e) { $total_students = 0; }
try { $total_staff = (int)$pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'active'")->fetchColumn(); } catch (Exception $e) { $total_staff = 0; }
try { $total_revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments")->fetchColumn(); } catch (Exception $e) { $total_revenue = 0; }
try { $payments_today = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE payment_date = CURRENT_DATE")->fetchColumn(); } catch (Exception $e) { $payments_today = 0; }
try { $total_payments = (int)$pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn(); } catch (Exception $e) { $total_payments = 0; }
try { $students_paid = (int)$pdo->query("SELECT COUNT(DISTINCT student_id) FROM payments")->fetchColumn(); } catch (Exception $e) { $students_paid = 0; }
try { $pending_messages = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE is_read = false OR is_read IS NULL")->fetchColumn(); } catch (Exception $e) { $pending_messages = 0; }
try { $absent_today = (int)$pdo->query("SELECT COUNT(*) FROM student_attendance WHERE attendance_date = CURRENT_DATE AND status = 'absent'")->fetchColumn(); } catch (Exception $e) { $absent_today = 0; }

$compliance_rate = $total_students > 0 ? round(($students_paid / (int)$total_students) * 100, 1) : 0;
$outstanding_students = max(0, (int)$total_students - $students_paid);

// Recent Payments (direct query)
try {
    $stmt = $pdo->query("SELECT * FROM payments ORDER BY created_at DESC LIMIT 10");
    $recent_payments = $stmt->fetchAll();
    
    // Enrich with student names
    foreach ($recent_payments as &$payment) {
        $s = $pdo->prepare("SELECT full_name, index_number FROM students WHERE id = ?");
        $s->execute([$payment['student_id']]);
        $stu = $s->fetch();
        if ($stu) {
            $payment['full_name'] = $stu['full_name'];
            $payment['index_number'] = $stu['index_number'];
        } else {
            $payment['full_name'] = 'Unknown';
            $payment['index_number'] = '-';
        }
    }
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
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="staff.php"><i class="fas fa-chalkboard-teacher"></i> Staff</a></li>
                <li><a href="payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="fees.php"><i class="fas fa-list-alt"></i> Fee Structure</a></li>
                <li><a href="payroll.php"><i class="fas fa-file-invoice-dollar"></i> Payroll</a></li>
                <li><a href="salary.php"><i class="fas fa-money-check-alt"></i> Salary Structures</a></li>
                <li><a href="grades.php"><i class="fas fa-clipboard-list"></i> SBA / Grades</a></li>
                <li><a href="attendance.php"><i class="fas fa-user-check"></i> Attendance</a></li>
                <li><a href="staff_attendance.php"><i class="fas fa-user-tie"></i> Staff Attendance</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="verify.php"><i class="fas fa-qrcode"></i> Verify Receipt</a></li>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="messaging.php"><i class="fas fa-envelope"></i> Messaging</a></li>
                <li><a href="inbox.php"><i class="fas fa-inbox"></i> Inbox</a></li>
                <li><a href="module_settings.php"><i class="fas fa-cogs"></i> Module Settings</a></li>
                <li><a href="settings.php"><i class="fas fa-tools"></i> System Settings</a></li>
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
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="stat-details"><h3><?php echo number_format($total_students); ?></h3><p>Total Students</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="stat-details"><h3><?php echo number_format($total_staff); ?></h3><p>Active Staff</p></div>
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
                    <div class="stat-details"><h3><?php echo $compliance_rate; ?>%</h3><p>Payment Compliance</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
                    <div class="stat-details"><h3><?php echo $absent_today; ?></h3><p>Absent Today</p></div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="section" style="margin-bottom: 30px;">
                <h3>Quick Actions</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                    <a href="students.php" class="card" style="text-decoration: none; color: inherit;">
                        <div class="card-content" style="text-align: center;">
                            <i class="fas fa-user-plus" style="font-size: 2rem; color: var(--primary-color);"></i>
                            <p style="margin-top: 10px; font-weight: bold;">Add Student</p>
                        </div>
                    </a>
                    <a href="payments.php" class="card" style="text-decoration: none; color: inherit;">
                        <div class="card-content" style="text-align: center;">
                            <i class="fas fa-money-bill-wave" style="font-size: 2rem; color: #27ae60;"></i>
                            <p style="margin-top: 10px; font-weight: bold;">Record Payment</p>
                        </div>
                    </a>
                    <a href="bulk_import.php" class="card" style="text-decoration: none; color: inherit;">
                        <div class="card-content" style="text-align: center;">
                            <i class="fas fa-file-csv" style="font-size: 2rem; color: #f39c12;"></i>
                            <p style="margin-top: 10px; font-weight: bold;">Bulk Import</p>
                        </div>
                    </a>
                    <a href="attendance.php" class="card" style="text-decoration: none; color: inherit;">
                        <div class="card-content" style="text-align: center;">
                            <i class="fas fa-user-check" style="font-size: 2rem; color: #2e86c1;"></i>
                            <p style="margin-top: 10px; font-weight: bold;">Take Attendance</p>
                        </div>
                    </a>
                    <a href="payroll.php" class="card" style="text-decoration: none; color: inherit;">
                        <div class="card-content" style="text-align: center;">
                            <i class="fas fa-file-invoice-dollar" style="font-size: 2rem; color: #8e44ad;"></i>
                            <p style="margin-top: 10px; font-weight: bold;">Generate Payroll</p>
                        </div>
                    </a>
                    <a href="messaging.php" class="card" style="text-decoration: none; color: inherit;">
                        <div class="card-content" style="text-align: center;">
                            <i class="fas fa-envelope" style="font-size: 2rem; color: #e74c3c;"></i>
                            <p style="margin-top: 10px; font-weight: bold;">Send Message<?php echo $pending_messages > 0 ? " ($pending_messages)" : ""; ?></p>
                        </div>
                    </a>
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
