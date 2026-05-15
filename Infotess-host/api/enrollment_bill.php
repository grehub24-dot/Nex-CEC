<?php
require_once 'includes/db.php';
require_once 'includes/BillGenerator.php';

$ref = isset($_GET['ref']) ? trim($_GET['ref']) : '';
if (empty($ref)) {
    die("No enrollment reference provided.");
}

// Fetch enrollment by reference
$stmt = $pdo->prepare("SELECT * FROM students WHERE enrollment_id = ?");
$stmt->execute([$ref]);
$student = $stmt->fetch();

if (!$student) {
    die("Enrollment not found. Please check your reference number.");
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
$school_address = $settings['school_address'] ?? 'School Address, City, Ghana';
$school_phone = $settings['school_phone'] ?? '+233 XX XXX XXXX';
$school_email = $settings['school_email'] ?? 'info@school.edu.gh';

$admission_fee = $settings['admission_fee'] ?? '150.00';
$prospectus_fee = $settings['prospectus_fee'] ?? '50.00';
$form_fee = $settings['enrollment_form_fee'] ?? '20.00';

$fees = [
    'admission_fee' => (float)$admission_fee,
    'prospectus_fee' => (float)$prospectus_fee,
    'form_fee' => (float)$form_fee,
];
$total = (float)$admission_fee + (float)$prospectus_fee + (float)$form_fee;

// Generate bill (file saved for email attachment, not for HTTP serving)
$billGen = new BillGenerator();
$billGen->generate($student, $fees, $total, $school_name);

// Render bill HTML directly (no redirect to static file — Vercel compatible)
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

$studentName  = htmlspecialchars($student['full_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
$className    = htmlspecialchars($student['class_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
$gender       = htmlspecialchars($student['gender'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
$guardianName = htmlspecialchars($student['guardian_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
$guardianPhone = htmlspecialchars($student['guardian_phone_primary'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
$guardianEmail = htmlspecialchars($student['guardian_email'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
$guardianRel  = htmlspecialchars($student['guardian_relationship'] ?? 'Guardian', ENT_QUOTES, 'UTF-8');
$admissionFeeF = number_format((float)$admission_fee, 2);
$prospectusFeeF = number_format((float)$prospectus_fee, 2);
$formFeeF = number_format((float)$form_fee, 2);
$totalF = number_format($total, 2);
$generatedDate = date('j F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enrollment Fee Bill — <?php echo $ref; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; padding: 40px; color: #333; }
        .bill-container { max-width: 800px; margin: 0 auto; background: white; padding: 40px; border: 1px solid #ddd; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .bill-header { text-align: center; border-bottom: 3px solid #1a5276; padding-bottom: 20px; margin-bottom: 30px; }
        .bill-header h1 { color: #1a5276; font-size: 24px; text-transform: uppercase; }
        .bill-header h2 { margin-top: 15px; font-size: 20px; color: #c0392b; text-transform: uppercase; letter-spacing: 2px; }
        .bill-header p { font-size: 13px; color: #666; margin: 3px 0; }
        .logo { width: 80px; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; }
        .row { display: flex; justify-content: space-between; margin-bottom: 25px; gap: 20px; }
        .col { width: 50%; }
        .details-title { font-weight: bold; font-size: 15px; margin-bottom: 10px; border-bottom: 2px solid #1a5276; padding-bottom: 5px; color: #1a5276; }
        .details-item { margin-bottom: 6px; font-size: 14px; }
        .details-item strong { display: inline-block; width: 120px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        th, td { border: 1px solid #ddd; padding: 12px 15px; text-align: left; }
        th { background: #1a5276; color: white; font-weight: bold; font-size: 14px; }
        .text-end { text-align: right; }
        .total-row td { font-weight: bold; font-size: 16px; border-top: 2px solid #1a5276; }
        .total-amount { color: #c0392b; font-size: 18px; }
        .ref-badge { display: inline-block; background: #1a5276; color: white; padding: 8px 20px; border-radius: 4px; font-size: 18px; font-weight: bold; letter-spacing: 1px; margin: 10px 0; }
        .important-note { background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 15px; font-size: 13px; color: #856404; margin-bottom: 25px; }
        .payment-info { background: #f0f7ff; border: 1px solid #b8d9e8; border-radius: 6px; padding: 20px; margin-bottom: 25px; }
        .payment-info h3 { color: #1a5276; margin-bottom: 10px; font-size: 16px; }
        .payment-info p { font-size: 13px; margin-bottom: 5px; }
        .signature-line { border-top: 1px solid #333; margin-bottom: 5px; width: 200px; }
        .action-buttons { max-width: 800px; margin: 0 auto 20px auto; display: flex; justify-content: flex-end; gap: 10px; }
        .btn { padding: 10px 24px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 14px; }
        .btn-print { background: #1a5276; color: white; }
        .btn-download { background: #1a865c; color: white; }
        @media print { .no-print { display: none !important; } body { background: white; padding: 0; } .bill-container { box-shadow: none; border: none; padding: 20px; } }
    </style>
</head>
<body>
    <div class="action-buttons no-print">
        <button onclick="window.print()" class="btn btn-print">Print Bill</button>
        <button onclick="downloadPDF()" class="btn btn-download">Download PDF</button>
        <button onclick="history.back()" class="btn" style="background: #6c757d; color: white;">Go Back</button>
    </div>
    <div class="bill-container" id="bill-content">
        <div class="bill-header">
            <?php if ($logoData): ?><img src="<?php echo $logoData; ?>" alt="Logo" class="logo"><?php endif; ?>
            <h1><?php echo htmlspecialchars($school_name); ?></h1>
            <p><?php echo htmlspecialchars($school_address); ?> | Phone: <?php echo htmlspecialchars($school_phone); ?> | Email: <?php echo htmlspecialchars($school_email); ?></p>
            <h2>Enrollment Fee Bill</h2>
        </div>

        <div style="text-align: center; margin-bottom: 20px;">
            <div class="ref-badge">Ref: <?php echo htmlspecialchars($ref); ?></div>
            <p style="margin-top: 8px; font-size: 13px; color: #666;">Date Generated: <?php echo $generatedDate; ?></p>
        </div>

        <div class="important-note">
            <strong>Important:</strong> Please bring this bill to the school to complete payment and enrollment. Keep this document safe.
        </div>

        <div class="row">
            <div class="col">
                <div class="details-title">Student Information</div>
                <div class="details-item"><strong>Name:</strong> <?php echo $studentName; ?></div>
                <div class="details-item"><strong>Class:</strong> <?php echo $className; ?></div>
                <div class="details-item"><strong>Gender:</strong> <?php echo $gender; ?></div>
                <div class="details-item"><strong>Ref:</strong> <?php echo htmlspecialchars($ref); ?></div>
            </div>
            <div class="col">
                <div class="details-title">Parent / Guardian</div>
                <div class="details-item"><strong>Name:</strong> <?php echo $guardianName; ?></div>
                <div class="details-item"><strong>Relation:</strong> <?php echo $guardianRel; ?></div>
                <div class="details-item"><strong>Phone:</strong> <?php echo $guardianPhone; ?></div>
                <div class="details-item"><strong>Email:</strong> <?php echo $guardianEmail; ?></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 60%;">Fee Description</th>
                    <th class="text-end" style="width: 40%;">Amount (GHS)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Admission Fee — One-time enrollment fee</td>
                    <td class="text-end">GHS <?php echo $admissionFeeF; ?></td>
                </tr>
                <tr>
                    <td>Prospectus Fee — School prospectus and information pack</td>
                    <td class="text-end">GHS <?php echo $prospectusFeeF; ?></td>
                </tr>
                <tr>
                    <td>Form Processing Fee — Enrollment form processing</td>
                    <td class="text-end">GHS <?php echo $formFeeF; ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td class="text-end">TOTAL DUE:</td>
                    <td class="text-end total-amount">GHS <?php echo $totalF; ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="payment-info">
            <h3>Payment Instructions</h3>
            <p>1. Bring this bill to the <strong>Finance Office</strong> at the school.</p>
            <p>2. Make payment via <strong>Cash, Mobile Money, or Bank Transfer</strong>.</p>
            <p>3. The school will issue an official receipt and complete the enrollment.</p>
            <p>4. After enrollment, you will receive portal login credentials via SMS and email.</p>
            <p style="margin-top: 10px;"><strong>Enrollment Reference:</strong> <?php echo htmlspecialchars($ref); ?> — Please quote this in all communications.</p>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
            <div style="font-size: 12px; color: #666;">
                <p>Generated by Nex CEC School System</p>
                <p>Ref: <?php echo htmlspecialchars($ref); ?></p>
            </div>
            <div style="text-align: center;">
                <div class="signature-line"></div>
                <div style="font-weight: bold;">Authorized Signature</div>
                <div style="font-size: 12px; color: #666;">School Administrator</div>
            </div>
        </div>

        <div style="margin-top: 20px; font-size: 11px; color: #999; text-align: center;">
            <p>This is a computer-generated bill. Valid only for the enrollment of the student named above.</p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            var element = document.getElementById('bill-content');
            var opt = {
                margin: 10,
                filename: 'Enrollment_Bill_<?php echo htmlspecialchars($ref); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>
