<?php
// api/index.php
// Central Router for Vercel PHP — dispatches to Infotess-host/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Load Environment Variables
if (file_exists(__DIR__ . '/../Infotess-host/.env')) {
    $lines = file(__DIR__ . '/../Infotess-host/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

// 2. Define base path
define('BASE_PATH', '');

// 3. Load Supabase client FIRST (so $supabase global exists for all included files)
require_once __DIR__ . '/../Infotess-host/lib/Supabase.php';

global $supabase;
try {
    $supabase = new SupabaseClient();
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage();
    exit;
}

// 4. Load helper functions
require_once __DIR__ . '/../Infotess-host/includes/functions.php';

// 5. Resolve URI to file
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');

if (empty($uri)) {
    $file = 'index.php';
} else {
    $file = $uri;
}

// Prevent directory traversal
$file = str_replace(['../', '..\\'], '', $file);

$targetPath = __DIR__ . '/../Infotess-host/' . $file;

if (pathinfo($targetPath, PATHINFO_EXTENSION) === 'php' && file_exists($targetPath)) {
    require $targetPath;
    exit;
}

// Fallback: 404
http_response_code(404);
echo "<h1>404 Not Found</h1><p>Requested: $file</p><p>Path: $targetPath</p>";
