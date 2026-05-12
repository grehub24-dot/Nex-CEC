<?php
require_once 'includes/db.php';
require_once 'includes/SMSHelper.php';

// Enforce access control
requireAccess('messaging');

// Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $title = sanitize($_POST['title'] ?? '');
    $content = sanitize($_POST['content'] ?? '');
    $send_sms = isset($_POST['send_sms']);

    if ($action === 'broadcast') {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, title, content, is_broadcast) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $title, $content, 1]);

            if ($send_sms) {
                $sms = new SMSHelper();
                $smsText = trim($title . ': ' . $content);
                $students = array_filter($pdo->query("SELECT guardian_phone_primary, guardian_phone_emergency FROM students")->fetchAll(), function($s) {
                    $p = $s['guardian_phone_primary'] ?? ''; $e = $s['guardian_phone_emergency'] ?? '';
                    return !empty($p) || !empty($e);
                });
                $sentCount = 0;
                $failedCount = 0;
                foreach ($students as $student) {
                    $to = $student['guardian_phone_primary'] ?? '';
                    if (!$to) {
                        $to = $student['guardian_phone_emergency'] ?? '';
                    }
                    if (!$to) {
                        continue;
                    }
                    if ($sms->send($to, $smsText)) {
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
                        SELECT DISTINCT guardian_phone_primary, guardian_phone_emergency
                        FROM students
                        WHERE (guardian_phone_primary IS NOT NULL AND guardian_phone_primary != '')
                           OR (guardian_phone_emergency IS NOT NULL AND guardian_phone_emergency != '')
                    ")->fetchAll();
                    foreach ($students as $student) {
                        $to = $student['guardian_phone_primary'] ?? '';
                        if (!$to) {
                            $to = $student['guardian_phone_emergency'] ?? '';
                        }
                        if (!$to) {
                            continue;
                        }
                        if ($sms->send($to, $sms_content)) {
                            $count++;
                        } else {
                            $failedCount++;
                        }
                    }

                    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, title, content, is_broadcast) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], "Bulk SMS", $sms_content, 1]);

                    $message = "SMS sent successfully to $count member(s)";
                    if ($failedCount > 0) {
                        $message .= " ($failedCount failed).";
                    } else {
                        $message .= ".";
                    }
                } else {
                    $stmt = $pdo->prepare("SELECT s.guardian_phone_primary, s.guardian_phone_emergency, s.full_name, s.user_id FROM students s WHERE s.id = ?");
                    $stmt->execute([$student_id]);
                    $student = $stmt->fetch();
                    $to = $student['guardian_phone_primary'] ?? '';
                    if (!$to) {
                        $to = $student['guardian_phone_emergency'] ?? '';
                    }
                    if ($student && !empty($to)) {
                        if ($sms->send($to, $sms_content)) {
                            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, title, content, is_broadcast) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$_SESSION['user_id'], $student['user_id'], "Individual SMS", $sms_content, 0]);
                            
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
$all_students = $pdo->query("SELECT id, full_name, admission_number FROM students ORDER BY full_name ASC")->fetchAll();
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
            <?php echo renderSidebar('messaging', $school_name); ?>

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
                    // Two-step: get admin user IDs first, then filter messages (bridge cannot handle subqueries)
                    $admin_ids = [];
                    $admins = $pdo->prepare("SELECT id FROM users WHERE role = ?");
                    $admins->execute(['admin']);
                    $admin_rows = $admins->fetchAll();
                    foreach ($admin_rows as $r) {
                        $admin_ids[] = (int)$r['id'];
                    }
                    $all_non_broadcast = [];
                    $stmt = $pdo->prepare("SELECT * FROM messages WHERE is_broadcast = ?");
                    $stmt->execute([0]);
                    $all_non_broadcast = $stmt->fetchAll();
                    $student_msgs = array_filter($all_non_broadcast, fn($m) => in_array((int)$m['receiver_id'], $admin_ids));
                    usort($student_msgs, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
                    $student_msgs = array_slice($student_msgs, 0, 15);
                    
                    // Enrich with student names (two-step lookup for Supabase compatibility)
                    foreach ($student_msgs as &$msg) {
                        $s = $pdo->prepare("SELECT full_name, admission_number FROM students WHERE user_id = ?");
                        $s->execute([$msg['sender_id']]);
                        $stu = $s->fetch();
                        $msg['sender_name'] = $stu ? $stu['full_name'] : 'Unknown';
                        $msg['admission_number'] = $stu ? $stu['admission_number'] : '-';
                    }
                    
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
                                            <small><?php echo htmlspecialchars($msg['admission_number']); ?></small>
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
                        SELECT *
                        FROM messages 
                        ORDER BY created_at DESC 
                        LIMIT 20
                    ")->fetchAll();
                    
                    // Enrich with recipient names (two-step lookup for Supabase compatibility)
                    foreach ($all_msgs as &$msg) {
                        if ($msg['is_broadcast']) {
                            $msg['recipient_name'] = 'All Students (Broadcast)';
                        } else {
                            $s = $pdo->prepare("SELECT full_name FROM students WHERE user_id = ?");
                            $s->execute([$msg['receiver_id']]);
                            $stu = $s->fetch();
                            $msg['recipient_name'] = $stu ? $stu['full_name'] : 'Admin/System';
                        }
                    }
                    
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
                            <option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['full_name']) . " (" . $student['admission_number'] . ")"; ?></option>
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

