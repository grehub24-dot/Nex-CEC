<?php
// staff_messaging.php — Staff Portal Messages
require_once 'includes/db.php';

if (!isLoggedIn() || (!isStaff() && !isTeacher())) {
    redirect('../login.php');
}

$settings = fetchSettings($pdo);
$school_name = $settings['school_name'] ?? 'Nex CEC';

$user_id = $_SESSION['user_id'];

// Fetch staff record
$stmt = $pdo->prepare("SELECT * FROM staff WHERE user_id = ?");
$stmt->execute([$user_id]);
$staff = $stmt->fetch();

if (!$staff) {
    echo '<div class="container" style="padding:100px 0;text-align:center;"><h2>Staff record not found</h2><a href="../logout.php" class="btn-primary">Logout</a></div>';
    exit;
}

$message_status = '';
$error_status = '';

// Handle Sending Message to Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_to_admin') {
    $subject = sanitize($_POST['subject']);
    $content = sanitize($_POST['content']);

    // Find the first admin user
    $admin_id = null;
    $allUsers = $pdo->query("SELECT * FROM users")->fetchAll();
    foreach ($allUsers as $u) {
        if (isset($u['role']) && $u['role'] === 'admin') {
            $admin_id = $u['id'];
            break;
        }
    }

    if ($admin_id) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, title, content, is_broadcast) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $admin_id, $subject, $content, 0])) {
            $message_status = "Message sent to administrator successfully!";
        } else {
            $error_status = "Failed to send message.";
        }
    } else {
        $error_status = "No administrator found to receive the message.";
    }
}

// Fetch messages for this staff user (broadcasts + direct)
$messages = [];
try {
    // Step 1: Direct messages for this user
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE receiver_id = ?");
    $stmt->execute([$user_id]);
    $my_messages = $stmt->fetchAll();

    // Step 2: Broadcast messages
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE is_broadcast = ?");
    $stmt->execute([1]);
    $broadcast_messages = $stmt->fetchAll();

    // Combine and deduplicate
    $seen = [];
    $combined = array_merge($my_messages, $broadcast_messages);
    foreach ($combined as $m) {
        if (!isset($seen[$m['id']])) {
            $seen[$m['id']] = true;
            $messages[] = $m;
        }
    }

    // Sort by created_at DESC
    usort($messages, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    $messages = array_slice($messages, 0, 50);

    // Enrich with sender info
    foreach ($messages as &$msg) {
        if (!empty($msg['sender_id'])) {
            $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
            $stmt->execute([$msg['sender_id']]);
            $user = $stmt->fetch();
            if ($user) {
                $msg['sender_email'] = $user['email'] ?? 'School';
                $msg['sender_name'] = $user['full_name'] ?? 'School Admin';
            } else {
                $msg['sender_email'] = 'School';
                $msg['sender_name'] = 'School Admin';
            }
        } else {
            $msg['sender_email'] = 'School';
            $msg['sender_name'] = 'School Admin';
        }
    }
    unset($msg);
} catch (Exception $e) {
    error_log("Staff messages fetch error: " . $e->getMessage());
}

// Determine read status using message_reads table
$read_message_ids = [];
try {
    $stmt = $pdo->prepare("SELECT message_id FROM message_reads WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $read_rows = $stmt->fetchAll();
    $read_message_ids = array_map(fn($r) => (int)$r['message_id'], $read_rows);
} catch (Exception $e) {
    error_log("message_reads fetch error: " . $e->getMessage());
}

// Count unread
$unread_count = 0;
foreach ($messages as &$m) {
    $mid = (int)$m['id'];
    $is_read = in_array($mid, $read_message_ids) || !empty($m['read_at']);
    $m['is_read'] = $is_read;
    if (!$is_read) $unread_count++;
}
unset($m);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages — Staff Portal</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .staff-container { display: flex; min-height: 100vh; }
        .staff-sidebar {
            width: 250px; background: #1a5276; color: white; position: fixed;
            top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 100;
        }
        .staff-sidebar .sidebar-header { padding: 25px 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .staff-sidebar .sidebar-header img { width: 64px; height: 64px; border-radius: 50%; background: white; padding: 3px; margin-bottom: 10px; object-fit: cover; }
        .staff-sidebar .sidebar-header h3 { font-size: 15px; margin: 0; }
        .staff-sidebar .sidebar-header p { font-size: 12px; opacity: 0.8; margin: 5px 0 0; }
        .staff-sidebar ul { list-style: none; padding: 0; margin: 0; }
        .staff-sidebar ul li { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .staff-sidebar ul li a {
            display: block; padding: 14px 20px; color: rgba(255,255,255,0.85); text-decoration: none;
            font-size: 14px; transition: all 0.2s; position: relative;
        }
        .staff-sidebar ul li a:hover, .staff-sidebar ul li a.active { background: rgba(255,255,255,0.1); color: white; padding-left: 25px; }
        .staff-sidebar ul li a i { width: 22px; text-align: center; margin-right: 8px; }
        .staff-sidebar .msg-count {
            position: absolute; right: 15px; top: 50%; transform: translateY(-50%);
            background: #e74c3c; color: white; padding: 1px 8px;
            border-radius: 10px; font-size: 11px; font-weight: 700; line-height: 1.5;
            min-width: 20px; text-align: center;
        }
        .staff-main { flex: 1; padding: 30px; background: #f4f6f9; margin-left: 250px; }
        .top-bar {
            background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: center; justify-content: space-between;
        }
        .top-bar h2 { font-size: 20px; margin: 0; color: #1a5276; }
        .top-bar .subtitle { font-size: 13px; color: #888; margin: 3px 0 0; }
        .hamburger-menu { display: none; position: fixed; top: 15px; left: 15px; z-index: 200;
            background: #1a5276; color: white; border: none; width: 40px; height: 40px;
            border-radius: 8px; font-size: 18px; cursor: pointer;
        }
        @media (max-width: 768px) {
            .staff-sidebar { left: -250px; transition: left 0.3s; }
            .staff-sidebar.open { left: 0; }
            .staff-main { margin-left: 0; padding: 20px; }
            .hamburger-menu { display: block; }
            .top-bar { flex-direction: column; text-align: center; margin-top: 50px; gap: 10px; }
        }

        /* Message card styles */
        .msg-card {
            background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 15px; overflow: hidden; transition: box-shadow 0.2s;
        }
        .msg-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .msg-card.unread { border-left: 4px solid #1a5276; }
        .msg-card .msg-header {
            padding: 18px 22px; cursor: pointer; display: flex;
            align-items: center; justify-content: space-between;
        }
        .msg-card .msg-header .msg-title {
            display: flex; align-items: center; gap: 12px; flex: 1;
        }
        .msg-card .msg-header .msg-title h4 { font-size: 15px; margin: 0; }
        .msg-card .msg-header .msg-title .msg-type-badge {
            font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600; text-transform: uppercase;
        }
        .msg-card .msg-header .msg-title .msg-type-badge.broadcast { background: #003366; color: white; }
        .msg-card .msg-header .msg-title .msg-type-badge.direct { background: #28a745; color: white; }
        .msg-card .msg-header .msg-title .unread-dot {
            width: 8px; height: 8px; background: #1a5276; border-radius: 50%; flex-shrink: 0;
        }
        .msg-card .msg-header .msg-date { font-size: 12px; color: #999; white-space: nowrap; }
        .msg-card .msg-body {
            padding: 0 22px 18px; display: none; font-size: 14px;
            line-height: 1.7; color: #555;
        }
        .msg-card .msg-body.open { display: block; }
        .msg-card .msg-body .sender { font-size: 12px; color: #888; margin-top: 12px; }
        .no-messages {
            text-align: center; padding: 60px 20px; background: white;
            border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .no-messages i { font-size: 48px; color: #ddd; margin-bottom: 15px; }
        .no-messages h3 { color: #888; margin: 0 0 8px; }
        .no-messages p { color: #aaa; font-size: 14px; }

        /* Modal styles */
        .modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0;
            width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fff; margin: 10% auto; padding: 25px;
            border-radius: 8px; width: 500px; position: relative; box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .close-btn {
            position: absolute; right: 15px; top: 10px; font-size: 24px;
            cursor: pointer; color: #888;
        }
        .modal-content h3 { margin: 0 0 15px; color: #1a5276; }
        .modal-content .form-group { margin-bottom: 15px; }
        .modal-content .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; color: #333; }
        .modal-content .form-group input,
        .modal-content .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .modal-content .form-group textarea { resize: vertical; }
        .modal-content .char-count { font-size: 12px; color: #888; margin-top: 4px; }

        /* Section titles */
        .section-title {
            font-size: 16px; color: #1a5276; margin-bottom: 15px; padding-bottom: 8px;
            border-bottom: 2px solid #e8f0fe; display: flex; align-items: center; gap: 8px;
        }
        .badge-unread {
            background: #e74c3c; color: white; padding: 4px 12px;
            border-radius: 20px; font-size: 13px; font-weight: 600;
        }
    </style>
</head>
<body>
    <button class="hamburger-menu" id="hamburgerBtn" onclick="document.getElementById('sidebar').classList.toggle('open')">
        <i class="fas fa-bars"></i>
    </button>

    <aside class="staff-sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="../images/school-logo.png" alt="Logo" onerror="this.src='../images/aamusted.jpg'">
            <h3><?php echo htmlspecialchars($school_name); ?></h3>
            <p>Staff Portal</p>
        </div>
        <ul>
            <li><a href="../staff/dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <?php if (isTeacher()): ?>
            <li><a href="../staff/grades.php"><i class="fas fa-clipboard-list"></i> SBA / Grades</a></li>
            <?php endif; ?>
            <li><a href="../staff/attendance.php"><i class="fas fa-calendar-check"></i> My Attendance</a></li>
            <li><a href="../staff/payslip.php"><i class="fas fa-file-invoice-dollar"></i> Pay Slips</a></li>
            <li><a href="../staff/profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
            <li>
                <a href="../staff/messaging.php" class="active">
                    <i class="fas fa-envelope"></i> Messages
                    <?php if ($unread_count > 0): ?>
                        <span class="msg-count"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php if (isTeacher()): ?>
            <li><a href="../admin/attendance.php"><i class="fas fa-user-check"></i> Student Attendance</a></li>
            <?php endif; ?>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <div class="staff-main">
        <div class="top-bar">
            <div>
                <h2><i class="fas fa-envelope"></i> Messages</h2>
                <p class="subtitle"><?php echo htmlspecialchars($staff['full_name'] ?? 'Staff'); ?> &bull; <?php echo htmlspecialchars($staff['position'] ?? ''); ?></p>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <?php if ($unread_count > 0): ?>
                    <span class="badge-unread"><?php echo $unread_count; ?> unread</span>
                <?php endif; ?>
                <button onclick="document.getElementById('sendModal').style.display='block'" class="btn-primary" style="padding:10px 18px; font-size:14px;">
                    <i class="fas fa-paper-plane"></i> Contact Admin
                </button>
            </div>
        </div>

        <?php if ($message_status): ?>
            <div class="alert alert-success"><?php echo $message_status; ?></div>
        <?php endif; ?>
        <?php if ($error_status): ?>
            <div class="alert alert-danger"><?php echo $error_status; ?></div>
        <?php endif; ?>

        <?php if (empty($messages)): ?>
            <div class="no-messages">
                <i class="fas fa-inbox"></i>
                <h3>No Messages</h3>
                <p>You don't have any messages yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $msg):
                $is_unread = empty($msg['is_read']);
                $title = htmlspecialchars(fix_utf8_encoding($msg['title'] ?? '(No subject)'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $content = htmlspecialchars(fix_utf8_encoding($msg['content'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $date = date('j M Y, g:i a', strtotime($msg['created_at'] ?? 'now'));
                $sender = htmlspecialchars($msg['sender_name'] ?? 'School', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $sender_email = htmlspecialchars($msg['sender_email'] ?? 'School');
                $msg_id = (int)$msg['id'];
                $is_broadcast = !empty($msg['is_broadcast']);
            ?>
                <div class="msg-card <?php echo $is_unread ? 'unread' : ''; ?>" data-msg-id="<?php echo $msg_id; ?>">
                    <div class="msg-header" onclick="toggleMsg(this, <?php echo $msg_id; ?>)">
                        <div class="msg-title">
                            <?php if ($is_unread): ?>
                                <span class="unread-dot"></span>
                            <?php endif; ?>
                            <span class="msg-type-badge <?php echo $is_broadcast ? 'broadcast' : 'direct'; ?>">
                                <?php echo $is_broadcast ? 'Broadcast' : 'Direct'; ?>
                            </span>
                            <h4><?php echo $title; ?></h4>
                        </div>
                        <span class="msg-date"><?php echo $date; ?></span>
                    </div>
                    <div class="msg-body">
                        <p><?php echo nl2br($content); ?></p>
                        <p class="sender">From: <?php echo $sender; ?> (<?php echo $sender_email; ?>)</p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Send Message Modal -->
    <div id="sendModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('sendModal').style.display='none'">&times;</span>
            <h3>Send Message to Admin</h3>
            <form method="POST" action="" style="margin-top: 10px;">
                <input type="hidden" name="action" value="send_to_admin">
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" class="form-control" placeholder="e.g. Leave request" required>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="content" class="form-control" rows="5" placeholder="Type your message here..." required></textarea>
                </div>
                <button type="submit" class="btn-primary" style="width:100%; padding:12px;"><i class="fas fa-paper-plane"></i> Send Message</button>
            </form>
        </div>
    </div>

    <script>
        function toggleMsg(header, msgId) {
            var body = header.nextElementSibling;
            var card = header.closest('.msg-card');
            if (body) {
                body.classList.toggle('open');

                // If message was unread and is now being opened, mark as read
                if (card && card.classList.contains('unread')) {
                    card.classList.remove('unread');
                    var dot = header.querySelector('.unread-dot');
                    if (dot) dot.style.display = 'none';

                    // Update unread count in header and top bar
                    var badge = document.querySelector('.badge-unread');
                    if (badge) {
                        var count = parseInt(badge.textContent);
                        if (count > 1) {
                            badge.textContent = (count - 1) + ' unread';
                        } else {
                            badge.remove();
                        }
                    }

                    // Also update sidebar count
                    var sidebarCount = document.querySelector('.staff-sidebar .msg-count');
                    if (sidebarCount) {
                        var sc = parseInt(sidebarCount.textContent);
                        if (sc > 1) {
                            sidebarCount.textContent = sc - 1;
                        } else {
                            sidebarCount.remove();
                        }
                    }

                    // AJAX: mark message as read
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '../mark_message_read.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.send('message_id=' + msgId);
                }
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = "none";
            }
        }
    </script>
</body>
</html>
