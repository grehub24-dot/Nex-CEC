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

<!-- Hero Inner -->
<div class="hero-inner">
    <h1>About <?php echo htmlspecialchars($school_name); ?></h1>
    <p>Discover our story, mission, and the values that guide us in shaping the next generation of leaders.</p>
</div>

<!-- About Intro -->
<section class="section">
    <div class="container">
        <div class="photo-split">
            <div class="photo-split-content">
                <h2 style="font-size: var(--text-3xl); color: var(--color-primary); margin-bottom: var(--space-6);">Our Story</h2>
                <p><?php echo htmlspecialchars($school_name); ?> is a nurturing learning environment dedicated to building strong academic foundations, character development, and holistic growth for every child from Creche to Junior High School.</p>
                <p>Our school is committed to the Ghana Education Service curriculum while fostering creativity, discipline, and a love for lifelong learning. We believe in partnering with parents to provide the best possible educational experience for every child in a safe, supportive, and stimulating environment.</p>
            </div>
            <div class="photo-split-image">
                <img src="images/story-photo.jpg" alt="<?php echo htmlspecialchars($school_name); ?> classroom learning" loading="lazy">
            </div>
        </div>
    </div>
</section>

<!-- Mission & Vision -->
<section id="mission" class="section" style="background: var(--color-gray-50); padding-top: var(--space-16);">
    <div class="container">
        <h2 class="section-title">Our Mission & Vision</h2>
        <div class="mission-vision-grid">
            <div class="mission-card">
                <i class="fas fa-bullseye"></i>
                <h3>Our Mission</h3>
                <p>To provide quality basic education that develops the intellectual, moral, and physical potential of every child in a safe and supportive environment, preparing them to become responsible and productive citizens.</p>
            </div>
            <div class="vision-card">
                <i class="fas fa-eye"></i>
                <h3>Our Vision</h3>
                <p>To be a leading basic school that produces well-rounded, confident, and academically excellent students who excel in their future endeavours and contribute meaningfully to society.</p>
            </div>
        </div>

        <!-- School Values -->
        <h2 class="section-title" style="margin-top: var(--space-12);">Our Core Values</h2>
        <div class="values-grid">
            <div class="value-card">
                <div class="value-icon"><i class="fas fa-star"></i></div>
                <h4>Excellence</h4>
                <p>We strive for the highest standards in teaching, learning, and character development.</p>
            </div>
            <div class="value-card">
                <div class="value-icon"><i class="fas fa-handshake"></i></div>
                <h4>Integrity</h4>
                <p>We uphold honesty, fairness, and strong moral principles in all that we do.</p>
            </div>
            <div class="value-card">
                <div class="value-icon"><i class="fas fa-heart"></i></div>
                <h4>Respect</h4>
                <p>We foster a culture of mutual respect, kindness, and appreciation for diversity.</p>
            </div>
            <div class="value-card">
                <div class="value-icon"><i class="fas fa-lightbulb"></i></div>
                <h4>Innovation</h4>
                <p>We embrace creative thinking, modern teaching methods, and continuous improvement.</p>
            </div>
        </div>
    </div>
</section>

<!-- Programmes Details -->
<section class="section" id="programmes">
    <div class="container">
        <h2 class="section-title">Our Academic Programmes</h2>
        <p style="text-align: center; max-width: 700px; margin: -20px auto 50px; color: var(--text-muted);">
            We offer a complete educational journey from early childhood through junior high school.
        </p>

        <div class="photo-card-grid" style="margin-top: 30px;">
            <div class="photo-card" id="early-childhood">
                <div class="photo-card-image">
                    <img src="images/students/early-childhood.jpg" alt="Early Childhood students at <?php echo htmlspecialchars($school_name); ?>" loading="lazy">
                </div>
                <div class="photo-card-body">
                    <h3>Early Childhood</h3>
                    <p><strong>Creche, Nursery, KG 1 & 2</strong> — Play-based learning and early development in a warm, caring environment. Our early childhood programme focuses on social, emotional, and cognitive development through structured play, music, art, and guided exploration.</p>
                </div>
            </div>
            <div class="photo-card" id="primary">
                <div class="photo-card-image">
                    <img src="images/students/primary.jpg" alt="Primary students at <?php echo htmlspecialchars($school_name); ?>" loading="lazy">
                </div>
                <div class="photo-card-body">
                    <h3>Primary School</h3>
                    <p><strong>Basic 1 – 6</strong> — Comprehensive GES curriculum covering English, Mathematics, Science, Social Studies, ICT, Creative Arts, and Physical Education. We emphasize critical thinking and problem-solving skills.</p>
                </div>
            </div>
            <div class="photo-card" id="jhs">
                <div class="photo-card-image">
                    <img src="images/students/jhs.jpg" alt="Junior High School students at <?php echo htmlspecialchars($school_name); ?>" loading="lazy">
                </div>
                <div class="photo-card-body">
                    <h3>Junior High School</h3>
                    <p><strong>JHS 1 – 3</strong> — Rigorous academic programme preparing students for the BECE. Subjects include core academics, pre-vocational skills, and life skills education to ensure holistic development.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section class="section section-photo-bg section-photo-bg-about" style="background: var(--color-gray-50);">
    <style>
        .section-photo-bg-about::before {
            background-image: url('images/students/students-group-1.jpg');
        }
    </style>
    <div class="container">
        <h2 class="section-title">Why Choose <?php echo htmlspecialchars($school_name); ?></h2>
        <div class="card-grid">
            <div class="card">
                <div class="card-content" style="text-align: center; padding: 30px;">
                    <i class="fas fa-user-tie" style="font-size: 2rem; color: var(--color-primary); margin-bottom: 15px;"></i>
                    <h3 class="card-title">Experienced Teachers</h3>
                    <p>Our dedicated teaching staff are qualified, experienced, and passionate about nurturing young minds.</p>
                </div>
            </div>
            <div class="card">
                <div class="card-content" style="text-align: center; padding: 30px;">
                    <i class="fas fa-users" style="font-size: 2rem; color: var(--color-primary); margin-bottom: 15px;"></i>
                    <h3 class="card-title">Small Class Sizes</h3>
                    <p>We maintain small class sizes to ensure every child receives personalized attention and support.</p>
                </div>
            </div>
            <div class="card">
                <div class="card-content" style="text-align: center; padding: 30px;">
                    <i class="fas fa-laptop" style="font-size: 2rem; color: var(--color-primary); margin-bottom: 15px;"></i>
                    <h3 class="card-title">ICT-Integrated Learning</h3>
                    <p>Modern ICT and computing resources are integrated into the curriculum to prepare students for the digital age.</p>
                </div>
            </div>
            <div class="card">
                <div class="card-content" style="text-align: center; padding: 30px;">
                    <i class="fas fa-shield-alt" style="font-size: 2rem; color: var(--color-primary); margin-bottom: 15px;"></i>
                    <h3 class="card-title">Safe Environment</h3>
                    <p>A secure, conducive learning environment where children feel safe, valued, and motivated to learn.</p>
                </div>
            </div>
            <div class="card">
                <div class="card-content" style="text-align: center; padding: 30px;">
                    <i class="fas fa-people-arrows" style="font-size: 2rem; color: var(--color-primary); margin-bottom: 15px;"></i>
                    <h3 class="card-title">Parent Partnership</h3>
                    <p>Regular communication and progress reports keep parents informed and engaged in their child's education.</p>
                </div>
            </div>
            <div class="card">
                <div class="card-content" style="text-align: center; padding: 30px;">
                    <i class="fas fa-medal" style="font-size: 2rem; color: var(--color-primary); margin-bottom: 15px;"></i>
                    <h3 class="card-title">Character Formation</h3>
                    <p>We focus on building strong character, discipline, and moral values alongside academic excellence.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Info -->
<section class="section">
    <div class="container">
        <div class="contact-grid">
            <div class="contact-form-card" style="text-align: center;">
                <i class="fas fa-map-pin" style="font-size: 2.5rem; color: var(--color-primary); margin-bottom: 20px;"></i>
                <h3 style="justify-content: center;">Visit Our School</h3>
                <p style="color: var(--text-muted); margin-bottom: 20px;">
                    We welcome parents and guardians to visit our campus and see our facilities firsthand. Schedule a tour today!
                </p>
                <a href="contact.php" class="btn-primary"><i class="fas fa-calendar-alt"></i> Schedule a Visit</a>
            </div>
            <div class="contact-form-card" style="text-align: center;">
                <i class="fas fa-file-alt" style="font-size: 2.5rem; color: var(--color-primary); margin-bottom: 20px;"></i>
                <h3 style="justify-content: center;">Admissions Open</h3>
                <p style="color: var(--text-muted); margin-bottom: 20px;">
                    Registration is ongoing for all levels. Download the prospectus or apply online today.
                </p>
                <a href="register.php" class="btn-primary"><i class="fas fa-user-plus"></i> Enroll Now</a>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
