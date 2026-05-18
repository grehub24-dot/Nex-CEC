<?php
require_once 'includes/db.php';

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
$current_term = $settings['current_term'] ?? '1';
$admission_fee = $settings['admission_fee'] ?? '150.00';
$prospectus_fee = $settings['prospectus_fee'] ?? '50.00';
$form_fee = $settings['enrollment_form_fee'] ?? '20.00';

// Convert logo to base64 for print
$logoData = '';
$logoPath = getSchoolLogoFilePath();
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
    <title>Enrollment Form — <?php echo htmlspecialchars($school_name); ?></title>
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
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #1a5276;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #1a5276;
            font-size: 24px;
            text-transform: uppercase;
        }
        .header h2 {
            font-size: 18px;
            margin-top: 5px;
            color: #c0392b;
            text-transform: uppercase;
        }
        .header p { font-size: 13px; color: #666; margin: 3px 0; }
        .logo { width: 80px; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; }
        .section-title {
            background: #1a5276;
            color: white;
            padding: 8px 15px;
            font-size: 14px;
            font-weight: bold;
            margin: 25px 0 15px 0;
            text-transform: uppercase;
        }
        .field-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 5px;
        }
        .field-group.three-col {
            grid-template-columns: 1fr 1fr 1fr;
        }
        .field {
            margin-bottom: 15px;
        }
        .field label {
            display: block;
            font-size: 12px;
            font-weight: bold;
            color: #555;
            margin-bottom: 3px;
            text-transform: uppercase;
        }
        .field .line {
            border-bottom: 1px solid #333;
            height: 30px;
            width: 100%;
        }
        .field .line-sm {
            border-bottom: 1px solid #333;
            height: 24px;
            width: 100%;
        }
        .fee-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .fee-table th, .fee-table td {
            border: 1px solid #333;
            padding: 10px 15px;
            text-align: left;
            font-size: 14px;
        }
        .fee-table th {
            background: #f0f0f0;
            font-weight: bold;
        }
        .fee-table .text-end { text-align: right; }
        .fee-table .total td { font-weight: bold; font-size: 15px; }
        .declaration {
            margin: 25px 0;
            padding: 15px;
            border: 1px solid #ddd;
            background: #f9f9f9;
            font-size: 13px;
            line-height: 1.6;
        }
        .signature-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin: 30px 0;
        }
        .signature-box {
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin: 50px 0 5px 0;
        }
        .for-office-use {
            border: 2px dashed #c0392b;
            padding: 20px;
            margin-top: 30px;
            background: #fff5f5;
        }
        .for-office-use h3 {
            color: #c0392b;
            font-size: 14px;
            margin-bottom: 15px;
            text-transform: uppercase;
        }
        .for-office-use .field-group {
            grid-template-columns: 1fr 1fr 1fr;
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
        .note { font-size: 12px; color: #888; margin-top: 20px; text-align: center; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 10px; }
            .form-container { box-shadow: none; border: none; padding: 20px; }
            .for-office-use { border: 2px dashed #c0392b; }
        }
    </style>
</head>
<body>
    <div class="action-buttons no-print">
        <button onclick="window.print()" class="btn btn-print">Print Form</button>
        <button onclick="downloadPDF()" class="btn btn-download">Download PDF</button>
    </div>

    <div class="form-container" id="form-content">
        <div class="header">
            <?php if ($logoData): ?>
                <img src="<?php echo $logoData; ?>" alt="Logo" class="logo">
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($school_name); ?></h1>
            <p><?php echo htmlspecialchars($school_address); ?></p>
            <p>Phone: <?php echo htmlspecialchars($school_phone); ?> | Email: <?php echo htmlspecialchars($school_email); ?></p>
            <h2>Student Enrollment Form</h2>
            <p style="margin-top: 10px; font-weight: bold;">Academic Year: <?php echo htmlspecialchars($current_year); ?> | Term: <?php echo htmlspecialchars($current_term); ?></p>
        </div>

        <p style="font-size: 13px; margin-bottom: 20px;">
            <strong>Instructions:</strong> Please complete all sections in BLOCK LETTERS. Tick (✓) where applicable. 
            Submit this form along with the required fees to the school administration.
        </p>

        <!-- Section A: Student Details -->
        <div class="section-title">Section A — Student Details</div>
        <div class="field-group">
            <div class="field">
                <label>Full Name (Surname First)</label>
                <div class="line"></div>
            </div>
            <div class="field">
                <label>Date of Birth (DD/MM/YYYY)</label>
                <div class="line"></div>
            </div>
        </div>
        <div class="field-group three-col">
            <div class="field">
                <label>Gender</label>
                <div class="line"></div>
            </div>
            <div class="field">
                <label>Place of Birth</label>
                <div class="line"></div>
            </div>
            <div class="field">
                <label>Nationality</label>
                <div class="line"></div>
            </div>
        </div>
        <div class="field-group">
            <div class="field">
                <label>Class Applying For</label>
                <div class="line"></div>
            </div>
            <div class="field">
                <label>Home Address / Residence</label>
                <div class="line"></div>
            </div>
        </div>
        <div class="field-group">
            <div class="field">
                <label>Previous School Attended (if any)</label>
                <div class="line"></div>
            </div>
            <div class="field">
                <label>Previous Class Completed</label>
                <div class="line"></div>
            </div>
        </div>

        <!-- Section B: Health Information -->
        <div class="section-title">Section B — Health Information</div>
        <div class="field-group">
            <div class="field">
                <label>Health Insurance (NHIS) Number</label>
                <div class="line"></div>
            </div>
            <div class="field">
                <label>Blood Group (if known)</label>
                <div class="line"></div>
            </div>
        </div>
        <div class="field">
            <label>Medical Conditions / Allergies (if any)</label>
            <div class="line"></div>
        </div>
        <div class="field">
            <label>Special Needs (if any)</label>
            <div class="line"></div>
        </div>

        <!-- Section C: Parent / Guardian Details -->
        <div class="section-title">Section C — Parent / Guardian Details</div>
        <div class="field-group">
            <div class="field">
                <label>Full Name of Parent/Guardian</label>
                <div class="line"></div>
            </div>
            <div class="field">
                <label>Relationship to Student</label>
                <div class="line"></div>
            </div>
        </div>
        <div class="field-group">
            <div class="field">
                <label>Phone Number (Primary)</label>
                <div class="line"></div>
            </div>
            <div class="field">
                <label>Phone Number (Secondary/Emergency)</label>
                <div class="line"></div>
            </div>
        </div>
        <div class="field">
            <label>Email Address (for portal access and receipts)</label>
            <div class="line"></div>
        </div>
        <div class="field">
            <label>Occupation</label>
            <div class="line"></div>
        </div>
        <div class="field">
            <label>Residential / Postal Address</label>
            <div class="line"></div>
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

        <!-- Declaration -->
        <div class="declaration">
            <strong>DECLARATION:</strong>
            <p style="margin-top: 8px;">
                I, <span style="border-bottom: 1px solid #333; display: inline-block; width: 200px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>, 
                hereby declare that the information provided above is true and correct to the best of my knowledge. 
                I agree to abide by the rules and regulations of <strong><?php echo htmlspecialchars($school_name); ?></strong>.
                I understand that the enrollment is subject to approval by the school administration upon payment of the required fees.
            </p>
        </div>

        <!-- Signatures -->
        <div class="signature-row">
            <div class="signature-box">
                <div class="signature-line"></div>
                <strong>Parent / Guardian Signature</strong>
                <p style="font-size: 12px; color: #666;">Date: _______________</p>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <strong>Student Signature (if applicable)</strong>
                <p style="font-size: 12px; color: #666;">Date: _______________</p>
            </div>
        </div>

        <!-- For Office Use Only -->
        <div class="for-office-use">
            <h3>For Office Use Only</h3>
            <div class="field-group three-col">
                <div class="field">
                    <label>Date Received</label>
                    <div class="line-sm"></div>
                </div>
                <div class="field">
                    <label>Received By</label>
                    <div class="line-sm"></div>
                </div>
                <div class="field">
                    <label>Payment Verified By</label>
                    <div class="line-sm"></div>
                </div>
            </div>
            <div class="field-group" style="margin-top: 15px;">
                <div class="field">
                    <label>Admission Number Assigned</label>
                    <div class="line-sm"></div>
                </div>
                <div class="field">
                    <label>Approved By</label>
                    <div class="line-sm"></div>
                </div>
            </div>
            <div style="margin-top: 15px;">
                <table class="fee-table" style="margin: 0;">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Fee Type</th>
                            <th class="text-end" style="width: 20%;">Amount (GHS)</th>
                            <th style="width: 20%;">Receipt No.</th>
                            <th style="width: 20%;">Paid By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Admission Fee</td>
                            <td class="text-end"><?php echo number_format((float)$admission_fee, 2); ?></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>Prospectus Fee</td>
                            <td class="text-end"><?php echo number_format((float)$prospectus_fee, 2); ?></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>Form Fee</td>
                            <td class="text-end"><?php echo number_format((float)$form_fee, 2); ?></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr class="total">
                            <td class="text-end">TOTAL PAID</td>
                            <td class="text-end"></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 10px; display: flex; gap: 40px;">
                <div style="text-align: center; flex: 1;">
                    <div class="signature-line" style="margin-top: 30px;"></div>
                    <strong>Finance Officer Signature</strong>
                </div>
                <div style="text-align: center; flex: 1;">
                    <div class="signature-line" style="margin-top: 30px;"></div>
                    <strong>Head Teacher / Admin Signature</strong>
                </div>
            </div>
        </div>

        <div class="note">
            <p>Thank you for choosing <?php echo htmlspecialchars($school_name); ?>. We look forward to welcoming your child.</p>
            <p style="margin-top: 3px;">This form can be downloaded from our website or obtained at the school's administration office.</p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            var element = document.getElementById('form-content');
            var opt = {
                margin: 10,
                filename: 'Enrollment_Form_Blank.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>
