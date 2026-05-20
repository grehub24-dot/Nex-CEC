<?php
require_once 'includes/header.php';

// Fetch alumni
$alumni = [];
try {
    $result = $pdo->query("SELECT id, full_name, image_url, graduation_year, position, company, testimonial FROM alumni ORDER BY graduation_year DESC");
    if ($result && $result->rowCount() > 0) {
        $alumni = $result->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Alumni table may not exist yet
}
?>

<!-- Hero Inner -->
<section class="hero-inner">
    <div class="container" style="text-align: center; position: relative; z-index: 2;">
        <span class="badge-pill badge-gold" style="margin-bottom: 16px;">Our Heritage</span>
        <h1 style="font-size: 2.8rem; color: #fff; margin-bottom: 12px;">Past Students</h1>
        <p style="color: rgba(255,255,255,0.8); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">Our past students are the pride of Chariot Educational Complex. Stay connected, share your story, and inspire the next generation.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (!empty($alumni)): ?>
        <!-- Alumni Stats -->
        <div class="animate-on-scroll" style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:48px;text-align:center;">
            <div style="background:#f8f9fa;border-radius:16px;padding:24px 16px;">
                <div style="font-size:2rem;font-weight:800;color:#003366;"><?php echo count($alumni); ?></div>
                <div style="font-size:0.85rem;color:#888;">Past Students</div>
            </div>
            <div style="background:#f8f9fa;border-radius:16px;padding:24px 16px;">
                <div style="font-size:2rem;font-weight:800;color:#003366;">
                    <?php 
                    $years = array_unique(array_column($alumni, 'graduation_year'));
                    echo count($years);
                    ?>
                </div>
                <div style="font-size:0.85rem;color:#888;">Graduating Classes</div>
            </div>
            <div style="background:#f8f9fa;border-radius:16px;padding:24px 16px;">
                <div style="font-size:2rem;font-weight:800;color:#003366;">🌍</div>
                <div style="font-size:0.85rem;color:#888;">Global Community</div>
            </div>
        </div>

        <!-- Alumni Grid -->
        <div class="stagger-children" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:24px;">
            <?php foreach ($alumni as $al): ?>
            <div class="card-premium" style="text-align:center;">
                <div style="padding:32px 24px 20px;">
                    <div style="width:100px;height:100px;border-radius:50%;margin:0 auto 16px;overflow:hidden;border:4px solid #ffcc00;box-shadow:0 4px 16px rgba(0,0,0,0.1);">
                        <img src="<?php echo htmlspecialchars($al['image_url'] ?? ''); ?>" 
                             alt="<?php echo htmlspecialchars($al['full_name']); ?>"
                             style="width:100%;height:100%;object-fit:cover;"
                             onerror="this.onerror=null;this.src='';this.parentElement.innerHTML='<div style=\"width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg,#003366,#004080);display:flex;align-items:center;justify-content:center;color:#ffcc00;font-size:2.5rem;font-weight:700;\">' + this.alt.charAt(0).toUpperCase() + '</div>';">
                    </div>
                    <h3 style="color:#003366;font-size:1.1rem;margin-bottom:4px;"><?php echo htmlspecialchars($al['full_name']); ?></h3>
                    <?php if (!empty($al['graduation_year'])): ?>
                    <span class="badge-pill badge-gold" style="margin-bottom:10px;">Class of <?php echo htmlspecialchars($al['graduation_year']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($al['position']) || !empty($al['company'])): ?>
                    <p style="color:#666;font-size:0.88rem;margin-top:8px;">
                        <?php echo htmlspecialchars($al['position'] ?? ''); ?>
                        <?php if (!empty($al['company'])): ?>
                        @ <?php echo htmlspecialchars($al['company']); ?>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                    <?php if (!empty($al['testimonial'])): ?>
                    <div style="margin-top:12px;padding:12px 16px;background:#f8f9fa;border-radius:10px;font-style:italic;font-size:0.84rem;color:#555;text-align:left;">
                        <span style="color:#ffcc00;font-size:1.2rem;">"</span>
                        <?php echo htmlspecialchars(substr($al['testimonial'], 0, 150)); ?>
                        <?php if (strlen($al['testimonial']) > 150) echo '...'; ?>
                        <span style="color:#ffcc00;font-size:1.2rem;">"</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- Empty State -->
        <div class="animate-on-scroll" style="text-align: center; padding: 40px 20px;">
            <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;">🎓</div>
            <h3 style="color: #003366; margin-bottom: 12px;">Past Students Network Coming Soon</h3>
            <p style="color: #888; max-width: 550px; margin: 0 auto 32px;">We are building our past students community! If you are a former student of Chariot Educational Complex, we would love to hear from you. Share your story and help us build a legacy of excellence.</p>
            
            <!-- Placeholder show of pride -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;max-width:700px;margin:0 auto;">
                <div style="background:#f5f5f5;border-radius:12px;padding:24px;text-align:center;">
                    <div style="font-size:2rem;margin-bottom:8px;">🤝</div>
                    <h4 style="color:#003366;font-size:0.95rem;margin-bottom:4px;">Stay Connected</h4>
                    <p style="font-size:0.82rem;color:#888;margin:0;">Join our past students directory and connect with former classmates.</p>
                </div>
                <div style="background:#f5f5f5;border-radius:12px;padding:24px;text-align:center;">
                    <div style="font-size:2rem;margin-bottom:8px;">💬</div>
                    <h4 style="color:#003366;font-size:0.95rem;margin-bottom:4px;">Share Your Story</h4>
                    <p style="font-size:0.82rem;color:#888;margin:0;">Inspire current students with your journey and achievements.</p>
                </div>
                <div style="background:#f5f5f5;border-radius:12px;padding:24px;text-align:center;">
                    <div style="font-size:2rem;margin-bottom:8px;">🎉</div>
                    <h4 style="color:#003366;font-size:0.95rem;margin-bottom:4px;">Give Back</h4>
                    <p style="font-size:0.82rem;color:#888;margin:0;">Mentor students or support school development projects.</p>
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
