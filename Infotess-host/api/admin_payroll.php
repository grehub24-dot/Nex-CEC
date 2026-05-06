<?php
require_once 'includes/db.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

// Handle Generate Payroll
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_payroll') {
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    
    try {
        $pdo->beginTransaction();
        
        // Get all active staff
        $stmt = $pdo->query("SELECT * FROM staff WHERE status = 'active'");
        $active_staff = $stmt->fetchAll();
        
        $generated_count = 0;
        foreach ($active_staff as $staff) {
            // Check if payroll already exists for this month/year
            $stmt = $pdo->prepare("SELECT id FROM payroll WHERE staff_id = ? AND month = ? AND year = ?");
            $stmt->execute([$staff['id'], $month, $year]);
            if ($stmt->fetch()) continue;
            
            // Get salary structure
            $stmt = $pdo->prepare("SELECT * FROM salary_structures WHERE staff_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$staff['id']]);
            $salary = $stmt->fetch();
            
            if (!$salary) continue;
            
            $basic = (float)$salary['basic_salary'];
            $housing = (float)$salary['housing_allowance'];
            $transport = (float)$salary['transport_allowance'];
            $other_allow = (float)$salary['other_allowances'];
            $total_allowances = $housing + $transport + $other_allow;
            $gross = $basic + $total_allowances;
            
            // SSNIT (Tier 1: 5.5% employee, Tier 2: 5.5% employee = 11% total employee contribution)
            $ssnit_rate = (float)$salary['ssnit_rate'] / 100;
            $ssnit_deduction = round($basic * $ssnit_rate, 2);
            
            // Tax (simplified - Ghana PAYE)
            $tax_rate = (float)$salary['tax_rate'] / 100;
            $taxable_income = $gross - $ssnit_deduction;
            $tax_deduction = round($taxable_income * $tax_rate, 2);
            
            // Other deductions
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM deductions WHERE staff_id = ? AND is_recurring = true");
            $stmt->execute([$staff['id']]);
            $other_deductions = (float)$stmt->fetchColumn();
            
            $total_deductions = $ssnit_deduction + $tax_deduction + $other_deductions;
            $net_pay = $gross - $total_deductions;
            
            $stmt = $pdo->prepare("INSERT INTO payroll (staff_id, month, year, basic_salary, total_allowances, gross_pay, ssnit_deduction, tax_deduction, other_deductions, total_deductions, net_pay, status, pay_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NULL)");
            $stmt->execute([$staff['id'], $month, $year, $basic, $total_allowances, $gross, $ssnit_deduction, $tax_deduction, $other_deductions, $total_deductions, $net_pay]);
            
            $generated_count++;
        }
        
        $pdo->commit();
        $message = "Payroll generated for $generated_count staff members for " . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . ".";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error generating payroll: " . $e->getMessage();
    }
}

// Handle Approve Payroll
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_payroll') {
    $payroll_id = (int)$_POST['payroll_id'];
    try {
        $stmt = $pdo->prepare("UPDATE payroll SET status = 'approved', pay_date = NOW() WHERE id = ?");
        $stmt->execute([$payroll_id]);
        $message = "Payroll approved successfully.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Delete Payroll
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $payroll_id = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM pay_slips WHERE payroll_id = ?")->execute([$payroll_id]);
        $pdo->prepare("DELETE FROM payroll WHERE id = ?")->execute([$payroll_id]);
        $message = "Payroll record deleted.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// View current payroll
$selected_month = (int)($_GET['month'] ?? date('n'));
$selected_year = (int)($_GET['year'] ?? date('Y'));

// Fetch payroll records (no JOIN — bridge compatibility)
$payroll_records = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM payroll WHERE month = ? AND year = ? ORDER BY id ASC");
    $stmt->execute([$selected_month, $selected_year]);
    $raw_records = $stmt->fetchAll();
    
    foreach ($raw_records as $p) {
        $stmt = $pdo->prepare("SELECT full_name, staff_id, position, bank_name, account_number FROM staff WHERE id = ?");
        $stmt->execute([$p['staff_id']]);
        $s = $stmt->fetch();
        if ($s) {
            $p['full_name'] = $s['full_name'];
            $p['staff_id'] = $s['staff_id'];
            $p['position'] = $s['position'];
            $p['bank_name'] = $s['bank_name'] ?? '';
            $p['account_number'] = $s['account_number'] ?? '';
            $payroll_records[] = $p;
        }
    }
} catch (Exception $e) {
    $payroll_records = [];
}

// Summary
$total_gross = array_sum(array_map(fn($r) => (float)$r['gross_pay'], $payroll_records));
$total_net = array_sum(array_map(fn($r) => (float)$r['net_pay'], $payroll_records));
$total_deductions = array_sum(array_map(fn($r) => (float)$r['total_deductions'], $payroll_records));
$approved_count = count(array_filter($payroll_records, fn($r) => $r['status'] === 'approved'));
$pending_count = count(array_filter($payroll_records, fn($r) => $r['status'] === 'pending'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header" style="text-align: center; padding: 20px 10px;">
                <img src="../images/school-logo.png" alt="Logo" style="width: 80px; height: 80px; margin-bottom: 10px; border-radius: 50%; background: #fff; padding: 5px;" onerror="this.src='../images/aamusted.jpg'">
                <h3><?php echo htmlspecialchars($school_name); ?> Admin</h3>
            </div>
                        <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="bulk_import.php"><i class="fas fa-file-csv"></i> Bulk Import</a></li>
                <li><a href="staff.php"><i class="fas fa-chalkboard-teacher"></i> Staff</a></li>
                <li><a href="payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="fees.php"><i class="fas fa-list-alt"></i> Fee Structure</a></li>
                <li><a href="payroll.php"><i class="fas fa-file-invoice-dollar"></i> Payroll</a></li>
                <li><a href="salary.php"><i class="fas fa-money-check-alt"></i> Salary Structures</a></li>
                <li><a href="grades.php"><i class="fas fa-clipboard-list"></i> SBA / Grades</a></li>
                <li><a href="attendance.php"><i class="fas fa-user-check"></i> Attendance</a></li>
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

        <main class="main-content">
            <div class="top-bar">
                <h2>Staff Payroll Management</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Month Selector & Generate -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-content" style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                    <form method="GET" action="payroll.php" style="display: flex; gap: 10px; align-items: center;">
                        <label><strong>Select Period:</strong></label>
                        <select name="month" class="form-control" style="width: auto;">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $selected_month == $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="year" class="form-control" style="width: auto;">
                            <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $selected_year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn-login"><i class="fas fa-search"></i> View</button>
                    </form>
                    
                    <form method="POST" action="payroll.php" style="margin-left: auto;">
                        <input type="hidden" name="action" value="generate_payroll">
                        <input type="hidden" name="month" value="<?php echo $selected_month; ?>">
                        <input type="hidden" name="year" value="<?php echo $selected_year; ?>">
                        <button type="submit" class="btn-primary" onclick="return confirm('Generate payroll for all active staff? This will create records for staff without existing payroll for this period.')">
                            <i class="fas fa-calculator"></i> Generate Payroll
                        </button>
                    </form>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="stat-cards" style="margin-bottom: 30px;">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-details"><h3><?php echo count($payroll_records); ?></h3><p>Staff on Payroll</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-details"><h3>GHS <?php echo number_format($total_gross, 2); ?></h3><p>Total Gross Pay</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-minus-circle" style="color: #e74c3c;"></i></div>
                    <div class="stat-details"><h3>GHS <?php echo number_format($total_deductions, 2); ?></h3><p>Total Deductions</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-wallet" style="color: #27ae60;"></i></div>
                    <div class="stat-details"><h3>GHS <?php echo number_format($total_net, 2); ?></h3><p>Total Net Pay</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock" style="color: #f39c12;"></i></div>
                    <div class="stat-details"><h3><?php echo $pending_count; ?> / <?php echo $approved_count; ?></h3><p>Pending / Approved</p></div>
                </div>
            </div>

            <!-- Payroll Table -->
            <div class="section">
                <h3>Payroll — <?php echo date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year)); ?></h3>
                
                <?php if (empty($payroll_records)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No payroll records found for this period. Click "Generate Payroll" to create records for all active staff.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Staff ID</th>
                                    <th>Name</th>
                                    <th>Position</th>
                                    <th>Basic (GHS)</th>
                                    <th>Allowances (GHS)</th>
                                    <th>Gross (GHS)</th>
                                    <th>SSNIT (GHS)</th>
                                    <th>Tax (GHS)</th>
                                    <th>Other Ded. (GHS)</th>
                                    <th>Net Pay (GHS)</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payroll_records as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['staff_id']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($record['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($record['position']); ?></td>
                                    <td><?php echo number_format($record['basic_salary'], 2); ?></td>
                                    <td><?php echo number_format($record['total_allowances'], 2); ?></td>
                                    <td><strong><?php echo number_format($record['gross_pay'], 2); ?></strong></td>
                                    <td style="color: #e74c3c;"><?php echo number_format($record['ssnit_deduction'], 2); ?></td>
                                    <td style="color: #e74c3c;"><?php echo number_format($record['tax_deduction'], 2); ?></td>
                                    <td style="color: #e74c3c;"><?php echo number_format($record['other_deductions'], 2); ?></td>
                                    <td><strong style="color: #27ae60;"><?php echo number_format($record['net_pay'], 2); ?></strong></td>
                                    <td>
                                        <?php if ($record['status'] === 'approved'): ?>
                                            <span style="background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 4px; font-size: 0.85rem;">Approved</span>
                                        <?php elseif ($record['status'] === 'paid'): ?>
                                            <span style="background: #cce5ff; color: #004085; padding: 4px 10px; border-radius: 4px; font-size: 0.85rem;">Paid</span>
                                        <?php else: ?>
                                            <span style="background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 4px; font-size: 0.85rem;">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($record['status'] === 'pending'): ?>
                                            <form method="POST" action="payroll.php" style="display: inline;">
                                                <input type="hidden" name="action" value="approve_payroll">
                                                <input type="hidden" name="payroll_id" value="<?php echo $record['id']; ?>">
                                                <button type="submit" class="btn-login" style="background: #28a745; padding: 5px 10px; font-size: 0.8rem;">Approve</button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="pay_slip.php?id=<?php echo $record['id']; ?>" class="btn-login" style="background: #17a2b8; padding: 5px 10px; font-size: 0.8rem;">Slip</a>
                                        <a href="payroll.php?delete=<?php echo $record['id']; ?>" class="btn-login" style="background: #e74c3c; padding: 5px 10px; font-size: 0.8rem;" onclick="return confirm('Delete this payroll record?');">Delete</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background: #f8f9fa; font-weight: bold;">
                                    <td colspan="3" style="padding: 15px;">TOTAL</td>
                                    <td style="padding: 15px; text-align: right;">GHS <?php echo number_format(array_sum(array_map(fn($r) => (float)$r['basic_salary'], $payroll_records)), 2); ?></td>
                                    <td style="padding: 15px; text-align: right;">GHS <?php echo number_format($total_gross - array_sum(array_map(fn($r) => (float)$r['basic_salary'], $payroll_records)), 2); ?></td>
                                    <td style="padding: 15px; text-align: right;">GHS <?php echo number_format($total_gross, 2); ?></td>
                                    <td style="padding: 15px; text-align: right; color: #e74c3c;">GHS <?php echo number_format(array_sum(array_map(fn($r) => (float)$r['ssnit_deduction'], $payroll_records)), 2); ?></td>
                                    <td style="padding: 15px; text-align: right; color: #e74c3c;">GHS <?php echo number_format(array_sum(array_map(fn($r) => (float)$r['tax_deduction'], $payroll_records)), 2); ?></td>
                                    <td style="padding: 15px; text-align: right; color: #e74c3c;">GHS <?php echo number_format(array_sum(array_map(fn($r) => (float)$r['other_deductions'], $payroll_records)), 2); ?></td>
                                    <td style="padding: 15px; text-align: right; color: #27ae60;">GHS <?php echo number_format($total_net, 2); ?></td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
