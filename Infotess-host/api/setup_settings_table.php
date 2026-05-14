<?php
require_once 'includes/db.php';

try {
    // Create system_settings table
    $sql = "CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "Table system_settings created successfully.<br>";

    // Insert default settings if they don't exist
    $defaults = [
        'current_academic_year' => '2025/2026',
        'current_term' => '1',
        'annual_dues_amount' => '100.00',
        'payment_modes' => 'Cash,Mobile Money,Bank Transfer',
        'school_section' => 'Primary',
        'institution_name' => 'Nex CEC Basic School'
    ];

    // Bridge doesn't support ON CONFLICT — check existence before insert
    $checkStmt = $pdo->prepare("SELECT setting_key FROM system_settings WHERE setting_key = ?");
    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaults as $key => $value) {
        $checkStmt->execute([$key]);
        if (!$checkStmt->fetch()) {
            $stmt->execute([$key, $value]);
        }
    }
    echo "Default settings initialized successfully.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
