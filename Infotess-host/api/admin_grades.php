<?php
require_once 'includes/db.php';

// Enforce access control
requireAccess('grades');

// Auto-migrate old "Nursery" class to "Nursery 1" / "Nursery 2" if needed
migrateNurseryClasses($pdo);

$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

// Get classes, terms, subjects (bridge ignores ORDER BY — sort in PHP)
$all_classes = $pdo->query("SELECT * FROM classes");
$all_classes = $all_classes ? $all_classes->fetchAll() : [];
usort($all_classes, function($a, $b) {
    return ((int)($a['sort_order'] ?? 0)) - ((int)($b['sort_order'] ?? 0));
});

// Teacher scope: if logged in as teacher, only show assigned classes
if (isTeacher()) {
    $teacher_class_ids = getTeacherClassIds($pdo);
    $classes = array_filter($all_classes, function($c) use ($teacher_class_ids) {
        return in_array((int)$c['id'], $teacher_class_ids);
    });
} else {
    $classes = $all_classes;
}

$terms = $pdo->query("SELECT * FROM terms");
$terms = $terms ? $terms->fetchAll() : [];

// Count active students per class name for card display
$students_per_class = [];
try {
    $all_students_raw = $pdo->query("SELECT * FROM students");
    if ($all_students_raw) {
        foreach ($all_students_raw->fetchAll() as $s) {
            $cn = trim($s['class_name'] ?? '');
            $st = trim($s['status'] ?? '');
            if ($cn !== '' && $st === 'active') {
                $students_per_class[$cn] = ($students_per_class[$cn] ?? 0) + 1;
            }
        }
    }
} catch (Exception $e) {
    error_log("admin_grades.php: error counting students per class: " . $e->getMessage());
}

$selected_class = $_GET['class_id'] ?? '';
$selected_term = $_GET['term_id'] ?? '';
$selected_subject = $_GET['subject_id'] ?? '';

// Map class names to their educational category (matches admin_subjects.php categories)
$class_category_map = [
    'Creche'  => 'creche',
    'Nursery 1' => 'nursery',
    'Nursery 2' => 'nursery',
    'KG 1'    => 'kindergarten',
    'KG 2'    => 'kindergarten',
    'Basic 1' => 'primary', 'Basic 2' => 'primary', 'Basic 3' => 'primary',
    'Basic 4' => 'primary', 'Basic 5' => 'primary', 'Basic 6' => 'primary',
    'JHS 1'   => 'jhs',     'JHS 2'   => 'jhs',     'JHS 3'   => 'jhs',
];

// Load subject-to-category mapping from system_settings (set via admin_subjects.php)
$subject_category_mapping = [];
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute(['subject_categories']);
    $row = $stmt->fetch();
    if ($row && !empty($row['setting_value'])) {
        $decoded = json_decode($row['setting_value'], true);
        if (is_array($decoded)) {
            $subject_category_mapping = $decoded;
        }
    }
} catch (Exception $e) {}

// Get selected class name for category lookup
// NOTE: bridge ignores column list in SELECT; always use SELECT * and access by key.
$class_name = '';
if ($selected_class) {
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([(int)$selected_class]);
    $classRow = $stmt->fetch();
    $class_name = $classRow ? ($classRow['name'] ?? '') : '';
}

// Load all subjects from the database
$all_subjects = $pdo->query("SELECT * FROM subjects");
$all_subjects = $all_subjects ? $all_subjects->fetchAll() : [];
usort($all_subjects, function($a, $b) {
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

// -------------------------------------------------------
// AUTO-SEED: If subject_categories mapping is empty, auto-generate it
// by matching existing subject names to known category defaults.
// This ensures grades.php works immediately without needing to
// manually click "Seed All Defaults" in Subject Settings.
// -------------------------------------------------------
if (empty($subject_category_mapping) && !empty($all_subjects)) {
    $category_matchers = [
        'creche'       => [
            'Early Stimulation & Sensory Play', 'Responsive Caregiving & Nurturing',
            'Health, Hygiene & Nutrition', 'Safety & Security Awareness',
            'Physical & Motor Development', 'Cognitive Development & Exploration',
            'Language & Communication Skills', 'Social & Emotional Development',
        ],
        'nursery'      => [
            'Language & Literacy', 'Numeracy', 'Creative Activities',
            'Environmental Studies', 'Our World Our People (OWOP)',
            'Movement, Music, Drama & PE',
        ],
        'kindergarten' => [
            'Language and Literacy', 'Numeracy', 'Creative Arts',
            'Environmental Studies', 'Our World Our People (OWOP)',
            'Movement, Music, Drama & PE',
        ],
        'primary'      => [
            'English Language', 'Mathematics', 'Science', 'Ghanaian Language',
            'History of Ghana', 'Religious and Moral Education', 'Creative Arts',
            'Computing', 'French', 'Physical Education',
        ],
        'jhs'          => [
            'English Language', 'Mathematics', 'Science', 'Social Studies',
            'Religious and Moral Education', 'Ghanaian Language',
            'Creative Arts and Design', 'Career Technology', 'Computing',
            'French', 'Physical Education',
        ],
    ];

    // Build lookup: map normalized names, short names, and core keywords to subject IDs
    // so we can match subjects even when names differ slightly (e.g. "Science" matches
    // "Integrated Science", "Ghanaian Language" matches "Ghanaian Language (Twi)").
    $subjects_by_name = [];
    $subjects_by_keyword = [];
    foreach ($all_subjects as $s) {
        $sid = (int)$s['id'];
        $name_lower = strtolower(trim($s['name']));
        $name_clean = strtolower(trim(preg_replace('/\s*\(.*?\)\s*/', '', $s['name'])));
        
        // Store by exact name
        $subjects_by_name[$name_lower] = $sid;
        // Store by name without parenthetical
        $subjects_by_name[$name_clean] = $sid;
        
        // Store individual keywords for fallback matching (words >= 4 chars)
        foreach (explode(' ', $name_clean) as $word) {
            $word = trim($word);
            if (strlen($word) >= 4) {
                $subjects_by_keyword[$word] = $sid;
            }
        }
    }

    foreach ($category_matchers as $cat => $names) {
        $subject_category_mapping[$cat] = [];
        $seen_ids = [];
        foreach ($names as $name) {
            $key = strtolower(trim($name));
            $matched_id = null;
            
            // 1st pass: exact match (name or cleaned name)
            if (isset($subjects_by_name[$key])) {
                $matched_id = $subjects_by_name[$key];
            }
            
            // 2nd pass: keyword match (any word >= 4 chars in the matcher name
            // that matches a subject keyword)
            if ($matched_id === null) {
                foreach (explode(' ', $key) as $word) {
                    $word = trim($word);
                    if (strlen($word) >= 4 && isset($subjects_by_keyword[$word])) {
                        $matched_id = $subjects_by_keyword[$word];
                        break;
                    }
                }
            }
            
            if ($matched_id !== null && !in_array($matched_id, $seen_ids)) {
                $subject_category_mapping[$cat][] = $matched_id;
                $seen_ids[] = $matched_id;
            }
        }
    }

    // Persist the auto-generated mapping
    try {
        $json = json_encode($subject_category_mapping);
        $stmt = $pdo->prepare("SELECT setting_key FROM system_settings WHERE setting_key = ?");
        $stmt->execute(['subject_categories']);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$json, 'subject_categories']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute(['subject_categories', $json]);
        }
    } catch (Exception $e) {
        error_log("admin_grades.php auto-seed error: " . $e->getMessage());
    }
}

// Filter subjects by category when a class is selected.
// Uses the subject-to-category mapping set in admin_subjects.php (stored in system_settings).
// If no mapping exists or filtering yields no results, ALL subjects are shown (never empty).
$subjects = $all_subjects; // default: show all
if ($selected_class && $class_name) {
    $category_key = $class_category_map[$class_name] ?? null;
    if ($category_key && isset($subject_category_mapping[$category_key]) && !empty($subject_category_mapping[$category_key])) {
        $allowed_ids = array_map('intval', $subject_category_mapping[$category_key]);
        $filtered = array_filter($all_subjects, function($s) use ($allowed_ids) {
            return in_array((int)$s['id'], $allowed_ids);
        });
        // Only use filtered list if it actually has results
        if (!empty($filtered)) {
            $subjects = $filtered;
        }
    }
}

// Get students in selected class
$students = [];
if ($selected_class && $class_name) {
    // NOTE: bridge ignores column list and ORDER BY; sort in PHP after fetch.
    $stmt = $pdo->prepare("SELECT * FROM students WHERE class_name = ?");
    $stmt->execute([$class_name]);
    $students = $stmt->fetchAll();
    usort($students, function($a, $b) {
        return strcmp($a['full_name'] ?? '', $b['full_name'] ?? '');
    });
}

// Handle Bulk Save SBA Scores
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_bulk_sba') {
    validate_request_csrf();
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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter SBA Scores — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    .class-card:hover {
        transform: translateY(-3px) !important;
        box-shadow: 0 6px 20px rgba(0,0,0,0.12) !important;
        border-color: #3498db !important;
    }
    .score-input:disabled, .sba-select:disabled {
        background: #f8f9fa !important;
        color: #2c3e50 !important;
        border: 1px solid #e9ecef !important;
        cursor: default !important;
        opacity: 1 !important;
        -webkit-text-fill-color: #2c3e50 !important;
    }
    .sba-select:disabled {
        -webkit-appearance: none !important;
        appearance: none !important;
    }
    </style>
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

            <?php if (!$selected_class): ?>
            <!-- Class Cards Grid (default view) -->
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:35px;margin-bottom:30px;">
                <?php foreach ($classes as $c):
                    $cname = $c['name'] ?? '';
                    $count = $students_per_class[$cname] ?? 0;
                ?>
                <a href="?class_id=<?php echo $c['id']; ?>" class="card class-card" style="display:block;text-decoration:none;color:inherit;transition:transform 0.15s,box-shadow 0.15s;border:2px solid transparent;">
                    <div class="card-content" style="text-align:center;padding:24px 16px;">
                        <div style="font-size:1.5rem;font-weight:700;color:#2c3e50;margin-bottom:6px;"><?php echo htmlspecialchars($cname); ?></div>
                        <div style="font-size:0.85rem;color:#7f8c8d;">
                            <i class="fas fa-user-graduate"></i> <?php echo $count; ?> student<?php echo $count !== 1 ? 's' : ''; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <!-- Back to Classes + Selection Form -->
            <div style="margin-bottom:20px;">
                <a href="grades.php" style="display:inline-flex;align-items:center;gap:6px;color:#2980b9;font-weight:600;text-decoration:none;margin-bottom:15px;">
                    <i class="fas fa-arrow-left"></i> Back to Classes
                </a>
                <div class="card" style="margin-bottom:0;">
                    <div class="card-content">
                        <form method="GET" action="grades.php" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                            <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                            <div>
                                <label><strong>Term</strong></label>
                                <select name="term_id" class="form-control" style="width: 180px;" required>
                                    <option value="">-- Select Term --</option>
                                    <?php foreach ($terms as $t): ?>
                                        <option value="<?php echo $t['id']; ?>" <?php echo $selected_term == $t['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['name']); ?> (<?php echo htmlspecialchars($t['academic_year']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label><strong>Subject</strong></label>
                                <select name="subject_id" id="subject_select" class="form-control" style="width: 220px;" required>
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
            </div>

            <?php if ($selected_term && $selected_subject && !empty($students)): ?>
            <!-- Bulk Scores Entry -->
            <div class="card">
                <div class="card-content">
                    <h3><?php echo htmlspecialchars($class_name); ?> SBA Assessment &nbsp;|&nbsp; <?php
                        foreach ($terms as $t) { if ($t['id'] == $selected_term) { echo htmlspecialchars($t['name']); break; } }
                    ?></h3>
                    
                    <div class="alert alert-info" style="margin-top: 15px; font-size: 0.9rem;display:flex;align-items:center;justify-content:space-between;">
                        <span><i class="fas fa-info-circle"></i> <strong>Ghana SBA Scoring:</strong> Individual Test (30) + Class Test (30) = Total Class Score (60) → scaled to 50%. End of Term Exams (100) → scaled to 50%. Overall = Scaled Class Score + Scaled Exams.</span>
                        <button type="button" id="toggleEditBtn" style="white-space:nowrap;flex-shrink:0;margin-left:12px;padding:6px 14px;border:1px solid #bdc3c7;border-radius:4px;background:#fff;color:#2c3e50;cursor:pointer;font-size:0.85rem;" onclick="toggleEditMode()">
                            <i class="fas fa-lock"></i> Enable Editing
                        </button>
                    </div>

                    <form method="POST" action="grades.php">
                        <?php csrf_field(); ?>
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
                                                <select name="scores[<?php echo $student['id']; ?>][attitude]" class="form-control sba-select" style="width: 80px;">
                                                    <option value="">--</option>
                                                    <option value="Excellent" <?php echo ($db && $db['attitude'] === 'Excellent') ? 'selected' : ''; ?>>Excellent</option>
                                                    <option value="Good" <?php echo ($db && $db['attitude'] === 'Good') ? 'selected' : ''; ?>>Good</option>
                                                    <option value="Satisfactory" <?php echo ($db && $db['attitude'] === 'Satisfactory') ? 'selected' : ''; ?>>Satisfactory</option>
                                                    <option value="Needs Improvement" <?php echo ($db && $db['attitude'] === 'Needs Improvement') ? 'selected' : ''; ?>>Needs Imp.</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="scores[<?php echo $student['id']; ?>][interest]" class="form-control sba-select" style="width: 80px;">
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
                        
                        <button type="submit" id="saveScoresBtn" class="btn-primary" style="margin-top: 20px; width: 100%;"><i class="fas fa-save"></i> Save All Scores</button>
                    </form>
                </div>
            </div>
            <?php elseif ($selected_term && $selected_subject && empty($students)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> No students found in <?php echo htmlspecialchars($class_name); ?>.
                </div>
            <?php endif; ?>
            <?php endif; // end class-selected wrapper ?>
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

    /**
     * Toggle between locked (view-only) and editable mode.
     * In locked mode, all inputs and selects are disabled;
     * the Save button is hidden.
     */
    var editMode = false;

    function toggleEditMode() {
        editMode = !editMode;
        var inputs = document.querySelectorAll('.score-input');
        var selects = document.querySelectorAll('.sba-select');
        var saveBtn = document.getElementById('saveScoresBtn');
        var toggleBtn = document.getElementById('toggleEditBtn');

        inputs.forEach(function(inp) { inp.disabled = !editMode; });
        selects.forEach(function(sel) { sel.disabled = !editMode; });
        if (saveBtn) saveBtn.style.display = editMode ? '' : 'none';

        if (editMode) {
            toggleBtn.innerHTML = '<i class="fas fa-unlock-alt"></i> Lock &amp; View Only';
            toggleBtn.style.background = '#27ae60';
            toggleBtn.style.color = '#fff';
            toggleBtn.style.borderColor = '#27ae60';
        } else {
            toggleBtn.innerHTML = '<i class="fas fa-lock"></i> Enable Editing';
            toggleBtn.style.background = '#fff';
            toggleBtn.style.color = '#2c3e50';
            toggleBtn.style.borderColor = '#bdc3c7';
        }
    }

    // Attach event listeners and recalc all on page load
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.score-input').forEach(function(inp) {
            var sid = inp.getAttribute('data-student');
            inp.addEventListener('input', function() { recalcStudent(sid); });
            // Trigger initial calculation
            recalcStudent(sid);
        });

        // Always start in locked (view-only) mode
        editMode = true;  // force toggle to false
        toggleEditMode();
    });
    </script>
</body>
</html>
