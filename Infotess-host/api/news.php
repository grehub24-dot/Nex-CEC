<?php
require_once 'includes/header.php';

// Fetch news items
$news_items = [];
try {
    $result = $pdo->query("SELECT id, title, content, image_url, source_url, published_at FROM news ORDER BY published_at DESC");
    if ($result && $result->rowCount() > 0) {
        $news_items = $result->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // News table may not exist yet
}
?>

<!-- Hero Inner -->
<section class="hero-inner">
    <div class="container" style="text-align: center; position: relative; z-index: 2;">
        <span class="badge-pill badge-gold" style="margin-bottom: 16px;">Stay Informed</span>
        <h1 style="font-size: 2.8rem; color: #fff; margin-bottom: 12px;">News & Updates</h1>
        <div id="calendar-3d" class="school-3d-container content-3d" style="margin: var(--space-xxl) auto; width: 100%; max-width: 400px; height: 300px;"></div>
        <p style="color: rgba(255,255,255,0.8); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">Stay up to date with the latest happenings, achievements, and announcements from Chariot Educational Complex.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (!empty($news_items)): 
            $featured = $news_items[0];
            $remaining = array_slice($news_items, 1);
        ?>
        <!-- Featured Post + Side Grid -->
        <div class="news-featured animate-on-scroll">
            <div class="featured-main">
                <?php if (!empty($featured['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($featured['image_url']); ?>" alt="<?php echo htmlspecialchars($featured['title']); ?>"
                     onerror="this.style.display='none'; this.parentElement.style.background='linear-gradient(135deg,#003366,#004080)';">
                <?php else: ?>
                <div style="position:absolute;inset:0;background:linear-gradient(135deg,#003366,#004080);display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:3rem;opacity:0.3;">📰</span>
                </div>
                <?php endif; ?>
                <div class="featured-overlay">
                    <span class="badge-pill badge-gold" style="align-self:flex-start;margin-bottom:12px;">
                        <?php echo date('M d, Y', strtotime($featured['published_at'])); ?>
                    </span>
                    <h2><?php echo htmlspecialchars($featured['title']); ?></h2>
                    <p><?php echo htmlspecialchars(substr(strip_tags($featured['content']), 0, 150)) . '...'; ?></p>
                    <a href="<?php echo !empty($featured['source_url']) ? htmlspecialchars($featured['source_url']) : '#'; ?>" class="btn-gold" style="align-self:flex-start;margin-top:8px;padding:10px 24px;font-size:0.85rem;">
                        Read More →
                    </a>
                </div>
            </div>
            <div class="featured-side">
                <?php 
                $side_items = array_slice($news_items, 1, 3);
                foreach ($side_items as $side): 
                ?>
                <div class="card-premium" style="display:flex;flex-direction:row;min-height:0;cursor:pointer;" onclick="window.location.href='<?php echo !empty($side['source_url']) ? htmlspecialchars($side['source_url']) : '#'; ?>'">
                    <?php if (!empty($side['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($side['image_url']); ?>" alt="" style="width:120px;height:120px;object-fit:cover;flex-shrink:0;" onerror="this.style.display='none';">
                    <?php endif; ?>
                    <div class="card-body" style="padding:12px 16px;">
                        <div class="card-date"><?php echo date('M d, Y', strtotime($side['published_at'])); ?></div>
                        <h4 class="card-title" style="font-size:0.95rem;margin-bottom:4px;"><?php echo htmlspecialchars($side['title']); ?></h4>
                        <p class="card-text" style="font-size:0.82rem;"><?php echo htmlspecialchars(substr(strip_tags($side['content']), 0, 80)) . '...'; ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Remaining News Grid -->
        <?php if (!empty($remaining)): ?>
        <div class="stagger-children" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:24px;">
            <?php foreach ($remaining as $item): ?>
            <div class="card-premium">
                <?php if (!empty($item['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="" class="card-image"
                     onerror="this.style.display='none';">
                <?php endif; ?>
                <div class="card-body">
                    <div class="card-date">
                        <span>📅</span> <?php echo date('F d, Y', strtotime($item['published_at'])); ?>
                    </div>
                    <h3 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                    <p class="card-text"><?php echo htmlspecialchars(substr(strip_tags($item['content']), 0, 120)) . '...'; ?></p>
                    <?php if (!empty($item['source_url'])): ?>
                    <a href="<?php echo htmlspecialchars($item['source_url']); ?>" class="btn-outline-gold" style="margin-top:12px;padding:8px 20px;font-size:0.82rem;display:inline-flex;">Read Full Story →</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- Empty State -->
        <div class="animate-on-scroll" style="text-align: center; padding: 60px 20px;">
            <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;">📢</div>
            <h3 style="color: #003366; margin-bottom: 12px;">No News Yet</h3>
            <p style="color: #888; max-width: 500px; margin: 0 auto;">Stay tuned! News and updates from Chariot Educational Complex will be posted here soon. We look forward to sharing our story with you.</p>
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

<!-- Three.js 3D Calendar Scene -->
<script type="importmap">
{
    "imports": {
        "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js"
    }
}
</script>
<script type="module">
import * as THREE from 'three';
(function initCalendar() {
    const container = document.getElementById('calendar-3d');
    if (!container) return;
    const w = container.offsetWidth || 400;
    const h = container.offsetHeight || 300;
    if (w < 100 || h < 100) return;
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(40, w / h, 0.1, 1000);
    camera.position.set(2, 1.2, 2.5);
    camera.lookAt(0, 0, 0);
    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setSize(w, h);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    container.appendChild(renderer.domElement);
    const ambient = new THREE.AmbientLight(0xffffff, 0.5);
    scene.add(ambient);
    const dir = new THREE.DirectionalLight(0xffffff, 0.8);
    dir.position.set(3, 4, 3);
    scene.add(dir);
    // Calendar body
    const calMat = new THREE.MeshPhongMaterial({ color: 0x5645d4, shininess: 25 });
    const body = new THREE.Mesh(new THREE.BoxGeometry(1.0, 0.8, 0.06), calMat);
    body.position.set(0, 0.4, 0);
    scene.add(body);
    // Calendar pages (slightly offset)
    const pageMat = new THREE.MeshPhongMaterial({ color: 0xffffff });
    const page1 = new THREE.Mesh(new THREE.BoxGeometry(0.85, 0.65, 0.02), pageMat);
    page1.position.set(0.01, 0.35, 0.05);
    scene.add(page1);
    const page2 = new THREE.Mesh(new THREE.BoxGeometry(0.85, 0.65, 0.02), pageMat);
    page2.position.set(-0.01, 0.33, -0.05);
    page2.rotation.y = 0.05;
    scene.add(page2);
    // Calendar grid lines (small bars)
    const lineMat = new THREE.MeshBasicMaterial({ color: 0xd9f3e1 });
    const lineMat2 = new THREE.MeshBasicMaterial({ color: 0xe6e0f5 });
    for (let row = 0; row < 4; row++) {
        for (let col = 0; col < 5; col++) {
            const cell = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.04, 0.01), row === 0 ? lineMat2 : lineMat);
            cell.position.set(-0.3 + col * 0.15, 0.45 - row * 0.12, 0.07);
            scene.add(cell);
        }
    }
    // Spirals (small rings)
    const spiralMat = new THREE.MeshBasicMaterial({ color: 0x4534b3 });
    for (let i = -0.3; i <= 0.3; i += 0.2) {
        const ring = new THREE.Mesh(new THREE.TorusGeometry(0.03, 0.01, 6, 8), spiralMat);
        ring.position.set(i, 0.82, 0);
        ring.rotation.x = Math.PI / 2;
        scene.add(ring);
    }
    function animate() {
        requestAnimationFrame(animate);
        body.rotation.y += 0.005;
        page1.rotation.y += 0.005;
        page2.rotation.y += 0.005;
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
