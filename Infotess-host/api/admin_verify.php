<?php
require_once 'includes/db.php';

// Enforce access control
requireAccess('verify');

// Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$receipt_number = $_GET['receipt'] ?? '';
$payment = null;
$history = [];

if ($receipt_number) {
    // Fetch specific payment
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE receipt_number = ?");
    $stmt->execute([$receipt_number]);
    $payment = $stmt->fetch();

    if ($payment) {
        // Enrich with student data (two-step lookup for Supabase compatibility)
        $s = $pdo->prepare("SELECT full_name, admission_number, class_name FROM students WHERE id = ?");
        $s->execute([$payment['student_id']]);
        $stu = $s->fetch();
        if ($stu) {
            $payment['full_name'] = $stu['full_name'];
            $payment['admission_number'] = $stu['admission_number'];
            $payment['class_name'] = $stu['class_name'];
        }

        // Fetch full history for this student
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? ORDER BY payment_date DESC");
        $stmt->execute([$payment['student_id']]);
        $history = $stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Receipt - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
            <?php echo renderSidebar('verify', $school_name); ?>

        <main class="main-content">
            <div class="top-bar">
                <h2>Verify Receipt</h2>
            </div>

            <div class="card">
                <h3>Enter Receipt Number or Scan QR</h3>
                <form method="GET" action="" style="display: flex; gap: 10px; margin-bottom: 20px;">
                    <input type="text" name="receipt" value="<?php echo htmlspecialchars($receipt_number); ?>" placeholder="SDMS-2026-XXXX" class="form-control" style="flex: 1;">
                    <button type="submit" class="btn-admin-action"><i class="fas fa-check-circle"></i> Verify</button>
                </form>
                
                <div id="reader" style="width: 300px; display: none;"></div>
                <button onclick="startScanner()" class="btn-admin-action btn-admin-secondary"><i class="fas fa-qrcode"></i> Start QR Scanner</button>
            </div>

            <?php if ($receipt_number): ?>
                <?php if ($payment): ?>
                    <div class="card success-card" style="margin-top: 20px; border-left: 5px solid green;">
                        <h3><i class="fas fa-check-circle" style="color: green;"></i> Valid Receipt</h3>
                        <div class="details-grid">
                            <p><strong>Student:</strong> <?php echo htmlspecialchars($payment['full_name']); ?> (<?php echo htmlspecialchars($payment['admission_number']); ?>)</p>
                            <p><strong>Class:</strong> <?php echo htmlspecialchars($payment['class_name'] ?? '-'); ?></p>
                            <p><strong>Amount:</strong> GHS <?php echo number_format($payment['amount'], 2); ?></p>
                            <p><strong>Date:</strong> <?php echo $payment['payment_date']; ?></p>
                            <p><strong>Purpose:</strong> Term <?php echo htmlspecialchars($payment['semester'] . ' — ' . $payment['academic_year']); ?></p>
                        </div>
                    </div>

                    <div class="card" style="margin-top: 20px;">
                        <h3>Payment History for Student</h3>
                        <table class="table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Receipt #</th>
                                    <th>Amount</th>
                                    <th>Term</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $record): ?>
                                    <tr style="<?php echo $record['receipt_number'] === $receipt_number ? 'background: #e8f5e9;' : ''; ?>">
                                        <td><?php echo $record['payment_date']; ?></td>
                                        <td><?php echo $record['receipt_number']; ?></td>
                                        <td>GHS <?php echo number_format($record['amount'], 2); ?></td>
                                        <td>Term <?php echo $record['semester'] . ' — ' . $record['academic_year']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="card error-card" style="margin-top: 20px; border-left: 5px solid red;">
                        <h3><i class="fas fa-times-circle" style="color: red;"></i> Invalid Receipt</h3>
                        <p>No payment record found for receipt number: <strong><?php echo htmlspecialchars($receipt_number); ?></strong></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function startScanner() {
            const html5QrCode = new Html5Qrcode("reader");
            document.getElementById('reader').style.display = 'block';
            html5QrCode.start(
                { facingMode: "environment" }, 
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText, decodedResult) => {
                    // Handle on success condition with the decoded message.
                    console.log(`Scan result: ${decodedText}`);
                    // Extract receipt number from URL if it's a URL
                    let receipt = decodedText;
                    if (decodedText.includes('receipt=')) {
                        const url = new URL(decodedText);
                        receipt = url.searchParams.get('receipt');
                    }
                    window.location.href = `?receipt=${receipt}`;
                    html5QrCode.stop();
                },
                (errorMessage) => {
                    // parse error, ignore it.
                })
            .catch((err) => {
                // Start failed, handle it.
            });
        }
    </script>
</body>
</html>
