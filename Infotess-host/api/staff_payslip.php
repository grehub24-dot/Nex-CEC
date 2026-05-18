<?php
require_once 'includes/db.php';

if (!isLoggedIn() || (!isStaff() && !isTeacher())) {
    redirect('../login.php');
}

$settings = fetchSettings($pdo);
$school_name = $settings['school_name'] ?? 'Nex CEC';
$school_address = $settings['school_address'] ?? 'Kumasi, Ghana';
$school_phone = $settings['school_phone'] ?? '+233 XX XXX XXXX';

$user_id = $_SESSION['user_id'];

// Fetch staff record
$stmt = $pdo->prepare("SELECT * FROM staff WHERE user_id = ?");
$stmt->execute([$user_id]);
$staff = $stmt->fetch();

if (!$staff) {
    echo '<div class="container" style="padding:100px 0;text-align:center;"><h2>Staff record not found</h2><a href="../logout.php" class="btn-primary">Logout</a></div>';
    exit;
}

$staff_id = (int)$staff['id'];

// Get all payroll records for this staff
$all_payroll = $pdo->query("SELECT * FROM payroll")->fetchAll();
$payroll_records = array_filter($all_payroll, function($p) use ($staff_id) {
    return (int)$p['staff_id'] === $staff_id;
});
usort($payroll_records, function($a, $b) {
    $a_ts = mktime(0,0,0,(int)$a['month'],1,(int)$a['year']);
    $b_ts = mktime(0,0,0,(int)$b['month'],1,(int)$b['year']);
    return $b_ts - $a_ts;
});

$selected_id = (int)($_GET['id'] ?? 0);
$selected_payroll = null;
if ($selected_id) {
    foreach ($payroll_records as $p) {
        if ((int)$p['id'] === $selected_id) { $selected_payroll = $p; break; }
    }
}
if (!$selected_payroll && !empty($payroll_records)) {
    $selected_payroll = $payroll_records[0];
}

// Get deductions for this staff
$deductions = [];
$stmt = $pdo->prepare("SELECT * FROM deductions WHERE staff_id = ? AND is_recurring = ?");
$stmt->execute([$staff_id, 1]);
$deductions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Slips — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .staff-container { display: flex; min-height: 100vh; }
        .staff-sidebar {
            width: 250px; background: #1a5276; color: white; position: fixed;
            top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 100;
        }
        .staff-sidebar .sidebar-header { padding: 25px 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .staff-sidebar .sidebar-header img.sidebar-profile-img { width: 64px; height: 64px; border-radius: 50%; background: white; padding: 3px; margin-bottom: 10px; object-fit: cover; }
        .staff-sidebar .sidebar-header h3 { font-size: 15px; margin: 0; }
        .staff-sidebar .sidebar-header p { font-size: 12px; opacity: 0.8; margin: 5px 0 0; }
        .staff-sidebar ul { list-style: none; padding: 0; margin: 0; }
        .staff-sidebar ul li { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .staff-sidebar ul li a { display: block; padding: 14px 20px; color: rgba(255,255,255,0.85); text-decoration: none; font-size: 14px; transition: all 0.2s; }
        .staff-sidebar ul li a:hover, .staff-sidebar ul li a.active { background: rgba(255,255,255,0.1); color: white; padding-left: 25px; }
        .staff-sidebar ul li a i { width: 22px; text-align: center; margin-right: 8px; }
        .staff-main { flex: 1; padding: 30px; background: #f4f6f9; margin-left: 250px; }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: center; justify-content: space-between; }
        .top-bar h2 { font-size: 20px; margin: 0; color: #1a5276; }
        .payslip { max-width: 800px; margin: 0 auto; background: #fff; border: 2px solid #1a5276; border-radius: 8px; padding: 30px; }
        .payslip-header { text-align: center; border-bottom: 3px solid #1a5276; padding-bottom: 20px; margin-bottom: 20px; }
        .payslip-header h1 { color: #1a5276; margin: 0 0 5px 0; }
        .payslip-header h2 { color: #2e86c1; margin: 0; font-size: 1.2rem; }
        .payslip-info { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
        .payslip-info div { padding: 5px 0; }
        .payslip-info strong { color: #1a5276; }
        .payslip-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .payslip-table th { background: #1a5276; color: #fff; padding: 12px; text-align: left; }
        .payslip-table td { padding: 10px 12px; border-bottom: 1px solid #eee; }
        .payslip-table .total-row { background: #f0f7ff; font-weight: bold; font-size: 1.1rem; }
        .payslip-footer { margin-top: 40px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
        .signature-line { border-top: 1px solid #333; padding-top: 5px; text-align: center; margin-top: 60px; }
        .payroll-selector { margin-bottom: 20px; }
        @media print { .no-print { display: none; } .payslip { border: none; margin: 0; padding: 20px; } }
        @media (max-width: 768px) {
            .staff-sidebar { left: -250px; transition: left 0.3s; }
            .staff-sidebar.open { left: 0; }
            .staff-main { margin-left: 0; padding: 20px; }
            .top-bar { flex-direction: column; text-align: center; }
        }
        .hamburger-menu { display: none; position: fixed; top: 15px; left: 15px; z-index: 200;
            background: #1a5276; color: white; border: none; width: 40px; height: 40px;
            border-radius: 8px; font-size: 18px; cursor: pointer;
        }
        @media (max-width: 768px) { .hamburger-menu { display: block; } }
    </style>
</head>
<body>
    <?php echo renderStaffSidebar('payslip', $school_name, 0, $staff['profile_picture'] ?? '', $staff['full_name'] ?? ''); ?>

    <div class="staff-main">
        <div class="top-bar">
            <h2>My Pay Slips</h2>
            <span style="font-size:13px;color:#888;"><?php echo htmlspecialchars($staff['full_name'] ?? ''); ?> (<?php echo htmlspecialchars($staff['staff_id'] ?? ''); ?>)</span>
        </div>

        <?php if (empty($payroll_records)): ?>
            <div class="alert alert-info"><i class="fas fa-info-circle"></i> No pay slips available yet.</div>
        <?php else: ?>
        <div class="payroll-selector no-print">
            <form method="GET" action="../staff/payslip.php" style="display:flex;gap:10px;align-items:flex-end;">
                <div>
                    <label><strong>Select Pay Period</strong></label>
                    <select name="id" class="form-control" style="width:250px;" onchange="this.form.submit()">
                        <?php foreach ($payroll_records as $p):
                            $month_name = date('F Y', mktime(0,0,0,(int)$p['month'],1,(int)$p['year']));
                            $selected = $selected_payroll && (int)$p['id'] === (int)$selected_payroll['id'] ? 'selected' : '';
                        ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $selected; ?>><?php echo $month_name; ?> &mdash; GHS <?php echo number_format($p['net_pay'], 2); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if ($selected_payroll):
            $month_name = date('F', mktime(0,0,0,(int)$selected_payroll['month'],1));
            $slip_status = $selected_payroll['status'] ?? 'pending';
        ?>

        <?php if (in_array($slip_status, ['approved', 'paid'])): ?>
        <div class="no-print" style="text-align:right;margin-bottom:10px;">
            <button onclick="window.print()" class="btn-primary btn-sm"><i class="fas fa-print"></i> Print</button>
        </div>
        <div class="payslip">
            <div class="payslip-header">
                <h1><?php echo htmlspecialchars($school_name); ?></h1>
                <p style="color:#666;margin:5px 0;"><?php echo htmlspecialchars($school_address); ?> | <?php echo htmlspecialchars($school_phone); ?></p>
                <h2>PAY SLIP — <?php echo $month_name . ' ' . $selected_payroll['year']; ?></h2>
            </div>
            <div class="payslip-info">
                <div><strong>Staff ID:</strong> <?php echo htmlspecialchars($staff['staff_id']); ?></div>
                <div><strong>Name:</strong> <?php echo htmlspecialchars($staff['full_name']); ?></div>
                <div><strong>Position:</strong> <?php echo htmlspecialchars($staff['position']); ?></div>
                <div><strong>Department:</strong> <?php echo htmlspecialchars($staff['department'] ?? 'N/A'); ?></div>
                <div><strong>Bank:</strong> <?php echo htmlspecialchars($staff['bank_name'] ?? 'N/A'); ?></div>
                <div><strong>Account:</strong> <?php echo htmlspecialchars($staff['account_number'] ?? 'N/A'); ?></div>
            </div>
            <table class="payslip-table">
                <thead>
                    <tr>
                        <th>Earnings</th>
                        <th style="text-align:right;">Amount (GHS)</th>
                        <th>Deductions</th>
                        <th style="text-align:right;">Amount (GHS)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Basic Salary</td>
                        <td style="text-align:right;"><?php echo number_format($selected_payroll['basic_salary'], 2); ?></td>
                        <td>SSNIT</td>
                        <td style="text-align:right;color:#e74c3c;"><?php echo number_format($selected_payroll['ssnit_deduction'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>Housing Allowance</td>
                        <td style="text-align:right;"><?php echo number_format($selected_payroll['housing_allowance'] ?? 0, 2); ?></td>
                        <td>PAYE Tax</td>
                        <td style="text-align:right;color:#e74c3c;"><?php echo number_format($selected_payroll['tax_deduction'], 2); ?></td>
                    </tr>
                    <?php if (!empty($deductions)): ?>
                        <?php foreach ($deductions as $d): ?>
                        <tr>
                            <td></td><td></td>
                            <td><?php echo htmlspecialchars($d['deduction_type']); ?><?php echo $d['description'] ? ' (' . htmlspecialchars($d['description']) . ')' : ''; ?></td>
                            <td style="text-align:right;color:#e74c3c;"><?php echo number_format($d['amount'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td><strong>GROSS PAY</strong></td>
                        <td style="text-align:right;"><strong><?php echo number_format($selected_payroll['gross_pay'], 2); ?></strong></td>
                        <td><strong>TOTAL DEDUCTIONS</strong></td>
                        <td style="text-align:right;color:#e74c3c;"><strong><?php echo number_format($selected_payroll['total_deductions'], 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
            <div style="background:linear-gradient(90deg,#1a5276,#2e86c1);color:white;text-align:center;padding:20px;border-radius:8px;margin-bottom:20px;">
                <p style="margin:0;font-size:14px;">NET PAY</p>
                <h2 style="margin:5px 0 0 0;font-size:2rem;">GHS <?php echo number_format($selected_payroll['net_pay'], 2); ?></h2>
            </div>
            <div style="font-size:0.85rem;color:#666;margin-bottom:20px;">
                <strong>Status:</strong>
                <?php if ($selected_payroll['status'] === 'approved'): ?>
                    <span style="color:green;">Approved</span>
                <?php elseif ($selected_payroll['status'] === 'paid'): ?>
                    <span style="color:#004085;">Paid on <?php echo date('M d, Y', strtotime($selected_payroll['pay_date'])); ?></span>
                <?php else: ?>
                    <span style="color:#856404;">Pending</span>
                <?php endif; ?>
            </div>
            <div class="payslip-footer">
                <div><div class="signature-line">Employee Signature</div></div>
                <div><div class="signature-line">Authorized Signature</div></div>
            </div>
        </div>
        <?php else: /* pending — hide preview */ ?>
            <div style="max-width:600px;margin:40px auto;text-align:center;padding:60px 30px;background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.08);">
                <div style="font-size:60px;color:#f39c12;margin-bottom:20px;"><i class="fas fa-hourglass-half"></i></div>
                <h3 style="color:#333;margin:0 0 10px 0;">Pay Slip Pending Approval</h3>
                <p style="color:#888;font-size:14px;margin:0;">Your pay slip for <?php echo $month_name . ' ' . $selected_payroll['year']; ?> is awaiting approval. It will be available for preview and printing once approved.</p>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
