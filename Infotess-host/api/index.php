<?php
// api/index.php
// Central Router for Vercel PHP

// 1. Load Environment Variables
if (file_exists(__DIR__ . '/../.env')) {
    // Simple env parser for production if needed, 
    // but Vercel injects env vars automatically.
}

// 2. Set Base Path for Assets
$scriptName = $_SERVER['SCRIPT_NAME'];
$basePath = dirname($scriptName);
if ($basePath === '/' || $basePath === '\\') {
    $basePath = '';
}
define('BASE_PATH', $basePath);

// 3. Load Core Library
require_once __DIR__ . '/../lib/Supabase.php';
require_once __DIR__ . '/../includes/functions.php';

// 4. Routing Logic
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove base path from URI
if ($basePath !== '' && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}
$uri = trim($uri, '/');

// Map URI to file
// If URI is 'login.php', we look for 'login.php' in the root
// If URI is 'admin/dashboard.php', we look for 'admin/dashboard.php'
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
    $supabase = new SupabaseClient();
    
    require $targetPath;
    exit;
}

// Fallback: 404
http_response_code(404);
echo "404 Not Found";
