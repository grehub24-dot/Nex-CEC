<?php
require_once 'includes/db.php';
require_once 'includes/ReceiptGenerator.php';
require_once 'includes/SMSHelper.php';
require_once 'includes/Mailer.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

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

// Basic School fee types
$fee_types = explode(',', $settings['fee_types'] ?? 'Tuition,PTA Levy,Sports & Culture,ICT,Examination,Development,Feeding,Transport,Uniform,Books & Materials');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    $index_number = sanitize($_POST['index_number']);
    $class_name = sanitize($_POST['class_name']);
    $amount = floatval($_POST['amount']);
    $year = sanitize($_POST['academic_year']);
    $term = sanitize($_POST['term']);
    $fee_type = sanitize($_POST['fee_type']);
    $method = sanitize($_POST['payment_method']);
    $date = sanitize($_POST['payment_date']);

    // Find Student (two-step lookup for Supabase compatibility)
    $stmt = $pdo->prepare("SELECT * FROM students WHERE index_number = ?");
    $stmt->execute([$index_number]);
    $student = $stmt->fetch();
    if ($student) {
        $u = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $u->execute([$student['user_id']]);
        $urow = $u->fetch();
        $student['email'] = $urow ? $urow['email'] : '';
    }

    if (!$student) {
        $error = "Student with Index Number $index_number not found.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Generate Receipt Number: SCHOOL + YEAR + MONTH + RANDOM
            $receipt_number = strtoupper(substr(preg_replace('/[^A-Z]/', '', $school_name), 0, 4)) . "-" . date('ym') . "-" . rand(1000, 9999);

            // Insert Payment
            $stmt = $pdo->prepare("INSERT INTO payments (student_id, amount, academic_year, semester, payment_method, payment_date, receipt_number, recorded_by, fee_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$student['id'], $amount, $year, $term, $method, $date, $receipt_number, $_SESSION['user_id'], $fee_type]);
            $payment_id = $pdo->lastInsertId();

            // Generate Receipt
            $generator = new ReceiptGenerator();
            
            // Fetch student total paid for the year
            $stmt_paid = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE student_id = ? AND academic_year = ?");
            $stmt_paid->execute([$student['id'], $year]);
            $total_paid = (float)$stmt_paid->fetchColumn();
            
            // Fetch required dues from settings
            $stmt_settings = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'annual_dues_amount'");
            $settings_dues = $stmt_settings->fetchColumn();
            $required_dues = $settings_dues !== false ? (float)$settings_dues : 500.00;
            
            $current_balance = max(0, $required_dues - $total_paid);

            $receipt_path = $generator->generate($payment_id, $receipt_number, $student, $amount, $date, $class_name, $fee_type, $school_name, $current_balance, $year, $term, $method);
            
            // Save Receipt Record
            $hash = md5($receipt_number . $payment_id . 'SALT');
            $stmt = $pdo->prepare("INSERT INTO receipts (payment_id, receipt_file_path, verification_hash) VALUES (?, ?, ?)");
            $stmt->execute([$payment_id, $receipt_path, $hash]);

            $pdo->commit();
            $message = "Payment recorded and receipt generated successfully. Receipt #: $receipt_number";
            
            // Send SMS notification to student phone
            if (!empty($student['phone_number'])) {
                $sms = new SMSHelper();
                $sms_message = "Hello {$student['full_name']}, payment of GHS " . number_format($amount, 2) . " for $fee_type ($year Term $term) received. Receipt: $receipt_number.";
                $sms->send($student['phone_number'], $sms_message);
            }

            // Send SMS to guardian
            if (!empty($student['guardian_phone'])) {
                $sms = new SMSHelper();
                $sms_message = "Payment alert: GHS " . number_format($amount, 2) . " received for {$student['full_name']} - $fee_type ($year Term $term). Receipt: $receipt_number.";
                $sms->send($student['guardian_phone'], $sms_message);
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
                                    <span style='color: #666;'>Index Number:</span>
                                    <strong>" . htmlspecialchars($student['index_number'], ENT_QUOTES, 'UTF-8') . "</strong>
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

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Record Payment — <?php echo htmlspecialchars($school_name); ?> Admin</title>
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
                <h2>Record Payment</h2>
                <button id="openModalBtn" class="btn-primary" style="padding: 10px 20px;"><i class="fas fa-plus"></i> Record New Payment</button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Payment Records List -->
            <div class="section">
                <h3>Recent Payments</h3>
                <?php
                $stmt_settings = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'annual_dues_amount'");
                $settings_dues = $stmt_settings->fetchColumn();
                $required_dues = $settings_dues !== false ? (float)$settings_dues : 500.00;
                
                $limit = 10;
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                if ($page < 1) $page = 1;
                $offset = ($page - 1) * $limit;

                $stmt = $pdo->prepare("SELECT * FROM payments ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
                $stmt->execute();
                $recent_payments = $stmt->fetchAll();

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
                    $tp = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE student_id = ? AND academic_year = ?");
                    $tp->execute([$payment['student_id'], $current_academic_year]);
                    $payment['total_paid'] = (float)$tp->fetchColumn();
                }

                $total_stmt = $pdo->query("SELECT COUNT(*) FROM payments");
                $total_rows = (int)$total_stmt->fetchColumn();
                $total_pages = ceil($total_rows / $limit);
                ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Student</th>
                                <th>Fee Type</th>
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
                                    <small><?php echo htmlspecialchars($payment['index_number']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($payment['fee_type'] ?? 'General'); ?></td>
                                <td><?php echo number_format($payment['amount'], 2); ?></td>
                                <td>
                                    <span style="color: <?php echo $balance > 0 ? 'red' : 'green'; ?>; font-weight: bold;">
                                        <?php echo number_format($balance, 2); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                <td>
                                    <a href="../receipts/receipt_<?php echo htmlspecialchars($payment['receipt_number']); ?>.html" target="_blank" class="btn-login" style="padding: 5px 10px; font-size: 0.8rem;">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div style="display: flex; justify-content: center; margin-top: 20px; gap: 5px;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="btn-login" style="background: #f8f9fa; color: #333; border: 1px solid #ddd;">&laquo; Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="btn-login" style="<?php echo $i == $page ? 'background: var(--primary-color);' : 'background: #f8f9fa; color: #333; border: 1px solid #ddd;'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="btn-login" style="background: #f8f9fa; color: #333; border: 1px solid #ddd;">Next &raquo;</a>
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
                            <label>Student Index Number</label>
                            <input type="text" name="index_number" class="form-control" required placeholder="e.g. NXC/2026/001">
                            <small id="indexLookupStatus" style="display:none; margin-top:6px; font-size:0.85rem;"></small>
                        </div>

                        <div>
                            <label>Class</label>
                            <select name="class_name" class="form-control" required>
                                <option value="">-- Select Class --</option>
                                <optgroup label="Early Childhood">
                                    <option value="Creche">Creche</option>
                                    <option value="Nursery">Nursery</option>
                                    <option value="KG 1">KG 1</option>
                                    <option value="KG 2">KG 2</option>
                                </optgroup>
                                <optgroup label="Primary">
                                    <option value="Basic 1">Basic 1</option>
                                    <option value="Basic 2">Basic 2</option>
                                    <option value="Basic 3">Basic 3</option>
                                    <option value="Basic 4">Basic 4</option>
                                    <option value="Basic 5">Basic 5</option>
                                    <option value="Basic 6">Basic 6</option>
                                </optgroup>
                                <optgroup label="Junior High School">
                                    <option value="JHS 1">JHS 1</option>
                                    <option value="JHS 2">JHS 2</option>
                                    <option value="JHS 3">JHS 3</option>
                                </optgroup>
                            </select>
                        </div>

                        <div>
                            <label>Fee Type</label>
                            <select name="fee_type" class="form-control" required>
                                <option value="">-- Select Fee --</option>
                                <?php foreach ($fee_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars(trim($type)); ?>"><?php echo htmlspecialchars(trim($type)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Amount (GHS)</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>

                        <div>
                            <label>Academic Year</label>
                            <input type="text" name="academic_year" class="form-control" value="<?php echo htmlspecialchars($current_academic_year); ?>" required>
                        </div>

                        <div>
                            <label>Term</label>
                            <select name="term" class="form-control" required>
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

                        <div style="grid-column: span 2; margin-top: 10px;">
                            <button type="submit" class="btn-submit" style="width:100%;">Record Payment &amp; Generate Receipt</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        const modal = document.getElementById("paymentModal");
        const btn = document.getElementById("openModalBtn");
        const span = document.getElementsByClassName("close-btn")[0];
        const indexInput = document.querySelector('input[name="index_number"]');
        const classSelect = document.querySelector('select[name="class_name"]');
        const lookupStatus = document.getElementById('indexLookupStatus');
        let lookupTimer = null;
        let lastLookupValue = '';

        btn.onclick = function() { modal.style.display = "block"; }
        span.onclick = function() { modal.style.display = "none"; }
        window.onclick = function(event) { if (event.target == modal) { modal.style.display = "none"; } }

        function setLookupStatus(message, color) {
            if (!lookupStatus) return;
            if (!message) { lookupStatus.style.display = 'none'; lookupStatus.textContent = ''; return; }
            lookupStatus.style.display = 'block';
            lookupStatus.style.color = color;
            lookupStatus.textContent = message;
        }

        function lookupStudent(force = false) {
            if (!indexInput) return;
            const rawValue = indexInput.value || '';
            const indexNumber = rawValue.replace(/\s+/g, '').toUpperCase();
            indexInput.value = indexNumber;
            if (!indexNumber) { lastLookupValue = ''; setLookupStatus('', ''); return; }
            if (!force && (indexNumber.length < 3 || indexNumber === lastLookupValue)) return;
            lastLookupValue = indexNumber;
            setLookupStatus('Fetching student details...', '#0c5fb5');

            fetch(`../api/api/admin/get_student_by_index.php?index_number=${encodeURIComponent(indexNumber)}`, {
                headers: { 'Accept': 'application/json' }
            })
                .then(async response => {
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok || !payload.ok || !payload.student) throw new Error(payload.error || 'Student not found');
                    return payload.student;
                })
                .then(student => {
                    if (classSelect && student.class_name) {
                        for (let i = 0; i < classSelect.options.length; i++) {
                            if (classSelect.options[i].value === student.class_name) {
                                classSelect.selectedIndex = i;
                                break;
                            }
                        }
                    }
                    setLookupStatus(`Loaded: ${student.full_name} (${student.index_number})`, '#15803d');
                })
                .catch(() => {
                    setLookupStatus('No student found for this index number.', '#b42333');
                });
        }

        if (indexInput) {
            indexInput.addEventListener('input', function() {
                if (lookupTimer) clearTimeout(lookupTimer);
                lookupTimer = setTimeout(function() { lookupStudent(false); }, 300);
            });
            indexInput.addEventListener('blur', function() { lookupStudent(true); });
            indexInput.addEventListener('keydown', function(event) {
                if (event.key === 'Enter') { event.preventDefault(); lookupStudent(true); }
            });
        }
    </script>
</body>
</html>
