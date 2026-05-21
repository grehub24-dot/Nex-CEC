<?php
require_once 'includes/db.php';

// Enforce access control
requireAccess('attendance');

$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

// Get classes — bridge ignores ORDER BY, so sort in PHP (narrow columns to prevent 413)
$all_classes = $pdo->query("SELECT id, name, sort_order FROM classes")->fetchAll();
usort($all_classes, fn($a, $b) => ((int)($a['sort_order'] ?? 0)) - ((int)($b['sort_order'] ?? 0)));

// Teacher scope: if logged in as teacher, only show assigned classes
if (isTeacher()) {
    $teacher_class_ids = getTeacherClassIds($pdo);
    $classes = array_filter($all_classes, function($c) use ($teacher_class_ids) {
        return in_array((int)$c['id'], $teacher_class_ids);
    });
} else {
    $classes = $all_classes;
}

// Fetch staff record for profile pic (needed by renderStaffSidebar for teachers)
$staff_pp = '';
$staff_name = '';
if (isTeacher() && !empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT profile_picture, full_name FROM staff WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $s = $stmt->fetch();
    if ($s) {
        $staff_pp = $s['profile_picture'] ?? '';
        $staff_name = $s['full_name'] ?? '';
    }
}

$selected_class = $_GET['class_id'] ?? '';
$selected_date = $_GET['date'] ?? date('Y-m-d');

// Handle message/error from POST-Redirect-GET
$message = $_GET['msg'] ?? $message;
$error   = $_GET['err'] ?? $error;

// Get students in selected class
$students = [];
$class_name = '';
if ($selected_class) {
    // Bridge ignores column list; fetch full row and access by key
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([(int)$selected_class]);
    $classRow = $stmt->fetch();
    $class_name = $classRow ? ($classRow['name'] ?? '') : '';
    
    if ($class_name !== '') {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE class_name = ?");
        $stmt->execute([$class_name]);
        $students = $stmt->fetchAll();
        // Bridge ignores ORDER BY, so sort in PHP
        usort($students, fn($a, $b) => strcmp($a['full_name'] ?? '', $b['full_name'] ?? ''));
    }
}

// Handle Save Attendance — use POST-Redirect-GET
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance') {
    validate_request_csrf();
    $class_id = (int)$_POST['class_id'];
    $attendance_date = sanitize($_POST['attendance_date']);
    
    try {
        $pdo->beginTransaction();
        $saved = 0;
        
        foreach ($_POST['attendance'] as $student_id => $status) {
            $student_id = (int)$student_id;
            $reason = sanitize($_POST['reasons'][$student_id] ?? '');
            
            // Bridge doesn't support ON CONFLICT — use SELECT-then-UPDATE-or-INSERT
            $existing = $pdo->prepare("SELECT id FROM student_attendance WHERE student_id = ? AND attendance_date = ?");
            $existing->execute([$student_id, $attendance_date]);
            if ($existing->fetch()) {
                // Also set class_id to backfill legacy records that have it null
                $stmt = $pdo->prepare("UPDATE student_attendance SET status=?, reason=?, recorded_by=?, class_id=? WHERE student_id=? AND attendance_date=?");
                $stmt->execute([$status, $reason, $_SESSION['user_id'], $class_id, $student_id, $attendance_date]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO student_attendance (student_id, class_id, attendance_date, status, reason, recorded_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $class_id, $attendance_date, $status, $reason, $_SESSION['user_id']]);
            }
            $saved++;
        }
        
        $pdo->commit();
        header("Location: /admin/attendance.php?class_id=" . urlencode($class_id) . "&date=" . urlencode($attendance_date) . "&msg=" . urlencode("Attendance saved for $saved students on $attendance_date."));
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: /admin/attendance.php?class_id=" . urlencode($class_id) . "&date=" . urlencode($attendance_date) . "&err=" . urlencode("Error: " . $e->getMessage()));
        exit;
    }
}

// Get existing attendance for selected class and date
// NOTE: Some legacy records have class_id = NULL (created by old seed data).
// Fetch by date first, then filter by students in the current class.
$existing_attendance = [];
if ($selected_class && $selected_date && !empty($students)) {
    $class_student_ids = array_map(fn($s) => (int)($s['id'] ?? 0), $students);
    $stmt = $pdo->prepare("SELECT * FROM student_attendance WHERE attendance_date = ?");
    $stmt->execute([$selected_date]);
    while ($row = $stmt->fetch()) {
        $sid = (int)$row['student_id'];
        if (in_array($sid, $class_student_ids)) {
            $existing_attendance[$sid] = $row;
            // Silently backfill legacy null class_id on first encounter
            if ($row['class_id'] === null || $row['class_id'] === '') {
                $fix = $pdo->prepare("UPDATE student_attendance SET class_id=? WHERE id=?");
                $fix->execute([(int)$selected_class, $row['id']]);
            }
        }
    }
}

// Attendance statistics (bridge drops complex aggregation — compute in PHP)
$stats = [];
if ($selected_class) {
    $all_att = $pdo->query("SELECT id, class_id, attendance_date, status FROM student_attendance")->fetchAll();
    $dateStr = substr($selected_date, 0, 10);
    $classRecs = array_filter($all_att, fn($r) => (int)($r['class_id'] ?? 0) === (int)$selected_class && substr($r['attendance_date'], 0, 10) === $dateStr);
    $stats = [
        'total_records' => count($classRecs),
        'present' => count(array_filter($classRecs, fn($r) => ($r['status'] ?? '') === 'present')),
        'absent'  => count(array_filter($classRecs, fn($r) => ($r['status'] ?? '') === 'absent')),
        'late'    => count(array_filter($classRecs, fn($r) => ($r['status'] ?? '') === 'late')),
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Attendance — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-btn { padding: 6px 12px; border: 2px solid #ddd; border-radius: 4px; cursor: pointer; font-size: 0.85rem; background: #fff; transition: all 0.2s; }
        .status-btn.present { border-color: #27ae60; background: #d4edda; color: #155724; }
        .status-btn.absent { border-color: #e74c3c; background: #f8d7da; color: #721c24; }
        .status-btn.late { border-color: #f39c12; background: #fff3cd; color: #856404; }
        .status-btn.active { box-shadow: 0 0 0 2px #333; transform: scale(1.05); font-weight: bold; }
        .status-btn.present.active { border-color: #1e8449; background: #28a745; color: #fff; }
        .status-btn.absent.active { border-color: #c0392b; background: #dc3545; color: #fff; }
        .status-btn.late.active { border-color: #d68910; background: #e67e22; color: #fff; }
        .status-btn:hover { opacity: 0.8; }
        .quick-actions { display: flex; gap: 10px; margin-bottom: 15px; }
        <?php if (isTeacher()): ?>
        /* Staff sidebar styles for teacher access */
        .staff-sidebar {
            width: 250px; background: #1a5276; color: white; position: fixed;
            top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 100;
        }
        .staff-sidebar .sidebar-header { padding: 25px 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .staff-sidebar .sidebar-header img.sidebar-profile-img { width: 64px; height: 64px; border-radius: 50%; background: white; padding: 3px; margin-bottom: 10px; object-fit: cover; }
        .staff-sidebar .sidebar-header h3 { font-size: 15px; margin: 0; }
        .staff-sidebar .sidebar-header p { font-size: 12px; opacity: 0.8; margin: 5px 0 0; }
        .staff-sidebar ul { list-style: none; padding: 0; margin: 0; }
        .staff-sidebar ul li { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .staff-sidebar ul li a {
            display: block; padding: 14px 20px; color: rgba(255,255,255,0.85); text-decoration: none;
            font-size: 14px; transition: all 0.2s; position: relative;
        }
        .staff-sidebar ul li a:hover, .staff-sidebar ul li a.active { background: rgba(255,255,255,0.1); color: white; padding-left: 25px; }
        .staff-sidebar ul li a i { width: 22px; text-align: center; margin-right: 8px; }
        .staff-sidebar .msg-count {
            position: absolute; right: 15px; top: 50%; transform: translateY(-50%);
            background: #e74c3c; color: white; padding: 1px 8px;
            border-radius: 10px; font-size: 11px; font-weight: 700; line-height: 1.5;
            min-width: 20px; text-align: center;
        }
        .hamburger-menu { display: none; position: fixed; top: 15px; left: 15px; z-index: 200;
            background: #1a5276; color: white; border: none; width: 40px; height: 40px;
            border-radius: 8px; font-size: 18px; cursor: pointer;
        }
        .staff-main { flex: 1; padding: 30px; background: #f4f6f9; margin-left: 250px; }
        .staff-main .top-bar {
            background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: center;
        }
        .staff-main .top-bar h2 { font-size: 20px; margin: 0; color: #1a5276; }
        @media (max-width: 768px) {
            .staff-sidebar { left: -250px; transition: left 0.3s; }
            .staff-sidebar.open { left: 0; }
            .hamburger-menu { display: block; }
            .staff-main { margin-left: 0; padding: 20px; }
            .staff-main .top-bar { flex-direction: column; text-align: center; margin-top: 50px; gap: 10px; }
        }
        <?php endif; ?>
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .main-content { margin-left: 0 !important; padding: 60px 12px 20px; }
            .main-content .top-bar { margin-top: 0; }
            /* Filter form — override hardcoded inline widths */
            .card-content form[action="/admin/attendance.php"] select,
            .card-content form[action="/admin/attendance.php"] input[type="date"] {
                width: 100% !important; max-width: 100%;
            }
            .card-content form[action="/admin/attendance.php"] { flex-direction: column; }
            .card-content form[action="/admin/attendance.php"] button[type="submit"] { width: 100%; }
            /* Quick actions — stack vertically */
            .quick-actions { flex-direction: column; }
            .quick-actions .btn-login { width: 100%; text-align: center; }
            /* Stat cards — tighten */
            .stat-cards { grid-template-columns: 1fr 1fr; gap: 10px; }
            /* Status buttons — compact */
            .status-btn { padding: 5px 8px; font-size: 0.75rem; min-width: 0; }
            td > div[style*="display: flex"] { gap: 3px !important; }
            /* Reason input — full width */
            td input[type="text"] { max-width: 120px; font-size: 0.8rem; }
            /* Table font size */
            .table th, .table td { font-size: 0.8rem; padding: 6px 4px; }
            .table th:nth-child(1), .table td:nth-child(1) { display: none; } /* hide Index Number on tiny screens */
        }
        @media (max-width: 480px) {
            .stat-cards { grid-template-columns: 1fr; }
            .table th:nth-child(4), .table td:nth-child(4) { display: none; } /* hide Reason column */
        }
    </style>
</head>
<body>
    <?php if (isTeacher()): ?>
        <?php echo renderStaffSidebar('student_attendance', $school_name, 0, $staff_pp, $staff_name); ?>
    <?php else: ?>
    <div class="dashboard-container">
        <?php echo renderSidebar('attendance', $school_name); ?>
    <?php endif; ?>

        <main class="<?php echo isTeacher() ? 'staff-main' : 'main-content'; ?>">
            <div class="top-bar">
                <h2>Student Attendance</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Selection -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-content">
                    <form method="GET" action="/admin/attendance.php" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                        <div>
                            <label><strong>Class</strong></label>
                            <select name="class_id" class="form-control" style="width: 200px;" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $selected_class == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name'] ?? ''); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label><strong>Date</strong></label>
                            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($selected_date); ?>" style="width: 180px;" required>
                        </div>
                        <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Load Students</button>
                    </form>
                </div>
            </div>

            <?php if ($selected_class && !empty($students)): ?>
            <!-- Attendance Stats -->
            <?php if ($stats['total_records'] > 0): ?>
            <div class="stat-cards" style="margin-bottom: 20px;">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-check" style="color: #27ae60;"></i></div>
                    <div class="stat-details"><h3><?php echo $stats['present']; ?></h3><p>Present</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-times" style="color: #e74c3c;"></i></div>
                    <div class="stat-details"><h3><?php echo $stats['absent']; ?></h3><p>Absent</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock" style="color: #f39c12;"></i></div>
                    <div class="stat-details"><h3><?php echo $stats['late']; ?></h3><p>Late</p></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Attendance Form -->
            <div class="card">
                <div class="card-content">
                    <h3>Attendance — <?php echo htmlspecialchars($class_name); ?> | <?php echo date('l, F d, Y', strtotime($selected_date)); ?></h3>
                    
                    <div class="quick-actions" style="margin-top: 15px;">
                        <button type="button" class="btn-login" onclick="markAll('present')" style="background: #27ae60;"><i class="fas fa-check"></i> Mark All Present</button>
                        <button type="button" class="btn-login" onclick="markAll('absent')" style="background: #e74c3c;"><i class="fas fa-times"></i> Mark All Absent</button>
                    </div>

                    <form method="POST" action="/admin/attendance.php">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="save_attendance">
                        <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($selected_class); ?>">
                        <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selected_date); ?>">
                        
                        <div class="table-responsive" style="margin-top: 15px;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Index Number</th>
                                        <th>Student Name</th>
                                        <th style="width: 200px;">Status</th>
                                        <th>Reason (if absent/late)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student):
                                        $sid = (int)($student['id'] ?? 0);
                                        $status = $existing_attendance[$sid]['status'] ?? 'present';
                                        $reason = $existing_attendance[$sid]['reason'] ?? '';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['admission_number'] ?? ''); ?></td>
                                        <td><strong><?php echo htmlspecialchars($student['full_name'] ?? ''); ?></strong></td>
                                        <td>
                                            <div style="display: flex; gap: 5px;">
                                                <button type="button" class="status-btn present <?php echo $status === 'present' ? 'active' : ''; ?>" onclick="setStatus(this, 'present', <?php echo $sid; ?>)">Present</button>
                                                <button type="button" class="status-btn absent <?php echo $status === 'absent' ? 'active' : ''; ?>" onclick="setStatus(this, 'absent', <?php echo $sid; ?>)">Absent</button>
                                                <button type="button" class="status-btn late <?php echo $status === 'late' ? 'active' : ''; ?>" onclick="setStatus(this, 'late', <?php echo $sid; ?>)">Late</button>
                                            </div>
                                            <input type="hidden" class="attendance-status-val" name="attendance[<?php echo $sid; ?>]" value="<?php echo $status; ?>" id="status_<?php echo $sid; ?>">
                                        </td>
                                        <td><input type="text" name="reasons[<?php echo $sid; ?>]" class="form-control" value="<?php echo htmlspecialchars($reason); ?>" placeholder="Optional"></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <button type="submit" class="btn-primary" style="margin-top: 20px; width: 100%;"><i class="fas fa-save"></i> Save Attendance</button>
                    </form>
                </div>
            </div>
            <?php elseif ($selected_class && empty($students)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> No students found in this class.
                </div>
            <?php endif; ?>
        </main>
    <?php if (!isTeacher()): ?>
    </div>
    <?php endif; ?>

    <script>
    console.log('Student Attendance JS loaded');
    function setStatus(btn, status, studentId) {
        var row = btn.closest('tr');
        var btns = row.querySelectorAll('.status-btn');
        for (var i = 0; i < btns.length; i++) { btns[i].classList.remove('active'); }
        btn.classList.add('active');
        document.getElementById('status_' + studentId).value = status;
    }

    function markAll(status) {
        var allBtns = document.querySelectorAll('.status-btn');
        for (var i = 0; i < allBtns.length; i++) {
            allBtns[i].classList.remove('active');
            if (allBtns[i].textContent.trim().toLowerCase() === status) {
                allBtns[i].classList.add('active');
            }
        }
        var hiddenInputs = document.querySelectorAll('.attendance-status-val');
        for (var i = 0; i < hiddenInputs.length; i++) {
            hiddenInputs[i].value = status;
        }
    }
    </script>
</body>
</html>
