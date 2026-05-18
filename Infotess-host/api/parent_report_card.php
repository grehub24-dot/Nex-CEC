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

// Verify ownership
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
if (!$student) redirect('parent/dashboard.php');

// Fetch report cards (two-step: no JOIN support in bridge)
$report_cards = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM report_cards WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $report_cards = $stmt->fetchAll();

    // Fetch terms for enrichment and dropdown
    $terms_raw = [];
    $stmt = $pdo->query("SELECT * FROM terms");
    $terms_raw = $stmt->fetchAll();
    $terms = $terms_raw; // keep full list for dropdown below

    $term_map = [];
    foreach ($terms_raw as $t) {
        $term_map[$t['id']] = $t;
    }

    // Enrich report cards with term name + academic year
    foreach ($report_cards as &$rc) {
        $term = $term_map[$rc['term_id']] ?? null;
        $rc['term_name'] = $term['name'] ?? 'N/A';
        $rc['academic_year'] = $term['academic_year'] ?? '';
    }
    unset($rc);

    // Sort by academic_year DESC, term_name DESC (bridge ignores ORDER BY)
    usort($report_cards, function ($a, $b) {
        $cmp = strcmp($b['academic_year'] ?? '', $a['academic_year'] ?? '');
        if ($cmp === 0) {
            return strcmp($b['term_name'] ?? '', $a['term_name'] ?? '');
        }
        return $cmp;
    });
} catch (Exception $e) {}

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
    <title>Report Cards — <?php echo htmlspecialchars($student['full_name'] ?? ''); ?></title>
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
        .parent-content-wrap { max-width: 800px; margin: 0 auto; }
        .card {
            background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 25px; margin-bottom: 25px;
        }
        .card h3 {
            font-size: 16px; color: #1a5276; margin-bottom: 18px;
            padding-bottom: 10px; border-bottom: 2px solid #1a5276;
        }
        .report-grid { display: grid; gap: 15px; }
        .report-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 15px 20px; background: #f8f9fa; border-radius: 8px;
            border-left: 4px solid #1a5276;
        }
        .report-item .info h4 { font-size: 15px; margin: 0; }
        .report-item .info p { font-size: 13px; color: #888; margin: 2px 0 0; }
        .report-item .actions { display: flex; gap: 8px; }
        .btn {
            display: inline-block; padding: 8px 16px; border-radius: 6px;
            font-size: 13px; font-weight: 600; text-decoration: none; transition: all 0.2s;
        }
        .btn-view { background: #1a5276; color: white; }
        .btn-view:hover { background: #143c58; }
        .btn-download { background: #27ae60; color: white; }
        .btn-download:hover { background: #1e8449; }
        .btn-back { background: #6c757d; color: white; }
        .btn-back:hover { background: #5a6268; }
        .no-data { text-align: center; padding: 40px; color: #888; }
        .no-data i { font-size: 48px; color: #ddd; margin-bottom: 15px; display: block; }
        @media (max-width: 600px) {
            .report-item { flex-direction: column; gap: 10px; text-align: center; }
        }
    </style>
</head>
<body>
<div class="parent-container">
    <?php
    $profile_pic_path = $parent_profile_pic ? '../' . htmlspecialchars($parent_profile_pic) : '';
    echo renderParentSidebar('report', $school_name, $unread_count, $profile_pic_path, !empty($_SESSION['has_children']));
    ?>
    <div class="parent-main">
    <div class="parent-content-wrap">
        <div style="margin-bottom: 20px;">
            <a href="../parent/student.php?id=<?php echo $student_id; ?>" class="btn btn-back"><i class="fas fa-eye"></i> View Profile</a>
        </div>

        <div class="card">
            <h3><i class="fas fa-clipboard"></i> Academic Reports — <?php echo htmlspecialchars($student['full_name'] ?? ''); ?></h3>
            <p style="font-size: 14px; color: #888; margin-bottom: 20px;">
                Class: <?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?>
                <?php if (!empty($student['admission_number'])): ?>
                    &bull; Adm: <?php echo htmlspecialchars($student['admission_number']); ?>
                <?php endif; ?>
            </p>

            <?php if (empty($report_cards)): ?>
                <div class="no-data">
                    <i class="fas fa-file-alt"></i>
                    <h4>No Report Cards Available</h4>
                    <p>Report cards will appear here once they are generated by the school.</p>
                </div>
            <?php else: ?>
                <div class="report-grid">
                    <?php foreach ($report_cards as $rc): ?>
                        <div class="report-item">
                            <div class="info">
                                <h4><?php echo htmlspecialchars($rc['term_name'] ?? 'N/A'); ?> — <?php echo htmlspecialchars($rc['academic_year'] ?? $current_academic_year); ?></h4>
                                <p>
                                    <?php if (!empty($rc['class_position'])): ?>
                                        Position: <?php echo (int)$rc['class_position']; ?>/<?php echo (int)($rc['total_students'] ?? 'N/A'); ?>
                                    <?php else: ?>
                                        Status: Generated
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="actions">
                                <a href="report_card_pdf.php?student_id=<?php echo $student_id; ?>&term_id=<?php echo $rc['term_id']; ?>" class="btn btn-download" target="_blank">
                                    <i class="fas fa-download"></i> PDF
                                </a>
                                <a href="report_card_pdf.php?student_id=<?php echo $student_id; ?>&term_id=<?php echo $rc['term_id']; ?>" class="btn btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 10px;">
            <a href="../parent/student.php?id=<?php echo $student_id; ?>" class="btn btn-back"><i class="fas fa-arrow-left"></i> Back to Student</a>
            <a href="../parent/dashboard.php" class="btn btn-view"><i class="fas fa-home"></i> Dashboard</a>
        </div>
    </div>
</div>
</div>
</body>
</html>
