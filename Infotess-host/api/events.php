<?php
require_once 'includes/header.php';

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}

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

<!-- Hero Band -->
<div class="hero-band-narrow">
    <div class="hero-band-content">
        <span class="badge badge-on-dark">Save the Date</span>
        <h1 class="text-hero">Upcoming Events</h1>
        <p class="text-on-dark-muted hero-band-text">Mark your calendars! Stay connected with the latest school events, activities, and important dates at <?php echo htmlspecialchars($settings['school_name'] ?? 'Nex CEC'); ?>.</p>
    </div>
    <div id="events-3d" class="school-3d-container content-3d"></div>
</div>

<section class="section-block">
    <div class="container">
        <?php if ($has_upcoming): ?>
        <!-- Upcoming Events Timeline -->
        <div class="anim-stagger visible timeline-block">
            <h2 class="section-title">📅 Upcoming Events</h2>
            <p class="timeline-subtitle">Events you won't want to miss this term.</p>
            <div class="timeline">
                <?php foreach ($upcoming_events as $ev): 
                    $day = date('d', strtotime($ev['event_date']));
                    $month = date('M', strtotime($ev['event_date']));
                    $year = date('Y', strtotime($ev['event_date']));
                ?>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="timeline-date-box">
                            <div class="day"><?php echo $day; ?></div>
                            <div class="month"><?php echo $month; ?></div>
                            <div class="year"><?php echo $year; ?></div>
                        </div>
                        <div class="timeline-info">
                            <h3><?php echo htmlspecialchars($ev['title']); ?></h3>
                            <p class="timeline-desc"><?php echo htmlspecialchars($ev['description'] ?? 'No description available.'); ?></p>
                            <?php if (!empty($ev['location'])): ?>
                            <div class="timeline-location">📍 <?php echo htmlspecialchars($ev['location']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($ev['source_url'])): ?>
                            <a href="<?php echo htmlspecialchars($ev['source_url']); ?>" class="btn btn-primary">More Info →</a>
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
        <div class="past-block">
            <h2 class="section-title">📖 Past Events</h2>
            <p class="past-subtitle">Highlights from our recent activities.</p>
            <div class="card-grid card-grid-3">
                <?php
                $sorted_past = array_reverse($past_events);
                foreach ($sorted_past as $ev): 
                ?>
                <div class="card-premium">
                    <div class="card-body">
                        <div class="card-date">📅 <?php echo date('F d, Y', strtotime($ev['event_date'])); ?></div>
                        <h3 class="card-title"><?php echo htmlspecialchars($ev['title']); ?></h3>
                        <p class="card-text"><?php echo htmlspecialchars($ev['description'] ?? 'No description.'); ?></p>
                        <?php if (!empty($ev['location'])): ?>
                        <div class="event-location">📍 <?php echo htmlspecialchars($ev['location']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$has_upcoming && !$has_past): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-state-icon">🗓️</div>
            <h3 class="empty-state-title">No Events Scheduled</h3>
            <p class="empty-state-text">There are no events posted yet. Check back soon for updates on school activities, parent-teacher meetings, and special celebrations.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
.timeline-block { margin-bottom: var(--space-section); }
.timeline-subtitle,
.past-subtitle {
    color: var(--color-slate);
    margin-bottom: var(--space-lg);
}
.timeline-content {
    display: flex;
    gap: var(--space-md);
    align-items: flex-start;
}
.timeline-info h3 {
    margin: 0 0 var(--space-xs);
}
.timeline-desc {
    color: var(--color-slate);
    margin: 0 0 var(--space-sm);
}
.timeline-location {
    font-size: var(--text-sm-size);
    color: var(--color-steel);
    margin-bottom: var(--space-sm);
}
.event-location {
    font-size: var(--text-sm-size);
    color: var(--color-steel);
    margin-top: var(--space-sm);
}
</style>

<!-- 3D Calendar Scene (shared module) -->
<script type="module">
    import { initScene } from '../js/school-3d.js';
    if (document.getElementById('events-3d')) {
        initScene('events-3d', 'calendar');
    }
</script>

<?php require_once 'includes/footer.php'; ?>
