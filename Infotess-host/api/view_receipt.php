<?php
/**
 * Dynamic receipt viewer.
 * Fetches payment data from the database and renders the receipt HTML inline,
 * eliminating the need to write receipt files to disk.
 */
require_once 'includes/db.php';
require_once 'includes/functions.php';

$receipt = isset($_GET['receipt']) ? trim($_GET['receipt']) : '';
if (empty($receipt)) {
    die('No receipt number provided.');
}

// Fetch payment by receipt number
$stmt = $pdo->prepare("SELECT * FROM payments WHERE receipt_number = ?");
$stmt->execute([$receipt]);
$payment = $stmt->fetch();

if (!$payment) {
    die('Receipt not found.');
}

// Fetch student
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$payment['student_id']]);
$student = $stmt->fetch();

if (!$student) {
    die('Student not found.');
}

// Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$amount = (float)($payment['amount'] ?? 0);
$date = htmlspecialchars($payment['payment_date'] ?? $payment['created_at'] ?? date('Y-m-d'));
$academicYear = htmlspecialchars($payment['academic_year'] ?? date('Y') . '/' . (date('Y')+1));
$term = htmlspecialchars($payment['semester'] ?? '1');
$paymentMethod = htmlspecialchars($payment['payment_method'] ?? 'Mobile Money');
$receiptNumber = htmlspecialchars($receipt);
$className = htmlspecialchars($student['class_name'] ?? 'N/A');

// Convert local image to base64 for display
$logoData = '';
$logoPath = __DIR__ . '/images/school-logo.png';
if (file_exists($logoPath)) {
    $type = pathinfo($logoPath, PATHINFO_EXTENSION);
    $data = file_get_contents($logoPath);
    $logoData = 'data:image/' . $type . ';base64,' . base64_encode($data);
} else {
    $logoPath = __DIR__ . '/images/infotess.png';
    if (file_exists($logoPath)) {
        $type = pathinfo($logoPath, PATHINFO_EXTENSION);
        $data = file_get_contents($logoPath);
        $logoData = 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt — <?php echo $receiptNumber; ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            padding: 40px;
            color: #333;
        }
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border: 1px solid #ddd;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .receipt-header {
            text-align: center;
            border-bottom: 3px solid #1a5276;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .receipt-header h1 {
            color: #1a5276;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .receipt-header p { margin: 2px 0; font-size: 14px; color: #555; }
        .receipt-header h3 {
            margin-top: 15px;
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            color: #333;
        }
        .logo { width: 80px; height: auto; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; }
        .row { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .col { width: 48%; }
        .details-title { font-weight: bold; font-size: 16px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .details-item { margin-bottom: 5px; font-size: 14px; }
        .details-item strong { display: inline-block; width: 130px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: bold; }
        .text-end { text-align: right; }
        .footer-row { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 40px; }
        .qr-section { text-align: center; }
        .qr-code { width: 100px; height: 100px; margin-bottom: 10px; }
        .signature-section { text-align: center; width: 200px; }
        .signature-line { border-top: 1px solid #333; margin-bottom: 5px; }
        .info-box {
            background-color: #e8f4f8; color: #1a5276; padding: 15px;
            border-radius: 4px; font-size: 13px; margin-top: 30px; border: 1px solid #b8d9e8;
        }
        .status-badge {
            position: absolute; top: 160px; right: 60px;
            border: 2px solid #28a745; color: #28a745;
            padding: 5px 15px; font-weight: bold; font-size: 18px;
            transform: rotate(-15deg); opacity: 0.8;
        }
        .action-buttons { max-width: 800px; margin: 0 auto 20px auto; display: flex; justify-content: flex-end; gap: 10px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 14px; display: flex; align-items: center; gap: 5px; }
        .btn-print { background-color: #1a5276; color: white; }
        .btn-download { background-color: #1a865c; color: white; }
        @media print {
            .no-print { display: none !important; }
            body { background-color: white; padding: 0; }
            .receipt-container { box-shadow: none; border: none; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="action-buttons no-print">
        <button onclick="window.print()" class="btn btn-print">Print Receipt</button>
        <button onclick="downloadPDF()" class="btn btn-download">Download PDF</button>
    </div>
    <div class="receipt-container" id="receipt-content" style="position:relative;">
        <div class="receipt-header">
            <?php if (!empty($logoData)): ?>
                <img src="<?php echo $logoData; ?>" alt="Logo" class="logo">
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($school_name); ?></h1>
            <p>Basic School Management System</p>
            <h3>Official Fee Payment Receipt</h3>
        </div>

        <div class="status-badge">PAID</div>

        <div class="row">
            <div class="col">
                <div class="details-title">Receipt Details</div>
                <div class="details-item"><strong>Receipt No:</strong> <?php echo $receiptNumber; ?></div>
                <div class="details-item"><strong>Date:</strong> <?php echo $date; ?></div>
                <div class="details-item"><strong>Payment Method:</strong> <?php echo $paymentMethod; ?></div>
            </div>
            <div class="col text-end" style="text-align: right;">
                <div class="details-title" style="text-align: right;">Student Details</div>
                <div class="details-item"><strong>Name:</strong> <?php echo htmlspecialchars($student['full_name'] ?? 'N/A'); ?></div>
                <div class="details-item"><strong>Index No:</strong> <?php echo htmlspecialchars($student['admission_number'] ?? $student['index_number'] ?? 'N/A'); ?></div>
                <div class="details-item"><strong>Class:</strong> <?php echo $className; ?></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Academic Year</th>
                    <th>Term</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>School Fee</td>
                    <td><?php echo $academicYear; ?></td>
                    <td>Term <?php echo $term; ?></td>
                    <td class="text-end">GHS <?php echo number_format($amount, 2); ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-end">Total Amount Paid:</th>
                    <th class="text-end">GHS <?php echo number_format($amount, 2); ?></th>
                </tr>
                <tr>
                    <th colspan="3" class="text-end" style="color: green;">Balance:</th>
                    <th class="text-end" style="color: green;">GHS 0.00</th>
                </tr>
            </tfoot>
        </table>

        <div class="footer-row">
            <div class="qr-section">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode(getAppUrl() . '/verify_public.php?receipt=' . $receiptNumber); ?>" class="qr-code" />
                <p style="font-size: 12px; margin: 0;">Scan to verify: <?php echo $receiptNumber; ?></p>
            </div>
            <div class="signature-section">
                <div class="signature-line"></div>
                <div style="font-weight: bold;">Authorized Signature</div>
                <div style="font-size: 12px; color: #666;">Finance Office</div>
            </div>
        </div>

        <div class="info-box">
            <strong>Note:</strong> This is an official digital receipt. Keep this for your records. You can access this receipt anytime from the student portal.
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            const element = document.getElementById('receipt-content');
            const opt = {
                margin:       10,
                filename:     'Receipt_<?php echo $receiptNumber; ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>
