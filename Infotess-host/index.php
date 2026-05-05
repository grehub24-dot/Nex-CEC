<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

// Fetch recent news/events for the homepage
$stmt = $pdo->query("SELECT * FROM activities ORDER BY activity_date DESC LIMIT 3");
$activities = $stmt->fetchAll();
?>

<!-- Hero Section -->
<section class="hero">
    <h1>Welcome to INFOTESS</h1>
    <p>Information Technology Students’ Society of USTED. Empowering students through technology, innovation, and leadership.</p>
    <a href="about.php" class="btn-cta">Learn More</a>
</section>

<!-- About Preview -->
<section class="section">
    <div class="container">
        <h2 class="section-title">Who We Are</h2>
        <div style="text-align: center; max-width: 800px; margin: 0 auto;">
            <p>INFOTESS is the official student body for the Department of Information Technology Education (DITE) at USTED. We are dedicated to bridging the gap between academic theory and industry practice through workshops, seminars, and collaborative projects.</p>
        </div>
    </div>
</section>

<!-- Activities/News -->
<section class="section" style="background: var(--light-bg);">
    <div class="container">
        <h2 class="section-title">Latest Activities</h2>
        <div class="card-grid">
            <?php if (count($activities) > 0): ?>
                <?php foreach ($activities as $activity): ?>
                <div class="card">
                    <img src="<?php echo !empty($activity['image_url']) ? $activity['image_url'] : 'images/default-activity.jpg'; ?>" alt="Activity">
                    <div class="card-content">
                        <h3 class="card-title"><?php echo htmlspecialchars($activity['title']); ?></h3>
                        <p><?php echo substr(htmlspecialchars($activity['description']), 0, 100) . '...'; ?></p>
                        <a href="activities.php" style="color: var(--primary-color); font-weight: bold; margin-top: 10px; display: inline-block;">Read More &rarr;</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Static placeholders if no DB data -->
                <div class="card">
                    <img src="images/aamusted.jpg" alt="Freshers Week Celebration" style="background:#ccc;">
                    <div class="card-content">
                        <span style="display:inline-block; font-size:0.8rem; color:#666; margin-bottom:8px;">Activity</span>
                        <h3 class="card-title">Freshers Week Celebration</h3>
                        <p>Welcome program for new students with orientation, networking, and onboarding activities.</p>
                        <a href="activities.php" style="color: var(--primary-color); font-weight: bold; margin-top: 10px; display: inline-block;">Read More &rarr;</a>
                    </div>
                </div>
                <div class="card">
                    <img src="images/infotess.png" alt="Community of Practice" style="background:#ccc;">
                    <div class="card-content">
                        <span style="display:inline-block; font-size:0.8rem; color:#666; margin-bottom:8px;">Activity</span>
                        <h3 class="card-title">Community of Practice</h3>
                        <p>Peer-led knowledge sharing sessions focused on practical skills and collaborative learning.</p>
                        <a href="activities.php" style="color: var(--primary-color); font-weight: bold; margin-top: 10px; display: inline-block;">Read More &rarr;</a>
                    </div>
                </div>
                <div class="card">
                    <img src="images/aamusted-logo.svg" alt="Infotess Cloud 9 Connection" style="background:#ccc;">
                    <div class="card-content">
                        <span style="display:inline-block; font-size:0.8rem; color:#666; margin-bottom:8px;">Activity</span>
                        <h3 class="card-title">Infotess Cloud 9 Connection: Chocolate + Photoshoot (Valentine)</h3>
                        <p>Valentine special social-tech event featuring community bonding, treats, and themed photoshoot moments.</p>
                        <a href="activities.php" style="color: var(--primary-color); font-weight: bold; margin-top: 10px; display: inline-block;">Read More &rarr;</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="section" style="text-align: center;">
    <div class="container">
        <h2>Ready to join the movement?</h2>
        <p style="margin: 20px 0;">Become an active member of INFOTESS today.</p>
        <a href="contact.php" class="btn-cta">Contact Us</a>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
