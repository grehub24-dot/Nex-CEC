<?php
require_once 'includes/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'student') {
        redirect('student/dashboard.php');
    } elseif ($role === 'parent') {
        redirect('parent/dashboard.php');
    } elseif (isset($_SESSION['has_children']) && $_SESSION['has_children']) {
        redirect('route_selector.php');
    } elseif (in_array($role, ['staff', 'teacher'])) {
        redirect('staff/dashboard.php');
    } else {
        redirect('admin/dashboard.php');
    }
}

$error = '';

// Load settings for dynamic logo
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}

$school_name = $settings['school_name'] ?? 'Nex CEC';
$school_logo = $settings['school_logo_url'] ?? 'images/aamusted.jpg';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting: 5 attempts within 15 min → delays; 10 → lockout
    $attempts = &$_SESSION['login_attempts'];
    if (!isset($attempts)) {
        $attempts = ['count' => 0, 'first' => 0];
    }
    // Reset if 30+ minutes since first attempt
    if ($attempts['first'] > 0 && (time() - $attempts['first']) > 1800) {
        $attempts = ['count' => 0, 'first' => 0];
    }
    if ($attempts['count'] >= 10) {
        $error = "Too many failed login attempts. Please try again in 30 minutes.";
    } elseif ($attempts['count'] >= 5) {
        // Progressive delay: 2s per failed attempt beyond 5
        $delay = min(($attempts['count'] - 4) * 2, 10);
        sleep($delay);
    }

    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($error) {
        // Already locked out, skip processing
    } elseif (empty($identifier) || empty($password)) {
        $error = "Please enter both identifier and password.";
    } elseif (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Invalid or expired session. Please refresh the page and try again.";
    } else {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->execute(['email' => $identifier]);
            $user = $stmt->fetch();
        } else {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE admission_number = ?");
            $stmt->execute([$identifier]);
            $student = $stmt->fetch();
            if ($student) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$student['user_id']]);
                $user = $stmt->fetch();
                if ($user) {
                    $user['admission_number'] = $student['admission_number'];
                }
            }
        }

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = "Your account is inactive or banned. Please contact support.";
            } else {
                // Reset rate limiter on successful login
                $_SESSION['login_attempts'] = ['count' => 0, 'first' => 0];

                // Regenerate session to prevent session fixation attacks
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] === 'student') {
                    $stmt_s = $pdo->prepare("SELECT * FROM students WHERE user_id = :uid");
                    $stmt_s->execute(['uid' => $user['id']]);
                    $student = $stmt_s->fetch();
                    $_SESSION['student_id'] = $student['id'];
                    $_SESSION['admission_number'] = $student['admission_number'];
                    $_SESSION['name'] = $student['full_name'];

                    $is_reset = isset($user['is_password_reset']) ? $user['is_password_reset'] : 0;
                    $_SESSION['is_password_reset'] = $is_reset;

                    if ($is_reset == 0) {
                        redirect('student/password-reset.php');
                    }

                    redirect('student/dashboard.php');
                } elseif ($user['role'] === 'parent') {
                    $stmt_s = $pdo->prepare("SELECT student_id FROM parent_students WHERE parent_user_id = ? LIMIT 1");
                    $stmt_s->execute([$user['id']]);
                    $link = $stmt_s->fetch();
                    $child_name = 'Parent';
                    if ($link && !empty($link['student_id'])) {
                        $stmt_s2 = $pdo->prepare("SELECT full_name, admission_number FROM students WHERE id = ?");
                        $stmt_s2->execute([$link['student_id']]);
                        $child = $stmt_s2->fetch();
                        if ($child) {
                            $child_name = $child['full_name'] . "'s Parent";
                        }
                    }
                    $_SESSION['name'] = $child_name;
                    redirect('parent/dashboard.php');
                } elseif (in_array($user['role'], ['teacher', 'staff', 'bursar'])) {
                    $_SESSION['name'] = ucfirst($user['role']);

                    if ($user['role'] === 'staff') {
                        $stmt_t = $pdo->prepare("SELECT position FROM staff WHERE user_id = ?");
                        $stmt_t->execute([$user['id']]);
                        $staffRow = $stmt_t->fetch();
                        $_SESSION['is_class_teacher'] = ($staffRow && strpos($staffRow['position'], 'Class Teacher') !== false);
                    } else {
                        $_SESSION['is_class_teacher'] = false;
                    }

                    $is_reset = isset($user['is_password_reset']) ? $user['is_password_reset'] : 0;
                    $_SESSION['is_password_reset'] = $is_reset;

                    if ($is_reset == 0) {
                        redirect('password-reset.php');
                    }

                    $stmt_hc = $pdo->prepare("SELECT id FROM parent_students WHERE parent_user_id = ? LIMIT 1");
                    $stmt_hc->execute([$user['id']]);
                    if ($stmt_hc->fetch()) {
                        $_SESSION['has_children'] = true;
                        redirect('route_selector.php');
                    }

                    if (in_array($user['role'], ['staff', 'teacher'])) {
                        redirect('staff/dashboard.php');
                    } else {
                        redirect('admin/dashboard.php');
                    }
                } else {
                    $_SESSION['name'] = "User";

                    $stmt_hc = $pdo->prepare("SELECT id FROM parent_students WHERE parent_user_id = ? LIMIT 1");
                    $stmt_hc->execute([$user['id']]);
                    if ($stmt_hc->fetch()) {
                        $_SESSION['has_children'] = true;
                        redirect('route_selector.php');
                    }

                    redirect('admin/dashboard.php');
                }
            }
        } else {
            // Track failed attempt for rate limiting
            if ($attempts['count'] === 0) $attempts['first'] = time();
            $attempts['count']++;
            $error = "Invalid credentials. Please try again.";
        }
    }
}

require_once 'includes/header.php';
?>

<style>
    .login-page { min-height: 80vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 40px 20px; }
    .login-card { width: 100%; max-width: 440px; background: #fff; border-radius: 16px; box-shadow: 0 12px 36px rgba(0,0,0,0.12); padding: 40px; animation: fadeInUp 0.5s cubic-bezier(0.16,1,0.3,1); }
    .login-logo { text-align: center; margin-bottom: 32px; }
    .login-logo img { max-width: 100px; max-height: 60px; width: auto; height: auto; object-fit: contain; margin-bottom: 16px; border-radius: 8px; }
    .login-logo h2 { font-size: 1.5rem; color: #003366; margin-bottom: 4px; }
    .login-logo p { font-size: 0.85rem; color: #888; }
    .login-divider { height: 1px; background: #e9ecef; margin: 24px 0; }
    .login-footer-text { text-align: center; margin-top: 20px; font-size: 0.85rem; color: #888; }
    .login-footer-text a { color: #003366; font-weight: 600; text-decoration: none; }
    .login-footer-text a:hover { text-decoration: underline; }
</style>

<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <img src="<?php echo htmlspecialchars($school_logo); ?>" alt="School Logo" onerror="this.onerror=null;this.src='images/chariot-logo.svg'">
            <h2><?php echo htmlspecialchars($school_name); ?></h2>
            <p>Sign in to your portal account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="animation: fadeInDown 0.4s var(--ease-out-expo); font-size: 0.9rem;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="identifier" class="required">Email or Index Number</label>
                <input type="text" name="identifier" id="identifier" class="form-control" required
                       placeholder="Email (Admin/Staff/Parent) or Index No. (Student)">
            </div>

            <div class="form-group">
                <label for="password" class="required">Password</label>
                <div class="password-field">
                    <input type="password" name="password" id="password" class="form-control" required
                           placeholder="Enter your password">
                    <button type="button" class="password-toggle" data-target="password"
                            aria-label="Toggle password visibility">View</button>
                </div>
            </div>

            <?php csrf_field(); ?>

            <button type="submit" class="btn-submit" style="margin-top: 8px;">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>

            <div class="login-divider"></div>

            <div style="text-align: center;">
                <a href="forgot-password.php" style="color: var(--color-primary); font-size: 0.9rem; text-decoration: none;">
                    <i class="fas fa-lock"></i> Forgot Password?
                </a>
            </div>
        </form>

        <div class="login-footer-text">
            <p>Don't have an account? <a href="register.php">Enroll Now</a></p>
            <p style="margin-top: 8px;"><a href="index.php"><i class="fas fa-arrow-left"></i> Back to Home</a></p>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.password-toggle').forEach(function(button) {
        button.addEventListener('click', function() {
            const targetId = button.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Hide' : 'View';
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>
