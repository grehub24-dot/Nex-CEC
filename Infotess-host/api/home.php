<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

// Fetch counts (individual queries — no JOINs per bridge rules)
$student_count = 0;
$staff_count = 0;
$class_count = 0;
$years_count = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as c FROM students WHERE status = 'active'");
    $student_count = (int)$stmt->fetchColumn();
} catch (Exception $e) {}
try {
    $stmt = $pdo->query("SELECT COUNT(*) as c FROM staff WHERE status = 'active'");
    $staff_count = (int)$stmt->fetchColumn();
} catch (Exception $e) {}
try {
    $stmt = $pdo->query("SELECT COUNT(*) as c FROM classes");
    $class_count = (int)$stmt->fetchColumn();
} catch (Exception $e) {}
// Graduating years (hard-coded to current + 3 for display)
$years_count = 4;
?>

<!-- Hero Section -->
<section class="hero-band" style="position: relative; overflow: hidden;">
    <!-- 3D School Building -->
    <div id="hero-3d-container" class="school-3d-container hero-3d"></div>

    <!-- Hero Content -->
    <div style="position: relative; z-index: 2; text-align: center; max-width: 900px; margin: 0 auto; padding: 0 24px;">
        <h1 class="text-hero" style="margin-bottom: var(--space-md);">Welcome to <?php echo htmlspecialchars($school_name); ?></h1>
        <p class="text-on-dark-muted" style="font-size: 18px; line-height: 1.7; max-width: 700px; margin: 0 auto var(--space-xl);">
            <?php echo htmlspecialchars($settings['school_motto'] ?? 'Excellence in Education'); ?> — Providing quality education from Creche through Junior High School in a safe, nurturing, and academically excellent environment.
        </p>
        <div style="display: flex; gap: var(--space-md); flex-wrap: wrap; justify-content: center; margin-bottom: var(--space-xxl);">
            <a href="register.php" class="btn btn-on-dark btn-lg"><i class="fas fa-user-plus"></i> Enroll Now</a>
            <a href="contact.php" class="btn btn-secondary-on-dark btn-lg">Contact Us</a>
        </div>
        <div style="display: flex; gap: var(--space-xxl); flex-wrap: wrap; justify-content: center;">
            <span style="color: var(--color-on-dark-muted); font-size: 14px;"><i class="fas fa-calendar-check" style="color: var(--color-primary); margin-right: 6px;"></i> 18+ Years of Excellence</span>
            <span style="color: var(--color-on-dark-muted); font-size: 14px;"><i class="fas fa-chalkboard-teacher" style="color: var(--color-primary); margin-right: 6px;"></i> Dedicated Staff</span>
            <span style="color: var(--color-on-dark-muted); font-size: 14px;"><i class="fas fa-users" style="color: var(--color-primary); margin-right: 6px;"></i> Holistic Education</span>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-bar">
    <div class="container">
        <div class="grid-4 anim-stagger" id="statsGrid">
            <div class="card-stat">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <h3><?php echo number_format($student_count); ?></h3>
                <p class="text-sm" style="color: var(--color-steel); margin: 0;">Students Enrolled</p>
            </div>
            <div class="card-stat">
                <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <h3><?php echo number_format($staff_count); ?></h3>
                <p class="text-sm" style="color: var(--color-steel); margin: 0;">Staff Members</p>
            </div>
            <div class="card-stat">
                <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
                <h3><?php echo $class_count; ?>+</h3>
                <p class="text-sm" style="color: var(--color-steel); margin: 0;">Class Levels</p>
            </div>
            <div class="card-stat">
                <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                <h3><?php echo $years_count; ?>+</h3>
                <p class="text-sm" style="color: var(--color-steel); margin: 0;">Years of Impact</p>
            </div>
        </div>
    </div>
</section>

<!-- What We Offer -->
<section class="section-block">
    <div class="container">
        <h2 class="text-h2 text-center" style="margin-bottom: var(--space-xs);">What We Offer</h2>
        <p class="text-sm text-center" style="max-width: 600px; margin: 0 auto var(--space-xxl); color: var(--color-steel);">
            Comprehensive educational programmes designed to nurture every child's potential from early childhood through junior high school.
        </p>
        <div class="grid-3 anim-stagger" id="featuresGrid">
            <div class="card-feature card-tint-peach">
                <div style="font-size: 36px; margin-bottom: var(--space-md);"><i class="fas fa-baby" style="color: #e67e22;"></i></div>
                <h3 class="text-h3">Early Childhood</h3>
                <p class="text-sm" style="color: var(--color-charcoal);">Creche, Nursery, and Kindergarten programmes designed to spark curiosity, creativity, and a lifelong love for learning.</p>
                <a href="about.php#early-childhood" class="btn btn-link" style="margin-top: var(--space-sm);">Learn More <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-feature card-tint-mint">
                <div style="font-size: 36px; margin-bottom: var(--space-md);"><i class="fas fa-book-open" style="color: #27ae60;"></i></div>
                <h3 class="text-h3">Primary Education</h3>
                <p class="text-sm" style="color: var(--color-charcoal);">Basic 1 to 6 with a comprehensive curriculum covering core subjects, creative arts, ICT, and physical education.</p>
                <a href="about.php#primary" class="btn btn-link" style="margin-top: var(--space-sm);">Learn More <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-feature card-tint-lavender">
                <div style="font-size: 36px; margin-bottom: var(--space-md);"><i class="fas fa-graduation-cap" style="color: var(--color-primary);"></i></div>
                <h3 class="text-h3">Junior High School</h3>
                <p class="text-sm" style="color: var(--color-charcoal);">JHS 1 to 3 preparing students for the BECE with strong academics, practical skills, and character formation.</p>
                <a href="about.php#jhs" class="btn btn-link" style="margin-top: var(--space-sm);">Learn More <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</section>

<!-- About Preview -->
<section class="section-block" style="background: var(--color-surface);">
    <div class="container">
        <div class="split-layout">
            <div>
                <span class="badge badge-primary" style="margin-bottom: var(--space-sm);">Our School</span>
                <h2 class="text-h2" style="margin-bottom: var(--space-md);">Nurturing Excellence, Building Character</h2>
                <p><?php echo htmlspecialchars($school_name); ?> is a nurturing learning environment dedicated to building strong academic foundations, character development, and holistic growth for every child from Creche to JHS 3.</p>
                <p>Our school follows the Ghana Education Service curriculum while fostering creativity, discipline, and a love for lifelong learning. We believe in partnering with parents to provide the best possible educational experience for every child.</p>
                <a href="about.php" class="btn btn-primary" style="margin-top: var(--space-sm);"><i class="fas fa-arrow-right"></i> Learn More About Us</a>
            </div>
            <div style="text-align: center;">
                <!-- 3D Books -->
                <div id="about-3d-preview" class="school-3d-container content-3d" style="width: 100%; max-width: 400px; height: 300px; margin: 0 auto;"></div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="section-block">
    <div class="container">
        <h2 class="text-h2 text-center" style="margin-bottom: var(--space-xs);">What Parents Say</h2>
        <p class="text-sm text-center" style="max-width: 600px; margin: 0 auto var(--space-xxl); color: var(--color-steel);">
            Hear from our community of parents and guardians about their experience with our school.
        </p>
        <div class="grid-3 anim-stagger" id="testimonialsGrid">
            <div class="card-testimonial">
                <div class="testimonial-stars">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
                <blockquote class="testimonial-quote">"The care and attention my child receives at <?php echo htmlspecialchars($school_name); ?> is outstanding. I've seen remarkable growth in both academics and confidence."</blockquote>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">A</div>
                    <div>
                        <strong style="font-size: 14px;">Parent of KG 2 Student</strong>
                        <p style="font-size: 13px; color: var(--color-steel); margin: 0;">Current Parent</p>
                    </div>
                </div>
            </div>
            <div class="card-testimonial">
                <div class="testimonial-stars">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
                <blockquote class="testimonial-quote">"The dedicated teachers and small class sizes make all the difference. My child loves going to school every day!"</blockquote>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">M</div>
                    <div>
                        <strong style="font-size: 14px;">Parent of B4 Student</strong>
                        <p style="font-size: 13px; color: var(--color-steel); margin: 0;">Current Parent</p>
                    </div>
                </div>
            </div>
            <div class="card-testimonial">
                <div class="testimonial-stars">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
                <blockquote class="testimonial-quote">"Excellent preparation for the BECE. The academic standards are high, and the moral foundation my child received is invaluable."</blockquote>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">E</div>
                    <div>
                        <strong style="font-size: 14px;">Parent of JHS Graduate</strong>
                        <p style="font-size: 13px; color: var(--color-steel); margin: 0;">Alumni Parent</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Banner -->
<section class="section-block" style="background: var(--color-surface);">
    <div class="container" style="text-align: center; max-width: 700px;">
        <h2 class="text-h2" style="margin-bottom: var(--space-sm);">Enroll Your Child Today</h2>
        <p style="margin-bottom: var(--space-xl); color: var(--color-steel);">Give your child the best foundation for a bright future. Registration is now open for all levels — Creche through JHS 3.</p>
        <div style="display: flex; gap: var(--space-md); justify-content: center; flex-wrap: wrap;">
            <a href="register.php" class="btn btn-primary btn-lg"><i class="fas fa-user-plus"></i> Enroll Now</a>
            <a href="contact.php" class="btn btn-secondary btn-lg"><i class="fas fa-phone-alt"></i> Contact Us</a>
        </div>
    </div>
</section>

<!-- Three.js 3D School Building -->
<script type="importmap">
{
    "imports": {
        "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js"
    }
}
</script>
<script type="module">
import * as THREE from 'three';

(function initSchoolScene() {
    const container = document.getElementById('hero-3d-container');
    if (!container) return;

    // Check WebGL support
    const canvas = document.createElement('canvas');
    const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
    if (!gl) {
        container.classList.add('no-webgl');
        return;
    }

    const width = container.offsetWidth || 400;
    const height = container.offsetHeight || 300;
    if (width < 100 || height < 100) return;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(35, width / height, 0.1, 1000);
    camera.position.set(3, 1.5, 4);
    camera.lookAt(0, 0, 0);

    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    container.appendChild(renderer.domElement);

    // --- Lights ---
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
    scene.add(ambientLight);
    const dirLight = new THREE.DirectionalLight(0xffffff, 0.8);
    dirLight.position.set(5, 8, 5);
    dirLight.castShadow = true;
    scene.add(dirLight);
    const fillLight = new THREE.DirectionalLight(0x8888ff, 0.3);
    fillLight.position.set(-3, 2, -3);
    scene.add(fillLight);

    // --- School Building (main block) ---
    const buildingMat = new THREE.MeshPhongMaterial({
        color: 0x5645d4,
        emissive: 0x2a1a7a,
        emissiveIntensity: 0.1,
        shininess: 30,
    });
    const building = new THREE.Mesh(new THREE.BoxGeometry(1.6, 1.2, 1.0), buildingMat);
    building.position.y = 0.6;
    building.castShadow = true;
    scene.add(building);

    // Roof
    const roofMat = new THREE.MeshPhongMaterial({
        color: 0x0a1530,
        shininess: 10,
    });
    const roof = new THREE.Mesh(new THREE.ConeGeometry(1.1, 0.5, 4), roofMat);
    roof.position.y = 1.45;
    roof.rotation.y = Math.PI / 4;
    roof.castShadow = true;
    scene.add(roof);

    // Windows (row of small blocks)
    const windowMat = new THREE.MeshPhongMaterial({
        color: 0xffe8d4,
        emissive: 0xffcc80,
        emissiveIntensity: 0.3,
    });
    for (let i = -0.5; i <= 0.5; i += 0.5) {
        const windowBox = new THREE.Mesh(new THREE.BoxGeometry(0.15, 0.25, 0.05), windowMat);
        windowBox.position.set(i, 0.65, 0.51);
        scene.add(windowBox);
        const windowBox2 = new THREE.Mesh(new THREE.BoxGeometry(0.15, 0.25, 0.05), windowMat);
        windowBox2.position.set(i, 0.65, -0.51);
        scene.add(windowBox2);
    }

    // Door
    const doorMat = new THREE.MeshPhongMaterial({ color: 0x1a2a52 });
    const door = new THREE.Mesh(new THREE.BoxGeometry(0.25, 0.4, 0.05), doorMat);
    door.position.set(0, 0.2, 0.51);
    scene.add(door);

    // Ground plane
    const groundMat = new THREE.MeshPhongMaterial({
        color: 0x1a2a52,
        transparent: true,
        opacity: 0.3,
    });
    const ground = new THREE.Mesh(new THREE.CircleGeometry(2.5, 32), groundMat);
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = -0.01;
    ground.receiveShadow = true;
    scene.add(ground);

    // --- Floating books ---
    const bookMat = new THREE.MeshPhongMaterial({ color: 0xd9f3e1 });
    const bookMat2 = new THREE.MeshPhongMaterial({ color: 0xe6e0f5 });
    const bookMat3 = new THREE.MeshPhongMaterial({ color: 0xffe8d4 });

    const book1 = new THREE.Mesh(new THREE.BoxGeometry(0.3, 0.05, 0.2), bookMat);
    book1.position.set(-1.4, 1.0, 0.6);
    book1.rotation.z = 0.1;
    scene.add(book1);

    const book2 = new THREE.Mesh(new THREE.BoxGeometry(0.25, 0.05, 0.18), bookMat2);
    book2.position.set(-1.3, 1.1, 0.7);
    book2.rotation.z = -0.05;
    scene.add(book2);

    const book3 = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.05, 0.22), bookMat3);
    book3.position.set(-1.5, 1.2, 0.5);
    book3.rotation.z = 0.15;
    scene.add(book3);

    // --- Small floating stars/particles ---
    const starMat = new THREE.PointsMaterial({
        color: 0xffffff,
        size: 0.02,
        transparent: true,
        opacity: 0.4,
    });
    const starPositions = [];
    for (let i = 0; i < 60; i++) {
        const theta = Math.random() * Math.PI * 2;
        const phi = Math.acos(2 * Math.random() - 1);
        const r = 2.5 + Math.random() * 1.5;
        starPositions.push(
            r * Math.sin(phi) * Math.cos(theta),
            r * Math.cos(phi) * 0.5 + 0.5,
            r * Math.sin(phi) * Math.sin(theta)
        );
    }
    const starGeo = new THREE.BufferGeometry();
    starGeo.setAttribute('position', new THREE.Float32BufferAttribute(starPositions, 3));
    const stars = new THREE.Points(starGeo, starMat);
    scene.add(stars);

    // --- Animation ---
    function animate() {
        requestAnimationFrame(animate);
        building.rotation.y += 0.005;
        roof.rotation.y += 0.005;
        book1.rotation.y += 0.003;
        book2.rotation.y += 0.003;
        book3.rotation.y += 0.003;
        stars.rotation.y -= 0.001;
        renderer.render(scene, camera);
    }
    animate();

    // --- Resize ---
    window.addEventListener('resize', function() {
        const w = container.offsetWidth || 400;
        const h = container.offsetHeight || 300;
        if (w < 100 || h < 100) return;
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        renderer.setSize(w, h);
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
