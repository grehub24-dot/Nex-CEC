<?php
// api/index.php — Homepage only
// All other PHP files are handled by file-based routing (api/**/*.php functions)

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

// Load Supabase client
require_once __DIR__ . '/lib/Supabase.php';
require_once __DIR__ . '/includes/functions.php';

global $supabase;
try {
    $supabase = new SupabaseClient();
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage();
    exit;
}

// Include the homepage content
require_once __DIR__ . '/home.php';
