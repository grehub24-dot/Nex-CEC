<?php
require_once 'includes/db.php';
require_once 'includes/Mailer.php';
require_once 'includes/SMSHelper.php';
requireAccess('enrollments');

// Fetch Settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$school_name = $settings['school_name'] ?? 'Nex CEC';
$school_address = $settings['school_address'] ?? '';
$school_phone = $settings['school_phone'] ?? '';

$message = '';
$error = '';

/**
 * Create a parent user account and link to student.
 */
function createParentAccount($pdo, $student, $school_name) {
    $guardian_email = $student['guardian_email'];
    $guardian_name  = $student['guardian_name'];
    $guardian_phone = $student['guardian_phone_primary'] ?? '';
    $student_id     = $student['id'];

    // Check if parent user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$guardian_email]);
    $existing = $stmt->fetch();

    $is_new = false;
    if ($existing) {
        $parent_user_id = $existing['id'];
    } else {
        // Create new parent user account
        $auto_password = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);
        $password_hash = password_hash($auto_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (email, password, role, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$guardian_email, $password_hash, 'parent', 'active']);
        $parent_user_id = $pdo->lastInsertId();
        $is_new = true;
    }

    // Create parent_students link (if not already linked)
    $stmt = $pdo->prepare("SELECT id FROM parent_students WHERE parent_user_id = ? AND student_id = ?");
    $stmt->execute([$parent_user_id, $student_id]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO parent_students (parent_user_id, student_id, relationship, is_primary) VALUES (?, ?, ?, ?)");
        $stmt->execute([$parent_user_id, $student_id, $student['guardian_relationship'] ?? 'Guardian', true]);
    }

    // Update student record with user_id (for backward compatibility)
    $stmt = $pdo->prepare("UPDATE students SET user_id = ? WHERE id = ?");
    $stmt->execute([$parent_user_id, $student_id]);

    // Send welcome email with credentials (only for new accounts)
    if ($is_new && !empty($guardian_email)) {
        try {
            $appUrl = getAppUrl();
            $mailer = new Mailer();
            $subject = "Parent Portal Access — $school_name";

            $email_html = "
            <!DOCTYPE html>
            <html>
            <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><style>
                body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; }
                .header { background: linear-gradient(to right, #1a5276, #2e86c1); color: white; text-align: center; padding: 40px 20px; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px; color: #333; font-size: 14px; }
                .cred-box { background: #f0f7ff; border: 1px solid #b8d9e8; border-radius: 6px; padding: 20px; margin: 20px 0; }
                .cred-box .label { font-size: 12px; color: #666; }
                .cred-box .value { font-size: 18px; font-weight: bold; color: #1a5276; }
                .btn-green { display: inline-block; background: #27ae60; color: white; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 15px; }
                .footer { text-align: center; padding: 30px; font-size: 12px; color: #666; border-top: 1px solid #eee; }
            </style></head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Parent Portal Access</h1>
                        <p>" . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . "</p>
                    </div>
                    <div class='content'>
                        <p>Dear <strong>" . htmlspecialchars($guardian_name, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                        <p>Your child <strong>" . htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8') . "</strong> has been enrolled at " . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . ".</p>
                        <p>You now have access to the parent portal where you can:</p>
                        <ul>
                            <li>View fees and make payments</li>
                            <li>Download receipts</li>
                            <li>View report cards</li>
                            <li>Check attendance</li>
                            <li>Receive school messages</li>
                        </ul>
                        <div class='cred-box'>
                            <div class='label'>Login Email</div>
                            <div class='value'>" . htmlspecialchars($guardian_email, ENT_QUOTES, 'UTF-8') . "</div>
                            <div style='margin-top: 15px;'></div>
                            <div class='label'>Temporary Password</div>
                            <div class='value'>" . htmlspecialchars($auto_password, ENT_QUOTES, 'UTF-8') . "</div>
                        </div>
                        <p style='text-align: center;'>
                            <a href='{$appUrl}/login.php' class='btn-green'>Login to Parent Portal</a>
                        </p>
                        <p style='margin-top: 15px; font-size: 12px; color: #888;'>
                            You will be required to change your password after first login.
                        </p>
                    </div>
                    <div class='footer'>
                        <p><strong>" . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . "</strong></p>
                        <p>This is an automated message.</p>
                    </div>
                </div>
            </body></html>";

            $mailer->sendHTML($guardian_email, $subject, $email_html);
        } catch (Exception $e) {
            error_log("Parent welcome email error: " . $e->getMessage());
        }

        // Send SMS
        try {
            if (!empty($guardian_phone)) {
                $smsHelper = new SMSHelper();
                $smsMsg = "PORTAL ACCESS: Your child " . $student['full_name'] . " has been enrolled at $school_name. Login: $guardian_email / Password: $auto_password. Login at: " . getAppUrl() . "/login.php";
                $smsHelper->send($guardian_phone, $smsMsg);
            }
        } catch (Exception $e) {
            error_log("Parent welcome SMS error: " . $e->getMessage());
        }
    } elseif (!$is_new) {
        // Notify existing parent that a new child was added
        try {
            if (!empty($guardian_email)) {
                $mailer = new Mailer();
                $subject = "New Child Enrolled — $school_name";
                $appUrl = getAppUrl();
                $email_html = "
                <!DOCTYPE html>
                <html>
                <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><style>
                    body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
                    .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; }
                    .header { background: linear-gradient(to right, #1a5276, #2e86c1); color: white; text-align: center; padding: 30px 20px; }
                    .header h1 { margin: 0; font-size: 22px; }
                    .content { padding: 30px; }
                    .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; border-top: 1px solid #eee; }
                </style></head>
                <body>
                    <div class='container'>
                        <div class='header'><h1>New Child Enrolled</h1></div>
                        <div class='content'>
                            <p>Dear <strong>" . htmlspecialchars($guardian_name, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                            <p><strong>" . htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8') . "</strong> has been enrolled at " . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . " and added to your parent portal.</p>
                            <p>Log in to your portal to view your children's information.</p>
                            <p style='text-align: center;'><a href='{$appUrl}/login.php' style='display: inline-block; background: #1a5276; color: white; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold;'>Login to Portal</a></p>
                        </div>
                        <div class='footer'><p>" . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . "</p></div>
                    </div>
                </body></html>";
                $mailer->sendHTML($guardian_email, $subject, $email_html);
            }
        } catch (Exception $e) {
            error_log("Existing parent notification error: " . $e->getMessage());
        }
    }

    return $parent_user_id;
}

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'approve') {
        // Assign admission number
        $today = date('ymd');
        $allStudentsForCount = $pdo->query("SELECT admission_number FROM students")->fetchAll();
        $counter = 0;
        foreach ($allStudentsForCount as $s) {
            $adm = $s['admission_number'] ?? '';
            if (strpos($adm, "CEC-{$today}-") === 0) {
                $counter++;
            }
        }
        $admissionNumber = "CEC-{$today}-{$counter}";
        $pdo->prepare("UPDATE students SET admission_number = ?, status = ? WHERE id = ?")->execute([$admissionNumber, 'active', $id]);

        // Fetch student and create parent account
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $student = $stmt->fetch();

        if ($student) {
            try {
                createParentAccount($pdo, $student, $school_name);
                $message = "Enrollment approved! Admission Number: $admissionNumber. Parent portal credentials sent.";
            } catch (Exception $e) {
                $message = "Enrollment approved! Admission Number: $admissionNumber. (Note: parent account creation failed: " . $e->getMessage() . ")";
            }
        } else {
            $message = "Enrollment approved! Admission Number: $admissionNumber";
        }

    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE students SET status = ? WHERE id = ?")->execute(['rejected', $id]);

        // Notify parent
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $student = $stmt->fetch();
        if ($student && !empty($student['guardian_email'])) {
            try {
                $mailer = new Mailer();
                $subject = "Enrollment Status — $school_name";
                $email_html = "
                <!DOCTYPE html>
                <html>
                <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><style>
                    body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
                    .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; }
                    .header { background: #e74c3c; color: white; text-align: center; padding: 30px 20px; }
                    .header h1 { margin: 0; font-size: 22px; }
                    .content { padding: 30px; }
                    .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; border-top: 1px solid #eee; }
                </style></head>
                <body>
                    <div class='container'>
                        <div class='header'><h1>Enrollment Update</h1></div>
                        <div class='content'>
                            <p>Dear <strong>" . htmlspecialchars($student['guardian_name'] ?? '', ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                            <p>Regarding the enrollment of <strong>" . htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8') . "</strong> at " . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . ":</p>
                            <p>We regret to inform you that your enrollment application (Ref: " . htmlspecialchars($student['enrollment_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . ") could not be approved at this time.</p>
                            <p>Please contact the school administration for more information.</p>
                        </div>
                        <div class='footer'><p>" . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . "</p></div>
                    </div>
                </body></html>";
                $mailer->sendHTML($student['guardian_email'], $subject, $email_html);
            } catch (Exception $e) {
                error_log("Rejection email error: " . $e->getMessage());
            }
        }
        $message = "Enrollment rejected.";
    } elseif ($action === 'pay_approve') {
        // Record payment & approve in one step
        $amount = isset($_GET['amount']) ? (float)$_GET['amount'] : 0;
        $method = isset($_GET['method']) ? sanitize($_GET['method']) : 'Cash';

        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $student = $stmt->fetch();

        if (!$student) {
            $error = "Student not found.";
        } elseif ($amount <= 0) {
            $error = "Invalid payment amount.";
        } else {
            // Assign admission number
            $today = date('ymd');
            $allStudentsForCount = $pdo->query("SELECT admission_number FROM students")->fetchAll();
            $counter = 0;
            foreach ($allStudentsForCount as $s) {
                $adm = $s['admission_number'] ?? '';
                if (strpos($adm, "CEC-{$today}-") === 0) { $counter++; }
            }
            $admissionNumber = "CEC-{$today}-{$counter}";

            // Generate receipt number
            $receiptNumber = "INFO-" . date('md') . "-" . rand(1000, 9999);

            $pdo->beginTransaction();
            try {
                // Update student status
                $today = date('Y-m-d');
                $pdo->prepare("UPDATE students SET admission_number = ?, status = ?, payment_status = ? WHERE id = ?")
                    ->execute([$admissionNumber, 'active', 'paid', $id]);

                // Record payment
                $stmt = $pdo->prepare("INSERT INTO payments (student_id, amount, academic_year, semester, payment_method, payment_date, receipt_number, recorded_by, status, enrollment_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $semester = $settings['current_term'] ?? '1';
                $year = $settings['current_academic_year'] ?? date('Y') . '/' . (date('Y') + 1);
                $stmt->execute([$id, $amount, $year, $semester, $method, $today, $receiptNumber, $_SESSION['user_id'], 'completed', $student['enrollment_id']]);

                $pdo->commit();

                // Create parent account
                try {
                    createParentAccount($pdo, $student, $school_name);
                } catch (Exception $e) {
                    error_log("Parent account creation after payment: " . $e->getMessage());
                }

                // Generate receipt HTML
                require_once 'includes/ReceiptGenerator.php';
                try {
                    $receiptGen = new ReceiptGenerator();
                    $receiptGen->generate($id, $receiptNumber, $student, $amount, date('Y-m-d'), $student['class_name'] ?? '', 'Admission', $school_name, 0, $year, $semester, $method);
                } catch (Exception $e) {
                    error_log("Receipt generation error: " . $e->getMessage());
                }

                $message = "Payment recorded! Receipt: $receiptNumber. Admission Number: $admissionNumber. Parent portal credentials sent.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error processing payment: " . $e->getMessage();
            }
        }
    }

    // Redirect back to enrollments page
    $redirectFilter = $_GET['filter'] ?? 'all';
    $redirectSearch = urlencode($_GET['search'] ?? '');
    $redirectPage   = isset($_GET['page']) ? '&page=' . (int)$_GET['page'] : '';
    header("Location: enrollments.php?filter={$redirectFilter}&search={$redirectSearch}{$redirectPage}");
    exit;
}

// Fetch all students (bridge only handles simple WHERE col = ?)
// All complex filtering is done in PHP below.
try {
    $allStudents = $pdo->query("SELECT * FROM students")->fetchAll();
} catch (Exception $e) {
    $allStudents = [];
}

// Fetch enrollment inquiries
$inquiries = [];
try {
    $inquiries = $pdo->query("SELECT * FROM enrollment_inquiries ORDER BY created_at DESC")->fetchAll();
} catch (Exception $e) {
    $inquiries = [];
}
$totalInquiries = count($inquiries);

$totalStudents = count($allStudents);
$totalApproved  = count(array_filter($allStudents, fn($s) => !empty($s['admission_number'])));
$totalRejected  = count(array_filter($allStudents, fn($s) => ($s['status'] ?? '') === 'rejected'));
// Pending = no admission_number AND not rejected (matches Pending tab filter exactly)
$pendingCount   = count(array_filter($allStudents, fn($s) => empty($s['admission_number']) && ($s['status'] ?? '') !== 'rejected'));
$totalEnrolled  = count(array_filter($allStudents, fn($s) => !empty($s['admission_number']) && ($s['status'] ?? '') !== 'rejected'));
$today = date('Y-m-d');
$enrolledToday  = count(array_filter($allStudents, fn($s) => !empty($s['admission_date']) && substr($s['admission_date'], 0, 10) === $today));

// Fetch enrollments — bridge only handles bare SELECT with LIMIT/OFFSET.
// All filtering is done in PHP.
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$showInquiries = ($filter === 'inquiries');

// PHP-side filter matches the tab logic
$enrollments = $showInquiries ? [] : array_filter($allStudents, function($s) use ($filter, $search) {
    // Apply tab filter first
    if ($filter === 'pending')   { if ( !empty($s['admission_number']) || ($s['status'] ?? '') === 'rejected') return false; }
    if ($filter === 'enrolled') { if ( empty($s['admission_number']) || ($s['status'] ?? '') === 'rejected') return false; }
    if ($filter === 'rejected') { if (($s['status'] ?? '') !== 'rejected') return false; }

    // Apply search filter (matches both full_name and admission_number)
    if ($search !== '') {
        $term = strtolower($search);
        $matchName = stripos($s['full_name'] ?? '', $search) !== false;
        $matchAdm  = stripos($s['admission_number'] ?? '', $search) !== false;
        if (!$matchName && !$matchAdm) return false;
    }
    return true;
});

// Sort by admission_date DESC
usort($enrollments, fn($a, $b) => strcmp($b['admission_date'] ?? '', $a['admission_date'] ?? ''));

// Pagination
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$total_enrollments = count($enrollments);
$total_pages = $total_enrollments > 0 ? (int)ceil($total_enrollments / $limit) : 1;
$offset = ($page - 1) * $limit;
$enrollments = array_slice($enrollments, $offset, $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Enrollments — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); text-align: center; }
        .stat-card .stat-number { font-size: 28px; font-weight: 700; color: #1a5276; }
        .stat-card .stat-label { font-size: 13px; color: #666; margin-top: 5px; }
        .stat-card.pending .stat-number { color: #f39c12; }
        .stat-card.enrolled .stat-number { color: #27ae60; }
        .stat-card.today .stat-number { color: #3498db; }
        .stat-card.rejected .stat-number { color: #e74c3c; }
        .filter-tabs { display: flex; gap: 10px; margin-bottom: 15px; }
        .filter-tabs a { padding: 8px 18px; border-radius: 20px; background: #f0f0f0; color: #333; text-decoration: none; font-size: 14px; }
        .filter-tabs a.active { background: #1a5276; color: #fff; }
        .table-responsive { overflow-x: auto; }
        .btn-approve { background: #27ae60; color: #fff; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; }
        .btn-reject { background: #e74c3c; color: #fff; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; }
        .btn-pay { background: #8e44ad; color: #fff; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; }
        .btn-view { background: #3498db; color: #fff; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; }
        .status-badge { padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.enrolled { background: #d4edda; color: #155724; }
        .status-badge.rejected { background: #f8d7da; color: #721c24; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 3% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 700px; border-radius: 8px; position: relative; }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover { color: black; }
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; }
        .detail-item { padding: 8px; background: #f9fafb; border-radius: 4px; }
        .detail-item .label { font-size: 12px; color: #666; }
        .detail-item .value { font-weight: 600; color: #333; }
        .section-title { grid-column: span 2; font-weight: 700; color: #1a5276; margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php echo renderSidebar('enrollments', $school_name); ?>

        <main class="main-content">
            <div class="top-bar">
                <h2>Manage Enrollments</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <?php if (!$showInquiries): ?>
            <div class="stats-grid">
                <div class="stat-card pending">
                    <div class="stat-number"><?php echo (int)$pendingCount; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card today">
                    <div class="stat-number"><?php echo (int)$enrolledToday; ?></div>
                    <div class="stat-label">Enrolled Today</div>
                </div>
                <div class="stat-card enrolled">
                    <div class="stat-number"><?php echo (int)$totalEnrolled; ?></div>
                    <div class="stat-label">Total Enrolled</div>
                </div>
                <div class="stat-card rejected">
                    <div class="stat-number"><?php echo (int)$totalRejected; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
            <?php else: ?>
            <!-- Inquiries stat -->
            <div class="stats-grid">
                <div class="stat-card pending">
                    <div class="stat-number"><?php echo (int)$totalInquiries; ?></div>
                    <div class="stat-label">Total Inquiries</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Search and Filter -->
            <div class="section">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap: wrap; gap: 10px;">
                    <div class="filter-tabs">
                        <a href="?filter=pending&page=1" class="<?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                        <a href="?filter=enrolled&page=1" class="<?php echo $filter === 'enrolled' ? 'active' : ''; ?>">Approved</a>
                        <a href="?filter=rejected&page=1" class="<?php echo $filter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
                        <a href="?filter=all&page=1" class="<?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                        <a href="?filter=inquiries&page=1" class="<?php echo $filter === 'inquiries' ? 'active' : ''; ?>" style="display:inline-flex;align-items:center;gap:6px;">Inquiries <?php if ($totalInquiries > 0): ?><span style="background:#e74c3c;color:#fff;font-size:11px;padding:2px 8px;border-radius:10px;font-weight:700;"><?php echo $totalInquiries; ?></span><?php endif; ?></a>
                    </div>
                    <?php if (!$showInquiries): ?>
                    <form action="enrollments.php" method="GET" style="display:flex; gap:10px;">
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <input type="hidden" name="page" value="1">
                        <input type="text" name="search" placeholder="Search name or admission #..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-login"><i class="fas fa-search"></i></button>
                    </form>
                    <?php endif; ?>
                </div>

                <?php if ($showInquiries): ?>

                <!-- Inquiries Table -->
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Parent Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Child Name</th>
                                <th>Class Applying</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inquiries)): ?>
                                <tr><td colspan="7" style="text-align:center; padding: 30px; color: #888;">No inquiries yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($inquiries as $inq): ?>
                                <tr>
                                    <td style="white-space:nowrap;"><?php echo $inq['created_at'] ? date('n/j/Y g:i a', strtotime($inq['created_at'])) : '-'; ?></td>
                                    <td><strong><?php echo htmlspecialchars($inq['parent_name'] ?? '-'); ?></strong></td>
                                    <td><a href="mailto:<?php echo htmlspecialchars($inq['email'] ?? ''); ?>"><?php echo htmlspecialchars($inq['email'] ?? '-'); ?></a></td>
                                    <td><?php echo htmlspecialchars($inq['phone'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($inq['child_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($inq['class_applying'] ?? '-'); ?></td>
                                    <td style="max-width:250px; white-space:normal; word-break:break-word; font-size:13px; color:#555;"><?php echo htmlspecialchars($inq['message'] ?? '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php else: ?>

                <!-- Enrollments Table -->
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Admission #</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Guardian</th>
                                <th>Phone</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($enrollments)): ?>
                                <tr><td colspan="8" style="text-align:center; padding: 30px; color: #888;">No enrollments found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($enrollments as $en): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($en['admission_number'] ?? '-'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($en['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($en['class_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($en['guardian_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($en['guardian_phone_primary'] ?? '-'); ?></td>
                                    <td><?php echo $en['admission_date'] ? date('n/j/Y', strtotime($en['admission_date'])) : '-'; ?></td>
                                    <td><span class="status-badge <?php echo $en['status']; ?>"><?php echo ucfirst($en['status']); ?></span></td>
                                    <td>
                                        <?php if (empty($en['admission_number'])): ?>
                                            <a href="?action=approve&id=<?php echo $en['id']; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" class="btn-approve" onclick="return confirm('Approve this enrollment?')">Approve</a>
                                            <a href="?action=reject&id=<?php echo $en['id']; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" class="btn-reject" onclick="return confirm('Reject this enrollment?')">Reject</a>
                                            <a href="#" class="btn-pay" onclick="payApprove(<?php echo $en['id']; ?>); return false;">Pay & Approve</a>
                                        <?php endif; ?>
                                        <a href="#" class="btn-view" onclick="showDetails(<?php echo htmlspecialchars(json_encode($en)); ?>); return false;">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!$showInquiries && $total_pages > 1): ?>
                <div style="display:flex; justify-content:center; gap:5px; margin-top:20px; flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo urlencode($filter); ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" style="display:inline-flex; align-items:center; gap:5px; padding:8px 16px; background:#f8f9fa; color:#000; border:1px solid #ddd; border-radius:6px; text-decoration:none; font-size:14px;">&laquo; Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&filter=<?php echo urlencode($filter); ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" style="display:inline-flex; align-items:center; justify-content:center; min-width:38px; padding:8px 12px; background:<?php echo $i == $page ? '#1a5276' : '#f8f9fa'; ?>; color:<?php echo $i == $page ? '#fff' : '#000'; ?>; border:1px solid <?php echo $i == $page ? '#1a5276' : '#ddd'; ?>; border-radius:6px; text-decoration:none; font-size:14px; font-weight:<?php echo $i == $page ? '700' : '400'; ?>;"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo urlencode($filter); ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" style="display:inline-flex; align-items:center; gap:5px; padding:8px 16px; background:#f8f9fa; color:#000; border:1px solid #ddd; border-radius:6px; text-decoration:none; font-size:14px;">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                <div style="text-align:center; margin-top:10px; font-size:13px; color:#888;">
                    Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_enrollments; ?> total enrollments)
                </div>
                <?php endif; ?>

                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('detailsModal').style.display='none'">&times;</span>
            <h3>Enrollment Details</h3>
            <div id="modalBody"></div>
        </div>
    </div>

    <!-- Pay & Approve Modal -->
    <div id="payModal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <span class="close-btn" onclick="document.getElementById('payModal').style.display='none'">&times;</span>
            <h3>Pay & Approve Enrollment</h3>
            <p style="font-size: 13px; color: #888; margin-bottom: 15px;">
                Record payment and approve this enrollment in one step.
            </p>
            <form id="payForm" method="GET" action="enrollments.php">
                <input type="hidden" name="action" value="pay_approve">
                <input type="hidden" name="id" id="payStudentId" value="">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <input type="hidden" name="page" value="<?php echo (int)$page; ?>">
                <div class="form-group">
                    <label>Payment Amount (GHS)</label>
                    <input type="number" step="0.01" name="amount" id="payAmount" class="form-control" required placeholder="e.g. 220.00">
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="method" class="form-control" required>
                        <option value="Cash">Cash</option>
                        <option value="Mobile Money">Mobile Money</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px;">
                    <button type="button" class="btn-reject" onclick="document.getElementById('payModal').style.display='none'" style="padding: 8px 20px; border: none; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn-approve" style="padding: 8px 20px; border: none; cursor: pointer;">Record Payment & Approve</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function payApprove(studentId) {
            document.getElementById('payStudentId').value = studentId;
            // Suggest default amount
            var amountField = document.getElementById('payAmount');
            if (amountField) amountField.value = '220.00';
            document.getElementById('payModal').style.display = 'block';
        }

        function showDetails(en) {
            const statusColors = { pending: '#856404', enrolled: '#155724', rejected: '#721c24' };
            const statusBg = { pending: '#fff3cd', enrolled: '#d4edda', rejected: '#f8d7da' };
            let html = '<div class="detail-grid">';
            html += '<div class="detail-item"><div class="label">Admission #</div><div class="value">' + (en.admission_number || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Status</div><div class="value"><span class="status-badge ' + (en.status || '') + '">' + (en.status ? en.status.charAt(0).toUpperCase() + en.status.slice(1) : '-') + '</span></div></div>';
            html += '<div class="section-title">Student Information</div>';
            html += '<div class="detail-item"><div class="label">Full Name</div><div class="value">' + (en.full_name || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Class</div><div class="value">' + (en.class_name || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Gender</div><div class="value">' + (en.gender || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Date of Birth</div><div class="value">' + (en.date_of_birth || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Place of Birth</div><div class="value">' + (en.place_of_birth || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Nationality</div><div class="value">' + (en.nationality || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Address</div><div class="value">' + (en.address || '-') + '</div></div>';
            html += '<div class="section-title">Guardian Information</div>';
            html += '<div class="detail-item"><div class="label">Guardian Name</div><div class="value">' + (en.guardian_name || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Relationship</div><div class="value">' + (en.guardian_relationship || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Email</div><div class="value">' + (en.guardian_email || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Primary Phone</div><div class="value">' + (en.guardian_phone_primary || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Emergency Phone</div><div class="value">' + (en.guardian_phone_emergency || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Occupation</div><div class="value">' + (en.guardian_occupation || '-') + '</div></div>';
            html += '<div class="detail-item" style="grid-column: span 2;"><div class="label">Guardian Address</div><div class="value">' + (en.guardian_address || '-') + '</div></div>';
            html += '<div class="section-title">Academic Background</div>';
            html += '<div class="detail-item"><div class="label">Previous School</div><div class="value">' + (en.previous_school || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Previous Class</div><div class="value">' + (en.previous_class || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Admission Date</div><div class="value">' + (en.admission_date || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Academic Year</div><div class="value">' + (en.academic_year || '-') + '</div></div>';
            html += '<div class="section-title">Health Information</div>';
            html += '<div class="detail-item"><div class="label">Health Insurance ID</div><div class="value">' + (en.health_insurance_id || '-') + '</div></div>';
            html += '<div class="detail-item" style="grid-column: span 2;"><div class="label">Medical Conditions</div><div class="value">' + (en.medical_conditions || 'None') + '</div></div>';
            html += '<div class="detail-item" style="grid-column: span 2;"><div class="label">Allergies</div><div class="value">' + (en.allergies || 'None') + '</div></div>';
            html += '<div class="detail-item" style="grid-column: span 2;"><div class="label">Special Needs</div><div class="value">' + (en.special_needs || 'None') + '</div></div>';
            html += '</div>';
            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('detailsModal').style.display = 'block';
        }

        // Close modal on outside click
        window.onclick = function(event) {
            var modal = document.getElementById('detailsModal');
            if (event.target == modal) modal.style.display = 'none';
        }
        // Close on Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') document.getElementById('detailsModal').style.display = 'none';
        });
    </script>
</body>
</html>
