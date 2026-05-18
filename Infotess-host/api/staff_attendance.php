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

$selected_month = $_GET['month'] ?? date('m');
$selected_year = $_GET['year'] ?? date('Y');

// Fetch all attendance records for this staff
$all_attendance = $pdo->query("SELECT * FROM staff_attendance")->fetchAll();
$my_attendance = array_filter($all_attendance, function($a) use ($staff_id) {
    return (int)$a['staff_id'] === $staff_id;
});

// Filter by selected month/year
$filtered_attendance = array_filter($my_attendance, function($a) use ($selected_month, $selected_year) {
    return date('m', strtotime($a['attendance_date'])) === str_pad($selected_month, 2, '0', STR_PAD_LEFT)
        && date('Y', strtotime($a['attendance_date'])) === $selected_year;
});

usort($filtered_attendance, function($a, $b) {
    return strcmp($b['attendance_date'], $a['attendance_date']);
});

// Stats
$present = count(array_filter($filtered_attendance, fn($a) => ($a['status'] ?? '') === 'present'));
$absent = count(array_filter($filtered_attendance, fn($a) => ($a['status'] ?? '') === 'absent'));
$late = count(array_filter($filtered_attendance, fn($a) => ($a['status'] ?? '') === 'late'));
$total = count($filtered_attendance);

// Overall stats (all time)
$total_present = count(array_filter($my_attendance, fn($a) => ($a['status'] ?? '') === 'present'));
$total_absent = count(array_filter($my_attendance, fn($a) => ($a['status'] ?? '') === 'absent'));
$total_late = count(array_filter($my_attendance, fn($a) => ($a['status'] ?? '') === 'late'));
$total_all = count($my_attendance);
$attendance_rate = $total_all > 0 ? round(($total_present / $total_all) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .staff-container { display: flex; min-height: 100vh; }
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
        .staff-sidebar ul li a { display: block; padding: 14px 20px; color: rgba(255,255,255,0.85); text-decoration: none; font-size: 14px; transition: all 0.2s; }
        .staff-sidebar ul li a:hover, .staff-sidebar ul li a.active { background: rgba(255,255,255,0.1); color: white; padding-left: 25px; }
        .staff-sidebar ul li a i { width: 22px; text-align: center; margin-right: 8px; }
        .staff-main { flex: 1; padding: 30px; background: #f4f6f9; margin-left: 250px; }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: center; justify-content: space-between; }
        .top-bar h2 { font-size: 20px; margin: 0; color: #1a5276; }
        .stat-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 18px; margin-bottom: 25px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); text-align: center; }
        .stat-card .num { font-size: 28px; font-weight: 700; margin: 0; }
        .stat-card .lbl { font-size: 13px; color: #888; margin: 5px 0 0; }
        .stat-card.green .num { color: #27ae60; }
        .stat-card.red .num { color: #e74c3c; }
        .stat-card.orange .num { color: #f39c12; }
        .stat-card.blue .num { color: #1a5276; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .status-present { background: #e6f7e6; color: #27ae60; }
        .status-absent { background: #ffe6e6; color: #e74c3c; }
        .status-late { background: #fff3e0; color: #f39c12; }
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
    <?php echo renderStaffSidebar('attendance', $school_name, 0, $staff['profile_picture'] ?? '', $staff['full_name'] ?? ''); ?>

    <div class="staff-main">
        <div class="top-bar">
            <div>
                <h2>My Attendance</h2>
                <p class="subtitle" style="font-size:13px;color:#888;margin:3px 0 0;">
                    Overall Rate: <strong style="color:<?php echo $attendance_rate >= 80 ? '#27ae60' : ($attendance_rate >= 60 ? '#f39c12' : '#e74c3c'); ?>"><?php echo $attendance_rate; ?>%</strong>
                    &bull; <?php echo $total_present; ?> Present / <?php echo $total_absent; ?> Absent / <?php echo $total_late; ?> Late
                </p>
            </div>
        </div>

        <div class="stat-cards">
            <div class="stat-card green">
                <p class="num"><?php echo $present; ?></p>
                <p class="lbl">Present</p>
            </div>
            <div class="stat-card red">
                <p class="num"><?php echo $absent; ?></p>
                <p class="lbl">Absent</p>
            </div>
            <div class="stat-card orange">
                <p class="num"><?php echo $late; ?></p>
                <p class="lbl">Late</p>
            </div>
            <div class="stat-card blue">
                <p class="num"><?php echo $total; ?></p>
                <p class="lbl">Total Records</p>
            </div>
        </div>

        <div style="margin-bottom:20px;">
            <form method="GET" action="../staff/attendance.php" style="display:flex;gap:10px;align-items:flex-end;">
                <div>
                    <label><strong>Month</strong></label>
                    <select name="month" class="form-control" style="width:120px;">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo $selected_month == $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label><strong>Year</strong></label>
                    <select name="year" class="form-control" style="width:100px;">
                        <?php for ($y = (int)date('Y') - 2; $y <= (int)date('Y'); $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo (int)$selected_year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary btn-sm"><i class="fas fa-search"></i> View</button>
            </form>
        </div>

        <div class="card">
            <div class="card-content">
                <h3>Attendance Records — <?php echo date('F Y', mktime(0,0,0,(int)$selected_month,1,(int)$selected_year)); ?></h3>
                <?php if (empty($filtered_attendance)): ?>
                    <div class="alert alert-info" style="margin-top:15px;"><i class="fas fa-info-circle"></i> No attendance records for this month.</div>
                <?php else: ?>
                <div class="table-responsive" style="margin-top:15px;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Status</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filtered_attendance as $a): ?>
                            <tr>
                                <td><strong><?php echo date('M d, Y', strtotime($a['attendance_date'])); ?></strong></td>
                                <td><?php echo date('l', strtotime($a['attendance_date'])); ?></td>
                                <td><span class="status-badge status-<?php echo $a['status']; ?>"><?php echo ucfirst($a['status']); ?></span></td>
                                <td><?php echo $a['check_in'] ? date('h:i A', strtotime($a['check_in'])) : '-'; ?></td>
                                <td><?php echo $a['check_out'] ? date('h:i A', strtotime($a['check_out'])) : '-'; ?></td>
                                <td><?php echo htmlspecialchars($a['notes'] ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
