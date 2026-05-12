<?php
// Load functions.php directly to ensure session handler is registered
require_once __DIR__ . '/includes/functions.php';
session_destroy();
redirect('login.php');
