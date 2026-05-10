<?php
require_once 'includes/db.php';

// Enforce access control
requireAccess('staff_attendance');

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

// After a successful POST, redirect to a GET URL to avoid re-submission on refresh
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_staff_attendance') {
    $attendance_date = sanitize($_POST['attendance_date']);
    try {
        $pdo->beginTransaction();
        $saved = 0;

        foreach ($_POST['staff_attendance'] as $staff_id => $data) {
            $status = sanitize($data['status'] ?? 'present');
            $check_in = isset($data['check_in']) && !empty($data['check_in']) ? $attendance_date . ' ' . $data['check_in'] : null;
            $check_out = isset($data['check_out']) && !empty($data['check_out']) ? $attendance_date . ' ' . $data['check_out'] : null;
            $notes = sanitize($data['notes'] ?? '');

            // Bridge doesn't support ON CONFLICT — use SELECT-then-UPDATE-or-INSERT
            $existing = $pdo->prepare("SELECT id FROM staff_attendance WHERE staff_id = ? AND attendance_date = ?");
            $existing->execute([$staff_id, $attendance_date]);
            if ($existing->fetch()) {
                $stmt = $pdo->prepare("UPDATE staff_attendance SET check_in=?, check_out=?, status=?, notes=? WHERE staff_id=? AND attendance_date=?");
                $stmt->execute([$check_in, $check_out, $status, $notes, $staff_id, $attendance_date]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO staff_attendance (staff_id, attendance_date, check_in, check_out, status, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$staff_id, $attendance_date, $check_in, $check_out, $status, $notes]);
            }
            $saved++;
        }

        $pdo->commit();
        header("Location: admin/staff_attendance.php?date=" . urlencode($attendance_date) . "&msg=" . urlencode("Staff attendance saved for $saved members on $attendance_date."));
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: admin/staff_attendance.php?date=" . urlencode($attendance_date) . "&err=" . urlencode("Error: " . $e->getMessage()));
        exit;
    }
}

$selected_date = $_GET['date'] ?? date('Y-m-d');
$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';



// Get all staff (filter active in PHP — bridge drops WHERE status = 'active')
$all_staff = $pdo->query("SELECT id, staff_id, full_name, position, status FROM staff")->fetchAll();
$staff_list = array_filter($all_staff, fn($s) => ($s['status'] ?? '') === 'active');
usort($staff_list, fn($a, $b) => strcmp($a['full_name'], $b['full_name']));

// Get existing attendance for selected date
$existing_attendance = [];
try {
    $stmt = $pdo->prepare("SELECT staff_id, status, check_in, check_out, notes FROM staff_attendance WHERE attendance_date = ?");
    $stmt->execute([$selected_date]);
    while ($row = $stmt->fetch()) {
        $existing_attendance[(int)$row['staff_id']] = $row;
    }
} catch (Exception $e) {
    // staff_attendance table may not exist yet
}

// Stats for selected date — build in PHP (bridge drops complex aggregation)
$stats = ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0];
try {
    $all_attendance = $pdo->query("SELECT * FROM staff_attendance")->fetchAll();
    $date_records = array_filter($all_attendance, fn($r) => $r['attendance_date'] === $selected_date);
    $stats = [
        'total'   => count($date_records),
        'present' => count(array_filter($date_records, fn($r) => ($r['status'] ?? '') === 'present')),
        'absent'  => count(array_filter($date_records, fn($r) => ($r['status'] ?? '') === 'absent')),
        'late'    => count(array_filter($date_records, fn($r) => ($r['status'] ?? '') === 'late')),
    ];
} catch (Exception $e) {
    // staff_attendance table may not exist yet
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Attendance — <?php echo htmlspecialchars($school_name); ?> Admin</title>
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
    </style>
</head>
<body>
    <div class="dashboard-container">
            <?php echo renderSidebar('staff_attendance', $school_name); ?>

        <main class="main-content">
            <div class="top-bar">
                <h2>Staff Attendance</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Date Selector -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-content">
                    <form method="GET" action="admin/staff_attendance.php" style="display: flex; gap: 15px; align-items: flex-end;">
                        <div>
                            <label><strong>Date</strong></label>
                            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($selected_date); ?>" required>
                        </div>
                        <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Load</button>
                    </form>
                </div>
            </div>

            <?php if (!empty($staff_list)): ?>
            <!-- Stats -->
            <?php if ($stats['total'] > 0): ?>
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
                    <h3>Attendance — <?php echo date('l, F d, Y', strtotime($selected_date)); ?></h3>
                    
                    <div style="display: flex; gap: 10px; margin: 15px 0;">
                        <button type="button" class="btn-login" onclick="markAll('present')" style="background: #27ae60;"><i class="fas fa-check"></i> All Present</button>
                        <button type="button" class="btn-login" onclick="markAll('absent')" style="background: #e74c3c;"><i class="fas fa-times"></i> All Absent</button>
                    </div>

                    <form method="POST" action="admin/staff_attendance.php">
                        <input type="hidden" name="action" value="save_staff_attendance">
                        <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selected_date); ?>">
                        
                        <div class="table-responsive" style="margin-top: 15px;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Staff ID</th>
                                        <th>Name</th>
                                        <th>Position</th>
                                        <th style="width: 180px;">Status</th>
                                        <th style="width: 100px;">Check In</th>
                                        <th style="width: 100px;">Check Out</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($staff_list as $staff):
                                        $staffId = (int)($staff['id'] ?? 0);
                                        $att = $existing_attendance[$staffId] ?? null;
                                        $status = $att ? ($att['status'] ?? 'present') : 'present';
                                        $check_in = $att && !empty($att['check_in']) ? date('H:i', strtotime($att['check_in'])) : '';
                                        $check_out = $att && !empty($att['check_out']) ? date('H:i', strtotime($att['check_out'])) : '';
                                        $notes = $att ? ($att['notes'] ?? '') : '';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($staff['staff_id']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($staff['full_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($staff['position']); ?></td>
                                        <td>
                                            <div style="display: flex; gap: 5px;">
                                                <button type="button" class="status-btn present <?php echo $status === 'present' ? 'active' : ''; ?>" onclick="setStatus(this, 'present', <?php echo $staffId; ?>)">Present</button>
                                                <button type="button" class="status-btn absent <?php echo $status === 'absent' ? 'active' : ''; ?>" onclick="setStatus(this, 'absent', <?php echo $staffId; ?>)">Absent</button>
                                                <button type="button" class="status-btn late <?php echo $status === 'late' ? 'active' : ''; ?>" onclick="setStatus(this, 'late', <?php echo $staffId; ?>)">Late</button>
                                            </div>
                                            <input type="hidden" class="staff-status-val" name="staff_attendance[<?php echo $staffId; ?>][status]" value="<?php echo $status; ?>" id="status_<?php echo $staffId; ?>">
                                        </td>
                                        <td><input type="time" name="staff_attendance[<?php echo $staffId; ?>][check_in]" class="form-control" value="<?php echo htmlspecialchars($check_in); ?>" style="width: 100px;"></td>
                                        <td><input type="time" name="staff_attendance[<?php echo $staffId; ?>][check_out]" class="form-control" value="<?php echo htmlspecialchars($check_out); ?>" style="width: 100px;"></td>
                                        <td><input type="text" name="staff_attendance[<?php echo $staffId; ?>][notes]" class="form-control" value="<?php echo htmlspecialchars($notes); ?>" placeholder="Optional"></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <button type="submit" class="btn-primary" style="margin-top: 20px; width: 100%;"><i class="fas fa-save"></i> Save Staff Attendance</button>
                    </form>
                </div>
            </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No active staff members found. <a href="staff.php">Add staff first</a>.
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
    console.log('Staff Attendance JS loaded');
    function setStatus(btn, status, staffId) {
        // Find the row and update visual active state
        var row = btn.closest('tr');
        var btns = row.querySelectorAll('.status-btn');
        for (var i = 0; i < btns.length; i++) { btns[i].classList.remove('active'); }
        btn.classList.add('active');
        // Update the hidden input value
        document.getElementById('status_' + staffId).value = status;
    }

    function markAll(status) {
        // Update visual active state on all status buttons
        var allBtns = document.querySelectorAll('.status-btn');
        for (var i = 0; i < allBtns.length; i++) {
            allBtns[i].classList.remove('active');
            if (allBtns[i].textContent.trim().toLowerCase() === status) {
                allBtns[i].classList.add('active');
            }
        }
        // Update all hidden status input values using class selector
        var hiddenInputs = document.querySelectorAll('.staff-status-val');
        for (var i = 0; i < hiddenInputs.length; i++) {
            hiddenInputs[i].value = status;
        }
    }
    </script>
</body>
</html>
