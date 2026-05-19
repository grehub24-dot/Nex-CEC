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
<section class="hero-inner" style="background: linear-gradient(135deg, #002244 0%, #003366 50%, #004080 100%);">
    <div class="container" style="text-align: center; position: relative; z-index: 2;">
        <span class="badge-pill badge-gold" style="margin-bottom: 16px;">Stay Informed</span>
        <h1 style="font-size: 2.8rem; color: #fff; margin-bottom: 12px;">News & Updates</h1>
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

<?php require_once 'includes/footer.php'; ?>
