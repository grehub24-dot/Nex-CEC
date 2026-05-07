<?php
require_once 'includes/db.php';
require_once 'includes/Mailer.php';

// Enforce access control
requireAccess('bulk_import');

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

// Handle CSV Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_students') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        if ($handle) {
            // Skip header row
            $header = fgetcsv($handle);
            
            $imported = 0;
            $skipped = 0;
            $errors = [];
            
            while (($row = fgetcsv($handle)) !== false) {
                // Expected columns: full_name, index_number, class_name, gender, date_of_birth,
                // guardian_name, guardian_email, guardian_relationship, guardian_phone_primary,
                // guardian_phone_emergency, health_insurance_id, previous_school, address
                if (count($row) < 4) {
                    $skipped++;
                    continue;
                }
                
                $full_name = trim($row[0] ?? '');
                $index_number = trim($row[1] ?? '');
                $class_name = trim($row[2] ?? '');
                $gender = trim($row[3] ?? '');
                $date_of_birth = trim($row[4] ?? '');
                $guardian_name = trim($row[5] ?? '');
                $guardian_email = trim($row[6] ?? '');
                $guardian_relationship = trim($row[7] ?? '');
                $guardian_phone_primary = trim($row[8] ?? '');
                $guardian_phone_emergency = trim($row[9] ?? '');
                $health_insurance_id = trim($row[10] ?? '');
                $previous_school = trim($row[11] ?? '');
                $address = trim($row[12] ?? '');
                
                if (empty($full_name) || empty($index_number) || empty($class_name)) {
                    $skipped++;
                    continue;
                }
                
                // Use guardian email as student account email
                $email = $guardian_email ?: strtolower(str_replace(' ', '', $full_name)) . '@nexcec.edu';
                
                // Check for duplicate
                $stmt = $pdo->prepare("SELECT id FROM students WHERE index_number = ?");
                $stmt->execute([$index_number]);
                if ($stmt->fetch()) {
                    $skipped++;
                    continue;
                }
                
                try {
                    $pdo->beginTransaction();
                    
                    $auto_password = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
                    $password_hash = password_hash($auto_password, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'student')");
                    $stmt->execute([$email, $password_hash]);
                    $user_id = $pdo->lastInsertId();
                    
                    $stmt = $pdo->prepare("INSERT INTO students (
                        user_id, index_number, full_name, class_name, gender, date_of_birth,
                        guardian_name, guardian_email, guardian_relationship,
                        guardian_phone_primary, guardian_phone_emergency,
                        health_insurance_id, previous_school, address
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $user_id, $index_number, $full_name, $class_name, $gender,
                        $date_of_birth ?: null,
                        $guardian_name, $guardian_email, $guardian_relationship,
                        $guardian_phone_primary, $guardian_phone_emergency,
                        $health_insurance_id, $previous_school, $address
                    ]);
                    
                    $pdo->commit();
                    $imported++;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = "Error importing $full_name: " . $e->getMessage();
                    $skipped++;
                }
            }
            
            fclose($handle);
            
            $message = "Import complete! $imported students imported, $skipped skipped.";
            if (!empty($errors)) {
                $message .= "<br>Errors: " . implode('<br>', array_slice($errors, 0, 5));
            }
        } else {
            $error = "Could not read CSV file.";
        }
    } else {
        $error = "Please upload a CSV file.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk Import Students — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .csv-info { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .csv-info table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .csv-info th, .csv-info td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
        .csv-info th { background: #1a5276; color: white; }
    </style>
</head>
<body>
    <div class="dashboard-container">
            <?php echo renderSidebar('bulk_import', $school_name); ?>

        <main class="main-content">
            <div class="top-bar">
                <h2>Bulk Import Students</h2>
                <a href="students.php" class="btn-login" style="background: #6c757d;"><i class="fas fa-arrow-left"></i> Back to Students</a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-content">
                    <h3><i class="fas fa-file-csv" style="color: #27ae60;"></i> CSV Import</h3>
                    <p>Upload a CSV file to bulk import students. The file should have the following columns:</p>
                    
                    <div class="csv-info">
                        <table>
                            <thead>
                                <tr>
                                    <th>Column</th>
                                    <th>Required</th>
                                    <th>Example</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>full_name</td><td>Yes</td><td>Kwame Asante</td></tr>
                                <tr><td>index_number</td><td>Yes</td><td>NXC/2026/001</td></tr>
                                <tr><td>class_name</td><td>Yes</td><td>Basic 1, JHS 2, KG 1</td></tr>
                                <tr><td>gender</td><td>Yes</td><td>Male or Female</td></tr>
                                <tr><td>date_of_birth</td><td>No</td><td>2018-05-15</td></tr>
                                <tr><td>guardian_name</td><td>No</td><td>Mr. Asante</td></tr>
                                <tr><td>guardian_email</td><td>Yes</td><td>parent@email.com <span style="color:red;">(for receipts)</span></td></tr>
                                <tr><td>guardian_relationship</td><td>No</td><td>Father, Mother, Guardian</td></tr>
                                <tr><td>guardian_phone_primary</td><td>Yes</td><td>0241234567 <span style="color:red;">(for SMS)</span></td></tr>
                                <tr><td>guardian_phone_emergency</td><td>No</td><td>0241234568 (fallback)</td></tr>
                                <tr><td>health_insurance_id</td><td>No</td><td>NHIS123456789</td></tr>
                                <tr><td>previous_school</td><td>No</td><td>ABC Kindergarten</td></tr>
                                <tr><td>address</td><td>No</td><td>Kumasi, Ghana</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <a href="#" onclick="downloadSample(); return false;" class="btn-login"><i class="fas fa-download"></i> Download Sample CSV</a>
                    </div>

                    <form action="bulk_import.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="import_students">
                        
                        <div class="form-group">
                            <label>Select CSV File</label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                        </div>
                        
                        <button type="submit" class="btn-primary" style="margin-top: 20px; width: 100%;">
                            <i class="fas fa-upload"></i> Import Students
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
    function downloadSample() {
        const csv = "full_name,index_number,class_name,gender,date_of_birth,guardian_name,guardian_email,guardian_relationship,guardian_phone_primary,guardian_phone_emergency,health_insurance_id,previous_school,address\nKwame Asante,NXC/2026/001,Basic 1,Male,2018-05-15,Mr. Asante,asante@email.com,Father,0241234567,0241234568,NHIS123456789,ABC Kindergarten,Kumasi\nAma Mensah,NXC/2026/002,Basic 2,Female,2017-03-20,Mrs. Mensah,mensah@email.com,Mother,0241234569,0241234570,NHIS987654321,XYZ School,Accra";
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'students_import_sample.csv';
        a.click();
        URL.revokeObjectURL(url);
    }
    </script>
</body>
</html>
