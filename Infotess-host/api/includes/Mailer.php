<?php
// Load PHPMailer manually
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    // Gmail SMTP Configuration — read from environment variables
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;

    public function __construct() {
        $this->smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $this->smtpPort = (int)(getenv('SMTP_PORT') ?: 465);
        $this->smtpUsername = getenv('SMTP_USERNAME') ?: '';
        $this->smtpPassword = getenv('SMTP_PASSWORD') ?: '';
    }

    public function sendHTML($to, $subject, $html, $fromEmail = null, $fromName = 'School Admin') {
        // Save copy locally for reference (skip on read-only filesystem like Vercel)
        $filename = null;
        $dir = __DIR__ . '/../emails';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (is_writable($dir)) {
            $safeTo = preg_replace('/[^a-zA-Z0-9_\-\.@]/', '_', (string)$to);
            $filename = $dir . '/registration_' . date('Ymd_His') . '_' . $safeTo . '.html';
            @file_put_contents($filename, $html);
        }

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $this->smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->smtpUsername;
            $mail->Password   = $this->smtpPassword;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable implicit SSL encryption
            $mail->Port       = $this->smtpPort;
            
            // Bypass SSL verification for local WAMP testing
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Recipients
            $fromEmail = $fromEmail ?: $this->smtpUsername; // Use Gmail address as default sender
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->addReplyTo($this->smtpUsername, $fromName); // Where replies will go

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = strip_tags($html);

            $mail->send();
            return $filename; // Return filename on success like original

        } catch (Exception $e) {
            error_log("Gmail SMTP Email Error: {$mail->ErrorInfo}");
            return false;
        }
    }
}
?>
