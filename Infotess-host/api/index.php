<?php
// api/index.php — Central Router
// ALL requests go through here. Files are in the same api/ directory.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load Environment Variables
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

define('BASE_PATH', '');

// Pre-load ALL dependencies
require_once __DIR__ . '/lib/Supabase.php';
require_once __DIR__ . '/includes/functions.php';

// Initialize Supabase globally
global $supabase;
try {
    $supabase = new SupabaseClient();
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage();
    exit;
}

// Initialize $pdo globally (for legacy code compatibility)
require_once __DIR__ . '/includes/db.php';

// Route resolution
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');

// Map clean URLs to actual files
$routes = [
    '' => 'home.php',
    'index.php' => 'home.php',
];

// Public pages (accessible without authentication)
$publicPages = ['register', 'enrollment_confirm', 'enrollment_print', 'enrollment_bill', 'enrollment_blank_form'];
foreach ($publicPages as $page) {
    $routes["$page.php"] = "$page.php";
}

// Public informational pages
$publicInfoPages = ['about', 'contact', 'gallery', 'news', 'events', 'activities', 'alumni', 'resources', 'department', 'executives', 'fees', 'membership', 'projects'];
foreach ($publicInfoPages as $page) {
    $routes["$page.php"] = "$page.php";
}

// Auth pages
$routes['login.php'] = 'login.php';
$routes['logout.php'] = 'logout.php';
$routes['forgot-password.php'] = 'forgot-password.php';

// Route selector for dual-role users
$routes['route_selector.php'] = 'route_selector.php';

// Admin routes
$adminPages = ['dashboard','students','staff','edit_staff','payments','fees','fees_debt','payroll','pay_slip','salary','grades','attendance','staff_attendance','reports','settings','users','edit_student','inbox','messaging','module_settings','subjects','verify','bulk_import','enrollments','role_permissions'];
foreach ($adminPages as $page) {
    $routes["admin/$page.php"] = "admin_$page.php";
}

// Student routes
$studentPages = ['dashboard','history','messages','password-reset','profile','fees','report_card'];
foreach ($studentPages as $page) {
    $routes["student/$page.php"] = "student_$page.php";
}

// Direct AJAX / utility routes
$routes['mark_message_read.php'] = 'mark_message_read.php';
$routes['report_card_pdf.php'] = 'report_card_pdf.php';
$routes['ajax_get_subjects_by_class.php'] = 'ajax_get_subjects_by_class.php';
$routes['admin/view_receipt.php'] = 'view_receipt.php';

// Staff routes
$staffPages = ['dashboard', 'payslip', 'attendance', 'grades', 'fees_debt', 'profile', 'messaging'];
foreach ($staffPages as $page) {
    $routes["staff/$page.php"] = "staff_$page.php";
}
$routes["staff/student_attendance.php"] = "admin_attendance.php";

// Staff self-registration (via invite token, no auth required)
$routes["staff/register.php"] = "staff_register.php";

// Staff login (same as root login — route alias avoids 404 when accessed from /staff/ subdirectory)
$routes["staff/login.php"] = "login.php";

// Parent routes
$parentPages = ['dashboard', 'student', 'fees', 'report_card', 'messages', 'profile', 'password-reset'];
foreach ($parentPages as $page) {
    $routes["parent/$page.php"] = "parent_$page.php";
}

$file = $routes[$uri] ?? $uri;

// Prevent directory traversal
$file = str_replace(['../', '..\\'], '', $file);

$targetPath = realpath(__DIR__ . '/' . $file);

// Fallback: check parent directory for static assets (receipts, images, css, js)
if (!$targetPath) {
    $parentPath = realpath(__DIR__ . '/../' . $file);
    if ($parentPath) {
        $targetPath = $parentPath;
    }
}

if ($targetPath && pathinfo($targetPath, PATHINFO_EXTENSION) === 'php') {
    require $targetPath;
    exit;
}

if ($targetPath) {
    $mime = mime_content_type($targetPath);
    header("Content-Type: $mime");
    readfile($targetPath);
    exit;
}

http_response_code(404);
echo "<h1>404 Not Found</h1><p>File not found: $file</p>";
