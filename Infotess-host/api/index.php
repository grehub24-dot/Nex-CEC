<?php
// api/index.php
// Central Router — dispatches to PHP files within api/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Load Environment Variables
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

// 2. Define base path (root-level deployment)
define('BASE_PATH', '');

// 3. Load Supabase client FIRST (before any included files need it)
require_once __DIR__ . '/lib/Supabase.php';

global $supabase;
try {
    $supabase = new SupabaseClient();
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage();
    exit;
}

// 4. Load helper functions
require_once __DIR__ . '/includes/functions.php';

// 5. Resolve URI to file within api/
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');

if (empty($uri)) {
    // Homepage — use the dedicated home page file, not this router
    $file = 'home.php';
} else {
    $file = $uri;
}

// Prevent directory traversal
$file = str_replace(['../', '..\\'], '', $file);

// Prevent infinite recursion (router requiring itself)
if ($file === 'index.php' || $file === 'api/index.php') {
    $file = 'home.php';
}

$targetPath = realpath(__DIR__ . '/' . $file);

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
echo "<h1>404 Not Found</h1><p>Requested: $file</p>";
