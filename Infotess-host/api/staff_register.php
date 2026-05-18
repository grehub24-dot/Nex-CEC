<?php
// staff_register.php — Staff self-registration via invite token
require_once 'includes/db.php';
require_once 'includes/functions.php';

// $school_name (for page title/header)
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$staff = null;
$invite = null;

// Validate token
if (!empty($token)) {
    try {
        // Step 1: Look up the invite (bridge does NOT support JOINs)
        $stmt = $pdo->prepare("SELECT * FROM staff_invites WHERE token = ? AND status = 'pending' LIMIT 1");
        $stmt->execute([$token]);
        $invite = $stmt->fetch();

        if ($invite) {
            $expiresAt = strtotime($invite['expires_at']);
            if ($expiresAt < time()) {
                $error = "This invitation link has expired. Please contact the school administrator for a new invite.";
                $invite = null;
            } else {
                // Step 2: Fetch staff member data by the FK staff_id from invite
                $staffStmt = $pdo->prepare("SELECT * FROM staff WHERE id = ? LIMIT 1");
                $staffStmt->execute([(int)$invite['staff_id']]);
                $staffRow = $staffStmt->fetch();
                if ($staffRow) {
                    $staff = $staffRow;
                } else {
                    $error = "Associated staff record not found. Please contact the school administrator.";
                    $invite = null;
                }
            }
        } else {
            // Check if already accepted
            $stmt2 = $pdo->prepare("SELECT * FROM staff_invites WHERE token = ? LIMIT 1");
            $stmt2->execute([$token]);
            $existing = $stmt2->fetch();
            if ($existing && $existing['status'] === 'accepted') {
                $error = "This invitation has already been accepted. Please log in with the credentials you created.";
            } else {
                $error = "Invalid or expired invitation token. Please contact the school administrator.";
            }
        }
    } catch (Exception $e) {
        $error = "An error occurred. Please try again later.";
        error_log("staff_register token validation: " . $e->getMessage());
    }
} else {
    $error = "No invitation token provided. Please use the link from your invitation email or SMS.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $staff && isset($_POST['register'])) {
    $gender = $_POST['gender'] ?? '';
    $dateOfBirth = $_POST['date_of_birth'] ?? '';
    $address = $_POST['address'] ?? '';
    $qualification = $_POST['qualification'] ?? '';
    $phone = $_POST['phone'] ?? $staff['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($password)) {
        $error = "Password is required.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        try {
            $pdo->beginTransaction();

            // Handle file uploads
            $profilePicture = $staff['profile_picture'] ?? '';
            $cvPath = $staff['cv_path'] ?? '';
            $documents = $staff['documents'] ?? '[]';

            // Upload profile picture
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $uploaded = uploadStaffFile($_FILES['profile_picture'], 'staff_documents');
                if ($uploaded) $profilePicture = $uploaded;
            }

            // Upload CV
            if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
                $uploaded = uploadStaffFile($_FILES['cv'], 'staff_documents');
                if ($uploaded) $cvPath = $uploaded;
            }

            // Upload additional documents
            $existingDocs = json_decode($documents, true) ?: [];
            if (isset($_FILES['documents'])) {
                $files = $_FILES['documents'];
                if (is_array($files['name'])) {
                    $fileCount = count($files['name']);
                    for ($i = 0; $i < $fileCount; $i++) {
                        if ($files['error'][$i] === UPLOAD_ERR_OK) {
                            $singleFile = [
                                'name' => $files['name'][$i],
                                'type' => $files['type'][$i],
                                'tmp_name' => $files['tmp_name'][$i],
                                'error' => $files['error'][$i],
                                'size' => $files['size'][$i]
                            ];
                            $uploaded = uploadStaffFile($singleFile, 'staff_documents');
                            if ($uploaded) $existingDocs[] = $uploaded;
                        }
                    }
                } elseif ($files['error'] === UPLOAD_ERR_OK) {
                    $uploaded = uploadStaffFile($_FILES['documents'], 'staff_documents');
                    if ($uploaded) $existingDocs[] = $uploaded;
                }
            }
            $documents = json_encode($existingDocs);

            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Update staff record
            $updFields = [];
            $updVals = [];
            if (!empty($gender)) { $updFields[] = "gender = ?"; $updVals[] = $gender; }
            if (!empty($dateOfBirth)) { $updFields[] = "date_of_birth = ?"; $updVals[] = $dateOfBirth; }
            if (!empty($address)) { $updFields[] = "address = ?"; $updVals[] = $address; }
            if (!empty($qualification)) { $updFields[] = "qualification = ?"; $updVals[] = $qualification; }
            if (!empty($phone)) { $updFields[] = "phone = ?"; $updVals[] = $phone; }
            if (!empty($profilePicture)) { $updFields[] = "profile_picture = ?"; $updVals[] = $profilePicture; }
            if (!empty($cvPath)) { $updFields[] = "cv_path = ?"; $updVals[] = $cvPath; }
            if (!empty($documents)) { $updFields[] = "documents = ?"; $updVals[] = $documents; }

            if (!empty($updFields)) {
                $updVals[] = (int)$staff['id'];
                $pdo->prepare("UPDATE staff SET " . implode(', ', $updFields) . " WHERE id = ?")->execute($updVals);
            }

            // Update users table — set password, keep status as inactive
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashedPassword, (int)$staff['user_id']]);

            // Mark invite as accepted
            $pdo->prepare("UPDATE staff_invites SET status = 'accepted', accepted_at = NOW() WHERE id = ?")->execute([(int)$invite['id']]);

            $pdo->commit();

            $success = "Registration completed successfully! Your account is now pending activation by an administrator. You will be able to log in once your account is activated.";
            $staff = null; // hide form

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Registration failed: " . $e->getMessage();
            error_log("staff_register submission: " . $e->getMessage());
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Registration — <?php echo htmlspecialchars($school_name ?? 'SchoolName'); ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a5276 0%, #2e86c1 50%, #85c1e9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .register-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 700px;
            padding: 40px;
        }
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .register-header h1 {
            color: #1a5276;
            font-size: 24px;
            margin: 0 0 5px 0;
        }
        .register-header p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }
        .register-header .school-name {
            color: #2e86c1;
            font-weight: 600;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
            font-size: 13px;
        }
        .form-group .hint {
            font-size: 11px;
            color: #999;
            font-weight: normal;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1a5276;
            box-shadow: 0 0 0 3px rgba(26,82,118,0.1);
        }
        .form-group input[readonly] {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        .form-group input[type="file"] {
            padding: 8px;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 60px;
        }
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1a5276;
            border-bottom: 2px solid #1a5276;
            padding-bottom: 8px;
            margin: 25px 0 20px 0;
        }
        .section-title i {
            margin-right: 8px;
        }
        .btn-register {
            width: 100%;
            padding: 14px;
            background: #1a5276;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
        }
        .btn-register:hover {
            background: #154360;
        }
        .btn-register:disabled {
            background: #999;
            cursor: not-allowed;
        }
        .error-box, .success-box, .info-box {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .error-box {
            background: #fdf0ef;
            color: #b03a2e;
            border: 1px solid #f5c6cb;
        }
        .success-box {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .info-box {
            background: #d6eaf8;
            color: #1a5276;
            border: 1px solid #aed6f1;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        .login-link a {
            color: #1a5276;
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .password-hint {
            font-size: 12px;
            color: #888;
            margin-top: 3px;
        }
        .required::after {
            content: " *";
            color: #e74c3c;
        }
        .file-upload-hint {
            font-size: 11px;
            color: #999;
            display: block;
            margin-top: 3px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>Staff Registration</h1>
            <p>Complete your profile to join <span class="school-name"><?php echo htmlspecialchars($school_name ?? ''); ?></span></p>
        </div>

        <?php if ($error): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <?php if (strpos($error, 'expired') !== false || strpos($error, 'Invalid') !== false || strpos($error, 'already') !== false): ?>
                    <div class="login-link" style="margin-top:10px;">
                        <a href="login.php"><i class="fas fa-sign-in-alt"></i> Go to Login</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-box">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <div class="login-link" style="margin-top:15px;">
                    <a href="login.php" style="background:#1a5276; color:white; padding:10px 24px; border-radius:6px; display:inline-block; text-decoration:none;">
                        <i class="fas fa-sign-in-alt"></i> Go to Login
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($staff && empty($success)): ?>
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                Welcome, <strong><?php echo htmlspecialchars($staff['full_name']); ?></strong>!
                Please complete your registration below. Fields marked with <span style="color:#e74c3c;">*</span> are required.
            </div>

            <form method="POST" enctype="multipart/form-data" id="registerForm">
                <!-- Personal Information -->
                <div class="section-title"><i class="fas fa-user"></i> Personal Information</div>

                <div class="form-row">
                    <div class="form-group">
                        <label><span class="required">Full Name</span></label>
                        <input type="text" value="<?php echo htmlspecialchars($staff['full_name'] ?? ''); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label><span class="required">Email</span></label>
                        <input type="email" value="<?php echo htmlspecialchars($staff['email'] ?? ''); ?>" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><span class="required">Staff ID</span></label>
                        <input type="text" value="<?php echo htmlspecialchars($staff['staff_id'] ?? ''); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label><span class="required">Position</span></label>
                        <input type="text" value="<?php echo htmlspecialchars($staff['position'] ?? ''); ?>" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" value="<?php echo htmlspecialchars($staff['department'] ?? ''); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender <span class="hint">(required)</span></label>
                        <select name="gender" id="gender">
                            <option value="">-- Select Gender --</option>
                            <option value="Male" <?php echo ($staff['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($staff['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" name="date_of_birth" id="date_of_birth" value="<?php echo htmlspecialchars($staff['date_of_birth'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($staff['phone'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea name="address" id="address"><?php echo htmlspecialchars($staff['address'] ?? ''); ?></textarea>
                </div>

                <!-- Professional Information -->
                <div class="section-title"><i class="fas fa-graduation-cap"></i> Professional Information</div>

                <div class="form-group">
                    <label for="qualification">Qualifications</label>
                    <textarea name="qualification" id="qualification" placeholder="e.g., B.Ed. Mathematics, Certified Teacher, etc."><?php echo htmlspecialchars($staff['qualification'] ?? ''); ?></textarea>
                </div>

                <!-- Document Uploads -->
                <div class="section-title"><i class="fas fa-upload"></i> Document Uploads</div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="profile_picture">Profile Picture</label>
                        <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg,image/png,image/gif,image/webp">
                        <span class="file-upload-hint">JPG, PNG, GIF or WebP. Max 2MB.</span>
                    </div>
                    <div class="form-group">
                        <label for="cv">Curriculum Vitae (CV)</label>
                        <input type="file" name="cv" id="cv" accept=".pdf,.doc,.docx">
                        <span class="file-upload-hint">PDF or Word document.</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="documents">Additional Documents <span class="hint">(optional, multiple files)</span></label>
                    <input type="file" name="documents[]" id="documents" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    <span class="file-upload-hint">Certificates, transcripts, or any supporting documents.</span>
                </div>

                <!-- Account Security -->
                <div class="section-title"><i class="fas fa-lock"></i> Account Security</div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password"><span class="required">Password</span></label>
                        <input type="password" name="password" id="password" required minlength="6">
                        <span class="password-hint">At least 6 characters</span>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password"><span class="required">Confirm Password</span></label>
                        <input type="password" name="confirm_password" id="confirm_password" required minlength="6">
                    </div>
                </div>

                <button type="submit" name="register" class="btn-register" id="submitBtn">
                    <i class="fas fa-check-circle"></i> Complete Registration
                </button>
            </form>

            <div class="login-link">
                <p><small>Already registered? <a href="login.php">Log in</a></small></p>
            </div>

            <script>
                document.getElementById('registerForm')?.addEventListener('submit', function(e) {
                    var pw = document.getElementById('password');
                    var cpw = document.getElementById('confirm_password');
                    if (pw.value !== cpw.value) {
                        e.preventDefault();
                        alert('Passwords do not match. Please re-enter them.');
                        return;
                    }
                    document.getElementById('submitBtn').disabled = true;
                    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                });
            </script>
        <?php elseif (empty($error) && empty($success)): ?>
            <div class="info-box">
                <i class="fas fa-spinner fa-spin"></i> Validating your invitation token...
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
