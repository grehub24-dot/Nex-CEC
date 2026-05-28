<?php
require_once 'includes/header.php';

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

<!-- Hero Inner -->
<section class="hero-inner">
    <div class="container" style="text-align: center; position: relative; z-index: 2;">
        <span class="badge-pill badge-gold" style="margin-bottom: 16px;">Captured Moments</span>
        <h1 style="font-size: 2.8rem; color: #fff; margin-bottom: 12px;">Our Gallery</h1>
        <p style="color: rgba(255,255,255,0.8); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">Explore the vibrant life and learning at Chariot Educational Complex through our photo collection.</p>
    </div>
</section>

<div id="gallery-3d" class="school-3d-container content-3d" style="margin: var(--space-xxl) auto; width: 100%; max-width: 400px; height: 300px;"></div>

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
                     alt="<?php echo htmlspecialchars($item['caption'] ?? 'Gallery image'); ?>"
                     loading="lazy"
                     onclick="openLightbox(this.src, '<?php echo htmlspecialchars($item['caption'] ?? ''); ?>')"
                     onerror="this.onerror=null; this.parentElement.innerHTML='<div style=\"background:#f0f0f0;height:200px;display:flex;align-items:center;justify-content:center;border-radius:16px;color:#999;font-size:0.9rem;\">📷 Image</div>'">
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

<!-- Three.js 3D Picture Frame Scene -->
<script type="importmap">
{
    "imports": {
        "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js"
    }
}
</script>
<script type="module">
import * as THREE from 'three';
(function initFrame() {
    const container = document.getElementById('gallery-3d');
    if (!container) return;
    const w = container.offsetWidth || 400;
    const h = container.offsetHeight || 300;
    if (w < 100 || h < 100) return;
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(40, w / h, 0.1, 1000);
    camera.position.set(1.5, 0.8, 2.5);
    camera.lookAt(0, 0, 0);
    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setSize(w, h);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    container.appendChild(renderer.domElement);
    const ambient = new THREE.AmbientLight(0xffffff, 0.5);
    scene.add(ambient);
    const dir = new THREE.DirectionalLight(0xffffff, 0.8);
    dir.position.set(2, 3, 4);
    scene.add(dir);
    // Frame (using EdgesGeometry for outline look)
    const frameMat = new THREE.MeshPhongMaterial({ color: 0x5645d4, shininess: 40 });
    const frame = new THREE.Mesh(new THREE.BoxGeometry(1.4, 1.0, 0.08), frameMat);
    frame.position.z = 0;
    scene.add(frame);
    // Inner mat (lighter)
    const matMat = new THREE.MeshPhongMaterial({ color: 0xe6e0f5 });
    const inner = new THREE.Mesh(new THREE.BoxGeometry(1.1, 0.7, 0.09), matMat);
    inner.position.set(0, 0, 0.04);
    scene.add(inner);
    // Image plane (photo inside frame)
    const photoMat = new THREE.MeshBasicMaterial({ color: 0xd9f3e1 });
    const photo = new THREE.Mesh(new THREE.PlaneGeometry(0.9, 0.5), photoMat);
    photo.position.set(0, 0, 0.09);
    scene.add(photo);
    // Small decorative dots at corners
    const dotMat = new THREE.MeshBasicMaterial({ color: 0xffe8d4 });
    const positions = [[-0.65, 0.45, 0.05], [0.65, 0.45, 0.05], [-0.65, -0.45, 0.05], [0.65, -0.45, 0.05]];
    positions.forEach(function(pos) {
        const dot = new THREE.Mesh(new THREE.CircleGeometry(0.03, 8), dotMat);
        dot.position.set(pos[0], pos[1], pos[2]);
        scene.add(dot);
    });
    function animate() {
        requestAnimationFrame(animate);
        scene.rotation.y += 0.005;
        renderer.render(scene, camera);
    }
    animate();
    window.addEventListener('resize', function() {
        const w2 = container.offsetWidth || 400;
        const h2 = container.offsetHeight || 300;
        if (w2 < 100 || h2 < 100) return;
        camera.aspect = w2 / h2;
        camera.updateProjectionMatrix();
        renderer.setSize(w2, h2);
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
