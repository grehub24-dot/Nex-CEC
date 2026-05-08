<?php
require_once 'includes/db.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../login.php');
}

enforcePasswordReset();

$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
$school_name = $settings['school_name'] ?? 'Nex CEC';
$school_motto = $settings['school_motto'] ?? 'Education for Excellence';
$school_address = $settings['school_address'] ?? 'Kumasi, Ghana';

$student_id = $_SESSION['student_id'];

// Fetch student data
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// Get terms
$terms = $pdo->query("SELECT * FROM terms ORDER BY id ASC")->fetchAll();
$selected_term_id = (int)($_GET['term_id'] ?? ($terms[0]['id'] ?? 0));

$selected_term = null;
foreach ($terms as $t) {
    if ($t['id'] == $selected_term_id) { $selected_term = $t; break; }
}

// Get student's class
$class_name = $student['class_name'] ?? '';

// Get subjects for this class
$stmt = $pdo->prepare("SELECT * FROM subjects WHERE class_id = (SELECT id FROM classes WHERE name = ?) OR class_id IS NULL ORDER BY name ASC");
$stmt->execute([$class_name]);
$subjects = $stmt->fetchAll();

// Get SBA scores for this term
$sba_scores = [];
$stmt = $pdo->prepare("SELECT * FROM sba_scores WHERE student_id = ? AND term_id = ?");
$stmt->execute([$student_id, $selected_term_id]);
while ($row = $stmt->fetch()) {
    $sba_scores[$row['subject_id']] = $row;
}

// Get grading boundaries
$grades = $pdo->query("SELECT * FROM grade_boundaries ORDER BY min_score DESC")->fetchAll();

// Get attendance summary
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

// Calculate total and grade for each subject
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Card — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .report-card { max-width: 900px; margin: 30px auto; background: #fff; border: 2px solid #1a5276; border-radius: 8px; padding: 30px; }
        .report-header { text-align: center; border-bottom: 3px solid #1a5276; padding-bottom: 20px; margin-bottom: 20px; }
        .report-header h1 { color: #1a5276; margin: 0 0 5px 0; }
        .report-header .motto { color: #666; font-style: italic; margin: 5px 0; }
        .report-header h2 { color: #2e86c1; margin: 0; font-size: 1.3rem; }
        .student-info { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .student-info div { padding: 3px 0; }
        .student-info strong { color: #1a5276; }
        .scores-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .scores-table th { background: #1a5276; color: #fff; padding: 10px 8px; text-align: center; font-size: 0.85rem; }
        .scores-table td { padding: 8px; text-align: center; border-bottom: 1px solid #eee; font-size: 0.9rem; }
        .scores-table td:nth-child(2) { text-align: left; }
        .scores-table .total-row { background: #f0f7ff; font-weight: bold; }
        .grading-key { display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; font-size: 0.85rem; margin-top: 20px; }
        .grading-key div { padding: 3px 8px; background: #f8f9fa; border-radius: 4px; }
        @media print {
            .no-print { display: none; }
            .report-card { border: none; margin: 0; padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; padding: 20px;">
        <a href="dashboard.php" class="btn-login"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <button onclick="window.print()" class="btn-primary"><i class="fas fa-print"></i> Print Report Card</button>
        <select onchange="window.location.href='student_report_card.php?term_id='+this.value" class="form-control" style="width: 200px; display: inline-block; margin-left: 10px;">
            <option value="">-- Select Term --</option>
            <?php foreach ($terms as $t): ?>
                <option value="<?php echo $t['id']; ?>" <?php echo $selected_term_id == $t['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['name']); ?> (<?php echo htmlspecialchars($t['academic_year']); ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="report-card">
        <div class="report-header">
            <h1><?php echo htmlspecialchars($school_name); ?></h1>
            <p class="motto">"<?php echo htmlspecialchars($school_motto); ?>"</p>
            <p style="color: #666; margin: 5px 0;"><?php echo htmlspecialchars($school_address); ?></p>
            <h2>TERMINAL REPORT CARD</h2>
        </div>

        <div class="student-info">
            <div><strong>Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></div>
            <div><strong>Index No:</strong> <?php echo htmlspecialchars($student['admission_number']); ?></div>
            <div><strong>Class:</strong> <?php echo htmlspecialchars($class_name); ?></div>
            <div><strong>Term:</strong> <?php echo $selected_term ? htmlspecialchars($selected_term['name'] . ' ' . $selected_term['academic_year']) : 'N/A'; ?></div>
            <div><strong>Gender:</strong> <?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></div>
            <div><strong>Attendance:</strong> <?php echo $attendance ? $attendance['present'] . '/' . $attendance['total_school_days'] . ' days' : 'N/A'; ?></div>
        </div>

        <table class="scores-table">
            <thead>
                <tr>
                    <th style="text-align: left; width: 25%;">Subject</th>
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
                    <td style="text-align: left;"><strong><?php echo htmlspecialchars($result['name']); ?></strong></td>
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
                    <td style="text-align: left;" colspan="5"><strong>AVERAGE SCORE</strong></td>
                    <td colspan="2"><strong style="font-size: 1.1rem;"><?php echo $average > 0 ? $average : '-'; ?></strong></td>
                    <td colspan="3"></td>
                </tr>
            </tbody>
        </table>

        <!-- Grading Key -->
        <h4 style="margin-top: 20px;">Grading Key</h4>
        <div class="grading-key">
            <?php foreach ($grades as $g): ?>
                <div><strong><?php echo $g['grade']; ?></strong> (<?php echo $g['min_score']; ?>-<?php echo $g['max_score']; ?>): <?php echo htmlspecialchars($g['remark']); ?></div>
            <?php endforeach; ?>
        </div>

        <!-- Remarks Section -->
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <div style="margin-bottom: 15px;">
                <strong>Class Teacher's Remark:</strong>
                <p style="margin-top: 5px;"><?php echo $average >= 70 ? 'An excellent performance. Keep it up!' : ($average >= 50 ? 'Good effort. You can do better with more dedication.' : 'Needs to put in more effort. Don\'t give up!'); ?></p>
            </div>
            <div>
                <strong>Head Teacher's Remark:</strong>
                <p style="margin-top: 5px;"><?php echo $average >= 80 ? 'Outstanding student. A role model for others.' : ($average >= 60 ? 'A good student with room for improvement.' : 'Encouraged to seek extra help and improve.'); ?></p>
            </div>
            <div style="margin-top: 15px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
                <div>
                    <div class="signature-line">Class Teacher's Signature</div>
                </div>
                <div>
                    <div class="signature-line">Head Teacher's Signature</div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .signature-line { border-top: 1px solid #333; padding-top: 5px; text-align: center; margin-top: 60px; font-size: 0.85rem; color: #666; }
    </style>
</body>
</html>
