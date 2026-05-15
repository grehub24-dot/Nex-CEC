<?php
require_once 'includes/db.php';

// Enforce access control
requireAccess('staff');

$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

// Handle Add Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_staff') {
    validate_request_csrf();
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
    $bank_name = sanitize($_POST['bank_name'] ?? '');
    $account_number = sanitize($_POST['account_number'] ?? '');

    // Validate Ghana phone number (MoMo/Ghana Pay compatible)
    // Accepts: 024, 025, 026, 027, 054, 055, 056, 057, 050, 059 (MTN), 020, 050 (Vodafone/Telecel), 026, 056 (AirtelTigo)
    $phone_digits = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone_digits) === 10 && in_array(substr($phone_digits, 0, 3), [
        '024','025','054','055','056','059', // MTN
        '020','050',                          // Telecel (formerly Vodafone)
        '026','057',                          // AirtelTigo
        '027','053'                           // AT/Glo
    ])) {
        $network = '';
        if (in_array(substr($phone_digits, 0, 3), ['024','025','054','055','059'])) $network = 'MTN';
        elseif (in_array(substr($phone_digits, 0, 3), ['020','050'])) $network = 'Telecel';
        elseif (in_array(substr($phone_digits, 0, 3), ['026','057'])) $network = 'AirtelTigo';
        elseif (in_array(substr($phone_digits, 0, 3), ['027','053'])) $network = 'AT/Glo';
        else $network = 'Unknown';
    } else {
        $error = "Invalid phone number. Must be a valid Ghana mobile number (e.g. 024XXXXXXX, 050XXXXXXX, 054XXXXXXX).";
        $network = null;
    }

    if (!isset($error)) {
        // Auto-generate staff ID (bridge doesn't support COUNT(*) — count in PHP)
        $allStaffForCount = $pdo->query("SELECT id FROM staff");
        $allStaffForCount = $allStaffForCount ? $allStaffForCount->fetchAll() : [];
        $count = count($allStaffForCount);
        $staff_id = 'NXC-STF-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("SELECT id FROM staff WHERE staff_id = ?");
        $stmt->execute([$staff_id]);
        if ($stmt->fetch()) {
            $error = "Staff ID $staff_id already exists.";
        } else {
            $pdo->beginTransaction();
            try {
                $auto_password = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);
                $password_hash = password_hash($auto_password, PASSWORD_DEFAULT);
                
                // Determine role: teaching positions get 'teacher', others get 'staff'
                $teaching_keywords = ['teacher', 'instructor', 'tutor', 'lecturer', 'facilitator', 'coach'];
                $position_lower = strtolower($position);
                $is_teaching = false;
                foreach ($teaching_keywords as $kw) {
                    if (strpos($position_lower, $kw) !== false) {
                        $is_teaching = true;
                        break;
                    }
                }
                $role = $is_teaching ? 'teacher' : 'staff';
                $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
                $stmt->execute([$email, $password_hash, $role]);
                $user_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO staff (user_id, staff_id, full_name, position, department, qualification, phone, email, gender, date_of_birth, address, hire_date, bank_name, account_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $staff_id, $full_name, $position, $department, $qualification, $phone, $email, $gender, $date_of_birth, $address, $hire_date, $bank_name, $account_number]);
                
                $pdo->commit();
                $message = "Staff member added successfully! ID: $staff_id | Temp password: $auto_password";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error: " . $e->getMessage();
            }
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
// Fetch all staff (bridge only handles simple WHERE col = ?)
// Complex search filtering is done in PHP.
$all_staff = $pdo->query("SELECT * FROM staff")->fetchAll();

// Apply search filter in PHP (matches full_name, staff_id, and position)
if ($search !== '') {
    $staff_list = array_filter($all_staff, function($s) use ($search) {
        return stripos($s['full_name'] ?? '', $search) !== false
            || stripos($s['staff_id'] ?? '', $search) !== false
            || stripos($s['position'] ?? '', $search) !== false;
    });
} else {
    $staff_list = $all_staff;
}

// Sort by created_at DESC and apply pagination in PHP
usort($staff_list, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
$total_rows = count($staff_list);
$staff_list = array_slice($staff_list, $offset, $limit);
$total_pages = $total_rows > 0 ? (int)ceil($total_rows / $limit) : 1;
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
            <?php echo renderSidebar('staff', $school_name); ?>

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
                // Clean up duplicate staff rows (keep oldest per staff_id), run once per page load for safety
                try {
                    $dupStmt = $pdo->query("SELECT id, staff_id FROM staff ORDER BY staff_id ASC, id ASC");
                    $rows = $dupStmt ? $dupStmt->fetchAll() : [];
                    $seen = [];
                    $del = $pdo->prepare("DELETE FROM staff WHERE id = ?");
                    foreach ($rows as $r) {
                        $sid = $r['staff_id'] ?? null;
                        $id = $r['id'] ?? null;
                        if (!$sid || !$id) continue;
                        if (isset($seen[$sid])) {
                            $del->execute([(int)$id]);
                        } else {
                            $seen[$sid] = true;
                        }
                    }
                } catch (Exception $e) {
                    // ignore cleanup errors
                }
                // Bridge doesn't support COUNT(*) — fetch all rows, count in PHP
                $allStaffStats = $pdo->query("SELECT * FROM staff")->fetchAll();
                if (!is_array($allStaffStats)) $allStaffStats = [];
                $total_staff = count($allStaffStats);
                $active_staff = count(array_filter($allStaffStats, fn($s) => ($s['status'] ?? '') === 'active'));
                $non_teachers = count(array_filter($allStaffStats, function($s) {
                    $pos = strtolower($s['position'] ?? '');
                    return strpos($pos, 'teacher') === false && strpos($pos, 'instructor') === false && strpos($pos, 'head') === false;
                }));
                $teachers = $total_staff - $non_teachers;
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
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-cog" style="color: #8e44ad;"></i></div>
                    <div class="stat-details"><h3><?php echo $non_teachers; ?></h3><p>Non-Teaching Staff</p></div>
                </div>
            </div>

            <!-- Add Staff Modal -->
            <div id="staffModal" class="modal">
                <div class="modal-content">
                    <span class="close-btn">&times;</span>
                    <h3>Add New Staff Member</h3>
                    <form action="staff.php" method="POST" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top: 15px;">
                        <input type="hidden" name="action" value="add_staff">
                        <?php csrf_field(); ?>
                        
                        <div>
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control" required placeholder="e.g. Mr. Kwame Asante">
                        </div>
                        <div>
                            <label>Staff ID</label>
                            <input type="text" id="autoStaffId" class="form-control" readonly placeholder="Auto-generated" style="background: #f0f2f5; cursor: not-allowed;">
                            <small style="color: #666; font-size: 0.8rem;">Auto-generated on submit</small>
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
                            <label>Phone <span style="color:red;">(MoMo/Ghana Pay)</span></label>
                            <input type="text" name="phone" id="staffPhone" class="form-control" required placeholder="e.g. 0241234567" maxlength="10" pattern="[0-9]{10}" oninput="validatePhone(this)">
                            <div id="phoneBadge" style="margin-top:5px; display:none;">
                                <span id="phoneBadgeSpan" style="display:inline-block; padding:3px 10px; border-radius:12px; font-size:0.75rem; font-weight:bold;"></span>
                            </div>
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
                            <h4><i class="fas fa-piggy-bank"></i> Bank Details (for Payroll)</h4>
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
        // Force close any stuck modal on load
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById("staffModal");
            if (modal) modal.style.display = "none";
        });

        const modal = document.getElementById("staffModal");
        const btn = document.getElementById("openModalBtn");
        const span = document.getElementsByClassName("close-btn")[0];
        
        if (btn) btn.onclick = function() { modal.style.display = "block"; }
        if (span) span.onclick = function() { modal.style.display = "none"; }
        if (modal) {
            window.onclick = function(event) { if (event.target == modal) { modal.style.display = "none"; } }
        }
        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal && modal.style.display === 'block') {
                modal.style.display = 'none';
            }
        });

        // Auto-generate Staff ID preview (uses $total_staff computed from PHP)
        function updateStaffIdPreview() {
            const count = <?php echo (int)$total_staff; ?>;
            const el = document.getElementById('autoStaffId');
            if (el) el.value = 'NXC-STF-' + String(count + 1).padStart(4, '0');
        }
        updateStaffIdPreview();

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
    </script>
</body>
</html>
