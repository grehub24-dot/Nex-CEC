<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

// Fetch counts (individual queries — no JOINs per bridge rules)
$student_count = 0;
$staff_count = 0;
$class_count = 0;
$years_count = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as c FROM students WHERE status = 'active'");
    $student_count = (int)$stmt->fetchColumn();
} catch (Exception $e) {}
try {
    $stmt = $pdo->query("SELECT COUNT(*) as c FROM staff WHERE status = 'active'");
    $staff_count = (int)$stmt->fetchColumn();
} catch (Exception $e) {}
try {
    $stmt = $pdo->query("SELECT COUNT(*) as c FROM classes");
    $class_count = (int)$stmt->fetchColumn();
} catch (Exception $e) {}
// Graduating years (hard-coded to current + 3 for display)
$years_count = 4;
?>

<!-- Hero Section -->
<section class="hero-band" style="position: relative; overflow: hidden;">
    <!-- 3D School Building -->
    <div id="hero-3d-container" class="school-3d-container hero-3d"></div>

    <!-- Hero Content -->
    <div class="hero-band-content">
        <h1 class="text-hero" style="margin-bottom: var(--space-md);">Welcome to <?php echo htmlspecialchars($school_name); ?></h1>
        <p class="text-on-dark-muted hero-band-text">
            <?php echo htmlspecialchars($settings['school_motto'] ?? 'Excellence in Education'); ?> — Providing quality education from Creche through Junior High School in a safe, nurturing, and academically excellent environment.
        </p>
        <div style="display: flex; gap: var(--space-md); flex-wrap: wrap; justify-content: center; margin-bottom: var(--space-xxl);">
            <a href="register.php" class="btn btn-on-dark btn-lg"><i class="fas fa-user-plus"></i> Enroll Now</a>
            <a href="contact.php" class="btn btn-secondary-on-dark btn-lg">Contact Us</a>
        </div>
        <div class="hero-band-meta">
            <span class="text-on-dark-muted" style="font-size: 14px;"><i class="fas fa-calendar-check" style="color: var(--color-primary); margin-right: 6px;"></i> 18+ Years of Excellence</span>
            <span class="text-on-dark-muted" style="font-size: 14px;"><i class="fas fa-chalkboard-teacher" style="color: var(--color-primary); margin-right: 6px;"></i> Dedicated Staff</span>
            <span class="text-on-dark-muted" style="font-size: 14px;"><i class="fas fa-users" style="color: var(--color-primary); margin-right: 6px;"></i> Holistic Education</span>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-bar">
    <div class="container">
        <div class="grid-4 anim-stagger" id="statsGrid">
            <div class="card-stat">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <h3><?php echo number_format($student_count); ?></h3>
                <p class="text-sm" style="color: var(--color-steel); margin: 0;">Students Enrolled</p>
            </div>
            <div class="card-stat">
                <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <h3><?php echo number_format($staff_count); ?></h3>
                <p class="text-sm" style="color: var(--color-steel); margin: 0;">Staff Members</p>
            </div>
            <div class="card-stat">
                <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
                <h3><?php echo $class_count; ?>+</h3>
                <p class="text-sm" style="color: var(--color-steel); margin: 0;">Class Levels</p>
            </div>
            <div class="card-stat">
                <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                <h3><?php echo $years_count; ?>+</h3>
                <p class="text-sm" style="color: var(--color-steel); margin: 0;">Years of Impact</p>
            </div>
        </div>
    </div>
</section>

<!-- What We Offer -->
<section class="section-block">
    <div class="container">
        <h2 class="text-h2 text-center" style="margin-bottom: var(--space-xs);">What We Offer</h2>
        <p class="text-sm text-center" style="max-width: 600px; margin: 0 auto var(--space-xxl); color: var(--color-steel);">
            Comprehensive educational programmes designed to nurture every child's potential from early childhood through junior high school.
        </p>
        <div class="grid-3 anim-stagger" id="featuresGrid">
            <div class="card-feature card-tint-peach">
                <div style="font-size: 36px; margin-bottom: var(--space-md);"><i class="fas fa-baby" style="color: #e67e22;"></i></div>
                <h3 class="text-h3">Early Childhood</h3>
                <p class="text-sm" style="color: var(--color-charcoal);">Creche, Nursery, and Kindergarten programmes designed to spark curiosity, creativity, and a lifelong love for learning.</p>
                <a href="about.php#early-childhood" class="btn btn-link" style="margin-top: var(--space-sm);">Learn More <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-feature card-tint-mint">
                <div style="font-size: 36px; margin-bottom: var(--space-md);"><i class="fas fa-book-open" style="color: #27ae60;"></i></div>
                <h3 class="text-h3">Primary Education</h3>
                <p class="text-sm" style="color: var(--color-charcoal);">Basic 1 to 6 with a comprehensive curriculum covering core subjects, creative arts, ICT, and physical education.</p>
                <a href="about.php#primary" class="btn btn-link" style="margin-top: var(--space-sm);">Learn More <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-feature card-tint-lavender">
                <div style="font-size: 36px; margin-bottom: var(--space-md);"><i class="fas fa-graduation-cap" style="color: var(--color-primary);"></i></div>
                <h3 class="text-h3">Junior High School</h3>
                <p class="text-sm" style="color: var(--color-charcoal);">JHS 1 to 3 preparing students for the BECE with strong academics, practical skills, and character formation.</p>
                <a href="about.php#jhs" class="btn btn-link" style="margin-top: var(--space-sm);">Learn More <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</section>

<!-- About Preview -->
<section class="section-block" style="background: var(--color-surface);">
    <div class="container">
        <div class="split-layout">
            <div>
                <span class="badge badge-primary" style="margin-bottom: var(--space-sm);">Our School</span>
                <h2 class="text-h2" style="margin-bottom: var(--space-md);">Nurturing Excellence, Building Character</h2>
                <p><?php echo htmlspecialchars($school_name); ?> is a nurturing learning environment dedicated to building strong academic foundations, character development, and holistic growth for every child from Creche to JHS 3.</p>
                <p>Our school follows the Ghana Education Service curriculum while fostering creativity, discipline, and a love for lifelong learning. We believe in partnering with parents to provide the best possible educational experience for every child.</p>
                <a href="about.php" class="btn btn-primary" style="margin-top: var(--space-sm);"><i class="fas fa-arrow-right"></i> Learn More About Us</a>
            </div>
            <div style="text-align: center;">
                <!-- 3D Books -->
                <div id="about-3d-preview" class="school-3d-container content-3d" style="width: 100%; max-width: 400px; height: 300px; margin: 0 auto;"></div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="section-block">
    <div class="container">
        <h2 class="text-h2 text-center" style="margin-bottom: var(--space-xs);">What Parents Say</h2>
        <p class="text-sm text-center" style="max-width: 600px; margin: 0 auto var(--space-xxl); color: var(--color-steel);">
            Hear from our community of parents and guardians about their experience with our school.
        </p>
        <div class="grid-3 anim-stagger" id="testimonialsGrid">
            <div class="card-testimonial">
                <div class="testimonial-stars">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
                <blockquote class="testimonial-quote">"The care and attention my child receives at <?php echo htmlspecialchars($school_name); ?> is outstanding. I've seen remarkable growth in both academics and confidence."</blockquote>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">A</div>
                    <div>
                        <strong style="font-size: 14px;">Parent of KG 2 Student</strong>
                        <p style="font-size: 13px; color: var(--color-steel); margin: 0;">Current Parent</p>
                    </div>
                </div>
            </div>
            <div class="card-testimonial">
                <div class="testimonial-stars">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
                <blockquote class="testimonial-quote">"The dedicated teachers and small class sizes make all the difference. My child loves going to school every day!"</blockquote>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">M</div>
                    <div>
                        <strong style="font-size: 14px;">Parent of B4 Student</strong>
                        <p style="font-size: 13px; color: var(--color-steel); margin: 0;">Current Parent</p>
                    </div>
                </div>
            </div>
            <div class="card-testimonial">
                <div class="testimonial-stars">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
                <blockquote class="testimonial-quote">"Excellent preparation for the BECE. The academic standards are high, and the moral foundation my child received is invaluable."</blockquote>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">E</div>
                    <div>
                        <strong style="font-size: 14px;">Parent of JHS Graduate</strong>
                        <p style="font-size: 13px; color: var(--color-steel); margin: 0;">Alumni Parent</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Banner -->
<section class="section-block" style="background: var(--color-surface);">
    <div class="container" style="text-align: center; max-width: 700px;">
        <h2 class="text-h2" style="margin-bottom: var(--space-sm);">Enroll Your Child Today</h2>
        <p style="margin-bottom: var(--space-xl); color: var(--color-steel);">Give your child the best foundation for a bright future. Registration is now open for all levels — Creche through JHS 3.</p>
        <div style="display: flex; gap: var(--space-md); justify-content: center; flex-wrap: wrap;">
            <a href="register.php" class="btn btn-primary btn-lg"><i class="fas fa-user-plus"></i> Enroll Now</a>
            <a href="contact.php" class="btn btn-secondary btn-lg"><i class="fas fa-phone-alt"></i> Contact Us</a>
        </div>
    </div>
</section>

<!-- Three.js 3D School Building -->
<!-- 3D hero scene (shared module) -->
<script type="module">
    import { initScene } from '../js/school-3d.js';
    if (document.getElementById('hero-3d-container')) {
        initScene('hero-3d-container', 'school');
    }
</script>


<?php require_once 'includes/footer.php'; ?>
