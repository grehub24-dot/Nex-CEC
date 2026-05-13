<?php
/**
 * AJAX endpoint: Mark a single message as read.
 * Called via fetch() when a parent clicks to expand a message.
 * Uses the message_reads table for per-user read tracking.
 */
require_once 'includes/db.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Not authenticated');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$user_id = $_SESSION['user_id'];

if (!$message_id) {
    http_response_code(400);
    exit('Invalid message ID');
}

try {
    // Use message_reads table for per-user read tracking
    $stmt = $pdo->prepare("INSERT INTO message_reads (message_id, user_id, read_at) VALUES (?, ?, NOW()) ON CONFLICT (message_id, user_id) DO NOTHING");
    $stmt->execute([$message_id, $user_id]);
    echo 'ok';
} catch (Exception $e) {
    // Fallback for MySQL-compatible ON DUPLICATE KEY
    try {
        // Check if already read
        $stmt = $pdo->prepare("SELECT id FROM message_reads WHERE message_id = ? AND user_id = ?");
        $stmt->execute([$message_id, $user_id]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO message_reads (message_id, user_id, read_at) VALUES (?, ?, NOW())");
            $stmt->execute([$message_id, $user_id]);
        }
        echo 'ok';
    } catch (Exception $e2) {
        // Last resort fallback: update the messages table read_at directly
        $stmt = $pdo->prepare("UPDATE messages SET read_at = NOW() WHERE id = ? AND (receiver_id = ? OR is_broadcast = ?)");
        $stmt->execute([$message_id, $user_id, 1]);
        echo 'ok';
    }
}
