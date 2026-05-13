<?php
// includes/functions.php

// Register database-backed session handler (replaces file-based /tmp sessions)
require_once __DIR__ . '/SessionHandler.php';
$sessionHandler = new DatabaseSessionHandler();
session_set_save_handler($sessionHandler, true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restore getBasePath() which was missing
function getAppUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = defined('BASE_PATH') ? BASE_PATH : getBasePath();
    return rtrim("$protocol://$host/$basePath", '/');
}

function getBasePath() {
    // Check BASE_PATH constant first (set by api/index.php)
    if (defined('BASE_PATH')) {
        $bp = BASE_PATH;
        return $bp === '' ? '/' : rtrim($bp, '/') . '/';
    }

    $configured = getenv('APP_BASE_PATH');
    if ($configured !== false && trim($configured) !== '') {
        $normalized = '/' . trim(str_replace('\\', '/', trim($configured)), '/');
        return $normalized === '/' ? '/' : $normalized . '/';
    }

    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
    $segments = array_values(array_filter(explode('/', trim($scriptName, '/'))));
    if (empty($segments)) {
        return '/';
    }

    $knownAppDirs = ['admin', 'student', 'api', 'includes', 'jobs', 'css', 'js', 'images', 'receipts', 'database'];
    $baseSegments = [];
    foreach ($segments as $index => $segment) {
        if (in_array($segment, $knownAppDirs, true)) {
            if ($index === 0) {
                return '/';
            }
            $baseSegments = array_slice($segments, 0, $index);
            break;
        }
    }

    if (!empty($baseSegments)) {
        return '/' . implode('/', $baseSegments) . '/';
    }

    return '/';
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin', 'bursar']);
}

function isSuperAdmin() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin']);
}

function isBursar() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'bursar';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

function isParent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'parent';
}

function isTeacher() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
}

function isStaff() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'staff';
}

/**
 * Check if user is a parent OR a staff/teacher with linked children (dual-role).
 * Used by parent pages to grant access to dual-role users.
 */
function isParentOrDual() {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'parent') {
        return true;
    }
    return isset($_SESSION['has_children']) && $_SESSION['has_children'] === true;
}

/**
 * Check if the current user has children linked (dual-role).
 */
function hasChildren() {
    return isset($_SESSION['has_children']) && $_SESSION['has_children'] === true;
}

/**
 * RBAC: Define page-to-role permissions.
 * 'admin' = admin + super_admin only
 * 'bursar' = bursar only
 * 'admin_or_bursar' = both
 * 'student' = student only
 */
function getAccessControl() {
    return [
        // Full admin only
        'staff' => ['admin', 'super_admin'],
        'edit_staff' => ['admin', 'super_admin'],
        'salary' => ['admin', 'super_admin'],
        'payroll' => ['admin', 'super_admin'],
        'pay_slip' => ['admin', 'super_admin'],
        'settings' => ['admin', 'super_admin'],
        'module_settings' => ['admin', 'super_admin'],
        'users' => ['admin', 'super_admin'],
        'bulk_import' => ['admin', 'super_admin'],
        
        // Admin + Teacher
        'grades' => ['admin', 'super_admin', 'teacher'],
        'attendance' => ['admin', 'super_admin', 'teacher'],
        
        // Admin only (no teacher)
        'staff_attendance' => ['admin', 'super_admin'],
        'enrollments' => ['admin', 'super_admin'],
        
        // Bursar + Admin
        'dashboard' => ['admin', 'super_admin', 'bursar', 'teacher', 'staff'],
        'students' => ['admin', 'super_admin', 'bursar', 'teacher'],
        'edit_student' => ['admin', 'super_admin', 'bursar'],
        'payments' => ['admin', 'super_admin', 'bursar'],
        'fees' => ['admin', 'super_admin', 'bursar', 'teacher'],
        'reports' => ['admin', 'super_admin', 'bursar', 'teacher'],
        'verify' => ['admin', 'super_admin', 'bursar'],
        'messaging' => ['admin', 'super_admin', 'bursar', 'teacher', 'staff'],
        'inbox' => ['admin', 'super_admin', 'bursar', 'teacher', 'staff'],
    ];
}

/**
 * Get the list of class IDs assigned to the current teacher.
 * Returns an empty array if the user is not a teacher or has no classes assigned.
 */
function getTeacherClassIds($pdo) {
    if (!isTeacher()) return [];
    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) return [];
    
    try {
        // Find staff record for this user
        $stmt = $pdo->prepare("SELECT id FROM staff WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $staff = $stmt->fetch();
        if (!$staff) return [];
        $staff_id = (int)$staff['id'];
        
        // Find subjects where this teacher is assigned, extract unique class IDs
        $stmt = $pdo->prepare("SELECT DISTINCT class_id FROM subjects WHERE teacher_id = ? AND class_id IS NOT NULL");
        $stmt->execute([$staff_id]);
        $rows = $stmt->fetchAll();
        $class_ids = array_map(fn($r) => (int)$r['class_id'], $rows);
        return array_unique(array_filter($class_ids));
    } catch (Exception $e) {
        error_log("getTeacherClassIds error: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if current user can access a page.
 * Returns true if allowed, false otherwise.
 */
function canAccessPage($page) {
    if (!isLoggedIn()) return false;
    
    $role = $_SESSION['role'] ?? '';
    $acl = getAccessControl();
    
    // If page not in ACL, default: only full admins
    $allowed = $acl[$page] ?? ['admin', 'super_admin'];
    
    return in_array($role, $allowed);
}

/**
 * Enforce access control. Redirects to dashboard if denied.
 * Call at top of admin pages: requireAccess('page_name');
 */
function requireAccess($page) {
    if (!isLoggedIn()) {
        redirect('../login.php');
    }
    if (!canAccessPage($page)) {
        // Flash access denied message
        $_SESSION['access_denied'] = true;
        redirect('dashboard.php');
    }
}

/**
 * Get sidebar menu items filtered by user role.
 */
function getSidebarMenu($currentPage = '') {
    $role = $_SESSION['role'] ?? '';
    $isFullAdmin = in_array($role, ['admin', 'super_admin']);
    $isTeacher = in_array($role, ['teacher']);
    $isStaff = in_array($role, ['staff']);
    
    // Build all potential items with their ACL page keys
    $allItems = [
        ['href' => 'dashboard.php', 'icon' => 'fas fa-home', 'label' => 'Dashboard', 'acl' => 'dashboard'],
        ['href' => 'students.php', 'icon' => 'fas fa-user-graduate', 'label' => 'Students', 'acl' => 'students'],
    ];
    
    if ($isFullAdmin) {
        $allItems[] = ['href' => 'enrollments.php', 'icon' => 'fas fa-file-signature', 'label' => 'Enrollments', 'acl' => 'enrollments'];
        $allItems[] = ['href' => 'staff.php', 'icon' => 'fas fa-chalkboard-teacher', 'label' => 'Staff', 'acl' => 'staff'];
    }
    
    $allItems[] = ['href' => 'payments.php', 'icon' => 'fas fa-money-bill-wave', 'label' => 'Payments', 'acl' => 'payments'];
    $allItems[] = ['href' => 'fees.php', 'icon' => 'fas fa-list-alt', 'label' => 'Fee Structure', 'acl' => 'fees'];
    
    if ($isFullAdmin || $isTeacher) {
        $allItems[] = ['href' => 'grades.php', 'icon' => 'fas fa-clipboard-list', 'label' => 'SBA / Grades', 'acl' => 'grades'];
        $allItems[] = ['href' => 'attendance.php', 'icon' => 'fas fa-user-check', 'label' => 'Student Attendance', 'acl' => 'attendance'];
    }
    
    if ($isFullAdmin) {
        $allItems[] = ['href' => 'payroll.php', 'icon' => 'fas fa-file-invoice-dollar', 'label' => 'Payroll', 'acl' => 'payroll'];
        $allItems[] = ['href' => 'salary.php', 'icon' => 'fas fa-money-check-alt', 'label' => 'Salary Structures', 'acl' => 'salary'];
        $allItems[] = ['href' => 'staff_attendance.php', 'icon' => 'fas fa-user-tie', 'label' => 'Staff Attendance', 'acl' => 'staff_attendance'];
    }
    
    $allItems[] = ['href' => 'reports.php', 'icon' => 'fas fa-chart-bar', 'label' => 'Reports', 'acl' => 'reports'];
    $allItems[] = ['href' => 'verify.php', 'icon' => 'fas fa-qrcode', 'label' => 'Verify Receipt', 'acl' => 'verify'];
    
    if ($isFullAdmin) {
        $allItems[] = ['href' => 'users.php', 'icon' => 'fas fa-users-cog', 'label' => 'User Management', 'acl' => 'users'];
    }
    
    $allItems[] = ['href' => 'messaging.php', 'icon' => 'fas fa-envelope', 'label' => 'Messaging', 'acl' => 'messaging'];
    $allItems[] = ['href' => 'inbox.php', 'icon' => 'fas fa-inbox', 'label' => 'Inbox', 'acl' => 'inbox'];
    
    if ($isFullAdmin) {
        $allItems[] = ['href' => 'module_settings.php', 'icon' => 'fas fa-cogs', 'label' => 'Module Settings', 'acl' => 'module_settings'];
        $allItems[] = ['href' => 'subjects.php', 'icon' => 'fas fa-book', 'label' => 'Subjects', 'acl' => 'subjects'];
        $allItems[] = ['href' => 'settings.php', 'icon' => 'fas fa-tools', 'label' => 'System Settings', 'acl' => 'settings'];
    }
    
    // Dual-role: add "Parent View" link for staff/teachers who also have children
    if (isset($_SESSION['has_children']) && $_SESSION['has_children']) {
        $allItems[] = ['href' => '../parent/dashboard.php', 'icon' => 'fas fa-user-friends', 'label' => 'Parent View', 'acl' => null];
    }

    $allItems[] = ['href' => '../logout.php', 'icon' => 'fas fa-sign-out-alt', 'label' => 'Logout', 'acl' => null];
    
    // Filter by ACL: only show items the user can access (logout always shown)
    $menu = array_filter($allItems, function($item) {
        if ($item['acl'] === null) return true; // logout always visible
        return canAccessPage($item['acl']);
    });
    
    return array_values($menu); // re-index
}

/**
 * Render sidebar menu HTML.
 */
function renderSidebar($currentPage = '', $schoolName = 'Nex CEC') {
    $menu = getSidebarMenu($currentPage);
    $role = $_SESSION['role'] ?? 'admin';
    $roleLabel = ucfirst($role);
    
    $html = '';
    
    // Hamburger button (visible on mobile)
    $html .= '<button class="hamburger-btn" id="hamburgerBtn" aria-label="Open menu"><i class="fas fa-bars"></i></button>';
    
    // Sidebar overlay
    $html .= '<div class="sidebar-overlay" id="sidebarOverlay"></div>';
    
    // Sidebar
    $html .= '<aside class="sidebar" id="sidebar">';
    $html .= '<div class="sidebar-header position-relative" style="padding: 20px 10px;">';
    $html .= '<button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close menu"><i class="fas fa-times"></i></button>';
    $html .= '<img src="../images/school-logo.png" alt="Logo" class="rounded-full mb-10" style="width: 80px; height: 80px; background: #fff; padding: 5px;" onerror="this.src=\'../images/aamusted.jpg\'">';
    $html .= '<h3>' . htmlspecialchars($schoolName) . ' ' . $roleLabel . '</h3>';
    $html .= '</div>';
    $html .= '<ul class="sidebar-menu">';
    
    foreach ($menu as $item) {
        $active = ($currentPage === basename($item['href'], '.php')) ? ' class="active"' : '';
        $html .= '<li><a href="' . htmlspecialchars($item['href']) . '"' . $active . '><i class="' . htmlspecialchars($item['icon']) . '"></i> ' . htmlspecialchars($item['label']) . '</a></li>';
    }
    
    $html .= '</ul></aside>';
    
    // Mobile sidebar toggle script
    $html .= '<script>
    (function() {
        var hamburger = document.getElementById("hamburgerBtn");
        var sidebar = document.getElementById("sidebar");
        var overlay = document.getElementById("sidebarOverlay");
        var closeBtn = document.getElementById("sidebarCloseBtn");
        if (!hamburger || !sidebar || !overlay) return;
        
        function openSidebar() {
            sidebar.classList.add("open");
            overlay.classList.add("active");
            document.body.style.overflow = "hidden";
        }
        function closeSidebar() {
            sidebar.classList.remove("open");
            overlay.classList.remove("active");
            document.body.style.overflow = "";
        }
        
        hamburger.addEventListener("click", openSidebar);
        overlay.addEventListener("click", closeSidebar);
        if (closeBtn) closeBtn.addEventListener("click", closeSidebar);
        
        // Close sidebar when clicking a menu link (mobile)
        var links = sidebar.querySelectorAll(".sidebar-menu a");
        for (var i = 0; i < links.length; i++) {
            links[i].addEventListener("click", function() {
                if (window.innerWidth <= 768) closeSidebar();
            });
        }
        
        // Close on Escape key
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") closeSidebar();
        });
        
        // Close on window resize to desktop
        window.addEventListener("resize", function() {
            if (window.innerWidth > 768) closeSidebar();
        });
    })();
    </script>';
    
    return $html;
}

function redirect($url) {
    if (strpos($url, 'http') !== 0) {
        $basePath = defined('BASE_PATH') ? BASE_PATH : getBasePath();
        $url = $basePath . '/' . ltrim($url, '/');
    }
    header("Location: $url");
    exit;
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function flash($name, $message = '', $class = 'success') {
    if (!empty($message)) {
        $_SESSION[$name] = $message;
        $_SESSION[$name . '_class'] = $class;
    } elseif (isset($_SESSION[$name])) {
        $class = $_SESSION[$name . '_class'] ?? 'success';
        echo '<div class="alert alert-' . $class . '">' . $_SESSION[$name] . '</div>';
        unset($_SESSION[$name]);
        unset($_SESSION[$name . '_class']);
    }
}

/**
 * Enforce password reset for students who haven't reset their temporary password.
 */
function enforcePasswordReset() {
    if (isset($_SESSION['is_password_reset']) && $_SESSION['is_password_reset'] == 0) {
        // Only redirect if not already on the password reset page
        $currentScript = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($currentScript, 'password-reset') === false) {
            redirect('student/password-reset.php');
        }
    }
}
