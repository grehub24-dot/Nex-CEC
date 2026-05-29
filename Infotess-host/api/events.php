<?php
require_once 'includes/header.php';

// Fetch events
$events = [];
try {
    $result = $pdo->query("SELECT id, title, description, event_date, location, source_url FROM events ORDER BY event_date ASC");
    if ($result && $result->rowCount() > 0) {
        $events = $result->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Events table may not exist yet
}

$has_upcoming = false;
$has_past = false;
$upcoming_events = [];
$past_events = [];
$now = date('Y-m-d');

foreach ($events as $ev) {
    if ($ev['event_date'] >= $now) {
        $upcoming_events[] = $ev;
        $has_upcoming = true;
    } else {
        $past_events[] = $ev;
        $has_past = true;
    }
}
?>

<!-- Hero Inner -->
<section class="hero-inner">
    <div class="container" style="text-align: center; position: relative; z-index: 2;">
        <span class="badge-pill badge-gold" style="margin-bottom: 16px;">Save the Date</span>
        <h1 style="font-size: 2.8rem; color: #fff; margin-bottom: 12px;">Upcoming Events</h1>
        <p style="color: rgba(255,255,255,0.8); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">Mark your calendars! Stay connected with the latest school events, activities, and important dates at Chariot Educational Complex.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if ($has_upcoming): ?>
        <!-- Upcoming Events Timeline -->
        <div class="animate-on-scroll" style="margin-bottom: 48px;">
            <h2 style="color: #003366; margin-bottom: 8px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.5rem;">📅</span> Upcoming Events
            </h2>
            <p style="color: #888; margin-bottom: 28px;">Events you won't want to miss this term.</p>
            <div class="timeline">
                <?php foreach ($upcoming_events as $ev): 
                    $day = date('d', strtotime($ev['event_date']));
                    $month = date('M', strtotime($ev['event_date']));
                    $year = date('Y', strtotime($ev['event_date']));
                ?>
                <div class="timeline-item animate-on-scroll">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content" style="display:flex;gap:16px;align-items:flex-start;">
                        <div style="text-align:center;flex-shrink:0;background:#003366;color:#ffcc00;border-radius:12px;padding:8px 14px;min-width:60px;">
                            <div style="font-size:1.3rem;font-weight:800;line-height:1;"><?php echo $day; ?></div>
                            <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:1px;"><?php echo $month; ?></div>
                            <div style="font-size:0.65rem;opacity:0.7;"><?php echo $year; ?></div>
                        </div>
                        <div>
                            <h3><?php echo htmlspecialchars($ev['title']); ?></h3>
                            <p><?php echo htmlspecialchars($ev['description'] ?? 'No description available.'); ?></p>
                            <?php if (!empty($ev['location'])): ?>
                            <div style="font-size:0.82rem;color:#888;margin-top:8px;">
                                <span>📍</span> <?php echo htmlspecialchars($ev['location']); ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($ev['source_url'])): ?>
                            <a href="<?php echo htmlspecialchars($ev['source_url']); ?>" class="btn-gold" style="margin-top:10px;padding:8px 20px;font-size:0.82rem;display:inline-flex;">More Info →</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($has_past): ?>
        <!-- Past Events -->
        <div class="animate-on-scroll">
            <h2 style="color: #003366; margin-bottom: 8px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.5rem;">📖</span> Past Events
            </h2>
            <p style="color: #888; margin-bottom: 28px;">Highlights from our recent activities.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;">
                <?php
                $sorted_past = array_reverse($past_events);
                foreach ($sorted_past as $ev): 
                ?>
                <div class="card-premium">
                    <div class="card-body">
                        <div class="card-date">
                            <span>📅</span> <?php echo date('F d, Y', strtotime($ev['event_date'])); ?>
                        </div>
                        <h3 class="card-title"><?php echo htmlspecialchars($ev['title']); ?></h3>
                        <p class="card-text"><?php echo htmlspecialchars($ev['description'] ?? 'No description.'); ?></p>
                        <?php if (!empty($ev['location'])): ?>
                        <div style="font-size:0.82rem;color:#888;margin-top:8px;">
                            <span>📍</span> <?php echo htmlspecialchars($ev['location']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$has_upcoming && !$has_past): ?>
        <!-- Empty State -->
        <div class="animate-on-scroll" style="text-align: center; padding: 60px 20px;">
            <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;">🗓️</div>
            <h3 style="color: #003366; margin-bottom: 12px;">No Events Scheduled</h3>
            <p style="color: #888; max-width: 500px; margin: 0 auto;">There are no events posted yet. Check back soon for updates on school activities, parent-teacher meetings, and special celebrations at Chariot Educational Complex.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
(function() {
    var els = document.querySelectorAll('.animate-on-scroll');
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
