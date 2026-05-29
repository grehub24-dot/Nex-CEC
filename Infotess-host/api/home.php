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

<div class="container">
    <!-- Hero Section -->
    <section class="hero">
        <h1 class="hero-title">Welcome to <?php echo htmlspecialchars($school_name); ?></h1>
        <p class="hero-subtitle">
            <?php echo htmlspecialchars($settings['school_motto'] ?? 'Excellence in Education'); ?>
            — Providing quality education from Creche through Junior High School in a safe, nurturing environment.
        </p>
        <div class="hero-ctas">
            <a href="register.php" class="btn btn-primary btn-lg"><i class="fas fa-user-plus"></i> Enroll Now</a>
            <a href="contact.php" class="btn btn-secondary btn-lg">Contact Us</a>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-8">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($student_count); ?></div>
                <div class="stat-label">Students Enrolled</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($staff_count); ?></div>
                <div class="stat-label">Staff Members</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $class_count; ?>+</div>
                <div class="stat-label">Class Levels</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $years_count; ?>+</div>
                <div class="stat-label">Years of Impact</div>
            </div>
        </div>
    </section>

    <!-- What We Offer -->
    <section class="py-8">
        <div class="page-header">
            <h2 class="page-title">What We Offer</h2>
            <p class="page-description">Comprehensive educational programmes for every child.</p>
        </div>
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-6);">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-baby" style="color: var(--color-primary-light);"></i> Early Childhood</h3>
                </div>
                <div class="card-body">
                    <p>Creche, Nursery, and Kindergarten programmes designed to spark curiosity and creativity.</p>
                </div>
                <div class="card-footer">
                    <a href="about.php#early-childhood" class="btn btn-secondary btn-sm">Learn More</a>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-book-open" style="color: var(--color-success);"></i> Primary Education</h3>
                </div>
                <div class="card-body">
                    <p>Basic 1 to 6 with comprehensive curriculum covering core subjects, creative arts, and ICT.</p>
                </div>
                <div class="card-footer">
                    <a href="about.php#primary" class="btn btn-secondary btn-sm">Learn More</a>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-graduation-cap" style="color: var(--color-primary);"></i> Junior High</h3>
                </div>
                <div class="card-body">
                    <p>JHS 1 to 3 preparing students for the BECE with strong academics and practical skills.</p>
                </div>
                <div class="card-footer">
                    <a href="about.php#jhs" class="btn btn-secondary btn-sm">Learn More</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="py-8" style="background-color: var(--color-bg-alt);">
        <div class="page-header">
            <h2 class="page-title">What Parents Say</h2>
        </div>
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-6);">
            <div class="testimonial-card">
                <div class="testimonial-quote">"The care and attention my child receives is outstanding. I've seen remarkable growth!"</div>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">A</div>
                    <div>
                        <span class="testimonial-name">Parent of KG 2 Student</span>
                        <span class="testimonial-role">Current Parent</span>
                    </div>
                </div>
            </div>
            <div class="testimonial-card">
                <div class="testimonial-quote">"The dedicated teachers and small class sizes make all the difference. My child loves school!"</div>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">M</div>
                    <div>
                        <span class="testimonial-name">Parent of B4 Student</span>
                        <span class="testimonial-role">Current Parent</span>
                    </div>
                </div>
            </div>
            <div class="testimonial-card">
                <div class="testimonial-quote">"Excellent BECE preparation. The moral foundation my child received is invaluable."</div>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">E</div>
                    <div>
                        <span class="testimonial-name">Parent of JHS Graduate</span>
                        <span class="testimonial-role">Alumni Parent</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Banner -->
    <section class="py-8">
        <div class="card" style="text-align: center; border-color: var(--color-primary-soft); background-color: var(--color-primary-soft);">
            <div class="card-body">
                <h2 class="card-title" style="color: var(--color-primary); margin-bottom: var(--space-4);">Enroll Your Child Today</h2>
                <p style="margin-bottom: var(--space-6);">Give your child the best foundation for a bright future. Registration is now open for all levels.</p>
                <div class="cta-group">
                    <a href="register.php" class="btn btn-primary btn-lg"><i class="fas fa-user-plus"></i> Enroll Now</a>
                    <a href="contact.php" class="btn btn-secondary btn-lg">Contact Us</a>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once 'includes/footer.php'; ?>
