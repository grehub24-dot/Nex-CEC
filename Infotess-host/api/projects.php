<?php
require_once 'includes/header.php';

// Fetch projects
$projects = [];
try {
    $result = $pdo->query("SELECT id, title, description, image_url, status, start_date, end_date FROM projects ORDER BY start_date DESC");
    if ($result && $result->rowCount() > 0) {
        $projects = $result->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Table may not exist
}

function getStatusBadge($status) {
    $s = strtolower($status);
    if ($s === 'completed') return '<span class="badge-pill" style="background:#d4edda;color:#155724;">✅ Completed</span>';
    if ($s === 'ongoing' || $s === 'in progress') return '<span class="badge-pill" style="background:#cce5ff;color:#004085;">🔄 Ongoing</span>';
    if ($s === 'planned' || $s === 'upcoming') return '<span class="badge-pill" style="background:#fff3cd;color:#856404;">📅 Planned</span>';
    return '<span class="badge-pill" style="background:#f0f0f0;color:#666;">📌 ' . htmlspecialchars(ucfirst($status)) . '</span>';
}
?>

<!-- Hero Inner -->
<section class="hero-inner" style="background: linear-gradient(135deg, #002244 0%, #003366 50%, #004080 100%);">
    <div class="container" style="text-align: center; position: relative; z-index: 2;">
        <span class="badge-pill badge-gold" style="margin-bottom: 16px;">Building the Future</span>
        <h1 style="font-size: 2.8rem; color: #fff; margin-bottom: 12px;">School Projects</h1>
        <p style="color: rgba(255,255,255,0.8); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">Discover the development projects and initiatives underway at Chariot Educational Complex, aimed at improving our facilities and learning environment.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (!empty($projects)): ?>
        <div class="stagger-children" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:24px;">
            <?php foreach ($projects as $proj): ?>
            <div class="card-premium">
                <?php if (!empty($proj['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($proj['image_url']); ?>" alt="" class="card-image"
                     onerror="this.style.display='none';">
                <?php endif; ?>
                <div class="card-body">
                    <div style="margin-bottom: 10px;">
                        <?php echo getStatusBadge($proj['status'] ?? 'planned'); ?>
                    </div>
                    <h3 class="card-title"><?php echo htmlspecialchars($proj['title']); ?></h3>
                    <p class="card-text"><?php echo nl2br(htmlspecialchars($proj['description'])); ?></p>
                    <div style="margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f0;display:flex;gap:16px;font-size:0.82rem;color:#888;">
                        <?php if (!empty($proj['start_date'])): ?>
                        <span>📅 Started: <?php echo date('M Y', strtotime($proj['start_date'])); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($proj['end_date'])): ?>
                        <span>🏁 Ends: <?php echo date('M Y', strtotime($proj['end_date'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- Default Projects -->
        <div class="animate-on-scroll" style="text-align: center; margin-bottom: 32px;">
            <div style="font-size: 4rem; margin-bottom: 16px; opacity: 0.3;">🏗️</div>
            <h3 style="color: #003366; margin-bottom: 12px;">Projects & Developments</h3>
            <p style="color: #888; max-width: 600px; margin: 0 auto 32px;">Chariot Educational Complex is committed to continuous improvement. Here are some of our ongoing and planned development projects.</p>
        </div>

        <div class="stagger-children" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;">
            <div class="card-premium">
                <div style="background:linear-gradient(135deg,#003366,#004080);height:180px;display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:3rem;">📚</span>
                </div>
                <div class="card-body">
                    <span class="badge-pill" style="background:#fff3cd;color:#856404;margin-bottom:10px;">📅 Planned</span>
                    <h3 class="card-title">Library Expansion</h3>
                    <p class="card-text">Plans to expand our school library with more books, reading spaces, and digital learning resources to foster a strong reading culture.</p>
                </div>
            </div>

            <div class="card-premium">
                <div style="background:linear-gradient(135deg,#ffcc00,#ffd633);height:180px;display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:3rem;">⚽</span>
                </div>
                <div class="card-body">
                    <span class="badge-pill" style="background:#cce5ff;color:#004085;margin-bottom:10px;">🔄 Ongoing</span>
                    <h3 class="card-title">Playground Renovation</h3>
                    <p class="card-text">Renovating our playground to provide safe, modern play equipment and sports facilities for our students.</p>
                </div>
            </div>

            <div class="card-premium">
                <div style="background:linear-gradient(135deg,#002244,#003366);height:180px;display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:3rem;">💻</span>
                </div>
                <div class="card-body">
                    <span class="badge-pill" style="background:#fff3cd;color:#856404;margin-bottom:10px;">📅 Planned</span>
                    <h3 class="card-title">ICT Lab Setup</h3>
                    <p class="card-text">Establishing a modern computer laboratory to equip our students with essential digital skills for the 21st century.</p>
                </div>
            </div>

            <div class="card-premium">
                <div style="background:linear-gradient(135deg,#003366,#004080);height:180px;display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:3rem;">🚰</span>
                </div>
                <div class="card-body">
                    <span class="badge-pill" style="background:#d4edda;color:#155724;margin-bottom:10px;">✅ Completed</span>
                    <h3 class="card-title">Water & Sanitation</h3>
                    <p class="card-text">Improved water supply and sanitation facilities for a healthier, more comfortable learning environment.</p>
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
