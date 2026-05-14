<?php
require_once 'includes/db.php';

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
$current_year = $settings['current_academic_year'] ?? date('Y') . '/' . (date('Y') + 1);
$admission_fee = $settings['admission_fee'] ?? '150.00';
$prospectus_fee = $settings['prospectus_fee'] ?? '50.00';
$form_fee = $settings['enrollment_form_fee'] ?? '20.00';

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

$status = $student['status'] ?? 'pending';
$statusBadge = '';
$statusColor = '';
if ($status === 'active' || $status === 'enrolled') {
    $statusBadge = 'ENROLLED';
    $statusColor = '#27ae60';
} elseif ($status === 'rejected') {
    $statusBadge = 'REJECTED';
    $statusColor = '#e74c3c';
} else {
    $statusBadge = 'PENDING';
    $statusColor = '#f39c12';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Form — <?php echo htmlspecialchars($student['full_name'] ?? ''); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            padding: 40px;
            color: #333;
        }
        .form-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border: 1px solid #ddd;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: relative;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #1a5276;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 { color: #1a5276; font-size: 24px; text-transform: uppercase; }
        .header h2 { font-size: 18px; margin-top: 5px; color: #c0392b; text-transform: uppercase; }
        .header p { font-size: 13px; color: #666; margin: 3px 0; }
        .logo { width: 80px; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; }
        .status-badge {
            position: absolute;
            top: 30px;
            right: 40px;
            border: 3px solid <?php echo $statusColor; ?>;
            color: <?php echo $statusColor; ?>;
            padding: 8px 20px;
            font-weight: bold;
            font-size: 16px;
            transform: rotate(-10deg);
            border-radius: 4px;
        }
        .section-title {
            background: #1a5276;
            color: white;
            padding: 8px 15px;
            font-size: 14px;
            font-weight: bold;
            margin: 25px 0 15px 0;
            text-transform: uppercase;
        }
        .info-row {
            display: flex;
            border-bottom: 1px solid #eee;
            padding: 8px 0;
        }
        .info-row .label {
            width: 200px;
            font-weight: bold;
            font-size: 13px;
            color: #555;
        }
        .info-row .value {
            flex: 1;
            font-size: 14px;
        }
        .fee-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .fee-table th, .fee-table td {
            border: 1px solid #ddd;
            padding: 10px 15px;
            text-align: left;
            font-size: 14px;
        }
        .fee-table th { background: #f0f0f0; font-weight: bold; }
        .fee-table .text-end { text-align: right; }
        .fee-table .total td { font-weight: bold; font-size: 15px; border-top: 2px solid #1a5276; }
        .declaration {
            margin: 25px 0;
            padding: 15px;
            border: 1px solid #ddd;
            background: #f9f9f9;
            font-size: 13px;
            line-height: 1.6;
        }
        .signature-row {
            display: flex;
            justify-content: space-around;
            margin: 30px 0;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin: 50px 0 5px 0;
            width: 200px;
        }
        .ref-box {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: #f0f7ff;
            border: 1px solid #b8d9e8;
            border-radius: 6px;
        }
        .ref-box .ref-number {
            font-size: 22px;
            font-weight: bold;
            color: #1a5276;
            letter-spacing: 2px;
        }
        .action-buttons {
            max-width: 900px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
        }
        .btn-print { background: #1a5276; color: white; }
        .btn-download { background: #1a865c; color: white; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 10px; }
            .form-container { box-shadow: none; border: none; padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="action-buttons no-print">
        <button onclick="window.print()" class="btn btn-print">Print Form</button>
        <button onclick="downloadPDF()" class="btn btn-download">Download PDF</button>
    </div>

    <div class="form-container" id="form-content">
        <div class="status-badge"><?php echo $statusBadge; ?></div>
        <div class="header">
            <?php if ($logoData): ?>
                <img src="<?php echo $logoData; ?>" alt="Logo" class="logo">
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($school_name); ?></h1>
            <p><?php echo htmlspecialchars($school_address); ?></p>
            <p>Phone: <?php echo htmlspecialchars($school_phone); ?> | Email: <?php echo htmlspecialchars($school_email); ?></p>
            <h2>Student Enrollment Form</h2>
            <p style="margin-top: 10px; font-weight: bold;">Academic Year: <?php echo htmlspecialchars($current_year); ?></p>
        </div>

        <div class="ref-box">
            <p style="font-size: 13px; margin-bottom: 5px;">Enrollment Reference Number</p>
            <div class="ref-number"><?php echo htmlspecialchars($student['enrollment_id'] ?? $ref); ?></div>
            <p style="font-size: 12px; color: #666; margin-top: 5px;">Please quote this reference in all correspondence</p>
        </div>

        <!-- Section A: Student Details -->
        <div class="section-title">Section A — Student Details</div>
        <div class="info-row">
            <span class="label">Full Name:</span>
            <span class="value"><?php echo htmlspecialchars($student['full_name'] ?? 'N/A'); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Date of Birth:</span>
            <span class="value"><?php echo htmlspecialchars($student['date_of_birth'] ?? 'N/A'); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Gender:</span>
            <span class="value"><?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Place of Birth:</span>
            <span class="value"><?php echo htmlspecialchars($student['place_of_birth'] ?? 'N/A'); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Nationality:</span>
            <span class="value"><?php echo htmlspecialchars($student['nationality'] ?? 'Ghanaian'); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Class Applying For:</span>
            <span class="value"><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Home Address:</span>
            <span class="value"><?php echo htmlspecialchars($student['address'] ?? 'N/A'); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Previous School:</span>
            <span class="value"><?php echo htmlspecialchars($student['previous_school'] ?? 'N/A'); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Previous Class:</span>
            <span class="value"><?php echo htmlspecialchars($student['previous_class'] ?? 'N/A'); ?></span>
        </div>

        <!-- Section B: Health Information -->
        <div class="section-title">Section B — Health Information</div>
        <div class="info-row">
            <span class="label">Health Insurance (NHIS):</span>
            <span class="value"><?php echo htmlspecialchars($student['health_insurance_id'] ?? 'N/A'); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Medical Conditions:</span>
            <span class="value"><?php echo htmlspecialchars($student['medical_conditions'] ?? 'None'); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Allergies:</span>
            <span class="value"><?php echo htmlspecialchars($student['allergies'] ?? 'None'); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Special Needs:</span>
            <span class="value"><?php echo htmlspecialchars($student['special_needs'] ?? 'None'); ?></span>
        </div>

        <!-- Section C: Parent / Guardian Details -->
        <div class="section-title">Section C — Parent / Guardian Details</div>
        <div class="info-row">
            <span class="label">Guardian Name:</span>
            <span class="value"><?php echo htmlspecialchars($student['guardian_name'] ?? 'N/A'); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Relationship:</span>
            <span class="value"><?php echo htmlspecialchars($student['guardian_relationship'] ?? 'N/A'); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Phone (Primary):</span>
            <span class="value"><?php echo htmlspecialchars($student['guardian_phone_primary'] ?? 'N/A'); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Phone (Emergency):</span>
            <span class="value"><?php echo htmlspecialchars($student['guardian_phone_emergency'] ?? 'N/A'); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Email:</span>
            <span class="value"><?php echo htmlspecialchars($student['guardian_email'] ?? 'N/A'); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Occupation:</span>
            <span class="value"><?php echo htmlspecialchars($student['guardian_occupation'] ?? 'N/A'); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Address:</span>
            <span class="value"><?php echo htmlspecialchars($student['guardian_address'] ?? 'N/A'); ?></span>
        </div>

        <!-- Section D: Fees -->
        <div class="section-title">Section D — Enrollment Fees</div>
        <table class="fee-table">
            <thead>
                <tr>
                    <th style="width: 60%;">Fee Description</th>
                    <th class="text-end" style="width: 40%;">Amount (GHS)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Admission Fee — One-time enrollment fee</td>
                    <td class="text-end"><?php echo number_format((float)$admission_fee, 2); ?></td>
                </tr>
                <tr>
                    <td>Prospectus Fee — School prospectus and information pack</td>
                    <td class="text-end"><?php echo number_format((float)$prospectus_fee, 2); ?></td>
                </tr>
                <tr>
                    <td>Form Processing Fee</td>
                    <td class="text-end"><?php echo number_format((float)$form_fee, 2); ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="total">
                    <td class="text-end">TOTAL DUE:</td>
                    <td class="text-end">GHS <?php echo number_format((float)$admission_fee + (float)$prospectus_fee + (float)$form_fee, 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <?php if (!empty($student['admission_number'])): ?>
            <div class="info-row">
                <span class="label">Admission Number:</span>
                <span class="value"><strong><?php echo htmlspecialchars($student['admission_number']); ?></strong></span>
            </div>
        <?php endif; ?>
        <div class="info-row">
            <span class="label">Status:</span>
            <span class="value"><strong style="color: <?php echo $statusColor; ?>;"><?php echo ucfirst($status); ?></strong></span>
        </div>
        <div class="info-row">
            <span class="label">Date Submitted:</span>
            <span class="value"><?php echo htmlspecialchars($student['created_at'] ?? date('Y-m-d H:i')); ?></span>
        </div>

        <!-- Declaration -->
        <div class="declaration">
            <strong>DECLARATION:</strong>
            <p style="margin-top: 8px;">
                I, <strong><?php echo htmlspecialchars($student['guardian_name'] ?? 'N/A'); ?></strong>, 
                hereby declare that the information provided above is true and correct to the best of my knowledge. 
                I agree to abide by the rules and regulations of <strong><?php echo htmlspecialchars($school_name); ?></strong>.
            </p>
        </div>

        <!-- Signatures -->
        <div class="signature-row">
            <div>
                <div class="signature-line"></div>
                <strong>Parent / Guardian Signature</strong>
            </div>
            <div>
                <div class="signature-line"></div>
                <strong>School Official Signature</strong>
            </div>
        </div>

        <div style="margin-top: 20px; font-size: 11px; color: #999; text-align: center;">
            <p>Generated by Nex CEC School System | Enrollment Ref: <?php echo htmlspecialchars($student['enrollment_id'] ?? $ref); ?></p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            var element = document.getElementById('form-content');
            var opt = {
                margin: 10,
                filename: 'Enrollment_Form_<?php echo htmlspecialchars($ref); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>
