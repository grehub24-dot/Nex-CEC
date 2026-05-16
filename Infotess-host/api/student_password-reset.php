<?php
require_once 'includes/db.php';
require_once 'includes/SMSHelper.php';

// Check if logged in and is student
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    redirect('../login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $user_id = $_SESSION['user_id'];
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        try {
            // Safely update password with is_password_reset column fallback
            // First check if is_password_reset column exists (PostgreSQL compatible)
            $hasColumn = false;
            try {
                $checkCol = $pdo->query("SELECT 1 FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'is_password_reset'");
                $hasColumn = $checkCol && $checkCol->fetchColumn() > 0;
            } catch (Exception $e) {
                $hasColumn = true; // Assume column exists if check fails
            }
            if ($hasColumn) {
                $stmt = $pdo->prepare("UPDATE users SET password = ?, is_password_reset = true WHERE id = ?");
                $stmt->execute([$hash, $user_id]);
            } else {
                // Fallback: update password without is_password_reset column
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hash, $user_id]);
            }

            // Update session flag
            $_SESSION['is_password_reset'] = 1;

            // Fetch student phone number for SMS (outside main transaction - won't affect password update)
            $phone_number = null;
            $student_name = $_SESSION['name'] ?? '';
            try {
                $stmt_s = $pdo->prepare("SELECT guardian_phone_primary, guardian_phone_emergency FROM students WHERE user_id = :uid");
                $stmt_s->execute(['uid' => $user_id]);
                $student_row = $stmt_s->fetch();
                $phone_number = $student_row['guardian_phone_primary'] ?? null;
                if (!$phone_number) {
                    $phone_number = $student_row['guardian_phone_emergency'] ?? null;
                }
            } catch (PDOException $e) {
                // Silently ignore SMS fetch error - password is already saved
            }

            // Send SMS if phone exists (isolated so it can't break the reset)
            if ($phone_number) {
                try {
                    $sms = new SMSHelper();
                    $message = "Hello " . $student_name . ", your password has been successfully reset. You can now use your new password to login. Thank you.";
                    $sms->send($phone_number, $message);
                } catch (Exception $e) {
                    // Silently ignore SMS error - password is already reset
                }
            }
            
            $success = "Password reset successfully! Redirecting to profile setup...";
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'profile.php';
                }, 3000);
            </script>";
        } catch (PDOException $e) {
            $error = "Database error: " . htmlspecialchars($e->getMessage());
        } catch (Exception $e) {
            $error = "An error occurred: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<style>
    .reset-wrap {
        background: linear-gradient(135deg, rgba(0, 51, 102, 0.06), rgba(255, 204, 0, 0.12));
        min-height: 70vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 50px 16px;
    }
    .reset-card {
        width: 100%;
        max-width: 520px;
        margin: 0;
        border-radius: 14px;
        box-shadow: 0 14px 35px rgba(0, 0, 0, 0.14);
        border-top: 4px solid var(--secondary-color);
    }
    .reset-title {
        margin-bottom: 10px;
    }
    .reset-note {
        text-align: center;
        margin-bottom: 24px;
        color: #555;
        font-size: 0.95rem;
    }
    .reset-group {
        margin-bottom: 18px;
    }
    .reset-group label {
        margin-bottom: 8px;
        font-weight: 600;
        color: #213547;
    }
    .reset-group .form-control {
        height: 46px;
    }
    .password-field {
        position: relative;
    }
    .password-field .form-control {
        padding-right: 86px;
    }
    .password-toggle {
        position: absolute;
        top: 50%;
        right: 10px;
        transform: translateY(-50%);
        border: 1px solid #d0d7de;
        background: #f8fafc;
        color: #1f2937;
        border-radius: 6px;
        padding: 5px 10px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        line-height: 1;
    }
    .reset-group .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.12);
    }
    .reset-btn {
        margin-top: 8px;
        height: 48px;
        font-size: 1rem;
        font-weight: 700;
    }
    .reset-alert {
        margin-bottom: 18px;
    }
    @media (max-width: 576px) {
        .reset-wrap {
            padding: 30px 14px;
        }
        .reset-card {
            padding: 28px 20px;
        }
    }
</style>

<div class="section reset-wrap">
    <div class="form-container reset-card">
        <h2 class="section-title reset-title">Reset Your Password</h2>
        <p class="reset-note">You are using a temporary password. Please set a new password to continue.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger reset-alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success reset-alert"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form action="password-reset.php" method="POST">
            <div class="form-group reset-group">
                <label for="new_password">New Password</label>
                <div class="password-field">
                    <input type="password" name="new_password" id="new_password" class="form-control" required placeholder="Enter New Password">
                    <button type="button" class="password-toggle" data-target="new_password" aria-label="Toggle new password visibility">View</button>
                </div>
            </div>

            <div class="form-group reset-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-field">
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required placeholder="Confirm New Password">
                    <button type="button" class="password-toggle" data-target="confirm_password" aria-label="Toggle confirm password visibility">View</button>
                </div>
            </div>

            <button type="submit" class="btn-submit reset-btn">Reset Password</button>
        </form>
    </div>
</div>

<script>
    document.querySelectorAll('.password-toggle').forEach(function(button) {
        button.addEventListener('click', function() {
            const targetId = button.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) {
                return;
            }
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Hide' : 'View';
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>
