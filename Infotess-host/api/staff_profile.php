<?php
require_once 'includes/db.php';

if (!isLoggedIn() || (!isStaff() && !isTeacher())) {
    redirect('../login.php');
}

$settings = fetchSettings($pdo);
$school_name = $settings['school_name'] ?? 'Nex CEC';

$user_id = $_SESSION['user_id'];

$message = '';
$error = '';

// Ensure profile_picture column exists in staff table
// ALTER TABLE cannot run through the PDO bridge (DDL skipped), so use Supabase SQL API directly
global $supabase;
if ($supabase && $supabase instanceof SupabaseClient) {
    try {
        $supabase->executeSql("ALTER TABLE staff ADD COLUMN IF NOT EXISTS profile_picture TEXT");
    } catch (Exception $e) {
        error_log("profile_picture migration: " . $e->getMessage());
        // Non-fatal — the UPDATE below will fail with a clear error if column is missing
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Handle profile picture upload to Supabase Storage
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            // Validate file size (max 2MB)
            if ($file['size'] > 2 * 1024 * 1024) {
                $error = "File too large. Maximum size is 2MB.";
            } else {
                $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo !== false) {
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                } else {
                    // Fallback: guess from extension
                    $extMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $mime = $extMap[$ext] ?? 'application/octet-stream';
                }
                $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($mime, $allowed_mime)) {
                    $error = "Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.";
                } else {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $file_name = 'staff_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $ext;

                    // Check if Supabase client is available before attempting upload
                    global $supabase;
                    if (!$supabase || !($supabase instanceof SupabaseClient)) {
                        $error = "Upload service not available (Supabase not connected).";
                    } else {
                        try {
                            // Read file data (MIME already detected above as $mime)
                            $fileData = file_get_contents($file['tmp_name']);
                            if ($fileData === false) {
                                throw new Exception("Cannot read uploaded file.");
                            }

                            // Upload directly via Supabase client
                            try {
                                $supabase->uploadFile('profiles', $file_name, $fileData, $mime ?: 'image/jpeg');
                            } catch (Exception $e) {
                                // If bucket doesn't exist, create it and retry once
                                if (strpos($e->getMessage(), 'Bucket not found') !== false) {
                                    $supabase->createBucket('profiles');
                                    $supabase->uploadFile('profiles', $file_name, $fileData, $mime ?: 'image/jpeg');
                                } else {
                                    throw $e; // Re-throw non-bucket errors
                                }
                            }
                            $newUrl = $supabase->getPublicUrl('profiles', $file_name);

                            // Save to database (use SupabaseClient directly — PDO bridge can't handle DDL
                            // and the PGRST204 column-stripping retry would silently produce empty data)
                            $supabase->table('staff')->where('user_id', $user_id)->update(['profile_picture' => $newUrl]);
                            $_SESSION['profile_picture'] = $newUrl;
                            $message = "Profile picture updated successfully!";
                        } catch (Exception $e) {
                            $error = "Upload failed: " . $e->getMessage();
                        }
                    }
                }
            }
        } else {
            $error = "No file selected or upload error.";
        }
    }
}

// Fetch staff record
$stmt = $pdo->prepare("SELECT * FROM staff WHERE user_id = ?");
$stmt->execute([$user_id]);
$staff = $stmt->fetch();

if (!$staff) {
    echo '<div class="container" style="padding:100px 0;text-align:center;"><h2>Staff record not found</h2><a href="../logout.php" class="btn-primary">Logout</a></div>';
    exit;
}

$staff_id = (int)$staff['id'];

// Profile picture from DB (fall back to session cache, then empty)
$profile_pic = $staff['profile_picture'] ?? ($_SESSION['profile_picture'] ?? '');

// Fetch user record
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch salary structure
$stmt = $pdo->prepare("SELECT * FROM salary_structures WHERE staff_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$staff_id]);
$salary = $stmt->fetch();

// Fetch unread messages count
$stmt = $pdo->prepare("SELECT id FROM messages WHERE receiver_id = ?");
$stmt->execute([$user_id]);
$direct_ids = array_map(fn($r) => (int)$r['id'], $stmt->fetchAll());
$stmt = $pdo->prepare("SELECT id FROM messages WHERE is_broadcast = ?");
$stmt->execute([1]);
$broadcast_ids = array_map(fn($r) => (int)$r['id'], $stmt->fetchAll());
$all_msg_ids = array_unique(array_merge($direct_ids, $broadcast_ids));
$stmt = $pdo->prepare("SELECT message_id FROM message_reads WHERE user_id = ?");
$stmt->execute([$user_id]);
$read_ids = array_map(fn($r) => (int)$r['message_id'], $stmt->fetchAll());
$unread_count = 0;
foreach ($all_msg_ids as $mid) {
    if (!in_array($mid, $read_ids)) $unread_count++;
}

// CSRF token
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .staff-container { display: flex; min-height: 100vh; }
        .staff-sidebar {
            width: 250px; background: #1a5276; color: white; position: fixed;
            top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 100;
        }
        .staff-sidebar .sidebar-header { padding: 25px 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .staff-sidebar .sidebar-header img { width: 64px; height: 64px; border-radius: 50%; background: white; padding: 3px; margin-bottom: 10px; object-fit: cover; }
        .staff-sidebar .sidebar-header h3 { font-size: 15px; margin: 0; }
        .staff-sidebar .sidebar-header p { font-size: 12px; opacity: 0.8; margin: 5px 0 0; }
        .staff-sidebar ul { list-style: none; padding: 0; margin: 0; }
        .staff-sidebar ul li { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .staff-sidebar ul li a { display: block; padding: 14px 20px; color: rgba(255,255,255,0.85); text-decoration: none; font-size: 14px; transition: all 0.2s; }
        .staff-sidebar ul li a:hover, .staff-sidebar ul li a.active { background: rgba(255,255,255,0.1); color: white; padding-left: 25px; }
        .staff-sidebar ul li a i { width: 22px; text-align: center; margin-right: 8px; }
        .staff-sidebar .msg-count { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: #e74c3c; color: white; padding: 1px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }
        .staff-main { flex: 1; padding: 30px; background: #f4f6f9; margin-left: 250px; }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: center; justify-content: space-between; }
        .top-bar h2 { font-size: 20px; margin: 0; color: #1a5276; }
        .profile-section { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 25px; margin-bottom: 25px; }
        .profile-section h3 { font-size: 16px; color: #1a5276; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .info-grid .item { font-size: 14px; }
        .info-grid .item .label { color: #888; display: block; font-size: 12px; }
        .info-grid .item .value { font-weight: 600; color: #333; }
        .profile-pic-section { text-align: center; padding: 20px; }
        .profile-pic-section img { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid #1a5276; margin-bottom: 10px; }
        .profile-pic-section .upload-label { display: inline-block; padding: 8px 20px; background: #1a5276; color: white; border-radius: 6px; cursor: pointer; font-size: 14px; transition: background 0.2s; }
        .profile-pic-section .upload-label:hover { background: #154360; }
        .profile-pic-section .upload-label i { margin-right: 5px; }
        .alert { padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        @media (max-width: 768px) {
            .staff-sidebar { left: -250px; transition: left 0.3s; }
            .staff-sidebar.open { left: 0; }
            .staff-main { margin-left: 0; padding: 20px; }
            .top-bar { flex-direction: column; text-align: center; }
            .info-grid { grid-template-columns: 1fr; }
        }
        .hamburger-menu { display: none; position: fixed; top: 15px; left: 15px; z-index: 200; background: #1a5276; color: white; border: none; width: 40px; height: 40px; border-radius: 8px; font-size: 18px; cursor: pointer; }
        @media (max-width: 768px) { .hamburger-menu { display: block; } }
    </style>
</head>
<body>
    <?php echo renderStaffSidebar('profile', $school_name, $unread_count, $profile_pic, $staff['full_name'] ?? ''); ?>

    <div class="staff-main">
        <div class="top-bar">
            <div>
                <h2>My Profile</h2>
                <p class="subtitle" style="font-size:13px;color:#888;margin:3px 0 0;"><?php echo htmlspecialchars($staff['position'] ?? ''); ?> &bull; <?php echo htmlspecialchars($staff['department'] ?? 'General'); ?></p>
            </div>
            <span style="font-size:13px;color:#888;">Staff ID: <?php echo htmlspecialchars($staff['staff_id'] ?? 'N/A'); ?></span>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Profile Picture -->
        <div class="profile-section">
            <h3><i class="fas fa-camera"></i> Profile Picture</h3>
            <div class="profile-pic-section">
                <form method="POST" enctype="multipart/form-data" id="pictureForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <img id="profilePreview" src="<?php echo htmlspecialchars(resolve_storage_url($profile_pic, 'images/aamusted.jpg')); ?>" alt="Profile Picture">
                    <div style="margin-top:8px;">
                        <label for="profile_picture" class="upload-label"><i class="fas fa-camera"></i> Change Picture</label>
                        <input type="file" name="profile_picture" id="profile_picture" style="display:none;" accept="image/*">
                    </div>
                    <p style="font-size:12px;color:#888;margin-top:8px;">Max 2MB. JPG, PNG, GIF, or WebP.</p>
                </form>
            </div>
        </div>

        <!-- Personal Information -->
        <div class="profile-section">
            <h3><i class="fas fa-user"></i> Personal Information</h3>
            <div class="info-grid">
                <div class="item"><span class="label">Full Name</span><span class="value"><?php echo htmlspecialchars($staff['full_name'] ?? ''); ?></span></div>
                <div class="item"><span class="label">Staff ID</span><span class="value"><?php echo htmlspecialchars($staff['staff_id'] ?? ''); ?></span></div>
                <div class="item"><span class="label">Gender</span><span class="value"><?php echo htmlspecialchars($staff['gender'] ?? 'N/A'); ?></span></div>
                <div class="item"><span class="label">Date of Birth</span><span class="value"><?php echo htmlspecialchars($staff['date_of_birth'] ?? 'N/A'); ?></span></div>
                <div class="item"><span class="label">Address</span><span class="value"><?php echo htmlspecialchars($staff['address'] ?? 'N/A'); ?></span></div>
            </div>
        </div>

        <!-- Employment Information -->
        <div class="profile-section">
            <h3><i class="fas fa-briefcase"></i> Employment Information</h3>
            <div class="info-grid">
                <div class="item"><span class="label">Position</span><span class="value"><?php echo htmlspecialchars($staff['position'] ?? ''); ?></span></div>
                <div class="item"><span class="label">Department</span><span class="value"><?php echo htmlspecialchars($staff['department'] ?? 'N/A'); ?></span></div>
                <div class="item"><span class="label">Qualification</span><span class="value"><?php echo htmlspecialchars($staff['qualification'] ?? 'N/A'); ?></span></div>
                <div class="item"><span class="label">Hire Date</span><span class="value"><?php echo htmlspecialchars($staff['hire_date'] ?? 'N/A'); ?></span></div>
                <div class="item"><span class="label">Status</span><span class="value"><span class="badge badge-success"><?php echo htmlspecialchars(ucfirst($staff['status'] ?? 'Active')); ?></span></span></div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="profile-section">
            <h3><i class="fas fa-address-card"></i> Contact Information</h3>
            <div class="info-grid">
                <div class="item"><span class="label">Email</span><span class="value"><?php echo htmlspecialchars($staff['email'] ?? htmlspecialchars($user['email'] ?? '')); ?></span></div>
                <div class="item"><span class="label">Phone</span><span class="value"><?php echo htmlspecialchars($staff['phone'] ?? 'N/A'); ?></span></div>
            </div>
        </div>

        <!-- Bank Information -->
        <?php if (!empty($staff['bank_name']) || !empty($staff['account_number'])): ?>
        <div class="profile-section">
            <h3><i class="fas fa-piggy-bank"></i> Bank Information</h3>
            <div class="info-grid">
                <div class="item"><span class="label">Bank Name</span><span class="value"><?php echo htmlspecialchars($staff['bank_name'] ?? 'N/A'); ?></span></div>
                <div class="item"><span class="label">Account Number</span><span class="value"><?php echo htmlspecialchars($staff['account_number'] ?? 'N/A'); ?></span></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Salary Structure -->
        <?php if ($salary): ?>
        <div class="profile-section">
            <h3><i class="fas fa-money-check-alt"></i> Salary Structure</h3>
            <div class="info-grid">
                <div class="item"><span class="label">Basic Salary</span><span class="value">GHS <?php echo number_format($salary['basic_salary'], 2); ?></span></div>
                <div class="item"><span class="label">Housing Allowance</span><span class="value">GHS <?php echo number_format($salary['housing_allowance'] ?? 0, 2); ?></span></div>
                <div class="item"><span class="label">Transport Allowance</span><span class="value">GHS <?php echo number_format($salary['transport_allowance'] ?? 0, 2); ?></span></div>
                <div class="item"><span class="label">Other Allowances</span><span class="value">GHS <?php echo number_format($salary['other_allowances'] ?? 0, 2); ?></span></div>
                <div class="item"><span class="label">SSNIT Rate</span><span class="value"><?php echo $salary['ssnit_rate']; ?>%</span></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    // Auto-submit form when file is selected
    document.getElementById('profile_picture').addEventListener('change', function() {
        if (this.files && this.files[0]) {
            // Show preview immediately
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profilePreview').src = e.target.result;
            };
            reader.readAsDataURL(this.files[0]);
            // Submit form
            document.getElementById('pictureForm').submit();
        }
    });
    </script>
</body>
</html>
