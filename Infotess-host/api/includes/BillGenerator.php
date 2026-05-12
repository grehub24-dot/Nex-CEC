<?php
// Bill Generator for Enrollment Fee Bills
// Generates print-friendly HTML enrollment bills (same pattern as ReceiptGenerator)

class BillGenerator {
    /**
     * Generate an enrollment fee bill.
     *
     * @param array $enrollment  Student data (id, enrollment_id, full_name, class_name, gender, guardian_name, guardian_email, guardian_phone_primary, etc.)
     * @param array $fees        Fee breakdown: ['admission_fee' => 150.00, 'prospectus_fee' => 50.00, 'form_fee' => 20.00]
     * @param float $total       Total amount due
     * @param string $school_name School name
     * @return string            Filename of the generated bill (e.g. "bill_ENR-2026-A1B2C3.html")
     */
    public function generate($enrollment, $fees, $total, $school_name = 'Nex CEC') {
        $enrollmentRef = $enrollment['enrollment_id'] ?? 'ENR-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $filename = "bill_{$enrollmentRef}.html";
        
        // Try to save to bills/ directory (may fail on Vercel read-only filesystem — that's OK)
        $saved = false;
        $directory = __DIR__ . '/../bills/';
        if (is_dir($directory) || @mkdir($directory, 0777, true)) {
            $filepath = $directory . $filename;
        } else {
            // Fallback: try /tmp (Vercel writable temp dir)
            $directory = sys_get_temp_dir() . '/bills/';
            if (is_dir($directory) || @mkdir($directory, 0777, true)) {
                $filepath = $directory . $filename;
            } else {
                $filepath = null;
            }
        }

        // Convert local image to base64 for email compatibility
        $logoPath = __DIR__ . '/../images/school-logo.png';
        $logoData = '';
        if (file_exists($logoPath)) {
            $type = pathinfo($logoPath, PATHINFO_EXTENSION);
            $data = file_get_contents($logoPath);
            $logoData = 'data:image/' . $type . ';base64,' . base64_encode($data);
        } else {
            $logoPath = __DIR__ . '/../images/infotess.png';
            if (file_exists($logoPath)) {
                $type = pathinfo($logoPath, PATHINFO_EXTENSION);
                $data = file_get_contents($logoPath);
                $logoData = 'data:image/' . $type . ';base64,' . base64_encode($data);
            }
        }

        $studentName  = htmlspecialchars($enrollment['full_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
        $className    = htmlspecialchars($enrollment['class_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
        $gender       = htmlspecialchars($enrollment['gender'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
        $guardianName = htmlspecialchars($enrollment['guardian_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
        $guardianPhone = htmlspecialchars($enrollment['guardian_phone_primary'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
        $guardianEmail = htmlspecialchars($enrollment['guardian_email'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
        $guardianRel  = htmlspecialchars($enrollment['guardian_relationship'] ?? 'Guardian', ENT_QUOTES, 'UTF-8');
        $admissionFee = number_format((float)($fees['admission_fee'] ?? 0), 2);
        $prospectusFee = number_format((float)($fees['prospectus_fee'] ?? 0), 2);
        $formFee      = number_format((float)($fees['form_fee'] ?? 0), 2);
        $totalFormatted = number_format($total, 2);
        $generatedDate = date('j F Y');
        $schoolPhone  = htmlspecialchars($enrollment['school_phone'] ?? '+233 XX XXX XXXX', ENT_QUOTES, 'UTF-8');
        $schoolEmail  = htmlspecialchars($enrollment['school_email'] ?? 'info@school.edu.gh', ENT_QUOTES, 'UTF-8');
        $schoolAddress = htmlspecialchars($enrollment['school_address'] ?? 'School Address, City, Ghana', ENT_QUOTES, 'UTF-8');

        $html = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Enrollment Fee Bill - $enrollmentRef</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    background-color: #f8f9fa;
                    padding: 40px;
                    color: #333;
                }
                .bill-container {
                    max-width: 800px;
                    margin: 0 auto;
                    background: white;
                    padding: 40px;
                    border: 1px solid #ddd;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    position: relative;
                }
                .bill-header {
                    text-align: center;
                    border-bottom: 3px solid #1a5276;
                    padding-bottom: 20px;
                    margin-bottom: 30px;
                }
                .bill-header h1 {
                    color: #1a5276;
                    font-size: 24px;
                    font-weight: bold;
                    margin-bottom: 5px;
                    text-transform: uppercase;
                }
                .bill-header p {
                    margin: 2px 0;
                    font-size: 14px;
                    color: #555;
                }
                .bill-header h2 {
                    margin-top: 15px;
                    font-size: 20px;
                    font-weight: bold;
                    text-transform: uppercase;
                    color: #c0392b;
                    letter-spacing: 2px;
                }
                .logo {
                    width: 80px;
                    height: auto;
                    margin-bottom: 10px;
                    display: block;
                    margin-left: auto;
                    margin-right: auto;
                }
                .row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 25px;
                    gap: 20px;
                }
                .col {
                    width: 50%;
                }
                .details-title {
                    font-weight: bold;
                    font-size: 15px;
                    margin-bottom: 10px;
                    border-bottom: 2px solid #1a5276;
                    padding-bottom: 5px;
                    color: #1a5276;
                }
                .details-item {
                    margin-bottom: 6px;
                    font-size: 14px;
                    line-height: 1.5;
                }
                .details-item strong {
                    display: inline-block;
                    width: 120px;
                    color: #555;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 25px;
                }
                th, td {
                    border: 1px solid #ddd;
                    padding: 12px 15px;
                    text-align: left;
                }
                th {
                    background-color: #1a5276;
                    color: white;
                    font-weight: bold;
                    font-size: 14px;
                }
                .text-end {
                    text-align: right;
                }
                .text-center {
                    text-align: center;
                }
                .total-row td {
                    font-weight: bold;
                    font-size: 16px;
                    border-top: 2px solid #1a5276;
                }
                .total-amount {
                    color: #c0392b;
                    font-size: 18px;
                }
                .payment-info {
                    background-color: #f0f7ff;
                    border: 1px solid #b8d9e8;
                    border-radius: 6px;
                    padding: 20px;
                    margin-bottom: 25px;
                }
                .payment-info h3 {
                    color: #1a5276;
                    margin-bottom: 10px;
                    font-size: 16px;
                }
                .payment-info p {
                    font-size: 13px;
                    margin-bottom: 5px;
                    color: #333;
                }
                .payment-info strong {
                    color: #1a5276;
                }
                .ref-badge {
                    display: inline-block;
                    background: #1a5276;
                    color: white;
                    padding: 8px 20px;
                    border-radius: 4px;
                    font-size: 18px;
                    font-weight: bold;
                    letter-spacing: 1px;
                    margin: 10px 0;
                }
                .important-note {
                    background-color: #fff3cd;
                    border: 1px solid #ffc107;
                    border-radius: 6px;
                    padding: 15px;
                    font-size: 13px;
                    color: #856404;
                    margin-bottom: 25px;
                }
                .important-note strong {
                    color: #533f03;
                }
                .footer-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-end;
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #ddd;
                }
                .signature-section {
                    text-align: center;
                    width: 200px;
                }
                .signature-line {
                    border-top: 1px solid #333;
                    margin-bottom: 5px;
                    width: 200px;
                }
                .action-buttons {
                    max-width: 800px;
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
                .btn-print {
                    background-color: #1a5276;
                    color: white;
                }
                .btn-download {
                    background-color: #1a865c;
                    color: white;
                }
                @media print {
                    .no-print { display: none !important; }
                    body { background-color: white; padding: 0; }
                    .bill-container { box-shadow: none; border: none; padding: 20px; max-width: 100%; }
                }
            </style>
        </head>
        <body>
            <div class='action-buttons no-print'>
                <button onclick='window.print()' class='btn btn-print'>Print Bill</button>
                <button onclick='downloadPDF()' class='btn btn-download'>Download PDF</button>
            </div>
            <div class='bill-container' id='bill-content'>
                <div class='bill-header'>
                    " . (!empty($logoData) ? "<img src='$logoData' alt='Logo' class='logo'>" : "") . "
                    <h1>" . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . "</h1>
                    <p>$schoolAddress</p>
                    <p>Phone: $schoolPhone | Email: $schoolEmail</p>
                    <h2>Enrollment Fee Bill</h2>
                </div>

                <div class='text-center' style='margin-bottom: 20px;'>
                    <div class='ref-badge'>Ref: $enrollmentRef</div>
                    <p style='margin-top: 8px; font-size: 13px; color: #666;'>Date Generated: $generatedDate</p>
                </div>

                <div class='important-note'>
                    <strong>Important:</strong> Please bring this bill to the school to complete payment and enrollment.
                    Keep this document safe — you will need the reference number for all future correspondence.
                </div>

                <div class='row'>
                    <div class='col'>
                        <div class='details-title'>Student Information</div>
                        <div class='details-item'><strong>Full Name:</strong> $studentName</div>
                        <div class='details-item'><strong>Class:</strong> $className</div>
                        <div class='details-item'><strong>Gender:</strong> $gender</div>
                        <div class='details-item'><strong>Enrollment Ref:</strong> $enrollmentRef</div>
                    </div>
                    <div class='col'>
                        <div class='details-title'>Parent / Guardian Information</div>
                        <div class='details-item'><strong>Name:</strong> $guardianName</div>
                        <div class='details-item'><strong>Relationship:</strong> $guardianRel</div>
                        <div class='details-item'><strong>Phone:</strong> $guardianPhone</div>
                        <div class='details-item'><strong>Email:</strong> $guardianEmail</div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style='width: 60%;'>Fee Description</th>
                            <th class='text-end' style='width: 40%;'>Amount (GHS)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Admission Fee — One-time enrollment fee</td>
                            <td class='text-end'>GHS $admissionFee</td>
                        </tr>
                        <tr>
                            <td>Prospectus Fee — School prospectus and information pack</td>
                            <td class='text-end'>GHS $prospectusFee</td>
                        </tr>
                        <tr>
                            <td>Form Processing Fee — Enrollment form processing</td>
                            <td class='text-end'>GHS $formFee</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class='total-row'>
                            <td class='text-end' style='font-size: 16px;'>TOTAL DUE:</td>
                            <td class='text-end total-amount'>GHS $totalFormatted</td>
                        </tr>
                    </tfoot>
                </table>

                <div class='payment-info'>
                    <h3>Payment Instructions</h3>
                    <p>1. Bring this bill to the <strong>Finance Office</strong> at the school.</p>
                    <p>2. Make payment via <strong>Cash, Mobile Money, or Bank Transfer</strong>.</p>
                    <p>3. The school will issue an official receipt and complete the enrollment.</p>
                    <p>4. After enrollment, you will receive portal login credentials via SMS and email.</p>
                    <p style='margin-top: 10px;'><strong>Enrollment Reference:</strong> $enrollmentRef — Please quote this in all communications.</p>
                </div>

                <div class='footer-row'>
                    <div style='font-size: 12px; color: #666;'>
                        <p>Generated by INFOTESS SDMS</p>
                        <p>Bill Ref: $enrollmentRef</p>
                    </div>
                    <div class='signature-section'>
                        <div class='signature-line'></div>
                        <div style='font-weight: bold;'>Authorized Signature</div>
                        <div style='font-size: 12px; color: #666;'>School Administrator</div>
                    </div>
                </div>

                <div style='margin-top: 20px; font-size: 11px; color: #999; text-align: center;'>
                    <p>This is a computer-generated bill. It is valid only for the enrollment of the student named above.</p>
                    <p>For any enquiries, please contact the school administration.</p>
                </div>
            </div>

            <script src='https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js'></script>
            <script>
                function downloadPDF() {
                    var element = document.getElementById('bill-content');
                    var opt = {
                        margin:       10,
                        filename:     'Enrollment_Bill_$enrollmentRef.pdf',
                        image:        { type: 'jpeg', quality: 0.98 },
                        html2canvas:  { scale: 2, useCORS: true },
                        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
                    };
                    html2pdf().set(opt).from(element).save();
                }
            </script>
        </body>
        </html>";

        // Only write to disk if we have a writable path (may fail on Vercel read-only FS)
        if ($filepath !== null) {
            @file_put_contents($filepath, $html);
        }
        return $filename;
    }
}
