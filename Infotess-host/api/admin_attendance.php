<?php
require_once 'includes/db.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

// Get classes
$classes = $pdo->query("SELECT * FROM classes ORDER BY sort_order ASC")->fetchAll();

$selected_class = $_GET['class_id'] ?? '';
$selected_date = $_GET['date'] ?? date('Y-m-d');

// Get students in selected class
$students = [];
$class_name = '';
if ($selected_class) {
    $stmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
    $stmt->execute([$selected_class]);
    $class_name = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT id, full_name, index_number FROM students WHERE class_name = ? ORDER BY full_name ASC");
    $stmt->execute([$class_name]);
    $students = $stmt->fetchAll();
}

// Handle Save Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance') {
    $class_id = (int)$_POST['class_id'];
    $attendance_date = sanitize($_POST['attendance_date']);
    
    try {
        $pdo->beginTransaction();
        $saved = 0;
        
        foreach ($_POST['attendance'] as $student_id => $status) {
            $reason = sanitize($_POST['reasons'][$student_id] ?? '');
            
            $stmt = $pdo->prepare("INSERT INTO student_attendance (student_id, class_id, attendance_date, status, reason, recorded_by) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT (student_id, attendance_date) DO UPDATE SET status=?, reason=?, recorded_by=?");
            $stmt->execute([$student_id, $class_id, $attendance_date, $status, $reason, $_SESSION['user_id'], $status, $reason, $_SESSION['user_id']]);
            $saved++;
        }
        
        $pdo->commit();
        $message = "Attendance saved for $saved students on $attendance_date.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Get existing attendance for selected class and date
$existing_attendance = [];
if ($selected_class && $selected_date) {
    $stmt = $pdo->prepare("SELECT student_id, status, reason FROM student_attendance WHERE class_id = ? AND attendance_date = ?");
    $stmt->execute([$selected_class, $selected_date]);
    while ($row = $stmt->fetch()) {
        $existing_attendance[$row['student_id']] = $row;
    }
}

// Attendance statistics
$stats = [];
if ($selected_class) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_records,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
        FROM student_attendance 
        WHERE class_id = ? AND attendance_date = ?
    ");
    $stmt->execute([$selected_class, $selected_date]);
    $stats = $stmt->fetch();
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
        .status-btn:hover { opacity: 0.8; }
        .quick-actions { display: flex; gap: 10px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header" style="text-align: center; padding: 20px 10px;">
                <img src="../images/school-logo.png" alt="Logo" style="width: 80px; height: 80px; margin-bottom: 10px; border-radius: 50%; background: #fff; padding: 5px;" onerror="this.src='../images/aamusted.jpg'">
                <h3><?php echo htmlspecialchars($school_name); ?> Admin</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="admin_students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="admin_staff.php"><i class="fas fa-chalkboard-teacher"></i> Staff</a></li>
                <li><a href="admin_payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="admin_fees.php"><i class="fas fa-list-alt"></i> Fee Structure</a></li>
                <li><a href="admin_payroll.php"><i class="fas fa-file-invoice-dollar"></i> Payroll</a></li>
                <li><a href="admin_salary.php"><i class="fas fa-money-check-alt"></i> Salary Structures</a></li>
                <li><a href="admin_grades.php"><i class="fas fa-clipboard-list"></i> SBA / Grades</a></li>
                <li><a href="admin_attendance.php" class="active"><i class="fas fa-user-check"></i> Attendance</a></li>
                <li><a href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="admin_verify.php"><i class="fas fa-qrcode"></i> Verify Receipt</a></li>
                <li><a href="admin_users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="admin_messaging.php"><i class="fas fa-envelope"></i> Messaging</a></li>
                <li><a href="admin_inbox.php"><i class="fas fa-inbox"></i> Inbox</a></li>
                <li><a href="admin_module_settings.php"><i class="fas fa-cogs"></i> Module Settings</a></li>
                <li><a href="admin_settings.php"><i class="fas fa-tools"></i> System Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h2>Student Attendance</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Selection -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-content">
                    <form method="GET" action="admin_attendance.php" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
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

                    <form method="POST" action="admin_attendance.php">
                        <input type="hidden" name="action" value="save_attendance">
                        <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
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
                                        $status = $existing_attendance[$student['id']]['status'] ?? 'present';
                                        $reason = $existing_attendance[$student['id']]['reason'] ?? '';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['index_number']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                                        <td>
                                            <div style="display: flex; gap: 5px;">
                                                <button type="button" class="status-btn present <?php echo $status === 'present' ? 'active' : ''; ?>" onclick="setStatus(this, 'present', <?php echo $student['id']; ?>)">Present</button>
                                                <button type="button" class="status-btn absent <?php echo $status === 'absent' ? 'active' : ''; ?>" onclick="setStatus(this, 'absent', <?php echo $student['id']; ?>)">Absent</button>
                                                <button type="button" class="status-btn late <?php echo $status === 'late' ? 'active' : ''; ?>" onclick="setStatus(this, 'late', <?php echo $student['id']; ?>)">Late</button>
                                            </div>
                                            <input type="hidden" name="attendance[<?php echo $student['id']; ?>]" value="<?php echo $status; ?>" id="status_<?php echo $student['id']; ?>">
                                        </td>
                                        <td><input type="text" name="reasons[<?php echo $student['id']; ?>]" class="form-control" value="<?php echo htmlspecialchars($reason); ?>" placeholder="Optional"></td>
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
    </div>

    <script>
    function setStatus(btn, status, studentId) {
        const row = btn.closest('tr');
        row.querySelectorAll('.status-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('status_' + studentId).value = status;
    }

    function markAll(status) {
        document.querySelectorAll('.status-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.textContent.trim().toLowerCase() === status) {
                btn.classList.add('active');
            }
        });
        document.querySelectorAll('input[name^="attendance["]').forEach(input => {
            input.value = status;
        });
    }
    </script>
</body>
</html>
