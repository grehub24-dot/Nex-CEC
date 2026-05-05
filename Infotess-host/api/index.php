<?php
// api/index.php
// Central Router for Vercel PHP (fallback for non-file routes)

// 1. Enable Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Load Environment Variables
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

// 3. Set Base Path for Assets
define('BASE_PATH', '');

// 4. Load Core Library
require_once __DIR__ . '/../lib/Supabase.php';
require_once __DIR__ . '/../includes/functions.php';

// 5. Routing Logic
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');

if (empty($uri)) {
    $file = 'index.php';
} else {
    $file = $uri;
}

// Prevent directory traversal
$file = str_replace(['../', '..\\'], '', $file);

$targetPath = realpath(__DIR__ . '/../' . $file);

if ($targetPath && pathinfo($targetPath, PATHINFO_EXTENSION) === 'php') {
    global $supabase;
    try {
        $supabase = new SupabaseClient();
    } catch (Exception $e) {
        echo "Database Init Error: " . $e->getMessage();
        exit;
    }
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
