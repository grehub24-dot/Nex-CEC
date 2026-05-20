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
            <?php echo renderSidebar('edit_staff', $school_name); ?>

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
                        <?php csrf_field(); ?>
                        
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

                        <!-- Uploaded Documents Section -->
                        <div style="grid-column: span 2; border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px;">
                            <h4 style="font-size:15px; color:#1a5276; margin:0 0 10px 0;"><i class="fas fa-file-alt"></i> Uploaded Documents</h4>
                            <?php
                            $cvPath = $staff['cv_path'] ?? '';
                            $docsJson = $staff['documents'] ?? '[]';
                            $docs = json_decode($docsJson, true);
                            $hasDocs = !empty($cvPath) || (is_array($docs) && !empty($docs));
                            ?>
                            <?php if ($hasDocs): ?>
                                <div style="display:flex;flex-direction:column;gap:8px;">
                                    <?php if (!empty($cvPath)): ?>
                                    <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:#f8f9fa;border-radius:8px;border:1px solid #e9ecef;">
                                        <span style="font-size:24px;color:#e74c3c;"><i class="fas fa-file-pdf"></i></span>
                                        <div style="flex:1;">
                                            <div style="font-size:12px;color:#888;">Curriculum Vitae (CV)</div>
                                            <a href="<?php echo htmlspecialchars($cvPath); ?>" target="_blank" rel="noopener" style="font-weight:600;color:#1a5276;text-decoration:none;font-size:13px;">
                                                <i class="fas fa-external-link-alt"></i> View / Download CV
                                            </a>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (is_array($docs) && !empty($docs)): ?>
                                    <div>
                                        <div style="font-size:12px;color:#888;margin-bottom:6px;font-weight:500;">Additional Documents (<?php echo count($docs); ?>)</div>
                                        <div style="display:flex;flex-direction:column;gap:4px;">
                                            <?php foreach ($docs as $docUrl): ?>
                                            <a href="<?php echo htmlspecialchars($docUrl); ?>" target="_blank" rel="noopener" style="display:flex;align-items:center;gap:10px;padding:7px 12px;background:#f8f9fa;border-radius:6px;border:1px solid #e9ecef;color:#333;text-decoration:none;font-size:12px;transition:all 0.2s;" onmouseover="this.style.borderColor='#1a5276';this.style.background='#eef2f7';" onmouseout="this.style.borderColor='#e9ecef';this.style.background='#f8f9fa';">
                                                <i class="fas fa-file" style="color:#1a5276;font-size:14px;"></i>
                                                <span style="flex:1;"><?php echo htmlspecialchars(basename($docUrl)); ?></span>
                                                <i class="fas fa-external-link-alt" style="color:#888;font-size:10px;"></i>
                                            </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <p style="font-size:13px;color:#999;padding:8px 0;">
                                    <i class="fas fa-info-circle"></i> No documents uploaded yet. Staff can upload documents during self-registration.
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="section-divider">
                            <h4><i class="fas fa-piggy-bank"></i> Bank Details</h4>
                        </div>
                            <h4><i class="fas fa-piggy-bank"></i> Bank Details</h4>
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
