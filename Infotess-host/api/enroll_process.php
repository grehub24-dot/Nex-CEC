<?php
// DEPRECATED: This file is kept for backward compatibility.
// The new enrollment flow uses api/register.php (self-contained form + processing).
// This file now logs errors and redirects to the new registration form.

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

// Log the fact that someone hit this old endpoint
error_log("DEPRECATED enroll_process.php called. Referer: " . ($_SERVER['HTTP_REFERER'] ?? 'unknown') . " | POST fields: " . implode(', ', array_keys($_POST)));

// Save the POST data to session so register.php can use it (best-effort)
$_SESSION['old_enrollment_data'] = $_POST;

header('Location: register.php');
exit;
