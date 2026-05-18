<?php
require_once 'includes/db.php';

// Must be logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Must be a dual-role user (has children)
if (!isset($_SESSION['has_children']) || !$_SESSION['has_children']) {
    // No dual role — redirect based on role
    $role = $_SESSION['role'] ?? '';
    if ($role === 'parent') {
        redirect('parent/dashboard.php');
    } elseif ($role === 'student') {
        redirect('student/dashboard.php');
    } else {
        redirect('admin/dashboard.php');
    }
}

// Fetch Settings for branding
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

// Determine staff dashboard link based on role
$staffDashboardLink = (isAdmin()) ? 'admin/dashboard.php' : 'staff/dashboard.php';

// Fetch children for display
$children = [];
$parent_user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT s.id, s.full_name, s.class_name FROM parent_students ps JOIN students s ON s.id = ps.student_id WHERE ps.parent_user_id = ?");
    $stmt->execute([$parent_user_id]);
    $children = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Route selector fetch error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Portal — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
        }
        .branding {
            text-align: center;
            margin-bottom: 30px;
        }
        .branding img {
            max-width: 120px;
            max-height: 64px;
            width: auto;
            height: auto;
            object-fit: contain;
            background: #fff;
            padding: 6px 12px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 12px;
            display: inline-block;
        }
        .branding h1 {
            font-size: 22px;
            color: #1a5276;
        }
        .branding p {
            font-size: 14px;
            color: #888;
            margin-top: 4px;
        }
        .welcome-card {
            background: white;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 25px 30px;
            margin-bottom: 30px;
            max-width: 700px;
            width: 100%;
            text-align: center;
        }
        .welcome-card h2 {
            font-size: 18px;
            color: #333;
            margin-bottom: 10px;
        }
        .welcome-card .children-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            margin-top: 8px;
        }
        .welcome-card .child-tag {
            display: inline-block;
            background: #e8f0fe;
            color: #1a5276;
            font-size: 13px;
            padding: 5px 14px;
            border-radius: 20px;
            font-weight: 500;
        }
        .portal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            max-width: 700px;
            width: 100%;
        }
        .portal-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 30px 25px;
            text-align: center;
            transition: transform 0.25s, box-shadow 0.25s;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .portal-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 36px rgba(0,0,0,0.12);
        }
        .portal-card .icon {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-bottom: 18px;
        }
        .portal-card .icon.staff-icon {
            background: #1a5276;
            color: white;
        }
        .portal-card .icon.parent-icon {
            background: #27ae60;
            color: white;
        }
        .portal-card h3 {
            font-size: 19px;
            color: #333;
            margin-bottom: 8px;
        }
        .portal-card p {
            font-size: 14px;
            color: #888;
            line-height: 1.6;
            margin-bottom: 20px;
            max-width: 240px;
        }
        .portal-card .btn-portal {
            display: inline-block;
            padding: 10px 28px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .portal-card .btn-staff {
            background: #1a5276;
            color: white;
        }
        .portal-card .btn-staff:hover {
            background: #143c58;
        }
        .portal-card .btn-parent {
            background: #27ae60;
            color: white;
        }
        .portal-card .btn-parent:hover {
            background: #1e8449;
        }
        .logout-link {
            margin-top: 32px;
            font-size: 13px;
        }
        .logout-link a {
            color: #888;
            text-decoration: none;
        }
        .logout-link a:hover {
            color: #e74c3c;
        }
        @media (max-width: 600px) {
            .portal-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .welcome-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

    <!-- Branding -->
    <div class="branding">
        <img src="<?php echo htmlspecialchars($settings['school_logo_url'] ?? 'images/aamusted.jpg'); ?>" alt="<?php echo htmlspecialchars($school_name); ?> Logo" onerror="this.src='images/aamusted.jpg'">
        <h1><?php echo htmlspecialchars($school_name); ?></h1>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?></p>
    </div>

    <!-- Welcome Card -->
    <div class="welcome-card">
        <h2><i class="fas fa-users" style="color: #1a5276; margin-right: 8px;"></i>Linked Children</h2>
        <p style="font-size: 14px; color: #888;">You are registered as a guardian for:</p>
        <?php if (!empty($children)): ?>
            <div class="children-list">
                <?php foreach ($children as $child): ?>
                    <span class="child-tag">
                        <i class="fas fa-child"></i>
                        <?php echo htmlspecialchars($child['full_name'] ?? 'Unknown'); ?>
                        <?php if (!empty($child['class_name'])): ?>
                            &mdash; <?php echo htmlspecialchars($child['class_name']); ?>
                        <?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: #aaa; font-size: 13px; margin-top: 6px;">(No children currently linked)</p>
        <?php endif; ?>
    </div>

    <!-- Portal Selection Cards -->
    <div class="portal-grid">

        <!-- Staff Portal Card -->
        <a href="<?php echo $staffDashboardLink; ?>" class="portal-card">
            <div class="icon staff-icon"><i class="fas fa-chalkboard-teacher"></i></div>
            <h3>Staff Portal</h3>
            <p>Access the admin dashboard, manage students, grades, attendance, fees, and school operations.</p>
            <span class="btn-portal btn-staff"><i class="fas fa-sign-in-alt"></i> Enter Staff Portal</span>
        </a>

        <!-- Parent Portal Card -->
        <a href="parent/dashboard.php" class="portal-card">
            <div class="icon parent-icon"><i class="fas fa-user-friends"></i></div>
            <h3>Parent Portal</h3>
            <p>Monitor your children's academic progress, view fee statements, report cards, and communicate with the school.</p>
            <span class="btn-portal btn-parent"><i class="fas fa-sign-in-alt"></i> Enter Parent Portal</span>
        </a>

    </div>

    <!-- Logout -->
    <div class="logout-link">
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Not you? Logout</a>
    </div>

</body>
</html>
