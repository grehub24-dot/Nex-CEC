<?php
require_once 'includes/db.php';

// Ensure Admin Access
// Enforce access control
requireAccess('settings');

$message = '';
$error = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    validate_request_csrf();
    try {
        $pdo->beginTransaction();
        
        $settings_keys = [
            'school_name', 'school_motto', 'school_address', 'school_email', 'school_phone',
            'current_academic_year', 'current_term', 'annual_dues_amount',
            'payment_modes', 'fee_types',
            'admission_fee', 'prospectus_fee', 'enrollment_form_fee',
            'staff_child_discount', 'sibling_discount_amount'
        ];

        // Bridge doesn't support ON CONFLICT — use separate update/insert per key
        foreach ($settings_keys as $key) {
            if (isset($_POST[$key])) {
                $val = sanitize($_POST[$key]);
                $existing = $pdo->prepare("SELECT setting_key FROM system_settings WHERE setting_key = ?");
                $existing->execute([$key]);
                if ($existing->fetch()) {
                    $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->execute([$val, $key]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
                    $stmt->execute([$key, $val]);
                }
            }
        }

        $pdo->commit();
        $message = "System settings updated successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Handle Logo Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_logo') {
    validate_request_csrf();
    if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['school_logo'];
        // Validate file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            $error = "File too large. Maximum size is 2MB.";
        } else {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
            @finfo_close($finfo);
            if (!in_array($mime, $allowed_types)) {
                $error = "Invalid file type. Only JPG, PNG, GIF, WebP, and SVG images are allowed.";
            } else {
                try {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
                    $file_name = 'school_logo_' . time() . '_' . uniqid() . '.' . $ext;
                    $fileData = file_get_contents($file['tmp_name']);
                    
                    global $supabase;
                    if (!$supabase || !($supabase instanceof SupabaseClient)) {
                        $error = "Upload service not available.";
                    } else {
                        try {
                            $supabase->uploadFile('logos', $file_name, $fileData, $mime);
                        } catch (Exception $e) {
                            if (strpos($e->getMessage(), 'Bucket not found') !== false) {
                                $supabase->createBucket('logos');
                                $supabase->uploadFile('logos', $file_name, $fileData, $mime);
                            } else {
                                throw $e;
                            }
                        }
                        $logoUrl = $supabase->getPublicUrl('logos', $file_name);
                        
                        // Save to system_settings
                        $existing = $pdo->prepare("SELECT setting_key FROM system_settings WHERE setting_key = ?");
                        $existing->execute(['school_logo_url']);
                        if ($existing->fetch()) {
                            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                            $stmt->execute([$logoUrl, 'school_logo_url']);
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
                            $stmt->execute(['school_logo_url', $logoUrl]);
                        }
                        
                        $message = "School logo updated successfully!";
                    }
                } catch (Exception $e) {
                    $error = "Upload failed: " . $e->getMessage();
                }
            }
        }
    } else {
        $error = "No file selected or upload error.";
    }
}

// Handle Logo Removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_logo') {
    validate_request_csrf();
    try {
        $pdo->prepare("DELETE FROM system_settings WHERE setting_key = ?")->execute(['school_logo_url']);
        $message = "School logo removed. Default logo will be used.";
    } catch (Exception $e) {
        $error = "Error removing logo: " . $e->getMessage();
    }
}

// Fetch Current Settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Default values if not set
$school_name = $settings['school_name'] ?? 'Nex CEC';
$defaults = [
    'school_name' => 'Nex CEC',
    'school_motto' => 'Excellence in Education',
    'school_address' => 'School Address, City, Ghana',
    'school_email' => 'info@school.edu.gh',
    'school_phone' => '+233 XX XXX XXXX',
    'current_academic_year' => date('Y') . '/' . (date('Y') + 1),
    'current_term' => '1',
    'annual_dues_amount' => '500.00',
    'fee_types' => 'Tuition,PTA Levy,Sports & Culture,ICT,Examination,Development,Feeding,Transport,Uniform,Books & Materials',
    'payment_modes' => 'Cash,Mobile Money,Bank Transfer',
    'admission_fee' => '150.00',
    'prospectus_fee' => '50.00',
    'enrollment_form_fee' => '20.00',
    'staff_child_discount' => '150.00',
    'sibling_discount_amount' => '150.00'
];
$settings = array_merge($defaults, $settings);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings — <?php echo htmlspecialchars($school_name); ?> Admin</title>
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
            <?php echo renderSidebar('settings', $school_name); ?>

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
                                <span class="setting-label">School Name</span>
                                <span class="setting-value"><?php echo htmlspecialchars($settings['school_name'] ?? 'Nex CEC'); ?></span>
                            </div>
                            <div class="setting-item">
                                <span class="setting-label">School Motto</span>
                                <span class="setting-value"><?php echo htmlspecialchars($settings['school_motto'] ?? 'Excellence in Education'); ?></span>
                            </div>
                            <div class="setting-item">
                                <span class="setting-label">Current Academic Year</span>
                                <span class="setting-value"><?php echo htmlspecialchars($settings['current_academic_year']); ?></span>
                            </div>
                            <div class="setting-item">
                                <span class="setting-label">Current Term</span>
                                <span class="setting-value">Term <?php echo htmlspecialchars($settings['current_term'] ?? '1'); ?></span>
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
                                <span class="setting-label">Fee Types</span>
                                <span class="setting-value"><?php echo htmlspecialchars($settings['fee_types'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="setting-item">
                                <span class="setting-label">Contact Email</span>
                                <span class="setting-value"><?php echo htmlspecialchars($settings['school_email'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="setting-item" style="border-left-color: #e74c3c;">
                                <span class="setting-label">Admission Fee (Enrollment)</span>
                                <span class="setting-value">GHS <?php echo htmlspecialchars($settings['admission_fee'] ?? '150.00'); ?></span>
                            </div>
                            <div class="setting-item" style="border-left-color: #e74c3c;">
                                <span class="setting-label">Prospectus Fee (Enrollment)</span>
                                <span class="setting-value">GHS <?php echo htmlspecialchars($settings['prospectus_fee'] ?? '50.00'); ?></span>
                            </div>
                            <div class="setting-item" style="border-left-color: #e74c3c;">
                                <span class="setting-label">Enrollment Form Fee</span>
                                <span class="setting-value">GHS <?php echo htmlspecialchars($settings['enrollment_form_fee'] ?? '20.00'); ?></span>
                            </div>
                            <div class="setting-item" style="border-left-color: #8e44ad;">
                                <span class="setting-label">Staff Child Discount</span>
                                <span class="setting-value">GHS <?php echo htmlspecialchars($settings['staff_child_discount'] ?? '150.00'); ?></span>
                            </div>
                            <div class="setting-item" style="border-left-color: #8e44ad;">
                                <span class="setting-label">Sibling Discount (3rd+ Child)</span>
                                <span class="setting-value">GHS <?php echo htmlspecialchars($settings['sibling_discount_amount'] ?? '150.00'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- School Logo Section -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3>School Logo</h3>
                    </div>
                    <div class="card-content">
                        <div style="display: flex; align-items: center; gap: 30px; flex-wrap: wrap;">
                            <div style="text-align: center;">
                                <p style="margin-bottom: 10px; color: #555; font-weight: 500;">Current Logo</p>
                                <img src="<?php echo htmlspecialchars($settings['school_logo_url'] ?? '../images/aamusted.jpg'); ?>" 
                                     alt="School Logo" 
                                     style="max-width: 180px; max-height: 120px; border: 2px solid #ddd; border-radius: 8px; padding: 8px; background: #fff;"
                                     onerror="this.onerror=null;this.src='../images/aamusted.jpg'">
                            </div>
                            <div>
                                <p style="color: #666; margin-bottom: 10px; font-size: 0.9em;">
                                    <i class="fas fa-info-circle"></i> Upload a new logo (JPG, PNG, GIF, WebP, SVG — max 2MB).
                                    The logo will be used across the system (login page, header, PDF receipts).
                                </p>
                                <form action="settings.php" method="POST" enctype="multipart/form-data" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <input type="hidden" name="action" value="upload_logo">
                                    <?php csrf_field(); ?>
                                    <input type="file" name="school_logo" accept="image/*" required style="padding: 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.9em;">
                                    <button type="submit" class="btn-primary" style="padding: 8px 20px;"><i class="fas fa-upload"></i> Upload Logo</button>
                                </form>
                                <?php if (!empty($settings['school_logo_url'])): ?>
                                    <form action="settings.php" method="POST" style="display: inline-block; margin-top: 8px;" onsubmit="return confirm('Remove the custom school logo and revert to default?')">
                                        <input type="hidden" name="action" value="remove_logo">
                                        <?php csrf_field(); ?>
                                        <button type="submit" class="btn-danger" style="padding: 5px 15px; font-size: 0.85em;"><i class="fas fa-trash"></i> Remove Logo</button>
                                    </form>
                                <?php endif; ?>
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
                <?php csrf_field(); ?>
                
                <h4 style="margin: 0 0 15px 0; color: #1a5276;">School Information</h4>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>School Name</label>
                        <input type="text" name="school_name" class="form-control" value="<?php echo htmlspecialchars($settings['school_name'] ?? 'Nex CEC'); ?>" placeholder="e.g. Nex CEC Basic School">
                    </div>
                    <div class="form-group">
                        <label>School Motto</label>
                        <input type="text" name="school_motto" class="form-control" value="<?php echo htmlspecialchars($settings['school_motto'] ?? 'Excellence in Education'); ?>" placeholder="e.g. Excellence in Education">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>School Address</label>
                        <input type="text" name="school_address" class="form-control" value="<?php echo htmlspecialchars($settings['school_address'] ?? ''); ?>" placeholder="e.g. Kumasi, Ghana">
                    </div>
                    <div class="form-group">
                        <label>Contact Email</label>
                        <input type="email" name="school_email" class="form-control" value="<?php echo htmlspecialchars($settings['school_email'] ?? ''); ?>" placeholder="info@school.edu.gh">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Contact Phone</label>
                        <input type="text" name="school_phone" class="form-control" value="<?php echo htmlspecialchars($settings['school_phone'] ?? ''); ?>" placeholder="+233 XX XXX XXXX">
                    </div>
                </div>

                <h4 style="margin: 20px 0 15px 0; color: #e74c3c; border-top: 1px solid #eee; padding-top: 15px;">Enrollment / Admission Fees</h4>
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Admission Fee (GHS)</label>
                        <input type="number" step="0.01" name="admission_fee" class="form-control" value="<?php echo htmlspecialchars($settings['admission_fee'] ?? '150.00'); ?>" required placeholder="e.g. 150.00">
                        <small style="color: #666;">One-time admission fee</small>
                    </div>
                    <div class="form-group">
                        <label>Prospectus Fee (GHS)</label>
                        <input type="number" step="0.01" name="prospectus_fee" class="form-control" value="<?php echo htmlspecialchars($settings['prospectus_fee'] ?? '50.00'); ?>" required placeholder="e.g. 50.00">
                        <small style="color: #666;">Prospectus/information pack</small>
                    </div>
                    <div class="form-group">
                        <label>Enrollment Form Fee (GHS)</label>
                        <input type="number" step="0.01" name="enrollment_form_fee" class="form-control" value="<?php echo htmlspecialchars($settings['enrollment_form_fee'] ?? '20.00'); ?>" required placeholder="e.g. 20.00">
                        <small style="color: #666;">Form processing fee</small>
                    </div>
                </div>

                <h4 style="margin: 20px 0 15px 0; color: #1a5276; border-top: 1px solid #eee; padding-top: 15px;">Academic Settings</h4>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                    <div class="form-group">
                        <label>Current Academic Year</label>
                        <input type="text" name="current_academic_year" class="form-control" value="<?php echo htmlspecialchars($settings['current_academic_year']); ?>" required placeholder="e.g. 2025/2026">
                    </div>
                    <div class="form-group">
                        <label>Current Term</label>
                        <select name="current_term" class="form-control" required>
                            <option value="1" <?php echo ($settings['current_term'] ?? '1') == '1' ? 'selected' : ''; ?>>Term 1</option>
                            <option value="2" <?php echo ($settings['current_term'] ?? '1') == '2' ? 'selected' : ''; ?>>Term 2</option>
                            <option value="3" <?php echo ($settings['current_term'] ?? '1') == '3' ? 'selected' : ''; ?>>Term 3</option>
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

                <div style="margin-top: 15px;">
                    <div class="form-group">
                        <label>Fee Types (Comma separated)</label>
                        <input type="text" name="fee_types" class="form-control" value="<?php echo htmlspecialchars($settings['fee_types'] ?? 'Tuition,PTA Levy,Sports & Culture,ICT,Examination,Development,Feeding,Transport,Uniform,Books & Materials'); ?>" placeholder="e.g. Tuition,PTA Levy,Sports,ICT,Examination">
                        <small style="color: #666;">These are the fee categories that will appear on the student dashboard.</small>
                    </div>
                </div>

                <h4 style="margin: 20px 0 15px 0; color: #8e44ad; border-top: 1px solid #eee; padding-top: 15px;">Discount Settings</h4>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Staff Child Discount (GHS)</label>
                        <input type="number" step="0.01" name="staff_child_discount" class="form-control" value="<?php echo htmlspecialchars($settings['staff_child_discount'] ?? '150.00'); ?>" placeholder="e.g. 150.00">
                        <small style="color: #666;">Auto-applied when a guardian matches active staff</small>
                    </div>
                    <div class="form-group">
                        <label>Sibling Discount (GHS) — 3rd+ Child</label>
                        <input type="number" step="0.01" name="sibling_discount_amount" class="form-control" value="<?php echo htmlspecialchars($settings['sibling_discount_amount'] ?? '150.00'); ?>" placeholder="e.g. 150.00">
                        <small style="color: #666;">Auto-applied to 3rd+ child sharing same guardian</small>
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
                    <div class="card-header flex justify-between items-center">
                        <h3>Configuration Summary</h3>
                        <a href="subjects.php" class="btn-admin-action"><i class="fas fa-book"></i> Manage Subjects</a>
                    </div>
                    <div class="card-content">
                        <p>These settings control various aspects of the SDMS, including the academic year displayed on receipts and the default dues amount for compliance tracking.</p>
                        <ul style="margin-top: 10px; padding-left: 20px;">
                            <li><strong>Academic Year:</strong> Used for tracking payments and compliance.</li>
                            <li><strong>Dues Amount:</strong> Used to calculate the compliance rate on the dashboard.</li>
                            <li><strong>Payment Modes:</strong> These appear as options when recording a new payment.</li>
                            <li><strong>Subjects:</strong> <a href="subjects.php" class="color-primary fw-500">Click here to configure subjects</a> by educational level (Creche, Nursery, KG, Primary, JHS).</li>
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

