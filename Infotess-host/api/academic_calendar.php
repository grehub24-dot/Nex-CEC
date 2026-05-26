<?php
require_once 'includes/header.php';

// Fetch events
$events = [];
try {
    $result = $pdo->query("SELECT * FROM academic_calendar_events ORDER BY event_date ASC");
    if ($result && $result->rowCount() > 0) {
        $events = $result->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

// Fetch calendar file URL from resources
$calendar_file_url = '';
try {
    $stmt = $pdo->prepare("SELECT file_url FROM resources WHERE category = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute(['Academic']);
    $row = $stmt->fetch();
    if ($row && !empty($row['file_url'])) {
        $calendar_file_url = $row['file_url'];
    }
} catch (Exception $e) {}

// Group events by month
$grouped_by_month = [];
$now = date('Y-m-d');
$total_events = count($events);
$current_status = 'In Session';

foreach ($events as $ev) {
    $month_key = date('Y-m', strtotime($ev['event_date']));
    $month_label = date('F Y', strtotime($ev['event_date']));
    if (!isset($grouped_by_month[$month_key])) {
        $grouped_by_month[$month_key] = ['label' => $month_label, 'events' => []];
    }
    $grouped_by_month[$month_key]['events'][] = $ev;
}
ksort($grouped_by_month);

$calendar_start = $settings['academic_calendar_start'] ?? '—';
$calendar_end = $settings['academic_calendar_end'] ?? '—';
$term_label = $settings['calendar_term_label'] ?? '3rd Term — 2025/2026 Academic Year';

// Determine status: check if today is in a break period
foreach ($events as $ev) {
    $start = $ev['event_date'];
    $end = $ev['end_date'] ?? $start;
    if ($ev['event_type'] === 'break' && $now >= $start && $now <= $end) {
        $current_status = 'On Break';
        break;
    }
    if ($ev['event_type'] === 'holiday' && $now === $start) {
        $current_status = 'Holiday';
        break;
    }
}

$type_labels = [
    'event'    => 'Event',
    'holiday'  => 'Holiday',
    'exam'     => 'Exam',
    'training' => 'Training',
    'break'    => 'Break',
];
$type_colors = [
    'event'    => '#3498db',
    'holiday'  => '#e74c3c',
    'exam'     => '#e67e22',
    'training' => '#1abc9c',
    'break'    => '#95a5a6',
];
?>

<style>
    .calendar-hero {
        background: linear-gradient(135deg, #003366 0%, #004d99 100%);
        padding: 60px 0;
        text-align: center;
        color: #fff;
    }
    .calendar-hero h1 {
        font-size: 2.5rem;
        margin-bottom: 8px;
        color: #fff;
    }
    .calendar-hero p {
        font-size: 1.1rem;
        opacity: 0.85;
        margin-bottom: 4px;
    }
    .calendar-hero .badge-gold {
        display: inline-block;
        background: #ffcc00;
        color: #003366;
        padding: 6px 18px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
        margin-bottom: 16px;
    }
    .summary-bar {
        display: flex;
        gap: 20px;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 30px;
    }
    .summary-item {
        background: rgba(255,255,255,0.12);
        backdrop-filter: blur(4px);
        padding: 14px 24px;
        border-radius: 10px;
        text-align: center;
        min-width: 140px;
    }
    .summary-item .value {
        font-size: 1.1rem;
        font-weight: 700;
    }
    .summary-item .label {
        font-size: 0.75rem;
        opacity: 0.75;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .summary-item .status-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 6px;
        vertical-align: middle;
    }
    .status-in-session .status-dot { background: #2ecc71; }
    .status-on-break .status-dot { background: #e74c3c; }
    .status-holiday .status-dot { background: #f39c12; }

    .month-section {
        margin-bottom: 40px;
    }
    .month-section .month-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: #003366;
        padding-bottom: 10px;
        border-bottom: 3px solid #ffcc00;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .event-card {
        display: flex;
        gap: 16px;
        padding: 16px 18px;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        margin-bottom: 12px;
        border-left: 4px solid #ddd;
        transition: transform 0.15s, box-shadow 0.15s;
    }
    .event-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .event-card .date-box {
        flex-shrink: 0;
        text-align: center;
        background: #f8f9fa;
        border-radius: 8px;
        padding: 8px 14px;
        min-width: 60px;
    }
    .event-card .date-box .day {
        font-size: 1.3rem;
        font-weight: 800;
        line-height: 1.1;
        color: #003366;
    }
    .event-card .date-box .month {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #888;
    }
    .event-card .date-range {
        flex-shrink: 0;
        text-align: center;
        background: #f0f7ff;
        border-radius: 8px;
        padding: 8px 14px;
        min-width: 72px;
    }
    .event-card .date-range .range-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: #888;
    }
    .event-card .event-body {
        flex: 1;
    }
    .event-card .event-body h4 {
        font-size: 1rem;
        margin-bottom: 4px;
        color: #003366;
    }
    .event-card .event-body p {
        font-size: 0.85rem;
        color: #888;
        margin: 0;
    }
    .event-card .type-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        color: #fff;
    }
    .event-card .color-bar {
        width: 4px;
        flex-shrink: 0;
        border-radius: 2px;
        align-self: stretch;
    }
    .dl-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #ffcc00;
        color: #003366;
        padding: 12px 28px;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        margin-top: 20px;
        transition: background 0.2s;
    }
    .dl-btn:hover {
        background: #e6b800;
        color: #003366;
    }
    @media (max-width: 768px) {
        .calendar-hero h1 { font-size: 1.8rem; }
        .event-card { flex-wrap: wrap; }
        .event-card .date-box { min-width: 50px; }
        .summary-item { min-width: 100px; padding: 10px 16px; }
    }
</style>

<!-- Hero -->
<section class="calendar-hero">
    <div class="container">
        <span class="badge-gold"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($term_label); ?></span>
        <h1>Academic Calendar</h1>
        <p>Stay informed with important dates, exams, holidays, and school events.</p>

        <div class="summary-bar">
            <div class="summary-item">
                <div class="value"><?php echo htmlspecialchars($calendar_start); ?></div>
                <div class="label">Start Date</div>
            </div>
            <div class="summary-item">
                <div class="value"><?php echo htmlspecialchars($calendar_end); ?></div>
                <div class="label">End Date</div>
            </div>
            <div class="summary-item">
                <div class="value"><?php echo $total_events; ?></div>
                <div class="label">Total Events</div>
            </div>
            <div class="summary-item <?php
                echo $current_status === 'In Session' ? 'status-in-session' : ($current_status === 'On Break' ? 'status-on-break' : 'status-holiday');
            ?>">
                <div class="value"><span class="status-dot"></span><?php echo $current_status; ?></div>
                <div class="label">Current Status</div>
            </div>
        </div>

        <?php if (!empty($calendar_file_url)): ?>
            <a href="<?php echo htmlspecialchars($calendar_file_url); ?>" target="_blank" class="dl-btn" rel="noopener">
                <i class="fas fa-download"></i> Download Full Planner
            </a>
        <?php endif; ?>
    </div>
</section>

<!-- Events Timeline -->
<section class="section">
    <div class="container">
        <?php if (empty($events)): ?>
            <div style="text-align: center; padding: 60px 20px;">
                <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;">🗓️</div>
                <h3 style="color: #003366; margin-bottom: 12px;">No Events Scheduled</h3>
                <p style="color: #888; max-width: 500px; margin: 0 auto;">The academic calendar has not been published yet. Check back soon for updates.</p>
            </div>
        <?php else: ?>
            <?php foreach ($grouped_by_month as $month_key => $month_data): ?>
                <div class="month-section animate-on-scroll">
                    <div class="month-title">
                        <i class="fas fa-calendar-alt" style="color: #ffcc00;"></i>
                        <?php echo htmlspecialchars($month_data['label']); ?>
                    </div>
                    <?php foreach ($month_data['events'] as $ev):
                        $ev_color = $ev['color'] ?? '#3498db';
                        $type_label = $type_labels[$ev['event_type']] ?? ucfirst($ev['event_type']);
                        $start_day = date('d', strtotime($ev['event_date']));
                        $start_month = date('M', strtotime($ev['event_date']));
                        $is_multi = !empty($ev['end_date']) && $ev['end_date'] !== $ev['event_date'];
                    ?>
                        <div class="event-card" style="border-left-color: <?php echo $ev_color; ?>;">
                            <?php if ($is_multi): ?>
                                <div class="date-range">
                                    <div class="day" style="font-size:1rem;"><?php echo $start_day; ?></div>
                                    <div class="range-label">to</div>
                                    <div class="day" style="font-size:1rem;"><?php echo date('d', strtotime($ev['end_date'])); ?></div>
                                    <div class="month" style="font-size:0.6rem;"><?php echo $start_month; ?></div>
                                </div>
                            <?php else: ?>
                                <div class="date-box">
                                    <div class="day"><?php echo $start_day; ?></div>
                                    <div class="month"><?php echo $start_month; ?></div>
                                </div>
                            <?php endif; ?>
                            <div class="event-body">
                                <h4><?php echo htmlspecialchars($ev['title']); ?></h4>
                                <?php if (!empty($ev['description'])): ?>
                                    <p><?php echo htmlspecialchars($ev['description']); ?></p>
                                <?php endif; ?>
                                <div style="margin-top: 6px;">
                                    <span class="type-badge" style="background: <?php echo $type_colors[$ev['event_type']] ?? '#3498db'; ?>">
                                        <?php echo $type_label; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
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
