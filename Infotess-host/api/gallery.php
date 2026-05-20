<?php
require_once 'includes/header.php';

// Fetch gallery items
$gallery_items = [];
try {
    $result = $pdo->query("SELECT id, title, image_url, category, created_at FROM gallery ORDER BY created_at DESC");
    if ($result && $result->rowCount() > 0) {
        $gallery_items = $result->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Gallery table may not exist yet
}

// Categories for filter
$categories = [];
foreach ($gallery_items as $item) {
    if (!empty($item['category']) && !in_array($item['category'], $categories)) {
        $categories[] = $item['category'];
    }
}
?>

<!-- Hero Inner -->
<section class="hero-inner">
    <div class="container" style="text-align: center; position: relative; z-index: 2;">
        <span class="badge-pill badge-gold" style="margin-bottom: 16px;">Captured Moments</span>
        <h1 style="font-size: 2.8rem; color: #fff; margin-bottom: 12px;">Our Gallery</h1>
        <p style="color: rgba(255,255,255,0.8); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">Explore the vibrant life and learning at Chariot Educational Complex through our photo collection.</p>
    </div>
</section>

<!-- Gallery Section -->
<section class="section">
    <div class="container">
        <?php if (!empty($categories)): ?>
        <!-- Filter Buttons -->
        <div class="stagger-children visible" style="text-align: center; margin-bottom: 36px;">
            <button class="btn-gold gallery-filter active" data-filter="all" style="margin: 4px; padding: 8px 20px; font-size: 0.85rem;">All</button>
            <?php foreach ($categories as $cat): ?>
            <button class="btn-outline-gold gallery-filter" data-filter="<?php echo htmlspecialchars(strtolower($cat)); ?>" style="margin: 4px; padding: 8px 20px; font-size: 0.85rem;"><?php echo htmlspecialchars(ucfirst($cat)); ?></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($gallery_items)): ?>
        <!-- Masonry Grid -->
        <div class="gallery-masonry" id="gallery-grid">
            <?php foreach ($gallery_items as $item): 
                $cat_class = !empty($item['category']) ? strtolower(htmlspecialchars($item['category'])) : 'uncategorized';
            ?>
            <figure class="gallery-figure" data-category="<?php echo $cat_class; ?>">
                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                     alt="<?php echo htmlspecialchars($item['title'] ?? 'Gallery image'); ?>"
                     loading="lazy"
                     onclick="openLightbox(this.src, '<?php echo htmlspecialchars($item['title'] ?? ''); ?>')"
                     onerror="this.onerror=null; this.parentElement.innerHTML='<div style=\"background:#f0f0f0;height:200px;display:flex;align-items:center;justify-content:center;border-radius:16px;color:#999;font-size:0.9rem;\">📷 Image</div>'">
                <?php if (!empty($item['title'])): ?>
                <div class="gallery-overlay" onclick="openLightbox('<?php echo htmlspecialchars($item['image_url']); ?>', '<?php echo htmlspecialchars($item['title']); ?>')">
                    <span><?php echo htmlspecialchars($item['title']); ?></span>
                </div>
                <?php endif; ?>
            </figure>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- Empty State -->
        <div class="animate-on-scroll" style="text-align: center; padding: 60px 20px;">
            <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;">📸</div>
            <h3 style="color: #003366; margin-bottom: 12px;">Gallery Coming Soon</h3>
            <p style="color: #888; max-width: 500px; margin: 0 auto 24px;">We're collecting our best moments! Check back soon to see photos of our students, events, and campus life at Chariot Educational Complex.</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; max-width: 700px; margin: 0 auto;">
                <div style="background: #f5f5f5; border-radius: 12px; padding: 30px 20px; text-align: center;">
                    <div style="font-size: 2rem; margin-bottom: 8px;">🏫</div>
                    <p style="font-size: 0.85rem; color: #888; margin: 0;">Campus Life</p>
                </div>
                <div style="background: #f5f5f5; border-radius: 12px; padding: 30px 20px; text-align: center;">
                    <div style="font-size: 2rem; margin-bottom: 8px;">🎓</div>
                    <p style="font-size: 0.85rem; color: #888; margin: 0;">Graduations</p>
                </div>
                <div style="background: #f5f5f5; border-radius: 12px; padding: 30px 20px; text-align: center;">
                    <div style="font-size: 2rem; margin-bottom: 8px;">⚽</div>
                    <p style="font-size: 0.85rem; color: #888; margin: 0;">Sports</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
    <img id="lightbox-img" src="" alt="">
</div>

<script>
// Lightbox
function openLightbox(src, title) {
    var lb = document.getElementById('lightbox');
    var img = document.getElementById('lightbox-img');
    img.src = src;
    lb.classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    var lb = document.getElementById('lightbox');
    lb.classList.remove('active');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLightbox();
});

// Gallery filtering
document.querySelectorAll('.gallery-filter').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.gallery-filter').forEach(function(b) {
            b.className = b.className.replace(' btn-gold', ' btn-outline-gold');
            b.classList.remove('active');
        });
        this.className = this.className.replace(' btn-outline-gold', ' btn-gold');
        this.classList.add('active');
        var filter = this.getAttribute('data-filter');
        document.querySelectorAll('.gallery-figure').forEach(function(fig) {
            if (filter === 'all' || fig.getAttribute('data-category') === filter) {
                fig.style.display = '';
            } else {
                fig.style.display = 'none';
            }
        });
    });
});

// Scroll animation
(function() {
    var els = document.querySelectorAll('.animate-on-scroll');
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
