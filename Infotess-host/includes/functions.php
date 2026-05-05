<?php
// includes/functions.php
// Global helper functions moved from db.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    // Check for Session (Legacy) OR Supabase JWT (New)
    if (isset($_SESSION['user_id'])) return true;
    
    // Future: Check cookie 'sb-token'
    if (isset($_COOKIE['sb-access-token'])) return true;
    
    return false;
}

function isAdmin() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin', 'bursar']);
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

function redirect($url) {
    // Handle relative vs absolute paths in Vercel
    if (strpos($url, 'http') !== 0) {
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';
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
