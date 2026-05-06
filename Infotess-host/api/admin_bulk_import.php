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
                // Expected columns: full_name, index_number, class_name, gender, email, phone, guardian_name, guardian_phone
                if (count($row) < 4) {
                    $skipped++;
                    continue;
                }
                
                $full_name = trim($row[0] ?? '');
                $index_number = trim($row[1] ?? '');
                $class_name = trim($row[2] ?? '');
                $gender = trim($row[3] ?? '');
                $email = trim($row[4] ?? '');
                $phone = trim($row[5] ?? '');
                $guardian_name = trim($row[6] ?? '');
                $guardian_phone = trim($row[7] ?? '');
                
                if (empty($full_name) || empty($index_number) || empty($class_name)) {
                    $skipped++;
                    continue;
                }
                
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
                    $stmt->execute([$email ?: strtolower(str_replace(' ', '', $full_name)) . '@nexcec.edu', $password_hash]);
                    $user_id = $pdo->lastInsertId();
                    
                    $stmt = $pdo->prepare("INSERT INTO students (user_id, index_number, full_name, class_name, gender, phone_number, guardian_name, guardian_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $index_number, $full_name, $class_name, $gender, $phone, $guardian_name, $guardian_phone]);
                    
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
        <aside class="sidebar">
            <div class="sidebar-header" style="text-align: center; padding: 20px 10px;">
                <img src="../images/school-logo.png" alt="Logo" style="width: 80px; height: 80px; margin-bottom: 10px; border-radius: 50%; background: #fff; padding: 5px;" onerror="this.src='../images/aamusted.jpg'">
                <h3><?php echo htmlspecialchars($school_name); ?> Admin</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="students.php" class="active"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="staff.php"><i class="fas fa-chalkboard-teacher"></i> Staff</a></li>
                <li><a href="payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="fees.php"><i class="fas fa-list-alt"></i> Fee Structure</a></li>
                <li><a href="payroll.php"><i class="fas fa-file-invoice-dollar"></i> Payroll</a></li>
                <li><a href="salary.php"><i class="fas fa-money-check-alt"></i> Salary Structures</a></li>
                <li><a href="grades.php"><i class="fas fa-clipboard-list"></i> SBA / Grades</a></li>
                <li><a href="attendance.php"><i class="fas fa-user-check"></i> Student Attendance</a></li>
                <li><a href="staff_attendance.php"><i class="fas fa-user-tie"></i> Staff Attendance</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="verify.php"><i class="fas fa-qrcode"></i> Verify Receipt</a></li>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="messaging.php"><i class="fas fa-envelope"></i> Messaging</a></li>
                <li><a href="inbox.php"><i class="fas fa-inbox"></i> Inbox</a></li>
                <li><a href="module_settings.php"><i class="fas fa-cogs"></i> Module Settings</a></li>
                <li><a href="settings.php"><i class="fas fa-tools"></i> System Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

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
                                <tr><td>class_name</td><td>Yes</td><td>Basic 1, JHS 2, KG 1, etc.</td></tr>
                                <tr><td>gender</td><td>Yes</td><td>Male or Female</td></tr>
                                <tr><td>email</td><td>No</td><td>parent@email.com</td></tr>
                                <tr><td>phone</td><td>No</td><td>0241234567</td></tr>
                                <tr><td>guardian_name</td><td>No</td><td>Mr. Asante</td></tr>
                                <tr><td>guardian_phone</td><td>No</td><td>0241234567</td></tr>
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
        const csv = "full_name,index_number,class_name,gender,email,phone,guardian_name,guardian_phone\nKwame Asante,NXC/2026/001,Basic 1,Male,kwame@email.com,0241234567,Mr. Asante,0241234568\nAma Mensah,NXC/2026/002,Basic 2,Female,ama@email.com,0241234569,Mrs. Mensah,0241234570";
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
