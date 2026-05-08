<?php
// includes/functions.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restore getBasePath() which was missing
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
        'grades' => ['admin', 'super_admin'],
        'attendance' => ['admin', 'super_admin'],
        'staff_attendance' => ['admin', 'super_admin'],
        'settings' => ['admin', 'super_admin'],
        'module_settings' => ['admin', 'super_admin'],
        'users' => ['admin', 'super_admin'],
        'bulk_import' => ['admin', 'super_admin'],
        
        // Bursar + Admin
        'dashboard' => ['admin', 'super_admin', 'bursar'],
        'students' => ['admin', 'super_admin', 'bursar'],
        'edit_student' => ['admin', 'super_admin', 'bursar'],
        'enrollments' => ['admin', 'super_admin'],
        'payments' => ['admin', 'super_admin', 'bursar'],
        'fees' => ['admin', 'super_admin', 'bursar'],
        'reports' => ['admin', 'super_admin', 'bursar'],
        'verify' => ['admin', 'super_admin', 'bursar'],
        'messaging' => ['admin', 'super_admin', 'bursar'],
        'inbox' => ['admin', 'super_admin', 'bursar'],
    ];
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
    
    $menu = [
        ['href' => 'dashboard.php', 'icon' => 'fas fa-home', 'label' => 'Dashboard'],
        ['href' => 'students.php', 'icon' => 'fas fa-user-graduate', 'label' => 'Students'],
    ];
    
    if ($isFullAdmin) {
        $menu[] = ['href' => 'enrollments.php', 'icon' => 'fas fa-file-signature', 'label' => 'Enrollments'];
        $menu[] = ['href' => 'staff.php', 'icon' => 'fas fa-chalkboard-teacher', 'label' => 'Staff'];
    }
    
    $menu[] = ['href' => 'payments.php', 'icon' => 'fas fa-money-bill-wave', 'label' => 'Payments'];
    $menu[] = ['href' => 'fees.php', 'icon' => 'fas fa-list-alt', 'label' => 'Fee Structure'];
    
    if ($isFullAdmin) {
        $menu[] = ['href' => 'payroll.php', 'icon' => 'fas fa-file-invoice-dollar', 'label' => 'Payroll'];
        $menu[] = ['href' => 'salary.php', 'icon' => 'fas fa-money-check-alt', 'label' => 'Salary Structures'];
        $menu[] = ['href' => 'grades.php', 'icon' => 'fas fa-clipboard-list', 'label' => 'SBA / Grades'];
        $menu[] = ['href' => 'attendance.php', 'icon' => 'fas fa-user-check', 'label' => 'Student Attendance'];
        $menu[] = ['href' => 'staff_attendance.php', 'icon' => 'fas fa-user-tie', 'label' => 'Staff Attendance'];
    }
    
    $menu[] = ['href' => 'reports.php', 'icon' => 'fas fa-chart-bar', 'label' => 'Reports'];
    $menu[] = ['href' => 'verify.php', 'icon' => 'fas fa-qrcode', 'label' => 'Verify Receipt'];
    
    if ($isFullAdmin) {
        $menu[] = ['href' => 'users.php', 'icon' => 'fas fa-users-cog', 'label' => 'User Management'];
    }
    
    $menu[] = ['href' => 'messaging.php', 'icon' => 'fas fa-envelope', 'label' => 'Messaging'];
    $menu[] = ['href' => 'inbox.php', 'icon' => 'fas fa-inbox', 'label' => 'Inbox'];
    
    if ($isFullAdmin) {
        $menu[] = ['href' => 'module_settings.php', 'icon' => 'fas fa-cogs', 'label' => 'Module Settings'];
        $menu[] = ['href' => 'settings.php', 'icon' => 'fas fa-tools', 'label' => 'System Settings'];
    }
    
    $menu[] = ['href' => '../logout.php', 'icon' => 'fas fa-sign-out-alt', 'label' => 'Logout'];
    
    return $menu;
}

/**
 * Render sidebar menu HTML.
 */
function renderSidebar($currentPage = '', $schoolName = 'Nex CEC') {
    $menu = getSidebarMenu($currentPage);
    $role = $_SESSION['role'] ?? 'admin';
    $roleLabel = ucfirst($role);
    
    $html = '<aside class="sidebar">';
    $html .= '<div class="sidebar-header" style="text-align: center; padding: 20px 10px;">';
    $html .= '<img src="../images/school-logo.png" alt="Logo" style="width: 80px; height: 80px; margin-bottom: 10px; border-radius: 50%; background: #fff; padding: 5px;" onerror="this.src=\'../images/aamusted.jpg\'">';
    $html .= '<h3>' . htmlspecialchars($schoolName) . ' ' . $roleLabel . '</h3>';
    $html .= '</div>';
    $html .= '<ul class="sidebar-menu">';
    
    foreach ($menu as $item) {
        $active = ($currentPage === basename($item['href'], '.php')) ? ' class="active"' : '';
        $html .= '<li><a href="' . htmlspecialchars($item['href']) . '"' . $active . '><i class="' . htmlspecialchars($item['icon']) . '"></i> ' . htmlspecialchars($item['label']) . '</a></li>';
    }
    
    $html .= '</ul></aside>';
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
