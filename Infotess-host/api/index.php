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

$targetPath = realpath(__DIR__ . '/../' . $file);
if ($targetPath === false) {
    // File not found — debug output
    $baseDir = __DIR__ . '/../';
    http_response_code(404);
    echo "<h1>404 Not Found</h1>";
    echo "<p><strong>Requested URI:</strong> {$_SERVER['REQUEST_URI']}</p>";
    echo "<p><strong>Parsed file:</strong> $file</p>";
    echo "<p><strong>Attempted path:</strong> {$baseDir}{$file}</p>";
    echo "<p><strong>__DIR__:</strong> " . __DIR__ . "</p>";
    echo "<p><strong>SCRIPT_NAME:</strong> {$_SERVER['SCRIPT_NAME']}</p>";
    echo "<p><strong>PHP_SAPI:</strong> " . PHP_SAPI . "</p>";
    echo "<h3>Files in base directory:</h3><pre>";
    echo print_r(scandir($baseDir), true);
    echo "</pre>";
    echo "<h3>Files in admin directory (if exists):</h3><pre>";
    if (is_dir($baseDir . 'admin')) {
        echo print_r(scandir($baseDir . 'admin'), true);
    } else {
        echo "admin/ directory not found at: {$baseDir}admin";
    }
    echo "</pre>";
    exit;
}

// Serve PHP files
if (pathinfo($targetPath, PATHINFO_EXTENSION) === 'php') {
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

// Serve static files (should be handled by vercel.json, but fallback just in case)
if (file_exists($targetPath)) {
    $mime = mime_content_type($targetPath);
    header("Content-Type: $mime");
    readfile($targetPath);
    exit;
}

// Fallback: 404
http_response_code(404);
echo "404 Not Found - File: $file - Path: $targetPath";
