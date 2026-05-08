<?php
require_once 'includes/db.php';
requireAccess('enrollments');

// Fetch Settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    if ($action === 'approve') {
        // Assign admission number
        $today = date('ymd');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE admission_number LIKE ?");
        $stmt->execute(["CEC-{$today}-%"]);
        $counter = str_pad($stmt->fetchColumn() + 1, 3, '0', STR_PAD_LEFT);
        $admissionNumber = "CEC-{$today}-{$counter}";
        $pdo->prepare("UPDATE students SET admission_number = ?, status = 'enrolled' WHERE id = ?")->execute([$admissionNumber, $id]);
        $message = "Enrollment approved! Admission Number: $admissionNumber";
    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE students SET status = 'rejected' WHERE id = ?")->execute([$id]);
        $message = "Enrollment rejected.";
    }
    header("Location: enrollments.php?filter=" . ($_GET['filter'] ?? 'pending') . "&search=" . urlencode($_GET['search'] ?? ''));
    exit;
}

// Fetch stats — use simple counts the bridge handles correctly
try {
    $totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $totalApproved  = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE admission_number IS NOT NULL")->fetchColumn();
    $totalRejected = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status = 'rejected'")->fetchColumn();
    $rejectedCount = $totalRejected;
    $pendingCount   = $totalStudents - $totalApproved;
    $enrolledToday  = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE admission_date = CURRENT_DATE")->fetchColumn();
    $totalEnrolled  = $totalApproved - $totalRejected;
} catch (Exception $e) {
    $pendingCount = $rejectedCount = $totalApproved = $totalRejected = $enrolledToday = $totalEnrolled = 0;
}

$filter = $_GET['filter'] ?? 'pending';
$search = $_GET['search'] ?? '';

// Fetch enrollments — WHERE clauses are limited by the Supabase bridge.
// Workaround: fetch all, filter in PHP.
$query = "SELECT * FROM students";
$params = [];
$where = [];

if ($filter === 'pending') {
    $where[] = "admission_number IS NULL";
} elseif ($filter === 'enrolled') {
    $where[] = "admission_number IS NOT NULL";
} elseif ($filter === 'rejected') {
    $where[] = "status = 'rejected'";
} else {
    $where[] = "id > 0";
}

if ($search) {
    if ($filter === 'pending') {
        $where[] = "full_name LIKE ?";
        $params[] = "%$search%";
    } else {
        $where[] = "(full_name LIKE ? OR admission_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
}

if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}
$query .= " ORDER BY admission_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_enrollments = $stmt->fetchAll();

// Bridge limitation: complex WHERE clauses (IS NULL, IN, AND) may be dropped.
// Fallback: filter in PHP to match what the user sees.
$enrollments = array_filter($all_enrollments, function($s) use ($filter) {
    if ($filter === 'pending')  return empty($s['admission_number']) && ($s['status'] ?? '') !== 'rejected';
    if ($filter === 'enrolled') return !empty($s['admission_number']) && ($s['status'] ?? '') !== 'rejected';
    if ($filter === 'rejected') return ($s['status'] ?? '') === 'rejected';
    return true;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Enrollments — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); text-align: center; }
        .stat-card .stat-number { font-size: 28px; font-weight: 700; color: #1a5276; }
        .stat-card .stat-label { font-size: 13px; color: #666; margin-top: 5px; }
        .stat-card.pending .stat-number { color: #f39c12; }
        .stat-card.enrolled .stat-number { color: #27ae60; }
        .stat-card.today .stat-number { color: #3498db; }
        .stat-card.rejected .stat-number { color: #e74c3c; }
        .filter-tabs { display: flex; gap: 10px; margin-bottom: 15px; }
        .filter-tabs a { padding: 8px 18px; border-radius: 20px; background: #f0f0f0; color: #333; text-decoration: none; font-size: 14px; }
        .filter-tabs a.active { background: #1a5276; color: #fff; }
        .table-responsive { overflow-x: auto; }
        .btn-approve { background: #27ae60; color: #fff; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; }
        .btn-reject { background: #e74c3c; color: #fff; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; }
        .btn-view { background: #3498db; color: #fff; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; }
        .status-badge { padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.enrolled { background: #d4edda; color: #155724; }
        .status-badge.rejected { background: #f8d7da; color: #721c24; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 3% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 700px; border-radius: 8px; position: relative; }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover { color: black; }
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; }
        .detail-item { padding: 8px; background: #f9fafb; border-radius: 4px; }
        .detail-item .label { font-size: 12px; color: #666; }
        .detail-item .value { font-weight: 600; color: #333; }
        .section-title { grid-column: span 2; font-weight: 700; color: #1a5276; margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php echo renderSidebar('enrollments', $school_name); ?>

        <main class="main-content">
            <div class="top-bar">
                <h2>Manage Enrollments</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card pending">
                    <div class="stat-number"><?php echo (int)$pendingCount; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card today">
                    <div class="stat-number"><?php echo (int)$enrolledToday; ?></div>
                    <div class="stat-label">Enrolled Today</div>
                </div>
                <div class="stat-card enrolled">
                    <div class="stat-number"><?php echo (int)$totalEnrolled; ?></div>
                    <div class="stat-label">Total Enrolled</div>
                </div>
                <div class="stat-card rejected">
                    <div class="stat-number"><?php echo (int)$rejectedCount; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="section">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap: wrap; gap: 10px;">
                    <div class="filter-tabs">
                        <a href="?filter=pending" class="<?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                        <a href="?filter=enrolled" class="<?php echo $filter === 'enrolled' ? 'active' : ''; ?>">Approved</a>
                        <a href="?filter=rejected" class="<?php echo $filter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
                        <a href="?filter=all" class="<?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                    </div>
                    <form action="enrollments.php" method="GET" style="display:flex; gap:10px;">
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <input type="text" name="search" placeholder="Search name or admission #..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-login"><i class="fas fa-search"></i></button>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Admission #</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Guardian</th>
                                <th>Phone</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($enrollments)): ?>
                                <tr><td colspan="8" style="text-align:center; padding: 30px; color: #888;">No enrollments found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($enrollments as $en): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($en['admission_number'] ?? '-'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($en['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($en['class_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($en['guardian_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($en['guardian_phone_primary'] ?? '-'); ?></td>
                                    <td><?php echo $en['admission_date'] ? date('n/j/Y', strtotime($en['admission_date'])) : '-'; ?></td>
                                    <td><span class="status-badge <?php echo $en['status']; ?>"><?php echo ucfirst($en['status']); ?></span></td>
                                    <td>
                                        <?php if (empty($en['admission_number'])): ?>
                                            <a href="?action=approve&id=<?php echo $en['id']; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" class="btn-approve" onclick="return confirm('Approve this enrollment?')">Approve</a>
                                            <a href="?action=reject&id=<?php echo $en['id']; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" class="btn-reject" onclick="return confirm('Reject this enrollment?')">Reject</a>
                                        <?php endif; ?>
                                        <a href="#" class="btn-view" onclick="showDetails(<?php echo htmlspecialchars(json_encode($en)); ?>); return false;">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('detailsModal').style.display='none'">&times;</span>
            <h3>Enrollment Details</h3>
            <div id="modalBody"></div>
        </div>
    </div>

    <script>
        function showDetails(en) {
            const statusColors = { pending: '#856404', enrolled: '#155724', rejected: '#721c24' };
            const statusBg = { pending: '#fff3cd', enrolled: '#d4edda', rejected: '#f8d7da' };
            let html = '<div class="detail-grid">';
            html += '<div class="detail-item"><div class="label">Admission #</div><div class="value">' + (en.admission_number || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Status</div><div class="value"><span class="status-badge ' + (en.status || '') + '">' + (en.status ? en.status.charAt(0).toUpperCase() + en.status.slice(1) : '-') + '</span></div></div>';
            html += '<div class="section-title">Student Information</div>';
            html += '<div class="detail-item"><div class="label">Full Name</div><div class="value">' + (en.full_name || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Class</div><div class="value">' + (en.class_name || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Gender</div><div class="value">' + (en.gender || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Date of Birth</div><div class="value">' + (en.date_of_birth || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Place of Birth</div><div class="value">' + (en.place_of_birth || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Nationality</div><div class="value">' + (en.nationality || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Address</div><div class="value">' + (en.address || '-') + '</div></div>';
            html += '<div class="section-title">Guardian Information</div>';
            html += '<div class="detail-item"><div class="label">Guardian Name</div><div class="value">' + (en.guardian_name || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Relationship</div><div class="value">' + (en.guardian_relationship || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Email</div><div class="value">' + (en.guardian_email || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Primary Phone</div><div class="value">' + (en.guardian_phone_primary || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Emergency Phone</div><div class="value">' + (en.guardian_phone_emergency || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Occupation</div><div class="value">' + (en.guardian_occupation || '-') + '</div></div>';
            html += '<div class="detail-item" style="grid-column: span 2;"><div class="label">Guardian Address</div><div class="value">' + (en.guardian_address || '-') + '</div></div>';
            html += '<div class="section-title">Academic Background</div>';
            html += '<div class="detail-item"><div class="label">Previous School</div><div class="value">' + (en.previous_school || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Previous Class</div><div class="value">' + (en.previous_class || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Admission Date</div><div class="value">' + (en.admission_date || '-') + '</div></div>';
            html += '<div class="detail-item"><div class="label">Academic Year</div><div class="value">' + (en.academic_year || '-') + '</div></div>';
            html += '<div class="section-title">Health Information</div>';
            html += '<div class="detail-item"><div class="label">Health Insurance ID</div><div class="value">' + (en.health_insurance_id || '-') + '</div></div>';
            html += '<div class="detail-item" style="grid-column: span 2;"><div class="label">Medical Conditions</div><div class="value">' + (en.medical_conditions || 'None') + '</div></div>';
            html += '<div class="detail-item" style="grid-column: span 2;"><div class="label">Allergies</div><div class="value">' + (en.allergies || 'None') + '</div></div>';
            html += '<div class="detail-item" style="grid-column: span 2;"><div class="label">Special Needs</div><div class="value">' + (en.special_needs || 'None') + '</div></div>';
            html += '</div>';
            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('detailsModal').style.display = 'block';
        }

        // Close modal on outside click
        window.onclick = function(event) {
            var modal = document.getElementById('detailsModal');
            if (event.target == modal) modal.style.display = 'none';
        }
        // Close on Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') document.getElementById('detailsModal').style.display = 'none';
        });
    </script>
</body>
</html>
