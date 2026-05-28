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

<div id="contact-3d" class="school-3d-container content-3d" style="margin: var(--space-xxl) auto; width: 100%; max-width: 400px; height: 300px;"></div>

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

<!-- Three.js 3D Envelope Scene -->
<script type="importmap">
{
    "imports": {
        "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js"
    }
}
</script>
<script type="module">
import * as THREE from 'three';
(function initEnvelope() {
    const container = document.getElementById('contact-3d');
    if (!container) return;
    const w = container.offsetWidth || 400;
    const h = container.offsetHeight || 300;
    if (w < 100 || h < 100) return;
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(40, w / h, 0.1, 1000);
    camera.position.set(2, 1.2, 2.5);
    camera.lookAt(0, 0, 0);
    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setSize(w, h);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    container.appendChild(renderer.domElement);
    const ambient = new THREE.AmbientLight(0xffffff, 0.5);
    scene.add(ambient);
    const dir = new THREE.DirectionalLight(0xffffff, 0.8);
    dir.position.set(2, 4, 3);
    scene.add(dir);
    // Envelope body (box with slight opening)
    const envMat = new THREE.MeshPhongMaterial({ color: 0x5645d4, shininess: 20 });
    const body = new THREE.Mesh(new THREE.BoxGeometry(1.2, 0.7, 0.8), envMat);
    body.position.y = 0.35;
    scene.add(body);
    // Envelope flap (triangular shape using a cone with 3 sides)
    const flapMat = new THREE.MeshPhongMaterial({ color: 0x4534b3, shininess: 15 });
    const flap = new THREE.Mesh(new THREE.ConeGeometry(0.7, 0.15, 3), flapMat);
    flap.position.set(0, 0.78, -0.1);
    flap.rotation.x = 0.1;
    flap.rotation.y = Math.PI;
    scene.add(flap);
    // Seal dot
    const sealMat = new THREE.MeshPhongMaterial({ color: 0xffe8d4, emissive: 0xffcc80, emissiveIntensity: 0.2 });
    const seal = new THREE.Mesh(new THREE.CircleGeometry(0.08, 16), sealMat);
    seal.position.set(0, 0.78, 0.35);
    seal.rotation.x = -0.3;
    scene.add(seal);
    // Gentle floating animation
    let time = 0;
    function animate() {
        requestAnimationFrame(animate);
        time += 0.02;
        body.rotation.y += 0.004;
        flap.rotation.y += 0.004;
        body.position.y = 0.35 + Math.sin(time) * 0.04;
        flap.position.y = 0.78 + Math.sin(time) * 0.04;
        renderer.render(scene, camera);
    }
    animate();
    window.addEventListener('resize', function() {
        const w2 = container.offsetWidth || 400;
        const h2 = container.offsetHeight || 300;
        if (w2 < 100 || h2 < 100) return;
        camera.aspect = w2 / h2;
        camera.updateProjectionMatrix();
        renderer.setSize(w2, h2);
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
