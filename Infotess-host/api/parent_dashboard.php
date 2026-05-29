<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn() || !isParentOrDual()) {
    redirect('../login.php');
}

$parent_user_id = $_SESSION['user_id'];

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

// Fetch all children linked to this parent via parent_students
// Two-step for bridge compatibility: bridge cannot handle JOINs
$children = [];
try {
    $stmt = $pdo->prepare("SELECT student_id, relationship, is_primary FROM parent_students WHERE parent_user_id = ?");
    $stmt->execute([$parent_user_id]);
    $links = $stmt->fetchAll();
    foreach ($links as $link) {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([(int)$link['student_id']]);
        $student = $stmt->fetch();
        if ($student) {
            $student['relationship'] = $link['relationship'];
            $student['is_primary'] = $link['is_primary'];
            $children[] = $student;
        }
    }
} catch (Exception $e) {
    error_log("Parent dashboard fetch error: " . $e->getMessage());
}

// Count stats
$total_children = count($children);
$active_count = 0;
$pending_count = 0;
foreach ($children as $c) {
    if (($c['status'] ?? '') === 'active') $active_count++;
    elseif (($c['status'] ?? '') === 'pending') $pending_count++;
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

// Fetch parent profile picture
$parent_profile_pic = null;
try {
    // First check users table for profile_picture
    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->execute([$parent_user_id]);
    $row = $stmt->fetch();
    if ($row && !empty($row['profile_picture'])) {
        $parent_profile_pic = $row['profile_picture'];
    }
    // Fallback: if user is also staff, check staff table
    if (empty($parent_profile_pic) && ($_SESSION['role'] ?? '') === 'staff') {
        $stmt = $pdo->prepare("SELECT profile_picture FROM staff WHERE user_id = ?");
        $stmt->execute([$parent_user_id]);
        $srow = $stmt->fetch();
        if ($srow && !empty($srow['profile_picture'])) {
            $parent_profile_pic = $srow['profile_picture'];
        }
    }
} catch (Exception $e) {
    // Column may not exist yet
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .parent-container { display: flex; min-height: 100vh; }
        .parent-main { flex: 1; padding: 30px; background: #f4f6f9; margin-left: 250px; }
        .parent-sidebar {
            width: 250px; background: #1a5276; color: white; position: fixed;
            top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 100;
        }
        .parent-sidebar .sidebar-header { padding: 25px 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .parent-sidebar .sidebar-header img.sidebar-profile-img { width: 64px; height: 64px; border-radius: 50%; background: white; padding: 3px; margin-bottom: 10px; object-fit: cover; }
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
        .top-bar {
            background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: center; justify-content: space-between;
        }
        .top-bar h2 { font-size: 20px; margin: 0; color: #1a5276; }
        .top-bar .subtitle { font-size: 13px; color: #888; margin: 3px 0 0; }
        .stat-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 18px; margin-bottom: 30px; }
        .stat-card {
            background: white; border-radius: 10px; padding: 22px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex; align-items: center; gap: 16px;
        }
        .stat-card .icon {
            width: 48px; height: 48px; border-radius: 12px; display: flex;
            align-items: center; justify-content: center; font-size: 22px;
        }
        .stat-card .icon.blue { background: #e8f0fe; color: #1a5276; }
        .stat-card .icon.green { background: #e6f7e6; color: #27ae60; }
        .stat-card .icon.orange { background: #fff3e0; color: #f39c12; }
        .stat-card .icon.purple { background: #f0e6ff; color: #8e44ad; }
        .stat-card .info h3 { font-size: 22px; margin: 0; }
        .stat-card .info p { font-size: 13px; color: #888; margin: 2px 0 0; }
        .child-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .child-card {
            background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow: hidden; transition: transform 0.2s, box-shadow 0.2s;
        }
        .child-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
        .child-card .card-header {
            padding: 20px; display: flex; align-items: center; gap: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        .child-card .card-header .avatar {
            width: 50px; height: 50px; border-radius: 50%; background: #1a5276;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 20px; font-weight: bold; flex-shrink: 0;
        }
        .child-card .card-header .avatar-img {
            width: 50px; height: 50px; border-radius: 50%; object-fit: cover;
            flex-shrink: 0; border: 2px solid #1a5276;
        }
        .child-card .card-header .info { flex: 1; }
        .child-card .card-header .info h4 { font-size: 16px; margin: 0; color: #1a5276; }
        .child-card .card-header .info p { font-size: 13px; color: #888; margin: 2px 0 0; }
        .child-card .card-body { padding: 15px 20px; }
        .child-card .card-body .detail-row {
            display: flex; justify-content: space-between; padding: 6px 0;
            font-size: 13px; border-bottom: 1px solid #f8f8f8;
        }
        .child-card .card-body .detail-row .label { color: #888; }
        .child-card .card-body .detail-row .value { font-weight: 600; }
        .child-card .card-footer {
            padding: 15px 20px; background: #fafafa; display: flex; gap: 10px;
        }
        .child-card .card-footer a {
            flex: 1; text-align: center; padding: 8px; border-radius: 6px;
            font-size: 13px; text-decoration: none; font-weight: 600; transition: all 0.2s;
        }
        .btn-view { background: #1a5276; color: white; }
        .btn-view:hover { background: #143c58; }
        .btn-fees { background: #27ae60; color: white; }
        .btn-fees:hover { background: #1e8449; }
        .btn-report { background: #f39c12; color: white; }
        .btn-report:hover { background: #d68910; }
        .status-badge {
            display: inline-block; padding: 3px 10px; border-radius: 12px;
            font-size: 11px; font-weight: 600;
        }
        .status-active { background: #e6f7e6; color: #27ae60; }
        .status-pending { background: #fff3e0; color: #f39c12; }
        .status-rejected { background: #ffe6e6; color: #e74c3c; }
        .status-inactive { background: #f0f0f0; color: #888; }
        .no-children {
            text-align: center; padding: 60px 20px; background: white;
            border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .no-children i { font-size: 48px; color: #ccc; margin-bottom: 15px; }
        .no-children h3 { color: #888; margin: 0 0 8px; }
        .no-children p { color: #aaa; font-size: 14px; margin: 0; }
        .hamburger-menu { display: none; position: fixed; top: 15px; left: 15px; z-index: 200;
            background: #1a5276; color: white; border: none; width: 40px; height: 40px;
            border-radius: 8px; font-size: 18px; cursor: pointer;
        }
        @media (max-width: 768px) {
            .parent-sidebar { left: -250px; transition: left 0.3s; }
            .parent-sidebar.open { left: 0; }
            .parent-main { margin-left: 0; padding: 20px; }
            .hamburger-menu { display: block; }
            .top-bar { margin-top: 50px; }
            .child-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php
    $profile_pic_path = $parent_profile_pic ? '../' . htmlspecialchars($parent_profile_pic) : '';
    echo renderParentSidebar('dashboard', $school_name, $unread_count, $profile_pic_path, !empty($_SESSION['has_children']));
    ?>

    <!-- Main Content -->
    <div class="parent-main">
        <div class="top-bar">
            <div style="display: flex; align-items: center; gap: 15px;">
                <?php if (!empty($parent_profile_pic)): ?>
                    <img src="../<?php echo htmlspecialchars($parent_profile_pic); ?>" alt="Profile" style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid #1a5276;" onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='inline-flex'">
                    <div style="width:52px;height:52px;border-radius:50%;background:#1a5276;color:#fff;display:none;align-items:center;justify-content:center;font-size:22px;font-weight:700;flex-shrink:0;"><i class="fas fa-user"></i></div>
                <?php else: ?>
                    <div style="width:52px;height:52px;border-radius:50%;background:#1a5276;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;flex-shrink:0;"><i class="fas fa-user"></i></div>
                <?php endif; ?>
                <div>
                    <h2>👋 Parent Dashboard</h2>
                    <p class="subtitle">Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Parent'); ?></p>
                </div>
            </div>
            <div>
                <span style="font-size: 13px; color: #888;"><?php echo htmlspecialchars($current_academic_year); ?></span>
            </div>
        </div>

        <!-- Stats -->
        <div class="stat-cards">
            <div class="stat-card">
                <div class="icon blue"><i class="fas fa-users"></i></div>
                <div class="info">
                    <h3><?php echo $total_children; ?></h3>
                    <p>Children Enrolled</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon green"><i class="fas fa-check-circle"></i></div>
                <div class="info">
                    <h3><?php echo $active_count; ?></h3>
                    <p>Active Students</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon orange"><i class="fas fa-clock"></i></div>
                <div class="info">
                    <h3><?php echo $pending_count; ?></h3>
                    <p>Pending Enrollments</p>
                </div>
            </div>
        </div>

        <!-- Children Cards -->
        <h3 style="font-size: 18px; color: #333; margin-bottom: 15px;">My Children</h3>

        <?php if (empty($children)): ?>
            <div class="no-children">
                <i class="fas fa-child"></i>
                <h3>No Children Linked Yet</h3>
                <p>Your account is not yet linked to any students. Contact the school administration.</p>
            </div>
        <?php else: ?>
            <div class="child-grid">
                <?php foreach ($children as $child):
                    $initial = strtoupper(substr($child['full_name'] ?? '?', 0, 1));
                    $status = $child['status'] ?? 'pending';
                    $status_class = 'status-' . $status;
                ?>
                    <div class="child-card">
                        <div class="card-header">
                            <?php if (!empty($child['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars(resolve_storage_url($child['profile_picture'] ?? null)); ?>" alt="Profile" class="avatar-img" id="child-avatar-<?php echo $child['id']; ?>" onerror="var fb=document.getElementById('child-avatar-fb-<?php echo $child['id']; ?>');if(fb){fb.style.display='flex';}this.style.display='none';">
                                <div class="avatar" id="child-avatar-fb-<?php echo $child['id']; ?>" style="display:none;"><?php echo htmlspecialchars($initial); ?></div>
                            <?php else: ?>
                                <div class="avatar"><?php echo htmlspecialchars($initial); ?></div>
                            <?php endif; ?>
                            <div class="info">
                                <h4><?php echo htmlspecialchars($child['full_name'] ?? 'Unknown'); ?></h4>
                                <p>
                                    <?php echo htmlspecialchars($child['class_name'] ?? 'No class'); ?>
                                    <?php if (!empty($child['admission_number'])): ?>
                                        &bull; <?php echo htmlspecialchars($child['admission_number']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($status); ?></span>
                        </div>
                        <div class="card-body">
                            <div class="detail-row">
                                <span class="label">Relationship</span>
                                <span class="value"><?php echo htmlspecialchars($child['relationship'] ?? 'Parent'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Gender</span>
                                <span class="value"><?php echo htmlspecialchars($child['gender'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Enrollment Ref</span>
                                <span class="value"><?php echo htmlspecialchars($child['enrollment_id'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Payment Status</span>
                                <span class="value"><?php echo htmlspecialchars(ucfirst($child['payment_status'] ?? 'Unpaid')); ?></span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="../parent/student.php?id=<?php echo $child['id']; ?>" class="btn-view">
                                <i class="fas fa-eye"></i> View Profile
                            </a>
                            <a href="../parent/fees.php?id=<?php echo $child['id']; ?>" class="btn-fees">
                                <i class="fas fa-money-bill"></i> Fees
                            </a>
                            <a href="../parent/report_card.php?id=<?php echo $child['id']; ?>" class="btn-report">
                                <i class="fas fa-clipboard"></i> Report
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
