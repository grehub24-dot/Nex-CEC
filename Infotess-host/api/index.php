<?php
// api/index.php — Central Router
// ALL requests go through here. Files are in the same api/ directory.

// Production mode: errors logged, never displayed
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

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

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Build CSP — allow Supabase Storage origin for uploaded images (logos, profile pics)
$supabaseUrl = trim(getenv('SUPABASE_URL'));
$supabaseImgSrc = '';
if (!empty($supabaseUrl)) {
    $parsed = parse_url($supabaseUrl);
    $origin = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
    $supabaseImgSrc = ' ' . $origin;
}
header("Content-Security-Policy: default-src 'self'; "
    . "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
    . "font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.gstatic.com; "
    . "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://unpkg.com; "
    . "img-src 'self' data:$supabaseImgSrc; "
    . "connect-src 'self'; "
    . "frame-ancestors 'self'; "
    . "base-uri 'self'");

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
$publicInfoPages = ['about', 'contact', 'gallery', 'news', 'events', 'activities', 'alumni', 'resources', 'department', 'executives', 'fees', 'membership', 'projects', 'academic_calendar'];
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
$adminPages = ['dashboard','students','staff','edit_staff','payments','fees','fees_debt','student_billing','class_billing','payroll','pay_slip','salary','grades','attendance','staff_attendance','reports','settings','users','edit_student','inbox','messaging','module_settings','subjects','link_subjects','verify','bulk_import','enrollments','role_permissions','academic_calendar','migrate_parent_students','resources','lesson_notes'];
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

// Resource viewer (masked external URL viewer)
$routes['resource.php'] = 'resource_viewer.php';
$routes['resource_redirect.php'] = 'resource_redirect.php';

// Lesson note viewer (shared across all roles)
$routes['lesson_note_view.php'] = 'lesson_note_view.php';

// AJAX endpoints
$routes['ajax_get_lesson_note.php'] = 'ajax_get_lesson_note.php';

// Staff routes
$staffPages = ['dashboard', 'payslip', 'attendance', 'grades', 'fees_debt', 'profile', 'messaging', 'academic_calendar', 'resources', 'resource_assignments', 'lesson_notes'];
foreach ($staffPages as $page) {
    $routes["staff/$page.php"] = "staff_$page.php";
}
$routes["staff/student_attendance.php"] = "admin_attendance.php";

// Staff self-registration (via invite token, no auth required)
$routes["staff/register.php"] = "staff_register.php";

// Staff login (same as root login — route alias avoids 404 when accessed from /staff/ subdirectory)
$routes["staff/login.php"] = "login.php";

// Parent routes
$parentPages = ['dashboard', 'student', 'fees', 'report_card', 'messages', 'profile', 'password-reset', 'academic_calendar', 'resources', 'lesson_notes'];
foreach ($parentPages as $page) {
    $routes["parent/$page.php"] = "parent_$page.php";
}

$file = $routes[$uri] ?? $uri;

// Prevent directory traversal (iterative removal to defeat ....// bypass)
do { $clean = $file; $file = str_replace(['../', '..\\'], '', $file); } while ($clean !== $file);

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

    // Add Cache-Control for static assets (1 hour for HTML/PDF, 7 days for images/css/js)
    $ext = pathinfo($targetPath, PATHINFO_EXTENSION);
    $cacheMaxAge = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'css', 'js', 'ico', 'woff2', 'woff', 'ttf']) ? 604800 : 3600;
    header("Cache-Control: public, max-age=$cacheMaxAge, immutable");

    readfile($targetPath);
    exit;
}

http_response_code(404);
echo "<h1>404 Not Found</h1><p>File not found: $file</p>";
