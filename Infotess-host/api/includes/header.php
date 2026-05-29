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
    $school_logo = $settings['school_logo_url'] ?? ($base_url . 'images/chariot-logo.svg');

    // Determine current page for active nav state
    $current_page = basename($_SERVER['PHP_SELF']);
    ?>

    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-inner">
                <a href="<?php echo $base_url; ?>index.php" class="header-logo">
                    <div class="header-logo-img">
                        <?php echo substr($school_name, 0, 1); ?>
                    </div>
                    <h1 class="header-logo-text"><?php echo htmlspecialchars($school_name); ?></h1>
                </a>

                <nav class="header-nav">
                    <a href="<?php echo $base_url; ?>index.php" class="<?php echo ($current_page === 'home.php' || $current_page === 'index.php') ? 'active' : ''; ?>">Home</a>
                    <a href="<?php echo $base_url; ?>about.php" class="<?php echo ($current_page === 'about.php') ? 'active' : ''; ?>">About</a>
                    <a href="<?php echo $base_url; ?>contact.php" class="<?php echo ($current_page === 'contact.php') ? 'active' : ''; ?>">Contact</a>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if (isset($_SESSION['has_children']) && $_SESSION['has_children']): ?>
                            <a href="<?php echo $base_url; ?>route_selector.php" class="btn btn-primary btn-sm">Portals</a>
                        <?php elseif ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin'): ?>
                            <a href="<?php echo $base_url; ?>admin_dashboard.php" class="btn btn-primary btn-sm">Admin</a>
                        <?php elseif ($_SESSION['role'] === 'parent'): ?>
                            <a href="<?php echo $base_url; ?>parent_dashboard.php" class="btn btn-primary btn-sm">Parent</a>
                        <?php elseif ($_SESSION['role'] === 'staff' || $_SESSION['role'] === 'teacher'): ?>
                            <a href="<?php echo $base_url; ?>staff_dashboard.php" class="btn btn-primary btn-sm">Staff</a>
                        <?php else: ?>
                            <a href="<?php echo $base_url; ?>student_dashboard.php" class="btn btn-primary btn-sm">Dashboard</a>
                        <?php endif; ?>
                        <a href="<?php echo $base_url; ?>logout.php" style="color: var(--color-text-secondary);" aria-label="Logout"><i class="fas fa-sign-out-alt"></i></a>
                    <?php else: ?>
                        <a href="<?php echo $base_url; ?>login.php" class="btn btn-secondary btn-sm">Login</a>
                        <a href="<?php echo $base_url; ?>register.php" class="btn btn-primary btn-sm">Enroll</a>
                    <?php endif; ?>
                </nav>

                <div class="header-actions">
                    <button class="header-toggle" id="mobileNavToggle" aria-label="Toggle menu" aria-expanded="false">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

            <!-- Mobile Nav -->
            <nav class="mobile-nav" id="mobileNav">
                <a href="<?php echo $base_url; ?>index.php">Home</a>
                <a href="<?php echo $base_url; ?>about.php">About</a>
                <a href="<?php echo $base_url; ?>contact.php">Contact</a>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if (isset($_SESSION['has_children']) && $_SESSION['has_children']): ?>
                        <a href="<?php echo $base_url; ?>route_selector.php">Portals</a>
                    <?php elseif ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin'): ?>
                        <a href="<?php echo $base_url; ?>admin_dashboard.php">Admin</a>
                    <?php elseif ($_SESSION['role'] === 'parent'): ?>
                        <a href="<?php echo $base_url; ?>parent_dashboard.php">Parent</a>
                    <?php elseif ($_SESSION['role'] === 'staff' || $_SESSION['role'] === 'teacher'): ?>
                        <a href="<?php echo $base_url; ?>staff_dashboard.php">Staff</a>
                    <?php else: ?>
                        <a href="<?php echo $base_url; ?>student_dashboard.php">Dashboard</a>
                    <?php endif; ?>
                    <a href="<?php echo $base_url; ?>logout.php">Logout</a>
                <?php else: ?>
                    <a href="<?php echo $base_url; ?>login.php">Login</a>
                    <a href="<?php echo $base_url; ?>register.php">Enroll</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <script>
    // Mobile nav toggle
    (function() {
        var toggle = document.getElementById("mobileNavToggle");
        var mobileNav = document.getElementById("mobileNav");
        if (!toggle || !mobileNav) return;

        toggle.addEventListener("click", function() {
            mobileNav.classList.toggle("active");
            var isOpen = mobileNav.classList.contains("active");
            toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
        });

        // Close nav when clicking a link (mobile)
        var links = mobileNav.querySelectorAll("a");
        for (var i = 0; i < links.length; i++) {
            links[i].addEventListener("click", function() {
                mobileNav.classList.remove("active");
                toggle.setAttribute("aria-expanded", "false");
            });
        }
    })();
    </script>

    <main class="main" id="main-content">
