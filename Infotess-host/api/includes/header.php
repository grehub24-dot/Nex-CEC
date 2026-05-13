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
    <!-- Skip to main content (accessibility) -->
    <a href="#main-content" class="skip-link" style="position: absolute; top: -100%; left: 0; background: var(--primary-color); color: #fff; padding: 10px 20px; z-index: 9999; transition: top 0.2s;">Skip to main content</a>
    <style>
        .skip-link:focus { top: 0; }
    </style>

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
    <nav class="navbar" role="navigation" aria-label="Main navigation">
        <div class="container">
            <a href="<?php echo $base_url; ?>index.php" class="logo" aria-label="<?php echo htmlspecialchars($school_name); ?> Home">
                <img src="<?php echo $base_url; ?>images/school-logo.png" alt="<?php echo htmlspecialchars($school_name); ?> Logo" height="40" onerror="this.style.display='none'"> <?php echo htmlspecialchars($school_name); ?>
            </a>
            <ul class="nav-links" role="menubar">
                <li role="none"><a href="<?php echo $base_url; ?>index.php" role="menuitem">Home</a></li>
                <li role="none"><a href="<?php echo $base_url; ?>about.php" role="menuitem">About</a></li>
                <li role="none"><a href="<?php echo $base_url; ?>contact.php" role="menuitem">Contact</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if (isset($_SESSION['has_children']) && $_SESSION['has_children']): ?>
                        <li role="none"><a href="<?php echo $base_url; ?>route_selector.php" class="btn-login" role="menuitem">Portals</a></li>
                    <?php elseif ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin'): ?>
                        <li role="none"><a href="<?php echo $base_url; ?>admin/dashboard.php" class="btn-login" role="menuitem">Admin Panel</a></li>
                    <?php elseif ($_SESSION['role'] === 'parent'): ?>
                        <li role="none"><a href="<?php echo $base_url; ?>parent/dashboard.php" class="btn-login" role="menuitem">Parent Portal</a></li>
                    <?php else: ?>
                        <li role="none"><a href="<?php echo $base_url; ?>student/dashboard.php" class="btn-login" role="menuitem">Dashboard</a></li>
                    <?php endif; ?>
                    <li role="none"><a href="<?php echo $base_url; ?>logout.php" role="menuitem">Logout</a></li>
                <?php else: ?>
                    <li role="none"><a href="<?php echo $base_url; ?>register.php" class="btn-login" role="menuitem">Enroll Now</a></li>
                    <li role="none"><a href="<?php echo $base_url; ?>login.php" class="btn-login" role="menuitem">Login</a></li>
                <?php endif; ?>
            </ul>
            <div class="hamburger" aria-label="Toggle navigation menu" role="button" tabindex="0">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    </nav>
