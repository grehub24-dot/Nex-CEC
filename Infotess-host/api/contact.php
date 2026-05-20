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
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $subject = sanitize($_POST['subject'] ?? 'No Subject');
    $msg = sanitize($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($msg)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_submissions (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $subject, $msg]);
            $message = "Thank you for contacting us, $name! We have received your message and will get back to you shortly.";
        } catch (Exception $e) {
            $error = "Sorry, there was an error sending your message. Please try again later.";
        }
    }
}
?>

<!-- Hero Inner -->
<div class="hero-inner">
    <h1>Contact <?php echo htmlspecialchars($school_name); ?></h1>
    <p>We'd love to hear from you. Get in touch with our team for any inquiries or to schedule a visit.</p>
</div>

<section class="section">
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success" style="animation: fadeInDown 0.4s var(--ease-out-expo);"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger" style="animation: fadeInDown 0.4s var(--ease-out-expo);"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="contact-grid">
            <!-- Contact Form -->
            <div class="contact-form-card">
                <h3><i class="fas fa-paper-plane"></i> Send us a Message</h3>
                <form method="POST" action="">
                    <div class="grid-form">
                        <div class="form-group">
                            <label for="name" class="required">Full Name</label>
                            <input type="text" id="name" name="name" class="form-control" required placeholder="Your full name">
                        </div>
                        <div class="form-group">
                            <label for="email" class="required">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" required placeholder="your@email.com">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" class="form-control" placeholder="How can we help you?" value="General Inquiry">
                    </div>
                    <div class="form-group">
                        <label for="message" class="required">Your Message</label>
                        <textarea id="message" name="message" class="form-control" rows="6" required placeholder="Tell us more about your inquiry..."></textarea>
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%;"><i class="fas fa-paper-plane"></i> Send Message</button>
                </form>
            </div>

            <!-- Contact Info -->
            <div class="contact-info-card">
                <h3><i class="fas fa-address-card"></i> Contact Information</h3>

                <div class="contact-info-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <strong>Location</strong>
                        <span><?php echo nl2br(htmlspecialchars($settings['school_address'] ?? 'School Address, City, Ghana')); ?></span>
                    </div>
                </div>

                <div class="contact-info-item">
                    <i class="fas fa-phone-alt"></i>
                    <div>
                        <strong>Phone</strong>
                        <span><a href="tel:<?php echo htmlspecialchars($settings['school_phone'] ?? ''); ?>"><?php echo htmlspecialchars($settings['school_phone'] ?? '+233 XX XXX XXXX'); ?></a></span>
                    </div>
                </div>

                <div class="contact-info-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <strong>Email</strong>
                        <span><a href="mailto:<?php echo htmlspecialchars($settings['school_email'] ?? ''); ?>"><?php echo htmlspecialchars($settings['school_email'] ?? 'info@school.edu.gh'); ?></a></span>
                    </div>
                </div>

                <div class="contact-hours">
                    <h4><i class="fas fa-clock"></i> Office Hours</h4>
                    <p>Monday – Friday: <strong>7:30 AM – 4:00 PM</strong></p>
                    <p>Saturday & Sunday: <strong>Closed</strong></p>
                    <p style="margin-top: 10px; font-size: var(--text-xs); opacity: 0.75;"><i class="fas fa-info-circle"></i> We respond to messages within 24 hours during the school week.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Map / Location Section -->
<section class="section section-photo-bg section-photo-bg-contact" style="background: var(--color-gray-50); padding-top: var(--space-8); padding-bottom: var(--space-8);">
    <style>
        .section-photo-bg-contact::before {
            background-image: url('images/students/gallery-1.jpg');
        }
    </style>
    <div class="container">
        <div class="photo-split" style="align-items: stretch;">
            <div class="photo-split-image" style="min-height: 300px;">
                <img src="images/students/students-group-2.jpg" alt="<?php echo htmlspecialchars($school_name); ?> campus life" loading="lazy" style="min-height: 300px;">
            </div>
            <div class="photo-split-content" style="display: flex; flex-direction: column; justify-content: center;">
                <h2 style="font-size: var(--text-3xl); color: var(--color-primary); margin-bottom: var(--space-4);">Find Us</h2>
                <p style="color: var(--text-muted); margin-bottom: var(--space-4);">We are located in the heart of the community. Visit our campus to see our facilities and meet our team.</p>
                <div style="border-radius: var(--radius-lg); padding: var(--space-6); background: var(--color-gray-50); border: 1px solid var(--color-gray-200); text-align: center;">
                    <i class="fas fa-map-marked-alt" style="font-size: 2rem; color: var(--color-primary); margin-bottom: 10px; display: block;"></i>
                    <p style="font-weight: 600; color: var(--color-gray-700); margin-bottom: 4px;"><?php echo htmlspecialchars($settings['school_address'] ?? 'School Address, City, Ghana'); ?></p>
                    <p style="font-size: var(--text-sm); color: var(--text-muted);">Interactive map available on Google Maps</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
