<?php
require_once 'includes/db.php';

// Must be logged in, must need password reset
if (!isLoggedIn()) {
    redirect('../login.php');
}

$role = $_SESSION['role'] ?? '';
$is_reset = $_SESSION['is_password_reset'] ?? 0;

// If already reset, redirect to dashboard
if ($is_reset == 1) {
    if ($role === 'student') {
        redirect('student/dashboard.php');
    } elseif (in_array($role, ['staff', 'teacher'])) {
        redirect('staff/dashboard.php');
    } else {
        redirect('admin/dashboard.php');
    }
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
            // Update password and set is_password_reset = true
            $hasColumn = false;
            try {
                $checkCol = $pdo->query("SELECT 1 FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'is_password_reset'");
                $hasColumn = $checkCol && $checkCol->fetchColumn() > 0;
            } catch (Exception $e) {
                $hasColumn = true;
            }
            if ($hasColumn) {
                $stmt = $pdo->prepare("UPDATE users SET password = :password, is_password_reset = true WHERE id = :id");
                $stmt->execute(['password' => $hash, 'id' => $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
                $stmt->execute(['password' => $hash, 'id' => $user_id]);
            }

            // Update session flag
            $_SESSION['is_password_reset'] = 1;

            $success = "Password reset successfully! Redirecting to dashboard...";

            // Redirect based on role
            $dashboard = '../login.php';
            if ($role === 'student') {
                $dashboard = 'student/dashboard.php';
            } elseif (in_array($role, ['staff', 'teacher', 'bursar'])) {
                $dashboard = 'staff/dashboard.php';
            } else {
                $dashboard = 'admin/dashboard.php';
            }

            echo "<script>
                setTimeout(function() {
                    window.location.href = '$dashboard';
                }, 2000);
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
        <h2 class="section-title reset-title">Set Your Password</h2>
        <p class="reset-note">You are using a system-generated password. Please set a new password to continue.</p>

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

            <button type="submit" class="btn-submit reset-btn">Set Password</button>
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
