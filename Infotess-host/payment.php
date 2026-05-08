<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'api/includes/db.php';

$error = '';
$student = null;
$pendingPayment = $_SESSION['payment_pending'] ?? null;
$successData = isset($_SESSION['payment_success']) ? $_SESSION['payment_success'] : null;

// Step 0: Auto-load from enroll session if coming from enrollment form
if (!isset($_POST['enrollment_id']) && !$student && isset($_SESSION['enrollment'])) {
    $enr = $_SESSION['enrollment'];
    if (isset($enr['student_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$enr['student_id']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Step 1: Enrollment ID lookup (GET from enroll_form or POST)
if (isset($_GET['enrollment_id']) || isset($_POST['enrollment_id'])) {
    $enrollmentId = strtoupper(trim($_GET['enrollment_id'] ?? $_POST['enrollment_id']));
    if (!preg_match('/^ENR-\d{4}-[A-Z0-9]{6}$/', $enrollmentId)) {
        $error = 'Invalid enrollment ID format. Use ENR-YYYY-XXXXXX (e.g., ENR-2026-A3K7B2)';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE enrollment_id = ?");
        $stmt->execute([$enrollmentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$student) {
            $error = 'Invalid enrollment ID. No student found with this ID.';
        } else {
            $_SESSION['payment_student'] = $student;
            unset($_SESSION['payment_pending']);
        }
    }
}

// Load student from session if available
if (isset($_SESSION['payment_student']) && !$student) {
    $student = $_SESSION['payment_student'];
}

$amount = 150.00;

// Step 2: Handle payment method submission
if (isset($_POST['payment_method']) && $student) {
    $method = $_POST['payment_method'];
    $phone = trim($_POST['phone'] ?? '');
    $studentId = $student['id'];

    if (($method === 'momo' || $method === 'telecel') && (empty($phone) || !preg_match('/^0[0-9]{9}$/', $phone))) {
        $error = 'Please enter a valid 10-digit phone number starting with 0.';
    }

    if (empty($error)) {
        $receiptNumber = 'NXC-' . time() . '-' . rand(1000, 9999);
        try {
            $stmt = $pdo->prepare("INSERT INTO payments (student_id, amount, payment_method, payment_date, receipt_number, status, enrollment_id) VALUES (?, ?, ?, NOW(), ?, 'pending', ?)");
            $stmt->execute([$studentId, $amount, $method, $receiptNumber, $student['enrollment_id']]);
            $paymentId = $pdo->lastInsertId();

            $_SESSION['payment_pending'] = [
                'payment_id' => $paymentId,
                'student_id' => $studentId,
                'receipt_number' => $receiptNumber,
                'method' => $method,
                'phone' => $phone,
                'amount' => $amount,
                'enrollment_id' => $student['enrollment_id'],
                'full_name' => $student['full_name'],
                'class_name' => $student['class_name']
            ];

            header($method === 'bank' ? 'Location: payment.php?voucher=1' : 'Location: payment.php?processing=1');
            exit;
        } catch (Exception $e) {
            $error = 'Payment initiation failed. Please try again.';
        }
    }
}

// Step 3: Confirm MoMo/Telecel payment
if (isset($_POST['confirm_payment']) && isset($_SESSION['payment_pending'])) {
    $pending = $_SESSION['payment_pending'];
    try {
        $pdo->prepare("UPDATE payments SET status = 'completed' WHERE id = ?")->execute([$pending['payment_id']]);

        // Generate admission number
        $today = date('ymd');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE admission_number LIKE ?");
        $stmt->execute(["CEC-{$today}-%"]);
        $counter = str_pad($stmt->fetchColumn() + 1, 3, '0', STR_PAD_LEFT);
        $admissionNumber = "CEC-{$today}-{$counter}";

        // Update student
        $pdo->prepare("UPDATE students SET admission_number = ?, payment_status = 'paid', status = 'enrolled' WHERE id = ?")
            ->execute([$admissionNumber, $pending['student_id']]);

        $_SESSION['payment_success'] = [
            'student_name' => $pending['full_name'],
            'admission_number' => $admissionNumber,
            'receipt_number' => $pending['receipt_number'],
            'amount' => $pending['amount'],
            'class_name' => $pending['class_name'],
            'enrollment_id' => $pending['enrollment_id'],
            'guardian_email' => $student['guardian_email'] ?? '',
            'gender' => $student['gender'] ?? '',
            'date_of_birth' => $student['date_of_birth'] ?? '',
            'guardian_name' => $student['guardian_name'] ?? '',
            'guardian_phone_primary' => $student['guardian_phone_primary'] ?? '',
            'address' => $student['address'] ?? ''
        ];

        // Re-fetch updated student for printable form
        $stmt2 = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt2->execute([$pending['student_id']]);
        $_SESSION['payment_success']['student_data'] = $stmt2->fetch(PDO::FETCH_ASSOC);

        unset($_SESSION['payment_pending'], $_SESSION['payment_student'], $_SESSION['enrollment']);
        header('Location: payment.php?success=1');
        exit;
    } catch (Exception $e) {
        $error = 'Payment confirmation failed. Please contact administration.';
    }
}

// Step 3b: Confirm Bank/Cash payment
if (isset($_POST['confirm_bank_payment']) && isset($_SESSION['payment_pending'])) {
    $pending = $_SESSION['payment_pending'];
    try {
        $pdo->prepare("UPDATE payments SET status = 'completed' WHERE id = ?")->execute([$pending['payment_id']]);

        $today = date('ymd');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE admission_number LIKE ?");
        $stmt->execute(["CEC-{$today}-%"]);
        $counter = str_pad($stmt->fetchColumn() + 1, 3, '0', STR_PAD_LEFT);
        $admissionNumber = "CEC-{$today}-{$counter}";

        $pdo->prepare("UPDATE students SET admission_number = ?, payment_status = 'paid', status = 'enrolled' WHERE id = ?")
            ->execute([$admissionNumber, $pending['student_id']]);

        $_SESSION['payment_success'] = [
            'student_name' => $pending['full_name'],
            'admission_number' => $admissionNumber,
            'receipt_number' => $pending['receipt_number'],
            'amount' => $pending['amount'],
            'class_name' => $pending['class_name'],
            'enrollment_id' => $pending['enrollment_id']
        ];

        unset($_SESSION['payment_pending'], $_SESSION['payment_student'], $_SESSION['enrollment']);
        header('Location: payment.php?success=1');
        exit;
    } catch (Exception $e) {
        $error = 'Payment confirmation failed. Please contact administration.';
    }
}

// Load success/pending data for display
if (isset($_GET['success']) && isset($_SESSION['payment_success'])) {
    $successData = $_SESSION['payment_success'];
}
if (isset($_GET['processing']) && isset($_SESSION['payment_pending'])) {
    $pendingPayment = $_SESSION['payment_pending'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment — Nex CEC Basic School</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; color: #333; line-height: 1.6; }
        .header { background: linear-gradient(135deg, #1a5276, #2e86c1); color: white; padding: 24px 0; text-align: center; }
        .header h1 { font-size: 22px; }
        .header p { font-size: 14px; opacity: 0.85; margin-top: 4px; }
        .container { max-width: 960px; margin: 30px auto; padding: 0 20px; }
        .card { background: #fff; border-radius: 12px; padding: 28px; margin-bottom: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
        .error { background: #fde8e8; color: #c0392b; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #e74c3c; }
        .success-msg { background: #e8f5e9; color: #27ae60; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #27ae60; }

        /* Step 1: Enrollment Entry */
        .enrollment-entry { max-width: 480px; margin: 60px auto; text-align: center; }
        .enrollment-entry i.enroll-icon { font-size: 48px; color: #1a5276; margin-bottom: 16px; }
        .enrollment-entry h2 { color: #1a5276; margin-bottom: 8px; }
        .enrollment-entry p { color: #666; margin-bottom: 24px; }
        .form-group { margin-bottom: 18px; text-align: left; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #555; font-size: 14px; }
        .form-group input { width: 100%; padding: 14px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: border-color 0.2s; }
        .form-group input:focus { outline: none; border-color: #1a5276; }
        .btn-primary { background: #1a5276; color: #fff; border: none; padding: 14px 32px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-primary:hover { background: #154360; }
        .btn-success { background: #27ae60; color: #fff; border: none; padding: 14px 32px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-success:hover { background: #219a52; }
        .btn-secondary { background: #0066cc; color: #fff; border: none; padding: 14px 32px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-secondary:hover { background: #0052a3; }

        /* Student info */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .info-item { padding: 12px; background: #f8f9fa; border-radius: 8px; }
        .info-label { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { font-weight: 700; color: #1a5276; font-size: 16px; }
        .amount-big { font-size: 32px; color: #1a5276; font-weight: 800; text-align: center; margin: 12px 0; }

        /* Payment cards */
        .payment-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 24px; }
        @media (max-width: 768px) { .payment-cards { grid-template-columns: 1fr; } .info-grid { grid-template-columns: 1fr; } }
        .payment-card { border-radius: 12px; padding: 24px; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.06); border-top: 4px solid; }
        .payment-card.momo { border-top-color: #f1c40f; }
        .payment-card.telecel { border-top-color: #e74c3c; }
        .payment-card.bank { border-top-color: #3498db; }
        .card-header { display: flex; align-items: center; margin-bottom: 18px; }
        .card-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; margin-right: 14px; font-weight: 700; }
        .momo .card-icon { background: #f1c40f; color: #333; }
        .telecel .card-icon { background: #e74c3c; color: #fff; }
        .bank .card-icon { background: #3498db; color: #fff; }
        .card-title { font-size: 17px; font-weight: 600; }
        .btn-pay { width: 100%; padding: 14px; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 12px; }
        .momo .btn-pay { background: #f1c40f; color: #333; }
        .telecel .btn-pay { background: #e74c3c; color: #fff; }
        .bank .btn-pay { background: #3498db; color: #fff; }

        /* Processing */
        .processing-view { text-align: center; padding: 50px 20px; }
        .spinner { border: 4px solid #e8e8e8; border-top: 4px solid #1a5276; border-radius: 50%; width: 56px; height: 56px; animation: spin 1s linear infinite; margin: 0 auto 20px; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Voucher */
        .voucher { max-width: 580px; margin: 24px auto; border: 2px solid #1a5276; border-radius: 10px; overflow: hidden; }
        .voucher-head { background: #1a5276; color: #fff; padding: 20px; text-align: center; }
        .voucher-body { padding: 24px; }
        .voucher table { width: 100%; border-collapse: collapse; }
        .voucher td { padding: 10px 0; border-bottom: 1px solid #eee; }
        .voucher td:first-child { font-weight: 600; color: #555; width: 42%; }
        .voucher-footer { padding: 16px 24px; background: #f8f9fa; text-align: center; font-size: 14px; color: #666; }

        /* Success */
        .success-view { text-align: center; padding: 40px 20px; }
        .checkmark-circle { width: 80px; height: 80px; border-radius: 50%; background: #27ae60; color: #fff; font-size: 40px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .success-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 24px; }

        /* Printable sections */
        .printable { display: none; }
        .printable-admission { max-width: 560px; margin: 24px auto; padding: 32px; border: 2px solid #1a5276; text-align: left; }
        .printable-form { max-width: 700px; margin: 24px auto; padding: 32px; border: 2px solid #1a5276; text-align: left; }

        @media print {
            body * { visibility: hidden !important; }
            .printable { visibility: visible !important; display: block !important; position: absolute; left: 0; top: 0; width: 100%; border: none !important; }
            .printable * { visibility: visible !important; }
            .voucher { visibility: visible !important; display: block !important; position: absolute; left: 0; top: 0; width: 100%; }
            .voucher * { visibility: visible !important; }
        }
    </style>
</head>
<body>
<div class="header">
    <h1>Nex CEC Basic School</h1>
    <p>Student Enrollment Payment</p>
</div>

<div class="container">
    <?php if ($error): ?>
        <div class="error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- STEP 4: Payment Success -->
    <?php if ($successData): ?>
        <div class="success-view">
            <div class="checkmark-circle"><i class="fas fa-check"></i></div>
            <h2>Payment Successful!</h2>
            <p style="color:#666;">Your enrollment is now complete.</p>

            <div class="card" style="text-align:left; max-width:500px; margin:24px auto;">
                <h3 style="color:#1a5276; margin-bottom:16px;">Admission Details</h3>
                <div class="info-grid">
                    <div class="info-item"><div class="info-label">Student Name</div><div class="info-value"><?php echo htmlspecialchars($successData['student_name']); ?></div></div>
                    <div class="info-item"><div class="info-label">Admission Number</div><div class="info-value"><?php echo htmlspecialchars($successData['admission_number']); ?></div></div>
                    <div class="info-item"><div class="info-label">Class</div><div class="info-value"><?php echo htmlspecialchars($successData['class_name']); ?></div></div>
                    <div class="info-item"><div class="info-label">Enrollment ID</div><div class="info-value"><?php echo htmlspecialchars($successData['enrollment_id']); ?></div></div>
                    <div class="info-item"><div class="info-label">Amount Paid</div><div class="info-value">GHS <?php echo number_format($successData['amount'], 2); ?></div></div>
                    <div class="info-item"><div class="info-label">Receipt Number</div><div class="info-value"><?php echo htmlspecialchars($successData['receipt_number']); ?></div></div>
                </div>
            </div>

            <div class="success-actions">
                <button onclick="togglePrint('printable-admission')" class="btn-primary"><i class="fas fa-id-card"></i> Print Admission Card</button>
                <button onclick="togglePrint('printable-form')" class="btn-secondary"><i class="fas fa-file-alt"></i> Print Enrollment Form</button>
            </div>
            <div style="margin-top:20px;"><a href="index.php" style="color:#1a5276;"><i class="fas fa-home"></i> Back to Home</a></div>

            <!-- Printable Admission Card -->
            <div class="printable printable-admission" id="printable-admission">
                <div style="text-align:center; margin-bottom:24px;">
                    <h1 style="color:#1a5276;">Nex CEC Basic School</h1>
                    <p style="font-size:18px; color:#666;">Admission Card</p>
                    <p style="font-size:13px; color:#999;">Academic Year <?php echo date('Y') . '/' . (date('Y')+1); ?></p>
                </div>
                <table style="width:100%; font-size:14px;">
                    <tr><td style="padding:8px; font-weight:700; width:40%; border-bottom:1px solid #eee;">Student Name:</td><td style="padding:8px; border-bottom:1px solid #eee;"><?php echo htmlspecialchars($successData['student_name']); ?></td></tr>
                    <tr><td style="padding:8px; font-weight:700; border-bottom:1px solid #eee;">Admission Number:</td><td style="padding:8px; border-bottom:1px solid #eee;"><?php echo htmlspecialchars($successData['admission_number']); ?></td></tr>
                    <tr><td style="padding:8px; font-weight:700; border-bottom:1px solid #eee;">Class:</td><td style="padding:8px; border-bottom:1px solid #eee;"><?php echo htmlspecialchars($successData['class_name']); ?></td></tr>
                    <tr><td style="padding:8px; font-weight:700; border-bottom:1px solid #eee;">Gender:</td><td style="padding:8px; border-bottom:1px solid #eee;"><?php echo htmlspecialchars($successData['gender'] ?? ''); ?></td></tr>
                    <tr><td style="padding:8px; font-weight:700; border-bottom:1px solid #eee;">Guardian:</td><td style="padding:8px; border-bottom:1px solid #eee;"><?php echo htmlspecialchars($successData['guardian_name'] ?? ''); ?></td></tr>
                    <tr><td style="padding:8px; font-weight:700; border-bottom:1px solid #eee;">Amount Paid:</td><td style="padding:8px; border-bottom:1px solid #eee;">GHS <?php echo number_format($successData['amount'], 2); ?></td></tr>
                    <tr><td style="padding:8px; font-weight:700; border-bottom:1px solid #eee;">Status:</td><td style="padding:8px; border-bottom:1px solid #eee; color:#27ae60; font-weight:700;">ENROLLED</td></tr>
                </table>
                <p style="text-align:center; margin-top:24px; font-size:12px; color:#999;">Date Issued: <?php echo date('F j, Y'); ?></p>
            </div>

            <!-- Printable Enrollment Form -->
            <div class="printable printable-form" id="printable-form">
                <div style="text-align:center; margin-bottom:24px;">
                    <h1 style="color:#1a5276;">Nex CEC Basic School</h1>
                    <p style="font-size:18px; color:#666;">Enrollment Confirmation Form</p>
                </div>
                <h4 style="color:#1a5276; margin-bottom:12px;">Student Information</h4>
                <?php
                $sd = $successData['student_data'] ?? [];
                ?>
                <table style="width:100%; font-size:13px; margin-bottom:20px;">
                    <tr><td style="padding:6px; font-weight:700; width:35%; border-bottom:1px solid #eee;">Full Name:</td><td style="padding:6px; border-bottom:1px solid #eee;"><?php echo htmlspecialchars($sd['full_name'] ?? $successData['student_name']); ?></td></tr>
                    <tr><td style="padding:6px; font-weight:700; border-bottom:1px solid #eee;">Admission Number:</td><td style="padding:6px; border-bottom:1px solid #eee;"><?php echo htmlspecialchars($successData['admission_number']); ?></td></tr>
                    <tr><td style="padding:6px; font-weight:700; border-bottom:1px solid #eee;">Class:</td><td style="padding:6px; border-bottom:1px solid #eee;"><?php echo htmlspecialchars($sd['class_name'] ?? $successData['class_name']); ?></td></tr>
                    <tr><td style="padding:6px; font-weight:700; border-bottom:1px solid #eee;">Gender:</td><td style="padding:6px; border-bottom:1px solid #eee;"><?php echo htmlspecialchars($sd['gender'] ?? ''); ?></td></tr>
                    <tr><td style="padding:6px; font-weight:700; border-bottom:1px solid #eee;">Date of Birth:</td><td style="padding:6px; border-bottom:1px solid #eee;"><?php echo htmlspecialchars($sd['date_of_birth'] ?? ''); ?></td></tr>
                    <tr><td style="padding:6px; font-weight:700; border-bottom:1px solid #eee;">Address:</td><td style="padding:6px; border-bottom:1px solid #eee;"><?php echo htmlspecialchars($sd['address'] ?? ''); ?></td></tr>
                </table>
                <h4 style="color:#1a5276; margin-bottom:12px;">Guardian Information</h4>
                <table style="width:100%; font-size:13px; margin-bottom:20px;">
                    <tr><td style="padding:6px; font-weight:700; width:35%; border-bottom:1px solid #eee;">Guardian Name:</td><td style="padding:6px; border-bottom:1px solid #eee;"><?php echo htmlspecialchars($sd['guardian_name'] ?? ''); ?></td></tr>
                    <tr><td style="padding:6px; font-weight:700; border-bottom:1px solid #eee;">Relationship:</td><td style="padding:6px; border-bottom:1px solid #eee;"><?php echo htmlspecialchars($sd['guardian_relationship'] ?? ''); ?></td></tr>
                    <tr><td style="padding:6px; font-weight:700; border-bottom:1px solid #eee;">Phone:</td><td style="padding:6px; border-bottom:1px solid #eee;"><?php echo htmlspecialchars($sd['guardian_phone_primary'] ?? ''); ?></td></tr>
                    <tr><td style="padding:6px; font-weight:700; border-bottom:1px solid #eee;">Email:</td><td style="padding:6px; border-bottom:1px solid #eee;"><?php echo htmlspecialchars($sd['guardian_email'] ?? ''); ?></td></tr>
                </table>
                <div style="text-align:center; margin-top:16px;">
                    <p style="font-weight:700; color:#27ae60;">PAYMENT STATUS: PAID</p>
                    <p>Receipt: <?php echo htmlspecialchars($successData['receipt_number']); ?> | Date: <?php echo date('F j, Y'); ?></p>
                </div>
            </div>

        </div>

    <!-- STEP 3a: MoMo/Telecel Processing -->
    <?php elseif (isset($_GET['processing']) && $pendingPayment): ?>
        <div class="card processing-view">
            <div class="spinner"></div>
            <h2 style="color:#1a5276;">Processing Payment...</h2>
            <p style="margin:12px 0; color:#666;">Check your phone <strong><?php echo htmlspecialchars($pendingPayment['phone']); ?></strong> to complete the <strong><?php echo $pendingPayment['method'] === 'momo' ? 'MTN MoMo' : 'Telecel Cash'; ?></strong> payment.</p>
            <form method="POST" style="margin-top:24px;">
                <input type="hidden" name="confirm_payment" value="1">
                <button type="submit" class="btn-primary"><i class="fas fa-check-circle"></i> I've Completed the Payment</button>
            </form>
        </div>

    <!-- STEP 3b: Bank/Cash Voucher -->
    <?php elseif (isset($_GET['voucher']) && $pendingPayment): ?>
        <div style="text-align:center; margin-bottom:20px;">
            <button onclick="window.print()" class="btn-secondary" style="margin-right:10px;"><i class="fas fa-print"></i> Print Voucher</button>
            <form method="POST" style="display:inline-block;">
                <input type="hidden" name="confirm_bank_payment" value="1">
                <button type="submit" class="btn-success"><i class="fas fa-check-circle"></i> I've Made the Payment</button>
            </form>
        </div>

        <div class="voucher printable" id="printable-voucher">
            <div class="voucher-head">
                <div style="font-size:36px; margin-bottom:8px;">&#127970;</div>
                <h2>Nex CEC Basic School</h2>
                <p style="opacity:0.85;">Payment Voucher</p>
            </div>
            <div class="voucher-body">
                <table>
                    <tr><td>Voucher Number:</td><td><?php echo htmlspecialchars($pendingPayment['receipt_number']); ?></td></tr>
                    <tr><td>Date:</td><td><?php echo date('F j, Y — g:i A'); ?></td></tr>
                    <tr><td>Enrollment ID:</td><td><?php echo htmlspecialchars($pendingPayment['enrollment_id']); ?></td></tr>
                    <tr><td>Student Name:</td><td><?php echo htmlspecialchars($pendingPayment['full_name']); ?></td></tr>
                    <tr><td>Class:</td><td><?php echo htmlspecialchars($pendingPayment['class_name']); ?></td></tr>
                    <tr><td style="font-size:16px; font-weight:700; color:#1a5276;">Amount:</td><td style="font-size:16px; font-weight:700; color:#1a5276;">GHS <?php echo number_format($pendingPayment['amount'], 2); ?></td></tr>
                    <tr><td>Payment Method:</td><td>Bank / Cash</td></tr>
                </table>
            </div>
            <div class="voucher-footer">
                <p><strong>Instructions:</strong> Bring this voucher to the school office with your payment.</p>
                <p style="font-size:12px; margin-top:6px;">Nex CEC Basic School &bull; Payment Confirmation Required</p>
            </div>
        </div>

    <!-- STEP 2: Payment Method Selection -->
    <?php elseif ($student): ?>
        <div class="card">
            <h3 style="color:#1a5276; margin-bottom:16px;">Student Information</h3>
            <div class="info-grid">
                <div class="info-item"><div class="info-label">Student Name</div><div class="info-value"><?php echo htmlspecialchars($student['full_name']); ?></div></div>
                <div class="info-item"><div class="info-label">Class</div><div class="info-value"><?php echo htmlspecialchars($student['class_name']); ?></div></div>
                <div class="info-item"><div class="info-label">Enrollment ID</div><div class="info-value" style="font-family:monospace; font-size:15px;"><?php echo htmlspecialchars($student['enrollment_id']); ?></div></div>
                <div class="info-item"><div class="info-label">Guardian Email</div><div class="info-value" style="font-size:13px;"><?php echo htmlspecialchars($student['guardian_email']); ?></div></div>
            </div>
            <div class="amount-big">GHS <?php echo number_format($amount, 2); ?></div>
            <p style="text-align:center; color:#888; font-size:14px;">Enrollment Fee</p>
        </div>

        <h3 style="color:#1a5276; text-align:center; margin:24px 0;">Select Payment Method</h3>

        <div class="payment-cards">
            <div class="payment-card momo">
                <div class="card-header">
                    <div class="card-icon">M</div>
                    <div class="card-title">MTN MoMo</div>
                </div>
                <form method="POST">
                    <input type="hidden" name="payment_method" value="momo">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" placeholder="024 000 0000" value="<?php echo htmlspecialchars($student['guardian_phone_primary'] ?? ''); ?>" required>
                    </div>
                    <button type="submit" class="btn-pay"><i class="fas fa-mobile-alt"></i> Pay with MoMo</button>
                </form>
            </div>

            <div class="payment-card telecel">
                <div class="card-header">
                    <div class="card-icon">T</div>
                    <div class="card-title">Telecel Cash</div>
                </div>
                <form method="POST">
                    <input type="hidden" name="payment_method" value="telecel">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" placeholder="020 000 0000" value="<?php echo htmlspecialchars($student['guardian_phone_primary'] ?? ''); ?>" required>
                    </div>
                    <button type="submit" class="btn-pay"><i class="fas fa-mobile-alt"></i> Pay with Telecel</button>
                </form>
            </div>

            <div class="payment-card bank">
                <div class="card-header">
                    <div class="card-icon">B</div>
                    <div class="card-title">Bank / Cash</div>
                </div>
                <p style="font-size:14px; color:#666; margin-bottom:12px;">Pay at the school office. Print a voucher to bring with you.</p>
                <form method="POST">
                    <input type="hidden" name="payment_method" value="bank">
                    <button type="submit" class="btn-pay"><i class="fas fa-print"></i> Print Payment Voucher</button>
                </form>
            </div>
        </div>

    <!-- STEP 1: Enrollment ID Entry -->
    <?php else: ?>
        <div class="card enrollment-entry">
            <i class="fas fa-ticket-alt enroll-icon"></i>
            <h2>Enter Your Enrollment ID</h2>
            <p>Enter the enrollment ID you received after submitting your form to proceed to payment.</p>
            <form method="POST">
                <div class="form-group">
                    <label for="enrollment_id">Enrollment ID</label>
                    <input type="text" id="enrollment_id" name="enrollment_id" placeholder="ENR-2026-A3K7B2" required style="text-transform:uppercase; letter-spacing:1px; font-family:monospace;">
                </div>
                <button type="submit" class="btn-primary" style="width:100%;"><i class="fas fa-arrow-right"></i> Proceed to Payment</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
function togglePrint(id) {
    var el = document.getElementById(id);
    if (el.style.display === 'block') {
        el.style.display = 'none';
    } else {
        el.style.display = 'block';
        window.print();
        el.style.display = 'none';
    }
}
</script>
</body>
</html>
