<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn() || !isParentOrDual()) {
    redirect('../login.php');
}

$parent_user_id = $_SESSION['user_id'];
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$student_id) {
    redirect('parent/dashboard.php');
}

// Verify this parent owns this student
try {
    $stmt = $pdo->prepare("SELECT student_id FROM parent_students WHERE parent_user_id = ? AND student_id = ?");
    $stmt->execute([$parent_user_id, $student_id]);
    if (!$stmt->fetch()) {
        redirect('parent/dashboard.php');
    }
} catch (Exception $e) {
    redirect('parent/dashboard.php');
}

// Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';
$current_academic_year = $settings['current_academic_year'] ?? date('Y') . '/' . (date('Y') + 1);

// Fetch student
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    redirect('parent/dashboard.php');
}

// Fetch payments
$payments = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? ORDER BY payment_date DESC");
    $stmt->execute([$student_id]);
    $payments = $stmt->fetchAll();
} catch (Exception $e) {}
$total_paid = array_sum(array_map(fn($p) => (float)($p['amount'] ?? 0), $payments));

// Fetch attendance
$attendance = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM student_attendance WHERE student_id = ? ORDER BY attendance_date DESC LIMIT 20");
    $stmt->execute([$student_id]);
    $attendance = $stmt->fetchAll();
} catch (Exception $e) {}
$present_count = count(array_filter($attendance, fn($a) => ($a['status'] ?? '') === 'present'));
$absent_count = count(array_filter($attendance, fn($a) => ($a['status'] ?? '') === 'absent'));

$initial = strtoupper(substr($student['full_name'] ?? '?', 0, 1));

// Handle child profile picture upload
$upload_message = '';
$upload_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_child_photo'])) {
    if (isset($_FILES['child_photo']) && $_FILES['child_photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['child_photo'];
        // Validate file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            $upload_error = "File too large. Maximum size is 2MB.";
        } else {
            // Validate MIME type
            $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($mime, $allowed_mime) || !in_array($ext, $allowed_ext)) {
                $upload_error = "Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.";
            } else {
                $objectName = 'student_' . $student_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $url = upload_to_supabase_storage($file, 'profiles', $objectName, '');
                if ($url !== '' && strpos($url, 'http') === 0) {
                    $stmt = $pdo->prepare("UPDATE students SET profile_picture = ? WHERE id = ?");
                    $stmt->execute([$url, $student_id]);
                    $student['profile_picture'] = $url;
                    $upload_message = "Profile picture updated successfully.";
                } else {
                    $upload_error = "Failed to upload image.";
                }
            }
        }
    } else {
        $upload_error = "No image selected or upload error.";
    }
}

// Fetch unread message count for sidebar badge
$unread_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM messages m WHERE (m.receiver_id = ? OR m.is_broadcast = 1) AND NOT EXISTS (SELECT 1 FROM message_reads mr WHERE mr.message_id = m.id AND mr.user_id = ?)");
    $stmt->execute([$parent_user_id, $parent_user_id]);
    $row = $stmt->fetch();
    $unread_count = (int)($row['cnt'] ?? 0);
} catch (Exception $e) {
    error_log("Unread count error: " . $e->getMessage());
}

// Fetch parent profile picture for sidebar
$parent_profile_pic = null;
try {
    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->execute([$parent_user_id]);
    $row = $stmt->fetch();
    if ($row && !empty($row['profile_picture'])) {
        $parent_profile_pic = $row['profile_picture'];
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?> — Parent Portal</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; color: #333; }
        .parent-container { display: flex; min-height: 100vh; }
        .parent-main { flex: 1; padding: 30px; background: #f4f6f9; margin-left: 250px; }
        .parent-sidebar {
            width: 250px; background: #1a5276; color: white; position: fixed;
            top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 100;
        }
        .parent-sidebar .sidebar-header { padding: 25px 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .parent-sidebar .sidebar-header img { width: 64px; height: 64px; border-radius: 50%; background: white; padding: 3px; margin-bottom: 10px; }
        .parent-sidebar .sidebar-header h3 { font-size: 15px; margin: 0; }
        .parent-sidebar .sidebar-header p { font-size: 12px; opacity: 0.8; margin: 5px 0 0; }
        .parent-sidebar ul { list-style: none; padding: 0; margin: 0; }
        .parent-sidebar ul li { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .parent-sidebar ul li a {
            display: block; padding: 14px 20px; color: rgba(255,255,255,0.85); text-decoration: none;
            font-size: 14px; transition: all 0.2s; position: relative;
        }
        .parent-sidebar ul li a:hover, .parent-sidebar ul li a.active { background: rgba(255,255,255,0.1); color: white; padding-left: 25px; }
        .parent-sidebar ul li a i { width: 22px; text-align: center; margin-right: 8px; }
        .parent-sidebar .msg-count {
            position: absolute; right: 15px; top: 50%; transform: translateY(-50%);
            background: #e74c3c; color: white; padding: 1px 8px;
            border-radius: 10px; font-size: 11px; font-weight: 700; line-height: 1.5;
            min-width: 20px; text-align: center;
        }
        .hamburger-menu { display: none; position: fixed; top: 15px; left: 15px; z-index: 200;
            background: #1a5276; color: white; border: none; width: 40px; height: 40px;
            border-radius: 8px; font-size: 18px; cursor: pointer;
        }
        @media (max-width: 768px) {
            .parent-sidebar { left: -250px; transition: left 0.3s; }
            .parent-sidebar.open { left: 0; }
            .parent-main { margin-left: 0; padding: 20px; }
            .hamburger-menu { display: block; }
        }
        .parent-content { max-width: 1000px; margin: 0 auto; }
        .page-header {
            background: white; border-radius: 12px; padding: 25px 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 25px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .page-header .student-info { display: flex; align-items: center; gap: 18px; }
        .page-header .avatar {
            width: 60px; height: 60px; border-radius: 50%; background: #1a5276;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 26px; font-weight: bold; flex-shrink: 0;
            overflow: hidden; position: relative;
        }
        .page-header .avatar img {
            width: 100%; height: 100%; object-fit: cover;
        }
        .page-header .avatar .overlay {
            position: absolute; bottom: 0; left: 0; right: 0;
            background: rgba(0,0,0,0.5); color: white; font-size: 18px;
            height: 0; display: flex; align-items: center; justify-content: center;
            transition: height 0.2s; cursor: pointer;
        }
        .page-header .avatar:hover .overlay { height: 50%; }
        .page-header .student-info h2 { font-size: 22px; color: #1a5276; margin: 0; }
        .page-header .student-info p { font-size: 14px; color: #888; margin: 3px 0 0; }
        .photo-upload-form { display: none; }
        .status-badge { padding: 5px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; }
        .status-active { background: #e6f7e6; color: #27ae60; }
        .status-pending { background: #fff3e0; color: #f39c12; }
        .status-rejected { background: #ffe6e6; color: #e74c3c; }
        .card {
            background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 25px; margin-bottom: 25px;
        }
        .card h3 {
            font-size: 16px; color: #1a5276; margin-bottom: 18px;
            padding-bottom: 10px; border-bottom: 2px solid #1a5276;
        }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .info-grid .item { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .info-grid .item .label { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-grid .item .value { font-size: 15px; font-weight: 600; color: #333; margin-top: 2px; }
        .stat-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card {
            background: white; border-radius: 10px; padding: 20px; text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .stat-card .num { font-size: 28px; font-weight: bold; color: #1a5276; }
        .stat-card .lbl { font-size: 13px; color: #888; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        table th { background: #f8f9fa; color: #555; font-weight: 600; font-size: 13px; }
        .text-green { color: #27ae60; }
        .text-red { color: #e74c3c; }
        .btn-back {
            display: inline-block; padding: 10px 20px; background: #1a5276;
            color: white; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 600;
        }
        .btn-back:hover { background: #143c58; }
        .attendance-bar {
            display: flex; gap: 15px; align-items: center; margin-top: 10px;
        }
        .attendance-bar .bar {
            flex: 1; height: 12px; background: #f0f0f0; border-radius: 6px; overflow: hidden;
        }
        .attendance-bar .bar .fill { height: 100%; border-radius: 6px; transition: width 0.3s; }
        .attendance-bar .bar .fill.present { background: #27ae60; }
        .attendance-bar .bar .fill.absent { background: #e74c3c; }
        @media (max-width: 600px) {
            .info-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; text-align: center; gap: 10px; }
        }
    </style>
</head>
<body>
<div class="parent-container">
    <?php
    $profile_pic_path = $parent_profile_pic ? '../' . htmlspecialchars($parent_profile_pic) : '';
    echo renderParentSidebar('student', $school_name, $unread_count, $profile_pic_path, !empty($_SESSION['has_children']));
    ?>
    <div class="parent-main">
    <div class="parent-content">
        <!-- Upload message -->
        <?php if ($upload_message): ?>
            <div class="alert alert-success" style="margin-bottom: 15px;"><?php echo htmlspecialchars($upload_message); ?></div>
        <?php endif; ?>
        <?php if ($upload_error): ?>
            <div class="alert alert-danger" style="margin-bottom: 15px;"><?php echo htmlspecialchars($upload_error); ?></div>
        <?php endif; ?>

        <!-- Hidden upload form (submitted via JS) -->
        <form method="POST" enctype="multipart/form-data" class="photo-upload-form" id="childPhotoForm">
            <input type="hidden" name="upload_child_photo" value="1">
            <input type="file" name="child_photo" id="childPhotoInput" accept="image/*">
        </form>

        <!-- Page Header -->
        <div class="page-header">
            <div class="student-info">
                <div class="avatar" id="childAvatarContainer">
                    <?php if (!empty($student['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars(resolve_storage_url($student['profile_picture'] ?? null)); ?>" alt="Profile" id="childAvatarImg">
                    <?php else: ?>
                        <span id="childAvatarInitial"><?php echo htmlspecialchars($initial); ?></span>
                    <?php endif; ?>
                    <div class="overlay" onclick="document.getElementById('childPhotoInput').click();">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
                <div>
                    <h2><?php echo htmlspecialchars($student['full_name'] ?? 'Unknown'); ?></h2>
                    <p>
                        <?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?>
                        <?php if (!empty($student['admission_number'])): ?>
                            &bull; <?php echo htmlspecialchars($student['admission_number']); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <span class="status-badge status-<?php echo $student['status'] ?? 'pending'; ?>">
                <?php echo ucfirst($student['status'] ?? 'Pending'); ?>
            </span>
        </div>

        <!-- Stats -->
        <div class="stat-cards">
            <div class="stat-card">
                <div class="num">GHS <?php echo number_format($total_paid, 2); ?></div>
                <div class="lbl">Total Fees Paid</div>
            </div>
            <div class="stat-card">
                <div class="num"><?php echo count($payments); ?></div>
                <div class="lbl">Payments Made</div>
            </div>
            <div class="stat-card">
                <div class="num"><?php echo $present_count; ?>/<?php echo count($attendance); ?></div>
                <div class="lbl">Days Present</div>
            </div>
        </div>

        <!-- Student Information -->
        <div class="card">
            <h3><i class="fas fa-user"></i> Student Information</h3>
            <div class="info-grid">
                <div class="item">
                    <div class="label">Full Name</div>
                    <div class="value"><?php echo htmlspecialchars($student['full_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="item">
                    <div class="label">Date of Birth</div>
                    <div class="value"><?php echo htmlspecialchars($student['date_of_birth'] ?? 'N/A'); ?></div>
                </div>
                <div class="item">
                    <div class="label">Gender</div>
                    <div class="value"><?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></div>
                </div>
                <div class="item">
                    <div class="label">Class</div>
                    <div class="value"><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="item">
                    <div class="label">Enrollment Reference</div>
                    <div class="value"><?php echo htmlspecialchars($student['enrollment_id'] ?? 'N/A'); ?></div>
                </div>
                <div class="item">
                    <div class="label">Admission Number</div>
                    <div class="value"><?php echo htmlspecialchars($student['admission_number'] ?? 'N/A'); ?></div>
                </div>
                <div class="item">
                    <div class="label">Academic Year</div>
                    <div class="value"><?php echo htmlspecialchars($student['academic_year'] ?? $current_academic_year); ?></div>
                </div>
                <div class="item">
                    <div class="label">Payment Status</div>
                    <div class="value"><?php echo htmlspecialchars(ucfirst($student['payment_status'] ?? 'Unpaid')); ?></div>
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <div class="card">
            <h3><i class="fas fa-money-bill-wave"></i> Payment History</h3>
            <?php if (empty($payments)): ?>
                <p style="color: #888; font-size: 14px; text-align: center; padding: 20px;">No payments recorded yet.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Receipt</th>
                                <th>Fee Type</th>
                                <th>Amount (GHS)</th>
                                <th>Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($p['payment_date'] ?? $p['created_at'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($p['receipt_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($p['fee_type'] ?? 'General'); ?></td>
                                    <td><strong><?php echo number_format((float)($p['amount'] ?? 0), 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars($p['payment_method'] ?? 'N/A'); ?></td>
                                    <td><span class="text-green"><?php echo htmlspecialchars($p['status'] ?? 'completed'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Attendance Summary -->
        <div class="card">
            <h3><i class="fas fa-calendar-check"></i> Attendance Summary (Last 20 Records)</h3>
            <?php if (empty($attendance)): ?>
                <p style="color: #888; font-size: 14px; text-align: center; padding: 20px;">No attendance records available.</p>
            <?php else: ?>
                <?php
                $total_att = count($attendance);
                $present_pct = $total_att > 0 ? round(($present_count / $total_att) * 100) : 0;
                $absent_pct = $total_att > 0 ? round(($absent_count / $total_att) * 100) : 0;
                ?>
                <div class="attendance-bar">
                    <span style="font-size: 13px; color: #27ae60; font-weight: 600;">Present: <?php echo $present_count; ?></span>
                    <div class="bar">
                        <div class="fill present" style="width: <?php echo $present_pct; ?>%;"></div>
                    </div>
                    <span style="font-size: 13px; color: #e74c3c; font-weight: 600;">Absent: <?php echo $absent_count; ?></span>
                    <div class="bar">
                        <div class="fill absent" style="width: <?php echo $absent_pct; ?>%;"></div>
                    </div>
                </div>
                <div style="overflow-x: auto; margin-top: 15px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance as $a): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($a['attendance_date'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (($a['status'] ?? '') === 'present'): ?>
                                            <span class="text-green"><i class="fas fa-check-circle"></i> Present</span>
                                        <?php else: ?>
                                            <span class="text-red"><i class="fas fa-times-circle"></i> <?php echo htmlspecialchars(ucfirst($a['status'] ?? 'Absent')); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($a['reason'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div style="display: flex; gap: 15px; justify-content: center; margin-top: 10px;">
            <a href="../parent/fees.php?id=<?php echo $student_id; ?>" class="btn-back" style="background: #27ae60;">
                <i class="fas fa-money-bill"></i> View Full Fee Statement
            </a>
            <a href="../parent/report_card.php?id=<?php echo $student_id; ?>" class="btn-back" style="background: #f39c12;">
                <i class="fas fa-clipboard"></i> View Report Card
            </a>
            <a href="../parent/dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>
</div>

    <script>
        // Auto-upload child profile picture when file is selected
        var childPhotoInput = document.getElementById('childPhotoInput');
        var childPhotoForm = document.getElementById('childPhotoForm');
        var avatarContainer = document.getElementById('childAvatarContainer');
        var avatarImg = document.getElementById('childAvatarImg');
        var avatarInitial = document.getElementById('childAvatarInitial');

        if (childPhotoInput && childPhotoForm) {
            childPhotoInput.addEventListener('change', function() {
                var file = this.files && this.files[0];
                if (!file) return;
                if (!file.type.startsWith('image/')) {
                    alert('Please select an image file.');
                    this.value = '';
                    return;
                }
                // Show preview immediately
                var reader = new FileReader();
                reader.onload = function(e) {
                    if (avatarImg) {
                        avatarImg.src = e.target.result;
                        avatarImg.style.display = 'block';
                        if (avatarInitial) avatarInitial.style.display = 'none';
                    } else {
                        // Create img element if it doesn't exist
                        var img = document.createElement('img');
                        img.id = 'childAvatarImg';
                        img.src = e.target.result;
                        img.alt = 'Profile';
                        if (avatarInitial) {
                            avatarInitial.style.display = 'none';
                            avatarInitial.parentNode.insertBefore(img, avatarInitial);
                        }
                    }
                };
                reader.readAsDataURL(file);
                // Auto-submit the form
                childPhotoForm.submit();
            });
        }
    </script>
</body>
</html>
