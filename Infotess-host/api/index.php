<?php
// api/index.php
// Central Router for Vercel PHP

// 1. Enable Error Reporting for Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Load Environment Variables (if any)
// Vercel injects these automatically, but this is good for local dev
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

// 3. Set Base Path for Assets
// When deployed via root vercel.json, all routes are at the root level
define('BASE_PATH', '');

// 4. Load Core Library
require_once __DIR__ . '/../lib/Supabase.php';
require_once __DIR__ . '/../includes/functions.php';

// 5. Routing Logic
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');

// Map URI to file
if (empty($uri)) {
    $file = 'index.php';
} else {
    $file = $uri;
}

// Prevent directory traversal
$file = str_replace(['../', '..\\'], '', $file);

$targetPath = __DIR__ . '/../' . $file;

// Serve PHP files
if (pathinfo($targetPath, PATHINFO_EXTENSION) === 'php' && file_exists($targetPath)) {
    // Inject Supabase Client into Global Scope
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

// Fallback: 404
http_response_code(404);
echo "404 Not Found - File: $file";
