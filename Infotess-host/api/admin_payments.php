<?php
require_once 'includes/db.php';
require_once 'includes/ReceiptGenerator.php';
require_once 'includes/SMSHelper.php';
require_once 'includes/Mailer.php';

// Enforce access control
requireAccess('payments');

// Fetch Settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$school_name = $settings['school_name'] ?? 'Nex CEC';
$current_academic_year = $settings['current_academic_year'] ?? date('Y') . '/' . (date('Y') + 1);
$current_term = $settings['current_term'] ?? '1';
$payment_modes = explode(',', $settings['payment_modes'] ?? 'Cash,Mobile Money,Bank Transfer');

// Basic School fee types (fallback)
$fee_types = explode(',', $settings['fee_types'] ?? 'Tuition,PTA Levy,Sports & Culture,ICT,Examination,Development,Feeding,Transport,Uniform,Books & Materials');

// Fetch classes, students, and fee structures for dynamic dropdowns (narrow columns to prevent 413)
$all_classes = $pdo->query("SELECT id, name FROM classes")->fetchAll();
$all_students = $pdo->query("SELECT id, full_name, admission_number, class_name FROM students")->fetchAll();
$all_fee_structures = $pdo->query("SELECT id, fee_type, title, amount, class_id, academic_year, term FROM fee_structures")->fetchAll();

// Fetch student_bill_items for bill breakdown in payment form
$all_bill_items = [];
try {
    $stmt = $pdo->prepare("SELECT sbi.*, s.full_name, s.class_name FROM student_bill_items sbi JOIN students s ON sbi.student_id = s.id WHERE sbi.academic_year = ? AND sbi.term = ?");
    $stmt->execute([$current_academic_year, $current_term]);
    $all_bill_items = $stmt->fetchAll();
} catch (\Throwable $e) {
    error_log("admin_payments.php bill_items query error: " . $e->getMessage());
    $all_bill_items = [];
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    // CSRF validation
    validate_request_csrf();
    
    $student_id_input = (int)($_POST['student_id'] ?? 0);
    $admission_number = sanitize($_POST['admission_number'] ?? '');
    $transaction_reference = sanitize($_POST['transaction_reference'] ?? '');
    $class_name = sanitize($_POST['class_name']);
    $amount = floatval($_POST['amount']);
    $year = sanitize($_POST['academic_year']);
    $term = sanitize($_POST['term']);
    $payment_type = sanitize($_POST['payment_type'] ?? 'full');
    $fee_type = ($payment_type === 'partial') ? 'Partial Payment' : 'Full Payment';
    $method = sanitize($_POST['payment_method']);
    $date = sanitize($_POST['payment_date']);

    // Find Student by ID (from student dropdown) or fallback to admission_number
    $student = null;
    if ($student_id_input > 0) {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$student_id_input]);
        $student = $stmt->fetch();
    }
    if (!$student && $admission_number !== '') {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE admission_number = ?");
        $stmt->execute([$admission_number]);
        $student = $stmt->fetch();
    }
    if ($student) {
        $u = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $u->execute([$student['user_id']]);
        $urow = $u->fetch();
        $student['email'] = $urow ? $urow['email'] : '';
    }

    if (!$student) {
        $error = "Student not found. Please select a student from the list.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Generate Receipt Number: SCHOOL + YEAR + MONTH + RANDOM
            $receipt_number = strtoupper(substr(preg_replace('/[^A-Z]/', '', $school_name), 0, 4)) . "-" . date('ym') . "-" . rand(1000, 9999);

            // Insert Payment
            $stmt = $pdo->prepare("INSERT INTO payments (student_id, amount, academic_year, term, payment_method, payment_date, receipt_number, recorded_by, fee_type, transaction_reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$student['id'], $amount, $year, $term, $method, $date, $receipt_number, $_SESSION['user_id'], $fee_type, $transaction_reference]);
            $payment_id = $pdo->lastInsertId();

            // Insert payment allocations
            $fee_items_json = $_POST['fee_items_json'] ?? '';
            $fee_items = $fee_items_json ? json_decode($fee_items_json, true) : [];
            if (empty($fee_items)) {
                // Fallback: one allocation for the payment amount
                $fee_items = [['fee_type' => $fee_type, 'fee_title' => $fee_type, 'amount' => $amount]];
            }
            $allocStmt = $pdo->prepare("INSERT INTO payment_allocations (payment_id, fee_type, fee_title, amount) VALUES (?, ?, ?, ?)");
            foreach ($fee_items as $item) {
                $allocStmt->execute([
                    $payment_id,
                    $item['fee_type'] ?? 'General',
                    $item['fee_title'] ?? $item['fee_type'] ?? 'General',
                    (float)($item['amount'] ?? 0)
                ]);
            }

            // Generate Receipt
            $generator = new ReceiptGenerator();
            
            // Fetch student total paid for the year (bridge doesn't support SUM() — fetch rows, sum in PHP)
            $stmt_paid = $pdo->prepare("SELECT amount FROM payments WHERE student_id = ? AND academic_year = ?");
            $stmt_paid->execute([$student['id'], $year]);
            $all_payments_for_year = $stmt_paid->fetchAll();
            $total_paid = array_sum(array_map(fn($r) => (float)($r['amount'] ?? 0), $all_payments_for_year));
            
            // Fetch required dues from already-loaded settings
            $required_dues = (float)($settings['annual_dues_amount'] ?? 500.00);
            
            $current_balance = max(0, $required_dues - $total_paid);

            $receipt_path = $generator->generate($payment_id, $receipt_number, $student, $amount, $date, $class_name, $fee_type, $school_name, $current_balance, $year, $term, $method);
            
            // Save Receipt Record
            $hash = md5($receipt_number . $payment_id . 'SALT');
            $stmt = $pdo->prepare("INSERT INTO receipts (payment_id, receipt_file_path, verification_hash) VALUES (?, ?, ?)");
            $stmt->execute([$payment_id, $receipt_path, $hash]);

            $pdo->commit();
            
            // Send SMS to guardian (basic school schema)
            $guardianPhone = $student['guardian_phone_primary'] ?? '';
            if (!$guardianPhone) {
                $guardianPhone = $student['guardian_phone_emergency'] ?? '';
            }
            if (!empty($guardianPhone)) {
                $sms = new SMSHelper();
                $sms_message = "Payment alert: GHS " . number_format($amount, 2) . " received for {$student['full_name']} - $fee_type ($year Term $term). Receipt: $receipt_number.";
                $sms->send($guardianPhone, $sms_message);
            }

            // Send Email with Receipt
            if (!empty($student['email'])) {
                $mailer = new Mailer();
                
                $email_html = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
                        .email-container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                        .header { background: linear-gradient(to right, #1a5276, #2e86c1); color: white; text-align: center; padding: 40px 20px; }
                        .header h1 { margin: 0; font-size: 26px; }
                        .content { padding: 30px; color: #333; }
                        .receipt-box { border: 1px solid #2e86c1; border-radius: 8px; padding: 20px; margin-top: 20px; }
                        .receipt-title { text-align: center; color: #2e86c1; margin-bottom: 20px; }
                        .receipt-title h2 { margin: 0; font-size: 20px; }
                        .receipt-title p { margin: 5px 0 0 0; color: #555; font-size: 14px; }
                        .receipt-row { padding: 12px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; }
                        .receipt-row:last-child { border-bottom: none; }
                        .amount-box { background: linear-gradient(to right, #1a5276, #2e86c1); color: white; text-align: center; padding: 20px; border-radius: 8px; margin-top: 20px; }
                        .amount-box p { margin: 0 0 5px 0; font-size: 14px; }
                        .amount-box h2 { margin: 0; font-size: 28px; }
                        .paid-badge { background: #1a5276; color: white; padding: 5px 15px; border-radius: 15px; display: inline-block; margin-top: 15px; font-weight: bold; font-size: 14px; }
                        .notes { margin-top: 30px; font-size: 12px; color: #333; }
                        .notes ul { padding-left: 20px; }
                        .footer { text-align: center; padding: 30px; font-size: 12px; color: #666; border-top: 1px solid #eee; }
                        .footer a { color: #0056b3; text-decoration: none; }
                    </style>
                </head>
                <body>
                    <div class='email-container'>
                        <div class='header'>
                            <h1>&#10003; Payment Received!</h1>
                            <p>" . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . " — Fee Payment Confirmation</p>
                        </div>
                        <div class='content'>
                            <p>Dear <strong>" . htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                            <p>Your payment has been successfully received and recorded in our system.</p>
                            
                            <div class='receipt-box'>
                                <div class='receipt-title'>
                                    <h2>OFFICIAL RECEIPT</h2>
                                    <p>Receipt No: $receipt_number</p>
                                </div>
                                
                                <div class='receipt-row'>
                                    <span style='color: #666;'>Student Name:</span>
                                    <strong>" . htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8') . "</strong>
                                </div>
                                <div class='receipt-row'>
                                    <span style='color: #666;'>Admission Number:</span>
                                    <strong>" . htmlspecialchars($student['admission_number'], ENT_QUOTES, 'UTF-8') . "</strong>
                                </div>
                                <div class='receipt-row'>
                                    <span style='color: #666;'>Class:</span>
                                    <strong>" . htmlspecialchars($class_name, ENT_QUOTES, 'UTF-8') . "</strong>
                                </div>
                                <div class='receipt-row'>
                                    <span style='color: #666;'>Fee Type:</span>
                                    <strong>" . htmlspecialchars($fee_type, ENT_QUOTES, 'UTF-8') . "</strong>
                                </div>
                                <div class='receipt-row'>
                                    <span style='color: #666;'>Academic Year:</span>
                                    <strong>$year</strong>
                                </div>
                                <div class='receipt-row'>
                                    <span style='color: #666;'>Term:</span>
                                    <strong>Term $term</strong>
                                </div>
                                <div class='receipt-row'>
                                    <span style='color: #666;'>Payment Method:</span>
                                    <strong>$method</strong>
                                </div>
                                <div class='receipt-row'>
                                    <span style='color: #666;'>Payment Date:</span>
                                    <strong>$date</strong>
                                </div>
                                
                                <div class='amount-box'>
                                    <p>Amount Paid</p>
                                    <h2>GHS " . number_format($amount, 2) . "</h2>
                                </div>
                                
                                <div style='text-align: center; margin-top: 20px;'>
                                    <div class='paid-badge'>&#10003; PAID</div>
                                </div>
                            </div>
                            
                            <div class='notes'>
                                <strong>Important Notes:</strong>
                                <ul>
                                    <li>Keep this email for your records</li>
                                    <li>This receipt is valid for school clearance</li>
                                    <li>You can access this receipt anytime from the portal</li>
                                    <li>Receipt Number: <strong>$receipt_number</strong></li>
                                </ul>
                                <p>Thank you for your prompt payment!</p>
                            </div>
                        </div>
                        
                        <div class='footer'>
                            <p><strong>" . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . " — Finance Office</strong></p>
                            <p style='color: #999; margin-top: 20px;'>This is an automated email. Please do not reply to this message.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";

                $mailer->sendHTML($student['email'], "Payment Receipt - " . $receipt_number, $email_html);
            }

            // PRG: redirect after successful POST to prevent form resubmission
            $msg = "Payment recorded and receipt generated successfully. Receipt #: $receipt_number";
            header("Location: payments.php?msg=" . urlencode($msg));
            exit;

        } catch (\Throwable $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
            error_log("admin_payments.php record_payment error: " . $e->getMessage() . " | File: " . $e->getFile() . ":" . $e->getLine());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; position: relative; }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover, .close-btn:focus { color: black; text-decoration: none; cursor: pointer; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
            <?php echo renderSidebar('payments', $school_name); ?>

        <main class="main-content">
            <div class="top-bar">
                <h2>Record Payment</h2>
                <button id="openModalBtn" class="btn-primary" style="padding: 10px 20px;"><i class="fas fa-plus"></i> Record New Payment</button>
            </div>

            <?php
            // Support both direct $message/$error and redirected msg/err query params
            $displayMsg = $message ?: (isset($_GET['msg']) ? $_GET['msg'] : '');
            $displayErr = $error ?: (isset($_GET['err']) ? $_GET['err'] : '');
            ?>
            <?php if ($displayMsg): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($displayMsg); ?></div>
            <?php endif; ?>
            <?php if ($displayErr): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($displayErr); ?></div>
            <?php endif; ?>

            <!-- Payment Records List -->
            <div class="section">
                <h3>Recent Payments</h3>
                <?php
                $required_dues = (float)($settings['annual_dues_amount'] ?? 500.00);
                
                $limit = 10;
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                if ($page < 1) $page = 1;
                $offset = ($page - 1) * $limit;

                // Bridge doesn't support COUNT(*) or SUM() — fetch all, count & sum in PHP (narrow columns to prevent 413)
                $allPayments = $pdo->query("SELECT id, student_id, amount, payment_date, created_at, receipt_number, fee_type, payment_method, academic_year, transaction_reference FROM payments ORDER BY created_at DESC");
                $allPayments = $allPayments ? $allPayments->fetchAll() : [];
                // Deduplicate by receipt_number in PHP (pg-bridge doesn't support MIN()/subqueries for SQL-side dedup)
                $seen = [];
                $deduped = [];
                foreach ($allPayments as $p) {
                    $rn = $p['receipt_number'] ?? '';
                    if ($rn !== '' && isset($seen[$rn])) continue;
                    $seen[$rn] = true;
                    $deduped[] = $p;
                }
                $allPayments = $deduped;
                unset($seen, $deduped);
                $total_rows = count($allPayments);
                $total_pages = ceil($total_rows / $limit);
                $recent_payments = array_slice($allPayments, $offset, $limit);

                // Pre-compute per-student totals for the current academic year (used for balance display)
                $studentTotals = [];
                foreach ($allPayments as $p) {
                    if (($p['academic_year'] ?? '') === $current_academic_year) {
                        $sid = $p['student_id'] ?? null;
                        if ($sid) {
                            $studentTotals[(string)$sid] = ($studentTotals[(string)$sid] ?? 0) + (float)($p['amount'] ?? 0);
                        }
                    }
                }

                foreach ($recent_payments as &$payment) {
                    $s = $pdo->prepare("SELECT full_name, admission_number FROM students WHERE id = ?");
                    $s->execute([$payment['student_id']]);
                    $stu = $s->fetch();
                    if ($stu) {
                        $payment['full_name'] = $stu['full_name'];
                        $payment['admission_number'] = $stu['admission_number'];
                    } else {
                        $payment['full_name'] = 'Unknown';
                        $payment['admission_number'] = '-';
                    }
                    $payment['total_paid'] = $studentTotals[(string)$payment['student_id']] ?? 0;
                }
                unset($payment); // break reference to prevent corruption in subsequent foreach
                ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Student</th>
                                <th>Status</th>
                                <th>Trans. Ref</th>
                                <th>Amount (GHS)</th>
                                <th>Balance (GHS)</th>
                                <th>Date</th>
                                <th>Method</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $payment): 
                                $balance = max(0, $required_dues - (float)$payment['total_paid']);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($payment['full_name']); ?><br>
                                    <small><?php echo htmlspecialchars($payment['admission_number']); ?></small>
                                </td>
                                <td>
                                    <?php
                                    $status_balance = max(0, $required_dues - (float)$payment['total_paid']);
                                    if ($status_balance <= 0): ?>
                                        <span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:600;background:#d5f5e3;color:#1e8449;">PAID</span>
                                    <?php else: ?>
                                        <span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:600;background:#fef9e7;color:#b7950b;">PARTIAL</span>
                                    <?php endif; ?>
                                </td>
                                <td><small style="color:#666;"><?php echo htmlspecialchars($payment['transaction_reference'] ?? '-'); ?></small></td>
                                <td><?php echo number_format($payment['amount'], 2); ?></td>
                                <td>
                                    <span style="color: <?php echo $balance > 0 ? 'red' : 'green'; ?>; font-weight: bold;">
                                        <?php echo number_format($balance, 2); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                <td>
                                    <a href="view_receipt.php?receipt=<?php echo urlencode($payment['receipt_number']); ?>" target="_blank" class="btn-login" style="padding: 5px 10px; font-size: 0.8rem;">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div style="display:flex; justify-content:center; gap:5px; margin-top:20px; flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" style="display:inline-flex; align-items:center; gap:5px; padding:8px 16px; background:#f8f9fa; color:#000; border:1px solid #ddd; border-radius:6px; text-decoration:none; font-size:14px;">&laquo; Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" style="display:inline-flex; align-items:center; justify-content:center; min-width:38px; padding:8px 12px; background:<?php echo $i == $page ? '#1a5276' : '#f8f9fa'; ?>; color:<?php echo $i == $page ? '#fff' : '#000'; ?>; border:1px solid <?php echo $i == $page ? '#1a5276' : '#ddd'; ?>; border-radius:6px; text-decoration:none; font-size:14px; font-weight:<?php echo $i == $page ? '700' : '400'; ?>;"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" style="display:inline-flex; align-items:center; gap:5px; padding:8px 16px; background:#f8f9fa; color:#000; border:1px solid #ddd; border-radius:6px; text-decoration:none; font-size:14px;">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Record Payment Modal -->
            <div id="paymentModal" class="modal">
                <div class="modal-content">
                    <span class="close-btn">&times;</span>
                    <h3>Record Payment</h3>
                    <form action="payments.php" method="POST" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top: 15px;">
                        <input type="hidden" name="action" value="record_payment">
                        
                        <div style="grid-column: span 2;">
                            <label>Class</label>
                            <select name="class_name" id="pay_class_name" class="form-control" required>
                                <option value="">-- Select Class --</option>
                                <?php
                                $grouped = [];
                                foreach ($all_classes as $c) {
                                    $g = $c['level_group'] ?? 'other';
                                    $grouped[$g][] = $c;
                                }
                                foreach ($grouped as $group => $clist):
                                ?>
                                    <optgroup label="<?php echo htmlspecialchars(ucfirst($group)); ?>">
                                        <?php foreach ($clist as $c): ?>
                                            <option value="<?php echo htmlspecialchars($c['name']); ?>" data-class-id="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="grid-column: span 2;">
                            <label>Student Name</label>
                            <select name="student_id" id="pay_student_id" class="form-control" required>
                                <option value="">-- Select Class First --</option>
                            </select>
                            <small id="studentLookupStatus" style="margin-top:6px; font-size:0.85rem; color:#666;"></small>
                        </div>

                        <div>
                            <label>Other/Trans. No</label>
                            <input type="text" name="transaction_reference" id="pay_transaction_ref" class="form-control" placeholder="e.g. Momo ref, Bank teller no.">
                        </div>

                        <div style="grid-column: span 2;">
                            <div id="bill_summary" style="display:none; background:#f0f8ff; border:1px solid #d4e6f1; border-radius:8px; padding:15px; margin-bottom:10px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                    <strong style="font-size:16px;">Total Bill: GHS <span id="total_bill_amount">0.00</span></strong>
                                    <span id="bill_source" style="font-size:12px; color:#666;"></span>
                                </div>
                                <div style="border-top:1px solid #d4e6f1; padding-top:10px;">
                                    <label style="display:flex; align-items:center; gap:10px; margin-bottom:8px; cursor:pointer;">
                                        <input type="radio" name="payment_type" value="full" id="pay_full" checked onchange="togglePaymentType()">
                                        <strong>Full Payment</strong> <span style="color:#666; font-size:13px;">&mdash; Pay entire bill</span>
                                    </label>
                                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                        <input type="radio" name="payment_type" value="partial" id="pay_partial" onchange="togglePaymentType()">
                                        <strong>Partial Payment</strong> <span style="color:#666; font-size:13px;">&mdash; Select specific items to pay</span>
                                    </label>
                                </div>
                                <div id="partial_breakdown" style="display:none; margin-top:12px; max-height:250px; overflow-y:auto; border:1px solid #e0e0e0; border-radius:6px;">
                                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                                        <thead>
                                            <tr style="background:#f8f9fa;">
                                                <th style="padding:8px 10px; width:40px; text-align:center;">Pay</th>
                                                <th style="padding:8px 10px; text-align:left;">Fee Item</th>
                                                <th style="padding:8px 10px; text-align:center; width:80px;">Amount (GHS)</th>
                                            </tr>
                                        </thead>
                                        <tbody id="breakdown_items"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div id="no_bill_msg" style="display:none; background:#fef9e7; border:1px solid #f9e79f; border-radius:8px; padding:15px; text-align:center; color:#7d6608;">
                                No bill items found for this student in the selected term.
                            </div>
                        </div>

                        <div>
                            <label>Amount (GHS)</label>
                            <input type="number" step="0.01" name="amount" id="pay_amount" class="form-control" required readonly placeholder="Auto-calculated from bill">
                        </div>

                        <div>
                            <label>Academic Year</label>
                            <input type="text" name="academic_year" id="pay_academic_year" class="form-control" value="<?php echo htmlspecialchars($current_academic_year); ?>" required>
                        </div>

                        <div>
                            <label>Term</label>
                            <select name="term" id="pay_term" class="form-control" required>
                                <option value="1" <?php echo $current_term == '1' ? 'selected' : ''; ?>>Term 1</option>
                                <option value="2" <?php echo $current_term == '2' ? 'selected' : ''; ?>>Term 2</option>
                                <option value="3" <?php echo $current_term == '3' ? 'selected' : ''; ?>>Term 3</option>
                            </select>
                        </div>

                        <div>
                            <label>Payment Method</label>
                            <select name="payment_method" class="form-control" required>
                                <?php foreach ($payment_modes as $mode): ?>
                                    <option value="<?php echo htmlspecialchars(trim($mode)); ?>"><?php echo htmlspecialchars(trim($mode)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Payment Date</label>
                            <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <input type="hidden" name="fee_items_json" id="fee_items_json" value="">
                        <?php csrf_field(); ?>
                        <div style="grid-column: span 2; margin-top: 10px;">
                            <button type="submit" class="btn-submit" style="width:100%;">Record Payment &amp; Generate Receipt</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        // ====== Data from server ======
        var CLASSES = <?php echo json_encode($all_classes); ?>;
        var STUDENTS = <?php echo json_encode($all_students); ?>;
        var FEE_STRUCTURES = <?php echo json_encode($all_fee_structures); ?>;
        var BILL_ITEMS = <?php echo json_encode($all_bill_items); ?>;

        // ====== DOM refs ======
        var modal = document.getElementById("paymentModal");
        var btn = document.getElementById("openModalBtn");
        var span = document.getElementsByClassName("close-btn")[0];
        var classSelect = document.getElementById("pay_class_name");
        var studentSelect = document.getElementById("pay_student_id");
        var transRefInput = document.getElementById("pay_transaction_ref");
        var amountInput = document.getElementById("pay_amount");
        var academicYearInput = document.getElementById("pay_academic_year");
        var termSelect = document.getElementById("pay_term");

        // ====== Modal controls ======
        btn.onclick = function() { modal.style.display = "block"; }
        span.onclick = function() { modal.style.display = "none"; }
        window.onclick = function(event) { if (event.target == modal) { modal.style.display = "none"; } }

        // ====== Populate student dropdown when class changes ======
        classSelect.addEventListener('change', function() {
            var className = this.value;
            var statusEl = document.getElementById('studentLookupStatus');
            
            // Reset downstream fields
            studentSelect.innerHTML = '<option value="">-- Select Student --</option>';
            transRefInput.value = '';
            amountInput.value = '';

            if (!className) {
                studentSelect.innerHTML = '<option value="">-- Select Class First --</option>';
                statusEl.textContent = '';
                return;
            }

            // Filter students by class_name
            var matching = STUDENTS.filter(function(s) { return (s.class_name || '') === className; });
            matching.sort(function(a, b) { return (a.full_name || '').localeCompare(b.full_name || ''); });

            if (matching.length === 0) {
                studentSelect.innerHTML = '<option value="">-- No students in this class --</option>';
                statusEl.textContent = 'No students found for ' + className;
                return;
            }

            matching.forEach(function(s) {
                var opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.full_name + ' (' + (s.admission_number || 'no index') + ')';
                opt.setAttribute('data-admission', s.admission_number || '');
                studentSelect.appendChild(opt);
            });
            statusEl.textContent = matching.length + ' student(s) loaded for ' + className;

            // Also render bill breakdown if student was auto-selected
            renderBillBreakdown();
        });

        // ====== Render bill breakdown when student/year/term changes ======
        function renderBillBreakdown() {
            var studentId = parseInt(studentSelect.value);
            var year = academicYearInput.value;
            var term = termSelect.value;
            var billSummary = document.getElementById('bill_summary');
            var noBillMsg = document.getElementById('no_bill_msg');
            var breakdownBody = document.getElementById('breakdown_items');
            var totalBillSpan = document.getElementById('total_bill_amount');
            var billSource = document.getElementById('bill_source');
            var amountInput = document.getElementById('pay_amount');

            // Reset
            billSummary.style.display = 'none';
            noBillMsg.style.display = 'none';
            breakdownBody.innerHTML = '';

            if (!studentId || !year || !term) return;

            // Filter bill items for this student/year/term
            var items = BILL_ITEMS.filter(function(b) {
                return parseInt(b.student_id) === studentId
                    && (b.academic_year || '') === year
                    && (b.term || '') === term;
            });

            if (items.length === 0) {
                noBillMsg.style.display = 'block';
                billSummary.style.display = 'none';
                amountInput.value = '0';
                return;
            }

            billSummary.style.display = 'block';
            noBillMsg.style.display = 'none';

            var total = 0;
            items.forEach(function(item) {
                var amt = parseFloat(item.amount || 0);
                total += amt;
                var isCustom = (item.fee_structure_id === null || item.fee_structure_id === '');
                var tr = document.createElement('tr');
                tr.style.borderBottom = '1px solid #f0f0f0';
                if (isCustom) tr.style.background = '#fef9e7';
                tr.innerHTML = '<td style="padding:8px 10px;text-align:center;">'
                    + '<input type="checkbox" class="item-checkbox" checked data-title="' + htmlspecialchars(item.title || item.fee_type)
                    + '" data-type="' + htmlspecialchars(item.fee_type || 'General')
                    + '" data-amount="' + amt.toFixed(2) + '"></td>'
                    + '<td style="padding:8px 10px;">' + htmlspecialchars(item.title || item.fee_type)
                    + (isCustom ? ' <span style="font-size:10px;color:#e8a317;font-weight:600;">(Custom)</span>' : '')
                    + '</td>'
                    + '<td style="padding:8px 10px;text-align:center;font-weight:600;">' + amt.toFixed(2) + '</td>';
                breakdownBody.appendChild(tr);
            });

            totalBillSpan.textContent = total.toFixed(2);
            billSource.textContent = items.length + ' item(s)';

            // Default: Full Payment / amount = total bill
            document.getElementById('pay_full').checked = true;
            document.getElementById('partial_breakdown').style.display = 'none';
            amountInput.value = total.toFixed(2);
            amountInput.readOnly = true;
        }

        // ====== Toggle between Full and Partial Payment ======
        function togglePaymentType() {
            var isPartial = document.getElementById('pay_partial').checked;
            var breakdown = document.getElementById('partial_breakdown');
            var amountInput = document.getElementById('pay_amount');
            var totalBill = parseFloat(document.getElementById('total_bill_amount').textContent) || 0;

            if (isPartial) {
                breakdown.style.display = 'block';
                amountInput.readOnly = false;
                updatePartialTotal();
            } else {
                breakdown.style.display = 'none';
                amountInput.value = totalBill.toFixed(2);
                amountInput.readOnly = true;
            }
        }

        // ====== Update amount when partial checkboxes change ======
        function updatePartialTotal() {
            var checkboxes = document.querySelectorAll('.item-checkbox:checked');
            var total = 0;
            checkboxes.forEach(function(cb) {
                total += parseFloat(cb.getAttribute('data-amount') || 0);
            });
            document.getElementById('pay_amount').value = total.toFixed(2);
        }

        // ====== Wire up event delegation for checkbox changes ======
        document.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('item-checkbox')) {
                updatePartialTotal();
            }
        });

        // ====== Serialize selected fee items on form submit ======
        document.querySelector('#paymentModal form').addEventListener('submit', function() {
            var checkboxes = document.querySelectorAll('.item-checkbox');
            var items = [];
            checkboxes.forEach(function(cb) {
                if (cb.checked) {
                    items.push({
                        fee_type: cb.getAttribute('data-type'),
                        fee_title: cb.getAttribute('data-title'),
                        amount: parseFloat(cb.getAttribute('data-amount'))
                    });
                }
            });
            document.getElementById('fee_items_json').value = JSON.stringify(items);
        });

        // ====== Render bill breakdown when student is selected ======
        studentSelect.addEventListener('change', function() {
            renderBillBreakdown();
        });

        // ====== Refresh on year/term changes ======
        academicYearInput.addEventListener('change', renderBillBreakdown);
        academicYearInput.addEventListener('input', renderBillBreakdown);
        termSelect.addEventListener('change', renderBillBreakdown);

        // ====== Helper: htmlspecialchars for JS ======
        function htmlspecialchars(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(String(str)));
            return div.innerHTML;
        }
    </script>
</body>
</html>
