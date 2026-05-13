<?php
require_once 'includes/db.php';

if (!isLoggedIn() || !isParentOrDual()) {
    redirect('../login.php');
}

$parent_user_id = $_SESSION['user_id'];

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

// Ensure profile_picture column exists in users table
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    if ($stmt->rowCount() == 0) {
        // Try PostgreSQL syntax first
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture TEXT");
        } catch (Exception $e2) {
            // Fallback to MySQL syntax
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL");
            } catch (Exception $e3) {
                // Column may already exist or table is read-only
            }
        }
    }
} catch (Exception $e) {
    // Schema check failed, try adding column directly
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture TEXT");
    } catch (Exception $e2) {}
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');

    // Update email in users table
    try {
        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$email, $parent_user_id]);
    } catch (Exception $e) {
        $error = "Error updating email: " . $e->getMessage();
    }

    // Handle profile picture upload
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        // Validate file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            $error = "File too large. Maximum size is 2MB.";
        } else {
            // Validate MIME type and extension
            $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($mime, $allowed_mime) || !in_array($ext, $allowed_ext)) {
                $error = "Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.";
            } else {
                $upload_dir = __DIR__ . '/../images/profiles/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0775, true);
                }
                $file_name = 'parent_' . $parent_user_id . '_' . time() . '_' . uniqid() . '.' . $ext;
                $target_file = $upload_dir . $file_name;
                if (move_uploaded_file($file['tmp_name'], $target_file)) {
                    $profile_picture = 'images/profiles/' . $file_name;
                    try {
                        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                        $stmt->execute([$profile_picture, $parent_user_id]);
                    } catch (Exception $e) {
                        $error = "Error saving profile picture: " . $e->getMessage();
                    }
                } else {
                    $error = "Failed to upload image.";
                }
            }
        }
    }

    // Also update guardian phone in students table (for any linked children)
    if (!empty($phone)) {
        try {
            $stmt = $pdo->prepare("UPDATE students SET guardian_phone_primary = ? WHERE user_id = ?");
            $stmt->execute([$phone, $parent_user_id]);
        } catch (Exception $e) {
            // user_id on students might point to parent - that's fine
        }
        // Also try via parent_students junction
        try {
            $stmt = $pdo->prepare("SELECT student_id FROM parent_students WHERE parent_user_id = ?");
            $stmt->execute([$parent_user_id]);
            $links = $stmt->fetchAll();
            foreach ($links as $link) {
                $stmt = $pdo->prepare("UPDATE students SET guardian_phone_primary = ? WHERE id = ?");
                $stmt->execute([$phone, (int)$link['student_id']]);
            }
        } catch (Exception $e) {}
    }

    if (empty($error)) {
        $message = "Profile updated successfully.";
        $_SESSION['email'] = $email;
    }
}

// Fetch current user data
$user = [];
$children_names = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$parent_user_id]);
    $user = $stmt->fetch();

    // Fetch children names for display
    $stmt = $pdo->prepare("SELECT s.full_name FROM parent_students ps JOIN students s ON s.id = ps.student_id WHERE ps.parent_user_id = ?");
    $stmt->execute([$parent_user_id]);
    $children_names = array_map(fn($r) => $r['full_name'], $stmt->fetchAll());
} catch (Exception $e) {
    error_log("Parent profile fetch error: " . $e->getMessage());
}

$profile_pic = $user['profile_picture'] ?? null;
$user_email = $user['email'] ?? '';

// Fetch first child's guardian phone as default
$guardian_phone = '';
try {
    $stmt = $pdo->prepare("SELECT guardian_phone_primary FROM parent_students ps JOIN students s ON s.id = ps.student_id WHERE ps.parent_user_id = ? LIMIT 1");
    $stmt->execute([$parent_user_id]);
    $row = $stmt->fetch();
    if ($row && !empty($row['guardian_phone_primary'])) {
        $guardian_phone = $row['guardian_phone_primary'];
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Parent Portal</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; color: #333; }
        .top-bar {
            background: #1a5276; color: white; padding: 15px 30px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .top-bar a { color: white; text-decoration: none; font-size: 14px; }
        .container { max-width: 700px; margin: 0 auto; padding: 30px 20px; }
        .card {
            background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 28px; margin-bottom: 20px;
        }
        .card h3 {
            font-size: 17px; color: #1a5276; margin-bottom: 20px;
            padding-bottom: 10px; border-bottom: 2px solid #1a5276;
        }
        .profile-pic-section { text-align: center; margin-bottom: 25px; }
        .profile-pic-section img {
            width: 130px; height: 130px; border-radius: 50%; object-fit: cover;
            border: 4px solid #e8f0fe; margin-bottom: 12px;
        }
        .profile-pic-section .upload-label {
            display: inline-block; background: #1a5276; color: white;
            padding: 8px 20px; border-radius: 6px; cursor: pointer;
            font-size: 14px; font-weight: 600; transition: background 0.2s;
        }
        .profile-pic-section .upload-label:hover { background: #143c58; }
        .profile-pic-section input[type="file"] { display: none; }
        .profile-pic-section .file-name { font-size: 13px; color: #888; margin-top: 8px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .info-item label { font-size: 13px; color: #888; display: block; margin-bottom: 4px; }
        .info-item span { font-weight: 600; color: #333; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .form-control:focus { border-color: #1a5276; outline: none; box-shadow: 0 0 0 3px rgba(26,82,118,0.1); }
        .btn-save {
            background: #1a5276; color: white; border: none; padding: 12px 30px;
            border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer;
            transition: background 0.2s;
        }
        .btn-save:hover { background: #143c58; }
        .btn-back {
            display: inline-flex; align-items: center; gap: 6px;
            background: transparent; color: white; text-decoration: none; font-size: 14px;
        }
        .btn-back:hover { text-decoration: underline; }
        .children-tags {
            display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px;
        }
        .children-tags .tag {
            background: #e8f0fe; color: #1a5276; font-size: 12px;
            padding: 4px 12px; border-radius: 20px; font-weight: 500;
        }
        @media (max-width: 600px) {
            .info-grid { grid-template-columns: 1fr; }
            .top-bar { flex-direction: column; gap: 8px; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div>
            <a href="../parent/dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
        <span>My Profile — <?php echo htmlspecialchars($school_name); ?></span>
        <div>
            <?php if (isset($_SESSION['has_children']) && $_SESSION['has_children']): ?>
            <a href="../admin/dashboard.php" style="color: white; margin-left: 15px; font-size: 13px;"><i class="fas fa-chalkboard-teacher"></i> Staff Portal</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <h3><i class="fas fa-user-circle"></i> My Profile</h3>

            <form method="POST" action="" enctype="multipart/form-data">
                <!-- Profile Picture -->
                <div class="profile-pic-section">
                    <img id="profilePreview" src="../<?php echo htmlspecialchars($profile_pic ?? 'images/aamusted.jpg'); ?>" alt="Profile Picture">
                    <br>
                    <label for="profile_picture" class="upload-label"><i class="fas fa-camera"></i> Change Picture</label>
                    <input type="file" name="profile_picture" id="profile_picture" accept="image/*">
                    <div class="file-name" id="fileName">No image selected</div>
                </div>

                <!-- Read-Only Info -->
                <div class="info-grid">
                    <div class="info-item">
                        <label>Email</label>
                        <span><?php echo htmlspecialchars($user_email); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Role</label>
                        <span><?php echo ucfirst($_SESSION['role'] ?? 'Parent'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Linked Children</label>
                        <div class="children-tags">
                            <?php if (!empty($children_names)): ?>
                                <?php foreach ($children_names as $name): ?>
                                    <span class="tag"><?php echo htmlspecialchars($name); ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color: #888; font-size: 13px;">None</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Editable Fields -->
                <h3 style="margin-top: 25px;"><i class="fas fa-edit"></i> Edit Information</h3>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control"
                           value="<?php echo htmlspecialchars($user_email); ?>" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" name="phone" id="phone" class="form-control"
                           value="<?php echo htmlspecialchars($guardian_phone); ?>" placeholder="Primary contact number">
                </div>

                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
            </form>
        </div>

        <div style="text-align: center; margin-top: 10px;">
            <a href="../parent/password-reset.php" style="color: #1a5276; font-size: 14px;"><i class="fas fa-key"></i> Change Password</a>
            &nbsp;&middot;&nbsp;
            <a href="../parent/dashboard.php" style="color: #1a5276; font-size: 14px;"><i class="fas fa-home"></i> Back to Dashboard</a>
        </div>
    </div>

    <script>
        const fileInput = document.getElementById('profile_picture');
        const preview = document.getElementById('profilePreview');
        const fileName = document.getElementById('fileName');

        if (fileInput && preview && fileName) {
            fileInput.addEventListener('change', function() {
                const file = this.files && this.files[0];
                if (!file) {
                    preview.src = '../<?php echo htmlspecialchars($profile_pic ?? 'images/aamusted.jpg'); ?>';
                    fileName.textContent = 'No image selected';
                    return;
                }
                fileName.textContent = file.name;
                if (!file.type.startsWith('image/')) {
                    preview.src = '../<?php echo htmlspecialchars($profile_pic ?? 'images/aamusted.jpg'); ?>';
                    fileName.textContent = 'Please select an image file';
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) { preview.src = e.target.result; };
                reader.readAsDataURL(file);
            });
        }
    </script>
</body>
</html>
