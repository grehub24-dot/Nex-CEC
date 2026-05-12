<?php
require_once 'includes/db.php';
require_once 'includes/Mailer.php';
require_once 'includes/SMSHelper.php';
require_once 'includes/BillGenerator.php';

// Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';
$school_motto = $settings['school_motto'] ?? 'Excellence in Education';
$school_address = $settings['school_address'] ?? '';
$school_phone = $settings['school_phone'] ?? '';
$school_email_setting = $settings['school_email'] ?? '';
$current_year = $settings['current_academic_year'] ?? date('Y') . '/' . (date('Y') + 1);
$admission_fee = (float)($settings['admission_fee'] ?? 150.00);
$prospectus_fee = (float)($settings['prospectus_fee'] ?? 50.00);
$form_fee = (float)($settings['enrollment_form_fee'] ?? 20.00);
$total_fees = $admission_fee + $prospectus_fee + $form_fee;

$error = '';
$success_ref = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    // Student details
    $full_name        = sanitize($_POST['full_name'] ?? '');
    $class_name       = sanitize($_POST['class_name'] ?? '');
    $gender           = sanitize($_POST['gender'] ?? '');
    $date_of_birth    = sanitize($_POST['date_of_birth'] ?? '');
    $place_of_birth   = sanitize($_POST['place_of_birth'] ?? '');
    $nationality      = sanitize($_POST['nationality'] ?? 'Ghanaian');
    $address          = sanitize($_POST['address'] ?? '');
    $previous_school  = sanitize($_POST['previous_school'] ?? '');
    $previous_class   = sanitize($_POST['previous_class'] ?? '');
    
    // Health info
    $health_insurance_id = sanitize($_POST['health_insurance_id'] ?? '');
    $medical_conditions  = sanitize($_POST['medical_conditions'] ?? '');
    $allergies           = sanitize($_POST['allergies'] ?? '');
    $special_needs       = sanitize($_POST['special_needs'] ?? '');
    
    // Guardian details
    $guardian_name           = sanitize($_POST['guardian_name'] ?? '');
    $guardian_relationship   = sanitize($_POST['guardian_relationship'] ?? '');
    $guardian_phone_primary  = sanitize($_POST['guardian_phone_primary'] ?? '');
    $guardian_phone_emergency = sanitize($_POST['guardian_phone_emergency'] ?? '');
    $guardian_email          = sanitize($_POST['guardian_email'] ?? '');
    $guardian_occupation     = sanitize($_POST['guardian_occupation'] ?? '');
    $guardian_address        = sanitize($_POST['guardian_address'] ?? '');

    // Validation
    if (empty($full_name) || empty($class_name) || empty($gender) || empty($guardian_name) || empty($guardian_phone_primary)) {
        $error = "Please fill in all required fields.";
    } elseif (empty($guardian_email) || !filter_var($guardian_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address for the parent/guardian.";
    } else {
        // Check if guardian email already exists in users (for returning parents)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$guardian_email]);
        $existing_user = $stmt->fetch();

        // Check duplicate enrollment by guardian email + student name
        $stmt = $pdo->prepare("SELECT id FROM students WHERE guardian_email = ? AND full_name = ? AND status != 'rejected'");
        $stmt->execute([$guardian_email, $full_name]);
        if ($stmt->fetch()) {
            $error = "A pending or active enrollment already exists for $full_name with this guardian email.";
        } else {
            // Generate enrollment reference
            $year_short = date('y');
            $random_part = strtoupper(substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 5));
            $enrollment_ref = "ENR-{$year_short}{$random_part}";
            
            // Ensure uniqueness
            $max_attempts = 5;
            $attempt = 0;
            while ($attempt < $max_attempts) {
                $stmt = $pdo->prepare("SELECT id FROM students WHERE enrollment_id = ?");
                $stmt->execute([$enrollment_ref]);
                if (!$stmt->fetch()) break;
                $random_part = strtoupper(substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 5));
                $enrollment_ref = "ENR-{$year_short}{$random_part}";
                $attempt++;
            }

            $pdo->beginTransaction();
            try {
                // Insert student with status='pending', no admission_number, no user_id
                $today = date('Y-m-d');
                $stmt = $pdo->prepare("INSERT INTO students (
                    enrollment_id, full_name, class_name, gender, date_of_birth, 
                    place_of_birth, nationality, address, previous_school, previous_class,
                    health_insurance_id, medical_conditions, allergies, special_needs,
                    guardian_name, guardian_relationship, guardian_phone_primary, 
                    guardian_phone_emergency, guardian_email, guardian_occupation, guardian_address,
                    status, academic_year, admission_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $enrollment_ref, $full_name, $class_name, $gender, $date_of_birth ?: null,
                    $place_of_birth ?: null, $nationality, $address ?: null, $previous_school ?: null, $previous_class ?: null,
                    $health_insurance_id ?: null, $medical_conditions ?: null, $allergies ?: null, $special_needs ?: null,
                    $guardian_name, $guardian_relationship ?: null, $guardian_phone_primary,
                    $guardian_phone_emergency ?: null, $guardian_email, $guardian_occupation ?: null, $guardian_address ?: null,
                    'pending', $current_year, $today
                ]);
                
                $student_id = $pdo->lastInsertId();

                $pdo->commit();

                // Generate fee bill
                $enrollment_data = [
                    'enrollment_id' => $enrollment_ref,
                    'full_name' => $full_name,
                    'class_name' => $class_name,
                    'gender' => $gender,
                    'guardian_name' => $guardian_name,
                    'guardian_email' => $guardian_email,
                    'guardian_phone_primary' => $guardian_phone_primary,
                    'guardian_relationship' => $guardian_relationship,
                    'school_phone' => $school_phone,
                    'school_email' => $school_email_setting,
                    'school_address' => $school_address,
                ];
                
                $fees = [
                    'admission_fee' => $admission_fee,
                    'prospectus_fee' => $prospectus_fee,
                    'form_fee' => $form_fee,
                ];
                
                try {
                    $billGen = new BillGenerator();
                    $billFile = $billGen->generate($enrollment_data, $fees, $total_fees, $school_name);
                    $billUrl = ($billFile ? 'bills/' . $billFile : '');
                } catch (Exception $e) {
                    $billUrl = '';
                    error_log("Bill generation error: " . $e->getMessage());
                }

                // Send Email to parent with bill
                try {
                    $mailer = new Mailer();
                    $subject = "Enrollment Submitted — $school_name";
                    
                    $billLink = '';
                    if (!empty($billUrl)) {
                        $appUrl = getAppUrl();
                        $billLink = "<p style='margin-top: 15px;'><a href='{$appUrl}/{$billUrl}' style='display: inline-block; background: #1a5276; color: white; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold;'>Download Fee Bill</a></p>";
                    }

                    $email_html = "
                    <!DOCTYPE html>
                    <html>
                    <head><meta charset='UTF-8'><style>
                        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
                        .email-container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                        .header { background: linear-gradient(to right, #1a5276, #2e86c1); color: white; text-align: center; padding: 40px 20px; }
                        .header h1 { margin: 0; font-size: 24px; }
                        .content { padding: 30px; color: #333; font-size: 14px; }
                        .ref-box { background: #f0f7ff; border: 2px dashed #1a5276; border-radius: 6px; padding: 20px; text-align: center; margin: 20px 0; }
                        .ref-box .ref { font-size: 24px; font-weight: bold; color: #1a5276; letter-spacing: 2px; }
                        .info-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                        .info-table td { padding: 8px 10px; border-bottom: 1px solid #eee; }
                        .info-table td:first-child { font-weight: bold; color: #555; width: 140px; }
                        .steps { margin: 20px 0; padding-left: 20px; line-height: 2; }
                        .footer { text-align: center; padding: 30px; font-size: 12px; color: #666; border-top: 1px solid #eee; }
                    </style></head>
                    <body>
                        <div class='email-container'>
                            <div class='header'>
                                <h1>Enrollment Submitted Successfully</h1>
                                <p>" . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . "</p>
                            </div>
                            <div class='content'>
                                <p>Dear <strong>" . htmlspecialchars($guardian_name, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                                <p>Your enrollment application for <strong>" . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') . "</strong> has been received.</p>
                                
                                <div class='ref-box'>
                                    <p style='font-size: 13px; margin-bottom: 5px;'>Your Enrollment Reference</p>
                                    <div class='ref'>" . htmlspecialchars($enrollment_ref) . "</div>
                                    <p style='font-size: 12px; color: #666; margin-top: 5px;'>Keep this number — you'll need it for all correspondence</p>
                                </div>
                                
                                <table class='info-table'>
                                    <tr><td>Student:</td><td>" . htmlspecialchars($full_name) . "</td></tr>
                                    <tr><td>Class:</td><td>" . htmlspecialchars($class_name) . "</td></tr>
                                    <tr><td>Total Fees Due:</td><td><strong>GHS " . number_format($total_fees, 2) . "</strong></td></tr>
                                </table>
                                
                                $billLink
                                
                                <h4 style='margin-top: 25px; color: #1a5276;'>What Happens Next?</h4>
                                <ol class='steps'>
                                    <li>Bring the fee bill to the school's finance office.</li>
                                    <li>Make payment via Cash, Mobile Money, or Bank Transfer.</li>
                                    <li>The school will complete the enrollment and assign an admission number.</li>
                                    <li>You will receive portal login credentials to access your child's information.</li>
                                </ol>
                                
                                <p style='margin-top: 20px;'>If you have any questions, please contact the school administration.</p>
                            </div>
                            <div class='footer'>
                                <p><strong>" . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . "</strong></p>
                                <p>This is an automated message. Please do not reply.</p>
                            </div>
                        </div>
                    </body></html>";
                    
                    $mailer->sendHTML($guardian_email, $subject, $email_html);
                } catch (Exception $e) {
                    error_log("Enrollment email error: " . $e->getMessage());
                }

                // Send SMS to parent
                try {
                    if (!empty($guardian_phone_primary)) {
                        $smsHelper = new SMSHelper();
                        $smsMsg = "ENROLLMENT: $full_name submitted at $school_name. Ref: $enrollment_ref. Total fee: GHS " . number_format($total_fees, 2) . ". Bring bill to school to pay. Check your email for details.";
                        $smsHelper->send($guardian_phone_primary, $smsMsg);
                    }
                } catch (Exception $e) {
                    error_log("Enrollment SMS error: " . $e->getMessage());
                }

                // Redirect to confirmation page
                header("Location: enrollment_confirm.php?ref=" . urlencode($enrollment_ref) . "&email=" . urlencode($guardian_email));
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error processing enrollment: " . $e->getMessage();
            }
        }
    }
}

require_once 'includes/header.php';
?>

<style>
    .enrollment-form { max-width: 900px; margin: 0 auto; padding: 20px; }
    .enrollment-form .card { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); padding: 30px; }
    .enrollment-form .form-section { 
        background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px;
        border-left: 4px solid #1a5276;
    }
    .enrollment-form .form-section h3 { 
        font-size: 16px; color: #1a5276; margin-bottom: 15px; 
        display: flex; align-items: center; gap: 8px;
    }
    .enrollment-form .form-section h3 .badge {
        background: #1a5276; color: white; border-radius: 50%;
        width: 24px; height: 24px; display: inline-flex;
        align-items: center; justify-content: center;
        font-size: 12px; margin-right: 5px;
    }
    .fee-summary {
        background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px;
        padding: 15px 20px; margin-top: 15px; text-align: center;
    }
    .fee-summary .amount { font-size: 24px; font-weight: bold; color: #c0392b; }
    .fee-summary .label { font-size: 13px; color: #856404; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }
    .full-width { grid-column: span 2; }
    @media (max-width: 600px) {
        .grid-2, .grid-3 { grid-template-columns: 1fr; }
        .full-width { grid-column: span 1; }
    }
</style>

<div class="enrollment-form">
    <div class="card">
        <div style="text-align: center; margin-bottom: 25px;">
            <h1 style="font-size: 24px; color: #1a5276;"><?php echo htmlspecialchars($school_name); ?></h1>
            <p style="color: #666; font-size: 15px;">Student Enrollment Form — <?php echo htmlspecialchars($current_year); ?></p>
            <p style="color: #888; font-size: 13px;">Fill in the form below to enroll your child. A fee bill will be sent to your email.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <input type="hidden" name="action" value="register">

            <!-- Section A: Student Details -->
            <div class="form-section">
                <h3><span class="badge">1</span> Student Details</h3>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Full Name <span style="color:red;">*</span></label>
                        <input type="text" name="full_name" class="form-control" required placeholder="e.g. Kwame Junior Asante">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Class Applying For <span style="color:red;">*</span></label>
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
                    <div class="form-group">
                        <label>Gender <span style="color:red;">*</span></label>
                        <select name="gender" class="form-control" required>
                            <option value="">-- Select --</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Place of Birth</label>
                        <input type="text" name="place_of_birth" class="form-control" placeholder="e.g. Kumasi">
                    </div>
                    <div class="form-group">
                        <label>Nationality</label>
                        <input type="text" name="nationality" class="form-control" value="Ghanaian">
                    </div>
                    <div class="form-group full-width">
                        <label>Home Address</label>
                        <input type="text" name="address" class="form-control" placeholder="e.g. 15 Bantama, Kumasi">
                    </div>
                    <div class="form-group">
                        <label>Previous School (if any)</label>
                        <input type="text" name="previous_school" class="form-control" placeholder="e.g. ABC Academy">
                    </div>
                    <div class="form-group">
                        <label>Previous Class</label>
                        <input type="text" name="previous_class" class="form-control" placeholder="e.g. Basic 3">
                    </div>
                </div>
            </div>

            <!-- Section B: Health Information -->
            <div class="form-section">
                <h3><span class="badge">2</span> Health Information</h3>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Health Insurance (NHIS) Number</label>
                        <input type="text" name="health_insurance_id" class="form-control" placeholder="e.g. NHIS-1234567">
                    </div>
                    <div class="form-group">
                        <label>Medical Conditions</label>
                        <input type="text" name="medical_conditions" class="form-control" placeholder="e.g. Asthma, Sickle cell, None">
                    </div>
                    <div class="form-group">
                        <label>Allergies</label>
                        <input type="text" name="allergies" class="form-control" placeholder="e.g. Penicillin, Dust, None">
                    </div>
                    <div class="form-group">
                        <label>Special Needs</label>
                        <input type="text" name="special_needs" class="form-control" placeholder="e.g. Visual impairment, None">
                    </div>
                </div>
            </div>

            <!-- Section C: Parent / Guardian Details -->
            <div class="form-section">
                <h3><span class="badge">3</span> Parent / Guardian Details</h3>
                <p style="font-size: 13px; color: #888; margin-bottom: 15px;">
                    The portal login credentials and all communications will be sent to the parent/guardian.
                </p>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Full Name <span style="color:red;">*</span></label>
                        <input type="text" name="guardian_name" class="form-control" required placeholder="e.g. Mr. Kwame Asante">
                    </div>
                    <div class="form-group">
                        <label>Relationship to Student <span style="color:red;">*</span></label>
                        <select name="guardian_relationship" class="form-control" required>
                            <option value="">-- Select --</option>
                            <option value="Father">Father</option>
                            <option value="Mother">Mother</option>
                            <option value="Guardian">Guardian</option>
                            <option value="Uncle">Uncle</option>
                            <option value="Aunt">Aunt</option>
                            <option value="Grandparent">Grandparent</option>
                            <option value="Sibling">Sibling</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Phone Number (Primary) <span style="color:red;">*</span></label>
                        <input type="text" name="guardian_phone_primary" class="form-control" required placeholder="e.g. 0244111222">
                    </div>
                    <div class="form-group">
                        <label>Phone (Secondary / Emergency)</label>
                        <input type="text" name="guardian_phone_emergency" class="form-control" placeholder="e.g. 0244111333">
                    </div>
                    <div class="form-group">
                        <label>Email Address <span style="color:red;">*</span></label>
                        <input type="email" name="guardian_email" class="form-control" required placeholder="parent@email.com">
                        <small style="color: #888;">Portal credentials and receipts will be sent here</small>
                    </div>
                    <div class="form-group">
                        <label>Occupation</label>
                        <input type="text" name="guardian_occupation" class="form-control" placeholder="e.g. Teacher, Trader, etc.">
                    </div>
                    <div class="form-group full-width">
                        <label>Residential / Postal Address</label>
                        <input type="text" name="guardian_address" class="form-control" placeholder="e.g. 15 Bantama High Street, Kumasi">
                    </div>
                </div>
            </div>

            <!-- Fee Summary -->
            <div class="fee-summary">
                <div class="label">Estimated Enrollment Fees</div>
                <div class="amount">GHS <?php echo number_format($total_fees, 2); ?></div>
                <div style="font-size: 13px; color: #856404; margin-top: 5px;">
                    Admission: GHS <?php echo number_format($admission_fee, 2); ?> 
                    + Prospectus: GHS <?php echo number_format($prospectus_fee, 2); ?> 
                    + Form: GHS <?php echo number_format($form_fee, 2); ?>
                </div>
                <div style="font-size: 12px; color: #856404; margin-top: 3px;">
                    Pay at the school to complete enrollment. A detailed bill will be sent to your email.
                </div>
            </div>

            <!-- Submit -->
            <div style="text-align: center; margin-top: 25px;">
                <button type="submit" class="btn-primary" style="padding: 15px 40px; font-size: 16px;">
                    <i class="fas fa-paper-plane"></i> Submit Enrollment
                </button>
                <p style="margin-top: 10px; font-size: 13px; color: #888;">
                    By submitting, you agree to the school's terms and conditions.
                </p>
            </div>

            <div style="text-align: center; margin-top: 15px;">
                <p style="font-size: 13px; color: #888;">
                    <i class="fas fa-download"></i> 
                    <a href="enrollment_blank_form.php" target="_blank">Download blank form</a> to fill by hand
                    | <a href="fees.php">View fee structure</a>
                </p>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
