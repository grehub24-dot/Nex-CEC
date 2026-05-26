<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn() || !isParentOrDual()) {
    redirect('../login.php');
}

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

// Fetch events
$events = [];
try {
    $result = $pdo->query("SELECT * FROM academic_calendar_events ORDER BY event_date ASC");
    if ($result) { $events = $result->fetchAll(); }
} catch (Exception $e) {}

// Fetch calendar planner file
$calendar_file = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM resources WHERE category = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute(['Academic']);
    $calendar_file = $stmt->fetch();
} catch (Exception $e) {}

// Group by month
$grouped_by_month = [];
$today = date('Y-m-d');
$total_events = count($events);
$calendar_start = $settings['academic_calendar_start'] ?? null;
$calendar_end = $settings['academic_calendar_end'] ?? null;

// Current status
$current_status = '—';
if ($calendar_start && $calendar_end) {
    if ($today < $calendar_start) {
        $current_status = 'Not Started';
    } elseif ($today > $calendar_end) {
        $current_status = 'Vacation';
    } else {
        $is_holiday = false;
        foreach ($events as $ev) {
            $ev_start = $ev['event_date'];
            $ev_end = $ev['end_date'] ?? $ev_start;
            if (($ev['event_type'] === 'holiday' || $ev['event_type'] === 'break') && $today >= $ev_start && $today <= $ev_end) {
                $is_holiday = true; break;
            }
        }
        $current_status = $is_holiday ? 'On Break' : 'In Session';
    }
}

foreach ($events as $ev) {
    $month_key = date('Y-m', strtotime($ev['event_date']));
    $month_label = date('F Y', strtotime($ev['event_date']));
    if (!isset($grouped_by_month[$month_key])) {
        $grouped_by_month[$month_key] = ['label' => $month_label, 'events' => []];
    }
    $grouped_by_month[$month_key]['events'][] = $ev;
}
ksort($grouped_by_month);

// Upcoming events (next 3)
$upcoming_events = [];
foreach ($events as $ev) {
    if ($ev['event_date'] >= $today) {
        $upcoming_events[] = $ev;
        if (count($upcoming_events) >= 3) break;
    }
}

function parentDaysUntil($date_str) {
    $now = new DateTime();
    $target = new DateTime($date_str);
    return (int)$now->diff($target)->days;
}

$type_colors = [
    'event'    => '#3498db', 'holiday'  => '#e74c3c', 'exam'     => '#e67e22',
    'training' => '#1abc9c', 'break'    => '#95a5a6',
];
$type_labels = [
    'event'    => 'Event', 'holiday'  => 'Holiday', 'exam'     => 'Exam',
    'training' => 'Training', 'break'    => 'Break',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Calendar — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .parent-main { flex: 1; padding: 30px; background: #f4f6f9; margin-left: 250px; }
        .parent-main .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 12px; }
        .parent-main .top-bar h2 { margin: 0; font-size: 22px; color: #1a5276; }
        .parent-main .top-bar .subtitle { margin: 4px 0 0; color: #888; font-size: 13px; }
        .cal-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 16px; margin-bottom: 25px; }
        .cal-summary .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 14px; }
        .cal-summary .stat-card .icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .cal-summary .stat-card .icon.info { background: #e8f0fe; color: #1a5276; }
        .cal-summary .stat-card .icon.success { background: #e6f7e6; color: #27ae60; }
        .cal-summary .stat-card .icon.warning { background: #fff3e0; color: #f39c12; }
        .cal-summary .stat-card .icon.danger { background: #fde8e8; color: #e74c3c; }
        .cal-summary .stat-card .info h3 { font-size: 20px; margin: 0; }
        .cal-summary .stat-card .info p { font-size: 12px; color: #888; margin: 2px 0 0; }
        .status-badge { display: inline-block; padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .status-badge.session { background: #e6f7e6; color: #27ae60; }
        .status-badge.break { background: #fde8e8; color: #e74c3c; }
        .status-badge.vacation { background: #f0e6ff; color: #8e44ad; }
        .status-badge.not-started { background: #fff3e0; color: #e67e22; }
        .upcoming-strip { background: white; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 20px 25px; margin-bottom: 25px; display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
        .upcoming-strip h4 { margin: 0; font-size: 14px; color: #1a5276; white-space: nowrap; }
        .upcoming-strip .up-item { display: flex; align-items: center; gap: 12px; background: #f8f9fa; padding: 10px 16px; border-radius: 8px; flex: 1; min-width: 180px; border-left: 3px solid #1a5276; }
        .upcoming-strip .up-item .up-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .upcoming-strip .up-item .up-info { flex: 1; min-width: 0; }
        .upcoming-strip .up-item .up-info .up-title { font-size: 13px; font-weight: 600; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .upcoming-strip .up-item .up-info .up-date { font-size: 11px; color: #888; }
        .upcoming-strip .up-item .up-countdown { font-size: 11px; font-weight: 700; color: #1a5276; white-space: nowrap; background: #e8f0fe; padding: 3px 10px; border-radius: 12px; }
        .month-header { background: #f0f7ff; border-left: 4px solid #1a5276; padding: 12px 16px; border-radius: 4px; margin: 20px 0 10px; font-size: 1rem; font-weight: 600; color: #1a5276; }
        .month-header:first-of-type { margin-top: 0; }
        .type-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; color: #fff; }
        .empty-state { text-align: center; padding: 50px 20px; color: #888; }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; color: #ccc; }
        .table th { background: #f8f9fa; font-size: 13px; white-space: nowrap; }
        .table td { font-size: 13px; vertical-align: middle; }
        .table td .desc { font-size: 12px; color: #888; display: block; margin-top: 2px; }
        .download-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; background: #1a5276; color: white; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; transition: background 0.2s; }
        .download-btn:hover { background: #154360; color: white; }
        @media (max-width: 768px) { .parent-main { margin-left: 0; padding: 20px 15px; } }
    </style>
</head>
<body>
    <div class="parent-container">
        <?php echo renderParentSidebar('academic_calendar', $school_name); ?>

        <main class="parent-main">
            <div class="top-bar">
                <div>
                    <h2><i class="fas fa-calendar-alt"></i> Academic Calendar</h2>
                    <p class="subtitle">View all scheduled events, holidays, and activities for the term</p>
                </div>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <?php if ($calendar_file && !empty($calendar_file['file_url'])): ?>
                        <a href="<?php echo htmlspecialchars($calendar_file['file_url']); ?>" class="download-btn" target="_blank" rel="noopener">
                            <i class="fas fa-file-download"></i> Download Calendar
                        </a>
                    <?php endif; ?>
                    <span style="font-size:13px;color:#888;"><i class="fas fa-calendar-day"></i> <?php echo date('F j, Y'); ?></span>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="cal-summary">
                <div class="stat-card">
                    <div class="icon info"><i class="fas fa-play-circle"></i></div>
                    <div class="info">
                        <h3><?php echo $calendar_start ? date('M j, Y', strtotime($calendar_start)) : '-'; ?></h3>
                        <p>Start Date</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon danger"><i class="fas fa-stop-circle"></i></div>
                    <div class="info">
                        <h3><?php echo $calendar_end ? date('M j, Y', strtotime($calendar_end)) : '-'; ?></h3>
                        <p>End Date</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon warning"><i class="fas fa-calendar-check"></i></div>
                    <div class="info">
                        <h3><?php echo $total_events; ?></h3>
                        <p>Total Events</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon success"><i class="fas fa-signal"></i></div>
                    <div class="info">
                        <?php
                        $status_class = 'not-started';
                        if ($current_status === 'In Session') $status_class = 'session';
                        elseif ($current_status === 'On Break') $status_class = 'break';
                        elseif ($current_status === 'Vacation') $status_class = 'vacation';
                        ?>
                        <span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($current_status); ?></span>
                        <p style="margin-top:4px;">Current Status</p>
                    </div>
                </div>
            </div>

            <!-- Upcoming Events -->
            <?php if (!empty($upcoming_events)): ?>
            <div class="upcoming-strip">
                <h4><i class="fas fa-clock"></i> Upcoming</h4>
                <?php foreach ($upcoming_events as $ue): ?>
                <?php $days = parentDaysUntil($ue['event_date']); ?>
                <?php $bg = $type_colors[$ue['event_type']] ?? '#3498db'; ?>
                <div class="up-item">
                    <span class="up-dot" style="background:<?php echo $bg; ?>"></span>
                    <div class="up-info">
                        <div class="up-title"><?php echo htmlspecialchars($ue['title']); ?></div>
                        <div class="up-date"><?php echo date('M j, Y', strtotime($ue['event_date'])); ?></div>
                    </div>
                    <span class="up-countdown"><?php echo $days; ?> day<?php echo $days !== 1 ? 's' : ''; ?> away</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Events Table -->
            <div style="background:white;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.06);padding:20px;">
                <h3 style="margin:0 0 15px;font-size:16px;color:#1a5276;"><i class="fas fa-list"></i> Scheduled Events</h3>
                <?php if (empty($events)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>No events scheduled yet. Please check back later.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($grouped_by_month as $month_key => $month_data): ?>
                        <div class="month-header">
                            <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($month_data['label']); ?>
                            <span style="float:right;font-weight:400;font-size:0.85rem;color:#888;"><?php echo count($month_data['events']); ?> event(s)</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width:40px;">#</th>
                                        <th style="width:160px;">Date(s)</th>
                                        <th style="width:100px;">Type</th>
                                        <th>Title</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($month_data['events'] as $i => $ev): ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($ev['event_date'])); ?>
                                            <?php if (!empty($ev['end_date'])): ?>
                                                &mdash; <?php echo date('M d, Y', strtotime($ev['end_date'])); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php $bg = $type_colors[$ev['event_type']] ?? '#3498db'; ?>
                                            <?php $label = $type_labels[$ev['event_type']] ?? ucfirst($ev['event_type']); ?>
                                            <span class="type-badge" style="background:<?php echo $bg; ?>"><?php echo $label; ?></span>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($ev['title']); ?></strong></td>
                                        <td>
                                            <?php if (!empty($ev['description'])): ?>
                                                <span class="desc"><?php echo htmlspecialchars($ev['description']); ?></span>
                                            <?php else: ?>
                                                <span class="desc" style="color:#ccc;">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
