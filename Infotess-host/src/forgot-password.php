<?php
require_once 'includes/db.php';
require_once 'includes/Mailer.php';
require_once 'includes/SMSHelper.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('student/dashboard.php');
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier']); // Email or Index Number

    if (empty($identifier)) {
        $error = "Please enter your Email or Index Number.";
    } else {
        // Find user by email or index number
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
            // Student via Index Number
            $stmt = $pdo->prepare("
                SELECT u.*, s.index_number, s.full_name, s.phone_number 
                FROM users u 
                JOIN students s ON u.id = s.user_id 
                WHERE s.index_number = :index_number
            ");
            $stmt->execute(['index_number' => $identifier]);
            $user = $stmt->fetch();
            $student = $user; // $user array has the student data joined
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
                $subject = "INFOTESS SDMS - Password Reset";
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
                            <p><strong>USTED - Infotess</strong></p>
                            <p>This is an automated email. Please do not reply.</p>
                        </div>
                    </div>
                </body>
                </html>";

                $mail_sent = $mailer->sendHTML($user['email'], $subject, $email_html);
                
                // Send SMS if student has phone number
                if (isset($student) && !empty($student['phone_number'])) {
                    $sms = new SMSHelper();
                    $message = "Hello $name, your INFOTESS SDMS password has been reset. Your temporary password is: $temp_password. Please login and change it.";
                    $sms->send($student['phone_number'], $message);
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
        <img src="images/infotess.png" alt="INFOTESS Logo" style="width: 100px; margin-bottom: 20px;">
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
