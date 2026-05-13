<?php
require_once 'includes/db.php';

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
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, rgba(0,51,102,0.06), rgba(255,204,0,0.12));
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
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
