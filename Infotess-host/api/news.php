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
        <span class="badge badge-on-dark" style="margin-bottom: var(--space-md);">Stay Informed</span>
        <h1 class="text-hero">News &amp; Updates</h1>
        <p class="text-on-dark-muted hero-band-text">Stay up to date with the latest happenings, achievements, and announcements from <?php echo htmlspecialchars($settings['school_name'] ?? 'Nex CEC'); ?>.</p>
    </div>
    <div id="calendar-3d" class="school-3d-container content-3d" style="position: relative; margin: 0 auto; width: 100%; max-width: 400px; height: 300px; z-index: 2;"></div>
</div>

<section class="section-block">
    <div class="container">
        <?php if (!empty($news_items)): 
            $featured = $news_items[0];
            $remaining = array_slice($news_items, 1);
            $side_items = array_slice($news_items, 1, 3);
        ?>
        <!-- Featured Post -->
        <div class="card-grid card-grid-2 anim-stagger visible" style="margin-bottom: var(--space-xl);">
            <div class="card" style="position: relative; min-height: 350px; overflow: hidden; padding: 0;">
                <?php if (!empty($featured['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($featured['image_url']); ?>" 
                     alt="<?php echo htmlspecialchars($featured['title']); ?>"
                     style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover;"
                     onerror="this.style.display='none';">
                <?php else: ?>
                <div style="position:absolute;inset:0;background:linear-gradient(135deg,var(--color-brand-navy),var(--color-charcoal));display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:3rem;opacity:0.3;">📰</span>
                </div>
                <?php endif; ?>
                <div style="position: absolute; inset: 0; background: linear-gradient(transparent 30%, rgba(0,0,0,0.75)); display: flex; flex-direction: column; justify-content: flex-end; padding: var(--space-xl); color: var(--color-on-dark);">
                    <span class="badge badge-on-dark" style="align-self: flex-start; margin-bottom: var(--space-sm);">
                        <?php echo date('M d, Y', strtotime($featured['published_at'])); ?>
                    </span>
                    <h2 style="font-size: var(--text-h3-size); font-weight: 600; margin: 0 0 var(--space-xs); color: var(--color-on-dark);"><?php echo htmlspecialchars($featured['title']); ?></h2>
                    <p style="font-size: var(--text-sm-size); color: var(--color-on-dark-muted); margin-bottom: var(--space-sm);"><?php echo htmlspecialchars(substr(strip_tags($featured['content']), 0, 150)) . '...'; ?></p>
                    <a href="<?php echo !empty($featured['source_url']) ? htmlspecialchars($featured['source_url']) : '#'; ?>" class="btn btn-primary" style="align-self: flex-start;">
                        Read More →
                    </a>
                </div>
            </div>

            <!-- Side items -->
            <div style="display: flex; flex-direction: column; gap: var(--space-md);">
                <?php foreach ($side_items as $side): ?>
                <div class="card" style="display: flex; flex-direction: row; min-height: 0; cursor: pointer; padding: 0; overflow: hidden;" onclick="window.location.href='<?php echo !empty($side['source_url']) ? htmlspecialchars($side['source_url']) : '#'; ?>'">
                    <?php if (!empty($side['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($side['image_url']); ?>" alt="" style="width: 120px; height: 120px; object-fit: cover; flex-shrink: 0;" onerror="this.style.display='none';">
                    <?php endif; ?>
                    <div style="padding: var(--space-sm) var(--space-md);">
                        <div style="font-size: var(--text-caption-size); color: var(--color-steel); margin-bottom: var(--space-xs);"><?php echo date('M d, Y', strtotime($side['published_at'])); ?></div>
                        <h4 style="font-size: 0.95rem; font-weight: 600; color: var(--color-charcoal); margin-bottom: 4px;"><?php echo htmlspecialchars($side['title']); ?></h4>
                        <p style="font-size: 0.82rem; color: var(--color-slate); margin: 0;"><?php echo htmlspecialchars(substr(strip_tags($side['content']), 0, 80)) . '...'; ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Remaining News Grid -->
        <?php if (!empty($remaining)): ?>
        <div class="card-grid card-grid-3">
            <?php foreach ($remaining as $item): ?>
            <div class="card">
                <?php if (!empty($item['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="" class="card-image"
                     onerror="this.style.display='none';">
                <?php endif; ?>
                <div class="card-content" style="padding: var(--space-md) var(--space-lg);">
                    <div style="font-size: var(--text-caption-size); color: var(--color-steel); margin-bottom: var(--space-xs);">
                        <?php echo date('F d, Y', strtotime($item['published_at'])); ?>
                    </div>
                    <h3 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                    <p class="card-text"><?php echo htmlspecialchars(substr(strip_tags($item['content']), 0, 120)) . '...'; ?></p>
                    <?php if (!empty($item['source_url'])): ?>
                    <a href="<?php echo htmlspecialchars($item['source_url']); ?>" class="btn btn-secondary" style="margin-top: var(--space-sm); display: inline-flex;">Read Full Story →</a>
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
            <h3 style="color: var(--color-charcoal); margin-bottom: var(--space-sm);">No News Yet</h3>
            <p style="color: var(--color-steel); max-width: 500px; margin: 0 auto;">Stay tuned! News and updates will be posted here soon. We look forward to sharing our story with you.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- 3D Calendar Scene (shared module) -->
<script type="module">
    import { initScene } from '../js/school-3d.js';
    if (document.getElementById('calendar-3d')) {
        initScene('calendar-3d', 'calendar');
    }
</script>

<?php require_once 'includes/footer.php'; ?>
