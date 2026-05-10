<?php
require_once 'includes/db.php';

// Enforce access control
requireAccess('grades');

$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

// Get classes, terms, subjects (bridge ignores ORDER BY — sort in PHP)
$classes = $pdo->query("SELECT * FROM classes");
$classes = $classes ? $classes->fetchAll() : [];
usort($classes, function($a, $b) {
    return ((int)($a['sort_order'] ?? 0)) - ((int)($b['sort_order'] ?? 0));
});

$terms = $pdo->query("SELECT * FROM terms");
$terms = $terms ? $terms->fetchAll() : [];

$selected_class = $_GET['class_id'] ?? '';
$selected_term = $_GET['term_id'] ?? '';
$selected_subject = $_GET['subject_id'] ?? '';

// Get all subjects (bridge drops OR conditions in WHERE)
$all_subjects = $pdo->query("SELECT * FROM subjects");
$all_subjects = $all_subjects ? $all_subjects->fetchAll() : [];
usort($all_subjects, function($a, $b) {
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});
if ($selected_class) {
    $subjects = array_filter($all_subjects, fn($s) => empty($s['class_id']) || (int)$s['class_id'] === (int)$selected_class);
} else {
    $subjects = $all_subjects;
}

// Get students in selected class
$students = [];
if ($selected_class) {
    // Two-step lookup: bridge can't handle subquery in WHERE
    // NOTE: bridge ignores column list in SELECT; always use SELECT * and access by key.
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([(int)$selected_class]);
    $classRow = $stmt->fetch();
    $className = $classRow ? ($classRow['name'] ?? '') : '';
    if ($className) {
        // NOTE: bridge ignores column list and ORDER BY; sort in PHP after fetch.
        $stmt = $pdo->prepare("SELECT * FROM students WHERE class_name = ?");
        $stmt->execute([$className]);
        $students = $stmt->fetchAll();
        usort($students, function($a, $b) {
            return strcmp($a['full_name'] ?? '', $b['full_name'] ?? '');
        });
    }
}

// Handle Bulk Save SBA Scores
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_bulk_sba') {
    $subject_id = (int)$_POST['subject_id'];
    $term_id = (int)$_POST['term_id'];
    $class_name = sanitize($_POST['class_name']);
    
    try {
        $pdo->beginTransaction();
        $saved = 0;
        
        foreach ($_POST['scores'] as $student_id => $data) {
            $student_id = (int)$student_id;
            $individual_test = (float)($data['individual_test'] ?? 0);
            $class_test = (float)($data['class_test'] ?? 0);
            $end_term = (float)($data['end_term'] ?? 0);
            $attitude = sanitize($data['attitude'] ?? '');
            $interest = sanitize($data['interest'] ?? '');
            
            // Bridge doesn't support ON CONFLICT — use SELECT-then-UPDATE-or-INSERT
            // NOTE: sba_scores table has NO updated_at column — do not include it.
            $existing = $pdo->prepare("SELECT id FROM sba_scores WHERE student_id = ? AND subject_id = ? AND term_id = ?");
            $existing->execute([$student_id, $subject_id, $term_id]);
            if ($existing->fetch()) {
                $stmt = $pdo->prepare("UPDATE sba_scores SET class_test=?, mid_term=?, end_term=?, attitude=?, interest=? WHERE student_id=? AND subject_id=? AND term_id=?");
                $stmt->execute([$individual_test, $class_test, $end_term, $attitude, $interest, $student_id, $subject_id, $term_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO sba_scores (student_id, subject_id, term_id, class_test, mid_term, end_term, attitude, interest) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $subject_id, $term_id, $individual_test, $class_test, $end_term, $attitude, $interest]);
            }
            $saved++;
        }
        
        $pdo->commit();
        $message = "Scores saved for $saved students.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Get existing scores for bulk view
$existing_scores = [];
if ($selected_class && $selected_term && $selected_subject) {
    $stmt = $pdo->prepare("SELECT * FROM sba_scores WHERE subject_id = ? AND term_id = ?");
    $stmt->execute([$selected_subject, $selected_term]);
    while ($row = $stmt->fetch()) {
        $existing_scores[$row['student_id']] = $row;
    }
}

// Compute SBA calculations and positions
$sba_data = [];
if (!empty($students)) {
    foreach ($students as $st) {
        $s = $existing_scores[$st['id']] ?? null;
        $individual_test = (float)($s['class_test'] ?? 0);
        $class_test = (float)($s['mid_term'] ?? 0);
        $end_term = (float)($s['end_term'] ?? 0);
        
        $total_class_score = $individual_test + $class_test; // out of 60
        $scaled_60 = $total_class_score * 50 / 60; // scale 60 to 50%
        $scaled_100 = $end_term * 50 / 100; // scale 100 to 50%
        $overall_total = $scaled_60 + $scaled_100;
        
        $sba_data[$st['id']] = [
            'individual_test'    => $individual_test,
            'class_test'         => $class_test,
            'total_class_score'  => $total_class_score,
            'scaled_60'          => round($scaled_60, 1),
            'end_term'           => $end_term,
            'scaled_100'         => round($scaled_100, 1),
            'overall_total'      => round($overall_total, 1),
        ];
    }
    // Sort by overall_total DESC to assign positions
    $sorted = $sba_data;
    uasort($sorted, function($a, $b) {
        return $b['overall_total'] <=> $a['overall_total'];
    });
    $pos = 1;
    foreach ($sorted as $sid => $data) {
        $sba_data[$sid]['position'] = $pos++;
    }
}

// Get class name for bulk form
$class_name = '';
if ($selected_class) {
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([$selected_class]);
    $class_row = $stmt->fetch();
    $class_name = $class_row ? ($class_row['name'] ?? '') : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enter SBA Scores — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
            <?php echo renderSidebar('grades', $school_name); ?>

        <main class="main-content">
            <div class="top-bar">
                <h2>SBA / Exam Scores Entry</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Selection Form -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-content">
                    <form method="GET" action="grades.php" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                        <div>
                            <label><strong>Class</strong></label>
                            <select name="class_id" class="form-control" style="width: 200px;" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $selected_class == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label><strong>Term</strong></label>
                            <select name="term_id" class="form-control" style="width: 150px;" required>
                                <option value="">-- Select Term --</option>
                                <?php foreach ($terms as $t): ?>
                                    <option value="<?php echo $t['id']; ?>" <?php echo $selected_term == $t['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['name']); ?> (<?php echo htmlspecialchars($t['academic_year']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label><strong>Subject</strong></label>
                            <select name="subject_id" class="form-control" style="width: 200px;" required>
                                <option value="">-- Select Subject --</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo $selected_subject == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['code']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Load Students</button>
                    </form>
                </div>
            </div>

            <?php if ($selected_class && $selected_term && $selected_subject && !empty($students)): ?>
            <!-- Bulk Scores Entry -->
            <div class="card">
                <div class="card-content">
                    <h3>Bulk Entry — <?php echo htmlspecialchars($class_name); ?> | <?php
                        foreach ($terms as $t) { if ($t['id'] == $selected_term) { echo htmlspecialchars($t['name']); break; } }
                    ?> | <?php
                        foreach ($subjects as $s) { if ($s['id'] == $selected_subject) { echo htmlspecialchars($s['name']); break; } }
                    ?></h3>
                    
                    <div class="alert alert-info" style="margin-top: 15px; font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> <strong>Ghana SBA Scoring:</strong> Individual Test (30) + Class Test (30) = Total Class Score (60) → scaled to 50%. End of Term Exams (100) → scaled to 50%. Overall = Scaled Class Score + Scaled Exams.
                    </div>

                    <form method="POST" action="grades.php">
                        <input type="hidden" name="action" value="save_bulk_sba">
                        <input type="hidden" name="subject_id" value="<?php echo $selected_subject; ?>">
                        <input type="hidden" name="term_id" value="<?php echo $selected_term; ?>">
                        <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($class_name); ?>">
                        
                        <div class="table-responsive" style="margin-top: 15px;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Student Name</th>
                                        <th style="width: 70px;">Individual Test<br><small>(30mks)</small></th>
                                        <th style="width: 70px;">Class Test<br><small>(30mks)</small></th>
                                        <th style="width: 70px;">Total Class Score<br><small>(60mks)</small></th>
                                        <th style="width: 70px;">60 Scaled to<br><small>(50%)</small></th>
                                        <th style="width: 75px;">End of Term Exams<br><small>(100mks)</small></th>
                                        <th style="width: 70px;">100 Scaled to<br><small>(50%)</small></th>
                                        <th style="width: 65px;">Overall Total</th>
                                        <th style="width: 50px;">Position</th>
                                        <th style="width: 80px;">Attitude</th>
                                        <th style="width: 80px;">Interest</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $i => $student):
                                        $db = $existing_scores[$student['id']] ?? null;
                                        $calc = $sba_data[$student['id']] ?? null;
                                    ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($student['full_name'] ?? ''); ?></strong></td>
                                        <td><input type="number" step="0.5" min="0" max="30" name="scores[<?php echo $student['id']; ?>][individual_test]" class="form-control score-input" data-student="<?php echo $student['id']; ?>" value="<?php echo $db ? htmlspecialchars($db['class_test'] ?? 0) : '0'; ?>" style="width: 60px;"></td>
                                        <td><input type="number" step="0.5" min="0" max="30" name="scores[<?php echo $student['id']; ?>][class_test]" class="form-control score-input" data-student="<?php echo $student['id']; ?>" value="<?php echo $db ? htmlspecialchars($db['mid_term'] ?? 0) : '0'; ?>" style="width: 60px;"></td>
                                        <td class="calc-cell" id="total_class_<?php echo $student['id']; ?>"><?php echo $calc ? number_format($calc['total_class_score'], 1) : '0.0'; ?></td>
                                        <td class="calc-cell" id="scaled60_<?php echo $student['id']; ?>"><?php echo $calc ? number_format($calc['scaled_60'], 1) : '0.0'; ?></td>
                                        <td><input type="number" step="0.5" min="0" max="100" name="scores[<?php echo $student['id']; ?>][end_term]" class="form-control score-input" data-student="<?php echo $student['id']; ?>" value="<?php echo $db ? htmlspecialchars($db['end_term'] ?? 0) : '0'; ?>" style="width: 60px;"></td>
                                        <td class="calc-cell" id="scaled100_<?php echo $student['id']; ?>"><?php echo $calc ? number_format($calc['scaled_100'], 1) : '0.0'; ?></td>
                                        <td class="calc-cell" id="overall_<?php echo $student['id']; ?>" style="font-weight:bold;"><?php echo $calc ? number_format($calc['overall_total'], 1) : '0.0'; ?></td>
                                        <td class="calc-cell" id="pos_<?php echo $student['id']; ?>"><?php echo $calc ? $calc['position'] : '-'; ?></td>
                                        <td>
                                            <select name="scores[<?php echo $student['id']; ?>][attitude]" class="form-control" style="width: 80px;">
                                                <option value="">--</option>
                                                <option value="Excellent" <?php echo ($db && $db['attitude'] === 'Excellent') ? 'selected' : ''; ?>>Excellent</option>
                                                <option value="Good" <?php echo ($db && $db['attitude'] === 'Good') ? 'selected' : ''; ?>>Good</option>
                                                <option value="Satisfactory" <?php echo ($db && $db['attitude'] === 'Satisfactory') ? 'selected' : ''; ?>>Satisfactory</option>
                                                <option value="Needs Improvement" <?php echo ($db && $db['attitude'] === 'Needs Improvement') ? 'selected' : ''; ?>>Needs Imp.</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="scores[<?php echo $student['id']; ?>][interest]" class="form-control" style="width: 80px;">
                                                <option value="">--</option>
                                                <option value="Excellent" <?php echo ($db && $db['interest'] === 'Excellent') ? 'selected' : ''; ?>>Excellent</option>
                                                <option value="Good" <?php echo ($db && $db['interest'] === 'Good') ? 'selected' : ''; ?>>Good</option>
                                                <option value="Satisfactory" <?php echo ($db && $db['interest'] === 'Satisfactory') ? 'selected' : ''; ?>>Satisfactory</option>
                                                <option value="Needs Improvement" <?php echo ($db && $db['interest'] === 'Needs Improvement') ? 'selected' : ''; ?>>Needs Imp.</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <button type="submit" class="btn-primary" style="margin-top: 20px; width: 100%;"><i class="fas fa-save"></i> Save All Scores</button>
                    </form>
                </div>
            </div>
            <?php elseif ($selected_class && $selected_term && $selected_subject && empty($students)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> No students found in <?php echo htmlspecialchars($class_name); ?>.
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
    function recalcStudent(studentId) {
        var ind = parseFloat(document.querySelector('input[name="scores[' + studentId + '][individual_test]"]').value) || 0;
        var cls = parseFloat(document.querySelector('input[name="scores[' + studentId + '][class_test]"]').value) || 0;
        var end = parseFloat(document.querySelector('input[name="scores[' + studentId + '][end_term]"]').value) || 0;

        var totalClass = ind + cls;
        var scaled60 = totalClass * 50 / 60;
        var scaled100 = end * 50 / 100;
        var overall = scaled60 + scaled100;

        document.getElementById('total_class_' + studentId).textContent = totalClass.toFixed(1);
        document.getElementById('scaled60_' + studentId).textContent = scaled60.toFixed(1);
        document.getElementById('scaled100_' + studentId).textContent = scaled100.toFixed(1);
        document.getElementById('overall_' + studentId).textContent = overall.toFixed(1);

        // Color-code overall total
        var overallCell = document.getElementById('overall_' + studentId);
        if (overall >= 80) overallCell.style.color = '#27ae60';
        else if (overall >= 60) overallCell.style.color = '#2e86c1';
        else if (overall >= 50) overallCell.style.color = '#f39c12';
        else overallCell.style.color = '#e74c3c';
    }

    // Attach event listeners and recalc all on page load
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.score-input').forEach(function(inp) {
            var sid = inp.getAttribute('data-student');
            inp.addEventListener('input', function() { recalcStudent(sid); });
            // Trigger initial calculation
            recalcStudent(sid);
        });
    });
    </script>
</body>
</html>
