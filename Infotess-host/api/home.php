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
<section class="hero" style="position: relative; overflow: hidden; background: linear-gradient(rgba(0,51,102,0.85), rgba(0,34,68,0.75)), url('images/aamusted.jpg') no-repeat center center/cover; background-attachment: fixed;">
    <!-- 3D Globe Canvas -->
    <div id="globe-container" style="position: absolute; inset: 0; z-index: 1; pointer-events: none;"></div>
    
    <!-- Hero Content -->
    <div style="position: relative; z-index: 2; text-align: center; width: 100%; max-width: 900px; margin: 0 auto;">
        <h1>Welcome to <?php echo htmlspecialchars($school_name); ?></h1>
        <p style="font-size: var(--text-lg); line-height: var(--leading-relaxed); color: var(--color-gray-700); margin-bottom: 30px;"><?php echo htmlspecialchars($school_motto); ?> — Providing quality education from Creche through Junior High School in a safe, nurturing, and academically excellent environment.</p>
        <div class="hero-badges">
            <span class="hero-badge"><i class="fas fa-calendar-check"></i> <span>18+ Years of Excellence</span></span>
            <span class="hero-badge"><i class="fas fa-chalkboard-teacher"></i> <span>Dedicated Staff</span></span>
            <span class="hero-badge"><i class="fas fa-users"></i> <span>Holistic Education</span></span>
        </div>
        <div style="margin-top: 30px; display: flex; gap: 16px; flex-wrap: wrap; justify-content: center;">
            <a href="register.php" class="btn-cta"><i class="fas fa-user-plus"></i> Enroll Now</a>
            <a href="contact.php" class="btn-cta" style="background: transparent; color: #fff; border: 2px solid rgba(255,255,255,0.6);">Contact Us</a>
        </div>
    </div>
</section>

<!-- Stats Counter Section (overlapping) -->
<section class="stats-section">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-item" style="animation: fadeInUp 0.5s var(--ease-out-expo) both;">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <h3><?php echo number_format($student_count); ?></h3>
                <p>Students Enrolled</p>
            </div>
            <div class="stat-item" style="animation: fadeInUp 0.5s var(--ease-out-expo) 0.1s both;">
                <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <h3><?php echo number_format($staff_count); ?></h3>
                <p>Staff Members</p>
            </div>
            <div class="stat-item" style="animation: fadeInUp 0.5s var(--ease-out-expo) 0.2s both;">
                <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
                <h3><?php echo $class_count; ?>+</h3>
                <p>Class Levels</p>
            </div>
            <div class="stat-item" style="animation: fadeInUp 0.5s var(--ease-out-expo) 0.3s both;">
                <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                <h3><?php echo $years_count; ?>+</h3>
                <p>Years of Impact</p>
            </div>
        </div>
    </div>
</section>

<!-- What We Offer -->
<section class="features-section">
    <div class="container">
        <h2 class="section-title">What We Offer</h2>
        <p style="text-align: center; max-width: 700px; margin: -20px auto 50px; color: var(--text-muted);">
            Comprehensive educational programmes designed to nurture every child's potential from early childhood through junior high school.
        </p>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-baby"></i></div>
                <h3>Early Childhood</h3>
                <p>Creche, Nursery, and Kindergarten programmes designed to spark curiosity, creativity, and a lifelong love for learning in our youngest students.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-book-reader"></i></div>
                <h3>Primary Education</h3>
                <p>Basic 1 to 6 with a comprehensive curriculum covering core subjects, creative arts, ICT, and physical education for well-rounded development.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-graduation-cap"></i></div>
                <h3>Junior High School</h3>
                <p>JHS 1 to 3 preparing students for the BECE with strong academics, practical skills, and character formation for future success.</p>
            </div>
        </div>
    </div>
</section>

<!-- About Preview -->
<section class="section" style="background: var(--color-gray-50);">
    <div class="container">
        <h2 class="section-title">Our School</h2>
        <div style="max-width: 800px; margin: 0 auto; text-align: center;">
            <p style="font-size: var(--text-lg); line-height: var(--leading-relaxed); color: var(--color-gray-700); margin-bottom: 30px;">
                <?php echo htmlspecialchars($school_name); ?> is a nurturing learning environment dedicated to building strong academic foundations, character development, and holistic growth for every child from Creche to JHS 3.
            </p>
            <p style="color: var(--text-muted); margin-bottom: 30px;">
                Our school follows the Ghana Education Service curriculum while fostering creativity, discipline, and a love for lifelong learning. We believe in partnering with parents to provide the best possible educational experience for every child.
            </p>
            <a href="about.php" class="btn-primary"><i class="fas fa-arrow-right"></i> Learn More About Us</a>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="testimonials-section">
    <div class="container">
        <h2 class="section-title">What Parents Say</h2>
        <p style="text-align: center; max-width: 600px; margin: -20px auto 50px; opacity: 0.85;">
            Hear from our community of parents and guardians about their experience with our school.
        </p>
        <div class="testimonials-grid">
            <div class="testimonial-card">
                <div class="stars">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
                <blockquote>"The care and attention my child receives at <?php echo htmlspecialchars($school_name); ?> is outstanding. I've seen remarkable growth in both academics and confidence."</blockquote>
                <div class="testimonial-author">
                    <div class="avatar">A</div>
                    <div class="testimonial-author-info">
                        <strong>Parent of KG 2 Student</strong>
                        <span>Current Parent</span>
                    </div>
                </div>
            </div>
            <div class="testimonial-card">
                <div class="stars">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
                <blockquote>"The dedicated teachers and small class sizes make all the difference. My child loves going to school every day!"</blockquote>
                <div class="testimonial-author">
                    <div class="avatar">M</div>
                    <div class="testimonial-author-info">
                        <strong>Parent of B4 Student</strong>
                        <span>Current Parent</span>
                    </div>
                </div>
            </div>
            <div class="testimonial-card">
                <div class="stars">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
                <blockquote>"Excellent preparation for the BECE. The academic standards are high, and the moral foundation my child received is invaluable."</blockquote>
                <div class="testimonial-author">
                    <div class="avatar">E</div>
                    <div class="testimonial-author-info">
                        <strong>Parent of JHS Graduate</strong>
                        <span>Alumni Parent</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="cta-section">
    <div class="container">
        <h2>Enroll Your Child Today</h2>
        <p>Give your child the best foundation for a bright future. Registration is now open for all levels — Creche through JHS 3.</p>
        <div class="cta-buttons">
            <a href="register.php" class="btn-cta"><i class="fas fa-user-plus"></i> Enroll Now</a>
            <a href="contact.php" class="btn-cta-outline"><i class="fas fa-phone-alt"></i> Contact Us</a>
        </div>
    </div>
</section>

<!-- Three.js 3D Globe -->
<script type="importmap">
{
    "imports": {
        "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js"
    }
}
</script>
<script type="module">
import * as THREE from 'three';

(function initGlobe() {
    const container = document.getElementById('globe-container');
    if (!container) return;

    const width = container.offsetWidth || window.innerWidth;
    const height = container.offsetHeight || window.innerHeight;
    if (width < 100 || height < 100) return;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);
    camera.position.set(0, 0.3, 3.8);

    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    container.appendChild(renderer.domElement);

    // --- Globe Sphere (wireframe) ---
    const sphereGeo = new THREE.SphereGeometry(1.2, 32, 32);
    const sphereMat = new THREE.MeshPhongMaterial({
        color: 0x003366,
        emissive: 0x002244,
        wireframe: true,
        transparent: true,
        opacity: 0.35,
    });
    const sphere = new THREE.Mesh(sphereGeo, sphereMat);
    scene.add(sphere);

    // --- Inner glowing sphere ---
    const glowGeo = new THREE.SphereGeometry(1.15, 24, 24);
    const glowMat = new THREE.MeshBasicMaterial({
        color: 0x003366,
        transparent: true,
        opacity: 0.2,
    });
    const glowSphere = new THREE.Mesh(glowGeo, glowMat);
    scene.add(glowSphere);

    // --- Gold dots on globe surface ---
    const dotsGroup = new THREE.Group();
    const dotCount = 120;
    const dotGeo = new THREE.SphereGeometry(0.025, 6, 6);
    const dotMat = new THREE.MeshBasicMaterial({ color: 0xffcc00 });

    for (let i = 0; i < dotCount; i++) {
        const theta = Math.random() * Math.PI * 2;
        const phi = Math.acos(2 * Math.random() - 1);
        const r = 1.22;
        const x = r * Math.sin(phi) * Math.cos(theta);
        const y = r * Math.sin(phi) * Math.sin(theta);
        const z = r * Math.cos(phi);
        const dot = new THREE.Mesh(dotGeo, dotMat);
        dot.position.set(x, y, z);
        dotsGroup.add(dot);
    }
    scene.add(dotsGroup);

    // --- Connection lines between nearby dots ---
    const linePositions = [];
    const dotPositions = [];
    dotsGroup.children.forEach(function(d) { dotPositions.push(d.position); });
    for (let i = 0; i < dotPositions.length; i++) {
        for (let j = i + 1; j < dotPositions.length; j++) {
            const d = dotPositions[i].distanceTo(dotPositions[j]);
            if (d < 0.7 && d > 0.2) {
                linePositions.push(dotPositions[i].x, dotPositions[i].y, dotPositions[i].z);
                linePositions.push(dotPositions[j].x, dotPositions[j].y, dotPositions[j].z);
            }
        }
    }
    if (linePositions.length > 0) {
        const lineGeo = new THREE.BufferGeometry();
        lineGeo.setAttribute('position', new THREE.Float32BufferAttribute(linePositions, 3));
        const lineMat = new THREE.LineBasicMaterial({
            color: 0xffcc00,
            transparent: true,
            opacity: 0.15,
        });
        const lines = new THREE.LineSegments(lineGeo, lineMat);
        scene.add(lines);
    }

    // --- Orbiting particles ---
    const particleCount = 200;
    const particleGeo = new THREE.BufferGeometry();
    const particlePos = new Float32Array(particleCount * 3);
    for (let i = 0; i < particleCount; i++) {
        const theta = Math.random() * Math.PI * 2;
        const phi = Math.acos(2 * Math.random() - 1);
        const r = 1.8 + Math.random() * 1.2;
        particlePos[i * 3] = r * Math.sin(phi) * Math.cos(theta);
        particlePos[i * 3 + 1] = r * Math.sin(phi) * Math.sin(theta);
        particlePos[i * 3 + 2] = r * Math.cos(phi);
    }
    particleGeo.setAttribute('position', new THREE.BufferAttribute(particlePos, 3));
    const particleMat = new THREE.PointsMaterial({
        color: 0xffcc00,
        size: 0.02,
        transparent: true,
        opacity: 0.3,
    });
    const particles = new THREE.Points(particleGeo, particleMat);
    scene.add(particles);

    // --- Lights ---
    const ambientLight = new THREE.AmbientLight(0x404060);
    scene.add(ambientLight);
    const dirLight = new THREE.DirectionalLight(0xffcc00, 0.6);
    dirLight.position.set(2, 2, 3);
    scene.add(dirLight);
    const backLight = new THREE.DirectionalLight(0x003366, 0.4);
    backLight.position.set(-2, -1, -2);
    scene.add(backLight);

    // --- Animation ---
    let rotationSpeed = 0.002;
    let mouseX = 0;
    let targetRotX = 0;

    document.addEventListener('mousemove', function(e) {
        const rect = container.getBoundingClientRect();
        const x = (e.clientX - rect.left) / rect.width;
        mouseX = (x - 0.5) * 0.5;
    });

    function animate() {
        requestAnimationFrame(animate);
        targetRotX += (mouseX - targetRotX) * 0.03;
        sphere.rotation.y += rotationSpeed;
        sphere.rotation.x += targetRotX * 0.001;
        glowSphere.rotation.y += rotationSpeed;
        glowSphere.rotation.x += targetRotX * 0.001;
        dotsGroup.rotation.y += rotationSpeed;
        dotsGroup.rotation.x += targetRotX * 0.001;
        particles.rotation.y -= rotationSpeed * 0.5;
        renderer.render(scene, camera);
    }
    animate();

    // --- Resize handler ---
    window.addEventListener('resize', function() {
        const w = container.offsetWidth || window.innerWidth;
        const h = container.offsetHeight || window.innerHeight;
        if (w < 100 || h < 100) return;
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        renderer.setSize(w, h);
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
