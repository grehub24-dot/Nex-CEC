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
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher')
        || (isset($_SESSION['is_class_teacher']) && $_SESSION['is_class_teacher'] === true);
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
        'role_permissions' => ['admin', 'super_admin'],
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
    
    // Staff who are class teachers get the same access as 'teacher' role
    if (isTeacher() && in_array('teacher', $allowed)) {
        return true;
    }
    
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
    $isTeacher = \isTeacher();
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
        $allItems[] = ['href' => 'role_permissions.php', 'icon' => 'fas fa-user-shield', 'label' => 'Role Permissions', 'acl' => 'role_permissions'];
    }
    
    $allItems[] = ['href' => 'messaging.php', 'icon' => 'fas fa-envelope', 'label' => 'Communications', 'acl' => 'messaging'];
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
    $logoUrl = getCachedSchoolLogoUrl();
    $html .= '<img src="' . htmlspecialchars($logoUrl) . '" alt="Logo" class="rounded-full mb-10" style="width: 80px; height: 80px; background: #fff; padding: 5px;" onerror="this.src=\'../images/aamusted.jpg\'">';
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

/**
 * Render sidebar for staff pages (consistent across all staff/* pages).
 *
 * @param string $currentPage  Active page key for highlighting
 * @param string $schoolName   School name displayed in header
 * @param int    $unreadCount  Unread messages badge count
 * @param string $profilePic   Staff profile picture URL (absolute or relative)
 * @param string $staffName    Staff display name (shown instead of "Staff Portal")
 */
function renderStaffSidebar($currentPage = '', $schoolName = 'Nex CEC', $unreadCount = 0, $profilePic = '', $staffName = '') {
    $isTchr = \isTeacher();
    
    $html = '';
    
    // Hamburger button (visible on mobile)
    $html .= '<button class="hamburger-menu" id="hamburgerBtn" onclick="document.getElementById(\'sidebar\').classList.toggle(\'open\')">';
    $html .= '<i class="fas fa-bars"></i>';
    $html .= '</button>';
    
    // Sidebar
    $html .= '<aside class="staff-sidebar" id="sidebar">';
    $html .= '<div class="sidebar-header">';
    if (!empty($profilePic)) {
        $html .= '<img src="' . htmlspecialchars(resolve_storage_url($profilePic, '')) . '" alt="Profile" onerror="this.src=\'../images/aamusted.jpg\'">';
    } else {
        $logoUrl = getCachedSchoolLogoUrl();
        $html .= '<img src="' . htmlspecialchars($logoUrl) . '" alt="Logo" onerror="this.src=\'../images/aamusted.jpg\'">';
    }
    $html .= '<h3>' . htmlspecialchars($schoolName) . '</h3>';
    $html .= '<p>' . ($staffName ? htmlspecialchars($staffName) : 'Staff Portal') . '</p>';
    $html .= '</div>';
    $html .= '<ul>';
    
    // Build menu items
    $hasChildren = isset($_SESSION['has_children']) && $_SESSION['has_children'] === true;
    
    $items = [
        ['href' => '../staff/dashboard.php', 'icon' => 'fas fa-home',        'label' => 'Dashboard',        'key' => 'dashboard'],
        ['href' => '../staff/grades.php',    'icon' => 'fas fa-clipboard-list', 'label' => 'SBA / Grades',    'key' => 'grades',      'teacherOnly' => true],
        ['href' => '../staff/attendance.php','icon' => 'fas fa-calendar-check','label' => 'My Attendance',   'key' => 'attendance'],
        ['href' => '../staff/payslip.php',   'icon' => 'fas fa-file-invoice-dollar','label' => 'Pay Slips',  'key' => 'payslip'],
        ['href' => '../staff/profile.php',   'icon' => 'fas fa-user-cog',   'label' => 'Profile',           'key' => 'profile'],
        ['href' => '../staff/messaging.php', 'icon' => 'fas fa-envelope',    'label' => 'Messages',          'key' => 'messaging',   'badge' => $unreadCount],
        ['href' => '../staff/student_attendance.php','icon' => 'fas fa-user-check', 'label' => 'Student Attendance','key' => 'student_attendance', 'teacherOnly' => true],
    ];
    
    // Add Parent Portal link for dual-role staff (staff + parent)
    if ($hasChildren) {
        $items[] = ['href' => '../parent/dashboard.php', 'icon' => 'fas fa-child', 'label' => 'My Children / Wards', 'key' => 'children'];
    }
    
    $items[] = ['href' => '../logout.php', 'icon' => 'fas fa-sign-out-alt', 'label' => 'Logout', 'key' => 'logout'];
    
    foreach ($items as $item) {
        // Skip teacher-only items for non-teachers
        if (!empty($item['teacherOnly']) && !$isTchr) {
            continue;
        }
        
        $active = ($currentPage === $item['key']) ? ' class="active"' : '';
        $html .= '<li><a href="' . htmlspecialchars($item['href']) . '"' . $active . '>';
        $html .= '<i class="' . $item['icon'] . '"></i> ' . htmlspecialchars($item['label']);
        if (!empty($item['badge']) && $item['badge'] > 0) {
            $html .= ' <span class="msg-count">' . (int)$item['badge'] . '</span>';
        }
        $html .= '</a></li>';
    }
    
    $html .= '</ul></aside>';
    
    // Mobile sidebar toggle script
    $html .= '<script>
    (function() {
        var hamburger = document.getElementById("hamburgerBtn");
        var sidebar = document.getElementById("sidebar");
        if (!hamburger || !sidebar) return;
        hamburger.addEventListener("click", function(e) {
            e.stopPropagation();
            sidebar.classList.toggle("open");
        });
        document.addEventListener("click", function(e) {
            if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                sidebar.classList.remove("open");
            }
        });
        var links = sidebar.querySelectorAll("a");
        for (var i = 0; i < links.length; i++) {
            links[i].addEventListener("click", function() {
                if (window.innerWidth <= 768) sidebar.classList.remove("open");
            });
        }
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") sidebar.classList.remove("open");
        });
    })();
    </script>';
    
    return $html;
}

/**
 * Render parent portal sidebar with hamburger and navigation.
 * 
 * @param string $currentPage  Key of the active page (dashboard, messages, profile, password, fees, student, report)
 * @param string $schoolName   School name to display in header
 * @param int    $unreadCount  Number of unread messages for badge
 * @param string $profilePic   URL/path to profile picture
 * @param bool   $hasChildren  Whether user has linked children (shows Staff Dashboard link)
 * @return string HTML output
 */
function renderParentSidebar($currentPage = '', $schoolName = 'Nex CEC', $unreadCount = 0, $profilePic = '', $hasChildren = false) {
    $html = '';
    
    // Hamburger button (visible on mobile)
    $html .= '<button class="hamburger-menu" id="hamburgerBtn" onclick="document.getElementById(\'sidebar\').classList.toggle(\'open\')">';
    $html .= '<i class="fas fa-bars"></i>';
    $html .= '</button>';
    
    // Sidebar
    $html .= '<aside class="parent-sidebar" id="sidebar">';
    $html .= '<div class="sidebar-header">';
    if (!empty($profilePic)) {
        $html .= '<img src="' . htmlspecialchars($profilePic) . '" alt="Profile" onerror="this.src=\'../images/aamusted.jpg\'" style="width:64px;height:64px;border-radius:50%;background:white;padding:3px;margin-bottom:10px;object-fit:cover;">';
    } else {
        $logoUrl = getCachedSchoolLogoUrl();
        $html .= '<img src="' . htmlspecialchars($logoUrl) . '" alt="Logo" onerror="this.src=\'../images/aamusted.jpg\'">';
    }
    $html .= '<h3>' . htmlspecialchars($schoolName) . '</h3>';
    $html .= '<p>Parent Portal</p>';
    $html .= '</div>';
    $html .= '<ul>';
    
    // Staff Dashboard link (for dual-role users who have children)
    if ($hasChildren) {
        $active = ($currentPage === 'staff_dashboard') ? ' class="active"' : '';
        $html .= '<li><a href="../staff/dashboard.php"' . $active . '><i class="fas fa-chalkboard-teacher"></i> Staff Dashboard</a></li>';
    }
    
    // My Children
    $active = ($currentPage === 'dashboard') ? ' class="active"' : '';
    $html .= '<li><a href="../parent/dashboard.php"' . $active . '><i class="fas fa-home"></i> My Children</a></li>';
    
    // Messages (with unread badge)
    $active = ($currentPage === 'messages') ? ' class="active"' : '';
    $html .= '<li><a href="../parent/messages.php"' . $active . '><i class="fas fa-envelope"></i> Messages';
    if ($unreadCount > 0) {
        $html .= ' <span class="msg-count">' . (int)$unreadCount . '</span>';
    }
    $html .= '</a></li>';
    
    // My Profile
    $active = ($currentPage === 'profile') ? ' class="active"' : '';
    $html .= '<li><a href="../parent/profile.php"' . $active . '><i class="fas fa-user-cog"></i> My Profile</a></li>';
    
    // Change Password
    $active = ($currentPage === 'password') ? ' class="active"' : '';
    $html .= '<li><a href="../parent/password-reset.php"' . $active . '><i class="fas fa-key"></i> Change Password</a></li>';
    
    // Logout
    $html .= '<li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>';
    
    $html .= '</ul></aside>';
    
    // Mobile sidebar toggle script
    $html .= '<script>
    (function() {
        var hamburger = document.getElementById("hamburgerBtn");
        var sidebar = document.getElementById("sidebar");
        if (!hamburger || !sidebar) return;
        hamburger.addEventListener("click", function(e) {
            e.stopPropagation();
            sidebar.classList.toggle("open");
        });
        document.addEventListener("click", function(e) {
            if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                sidebar.classList.remove("open");
            }
        });
        var links = sidebar.querySelectorAll("a");
        for (var i = 0; i < links.length; i++) {
            links[i].addEventListener("click", function() {
                if (window.innerWidth <= 768) sidebar.classList.remove("open");
            });
        }
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") sidebar.classList.remove("open");
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
    // Handle null input to avoid deprecated trim(null) warning in PHP 8.1+
    if ($input === null || $input === false) {
        return '';
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Attempt to fix double-encoded UTF-8 text that was corrupted during storage.
 * Common cause: UTF-8 bytes stored into a latin1 column, then re-encoded.
 * This is a best-effort recovery for display purposes.
 */
function fix_utf8_encoding($text) {
    if ($text === null || $text === '') {
        return $text;
    }

    // If the text is already valid UTF-8 and doesn't have suspicious sequences, return as-is
    if (mb_check_encoding($text, 'UTF-8') && !preg_match('/[\x80-\x9F]/', $text)) {
        return $text;
    }

    // Try to detect and fix: if mb_detect_encoding says it's UTF-8 but has Windows-1252 range chars
    if (preg_match('/[\x80-\x9F]/', $text)) {
        // Convert from Windows-1252 to UTF-8
        $converted = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        if ($converted !== false) {
            $text = $converted;
        }
    }

    // If still not valid UTF-8, try ISO-8859-1 as source
    if (!mb_check_encoding($text, 'UTF-8')) {
        $converted = @mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        if ($converted !== false) {
            $text = $converted;
        }
    }

    return $text;
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
 * Enforce password reset for users who haven't reset their temporary password.
 * Works for students, staff, teachers, and bursars.
 */
function enforcePasswordReset() {
    if (isset($_SESSION['is_password_reset']) && $_SESSION['is_password_reset'] == 0) {
        // Only redirect if not already on the password reset page
        $currentScript = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($currentScript, 'password-reset') === false) {
            redirect('password-reset.php');
        }
    }
}

/**
 * Auto-migrate the old single "Nursery" class to "Nursery 1" and "Nursery 2".
 * Runs once on first admin page load after update.
 */
function migrateNurseryClasses($pdo): void {
    try {
        // Check if old "Nursery" class still exists
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE name = ?");
        $stmt->execute(['Nursery']);
        $old_nursery = $stmt->fetch();

        // Check if "Nursery 1" already exists (migration already done)
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE name = ?");
        $stmt->execute(['Nursery 1']);
        $nursery1_exists = $stmt->fetch();

        if ($old_nursery && !$nursery1_exists) {
            $old_id = (int)$old_nursery['id'];

            // Rename "Nursery" → "Nursery 1"
            $stmt = $pdo->prepare("UPDATE classes SET name = ? WHERE id = ?");
            $stmt->execute(['Nursery 1', $old_id]);

            // Update sort_order for all affected classes
            // New order: Creche(0), Nursery1(1), Nursery2(2), KG1(3), KG2(4), Basic1(5)...
            $sort_updates = [
                'Nursery 1' => 1,
                'Nursery 2' => 2,
                'KG 1'      => 3,
                'KG 2'      => 4,
                'Basic 1'   => 5,
                'Basic 2'   => 6,
                'Basic 3'   => 7,
                'Basic 4'   => 8,
                'Basic 5'   => 9,
                'Basic 6'   => 10,
                'JHS 1'     => 11,
                'JHS 2'     => 12,
                'JHS 3'     => 13,
            ];
            foreach ($sort_updates as $name => $order) {
                $stmt = $pdo->prepare("UPDATE classes SET sort_order = ? WHERE name = ?");
                $stmt->execute([$order, $name]);
            }

            // Insert "Nursery 2"
            $stmt = $pdo->prepare("INSERT INTO classes (name, level_group, sort_order) VALUES (?, ?, ?) ON CONFLICT (name) DO NOTHING");
            $stmt->execute(['Nursery 2', 'early_childhood', 2]);

            // Update existing students with class_name = 'Nursery' → 'Nursery 1'
            $stmt = $pdo->prepare("UPDATE students SET class_name = ? WHERE class_name = ?");
            $stmt->execute(['Nursery 1', 'Nursery']);
        }
    } catch (Exception $e) {
        // Migration failed silently — no disruption to the user
        error_log("migrateNurseryClasses: " . $e->getMessage());
    }
}

/**
 * Fetch system settings as a key-value array.
 * Call at the top of any page: $settings = fetchSettings($pdo);
 * Replaces the 10-line $settings = []; try { ... } catch pattern.
 */
function fetchSettings($pdo): array {
    $settings = [];
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        error_log("fetchSettings: " . $e->getMessage());
    }
    return $settings;
}

// ==========================================
// School Logo Helpers
// ==========================================

/**
 * Get the school logo URL for use in <img src="..."> tags.
 * Uses the uploaded logo from system_settings if available,
 * otherwise falls back to the default image.
 *
 * @param array $settings The settings array (from fetchSettings or inline query)
 * @param string $relativePath Relative prefix to the images folder (e.g. '../' or '')
 * @return string The logo URL
 */
function getSchoolLogoUrl(array $settings = [], string $relativePath = '../'): string {
    if (!empty($settings['school_logo_url'])) {
        return $settings['school_logo_url'];
    }
    return $relativePath . 'images/aamusted.jpg';
}

/**
 * Get the local filesystem path to the school logo for PDF/image generation.
 * Falls back to aamusted.jpg if no local school-logo.png exists.
 *
 * @return string Absolute filesystem path
 */
function getSchoolLogoFilePath(): string {
    $primaryPath = __DIR__ . '/../images/school-logo.png';
    if (file_exists($primaryPath)) {
        return $primaryPath;
    }
    $fallbackAamusted = __DIR__ . '/../images/aamusted.jpg';
    if (file_exists($fallbackAamusted)) {
        return $fallbackAamusted;
    }
    $fallbackInfotess = __DIR__ . '/../images/infotess.png';
    if (file_exists($fallbackInfotess)) {
        return $fallbackInfotess;
    }
    return $primaryPath; // last resort, will fail gracefully
}

/**
 * Get the school logo URL with database caching.
 * Queries system_settings once per request and caches in a static variable.
 * Used by sidebar render functions to show the uploaded logo.
 */
function getCachedSchoolLogoUrl(): string {
    static $url = null;
    if ($url !== null) {
        return $url;
    }
    // Check if stored in session first (faster)
    if (!empty($_SESSION['school_logo_url'])) {
        $url = $_SESSION['school_logo_url'];
        return $url;
    }
    // Fall back to DB query
    try {
        global $pdo;
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute(['school_logo_url']);
            $row = $stmt->fetch();
            if ($row && !empty($row['setting_value'])) {
                $url = $row['setting_value'];
                $_SESSION['school_logo_url'] = $url; // cache in session
                return $url;
            }
        }
    } catch (Exception $e) {
        error_log("getCachedSchoolLogoUrl: " . $e->getMessage());
    }
    $url = '../images/aamusted.jpg';
    return $url;
}

// ==========================================
// Supabase Storage Helper
// ==========================================

/**
 * Upload an uploaded file (from $_FILES) to a Supabase Storage bucket.
 * Returns the public URL on success, or the fallback path on failure.
 *
 * @param array  $file     An entry from $_FILES, e.g. $_FILES['image']
 * @param string $bucket   Supabase bucket name (e.g. 'executives', 'profiles')
 * @param string $filename Custom filename (if empty, uses time + original name)
 * @param string $fallback Fallback URL/path if upload fails or no file given
 * @return string          Full public URL of uploaded file, or fallback value
 */
function upload_to_supabase_storage(array $file, string $bucket, string $filename = '', string $fallback = 'images/aamusted.jpg'): string {
    global $supabase;
    if (!$supabase || !($supabase instanceof SupabaseClient)) {
        error_log("upload_to_supabase_storage: SupabaseClient not available");
        return $fallback;
    }

    if (empty($file) || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return $fallback;
    }

    $tmpPath = $file['tmp_name'];
    if (!file_exists($tmpPath) || !is_readable($tmpPath)) {
        return $fallback;
    }

    try {
        if (empty($filename)) {
            $ext = pathinfo($file['name'] ?? 'file', PATHINFO_EXTENSION);
            $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        }

        $fileData = file_get_contents($tmpPath);
        if ($fileData === false) {
            throw new Exception("Cannot read uploaded file from " . $tmpPath);
        }

        // Detect content type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $contentType = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        $supabase->uploadFile($bucket, $filename, $fileData, $contentType ?: 'application/octet-stream');
        return $supabase->getPublicUrl($bucket, $filename);
    } catch (Exception $e) {
        $msg = "Supabase Storage upload error (" . $bucket . "/" . $filename . "): " . $e->getMessage();
        error_log($msg);
        if (defined('VERBOSE_ERRORS') && VERBOSE_ERRORS) {
            error_log("Upload fallback used: " . ($fallback ?: 'none'));
        }
        return $fallback;
    }
}

/**
 * Resolve a storage URL for use in <img src="...">.
 * If the stored value is already an absolute URL (http/https), return as-is.
 * If it's a relative path (e.g. images/executives/foo.jpg), prepend ../ for local dev.
 *
 * @param string|null $path     Stored URL or path
 * @param string      $fallback Fallback relative path
 * @return string               Safe src attribute value
 */
function resolve_storage_url(?string $path, string $fallback = 'images/aamusted.jpg'): string {
    $url = $path ?: $fallback;
    if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
        return $url;
    }
    return '../' . ltrim($url, '/');
}

/**
 * Get the best available profile picture URL for a staff member.
 *
 * Fallback chain: DB column → Session cache → Empty (no picture uploaded yet).
 *
 * @param string|null $staffDbValue Value from staff.profile_picture column (null if column missing)
 * @param int         $userId       The user's ID (for constructing predictable storage path)
 * @return string                   Profile picture URL or empty string
 */
function getStaffProfilePictureUrl(?string $staffDbValue, int $userId): string {
    // 1. Database column (profile_picture column exists and has a value)
    if (!empty($staffDbValue)) {
        return $staffDbValue;
    }

    // 2. Session cache (set after upload even if DB column is missing)
    if (!empty($_SESSION['profile_picture'])) {
        return $_SESSION['profile_picture'];
    }

    // 3. No picture available
    return '';
}

// ==========================================
// CSRF Protection
// ==========================================

/**
 * Generate and store a CSRF token in the session if one doesn't exist.
 * Returns the current valid token.
 */
function generate_csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token against the session token using timing-safe comparison.
 * Automatically regenerates the token after successful validation (one-time use).
 * Returns true if valid, false otherwise.
 */
function validate_csrf_token(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    $valid = hash_equals($_SESSION['csrf_token'], $token);
    if ($valid) {
        // Regenerate token after successful validation (one-time use)
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $valid;
}

/**
 * Verify CSRF token from POST/GET request. If invalid, stops execution with an error.
 * Call at the top of any form handler: validate_request_csrf();
 */
function validate_request_csrf(): void {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        error_log("CSRF validation failed for " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
        http_response_code(419);
        die("Session expired or invalid request. Please refresh the page and try again.");
    }
}

/**
 * Render a hidden CSRF token input field.
 * Usage inside forms: <?php csrf_field(); ?>
 */
function csrf_field(): void {
    echo '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
}

/**
 * Render a CSRF token as a query parameter (for search links, delete links, etc.).
 * Usage: <a href="page.php?action=delete&id=1&<?php csrf_query(); ?>">
 */
function csrf_query(): string {
    return 'csrf_token=' . urlencode(generate_csrf_token());
}

// ==========================================
// Staff Invite Helpers
// ==========================================

/**
 * Generate a cryptographically secure 64-character hex token for staff invites.
 */
function generateStaffInviteToken(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Store a new staff invite record and send email/SMS notification.
 *
 * @param int    $staffId   staff.id
 * @param int    $userId    users.id
 * @param int    $invitedBy admin users.id
 * @param string $email     Staff email address
 * @param string $phone     Staff phone number
 * @param string $staffName Staff full name for message
 * @return array ['success' => bool, 'token' => string, 'message' => string]
 */
function sendStaffInvite(int $staffId, int $userId, int $invitedBy, string $email, string $phone, string $staffName): array {
    global $pdo;

    try {
        $token = generateStaffInviteToken();
        $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 48 hours from now

        // Check for existing pending invite
        $existing = $pdo->prepare("SELECT id, token FROM staff_invites WHERE staff_id = ? AND status = 'pending'");
        $existing->execute([$staffId]);
        $existingRow = $existing->fetch();

        if ($existingRow) {
            // Reuse existing token, just re-send
            $token = $existingRow['token'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO staff_invites (staff_id, user_id, token, status, invited_by, expires_at) VALUES (?, ?, ?, 'pending', ?, ?)");
            $stmt->execute([$staffId, $userId, $token, $invitedBy, $expiresAt]);
        }

        $inviteLink = rtrim(getenv('APP_URL') ?: 'https://nex-cec.vercel.app', '/') . '/staff/register.php?token=' . $token;

        $sentEmail = false;
        $sentSms = false;

        // Send email
        if (!empty($email)) {
            try {
                require_once __DIR__ . '/Mailer.php';
                $mailer = new Mailer();
                if ($mailer) {
                    $subject = "Staff Registration Invitation - " . ($GLOBALS['school_name'] ?? 'SchoolName');
                    $body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <h2 style='color: #1a5276;'>Staff Registration Invitation</h2>
                        <p>Dear <strong>" . htmlspecialchars($staffName) . "</strong>,</p>
                        <p>You have been invited to register for the staff portal of <strong>" . ($GLOBALS['school_name'] ?? 'SchoolName') . "</strong>.</p>
                        <p>Please click the button below to complete your registration. This link will expire in <strong>48 hours</strong>.</p>
                        <p style='text-align: center; margin: 30px 0;'>
                            <a href='$inviteLink' style='background: #1a5276; color: white; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-size: 16px; display: inline-block;'>Complete Registration</a>
                        </p>
                        <p>Or copy this link into your browser:</p>
                        <p style='background: #f5f5f5; padding: 10px; word-break: break-all; font-size: 13px;'>$inviteLink</p>
                        <p>If you did not expect this invitation, please ignore this email.</p>
                        <hr style='border: none; border-top: 1px solid #eee;'>
                        <p style='color: #999; font-size: 12px;'>This is an automated message from " . ($GLOBALS['school_name'] ?? 'SchoolName') . ".</p>
                    </div>";
                    $sentEmail = $mailer->sendHTML($email, $subject, $body);
                }
            } catch (Exception $e) {
                error_log("sendStaffInvite email error: " . $e->getMessage());
            }
        }

        // Send SMS
        if (!empty($phone)) {
            try {
                $smsText = "Staff Registration: $inviteLink (expires in 48h) - " . ($GLOBALS['school_name'] ?? 'SchoolName');
                require_once __DIR__ . '/SMSHelper.php';
                $smsHelper = new SMSHelper();
                $sentSms = $smsHelper->send($phone, $smsText);
            } catch (Exception $e) {
                error_log("sendStaffInvite SMS error: " . $e->getMessage());
            }
        }

        // Log delivery results
        error_log("sendStaffInvite result — staff_id=$staffId email_sent=" . ($sentEmail ? '1' : '0') . " sms_sent=" . ($sentSms ? '1' : '0') . " token=$token");

        // Update sent flags
        $upd = $pdo->prepare("UPDATE staff_invites SET email_sent = ?, sms_sent = ? WHERE token = ?");
        $upd->execute([$sentEmail ? 1 : 0, $sentSms ? 1 : 0, $token]);

        // Report accurate success — at least one delivery method worked
        if ($sentEmail || $sentSms) {
            return [
                'success' => true,
                'token' => $token,
                'message' => 'Invite sent successfully' . ($sentEmail ? ' via email' : '') . ($sentSms ? ($sentEmail ? ' and ' : ' via ') . 'SMS' : '') . '.'
            ];
        } else {
            return [
                'success' => true,
                'token' => $token,
                'message' => 'Invite link created but delivery failed. Check SMTP/SMS configuration in environment variables. Link: ' . $inviteLink
            ];
        }
    } catch (Exception $e) {
        error_log("sendStaffInvite error: " . $e->getMessage());
        return [
            'success' => false,
            'token' => '',
            'message' => 'Failed to send invite: ' . $e->getMessage()
        ];
    }
}

/**
 * Get the invite status string for a staff member.
 *
 * @param int   $staffId
 * @return string 'accepted' | 'pending' | 'expired' | 'not_invited'
 */
function getStaffInviteStatus(int $staffId): string {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT status, expires_at FROM staff_invites WHERE staff_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$staffId]);
        $row = $stmt->fetch();
        if (!$row) {
            return 'not_invited';
        }
        if ($row['status'] === 'accepted') {
            return 'accepted';
        }
        if ($row['status'] === 'pending') {
            $expiresAt = strtotime($row['expires_at']);
            if ($expiresAt < time()) {
                return 'expired';
            }
            return 'pending';
        }
        return $row['status'] ?? 'not_invited';
    } catch (Exception $e) {
        error_log("getStaffInviteStatus error: " . $e->getMessage());
        return 'not_invited';
    }
}

/**
 * Upload an uploaded file from $_FILES to Supabase Storage.
 * Thin wrapper specifically for staff documents.
 */
function uploadStaffFile(array $file, string $bucket = 'staff_documents'): string {
    global $supabase;
    if (!$supabase || !($supabase instanceof SupabaseClient) || empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return '';
    }
    try {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('staff_') . '_' . time() . '.' . $ext;
        $filePath = $filename;
        $fileContent = file_get_contents($file['tmp_name']);
        if ($fileContent === false) return '';

        // Ensure bucket exists
        try { $supabase->createBucket($bucket, ['public' => true]); } catch (Exception $e) {}

        $result = $supabase->uploadFile($bucket, $filePath, $fileContent, $ext === 'pdf' ? 'application/pdf' : mime_content_type($file['tmp_name']));

        if ($result) {
            return $supabase->getPublicUrl($bucket, $filePath);
        }
    } catch (Exception $e) {
        error_log("uploadStaffFile error: " . $e->getMessage());
    }
    return '';
}

/**
 * Decode staff documents JSON into a comma-separated HTML list.
 */
function formatStaffDocuments(string $documentsJson): string {
    $docs = json_decode($documentsJson, true);
    if (!is_array($docs) || empty($docs)) return '-';
    $items = [];
    foreach ($docs as $url) {
        $name = basename($url);
        $items[] = '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener">' . htmlspecialchars($name) . '</a>';
    }
    return implode(', ', $items);
}
