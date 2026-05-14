<?php
require_once 'includes/db.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../login.php');
}

$settings = fetchSettings($pdo);
$school_name = $settings['school_name'] ?? 'Nex CEC';

$user_id = $_SESSION['user_id'];

// Fetch staff record
$stmt = $pdo->prepare("SELECT * FROM staff WHERE user_id = ?");
$stmt->execute([$user_id]);
$staff = $stmt->fetch();
if (!$staff) { redirect('../logout.php'); }
$staff_id = (int)$staff['id'];

// Get teacher's assigned class IDs
$teacher_class_ids = getTeacherClassIds($pdo);

// Get classes, terms, subjects
$all_classes = $pdo->query("SELECT * FROM classes")->fetchAll();
usort($all_classes, fn($a, $b) => ((int)($a['sort_order'] ?? 0)) - ((int)($b['sort_order'] ?? 0)));
$classes = array_filter($all_classes, function($c) use ($teacher_class_ids) {
    return in_array((int)$c['id'], $teacher_class_ids);
});

$terms = $pdo->query("SELECT * FROM terms")->fetchAll();

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

$selected_class = $_GET['class_id'] ?? '';
$selected_term = $_GET['term_id'] ?? '';
$selected_subject = $_GET['subject_id'] ?? '';

$class_name = '';
if ($selected_class) {
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([(int)$selected_class]);
    $classRow = $stmt->fetch();
    $class_name = $classRow ? ($classRow['name'] ?? '') : '';
}

// Get subjects assigned to this teacher
$stmt = $pdo->prepare("SELECT * FROM subjects WHERE teacher_id = ?");
$stmt->execute([$staff_id]);
$teacher_subjects = $stmt->fetchAll();
usort($teacher_subjects, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

// Filter subjects by class
$subjects = $teacher_subjects;
if ($selected_class && $class_name) {
    $subjects = array_filter($teacher_subjects, function($s) use ($selected_class) {
        return (int)$s['class_id'] === (int)$selected_class;
    });
}
// Also apply category filter
if ($selected_class && $class_name) {
    $category_key = $class_category_map[$class_name] ?? null;
    if ($category_key && isset($subject_category_mapping[$category_key]) && !empty($subject_category_mapping[$category_key])) {
        $allowed_ids = array_map('intval', $subject_category_mapping[$category_key]);
        $subjects = array_filter($subjects, function($s) use ($allowed_ids) {
            return in_array((int)$s['id'], $allowed_ids);
        });
    }
}

// Get students in selected class
$students = [];
if ($selected_class && $class_name) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE class_name = ?");
    $stmt->execute([$class_name]);
    $students = $stmt->fetchAll();
    usort($students, fn($a, $b) => strcmp($a['full_name'] ?? '', $b['full_name'] ?? ''));
}

$message = '';
$error = '';

// Handle Bulk Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_bulk_sba') {
    $subject_id = (int)$_POST['subject_id'];
    $term_id = (int)$_POST['term_id'];
    $class_name_raw = sanitize($_POST['class_name']);
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
            $existing = $pdo->prepare("SELECT id FROM sba_scores WHERE student_id = ? AND subject_id = ? AND term_id = ?");
            $existing->execute([$student_id, $subject_id, $term_id]);
            if ($existing->fetch()) {
                $stmt = $pdo->prepare("UPDATE sba_scores SET class_test=?, mid_term=?, end_term=?, project=?, attitude=?, interest=? WHERE student_id=? AND subject_id=? AND term_id=?");
                $stmt->execute([$class_test, $mid_term, $end_term, $project, $attitude, $interest, $student_id, $subject_id, $term_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO sba_scores (student_id, subject_id, term_id, class_test, mid_term, end_term, project, attitude, interest) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $subject_id, $term_id, $class_test, $mid_term, $end_term, $project, $attitude, $interest]);
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

// Get existing scores
$existing_scores = [];
if ($selected_class && $selected_term && $selected_subject) {
    $stmt = $pdo->prepare("SELECT * FROM sba_scores WHERE subject_id = ? AND term_id = ?");
    $stmt->execute([$selected_subject, $selected_term]);
    while ($row = $stmt->fetch()) { $existing_scores[$row['student_id']] = $row; }
}

// Calculate SBA data
$sba_data = [];
if (!empty($students)) {
    foreach ($students as $st) {
        $s = $existing_scores[$st['id']] ?? null;
        $total = $s ? (float)$s['class_test'] + (float)$s['mid_term'] + (float)$s['end_term'] + (float)$s['project'] : 0;
        $sba_data[$st['id']] = [
            'class_test' => $s ? $s['class_test'] : 0,
            'mid_term' => $s ? $s['mid_term'] : 0,
            'end_term' => $s ? $s['end_term'] : 0,
            'project' => $s ? $s['project'] : 0,
            'total' => $total,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SBA / Grades — <?php echo htmlspecialchars($school_name); ?></title>
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
        .staff-main { flex: 1; padding: 30px; background: #f4f6f9; margin-left: 250px; }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: center; justify-content: space-between; }
        .top-bar h2 { font-size: 20px; margin: 0; color: #1a5276; }
        @media (max-width: 768px) {
            .staff-sidebar { left: -250px; transition: left 0.3s; }
            .staff-sidebar.open { left: 0; }
            .staff-main { margin-left: 0; padding: 20px; }
            .top-bar { flex-direction: column; text-align: center; }
        }
        .hamburger-menu { display: none; position: fixed; top: 15px; left: 15px; z-index: 200;
            background: #1a5276; color: white; border: none; width: 40px; height: 40px;
            border-radius: 8px; font-size: 18px; cursor: pointer;
        }
        @media (max-width: 768px) { .hamburger-menu { display: block; } }
    </style>
</head>
<body>
    <button class="hamburger-menu" onclick="document.getElementById('sidebar').classList.toggle('open')">
        <i class="fas fa-bars"></i>
    </button>

    <aside class="staff-sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="../images/school-logo.png" alt="Logo" onerror="this.src='../images/aamusted.jpg'">
            <h3><?php echo htmlspecialchars($school_name); ?></h3>
            <p>Staff Portal</p>
        </div>
        <ul>
            <li><a href="../staff/dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="../staff/grades.php" class="active"><i class="fas fa-clipboard-list"></i> SBA / Grades</a></li>
            <li><a href="../staff/attendance.php"><i class="fas fa-calendar-check"></i> My Attendance</a></li>
            <li><a href="../staff/payslip.php"><i class="fas fa-file-invoice-dollar"></i> Pay Slips</a></li>
            <li><a href="../staff/profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
            <li><a href="../admin/messaging.php"><i class="fas fa-envelope"></i> Messages</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <div class="staff-main">
        <div class="top-bar">
            <h2>SBA / Exam Scores Entry</h2>
            <span style="font-size:13px;color:#888;"><?php echo htmlspecialchars($staff['full_name'] ?? ''); ?></span>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card" style="margin-bottom:30px;">
            <div class="card-content">
                <form method="GET" action="../staff/grades.php" style="display:flex;gap:15px;align-items:flex-end;flex-wrap:wrap;">
                    <div>
                        <label><strong>Class</strong></label>
                        <select name="class_id" class="form-control" style="width:200px;" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $selected_class == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><strong>Term</strong></label>
                        <select name="term_id" class="form-control" style="width:150px;" required>
                            <option value="">-- Select Term --</option>
                            <?php foreach ($terms as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo $selected_term == $t['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><strong>Subject</strong></label>
                        <select name="subject_id" class="form-control" style="width:200px;" required>
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
        <div class="card">
            <div class="card-content">
                <h3>Bulk Entry — <?php echo htmlspecialchars($class_name); ?></h3>
                <div class="alert alert-info" style="margin-top:15px;font-size:0.9rem;">
                    <i class="fas fa-info-circle"></i> <strong>Ghana SBA Scoring:</strong> Class Test (30) + Mid-Term (20) + End-Term (30) + Project (20) = Total (100).
                </div>
                <form method="POST" action="../staff/grades.php">
                    <input type="hidden" name="action" value="save_bulk_sba">
                    <input type="hidden" name="subject_id" value="<?php echo $selected_subject; ?>">
                    <input type="hidden" name="term_id" value="<?php echo $selected_term; ?>">
                    <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($class_name); ?>">
                    <div class="table-responsive" style="margin-top:15px;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Student Name</th>
                                    <th style="width:65px;">Class Test (30)</th>
                                    <th style="width:65px;">Mid-Term (20)</th>
                                    <th style="width:65px;">End-Term (30)</th>
                                    <th style="width:65px;">Project (20)</th>
                                    <th style="width:65px;">Total (100)</th>
                                    <th style="width:80px;">Attitude</th>
                                    <th style="width:80px;">Interest</th>
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
                                    <td><input type="number" step="0.5" min="0" max="30" name="scores[<?php echo $student['id']; ?>][class_test]" class="form-control score-input" data-student="<?php echo $student['id']; ?>" value="<?php echo $db ? htmlspecialchars($db['class_test'] ?? 0) : '0'; ?>" style="width:60px;"></td>
                                    <td><input type="number" step="0.5" min="0" max="20" name="scores[<?php echo $student['id']; ?>][mid_term]" class="form-control score-input" data-student="<?php echo $student['id']; ?>" value="<?php echo $db ? htmlspecialchars($db['mid_term'] ?? 0) : '0'; ?>" style="width:60px;"></td>
                                    <td><input type="number" step="0.5" min="0" max="30" name="scores[<?php echo $student['id']; ?>][end_term]" class="form-control score-input" data-student="<?php echo $student['id']; ?>" value="<?php echo $db ? htmlspecialchars($db['end_term'] ?? 0) : '0'; ?>" style="width:60px;"></td>
                                    <td><input type="number" step="0.5" min="0" max="20" name="scores[<?php echo $student['id']; ?>][project]" class="form-control score-input" data-student="<?php echo $student['id']; ?>" value="<?php echo $db ? htmlspecialchars($db['project'] ?? 0) : '0'; ?>" style="width:60px;"></td>
                                    <td class="calc-cell" id="total_<?php echo $student['id']; ?>" style="font-weight:bold;"><?php echo $calc ? number_format($calc['total'], 1) : '0.0'; ?></td>
                                    <td>
                                        <select name="scores[<?php echo $student['id']; ?>][attitude]" class="form-control" style="width:80px;">
                                            <option value="">--</option>
                                            <option value="Excellent" <?php echo ($db && $db['attitude'] === 'Excellent') ? 'selected' : ''; ?>>Excellent</option>
                                            <option value="Good" <?php echo ($db && $db['attitude'] === 'Good') ? 'selected' : ''; ?>>Good</option>
                                            <option value="Satisfactory" <?php echo ($db && $db['attitude'] === 'Satisfactory') ? 'selected' : ''; ?>>Satisfactory</option>
                                            <option value="Needs Improvement" <?php echo ($db && $db['attitude'] === 'Needs Improvement') ? 'selected' : ''; ?>>Needs Imp.</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="scores[<?php echo $student['id']; ?>][interest]" class="form-control" style="width:80px;">
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
                    <button type="submit" class="btn-primary" style="margin-top:20px;width:100%;"><i class="fas fa-save"></i> Save All Scores</button>
                </form>
            </div>
        </div>
        <?php elseif ($selected_class && $selected_term && $selected_subject && empty($students)): ?>
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> No students found in <?php echo htmlspecialchars($class_name); ?>.</div>
        <?php endif; ?>
    </div>

    <script>
    function recalcStudent(studentId) {
        var ct = parseFloat(document.querySelector('input[name="scores[' + studentId + '][class_test]"]').value) || 0;
        var mt = parseFloat(document.querySelector('input[name="scores[' + studentId + '][mid_term]"]').value) || 0;
        var et = parseFloat(document.querySelector('input[name="scores[' + studentId + '][end_term]"]').value) || 0;
        var pj = parseFloat(document.querySelector('input[name="scores[' + studentId + '][project]"]').value) || 0;
        var total = ct + mt + et + pj;
        document.getElementById('total_' + studentId).textContent = total.toFixed(1);
        var cell = document.getElementById('total_' + studentId);
        if (total >= 80) cell.style.color = '#27ae60';
        else if (total >= 60) cell.style.color = '#2e86c1';
        else if (total >= 50) cell.style.color = '#f39c12';
        else cell.style.color = '#e74c3c';
    }
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.score-input').forEach(function(inp) {
            var sid = inp.getAttribute('data-student');
            inp.addEventListener('input', function() { recalcStudent(sid); });
            recalcStudent(sid);
        });
    });
    </script>
</body>
</html>
