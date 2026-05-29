<?php
require_once 'includes/header.php';

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}

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

<!-- Hero Band -->
<div class="hero-band-narrow">
    <div class="hero-band-content">
        <span class="badge badge-on-dark">Stay Informed</span>
        <h1 class="text-hero">News &amp; Updates</h1>
        <p class="text-on-dark-muted hero-band-text">Stay up to date with the latest happenings, achievements, and announcements from <?php echo htmlspecialchars($settings['school_name'] ?? 'Nex CEC'); ?>.</p>
    </div>
    <div id="calendar-3d" class="school-3d-container content-3d"></div>
</div>

<section class="section-block">
    <div class="container">
        <?php if (!empty($news_items)): 
            $featured = $news_items[0];
            $remaining = array_slice($news_items, 1);
            $side_items = array_slice($news_items, 1, 3);
        ?>
        <!-- Featured + Sidebar -->
        <div class="news-featured anim-stagger visible">
            <!-- Featured Main -->
            <div class="featured-main">
                <?php if (!empty($featured['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($featured['image_url']); ?>" 
                     alt="<?php echo htmlspecialchars($featured['title']); ?>"
                     onerror="this.style.display='none';">
                <?php else: ?>
                <div class="featured-placeholder">
                    <span>📰</span>
                </div>
                <?php endif; ?>
                <div class="featured-overlay">
                    <span class="badge badge-on-dark mb-sm">
                        <?php echo date('M d, Y', strtotime($featured['published_at'])); ?>
                    </span>
                    <h2><?php echo htmlspecialchars($featured['title']); ?></h2>
                    <p><?php echo htmlspecialchars(substr(strip_tags($featured['content']), 0, 150)) . '...'; ?></p>
                    <a href="<?php echo !empty($featured['source_url']) ? htmlspecialchars($featured['source_url']) : '#'; ?>" class="btn btn-primary align-self-start">
                        Read More →
                    </a>
                </div>
            </div>

            <!-- Side items -->
            <div class="featured-sidebar">
                <?php foreach ($side_items as $side): ?>
                <div class="card news-side-card" onclick="window.location.href='<?php echo !empty($side['source_url']) ? htmlspecialchars($side['source_url']) : '#'; ?>'">
                    <?php if (!empty($side['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($side['image_url']); ?>" alt="" onerror="this.style.display='none';">
                    <?php endif; ?>
                    <div class="news-meta">
                        <div class="news-date"><?php echo date('M d, Y', strtotime($side['published_at'])); ?></div>
                        <h4><?php echo htmlspecialchars($side['title']); ?></h4>
                        <p><?php echo htmlspecialchars(substr(strip_tags($side['content']), 0, 80)) . '...'; ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Remaining News Grid -->
        <?php if (!empty($remaining)): ?>
        <div class="card-grid card-grid-3">
            <?php foreach ($remaining as $item): ?>
            <div class="card-premium">
                <?php if (!empty($item['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="" class="card-image"
                     onerror="this.style.display='none';">
                <?php endif; ?>
                <div class="card-body">
                    <div class="card-date"><?php echo date('F d, Y', strtotime($item['published_at'])); ?></div>
                    <h3 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                    <p class="card-text"><?php echo htmlspecialchars(substr(strip_tags($item['content']), 0, 120)) . '...'; ?></p>
                    <?php if (!empty($item['source_url'])): ?>
                    <a href="<?php echo htmlspecialchars($item['source_url']); ?>" class="btn btn-secondary">Read Full Story →</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-state-icon">📢</div>
            <h3 class="empty-state-title">No News Yet</h3>
            <p class="empty-state-text">Stay tuned! News and updates will be posted here soon. We look forward to sharing our story with you.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
.featured-placeholder {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, var(--color-brand-navy), var(--color-charcoal));
    display: flex;
    align-items: center;
    justify-content: center;
}
.featured-placeholder span {
    font-size: 3rem;
    opacity: 0.3;
}
.align-self-start { align-self: flex-start; }
</style>

<!-- 3D Calendar Scene (shared module) -->
<script type="module">
    import { initScene } from '../js/school-3d.js';
    if (document.getElementById('calendar-3d')) {
        initScene('calendar-3d', 'calendar');
    }
</script>

<?php require_once 'includes/footer.php'; ?>
