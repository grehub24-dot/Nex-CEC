<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $settings['school_name'] ?? 'Nex CEC'; ?> — School Management System</title>
    <!-- CSS -->
    <?php $base_url = getBasePath(); ?>
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/style.css">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php
    // Load school settings for branding (available to all pages that include header)
    if (!isset($settings)) {
        $settings = [];
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // Settings table may not exist yet
        }
    }
    $school_name = $settings['school_name'] ?? 'Nex CEC';
    $school_motto = $settings['school_motto'] ?? 'Excellence in Education';
    ?>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a href="<?php echo $base_url; ?>index.php" class="logo">
                <img src="<?php echo $base_url; ?>images/school-logo.png" alt="<?php echo htmlspecialchars($school_name); ?> Logo" height="40" onerror="this.style.display='none'"> <?php echo htmlspecialchars($school_name); ?>
            </a>
            <ul class="nav-links">
                <li><a href="<?php echo $base_url; ?>index.php">Home</a></li>
                <li><a href="<?php echo $base_url; ?>about.php">About</a></li>
                <li><a href="<?php echo $base_url; ?>contact.php">Contact</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin'): ?>
                        <li><a href="<?php echo $base_url; ?>admin/dashboard.php" class="btn-login">Admin Panel</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo $base_url; ?>student/dashboard.php" class="btn-login">Dashboard</a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo $base_url; ?>logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="<?php echo $base_url; ?>register.php" class="btn-login">Enroll Now</a></li>
                    <li><a href="<?php echo $base_url; ?>login.php" class="btn-login">Login</a></li>
                <?php endif; ?>
            </ul>
            <div class="hamburger">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    </nav>
