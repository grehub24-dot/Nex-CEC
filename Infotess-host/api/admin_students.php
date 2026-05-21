<?php
require_once 'includes/db.php';
require_once 'includes/Mailer.php';

// Enforce access control
requireAccess('students');

// Fetch Settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';
$registered_student = null;

// Handle Student Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_student') {
    // CSRF validation
    validate_request_csrf();

    // Generate enrollment ID: ENR-YYYY-XXXXXX
    $enrollmentId = 'ENR-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $admission_number = null; // Will be assigned after payment

    $full_name = sanitize($_POST['full_name']);
    $class_name = sanitize($_POST['class_name']);
    $gender = sanitize($_POST['gender']);
    $guardian_email = sanitize($_POST['guardian_email']);
    
    // Guardian details
    $guardian_name = sanitize($_POST['guardian_name'] ?? '');
    $guardian_relationship = sanitize($_POST['guardian_relationship'] ?? '');
    $guardian_phone_primary = sanitize($_POST['guardian_phone_primary'] ?? '');
    $guardian_phone_emergency = sanitize($_POST['guardian_phone_emergency'] ?? '');
    $guardian_occupation = sanitize($_POST['guardian_occupation'] ?? '');
    $guardian_address = sanitize($_POST['guardian_address'] ?? '');
    
    // Student demographics
    $date_of_birth = sanitize($_POST['date_of_birth'] ?? '');
    $place_of_birth = sanitize($_POST['place_of_birth'] ?? '');
    $nationality = sanitize($_POST['nationality'] ?? 'Ghanaian');
    $address = sanitize($_POST['address'] ?? '');
    
    // Health information
    $health_insurance_id = sanitize($_POST['health_insurance_id'] ?? '');
    $medical_conditions = sanitize($_POST['medical_conditions'] ?? '');
    $allergies = sanitize($_POST['allergies'] ?? '');
    $special_needs = sanitize($_POST['special_needs'] ?? '');
    
    // Academic background
    $previous_school = sanitize($_POST['previous_school'] ?? '');
    $previous_class = sanitize($_POST['previous_class'] ?? '');
    $admission_date = sanitize($_POST['admission_date'] ?? date('Y-m-d'));
    $academic_year = sanitize($_POST['academic_year'] ?? date('Y') . '/' . (date('Y') + 1));
    
    // Handle Profile Picture — upload to Supabase Storage
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $filename = $enrollmentId . '_' . time() . '.' . $ext;
        $newUrl = upload_to_supabase_storage($_FILES['profile_picture'], 'profiles', $filename, 'images/aamusted.jpg');
        if (strpos($newUrl, 'http') === 0) {
            $profile_picture = $newUrl;
        }
    }

    // Check duplicate enrollment ID
    $stmt = $pdo->prepare("SELECT id FROM students WHERE enrollment_id = ?");
    $stmt->execute([$enrollmentId]);
    if ($stmt->fetch()) {
        $error = "Student with Enrollment ID $enrollmentId already exists.";
    } else {
        $pdo->beginTransaction();
        try {
            // Generate a random 6-character password
            $auto_password = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);

            // 1. Create User Account (email = guardian email for basic school)
            $password_hash = password_hash($auto_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$guardian_email, $password_hash, 'student']);
            $user_id = $pdo->lastInsertId();

            // 2. Create Student Record (Basic School schema)
            $stmt = $pdo->prepare("INSERT INTO students (
                user_id, admission_number, enrollment_id, full_name, class_name, gender, date_of_birth, place_of_birth,
                nationality, address, profile_picture,
                guardian_name, guardian_email, guardian_relationship,
                guardian_phone_primary, guardian_phone_emergency, guardian_occupation, guardian_address,
                health_insurance_id, medical_conditions, allergies, special_needs,
                previous_school, previous_class, admission_date, academic_year, enrollment_type,
                payment_status, status
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?
            )");
            $stmt->execute([
                $user_id, $admission_number, $enrollmentId, $full_name, $class_name, $gender,
                $date_of_birth ?: null, $place_of_birth, $nationality, $address, $profile_picture,
                $guardian_name, $guardian_email, $guardian_relationship,
                $guardian_phone_primary, $guardian_phone_emergency, $guardian_occupation, $guardian_address,
                $health_insurance_id, $medical_conditions, $allergies, $special_needs,
                $previous_school, $previous_class, $admission_date, $academic_year, 'admin',
                'unpaid', 'pending'
            ]);
            
            $student_id = $pdo->lastInsertId();

            $pdo->commit();
            $message = "Student registered successfully! Enrollment ID: $enrollmentId. Temporary password: $auto_password";
            $registered_student = [
                'id' => $student_id,
                'enrollment_id' => $enrollmentId,
                'full_name' => $full_name,
                'class_name' => $class_name
            ];

            // Send email to guardian
            if ($guardian_email) {
                $mailer = new Mailer();
                $subject = "Welcome — Student Registration at " . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8');
                $dateStr = date('n/j/Y');
                $html = "<div style=\"font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;\">
                    <div style=\"background: linear-gradient(90deg,#1a5276,#2e86c1); color:#fff; padding: 24px; text-align:center;\">
                        <div style=\"font-size: 20px; font-weight: 700;\">Welcome to " . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . "!</div>
                        <div style=\"margin-top:8px; font-size:14px; opacity:0.9;\">Student Registration Successful</div>
                    </div>
                    <div style=\"padding: 24px; color:#111827;\">
                        <p>Dear <strong>" . htmlspecialchars($guardian_name ?: 'Parent/Guardian', ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                        <p><strong>" . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') . "</strong> has been successfully registered. Details:</p>
                        <div style=\"border:1px solid #e5e7eb; border-radius:8px; padding:16px; background:#f9fafb; margin-top:12px;\">
                            <div style=\"display:grid; grid-template-columns: 180px 1fr; gap:8px; font-size:14px;\">
                                <div><strong>Student Name:</strong></div><div>" . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Enrollment ID:</strong></div><div>" . htmlspecialchars($enrollmentId, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Class:</strong></div><div>" . htmlspecialchars($class_name, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Guardian Email:</strong></div><div>" . htmlspecialchars($guardian_email, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Temp Password:</strong></div><div>" . htmlspecialchars($auto_password, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Admission Date:</strong></div><div>" . $dateStr . "</div>
                            </div>
                        </div>
                        <div style=\"margin-top:16px;\">
                            <div style=\"font-weight:600; margin-bottom:8px;\">Important:</div>
                            <ul style=\"margin:0; padding-left:20px; color:#374151; font-size:14px;\">
                                <li>Keep the enrollment ID safe — needed for all fee payments</li>
                                <li>Login and reset the password immediately</li>
                                <li>Payment receipts will be sent to this email</li>
                                <li>SMS notifications will be sent to the primary phone: " . htmlspecialchars($guardian_phone_primary, ENT_QUOTES, 'UTF-8') . "</li>
                            </ul>
                        </div>
                        <hr style=\"border:none; border-top:1px solid #e5e7eb; margin:20px 0;\"/>
                        <div style=\"font-size:13px; color:#6b7280; text-align:center;\">
                            <div style=\"font-weight:600;\">" . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . "</div>
                            <div style=\"margin-top:8px; font-size:12px;\">This is an automated email. Please do not reply.</div>
                        </div>
                    </div>
                </div>";
                $mailer->sendHTML($guardian_email, $subject, $html);
            }

            // Send SMS to guardian primary phone (fallback to emergency)
            $smsPhone = $guardian_phone_primary ?: $guardian_phone_emergency;
            if ($smsPhone) {
                $smsHelper = new SMSHelper();
                $smsMsg = "Registration successful: $full_name. Enrollment: $enrollmentId. Class: $class_name. Temp password: $auto_password. Login at " . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . " portal.";
                $smsHelper->send($smsPhone, $smsMsg);
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Handle Payment Confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_payment') {
    // CSRF validation
    validate_request_csrf();
    $studentId = (int)$_POST['student_id'];
    $method = sanitize($_POST['payment_method']);
    $enrollmentId = sanitize($_POST['enrollment_id']);

    // Generate admission number after payment
    // Bridge can't handle LIKE with param — find next counter in PHP instead
    $today = date('ymd');
    $allForCount = $pdo->query("SELECT admission_number FROM students")->fetchAll();
    $counter = 0;
    foreach ($allForCount as $s) {
        $adm = $s['admission_number'] ?? '';
        if (strpos($adm, "CEC-{$today}-") === 0) $counter++;
    }
    $admissionNumber = "CEC-{$today}-{$counter}";

    // Generate receipt number
    $receiptNumber = 'NXC-' . time() . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

    $pdo->beginTransaction();
    try {
        // Update student with admission number and status (bridge doesn't support literals in SET — use ? for all values)
        $pdo->prepare("UPDATE students SET admission_number = ?, payment_status = ?, status = ? WHERE id = ?")
            ->execute([$admissionNumber, 'paid', 'enrolled', $studentId]);

        // Record payment (bridge doesn't support NOW() in VALUES — use PHP timestamp and ? for literal status)
        $pdo->prepare("INSERT INTO payments (student_id, amount, payment_method, payment_date, receipt_number, status, enrollment_id) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$studentId, 150.00, $method, date('Y-m-d H:i:s'), $receiptNumber, 'completed', $enrollmentId]);

        $pdo->commit();
        $message = "Payment confirmed! Admission Number: $admissionNumber. Receipt: $receiptNumber";
        $registered_student = null;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Payment error: " . $e->getMessage();
    }
}

// Handle Student Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_student') {
    // CSRF validation
    validate_request_csrf();
    $studentId = (int)$_POST['student_id'];
    $studentName = sanitize($_POST['student_name'] ?? 'this student');

    try {
        // Get user_id before deleting student
        // NOTE: bridge ignores column list — use SELECT * and access by key.
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$studentId]);
        $stu_row = $stmt->fetch();
        $userId = $stu_row ? ($stu_row['user_id'] ?? null) : null;

        $pdo->beginTransaction();

        // Clean up all records referencing student_id
        $pdo->prepare("DELETE FROM payments WHERE student_id = ?")->execute([$studentId]);
        $pdo->prepare("DELETE FROM student_attendance WHERE student_id = ?")->execute([$studentId]);
        $pdo->prepare("DELETE FROM exam_scores WHERE student_id = ?")->execute([$studentId]);
        $pdo->prepare("DELETE FROM sba_scores WHERE student_id = ?")->execute([$studentId]);
        $pdo->prepare("DELETE FROM attendance_summary WHERE student_id = ?")->execute([$studentId]);
        $pdo->prepare("DELETE FROM report_cards WHERE student_id = ?")->execute([$studentId]);
        $pdo->prepare("DELETE FROM parent_students WHERE student_id = ?")->execute([$studentId]);

        // Clean up user-level FK references
        if ($userId) {
            $pdo->prepare("DELETE FROM messages WHERE sender_id = ?")->execute([$userId]);
            $pdo->prepare("DELETE FROM messages WHERE receiver_id = ?")->execute([$userId]);
            $pdo->prepare("DELETE FROM message_reads WHERE user_id = ?")->execute([$userId]);
            $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$userId]);
            $pdo->prepare("DELETE FROM executives WHERE user_id = ?")->execute([$userId]);
            $pdo->prepare("DELETE FROM parent_students WHERE parent_user_id = ?")->execute([$userId]);
            $pdo->prepare("UPDATE staff_invites SET invited_by = NULL WHERE invited_by = ?")->execute([$userId]);
        }

        // Delete the student record
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$studentId]);

        // Delete the associated user account (if exists)
        if ($userId) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
        }

        $pdo->commit();
        $message = "Student \"$studentName\" has been deleted successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error deleting student: " . $e->getMessage();
    }
}

// Handle Bulk Delete Students
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete_students') {
    validate_request_csrf();
    $ids = $_POST['student_ids'] ?? [];
    if (!empty($ids) && is_array($ids)) {
        $deleted_count = 0;
        $error_count = 0;
        foreach ($ids as $rawId) {
            $studentId = (int)$rawId;
            if ($studentId <= 0) continue;
            try {
                $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
                $stmt->execute([$studentId]);
                $stu_row = $stmt->fetch();
                $userId = $stu_row ? ($stu_row['user_id'] ?? null) : null;
                $pdo->beginTransaction();

                // Clean up all records referencing student_id
                $pdo->prepare("DELETE FROM payments WHERE student_id = ?")->execute([$studentId]);
                $pdo->prepare("DELETE FROM student_attendance WHERE student_id = ?")->execute([$studentId]);
                $pdo->prepare("DELETE FROM exam_scores WHERE student_id = ?")->execute([$studentId]);
                $pdo->prepare("DELETE FROM sba_scores WHERE student_id = ?")->execute([$studentId]);
                $pdo->prepare("DELETE FROM attendance_summary WHERE student_id = ?")->execute([$studentId]);
                $pdo->prepare("DELETE FROM report_cards WHERE student_id = ?")->execute([$studentId]);
                $pdo->prepare("DELETE FROM parent_students WHERE student_id = ?")->execute([$studentId]);

                // Clean up user-level FK references
                if ($userId) {
                    $pdo->prepare("DELETE FROM messages WHERE sender_id = ?")->execute([$userId]);
                    $pdo->prepare("DELETE FROM messages WHERE receiver_id = ?")->execute([$userId]);
                    $pdo->prepare("DELETE FROM message_reads WHERE user_id = ?")->execute([$userId]);
                    $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$userId]);
                    $pdo->prepare("DELETE FROM executives WHERE user_id = ?")->execute([$userId]);
                    $pdo->prepare("DELETE FROM parent_students WHERE parent_user_id = ?")->execute([$userId]);
                    $pdo->prepare("UPDATE staff_invites SET invited_by = NULL WHERE invited_by = ?")->execute([$userId]);
                }

                $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$studentId]);
                if ($userId) {
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
                }
                $pdo->commit();
                $deleted_count++;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_count++;
            }
        }
        $message = "$deleted_count student(s) deleted successfully.";
        if ($error_count > 0) {
            $message .= " $error_count failed.";
        }
    }
}

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Fetch all students (narrow columns to prevent 413 PAYLOAD_TOO_LARGE)
// Complex search/sort filtering is done in PHP.
$all_students = $pdo->query("SELECT id, profile_picture, admission_number, full_name, class_name, gender, guardian_name, guardian_relationship, guardian_phone_primary, guardian_phone_emergency, academic_year, created_at, status FROM students")->fetchAll();

// Apply search filter in PHP (matches both full_name and admission_number)
$search = $_GET['search'] ?? '';
if ($search !== '') {
    $students = array_filter($all_students, function($s) use ($search) {
        $term = strtolower($search);
        return stripos($s['full_name'] ?? '', $search) !== false
            || stripos($s['admission_number'] ?? '', $search) !== false;
    });
} else {
    $students = $all_students;
}

// Sort by created_at DESC and apply pagination in PHP
usort($students, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
$total_rows = count($students);
$students = array_slice($students, $offset, $limit);
$total_pages = $total_rows > 0 ? (int)ceil($total_rows / $limit) : 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 650px; border-radius: 8px; position: relative; }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover, .close-btn:focus { color: black; text-decoration: none; cursor: pointer; }
        .upload-preview { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 2px solid #d4dbe3; margin: 0 auto 10px auto; display: block; background: #f3f6f9; }
        .upload-file-name { margin-top: 8px; font-size: 0.82rem; color: #4b5563; }
        .section-divider { grid-column: span 2; border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px; }
        .section-divider h4 { font-size: 15px; color: #1a5276; margin: 0 0 10px 0; }
    </style>
</head>
<body>
    <div class="dashboard-container">
            <?php echo renderSidebar('students', $school_name); ?>

        <main class="main-content">
            <div class="top-bar">
                <h2>Student Management</h2>
                <div style="display:flex; gap:10px;">
                    <a href="bulk_import.php" class="btn-login" style="padding:10px 20px;"><i class="fas fa-file-csv"></i> Bulk Import</a>
                    <button id="openModalBtn" class="btn-primary" style="padding:10px 20px;"><i class="fas fa-plus"></i> Add New Student</button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Payment Section (shown after student registration) -->
            <?php if ($registered_student): ?>
            <div class="section" style="border: 2px solid #2e86c1; background: #f0f8ff;">
                <h3><i class="fas fa-credit-card"></i> Process Payment — <?php echo htmlspecialchars($registered_student['full_name']); ?></h3>
                <p><strong>Enrollment ID:</strong> <?php echo htmlspecialchars($registered_student['enrollment_id']); ?> |
                   <strong>Class:</strong> <?php echo htmlspecialchars($registered_student['class_name']); ?> |
                   <strong>Amount:</strong> GHS 150.00</p>

                <div style="display: flex; gap: 15px; margin-top: 15px; flex-wrap: wrap;">
                    <!-- MTN MoMo -->
                    <div style="flex: 1; min-width: 200px; border: 1px solid #ddd; padding: 15px; border-radius: 8px;">
                        <h4><i class="fas fa-mobile-alt"></i> MTN MoMo</h4>
                        <form method="POST" action="students.php">
                            <input type="hidden" name="action" value="confirm_payment">
                            <input type="hidden" name="student_id" value="<?php echo $registered_student['id']; ?>">
                            <input type="hidden" name="payment_method" value="MTN MoMo">
                            <input type="hidden" name="enrollment_id" value="<?php echo htmlspecialchars($registered_student['enrollment_id']); ?>">
                            <?php csrf_field(); ?>
                            <input type="text" name="phone" placeholder="024XXXXXXX" class="form-control" style="margin-bottom: 10px;" required>
                            <button type="submit" class="btn-primary" style="width:100%;">Process Payment</button>
                        </form>
                    </div>

                    <!-- Telecel Cash -->
                    <div style="flex: 1; min-width: 200px; border: 1px solid #ddd; padding: 15px; border-radius: 8px;">
                        <h4><i class="fas fa-phone"></i> Telecel Cash</h4>
                        <form method="POST" action="students.php">
                            <input type="hidden" name="action" value="confirm_payment">
                            <input type="hidden" name="student_id" value="<?php echo $registered_student['id']; ?>">
                            <input type="hidden" name="payment_method" value="Telecel Cash">
                            <input type="hidden" name="enrollment_id" value="<?php echo htmlspecialchars($registered_student['enrollment_id']); ?>">
                            <?php csrf_field(); ?>
                            <input type="text" name="phone" placeholder="020XXXXXXX" class="form-control" style="margin-bottom: 10px;" required>
                            <button type="submit" class="btn-primary" style="width:100%;">Process Payment</button>
                        </form>
                    </div>

                    <!-- Bank/Cash -->
                    <div style="flex: 1; min-width: 200px; border: 1px solid #ddd; padding: 15px; border-radius: 8px;">
                        <h4><i class="fas fa-piggy-bank"></i> Bank / Cash</h4>
                        <button onclick="showVoucher()" class="btn-primary" style="width:100%;">Print Payment Voucher</button>
                    </div>
                </div>
            </div>

            <!-- Payment Voucher (Printable) -->
            <div id="paymentVoucher" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:2000;">
                <div style="background:white; width:600px; margin:50px auto; padding:30px; border-radius:8px; position:relative;">
                    <button onclick="document.getElementById('paymentVoucher').style.display='none'" style="position:absolute; top:10px; right:15px; font-size:20px; background:none; border:none; cursor:pointer;">&times;</button>
                    <div id="voucherContent">
                        <div style="text-align:center; border-bottom:2px solid #2e86c1; padding-bottom:15px; margin-bottom:20px;">
                            <h2 style="margin:0; color:#1a5276;">Nex CEC Basic School</h2>
                            <p style="margin:5px 0; color:#666;">Payment Voucher</p>
                        </div>
                        <div style="display:grid; grid-template-columns: 180px 1fr; gap:10px; font-size:14px;">
                            <div><strong>Voucher Number:</strong></div><div id="voucherNum"></div>
                            <div><strong>Date:</strong></div><div><?php echo date('Y-m-d H:i:s'); ?></div>
                            <div><strong>Enrollment ID:</strong></div><div><?php echo htmlspecialchars($registered_student['enrollment_id']); ?></div>
                            <div><strong>Student Name:</strong></div><div><?php echo htmlspecialchars($registered_student['full_name']); ?></div>
                            <div><strong>Class:</strong></div><div><?php echo htmlspecialchars($registered_student['class_name']); ?></div>
                            <div><strong>Amount:</strong></div><div style="font-weight:700; color:#2e86c1;">GHS 150.00</div>
                        </div>
                        <div style="margin-top:25px; padding:15px; background:#fffde7; border:1px solid #f0c929; border-radius:6px; text-align:center;">
                            <strong>Instructions:</strong> Bring this voucher to the school office with payment
                        </div>
                    </div>
                    <div style="margin-top:20px; text-align:center;">
                        <button onclick="window.print()" class="btn-primary" style="padding:10px 30px;"><i class="fas fa-print"></i> Print Voucher</button>
                        <button onclick="confirmCashPayment()" class="btn-submit" style="padding:10px 30px; margin-left:10px;">Confirm Payment Received</button>
                    </div>
                </div>
            </div>

            <script>
            function showVoucher() {
                document.getElementById('voucherNum').textContent = 'NXC-' + Date.now() + '-' + Math.random().toString(36).substr(2, 4).toUpperCase();
                document.getElementById('paymentVoucher').style.display = 'block';
            }
            function confirmCashPayment() {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'students.php';
                form.innerHTML = '<input type="hidden" name="action" value="confirm_payment">' +
                    '<input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">' +
                    '<input type="hidden" name="student_id" value="<?php echo $registered_student['id']; ?>">' +
                    '<input type="hidden" name="payment_method" value="Bank/Cash">' +
                    '<input type="hidden" name="enrollment_id" value="<?php echo htmlspecialchars($registered_student['enrollment_id']); ?>">';
                document.body.appendChild(form);
                form.submit();
            }
            </script>
            <?php endif; ?>

            <!-- Add Student Modal -->
            <div id="studentModal" class="modal">
                <div class="modal-content" style="max-width: 800px;">
                    <span class="close-btn">&times;</span>
                    <h3>Register New Student</h3>
                    <form action="students.php" method="POST" enctype="multipart/form-data" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top: 15px;">
                        <input type="hidden" name="action" value="add_student">
                        <?php csrf_field(); ?>
                        
                        <!-- Profile Picture -->
                        <div style="grid-column: span 2; text-align: center; margin-bottom: 10px;">
                            <label>Profile Picture</label><br>
                            <img id="studentUploadPreview" src="../images/aamusted.jpg" alt="Profile Preview" class="upload-preview">
                            <input type="file" name="profile_picture" id="studentProfileUpload" class="form-control" accept="image/*">
                            <div id="studentUploadFileName" class="upload-file-name">No image selected</div>
                        </div>

                        <!-- Basic Info Section -->
                        <div class="section-divider" style="grid-column: span 2; border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px;">
                            <h4><i class="fas fa-user"></i> Student Information</h4>
                        </div>

                        <div>
                            <label>Full Name *</label>
                            <input type="text" name="full_name" class="form-control" required placeholder="e.g. Kwame Asante">
                        </div>
                        <div>
                            <label>Enrollment ID (Auto-generated)</label>
                            <input type="text" name="enrollment_id_display" class="form-control" value="ENR-<?php echo date('Y'); ?>-XXXXXX" readonly style="background:#f0f0f0; cursor:not-allowed;" title="Auto-generated on submission">
                        </div>
                        <div>
                            <label>Class *</label>
                            <select name="class_name" class="form-control" required>
                                <option value="">-- Select Class --</option>
                                <optgroup label="Early Childhood">
                                    <option value="Creche">Creche</option>
                                    <option value="Nursery 1">Nursery 1</option>
                                    <option value="Nursery 2">Nursery 2</option>
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
                            <label>Gender *</label>
                            <select name="gender" class="form-control" required>
                                <option value="">-- Select --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div>
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control">
                        </div>
                        <div>
                            <label>Place of Birth</label>
                            <input type="text" name="place_of_birth" class="form-control" placeholder="e.g. Kumasi">
                        </div>
                        <div>
                            <label>Nationality</label>
                            <input type="text" name="nationality" class="form-control" value="Ghanaian" placeholder="e.g. Ghanaian">
                        </div>
                        <div>
                            <label>Place of Residence</label>
                            <input type="text" name="address" class="form-control" placeholder="e.g. Kumasi">
                        </div>

                        <!-- Guardian Section -->
                        <div class="section-divider" style="grid-column: span 2; border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px;">
                            <h4><i class="fas fa-user-shield"></i> Guardian Details</h4>
                        </div>

                        <div>
                            <label>Guardian Name *</label>
                            <input type="text" name="guardian_name" class="form-control" required placeholder="e.g. Mr. Asante">
                        </div>
                        <div>
                            <label>Relationship *</label>
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
                        <div>
                            <label>Guardian Email *</label>
                            <input type="email" name="guardian_email" class="form-control" required placeholder="parent@email.com">
                        </div>
                        <div>
                            <label>Guardian Occupation</label>
                            <input type="text" name="guardian_occupation" class="form-control" placeholder="e.g. Teacher">
                        </div>
                        <div>
                            <label>Primary Phone *</label>
                            <input type="text" name="guardian_phone_primary" class="form-control" required placeholder="e.g. 0241234567">
                        </div>
                        <div>
                            <label>Emergency Phone</label>
                            <input type="text" name="guardian_phone_emergency" class="form-control" placeholder="Fallback contact">
                        </div>
                        <div style="grid-column: span 2;">
                            <label>Guardian Address</label>
                            <input type="text" name="guardian_address" class="form-control" placeholder="Guardian residential/work address">
                        </div>

                        <!-- Health Section -->
                        <div class="section-divider" style="grid-column: span 2; border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px;">
                            <h4><i class="fas fa-heartbeat"></i> Health Information</h4>
                        </div>

                        <div>
                            <label>Health Insurance ID</label>
                            <input type="text" name="health_insurance_id" class="form-control" placeholder="NHIS number">
                        </div>
                        <div style="grid-column: span 2;">
                            <label>Allergies</label>
                            <input type="text" name="allergies" class="form-control" placeholder="e.g. Peanuts, Penicillin">
                        </div>
                        <div style="grid-column: span 2;">
                            <label>Medical Conditions</label>
                            <textarea name="medical_conditions" class="form-control" rows="2" placeholder="e.g. Asthma, Diabetes, Epilepsy"></textarea>
                        </div>
                        <div style="grid-column: span 2;">
                            <label>Special Needs</label>
                            <textarea name="special_needs" class="form-control" rows="2" placeholder="Any learning or physical special needs"></textarea>
                        </div>

                        <!-- Academic Background -->
                        <div class="section-divider" style="grid-column: span 2; border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px;">
                            <h4><i class="fas fa-school"></i> Academic Background</h4>
                        </div>

                        <div>
                            <label>Previous School</label>
                            <input type="text" name="previous_school" class="form-control" placeholder="e.g. ABC Kindergarten">
                        </div>
                        <div>
                            <label>Previous Class</label>
                            <input type="text" name="previous_class" class="form-control" placeholder="e.g. KG 2">
                        </div>
                        <div>
                            <label>Academic Year</label>
                            <select name="academic_year" class="form-control">
                                <?php
                                $curr = (int)date('Y');
                                for ($i = -1; $i <= 5; $i++) {
                                    $yr = ($curr + $i) . '/' . ($curr + $i + 1);
                                    $sel = ($i === 0) ? 'selected' : '';
                                    echo "<option value=\"$yr\" $sel>$yr</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div style="grid-column: span 2; margin-top: 10px;">
                            <button type="submit" class="btn-submit" style="width:100%;">Register Student</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Student List -->
            <div class="section">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                    <h3>Registered Students</h3>
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <form action="students.php" method="GET" style="display:flex; gap:10px;">
                            <input type="text" name="search" placeholder="Search name or admission number..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn-login"><i class="fas fa-search"></i></button>
                        </form>
                    </div>
                </div>

                <!-- Bulk Delete Toolbar -->
                <form method="POST" action="students.php" id="studentBulkForm">
                    <input type="hidden" name="action" value="bulk_delete_students">
                    <?php csrf_field(); ?>
                    <div style="margin-bottom:15px; display:flex; align-items:center; gap:10px;">
                        <button type="button" onclick="confirmStudentBulkDelete()" class="btn-login" style="background:#e74c3c; color:#fff; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; font-size:0.9rem;">
                            <i class="fas fa-trash"></i> Delete Selected
                        </button>
                        <span id="studentSelectedCount" style="color:#666; font-size:0.85rem;">0 selected</span>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table" id="studentTable">
                            <thead>
                                <tr>
                                    <th style="width:40px;"><input type="checkbox" id="studentSelectAll" onchange="toggleStudentAll(this)"></th>
                                    <th>Photo</th>
                                    <th>Adm. No.</th>
                                    <th>Name</th>
                                    <th>Class</th>
                                    <th>Gender</th>
                                    <th>Guardian</th>
                                    <th>Primary Phone</th>
                                    <th>Academic Year</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                <tr>
                                    <td style="text-align:center;"><input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" class="student-checkbox" onchange="updateStudentSelectedCount()"></td>
                                    <td>
                                        <img src="<?php echo resolve_storage_url($student['profile_picture'] ?? ''); ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #ddd;">
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($student['admission_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['class_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($student['gender'] ?? '-'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($student['guardian_name'] ?? '-'); ?>
                                        <?php if (!empty($student['guardian_relationship'])): ?>
                                            <br><small style="color:#666;">(<?php echo htmlspecialchars($student['guardian_relationship']); ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($student['guardian_phone_primary'] ?? '-'); ?>
                                        <?php if (!empty($student['guardian_phone_emergency'])): ?>
                                            <br><small style="color:#e74c3c;">Emer: <?php echo htmlspecialchars($student['guardian_phone_emergency']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($student['academic_year'] ?? '-'); ?></small></td>
                                    <td>
                                        <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn-login" style="background:#f0ad4e;">Edit</a>
                                        <button type="button" class="btn-login" style="background:#e74c3c; color:#fff; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-size:12px;" onclick="showDeleteConfirm(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars(addslashes($student['full_name']), ENT_QUOTES, 'UTF-8'); ?>')"><i class="fas fa-trash"></i> Delete</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div style="display:flex; justify-content:center; gap:5px; margin-top:20px; flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" style="display:inline-flex; align-items:center; gap:5px; padding:8px 16px; background:#f8f9fa; color:#000; border:1px solid #ddd; border-radius:6px; text-decoration:none; font-size:14px;">&laquo; Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" style="display:inline-flex; align-items:center; justify-content:center; min-width:38px; padding:8px 12px; background:<?php echo $i == $page ? '#1a5276' : '#f8f9fa'; ?>; color:<?php echo $i == $page ? '#fff' : '#000'; ?>; border:1px solid <?php echo $i == $page ? '#1a5276' : '#ddd'; ?>; border-radius:6px; text-decoration:none; font-size:14px; font-weight:<?php echo $i == $page ? '700' : '400'; ?>;"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" style="display:inline-flex; align-items:center; gap:5px; padding:8px 16px; background:#f8f9fa; color:#000; border:1px solid #ddd; border-radius:6px; text-decoration:none; font-size:14px;">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // ========================================
        // DELETE CONFIRMATION MODAL
        // ========================================
        function showDeleteConfirm(studentId, studentName) {
            document.getElementById('deleteStudentId').value = studentId;
            document.getElementById('deleteStudentName').textContent = studentName;
            document.getElementById('deleteStudentForm').action = 'students.php';
            document.getElementById('deleteConfirmModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
        }

        // Close modal on backdrop click
        document.addEventListener('click', function(e) {
            var modal = document.getElementById('deleteConfirmModal');
            if (e.target === modal) closeDeleteModal();
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeDeleteModal();
        });

        // ========================================
        // BULK DELETE FUNCTIONS
        // ========================================
        function toggleStudentAll(master) {
            var cbs = document.querySelectorAll('.student-checkbox');
            cbs.forEach(function(cb) { cb.checked = master.checked; });
            updateStudentSelectedCount();
        }
        function updateStudentSelectedCount() {
            var cbs = document.querySelectorAll('.student-checkbox:checked');
            var el = document.getElementById('studentSelectedCount');
            if (el) el.textContent = cbs.length + ' selected';
        }
        function confirmStudentBulkDelete() {
            var cbs = document.querySelectorAll('.student-checkbox:checked');
            if (cbs.length === 0) { alert('No students selected.'); return; }
            if (confirm('Delete ' + cbs.length + ' selected student(s)? This will also remove their guardian accounts and cannot be undone.')) {
                document.getElementById('studentBulkForm').submit();
            }
        }

        // ========================================
        // ADD STUDENT MODAL
        // ========================================
        // Force close any stuck modal on load
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById("studentModal");
            if (modal) modal.style.display = "none";
        });

        const modal = document.getElementById("studentModal");
        const btn = document.getElementById("openModalBtn");
        const closeBtn = document.querySelector(".modal .close-btn");

        if (btn && modal) btn.onclick = function() { modal.style.display = "block"; }
        if (closeBtn && modal) closeBtn.onclick = function() { modal.style.display = "none"; }
        if (modal) {
            window.onclick = function(event) { if (event.target == modal) { modal.style.display = "none"; } }
        }
        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal && modal.style.display === 'block') {
                modal.style.display = 'none';
            }
        });

        const studentProfileUpload = document.getElementById('studentProfileUpload');
        const studentUploadPreview = document.getElementById('studentUploadPreview');
        const studentUploadFileName = document.getElementById('studentUploadFileName');

        if (studentProfileUpload && studentUploadPreview && studentUploadFileName) {
            studentProfileUpload.addEventListener('change', function() {
                const file = this.files && this.files[0] ? this.files[0] : null;
                if (!file) { studentUploadPreview.src = '../images/aamusted.jpg'; studentUploadFileName.textContent = 'No image selected'; return; }
                studentUploadFileName.textContent = file.name;
                if (!file.type.startsWith('image/')) { studentUploadPreview.src = '../images/aamusted.jpg'; studentUploadFileName.textContent = 'Please select an image file'; this.value = ''; return; }
                const reader = new FileReader();
                reader.onload = function(event) { studentUploadPreview.src = event.target.result; };
                reader.readAsDataURL(file);
            });
        }
    </script>

    <!-- ========================================
         DELETE CONFIRMATION MODAL
         ======================================== -->
    <div id="deleteConfirmModal" style="display:none; position:fixed; z-index:2000; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
        <div style="background:#fff; margin:10% auto; padding:0; width:90%; max-width:500px; border-radius:10px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.3); border-top:5px solid #e74c3c;">
            <!-- Warning Header -->
            <div style="background:#e74c3c; padding:20px 24px; display:flex; align-items:center; gap:12px;">
                <i class="fas fa-exclamation-triangle" style="font-size:28px; color:#fff;"></i>
                <div>
                    <div style="font-size:18px; font-weight:700; color:#fff; margin:0;">Delete Student Record</div>
                    <div style="font-size:12px; color:rgba(255,255,255,0.8); margin:4px 0 0 0;">This action cannot be undone</div>
                </div>
            </div>

            <!-- Modal Body -->
            <div style="padding:24px;">
                <!-- Warning Badge -->
                <div style="background:#fef3cd; border:1px solid #ffc107; border-radius:8px; padding:14px 16px; margin-bottom:20px; display:flex; align-items:flex-start; gap:10px;">
                    <i class="fas fa-exclamation-circle" style="color:#856404; font-size:20px; margin-top:2px; flex-shrink:0;"></i>
                    <div style="font-size:13px; color:#856404; line-height:1.5;">
                        <strong>You are about to permanently delete:</strong><br>
                        <span id="deleteStudentName" style="font-weight:700; font-size:15px;"></span><br>
                        This will also remove the guardian's login account and cannot be recovered.
                    </div>
                </div>

                <!-- Warning Points -->
                <ul style="font-size:13px; color:#6c757d; padding-left:18px; margin:0 0 24px 0; line-height:1.8;">
                    <li>All academic records (grades, attendance, report cards) will be lost</li>
                    <li>Payment history for this student will be affected</li>
                    <li>The guardian will no longer be able to access the parent portal</li>
                    <li>This student will be removed from all classes and groups</li>
                </ul>

                <!-- Actions -->
                <div style="display:flex; gap:12px; justify-content:flex-end;">
                    <button type="button" onclick="closeDeleteModal()" style="padding:10px 24px; background:#f8f9fa; color:#495057; border:1px solid #dee2e6; border-radius:6px; cursor:pointer; font-size:14px; font-weight:600;">
                        <i class="fas fa-times"></i>&nbsp; Cancel
                    </button>
                    <form id="deleteStudentForm" method="POST" style="margin:0;">
                        <input type="hidden" name="action" value="delete_student">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="student_id" id="deleteStudentId" value="">
                        <input type="hidden" name="student_name" id="deleteStudentNameHidden" value="">
                        <button type="submit" onclick="document.getElementById('deleteStudentNameHidden').value = document.getElementById('deleteStudentName').textContent;" style="padding:10px 24px; background:#dc3545; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:14px; font-weight:600;">
                            <i class="fas fa-trash"></i>&nbsp; Delete Student
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
