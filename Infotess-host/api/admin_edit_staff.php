<?php
require_once 'includes/db.php';

// Enforce access control
requireAccess('edit_staff');

$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('admin_staff.php');
}

$staff_id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
$stmt->execute([$staff_id]);
$staff = $stmt->fetch();

if (!$staff) {
    redirect('admin_staff.php');
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_staff') {
    $full_name = sanitize($_POST['full_name']);
    $position = sanitize($_POST['position']);
    $department = sanitize($_POST['department'] ?? '');
    $qualification = sanitize($_POST['qualification'] ?? '');
    $phone = sanitize($_POST['phone']);
    $email = sanitize($_POST['email']);
    $gender = sanitize($_POST['gender'] ?? '');
    $date_of_birth = sanitize($_POST['date_of_birth'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $hire_date = sanitize($_POST['hire_date']);
    $status = sanitize($_POST['status']);
    $bank_name = sanitize($_POST['bank_name'] ?? '');
    $account_number = sanitize($_POST['account_number'] ?? '');

    try {
        $stmt = $pdo->prepare("UPDATE staff SET full_name=?, position=?, department=?, qualification=?, phone=?, email=?, gender=?, date_of_birth=?, address=?, hire_date=?, status=?, bank_name=?, account_number=? WHERE id=?");
        $stmt->execute([$full_name, $position, $department, $qualification, $phone, $email, $gender, $date_of_birth, $address, $hire_date, $status, $bank_name, $account_number, $staff_id]);
        $message = "Staff member updated successfully.";
        
        // Refresh data
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
        $stmt->execute([$staff_id]);
        $staff = $stmt->fetch();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Staff — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .section-divider { grid-column: span 2; border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px; }
        .section-divider h4 { font-size: 15px; color: #1a5276; margin: 0 0 10px 0; }
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
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="staff.php"><i class="fas fa-chalkboard-teacher"></i> Staff</a></li>
                <li><a href="payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="fees.php"><i class="fas fa-list-alt"></i> Fee Structure</a></li>
                <li><a href="payroll.php"><i class="fas fa-file-invoice-dollar"></i> Payroll</a></li>
                <li><a href="salary.php"><i class="fas fa-money-check-alt"></i> Salary Structures</a></li>
                <li><a href="grades.php"><i class="fas fa-clipboard-list"></i> SBA / Grades</a></li>
                <li><a href="attendance.php"><i class="fas fa-user-check"></i> Attendance</a></li>
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
                <h2>Edit Staff Member</h2>
                <a href="staff.php" class="btn-login" style="background: #6c757d;"><i class="fas fa-arrow-left"></i> Back to Staff</a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card" style="max-width: 800px;">
                <div class="card-content">
                    <h3><?php echo htmlspecialchars($staff['full_name']); ?> (<?php echo htmlspecialchars($staff['staff_id']); ?>)</h3>
                    <form action="edit_staff.php?id=<?php echo $staff_id; ?>" method="POST" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top: 20px;">
                        <input type="hidden" name="action" value="update_staff">
                        
                        <div>
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($staff['full_name']); ?>">
                        </div>
                        <div>
                            <label>Staff ID</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($staff['staff_id']); ?>" disabled>
                            <small style="color: #666;">Staff ID cannot be changed</small>
                        </div>
                        <div>
                            <label>Position</label>
                            <select name="position" class="form-control" required>
                                <option value="">-- Select --</option>
                                <?php
                                $positions = ['Head Teacher','Assistant Head Teacher','Class Teacher','Subject Teacher','Teaching Assistant','School Administrator','Finance Officer','Secretary','Cleaner','Security','Cook','Other'];
                                foreach ($positions as $pos):
                                ?>
                                    <option value="<?php echo $pos; ?>" <?php echo $staff['position'] === $pos ? 'selected' : ''; ?>><?php echo $pos; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Department</label>
                            <input type="text" name="department" class="form-control" value="<?php echo htmlspecialchars($staff['department'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Qualification</label>
                            <input type="text" name="qualification" class="form-control" value="<?php echo htmlspecialchars($staff['qualification'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Gender</label>
                            <select name="gender" class="form-control">
                                <option value="">-- Select --</option>
                                <option value="Male" <?php echo $staff['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $staff['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div>
                            <label>Phone <span style="color:red;">(MoMo/Ghana Pay)</span></label>
                            <input type="text" name="phone" id="editStaffPhone" class="form-control" required placeholder="e.g. 0241234567" maxlength="10" pattern="[0-9]{10}" value="<?php echo htmlspecialchars($staff['phone'] ?? ''); ?>" oninput="validatePhone(this)">
                            <div id="phoneBadge" style="margin-top:5px; display:none;">
                                <span id="phoneBadgeSpan" style="display:inline-block; padding:3px 10px; border-radius:12px; font-size:0.75rem; font-weight:bold;"></span>
                            </div>
                        </div>
                        <div>
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($staff['email'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($staff['date_of_birth'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Hire Date</label>
                            <input type="date" name="hire_date" class="form-control" required value="<?php echo htmlspecialchars($staff['hire_date'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="active" <?php echo ($staff['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($staff['status'] ?? 'active') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="on_leave" <?php echo ($staff['status'] ?? 'active') === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                            </select>
                        </div>
                        <div>
                            <label>Address</label>
                            <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($staff['address'] ?? ''); ?>">
                        </div>

                        <div class="section-divider">
                            <h4><i class="fas fa-university"></i> Bank Details</h4>
                        </div>

                        <div>
                            <label>Bank Name</label>
                            <input type="text" name="bank_name" class="form-control" value="<?php echo htmlspecialchars($staff['bank_name'] ?? ''); ?>">
                        </div>
                        <div>
                            <label>Account Number</label>
                            <input type="text" name="account_number" class="form-control" value="<?php echo htmlspecialchars($staff['account_number'] ?? ''); ?>">
                        </div>

                        <div style="grid-column: span 2; margin-top: 10px;">
                            <button type="submit" class="btn-submit" style="width:100%;">Update Staff Member</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script>
        // Ghana MoMo/Ghana Pay phone validation
        function validatePhone(input) {
            let phone = input.value.replace(/[^0-9]/g, '');
            input.value = phone;
            const badge = document.getElementById('phoneBadge');
            const badgeSpan = document.getElementById('phoneBadgeSpan');
            
            if (phone.length !== 10) {
                badge.style.display = 'none';
                return;
            }
            
            const prefix = phone.substring(0, 3);
            const networks = {
                '024': { name: 'MTN MoMo', color: '#ffcc00', bg: '#fff8e1', text: '#333' },
                '025': { name: 'MTN MoMo', color: '#ffcc00', bg: '#fff8e1', text: '#333' },
                '054': { name: 'MTN MoMo', color: '#ffcc00', bg: '#fff8e1', text: '#333' },
                '055': { name: 'MTN MoMo', color: '#ffcc00', bg: '#fff8e1', text: '#333' },
                '059': { name: 'MTN MoMo', color: '#ffcc00', bg: '#fff8e1', text: '#333' },
                '056': { name: 'MTN MoMo', color: '#ffcc00', bg: '#fff8e1', text: '#333' },
                '020': { name: 'Telecel Cash', color: '#e4002b', bg: '#fde8ec', text: '#fff' },
                '050': { name: 'Telecel Cash', color: '#e4002b', bg: '#fde8ec', text: '#fff' },
                '026': { name: 'AirtelTigo Money', color: '#0066cc', bg: '#e6f0ff', text: '#fff' },
                '057': { name: 'AirtelTigo Money', color: '#0066cc', bg: '#e6f0ff', text: '#fff' },
                '027': { name: 'Glo/Ghana Pay', color: '#ff6600', bg: '#fff3e0', text: '#333' },
                '053': { name: 'Glo/Ghana Pay', color: '#ff6600', bg: '#fff3e0', text: '#333' }
            };
            
            if (networks[prefix]) {
                const net = networks[prefix];
                badge.style.display = 'block';
                badgeSpan.textContent = '✅ ' + net.name;
                badgeSpan.style.color = net.text;
                badgeSpan.style.background = net.bg;
                badgeSpan.style.border = '2px solid ' + net.color;
                input.style.borderColor = net.color;
            } else {
                badge.style.display = 'block';
                badgeSpan.textContent = '❌ Invalid network prefix';
                badgeSpan.style.color = '#fff';
                badgeSpan.style.background = '#f8d7da';
                badgeSpan.style.border = '2px solid #e74c3c';
                input.style.borderColor = '#e74c3c';
            }
        }
        // Trigger on load for existing value
        const existingPhone = document.getElementById('editStaffPhone');
        if (existingPhone && existingPhone.value) validatePhone(existingPhone);
    </script>
</body>
</html>
