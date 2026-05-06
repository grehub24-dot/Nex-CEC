<?php
require_once 'includes/db.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Fetch Settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect('students.php');
}

$message = '';
$error = '';

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $index_number = sanitize($_POST['index_number']);
    $class_name = sanitize($_POST['class_name']);
    $gender = sanitize($_POST['gender'] ?? '');
    $phone = sanitize($_POST['phone_number'] ?? '');
    $guardian_name = sanitize($_POST['guardian_name'] ?? '');
    $guardian_phone = sanitize($_POST['guardian_phone'] ?? '');
    $email = sanitize($_POST['email']);

    // Handle Profile Picture
    $profile_picture = $_POST['current_picture'] ?? null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../images/profiles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $filename = $index_number . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $filename)) {
            $profile_picture = 'images/profiles/' . $filename;
        }
    }

    try {
        $pdo->beginTransaction();

        // Update Student
        $stmt = $pdo->prepare("UPDATE students SET full_name = ?, index_number = ?, class_name = ?, gender = ?, phone_number = ?, guardian_name = ?, guardian_phone = ?, profile_picture = ? WHERE id = ?");
        $stmt->execute([$full_name, $index_number, $class_name, $gender, $phone, $guardian_name, $guardian_phone, $profile_picture, $id]);

        // Update User Email
        $stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $user_id = $stmt->fetchColumn();

        if ($user_id) {
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$email, $user_id]);
        }

        $pdo->commit();
        $message = "Student details updated successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating student: " . $e->getMessage();
    }
}

// Fetch Student Data
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if ($student) {
    $userId = isset($student['user_id']) && $student['user_id'] !== '' && $student['user_id'] !== null ? (int)$student['user_id'] : 0;
    if ($userId > 0) {
        $u = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $u->execute([$userId]);
        $urow = $u->fetch();
        $student['email'] = $urow ? $urow['email'] : '';
    }
}

if (!$student) {
    redirect('students.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Student — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .upload-file-name { margin-top: 8px; font-size: 0.82rem; color: #4b5563; }
        .section-divider { grid-column: span 2; border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px; }
        .section-divider h4 { font-size: 15px; color: #1a5276; margin: 0 0 10px 0; }
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
                <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="bulk_import.php"><i class="fas fa-file-csv"></i> Bulk Import</a></li>
                <li><a href="staff.php"><i class="fas fa-chalkboard-teacher"></i> Staff</a></li>
                <li><a href="payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="fees.php"><i class="fas fa-list-alt"></i> Fee Structure</a></li>
                <li><a href="payroll.php"><i class="fas fa-file-invoice-dollar"></i> Payroll</a></li>
                <li><a href="salary.php"><i class="fas fa-money-check-alt"></i> Salary Structures</a></li>
                <li><a href="grades.php"><i class="fas fa-clipboard-list"></i> SBA / Grades</a></li>
                <li><a href="attendance.php"><i class="fas fa-user-check"></i> Attendance</a></li>
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
                <h2>Edit Student</h2>
                <a href="students.php" class="btn-secondary">Back to List</a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card">
                <form action="" method="POST" enctype="multipart/form-data" style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                    <input type="hidden" name="current_picture" value="<?php echo htmlspecialchars($student['profile_picture'] ?? ''); ?>">
                    
                    <div style="grid-column: span 2; text-align: center; margin-bottom: 10px;">
                        <img id="editStudentPreview" src="../<?php echo htmlspecialchars($student['profile_picture'] ?? 'images/aamusted.jpg'); ?>" alt="Current Profile" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd; margin-bottom: 10px;">
                        <br>
                        <label>Update Profile Picture</label><br>
                        <input type="file" name="profile_picture" id="editStudentUpload" class="form-control" accept="image/*">
                        <div id="editStudentUploadName" class="upload-file-name">No image selected</div>
                    </div>

                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($student['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Index Number</label>
                        <input type="text" name="index_number" class="form-control" value="<?php echo htmlspecialchars($student['index_number'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Class</label>
                        <select name="class_name" class="form-control" required>
                            <option value="">-- Select Class --</option>
                            <optgroup label="Early Childhood">
                                <option value="Creche" <?php echo ($student['class_name'] ?? '') === 'Creche' ? 'selected' : ''; ?>>Creche</option>
                                <option value="Nursery" <?php echo ($student['class_name'] ?? '') === 'Nursery' ? 'selected' : ''; ?>>Nursery</option>
                                <option value="KG 1" <?php echo ($student['class_name'] ?? '') === 'KG 1' ? 'selected' : ''; ?>>KG 1</option>
                                <option value="KG 2" <?php echo ($student['class_name'] ?? '') === 'KG 2' ? 'selected' : ''; ?>>KG 2</option>
                            </optgroup>
                            <optgroup label="Primary">
                                <?php foreach (['Basic 1', 'Basic 2', 'Basic 3', 'Basic 4', 'Basic 5', 'Basic 6'] as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo ($student['class_name'] ?? '') === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Junior High School">
                                <?php foreach (['JHS 1', 'JHS 2', 'JHS 3'] as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo ($student['class_name'] ?? '') === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="Male" <?php echo ($student['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($student['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Student Phone</label>
                        <input type="text" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($student['phone_number'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" required>
                    </div>

                    <!-- Guardian Details -->
                    <div class="section-divider">
                        <h4><i class="fas fa-user-shield"></i> Parent / Guardian Details</h4>
                    </div>

                    <div class="form-group">
                        <label>Guardian Full Name</label>
                        <input type="text" name="guardian_name" class="form-control" value="<?php echo htmlspecialchars($student['guardian_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Guardian Phone Number</label>
                        <input type="text" name="guardian_phone" class="form-control" value="<?php echo htmlspecialchars($student['guardian_phone'] ?? ''); ?>">
                    </div>
                    
                    <div style="grid-column: span 2; margin-top: 20px;">
                        <button type="submit" class="btn-primary" style="width:100%;">Update Student Details</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>
        const editStudentUpload = document.getElementById('editStudentUpload');
        const editStudentPreview = document.getElementById('editStudentPreview');
        const editStudentUploadName = document.getElementById('editStudentUploadName');

        if (editStudentUpload && editStudentPreview && editStudentUploadName) {
            editStudentUpload.addEventListener('change', function() {
                const file = this.files && this.files[0] ? this.files[0] : null;
                const defaultSrc = "../<?php echo htmlspecialchars($student['profile_picture'] ?? 'images/aamusted.jpg'); ?>";
                if (!file) { editStudentPreview.src = defaultSrc; editStudentUploadName.textContent = 'No image selected'; return; }
                editStudentUploadName.textContent = file.name;
                if (!file.type.startsWith('image/')) { editStudentPreview.src = defaultSrc; editStudentUploadName.textContent = 'Please select an image file'; this.value = ''; return; }
                const reader = new FileReader();
                reader.onload = function(event) { editStudentPreview.src = event.target.result; };
                reader.readAsDataURL(file);
            });
        }
    </script>
</body>
</html>
