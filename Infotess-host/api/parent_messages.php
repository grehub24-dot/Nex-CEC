<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn() || !isParentOrDual()) {
    redirect('../login.php');
}

$parent_user_id = $_SESSION['user_id'];

// Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

// Fetch messages sent to this parent (receiver_id = parent's user_id)
// Also fetch broadcast messages (is_broadcast = true)
// Bridge compatibility: separate queries (no JOIN, no mixed literals)
$messages = [];
try {
    // Step 1: Direct messages for this receiver
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE receiver_id = ?");
    $stmt->execute([$parent_user_id]);
    $my_messages = $stmt->fetchAll();

    // Step 2: Broadcast messages (parametrized literal)
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

    // Sort by created_at DESC (bridge ignores ORDER BY)
    usort($messages, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    $messages = array_slice($messages, 0, 50);

    // Enrich with sender email (two-step lookup)
    foreach ($messages as &$msg) {
        if (!empty($msg['sender_id'])) {
            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$msg['sender_id']]);
            $user = $stmt->fetch();
            $msg['sender_email'] = $user ? $user['email'] : 'School';
        } else {
            $msg['sender_email'] = 'School';
        }
    }
    unset($msg);
} catch (Exception $e) {
    error_log("Parent messages fetch error: " . $e->getMessage());
}

// Determine read status using message_reads table (per-user tracking)
// Two-step: fetch all message_reads entries for this user
$read_message_ids = [];
try {
    $stmt = $pdo->prepare("SELECT message_id FROM message_reads WHERE user_id = ?");
    $stmt->execute([$parent_user_id]);
    $read_rows = $stmt->fetchAll();
    $read_message_ids = array_map(fn($r) => (int)$r['message_id'], $read_rows);
} catch (Exception $e) {
    error_log("message_reads fetch error: " . $e->getMessage());
}

// Count unread
$unread_count = 0;
foreach ($messages as &$m) {
    $mid = (int)$m['id'];
    // A message is "read" if there's an entry in message_reads for this user
    // Fallback: also check messages.read_at for backward compatibility (direct messages)
    $is_read = in_array($mid, $read_message_ids) || !empty($m['read_at']);
    $m['is_read'] = $is_read;
    if (!$is_read) $unread_count++;
}
unset($m);

// Fetch parent profile picture for sidebar
$parent_profile_pic = null;
try {
    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->execute([$parent_user_id]);
    $row = $stmt->fetch();
    if ($row && !empty($row['profile_picture'])) {
        $parent_profile_pic = $row['profile_picture'];
    }
    // Fallback: if user is also staff, check staff table
    if (empty($parent_profile_pic) && ($_SESSION['role'] ?? '') === 'staff') {
        $stmt = $pdo->prepare("SELECT profile_picture FROM staff WHERE user_id = ?");
        $stmt->execute([$parent_user_id]);
        $srow = $stmt->fetch();
        if ($srow && !empty($srow['profile_picture'])) {
            $parent_profile_pic = $srow['profile_picture'];
        }
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages — Parent Portal</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; color: #333; }
        .parent-container { display: flex; min-height: 100vh; }
        .parent-main { flex: 1; padding: 30px; background: #f4f6f9; margin-left: 250px; }
        .parent-sidebar {
            width: 250px; background: #1a5276; color: white; position: fixed;
            top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 100;
        }
        .parent-sidebar .sidebar-header { padding: 25px 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .parent-sidebar .sidebar-header img.sidebar-profile-img { width: 64px; height: 64px; border-radius: 50%; background: white; padding: 3px; margin-bottom: 10px; object-fit: cover; }
        .parent-sidebar .sidebar-header h3 { font-size: 15px; margin: 0; }
        .parent-sidebar .sidebar-header p { font-size: 12px; opacity: 0.8; margin: 5px 0 0; }
        .parent-sidebar ul { list-style: none; padding: 0; margin: 0; }
        .parent-sidebar ul li { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .parent-sidebar ul li a {
            display: block; padding: 14px 20px; color: rgba(255,255,255,0.85); text-decoration: none;
            font-size: 14px; transition: all 0.2s; position: relative;
        }
        .parent-sidebar ul li a:hover, .parent-sidebar ul li a.active { background: rgba(255,255,255,0.1); color: white; padding-left: 25px; }
        .parent-sidebar ul li a i { width: 22px; text-align: center; margin-right: 8px; }
        .parent-sidebar .msg-count {
            position: absolute; right: 15px; top: 50%; transform: translateY(-50%);
            background: #e74c3c; color: white; padding: 1px 8px;
            border-radius: 10px; font-size: 11px; font-weight: 700; line-height: 1.5;
            min-width: 20px; text-align: center;
        }
        .hamburger-menu { display: none; position: fixed; top: 15px; left: 15px; z-index: 200;
            background: #1a5276; color: white; border: none; width: 40px; height: 40px;
            border-radius: 8px; font-size: 18px; cursor: pointer;
        }
        @media (max-width: 768px) {
            .parent-sidebar { left: -250px; transition: left 0.3s; }
            .parent-sidebar.open { left: 0; }
            .parent-main { margin-left: 0; padding: 20px; }
            .hamburger-menu { display: block; }
        }
        .page-header {
            background: white; border-radius: 12px; padding: 22px 28px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 25px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .page-header h2 { font-size: 20px; color: #1a5276; margin: 0; }
        .page-header .badge {
            background: #e74c3c; color: white; padding: 4px 12px;
            border-radius: 20px; font-size: 13px; font-weight: 600;
        }
        .card {
            background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 15px; overflow: hidden; transition: box-shadow 0.2s;
        }
        .card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .card.unread { border-left: 4px solid #1a5276; }
        .card .msg-header {
            padding: 18px 22px; cursor: pointer; display: flex;
            align-items: center; justify-content: space-between;
        }
        .card .msg-header .msg-title {
            display: flex; align-items: center; gap: 12px; flex: 1;
        }
        .card .msg-header .msg-title h4 { font-size: 15px; margin: 0; }
        .card .msg-header .msg-title .unread-dot {
            width: 8px; height: 8px; background: #1a5276; border-radius: 50%; flex-shrink: 0;
        }
        .card .msg-header .msg-date { font-size: 12px; color: #999; white-space: nowrap; }
        .card .msg-body {
            padding: 0 22px 18px; display: none; font-size: 14px;
            line-height: 1.7; color: #555;
        }
        .card .msg-body.open { display: block; }
        .card .msg-body .sender { font-size: 12px; color: #888; margin-top: 12px; }
        .no-messages {
            text-align: center; padding: 60px 20px; background: white;
            border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .no-messages i { font-size: 48px; color: #ddd; margin-bottom: 15px; }
        .no-messages h3 { color: #888; margin: 0 0 8px; }
        .no-messages p { color: #aaa; font-size: 14px; }
        .btn {
            display: inline-block; padding: 10px 20px; background: #1a5276;
            color: white; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 600;
        }
        .btn:hover { background: #143c58; }
        @media (max-width: 600px) {
            .page-header { flex-direction: column; gap: 10px; text-align: center; }
        }
    </style>
</head>
<body>
<div class="parent-container">
    <?php
    $profile_pic_path = $parent_profile_pic ? '../' . htmlspecialchars($parent_profile_pic) : '';
    echo renderParentSidebar('messages', $school_name, $unread_count, $profile_pic_path, !empty($_SESSION['has_children']));
    ?>
    <div class="parent-main">
        <div class="page-header">
            <h2><i class="fas fa-envelope"></i> Messages</h2>
            <?php if ($unread_count > 0): ?>
                <span class="badge"><?php echo $unread_count; ?> unread</span>
            <?php endif; ?>
        </div>

        <?php if (empty($messages)): ?>
            <div class="no-messages">
                <i class="fas fa-inbox"></i>
                <h3>No Messages</h3>
                <p>You don't have any messages yet. The school will communicate with you here.</p>
                <a href="../parent/dashboard.php" class="btn" style="margin-top: 15px;">Back to Dashboard</a>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $msg):
                $is_unread = empty($msg['is_read']);
                $title = htmlspecialchars(fix_utf8_encoding($msg['title'] ?? '(No subject)'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $content = htmlspecialchars(fix_utf8_encoding($msg['content'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $date = date('j M Y, g:i a', strtotime($msg['created_at'] ?? 'now'));
                $sender = htmlspecialchars($msg['sender_email'] ?? 'School');
                $msg_id = (int)$msg['id'];
            ?>
                <div class="card <?php echo $is_unread ? 'unread' : ''; ?>" data-msg-id="<?php echo $msg_id; ?>">
                    <div class="msg-header" onclick="toggleMsg(this, <?php echo $msg_id; ?>)">
                        <div class="msg-title">
                            <?php if ($is_unread): ?>
                                <span class="unread-dot"></span>
                            <?php endif; ?>
                            <h4><?php echo $title; ?></h4>
                        </div>
                        <span class="msg-date"><?php echo $date; ?></span>
                    </div>
                    <div class="msg-body">
                        <p><?php echo nl2br($content); ?></p>
                        <p class="sender">From: <?php echo $sender; ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 15px;">
            <a href="../parent/dashboard.php" class="btn"><i class="fas fa-home"></i> Back to Dashboard</a>
        </div>
    </div>
</div>

    <script>
        function toggleMsg(header, msgId) {
            var body = header.nextElementSibling;
            var card = header.closest('.card');
            if (body) {
                body.classList.toggle('open');

                // If message was unread and is now being opened, mark as read
                if (card && card.classList.contains('unread')) {
                    card.classList.remove('unread');
                    var dot = header.querySelector('.unread-dot');
                    if (dot) dot.style.display = 'none';

                    // Update unread count in header
                    var badge = document.querySelector('.page-header .badge');
                    if (badge) {
                        var count = parseInt(badge.textContent);
                        if (count > 1) {
                            badge.textContent = (count - 1) + ' unread';
                        } else {
                            badge.remove();
                        }
                    }

                    // AJAX: mark message as read on the server
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '../mark_message_read.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.send('message_id=' + msgId);
                }
            }
        }
    </script>
</body>
</html>
