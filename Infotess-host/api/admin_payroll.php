<?php
require_once 'includes/db.php';

// Enforce access control
requireAccess('payroll');

$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

// Handle Generate Payroll
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_payroll') {
    validate_request_csrf();
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    
    try {
        $pdo->beginTransaction();
        
        // Get all active staff (filter in PHP — bridge drops WHERE status = 'active')
        $all_staff = $pdo->query("SELECT * FROM staff")->fetchAll();
        $active_staff = array_filter($all_staff, fn($s) => ($s['status'] ?? '') === 'active');
        
        $generated_count = 0;
        foreach ($active_staff as $staff) {
            // Check if payroll already exists for this staff/month/year (PHP-side check)
            $already_exists = false;
            $all_payroll = $pdo->query("SELECT staff_id, month, year FROM payroll")->fetchAll();
            foreach ($all_payroll as $p) {
                if ((int)$p['staff_id'] === (int)$staff['id'] && (int)$p['month'] === $month && (int)$p['year'] === $year) {
                    $already_exists = true; break;
                }
            }
            if ($already_exists) continue;
            
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
            
            // Other deductions (bridge doesn't support SUM(), COALESCE, or literal `= true` — fetch rows, filter & sum in PHP)
            $stmt = $pdo->prepare("SELECT * FROM deductions WHERE staff_id = ?");
            $stmt->execute([$staff['id']]);
            $all_deductions_for_staff = $stmt->fetchAll();
            $other_deductions = array_sum(
                array_map(
                    fn($d) => ($d['is_recurring'] ?? false) ? (float)($d['amount'] ?? 0) : 0,
                    $all_deductions_for_staff
                )
            );
            
            $total_deductions = $ssnit_deduction + $tax_deduction + $other_deductions;
            $net_pay = $gross - $total_deductions;
            
            $stmt = $pdo->prepare("INSERT INTO payroll (staff_id, month, year, basic_salary, total_allowances, gross_pay, ssnit_deduction, tax_deduction, other_deductions, total_deductions, net_pay, status, pay_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$staff['id'], $month, $year, $basic, $total_allowances, $gross, $ssnit_deduction, $tax_deduction, $other_deductions, $total_deductions, $net_pay, 'pending', null]);
            
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
    validate_request_csrf();
    $payroll_id = (int)$_POST['payroll_id'];
    try {
        // Bridge doesn't support literal values or NOW() in SET — use ? params for everything
        $stmt = $pdo->prepare("UPDATE payroll SET status = ?, pay_date = ? WHERE id = ?");
        $stmt->execute(['approved', date('Y-m-d H:i:s'), $payroll_id]);
        $message = "Payroll approved successfully.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Delete Payroll
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    validate_request_csrf();
    $payroll_id = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM pay_slips WHERE payroll_id = ?")->execute([$payroll_id]);
        $pdo->prepare("DELETE FROM payroll WHERE id = ?")->execute([$payroll_id]);
        $message = "Payroll record deleted.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Bulk Delete Payroll
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete_payroll') {
    validate_request_csrf();
    $ids = $_POST['payroll_ids'] ?? [];
    if (!empty($ids) && is_array($ids)) {
        try {
            $pdo->beginTransaction();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM pay_slips WHERE payroll_id IN ($placeholders)");
            $stmt->execute($ids);
            $stmt = $pdo->prepare("DELETE FROM payroll WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $pdo->commit();
            $message = count($ids) . " payroll record(s) deleted.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
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

// Fetch all active staff with their salary structures for preview
$all_staff_raw = $pdo->query("SELECT id, staff_id, full_name, position, status FROM staff")->fetchAll();
$active_for_preview = array_filter($all_staff_raw, fn($s) => ($s['status'] ?? '') === 'active');
usort($active_for_preview, fn($a, $b) => strcmp($a['full_name'] ?? '', $b['full_name'] ?? ''));

$staff_structures = [];
foreach ($active_for_preview as $s) {
    $stmt = $pdo->prepare("SELECT * FROM salary_structures WHERE staff_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$s['id']]);
    $salary = $stmt->fetch();
    $staff_structures[] = [
        'id'             => $s['id'],
        'staff_id'       => $s['staff_id'],
        'full_name'      => $s['full_name'],
        'position'       => $s['position'],
        'configured'     => $salary ? true : false,
        'basic'          => $salary ? (float)$salary['basic_salary'] : 0,
        'housing'        => $salary ? (float)$salary['housing_allowance'] : 0,
        'transport'      => $salary ? (float)$salary['transport_allowance'] : 0,
        'other_allow'    => $salary ? (float)$salary['other_allowances'] : 0,
        'ssnit_rate'     => $salary ? (float)$salary['ssnit_rate'] : 0,
        'tax_rate'       => $salary ? (float)$salary['tax_rate'] : 0,
    ];
}
$configured_count = count(array_filter($staff_structures, fn($s) => $s['configured']));
$unconfigured_count = count(array_filter($staff_structures, fn($s) => !$s['configured']));

// Salary Structures Pagination
$salary_per_page = 10;
$salary_page = isset($_GET['salary_page']) ? (int)$_GET['salary_page'] : 1;
if ($salary_page < 1) $salary_page = 1;
$salary_offset = ($salary_page - 1) * $salary_per_page;
$salary_total = count($staff_structures);
$salary_total_pages = max(1, (int)ceil($salary_total / $salary_per_page));
$salary_display = array_slice($staff_structures, $salary_offset, $salary_per_page);
// Track displayed row numbers for the "showing X-Y of Z" label
$salary_start_row = $salary_offset + 1;
$salary_end_row = min($salary_offset + $salary_per_page, $salary_total);
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
            <?php echo renderSidebar('payroll', $school_name); ?>

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
                        <?php csrf_field(); ?>
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

            <!-- Current Salary Structures Preview -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-content">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <h3 style="margin:0;"><i class="fas fa-cogs" style="color: var(--primary-color);"></i> Current Salary Structures
                            <span style="font-size: 0.85rem; font-weight: normal; color: #666; margin-left: 10px;">
                                <?php echo $configured_count; ?> configured
                                <?php if ($unconfigured_count > 0): ?>
                                    · <span style="color: #e74c3c;"><?php echo $unconfigured_count; ?> missing</span>
                                <?php endif; ?>
                            </span>
                        </h3>
                        <span style="font-size:0.85rem; color:#666;">
                            Showing <?php echo $salary_start_row; ?>–<?php echo $salary_end_row; ?> of <?php echo $salary_total; ?>
                        </span>
                    </div>
                    <div class="table-responsive" style="margin-top: 15px;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Staff ID</th>
                                    <th>Name</th>
                                    <th>Position</th>
                                    <th style="text-align: right;">Basic (GHS)</th>
                                    <th style="text-align: right;">Housing (GHS)</th>
                                    <th style="text-align: right;">Transport (GHS)</th>
                                    <th style="text-align: right;">Other (GHS)</th>
                                    <th style="text-align: center;">Gross (GHS)</th>
                                    <th style="text-align: center;">SSNIT Rate</th>
                                    <th style="text-align: center;">Tax Rate</th>
                                    <th style="text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salary_display as $s): 
                                    $gross_preview = $s['basic'] + $s['housing'] + $s['transport'] + $s['other_allow'];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['staff_id']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($s['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($s['position']); ?></td>
                                    <td style="text-align: right;"><?php echo number_format($s['basic'], 2); ?></td>
                                    <td style="text-align: right;"><?php echo number_format($s['housing'], 2); ?></td>
                                    <td style="text-align: right;"><?php echo number_format($s['transport'], 2); ?></td>
                                    <td style="text-align: right;"><?php echo number_format($s['other_allow'], 2); ?></td>
                                    <td style="text-align: center;"><strong><?php echo number_format($gross_preview, 2); ?></strong></td>
                                    <td style="text-align: center;"><?php echo $s['configured'] ? $s['ssnit_rate'] . '%' : '<span style="color:#e74c3c;">—</span>'; ?></td>
                                    <td style="text-align: center;"><?php echo $s['configured'] ? $s['tax_rate'] . '%' : '<span style="color:#e74c3c;">—</span>'; ?></td>
                                    <td style="text-align: center;">
                                        <?php if ($s['configured']): ?>
                                            <span style="background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem;">Ready</span>
                                        <?php else: ?>
                                            <span style="background: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem;">No Structure</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background: #f8f9fa; font-weight: bold;">
                                    <td colspan="3" style="padding: 12px;">TOTAL</td>
                                    <td style="padding: 12px; text-align: right;">GHS <?php echo number_format(array_sum(array_map(fn($s) => $s['basic'], $staff_structures)), 2); ?></td>
                                    <td style="padding: 12px; text-align: right;">GHS <?php echo number_format(array_sum(array_map(fn($s) => $s['housing'], $staff_structures)), 2); ?></td>
                                    <td style="padding: 12px; text-align: right;">GHS <?php echo number_format(array_sum(array_map(fn($s) => $s['transport'], $staff_structures)), 2); ?></td>
                                    <td style="padding: 12px; text-align: right;">GHS <?php echo number_format(array_sum(array_map(fn($s) => $s['other_allow'], $staff_structures)), 2); ?></td>
                                    <td style="padding: 12px; text-align: center;">GHS <?php echo number_format(array_sum(array_map(fn($s) => $s['basic'] + $s['housing'] + $s['transport'] + $s['other_allow'], $staff_structures)), 2); ?></td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Salary Structures Pagination -->
                <?php
                // Build base query string preserving existing params (month, year, etc.)
                $salary_qs_parts = [];
                foreach (['month', 'year', 'status'] as $key) {
                    if (isset($_GET[$key])) {
                        $salary_qs_parts[] = urlencode($key) . '=' . urlencode($_GET[$key]);
                    }
                }
                $salary_qs = $salary_qs_parts ? implode('&', $salary_qs_parts) . '&' : '';
                ?>
                <?php if ($salary_total_pages > 1): ?>
                <div style="display:flex; justify-content:center; gap:5px; margin-top:15px; flex-wrap:wrap;">
                    <?php if ($salary_page > 1): ?>
                        <a href="?<?php echo $salary_qs; ?>salary_page=<?php echo $salary_page - 1; ?>" style="display:inline-flex; align-items:center; gap:5px; padding:6px 14px; background:#f8f9fa; color:#000; border:1px solid #ddd; border-radius:6px; text-decoration:none; font-size:13px;">&laquo; Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $salary_total_pages; $i++): ?>
                        <a href="?<?php echo $salary_qs; ?>salary_page=<?php echo $i; ?>" style="display:inline-flex; align-items:center; justify-content:center; min-width:34px; padding:6px 10px; background:<?php echo $i == $salary_page ? '#1a5276' : '#f8f9fa'; ?>; color:<?php echo $i == $salary_page ? '#fff' : '#000'; ?>; border:1px solid <?php echo $i == $salary_page ? '#1a5276' : '#ddd'; ?>; border-radius:6px; text-decoration:none; font-size:13px; font-weight:<?php echo $i == $salary_page ? '700' : '400'; ?>;"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($salary_page < $salary_total_pages): ?>
                        <a href="?<?php echo $salary_qs; ?>salary_page=<?php echo $salary_page + 1; ?>" style="display:inline-flex; align-items:center; gap:5px; padding:6px 14px; background:#f8f9fa; color:#000; border:1px solid #ddd; border-radius:6px; text-decoration:none; font-size:13px;">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
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
                    <form method="POST" action="payroll.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" id="bulk-payroll-form">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="bulk_delete_payroll">
                        <div style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                            <button type="button" onclick="if(confirm('Delete all selected payroll records?')){var f=document.getElementById('bulk-payroll-form');var cbs=f.querySelectorAll('input[name=\'payroll_ids[]\']:checked');if(cbs.length===0){alert('No records selected.');return;}f.submit();}" style="background:#e74c3c;color:#fff;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;font-size:0.9rem;">
                                <i class="fas fa-trash"></i> Delete Selected
                            </button>
                            <span id="selected-count" style="color:#666;font-size:0.85rem;">0 selected</span>
                        </div>
                        <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" id="select-all" onchange="var cbs=document.querySelectorAll('input[name=\'payroll_ids[]\']');cbs.forEach(function(cb){cb.checked=this.checked},this);updateSelectedCount();"></th>
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
                                    <td style="text-align: center;"><input type="checkbox" name="payroll_ids[]" value="<?php echo $record['id']; ?>" onchange="updateSelectedCount()"></td>
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
                                            <button type="button" class="btn-login" style="background: #28a745; padding: 5px 10px; font-size: 0.8rem;" onclick="submitApprove(<?php echo $record['id']; ?>)">Approve</button>
                                        <?php endif; ?>
                                        <a href="pay_slip.php?id=<?php echo $record['id']; ?>" class="btn-login" style="background: #17a2b8; padding: 5px 10px; font-size: 0.8rem;">Slip</a>
                                        <a href="payroll.php?delete=<?php echo $record['id']; ?>&<?php echo csrf_query(); ?>" class="btn-login" style="background: #e74c3c; padding: 5px 10px; font-size: 0.8rem;" onclick="return confirm('Delete this payroll record?');">Delete</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background: #f8f9fa; font-weight: bold;">
                                    <td style="padding: 15px;"></td>
                                    <td colspan="2" style="padding: 15px;">TOTAL</td>
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
                    </form>

                    <!-- Standalone approve form (outside bulk form to avoid nesting) -->
                    <form method="POST" action="payroll.php" id="approve-form" style="display:none;">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="approve_payroll">
                        <input type="hidden" name="payroll_id" id="approve-payroll-id" value="">
                    </form>

                    <script>
                    function updateSelectedCount() {
                        var cbs = document.querySelectorAll('input[name="payroll_ids[]"]:checked');
                        document.getElementById('selected-count').textContent = cbs.length + ' selected';
                    }
                    function submitApprove(id) {
                        document.getElementById('approve-payroll-id').value = id;
                        document.getElementById('approve-form').submit();
                    }
                    </script>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
