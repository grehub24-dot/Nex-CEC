<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn() || !isParentOrDual()) {
    redirect('../login.php');
}

$error = '';
$success = '';

// Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$parent_user_id = $_SESSION['user_id'];

// Fetch unread message count for sidebar badge
$unread_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM messages m WHERE (m.receiver_id = ? OR m.is_broadcast = 1) AND NOT EXISTS (SELECT 1 FROM message_reads mr WHERE mr.message_id = m.id AND mr.user_id = ?)");
    $stmt->execute([$parent_user_id, $parent_user_id]);
    $row = $stmt->fetch();
    $unread_count = (int)($row['cnt'] ?? 0);
} catch (Exception $e) {
    error_log("Unread count error: " . $e->getMessage());
}

// Fetch parent profile picture for sidebar
$parent_profile_pic = null;
try {
    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->execute([$parent_user_id]);
    $row = $stmt->fetch();
    if ($row && !empty($row['profile_picture'])) {
        $parent_profile_pic = $row['profile_picture'];
    }
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_id = $_SESSION['user_id'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Verify current password
        try {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($current_password, $user['password'])) {
                $error = "Current password is incorrect.";
            } else {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);

                // Check if is_password_reset column exists
                $hasColumn = false;
                try {
                    $checkCol = $pdo->query("SELECT 1 FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'is_password_reset'");
                    $hasColumn = $checkCol && $checkCol->fetchColumn() > 0;
                } catch (Exception $e) {
                    $hasColumn = true;
                }

                if ($hasColumn) {
                    $stmt = $pdo->prepare("UPDATE users SET password = ?, is_password_reset = true WHERE id = ?");
                    $stmt->execute([$hash, $user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hash, $user_id]);
                }

                $_SESSION['is_password_reset'] = 1;
                $success = "Password changed successfully!";
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password — Parent Portal</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; color: #333; }
        .parent-container { display: flex; min-height: 100vh; }
        .parent-main { flex: 1; padding: 30px; background: #f4f6f9; margin-left: 250px; display: flex; align-items: center; justify-content: center; }
        .parent-sidebar {
            width: 250px; background: #1a5276; color: white; position: fixed;
            top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 100;
        }
        .parent-sidebar .sidebar-header { padding: 25px 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .parent-sidebar .sidebar-header img.sidebar-profile-img { width: 64px; height: 64px; border-radius: 50%; background: white; padding: 3px; margin-bottom: 10px; object-fit: cover; }
        .parent-sidebar .sidebar-header img.sidebar-logo-img { max-width: 120px; max-height: 56px; width: auto; height: auto; object-fit: contain; background: white; padding: 4px 8px; border-radius: 6px; margin-bottom: 10px; display: inline-block; }
        .parent-sidebar .sidebar-header h3 { font-size: 15px; margin: 0; }
        .parent-sidebar .sidebar-header p { font-size: 12px; opacity: 0.8; margin: 5px 0 0; }
        .parent-sidebar ul { list-style: none; padding: 0; margin: 0; }
        .parent-sidebar ul li { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .parent-sidebar ul li a {
            display: block; padding: 14px 20px; color: rgba(255,255,255,0.85); text-decoration: none;
            font-size: 14px; transition: all 0.2s; position: relative;
        }
        .parent-sidebar ul li a:hover, .parent-sidebar ul li a.active { background: rgba(255,255,255,0.1); color: white; padding-left: 25px; }
        .parent-sidebar ul li a i { width: 22px; text-align: center; margin-right: 8px; }
        .parent-sidebar .msg-count {
            position: absolute; right: 15px; top: 50%; transform: translateY(-50%);
            background: #e74c3c; color: white; padding: 1px 8px;
            border-radius: 10px; font-size: 11px; font-weight: 700; line-height: 1.5;
            min-width: 20px; text-align: center;
        }
        .hamburger-menu { display: none; position: fixed; top: 15px; left: 15px; z-index: 200;
            background: #1a5276; color: white; border: none; width: 40px; height: 40px;
            border-radius: 8px; font-size: 18px; cursor: pointer;
        }
        @media (max-width: 768px) {
            .parent-sidebar { left: -250px; transition: left 0.3s; }
            .parent-sidebar.open { left: 0; }
            .parent-main { margin-left: 0; padding: 20px; }
            .hamburger-menu { display: block; }
        }
        .reset-card {
            width: 100%;
            max-width: 480px;
            background: white;
            border-radius: 14px;
            box-shadow: 0 14px 35px rgba(0,0,0,0.14);
            padding: 35px 30px;
            border-top: 4px solid #ffcc00;
        }
        .reset-card h2 {
            text-align: center;
            color: #1a5276;
            margin-bottom: 8px;
            font-size: 22px;
        }
        .reset-card .subtitle {
            text-align: center;
            color: #888;
            font-size: 14px;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 14px;
            color: #333;
        }
        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            border-color: #1a5276;
            box-shadow: 0 0 0 3px rgba(26,82,118,0.1);
            outline: none;
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
        .btn-reset {
            width: 100%;
            padding: 14px;
            background: #1a5276;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 5px;
        }
        .btn-reset:hover { background: #143c58; }
        .links {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
        }
        .links a {
            color: #1a5276;
            text-decoration: none;
        }
        .links a:hover { text-decoration: underline; }
        .alert { margin-bottom: 18px; }
        @media (max-width: 500px) {
            .reset-card { padding: 25px 20px; }
        }
    </style>
</head>
<body>
<div class="parent-container">
    <?php
    $profile_pic_path = $parent_profile_pic ? '../' . htmlspecialchars($parent_profile_pic) : '';
    echo renderParentSidebar('password', $school_name, $unread_count, $profile_pic_path, !empty($_SESSION['has_children']));
    ?>
    <div class="parent-main">
    <div class="reset-card">
        <h2><i class="fas fa-key"></i> Change Password</h2>
        <p class="subtitle">Update your account password</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <div class="password-field">
                    <input type="password" name="current_password" id="current_password" class="form-control" required placeholder="Enter current password">
                    <button type="button" class="password-toggle" data-target="current_password">View</button>
                </div>
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <div class="password-field">
                    <input type="password" name="new_password" id="new_password" class="form-control" required placeholder="Enter new password (min 6 chars)">
                    <button type="button" class="password-toggle" data-target="new_password">View</button>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <div class="password-field">
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required placeholder="Re-enter new password">
                    <button type="button" class="password-toggle" data-target="confirm_password">View</button>
                </div>
            </div>

            <button type="submit" class="btn-reset"><i class="fas fa-save"></i> Change Password</button>
        </form>

        <div class="links">
            <a href="../parent/profile.php"><i class="fas fa-user"></i> Back to Profile</a>
            &nbsp;&middot;&nbsp;
            <a href="../parent/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        </div>
    </div>
</div>
</div>

    <script>
        document.querySelectorAll('.password-toggle').forEach(function(button) {
            button.addEventListener('click', function() {
                var targetId = button.getAttribute('data-target');
                var input = document.getElementById(targetId);
                if (!input) return;
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.textContent = show ? 'Hide' : 'View';
            });
        });
    </script>
</body>
</html>
