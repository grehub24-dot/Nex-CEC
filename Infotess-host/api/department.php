<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

// Get school settings
$settings = fetchSettings($pdo);
$school_name = $settings['school_name'] ?? 'Nex CEC Basic School';
$school_section = $settings['school_section'] ?? 'Creche – JHS 3';

// Fetch staff list from DB (public profiles only)
$stmt = $pdo->query("SELECT id, full_name, position, email, phone FROM staff WHERE status = 'active' ORDER BY full_name ASC");
$staff_members = $stmt->fetchAll();
?>

<div class="container" style="padding: 40px 0;">
    <h1>About Our School</h1>

    <!-- School Overview -->
    <div class="department-content" style="margin-bottom: 50px;">
        <p>Welcome to <strong><?php echo htmlspecialchars($school_name); ?></strong>, a private basic school dedicated to providing quality education from Creche through to Junior High School (JHS 3).</p>
        <p>We believe in nurturing the whole child — academically, socially, and morally — by creating a safe, supportive, and engaging learning environment.</p>

        <h3>Our Mission</h3>
        <p>To provide high-quality basic education that develops confident, creative, and responsible learners who are well-prepared for secondary education and life.</p>

        <h3>Our Vision</h3>
        <p>To be a leading basic school that sets the standard for academic excellence and character formation in Ghana.</p>

        <h3>Our Core Values</h3>
        <ul>
            <li><strong>Excellence</strong> — We strive for the highest standards in teaching and learning.</li>
            <li><strong>Integrity</strong> — We build character through honesty, respect, and accountability.</li>
            <li><strong>Community</strong> — We partner with parents and guardians to support every child's growth.</li>
            <li><strong>Innovation</strong> — We embrace modern teaching methods and technology.</li>
        </ul>

        <h3>School Sections</h3>
        <ul>
            <li><strong>Creche</strong> — Early childcare and foundational learning (Ages 1–2)</li>
            <li><strong>Nursery</strong> — Kindergarten preparation (Ages 3–4)</li>
            <li><strong>Kindergarten (KG 1 & 2)</strong> — Early childhood education</li>
            <li><strong>Lower Primary (Class 1 – 3)</strong> — Building core literacy and numeracy</li>
            <li><strong>Upper Primary (Class 4 – 6)</strong> — Deepening knowledge and critical thinking</li>
            <li><strong>Junior High School (JHS 1 – 3)</strong> — Preparing for BECE and secondary education</li>
        </ul>
    </div>

    <!-- Staff Section -->
    <h2 class="section-title">Meet Our Staff</h2>
    <?php if (empty($staff_members)): ?>
        <div class="card" style="padding: 25px; text-align: center;">
            <p>Staff profiles are being updated. Check back soon!</p>
        </div>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($staff_members as $staff): ?>
                <a href="public_staff_profile.php?id=<?php echo urlencode($staff['id']); ?>" class="card" style="text-align: center; text-decoration: none; color: inherit; display: block;">
                    <div class="card-content" style="padding: 25px;">
                        <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--color-primary); color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 2rem;">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3 style="margin-bottom: 5px; font-size: 1.1rem;"><?php echo htmlspecialchars($staff['full_name']); ?></h3>
                        <p style="color: var(--secondary-color); font-weight: bold; font-size: 0.9rem;"><?php echo htmlspecialchars($staff['position'] ?? 'Staff'); ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
