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
} catch (Exception $e) {}

$school_name = $settings['school_name'] ?? 'Nex CEC';
$current_year = $settings['current_academic_year'] ?? date('Y') . '/' . (date('Y') + 1);
$current_term = $settings['current_term'] ?? '1';

// Fetch Fee Structure for this term
$fees = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM fee_structures WHERE academic_year = ? AND term = ? ORDER BY is_mandatory DESC, title ASC");
    $stmt->execute([$current_year, $current_term]);
    $fees = $stmt->fetchAll();
    
    // Filter by student's class if class-specific, or keep all-class fees
    $class_name = $student['class_name'] ?? '';
    if ($class_name) {
        $cls = null;
        try {
            $cs = $pdo->prepare("SELECT id FROM classes WHERE name = ?");
            $cs->execute([$class_name]);
            $cls = $cs->fetch();
        } catch (Exception $e) {}
        
        $filtered = [];
        foreach ($fees as $f) {
            if (empty($f['class_id'])) {
                // Applies to all classes
                $filtered[] = $f;
            } elseif ($cls && $f['class_id'] == $cls['id']) {
                // Specific to this class
                $filtered[] = $f;
            }
        }
        $fees = $filtered;
    }
} catch (Exception $e) {
    $fees = [];
}

// Fetch student's payments for this year/term
$payments = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? AND academic_year = ? AND semester = ? ORDER BY payment_date DESC");
    $stmt->execute([$student_id, $current_year, $current_term]);
    $payments = $stmt->fetchAll();
} catch (Exception $e) { $payments = []; }

// Fetch payment_allocations for allocation-aware totals
$allocations_by_payment = [];
if (!empty($payments)) {
    $payment_ids = array_map(fn($p) => (int)$p['id'], $payments);
    try {
        $placeholders = implode(',', array_fill(0, count($payment_ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM payment_allocations WHERE payment_id IN ($placeholders)");
        $stmt->execute($payment_ids);
        foreach ($stmt->fetchAll() as $a) {
            $pid = (int)$a['payment_id'];
            if (!isset($allocations_by_payment[$pid])) $allocations_by_payment[$pid] = [];
            $allocations_by_payment[$pid][] = $a;
        }
    } catch (Exception $e) {}
}

// Group payments by fee type using allocations (with legacy fallback)
$paid_by_type = [];
foreach ($payments as $p) {
    $pid = (int)$p['id'];
    if (isset($allocations_by_payment[$pid]) && !empty($allocations_by_payment[$pid])) {
        foreach ($allocations_by_payment[$pid] as $a) {
            $type = $a['fee_type'] ?? 'General';
            if (!isset($paid_by_type[$type])) $paid_by_type[$type] = 0;
            $paid_by_type[$type] += (float)$a['amount'];
        }
    } else {
        $type = $p['fee_type'] ?? 'General';
        if (!isset($paid_by_type[$type])) $paid_by_type[$type] = 0;
        $paid_by_type[$type] += (float)$p['amount'];
    }
}

$total_expected = 0;
$total_paid = 0;
foreach ($fees as $f) {
    $total_expected += $f['amount'];
    $type = $f['fee_type'] ?? 'General';
    if (isset($paid_by_type[$type])) {
        $total_paid += min($paid_by_type[$type], $f['amount']);
    }
}
$outstanding = max(0, $total_expected - $total_paid);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Fees — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header" style="text-align:center; padding: 20px 10px;">
                <img src="<?php echo htmlspecialchars($settings['school_logo_url'] ?? '../images/aamusted.jpg'); ?>" alt="Logo" style="max-width: 90px; max-height: 48px; width: auto; height: auto; object-fit: contain; background: #fff; padding: 4px 8px; border-radius: 6px; display: inline-block; margin-bottom: 8px;" onerror="this.onerror=null;this.src='../images/aamusted.jpg'">
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
                <div>
                    <h2>Fee Structure</h2>
                    <div style="color: #666; font-size: 0.95rem;">
                        <?php echo htmlspecialchars($current_year); ?> — Term <?php echo htmlspecialchars($current_term); ?>
                        &bull; <?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div class="stat-cards" style="margin-bottom: 25px;">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-coins"></i></div>
                    <div class="stat-details"><h3>GHS <?php echo number_format($total_expected, 2); ?></h3><p>Total Fees</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle" style="color: #2ecc71;"></i></div>
                    <div class="stat-details"><h3>GHS <?php echo number_format($total_paid, 2); ?></h3><p>Amount Paid</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-exclamation-circle" style="color: #e74c3c;"></i></div>
                    <div class="stat-details"><h3>GHS <?php echo number_format($outstanding, 2); ?></h3><p>Outstanding</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                    <div class="stat-details"><h3><?php echo count($payments); ?></h3><p>Payments Made</p></div>
                </div>
            </div>

            <!-- Fee Breakdown -->
            <div class="section">
                <h3>Fee Breakdown</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fee</th>
                                <th>Amount (GHS)</th>
                                <th>Paid (GHS)</th>
                                <th>Balance (GHS)</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($fees)): ?>
                                <tr><td colspan="6" style="text-align:center; color: #666;">No fees have been configured for this term.</td></tr>
                            <?php else: ?>
                                <?php foreach ($fees as $fee): 
                                    $type = $fee['fee_type'] ?? 'General';
                                    $paid = isset($paid_by_type[$type]) ? $paid_by_type[$type] : 0;
                                    $bal = max(0, $fee['amount'] - $paid);
                                    $is_fully_paid = $paid >= $fee['amount'];
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($fee['title']); ?></strong>
                                        <?php if ($fee['is_mandatory']): ?>
                                            <span style="background: #e74c3c; color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 3px; margin-left: 8px;">Required</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($fee['amount'], 2); ?></td>
                                    <td style="color: #2ecc71;"><?php echo number_format($paid, 2); ?></td>
                                    <td style="color: <?php echo $bal > 0 ? '#e74c3c' : '#2ecc71'; ?>; font-weight:bold;"><?php echo number_format($bal, 2); ?></td>
                                    <td><?php echo htmlspecialchars($type); ?></td>
                                    <td>
                                        <?php if ($is_fully_paid): ?>
                                            <span style="color: #2ecc71; font-weight: bold;">&#10003; Paid</span>
                                        <?php elseif ($paid > 0): ?>
                                            <span style="color: #f39c12; font-weight: bold;">Partial</span>
                                        <?php else: ?>
                                            <span style="color: #e74c3c; font-weight: bold;">Unpaid</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($fees)): ?>
                        <tfoot>
                            <tr style="background: #f8f9fa;">
                                <td><strong>Total</strong></td>
                                <td><strong>GHS <?php echo number_format($total_expected, 2); ?></strong></td>
                                <td><strong style="color: #2ecc71;">GHS <?php echo number_format($total_paid, 2); ?></strong></td>
                                <td><strong style="color: <?php echo $outstanding > 0 ? '#e74c3c' : '#2ecc71'; ?>;">GHS <?php echo number_format($outstanding, 2); ?></strong></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- Payment Instructions -->
            <div class="section" style="margin-top: 25px;">
                <h3>How to Pay</h3>
                <div style="background: #f8f9fa; border-radius: 8px; padding: 20px;">
                    <p><i class="fas fa-info-circle" style="color: #3498db;"></i> <strong>Payment Instructions:</strong></p>
                    <ul style="margin: 10px 0 0 20px; color: #555;">
                        <li>Visit the school finance office to make your payment.</li>
                        <li>Payments can be made via <strong>Cash</strong>, <strong>Mobile Money</strong>, or <strong>Bank Transfer</strong>.</li>
                        <li>After payment, a receipt will be issued and sent to your email.</li>
                        <li>Use your <strong>Index Number</strong> as the payment reference.</li>
                        <li>Contact the finance office if you have questions about your fees.</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
