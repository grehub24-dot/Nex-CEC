<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../login.php');
}

enforcePasswordReset();

$student_id = $_SESSION['student_id'];
$message = '';
$error = '';

// Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = sanitize($_POST['phone_number']);
    $email = sanitize($_POST['email']);
    
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $objectName = $_SESSION['admission_number'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $url = upload_to_supabase_storage($file, 'profiles', $objectName, null);
        if ($url !== null && strpos($url, 'http') === 0) {
            $profile_picture = $url;
        }
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($profile_picture) {
            $stmt = $pdo->prepare("UPDATE students SET guardian_phone_primary = ?, profile_picture = ? WHERE id = ?");
            $stmt->execute([$phone, $profile_picture, $student_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE students SET guardian_phone_primary = ? WHERE id = ?");
            $stmt->execute([$phone, $student_id]);
        }
        $message = "Profile updated successfully.";
    } catch (Exception $e) {
        $error = "Error updating profile: " . $e->getMessage();
    }
}

// Fetch Student Data
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();
if ($student) {
    $u = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $u->execute([$student['user_id']]);
    $urow = $u->fetch();
    $student['email'] = $urow ? $urow['email'] : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-upload-name { margin-top: 8px; font-size: 0.82rem; color: #4b5563; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
        .info-item label { font-size: 0.85rem; color: #666; display: block; margin-bottom: 4px; }
        .info-item span { font-weight: 600; color: #333; }
        .section-divider { grid-column: span 2; border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px; }
        .section-divider h4 { font-size: 14px; color: #1a5276; margin: 0 0 10px 0; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header" style="text-align:center; padding: 20px 10px;">
                <img src="<?php echo htmlspecialchars($settings['school_logo_url'] ?? '../images/aamusted.jpg'); ?>" alt="Logo" style="max-width: 90px; max-height: 48px; width: auto; height: auto; object-fit: contain; background: #fff; padding: 4px 8px; border-radius: 6px; display: inline-block; margin-bottom: 8px;" onerror="this.onerror=null;this.src='../images/aamusted.jpg'">
                <h3 style="font-size:15px;">My Portal</h3>
            </div>
                                    <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="fees.php"><i class="fas fa-list-alt"></i> Fee Structure</a></li>
                <li><a href="report_card.php"><i class="fas fa-clipboard"></i> Report Card</a></li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages 
                    <span class="badge" style="background:#dc3545; color:white; padding:2px 6px; border-radius:50%; font-size:0.7rem;">0</span>
                </a></li>
                <li><a href="history.php"><i class="fas fa-history"></i> Payment History</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h2>My Profile</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card">
                <h3>Personal Information</h3>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group" style="text-align:center;">
                        <img id="profilePicturePreview" src="<?php echo htmlspecialchars(resolve_storage_url($student['profile_picture'] ?? null, 'images/aamusted.jpg')); ?>" alt="Profile Picture" style="width:150px; height:150px; border-radius:50%; object-fit:cover; margin-bottom:10px;">
                        <br>
                        <label for="profile_picture" class="btn-login" style="cursor:pointer; display:inline-block;">Change Picture</label>
                        <input type="file" name="profile_picture" id="profile_picture" style="display:none;" accept="image/*">
                        <div id="profileUploadName" class="profile-upload-name">No image selected</div>
                    </div>
                    
                    <!-- Read-Only Fields -->
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Full Name</label>
                            <span><?php echo htmlspecialchars($student['full_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Index Number</label>
                            <span><?php echo htmlspecialchars($student['admission_number']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Class</label>
                            <span><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Gender</label>
                            <span><?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></span>
                        </div>
                    </div>

                    <!-- Guardian Info -->
                    <div class="section-divider">
                        <h4><i class="fas fa-user-shield"></i> Guardian Information</h4>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Guardian Name</label>
                            <span><?php echo htmlspecialchars($student['guardian_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Guardian Phone</label>
                            <span><?php echo htmlspecialchars($student['guardian_phone_primary'] ?? 'N/A'); ?></span>
                        </div>
                    </div>

                    <!-- Editable Fields -->
                    <div style="margin-top: 25px;">
                        <div class="form-group">
                            <label>Email (for fee receipts)</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($student['guardian_phone_primary'] ?? ''); ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" style="margin-top: 15px;">Update Profile</button>
                </form>
            </div>
        </main>
    </div>
    <script>
        const profilePictureInput = document.getElementById('profile_picture');
        const profilePicturePreview = document.getElementById('profilePicturePreview');
        const profileUploadName = document.getElementById('profileUploadName');

        if (profilePictureInput && profilePicturePreview && profileUploadName) {
            profilePictureInput.addEventListener('change', function() {
                const file = this.files && this.files[0] ? this.files[0] : null;
                const defaultSrc = "<?php echo htmlspecialchars(resolve_storage_url($student['profile_picture'] ?? null, 'images/aamusted.jpg')); ?>";
                if (!file) { profilePicturePreview.src = defaultSrc; profileUploadName.textContent = 'No image selected'; return; }
                profileUploadName.textContent = file.name;
                if (!file.type.startsWith('image/')) { profilePicturePreview.src = defaultSrc; profileUploadName.textContent = 'Please select an image file'; this.value = ''; return; }
                const reader = new FileReader();
                reader.onload = function(event) { profilePicturePreview.src = event.target.result; };
                reader.readAsDataURL(file);
            });
        }
    </script>
</body>
</html>
