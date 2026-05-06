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

// Handle Add Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_staff') {
    $full_name = sanitize($_POST['full_name']);
    $staff_id = sanitize($_POST['staff_id']);
    $position = sanitize($_POST['position']);
    $department = sanitize($_POST['department'] ?? '');
    $qualification = sanitize($_POST['qualification'] ?? '');
    $phone = sanitize($_POST['phone']);
    $email = sanitize($_POST['email']);
    $gender = sanitize($_POST['gender'] ?? '');
    $date_of_birth = sanitize($_POST['date_of_birth'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $hire_date = sanitize($_POST['hire_date']);
    $bank_name = sanitize($_POST['bank_name'] ?? '');
    $account_number = sanitize($_POST['account_number'] ?? '');

    $stmt = $pdo->prepare("SELECT id FROM staff WHERE staff_id = ?");
    $stmt->execute([$staff_id]);
    if ($stmt->fetch()) {
        $error = "Staff ID $staff_id already exists.";
    } else {
        $pdo->beginTransaction();
        try {
            $auto_password = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);
            $password_hash = password_hash($auto_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'staff')");
            $stmt->execute([$email, $password_hash]);
            $user_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, gender, date_of_birth, address, hire_date, bank_name, account_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $staff_id, $full_name, $position, $department, $qualification, $phone, $email, $gender, $date_of_birth, $address, $hire_date, $bank_name, $account_number]);
            
            $pdo->commit();
            $message = "Staff member added successfully! Temporary password: $auto_password";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Handle Delete Staff
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $staff_id = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT user_id FROM staff WHERE id = ?");
        $stmt->execute([$staff_id]);
        $staff = $stmt->fetch();
        $pdo->prepare("DELETE FROM salary_structures WHERE staff_id = ?")->execute([$staff_id]);
        $pdo->prepare("DELETE FROM deductions WHERE staff_id = ?")->execute([$staff_id]);
        $pdo->prepare("DELETE FROM payroll WHERE staff_id = ?")->execute([$staff_id]);
        $pdo->prepare("DELETE FROM staff_attendance WHERE staff_id = ?")->execute([$staff_id]);
        $pdo->prepare("DELETE FROM staff WHERE id = ?")->execute([$staff_id]);
        if ($staff && $staff['user_id']) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$staff['user_id']]);
        }
        $pdo->commit();
        $message = "Staff member deleted successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error deleting staff: " . $e->getMessage();
    }
    header("Location: staff.php?msg=" . urlencode($message));
    exit;
}

// Pagination
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$query = "SELECT * FROM staff";
$params = [];
if ($search) {
    $query .= " WHERE full_name LIKE ? OR staff_id LIKE ? OR position LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}
$query .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$staff_list = $stmt->fetchAll();

$total_query = "SELECT COUNT(*) FROM staff";
$total_params = [];
if ($search) {
    $total_query .= " WHERE full_name LIKE ? OR staff_id LIKE ? OR position LIKE ?";
    $total_params = ["%$search%", "%$search%", "%$search%"];
}
$total_stmt = $pdo->prepare($total_query);
$total_stmt->execute($total_params);
$total_rows = (int)$total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Management — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 700px; border-radius: 8px; position: relative; max-height: 90vh; overflow-y: auto; }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover { color: black; }
        .section-divider { grid-column: span 2; border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px; }
        .section-divider h4 { font-size: 15px; color: #1a5276; margin: 0 0 10px 0; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
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
                <h2>Staff Management</h2>
                <button id="openModalBtn" class="btn-primary"><i class="fas fa-plus"></i> Add Staff Member</button>
            </div>

            <?php if ($message || isset($_GET['msg'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message ?: $_GET['msg']); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Staff Stats -->
            <div class="stat-cards" style="margin-bottom: 30px;">
                <?php
                $total_staff = (int)$pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
                $active_staff = (int)$pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'active'")->fetchColumn();
                $teachers = (int)$pdo->query("SELECT COUNT(*) FROM staff WHERE position LIKE '%Teacher%' OR position LIKE '%Instructor%'")->fetchColumn();
                ?>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-details"><h3><?php echo $total_staff; ?></h3><p>Total Staff</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-check" style="color: green;"></i></div>
                    <div class="stat-details"><h3><?php echo $active_staff; ?></h3><p>Active Staff</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chalkboard" style="color: #f39c12;"></i></div>
                    <div class="stat-details"><h3><?php echo $teachers; ?></h3><p>Teachers</p></div>
                </div>
            </div>

            <!-- Add Staff Modal -->
            <div id="staffModal" class="modal">
                <div class="modal-content">
                    <span class="close-btn">&times;</span>
                    <h3>Add New Staff Member</h3>
                    <form action="staff.php" method="POST" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top: 15px;">
                        <input type="hidden" name="action" value="add_staff">
                        
                        <div>
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control" required placeholder="e.g. Mr. Kwame Asante">
                        </div>
                        <div>
                            <label>Staff ID</label>
                            <input type="text" name="staff_id" class="form-control" required placeholder="e.g. NXC-STF-001">
                        </div>
                        <div>
                            <label>Position</label>
                            <select name="position" class="form-control" required>
                                <option value="">-- Select Position --</option>
                                <option value="Head Teacher">Head Teacher</option>
                                <option value="Assistant Head Teacher">Assistant Head Teacher</option>
                                <option value="Class Teacher">Class Teacher</option>
                                <option value="Subject Teacher">Subject Teacher</option>
                                <option value="Teaching Assistant">Teaching Assistant</option>
                                <option value="School Administrator">School Administrator</option>
                                <option value="Finance Officer">Finance Officer</option>
                                <option value="Secretary">Secretary</option>
                                <option value="Cleaner">Cleaner</option>
                                <option value="Security">Security</option>
                                <option value="Cook">Cook</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label>Department</label>
                            <input type="text" name="department" class="form-control" placeholder="e.g. Early Childhood, Primary, JHS">
                        </div>
                        <div>
                            <label>Qualification</label>
                            <input type="text" name="qualification" class="form-control" placeholder="e.g. B.Ed, Diploma, Certificate">
                        </div>
                        <div>
                            <label>Gender</label>
                            <select name="gender" class="form-control">
                                <option value="">-- Select --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div>
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" required placeholder="e.g. 0241234567">
                        </div>
                        <div>
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required placeholder="staff@email.com">
                        </div>
                        <div>
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control">
                        </div>
                        <div>
                            <label>Hire Date</label>
                            <input type="date" name="hire_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div>
                            <label>Address</label>
                            <input type="text" name="address" class="form-control" placeholder="Residential address">
                        </div>

                        <div class="section-divider">
                            <h4><i class="fas fa-university"></i> Bank Details (for Payroll)</h4>
                        </div>

                        <div>
                            <label>Bank Name</label>
                            <input type="text" name="bank_name" class="form-control" placeholder="e.g. GCB Bank">
                        </div>
                        <div>
                            <label>Account Number</label>
                            <input type="text" name="account_number" class="form-control" placeholder="e.g. 1234567890">
                        </div>

                        <div style="grid-column: span 2; margin-top: 10px;">
                            <button type="submit" class="btn-submit" style="width:100%;">Add Staff Member</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Staff List -->
            <div class="section">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h3>All Staff Members</h3>
                    <form action="staff.php" method="GET" style="display:flex; gap:10px;">
                        <input type="text" name="search" placeholder="Search name, ID, or position..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-login"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Staff ID</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($staff_list)): ?>
                                <tr><td colspan="7" style="text-align:center;">No staff members found. Add your first staff member above.</td></tr>
                            <?php else: ?>
                                <?php foreach ($staff_list as $staff): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($staff['staff_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($staff['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['position']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['department'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($staff['phone'] ?? '-'); ?></td>
                                    <td>
                                        <span style="color: <?php echo $staff['status'] === 'active' ? 'green' : 'red'; ?>; font-weight: bold;">
                                            <?php echo ucfirst($staff['status'] ?? 'active'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit_staff.php?id=<?php echo $staff['id']; ?>" class="btn-login" style="background:#f0ad4e; padding: 5px 10px; font-size: 0.8rem;">Edit</a>
                                        <a href="staff.php?delete=<?php echo $staff['id']; ?>" class="btn-login" style="background:#e74c3c; padding: 5px 10px; font-size: 0.8rem;" onclick="return confirm('Are you sure you want to delete this staff member?');">Delete</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
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
        const modal = document.getElementById("staffModal");
        const btn = document.getElementById("openModalBtn");
        const span = document.getElementsByClassName("close-btn")[0];
        btn.onclick = function() { modal.style.display = "block"; }
        span.onclick = function() { modal.style.display = "none"; }
        window.onclick = function(event) { if (event.target == modal) { modal.style.display = "none"; } }
    </script>
</body>
</html>
