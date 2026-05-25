<?php
/**
 * cron_birthday.php
 * 
 * Cron endpoint for auto-sending birthday greetings to parents.
 * Call this daily via a cron job / scheduled task:
 *   GET /api/cron_birthday.php?key=YOUR_CRON_SECRET
 * 
 * It checks for students whose birthday is today and sends
 * SMS and/or email to their parent/guardian based on settings.
 */

require_once 'includes/db.php';
require_once 'includes/SMSHelper.php';
require_once 'includes/Mailer.php';

// Simple key check to prevent abuse
$cron_key = getenv('CRON_SECRET') ?: 'change-me-to-a-random-string';
$request_key = $_GET['key'] ?? '';
if ($request_key !== $cron_key) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid or missing cron key. Set CRON_SECRET env var.']);
    exit;
}

// Fetch birthday settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load settings: ' . $e->getMessage()]);
    exit;
}

// Check if birthday greetings are enabled
if (($settings['birthday_greeting_enabled'] ?? '0') !== '1') {
    echo json_encode(['status' => 'disabled', 'message' => 'Birthday greetings are disabled.']);
    exit;
}

$sms_enabled = ($settings['birthday_sms_enabled'] ?? '1') === '1';
$email_enabled = ($settings['birthday_email_enabled'] ?? '1') === '1';
$template = $settings['birthday_greeting_template'] ?? 'Dear {parent_name}, we wish your child {student_name} a very happy birthday! They are turning {age} today. - {school_name}';
$school_name = $settings['school_name'] ?? 'Nex CEC';

if (!$sms_enabled && !$email_enabled) {
    echo json_encode(['status' => 'no_channels', 'message' => 'Both SMS and Email are disabled.']);
    exit;
}

// Find students whose birthday is today (MM-DD)
$today_md = date('m-d');

try {
    // Fetch all students with date_of_birth — filter in PHP for bridge compatibility
    $all_students = $pdo->query("SELECT id, full_name, date_of_birth, class_name, guardian_name, guardian_phone_primary, guardian_phone_emergency, guardian_email FROM students WHERE date_of_birth IS NOT NULL AND date_of_birth != ''")->fetchAll();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed: ' . $e->getMessage()]);
    exit;
}

$today_birthdays = [];
foreach ($all_students as $s) {
    $dob_ts = strtotime($s['date_of_birth']);
    if (!$dob_ts) continue;
    if (date('m-d', $dob_ts) === $today_md) {
        $today_birthdays[] = $s;
    }
}

if (empty($today_birthdays)) {
    echo json_encode(['status' => 'ok', 'message' => 'No birthdays today.', 'sent' => 0, 'failed' => 0]);
    exit;
}

$sent_count = 0;
$failed_count = 0;
$sms = $sms_enabled ? new SMSHelper() : null;
$mailer = $email_enabled ? new Mailer() : null;

foreach ($today_birthdays as $student) {
    $student_name = $student['full_name'] ?? 'Student';
    $parent_name = $student['guardian_name'] ?? 'Parent';
    $class_name = $student['class_name'] ?? '';
    $age = (int)date('Y') - (int)date('Y', strtotime($student['date_of_birth']));

    // Build message from template
    $message = str_replace(
        ['{student_name}', '{parent_name}', '{age}', '{class_name}', '{school_name}'],
        [$student_name, $parent_name, $age, $class_name, $school_name],
        $template
    );

    $student_ok = false;

    // Send SMS
    if ($sms_enabled && $sms) {
        $to = $student['guardian_phone_primary'] ?? '';
        if (!$to) {
            $to = $student['guardian_phone_emergency'] ?? '';
        }
        if ($to) {
            if ($sms->send($to, $message)) {
                $student_ok = true;
            } else {
                $failed_count++;
            }
        } else {
            $failed_count++;
        }
    }

    // Send Email
    if ($email_enabled && $mailer) {
        $parent_email = $student['guardian_email'] ?? '';
        if ($parent_email) {
            $subject = 'Happy Birthday ' . $student_name . '! - ' . $school_name;
            $html = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px;">';
            $html .= '<div style="background: white; padding: 30px; border-radius: 8px; text-align: center;">';
            $html .= '<div style="font-size: 48px; margin-bottom: 10px;">🎂</div>';
            $html .= '<h2 style="color: #1a5276; margin: 0 0 10px;">Happy Birthday!</h2>';
            $html .= '<p style="font-size: 16px; line-height: 1.6; color: #333;">' . nl2br(htmlspecialchars($message)) . '</p>';
            $html .= '<p style="font-size: 13px; color: #888; margin-top: 20px;">' . htmlspecialchars($school_name) . '</p>';
            $html .= '</div></div>';

            if ($mailer->sendHTML($parent_email, $subject, $html)) {
                $student_ok = true;
            } else {
                // Don't double-count if SMS already failed
                if (!$student_ok) {
                    $failed_count++;
                }
            }
        } else {
            if (!$student_ok) {
                $failed_count++;
            }
        }
    }

    if ($student_ok) {
        $sent_count++;
    }

    // Log the birthday greeting in the messages table
    try {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, title, content, is_broadcast) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            0, // system sender
            'Birthday Greeting: ' . $student_name,
            'Auto-sent birthday wish to ' . $parent_name . ' for ' . $student_name . ' (' . $age . ' years).',
            1
        ]);
    } catch (Exception $e) {
        // Non-critical — log but don't fail
        error_log("Birthday cron: failed to log message: " . $e->getMessage());
    }
}

// Update last run timestamp
try {
    $existing = $pdo->prepare("SELECT setting_key FROM system_settings WHERE setting_key = 'birthday_last_run'");
    $existing->execute(['birthday_last_run']);
    if ($existing->fetch()) {
        $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'birthday_last_run'")->execute([date('Y-m-d H:i:s')]);
    } else {
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('birthday_last_run', ?)")->execute([date('Y-m-d H:i:s')]);
    }
} catch (Exception $e) {
    // Non-critical
}

echo json_encode([
    'status' => 'ok',
    'message' => 'Birthday greetings processed.',
    'total_birthdays' => count($today_birthdays),
    'sent' => $sent_count,
    'failed' => $failed_count
]);
