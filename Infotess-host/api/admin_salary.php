<?php
require_once 'includes/db.php';

// Enforce access control
requireAccess('salary');

$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

// Handle Save/Update Salary Structure
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_salary') {
    validate_request_csrf();
    $staff_id = (int)$_POST['staff_id'];
    $basic_salary = (float)$_POST['basic_salary'];
    $housing_allowance = (float)$_POST['housing_allowance'];
    $transport_allowance = (float)$_POST['transport_allowance'];
    $other_allowances = (float)$_POST['other_allowances'];
    $ssnit_rate = (float)$_POST['ssnit_rate'];
    $tax_rate = (float)$_POST['tax_rate'];

    try {
        $stmt = $pdo->prepare("SELECT id FROM salary_structures WHERE staff_id = ?");
        $stmt->execute([$staff_id]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE salary_structures SET basic_salary=?, housing_allowance=?, transport_allowance=?, other_allowances=?, ssnit_rate=?, tax_rate=? WHERE staff_id=?");
            $stmt->execute([$basic_salary, $housing_allowance, $transport_allowance, $other_allowances, $ssnit_rate, $tax_rate, $staff_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO salary_structures (staff_id, basic_salary, housing_allowance, transport_allowance, other_allowances, ssnit_rate, tax_rate) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$staff_id, $basic_salary, $housing_allowance, $transport_allowance, $other_allowances, $ssnit_rate, $tax_rate]);
        }
        $message = "Salary structure saved successfully.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Add Deduction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_deduction') {
    validate_request_csrf();
    $staff_id = (int)$_POST['staff_id'];
    $deduction_type = sanitize($_POST['deduction_type']);
    $amount = (float)$_POST['deduction_amount'];
    $description = sanitize($_POST['description'] ?? '');
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;

    try {
        $stmt = $pdo->prepare("INSERT INTO deductions (staff_id, deduction_type, amount, description, is_recurring) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$staff_id, $deduction_type, $amount, $description, $is_recurring]);
        $message = "Deduction added successfully.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Delete Deduction
if (isset($_GET['delete_deduction']) && is_numeric($_GET['delete_deduction'])) {
    validate_request_csrf();
    try {
        $pdo->prepare("DELETE FROM deductions WHERE id = ?")->execute([(int)$_GET['delete_deduction']]);
        $message = "Deduction removed.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all staff for dropdown (bridge drops literal WHERE — filter active in PHP)
$all_staff = $pdo->query("SELECT id, staff_id, full_name, position, status FROM staff")->fetchAll();
$staff_list = array_filter($all_staff, fn($s) => ($s['status'] ?? '') === 'active');
usort($staff_list, fn($a, $b) => strcmp($a['full_name'] ?? '', $b['full_name'] ?? ''));

// Selected staff
$selected_staff_id = (int)($_GET['staff_id'] ?? ($_POST['staff_id'] ?? 0));
$selected_staff = null;
$salary = null;
$deductions = [];

if ($selected_staff_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->execute([$selected_staff_id]);
    $selected_staff = $stmt->fetch();
    
    if ($selected_staff) {
        $stmt = $pdo->prepare("SELECT * FROM salary_structures WHERE staff_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$selected_staff_id]);
        $salary = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT * FROM deductions WHERE staff_id = ? ORDER BY created_at DESC");
        $stmt->execute([$selected_staff_id]);
        $deductions = $stmt->fetchAll();
    }
}

// Calculate totals
$gross = $salary ? (float)$salary['basic_salary'] + (float)$salary['housing_allowance'] + (float)$salary['transport_allowance'] + (float)$salary['other_allowances'] : 0;
$ssnit = $salary ? round((float)$salary['basic_salary'] * ((float)$salary['ssnit_rate'] / 100), 2) : 0;
$taxable = $gross - $ssnit;
$tax = $salary ? round($taxable * ((float)$salary['tax_rate'] / 100), 2) : 0;
$recurring_deductions = array_sum(array_map(fn($d) => (float)$d['amount'], array_filter($deductions, fn($d) => $d['is_recurring'])));
$net = $gross - $ssnit - $tax - $recurring_deductions;

// Ghana SSNIT breakdown constants
$ssnit_employee_rate   = 5.5;
$ssnit_employer_rate   = 13.0;
$ssnit_total_rate      = $ssnit_employee_rate + $ssnit_employer_rate; // 18.5%
$tier1_pension_rate    = 11.0;
$tier1_nhia_rate       = 2.5;
$tier2_private_rate    = 5.0;
$basic_for_ssnit       = $salary ? (float)$salary['basic_salary'] : 0;
$employee_contribution = round($basic_for_ssnit * ($ssnit_employee_rate / 100), 2);
$employer_contribution = round($basic_for_ssnit * ($ssnit_employer_rate / 100), 2);
$total_contribution    = $employee_contribution + $employer_contribution;
$tier1_pension_amt     = round($basic_for_ssnit * ($tier1_pension_rate / 100), 2);
$tier1_nhia_amt        = round($basic_for_ssnit * ($tier1_nhia_rate / 100), 2);
$tier2_private_amt     = round($basic_for_ssnit * ($tier2_private_rate / 100), 2);
$net_after_ssnit       = round($basic_for_ssnit - $employee_contribution, 2);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Structures — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
            <?php echo renderSidebar('salary', $school_name); ?>

        <main class="main-content">
            <div class="top-bar">
                <h2>Salary Structure Management</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Staff Selector -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-content">
                    <form method="GET" action="salary.php" style="display: flex; gap: 15px; align-items: center;">
                        <label><strong>Select Staff:</strong></label>
                        <select name="staff_id" class="form-control" style="width: 300px;" onchange="this.form.submit()">
                            <option value="">-- Choose Staff Member --</option>
                            <?php foreach ($staff_list as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo $selected_staff_id == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['staff_id']); ?> — <?php echo htmlspecialchars($s['full_name']); ?> (<?php echo htmlspecialchars($s['position']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>

            <?php if ($selected_staff): ?>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                <!-- Salary Structure Form -->
                <div class="card">
                    <div class="card-content">
                        <h3><i class="fas fa-money-check-alt" style="color: var(--primary-color);"></i> Salary Structure</h3>
                        <form method="POST" action="salary.php" style="margin-top: 20px;">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="save_salary">
                            <input type="hidden" name="staff_id" value="<?php echo $selected_staff_id; ?>">
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <label>Basic Salary (GHS)</label>
                                    <input type="number" step="0.01" name="basic_salary" class="form-control" required value="<?php echo $salary ? htmlspecialchars($salary['basic_salary']) : ''; ?>">
                                </div>
                                <div>
                                    <label>Housing Allowance (GHS)</label>
                                    <input type="number" step="0.01" name="housing_allowance" class="form-control" value="<?php echo $salary ? htmlspecialchars($salary['housing_allowance']) : '0'; ?>">
                                </div>
                                <div>
                                    <label>Transport Allowance (GHS)</label>
                                    <input type="number" step="0.01" name="transport_allowance" class="form-control" value="<?php echo $salary ? htmlspecialchars($salary['transport_allowance']) : '0'; ?>">
                                </div>
                                <div>
                                    <label>Other Allowances (GHS)</label>
                                    <input type="number" step="0.01" name="other_allowances" class="form-control" value="<?php echo $salary ? htmlspecialchars($salary['other_allowances']) : '0'; ?>">
                                </div>
                                <div>
                                    <label>SSNIT Rate (%)</label>
                                    <input type="number" step="0.01" name="ssnit_rate" class="form-control" value="<?php echo $salary ? htmlspecialchars($salary['ssnit_rate']) : '13.5'; ?>">
                                </div>
                                <div>
                                    <label>Tax Rate (%)</label>
                                    <input type="number" step="0.01" name="tax_rate" class="form-control" value="<?php echo $salary ? htmlspecialchars($salary['tax_rate']) : '0'; ?>">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-primary" style="margin-top: 20px; width: 100%;">Save Salary Structure</button>
                        </form>
                    </div>
                </div>

                <!-- Pay Summary -->
                <div class="card">
                    <div class="card-content">
                        <h3>Estimated Monthly Pay</h3>
                        <div style="margin-top: 15px;">
                            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                                <span>Basic Salary</span><strong>GHS <?php echo number_format($salary ? (float)$salary['basic_salary'] : 0, 2); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                                <span>Housing Allowance</span><strong>GHS <?php echo number_format($salary ? (float)$salary['housing_allowance'] : 0, 2); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                                <span>Transport Allowance</span><strong>GHS <?php echo number_format($salary ? (float)$salary['transport_allowance'] : 0, 2); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
                                <span>Other Allowances</span><strong>GHS <?php echo number_format($salary ? (float)$salary['other_allowances'] : 0, 2); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 2px solid var(--primary-color); font-size: 1.1rem;">
                                <span><strong>Gross Pay</strong></span><strong style="color: var(--primary-color);">GHS <?php echo number_format($gross, 2); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; color: #e74c3c;">
                                <span>SSNIT (<?php echo $salary ? $salary['ssnit_rate'] : '13.5'; ?>%)
                                    <a href="#" onclick="event.preventDefault(); document.getElementById('ssnit-breakdown').style.display = document.getElementById('ssnit-breakdown').style.display === 'none' ? 'block' : 'none';" style="color: var(--primary-color); font-size: 0.8rem; text-decoration: underline; margin-left: 5px;">Breakdown</a>
                                </span><strong>- GHS <?php echo number_format($ssnit, 2); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; color: #e74c3c;">
                                <span>PAYE Tax (<?php echo $salary ? $salary['tax_rate'] : '0'; ?>%)</span><strong>- GHS <?php echo number_format($tax, 2); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; color: #e74c3c;">
                                <span>Other Deductions</span><strong>- GHS <?php echo number_format($recurring_deductions, 2); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 15px 0; font-size: 1.3rem; background: #f0f7ff; padding: 15px; border-radius: 8px; margin-top: 10px;">
                                <span><strong>Net Pay</strong></span><strong style="color: #27ae60;">GHS <?php echo number_format($net, 2); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Deductions -->
            <div class="card" style="margin-top: 30px;">
                <div class="card-content">
                    <h3><i class="fas fa-minus-circle" style="color: #e74c3c;"></i> Deductions</h3>
                    
                    <form method="POST" action="salary.php" style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap; align-items: flex-end;">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_deduction">
                        <input type="hidden" name="staff_id" value="<?php echo $selected_staff_id; ?>">
                        <div>
                            <label>Type</label>
                            <select name="deduction_type" class="form-control" required>
                                <option value="">-- Select --</option>
                                <option value="Loan Repayment">Loan Repayment</option>
                                <option value="Cooperative">Cooperative</option>
                                <option value="Insurance">Insurance</option>
                                <option value="Union Dues">Union Dues</option>
                                <option value="Advance Salary">Advance Salary</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label>Amount (GHS)</label>
                            <input type="number" step="0.01" name="deduction_amount" class="form-control" required>
                        </div>
                        <div>
                            <label>Description</label>
                            <input type="text" name="description" class="form-control" placeholder="Optional">
                        </div>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <input type="checkbox" name="is_recurring" checked> <label>Recurring</label>
                        </div>
                        <button type="submit" class="btn-primary">Add Deduction</button>
                    </form>

                    <?php if (!empty($deductions)): ?>
                    <table class="table" style="margin-top: 20px;">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Amount (GHS)</th>
                                <th>Description</th>
                                <th>Recurring</th>
                                <th>Date Added</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deductions as $d): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($d['deduction_type']); ?></td>
                                <td style="color: #e74c3c;">GHS <?php echo number_format($d['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($d['description'] ?? '-'); ?></td>
                                <td><?php echo $d['is_recurring'] ? '<span style="color: green;">Yes</span>' : 'No'; ?></td>
                                <td><?php echo date('M d, Y', strtotime($d['created_at'])); ?></td>
                                <td>
                                    <a href="salary.php?staff_id=<?php echo $selected_staff_id; ?>&delete_deduction=<?php echo $d['id']; ?>&<?php echo csrf_query(); ?>" class="btn-login" style="background: #e74c3c; padding: 5px 10px; font-size: 0.8rem;" onclick="return confirm('Remove this deduction?');">Remove</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            <!-- SSNIT Breakdown (hidden by default) -->
            <div id="ssnit-breakdown" class="card" style="margin-top: 30px; display: none;">
                <div class="card-content">
                    <h3><i class="fas fa-calculator" style="color: var(--primary-color);"></i> SSNIT Contribution Breakdown</h3>
                    <p style="color: #666; margin-bottom: 20px; font-size: 0.9rem;">
                        Based on a basic salary of <strong>GHS <?php echo number_format($basic_for_ssnit, 2); ?></strong> using standard Ghana SSNIT rates.
                        <a href="#" onclick="event.preventDefault(); document.getElementById('ssnit-breakdown').style.display='none';" style="margin-left: 10px; font-size: 0.8rem;">Hide</a>
                    </p>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                        <!-- Table 1: Contribution Split -->
                        <div style="background: #f9f9f9; border-radius: 8px; padding: 15px;">
                            <h4 style="margin-bottom: 10px; font-size: 1rem;">Contribution Split</h4>
                            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                                <thead>
                                    <tr style="background: var(--primary-color); color: #fff;">
                                        <th style="padding: 8px; text-align: left;">Party</th>
                                        <th style="padding: 8px; text-align: center;">Rate</th>
                                        <th style="padding: 8px; text-align: right;">Amount (GH¢)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr style="border-bottom: 1px solid #ddd;">
                                        <td style="padding: 8px;">Employee <span style="color: #888; font-size: 0.8rem;">(deducted from salary)</span></td>
                                        <td style="padding: 8px; text-align: center;"><?php echo $ssnit_employee_rate; ?>%</td>
                                        <td style="padding: 8px; text-align: right; color: #e74c3c;">GH¢ <?php echo number_format($employee_contribution, 2); ?></td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #ddd;">
                                        <td style="padding: 8px;">Employer <span style="color: #888; font-size: 0.8rem;">(paid by company)</span></td>
                                        <td style="padding: 8px; text-align: center;"><?php echo $ssnit_employer_rate; ?>%</td>
                                        <td style="padding: 8px; text-align: right; color: #e67e22;">GH¢ <?php echo number_format($employer_contribution, 2); ?></td>
                                    </tr>
                                    <tr style="font-weight: bold; background: #f0f0f0;">
                                        <td style="padding: 8px;">Total Monthly Contribution</td>
                                        <td style="padding: 8px; text-align: center;"><?php echo $ssnit_total_rate; ?>%</td>
                                        <td style="padding: 8px; text-align: right; color: var(--primary-color);">GH¢ <?php echo number_format($total_contribution, 2); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Table 2: Where the Money Goes -->
                        <div style="background: #f9f9f9; border-radius: 8px; padding: 15px;">
                            <h4 style="margin-bottom: 10px; font-size: 1rem;">Where the Money Goes</h4>
                            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                                <thead>
                                    <tr style="background: var(--primary-color); color: #fff;">
                                        <th style="padding: 8px; text-align: left;">Tier</th>
                                        <th style="padding: 8px; text-align: left;">Destination</th>
                                        <th style="padding: 8px; text-align: center;">Rate</th>
                                        <th style="padding: 8px; text-align: right;">Amount (GH¢)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr style="border-bottom: 1px solid #ddd;">
                                        <td style="padding: 8px;">Tier 1</td>
                                        <td style="padding: 8px;">SSNIT (Pension Fund)</td>
                                        <td style="padding: 8px; text-align: center;"><?php echo $tier1_pension_rate; ?>%</td>
                                        <td style="padding: 8px; text-align: right;">GH¢ <?php echo number_format($tier1_pension_amt, 2); ?></td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #ddd;">
                                        <td style="padding: 8px;">Tier 1</td>
                                        <td style="padding: 8px;">NHIA (Health Insurance)</td>
                                        <td style="padding: 8px; text-align: center;"><?php echo $tier1_nhia_rate; ?>%</td>
                                        <td style="padding: 8px; text-align: right;">GH¢ <?php echo number_format($tier1_nhia_amt, 2); ?></td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #ddd;">
                                        <td style="padding: 8px;">Tier 2</td>
                                        <td style="padding: 8px;">Private Corporate Trustee</td>
                                        <td style="padding: 8px; text-align: center;"><?php echo $tier2_private_rate; ?>%</td>
                                        <td style="padding: 8px; text-align: right;">GH¢ <?php echo number_format($tier2_private_amt, 2); ?></td>
                                    </tr>
                                    <tr style="font-weight: bold; background: #f0f0f0;">
                                        <td style="padding: 8px;"></td>
                                        <td style="padding: 8px;">Total</td>
                                        <td style="padding: 8px; text-align: center;"><?php echo $ssnit_total_rate; ?>%</td>
                                        <td style="padding: 8px; text-align: right; color: var(--primary-color);">GH¢ <?php echo number_format($total_contribution, 2); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Table 3: What the Employee Actually Receives -->
                        <div style="background: #f9f9f9; border-radius: 8px; padding: 15px;">
                            <h4 style="margin-bottom: 10px; font-size: 1rem;">What the Employee Actually Receives</h4>
                            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                                <thead>
                                    <tr style="background: var(--primary-color); color: #fff;">
                                        <th style="padding: 8px; text-align: left;">Item</th>
                                        <th style="padding: 8px; text-align: right;">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr style="border-bottom: 1px solid #ddd;">
                                        <td style="padding: 8px;">Basic Salary</td>
                                        <td style="padding: 8px; text-align: right;">GH¢ <?php echo number_format($basic_for_ssnit, 2); ?></td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #ddd;">
                                        <td style="padding: 8px;">Less: Employee SSNIT Contribution</td>
                                        <td style="padding: 8px; text-align: right; color: #e74c3c;">– GH¢ <?php echo number_format($employee_contribution, 2); ?></td>
                                    </tr>
                                    <tr style="font-weight: bold; background: #e8f5e9;">
                                        <td style="padding: 10px;">Net Salary (before tax)</td>
                                        <td style="padding: 10px; text-align: right; color: #27ae60; font-size: 1.1rem;">GH¢ <?php echo number_format($net_after_ssnit, 2); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                            <p style="margin-top: 10px; font-size: 0.8rem; color: #888; font-style: italic;">
                                The employer pays an additional GH¢ <?php echo number_format($employer_contribution, 2); ?> on top of the GH¢ <?php echo number_format($basic_for_ssnit, 2); ?>, so the total cost to the employer for this worker is <strong>GH¢ <?php echo number_format($basic_for_ssnit + $employer_contribution, 2); ?></strong> per month.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Select a staff member above to manage their salary structure.
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
