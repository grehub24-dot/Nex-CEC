<?php
require_once 'includes/db.php';

if (!isLoggedIn() || !isParent()) {
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

// Mark unread as read (bridge cannot handle NOW() or IS NULL in SET/WHERE)
try {
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE messages SET read_at = ? WHERE receiver_id = ?");
    $stmt->execute([$now, $parent_user_id]);
} catch (Exception $e) {}

// Count unread
$unread_count = 0;
foreach ($messages as $m) {
    if (empty($m['read_at'])) $unread_count++;
}
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
        .top-bar {
            background: #1a5276; color: white; padding: 15px 30px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .top-bar a { color: white; text-decoration: none; font-size: 14px; }
        .top-bar a:hover { text-decoration: underline; }
        .container { max-width: 800px; margin: 0 auto; padding: 30px 20px; }
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
    <div class="top-bar">
        <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <span>Parent Portal — <?php echo htmlspecialchars($school_name); ?></span>
    </div>

    <div class="container">
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
                <a href="dashboard.php" class="btn" style="margin-top: 15px;">Back to Dashboard</a>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $msg):
                $is_unread = empty($msg['read_at']);
                $title = htmlspecialchars($msg['title'] ?? '(No subject)');
                $content = htmlspecialchars($msg['content'] ?? '');
                $date = date('j M Y, g:i a', strtotime($msg['created_at'] ?? 'now'));
                $sender = htmlspecialchars($msg['sender_email'] ?? 'School');
            ?>
                <div class="card <?php echo $is_unread ? 'unread' : ''; ?>">
                    <div class="msg-header" onclick="toggleMsg(this)">
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
            <a href="dashboard.php" class="btn"><i class="fas fa-home"></i> Back to Dashboard</a>
        </div>
    </div>

    <script>
        function toggleMsg(header) {
            var body = header.nextElementSibling;
            if (body) {
                body.classList.toggle('open');
            }
        }
    </script>
</body>
</html>
