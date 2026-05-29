<?php
require_once 'includes/header.php';

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}

// Fetch gallery items
$gallery_items = [];
try {
    $result = $pdo->query("SELECT id, caption, image_url, category, created_at FROM gallery ORDER BY created_at DESC");
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

<!-- Hero Band -->
<div class="hero-band-narrow">
    <div class="hero-band-content">
        <span class="badge badge-on-dark">Captured Moments</span>
        <h1 class="text-hero">Our Gallery</h1>
        <p class="text-on-dark-muted hero-band-text">Explore the vibrant life and learning at <?php echo htmlspecialchars($settings['school_name'] ?? 'Nex CEC'); ?> through our photo collection.</p>
    </div>
    <div id="gallery-3d" class="school-3d-container content-3d"></div>
</div>

<!-- Gallery Section -->
<section class="section-block">
    <div class="container">
        <?php if (!empty($categories)): ?>
        <!-- Filter Buttons -->
        <div class="filter-bar anim-stagger visible">
            <button class="filter-btn active" data-filter="all" aria-pressed="true">All</button>
            <?php foreach ($categories as $cat): ?>
            <button class="filter-btn" data-filter="<?php echo htmlspecialchars(strtolower($cat)); ?>" aria-pressed="false"><?php echo htmlspecialchars(ucfirst($cat)); ?></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($gallery_items)): ?>
        <!-- Gallery Grid -->
        <div class="gallery-masonry" id="gallery-grid">
            <?php foreach ($gallery_items as $item): 
                $cat_class = !empty($item['category']) ? strtolower(htmlspecialchars($item['category'])) : 'uncategorized';
            ?>
            <figure class="gallery-figure" data-category="<?php echo $cat_class; ?>">
                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                     alt="<?php echo htmlspecialchars($item['caption'] ?? 'Gallery image'); ?>"
                     loading="lazy"
                     onclick="openLightbox(this.src, '<?php echo htmlspecialchars($item['caption'] ?? ''); ?>')"
                     onerror="this.onerror=null; this.parentElement.classList.add('gallery-img-fallback');">
                <?php if (!empty($item['caption'])): ?>
                <div class="gallery-overlay" onclick="openLightbox('<?php echo htmlspecialchars($item['image_url']); ?>', '<?php echo htmlspecialchars($item['caption']); ?>')">
                    <span><?php echo htmlspecialchars($item['caption']); ?></span>
                </div>
                <?php endif; ?>
            </figure>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state anim-stagger visible">
            <div class="empty-state-icon">📸</div>
            <h3 class="mb-sm">Gallery Coming Soon</h3>
            <p class="empty-state-text">We're collecting our best moments! Check back soon to see photos of our students, events, and campus life.</p>
            <div class="card-grid mx-auto">
                <div class="card text-center">
                    <div class="card-icon-block">🏫</div>
                    <p class="text-sm">Campus Life</p>
                </div>
                <div class="card text-center">
                    <div class="card-icon-block">🎓</div>
                    <p class="text-sm">Graduations</p>
                </div>
                <div class="card text-center">
                    <div class="card-icon-block">⚽</div>
                    <p class="text-sm">Sports</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Lightbox (a11y: dynamic alt + focus management) -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()" role="dialog" aria-modal="true" aria-label="Image preview">
    <button class="lightbox-close" onclick="closeLightbox()" aria-label="Close image preview">&times;</button>
    <img id="lightbox-img" src="" alt="">
</div>

<style>
.gallery-img-fallback {
    background: var(--color-surface);
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-lg);
    color: var(--color-steel);
    font-size: 0.9rem;
}
.gallery-img-fallback::after {
    content: "📷 Image";
}
</style>

<script>
// Lightbox
function openLightbox(src, title) {
    var lb = document.getElementById('lightbox');
    var img = document.getElementById('lightbox-img');
    img.src = src;
    img.alt = title || 'Gallery image preview';
    lb.classList.add('active');
    lb.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    setTimeout(function() {
        lb.querySelector('.lightbox-close').focus();
    }, 100);
}
function closeLightbox() {
    var lb = document.getElementById('lightbox');
    lb.classList.remove('active');
    lb.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLightbox();
});

// Gallery filtering (a11y: aria-pressed)
document.querySelectorAll('.filter-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-btn').forEach(function(b) {
            b.classList.remove('active');
            b.setAttribute('aria-pressed', 'false');
        });
        this.classList.add('active');
        this.setAttribute('aria-pressed', 'true');
        var filter = this.getAttribute('data-filter');
        document.querySelectorAll('.gallery-figure').forEach(function(fig) {
            fig.style.display = (filter === 'all' || fig.getAttribute('data-category') === filter) ? '' : 'none';
        });
    });
});
</script>

<!-- 3D Picture Frame Scene (shared module) -->
<script type="module">
    import { initScene } from '../js/school-3d.js';
    if (document.getElementById('gallery-3d')) {
        initScene('gallery-3d', 'frame');
    }
</script>

<?php require_once 'includes/footer.php'; ?>
