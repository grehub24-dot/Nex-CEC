<?php
require_once 'includes/db.php';
require_once 'includes/Mailer.php';
require_once 'includes/SMSHelper.php';

// Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Settings table may not exist yet
}
$school_name = $settings['school_name'] ?? 'Nex CEC';
$school_motto = $settings['school_motto'] ?? 'Excellence in Education';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $full_name = sanitize($_POST['full_name']);
    $index_number = sanitize($_POST['index_number']);
    $class_name = sanitize($_POST['class_name']);
    $gender = sanitize($_POST['gender']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone_number'] ?? '');
    $guardian_name = sanitize($_POST['guardian_name']);
    $guardian_phone = sanitize($_POST['guardian_phone']);

    // Check duplicate
    $stmt = $pdo->prepare("SELECT id FROM students WHERE index_number = ?");
    $stmt->execute([$index_number]);
    if ($stmt->fetch()) {
        $error = "Student with Index Number $index_number already exists.";
    } else {
        // Check email duplicate
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email address already registered.";
        } else {
            $pdo->beginTransaction();
            try {
                // Generate a random 6-character password
                $auto_password = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
                
                // 1. Create User Account
                $password_hash = password_hash($auto_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'student')");
                $stmt->execute([$email, $password_hash]);
                $user_id = $pdo->lastInsertId();

                // 2. Create Student Record
                $stmt = $pdo->prepare("INSERT INTO students (user_id, index_number, full_name, class_name, gender, phone_number, guardian_name, guardian_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $index_number, $full_name, $class_name, $gender, $phone, $guardian_name, $guardian_phone]);

                $pdo->commit();
                $message = "Student registered successfully!";

                // Send Email
                $mailer = new Mailer();
                $subject = "Welcome — Student Registration Successful";
                $dateStr = date('n/j/Y');
                
                $email_html = "
                <!DOCTYPE html>
                <html>
                <head><meta charset='UTF-8'><style>
                    body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
                    .email-container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                    .header { background: linear-gradient(to right, #1a5276, #2e86c1); color: white; text-align: center; padding: 40px 20px; }
                    .header h1 { margin: 0; font-size: 26px; }
                    .content { padding: 30px; color: #333; font-size: 14px; }
                    .info-box { border: 1px solid #eee; border-left: 4px solid #2e86c1; border-radius: 4px; padding: 0; margin-top: 20px; }
                    .info-title { color: #2e86c1; font-size: 16px; font-weight: bold; padding: 15px 20px; }
                    .info-row { padding: 12px 20px; border-top: 1px solid #eee; display: flex; }
                    .info-label { color: #333; font-weight: bold; width: 150px; }
                    .info-value { color: #555; }
                    .notes { margin-top: 30px; font-size: 13px; }
                    .footer { text-align: center; padding: 30px; font-size: 12px; color: #666; border-top: 1px solid #eee; }
                </style></head>
                <body>
                    <div class='email-container'>
                        <div class='header'><h1>Student Registration Successful</h1><p>" . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . "</p></div>
                        <div class='content'>
                            <p>Dear <strong>" . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                            <p>Your registration has been completed successfully.</p>
                            <div class='info-box'>
                                <div class='info-title'>Student Details</div>
                                <div class='info-row'><div class='info-label'>Name:</div><div class='info-value'>" . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') . "</div></div>
                                <div class='info-row'><div class='info-label'>Index Number:</div><div class='info-value'>" . htmlspecialchars($index_number, ENT_QUOTES, 'UTF-8') . "</div></div>
                                <div class='info-row'><div class='info-label'>Class:</div><div class='info-value'>" . htmlspecialchars($class_name, ENT_QUOTES, 'UTF-8') . "</div></div>
                                <div class='info-row'><div class='info-label'>Gender:</div><div class='info-value'>" . htmlspecialchars($gender, ENT_QUOTES, 'UTF-8') . "</div></div>
                                <div class='info-row'><div class='info-label'>Guardian:</div><div class='info-value'>" . htmlspecialchars($guardian_name, ENT_QUOTES, 'UTF-8') . "</div></div>
                                <div class='info-row'><div class='info-label'>Guardian Phone:</div><div class='info-value'>" . htmlspecialchars($guardian_phone, ENT_QUOTES, 'UTF-8') . "</div></div>
                                <div class='info-row'><div class='info-label'>Temporary Password:</div><div class='info-value'>" . htmlspecialchars($auto_password, ENT_QUOTES, 'UTF-8') . "</div></div>
                            </div>
                            <div class='notes'><ul>
                                <li>Keep your index number safe — you'll need it for all fee payments</li>
                                <li>Login and reset your password immediately</li>
                                <li>Payment receipts will be sent to this email</li>
                            </ul></div>
                        </div>
                        <div class='footer'><p><strong>" . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . "</strong></p><p>This is an automated message.</p></div>
                    </div>
                </body></html>";
                
                $mailer->sendHTML($email, $subject, $email_html);

                // Send SMS to guardian
                if ($guardian_phone) {
                    $smsHelper = new SMSHelper();
                    $smsMsg = "Registration successful for $full_name. Index: $index_number. Class: $class_name. Temp password: $auto_password. Login and reset password.";
                    $smsHelper->send($guardian_phone, $smsMsg);
                }

                header("refresh:3;url=login.php");

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="container" style="padding: 40px 0; max-width: 800px; margin: 0 auto;">
    <div class="card">
        <div class="card-header" style="text-align: center; border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 20px;">
            <h2><?php echo htmlspecialchars($school_name); ?> — Student Enrollment</h2>
            <p>Register a new student in the school management system</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?>
                <br><br>
                <a href="login.php" class="btn-primary" style="display:inline-block; padding:12px 24px; border-radius:6px; text-decoration:none;">Login</a>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="grid-form" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
            <input type="hidden" name="action" value="register">
            
            <div class="form-group">
                <label>Student Full Name</label>
                <input type="text" name="full_name" class="form-control" required placeholder="e.g. Kwame Asante">
            </div>
            <div class="form-group">
                <label>Index / Admission Number</label>
                <input type="text" name="index_number" class="form-control" required placeholder="e.g. NXC/2026/001">
            </div>
            <div class="form-group">
                <label>Class</label>
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
                <label>Gender</label>
                <select name="gender" class="form-control" required>
                    <option value="">-- Select --</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>
            <div class="form-group">
                <label>Student Phone (if applicable)</label>
                <input type="text" name="phone_number" class="form-control" placeholder="Optional for older students">
            </div>
            <div class="form-group">
                <label>Email (for fee receipts)</label>
                <input type="email" name="email" class="form-control" required placeholder="parent@email.com">
            </div>
            
            <!-- Guardian Details -->
            <div class="form-group full-width" style="grid-column: span 2; border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px;">
                <h3 style="font-size: 16px; margin-bottom: 15px; color: #1a5276;"><i class="fas fa-user-shield"></i> Parent / Guardian Details</h3>
            </div>
            <div class="form-group">
                <label>Guardian Full Name</label>
                <input type="text" name="guardian_name" class="form-control" required placeholder="e.g. Mr. Asante">
            </div>
            <div class="form-group">
                <label>Guardian Phone Number</label>
                <input type="text" name="guardian_phone" class="form-control" required placeholder="e.g. 0241234567">
            </div>
            
            <div class="form-group full-width" style="grid-column: span 2; text-align: center; margin-top: 20px;">
                <button type="submit" class="btn-primary" style="width: 100%; max-width: 300px; padding: 15px;">Enroll Student</button>
            </div>
            
            <div class="form-group full-width" style="grid-column: span 2; text-align: center; margin-top: 10px;">
                <p>Already registered? <a href="login.php">Login here</a></p>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
