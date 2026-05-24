<?php
require_once 'includes/header.php';

// Rate limiting: max 3 submissions per 15 min per session
$rl_key = 'enroll_rate_limit';
if (!isset($_SESSION[$rl_key])) {
    $_SESSION[$rl_key] = ['count' => 0, 'window_start' => time()];
}
// Reset window after 15 minutes
if (time() - $_SESSION[$rl_key]['window_start'] > 900) {
    $_SESSION[$rl_key] = ['count' => 0, 'window_start' => time()];
}

$message = '';
$message_type = '';

$rateLimited = ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION[$rl_key]['count'] >= 3);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$rateLimited) {
    $parent_name = trim($_POST['parent_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $child_name = trim($_POST['child_name'] ?? '');
    $class_applying = trim($_POST['class_applying'] ?? '');
    $inquiry_message = trim($_POST['inquiry_message'] ?? '');

    if (empty($parent_name) || empty($email) || empty($phone) || empty($child_name) || empty($class_applying)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO enrollment_inquiries (parent_name, email, phone, child_name, class_applying, message) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$parent_name, $email, $phone, $child_name, $class_applying, $inquiry_message]);
            $_SESSION[$rl_key]['count']++;
            $message = 'Thank you, ' . htmlspecialchars($parent_name) . '! Your enrollment inquiry has been received. We will contact you shortly.';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Sorry, we could not process your request. Please try again later or contact us directly.';
            $message_type = 'error';
        }
    }
} elseif ($rateLimited) {
    $message = 'You have submitted too many inquiries. Please try again later.';
    $message_type = 'error';
}
?>

<!-- Hero Inner -->
<section class="hero-inner">
    <div class="container" style="text-align: center; position: relative; z-index: 2;">
        <span class="badge-pill badge-gold" style="margin-bottom: 16px;">Join Us</span>
        <h1 style="font-size: 2.8rem; color: #fff; margin-bottom: 12px;">Enroll at <?php echo htmlspecialchars($settings['school_name'] ?? 'Chariot Educational Complex'); ?></h1>
        <p style="color: rgba(255,255,255,0.8); font-size: 1.1rem; max-width: 650px; margin: 0 auto;">Give your child the gift of quality education in a nurturing, disciplined, and Godly environment. We are accepting applications for the upcoming academic year.</p>
    </div>
</section>

<!-- Why Choose Us -->
<section class="section">
    <div class="container">
        <div class="animate-on-scroll" style="text-align: center; max-width: 700px; margin: 0 auto 40px;">
            <h2 style="color: #003366; margin-bottom: 12px;">Why Choose Chariot Educational Complex?</h2>
            <p style="color: #888;">We are dedicated to developing well-rounded learners who excel academically and grow in character.</p>
        </div>
        <div class="stagger-children" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-bottom:48px;">
            <div style="background:#f8f9fa;border-radius:16px;padding:24px;text-align:center;border:1px solid rgba(255,204,0,0.15);">
                <div style="font-size:2.5rem;margin-bottom:12px;">📖</div>
                <h4 style="color:#003366;font-size:1rem;margin-bottom:6px;">Academic Excellence</h4>
                <p style="font-size:0.84rem;color:#888;margin:0;">Rigorous curriculum that builds strong foundations in literacy, numeracy, and critical thinking.</p>
            </div>
            <div style="background:#f8f9fa;border-radius:16px;padding:24px;text-align:center;border:1px solid rgba(255,204,0,0.15);">
                <div style="font-size:2.5rem;margin-bottom:12px;">🙏</div>
                <h4 style="color:#003366;font-size:1rem;margin-bottom:6px;">Moral & Spiritual Growth</h4>
                <p style="font-size:0.84rem;color:#888;margin:0;">Godly environment that nurtures character, values, and respect for self and others.</p>
            </div>
            <div style="background:#f8f9fa;border-radius:16px;padding:24px;text-align:center;border:1px solid rgba(255,204,0,0.15);">
                <div style="font-size:2.5rem;margin-bottom:12px;">👩‍🏫</div>
                <h4 style="color:#003366;font-size:1rem;margin-bottom:6px;">Dedicated Teachers</h4>
                <p style="font-size:0.84rem;color:#888;margin:0;">Caring, qualified educators committed to each child's success and personal development.</p>
            </div>
            <div style="background:#f8f9fa;border-radius:16px;padding:24px;text-align:center;border:1px solid rgba(255,204,0,0.15);">
                <div style="font-size:2.5rem;margin-bottom:12px;">⚽</div>
                <h4 style="color:#003366;font-size:1rem;margin-bottom:6px;">Holistic Development</h4>
                <p style="font-size:0.84rem;color:#888;margin:0;">Sports, clubs, and creative arts that develop talents beyond the classroom.</p>
            </div>
        </div>
    </div>
</section>

<!-- Admission Process -->
<section class="section section-gold">
    <div class="container">
        <div class="animate-on-scroll" style="text-align: center; margin-bottom: 40px;">
            <h2 style="color: #003366; margin-bottom: 12px;">Admission Process</h2>
            <p style="color: #003366; opacity: 0.8;">Getting started is easy. Follow these simple steps to enroll your child.</p>
        </div>
        <div class="stagger-children" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;">
            <div style="text-align:center;">
                <div style="width:60px;height:60px;border-radius:50%;background:#003366;color:#ffcc00;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;margin:0 auto 12px;">1</div>
                <h4 style="color:#003366;font-size:0.95rem;">Inquiry</h4>
                <p style="font-size:0.82rem;color:#003366;opacity:0.7;margin:0;">Submit your interest using the form below</p>
            </div>
            <div style="text-align:center;">
                <div style="width:60px;height:60px;border-radius:50%;background:#003366;color:#ffcc00;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;margin:0 auto 12px;">2</div>
                <h4 style="color:#003366;font-size:0.95rem;">School Visit</h4>
                <p style="font-size:0.82rem;color:#003366;opacity:0.7;margin:0;">Tour our facilities and meet our staff</p>
            </div>
            <div style="text-align:center;">
                <div style="width:60px;height:60px;border-radius:50%;background:#003366;color:#ffcc00;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;margin:0 auto 12px;">3</div>
                <h4 style="color:#003366;font-size:0.95rem;">Application</h4>
                <p style="font-size:0.82rem;color:#003366;opacity:0.7;margin:0;">Complete admission forms and submit documents</p>
            </div>
            <div style="text-align:center;">
                <div style="width:60px;height:60px;border-radius:50%;background:#003366;color:#ffcc00;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;margin:0 auto 12px;">4</div>
                <h4 style="color:#003366;font-size:0.95rem;">Enrollment</h4>
                <p style="font-size:0.82rem;color:#003366;opacity:0.7;margin:0;">Welcome to the Chariot family!</p>
            </div>
        </div>
    </div>
</section>

<!-- Enrollment Form -->
<section class="section">
    <div class="container">
        <div class="animate-on-scroll" style="max-width: 700px; margin: 0 auto;">
            <h2 style="color: #003366; margin-bottom: 8px; text-align: center;">Submit Your Inquiry</h2>
            <p style="color: #888; text-align: center; margin-bottom: 32px;">Fill out the form below and our admissions team will get back to you.</p>

            <?php if (!empty($message)): ?>
            <div style="padding:16px 20px;border-radius:12px;margin-bottom:24px;<?php echo $message_type === 'success' ? 'background:#d4edda;color:#155724;border:1px solid #c3e6cb;' : 'background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;'; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" style="background:#fff;border-radius:20px;padding:32px;box-shadow:0 8px 32px rgba(0,51,102,0.08);border:1px solid rgba(255,204,0,0.1);">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div style="grid-column:1/-1;">
                        <label style="display:block;font-weight:600;color:#003366;margin-bottom:6px;font-size:0.9rem;">Parent/Guardian Full Name <span style="color:#e63946;">*</span></label>
                        <input type="text" name="parent_name" required
                               style="width:100%;padding:12px 16px;border:2px solid #e0e0e0;border-radius:10px;font-size:0.95rem;transition:border-color 0.3s;outline:none;"
                               onfocus="this.style.borderColor='#003366'" onblur="this.style.borderColor='#e0e0e0'"
                               placeholder="Enter your full name">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;color:#003366;margin-bottom:6px;font-size:0.9rem;">Email Address <span style="color:#e63946;">*</span></label>
                        <input type="email" name="email" required
                               style="width:100%;padding:12px 16px;border:2px solid #e0e0e0;border-radius:10px;font-size:0.95rem;transition:border-color 0.3s;outline:none;"
                               onfocus="this.style.borderColor='#003366'" onblur="this.style.borderColor='#e0e0e0'"
                               placeholder="your@email.com">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;color:#003366;margin-bottom:6px;font-size:0.9rem;">Phone Number <span style="color:#e63946;">*</span></label>
                        <input type="tel" name="phone" required
                               style="width:100%;padding:12px 16px;border:2px solid #e0e0e0;border-radius:10px;font-size:0.95rem;transition:border-color 0.3s;outline:none;"
                               onfocus="this.style.borderColor='#003366'" onblur="this.style.borderColor='#e0e0e0'"
                               placeholder="+233 XX XXX XXXX">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;color:#003366;margin-bottom:6px;font-size:0.9rem;">Child's Full Name <span style="color:#e63946;">*</span></label>
                        <input type="text" name="child_name" required
                               style="width:100%;padding:12px 16px;border:2px solid #e0e0e0;border-radius:10px;font-size:0.95rem;transition:border-color 0.3s;outline:none;"
                               onfocus="this.style.borderColor='#003366'" onblur="this.style.borderColor='#e0e0e0'"
                               placeholder="Child's full name">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;color:#003366;margin-bottom:6px;font-size:0.9rem;">Class Applying For <span style="color:#e63946;">*</span></label>
                        <select name="class_applying" required
                                style="width:100%;padding:12px 16px;border:2px solid #e0e0e0;border-radius:10px;font-size:0.95rem;transition:border-color 0.3s;outline:none;background:#fff;"
                                onfocus="this.style.borderColor='#003366'" onblur="this.style.borderColor='#e0e0e0'">
                            <option value="">Select a class</option>
                            <option value="Nursery 1">Nursery 1</option>
                            <option value="Nursery 2">Nursery 2</option>
                            <option value="Kindergarten 1">Kindergarten 1</option>
                            <option value="Kindergarten 2">Kindergarten 2</option>
                            <option value="Class 1">Class 1</option>
                            <option value="Class 2">Class 2</option>
                            <option value="Class 3">Class 3</option>
                            <option value="Class 4">Class 4</option>
                            <option value="Class 5">Class 5</option>
                            <option value="Class 6">Class 6</option>
                        </select>
                    </div>
                    <div style="grid-column:1/-1;">
                        <label style="display:block;font-weight:600;color:#003366;margin-bottom:6px;font-size:0.9rem;">Message (Optional)</label>
                        <textarea name="inquiry_message" rows="4"
                                  style="width:100%;padding:12px 16px;border:2px solid #e0e0e0;border-radius:10px;font-size:0.95rem;transition:border-color 0.3s;outline:none;resize:vertical;font-family:inherit;"
                                  onfocus="this.style.borderColor='#003366'" onblur="this.style.borderColor='#e0e0e0'"
                                  placeholder="Any additional information or questions..."></textarea>
                    </div>
                </div>
                <button type="submit" class="btn-gold" style="width:100%;justify-content:center;margin-top:20px;padding:16px;">
                    Submit Inquiry →
                </button>
            </form>

            <div style="text-align:center;margin-top:24px;padding:16px;background:#f8f9fa;border-radius:12px;">
                <p style="font-size:0.85rem;color:#666;margin:0;">
                    📞 Prefer to call? Contact us directly and we will be happy to assist you with the enrollment process.
                </p>
            </div>
        </div>
    </div>
</section>

<script>
(function() {
    var els = document.querySelectorAll('.animate-on-scroll, .stagger-children');
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    els.forEach(function(el) { observer.observe(el); });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
