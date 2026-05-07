<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

// Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';
$school_motto = $settings['school_motto'] ?? 'Excellence in Education';
?>

<!-- Hero Section -->
<section class="hero">
    <h1>Welcome to <?php echo htmlspecialchars($school_name); ?></h1>
    <p><?php echo htmlspecialchars($school_motto); ?> — Providing quality education from Creche through Junior High School.</p>
    <a href="enroll.php" class="btn-cta">Enroll Now</a>
</section>

<!-- About Preview -->
<section class="section">
    <div class="container">
        <h2 class="section-title">Our School</h2>
        <div style="text-align: center; max-width: 800px; margin: 0 auto;">
            <p><?php echo htmlspecialchars($school_name); ?> is a nurturing learning environment dedicated to building strong academic foundations, character development, and holistic growth for every child from Creche to JHS 3.</p>
        </div>
    </div>
</section>

<!-- What We Offer -->
<section class="section" style="background: var(--light-bg);">
    <div class="container">
        <h2 class="section-title">What We Offer</h2>
        <div class="card-grid">
            <div class="card" style="text-align:center;">
                <div style="padding: 30px 20px 10px;">
                    <i class="fas fa-baby" style="font-size: 48px; color: var(--primary-color);"></i>
                </div>
                <div class="card-content">
                    <h3 class="card-title">Early Childhood</h3>
                    <p>Creche, Nursery, and Kindergarten programs designed to spark curiosity and love for learning.</p>
                </div>
            </div>
            <div class="card" style="text-align:center;">
                <div style="padding: 30px 20px 10px;">
                    <i class="fas fa-book-reader" style="font-size: 48px; color: var(--primary-color);"></i>
                </div>
                <div class="card-content">
                    <h3 class="card-title">Primary Education</h3>
                    <p>Basic 1 to 6 with a comprehensive curriculum covering core subjects and creative arts.</p>
                </div>
            </div>
            <div class="card" style="text-align:center;">
                <div style="padding: 30px 20px 10px;">
                    <i class="fas fa-graduation-cap" style="font-size: 48px; color: var(--primary-color);"></i>
                </div>
                <div class="card-content">
                    <h3 class="card-title">Junior High School</h3>
                    <p>JHS 1 to 3 preparing students for the BECE with strong academic and life skills.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="section" style="text-align: center;">
    <div class="container">
        <h2>Enroll Your Child Today</h2>
        <p style="margin: 20px 0;">Give your child the best foundation for a bright future. Registration is now open.</p>
        <a href="enroll.php" class="btn-cta">Enroll Now</a>
        <a href="contact.php" class="btn-cta" style="background: transparent; color: var(--primary-color); border: 2px solid var(--primary-color); margin-left: 10px;">Contact Us</a>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
