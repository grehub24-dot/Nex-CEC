<?php
require_once 'includes/db.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$settings = fetchSettings($pdo);
$school_name = $settings['school_name'] ?? 'Nex CEC';
$school_motto = $settings['school_motto'] ?? 'Education for Excellence';
$school_address = $settings['school_address'] ?? 'Kumasi, Ghana';
$school_logo = $settings['school_logo'] ?? '';

// Determine which student to show
$student_id = 0;
if (isStudent()) {
    $student_id = (int)($_SESSION['student_id'] ?? 0);
} elseif (isParent() || (isset($_SESSION['has_children']) && $_SESSION['has_children'])) {
    $student_id = (int)($_GET['student_id'] ?? 0);
} elseif (isAdmin()) {
    $student_id = (int)($_GET['student_id'] ?? 0);
}

if ($student_id <= 0) {
    echo '<div style="text-align:center;padding:100px 20px;font-family:sans-serif;"><h2>Invalid student ID</h2><a href="javascript:history.back()" style="color:#1a5276;">Go Back</a></div>';
    exit;
}

// Fetch student
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();
if (!$student) {
    echo '<div style="text-align:center;padding:100px 20px;font-family:sans-serif;"><h2>Student not found</h2></div>';
    exit;
}

// Get terms
$terms = $pdo->query("SELECT * FROM terms ORDER BY id ASC")->fetchAll();
$selected_term_id = (int)($_GET['term_id'] ?? ($terms[0]['id'] ?? 0));

$selected_term = null;
foreach ($terms as $t) {
    if ($t['id'] == $selected_term_id) { $selected_term = $t; break; }
}
if (!$selected_term) {
    echo '<div style="text-align:center;padding:100px 20px;font-family:sans-serif;"><h2>Invalid term</h2></div>';
    exit;
}

$class_name = $student['class_name'] ?? '';

// Subject category mapping
$class_category_map = [
    'Creche'  => 'creche', 'Nursery 1' => 'nursery', 'Nursery 2' => 'nursery',
    'KG 1'    => 'kindergarten', 'KG 2'    => 'kindergarten',
    'Basic 1' => 'primary', 'Basic 2' => 'primary', 'Basic 3' => 'primary',
    'Basic 4' => 'primary', 'Basic 5' => 'primary', 'Basic 6' => 'primary',
    'JHS 1'   => 'jhs',     'JHS 2'   => 'jhs',     'JHS 3'   => 'jhs',
];
$subject_category_mapping = [];
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute(['subject_categories']);
    $row = $stmt->fetch();
    if ($row && !empty($row['setting_value'])) {
        $decoded = json_decode($row['setting_value'], true);
        if (is_array($decoded)) { $subject_category_mapping = $decoded; }
    }
} catch (Exception $e) {}

// Get subjects
$allSubj = $pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();
$subjects = $allSubj;
$category_key = $class_category_map[$class_name] ?? null;
if ($category_key && isset($subject_category_mapping[$category_key]) && !empty($subject_category_mapping[$category_key])) {
    $allowed_ids = array_map('intval', $subject_category_mapping[$category_key]);
    $filtered = array_filter($allSubj, function($s) use ($allowed_ids) {
        return in_array((int)$s['id'], $allowed_ids);
    });
    if (!empty($filtered)) { $subjects = $filtered; }
}

// Get SBA scores
$sba_scores = [];
$stmt = $pdo->prepare("SELECT * FROM sba_scores WHERE student_id = ? AND term_id = ?");
$stmt->execute([$student_id, $selected_term_id]);
while ($row = $stmt->fetch()) { $sba_scores[$row['subject_id']] = $row; }

// Get grades
$grades = $pdo->query("SELECT * FROM grade_boundaries ORDER BY min_score DESC")->fetchAll();

// Get attendance
$attendance = null;
if ($selected_term) {
    $term_month = (int)substr($selected_term['academic_year'], 0, 4);
    if (strpos($selected_term['name'], 'Term 1') !== false) $term_month = 9;
    elseif (strpos($selected_term['name'], 'Term 2') !== false) $term_month = 1;
    elseif (strpos($selected_term['name'], 'Term 3') !== false) $term_month = 5;
    $stmt = $pdo->prepare("SELECT * FROM attendance_summary WHERE student_id = ? AND month = ? AND year = ?");
    $stmt->execute([$student_id, $term_month, (int)substr($selected_term['academic_year'], 0, 4)]);
    $attendance = $stmt->fetch();
}

// Calculate results
$subject_results = [];
$total_sba = 0;
$subject_count = 0;

foreach ($subjects as $subj) {
    $sba = $sba_scores[$subj['id']] ?? null;
    $sba_total = $sba ? (float)$sba['class_test'] + (float)$sba['mid_term'] + (float)$sba['end_term'] + (float)$sba['project'] : 0;

    $grade = null;
    $remark = '-';
    foreach ($grades as $g) {
        if ($sba_total >= (int)$g['min_score'] && $sba_total <= (int)$g['max_score']) {
            $grade = $g['grade'];
            $remark = $g['remark'];
            break;
        }
    }

    $subject_results[] = [
        'name' => $subj['name'],
        'code' => $subj['code'],
        'class_test' => $sba ? $sba['class_test'] : 0,
        'mid_term' => $sba ? $sba['mid_term'] : 0,
        'end_term' => $sba ? $sba['end_term'] : 0,
        'project' => $sba ? $sba['project'] : 0,
        'total' => $sba_total,
        'grade' => $grade ?? '-',
        'remark' => $remark,
        'attitude' => $sba ? ($sba['attitude'] ?: '-') : '-',
        'interest' => $sba ? ($sba['interest'] ?: '-') : '-',
    ];

    if ($sba_total > 0) {
        $total_sba += $sba_total;
        $subject_count++;
    }
}
$average = $subject_count > 0 ? round($total_sba / $subject_count, 1) : 0;
$receiptNumber = 'RC-' . $student['admission_number'] . '-T' . $selected_term['name'] . '-' . $selected_term_id;

// Convert logo to base64 for PDF HTML
$logoData = '';
$logoPath = __DIR__ . '/../images/school-logo.png';
if (file_exists($logoPath)) {
    $type = pathinfo($logoPath, PATHINFO_EXTENSION);
    $data = file_get_contents($logoPath);
    $logoData = 'data:image/' . $type . ';base64,' . base64_encode($data);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Card PDF — <?php echo htmlspecialchars($school_name); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f4f4;
            padding: 30px;
        }
        .report-card {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border: 2px solid #1a5276;
            border-radius: 8px;
            padding: 35px;
        }
        .report-header {
            text-align: center;
            border-bottom: 3px solid #1a5276;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .report-header .logo { width: 80px; height: auto; margin-bottom: 10px; }
        .report-header h1 { color: #1a5276; margin: 0 0 5px 0; font-size: 24px; }
        .report-header .motto { color: #666; font-style: italic; margin: 5px 0; }
        .report-header h2 { color: #2e86c1; margin: 0; font-size: 1.3rem; }
        .student-info { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .student-info div { padding: 3px 0; font-size: 14px; }
        .student-info strong { color: #1a5276; }
        .scores-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .scores-table th { background: #1a5276; color: #fff; padding: 10px 8px; text-align: center; font-size: 0.85rem; }
        .scores-table td { padding: 8px; text-align: center; border-bottom: 1px solid #eee; font-size: 0.9rem; }
        .scores-table td:nth-child(2) { text-align: left; }
        .scores-table .total-row { background: #f0f7ff; font-weight: bold; }
        .grading-key { display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; font-size: 0.85rem; margin-top: 20px; }
        .grading-key div { padding: 3px 8px; background: #f8f9fa; border-radius: 4px; }
        .remarks-section { margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; }
        .remarks-section .remark-block { margin-bottom: 15px; }
        .remarks-section .remark-block strong { display: block; margin-bottom: 5px; }
        .signatures { margin-top: 15px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
        .signature-line { border-top: 1px solid #333; padding-top: 5px; text-align: center; margin-top: 60px; font-size: 0.85rem; color: #666; }
        .no-print { margin: 0 auto 15px auto; max-width: 900px; text-align: right; }
        .no-print .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; }
        .btn-pdf { background: #1a5276; color: white; }
        .btn-print { background: #27ae60; color: white; margin-right: 8px; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; }
            .report-card { border: none; margin: 0; padding: 20px; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        <button class="btn btn-pdf" onclick="downloadPDF()"><i class="fas fa-file-pdf"></i> Download PDF</button>
    </div>

    <div class="report-card" id="report-content">
        <div class="report-header">
            <?php if ($logoData): ?>
            <img src="<?php echo $logoData; ?>" alt="Logo" class="logo">
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($school_name); ?></h1>
            <p class="motto">"<?php echo htmlspecialchars($school_motto); ?>"</p>
            <p style="color:#666;margin:5px 0;"><?php echo htmlspecialchars($school_address); ?></p>
            <h2>TERMINAL REPORT CARD</h2>
        </div>

        <div class="student-info">
            <div><strong>Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></div>
            <div><strong>Index No:</strong> <?php echo htmlspecialchars($student['admission_number']); ?></div>
            <div><strong>Class:</strong> <?php echo htmlspecialchars($class_name); ?></div>
            <div><strong>Term:</strong> <?php echo htmlspecialchars($selected_term['name'] . ' ' . $selected_term['academic_year']); ?></div>
            <div><strong>Gender:</strong> <?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></div>
            <div><strong>Attendance:</strong> <?php echo $attendance ? $attendance['present'] . '/' . $attendance['total_school_days'] . ' days' : 'N/A'; ?></div>
        </div>

        <table class="scores-table">
            <thead>
                <tr>
                    <th style="text-align:left;width:25%;">Subject</th>
                    <th>Class Test (30)</th>
                    <th>Mid-Term (20)</th>
                    <th>End-Term (30)</th>
                    <th>Project (20)</th>
                    <th>Total (100)</th>
                    <th>Grade</th>
                    <th>Remark</th>
                    <th>Attitude</th>
                    <th>Interest</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subject_results as $result): ?>
                <tr>
                    <td style="text-align:left;"><strong><?php echo htmlspecialchars($result['name']); ?></strong></td>
                    <td><?php echo $result['class_test'] > 0 ? $result['class_test'] : '-'; ?></td>
                    <td><?php echo $result['mid_term'] > 0 ? $result['mid_term'] : '-'; ?></td>
                    <td><?php echo $result['end_term'] > 0 ? $result['end_term'] : '-'; ?></td>
                    <td><?php echo $result['project'] > 0 ? $result['project'] : '-'; ?></td>
                    <td><strong><?php echo $result['total'] > 0 ? $result['total'] : '-'; ?></strong></td>
                    <td><?php echo $result['grade']; ?></td>
                    <td><?php echo $result['remark']; ?></td>
                    <td><?php echo $result['attitude']; ?></td>
                    <td><?php echo $result['interest']; ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td style="text-align:left;" colspan="5"><strong>AVERAGE SCORE</strong></td>
                    <td colspan="2"><strong style="font-size:1.1rem;"><?php echo $average > 0 ? $average : '-'; ?></strong></td>
                    <td colspan="3"></td>
                </tr>
            </tbody>
        </table>

        <h4 style="margin-top:20px;">Grading Key</h4>
        <div class="grading-key">
            <?php foreach ($grades as $g): ?>
                <div><strong><?php echo $g['grade']; ?></strong> (<?php echo $g['min_score']; ?>-<?php echo $g['max_score']; ?>): <?php echo htmlspecialchars($g['remark']); ?></div>
            <?php endforeach; ?>
        </div>

        <div class="remarks-section">
            <div class="remark-block">
                <strong>Class Teacher's Remark:</strong>
                <p style="margin-top:5px;"><?php echo $average >= 70 ? 'An excellent performance. Keep it up!' : ($average >= 50 ? 'Good effort. You can do better with more dedication.' : 'Needs to put in more effort. Don\'t give up!'); ?></p>
            </div>
            <div class="remark-block">
                <strong>Head Teacher's Remark:</strong>
                <p style="margin-top:5px;"><?php echo $average >= 80 ? 'Outstanding student. A role model for others.' : ($average >= 60 ? 'A good student with room for improvement.' : 'Encouraged to seek extra help and improve.'); ?></p>
            </div>
            <div class="signatures">
                <div><div class="signature-line">Class Teacher's Signature</div></div>
                <div><div class="signature-line">Head Teacher's Signature</div></div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
    function downloadPDF() {
        const element = document.getElementById('report-content');
        const opt = {
            margin:       10,
            filename:     'Report_Card_<?php echo htmlspecialchars($student['admission_number']); ?>_Term<?php echo $selected_term['name']; ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }
    </script>
</body>
</html>
