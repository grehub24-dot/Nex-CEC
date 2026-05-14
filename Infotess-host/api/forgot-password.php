<?php
require_once 'includes/db.php';
require_once 'includes/Mailer.php';
require_once 'includes/SMSHelper.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'student') {
        redirect('student/dashboard.php');
    } elseif ($role === 'parent') {
        redirect('parent/dashboard.php');
    } else {
        redirect('admin/dashboard.php');
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? ''); // Email or Index Number

    if (empty($identifier)) {
        $error = "Please enter your Email or Admission Number.";
    } else {
        // Find user by email or admission number
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            // Admin/Executive or Student via email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->execute(['email' => $identifier]);
            $user = $stmt->fetch();
            
            if ($user && $user['role'] === 'student') {
                 $stmt_s = $pdo->prepare("SELECT * FROM students WHERE user_id = :uid");
                 $stmt_s->execute(['uid' => $user['id']]);
                 $student = $stmt_s->fetch();
            }
        } else {
            // Student via Index Number (two-step lookup for Supabase compatibility)
            $stmt_s = $pdo->prepare("SELECT user_id, admission_number, full_name, guardian_phone_primary, guardian_phone_emergency FROM students WHERE admission_number = :admission_number");
            $stmt_s->execute(['admission_number' => $identifier]);
            $student = $stmt_s->fetch();
            if ($student) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :uid");
                $stmt->execute(['uid' => $student['user_id']]);
                $user = $stmt->fetch();
                if ($user) {
                    $user['admission_number'] = $student['admission_number'];
                    $user['full_name'] = $student['full_name'];
                    $user['guardian_phone_primary'] = $student['guardian_phone_primary'] ?? '';
                    $user['guardian_phone_emergency'] = $student['guardian_phone_emergency'] ?? '';
                }
            }
            $student = $user;
        }

        if ($user) {
            // Generate a new temporary password
            $temp_password = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);
            $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);

            try {
                $pdo->beginTransaction();
                
                // Update user password and set is_password_reset to 0 (forces them to reset on next login)
                $stmt = $pdo->prepare("UPDATE users SET password = :password, is_password_reset = 0 WHERE id = :id");
                $stmt->execute([
                    'password' => $password_hash,
                    'id' => $user['id']
                ]);

                // Send Email
                $mailer = new Mailer();
                $subject = "Password Reset — School Portal";
                $name = isset($student) ? $student['full_name'] : "User";
                
                $email_html = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
                        .email-container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                        .header { background: linear-gradient(to right, #6b66d6, #7a3fa0); color: white; text-align: center; padding: 40px 20px; }
                        .header h1 { margin: 0; font-size: 26px; }
                        .content { padding: 30px; color: #333; font-size: 14px; }
                        .info-box { border: 1px solid #eee; border-left: 4px solid #4a90e2; border-radius: 4px; padding: 15px; margin-top: 20px; background: #f9fbfd;}
                        .footer { text-align: center; padding: 30px; font-size: 12px; color: #666; border-top: 1px solid #eee; }
                    </style>
                </head>
                <body>
                    <div class='email-container'>
                        <div class='header'>
                            <h1>Password Reset</h1>
                        </div>
                        <div class='content'>
                            <p>Dear <strong>" . htmlspecialchars($name) . "</strong>,</p>
                            <p>Your password has been reset. Please use the temporary password below to login to your account.</p>
                            
                            <div class='info-box'>
                                <p style='margin:0; font-size: 18px;'><strong>Temporary Password: </strong> " . htmlspecialchars($temp_password) . "</p>
                            </div>
                            
                            <p style='margin-top: 20px;'><strong>Note:</strong> You will be required to change this temporary password immediately after logging in.</p>
                        </div>
                        <div class='footer'>
                            <p><strong>Nex CEC Basic School</strong></p>
                            <p>This is an automated email. Please do not reply.</p>
                        </div>
                    </div>
                </body>
                </html>";

                $mail_sent = $mailer->sendHTML($user['email'], $subject, $email_html);
                
                // Send SMS if student has phone number
                $smsTo = '';
                if (isset($student)) {
                    $smsTo = $student['guardian_phone_primary'] ?? '';
                    if (!$smsTo) {
                        $smsTo = $student['guardian_phone_emergency'] ?? '';
                    }
                }
                if (!empty($smsTo)) {
                    $sms = new SMSHelper();
                    $message = "Hello $name, your password has been reset. Your temporary password is: $temp_password. Please login and change it.";
                    $sms->send($smsTo, $message);
                }

                $pdo->commit();
                $success = "A temporary password has been sent to your registered email address and phone number.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "An error occurred while resetting your password. Please try again later.";
            }
        } else {
            // For security, don't explicitly say the user doesn't exist, but we can be helpful here
            $error = "No account found with that Email or Index Number.";
        }
    }
}

require_once 'includes/header.php';
?>

<div class="section">
    <div class="form-container" style="text-align: center;">
        <img src="images/school-logo.png" alt="School Logo" style="width: 100px; margin-bottom: 20px;" onerror="this.src='images/aamusted.jpg'">
        <h2 class="section-title">Forgot Password</h2>
        <p style="margin-bottom: 20px; color: #666;">Enter your Email or Index Number to receive a temporary password.</p>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <div style="margin-top: 20px;">
                <a href="login.php" class="btn-submit" style="display: inline-block; text-decoration: none;">Return to Login</a>
            </div>
        <?php else: ?>
            <form action="forgot-password.php" method="POST" style="text-align: left;">
                <div class="form-group">
                    <label for="identifier">Email or Index Number</label>
                    <input type="text" name="identifier" id="identifier" class="form-control" required placeholder="Enter Email or Index No.">
                </div>
                
                <button type="submit" class="btn-submit">Reset Password</button>
                
                <div style="margin-top: 15px; text-align: center;">
                    <a href="login.php" style="color: var(--primary-color);"><i class="fas fa-arrow-left"></i> Back to Login</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
