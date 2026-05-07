<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'api/includes/db.php';

$error = '';
$success = '';
$student = null;
$enrollment = null;

if (!isset($_GET['enrollment']) && !isset($_SESSION['enrollment'])) {
    header('Location: enroll_process.php');
    exit;
}

if (isset($_SESSION['enrollment'])) {
    $enrollment = $_SESSION['enrollment'];
}

if (!$enrollment && isset($_GET['enrollment'])) {
    if (!isset($_SESSION['enrollment']['student_id'])) {
        $error = 'Enrollment session expired. Please start enrollment again.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method'])) {
    $method = $_POST['payment_method'];
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $amount = $enrollment['amount'] ?? 150.00;
    $studentId = $enrollment['student_id'] ?? 0;

    if ($method === 'momo' || $method === 'telecel') {
        if (empty($phone)) {
            $error = 'Please enter your phone number.';
        } elseif (!preg_match('/^0[0-9]{9}$/', $phone)) {
            $error = 'Please enter a valid 10-digit phone number starting with 0.';
        }
    }

    if (empty($error)) {
        $receiptNumber = 'NXC-' . time() . '-' . rand(1000, 9999);

        try {
            $stmt = $pdo->prepare("INSERT INTO payments (student_id, amount, payment_method, receipt_number, status, payment_date) VALUES (?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$studentId, $amount, $method, $receiptNumber]);
            $paymentId = $pdo->lastInsertId();

            if ($method === 'bank') {
                $_SESSION['payment_pending'] = [
                    'payment_id' => $paymentId,
                    'student_id' => $studentId,
                    'receipt_number' => $receiptNumber,
                    'method' => $method
                ];
                header('Location: payment.php?confirm_bank=1');
                exit;
            } else {
                $_SESSION['payment_pending'] = [
                    'payment_id' => $paymentId,
                    'student_id' => $studentId,
                    'receipt_number' => $receiptNumber,
                    'method' => $method,
                    'phone' => $phone
                ];
                header('Location: payment.php?processing=1');
                exit;
            }
        } catch (Exception $e) {
            $error = 'Payment recording failed. Please try again.';
        }
    }
}

if (isset($_GET['processing']) && isset($_SESSION['payment_pending'])) {
    $pending = $_SESSION['payment_pending'];
    $methodName = $pending['method'] === 'momo' ? 'MTN MoMo' : 'Telecel Cash';
}

if (isset($_GET['confirm_bank']) && isset($_SESSION['payment_pending'])) {
    $pending = $_SESSION['payment_pending'];
}

if (isset($_POST['confirm_payment']) && isset($_SESSION['payment_pending'])) {
    $pending = $_SESSION['payment_pending'];
    $paymentId = $pending['payment_id'];
    $studentId = $pending['student_id'] ?? 0;

    // Re-fetch enrollment from session if needed
    if (!$studentId && isset($_SESSION['enrollment'])) {
        $studentId = $_SESSION['enrollment']['student_id'];
    }

    try {
        $stmt = $pdo->prepare("UPDATE payments SET status = 'completed' WHERE id = ?");
        $stmt->execute([$paymentId]);

        $stmt = $pdo->prepare("UPDATE students SET status = 'enrolled' WHERE id = ?");
        $stmt->execute([$studentId]);

        unset($_SESSION['payment_pending']);
        unset($_SESSION['enrollment']);
        $_SESSION['payment_success'] = [
            'receipt_number' => $pending['receipt_number'],
            'amount' => $amount,
            'full_name' => $enrollment['full_name'] ?? '',
            'admission_number' => $enrollment['admission_number'] ?? '',
            'class_name' => $enrollment['class_name'] ?? ''
        ];
        header('Location: payment.php?success=1');
        exit;
    } catch (Exception $e) {
        $error = 'Confirmation failed. Please contact administration.';
    }
}

if (isset($_GET['success']) && isset($_SESSION['payment_success'])) {
    $successData = $_SESSION['payment_success'];
}

function getStudentInfo($pdo, $studentId) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Nex CEC Basic School</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; color: #333; line-height: 1.6; }
        .header { background: #1a5276; color: white; padding: 20px 0; text-align: center; }
        .header h1 { font-size: 24px; }
        .header p { font-size: 14px; opacity: 0.9; }
        .container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
        .enrollment-details { background: white; border-radius: 10px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .enrollment-details h2 { color: #1a5276; margin-bottom: 15px; font-size: 20px; }
        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: 600; color: #555; }
        .detail-value { color: #1a5276; font-weight: 600; }
        .amount { font-size: 28px; color: #1a5276; font-weight: 700; }
        .payment-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        @media (max-width: 768px) { .payment-cards { grid-template-columns: 1fr; } }
        .payment-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s; }
        .payment-card:hover { transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0,0,0,0.12); }
        .payment-card.momo { border-top: 4px solid #ffcc00; }
        .payment-card.telecel { border-top: 4px solid #e4002b; }
        .payment-card.bank { border-top: 4px solid #0066cc; }
        .card-header { display: flex; align-items: center; margin-bottom: 20px; }
        .card-icon { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-right: 15px; }
        .momo .card-icon { background: #ffcc00; color: #333; }
        .telecel .card-icon { background: #e4002b; color: white; }
        .bank .card-icon { background: #0066cc; color: white; }
        .card-title { font-size: 18px; font-weight: 600; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 14px; color: #555; }
        .form-group input { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; transition: border-color 0.2s; }
        .form-group input:focus { outline: none; border-color: #1a5276; }
        .btn-pay { width: 100%; padding: 14px; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: opacity 0.2s; }
        .btn-pay:hover { opacity: 0.9; }
        .momo .btn-pay { background: #ffcc00; color: #333; }
        .telecel .btn-pay { background: #e4002b; color: white; }
        .bank .btn-pay { background: #0066cc; color: white; }
        .bank-details { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 15px; font-size: 14px; }
        .bank-details p { margin-bottom: 8px; }
        .bank-details strong { color: #1a5276; }
        .error { background: #fee; color: #c33; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; }
        .success { background: #efe; color: #2a7; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; }
        .processing { text-align: center; padding: 50px 20px; }
        .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #1a5276; border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite; margin: 0 auto 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .success-page { text-align: center; padding: 40px 20px; }
        .success-page .checkmark { font-size: 60px; color: #2a7; margin-bottom: 20px; }
        .admission-card { background: white; border-radius: 12px; padding: 30px; margin: 30px auto; max-width: 500px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); text-align: left; }
        .admission-card h3 { color: #1a5276; text-align: center; margin-bottom: 20px; }
        .admission-card p { margin-bottom: 10px; }
        .btn-print { display: inline-block; padding: 12px 30px; background: #1a5276; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
        .btn-print:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Nex CEC Basic School</h1>
        <p>Student Enrollment Payment</p>
    </div>

    <div class="container">
        <?php if (isset($error) && $error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (isset($success) && $success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && isset($successData)): ?>
            <div class="success-page">
                <div class="checkmark">&#10004;</div>
                <h2>Payment Successful!</h2>
                <p>Your enrollment is now complete.</p>

                <div class="admission-card">
                    <h3>Admission Card</h3>
                    <p><strong>Student Name:</strong> <?php echo htmlspecialchars($successData['full_name']); ?></p>
                    <p><strong>Admission Number:</strong> <?php echo htmlspecialchars($successData['admission_number']); ?></p>
                    <p><strong>Class:</strong> <?php echo htmlspecialchars($successData['class_name']); ?></p>
                    <p><strong>Amount Paid:</strong> GHS <?php echo number_format($successData['amount'], 2); ?></p>
                    <p><strong>Receipt Number:</strong> <?php echo htmlspecialchars($successData['receipt_number']); ?></p>
                    <p><strong>Status:</strong> <span style="color: #2a7; font-weight: 600;">ENROLLED</span></p>
                </div>

                <a href="#" class="btn-print" onclick="window.print(); return false;">Print Admission Card</a>
                <br><br>
                <a href="index.php" style="color: #1a5276;">Back to Home</a>
            </div>

        <?php elseif (isset($_GET['processing']) && isset($pending)): ?>
            <div class="processing">
                <div class="spinner"></div>
                <h2>Processing Payment...</h2>
                <p>Check your phone <strong><?php echo htmlspecialchars($pending['phone']); ?></strong> to complete the <?php echo htmlspecialchars($methodName); ?> payment.</p>
                <p>Please wait while we confirm your transaction...</p>
                <form method="POST" action="payment.php?confirm_bank=1" style="margin-top: 30px;">
                    <input type="hidden" name="confirm_payment" value="1">
                    <button type="submit" class="btn-pay" style="max-width: 300px; background: #1a5276; color: white;">I've Completed the Payment</button>
                </form>
            </div>

        <?php elseif (isset($_GET['confirm_bank']) && isset($pending)): ?>
            <div class="processing">
                <h2>Bank Transfer Instructions</h2>
                <div class="bank-details" style="max-width: 500px; margin: 20px auto; text-align: left;">
                    <p><strong>Bank:</strong> GCB Bank</p>
                    <p><strong>Account Name:</strong> Nex CEC Basic School</p>
                    <p><strong>Account Number:</strong> 1234567890</p>
                    <p><strong>Branch:</strong> Main Branch</p>
                    <p><strong>Amount:</strong> GHS <?php echo number_format($enrollment['amount'] ?? 150.00, 2); ?></p>
                    <p><strong>Reference:</strong> <?php echo htmlspecialchars($enrollment['admission_number'] ?? ''); ?></p>
                </div>
                <p>Please make the transfer and click the button below once done.</p>
                <form method="POST" style="margin-top: 30px;">
                    <input type="hidden" name="confirm_payment" value="1">
                    <button type="submit" class="btn-pay" style="max-width: 300px; background: #0066cc; color: white;">I've Made the Transfer</button>
                </form>
                <p style="margin-top: 15px; font-size: 13px; color: #777;">Your payment will be verified by administration within 24 hours.</p>
            </div>

        <?php elseif ($enrollment): ?>
            <div class="enrollment-details">
                <h2>Enrollment Summary</h2>
                <div class="detail-row">
                    <span class="detail-label">Student Name</span>
                    <span class="detail-value"><?php echo htmlspecialchars($enrollment['full_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Admission Number</span>
                    <span class="detail-value"><?php echo htmlspecialchars($enrollment['admission_number'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Class</span>
                    <span class="detail-value"><?php echo htmlspecialchars($enrollment['class_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Guardian Email</span>
                    <span class="detail-value"><?php echo htmlspecialchars($enrollment['guardian_email'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Amount to Pay</span>
                    <span class="detail-value amount">GHS <?php echo number_format($enrollment['amount'] ?? 150.00, 2); ?></span>
                </div>
            </div>

            <h2 style="color: #1a5276; margin-bottom: 20px; text-align: center;">Select Payment Method</h2>

            <div class="payment-cards">
                <div class="payment-card momo">
                    <div class="card-header">
                        <div class="card-icon">M</div>
                        <div class="card-title">MTN MoMo</div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="payment_method" value="momo">
                        <div class="form-group">
                            <label for="momo-phone">Phone Number</label>
                            <input type="tel" id="momo-phone" name="phone" placeholder="024 000 0000" value="<?php echo htmlspecialchars($enrollment['guardian_phone'] ?? ''); ?>" required>
                        </div>
                        <button type="submit" class="btn-pay">Pay with MoMo</button>
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
                            <label for="telecel-phone">Phone Number</label>
                            <input type="tel" id="telecel-phone" name="phone" placeholder="020 000 0000" value="<?php echo htmlspecialchars($enrollment['guardian_phone'] ?? ''); ?>" required>
                        </div>
                        <button type="submit" class="btn-pay">Pay with Telecel</button>
                    </form>
                </div>

                <div class="payment-card bank">
                    <div class="card-header">
                        <div class="card-icon">B</div>
                        <div class="card-title">Bank Transfer</div>
                    </div>
                    <div class="bank-details">
                        <p><strong>GCB Bank</strong></p>
                        <p>Acc: 1234567890</p>
                        <p>Nex CEC Basic School</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="payment_method" value="bank">
                        <button type="submit" class="btn-pay">Pay via Bank Transfer</button>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <div class="enrollment-details">
                <p style="text-align: center; color: #777;">No enrollment data found. Please start the enrollment process.</p>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="enroll_process.php" style="color: #1a5276; text-decoration: none;">Go to Enrollment</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
