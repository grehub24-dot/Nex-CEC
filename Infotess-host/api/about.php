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

<div class="container" style="padding: 40px 0;">
    <h1>About <?php echo htmlspecialchars($school_name); ?></h1>
    <p><?php echo htmlspecialchars($school_name); ?> is a nurturing learning environment dedicated to building strong academic foundations, character development, and holistic growth for every child from Creche to Junior High School.</p>
    <p>Our school is committed to the Ghana Education Service curriculum while fostering creativity, discipline, and a love for lifelong learning.</p>
</div>

<div class="section" style="padding-top: 0;">
    <div class="container">
        <div class="card" style="margin-bottom: 25px;">
            <div class="card-content">
                <h2 class="section-title" style="margin-bottom: 20px;">Our Mission</h2>
                <p>To provide quality basic education that develops the intellectual, moral, and physical potential of every child in a safe and supportive environment.</p>
            </div>
        </div>

        <div class="card" style="margin-bottom: 25px;">
            <div class="card-content">
                <h2 class="section-title" style="margin-bottom: 20px;">What We Offer</h2>
                <ul style="list-style: disc; padding-left: 20px;">
                    <li><strong>Early Childhood:</strong> Creche, Nursery, KG 1 & 2 — play-based learning and early development.</li>
                    <li><strong>Primary School:</strong> Basic 1–6 — comprehensive GES curriculum with core and creative subjects.</li>
                    <li><strong>Junior High School:</strong> JHS 1–3 — BECE preparation with strong academics and life skills.</li>
                </ul>
            </div>
        </div>

        <div class="card" style="margin-bottom: 25px;">
            <div class="card-content">
                <h2 class="section-title" style="margin-bottom: 20px;">Why Choose Us</h2>
                <ul style="list-style: disc; padding-left: 20px;">
                    <li>Experienced and dedicated teaching staff.</li>
                    <li>Small class sizes for personalized attention.</li>
                    <li>ICT and Computing integrated into the curriculum.</li>
                    <li>Safe and conducive learning environment.</li>
                    <li>Regular parent-teacher communication and progress reports.</li>
                </ul>
            </div>
        </div>

        <div class="card" style="margin-bottom: 25px;">
            <div class="card-content">
                <h2 class="section-title" style="margin-bottom: 20px;">School Contact</h2>
                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($settings['school_address'] ?? 'School Address, City, Ghana'); ?></p>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($settings['school_email'] ?? 'info@school.edu.gh'); ?></p>
                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($settings['school_phone'] ?? '+233 XX XXX XXXX'); ?></p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
