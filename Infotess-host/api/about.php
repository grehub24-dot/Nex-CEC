<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';
$school_motto = $settings['school_motto'] ?? 'Excellence in Education';
?>

<!-- Hero Band -->
<div class="hero-band-narrow">
    <div class="hero-band-content">
        <h1 class="text-hero">About <?php echo htmlspecialchars($school_name); ?></h1>
        <p class="text-on-dark-muted hero-band-text">Discover our story, mission, and the values that guide us in shaping the next generation of leaders.</p>
    </div>
    <div id="about-3d-books" class="school-3d-container content-3d" style="position: relative; margin: 0 auto; width: 100%; max-width: 400px; height: 300px; z-index: 2;"></div>
</div>

<!-- Our Story -->
<section class="section-block">
    <div class="container">
        <div class="card-grid card-grid-2">
            <div>
                <h2 class="section-title" style="text-align: left; margin-bottom: var(--space-lg);">Our Story</h2>
                <p style="color: var(--color-slate); line-height: 1.8;"><?php echo htmlspecialchars($school_name); ?> is a nurturing learning environment dedicated to building strong academic foundations, character development, and holistic growth for every child from Creche to Junior High School.</p>
                <p style="color: var(--color-slate); line-height: 1.8;">Our school is committed to the Ghana Education Service curriculum while fostering creativity, discipline, and a love for lifelong learning. We believe in partnering with parents to provide the best possible educational experience for every child in a safe, supportive, and stimulating environment.</p>
            </div>
            <div>
                <img src="images/story-photo.jpg" alt="<?php echo htmlspecialchars($school_name); ?> classroom learning" loading="lazy" style="width: 100%; border-radius: var(--radius-lg); object-fit: cover; height: 320px;">
            </div>
        </div>
    </div>
</section>

<!-- Mission & Vision -->
<section class="section-block" style="background: var(--color-tint-gray);">
    <div class="container">
        <h2 class="section-title">Our Mission &amp; Vision</h2>
        <div class="card-grid card-grid-2">
            <div class="card card-highlight" style="border-top: 4px solid var(--color-primary);">
                <div class="card-content" style="text-align: center; padding: var(--space-xl);">
                    <i class="fas fa-bullseye" style="font-size: 2rem; color: var(--color-primary); margin-bottom: var(--space-md);"></i>
                    <h3>Our Mission</h3>
                    <p style="color: var(--color-slate);">To provide quality basic education that develops the intellectual, moral, and physical potential of every child in a safe and supportive environment, preparing them to become responsible and productive citizens.</p>
                </div>
            </div>
            <div class="card card-highlight" style="border-top: 4px solid var(--color-accent-green);">
                <div class="card-content" style="text-align: center; padding: var(--space-xl);">
                    <i class="fas fa-eye" style="font-size: 2rem; color: var(--color-accent-green); margin-bottom: var(--space-md);"></i>
                    <h3>Our Vision</h3>
                    <p style="color: var(--color-slate);">To be a leading basic school that produces well-rounded, confident, and academically excellent students who excel in their future endeavours and contribute meaningfully to society.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Core Values -->
<section class="section-block">
    <div class="container">
        <h2 class="section-title">Our Core Values</h2>
        <div class="card-grid card-grid-4">
            <div class="card card-tint-lavender">
                <div class="card-content" style="text-align: center; padding: var(--space-lg);">
                    <i class="fas fa-star" style="font-size: 2rem; color: var(--color-primary); margin-bottom: var(--space-sm); display: block;"></i>
                    <h3 class="card-title">Excellence</h3>
                    <p class="card-text">We strive for the highest standards in teaching, learning, and character development.</p>
                </div>
            </div>
            <div class="card card-tint-green">
                <div class="card-content" style="text-align: center; padding: var(--space-lg);">
                    <i class="fas fa-handshake" style="font-size: 2rem; color: var(--color-accent-green); margin-bottom: var(--space-sm); display: block;"></i>
                    <h3 class="card-title">Integrity</h3>
                    <p class="card-text">We uphold honesty, fairness, and strong moral principles in all that we do.</p>
                </div>
            </div>
            <div class="card card-tint-yellow">
                <div class="card-content" style="text-align: center; padding: var(--space-lg);">
                    <i class="fas fa-heart" style="font-size: 2rem; color: var(--color-accent-yellow); margin-bottom: var(--space-sm); display: block;"></i>
                    <h3 class="card-title">Respect</h3>
                    <p class="card-text">We foster a culture of mutual respect, kindness, and appreciation for diversity.</p>
                </div>
            </div>
            <div class="card card-tint-pink">
                <div class="card-content" style="text-align: center; padding: var(--space-lg);">
                    <i class="fas fa-lightbulb" style="font-size: 2rem; color: var(--color-accent-pink); margin-bottom: var(--space-sm); display: block;"></i>
                    <h3 class="card-title">Innovation</h3>
                    <p class="card-text">We embrace creative thinking, modern teaching methods, and continuous improvement.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Academic Programmes -->
<section class="section-block" style="background: var(--color-tint-gray);">
    <div class="container">
        <h2 class="section-title">Our Academic Programmes</h2>
        <p class="text-center" style="color: var(--color-slate); max-width: 700px; margin: -10px auto var(--space-xl);">
            We offer a complete educational journey from early childhood through junior high school.
        </p>

        <div class="card-grid card-grid-3">
            <div class="card" id="early-childhood">
                <img src="images/students/early-childhood.jpg" alt="Early Childhood students at <?php echo htmlspecialchars($school_name); ?>" loading="lazy" class="card-image">
                <div class="card-content" style="padding: var(--space-lg);">
                    <h3 class="card-title">Early Childhood</h3>
                    <p class="card-text"><strong>Creche, Nursery, KG 1 &amp; 2</strong> — Play-based learning and early development in a warm, caring environment. Our early childhood programme focuses on social, emotional, and cognitive development through structured play, music, art, and guided exploration.</p>
                </div>
            </div>
            <div class="card" id="primary">
                <img src="images/students/primary.jpg" alt="Primary students at <?php echo htmlspecialchars($school_name); ?>" loading="lazy" class="card-image">
                <div class="card-content" style="padding: var(--space-lg);">
                    <h3 class="card-title">Primary School</h3>
                    <p class="card-text"><strong>Basic 1 – 6</strong> — Comprehensive GES curriculum covering English, Mathematics, Science, Social Studies, ICT, Creative Arts, and Physical Education. We emphasize critical thinking and problem-solving skills.</p>
                </div>
            </div>
            <div class="card" id="jhs">
                <img src="images/students/jhs.jpg" alt="Junior High School students at <?php echo htmlspecialchars($school_name); ?>" loading="lazy" class="card-image">
                <div class="card-content" style="padding: var(--space-lg);">
                    <h3 class="card-title">Junior High School</h3>
                    <p class="card-text"><strong>JHS 1 – 3</strong> — Rigorous academic programme preparing students for the BECE. Subjects include core academics, pre-vocational skills, and life skills education to ensure holistic development.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section class="section-block section-photo-bg">
    <style>
        .section-photo-bg-about {
            background-image: url('images/students/students-group-1.jpg');
        }
    </style>
    <div class="container">
        <h2 class="section-title" style="color: var(--color-on-dark);">Why Choose <?php echo htmlspecialchars($school_name); ?></h2>
        <div class="section-photo-bg-content">
            <p class="hero-band-text" style="text-align: center;">Discover what makes us the right choice for your child's education journey.</p>
        </div>
        <div class="card-grid card-grid-3">
            <div class="card">
                <div class="card-content" style="text-align: center; padding: var(--space-lg);">
                    <i class="fas fa-user-tie" style="font-size: 2rem; color: var(--color-primary); margin-bottom: var(--space-md); display: block;"></i>
                    <h3 class="card-title">Experienced Teachers</h3>
                    <p class="card-text">Our dedicated teaching staff are qualified, experienced, and passionate about nurturing young minds.</p>
                </div>
            </div>
            <div class="card">
                <div class="card-content" style="text-align: center; padding: var(--space-lg);">
                    <i class="fas fa-users" style="font-size: 2rem; color: var(--color-primary); margin-bottom: var(--space-md); display: block;"></i>
                    <h3 class="card-title">Small Class Sizes</h3>
                    <p class="card-text">We maintain small class sizes to ensure every child receives personalized attention and support.</p>
                </div>
            </div>
            <div class="card">
                <div class="card-content" style="text-align: center; padding: var(--space-lg);">
                    <i class="fas fa-laptop" style="font-size: 2rem; color: var(--color-primary); margin-bottom: var(--space-md); display: block;"></i>
                    <h3 class="card-title">ICT-Integrated Learning</h3>
                    <p class="card-text">Modern ICT and computing resources are integrated into the curriculum to prepare students for the digital age.</p>
                </div>
            </div>
            <div class="card">
                <div class="card-content" style="text-align: center; padding: var(--space-lg);">
                    <i class="fas fa-shield-alt" style="font-size: 2rem; color: var(--color-primary); margin-bottom: var(--space-md); display: block;"></i>
                    <h3 class="card-title">Safe Environment</h3>
                    <p class="card-text">A secure, conducive learning environment where children feel safe, valued, and motivated to learn.</p>
                </div>
            </div>
            <div class="card">
                <div class="card-content" style="text-align: center; padding: var(--space-lg);">
                    <i class="fas fa-people-arrows" style="font-size: 2rem; color: var(--color-primary); margin-bottom: var(--space-md); display: block;"></i>
                    <h3 class="card-title">Parent Partnership</h3>
                    <p class="card-text">Regular communication and progress reports keep parents informed and engaged in their child's education.</p>
                </div>
            </div>
            <div class="card">
                <div class="card-content" style="text-align: center; padding: var(--space-lg);">
                    <i class="fas fa-medal" style="font-size: 2rem; color: var(--color-primary); margin-bottom: var(--space-md); display: block;"></i>
                    <h3 class="card-title">Character Formation</h3>
                    <p class="card-text">We focus on building strong character, discipline, and moral values alongside academic excellence.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Cards -->
<section class="section-block">
    <div class="container">
        <div class="card-grid card-grid-2">
            <div class="card card-highlight" style="text-align: center; border-top: 4px solid var(--color-primary);">
                <div class="card-content" style="padding: var(--space-xl);">
                    <i class="fas fa-map-pin" style="font-size: 2.5rem; color: var(--color-primary); margin-bottom: var(--space-md);"></i>
                    <h3>Visit Our School</h3>
                    <p style="color: var(--color-slate); margin-bottom: var(--space-lg);">
                        We welcome parents and guardians to visit our campus and see our facilities firsthand. Schedule a tour today!
                    </p>
                    <a href="contact.php" class="btn btn-primary"><i class="fas fa-calendar-alt"></i> Schedule a Visit</a>
                </div>
            </div>
            <div class="card card-highlight" style="text-align: center; border-top: 4px solid var(--color-accent-green);">
                <div class="card-content" style="padding: var(--space-xl);">
                    <i class="fas fa-file-alt" style="font-size: 2.5rem; color: var(--color-accent-green); margin-bottom: var(--space-md);"></i>
                    <h3>Admissions Open</h3>
                    <p style="color: var(--color-slate); margin-bottom: var(--space-lg);">
                        Registration is ongoing for all levels. Download the prospectus or apply online today.
                    </p>
                    <a href="register.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Enroll Now</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 3D Books Scene (shared module) -->
<script type="module">
    import { initScene } from '../js/school-3d.js';
    if (document.getElementById('about-3d-books')) {
        initScene('about-3d-books', 'books');
    }
</script>

<?php require_once 'includes/footer.php'; ?>
