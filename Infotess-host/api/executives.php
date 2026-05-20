<?php
require_once 'includes/header.php';

// Try to fetch from executives table
$executives = [];
try {
    $result = $pdo->query("SELECT id, full_name, position, image_url, bio FROM executives ORDER BY id ASC");
    if ($result && $result->rowCount() > 0) {
        $executives = $result->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Table may not exist
}
?>

<!-- Hero Inner -->
<section class="hero-inner">
    <div class="container" style="text-align: center; position: relative; z-index: 2;">
        <span class="badge-pill badge-gold" style="margin-bottom: 16px;">Leadership</span>
        <h1 style="font-size: 2.8rem; color: #fff; margin-bottom: 12px;">Our Leadership & Structure</h1>
        <p style="color: rgba(255,255,255,0.8); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">Meet the dedicated leaders guiding Chariot Educational Complex toward excellence in education and character formation.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (!empty($executives)): ?>
        <div class="animate-on-scroll" style="margin-bottom: 48px;">
            <h2 style="color: #003366; margin-bottom: 24px; text-align: center;">School Leadership</h2>
            <div class="stagger-children" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:24px;">
                <?php foreach ($executives as $ex): ?>
                <div class="card-premium" style="text-align:center;">
                    <div style="padding:32px 24px 20px;">
                        <div style="width:120px;height:120px;border-radius:50%;margin:0 auto 16px;overflow:hidden;border:4px solid #ffcc00;">
                            <img src="<?php echo htmlspecialchars($ex['image_url'] ?? ''); ?>" 
                                 alt="<?php echo htmlspecialchars($ex['full_name']); ?>"
                                 style="width:100%;height:100%;object-fit:cover;"
                                 onerror="this.onerror=null;this.parentElement.innerHTML='<div style=\"width:120px;height:120px;border-radius:50%;background:linear-gradient(135deg,#003366,#004080);display:flex;align-items:center;justify-content:center;color:#ffcc00;font-size:3rem;font-weight:700;\">' + (this.alt ? this.alt.charAt(0).toUpperCase() : 'L') + '</div>';">
                        </div>
                        <h3 style="color:#003366;font-size:1.15rem;margin-bottom:4px;"><?php echo htmlspecialchars($ex['full_name']); ?></h3>
                        <span class="badge-pill badge-gold" style="margin-bottom:10px;"><?php echo htmlspecialchars($ex['position']); ?></span>
                        <?php if (!empty($ex['bio'])): ?>
                        <p style="font-size:0.85rem;color:#666;margin-top:8px;"><?php echo htmlspecialchars($ex['bio']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <!-- Organizational Chart -->
        <div class="animate-on-scroll" style="margin-bottom: 48px;">
            <h2 style="color: #003366; margin-bottom: 24px; text-align: center;">Organizational Structure</h2>
            
            <div class="org-chart" style="max-width: 800px; margin: 0 auto;">
                <!-- Level 0: Proprietor -->
                <div class="org-level">
                    <div class="org-node level-0">
                        <div style="font-size:2rem;margin-bottom:6px;">👑</div>
                        <h4>Proprietor</h4>
                        <p>Founder & Owner</p>
                    </div>
                </div>
                
                <div class="org-connector"></div>

                <!-- Level 1: Manager + Board -->
                <div class="org-level">
                    <div class="org-node level-1">
                        <div style="font-size:1.5rem;margin-bottom:4px;">👤</div>
                        <h4>School Manager</h4>
                        <p>Day-to-Day Operations</p>
                    </div>
                    <div class="org-node level-1">
                        <div style="font-size:1.5rem;margin-bottom:4px;">📋</div>
                        <h4>Board of Directors</h4>
                        <p>Strategic Oversight</p>
                    </div>
                </div>

                <div class="org-connector"></div>

                <!-- Level 2: Staff + Teachers -->
                <div class="org-level">
                    <div class="org-node level-2">
                        <div style="font-size:1.5rem;margin-bottom:4px;">🧹</div>
                        <h4>Cleaning Staff</h4>
                        <p>Campus Maintenance</p>
                    </div>
                    <div class="org-node level-2">
                        <div style="font-size:1.5rem;margin-bottom:4px;">🍳</div>
                        <h4>Kitchen Staff</h4>
                        <p>Meals & Nutrition</p>
                    </div>
                    <div class="org-node level-2">
                        <div style="font-size:1.5rem;margin-bottom:4px;">👩‍🏫</div>
                        <h4>Teaching Staff</h4>
                        <p>Academic Delivery</p>
                    </div>
                </div>

                <div class="org-connector"></div>

                <!-- Level 3: Categories -->
                <div class="org-level">
                    <div class="org-node level-3">
                        <h4 style="font-size:0.85rem;">Support Services</h4>
                    </div>
                    <div class="org-node level-3">
                        <h4 style="font-size:0.85rem;">Catering</h4>
                    </div>
                    <div class="org-node level-3">
                        <h4 style="font-size:0.85rem;">Academic Departments</h4>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Core Values Section -->
        <div class="animate-on-scroll" style="margin-top: 48px; text-align: center;">
            <h2 style="color: #003366; margin-bottom: 24px;">Our Guiding Values</h2>
            <div class="stagger-children" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;max-width:800px;margin:0 auto;">
                <div style="background:#f8f9fa;border-radius:16px;padding:24px 16px;border:1px solid rgba(255,204,0,0.15);">
                    <div style="font-size:2rem;margin-bottom:8px;">⭐</div>
                    <h4 style="color:#003366;font-size:0.95rem;margin:0;">Excellence</h4>
                </div>
                <div style="background:#f8f9fa;border-radius:16px;padding:24px 16px;border:1px solid rgba(255,204,0,0.15);">
                    <div style="font-size:2rem;margin-bottom:8px;">📏</div>
                    <h4 style="color:#003366;font-size:0.95rem;margin:0;">Discipline</h4>
                </div>
                <div style="background:#f8f9fa;border-radius:16px;padding:24px 16px;border:1px solid rgba(255,204,0,0.15);">
                    <div style="font-size:2rem;margin-bottom:8px;">🔥</div>
                    <h4 style="color:#003366;font-size:0.95rem;margin:0;">Dedication</h4>
                </div>
                <div style="background:#f8f9fa;border-radius:16px;padding:24px 16px;border:1px solid rgba(255,204,0,0.15);">
                    <div style="font-size:2rem;margin-bottom:8px;">❤️</div>
                    <h4 style="color:#003366;font-size:0.95rem;margin:0;">Love</h4>
                </div>
                <div style="background:#f8f9fa;border-radius:16px;padding:24px 16px;border:1px solid rgba(255,204,0,0.15);">
                    <div style="font-size:2rem;margin-bottom:8px;">🙏</div>
                    <h4 style="color:#003366;font-size:0.95rem;margin:0;">Humility</h4>
                </div>
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
