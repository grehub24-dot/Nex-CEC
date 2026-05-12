<?php
require_once 'includes/db.php';

if (!isLoggedIn() || !isParent()) {
    redirect('../login.php');
}

$parent_user_id = $_SESSION['user_id'];
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$student_id) {
    redirect('dashboard.php');
}

// Verify ownership
try {
    $stmt = $pdo->prepare("SELECT student_id FROM parent_students WHERE parent_user_id = ? AND student_id = ?");
    $stmt->execute([$parent_user_id, $student_id]);
    if (!$stmt->fetch()) {
        redirect('dashboard.php');
    }
} catch (Exception $e) {
    redirect('dashboard.php');
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
if (!$student) redirect('dashboard.php');

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
        .top-bar {
            background: #1a5276; color: white; padding: 15px 30px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .top-bar a { color: white; text-decoration: none; font-size: 14px; }
        .container { max-width: 800px; margin: 0 auto; padding: 30px 20px; }
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
    <div class="top-bar">
        <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <span>Report Cards — <?php echo htmlspecialchars($student['full_name'] ?? ''); ?></span>
    </div>

    <div class="container">
        <div style="margin-bottom: 20px;">
            <a href="student.php?id=<?php echo $student_id; ?>" class="btn btn-back"><i class="fas fa-eye"></i> View Profile</a>
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
                                <?php if (!empty($rc['pdf_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($rc['pdf_path']); ?>" class="btn btn-download" target="_blank">
                                        <i class="fas fa-download"></i> PDF
                                    </a>
                                <?php endif; ?>
                                <a href="javascript:void(0)" onclick="alert('Detailed report view coming soon.')" class="btn btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 10px;">
            <a href="student.php?id=<?php echo $student_id; ?>" class="btn btn-back"><i class="fas fa-arrow-left"></i> Back to Student</a>
            <a href="dashboard.php" class="btn btn-view"><i class="fas fa-home"></i> Dashboard</a>
        </div>
    </div>
</body>
</html>
