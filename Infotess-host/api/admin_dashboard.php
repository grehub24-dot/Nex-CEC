<?php
require_once 'includes/db.php';

// Enforce access control
requireAccess('dashboard');

// Fetch Current Settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$school_name = $settings['school_name'] ?? 'Nex CEC';
$current_year = $settings['current_academic_year'] ?? date('Y') . '/' . (date('Y') + 1);
$required_dues = isset($settings['annual_dues_amount']) ? (float)$settings['annual_dues_amount'] : 500.00;
$current_role = $_SESSION['role'] ?? 'admin';
$display_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';

// ==========================================
// Fetch Stats — Supabase REST bridge drops WHERE clauses (IS NULL, AND, date functions).
// Workaround: fetch all rows, count/filter in PHP.
// ==========================================
try {
    // --- Students: only columns needed (prevent 413 PAYLOAD_TOO_LARGE via SELECT *) ---
    $all_students = $pdo->query("SELECT id, admission_number, full_name, status FROM students")->fetchAll();
    $total_students = count(array_filter($all_students, fn($s) => !empty($s['admission_number']) && ($s['status'] ?? '') !== 'rejected'));
    $pending_students = count(array_filter($all_students, fn($s) => empty($s['admission_number']) && ($s['status'] ?? '') !== 'rejected'));

    // --- Staff: only columns needed ---
    $all_staff = $pdo->query("SELECT id, status FROM staff")->fetchAll();
    $total_staff = count(array_filter($all_staff, fn($s) => ($s['status'] ?? '') === 'active'));

    // --- Payments: only columns needed ---
    $all_payments = $pdo->query("SELECT id, student_id, amount, payment_date, created_at, receipt_number, payment_method FROM payments ORDER BY payment_date DESC")->fetchAll();
    $today = date('Y-m-d');
    $today_payments = array_filter($all_payments, fn($p) => substr($p['payment_date'] ?? '', 0, 10) === $today);
    $payments_today = count($today_payments);
    $total_payments = count($all_payments);
    $total_revenue = 0;
    foreach ($all_payments as $p) { $total_revenue += (float)($p['amount'] ?? 0); }
    $students_paid = count(array_unique(array_filter(array_column($all_payments, 'student_id'), fn($id) => !empty($id))));

    // --- Messages: only columns needed ---
    $all_messages = $pdo->query("SELECT id, is_read FROM messages")->fetchAll();
    $pending_messages = count(array_filter($all_messages, fn($m) => empty($m['is_read'])));

    // --- Attendance (today): only columns needed ---
    $all_attendance = $pdo->query("SELECT id, attendance_date, status FROM student_attendance")->fetchAll();
    $absent_today = count(array_filter($all_attendance, fn($a) => substr($a['attendance_date'] ?? '', 0, 10) === $today && ($a['status'] ?? '') === 'absent'));

    // --- Stats ---
    $compliance_rate = $total_students > 0 ? round(($students_paid / $total_students) * 100, 1) : 0;
    $outstanding_students = max(0, $total_students - $students_paid);

    // --- Recent Payments (enrich with student names) ---
    $recent_payments = $all_payments;
    usort($recent_payments, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    $recent_payments = array_slice($recent_payments, 0, 10);
    $student_map = [];
    foreach ($all_students as $s) { $student_map[$s['id']] = $s; }
    foreach ($recent_payments as &$payment) {
        $stu = $student_map[$payment['student_id']] ?? null;
        $payment['full_name'] = $stu['full_name'] ?? 'Unknown';
        $payment['admission_number'] = $stu['admission_number'] ?? '-';
    }

    // --- Chart: monthly revenue ---
    $monthly_totals = [];
    foreach ($all_payments as $row) {
        $date = date('M Y', strtotime($row['payment_date'] ?? 'now'));
        $monthly_totals[$date] = ($monthly_totals[$date] ?? 0) + (float)($row['amount'] ?? 0);
    }
    $chart_labels = array_keys($monthly_totals);
    $chart_data = array_values($monthly_totals);
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    $total_students = $total_staff = $payments_today = $total_payments = $students_paid = $pending_messages = $absent_today = 0;
    $total_revenue = $compliance_rate = 0;
    $recent_payments = [];
    $chart_labels = [date('M Y')];
    $chart_data = [0];
}

if (empty($chart_labels)) { $chart_labels = [date('M Y')]; $chart_data = [0]; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/design-tokens.css">
    <link rel="stylesheet" href="../css/typography.css">
    <link rel="stylesheet" href="../css/layout.css">
    <link rel="stylesheet" href="../css/components.css">
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/3d-school.css">
    <link rel="stylesheet" href="../css/style.css"><!-- legacy compat -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Showcase section styles -->
    <style>
        /* Section header with left accent bar */
        .section-header-accent {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            margin-bottom: var(--space-lg);
        }
        .section-header-accent::before {
            content: '';
            width: 4px;
            height: 24px;
            background: var(--color-eduman-blue, var(--color-primary));
            border-radius: 2px;
            flex-shrink: 0;
        }
        .section-header-accent h3 {
            margin: 0;
        }
        .badge-showcase {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 3px 8px;
            border-radius: var(--radius-full);
            background: var(--color-eduman-blue, var(--color-primary));
            color: var(--color-on-dark);
            margin-left: auto;
        }
        /* Enhanced quick action cards */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: var(--space-md);
            margin-top: var(--space-md);
        }
        .quick-actions-grid .card {
            text-align: center;
            padding: var(--space-lg);
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: var(--space-sm);
            transition: all var(--transition-base);
        }
        .quick-actions-grid .card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
            border-color: var(--color-eduman-blue, var(--color-primary));
        }
        .quick-actions-grid .card i {
            font-size: 28px;
            transition: transform var(--transition-fast);
        }
        .quick-actions-grid .card:hover i {
            transform: scale(1.12);
        }
        .quick-actions-grid .card span {
            font-size: var(--text-sm-size);
            font-weight: 600;
            color: var(--color-charcoal);
        }
        /* Stat card accent overrides for showcase */
        .stat-card-eduman {
            border-left: 4px solid var(--color-eduman-blue, #2467ec);
        }
        .stat-card-accent {
            border-left: 4px solid var(--color-accent, #E8A838);
        }
    </style>
</head>
<body class="page-dashboard-eduman">
    <a href="#main-content" class="skip-link" style="position: absolute; top: -100%; left: 0; background: var(--color-primary); color: var(--color-on-dark); padding: 10px 20px; z-index: 9999; transition: top 0.2s;">Skip to main content</a>
    <style>.skip-link:focus { top: 0; }</style>
    <div class="dashboard-container">
            <?php echo renderSidebar('dashboard', $school_name); ?>

        <!-- Main Content -->
        <main class="main-content" id="main-content">
            <div class="top-bar">
                <h2>Dashboard Overview</h2>
                <div class="user-info"><span>Welcome, <?php echo htmlspecialchars($display_name); ?> (<?php echo ucfirst($current_role); ?>)</span></div>
            </div>

            <?php if (isTeacher()): ?>
            <?php
                // Teacher-scoped view: show assigned classes, subjects, and students
                $teacher_class_ids = getTeacherClassIds($pdo);
                $teacher_classes = [];
                $teacher_subjects = [];
                $teacher_student_count = 0;
                if (!empty($teacher_class_ids)) {
                    // Fetch class names
                    foreach ($teacher_class_ids as $cid) {
                        $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
                        $stmt->execute([$cid]);
                        $cls = $stmt->fetch();
                        if ($cls) $teacher_classes[] = $cls['name'];
                    }
                    // Count students in these classes (parametrize all values — bridge rejects mixed literals)
                    foreach ($teacher_classes as $cname) {
                        $stmt = $pdo->prepare("SELECT id FROM students WHERE class_name = ? AND status = ?");
                        $stmt->execute([$cname, 'active']);
                        $teacher_student_count += count($stmt->fetchAll());
                    }
                    // Fetch assigned subjects (two-step: bridge cannot handle JOINs or subqueries)
                    $teacher_subjects = [];
                    $stmt = $pdo->prepare("SELECT id FROM staff WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $staff_row = $stmt->fetch();
                    if ($staff_row) {
                        $staff_id = (int)$staff_row['id'];
                        $stmt = $pdo->prepare("SELECT * FROM subjects WHERE teacher_id = ?");
                        $stmt->execute([$staff_id]);
                        $teacher_subjects_raw = $stmt->fetchAll();
                        foreach ($teacher_subjects_raw as $subj) {
                            $stmt2 = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
                            $stmt2->execute([(int)$subj['class_id']]);
                            $cls = $stmt2->fetch();
                            $subj['class_name'] = $cls ? $cls['name'] : 'Unknown';
                            $teacher_subjects[] = $subj;
                        }
                    }
                }
            ?>
                <div class="stat-cards" style="margin-bottom: 20px;">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-chalkboard"></i></div>
                        <div class="stat-details">
                            <h3><?php echo count($teacher_classes); ?></h3>
                            <p>My Classes</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-book"></i></div>
                        <div class="stat-details">
                            <h3><?php echo count($teacher_subjects); ?></h3>
                            <p>My Subjects</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                        <div class="stat-details">
                            <h3><?php echo $teacher_student_count; ?></h3>
                            <p>My Students</p>
                        </div>
                    </div>
                </div>
                <?php if (!empty($teacher_classes)): ?>
                    <div style="margin-bottom: 25px; background: var(--color-canvas); padding: 18px 22px; border-radius: var(--radius-lg); box-shadow: var(--shadow-md);">
                        <strong style="font-size:14px; color: var(--color-brand-navy);">Assigned Classes:</strong>
                        <span style="margin-left: 10px; color: var(--color-slate);"><?php echo htmlspecialchars(implode(', ', $teacher_classes)); ?></span>
                    </div>
                <?php endif; ?>
            <?php else: ?>

            <?php if (isset($_SESSION['access_denied'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-lock"></i> Access Denied: You do not have permission to view that page.
                </div>
                <?php unset($_SESSION['access_denied']); ?>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stat-cards">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="stat-details"><h3><?php echo number_format($total_students); ?></h3><p>Total Students</p></div>
                </div>
                <?php if (isSuperAdmin()): ?>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="stat-details"><h3><?php echo number_format($total_staff); ?></h3><p>Active Staff</p></div>
                </div>
                <?php endif; ?>
                <div class="stat-card stat-card-eduman">
                    <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                    <div class="stat-details"><h3>GHS <?php echo number_format($total_revenue, 2); ?></h3><p>Total Revenue</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                    <div class="stat-details"><h3><?php echo $payments_today; ?></h3><p>Payments Today</p></div>
                </div>
                <div class="stat-card stat-card-accent">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-details"><h3><?php echo $compliance_rate; ?>%</h3><p>Payment Compliance</p></div>
                </div>
                <?php if (isSuperAdmin()): ?>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
                    <div class="stat-details"><h3><?php echo $absent_today; ?></h3><p>Absent Today</p></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quick Links — redesigned showcase -->
            <div class="section-block">
                <div class="section-header-accent">
                    <h3>Quick Actions</h3>
                    <span class="badge-showcase"><i class="fas fa-palette"></i> Redesigned</span>
                </div>
                <div class="quick-actions-grid">
                    <a href="students.php" class="card">
                        <i class="fas fa-user-plus" style="color: var(--color-eduman-blue, var(--color-primary));"></i>
                        <span>Add Student</span>
                    </a>
                    <a href="payments.php" class="card">
                        <i class="fas fa-money-bill-wave" style="color: var(--color-success);"></i>
                        <span>Record Payment</span>
                    </a>
                    <?php if (isSuperAdmin()): ?>
                    <a href="bulk_import.php" class="card">
                        <i class="fas fa-file-csv" style="color: var(--color-eduman-amber, var(--color-accent));"></i>
                        <span>Bulk Import</span>
                    </a>
                    <a href="attendance.php" class="card">
                        <i class="fas fa-user-check" style="color: var(--color-primary);"></i>
                        <span>Take Attendance</span>
                    </a>
                    <a href="payroll.php" class="card">
                        <i class="fas fa-file-invoice-dollar" style="color: #D94478;"></i>
                        <span>Generate Payroll</span>
                    </a>
                    <?php endif; ?>
                    <a href="messaging.php" class="card">
                        <i class="fas fa-envelope" style="color: var(--color-eduman-blue, var(--color-primary));"></i>
                        <span>Send Message<?php echo $pending_messages > 0 ? " ($pending_messages)" : ""; ?></span>
                    </a>
                </div>
            </div>

            <!-- Recent Payments Table -->
            <div class="section-block">
                <div class="section-header-accent"><h3>Recent Payments</h3></div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Receipt #</th><th>Student</th><th>Amount (GHS)</th><th>Date</th><th>Method</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_payments)): ?>
                                <tr><td colspan="6" style="text-align:center;">No payments recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['full_name']); ?><br><small><?php echo htmlspecialchars($payment['admission_number']); ?></small></td>
                                    <td><?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td><a href="view_receipt.php?receipt=<?php echo urlencode($payment['receipt_number']); ?>" target="_blank" class="btn-login btn-sm">View</a></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Charts -->
            <div class="section-block">
                <div class="section-header-accent"><h3>Revenue Analytics</h3></div>
                <canvas id="revenueChart" width="400" height="150"></canvas>
            </div>
        </main>
    </div>
    <?php endif; ?>

    <script>
        // Skeleton loading — hide skeleton when content is ready
        document.addEventListener('DOMContentLoaded', function() {
            var skeletons = document.querySelectorAll('.skeleton-placeholder');
            skeletons.forEach(function(el) { el.classList.remove('skeleton-placeholder'); });
        });
        const ctx = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Revenue (GHS)',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: 'rgba(43, 76, 126, 0.6)',
                    borderColor: 'rgba(43, 76, 126, 1)',
                    borderWidth: 1
                }]
            },
            options: { scales: { y: { beginAtZero: true } } }
        });
    </script>
</body>
</html>
