<?php
require_once 'includes/db.php';

echo "<h2>Database Migration Fix</h2><pre>";

try {
    // 1. Create message_reads table (the critical missing table)
    $check = $pdo->query("SHOW TABLES LIKE 'message_reads'");
    if ($check->rowCount() == 0) {
        $pdo->exec("CREATE TABLE message_reads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            message_id INT NOT NULL,
            user_id INT NOT NULL,
            read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_read (message_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "✓ Created table: message_reads\n";
    } else {
        echo "✓ Table already exists: message_reads\n";
    }

    // 2. Add is_password_reset to users table
    $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_password_reset'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_password_reset TINYINT(1) DEFAULT 0");
        echo "✓ Added column: users.is_password_reset\n";
    } else {
        echo "✓ Column already exists: users.is_password_reset\n";
    }

    // 3. Add class_name to students table
    $check = $pdo->query("SHOW COLUMNS FROM students LIKE 'class_name'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE students ADD COLUMN class_name VARCHAR(50) DEFAULT NULL");
        echo "✓ Added column: students.class_name\n";
    } else {
        echo "✓ Column already exists: students.class_name\n";
    }

    // 4. Add stream to students table
    $check = $pdo->query("SHOW COLUMNS FROM students LIKE 'stream'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE students ADD COLUMN stream VARCHAR(50) DEFAULT NULL");
        echo "✓ Added column: students.stream\n";
    } else {
        echo "✓ Column already exists: students.stream\n";
    }

    // 5. Add profile_picture to students table
    $check = $pdo->query("SHOW COLUMNS FROM students LIKE 'profile_picture'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE students ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL");
        echo "✓ Added column: students.profile_picture\n";
    } else {
        echo "✓ Column already exists: students.profile_picture\n";
    }

    // 6. Add created_at to messages table if missing
    $check = $pdo->query("SHOW COLUMNS FROM messages LIKE 'created_at'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "✓ Added column: messages.created_at\n";
    } else {
        echo "✓ Column already exists: messages.created_at\n";
    }

    // 7. Add title to messages table if missing
    $check = $pdo->query("SHOW COLUMNS FROM messages LIKE 'title'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN title VARCHAR(255) DEFAULT NULL");
        echo "✓ Added column: messages.title\n";
    } else {
        echo "✓ Column already exists: messages.title\n";
    }

    echo "\n=== All migrations completed successfully! ===</pre>";
    echo "<p><a href='login.php'>Go to Login</a></p>";

} catch (PDOException $e) {
    echo "\n❌ Error: " . $e->getMessage() . "</pre>";
}
?>
