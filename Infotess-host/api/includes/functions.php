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

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
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
