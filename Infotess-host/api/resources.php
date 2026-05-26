<?php
require_once 'includes/header.php';

// Fetch resources
$resources = [];
try {
    $result = $pdo->query("SELECT id, title, description, file_url, category, created_at FROM resources ORDER BY created_at DESC");
    if ($result && $result->rowCount() > 0) {
        $resources = $result->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Resources table may not exist yet
}

// Group by category
$grouped = [];
foreach ($resources as $r) {
    $cat = !empty($r['category']) ? $r['category'] : 'General';
    $grouped[$cat][] = $r;
}
?>

<!-- Hero Inner -->
<section class="hero-inner">
    <div class="container" style="text-align: center; position: relative; z-index: 2;">
        <span class="badge-pill badge-gold" style="margin-bottom: 16px;">Learning Tools</span>
        <h1 style="font-size: 2.8rem; color: #fff; margin-bottom: 12px;">Resources & Downloads</h1>
        <p style="color: rgba(255,255,255,0.8); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">Access academic materials, school documents, and useful resources to support learning at Chariot Educational Complex.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (!empty($grouped)): ?>
        <?php foreach ($grouped as $category => $items): ?>
        <div class="animate-on-scroll" style="margin-bottom: 40px;">
            <h2 style="color: #003366; margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
                <?php
                $icons = ['Academic' => '📚', 'Forms' => '📋', 'Sports' => '⚽', 'General' => '📁'];
                echo $icons[$category] ?? '📁';
                ?>
                <?php echo htmlspecialchars($category); ?>
            </h2>
            <p style="color: #888; font-size: 0.88rem; margin-bottom: 16px;"><?php echo count($items); ?> resource(s) available</p>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <?php foreach ($items as $res): ?>
                <a href="<?php echo htmlspecialchars($res['file_url']); ?>" class="resource-card" target="_blank" rel="noopener">
                    <div class="resource-icon">
                        <?php
                        $ext = strtolower(pathinfo($res['file_url'], PATHINFO_EXTENSION));
                        $icons = ['pdf' => '📄', 'doc' => '📝', 'docx' => '📝', 'xls' => '📊', 'xlsx' => '📊', 'jpg' => '🖼️', 'png' => '🖼️', 'mp4' => '🎬', 'mp3' => '🎵'];
                        echo $icons[$ext] ?? '📄';
                        ?>
                    </div>
                    <div class="resource-info">
                        <h4><?php echo htmlspecialchars($res['title']); ?></h4>
                        <?php if (!empty($res['description'])): ?>
                        <p><?php echo htmlspecialchars($res['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="resource-download">⬇</div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <!-- Default Resources -->
        <div class="animate-on-scroll" style="text-align: center; margin-bottom: 40px;">
            <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;">📁</div>
            <h3 style="color: #003366; margin-bottom: 12px;">Resources Coming Soon</h3>
            <p style="color: #888; max-width: 500px; margin: 0 auto;">We are compiling helpful resources for students, parents, and teachers. Check back soon for downloadable materials.</p>
        </div>

        <!-- Coming soon categories -->
        <div class="stagger-children" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;">
            <div class="card-premium">
                <div class="card-body">
                    <div style="font-size:2rem;margin-bottom:12px;">📋</div>
                    <h3 class="card-title">School Prospectus</h3>
                    <p class="card-text">Download our detailed prospectus to learn about our curriculum, facilities, and admission process.</p>
                    <span class="badge-pill badge-navy">Coming Soon</span>
                </div>
            </div>
            <div class="card-premium">
                <div class="card-body">
                    <div style="font-size:2rem;margin-bottom:12px;">📝</div>
                    <h3 class="card-title">Admission Forms</h3>
                    <p class="card-text">Get the admission application forms and other enrollment documents for the upcoming academic year.</p>
                    <span class="badge-pill badge-navy">Coming Soon</span>
                </div>
            </div>
            <div class="card-premium">
                <div class="card-body">
                    <div style="font-size:2rem;margin-bottom:12px;">📅</div>
                    <h3 class="card-title">Academic Calendar</h3>
                    <p class="card-text">View the term dates, holidays, examination schedules, and school events calendar.</p>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <a href="/academic_calendar.php" class="btn-primary" style="padding: 6px 14px; font-size: 0.85rem; text-decoration: none; display: inline-block;">
                            <i class="fas fa-calendar-alt"></i> View Calendar
                        </a>
                        <?php
                        // Check if a calendar file exists in resources
                        $cal_file = '';
                        try {
                            $stmt_cal = $pdo->prepare("SELECT file_url FROM resources WHERE category = ? ORDER BY id DESC LIMIT 1");
                            $stmt_cal->execute(['Academic']);
                            $row_cal = $stmt_cal->fetch();
                            if ($row_cal && !empty($row_cal['file_url'])) {
                                $cal_file = $row_cal['file_url'];
                            }
                        } catch (Exception $e) {}
                        ?>
                        <?php if ($cal_file): ?>
                            <a href="<?php echo htmlspecialchars($cal_file); ?>" class="btn-secondary" style="padding: 6px 14px; font-size: 0.85rem; text-decoration: none; display: inline-block;" target="_blank" rel="noopener">
                                <i class="fas fa-download"></i> Download
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-premium">
                <div class="card-body">
                    <div style="font-size:2rem;margin-bottom:12px;">📖</div>
                    <h3 class="card-title">School Timetables</h3>
                    <p class="card-text">Access class timetables and subject schedules for all grade levels.</p>
                    <span class="badge-pill badge-navy">Coming Soon</span>
                </div>
            </div>
            <div class="card-premium">
                <div class="card-body">
                    <div style="font-size:2rem;margin-bottom:12px;">📊</div>
                    <h3 class="card-title">Academic Reports</h3>
                    <p class="card-text">Sample academic report formats and grading guidelines for parents and students.</p>
                    <span class="badge-pill badge-navy">Coming Soon</span>
                </div>
            </div>
            <div class="card-premium">
                <div class="card-body">
                    <div style="font-size:2rem;margin-bottom:12px;">📢</div>
                    <h3 class="card-title">Parent Notices</h3>
                    <p class="card-text">Download important notices, circulars, and communication letters for parents and guardians.</p>
                    <span class="badge-pill badge-navy">Coming Soon</span>
                </div>
            </div>
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

<?php require_once 'includes/footer.php'; ?>
