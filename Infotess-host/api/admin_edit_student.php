<?php
require_once 'includes/db.php';

// Enforce access control
requireAccess('edit_student');

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
    // Student info
    $full_name = sanitize($_POST['full_name'] ?? '');
    $index_number = sanitize($_POST['index_number'] ?? '');
    $class_name = sanitize($_POST['class_name'] ?? '');
    $gender = sanitize($_POST['gender'] ?? '');
    $date_of_birth = sanitize($_POST['date_of_birth'] ?? '');
    $place_of_birth = sanitize($_POST['place_of_birth'] ?? '');
    $nationality = sanitize($_POST['nationality'] ?? 'Ghanaian');
    $address = sanitize($_POST['address'] ?? '');
    
    // Guardian details
    $guardian_name = sanitize($_POST['guardian_name'] ?? '');
    $guardian_email = sanitize($_POST['guardian_email'] ?? '');
    $guardian_relationship = sanitize($_POST['guardian_relationship'] ?? '');
    $guardian_phone_primary = sanitize($_POST['guardian_phone_primary'] ?? '');
    $guardian_phone_emergency = sanitize($_POST['guardian_phone_emergency'] ?? '');
    $guardian_occupation = sanitize($_POST['guardian_occupation'] ?? '');
    $guardian_address = sanitize($_POST['guardian_address'] ?? '');
    
    // Health info
    $health_insurance_id = sanitize($_POST['health_insurance_id'] ?? '');
    $medical_conditions = sanitize($_POST['medical_conditions'] ?? '');
    $allergies = sanitize($_POST['allergies'] ?? '');
    $special_needs = sanitize($_POST['special_needs'] ?? '');
    
    // Academic background
    $previous_school = sanitize($_POST['previous_school'] ?? '');
    $previous_class = sanitize($_POST['previous_class'] ?? '');
    $admission_date = sanitize($_POST['admission_date'] ?? '');
    $academic_year = sanitize($_POST['academic_year'] ?? '');

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

        // Update Student (all fields)
        $stmt = $pdo->prepare("UPDATE students SET 
            full_name = ?, index_number = ?, class_name = ?, gender = ?, 
            date_of_birth = ?, place_of_birth = ?, nationality = ?, 
            address = ?, profile_picture = ?,
            guardian_name = ?, guardian_email = ?, guardian_relationship = ?,
            guardian_phone_primary = ?, guardian_phone_emergency = ?, guardian_occupation = ?, guardian_address = ?,
            health_insurance_id = ?, medical_conditions = ?, allergies = ?, special_needs = ?,
            previous_school = ?, previous_class = ?, admission_date = ?, academic_year = ?
            WHERE id = ?");
        $stmt->execute([
            $full_name, $index_number, $class_name, $gender,
            $date_of_birth ?: null, $place_of_birth, $nationality,
            $address, $profile_picture,
            $guardian_name, $guardian_email, $guardian_relationship,
            $guardian_phone_primary, $guardian_phone_emergency, $guardian_occupation, $guardian_address,
            $health_insurance_id, $medical_conditions, $allergies, $special_needs,
            $previous_school, $previous_class, $admission_date ?: null, $academic_year,
            $id
        ]);

        // Update User Email (guardian email)
        $stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $user_id = $stmt->fetchColumn();

        if ($user_id && $guardian_email) {
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$guardian_email, $user_id]);
        }

        $pdo->commit();
        $message = "Student details updated successfully.";
        
        // Refresh student data
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $student = $stmt->fetch();
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
                <a href="students.php" class="btn-secondary" style="display:inline-block; padding:8px 20px; background:linear-gradient(135deg,#4b5563,#374151); color:#fff; text-decoration:none; border-radius:6px; font-size:14px; font-weight:600; border:1px solid rgba(31,41,55,0.35); box-shadow:0 2px 6px rgba(55,65,81,0.2);"><i class="fas fa-arrow-left"></i> Back to List</a>
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
                    
                    <!-- Profile Picture -->
                    <div style="grid-column: span 2; text-align: center; margin-bottom: 10px;">
                        <img id="editStudentPreview" src="../<?php echo htmlspecialchars($student['profile_picture'] ?? 'images/aamusted.jpg'); ?>" alt="Current Profile" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd; margin-bottom: 10px;">
                        <br>
                        <label>Update Profile Picture</label><br>
                        <input type="file" name="profile_picture" id="editStudentUpload" class="form-control" accept="image/*">
                        <div id="editStudentUploadName" class="upload-file-name">No image selected</div>
                    </div>

                    <!-- Student Information -->
                    <div class="section-divider" style="grid-column: span 2; border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px;">
                        <h4><i class="fas fa-user"></i> Student Information</h4>
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
                            <option value="">-- Select --</option>
                            <?php foreach (['Creche','Nursery','KG 1','KG 2','Basic 1','Basic 2','Basic 3','Basic 4','Basic 5','Basic 6','JHS 1','JHS 2','JHS 3'] as $c): ?>
                                <option value="<?php echo $c; ?>" <?php echo ($student['class_name'] ?? '') === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                            <?php endforeach; ?>
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
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($student['date_of_birth'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Place of Birth</label>
                        <input type="text" name="place_of_birth" class="form-control" value="<?php echo htmlspecialchars($student['place_of_birth'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Nationality</label>
                        <input type="text" name="nationality" class="form-control" value="<?php echo htmlspecialchars($student['nationality'] ?? 'Ghanaian'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Hometown</label>
                        <input type="text" name="hometown" class="form-control" value="<?php echo htmlspecialchars($student['hometown'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Place of Residence</label>
                        <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($student['address'] ?? ''); ?>">
                    </div>

                    <!-- Guardian Details -->
                    <div class="section-divider" style="grid-column: span 2; border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px;">
                        <h4><i class="fas fa-user-shield"></i> Parent / Guardian Details</h4>
                    </div>

                    <div class="form-group">
                        <label>Guardian Name</label>
                        <input type="text" name="guardian_name" class="form-control" value="<?php echo htmlspecialchars($student['guardian_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Relationship to Child</label>
                        <select name="guardian_relationship" class="form-control">
                            <option value="">-- Select --</option>
                            <?php foreach (['Father','Mother','Guardian','Uncle','Aunt','Grandparent','Sibling','Other'] as $r): ?>
                                <option value="<?php echo $r; ?>" <?php echo ($student['guardian_relationship'] ?? '') === $r ? 'selected' : ''; ?>><?php echo $r; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Guardian Email <span style="color:red;">(for receipts)</span></label>
                        <input type="email" name="guardian_email" class="form-control" value="<?php echo htmlspecialchars($student['guardian_email'] ?? $student['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Guardian Occupation</label>
                        <input type="text" name="guardian_occupation" class="form-control" value="<?php echo htmlspecialchars($student['guardian_occupation'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Primary Phone <span style="color:red;">(for SMS)</span></label>
                        <input type="text" name="guardian_phone_primary" class="form-control" value="<?php echo htmlspecialchars($student['guardian_phone_primary'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Emergency Phone</label>
                        <input type="text" name="guardian_phone_emergency" class="form-control" value="<?php echo htmlspecialchars($student['guardian_phone_emergency'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Residence</label>
                        <input type="text" name="guardian_address" class="form-control" value="<?php echo htmlspecialchars($student['guardian_address'] ?? ''); ?>">
                    </div>

                    <!-- Health Information -->
                    <div class="section-divider" style="grid-column: span 2; border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px;">
                        <h4><i class="fas fa-heartbeat"></i> Health Information</h4>
                    </div>

                    <div class="form-group">
                        <label>Health Insurance ID</label>
                        <input type="text" name="health_insurance_id" class="form-control" value="<?php echo htmlspecialchars($student['health_insurance_id'] ?? ''); ?>" placeholder="NHIS number">
                    </div>
                    <div class="form-group">
                        <label>Allergies</label>
                        <input type="text" name="allergies" class="form-control" value="<?php echo htmlspecialchars($student['allergies'] ?? ''); ?>" placeholder="e.g. Peanuts, Penicillin">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Medical Conditions</label>
                        <textarea name="medical_conditions" class="form-control" rows="2"><?php echo htmlspecialchars($student['medical_conditions'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Special Needs</label>
                        <textarea name="special_needs" class="form-control" rows="2"><?php echo htmlspecialchars($student['special_needs'] ?? ''); ?></textarea>
                    </div>

                    <!-- Academic Background -->
                    <div class="section-divider" style="grid-column: span 2; border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px;">
                        <h4><i class="fas fa-school"></i> Academic Background</h4>
                    </div>

                    <div class="form-group">
                        <label>Previous School</label>
                        <input type="text" name="previous_school" class="form-control" value="<?php echo htmlspecialchars($student['previous_school'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Previous Class</label>
                        <input type="text" name="previous_class" class="form-control" value="<?php echo htmlspecialchars($student['previous_class'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Admission Date</label>
                        <input type="date" name="admission_date" class="form-control" value="<?php echo htmlspecialchars($student['admission_date'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Academic Year</label>
                        <input type="text" name="academic_year" class="form-control" value="<?php echo htmlspecialchars($student['academic_year'] ?? ''); ?>">
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
