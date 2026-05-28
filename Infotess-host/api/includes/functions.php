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
        'fees_debt' => ['admin', 'super_admin', 'bursar', 'teacher'],
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
    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) return [];
    
    try {
        // Find staff record for this user
        $stmt = $pdo->prepare("SELECT id FROM staff WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $staff = $stmt->fetch();
        if (!$staff) return [];
        $staff_id = (int)$staff['id'];
        
        $class_ids = [];
        
        // 1. If the session says this user is a teacher, check subjects
        if (isTeacher()) {
            $stmt = $pdo->prepare("SELECT DISTINCT class_id FROM subjects WHERE teacher_id = ? AND class_id IS NOT NULL");
            $stmt->execute([$staff_id]);
            $rows = $stmt->fetchAll();
            $class_ids = array_map(fn($r) => (int)$r['class_id'], $rows);
        }
        
        // 2. ALWAYS check class_teachers — works regardless of session state.
        //    This covers Class Teachers who haven't logged out/in after their
        //    role was changed, and also serves as the primary mechanism for
        //    Class Teachers who have no subjects assigned.
        try {
            $ctStmt = $pdo->prepare("SELECT class_id FROM class_teachers WHERE staff_id = ?");
            $ctStmt->execute([$staff_id]);
            $ctRows = $ctStmt->fetchAll();
            foreach ($ctRows as $ctRow) {
                $class_ids[] = (int)$ctRow['class_id'];
            }
        } catch (Exception $e) {
            // class_teachers table might not exist yet — ignore silently
            error_log("getTeacherClassIds: class_teachers table not accessible: " . $e->getMessage());
        }
        
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
    $allItems[] = ['href' => 'fees_debt.php', 'icon' => 'fas fa-file-invoice', 'label' => 'Fee Debt Report', 'acl' => 'fees_debt'];
    $allItems[] = ['href' => 'class_billing.php', 'icon' => 'fas fa-users-cog', 'label' => 'Class Billing', 'acl' => 'fees_debt'];
    
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
        $allItems[] = ['href' => 'link_subjects.php', 'icon' => 'fas fa-link', 'label' => 'Link Subjects', 'acl' => 'subjects'];
        $allItems[] = ['href' => 'academic_calendar.php', 'icon' => 'fas fa-calendar-alt', 'label' => 'Academic Calendar', 'acl' => 'settings'];
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
    $html .= '<img src="' . htmlspecialchars($logoUrl) . '" alt="Logo" style="width: 56px; height: 56px; object-fit: cover; background: #fff; border-radius: 50%; display: inline-block;" onerror="this.onerror=null;this.src=\'../images/aamusted.jpg\'">';
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
    
    // Hamburger button (visible on mobile) — NO inline onclick, handled by JS below to avoid double-toggle
    $html .= '<button class="hamburger-menu" id="hamburgerBtn"><i class="fas fa-bars"></i></button>';
    
    // Sidebar overlay (tapping it closes sidebar)
    $html .= '<div class="sidebar-overlay" id="sidebarOverlay" style="z-index:90;"></div>';
    
    // Sidebar
    $html .= '<aside class="staff-sidebar" id="sidebar">';
    $html .= '<div class="sidebar-header">';
    $logoUrl = getCachedSchoolLogoUrl();
    $html .= '<img src="' . htmlspecialchars($logoUrl) . '" alt="Logo" style="width:64px;height:64px;border-radius:50%;object-fit:cover;background:white;padding:3px;margin-bottom:10px;" onerror="this.onerror=null;this.src=\'../images/aamusted.jpg\'">';
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
        ['href' => '../staff/fees_debt.php', 'icon' => 'fas fa-file-invoice', 'label' => 'Fee Debt', 'key' => 'fees_debt', 'teacherOnly' => true],
        ['href' => '../staff/academic_calendar.php', 'icon' => 'fas fa-calendar-alt', 'label' => 'Academic Calendar', 'key' => 'academic_calendar'],
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
    
    // Mobile sidebar toggle script — removes inline onclick conflict, adds overlay support
    $html .= '<script>
    (function() {
        var hamburger = document.getElementById("hamburgerBtn");
        var sidebar = document.getElementById("sidebar");
        var overlay = document.getElementById("sidebarOverlay");
        if (!hamburger || !sidebar) return;
        function openSidebar() {
            sidebar.classList.add("open");
            if (overlay) overlay.classList.add("active");
            document.body.style.overflow = "hidden";
        }
        function closeSidebar() {
            sidebar.classList.remove("open");
            if (overlay) overlay.classList.remove("active");
            document.body.style.overflow = "";
        }
        hamburger.addEventListener("click", function() {
            if (sidebar.classList.contains("open")) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
        if (overlay) overlay.addEventListener("click", closeSidebar);
        var links = sidebar.querySelectorAll("a");
        for (var i = 0; i < links.length; i++) {
            links[i].addEventListener("click", function() {
                if (window.innerWidth <= 768) closeSidebar();
            });
        }
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") closeSidebar();
        });
        window.addEventListener("resize", function() {
            if (window.innerWidth > 768) closeSidebar();
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
    
    // Hamburger button (visible on mobile) — NO inline onclick, handled by JS below
    $html .= '<button class="hamburger-menu" id="hamburgerBtn"><i class="fas fa-bars"></i></button>';
    
    // Sidebar overlay (tapping it closes sidebar) — z-index:90 keeps it between hamburger(200) and before page content
    $html .= '<div class="sidebar-overlay" id="sidebarOverlay" style="z-index:90;"></div>';
    
    // Sidebar
    $html .= '<aside class="parent-sidebar" id="sidebar">';
    $html .= '<div class="sidebar-header">';
        $logoUrl = getCachedSchoolLogoUrl();
        $html .= '<img src="' . htmlspecialchars($logoUrl) . '" alt="Logo" style="width:64px;height:64px;border-radius:50%;object-fit:cover;background:white;padding:3px;margin-bottom:10px;" onerror="this.onerror=null;this.src=\'../images/aamusted.jpg\'">';
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
    
    // Academic Calendar
    $active = ($currentPage === 'academic_calendar') ? ' class="active"' : '';
    $html .= '<li><a href="../parent/academic_calendar.php"' . $active . '><i class="fas fa-calendar-alt"></i> Academic Calendar</a></li>';

    // My Profile
    $active = ($currentPage === 'profile') ? ' class="active"' : '';
    $html .= '<li><a href="../parent/profile.php"' . $active . '><i class="fas fa-user-cog"></i> My Profile</a></li>';
    
    // Change Password
    $active = ($currentPage === 'password') ? ' class="active"' : '';
    $html .= '<li><a href="../parent/password-reset.php"' . $active . '><i class="fas fa-key"></i> Change Password</a></li>';
    
    // Logout
    $html .= '<li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>';
    
    $html .= '</ul></aside>';
    
    // Mobile sidebar toggle script — no inline onclick conflict, overlay support, body scroll lock
    $html .= '<script>
    (function() {
        var hamburger = document.getElementById("hamburgerBtn");
        var sidebar = document.getElementById("sidebar");
        var overlay = document.getElementById("sidebarOverlay");
        if (!hamburger || !sidebar) return;
        function openSidebar() {
            sidebar.classList.add("open");
            if (overlay) overlay.classList.add("active");
            document.body.style.overflow = "hidden";
        }
        function closeSidebar() {
            sidebar.classList.remove("open");
            if (overlay) overlay.classList.remove("active");
            document.body.style.overflow = "";
        }
        hamburger.addEventListener("click", function() {
            if (sidebar.classList.contains("open")) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
        if (overlay) overlay.addEventListener("click", closeSidebar);
        var links = sidebar.querySelectorAll("a");
        for (var i = 0; i < links.length; i++) {
            links[i].addEventListener("click", function() {
                if (window.innerWidth <= 768) closeSidebar();
            });
        }
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") closeSidebar();
        });
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
    } else {
        // Validate external redirect against trusted host to prevent host header injection
        $allowedHost = getenv('APP_URL') ? parse_url(getenv('APP_URL'), PHP_URL_HOST) : '';
        if ($allowedHost) {
            $urlHost = parse_url($url, PHP_URL_HOST);
            if ($urlHost && $urlHost !== $allowedHost) {
                error_log("redirect() blocked untrusted host: $urlHost (allowed: $allowedHost)");
                $url = '/'; // fallback to root
            }
        }
    }
    header("Location: $url");
    exit;
}

function sanitize($input) {
    // Handle null input to avoid deprecated trim(null) warning in PHP 8.1+
    if ($input === null || $input === false) {
        return '';
    }
    // Strip tags only — NO htmlspecialchars here.
    // Callers MUST apply htmlspecialchars() at render time for safe HTML output.
    // This avoids double-encoding (the old sanitize() encoded AND callers encoded again).
    return strip_tags(trim($input));
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
    if (!$pdo) {
        error_log("fetchSettings: \$pdo is null");
        return $settings;
    }
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

        // Validate MIME type against whitelist
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $contentType = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        if (!in_array($contentType, $allowedTypes, true)) {
            throw new Exception("File type '$contentType' is not allowed. Accepted: " . implode(', ', $allowedTypes));
        }

        // Reject files larger than 2 MB
        if (filesize($tmpPath) > 2 * 1024 * 1024) {
            throw new Exception("File exceeds maximum size of 2 MB.");
        }

        $fileData = file_get_contents($tmpPath);
        if ($fileData === false) {
            throw new Exception("Cannot read uploaded file from " . $tmpPath);
        }

        $supabase->uploadFile($bucket, $filename, $fileData, $contentType);
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
    if (empty($_SESSION['csrf_tokens'])) {
        // Initialize pool of tokens (keep last 3 valid)
        $_SESSION['csrf_tokens'] = [];
    }
    if (empty($_SESSION['csrf_tokens'])) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'][] = $token;
    }
    // Return the most recently generated token for new forms
    return end($_SESSION['csrf_tokens']);
}

/**
 * Validate a submitted CSRF token against the session token pool using timing-safe comparison.
 * Tokens are single-use but multiple valid tokens are kept (last 3) for multi-tab support.
 * Returns true if valid, false otherwise.
 */
function validate_csrf_token(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_tokens']) || empty($token)) {
        return false;
    }
    $index = array_search($token, $_SESSION['csrf_tokens'], true);
    if ($index === false) {
        return false;
    }
    // Remove the used token
    array_splice($_SESSION['csrf_tokens'], $index, 1);
    // Ensure at least one token remains for subsequent forms
    if (empty($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'][] = bin2hex(random_bytes(32));
    }
    // Prune pool to last 3 tokens
    if (count($_SESSION['csrf_tokens']) > 3) {
        $_SESSION['csrf_tokens'] = array_slice($_SESSION['csrf_tokens'], -3);
    }
    return true;
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

    // Load school name directly instead of relying on $GLOBALS
    $schoolName = 'SchoolName';
    if ($pdo) {
        try {
            $settings = fetchSettings($pdo);
            $schoolName = $settings['school_name'] ?? 'SchoolName';
        } catch (Exception $e) {
            error_log("sendStaffInvite: fetchSettings failed - " . $e->getMessage());
        }
    }

    try {
        $token = generateStaffInviteToken();
        $expiresAt = date('Y-m-d H:i:s', time() + 172800); // 48 hours from now

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
                    $subject = "Staff Registration Invitation - " . $schoolName;
                    $body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <h2 style='color: #1a5276;'>Staff Registration Invitation</h2>
                        <p>Dear <strong>" . htmlspecialchars($staffName) . "</strong>,</p>
                        <p>You have been invited to register for the staff portal of <strong>" . $schoolName . "</strong>.</p>
                        <p>Please click the button below to complete your registration. This link will expire in <strong>48 hours</strong>.</p>
                        <p style='text-align: center; margin: 30px 0;'>
                            <a href='$inviteLink' style='background: #1a5276; color: white; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-size: 16px; display: inline-block;'>Complete Registration</a>
                        </p>
                        <p>Or copy this link into your browser:</p>
                        <p style='background: #f5f5f5; padding: 10px; word-break: break-all; font-size: 13px;'>$inviteLink</p>
                        <p>If you did not expect this invitation, please ignore this email.</p>
                        <hr style='border: none; border-top: 1px solid #eee;'>
                        <p style='color: #999; font-size: 12px;'>This is an automated message from " . $schoolName . ".</p>
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
                $smsText = "Staff Registration: $inviteLink (expires in 48h) - " . $schoolName;
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
                'success' => false,
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

        // Ensure bucket exists (second param is bool $public, not an options array)
        try { $supabase->createBucket($bucket, true); } catch (Exception $e) {}

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

/**
 * Auto-link subjects for a Class Teacher assignment.
 *
 * When a staff member is assigned as Class Teacher for a class, this function:
 * 1. Looks up the class and its category (creche/nursery/kindergarten/primary/jhs)
 * 2. Finds all master subjects belonging to that category
 * 3. Creates per-class subject records with teacher_id and class_id set
 *
 * This ensures the teacher sees subjects in staff_grades.php.
 *
 * @param PDO   $pdo      Database connection
 * @param int   $staff_id  Staff ID
 * @param int   $class_id  Class ID
 * @return int  Number of subjects linked
 */
function linkTeacherClassSubjects($pdo, int $staff_id, int $class_id): int {
    // 1. Get class info
    try {
        $stmt = $pdo->prepare("SELECT name, level_group FROM classes WHERE id = ?");
        $stmt->execute([$class_id]);
        $class = $stmt->fetch();
        if (!$class) {
            error_log("linkTeacherClassSubjects: class #$class_id not found");
            return 0;
        }
    } catch (Exception $e) {
        error_log("linkTeacherClassSubjects: class fetch error: " . $e->getMessage());
        return 0;
    }

    $class_name = $class['name'];

    // 2. Map class name -> category (matches staff_grades.php)
    $class_category_map = [
        'Creche'    => 'creche',
        'Nursery 1' => 'nursery',
        'Nursery 2' => 'nursery',
        'KG 1'      => 'kindergarten',
        'KG 2'      => 'kindergarten',
        'Basic 1'   => 'primary',
        'Basic 2'   => 'primary',
        'Basic 3'   => 'primary',
        'Basic 4'   => 'primary',
        'Basic 5'   => 'primary',
        'Basic 6'   => 'primary',
        'JHS 1'     => 'jhs',
        'JHS 2'     => 'jhs',
        'JHS 3'     => 'jhs',
    ];
    $category = $class_category_map[$class_name] ?? null;
    if (!$category) {
        error_log("linkTeacherClassSubjects: no category mapping for class '$class_name'");
        return 0;
    }

    // 3. Get master subject IDs for this category from system_settings
    $subject_ids = [];
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'subject_categories'");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row && !empty($row['setting_value'])) {
            $mapping = json_decode($row['setting_value'], true);
            if (is_array($mapping) && isset($mapping[$category])) {
                $subject_ids = array_map('intval', $mapping[$category]);
            }
        }
    } catch (Exception $e) {
        error_log("linkTeacherClassSubjects: mapping fetch error: " . $e->getMessage());
    }

    // Fallback: use default_subjects from admin_subjects.php strategy
    if (empty($subject_ids)) {
        error_log("linkTeacherClassSubjects: no subject_categories mapping found for '$category', trying fallback by code");
        $default_codes = [
            'creche'       => ['ESS','RCN','HHN','SSA','PMD','CDE','LCS','SED'],
            'nursery'      => ['LAN','NUM','CRE','ENV','OWP','MMD'],
            'kindergarten' => ['KLAN','KNUM','KCRE','KENV','KOWO','KMMD'],
            'primary'      => ['ENG','MATH','SCI','GL','HOG','RME','CA','ICT','FRE','PE'],
            'jhs'          => ['ENG','MATH','SCI','SST','RME','GL','CAD','CT','COMP','FRE','PE'],
        ];
        $codes = $default_codes[$category] ?? [];
        if (!empty($codes)) {
            $placeholders = implode(',', array_fill(0, count($codes), '?'));
            try {
                $stmt = $pdo->prepare("SELECT id FROM subjects WHERE code IN ($placeholders) AND class_id IS NULL AND teacher_id IS NULL");
                $stmt->execute($codes);
                foreach ($stmt->fetchAll() as $r) {
                    $subject_ids[] = (int)$r['id'];
                }
            } catch (Exception $e) {
                error_log("linkTeacherClassSubjects: fallback query error: " . $e->getMessage());
            }
        }
    }

    if (empty($subject_ids)) {
        error_log("linkTeacherClassSubjects: no subjects found for category '$category'");
        return 0;
    }

    // 4. For each master subject, create/update a per-teacher-class record
    $count = 0;
    foreach ($subject_ids as $master_id) {
        try {
            // Check if this teacher already has a subject record for this class
            $check = $pdo->prepare("SELECT id FROM subjects WHERE teacher_id = ? AND class_id = ? AND code = (SELECT code FROM subjects WHERE id = ?)");
            $check->execute([$staff_id, $class_id, $master_id]);
            if ($check->fetch()) {
                continue; // Already linked
            }

            // Get master subject data
            $orig = $pdo->prepare("SELECT name, code FROM subjects WHERE id = ?");
            $orig->execute([$master_id]);
            $origData = $orig->fetch();
            if (!$origData) {
                continue;
            }

            // INSERT a new subject row for this teacher+class combination
            $ins = $pdo->prepare("INSERT INTO subjects (name, code, teacher_id, class_id) VALUES (?, ?, ?, ?)");
            $ins->execute([$origData['name'], $origData['code'], $staff_id, $class_id]);
            $count++;
        } catch (Exception $e) {
            error_log("linkTeacherClassSubjects: error linking subject #$master_id: " . $e->getMessage());
        }
    }

    error_log("linkTeacherClassSubjects: linked $count subjects for staff #$staff_id, class #$class_id ($class_name, category=$category)");
    return $count;
}

/**
 * Create or find a parent user account and link it to a student via parent_students.
 *
 * Uses the student's guardian_email to look up or create a parent user (role='parent'),
 * then inserts a row in parent_students to link them. Does NOT change students.user_id
 * (which remains the student's own login account).
 *
 * @param PDO    $pdo         Database connection
 * @param array  $student     Student record (must contain 'id', 'guardian_email', and optionally 'guardian_name', 'guardian_relationship', 'guardian_phone_primary')
 * @return int|null           Parent user ID, or null if no guardian_email or on failure
 */
function linkParentToStudent($pdo, array $student): ?int {
    $guardian_email = trim($student['guardian_email'] ?? '');
    if (empty($guardian_email)) {
        return null;
    }

    $student_id = (int)($student['id'] ?? 0);
    if ($student_id <= 0) {
        error_log("linkParentToStudent: invalid student_id");
        return null;
    }

    try {
        // 1. Look for existing user with this email (any role — could be a staff member)
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ?");
        $stmt->execute([$guardian_email]);
        $existing = $stmt->fetch();

        if ($existing) {
            $parent_user_id = (int)$existing['id'];
            // If this is a staff/teacher account, they already have login credentials
            // — no need to send a separate parent-portal welcome email
        } else {
            // 2. Create new parent user account
            $auto_password = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);
            $password_hash = password_hash($auto_password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (email, password, role, status) VALUES (?, ?, 'parent', 'active')");
            $stmt->execute([$guardian_email, $password_hash]);
            $parent_user_id = (int)$pdo->lastInsertId();

            // Send welcome email with credentials
            $guardian_name = $student['guardian_name'] ?? '';
            try {
                $appUrl = getAppUrl();
                $school_name = 'Nex CEC';
                // Try to get school name from settings
                $s = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'school_name'")->fetch();
                if ($s && !empty($s['setting_value'])) {
                    $school_name = $s['setting_value'];
                }

                $subject = "Parent Portal Access — $school_name";
                $html = "
                <!DOCTYPE html>
                <html>
                <head><meta charset='UTF-8'></head>
                <body style='font-family:Arial,sans-serif;background:#f4f4f4;padding:20px;'>
                    <div style='max-width:600px;margin:0 auto;background:white;border-radius:8px;overflow:hidden;'>
                        <div style='background:linear-gradient(to right,#1a5276,#2e86c1);color:white;text-align:center;padding:40px 20px;'>
                            <h1 style='margin:0;font-size:24px;'>Welcome to $school_name</h1>
                            <p style='margin-top:8px;opacity:0.9;'>Parent Portal Access</p>
                        </div>
                        <div style='padding:30px;color:#333;font-size:14px;'>
                            <p>Dear " . htmlspecialchars($guardian_name ?: 'Parent/Guardian', ENT_QUOTES, 'UTF-8') . ",</p>
                            <p>A parent portal account has been created for you.</p>
                            <div style='background:#f0f7ff;border:1px solid #b8d9e8;border-radius:6px;padding:20px;margin:20px 0;'>
                                <div style='font-size:12px;color:#666;'>Email</div>
                                <div style='font-size:18px;font-weight:bold;color:#1a5276;'>" . htmlspecialchars($guardian_email, ENT_QUOTES, 'UTF-8') . "</div>
                                <div style='font-size:12px;color:#666;margin-top:12px;'>Temporary Password</div>
                                <div style='font-size:18px;font-weight:bold;color:#1a5276;'>" . htmlspecialchars($auto_password, ENT_QUOTES, 'UTF-8') . "</div>
                            </div>
                            <p style='text-align:center;'>
                                <a href='" . htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8') . "/login.php' style='display:inline-block;background:#27ae60;color:white;padding:12px 25px;text-decoration:none;border-radius:6px;font-weight:bold;'>Login to Parent Portal</a>
                            </p>
                            <p style='font-size:12px;color:#999;'>Please log in and change your password immediately.</p>
                        </div>
                        <div style='text-align:center;padding:30px;font-size:12px;color:#666;border-top:1px solid #eee;'>
                            $school_name &bull; Parent Portal
                        </div>
                    </div>
                </body>
                </html>";

                $mailer = new Mailer();
                $mailer->sendHTML($guardian_email, $subject, $html);
            } catch (Exception $e) {
                error_log("linkParentToStudent: welcome email failed for $guardian_email: " . $e->getMessage());
                // Non-fatal — account was created
            }
        }

        // 3. Insert parent_students link if not already exists
        $stmt = $pdo->prepare("SELECT id FROM parent_students WHERE parent_user_id = ? AND student_id = ?");
        $stmt->execute([$parent_user_id, $student_id]);
        if (!$stmt->fetch()) {
            $relationship = $student['guardian_relationship'] ?? 'Guardian';
            $stmt = $pdo->prepare("INSERT INTO parent_students (parent_user_id, student_id, relationship, is_primary) VALUES (?, ?, ?, ?)");
            $stmt->execute([$parent_user_id, $student_id, $relationship, true]);
        }

        return $parent_user_id;
    } catch (Exception $e) {
        error_log("linkParentToStudent: error for student #$student_id: " . $e->getMessage());
        return null;
    }
}

/**
 * Auto-detect students whose guardian matches a staff member, and link them via parent_students.
 *
 * Called after adding or editing a staff member. Searches for students whose guardian_name,
 * guardian_phone_primary, or guardian_email matches the staff member's details, then creates
 * parent_students links using the staff member's user_id (enabling dual-role parent access).
 *
 * @param PDO    $pdo         Database connection
 * @param array  $staff       Staff record (must contain 'id', 'full_name', 'phone', 'email', 'user_id')
 * @return int   Number of students linked
 */
function autoLinkStaffChildren($pdo, array $staff): int {
    $staff_id   = (int)($staff['id'] ?? 0);
    $full_name  = trim($staff['full_name'] ?? '');
    $phone      = trim($staff['phone'] ?? '');
    $email      = trim($staff['email'] ?? '');
    $user_id    = (int)($staff['user_id'] ?? 0);

    if ($staff_id <= 0 || $user_id <= 0) {
        error_log("autoLinkStaffChildren: invalid staff record (id=$staff_id, user_id=$user_id)");
        return 0;
    }

    $linked = 0;
    try {
        // Build WHERE conditions matching the billing-file detection logic
        $conditions = [];
        $params = [];
        if (!empty($full_name)) {
            $conditions[] = "guardian_name = ?";
            $params[] = $full_name;
        }
        if (!empty($phone)) {
            $conditions[] = "guardian_phone_primary = ?";
            $params[] = $phone;
        }
        if (!empty($email)) {
            $conditions[] = "guardian_email = ?";
            $params[] = $email;
        }

        if (empty($conditions)) {
            return 0;
        }

        $sql = "SELECT id, guardian_name, guardian_email, guardian_relationship FROM students WHERE " . implode(' OR ', $conditions);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        while ($student = $stmt->fetch()) {
            $student_id = (int)$student['id'];
            // Check if already linked
            $check = $pdo->prepare("SELECT id FROM parent_students WHERE parent_user_id = ? AND student_id = ?");
            $check->execute([$user_id, $student_id]);
            if ($check->fetch()) {
                continue; // Already linked
            }

            $relationship = $student['guardian_relationship'] ?? 'Guardian';
            $ins = $pdo->prepare("INSERT INTO parent_students (parent_user_id, student_id, relationship, is_primary) VALUES (?, ?, ?, ?)");
            $ins->execute([$user_id, $student_id, $relationship, true]);
            $linked++;

            error_log("autoLinkStaffChildren: linked staff #$staff_id (user #$user_id) to student #$student_id");
        }
    } catch (Exception $e) {
        error_log("autoLinkStaffChildren: error for staff #$staff_id: " . $e->getMessage());
    }

    return $linked;
}
