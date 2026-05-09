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

// Get classes, terms, subjects
$classes = $pdo->query("SELECT * FROM classes ORDER BY sort_order ASC")->fetchAll();
$terms = $pdo->query("SELECT * FROM terms ORDER BY id ASC")->fetchAll();

$selected_class = $_GET['class_id'] ?? '';
$selected_term = $_GET['term_id'] ?? '';
$selected_subject = $_GET['subject_id'] ?? '';

// Get all subjects (bridge drops OR conditions in WHERE)
$all_subjects = $pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();
if ($selected_class) {
    $subjects = array_filter($all_subjects, fn($s) => empty($s['class_id']) || (int)$s['class_id'] === (int)$selected_class);
} else {
    $subjects = $all_subjects;
}

// Get students in selected class
$students = [];
if ($selected_class) {
    // Two-step lookup: bridge can't handle subquery in WHERE
    $stmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
    $stmt->execute([(int)$selected_class]);
    $className = $stmt->fetchColumn();
    if ($className) {
        $stmt = $pdo->prepare("SELECT id, full_name, admission_number FROM students WHERE class_name = ? ORDER BY full_name ASC");
        $stmt->execute([$className]);
        $students = $stmt->fetchAll();
    }
}

// Handle Save SBA Scores
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_sba') {
    $student_id = (int)$_POST['student_id'];
    $subject_id = (int)$_POST['subject_id'];
    $term_id = (int)$_POST['term_id'];
    $class_test = (float)($_POST['class_test'] ?? 0);
    $mid_term = (float)($_POST['mid_term'] ?? 0);
    $end_term = (float)($_POST['end_term'] ?? 0);
    $project = (float)($_POST['project'] ?? 0);
    $attitude = sanitize($_POST['attitude'] ?? '');
    $interest = sanitize($_POST['interest'] ?? '');

    try {
        $stmt = $pdo->prepare("INSERT INTO sba_scores (student_id, subject_id, term_id, class_test, mid_term, end_term, project, attitude, interest) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT (student_id, subject_id, term_id) DO UPDATE SET class_test=?, mid_term=?, end_term=?, project=?, attitude=?, interest=?, updated_at=NOW()");
        $stmt->execute([$student_id, $subject_id, $term_id, $class_test, $mid_term, $end_term, $project, $attitude, $interest, $class_test, $mid_term, $end_term, $project, $attitude, $interest]);
        $message = "Scores saved successfully.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
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
            $class_test = (float)($data['class_test'] ?? 0);
            $mid_term = (float)($data['mid_term'] ?? 0);
            $end_term = (float)($data['end_term'] ?? 0);
            $project = (float)($data['project'] ?? 0);
            $attitude = sanitize($data['attitude'] ?? '');
            $interest = sanitize($data['interest'] ?? '');
            
            $stmt = $pdo->prepare("INSERT INTO sba_scores (student_id, subject_id, term_id, class_test, mid_term, end_term, project, attitude, interest) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT (student_id, subject_id, term_id) DO UPDATE SET class_test=?, mid_term=?, end_term=?, project=?, attitude=?, interest=?");
            $stmt->execute([$student_id, $subject_id, $term_id, $class_test, $mid_term, $end_term, $project, $attitude, $interest, $class_test, $mid_term, $end_term, $project, $attitude, $interest]);
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

// Get class name for bulk form
$class_name = '';
if ($selected_class) {
    $stmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
    $stmt->execute([$selected_class]);
    $class_name = $stmt->fetchColumn();
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
                        <i class="fas fa-info-circle"></i> <strong>Scoring:</strong> Class Test (30), Mid-Term (20), End-Term (30), Project (20). Total SBA = 100.
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
                                        <th>Index Number</th>
                                        <th>Student Name</th>
                                        <th style="width: 80px;">Class Test (30)</th>
                                        <th style="width: 80px;">Mid-Term (20)</th>
                                        <th style="width: 80px;">End-Term (30)</th>
                                        <th style="width: 80px;">Project (20)</th>
                                        <th style="width: 80px;">Total</th>
                                        <th style="width: 100px;">Attitude</th>
                                        <th style="width: 100px;">Interest</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $i => $student):
                                        $sba = $existing_scores[$student['id']] ?? null;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['admission_number']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                                        <td><input type="number" step="0.5" min="0" max="30" name="scores[<?php echo $student['id']; ?>][class_test]" class="form-control score-input" value="<?php echo $sba ? htmlspecialchars($sba['class_test']) : ''; ?>" onchange="calcTotal(this)" style="width: 70px;"></td>
                                        <td><input type="number" step="0.5" min="0" max="20" name="scores[<?php echo $student['id']; ?>][mid_term]" class="form-control score-input" value="<?php echo $sba ? htmlspecialchars($sba['mid_term']) : ''; ?>" onchange="calcTotal(this)" style="width: 70px;"></td>
                                        <td><input type="number" step="0.5" min="0" max="30" name="scores[<?php echo $student['id']; ?>][end_term]" class="form-control score-input" value="<?php echo $sba ? htmlspecialchars($sba['end_term']) : ''; ?>" onchange="calcTotal(this)" style="width: 70px;"></td>
                                        <td><input type="number" step="0.5" min="0" max="20" name="scores[<?php echo $student['id']; ?>][project]" class="form-control score-input" value="<?php echo $sba ? htmlspecialchars($sba['project']) : ''; ?>" onchange="calcTotal(this)" style="width: 70px;"></td>
                                        <td><strong class="total-cell">0</strong></td>
                                        <td>
                                            <select name="scores[<?php echo $student['id']; ?>][attitude]" class="form-control" style="width: 90px;">
                                                <option value="">--</option>
                                                <option value="Excellent" <?php echo ($sba && $sba['attitude'] === 'Excellent') ? 'selected' : ''; ?>>Excellent</option>
                                                <option value="Good" <?php echo ($sba && $sba['attitude'] === 'Good') ? 'selected' : ''; ?>>Good</option>
                                                <option value="Satisfactory" <?php echo ($sba && $sba['attitude'] === 'Satisfactory') ? 'selected' : ''; ?>>Satisfactory</option>
                                                <option value="Needs Improvement" <?php echo ($sba && $sba['attitude'] === 'Needs Improvement') ? 'selected' : ''; ?>>Needs Imp.</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="scores[<?php echo $student['id']; ?>][interest]" class="form-control" style="width: 90px;">
                                                <option value="">--</option>
                                                <option value="Excellent" <?php echo ($sba && $sba['interest'] === 'Excellent') ? 'selected' : ''; ?>>Excellent</option>
                                                <option value="Good" <?php echo ($sba && $sba['interest'] === 'Good') ? 'selected' : ''; ?>>Good</option>
                                                <option value="Satisfactory" <?php echo ($sba && $sba['interest'] === 'Satisfactory') ? 'selected' : ''; ?>>Satisfactory</option>
                                                <option value="Needs Improvement" <?php echo ($sba && $sba['interest'] === 'Needs Improvement') ? 'selected' : ''; ?>>Needs Imp.</option>
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
    function calcTotal(input) {
        const row = input.closest('tr');
        const inputs = row.querySelectorAll('.score-input');
        let total = 0;
        inputs.forEach(inp => { total += parseFloat(inp.value) || 0; });
        row.querySelector('.total-cell').textContent = total.toFixed(1);
        
        // Color code
        const totalCell = row.querySelector('.total-cell');
        if (total >= 80) totalCell.style.color = '#27ae60';
        else if (total >= 60) totalCell.style.color = '#2e86c1';
        else if (total >= 50) totalCell.style.color = '#f39c12';
        else totalCell.style.color = '#e74c3c';
    }
    
    // Calculate totals on page load
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.score-input').forEach(input => calcTotal(input));
    });
    </script>
</body>
</html>
