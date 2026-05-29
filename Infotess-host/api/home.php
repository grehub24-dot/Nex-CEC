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
$years_count = 4;
?>

<!-- Hero Section -->
<section class="hero-band">
    <div id="hero-3d-container" class="school-3d-container hero-3d"></div>
    <div class="hero-band-content">
        <h1 class="text-hero mb-sm">Welcome to <?php echo htmlspecialchars($school_name); ?></h1>
        <p class="hero-band-text">
            <?php echo htmlspecialchars($settings['school_motto'] ?? 'Excellence in Education'); ?>
            — Providing quality education from Creche through Junior High School in a safe, nurturing, and academically excellent environment.
        </p>
        <div class="hero-ctas">
            <a href="register.php" class="btn btn-accent btn-lg"><i class="fas fa-user-plus"></i> Enroll Now</a>
            <a href="contact.php" class="btn btn-secondary-on-dark btn-lg">Contact Us</a>
        </div>
        <div class="hero-band-meta">
            <span class="text-on-dark-muted"><i class="fas fa-calendar-check"></i> 18+ Years of Excellence</span>
            <span class="text-on-dark-muted"><i class="fas fa-chalkboard-teacher"></i> Dedicated Staff</span>
            <span class="text-on-dark-muted"><i class="fas fa-users"></i> Holistic Education</span>
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
                <p class="stat-label">Students Enrolled</p>
            </div>
            <div class="card-stat">
                <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <h3><?php echo number_format($staff_count); ?></h3>
                <p class="stat-label">Staff Members</p>
            </div>
            <div class="card-stat">
                <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
                <h3><?php echo $class_count; ?>+</h3>
                <p class="stat-label">Class Levels</p>
            </div>
            <div class="card-stat">
                <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                <h3><?php echo $years_count; ?>+</h3>
                <p class="stat-label">Years of Impact</p>
            </div>
        </div>
    </div>
</section>

<!-- What We Offer -->
<section class="section-block">
    <div class="container">
        <h2 class="text-h2 text-center mb-xs">What We Offer</h2>
        <p class="text-sm text-center max-w-sm mb-xl">
            Comprehensive educational programmes designed to nurture every child's potential from early childhood through junior high school.
        </p>
        <div class="grid-3 anim-stagger" id="featuresGrid">
            <div class="card-feature card-tint-peach">
                <div class="card-feature-icon"><i class="fas fa-baby icon-color-orange"></i></div>
                <h3 class="text-h3">Early Childhood</h3>
                <p>Creche, Nursery, and Kindergarten programmes designed to spark curiosity, creativity, and a lifelong love for learning.</p>
                <a href="about.php#early-childhood" class="btn-link">Learn More <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-feature card-tint-mint">
                <div class="card-feature-icon"><i class="fas fa-book-open icon-color-green"></i></div>
                <h3 class="text-h3">Primary Education</h3>
                <p>Basic 1 to 6 with a comprehensive curriculum covering core subjects, creative arts, ICT, and physical education.</p>
                <a href="about.php#primary" class="btn-link">Learn More <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-feature card-tint-soft-blue">
                <div class="card-feature-icon"><i class="fas fa-graduation-cap icon-color-primary"></i></div>
                <h3 class="text-h3">Junior High School</h3>
                <p>JHS 1 to 3 preparing students for the BECE with strong academics, practical skills, and character formation.</p>
                <a href="about.php#jhs" class="btn-link">Learn More <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</section>

<!-- About Preview -->
<section class="section-block surface-bg">
    <div class="container">
        <div class="split-layout">
            <div class="split-content">
                <span class="badge badge-primary mb-sm">Our School</span>
                <h2 class="text-h2 mb-md">Nurturing Excellence, Building Character</h2>
                <p><?php echo htmlspecialchars($school_name); ?> is a nurturing learning environment dedicated to building strong academic foundations, character development, and holistic growth for every child from Creche to JHS 3.</p>
                <p class="mb-lg">Our school follows the Ghana Education Service curriculum while fostering creativity, discipline, and a love for lifelong learning. We believe in partnering with parents to provide the best possible educational experience for every child.</p>
                <a href="about.php" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Learn More About Us</a>
            </div>
            <div class="split-media">
                <div id="about-3d-preview" class="school-3d-container content-3d"></div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="section-block">
    <div class="container">
        <h2 class="text-h2 text-center mb-xs">What Parents Say</h2>
        <p class="text-sm text-center max-w-sm mb-xl">
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
                        <strong class="testimonial-name">Parent of KG 2 Student</strong>
                        <span class="testimonial-role">Current Parent</span>
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
                        <strong class="testimonial-name">Parent of B4 Student</strong>
                        <span class="testimonial-role">Current Parent</span>
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
                        <strong class="testimonial-name">Parent of JHS Graduate</strong>
                        <span class="testimonial-role">Alumni Parent</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Banner -->
<section class="section-block surface-bg">
    <div class="container text-center max-w-md">
        <h2 class="text-h2 mb-sm">Enroll Your Child Today</h2>
        <p class="mb-xl">Give your child the best foundation for a bright future. Registration is now open for all levels — Creche through JHS 3.</p>
        <div class="cta-group">
            <a href="register.php" class="btn btn-accent btn-lg"><i class="fas fa-user-plus"></i> Enroll Now</a>
            <a href="contact.php" class="btn btn-secondary btn-lg"><i class="fas fa-phone-alt"></i> Contact Us</a>
        </div>
    </div>
</section>

<!-- Three.js 3D School Building -->
<script type="module">
    import { initScene } from '../js/school-3d.js';
    if (document.getElementById('hero-3d-container')) {
        initScene('hero-3d-container', 'school');
    }
</script>

<?php require_once 'includes/footer.php'; ?>
