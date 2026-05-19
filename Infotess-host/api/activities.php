<?php
require_once 'includes/header.php';

// Fetch activities
$activities = [];
try {
    $result = $pdo->query("SELECT id, title, description, image_url, activity_date, registration_link FROM activities ORDER BY activity_date DESC");
    if ($result && $result->rowCount() > 0) {
        $activities = $result->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Activities table may not exist yet
}
?>

<!-- Hero Inner -->
<section class="hero-inner" style="background: linear-gradient(135deg, #002244 0%, #003366 50%, #004080 100%);">
    <div class="container" style="text-align: center; position: relative; z-index: 2;">
        <span class="badge-pill badge-gold" style="margin-bottom: 16px;">Beyond the Classroom</span>
        <h1 style="font-size: 2.8rem; color: #fff; margin-bottom: 12px;">Our Activities</h1>
        <p style="color: rgba(255,255,255,0.8); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">At Chariot Educational Complex, learning extends beyond the classroom. Discover the clubs, sports, and programs that shape well-rounded students.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (!empty($activities)): ?>
        <div class="stagger-children" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:24px;">
            <?php foreach ($activities as $act): ?>
            <div class="card-premium">
                <?php if (!empty($act['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($act['image_url']); ?>" alt="" class="card-image"
                     onerror="this.style.display='none';">
                <?php endif; ?>
                <div class="card-body">
                    <?php if (!empty($act['activity_date'])): ?>
                    <div class="card-date">
                        <span>📅</span> <?php echo date('F d, Y', strtotime($act['activity_date'])); ?>
                    </div>
                    <?php endif; ?>
                    <h3 class="card-title"><?php echo htmlspecialchars($act['title']); ?></h3>
                    <p class="card-text"><?php echo nl2br(htmlspecialchars($act['description'])); ?></p>
                    <?php if (!empty($act['registration_link'])): ?>
                    <a href="<?php echo htmlspecialchars($act['registration_link']); ?>" class="btn-gold" style="margin-top:12px;padding:10px 24px;font-size:0.85rem;display:inline-flex;">
                        Register Now →
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- Default activity cards -->
        <div class="animate-on-scroll" style="text-align:center;margin-bottom:32px;">
            <h2 style="color:#003366;">Our Core Programs</h2>
            <p style="color:#888;max-width:600px;margin:8px auto 0;">Every student at Chariot Educational Complex is encouraged to participate in these enriching activities.</p>
        </div>
        <div class="stagger-children" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:24px;">
            <!-- Sports -->
            <div class="card-premium">
                <div style="background:linear-gradient(135deg,#003366,#004080);height:200px;display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:4rem;">⚽</span>
                </div>
                <div class="card-body">
                    <span class="badge-pill badge-gold" style="margin-bottom:10px;">Physical Development</span>
                    <h3 class="card-title">Sports & Athletics</h3>
                    <p class="card-text">We believe in nurturing healthy bodies alongside healthy minds. Our sports program includes football (soccer), athletics, and recreational games that teach teamwork, discipline, and sportsmanship.</p>
                    <ul style="padding-left:18px;font-size:0.88rem;color:#666;line-height:1.8;">
                        <li>Football (Soccer)</li>
                        <li>Track & Field Athletics</li>
                        <li>Indoor Games & Recreation</li>
                    </ul>
                </div>
            </div>

            <!-- Clubs -->
            <div class="card-premium">
                <div style="background:linear-gradient(135deg,#ffcc00,#ffd633);height:200px;display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:4rem;">📚</span>
                </div>
                <div class="card-body">
                    <span class="badge-pill badge-navy" style="margin-bottom:10px;">Personal Growth</span>
                    <h3 class="card-title">Clubs & Societies</h3>
                    <p class="card-text">Our clubs help students discover passions, build leadership skills, and develop lifelong friendships. From debate to cultural arts, there's something for every learner.</p>
                    <ul style="padding-left:18px;font-size:0.88rem;color:#666;line-height:1.8;">
                        <li>Debate & Public Speaking Club</li>
                        <li>Cultural & Arts Society</li>
                        <li>Science & Discovery Club</li>
                    </ul>
                </div>
            </div>

            <!-- Music -->
            <div class="card-premium">
                <div style="background:linear-gradient(135deg,#002244,#003366);height:200px;display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:4rem;">🎵</span>
                </div>
                <div class="card-body">
                    <span class="badge-pill badge-gold" style="margin-bottom:10px;">Creative Expression</span>
                    <h3 class="card-title">Music & Performing Arts</h3>
                    <p class="card-text">Creative expression is central to a well-rounded education. Our music and performing arts program helps students build confidence, creativity, and cultural appreciation.</p>
                    <ul style="padding-left:18px;font-size:0.88rem;color:#666;line-height:1.8;">
                        <li>Choir & Vocal Training</li>
                        <li>Drama & Performances</li>
                        <li>Cultural Dance & Traditions</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
