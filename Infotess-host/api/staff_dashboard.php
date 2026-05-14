<?php
require_once 'includes/db.php';

if (!isLoggedIn() || (!isStaff() && !isTeacher())) {
    redirect('../login.php');
}

$settings = fetchSettings($pdo);
$school_name = $settings['school_name'] ?? 'Nex CEC';

$user_id = $_SESSION['user_id'];

// Fetch staff record
$stmt = $pdo->prepare("SELECT * FROM staff WHERE user_id = ?");
$stmt->execute([$user_id]);
$staff = $stmt->fetch();

if (!$staff) {
    echo '<div class="container" style="padding:100px 0;text-align:center;"><h2>Staff record not found</h2><a href="../logout.php" class="btn-primary">Logout</a></div>';
    exit;
}

$staff_id = (int)$staff['id'];

// Fetch latest payroll record
$stmt = $pdo->prepare("SELECT * FROM payroll WHERE staff_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$staff_id]);
$latest_payroll = $stmt->fetch();

// Fetch this month's attendance
$this_month = date('m');
$this_year = date('Y');
$all_attendance = $pdo->query("SELECT * FROM staff_attendance")->fetchAll();
$monthly_attendance = array_filter($all_attendance, function($a) use ($staff_id, $this_month, $this_year) {
    return (int)$a['staff_id'] === $staff_id
        && date('m', strtotime($a['attendance_date'])) === $this_month
        && date('Y', strtotime($a['attendance_date'])) === $this_year;
});
$present_days = count(array_filter($monthly_attendance, fn($a) => ($a['status'] ?? '') === 'present'));
$absent_days = count(array_filter($monthly_attendance, fn($a) => ($a['status'] ?? '') === 'absent'));
$late_days = count(array_filter($monthly_attendance, fn($a) => ($a['status'] ?? '') === 'late'));

// Fetch assigned subjects for teachers
$teacher_subjects = [];
if (isTeacher()) {
    $stmt = $pdo->prepare("SELECT s.*, c.name AS class_name FROM subjects s LEFT JOIN classes c ON s.class_id = c.id WHERE s.teacher_id = ?");
    $stmt->execute([$staff_id]);
    $teacher_subjects = $stmt->fetchAll();
}

// Fetch unread messages
$stmt = $pdo->prepare("SELECT id FROM messages WHERE receiver_id = ?");
$stmt->execute([$user_id]);
$direct_ids = array_map(fn($r) => (int)$r['id'], $stmt->fetchAll());
$stmt = $pdo->prepare("SELECT id FROM messages WHERE is_broadcast = ?");
$stmt->execute([1]);
$broadcast_ids = array_map(fn($r) => (int)$r['id'], $stmt->fetchAll());
$all_msg_ids = array_unique(array_merge($direct_ids, $broadcast_ids));
$stmt = $pdo->prepare("SELECT message_id FROM message_reads WHERE user_id = ?");
$stmt->execute([$user_id]);
$read_ids = array_map(fn($r) => (int)$r['message_id'], $stmt->fetchAll());
$unread_count = 0;
foreach ($all_msg_ids as $mid) {
    if (!in_array($mid, $read_ids)) $unread_count++;
}
// Also check read_at for direct messages
foreach (array_chunk($all_msg_ids, 50) as $chunk) {
    if (empty($chunk)) continue;
    $placeholders = implode(',', array_fill(0, count($chunk), '?'));
    $stmt = $pdo->prepare("SELECT id FROM messages WHERE id IN ($placeholders) AND read_at IS NULL");
    $stmt->execute($chunk);
    foreach ($stmt->fetchAll() as $r) {
        if (!in_array((int)$r['id'], $read_ids)) $unread_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard — <?php echo htmlspecialchars($school_name); ?></title>
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
        .staff-main { flex: 1; padding: 30px; background: #f4f6f9; margin-left: 250px; }
        .top-bar {
            background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: center; justify-content: space-between;
        }
        .top-bar h2 { font-size: 20px; margin: 0; color: #1a5276; }
        .top-bar .subtitle { font-size: 13px; color: #888; margin: 3px 0 0; }
        .stat-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 18px; margin-bottom: 30px; }
        .stat-card {
            background: white; border-radius: 10px; padding: 22px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex; align-items: center; gap: 16px;
        }
        .stat-card .icon {
            width: 48px; height: 48px; border-radius: 12px; display: flex;
            align-items: center; justify-content: center; font-size: 22px;
        }
        .stat-card .icon.blue { background: #e8f0fe; color: #1a5276; }
        .stat-card .icon.green { background: #e6f7e6; color: #27ae60; }
        .stat-card .icon.orange { background: #fff3e0; color: #f39c12; }
        .stat-card .icon.purple { background: #f0e6ff; color: #8e44ad; }
        .stat-card .info h3 { font-size: 22px; margin: 0; }
        .stat-card .info p { font-size: 13px; color: #888; margin: 2px 0 0; }
        .profile-section {
            background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 25px; margin-bottom: 25px;
        }
        .profile-section h3 { font-size: 16px; color: #1a5276; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .info-grid .item { font-size: 14px; }
        .info-grid .item .label { color: #888; display: block; font-size: 12px; }
        .info-grid .item .value { font-weight: 600; color: #333; }
        .hamburger-menu { display: none; position: fixed; top: 15px; left: 15px; z-index: 200;
            background: #1a5276; color: white; border: none; width: 40px; height: 40px;
            border-radius: 8px; font-size: 18px; cursor: pointer;
        }
        @media (max-width: 768px) {
            .staff-sidebar { left: -250px; transition: left 0.3s; }
            .staff-sidebar.open { left: 0; }
            .staff-main { margin-left: 0; padding: 20px; }
            .hamburger-menu { display: block; }
            .top-bar { flex-direction: column; text-align: center; margin-top: 50px; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <button class="hamburger-menu" id="hamburgerBtn" onclick="document.getElementById('sidebar').classList.toggle('open')">
        <i class="fas fa-bars"></i>
    </button>

    <aside class="staff-sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="../images/school-logo.png" alt="Logo" onerror="this.src='../images/aamusted.jpg'">
            <h3><?php echo htmlspecialchars($school_name); ?></h3>
            <p>Staff Portal</p>
        </div>
        <ul>
            <li><a href="../staff/dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <?php if (isTeacher()): ?>
            <li><a href="../staff/grades.php"><i class="fas fa-clipboard-list"></i> SBA / Grades</a></li>
            <?php endif; ?>
            <li><a href="../staff/attendance.php"><i class="fas fa-calendar-check"></i> My Attendance</a></li>
            <li><a href="../staff/payslip.php"><i class="fas fa-file-invoice-dollar"></i> Pay Slips</a></li>
            <li><a href="../staff/profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
            <li>
                <a href="../admin/messaging.php">
                    <i class="fas fa-envelope"></i> Messages
                    <?php if ($unread_count > 0): ?>
                        <span class="msg-count"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php if (isTeacher()): ?>
            <li><a href="../admin/attendance.php"><i class="fas fa-user-check"></i> Student Attendance</a></li>
            <?php endif; ?>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <div class="staff-main">
        <div class="top-bar">
            <div>
                <h2>Welcome, <?php echo htmlspecialchars($staff['full_name'] ?? 'Staff'); ?></h2>
                <p class="subtitle"><?php echo htmlspecialchars($staff['position'] ?? ''); ?> &bull; <?php echo htmlspecialchars($staff['department'] ?? 'General'); ?></p>
            </div>
            <div>
                <span style="font-size: 13px; color: #888;">Staff ID: <?php echo htmlspecialchars($staff['staff_id'] ?? 'N/A'); ?></span>
            </div>
        </div>

        <div class="stat-cards">
            <div class="stat-card">
                <div class="icon green"><i class="fas fa-check-circle"></i></div>
                <div class="info">
                    <h3><?php echo $present_days; ?></h3>
                    <p>Present This Month</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon orange"><i class="fas fa-clock"></i></div>
                <div class="info">
                    <h3><?php echo $late_days; ?></h3>
                    <p>Late This Month</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon purple"><i class="fas fa-file-invoice-dollar"></i></div>
                <div class="info">
                    <h3><?php echo $latest_payroll ? 'GHS ' . number_format($latest_payroll['net_pay'], 2) : 'N/A'; ?></h3>
                    <p>Latest Net Pay</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon blue"><i class="fas fa-envelope"></i></div>
                <div class="info">
                    <h3><?php echo $unread_count; ?></h3>
                    <p>Unread Messages</p>
                </div>
            </div>
        </div>

        <div class="profile-section">
            <h3><i class="fas fa-user"></i> My Information</h3>
            <div class="info-grid">
                <div class="item"><span class="label">Full Name</span><span class="value"><?php echo htmlspecialchars($staff['full_name'] ?? ''); ?></span></div>
                <div class="item"><span class="label">Staff ID</span><span class="value"><?php echo htmlspecialchars($staff['staff_id'] ?? ''); ?></span></div>
                <div class="item"><span class="label">Position</span><span class="value"><?php echo htmlspecialchars($staff['position'] ?? ''); ?></span></div>
                <div class="item"><span class="label">Department</span><span class="value"><?php echo htmlspecialchars($staff['department'] ?? 'N/A'); ?></span></div>
                <div class="item"><span class="label">Email</span><span class="value"><?php echo htmlspecialchars($staff['email'] ?? ''); ?></span></div>
                <div class="item"><span class="label">Phone</span><span class="value"><?php echo htmlspecialchars($staff['phone'] ?? 'N/A'); ?></span></div>
                <div class="item"><span class="label">Hire Date</span><span class="value"><?php echo htmlspecialchars($staff['hire_date'] ?? 'N/A'); ?></span></div>
                <div class="item"><span class="label">Status</span><span class="value"><span class="badge badge-success"><?php echo htmlspecialchars(ucfirst($staff['status'] ?? 'Active')); ?></span></span></div>
            </div>
        </div>

        <?php if (isTeacher() && !empty($teacher_subjects)): ?>
        <div class="profile-section">
            <h3><i class="fas fa-book"></i> My Assigned Subjects</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Code</th>
                        <th>Class</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teacher_subjects as $subj): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($subj['name']); ?></td>
                        <td><?php echo htmlspecialchars($subj['code']); ?></td>
                        <td><?php echo htmlspecialchars($subj['class_name'] ?? 'All'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($latest_payroll): ?>
        <div class="profile-section">
            <h3><i class="fas fa-file-invoice-dollar"></i> Latest Pay Slip Summary</h3>
            <div class="info-grid">
                <div class="item"><span class="label">Month</span><span class="value"><?php echo date('F Y', mktime(0,0,0,(int)$latest_payroll['month'],1,(int)$latest_payroll['year'])); ?></span></div>
                <div class="item"><span class="label">Gross Pay</span><span class="value">GHS <?php echo number_format($latest_payroll['gross_pay'], 2); ?></span></div>
                <div class="item"><span class="label">Total Deductions</span><span class="value" style="color:#e74c3c;">GHS <?php echo number_format($latest_payroll['total_deductions'], 2); ?></span></div>
                <div class="item"><span class="label">Net Pay</span><span class="value" style="color:#27ae60;font-size:18px;">GHS <?php echo number_format($latest_payroll['net_pay'], 2); ?></span></div>
                <div class="item"><span class="label">Status</span><span class="value">
                    <span class="badge <?php echo $latest_payroll['status'] === 'paid' ? 'badge-success' : ($latest_payroll['status'] === 'approved' ? 'badge-info' : 'badge-warning'); ?>">
                        <?php echo ucfirst($latest_payroll['status']); ?>
                    </span>
                </span></div>
                <?php if ($latest_payroll['pay_date']): ?>
                <div class="item"><span class="label">Pay Date</span><span class="value"><?php echo htmlspecialchars($latest_payroll['pay_date']); ?></span></div>
                <?php endif; ?>
            </div>
            <div style="margin-top:15px;">
                <a href="../staff/payslip.php?id=<?php echo $latest_payroll['id']; ?>" class="btn-primary"><i class="fas fa-eye"></i> View Full Pay Slip</a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('hamburgerBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });
        document.addEventListener('click', function(e) {
            var sidebar = document.getElementById('sidebar');
            var btn = document.getElementById('hamburgerBtn');
            if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !btn.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    });
    </script>
</body>
</html>
