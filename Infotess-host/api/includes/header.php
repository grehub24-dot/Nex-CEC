<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $settings['school_name'] ?? 'Nex CEC'; ?> — School Management System</title>
    <!-- CSS -->
    <?php $base_url = getBasePath(); ?>
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/design-tokens.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/typography.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/layout.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/components.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/animations.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/3d-school.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/style.css"><!-- legacy compat -->
    <!-- PWA -->
    <link rel="manifest" href="<?php echo $base_url; ?>manifest.json">
    <meta name="theme-color" content="#003366">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="<?php echo $base_url; ?>images/chariot-logo.svg">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?php echo $base_url; ?>sw.js').then(function(reg) {
                    console.log('SW registered: ' + reg.scope);
                }).catch(function(err) {
                    console.log('SW registration failed: ' + err);
                });
            });
        }
    </script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Skip to main content (accessibility) -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

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
    $school_logo = $settings['school_logo_url'] ?? ($base_url . 'images/chariot-logo.svg');

    // Determine current page for active nav state
    $current_page = basename($_SERVER['PHP_SELF']);
    ?>

    <!-- Top announcement bar (simplified) -->
    <div class="top-announcement" style="background: var(--color-brand-navy); color: var(--color-on-dark-muted); font-size: 13px; padding: 6px 0; display: none;">
        <div class="container" style="display: flex; justify-content: center; gap: 24px;">
            <span><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($settings['school_phone'] ?? '+233 XX XXX XXXX'); ?></span>
            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($settings['school_email'] ?? 'info@school.edu.gh'); ?></span>
        </div>
    </div>

    <!-- Navigation (Notion-style sticky white bar) -->
    <nav class="nav-white" role="navigation" aria-label="Main navigation">
        <div class="nav-inner">
            <a href="<?php echo $base_url; ?>index.php" class="logo" aria-label="<?php echo htmlspecialchars($school_name); ?> Home" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
                <img src="<?php echo htmlspecialchars($school_logo); ?>" alt="<?php echo htmlspecialchars($school_name); ?> Logo" height="32" onerror="this.onerror=null;this.src='<?php echo $base_url; ?>images/chariot-logo.svg'">
                <span style="font-size: 16px; font-weight: 600; color: var(--color-charcoal);"><?php echo htmlspecialchars($school_name); ?></span>
            </a>

            <ul class="nav-links" role="menubar" id="navLinks">
                <li role="none"><a href="<?php echo $base_url; ?>index.php" role="menuitem" class="<?php echo ($current_page === 'home.php' || $current_page === 'index.php') ? 'active' : ''; ?>">Home</a></li>
                <li role="none"><a href="<?php echo $base_url; ?>about.php" role="menuitem" class="<?php echo ($current_page === 'about.php') ? 'active' : ''; ?>">About</a></li>
                <li role="none"><a href="<?php echo $base_url; ?>news.php" role="menuitem" class="<?php echo ($current_page === 'news.php') ? 'active' : ''; ?>">News</a></li>
                <li role="none"><a href="<?php echo $base_url; ?>events.php" role="menuitem" class="<?php echo ($current_page === 'events.php') ? 'active' : ''; ?>">Events</a></li>
                <li role="none"><a href="<?php echo $base_url; ?>gallery.php" role="menuitem" class="<?php echo ($current_page === 'gallery.php') ? 'active' : ''; ?>">Gallery</a></li>
                <li role="none"><a href="<?php echo $base_url; ?>contact.php" role="menuitem" class="<?php echo ($current_page === 'contact.php') ? 'active' : ''; ?>">Contact</a></li>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if (isset($_SESSION['has_children']) && $_SESSION['has_children']): ?>
                        <li role="none"><a href="<?php echo $base_url; ?>route_selector.php" class="btn btn-primary btn-sm" role="menuitem">Portals</a></li>
                    <?php elseif ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin'): ?>
                        <li role="none"><a href="<?php echo $base_url; ?>admin_dashboard.php" class="btn btn-primary btn-sm" role="menuitem">Admin Panel</a></li>
                    <?php elseif ($_SESSION['role'] === 'parent'): ?>
                        <li role="none"><a href="<?php echo $base_url; ?>parent_dashboard.php" class="btn btn-primary btn-sm" role="menuitem">Parent Portal</a></li>
                    <?php elseif ($_SESSION['role'] === 'staff' || $_SESSION['role'] === 'teacher'): ?>
                        <li role="none"><a href="<?php echo $base_url; ?>staff_dashboard.php" class="btn btn-primary btn-sm" role="menuitem">Staff Portal</a></li>
                    <?php else: ?>
                        <li role="none"><a href="<?php echo $base_url; ?>student_dashboard.php" class="btn btn-primary btn-sm" role="menuitem">Dashboard</a></li>
                    <?php endif; ?>
                    <li role="none"><a href="<?php echo $base_url; ?>logout.php" role="menuitem" style="color: var(--color-steel);" aria-label="Logout"><i class="fas fa-sign-out-alt"></i></a></li>
                <?php else: ?>
                    <li role="none"><a href="<?php echo $base_url; ?>register.php" class="btn btn-primary" role="menuitem"><i class="fas fa-user-plus"></i> Enroll Now</a></li>
                    <li role="none"><a href="<?php echo $base_url; ?>login.php" class="btn btn-secondary" role="menuitem"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                <?php endif; ?>
            </ul>

            <button class="hamburger-btn" id="mobileNavToggle" aria-label="Toggle navigation menu" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <main id="main-content">
