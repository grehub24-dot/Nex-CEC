<?php
require_once 'includes/db.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../login.php');
}

enforcePasswordReset();

$student_id = $_SESSION['student_id'];

// Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

// Fetch Payments
$stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? ORDER BY payment_date DESC");
$stmt->execute([$student_id]);
$payments = $stmt->fetchAll();

// Calculate total
$total_paid = 0;
foreach ($payments as $p) {
    $total_paid += $p['amount'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment History — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header" style="text-align:center; padding: 20px 10px;">
                <img src="../images/school-logo.png" alt="Logo" style="width: 60px; height: 60px; margin-bottom: 8px; border-radius: 50%; background: #fff; padding: 5px;" onerror="this.src='../images/aamusted.jpg'">
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

        <main class="main-content">
            <div class="top-bar">
                <h2>Payment History</h2>
            </div>

            <!-- Summary -->
            <div class="stat-cards" style="margin-bottom: 25px;">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                    <div class="stat-details"><h3>GHS <?php echo number_format($total_paid, 2); ?></h3><p>Total Paid</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                    <div class="stat-details"><h3><?php echo count($payments); ?></h3><p>Total Transactions</p></div>
                </div>
            </div>

            <div class="card">
                <h3>All Transactions</h3>
                <table class="table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Date</th>
                            <th>Fee Type</th>
                            <th>Amount</th>
                            <th>Period</th>
                            <th>Status</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                            <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                            <td><?php echo htmlspecialchars($payment['fee_type'] ?? 'General'); ?></td>
                            <td><strong>GHS <?php echo number_format($payment['amount'], 2); ?></strong></td>
                            <td><?php echo htmlspecialchars($payment['academic_year'] . ' — Term ' . $payment['semester']); ?></td>
                            <td><span style="color:#2ecc71; font-weight:bold;">&#10003; Paid</span></td>
                            <td>
                                <a href="view_receipt.php?receipt=<?php echo urlencode($payment['receipt_number']); ?>" target="_blank" class="btn-sm btn-secondary"><i class="fas fa-download"></i> Receipt</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($payments)): ?>
                            <tr><td colspan="7" style="text-align:center; color: #666;">No payments found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
