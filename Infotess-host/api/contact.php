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

<!-- Hero Band -->
<div class="hero-band-narrow">
    <div class="hero-band-content">
        <h1 class="text-hero">Contact <?php echo htmlspecialchars($school_name); ?></h1>
        <p class="text-on-dark-muted hero-band-text">We'd love to hear from you. Get in touch with our team for any inquiries or to schedule a visit.</p>
    </div>
    <div id="contact-3d" class="school-3d-container content-3d"></div>
</div>

<section class="section-block">
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success alert-anim"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-anim"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card-grid card-grid-2 align-start">
            <!-- Contact Form -->
            <div class="card">
                <div class="card-content-xl">
                    <h3 class="card-title mb-lg"><i class="fas fa-paper-plane icon-color-primary"></i> Send us a Message</h3>
                    <form method="POST" action="">
                        <div class="card-grid card-grid-2 form-grid-gap">
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
                        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-paper-plane"></i> Send Message</button>
                    </form>
                </div>
            </div>

            <!-- Contact Info -->
            <div class="card">
                <div class="card-content-xl">
                    <h3 class="card-title mb-lg"><i class="fas fa-address-card icon-color-primary"></i> Contact Information</h3>

                    <div class="contact-info-item">
                        <i class="fas fa-map-marker-alt contact-icon"></i>
                        <div class="contact-detail">
                            <strong>Location</strong>
                            <p><?php echo nl2br(htmlspecialchars($settings['school_address'] ?? 'School Address, City, Ghana')); ?></p>
                        </div>
                    </div>

                    <div class="contact-info-item">
                        <i class="fas fa-phone-alt contact-icon"></i>
                        <div class="contact-detail">
                            <strong>Phone</strong>
                            <p><a href="tel:<?php echo htmlspecialchars($settings['school_phone'] ?? ''); ?>"><?php echo htmlspecialchars($settings['school_phone'] ?? '+233 XX XXX XXXX'); ?></a></p>
                        </div>
                    </div>

                    <div class="contact-info-item">
                        <i class="fas fa-envelope contact-icon"></i>
                        <div class="contact-detail">
                            <strong>Email</strong>
                            <p><a href="mailto:<?php echo htmlspecialchars($settings['school_email'] ?? ''); ?>"><?php echo htmlspecialchars($settings['school_email'] ?? 'info@school.edu.gh'); ?></a></p>
                        </div>
                    </div>

                    <div class="office-hours">
                        <h4 class="office-hours-title"><i class="fas fa-clock icon-color-primary"></i> Office Hours</h4>
                        <p class="office-hours-line">Monday – Friday: <strong>7:30 AM – 4:00 PM</strong></p>
                        <p class="office-hours-line">Saturday &amp; Sunday: <strong>Closed</strong></p>
                        <p class="office-hours-note"><i class="fas fa-info-circle"></i> We respond to messages within 24 hours during the school week.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Map / Location Section -->
<section class="section-block">
    <div class="container">
        <div class="card-grid card-grid-2 find-us-grid">
            <div class="find-us-image-wrap">
                <img src="images/students/students-group-2.jpg" alt="<?php echo htmlspecialchars($school_name); ?> campus life" loading="lazy" class="find-us-image">
            </div>
            <div class="find-us-info">
                <h2 class="section-title">Find Us</h2>
                <p class="find-us-desc">We are located in the heart of the community. Visit our campus to see our facilities and meet our team.</p>
                <div class="find-us-card">
                    <i class="fas fa-map-marked-alt find-us-icon"></i>
                    <p class="find-us-address"><?php echo htmlspecialchars($settings['school_address'] ?? 'School Address, City, Ghana'); ?></p>
                    <p class="find-us-hint">Interactive map available on Google Maps</p>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.form-grid-gap { gap: var(--space-md); }
.btn-block { width: 100%; }
.office-hours {
    border-top: 1px solid var(--color-hairline);
    padding-top: var(--space-lg);
    margin-top: var(--space-lg);
}
.office-hours-title {
    margin-bottom: var(--space-sm);
}
.office-hours-line {
    margin: 0;
    color: var(--color-slate);
    font-size: var(--text-sm-size);
}
.office-hours-note {
    margin-top: var(--space-md);
    font-size: var(--text-caption-size);
    color: var(--color-steel);
}
.find-us-grid { align-items: stretch; }
.find-us-image-wrap {
    min-height: 300px;
}
.find-us-image {
    width: 100%;
    min-height: 300px;
    object-fit: cover;
    border-radius: var(--radius-lg);
}
.find-us-info {
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.find-us-info .section-title { text-align: left; }
.find-us-desc {
    color: var(--color-slate);
    margin-bottom: var(--space-md);
}
.find-us-card {
    border-radius: var(--radius-lg);
    padding: var(--space-lg);
    background: var(--color-surface);
    border: 1px solid var(--color-hairline);
    text-align: center;
}
.find-us-icon {
    font-size: 2rem;
    color: var(--color-primary);
    margin-bottom: 10px;
    display: block;
}
.find-us-address {
    font-weight: 600;
    color: var(--color-charcoal);
    margin-bottom: 4px;
}
.find-us-hint {
    font-size: var(--text-sm-size);
    color: var(--color-steel);
    margin: 0;
}
</style>

<!-- 3D Envelope Scene (shared module) -->
<script type="module">
    import { initScene } from '../js/school-3d.js';
    if (document.getElementById('contact-3d')) {
        initScene('contact-3d', 'envelope');
    }
</script>

<?php require_once 'includes/footer.php'; ?>
