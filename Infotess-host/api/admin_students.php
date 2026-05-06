<?php
require_once 'includes/db.php';
require_once 'includes/Mailer.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Fetch Settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

// Handle Student Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_student') {
    $full_name = sanitize($_POST['full_name']);
    $index_number = sanitize($_POST['index_number']);
    $class_name = sanitize($_POST['class_name']);
    $gender = sanitize($_POST['gender']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone_number'] ?? '');
    $guardian_name = sanitize($_POST['guardian_name']);
    $guardian_phone = sanitize($_POST['guardian_phone']);
    
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
            // Generate a random 6-character password
            $auto_password = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
            
            // 1. Create User Account
            $password_hash = password_hash($auto_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'student')");
            $stmt->execute([$email, $password_hash]);
            $user_id = $pdo->lastInsertId();

            // 2. Create Student Record (Basic School fields)
            $stmt = $pdo->prepare("INSERT INTO students (user_id, index_number, full_name, class_name, gender, phone_number, guardian_name, guardian_phone, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $index_number, $full_name, $class_name, $gender, $phone, $guardian_name, $guardian_phone, $profile_picture]);
            
            $student_id = $pdo->lastInsertId();

            $pdo->commit();
            $message = "Student registered successfully! Temporary password: $auto_password";

            if ($email) {
                $mailer = new Mailer();
                $subject = "Welcome — Student Registration Successful";
                $dateStr = date('n/j/Y');
                $html = "<div style=\"font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;\">
                    <div style=\"background: linear-gradient(90deg,#1a5276,#2e86c1); color:#fff; padding: 24px; text-align:center;\">
                        <div style=\"font-size: 20px; font-weight: 700;\">Welcome to " . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . "!</div>
                        <div style=\"margin-top:8px; font-size:14px; opacity:0.9;\">Student Registration Successful</div>
                    </div>
                    <div style=\"padding: 24px; color:#111827;\">
                        <p>Dear <strong>" . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                        <p>Congratulations! You have been successfully registered. Below are your details:</p>
                        <div style=\"border:1px solid #e5e7eb; border-radius:8px; padding:16px; background:#f9fafb; margin-top:12px;\">
                            <div style=\"display:grid; grid-template-columns: 180px 1fr; gap:8px; font-size:14px;\">
                                <div><strong>Full Name:</strong></div><div>" . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Index Number:</strong></div><div>" . htmlspecialchars($index_number, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Class:</strong></div><div>" . htmlspecialchars($class_name, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Gender:</strong></div><div>" . htmlspecialchars($gender, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Guardian:</strong></div><div>" . htmlspecialchars($guardian_name, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Guardian Phone:</strong></div><div>" . htmlspecialchars($guardian_phone, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Email:</strong></div><div>" . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Temp Password:</strong></div><div>" . htmlspecialchars($auto_password, ENT_QUOTES, 'UTF-8') . "</div>
                                <div><strong>Registration Date:</strong></div><div>" . $dateStr . "</div>
                            </div>
                        </div>
                        <div style=\"margin-top:16px;\">
                            <div style=\"font-weight:600; margin-bottom:8px;\">Important Information:</div>
                            <ul style=\"margin:0; padding-left:20px; color:#374151; font-size:14px;\">
                                <li>Keep your index number safe — you'll need it for all fee payments</li>
                                <li>Login and reset your password immediately</li>
                                <li>All payment receipts will be sent to this email address</li>
                            </ul>
                        </div>
                        <hr style=\"border:none; border-top:1px solid #e5e7eb; margin:20px 0;\"/>
                        <div style=\"font-size:13px; color:#6b7280; text-align:center;\">
                            <div style=\"font-weight:600;\">" . htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8') . "</div>
                            <div style=\"margin-top:8px; font-size:12px;\">This is an automated email. Please do not reply to this message.</div>
                        </div>
                    </div>
                </div>";
                $mailer->sendHTML($email, $subject, $html);
            }

            // Send SMS to guardian
            if ($guardian_phone) {
                $smsHelper = new SMSHelper();
                $smsMsg = "Registration successful for $full_name. Index: $index_number. Class: $class_name. Temp password: $auto_password. Login and reset password.";
                $smsHelper->send($guardian_phone, $smsMsg);
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
$query = "SELECT * FROM students";
$params = [];
if ($search) {
    $query .= " WHERE full_name LIKE ? OR index_number LIKE ?";
    $params = ["%$search%", "%$search%"];
}
$query .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get total count
$total_query = "SELECT COUNT(*) FROM students";
$total_params = [];
if ($search) {
    $total_query .= " WHERE full_name LIKE ? OR index_number LIKE ?";
    $total_params = ["%$search%", "%$search%"];
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
    <title>Manage Students — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 650px; border-radius: 8px; position: relative; }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover, .close-btn:focus { color: black; text-decoration: none; cursor: pointer; }
        .upload-preview { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 2px solid #d4dbe3; margin: 0 auto 10px auto; display: block; background: #f3f6f9; }
        .upload-file-name { margin-top: 8px; font-size: 0.82rem; color: #4b5563; }
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
                            <label>Student Full Name</label>
                            <input type="text" name="full_name" class="form-control" required placeholder="e.g. Kwame Asante">
                        </div>
                        <div>
                            <label>Index / Admission Number</label>
                            <input type="text" name="index_number" class="form-control" required placeholder="e.g. NXC/2026/001">
                        </div>
                        <div>
                            <label>Class</label>
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
                        <div>
                            <label>Gender</label>
                            <select name="gender" class="form-control" required>
                                <option value="">-- Select --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div>
                            <label>Student Phone (if applicable)</label>
                            <input type="text" name="phone_number" class="form-control" placeholder="Optional for older students">
                        </div>
                        <div>
                            <label>Email (for fee receipts)</label>
                            <input type="email" name="email" class="form-control" required placeholder="parent@email.com">
                        </div>

                        <!-- Guardian Details Section -->
                        <div class="section-divider">
                            <h4><i class="fas fa-user-shield"></i> Parent / Guardian Details</h4>
                        </div>

                        <div>
                            <label>Guardian Full Name</label>
                            <input type="text" name="guardian_name" class="form-control" required placeholder="e.g. Mr. Asante">
                        </div>
                        <div>
                            <label>Guardian Phone Number</label>
                            <input type="text" name="guardian_phone" class="form-control" required placeholder="e.g. 0241234567">
                        </div>

                        <div style="grid-column: span 2; margin-top: 10px;">
                            <button type="submit" class="btn-submit" style="width:100%;">Register Student</button>
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
                                <th>Class</th>
                                <th>Gender</th>
                                <th>Guardian</th>
                                <th>Contact</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td>
                                    <img src="../<?php echo htmlspecialchars($student['profile_picture'] ?? 'images/aamusted.jpg'); ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #ddd;">
                                </td>
                                <td><?php echo htmlspecialchars($student['index_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['class_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($student['gender'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($student['guardian_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($student['phone_number'] ?? '-'); ?></td>
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
        const modal = document.getElementById("studentModal");
        const btn = document.getElementById("openModalBtn");
        const span = document.getElementsByClassName("close-btn")[0];

        btn.onclick = function() { modal.style.display = "block"; }
        span.onclick = function() { modal.style.display = "none"; }
        window.onclick = function(event) { if (event.target == modal) { modal.style.display = "none"; } }

        const studentProfileUpload = document.getElementById('studentProfileUpload');
        const studentUploadPreview = document.getElementById('studentUploadPreview');
        const studentUploadFileName = document.getElementById('studentUploadFileName');

        if (studentProfileUpload && studentUploadPreview && studentUploadFileName) {
            studentProfileUpload.addEventListener('change', function() {
                const file = this.files && this.files[0] ? this.files[0] : null;
                if (!file) { studentUploadPreview.src = '../images/aamusted.jpg'; studentUploadFileName.textContent = 'No image selected'; return; }
                studentUploadFileName.textContent = file.name;
                if (!file.type.startsWith('image/')) { studentUploadPreview.src = '../images/aamusted.jpg'; studentUploadFileName.textContent = 'Please select an image file'; this.value = ''; return; }
                const reader = new FileReader();
                reader.onload = function(event) { studentUploadPreview.src = event.target.result; };
                reader.readAsDataURL(file);
            });
        }
    </script>
</body>
</html>
