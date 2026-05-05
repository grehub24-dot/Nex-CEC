<?php
require_once 'includes/db.php';

// Ensure Admin Access
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

// Fetch Current Settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$current_year = $settings['current_academic_year'] ?? '2025/2026';
$required_dues = isset($settings['annual_dues_amount']) ? (float)$settings['annual_dues_amount'] : 100.00;

// ==========================================
// UPDATED: Fetch Stats from the new VIEW
// ==========================================
try {
    $stmt = $pdo->query("SELECT * FROM admin_dashboard_stats");
    $stats = $stmt->fetch();
    $total_students = $stats['total_students'] ?? 0;
    $total_revenue = $stats['total_revenue'] ?? 0;
    $payments_today = $stats['payments_today'] ?? 0;
    $students_paid = $stats['compliant_students'] ?? 0;
} catch (Exception $e) {
    $total_students = 0; $total_revenue = 0; $payments_today = 0; $students_paid = 0;
}

$compliance_rate = $total_students > 0 ? round(($students_paid / (int)$total_students) * 100, 1) : 0;
$outstanding_students = max(0, (int)$total_students - $students_paid);

// ==========================================
// UPDATED: Recent Payments from VIEW
// ==========================================
try {
    $stmt = $pdo->query("SELECT * FROM recent_payments_view");
    $recent_payments = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_payments = [];
}

// ==========================================
// UPDATED: Chart Data (Simplified)
// ==========================================
// We fetch raw payments and calculate chart data in PHP to avoid complex SQL issues
try {
    $stmt = $pdo->query("SELECT amount, payment_date FROM payments ORDER BY payment_date ASC");
    $raw_payments = $stmt->fetchAll();
    
    $monthly_totals = [];
    foreach ($raw_payments as $row) {
        $date = date('M Y', strtotime($row['payment_date']));
        $monthly_totals[$date] = ($monthly_totals[$date] ?? 0) + (float)$row['amount'];
    }
    
    $chart_labels = array_keys($monthly_totals);
    $chart_data = array_values($monthly_totals);
} catch (Exception $e) {
    $chart_labels = [date('M Y')];
    $chart_data = [0];
}

if (empty($chart_labels)) {
    $chart_labels = [date('M Y')];
    $chart_data = [0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - INFOTESS SDMS</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header" style="text-align: center; padding: 20px 10px;">
                <img src="../images/infotess.png" alt="INFOTESS Logo" style="width: 80px; height: 80px; margin-bottom: 10px; border-radius: 50%; background: #fff; padding: 5px;">
                <h3>INFOTESS Admin</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="verify.php"><i class="fas fa-qrcode"></i> Verify Receipt</a></li>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="messaging.php"><i class="fas fa-envelope"></i> Messaging</a></li>
                <li><a href="inbox.php"><i class="fas fa-inbox"></i> Inbox</a></li>
                <li><a href="module_settings.php"><i class="fas fa-cogs"></i> Module Settings</a></li>
                <li><a href="settings.php"><i class="fas fa-tools"></i> System Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <h2>Dashboard Overview</h2>
                <div class="user-info"><span>Welcome, Admin</span></div>
            </div>

            <!-- Stats -->
            <div class="stat-cards">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-details"><h3><?php echo number_format($total_students); ?></h3><p>Total Students</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                    <div class="stat-details"><h3>GHS <?php echo number_format($total_revenue, 2); ?></h3><p>Total Revenue</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                    <div class="stat-details"><h3><?php echo $payments_today; ?></h3><p>Payments Today</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-details"><h3><?php echo $compliance_rate; ?>%</h3><p>Compliance Rate</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
                    <div class="stat-details"><h3><?php echo number_format($outstanding_students); ?></h3><p>Outstanding Students</p></div>
                </div>
            </div>

            <!-- Recent Payments Table -->
            <div class="section">
                <h3>Recent Payments</h3>
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
                                    <td><?php echo htmlspecialchars($payment['full_name']); ?><br><small><?php echo htmlspecialchars($payment['index_number']); ?></small></td>
                                    <td><?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td><a href="../receipts/receipt_<?php echo $payment['receipt_number']; ?>.html" target="_blank" class="btn-login" style="padding: 5px 10px; font-size: 0.8rem;">View</a></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Charts -->
            <div class="section">
                <h3>Revenue Analytics</h3>
                <canvas id="revenueChart" width="400" height="150"></canvas>
            </div>
        </main>
    </div>

    <script>
        const ctx = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Revenue (GHS)',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: 'rgba(0, 51, 102, 0.7)',
                    borderColor: 'rgba(0, 51, 102, 1)',
                    borderWidth: 1
                }]
            },
            options: { scales: { y: { beginAtZero: true } } }
        });
    </script>
</body>
</html>
<?php
require_once 'includes/db.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect('admin_students.php');
}

$message = '';
$error = '';

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $index_number = sanitize($_POST['index_number']);
    $department = sanitize($_POST['department']);
    $level = sanitize($_POST['level']);
    $class_name = sanitize($_POST['class_name'] ?? '');
    $stream = sanitize($_POST['stream'] ?? '');
    $phone = sanitize($_POST['phone_number']);
    $email = sanitize($_POST['email']);

    // Handle Profile Picture
    $profile_picture = $_POST['current_picture'] ?? null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../images/profiles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $filename = $index_number . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $filename)) {
            $profile_picture = 'images/profiles/' . $filename;
        }
    }

    try {
        $pdo->beginTransaction();

        // Update Student
        $stmt = $pdo->prepare("UPDATE students SET full_name = ?, index_number = ?, department = ?, level = ?, class_name = ?, stream = ?, phone_number = ?, profile_picture = ? WHERE id = ?");
        $stmt->execute([$full_name, $index_number, $department, $level, $class_name, $stream, $phone, $profile_picture, $id]);

        // Update User Email (if needed)
        // First get user_id
        $stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $user_id = $stmt->fetchColumn();

        if ($user_id) {
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$email, $user_id]);
        }

        $pdo->commit();
        $message = "Student details updated successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating student: " . $e->getMessage();
    }
}

// Fetch Student Data
$stmt = $pdo->prepare("
    SELECT s.*, u.email 
    FROM students s 
    LEFT JOIN users u ON s.user_id = u.id 
    WHERE s.id = ?
");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    redirect('admin_students.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Student - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .upload-file-name {
            margin-top: 8px;
            font-size: 0.82rem;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header" style="text-align: center; padding: 20px 10px;">
                <img src="../images/infotess.png" alt="INFOTESS Logo" style="width: 80px; height: 80px; margin-bottom: 10px; border-radius: 50%; background: #fff; padding: 5px;">
                <h3>INFOTESS Admin</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="students.php" class="active"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="verify.php"><i class="fas fa-qrcode"></i> Verify Receipt</a></li>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="messaging.php"><i class="fas fa-envelope"></i> Messaging</a></li>
                <li><a href="inbox.php"><i class="fas fa-inbox"></i> Inbox</a></li>
                <li><a href="module_settings.php"><i class="fas fa-cogs"></i> Module Settings</a></li>
                <li><a href="settings.php"><i class="fas fa-tools"></i> System Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h2>Edit Student</h2>
                <a href="students.php" class="btn-secondary">Back to List</a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card">
                <form action="" method="POST" enctype="multipart/form-data" style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                    <input type="hidden" name="current_picture" value="<?php echo $student['profile_picture']; ?>">
                    
                    <div style="grid-column: span 2; text-align: center; margin-bottom: 10px;">
                        <img id="editStudentPreview" src="../<?php echo $student['profile_picture'] ?? 'images/aamusted.jpg'; ?>" alt="Current Profile" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd; margin-bottom: 10px;">
                        <br>
                        <label>Update Profile Picture</label><br>
                        <input type="file" name="profile_picture" id="editStudentUpload" class="form-control" accept="image/*">
                        <div id="editStudentUploadName" class="upload-file-name">No image selected</div>
                    </div>

                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Index Number</label>
                        <input type="text" name="index_number" class="form-control" value="<?php echo htmlspecialchars($student['index_number']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($student['phone_number']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Programme / Department</label>
                        <select name="department" class="form-control" required>
                            <option value="">-- Select Programme --</option>
                            <optgroup label="Bachelor's Degree Programmes">
                                <option value="B.Sc. Information Technology" <?php echo $student['department'] === 'B.Sc. Information Technology' ? 'selected' : ''; ?>>B.Sc. Information Technology</option>
                                <option value="B.Sc. Cyber Security and Digital Forensics" <?php echo $student['department'] === 'B.Sc. Cyber Security and Digital Forensics' ? 'selected' : ''; ?>>B.Sc. Cyber Security and Digital Forensics</option>
                                <option value="B.Ed. Computing with Artificial Intelligence (AI)" <?php echo $student['department'] === 'B.Ed. Computing with Artificial Intelligence (AI)' ? 'selected' : ''; ?>>B.Ed. Computing with Artificial Intelligence (AI)</option>
                                <option value="B.Ed. Computing with Internet of Things (IOT)" <?php echo $student['department'] === 'B.Ed. Computing with Internet of Things (IOT)' ? 'selected' : ''; ?>>B.Ed. Computing with Internet of Things (IOT)</option>
                                <option value="B.Ed. Information Technology" <?php echo $student['department'] === 'B.Ed. Information Technology' ? 'selected' : ''; ?>>B.Ed. Information Technology</option>
                            </optgroup>
                            <optgroup label="Diploma Programmes">
                                <option value="Diploma in Cyber Security and Digital Forensics" <?php echo $student['department'] === 'Diploma in Cyber Security and Digital Forensics' ? 'selected' : ''; ?>>Diploma in Cyber Security and Digital Forensics</option>
                                <option value="Diploma in Information Technology" <?php echo $student['department'] === 'Diploma in Information Technology' ? 'selected' : ''; ?>>Diploma in Information Technology</option>
                            </optgroup>
                            <optgroup label="Postgraduate Programmes">
                                <option value="M. Phil. Information Technology" <?php echo $student['department'] === 'M. Phil. Information Technology' ? 'selected' : ''; ?>>M. Phil. Information Technology</option>
                                <option value="M. Sc. Information Technology Education" <?php echo $student['department'] === 'M. Sc. Information Technology Education' ? 'selected' : ''; ?>>M. Sc. Information Technology Education</option>
                                <option value="M. Phil Information Technology (Top-up)" <?php echo $student['department'] === 'M. Phil Information Technology (Top-up)' ? 'selected' : ''; ?>>M. Phil Information Technology (Top-up)</option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Level</label>
                        <select name="level" class="form-control" required>
                            <option value="100" <?php echo $student['level'] === '100' ? 'selected' : ''; ?>>100</option>
                            <option value="200" <?php echo $student['level'] === '200' ? 'selected' : ''; ?>>200</option>
                            <option value="300" <?php echo $student['level'] === '300' ? 'selected' : ''; ?>>300</option>
                            <option value="400" <?php echo $student['level'] === '400' ? 'selected' : ''; ?>>400</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Class</label>
                        <select name="class_name" class="form-control">
                            <option value="">-- Select Class --</option>
                            <optgroup label="IT">
                                <?php foreach(['IT A', 'IT B', 'IT C', 'IT D', 'IT E', 'IT F', 'IT G', 'IT H'] as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo ($student['class_name'] ?? '') === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="ITE">
                                <?php foreach(['ITE A', 'ITE B', 'ITE C', 'ITE D', 'ITE E', 'ITE F', 'ITE G', 'ITE H', 'ITE I', 'ITE J', 'ITE K'] as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo ($student['class_name'] ?? '') === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="CB">
                                <?php foreach(['CB A', 'CB B', 'CB C', 'CB D', 'CB E', 'CB F', 'CB G', 'CB H'] as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo ($student['class_name'] ?? '') === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Stream</label>
                        <select name="stream" class="form-control">
                            <option value="">-- Select Stream --</option>
                            <?php foreach(['Regular', 'Sandwich', 'Evening'] as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo ($student['stream'] ?? '') === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="grid-column: span 2; margin-top: 20px;">
                        <button type="submit" class="btn-primary">Update Student Details</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>
        const editStudentUpload = document.getElementById('editStudentUpload');
        const editStudentPreview = document.getElementById('editStudentPreview');
        const editStudentUploadName = document.getElementById('editStudentUploadName');

        if (editStudentUpload && editStudentPreview && editStudentUploadName) {
            editStudentUpload.addEventListener('change', function() {
                const file = this.files && this.files[0] ? this.files[0] : null;
                const defaultSrc = "../<?php echo $student['profile_picture'] ?? 'images/aamusted.jpg'; ?>";

                if (!file) {
                    editStudentPreview.src = defaultSrc;
                    editStudentUploadName.textContent = 'No image selected';
                    return;
                }

                editStudentUploadName.textContent = file.name;

                if (!file.type.startsWith('image/')) {
                    editStudentPreview.src = defaultSrc;
                    editStudentUploadName.textContent = 'Please select an image file';
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(event) {
                    editStudentPreview.src = event.target.result;
                };
                reader.readAsDataURL(file);
            });
        }
    </script>
</body>
</html>
<?php
require_once 'includes/db.php';
require_once 'includes/ImapHelper.php';

// Ensure Admin Access
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

// Check if IMAP extension is installed
$imap_installed = function_exists('imap_open');

$imap = null;
$emails = [];
$error = '';
$total_emails = 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;

if ($imap_installed) {
    $imap = new ImapHelper();

    if ($imap->isConnected()) {
        // Handle actions like delete or mark as read
        if (isset($_GET['action']) && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            if ($_GET['action'] == 'read') {
                $imap->markAsRead($id);
                flash('inbox_message', 'Email marked as read.');
                redirect('inbox.php?page=' . $page);
            } elseif ($_GET['action'] == 'delete') {
                $imap->deleteEmail($id);
                flash('inbox_message', 'Email deleted.');
                redirect('inbox.php?page=' . $page);
            }
        }

        $total_emails = $imap->getEmailCount();
        $emails = $imap->getEmails($limit, $page);
    } else {
        $error = "Could not connect to Gmail IMAP server. Please check your credentials in includes/ImapHelper.php. Error: " . $imap->getError();
    }
} else {
    $error = "PHP IMAP extension is not installed or enabled. Please enable it in your php.ini.";
}

$total_pages = ceil($total_emails / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Inbox - INFOTESS SDMS</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .email-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .email-item {
            display: flex;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            align-items: center;
            transition: background 0.2s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        .email-item:hover {
            background: #f9f9f9;
        }
        .email-item.unread {
            font-weight: bold;
            background: #f0f7ff;
        }
        .email-sender {
            width: 25%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 15px;
        }
        .email-subject {
            flex-grow: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 15px;
        }
        .email-date {
            width: 15%;
            text-align: right;
            color: #666;
            font-size: 0.9em;
        }
        .email-actions {
            width: 10%;
            text-align: right;
        }
        .email-actions a {
            color: #666;
            margin-left: 10px;
        }
        .email-actions a:hover {
            color: #d9534f;
        }
        .email-body-preview {
            color: #888;
            font-weight: normal;
            font-size: 0.9em;
        }
        .pagination {
            display: flex;
            justify-content: center;
            padding: 20px;
            gap: 10px;
        }
        .pagination a {
            padding: 8px 12px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        .pagination a.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        /* Modal for viewing email */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 70%;
            max-width: 800px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            max-height: 80vh;
            overflow-y: auto;
        }
        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-modal:hover {
            color: black;
        }
        .modal-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .modal-body {
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header" style="text-align: center; padding: 20px 10px;">
                <img src="../images/infotess.png" alt="INFOTESS Logo" style="width: 80px; height: 80px; margin-bottom: 10px; border-radius: 50%; background: #fff; padding: 5px;">
                <h3>INFOTESS Admin</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="verify.php"><i class="fas fa-qrcode"></i> Verify Receipt</a></li>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="messaging.php"><i class="fas fa-envelope"></i> Messaging</a></li>
                <li><a href="inbox.php" class="active"><i class="fas fa-inbox"></i> Inbox</a></li>
                <li><a href="module_settings.php"><i class="fas fa-cogs"></i> Module Settings</a></li>
                <li><a href="settings.php"><i class="fas fa-tools"></i> System Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <h2>Email Inbox</h2>
                <div class="user-info">
                    <span>Welcome, Admin</span>
                </div>
            </div>

            <div class="section">
                <?php flash('inbox_message'); ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <p class="mt-2" style="font-size: 0.9em;">
                            <strong>Note:</strong> To use this feature, ensure you have set your Gmail credentials in <code>includes/ImapHelper.php</code> and that your server has the PHP IMAP extension enabled.
                        </p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <p>Showing <?php echo count($emails); ?> of <?php echo $total_emails; ?> emails.</p>
                        <button onclick="location.reload()" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Refresh</button>
                    </div>

                    <div class="email-list">
                        <?php if (empty($emails)): ?>
                            <div style="padding: 20px; text-align: center; color: #666;">
                                No emails found in your inbox.
                            </div>
                        <?php else: ?>
                            <?php foreach ($emails as $email): ?>
                                <div class="email-item <?php echo $email['seen'] ? '' : 'unread'; ?>" onclick="openEmail(<?php echo htmlspecialchars(json_encode($email)); ?>)">
                                    <div class="email-sender">
                                        <?php echo htmlspecialchars(strip_tags($email['from'])); ?>
                                    </div>
                                    <div class="email-subject">
                                        <?php echo htmlspecialchars($email['subject']); ?>
                                        <span class="email-body-preview"> - <?php echo htmlspecialchars(substr(strip_tags($email['body']), 0, 50)); ?>...</span>
                                    </div>
                                    <div class="email-date">
                                        <?php echo date('M j, Y g:i A', strtotime($email['date'])); ?>
                                    </div>
                                    <div class="email-actions" onclick="event.stopPropagation();">
                                        <?php if (!$email['seen']): ?>
                                            <a href="inbox.php?action=read&id=<?php echo $email['id']; ?>&page=<?php echo $page; ?>" title="Mark as Read"><i class="fas fa-envelope-open"></i></a>
                                        <?php endif; ?>
                                        <a href="inbox.php?action=delete&id=<?php echo $email['id']; ?>&page=<?php echo $page; ?>" title="Delete" onclick="return confirm('Are you sure you want to delete this email?');"><i class="fas fa-trash"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="inbox.php?page=<?php echo $page - 1; ?>">&laquo; Previous</a>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            for ($i = $start; $i <= $end; $i++): 
                            ?>
                                <a href="inbox.php?page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="inbox.php?page=<?php echo $page + 1; ?>">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Email View Modal -->
    <div id="emailModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEmail()">&times;</span>
            <div class="modal-header">
                <h3 id="modalSubject" style="margin-bottom: 10px;">Subject</h3>
                <div style="display: flex; justify-content: space-between; color: #666; font-size: 0.9em;">
                    <div><strong>From:</strong> <span id="modalFrom">Sender</span></div>
                    <div id="modalDate">Date</div>
                </div>
            </div>
            <div class="modal-body" id="modalBody">
                Body content goes here...
            </div>
            <div style="margin-top: 20px; text-align: right;">
                <a href="#" id="replyBtn" class="btn btn-primary"><i class="fas fa-reply"></i> Reply in Gmail</a>
            </div>
        </div>
    </div>

    <script>
        function openEmail(emailData) {
            document.getElementById('modalSubject').textContent = emailData.subject;
            document.getElementById('modalFrom').textContent = emailData.from.replace(/<[^>]*>?/gm, ''); // Basic strip tags for safety
            
            let dateObj = new Date(emailData.date);
            document.getElementById('modalDate').textContent = dateObj.toLocaleString();
            
            // Render body. We put it in an iframe or just div if we trust it, but innerHTML is okay for now since we strip scripts
            let safeBody = emailData.body.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
            document.getElementById('modalBody').innerHTML = safeBody;
            
            // Set reply link to open Gmail in a new tab
            document.getElementById('replyBtn').href = 'https://mail.google.com/mail/u/0/#inbox';
            document.getElementById('replyBtn').target = '_blank';
            
            document.getElementById('emailModal').style.display = 'block';

            // Optional: If email is unseen, we could trigger an AJAX call here to mark it as read without reloading
        }

        function closeEmail() {
            document.getElementById('emailModal').style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            let modal = document.getElementById('emailModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>
<?php
require_once 'includes/db.php';
require_once 'includes/SMSHelper.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $title = sanitize($_POST['title'] ?? '');
    $content = sanitize($_POST['content'] ?? '');
    $send_sms = isset($_POST['send_sms']);

    if ($action === 'broadcast') {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, title, content, is_broadcast) VALUES (?, ?, ?, 1)");
            $stmt->execute([$_SESSION['user_id'], $title, $content]);

            if ($send_sms) {
                $sms = new SMSHelper();
                $smsText = trim($title . ': ' . $content);
                $students = $pdo->query("
                    SELECT DISTINCT s.phone_number
                    FROM students s
                    LEFT JOIN users u ON s.user_id = u.id
                    WHERE s.phone_number IS NOT NULL
                      AND s.phone_number != ''
                      AND (u.id IS NULL OR u.status = 'active')
                ")->fetchAll();
                $sentCount = 0;
                $failedCount = 0;
                foreach ($students as $student) {
                    if ($sms->send($student['phone_number'], $smsText)) {
                        $sentCount++;
                    } else {
                        $failedCount++;
                    }
                }
                $message = "Broadcast sent. SMS delivered to $sentCount member(s)";
                if ($failedCount > 0) {
                    $message .= " ($failedCount failed).";
                } else {
                    $message .= ".";
                }
            } else {
                $message = "Broadcast message sent successfully!";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } elseif ($action === 'delete_message') {
        $msg_id = intval($_POST['message_id']);
        $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
        if ($stmt->execute([$msg_id])) {
            $message = "Message deleted successfully.";
        } else {
            $error = "Failed to delete message.";
        }
    } elseif ($action === 'send_sms_only') {
        try {
            $sms_content = sanitize($_POST['sms_content'] ?? '');
            $recipient_type = $_POST['recipient_type'] ?? 'all';
            $student_id = $_POST['student_id'] ?? null;

            if (empty($sms_content)) {
                $error = "SMS content cannot be empty.";
            } else {
                $sms = new SMSHelper();
                $count = 0;
                $failedCount = 0;

                if ($recipient_type === 'all') {
                    $students = $pdo->query("
                        SELECT DISTINCT s.phone_number
                        FROM students s
                        LEFT JOIN users u ON s.user_id = u.id
                        WHERE s.phone_number IS NOT NULL
                          AND s.phone_number != ''
                          AND (u.id IS NULL OR u.status = 'active')
                    ")->fetchAll();
                    foreach ($students as $student) {
                        if ($sms->send($student['phone_number'], $sms_content)) {
                            $count++;
                        } else {
                            $failedCount++;
                        }
                    }
                    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, title, content, is_broadcast) VALUES (?, ?, ?, 1)");
                    $stmt->execute([$_SESSION['user_id'], "Bulk SMS", $sms_content]);

                    $message = "SMS sent successfully to $count member(s)";
                    if ($failedCount > 0) {
                        $message .= " ($failedCount failed).";
                    } else {
                        $message .= ".";
                    }
                } else {
                    $stmt = $pdo->prepare("SELECT s.phone_number, s.full_name, s.user_id FROM students s WHERE s.id = ?");
                    $stmt->execute([$student_id]);
                    $student = $stmt->fetch();
                    if ($student && !empty($student['phone_number'])) {
                        if ($sms->send($student['phone_number'], $sms_content)) {
                            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, title, content, is_broadcast) VALUES (?, ?, ?, ?, 0)");
                            $stmt->execute([$_SESSION['user_id'], $student['user_id'], "Individual SMS", $sms_content]);
                            
                            $message = "SMS sent successfully to " . htmlspecialchars($student['full_name']) . "!";
                        } else {
                            $error = "Failed to send SMS to " . htmlspecialchars($student['full_name']) . ".";
                        }
                    } else {
                        $error = "Selected student has no valid phone number.";
                    }
                }
            }
        } catch (Exception $e) {
            $error = "Error sending SMS: " . $e->getMessage();
        }
    }
}

// Fetch all students for the dropdown
$all_students = $pdo->query("SELECT id, full_name, index_number FROM students ORDER BY full_name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Messaging - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 25px;
            border-radius: 8px;
            width: 500px;
            position: relative;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .close-btn {
            position: absolute;
            right: 15px;
            top: 10px;
            font-size: 24px;
            cursor: pointer;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header" style="text-align: center; padding: 20px 10px;">
                <img src="../images/infotess.png" alt="INFOTESS Logo" style="width: 80px; height: 80px; margin-bottom: 10px; border-radius: 50%; background: #fff; padding: 5px;">
                <h3>INFOTESS Admin</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="verify.php"><i class="fas fa-qrcode"></i> Verify Receipt</a></li>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="messaging.php" class="active"><i class="fas fa-envelope"></i> Messaging</a></li>
                <li><a href="inbox.php"><i class="fas fa-inbox"></i> Inbox</a></li>
                <li><a href="module_settings.php"><i class="fas fa-cogs"></i> Module Settings</a></li>
                <li><a href="settings.php"><i class="fas fa-tools"></i> System Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h2>Message Platform</h2>
                <div style="display:flex; gap:10px;">
                    <button onclick="document.getElementById('msgModal').style.display='block'" class="btn-admin-action"><i class="fas fa-paper-plane"></i> New Broadcast</button>
                    <button onclick="document.getElementById('smsModal').style.display='block'" class="btn-admin-action btn-admin-success"><i class="fas fa-sms"></i> Send SMS</button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="section">
                <div class="card">
                    <h3>Messages from Students</h3>
                    <?php
                    $student_msgs = $pdo->query("
                        SELECT m.*, s.full_name as sender_name, s.index_number 
                        FROM messages m 
                        JOIN students s ON m.sender_id = s.user_id 
                        WHERE m.is_broadcast = 0 AND m.receiver_id IN (SELECT id FROM users WHERE role = 'admin')
                        ORDER BY m.created_at DESC 
                        LIMIT 15
                    ")->fetchAll();
                    
                    if (empty($student_msgs)):
                    ?>
                        <p style="text-align:center; padding: 20px;">No messages from students yet.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th>Content</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($student_msgs as $msg): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($msg['created_at'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($msg['sender_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($msg['index_number']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($msg['title']); ?></td>
                                        <td><?php echo htmlspecialchars($msg['content']); ?></td>
                                        <td>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this message?');" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_message">
                                                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                                <button type="submit" class="btn-login" style="background:#dc3545; padding: 5px 10px; font-size: 0.8rem;"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section">
                <div class="card">
                    <h3>Message History</h3>
                    <?php
                    $all_msgs = $pdo->query("
                        SELECT m.*, s.full_name as recipient_name 
                        FROM messages m 
                        LEFT JOIN students s ON m.receiver_id = s.user_id 
                        ORDER BY m.created_at DESC 
                        LIMIT 20
                    ")->fetchAll();
                    
                    if (empty($all_msgs)):
                    ?>
                        <p style="text-align:center; padding: 20px;">No messages sent yet.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Recipient</th>
                                    <th>Subject</th>
                                    <th>Content</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_msgs as $msg): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($msg['created_at'])); ?></td>
                                        <td>
                                            <span class="badge" style="background: <?php echo $msg['is_broadcast'] ? '#003366' : '#28a745'; ?>; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem;">
                                                <?php echo $msg['is_broadcast'] ? 'Bulk / Broadcast' : 'Individual'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $msg['is_broadcast'] ? '<em>All Students</em>' : htmlspecialchars($msg['recipient_name'] ?? 'Unknown'); ?></td>
                                        <td><strong><?php echo htmlspecialchars($msg['title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($msg['content']); ?></td>
                                        <td>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this message?');" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_message">
                                                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                                <button type="submit" class="btn-login" style="background:#dc3545; padding: 5px 10px; font-size: 0.8rem;"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Message Modal -->
    <div id="msgModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('msgModal').style.display='none'">&times;</span>
            <h3>Send Broadcast Message</h3>
            <form method="POST" action="" style="margin-top: 20px;">
                <input type="hidden" name="action" value="broadcast">
                <div class="form-group">
                    <label>Title / Subject</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Urgent Notice" required>
                </div>
                <div class="form-group">
                    <label>Message Content</label>
                    <textarea name="content" class="form-control" rows="5" placeholder="Type your message here..." required></textarea>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="send_sms"> Also Send SMS (Caution: Costs apply)</label>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; padding: 12px;">Send to All Students</button>
            </form>
        </div>
    </div>

    <!-- SMS Only Modal -->
    <div id="smsModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('smsModal').style.display='none'">&times;</span>
            <h3>Send SMS</h3>
            <p style="font-size: 0.9rem; color: #666; margin-bottom: 15px;">Send a direct SMS message to students.</p>
            <form method="POST" action="" style="margin-top: 10px;">
                <input type="hidden" name="action" value="send_sms_only">
                
                <div class="form-group">
                    <label>Recipient Type</label>
                    <select name="recipient_type" id="recipientType" class="form-control" onchange="toggleStudentSelect()">
                        <option value="all">All Registered Students</option>
                        <option value="individual">Individual Student</option>
                    </select>
                </div>

                <div id="individualStudentSelect" class="form-group" style="display: none;">
                    <label>Select Student</label>
                    <select name="student_id" class="form-control">
                        <option value="">-- Search and Select Student --</option>
                        <?php foreach ($all_students as $student): ?>
                            <option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['full_name']) . " (" . $student['index_number'] . ")"; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>SMS Content</label>
                    <textarea name="sms_content" class="form-control" rows="5" placeholder="Type your SMS message here..." required maxlength="160"></textarea>
                    <small id="charCount" style="color: #666;">Characters remaining: 160</small>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; padding: 12px; background:#28a745; border-color:#28a745;"><i class="fas fa-sms"></i> Send SMS Now</button>
            </form>
        </div>
    </div>

    <script>
        // Toggle individual student select dropdown
        function toggleStudentSelect() {
            const recipientType = document.getElementById('recipientType').value;
            const studentSelect = document.getElementById('individualStudentSelect');
            studentSelect.style.display = recipientType === 'individual' ? 'block' : 'none';
            
            const studentIdSelect = studentSelect.querySelector('select');
            if (recipientType === 'individual') {
                studentIdSelect.setAttribute('required', 'required');
            } else {
                studentIdSelect.removeAttribute('required');
            }
        }

        // SMS character counter
        const smsTextArea = document.querySelector('textarea[name="sms_content"]');
        const charCount = document.getElementById('charCount');
        
        smsTextArea.addEventListener('input', () => {
            const remaining = 160 - smsTextArea.value.length;
            charCount.textContent = `Characters remaining: ${remaining}`;
            if (remaining < 0) {
                charCount.style.color = 'red';
            } else {
                charCount.style.color = '#666';
            }
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = "none";
            }
        }
    </script>
</body>
</html>

<?php
require_once 'includes/db.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_executive') {
        $name = sanitize($_POST['full_name']);
        $pos = sanitize($_POST['position']);
        $image_url = 'images/aamusted.jpg';

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../images/executives/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . $_FILES['image']['name'];
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                $image_url = 'images/executives/' . $filename;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO executives (full_name, position, image_url) VALUES (?, ?, ?)");
        $stmt->execute([$name, $pos, $image_url]);
        $message = "Executive added successfully!";
    } elseif ($action === 'add_alumni') {
        $name = sanitize($_POST['full_name']);
        $year = sanitize($_POST['graduation_year']);
        $image_url = 'images/aamusted.jpg';

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../images/alumni/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . $_FILES['image']['name'];
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                $image_url = 'images/alumni/' . $filename;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO alumni (full_name, graduation_year, image_url) VALUES (?, ?, ?)");
        $stmt->execute([$name, $year, $image_url]);
        $message = "Alumni added successfully!";
    } elseif ($action === 'add_gallery') {
        $title = sanitize($_POST['title']);
        $image_url = 'images/gallery-placeholder.png';

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../images/gallery/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . $_FILES['image']['name'];
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                $image_url = 'images/gallery/' . $filename;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO gallery (title, image_url) VALUES (?, ?)");
        $stmt->execute([$title, $image_url]);
        $message = "Gallery item added successfully!";
    } elseif ($action === 'add_project') {
        $title = sanitize($_POST['title']);
        $desc = sanitize($_POST['description']);
        $status = sanitize($_POST['status']);
        $date = sanitize($_POST['project_date']);
        $image_url = 'images/project-placeholder.png';

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../images/projects/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . $_FILES['image']['name'];
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                $image_url = 'images/projects/' . $filename;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO projects (title, description, status, project_date, image_url) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $desc, $status, $date, $image_url]);
        $message = "Project added successfully!";
    } elseif ($action === 'respond_contact') {
        $sub_id = intval($_POST['submission_id']);
        $response = sanitize($_POST['response']);
        $stmt = $pdo->prepare("UPDATE contact_submissions SET response = ?, responded_at = NOW() WHERE id = ?");
        $stmt->execute([$response, $sub_id]);
        $message = "Response saved successfully!";
    } elseif ($action === 'update_contact_submission') {
        $id = intval($_POST['id']);
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $subject = sanitize($_POST['subject']);
        $msg = sanitize($_POST['message']);
        $response = sanitize($_POST['response'] ?? '');
        $stmt = $pdo->prepare("UPDATE contact_submissions SET name = ?, email = ?, subject = ?, message = ?, response = ? WHERE id = ?");
        $stmt->execute([$name, $email, $subject, $msg, $response, $id]);
        $message = "Submission updated successfully!";
    } elseif ($action === 'delete_executive') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM executives WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Executive deleted successfully!";
    } elseif ($action === 'update_executive') {
        $id = intval($_POST['id']);
        $name = sanitize($_POST['full_name']);
        $pos = sanitize($_POST['position']);
        $image_url = sanitize($_POST['current_image_url'] ?? '');

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../images/executives/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . $_FILES['image']['name'];
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                $image_url = 'images/executives/' . $filename;
            }
        }

        $stmt = $pdo->prepare("UPDATE executives SET full_name = ?, position = ?, image_url = ? WHERE id = ?");
        $stmt->execute([$name, $pos, $image_url, $id]);
        $message = "Executive updated successfully!";
    } elseif ($action === 'delete_alumni') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM alumni WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Alumni deleted successfully!";
    } elseif ($action === 'update_alumni') {
        $id = intval($_POST['id']);
        $name = sanitize($_POST['full_name']);
        $year = sanitize($_POST['graduation_year']);
        $image_url = sanitize($_POST['current_image_url'] ?? '');

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../images/alumni/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . $_FILES['image']['name'];
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                $image_url = 'images/alumni/' . $filename;
            }
        }

        $stmt = $pdo->prepare("UPDATE alumni SET full_name = ?, graduation_year = ?, image_url = ? WHERE id = ?");
        $stmt->execute([$name, $year, $image_url, $id]);
        $message = "Alumni updated successfully!";
    } elseif ($action === 'delete_gallery') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM gallery WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Gallery item deleted successfully!";
    } elseif ($action === 'update_gallery') {
        $id = intval($_POST['id']);
        $title = sanitize($_POST['title']);
        $image_url = sanitize($_POST['current_image_url'] ?? '');

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../images/gallery/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . $_FILES['image']['name'];
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                $image_url = 'images/gallery/' . $filename;
            }
        }

        $stmt = $pdo->prepare("UPDATE gallery SET title = ?, image_url = ? WHERE id = ?");
        $stmt->execute([$title, $image_url, $id]);
        $message = "Gallery item updated successfully!";
    } elseif ($action === 'delete_project') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Project deleted successfully!";
    } elseif ($action === 'update_project') {
        $id = intval($_POST['id']);
        $title = sanitize($_POST['title']);
        $desc = sanitize($_POST['description']);
        $status = sanitize($_POST['status']);
        $date = sanitize($_POST['project_date']);
        $image_url = sanitize($_POST['current_image_url'] ?? '');

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../images/projects/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . $_FILES['image']['name'];
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                $image_url = 'images/projects/' . $filename;
            }
        }

        $stmt = $pdo->prepare("UPDATE projects SET title = ?, description = ?, status = ?, project_date = ?, image_url = ? WHERE id = ?");
        $stmt->execute([$title, $desc, $status, $date, $image_url, $id]);
        $message = "Project updated successfully!";
    }
}

// Fetch Data for Tables
$executives = $pdo->query("SELECT * FROM executives")->fetchAll();
$alumni = $pdo->query("SELECT * FROM alumni")->fetchAll();
$gallery = $pdo->query("SELECT * FROM gallery ORDER BY created_at DESC")->fetchAll();
$projects = $pdo->query("SELECT * FROM projects ORDER BY project_date DESC")->fetchAll();
$submissions = $pdo->query("SELECT * FROM contact_submissions ORDER BY created_at DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Module Settings - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
            position: relative;
        }
        .close-btn {
            position: absolute;
            right: 15px;
            top: 10px;
            font-size: 24px;
            cursor: pointer;
            color: #888;
        }
        .upload-preview {
            width: 110px;
            height: 110px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #d4dbe3;
            margin: 0 auto 10px auto;
            display: block;
            background: #f3f6f9;
        }
        .upload-file-name {
            margin-top: 8px;
            font-size: 0.82rem;
            text-align: center;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header" style="text-align: center; padding: 20px 10px;">
                <img src="../images/infotess.png" alt="INFOTESS Logo" style="width: 80px; height: 80px; margin-bottom: 10px; border-radius: 50%; background: #fff; padding: 5px;">
                <h3>INFOTESS Admin</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="verify.php"><i class="fas fa-qrcode"></i> Verify Receipt</a></li>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="messaging.php"><i class="fas fa-envelope"></i> Messaging</a></li>
                <li><a href="inbox.php"><i class="fas fa-inbox"></i> Inbox</a></li>
                <li><a href="module_settings.php" class="active"><i class="fas fa-cogs"></i> Module Settings</a></li>
                <li><a href="settings.php"><i class="fas fa-tools"></i> System Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h2>Module & Contact Settings</h2>
                <div style="display:flex; gap:10px; flex-wrap: wrap;">
                    <button onclick="document.getElementById('execModal').style.display='block'" class="btn-admin-action"><i class="fas fa-user-tie"></i> Add Executive</button>
                    <button onclick="document.getElementById('alumniModal').style.display='block'" class="btn-admin-action"><i class="fas fa-graduation-cap"></i> Add Alumni</button>
                    <button onclick="document.getElementById('galleryModal').style.display='block'" class="btn-admin-action"><i class="fas fa-images"></i> Add Gallery</button>
                    <button onclick="document.getElementById('projectModal').style.display='block'" class="btn-admin-action"><i class="fas fa-project-diagram"></i> Add Project</button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Contact Submissions -->
            <div class="section">
                <div class="card">
                    <h3>Contact Us Submissions</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Response</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submissions as $sub): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sub['name']); ?></td>
                                    <td><?php echo htmlspecialchars($sub['email']); ?></td>
                                    <td><?php echo htmlspecialchars($sub['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($sub['message']); ?></td>
                                    <td><?php echo $sub['response'] ? htmlspecialchars($sub['response']) : '<em>Pending</em>'; ?></td>
                                    <td>
                                        <button onclick="document.getElementById('resp-<?php echo $sub['id']; ?>').style.display='block'" class="btn-primary">Respond</button>
                                        <button onclick="document.getElementById('edit-sub-<?php echo $sub['id']; ?>').style.display='block'" class="btn-admin-action btn-admin-secondary btn-admin-sm" style="margin-top:6px;"><i class="fas fa-pen"></i> Edit</button>
                                        <div id="resp-<?php echo $sub['id']; ?>" style="display:none; margin-top:10px;">
                                            <form method="POST">
                                                <input type="hidden" name="action" value="respond_contact">
                                                <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                                <textarea name="response" class="form-control" placeholder="Type response..."></textarea>
                                                <button type="submit" class="btn-submit">Submit Response</button>
                                            </form>
                                        </div>
                                        <div id="edit-sub-<?php echo $sub['id']; ?>" style="display:none; margin-top:10px;">
                                            <form method="POST">
                                                <input type="hidden" name="action" value="update_contact_submission">
                                                <input type="hidden" name="id" value="<?php echo $sub['id']; ?>">
                                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($sub['name']); ?>" style="margin-bottom:8px;" required>
                                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($sub['email']); ?>" style="margin-bottom:8px;" required>
                                                <input type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars($sub['subject']); ?>" style="margin-bottom:8px;" required>
                                                <textarea name="message" class="form-control" rows="3" style="margin-bottom:8px;" required><?php echo htmlspecialchars($sub['message']); ?></textarea>
                                                <textarea name="response" class="form-control" rows="3" placeholder="Response (optional)" style="margin-bottom:8px;"><?php echo htmlspecialchars($sub['response'] ?? ''); ?></textarea>
                                                <button type="submit" class="btn-submit">Save Changes</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="section" style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div class="card">
                    <h3>Current Executives</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($executives as $exec): ?>
                                <tr>
                                    <td><img src="../<?php echo $exec['image_url'] ?: 'images/aamusted.jpg'; ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover;"></td>
                                    <td><?php echo htmlspecialchars($exec['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($exec['position']); ?></td>
                                    <td>
                                        <button type="button" onclick="document.getElementById('edit-exec-<?php echo $exec['id']; ?>').style.display='block'" class="btn-admin-action btn-admin-secondary btn-admin-sm"><i class="fas fa-pen"></i> Edit</button>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this executive?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_executive">
                                            <input type="hidden" name="id" value="<?php echo $exec['id']; ?>">
                                            <button type="submit" class="btn-login" style="background:#dc3545; padding: 5px 10px; font-size: 0.8rem;"><i class="fas fa-trash"></i></button>
                                        </form>
                                        <div id="edit-exec-<?php echo $exec['id']; ?>" style="display:none; margin-top:10px;">
                                            <form method="POST" enctype="multipart/form-data">
                                                <input type="hidden" name="action" value="update_executive">
                                                <input type="hidden" name="id" value="<?php echo $exec['id']; ?>">
                                                <input type="hidden" name="current_image_url" value="<?php echo htmlspecialchars($exec['image_url'] ?: 'images/aamusted.jpg'); ?>">
                                                <img id="editExecPreview-<?php echo $exec['id']; ?>" src="../<?php echo htmlspecialchars($exec['image_url'] ?: 'images/aamusted.jpg'); ?>" class="upload-preview" alt="Executive image">
                                                <input type="file" name="image" class="form-control image-upload-input" accept="image/*" data-preview-target="editExecPreview-<?php echo $exec['id']; ?>" data-file-name-target="editExecFileName-<?php echo $exec['id']; ?>" data-default-src="../<?php echo htmlspecialchars($exec['image_url'] ?: 'images/aamusted.jpg'); ?>" style="margin-bottom:8px;">
                                                <div id="editExecFileName-<?php echo $exec['id']; ?>" class="upload-file-name" style="margin-bottom:8px;">No image selected</div>
                                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($exec['full_name']); ?>" style="margin-bottom:8px;" required>
                                                <input type="text" name="position" class="form-control" value="<?php echo htmlspecialchars($exec['position']); ?>" style="margin-bottom:8px;" required>
                                                <button type="submit" class="btn-submit">Save Changes</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card">
                    <h3>Current Alumni</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Year</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alumni as $alum): ?>
                                <tr>
                                    <td><img src="../<?php echo $alum['image_url'] ?: 'images/aamusted.jpg'; ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover;"></td>
                                    <td><?php echo htmlspecialchars($alum['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($alum['graduation_year']); ?></td>
                                    <td>
                                        <button type="button" onclick="document.getElementById('edit-alum-<?php echo $alum['id']; ?>').style.display='block'" class="btn-admin-action btn-admin-secondary btn-admin-sm"><i class="fas fa-pen"></i> Edit</button>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this alumni?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_alumni">
                                            <input type="hidden" name="id" value="<?php echo $alum['id']; ?>">
                                            <button type="submit" class="btn-login" style="background:#dc3545; padding: 5px 10px; font-size: 0.8rem;"><i class="fas fa-trash"></i></button>
                                        </form>
                                        <div id="edit-alum-<?php echo $alum['id']; ?>" style="display:none; margin-top:10px;">
                                            <form method="POST" enctype="multipart/form-data">
                                                <input type="hidden" name="action" value="update_alumni">
                                                <input type="hidden" name="id" value="<?php echo $alum['id']; ?>">
                                                <input type="hidden" name="current_image_url" value="<?php echo htmlspecialchars($alum['image_url'] ?: 'images/aamusted.jpg'); ?>">
                                                <img id="editAlumPreview-<?php echo $alum['id']; ?>" src="../<?php echo htmlspecialchars($alum['image_url'] ?: 'images/aamusted.jpg'); ?>" class="upload-preview" alt="Alumni image">
                                                <input type="file" name="image" class="form-control image-upload-input" accept="image/*" data-preview-target="editAlumPreview-<?php echo $alum['id']; ?>" data-file-name-target="editAlumFileName-<?php echo $alum['id']; ?>" data-default-src="../<?php echo htmlspecialchars($alum['image_url'] ?: 'images/aamusted.jpg'); ?>" style="margin-bottom:8px;">
                                                <div id="editAlumFileName-<?php echo $alum['id']; ?>" class="upload-file-name" style="margin-bottom:8px;">No image selected</div>
                                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($alum['full_name']); ?>" style="margin-bottom:8px;" required>
                                                <input type="text" name="graduation_year" class="form-control" value="<?php echo htmlspecialchars($alum['graduation_year']); ?>" style="margin-bottom:8px;" required>
                                                <button type="submit" class="btn-submit">Save Changes</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="section" style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top: 30px;">
                <div class="card">
                    <h3>Current Gallery Items</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gallery as $item): ?>
                                <tr>
                                    <td><img src="../<?php echo $item['image_url']; ?>" style="width:60px; height:40px; object-fit:cover; border-radius:4px;"></td>
                                    <td><?php echo htmlspecialchars($item['title']); ?></td>
                                    <td>
                                        <button type="button" onclick="document.getElementById('edit-gallery-<?php echo $item['id']; ?>').style.display='block'" class="btn-admin-action btn-admin-secondary btn-admin-sm"><i class="fas fa-pen"></i> Edit</button>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this gallery item?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_gallery">
                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="btn-login" style="background:#dc3545; padding: 5px 10px; font-size: 0.8rem;"><i class="fas fa-trash"></i></button>
                                        </form>
                                        <div id="edit-gallery-<?php echo $item['id']; ?>" style="display:none; margin-top:10px;">
                                            <form method="POST" enctype="multipart/form-data">
                                                <input type="hidden" name="action" value="update_gallery">
                                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                <input type="hidden" name="current_image_url" value="<?php echo htmlspecialchars($item['image_url']); ?>">
                                                <img id="editGalleryPreview-<?php echo $item['id']; ?>" src="../<?php echo htmlspecialchars($item['image_url']); ?>" class="upload-preview" alt="Gallery image">
                                                <input type="file" name="image" class="form-control image-upload-input" accept="image/*" data-preview-target="editGalleryPreview-<?php echo $item['id']; ?>" data-file-name-target="editGalleryFileName-<?php echo $item['id']; ?>" data-default-src="../<?php echo htmlspecialchars($item['image_url']); ?>" style="margin-bottom:8px;">
                                                <div id="editGalleryFileName-<?php echo $item['id']; ?>" class="upload-file-name" style="margin-bottom:8px;">No image selected</div>
                                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($item['title']); ?>" style="margin-bottom:8px;" required>
                                                <button type="submit" class="btn-submit">Save Changes</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card">
                    <h3>Current Projects</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td><img src="../<?php echo $project['image_url'] ?: 'images/project-placeholder.png'; ?>" style="width:60px; height:40px; object-fit:cover; border-radius:4px;"></td>
                                    <td><?php echo htmlspecialchars($project['title']); ?></td>
                                    <td><span class="badge" style="background:#17a2b8; color:white; padding:2px 6px; border-radius:4px; font-size:0.75rem;"><?php echo ucfirst($project['status']); ?></span></td>
                                    <td>
                                        <button type="button" onclick="document.getElementById('edit-project-<?php echo $project['id']; ?>').style.display='block'" class="btn-admin-action btn-admin-secondary btn-admin-sm"><i class="fas fa-pen"></i> Edit</button>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this project?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_project">
                                            <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
                                            <button type="submit" class="btn-login" style="background:#dc3545; padding: 5px 10px; font-size: 0.8rem;"><i class="fas fa-trash"></i></button>
                                        </form>
                                        <div id="edit-project-<?php echo $project['id']; ?>" style="display:none; margin-top:10px;">
                                            <form method="POST" enctype="multipart/form-data">
                                                <input type="hidden" name="action" value="update_project">
                                                <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
                                                <input type="hidden" name="current_image_url" value="<?php echo htmlspecialchars($project['image_url'] ?: 'images/project-placeholder.png'); ?>">
                                                <img id="editProjectPreview-<?php echo $project['id']; ?>" src="../<?php echo htmlspecialchars($project['image_url'] ?: 'images/project-placeholder.png'); ?>" class="upload-preview" alt="Project image">
                                                <input type="file" name="image" class="form-control image-upload-input" accept="image/*" data-preview-target="editProjectPreview-<?php echo $project['id']; ?>" data-file-name-target="editProjectFileName-<?php echo $project['id']; ?>" data-default-src="../<?php echo htmlspecialchars($project['image_url'] ?: 'images/project-placeholder.png'); ?>" style="margin-bottom:8px;">
                                                <div id="editProjectFileName-<?php echo $project['id']; ?>" class="upload-file-name" style="margin-bottom:8px;">No image selected</div>
                                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($project['title']); ?>" style="margin-bottom:8px;" required>
                                                <textarea name="description" class="form-control" rows="3" style="margin-bottom:8px;"><?php echo htmlspecialchars($project['description']); ?></textarea>
                                                <select name="status" class="form-control" style="margin-bottom:8px;">
                                                    <option value="completed" <?php echo $project['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="ongoing" <?php echo $project['status'] === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                                    <option value="planned" <?php echo $project['status'] === 'planned' ? 'selected' : ''; ?>>Planned</option>
                                                </select>
                                                <input type="date" name="project_date" class="form-control" value="<?php echo htmlspecialchars($project['project_date'] ?? date('Y-m-d')); ?>" style="margin-bottom:8px;">
                                                <button type="submit" class="btn-submit">Save Changes</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Executive Modal -->
    <div id="execModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('execModal').style.display='none'">&times;</span>
            <h3>Add Executive</h3>
            <form method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                <input type="hidden" name="action" value="add_executive">
                <div class="form-group">
                    <label>Profile Picture</label>
                    <img id="execImagePreview" src="../images/aamusted.jpg" alt="Executive Preview" class="upload-preview">
                    <input type="file" name="image" class="form-control image-upload-input" accept="image/*" data-preview-target="execImagePreview" data-file-name-target="execImageFileName" data-default-src="../images/aamusted.jpg">
                    <div id="execImageFileName" class="upload-file-name">No image selected</div>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control" placeholder="Enter Full Name" required>
                </div>
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" name="position" class="form-control" placeholder="Enter Position" required>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%;">Add Executive</button>
            </form>
        </div>
    </div>

    <!-- Alumni Modal -->
    <div id="alumniModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('alumniModal').style.display='none'">&times;</span>
            <h3>Add Alumni</h3>
            <form method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                <input type="hidden" name="action" value="add_alumni">
                <div class="form-group">
                    <label>Profile Picture</label>
                    <img id="alumniImagePreview" src="../images/aamusted.jpg" alt="Alumni Preview" class="upload-preview">
                    <input type="file" name="image" class="form-control image-upload-input" accept="image/*" data-preview-target="alumniImagePreview" data-file-name-target="alumniImageFileName" data-default-src="../images/aamusted.jpg">
                    <div id="alumniImageFileName" class="upload-file-name">No image selected</div>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control" placeholder="Enter Full Name" required>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <input type="text" name="graduation_year" class="form-control" placeholder="Enter Graduation Year" required>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%;">Add Alumni</button>
            </form>
        </div>
    </div>

    <!-- Project Modal -->
    <div id="projectModal" class="modal">
        <div class="modal-content" style="width: 500px;">
            <span class="close-btn" onclick="document.getElementById('projectModal').style.display='none'">&times;</span>
            <h3>Add Project</h3>
            <form method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                <input type="hidden" name="action" value="add_project">
                <div class="form-group">
                    <label>Project Image</label>
                    <img id="projectImagePreview" src="../images/aamusted.jpg" alt="Project Preview" class="upload-preview">
                    <input type="file" name="image" class="form-control image-upload-input" accept="image/*" data-preview-target="projectImagePreview" data-file-name-target="projectImageFileName" data-default-src="../images/aamusted.jpg">
                    <div id="projectImageFileName" class="upload-file-name">No image selected</div>
                </div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" placeholder="Project Title" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Brief description"></textarea>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="completed">Completed</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="planned">Planned</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="project_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%;">Add Project</button>
            </form>
        </div>
    </div>

    <!-- Gallery Modal -->
    <div id="galleryModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('galleryModal').style.display='none'">&times;</span>
            <h3>Add Gallery Item</h3>
            <form method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                <input type="hidden" name="action" value="add_gallery">
                <div class="form-group">
                    <label>Image</label>
                    <img id="galleryImagePreview" src="../images/aamusted.jpg" alt="Gallery Preview" class="upload-preview">
                    <input type="file" name="image" class="form-control image-upload-input" accept="image/*" required data-preview-target="galleryImagePreview" data-file-name-target="galleryImageFileName" data-default-src="../images/aamusted.jpg">
                    <div id="galleryImageFileName" class="upload-file-name">No image selected</div>
                </div>
                <div class="form-group">
                    <label>Title/Caption</label>
                    <input type="text" name="title" class="form-control" placeholder="Image Title" required>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%;">Add to Gallery</button>
            </form>
        </div>
    </div>

    <script>
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = "none";
            }
        }

        document.querySelectorAll('.image-upload-input').forEach(function(input) {
            input.addEventListener('change', function() {
                const previewId = input.getAttribute('data-preview-target');
                const fileNameId = input.getAttribute('data-file-name-target');
                const defaultSrc = input.getAttribute('data-default-src') || '../images/aamusted.jpg';
                const preview = previewId ? document.getElementById(previewId) : null;
                const fileNameLabel = fileNameId ? document.getElementById(fileNameId) : null;
                const file = input.files && input.files[0] ? input.files[0] : null;

                if (!file) {
                    if (preview) preview.src = defaultSrc;
                    if (fileNameLabel) fileNameLabel.textContent = 'No image selected';
                    return;
                }

                if (fileNameLabel) fileNameLabel.textContent = file.name;

                if (!file.type.startsWith('image/')) {
                    if (preview) preview.src = defaultSrc;
                    if (fileNameLabel) fileNameLabel.textContent = 'Please select an image file';
                    input.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(event) {
                    if (preview) preview.src = event.target.result;
                };
                reader.readAsDataURL(file);
            });
        });
    </script>
</body>
</html>
<?php
require_once 'includes/db.php';
// In a real scenario, use require_once '../vendor/autoload.php';
// For now, we will simulate the Receipt Generation class
require_once 'includes/ReceiptGenerator.php'; 
require_once 'includes/SMSHelper.php';
require_once 'includes/Mailer.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

// Fetch Settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$current_academic_year = $settings['current_academic_year'] ?? date('Y') . '/' . (date('Y') + 1);
$current_semester = $settings['current_semester'] ?? '1';
$payment_modes = explode(',', $settings['payment_modes'] ?? 'Cash,Mobile Money,Bank Transfer');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    $index_number = sanitize($_POST['index_number']);
    $level = isset($_POST['level']) ? sanitize($_POST['level']) : '';
    $class = isset($_POST['class']) ? sanitize($_POST['class']) : '';
    $stream = isset($_POST['stream']) ? sanitize($_POST['stream']) : '';
    $programme = isset($_POST['programme']) ? sanitize($_POST['programme']) : '';
    $amount = floatval($_POST['amount']);
    $year = sanitize($_POST['academic_year']);
    $semester = sanitize($_POST['semester']);
    $method = sanitize($_POST['payment_method']);
    $date = sanitize($_POST['payment_date']);

    // Find Student
    $stmt = $pdo->prepare("
        SELECT s.id, s.full_name, s.index_number, s.phone_number, u.email 
        FROM students s 
        LEFT JOIN users u ON s.user_id = u.id 
        WHERE s.index_number = ?
    ");
    $stmt->execute([$index_number]);
    $student = $stmt->fetch();

    if (!$student) {
        $error = "Student with Index Number $index_number not found.";
    } else {
        // We will allow multiple payments per semester to pay off the balance
        // So we just proceed with recording the payment
        try {
            $pdo->beginTransaction();
            
            // Generate Receipt Number: INFO + YEAR + MONTH + RANDOM
            // Example: INFO-2603-7482
            $receipt_number = "INFO-" . date('ym') . "-" . rand(1000, 9999);

            // Insert Payment
            $stmt = $pdo->prepare("INSERT INTO payments (student_id, amount, academic_year, semester, payment_method, payment_date, receipt_number, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$student['id'], $amount, $year, $semester, $method, $date, $receipt_number, $_SESSION['user_id']]);
            $payment_id = $pdo->lastInsertId();

            // Generate Receipt PDF (Simulation)
            $generator = new ReceiptGenerator();
            
            // Fetch student total paid for the year to calculate balance
            $stmt_paid = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE student_id = ? AND academic_year = ?");
            $stmt_paid->execute([$student['id'], $year]);
            $total_paid = (float)$stmt_paid->fetchColumn();
            
            // Fetch required dues from settings
            $stmt_settings = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'annual_dues_amount'");
            $settings_dues = $stmt_settings->fetchColumn();
            $required_dues = $settings_dues !== false ? (float)$settings_dues : 100.00;
            
            $current_balance = max(0, $required_dues - $total_paid);

            $receipt_path = $generator->generate($payment_id, $receipt_number, $student, $amount, $date, $level, $class, $programme, $current_balance, $year, $semester, $method, $stream);
            
            // Save Receipt Record
            $hash = md5($receipt_number . $payment_id . 'SALT'); // Simple hash
            $stmt = $pdo->prepare("INSERT INTO receipts (payment_id, receipt_file_path, verification_hash) VALUES (?, ?, ?)");
            $stmt->execute([$payment_id, $receipt_path, $hash]);

            $pdo->commit();
            $message = "Payment recorded and receipt generated successfully. Receipt #: $receipt_number";
            
            // Send SMS notification
            if (!empty($student['phone_number'])) {
                $sms = new SMSHelper();
                $sms_message = "Hello " . $student['full_name'] . ", your payment of GHS " . number_format($amount, 2) . " for " . $year . " " . $semester . " has been received. Receipt #: " . $receipt_number . ". Thank you.";
                $sms->send($student['phone_number'], $sms_message);
            }

            // Send Email with Receipt
            if (!empty($student['email'])) {
                $mailer = new Mailer();
                
                // Create custom email template matching the provided image
                $email_html = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
                        .email-container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                        .header { background-color: #1a9e65; color: white; text-align: center; padding: 40px 20px; }
                        .header h1 { margin: 0; font-size: 28px; }
                        .header p { margin: 10px 0 0 0; font-size: 14px; }
                        .content { padding: 30px; color: #333; }
                        .receipt-box { border: 1px solid #1a9e65; border-radius: 8px; padding: 20px; margin-top: 20px; }
                        .receipt-title { text-align: center; color: #1a9e65; margin-bottom: 20px; }
                        .receipt-title h2 { margin: 0; font-size: 20px; }
                        .receipt-title p { margin: 5px 0 0 0; color: #555; font-size: 14px; }
                        .receipt-row { padding: 12px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; }
                        .receipt-row:last-child { border-bottom: none; }
                        .amount-box { background-color: #1a9e65; color: white; text-align: center; padding: 20px; border-radius: 8px; margin-top: 20px; }
                        .amount-box p { margin: 0 0 5px 0; font-size: 14px; }
                        .amount-box h2 { margin: 0; font-size: 28px; }
                        .paid-badge { background-color: #1a9e65; color: white; padding: 5px 15px; border-radius: 15px; display: inline-block; margin-top: 15px; font-weight: bold; font-size: 14px; }
                        .notes { margin-top: 30px; font-size: 12px; color: #333; }
                        .notes ul { padding-left: 20px; }
                        .footer { text-align: center; padding: 30px; font-size: 12px; color: #666; border-top: 1px solid #eee; }
                        .footer a { color: #0056b3; text-decoration: none; }
                    </style>
                </head>
                <body>
                    <div class='email-container'>
                        <div class='header'>
                            <h1>Γ£ô Payment Received!</h1>
                            <p>USTED - Infotess Dues Payment Confirmation</p>
                        </div>
                        <div class='content'>
                            <p>Dear <strong>{$student['full_name']}</strong>,</p>
                            <p>Your payment has been successfully received and recorded in our system.</p>
                            
                            <div class='receipt-box'>
                                <div class='receipt-title'>
                                    <h2>OFFICIAL RECEIPT</h2>
                                    <p>Receipt No: $receipt_number</p>
                                </div>
                                
                                <div class='receipt-row'>
                                    <span style='color: #666;'>Student Name:</span>
                                    <strong>{$student['full_name']}</strong>
                                </div>
                                <div class='receipt-row'>
                                    <span style='color: #666;'>Index Number:</span>
                                    <strong>{$student['index_number']}</strong>
                                </div>
                                <div class='receipt-row'>
                                    <span style='color: #666;'>Level:</span>
                                    <strong>Level " . (!empty($level) ? htmlspecialchars($level) : '100') . "</strong>
                                </div>
                                <div class='receipt-row'>
                                    <span style='color: #666;'>Class:</span>
                                    <strong>Class " . (!empty($class) ? htmlspecialchars($class) : 'E') . "</strong>
                                </div>
                                <div class='receipt-row'>
                                    <span style='color: #666;'>Stream:</span>
                                    <strong>" . (!empty($stream) ? htmlspecialchars($stream) : 'Regular') . "</strong>
                                </div>
                                <div class='receipt-row'>
                                    <span style='color: #666;'>Academic Year:</span>
                                    <strong>$year</strong>
                                </div>
                                <div class='receipt-row'>
                                    <span style='color: #666;'>Semester:</span>
                                    <strong>$semester</strong>
                                </div>
                                <div class='receipt-row'>
                                    <span style='color: #666;'>Payment Method:</span>
                                    <strong>$method</strong>
                                </div>
                                <div class='receipt-row'>
                                    <span style='color: #666;'>Payment Date:</span>
                                    <strong>$date</strong>
                                </div>
                                
                                <div class='amount-box'>
                                    <p>Amount Paid</p>
                                    <h2>GHΓé╡ " . number_format($amount, 2) . "</h2>
                                </div>
                                
                                <div style='text-align: center; margin-top: 20px;'>
                                    <img src='https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode(getAppUrl() . "/verify_public.php?receipt=" . $receipt_number) . "' alt='QR Code' style='width: 100px; height: 100px; margin-bottom: 10px;' />
                                    <br>
                                    <div class='paid-badge'>Γ£ô PAID</div>
                                </div>
                            </div>
                            
                            <div class='notes'>
                                <strong>Important Notes:</strong>
                                <ul>
                                    <li>Keep this email for your records</li>
                                    <li>This receipt is valid for graduation clearance</li>
                                    <li>You can access this receipt anytime from the system</li>
                                    <li>Receipt Number: <strong>$receipt_number</strong></li>
                                </ul>
                                <p>Thank you for your prompt payment!</p>
                            </div>
                        </div>
                        
                        <div class='footer'>
                            <p><strong>USTED - Infotess - Finance Office</strong></p>
                            <p><a href='http://usted.edu.gh'>usted.edu.gh</a>, Kumasi, Ghana</p>
                            <p>Phone: +233 24 091 8031</p>
                            <p style='color: #999; margin-top: 20px;'>This is an automated email. Please do not reply to this message.<br>For inquiries, contact the finance office directly.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";

                $mailer->sendHTML($student['email'], "Payment Receipt - " . $receipt_number, $email_html);
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Record Payment - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modal Styles */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.5); 
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto; 
            padding: 20px;
            border: 1px solid #888;
            width: 80%; 
            max-width: 600px;
            border-radius: 8px;
            position: relative;
        }
        .close-btn {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-btn:hover,
        .close-btn:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header" style="text-align: center; padding: 20px 10px;">
                <img src="../images/infotess.png" alt="INFOTESS Logo" style="width: 80px; height: 80px; margin-bottom: 10px; border-radius: 50%; background: #fff; padding: 5px;">
                <h3>INFOTESS Admin</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="payments.php" class="active"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="verify.php"><i class="fas fa-qrcode"></i> Verify Receipt</a></li>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="messaging.php"><i class="fas fa-envelope"></i> Messaging</a></li>
                <li><a href="inbox.php"><i class="fas fa-inbox"></i> Inbox</a></li>
                <li><a href="module_settings.php"><i class="fas fa-cogs"></i> Module Settings</a></li>
                <li><a href="settings.php"><i class="fas fa-tools"></i> System Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h2>Record Payment</h2>
                <button id="openModalBtn" class="btn-primary" style="padding: 10px 20px;"><i class="fas fa-plus"></i> Record New Payment</button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Payment Records List -->
            <div class="section">
                <h3>Recent Payments</h3>
                <?php
                // Fetch required dues from settings
                $stmt_settings = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'annual_dues_amount'");
                $settings_dues = $stmt_settings->fetchColumn();
                $required_dues = $settings_dues !== false ? (float)$settings_dues : 100.00;
                
                // Pagination settings
                $limit = 10;
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                if ($page < 1) $page = 1;
                $offset = ($page - 1) * $limit;

                // Fetch recent payments for display with pagination
                $stmt = $pdo->prepare("
                    SELECT SQL_CALC_FOUND_ROWS p.*, s.full_name, s.index_number,
                           (SELECT SUM(amount) FROM payments WHERE student_id = s.id AND academic_year = :year_sub) as total_paid
                    FROM payments p 
                    JOIN students s ON p.student_id = s.id 
                    ORDER BY p.created_at DESC 
                    LIMIT $limit OFFSET $offset
                ");
                $stmt->execute(['year_sub' => $current_academic_year]);
                $recent_payments = $stmt->fetchAll();

                $total_stmt = $pdo->query("SELECT FOUND_ROWS()");
                $total_rows = (int)$total_stmt->fetchColumn();
                $total_pages = ceil($total_rows / $limit);
                ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Student</th>
                                <th>Amount (GHS)</th>
                                <th>Balance (GHS)</th>
                                <th>Date</th>
                                <th>Method</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $payment): 
                                $balance = max(0, $required_dues - (float)$payment['total_paid']);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($payment['full_name']); ?><br>
                                    <small><?php echo htmlspecialchars($payment['index_number']); ?></small>
                                </td>
                                <td><?php echo number_format($payment['amount'], 2); ?></td>
                                <td>
                                    <span style="color: <?php echo $balance > 0 ? 'red' : 'green'; ?>; font-weight: bold;">
                                        <?php echo number_format($balance, 2); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                <td>
                                    <a href="../receipts/receipt_<?php echo htmlspecialchars($payment['receipt_number']); ?>.html" target="_blank" class="btn-login" style="padding: 5px 10px; font-size: 0.8rem;">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div style="display: flex; justify-content: center; margin-top: 20px; gap: 5px;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="btn-login" style="background: #f8f9fa; color: #333; border: 1px solid #ddd;">&laquo; Prev</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="btn-login" style="<?php echo $i == $page ? 'background: var(--primary-color);' : 'background: #f8f9fa; color: #333; border: 1px solid #ddd;'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="btn-login" style="background: #f8f9fa; color: #333; border: 1px solid #ddd;">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Record Payment Modal -->
            <div id="paymentModal" class="modal">
                <div class="modal-content">
                    <span class="close-btn">&times;</span>
                    <h3>Record Payment</h3>
                    <form action="payments.php" method="POST" class="card-content" style="margin-top: 15px;">
                        <input type="hidden" name="action" value="record_payment">
                        
                        <div class="form-group">
                            <label>Student Index Number</label>
                            <input type="text" name="index_number" class="form-control" required placeholder="e.g. 5231230001">
                            <small id="indexLookupStatus" style="display:none; margin-top:6px; font-size:0.85rem;"></small>
                        </div>

                        <div class="form-group">
                            <label>Programme / Department</label>
                            <select name="programme" class="form-control" required>
                                <option value="">-- Select Programme --</option>
                                <optgroup label="Bachelor's Degree Programmes">
                                    <option value="B.Sc. Information Technology">B.Sc. Information Technology</option>
                                    <option value="B.Sc. Cyber Security and Digital Forensics">B.Sc. Cyber Security and Digital Forensics</option>
                                    <option value="B.Ed. Computing with Artificial Intelligence (AI)">B.Ed. Computing with Artificial Intelligence (AI)</option>
                                    <option value="B.Ed. Computing with Internet of Things (IOT)">B.Ed. Computing with Internet of Things (IOT)</option>
                                    <option value="B.Ed. Information Technology">B.Ed. Information Technology</option>
                                </optgroup>
                                <optgroup label="Diploma Programmes">
                                    <option value="Diploma in Cyber Security and Digital Forensics">Diploma in Cyber Security and Digital Forensics</option>
                                    <option value="Diploma in Information Technology">Diploma in Information Technology</option>
                                </optgroup>
                                <optgroup label="Postgraduate Programmes">
                                    <option value="M. Phil. Information Technology">M. Phil. Information Technology</option>
                                    <option value="M. Sc. Information Technology Education">M. Sc. Information Technology Education</option>
                                    <option value="M. Phil Information Technology (Top-up)">M. Phil Information Technology (Top-up)</option>
                                </optgroup>
                            </select>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:15px;">
                            <div class="form-group">
                                <label>Level</label>
                                <input type="text" name="level" class="form-control" required placeholder="e.g. 300">
                            </div>
                            <div class="form-group">
                                <label>Class</label>
                                <select name="class" class="form-control" required>
                                    <option value="">-- Select Class --</option>
                                    <optgroup label="IT">
                                        <option value="IT A">IT A</option>
                                        <option value="IT B">IT B</option>
                                        <option value="IT C">IT C</option>
                                        <option value="IT D">IT D</option>
                                        <option value="IT E">IT E</option>
                                        <option value="IT F">IT F</option>
                                        <option value="IT G">IT G</option>
                                        <option value="IT H">IT H</option>
                                    </optgroup>
                                    <optgroup label="ITE">
                                        <option value="ITE A">ITE A</option>
                                        <option value="ITE B">ITE B</option>
                                        <option value="ITE C">ITE C</option>
                                        <option value="ITE D">ITE D</option>
                                        <option value="ITE E">ITE E</option>
                                        <option value="ITE F">ITE F</option>
                                        <option value="ITE G">ITE G</option>
                                        <option value="ITE H">ITE H</option>
                                        <option value="ITE I">ITE I</option>
                                        <option value="ITE J">ITE J</option>
                                        <option value="ITE K">ITE K</option>
                                    </optgroup>
                                    <optgroup label="CB">
                                        <option value="CB A">CB A</option>
                                        <option value="CB B">CB B</option>
                                        <option value="CB C">CB C</option>
                                        <option value="CB D">CB D</option>
                                        <option value="CB E">CB E</option>
                                        <option value="CB F">CB F</option>
                                        <option value="CB G">CB G</option>
                                        <option value="CB H">CB H</option>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Stream</label>
                                <select name="stream" class="form-control" required>
                                    <option value="">-- Select Stream --</option>
                                    <option value="Regular">Regular</option>
                                    <option value="Sandwich">Sandwich</option>
                                    <option value="Evening">Evening</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Amount (GHS)</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                            <div class="form-group">
                                <label>Academic Year</label>
                                <input type="text" name="academic_year" class="form-control" value="<?php echo htmlspecialchars($current_academic_year); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Semester</label>
                                <select name="semester" class="form-control" required>
                                    <option value="1" <?php echo $current_semester == '1' ? 'selected' : ''; ?>>First Semester</option>
                                    <option value="2" <?php echo $current_semester == '2' ? 'selected' : ''; ?>>Second Semester</option>
                                </select>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                            <div class="form-group">
                                <label>Payment Method</label>
                                <select name="payment_method" class="form-control" required>
                                    <?php foreach ($payment_modes as $mode): ?>
                                        <option value="<?php echo htmlspecialchars(trim($mode)); ?>"><?php echo htmlspecialchars(trim($mode)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Payment Date</label>
                                <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit" style="margin-top: 10px;">Record Payment & Generate Receipt</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        const modal = document.getElementById("paymentModal");
        const btn = document.getElementById("openModalBtn");
        const span = document.getElementsByClassName("close-btn")[0];
        const indexInput = document.querySelector('input[name="index_number"]');
        const programmeSelect = document.querySelector('select[name="programme"]');
        const levelInput = document.querySelector('input[name="level"]');
        const classSelect = document.querySelector('select[name="class"]');
        const streamSelect = document.querySelector('select[name="stream"]');
        const lookupStatus = document.getElementById('indexLookupStatus');
        let lookupTimer = null;
        let lastLookupValue = '';

        btn.onclick = function() {
            modal.style.display = "block";
        }

        span.onclick = function() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        function setLookupStatus(message, color) {
            if (!lookupStatus) return;
            if (!message) {
                lookupStatus.style.display = 'none';
                lookupStatus.textContent = '';
                return;
            }
            lookupStatus.style.display = 'block';
            lookupStatus.style.color = color;
            lookupStatus.textContent = message;
        }

        function selectOrCreateOption(selectElement, value) {
            if (!selectElement || !value) return;
            const normalized = String(value).trim().toLowerCase();
            let matched = false;
            for (let i = 0; i < selectElement.options.length; i++) {
                const optionValue = String(selectElement.options[i].value || '').trim().toLowerCase();
                if (optionValue === normalized) {
                    selectElement.selectedIndex = i;
                    matched = true;
                    break;
                }
            }
            if (!matched) {
                const option = document.createElement('option');
                option.value = value;
                option.text = value;
                selectElement.add(option);
                selectElement.value = value;
            }
        }

        function clearAutoFilledFields() {
            if (programmeSelect) programmeSelect.value = '';
            if (levelInput) levelInput.value = '';
            if (classSelect) classSelect.value = '';
            if (streamSelect) streamSelect.value = '';
        }

        function fillStudentFields(student) {
            if (programmeSelect && student.department) {
                selectOrCreateOption(programmeSelect, student.department);
            }
            if (levelInput && student.level) {
                levelInput.value = student.level;
            }
            if (classSelect && student.class_name) {
                selectOrCreateOption(classSelect, student.class_name);
            }
            if (streamSelect && student.stream) {
                selectOrCreateOption(streamSelect, student.stream);
            }
        }

        function lookupStudent(force = false) {
            if (!indexInput) return;
            const rawValue = indexInput.value || '';
            const indexNumber = rawValue.replace(/\s+/g, '').toUpperCase();
            indexInput.value = indexNumber;
            if (!indexNumber) {
                lastLookupValue = '';
                clearAutoFilledFields();
                setLookupStatus('', '');
                return;
            }
            if (!force && (indexNumber.length < 8 || indexNumber === lastLookupValue)) {
                return;
            }
            lastLookupValue = indexNumber;
            setLookupStatus('Fetching student details...', '#0c5fb5');

            fetch(`../api/admin/get_student_by_index.php?index_number=${encodeURIComponent(indexNumber)}`, {
                headers: { 'Accept': 'application/json' }
            })
                .then(async response => {
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok || !payload.ok || !payload.student) {
                        throw new Error(payload.error || 'Student not found');
                    }
                    return payload.student;
                })
                .then(student => {
                    fillStudentFields(student);
                    setLookupStatus(`Loaded: ${student.full_name} (${student.index_number})`, '#15803d');
                })
                .catch(() => {
                    clearAutoFilledFields();
                    setLookupStatus('No student found for this index number.', '#b42333');
                });
        }

        if (indexInput) {
            indexInput.addEventListener('input', function() {
                if (lookupTimer) {
                    clearTimeout(lookupTimer);
                }
                lookupTimer = setTimeout(function() {
                    lookupStudent(false);
                }, 300);
            });

            indexInput.addEventListener('blur', function() {
                lookupStudent(true);
            });

            indexInput.addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    lookupStudent(true);
                }
            });
        }
    </script>
</body>
</html>
<?php
require_once 'includes/db.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

// Generate Report Logic
$report_type = $_GET['type'] ?? '';
$data = [];
$headers = [];

if ($report_type) {
    if ($report_type === 'payments_per_dept') {
        $stmt = $pdo->query("
            SELECT s.department, COUNT(p.id) as payment_count, SUM(p.amount) as total_amount 
            FROM payments p 
            JOIN students s ON p.student_id = s.id 
            GROUP BY s.department
        ");
        $data = $stmt->fetchAll();
        $headers = ['Department', 'Payment Count', 'Total Amount'];
    } elseif ($report_type === 'payments_per_year') {
        $stmt = $pdo->query("
            SELECT academic_year, COUNT(id) as payment_count, SUM(amount) as total_amount 
            FROM payments 
            GROUP BY academic_year
        ");
        $data = $stmt->fetchAll();
        $headers = ['Academic Year', 'Payment Count', 'Total Amount'];
    } elseif ($report_type === 'payments_per_semester') {
        $stmt = $pdo->query("
            SELECT semester, COUNT(id) as payment_count, SUM(amount) as total_amount 
            FROM payments 
            GROUP BY semester
        ");
        $data = $stmt->fetchAll();
        $headers = ['Semester', 'Payment Count', 'Total Amount'];
    }
}

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $data) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . $report_type . '_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header" style="text-align: center; padding: 20px 10px;">
                <img src="../images/infotess.png" alt="INFOTESS Logo" style="width: 80px; height: 80px; margin-bottom: 10px; border-radius: 50%; background: #fff; padding: 5px;">
                <h3>INFOTESS Admin</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="verify.php"><i class="fas fa-qrcode"></i> Verify Receipt</a></li>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="messaging.php"><i class="fas fa-envelope"></i> Messaging</a></li>
                <li><a href="inbox.php"><i class="fas fa-inbox"></i> Inbox</a></li>
                <li><a href="module_settings.php"><i class="fas fa-cogs"></i> Module Settings</a></li>
                <li><a href="settings.php"><i class="fas fa-tools"></i> System Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h2>System Reports</h2>
            </div>

            <div class="report-filters card">
                <h3>Generate Report</h3>
                <form method="GET" action="">
                    <select name="type" required style="padding: 10px; margin-right: 10px;">
                        <option value="">Select Report Type</option>
                        <option value="payments_per_dept" <?php echo $report_type == 'payments_per_dept' ? 'selected' : ''; ?>>Payments per Department</option>
                        <option value="payments_per_year" <?php echo $report_type == 'payments_per_year' ? 'selected' : ''; ?>>Payments per Academic Year</option>
                        <option value="payments_per_semester" <?php echo $report_type == 'payments_per_semester' ? 'selected' : ''; ?>>Payments per Semester</option>
                    </select>
                    <button type="submit" class="btn-admin-action"><i class="fas fa-chart-line"></i> View Report</button>
                </form>
            </div>

            <?php if ($data): ?>
                <div class="card" style="margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3>Report Results</h3>
                        <div>
                            <a href="?type=<?php echo $report_type; ?>&export=csv" class="btn-primary" style="background: #28a745;">Export CSV</a>
                            <button onclick="window.print()" class="btn-primary" style="background: #17a2b8;">Print PDF</button>
                        </div>
                    </div>
                    
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f4f4f4; text-align: left;">
                                <?php foreach ($headers as $header): ?>
                                    <th style="padding: 10px; border-bottom: 2px solid #ddd;"><?php echo $header; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <?php foreach ($row as $cell): ?>
                                        <td style="padding: 10px; border-bottom: 1px solid #ddd;"><?php echo htmlspecialchars($cell); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Chart -->
                <div class="card" style="margin-top: 20px;">
                    <canvas id="reportChart"></canvas>
                </div>
                
                <script>
                    const ctx = document.getElementById('reportChart').getContext('2d');
                    const data = <?php echo json_encode($data); ?>;
                    const labels = data.map(item => Object.values(item)[0]);
                    const values = data.map(item => Object.values(item)[2]); // Total Amount

                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Total Amount (GHS)',
                                data: values,
                                backgroundColor: 'rgba(0, 51, 102, 0.6)',
                                borderColor: 'rgba(0, 51, 102, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });
                </script>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
<?php
require_once 'includes/db.php';

// Ensure Admin Access
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$message = '';
$error = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    try {
        $pdo->beginTransaction();
        
        $settings = [
            'current_academic_year' => sanitize($_POST['current_academic_year']),
            'current_semester' => sanitize($_POST['current_semester']),
            'annual_dues_amount' => sanitize($_POST['annual_dues_amount']),
            'payment_modes' => sanitize($_POST['payment_modes']),
            'department_name' => sanitize($_POST['department_name']),
            'institution_name' => sanitize($_POST['institution_name'])
        ];

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }

        $pdo->commit();
        $message = "System settings updated successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Fetch Current Settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Default values if not set
$defaults = [
    'current_academic_year' => '2025/2026',
    'current_semester' => '1',
    'annual_dues_amount' => '100.00',
    'payment_modes' => 'Cash,Mobile Money,Bank Transfer',
    'department_name' => 'Information Technology Education',
    'institution_name' => 'USTED'
];
$settings = array_merge($defaults, $settings);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - INFOTESS SDMS</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 50%;
            position: relative;
        }
        .close-btn {
            position: absolute;
            right: 15px;
            top: 10px;
            font-size: 24px;
            cursor: pointer;
            color: #888;
        }
        .settings-grid {
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px;
            margin-bottom: 20px;
        }
        .setting-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
        .setting-label {
            font-weight: bold;
            color: #555;
            display: block;
            margin-bottom: 5px;
        }
        .setting-value {
            font-size: 1.1em;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header" style="text-align: center; padding: 20px 10px;">
                <img src="../images/infotess.png" alt="INFOTESS Logo" style="width: 80px; height: 80px; margin-bottom: 10px; border-radius: 50%; background: #fff; padding: 5px;">
                <h3>INFOTESS Admin</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="verify.php"><i class="fas fa-qrcode"></i> Verify Receipt</a></li>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="messaging.php"><i class="fas fa-envelope"></i> Messaging</a></li>
                <li><a href="inbox.php"><i class="fas fa-inbox"></i> Inbox</a></li>
                <li><a href="module_settings.php"><i class="fas fa-cogs"></i> Module Settings</a></li>
                <li><a href="settings.php" class="active"><i class="fas fa-tools"></i> System Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <h2>System Configuration</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="section">
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3>General Settings</h3>
                        <button onclick="document.getElementById('settingsModal').style.display='block'" class="btn-admin-action"><i class="fas fa-edit"></i> Edit Configuration</button>
                    </div>
                    <div class="card-content">
                        <div class="settings-grid">
                            <div class="setting-item">
                                <span class="setting-label">Current Academic Year</span>
                                <span class="setting-value"><?php echo htmlspecialchars($settings['current_academic_year']); ?></span>
                            </div>
                            <div class="setting-item">
                                <span class="setting-label">Current Semester</span>
                                <span class="setting-value"><?php echo $settings['current_semester'] == '1' ? 'First Semester' : 'Second Semester'; ?></span>
                            </div>
                            <div class="setting-item">
                                <span class="setting-label">Annual Dues Amount</span>
                                <span class="setting-value">GHS <?php echo htmlspecialchars($settings['annual_dues_amount']); ?></span>
                            </div>
                            <div class="setting-item">
                                <span class="setting-label">Accepted Payment Modes</span>
                                <span class="setting-value"><?php echo htmlspecialchars($settings['payment_modes']); ?></span>
                            </div>
                            <div class="setting-item">
                                <span class="setting-label">Department Name</span>
                                <span class="setting-value"><?php echo htmlspecialchars($settings['department_name']); ?></span>
                            </div>
                            <div class="setting-item">
                                <span class="setting-label">Institution Name</span>
                                <span class="setting-value"><?php echo htmlspecialchars($settings['institution_name']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

    <!-- Settings Modal -->
    <div id="settingsModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('settingsModal').style.display='none'">&times;</span>
            <h2 style="margin-bottom: 20px;">General Settings</h2>
            <form action="settings.php" method="POST">
                <input type="hidden" name="action" value="update_settings">
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                    <div class="form-group">
                        <label>Current Academic Year</label>
                        <input type="text" name="current_academic_year" class="form-control" value="<?php echo htmlspecialchars($settings['current_academic_year']); ?>" required placeholder="e.g. 2025/2026">
                    </div>
                    <div class="form-group">
                        <label>Current Semester</label>
                        <select name="current_semester" class="form-control" required>
                            <option value="1" <?php echo $settings['current_semester'] == '1' ? 'selected' : ''; ?>>First Semester</option>
                            <option value="2" <?php echo $settings['current_semester'] == '2' ? 'selected' : ''; ?>>Second Semester</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                    <div class="form-group">
                        <label>Annual Dues Amount (GHS)</label>
                        <input type="number" step="0.01" name="annual_dues_amount" class="form-control" value="<?php echo htmlspecialchars($settings['annual_dues_amount']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Accepted Payment Modes (Comma separated)</label>
                        <input type="text" name="payment_modes" class="form-control" value="<?php echo htmlspecialchars($settings['payment_modes']); ?>" required placeholder="e.g. Cash, Mobile Money, Bank Transfer">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                    <div class="form-group">
                        <label>Department Name</label>
                        <input type="text" name="department_name" class="form-control" value="<?php echo htmlspecialchars($settings['department_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Institution Name</label>
                        <input type="text" name="institution_name" class="form-control" value="<?php echo htmlspecialchars($settings['institution_name']); ?>" required>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn-primary" style="padding: 12px 25px;"><i class="fas fa-save"></i> Save Configuration</button>
                </div>
            </form>
        </div>
    </div>
            </div>

            <div class="section">
                <div class="card">
                    <div class="card-header">
                        <h3>Configuration Summary</h3>
                    </div>
                    <div class="card-content">
                        <p>These settings control various aspects of the SDMS, including the academic year displayed on receipts and the default dues amount for compliance tracking.</p>
                        <ul style="margin-top: 10px; padding-left: 20px;">
                            <li><strong>Academic Year:</strong> Used for tracking payments and compliance.</li>
                            <li><strong>Dues Amount:</strong> Used to calculate the compliance rate on the dashboard.</li>
                            <li><strong>Payment Modes:</strong> These appear as options when recording a new payment.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            var modal = document.getElementById('settingsModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>

<?php
require_once 'includes/db.php';
require_once 'includes/Mailer.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$message = '';
$error = '';

// Handle Student Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_student') {
    $full_name = sanitize($_POST['full_name']);
    $index_number = sanitize($_POST['index_number']);
    $department = sanitize($_POST['department']);
    $level = sanitize($_POST['level']);
    $class = sanitize($_POST['class'] ?? '');
    $stream = sanitize($_POST['stream'] ?? '');
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone_number']);
    
    // Handle Profile Picture
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../images/profiles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $filename = $index_number . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $filename)) {
            $profile_picture = 'images/profiles/' . $filename;
        }
    }

    // Check duplicate
    $stmt = $pdo->prepare("SELECT id FROM students WHERE index_number = ?");
    $stmt->execute([$index_number]);
    if ($stmt->fetch()) {
        $error = "Student with Index Number $index_number already exists.";
    } else {
        $pdo->beginTransaction();
        try {
            // 1. Create User Account
            $password_hash = password_hash($index_number, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'student')");
            $stmt->execute([$email, $password_hash]);
            $user_id = $pdo->lastInsertId();

            // 2. Create Student Record
            $stmt = $pdo->prepare("INSERT INTO students (user_id, index_number, full_name, department, level, class_name, stream, phone_number, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $index_number, $full_name, $department, $level, $class, $stream, $phone, $profile_picture]);
            
            $student_id = $pdo->lastInsertId();

            $pdo->commit();
            $message = "Student registered successfully.";

            if ($email) {
                $mailer = new Mailer();
                $subject = "Welcome to USTED - Infotess!";
                $dateStr = date('n/j/Y');
                $html = "<div style=\"font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;\">
                    <div style=\"background: linear-gradient(90deg,#4b6cb7,#182848); color:#fff; padding: 24px; text-align:center;\">
                        <div style=\"font-size: 20px; font-weight: 700;\">Welcome to USTED - Infotess!</div>
                        <div style=\"margin-top:8px; font-size:14px; opacity:0.9;\">Student Registration Successful</div>
                    </div>
                    <div style=\"padding: 24px; color:#111827;\">
                        <p>Dear <strong>" . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                        <p>Congratulations! You have been successfully registered in our system. Below are your details:</p>
                        <div style=\"border:1px solid #e5e7eb; border-radius:8px; padding:16px; background:#f9fafb; margin-top:12px;\">
                            <div style=\"display:grid; grid-template-columns: 180px 1fr; gap:8px; font-size:14px;\">
                                <div><strong>Full Name:</strong></div><div>" . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Index Number:</strong></div><div>" . htmlspecialchars($index_number, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Level:</strong></div><div>Level " . htmlspecialchars($level, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Class:</strong></div><div>" . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Stream:</strong></div><div>" . htmlspecialchars($stream, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Department:</strong></div><div>" . htmlspecialchars($department, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Email:</strong></div><div>" . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Phone:</strong></div><div>" . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Registration Date:</strong></div><div>" . $dateStr . "</div>
                            </div>
                        </div>
                        <div style=\"margin-top:16px;\">
                            <div style=\"font-weight:600; margin-bottom:8px;\">Important Information:</div>
                            <ul style=\"margin:0; padding-left:20px; color:#374151; font-size:14px;\">
                                <li>Keep your index number safe - you'll need it for all transactions</li>
                                <li>All payment receipts will be sent to this email address</li>
                                <li>Contact the finance office for any payment-related queries</li>
                            </ul>
                        </div>
                        <p style=\"margin-top:16px; font-size:14px; color:#374151;\">If you have any questions or notice any incorrect information, please contact the administration office immediately.</p>
                        <hr style=\"border:none; border-top:1px solid #e5e7eb; margin:20px 0;\"/>
                        <div style=\"font-size:13px; color:#6b7280; text-align:center;\">
                            <div style=\"font-weight:600;\">USTED - Infotess</div>
                            <div>usted.edu.gh, Kumasi, Ghana</div>
                            <div>Phone: +233 24 091 8031</div>
                            <div style=\"margin-top:8px; font-size:12px;\">This is an automated email. Please do not reply to this message.</div>
                        </div>
                    </div>
                </div>";
                $mailer->sendHTML($email, $subject, $html);
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Fetch Students
$search = $_GET['search'] ?? '';
$query = "SELECT SQL_CALC_FOUND_ROWS * FROM students";
$params = [];
if ($search) {
    $query .= " WHERE full_name LIKE ? OR index_number LIKE ?";
    $params = ["%$search%", "%$search%"];
}
$query .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

$total_stmt = $pdo->query("SELECT FOUND_ROWS()");
$total_rows = (int)$total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modal Styles */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.5); 
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto; 
            padding: 20px;
            border: 1px solid #888;
            width: 80%; 
            max-width: 600px;
            border-radius: 8px;
            position: relative;
        }
        .close-btn {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-btn:hover,
        .close-btn:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .upload-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #d4dbe3;
            margin: 0 auto 10px auto;
            display: block;
            background: #f3f6f9;
        }
        .upload-file-name {
            margin-top: 8px;
            font-size: 0.82rem;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar (Reused) -->
        <aside class="sidebar">
            <div class="sidebar-header" style="text-align: center; padding: 20px 10px;">
                <img src="../images/infotess.png" alt="INFOTESS Logo" style="width: 80px; height: 80px; margin-bottom: 10px; border-radius: 50%; background: #fff; padding: 5px;">
                <h3>INFOTESS Admin</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="students.php" class="active"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="verify.php"><i class="fas fa-qrcode"></i> Verify Receipt</a></li>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="messaging.php"><i class="fas fa-envelope"></i> Messaging</a></li>
                <li><a href="inbox.php"><i class="fas fa-inbox"></i> Inbox</a></li>
                <li><a href="module_settings.php"><i class="fas fa-cogs"></i> Module Settings</a></li>
                <li><a href="settings.php"><i class="fas fa-tools"></i> System Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h2>Student Management</h2>
                <button id="openModalBtn" class="btn-primary" style="padding: 10px 20px;"><i class="fas fa-plus"></i> Add New Student</button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Add Student Modal -->
            <div id="studentModal" class="modal">
                <div class="modal-content">
                    <span class="close-btn">&times;</span>
                    <h3>Register New Student</h3>
                    <form action="students.php" method="POST" enctype="multipart/form-data" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top: 15px;">
                        <input type="hidden" name="action" value="add_student">
                        
                        <div style="grid-column: span 2; text-align: center; margin-bottom: 10px;">
                            <label>Profile Picture</label><br>
                            <img id="studentUploadPreview" src="../images/aamusted.jpg" alt="Profile Preview" class="upload-preview">
                            <input type="file" name="profile_picture" id="studentProfileUpload" class="form-control" accept="image/*">
                            <div id="studentUploadFileName" class="upload-file-name">No image selected</div>
                        </div>

                        <div>
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div>
                            <label>Index Number</label>
                            <input type="text" name="index_number" class="form-control" required>
                        </div>
                        <div>
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div>
                            <label>Phone Number</label>
                            <input type="text" name="phone_number" class="form-control">
                        </div>
                        <div>
                            <label>Programme / Department</label>
                            <select name="department" class="form-control" required>
                                <option value="">-- Select Programme --</option>
                                <optgroup label="Bachelor's Degree Programmes">
                                    <option value="B.Sc. Information Technology">B.Sc. Information Technology</option>
                                    <option value="B.Sc. Cyber Security and Digital Forensics">B.Sc. Cyber Security and Digital Forensics</option>
                                    <option value="B.Ed. Computing with Artificial Intelligence (AI)">B.Ed. Computing with Artificial Intelligence (AI)</option>
                                    <option value="B.Ed. Computing with Internet of Things (IOT)">B.Ed. Computing with Internet of Things (IOT)</option>
                                    <option value="B.Ed. Information Technology">B.Ed. Information Technology</option>
                                </optgroup>
                                <optgroup label="Diploma Programmes">
                                    <option value="Diploma in Cyber Security and Digital Forensics">Diploma in Cyber Security and Digital Forensics</option>
                                    <option value="Diploma in Information Technology">Diploma in Information Technology</option>
                                </optgroup>
                                <optgroup label="Postgraduate Programmes">
                                    <option value="M. Phil. Information Technology">M. Phil. Information Technology</option>
                                    <option value="M. Sc. Information Technology Education">M. Sc. Information Technology Education</option>
                                    <option value="M. Phil Information Technology (Top-up)">M. Phil Information Technology (Top-up)</option>
                                </optgroup>
                            </select>
                        </div>
                        <div>
                            <label>Level</label>
                            <select name="level" class="form-control" required>
                                <option value="100">100</option>
                                <option value="200">200</option>
                                <option value="300">300</option>
                                <option value="400">400</option>
                            </select>
                        </div>
                        <div>
                            <label>Class</label>
                            <select name="class" class="form-control">
                                <option value="">-- Select Class --</option>
                                <optgroup label="IT">
                                    <option value="IT A">IT A</option>
                                    <option value="IT B">IT B</option>
                                    <option value="IT C">IT C</option>
                                    <option value="IT D">IT D</option>
                                    <option value="IT E">IT E</option>
                                    <option value="IT F">IT F</option>
                                    <option value="IT G">IT G</option>
                                    <option value="IT H">IT H</option>
                                </optgroup>
                                <optgroup label="ITE">
                                    <option value="ITE A">ITE A</option>
                                    <option value="ITE B">ITE B</option>
                                    <option value="ITE C">ITE C</option>
                                    <option value="ITE D">ITE D</option>
                                    <option value="ITE E">ITE E</option>
                                    <option value="ITE F">ITE F</option>
                                    <option value="ITE G">ITE G</option>
                                    <option value="ITE H">ITE H</option>
                                    <option value="ITE I">ITE I</option>
                                    <option value="ITE J">ITE J</option>
                                    <option value="ITE K">ITE K</option>
                                </optgroup>
                                <optgroup label="CB">
                                    <option value="CB A">CB A</option>
                                    <option value="CB B">CB B</option>
                                    <option value="CB C">CB C</option>
                                    <option value="CB D">CB D</option>
                                    <option value="CB E">CB E</option>
                                    <option value="CB F">CB F</option>
                                    <option value="CB G">CB G</option>
                                    <option value="CB H">CB H</option>
                                </optgroup>
                            </select>
                        </div>
                        <div>
                            <label>Stream</label>
                            <select name="stream" class="form-control">
                                <option value="">-- Select Stream --</option>
                                <option value="Regular">Regular</option>
                                <option value="Sandwich">Sandwich</option>
                                <option value="Evening">Evening</option>
                            </select>
                        </div>
                        <div style="grid-column: span 2; margin-top: 10px;">
                            <button type="submit" class="btn-submit">Register Student</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Student List -->
            <div class="section">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h3>Registered Students</h3>
                    <form action="students.php" method="GET" style="display:flex; gap:10px;">
                        <input type="text" name="search" placeholder="Search name or index..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-login"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Index Number</th>
                                <th>Name</th>
                                <th>Programme</th>
                                <th>Level</th>
                                <th>Class</th>
                                <th>Stream</th>
                                <th>Contact</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td>
                                    <img src="../<?php echo $student['profile_picture'] ?? 'images/aamusted.jpg'; ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #ddd;">
                                </td>
                                <td><?php echo htmlspecialchars($student['index_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['department']); ?></td>
                                <td><?php echo htmlspecialchars($student['level']); ?></td>
                                <td><?php echo htmlspecialchars($student['class_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($student['stream'] ?? '-'); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($student['phone_number']); ?>
                                </td>
                                <td>
                                    <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn-login" style="background:#f0ad4e;">Edit</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div style="display: flex; justify-content: center; margin-top: 20px; gap: 5px;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="btn-login" style="background: #f8f9fa; color: #333; border: 1px solid #ddd;">&laquo; Prev</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="btn-login" style="<?php echo $i == $page ? 'background: var(--primary-color);' : 'background: #f8f9fa; color: #333; border: 1px solid #ddd;'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="btn-login" style="background: #f8f9fa; color: #333; border: 1px solid #ddd;">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Modal Logic
        const modal = document.getElementById("studentModal");
        const btn = document.getElementById("openModalBtn");
        const span = document.getElementsByClassName("close-btn")[0];

        // When the user clicks the button, open the modal 
        btn.onclick = function() {
            modal.style.display = "block";
        }

        // When the user clicks on <span> (x), close the modal
        span.onclick = function() {
            modal.style.display = "none";
        }

        // When the user clicks anywhere outside of the modal, close it
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        const studentProfileUpload = document.getElementById('studentProfileUpload');
        const studentUploadPreview = document.getElementById('studentUploadPreview');
        const studentUploadFileName = document.getElementById('studentUploadFileName');

        if (studentProfileUpload && studentUploadPreview && studentUploadFileName) {
            studentProfileUpload.addEventListener('change', function() {
                const file = this.files && this.files[0] ? this.files[0] : null;
                if (!file) {
                    studentUploadPreview.src = '../images/aamusted.jpg';
                    studentUploadFileName.textContent = 'No image selected';
                    return;
                }
                studentUploadFileName.textContent = file.name;
                if (!file.type.startsWith('image/')) {
                    studentUploadPreview.src = '../images/aamusted.jpg';
                    studentUploadFileName.textContent = 'Please select an image file';
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(event) {
                    studentUploadPreview.src = event.target.result;
                };
                reader.readAsDataURL(file);
            });
        }
    </script>
</body>
</html>
<?php
require_once 'includes/db.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$message = '';
$error = '';

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        if ($user_id !== $_SESSION['user_id']) { // Prevent self-delete
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $message = "User deleted successfully.";
            } else {
                $error = "Failed to delete user.";
            }
        } else {
            $error = "You cannot delete your own account.";
        }
    }
}

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Fetch Users
$stmt = $pdo->query("SELECT SQL_CALC_FOUND_ROWS * FROM users ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$users = $stmt->fetchAll();

$total_stmt = $pdo->query("SELECT FOUND_ROWS()");
$total_rows = (int)$total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header" style="text-align: center; padding: 20px 10px;">
                <img src="../images/infotess.png" alt="INFOTESS Logo" style="width: 80px; height: 80px; margin-bottom: 10px; border-radius: 50%; background: #fff; padding: 5px;">
                <h3>INFOTESS Admin</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="verify.php"><i class="fas fa-qrcode"></i> Verify Receipt</a></li>
                <li><a href="users.php" class="active"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="messaging.php"><i class="fas fa-envelope"></i> Messaging</a></li>
                <li><a href="inbox.php"><i class="fas fa-inbox"></i> Inbox</a></li>
                <li><a href="module_settings.php"><i class="fas fa-cogs"></i> Module Settings</a></li>
                <li><a href="settings.php"><i class="fas fa-tools"></i> System Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h2>User Management</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card">
                <h3>All Users</h3>
                <table class="table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge <?php echo $user['role'] === 'admin' ? 'badge-primary' : 'badge-secondary'; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo $user['created_at']; ?></td>
                                <td>
                                    <?php if ($user['role'] !== 'super_admin' && $user['id'] !== $_SESSION['user_id']): ?>
                                        <form method="POST" onsubmit="return confirm('Are you sure?');" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn-admin-action btn-admin-danger btn-admin-sm"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div style="display: flex; justify-content: center; margin-top: 20px; gap: 5px;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="btn-login" style="background: #f8f9fa; color: #333; border: 1px solid #ddd;">&laquo; Prev</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="btn-login" style="<?php echo $i == $page ? 'background: var(--primary-color);' : 'background: #f8f9fa; color: #333; border: 1px solid #ddd;'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="btn-login" style="background: #f8f9fa; color: #333; border: 1px solid #ddd;">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
<?php
require_once 'includes/db.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$receipt_number = $_GET['receipt'] ?? '';
$payment = null;
$history = [];

if ($receipt_number) {
    // Fetch specific payment
    $stmt = $pdo->prepare("SELECT p.*, s.full_name, s.index_number, s.department, s.level FROM payments p JOIN students s ON p.student_id = s.id WHERE p.receipt_number = ?");
    $stmt->execute([$receipt_number]);
    $payment = $stmt->fetch();

    if ($payment) {
        // Fetch full history for this student
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? ORDER BY payment_date DESC");
        $stmt->execute([$payment['student_id']]);
        $history = $stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Receipt - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header" style="text-align: center; padding: 20px 10px;">
                <img src="../images/infotess.png" alt="INFOTESS Logo" style="width: 80px; height: 80px; margin-bottom: 10px; border-radius: 50%; background: #fff; padding: 5px;">
                <h3>INFOTESS Admin</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="verify.php" class="active"><i class="fas fa-qrcode"></i> Verify Receipt</a></li>
                <li><a href="users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="messaging.php"><i class="fas fa-envelope"></i> Messaging</a></li>
                <li><a href="inbox.php"><i class="fas fa-inbox"></i> Inbox</a></li>
                <li><a href="module_settings.php"><i class="fas fa-cogs"></i> Module Settings</a></li>
                <li><a href="settings.php"><i class="fas fa-tools"></i> System Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h2>Verify Receipt</h2>
            </div>

            <div class="card">
                <h3>Enter Receipt Number or Scan QR</h3>
                <form method="GET" action="" style="display: flex; gap: 10px; margin-bottom: 20px;">
                    <input type="text" name="receipt" value="<?php echo htmlspecialchars($receipt_number); ?>" placeholder="SDMS-2026-XXXX" class="form-control" style="flex: 1;">
                    <button type="submit" class="btn-admin-action"><i class="fas fa-check-circle"></i> Verify</button>
                </form>
                
                <div id="reader" style="width: 300px; display: none;"></div>
                <button onclick="startScanner()" class="btn-admin-action btn-admin-secondary"><i class="fas fa-qrcode"></i> Start QR Scanner</button>
            </div>

            <?php if ($receipt_number): ?>
                <?php if ($payment): ?>
                    <div class="card success-card" style="margin-top: 20px; border-left: 5px solid green;">
                        <h3><i class="fas fa-check-circle" style="color: green;"></i> Valid Receipt</h3>
                        <div class="details-grid">
                            <p><strong>Student:</strong> <?php echo htmlspecialchars($payment['full_name']); ?> (<?php echo htmlspecialchars($payment['index_number']); ?>)</p>
                            <p><strong>Department:</strong> <?php echo htmlspecialchars($payment['department']); ?></p>
                            <p><strong>Amount:</strong> GHS <?php echo number_format($payment['amount'], 2); ?></p>
                            <p><strong>Date:</strong> <?php echo $payment['payment_date']; ?></p>
                            <p><strong>Purpose:</strong> <?php echo htmlspecialchars($payment['semester'] . ' ' . $payment['academic_year']); ?></p>
                        </div>
                    </div>

                    <div class="card" style="margin-top: 20px;">
                        <h3>Payment History for Student</h3>
                        <table class="table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Receipt #</th>
                                    <th>Amount</th>
                                    <th>Term</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $record): ?>
                                    <tr style="<?php echo $record['receipt_number'] === $receipt_number ? 'background: #e8f5e9;' : ''; ?>">
                                        <td><?php echo $record['payment_date']; ?></td>
                                        <td><?php echo $record['receipt_number']; ?></td>
                                        <td>GHS <?php echo number_format($record['amount'], 2); ?></td>
                                        <td><?php echo $record['semester'] . ' ' . $record['academic_year']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="card error-card" style="margin-top: 20px; border-left: 5px solid red;">
                        <h3><i class="fas fa-times-circle" style="color: red;"></i> Invalid Receipt</h3>
                        <p>No payment record found for receipt number: <strong><?php echo htmlspecialchars($receipt_number); ?></strong></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function startScanner() {
            const html5QrCode = new Html5Qrcode("reader");
            document.getElementById('reader').style.display = 'block';
            html5QrCode.start(
                { facingMode: "environment" }, 
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText, decodedResult) => {
                    // Handle on success condition with the decoded message.
                    console.log(`Scan result: ${decodedText}`);
                    // Extract receipt number from URL if it's a URL
                    let receipt = decodedText;
                    if (decodedText.includes('receipt=')) {
                        const url = new URL(decodedText);
                        receipt = url.searchParams.get('receipt');
                    }
                    window.location.href = `?receipt=${receipt}`;
                    html5QrCode.stop();
                },
                (errorMessage) => {
                    // parse error, ignore it.
                })
            .catch((err) => {
                // Start failed, handle it.
            });
        }
    </script>
</body>
</html>
