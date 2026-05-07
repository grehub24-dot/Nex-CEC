<?php
// enroll_form.php — Online Student Enrollment Form
session_start();

require_once __DIR__ . '/api/includes/db.php';

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}

$school_name = $settings['school_name'] ?? 'Nex CEC Basic School';
$school_motto = $settings['school_motto'] ?? 'Education for Excellence';
$academic_year = date('Y') . '/' . (date('Y') + 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Form — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .enroll-form-page {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .enroll-header {
            text-align: center;
            padding: 30px 20px;
            background: linear-gradient(135deg, #1a5276, #2e86c1);
            color: #fff;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .enroll-header h2 { font-size: 1.8rem; margin-bottom: 5px; }
        .enroll-header p { opacity: 0.9; }
        .enroll-form-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 30px;
            margin-bottom: 25px;
        }
        .form-section { margin-bottom: 25px; }
        .form-section h4 {
            color: #1a5276;
            font-size: 1.05rem;
            border-bottom: 2px solid #ffcc00;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group { margin-bottom: 0; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 5px; font-size: 0.9rem; }
        .form-group label .req { color: #e74c3c; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem; box-sizing: border-box; }
        .form-control:focus { border-color: #1a5276; outline: none; box-shadow: 0 0 0 2px rgba(26,82,118,0.1); }
        .full-width { grid-column: span 2; }
        .btn-enroll-submit {
            display: block;
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #27ae60, #219a52);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn-enroll-submit:hover { transform: translateY(-2px); }
        .back-link { display: inline-block; margin-top: 15px; color: #1a5276; text-decoration: none; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">
                <img src="images/school-logo.png" alt="Logo" style="width:40px;height:40px;border-radius:50%;" onerror="this.style.display='none'">
                <?php echo htmlspecialchars($school_name); ?>
            </a>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="enroll.php">Enroll</a>
                <a href="login.php" class="btn-login">Login</a>
            </div>
            <button class="hamburger" onclick="document.querySelector('.nav-links').classList.toggle('active')">☰</button>
        </div>
    </nav>

    <div class="enroll-form-page">
        <div class="enroll-header">
            <h2><i class="fas fa-user-plus"></i> Student Enrollment Form</h2>
            <p><?php echo htmlspecialchars($school_motto); ?> | Academic Year: <?php echo $academic_year; ?></p>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars(urldecode($_GET['error'])); ?></div>
        <?php endif; ?>

        <form action="api/enroll_process.php" method="POST" id="enrollmentForm">
            <!-- Student Information -->
            <div class="enroll-form-card">
                <div class="form-section">
                    <h4><i class="fas fa-child"></i> Student Information</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Student Full Name <span class="req">*</span></label>
                            <input type="text" name="full_name" class="form-control" required placeholder="e.g. Kwame Asante">
                        </div>
                        <div class="form-group">
                            <label>Gender <span class="req">*</span></label>
                            <select name="gender" class="form-control" required>
                                <option value="">-- Select --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date of Birth <span class="req">*</span></label>
                            <input type="date" name="date_of_birth" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Place of Birth</label>
                            <input type="text" name="place_of_birth" class="form-control" placeholder="e.g. Kumasi">
                        </div>
                        <div class="form-group">
                            <label>Class Applying For <span class="req">*</span></label>
                            <select name="class_name" class="form-control" required>
                                <option value="">-- Select Class --</option>
                                <optgroup label="Early Childhood">
                                    <option value="Creche">Creche</option>
                                    <option value="Nursery">Nursery</option>
                                    <option value="KG 1">KG 1</option>
                                    <option value="KG 2">KG 2</option>
                                </optgroup>
                                <optgroup label="Primary">
                                    <option value="Basic 1">Basic 1</option>
                                    <option value="Basic 2">Basic 2</option>
                                    <option value="Basic 3">Basic 3</option>
                                    <option value="Basic 4">Basic 4</option>
                                    <option value="Basic 5">Basic 5</option>
                                    <option value="Basic 6">Basic 6</option>
                                </optgroup>
                                <optgroup label="Junior High School">
                                    <option value="JHS 1">JHS 1</option>
                                    <option value="JHS 2">JHS 2</option>
                                    <option value="JHS 3">JHS 3</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Previous School</label>
                            <input type="text" name="previous_school" class="form-control" placeholder="e.g. ABC Kindergarten">
                        </div>
                        <div class="form-group">
                            <label>Previous Class</label>
                            <input type="text" name="previous_class" class="form-control" placeholder="e.g. KG 2">
                        </div>
                        <div class="form-group">
                            <label>Nationality</label>
                            <input type="text" name="nationality" class="form-control" value="Ghanaian">
                        </div>
                        <div class="form-group">
                            <label>NHIS Number</label>
                            <input type="text" name="health_insurance_id" class="form-control" placeholder="NHIS number">
                        </div>
                        <div class="form-group full-width">
                            <label>Allergies / Medical Conditions</label>
                            <input type="text" name="allergies" class="form-control" placeholder="e.g. Asthma, Peanuts, Penicillin">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Guardian Information -->
            <div class="enroll-form-card">
                <div class="form-section">
                    <h4><i class="fas fa-user-shield"></i> Parent / Guardian Information</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Guardian Full Name <span class="req">*</span></label>
                            <input type="text" name="guardian_name" class="form-control" required placeholder="e.g. Mr. Asante">
                        </div>
                        <div class="form-group">
                            <label>Relationship <span class="req">*</span></label>
                            <select name="guardian_relationship" class="form-control" required>
                                <option value="">-- Select --</option>
                                <option value="Father">Father</option>
                                <option value="Mother">Mother</option>
                                <option value="Guardian">Guardian</option>
                                <option value="Uncle">Uncle</option>
                                <option value="Aunt">Aunt</option>
                                <option value="Grandparent">Grandparent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Primary Phone <span class="req">*</span></label>
                            <input type="text" name="guardian_phone_primary" class="form-control" required maxlength="10" pattern="[0-9]{10}" placeholder="e.g. 0241234567">
                        </div>
                        <div class="form-group">
                            <label>Emergency Phone</label>
                            <input type="text" name="guardian_phone_emergency" class="form-control" maxlength="10" placeholder="e.g. 0501234567">
                        </div>
                        <div class="form-group">
                            <label>Guardian Email <span class="req">*</span></label>
                            <input type="email" name="guardian_email" class="form-control" required placeholder="parent@email.com">
                        </div>
                        <div class="form-group">
                            <label>Guardian Occupation</label>
                            <input type="text" name="guardian_occupation" class="form-control" placeholder="e.g. Teacher">
                        </div>
                        <div class="form-group full-width">
                            <label>Residence <span class="req">*</span></label>
                            <input type="text" name="address" class="form-control" required placeholder="e.g. Kumasi, Ghana">
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-enroll-submit">
                <i class="fas fa-arrow-right"></i> Submit & Proceed to Payment
            </button>
        </form>

        <a href="enroll.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Enrollment Options</a>
    </div>

    <script>
        // Phone validation
        document.querySelector('input[name="guardian_phone_primary"]').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        document.querySelector('input[name="guardian_phone_emergency"]').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>
