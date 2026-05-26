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

// Fetch upcoming calendar events
$upcoming_events = [];
try {
    $stmt_ev = $pdo->query("SELECT id, title, description, event_date, end_date, event_type, location, color FROM academic_calendar_events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 6");
    if ($stmt_ev && $stmt_ev->rowCount() > 0) {
        $upcoming_events = $stmt_ev->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

$event_type_labels = [
    'event'    => 'Event',
    'holiday'  => 'Holiday',
    'exam'     => 'Exam',
    'training' => 'Training',
    'break'    => 'Break',
];
$event_type_colors = [
    'event'    => '#3498db',
    'holiday'  => '#e74c3c',
    'exam'     => '#e67e22',
    'training' => '#1abc9c',
    'break'    => '#95a5a6',
];
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
                <?php foreach ($items as $res):
                    $res_url = !empty($res['file_url']) ? trim($res['file_url']) : '';
                    // Fallback: calendar resource with no uploaded file → link to academic calendar page
                    if (empty($res_url) && stripos($res['title'], 'calendar') !== false) {
                        $res_url = '/academic_calendar.php';
                    }
                    $has_link = !empty($res_url);
                    $ext = !empty($res['file_url']) ? strtolower(pathinfo($res['file_url'], PATHINFO_EXTENSION)) : '';
                    $icon_map = ['pdf' => '📄', 'doc' => '📝', 'docx' => '📝', 'xls' => '📊', 'xlsx' => '📊', 'jpg' => '🖼️', 'png' => '🖼️', 'mp4' => '🎬', 'mp3' => '🎵'];
                    $icon = $ext && isset($icon_map[$ext]) ? $icon_map[$ext] : (stripos($res['title'], 'calendar') !== false ? '📅' : '📄');
                ?>
                <?php if ($has_link): ?>
                <a href="<?php echo htmlspecialchars($res_url); ?>" class="resource-card" target="_blank" rel="noopener">
                <?php else: ?>
                <div class="resource-card">
                <?php endif; ?>
                    <div class="resource-icon"><?php echo $icon; ?></div>
                    <div class="resource-info">
                        <h4><?php echo htmlspecialchars($res['title']); ?></h4>
                        <?php if (!empty($res['description'])): ?>
                        <p><?php echo htmlspecialchars($res['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="resource-download">⬇</div>
                <?php if ($has_link): ?>
                </a>
                <?php else: ?>
                </div>
                <?php endif; ?>
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

<?php if (!empty($upcoming_events)): ?>
<!-- Upcoming Events -->
<section class="section" style="padding-top: 0;">
    <div class="container">
        <div class="animate-on-scroll">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
                <div>
                    <span class="badge-pill badge-gold" style="margin-bottom:8px;display:inline-block;">Stay Informed</span>
                    <h2 style="color:#003366;font-size:1.8rem;margin:0;display:flex;align-items:center;gap:10px;">
                        <i class="fas fa-calendar-alt" style="color:#ffcc00;"></i> Upcoming Events
                    </h2>
                    <p style="color:#888;margin:4px 0 0 0;">Events you won't want to miss this term.</p>
                </div>
                <a href="/academic_calendar.php" class="btn-primary" style="padding:8px 20px;text-decoration:none;font-size:0.9rem;display:inline-flex;align-items:center;gap:8px;">
                    <i class="fas fa-calendar-alt"></i> Full Calendar
                </a>
            </div>

            <div class="upcoming-events-grid">
                <?php foreach ($upcoming_events as $ev):
                    $ev_color = $ev['color'] ?? '#3498db';
                    $ev_type_label = $event_type_labels[$ev['event_type']] ?? ucfirst($ev['event_type']);
                    $ev_type_color = $event_type_colors[$ev['event_type']] ?? '#3498db';
                    $start_day = date('d', strtotime($ev['event_date']));
                    $start_month = date('M', strtotime($ev['event_date']));
                    $is_multi = !empty($ev['end_date']) && $ev['end_date'] !== $ev['event_date'];
                ?>
                <div class="upcoming-event-card" style="border-left-color: <?php echo htmlspecialchars($ev_color); ?>;">
                    <div class="ue-date-box" style="background: <?php echo $ev_type_color; ?>;">
                        <div class="ue-date-day"><?php echo $start_day; ?></div>
                        <div class="ue-date-month"><?php echo $start_month; ?></div>
                        <?php if ($is_multi): ?>
                            <div class="ue-date-to">—</div>
                            <div class="ue-date-day"><?php echo date('d', strtotime($ev['end_date'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="ue-body">
                        <span class="ue-type-badge" style="background:<?php echo $ev_type_color; ?>;"><?php echo htmlspecialchars($ev_type_label); ?></span>
                        <h4><?php echo htmlspecialchars($ev['title']); ?></h4>
                        <?php if (!empty($ev['description'])): ?>
                            <p><?php echo htmlspecialchars($ev['description']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($ev['location'])): ?>
                            <div class="ue-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ev['location']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<style>
.upcoming-events-grid {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.upcoming-event-card {
    display: flex;
    gap: 18px;
    background: #fff;
    border-radius: 12px;
    padding: 18px 22px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    border-left: 4px solid #3498db;
    transition: transform 0.15s, box-shadow 0.15s;
}
.upcoming-event-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}
.ue-date-box {
    flex-shrink: 0;
    text-align: center;
    border-radius: 10px;
    padding: 10px 16px;
    min-width: 60px;
    color: #fff;
}
.ue-date-day {
    font-size: 1.5rem;
    font-weight: 800;
    line-height: 1.1;
}
.ue-date-month {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    opacity: 0.9;
}
.ue-date-to {
    font-size: 0.75rem;
    opacity: 0.7;
    line-height: 1;
    margin: 2px 0;
}
.ue-body {
    flex: 1;
    min-width: 0;
}
.ue-body h4 {
    font-size: 1rem;
    margin: 0 0 4px 0;
    color: #003366;
}
.ue-body p {
    font-size: 0.85rem;
    color: #666;
    margin: 0 0 6px 0;
}
.ue-type-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 10px;
    font-size: 0.65rem;
    font-weight: 600;
    color: #fff;
    margin-bottom: 6px;
}
.ue-location {
    font-size: 0.8rem;
    color: #888;
    display: flex;
    align-items: center;
    gap: 5px;
}
.ue-location i {
    color: #e74c3c;
}
@media (max-width: 768px) {
    .upcoming-event-card {
        flex-direction: column;
        gap: 12px;
    }
    .ue-date-box {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        min-width: unset;
        align-self: flex-start;
    }
    .ue-date-day { font-size: 1rem; }
    .ue-date-month { font-size: 0.6rem; }
    .ue-date-to { font-size: 0.65rem; margin: 0; }
}
</style>

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
