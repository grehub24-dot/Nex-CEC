<?php
require_once 'includes/db.php';

// Create a default admin if none exists
// Bridge doesn't support COUNT(*) or literal WHERE — fetch all users, filter in PHP
$allUsersForSetup = $pdo->query("SELECT * FROM users");
$allUsersForSetup = $allUsersForSetup ? $allUsersForSetup->fetchAll() : [];
$adminExists = count(array_filter($allUsersForSetup, fn($u) => ($u['role'] ?? '') === 'admin')) > 0;
if (!$adminExists) {
    $email = 'admin@infotess.org';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
    $stmt->execute([$email, $password, 'admin']);
    echo "Default Admin created. Email: $email, Pass: admin123";
} else {
    echo "Admin already exists.";
}
?>
