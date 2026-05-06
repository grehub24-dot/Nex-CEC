<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $subject = sanitize($_POST['subject'] ?? 'No Subject');
    $msg = sanitize($_POST['message']);
    try {
        $stmt = $pdo->prepare("INSERT INTO contact_submissions (name, email, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $subject, $msg]);
        $message = "Thank you for contacting us, $name. We will get back to you shortly.";
    } catch (Exception $e) {
        $message = "Sorry, there was an error. Please try again later.";
    }
}
?>

<div class="hero" style="height: 40vh;">
    <h1>Contact <?php echo htmlspecialchars($school_name); ?></h1>
    <p>We'd love to hear from you. Get in touch with our team.</p>
</div>

<div class="section">
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
            <div class="card">
                <h3>Send us a Message</h3>
                <form method="POST" action="" style="margin-top: 20px;">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Your Message</label>
                        <textarea id="message" name="message" class="form-control" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn-primary">Send Message</button>
                </form>
            </div>
            
            <div class="card">
                <h3>Contact Information</h3>
                <div style="margin-top: 20px;">
                    <p><i class="fas fa-map-marker-alt"></i> <strong>Location:</strong><br>
                    <?php echo nl2br(htmlspecialchars($settings['school_address'] ?? 'School Address, City, Ghana')); ?></p>
                    
                    <p style="margin-top: 20px;"><i class="fas fa-phone"></i> <strong>Phone:</strong><br>
                    <?php echo htmlspecialchars($settings['school_phone'] ?? '+233 XX XXX XXXX'); ?></p>
                    
                    <p style="margin-top: 20px;"><i class="fas fa-envelope"></i> <strong>Email:</strong><br>
                    <?php echo htmlspecialchars($settings['school_email'] ?? 'info@school.edu.gh'); ?></p>
                    
                    <div style="margin-top: 30px;">
                        <h3>Office Hours</h3>
                        <p>Monday - Friday: 7:30 AM - 4:00 PM<br>
                        Saturday & Sunday: Closed</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
