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
        // Dual-role user — show route selector
        redirect('route_selector.php');
    } elseif (in_array($role, ['staff', 'teacher'])) {
        redirect('staff/dashboard.php');
    } else {
        // Admin and bursar go to admin dashboard
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? ''); // Email or Index Number
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = "Please enter both identifier and password.";
    } elseif (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Invalid or expired session. Please refresh the page and try again.";
    } else {
        // Check if it's an email (Admin) or Index Number (Student)
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            // Admin/Executive Login
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->execute(['email' => $identifier]);
            $user = $stmt->fetch();
        } else {
            // Student Login (Lookup student by admission number, then get user)
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
                // Login Success
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // If student, store student details in session
                if ($user['role'] === 'student') {
                    $stmt_s = $pdo->prepare("SELECT * FROM students WHERE user_id = :uid");
                    $stmt_s->execute(['uid' => $user['id']]);
                    $student = $stmt_s->fetch();
                    $_SESSION['student_id'] = $student['id'];
                    $_SESSION['admission_number'] = $student['admission_number'];
                    $_SESSION['name'] = $student['full_name'];
                    
                    // If password has NOT been reset (is temporal), redirect to reset page
                    // We check if the column exists or is 0. If it doesn't exist, we assume 0 (force reset).
                    $is_reset = isset($user['is_password_reset']) ? $user['is_password_reset'] : 0;
                    $_SESSION['is_password_reset'] = $is_reset;
                    
                    if ($is_reset == 0) {
                        redirect('student/password-reset.php');
                    }
                    
                    redirect('student/dashboard.php');
                } elseif ($user['role'] === 'parent') {
                    // Parent login — redirect to parent dashboard
                    // Note: bridge does not support JOINs, so we do two queries
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

                    // If staff, check if they are a Class Teacher (so isTeacher() works without role='teacher')
                    if ($user['role'] === 'staff') {
                        $stmt_t = $pdo->prepare("SELECT position FROM staff WHERE user_id = ?");
                        $stmt_t->execute([$user['id']]);
                        $staffRow = $stmt_t->fetch();
                        $_SESSION['is_class_teacher'] = ($staffRow && strpos($staffRow['position'], 'Class Teacher') !== false);
                    } else {
                        $_SESSION['is_class_teacher'] = false;
                    }

                    // Check if password needs reset (for staff/teacher/bursar too)
                    $is_reset = isset($user['is_password_reset']) ? $user['is_password_reset'] : 0;
                    $_SESSION['is_password_reset'] = $is_reset;

                    if ($is_reset == 0) {
                        redirect('password-reset.php');
                    }

                    // Check if this user also has children (dual-role: staff + parent)
                    $stmt_hc = $pdo->prepare("SELECT id FROM parent_students WHERE parent_user_id = ? LIMIT 1");
                    $stmt_hc->execute([$user['id']]);
                    if ($stmt_hc->fetch()) {
                        $_SESSION['has_children'] = true;
                        redirect('route_selector.php');
                    }

                    // Staff and teacher roles go to staff portal; bursar goes to admin dashboard
                    if (in_array($user['role'], ['staff', 'teacher'])) {
                        redirect('staff/dashboard.php');
                    } else {
                        redirect('admin/dashboard.php');
                    }
                } else {
                    // Fallback for admin, super_admin, or any other role
                    $_SESSION['name'] = "User";

                    // Also check for children (e.g., admin who is also a parent)
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
            $error = "Invalid credentials. Please try again.";
        }
    }
}

require_once 'includes/header.php';
?>

<style>
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
</style>

<div class="section">
    <div class="form-container" style="text-align: center;">
        <img src="<?php echo htmlspecialchars($settings['school_logo_url'] ?? 'images/aamusted.jpg'); ?>" alt="School Logo" style="width: 100px; margin-bottom: 20px;" onerror="this.src='images/aamusted.jpg'">
        <h2 class="section-title">Login to Portal</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form action="login.php" method="POST" style="text-align: left;">
            <div class="form-group">
                <label for="identifier">Email or Index Number</label>
                <input type="text" name="identifier" id="identifier" class="form-control" required placeholder="Enter Email (Admin) or Index No. (Student)">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-field">
                    <input type="password" name="password" id="password" class="form-control" required placeholder="Enter Password">
                    <button type="button" class="password-toggle" data-target="password" aria-label="Toggle password visibility">View</button>
                </div>
            </div>
            
            <?php csrf_field(); ?>
            <button type="submit" class="btn-submit">Login</button>
            
            <div style="margin-top: 15px; text-align: center;">
                <a href="forgot-password.php" style="color: var(--primary-color);">Forgot Password?</a>
            </div>
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
