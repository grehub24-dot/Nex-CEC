<?php
require_once 'includes/db.php';
requireAccess('settings');

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

// ==========================================
// Helper: Extract calendar events from DOCX/PDF
// ==========================================
/**
 * Parse a DOCX or PDF file and extract date+event pairs into the DB.
 * Supports formats like:
 *   "Monday, 2nd June, 2025 — Reporting"
 *   "3rd June — 5th August, 2025" (multi-date span)
 *
 * @param string $filePath Temporary file path
 * @param string $ext      File extension ('docx' or 'pdf')
 * @param PDO    $pdo      Database connection
 * @return int             Number of events extracted and inserted
 */
function extractCalendarEventsFromFile(string $filePath, string $ext, $pdo): int {
    $text = '';

    // Extract raw text based on file type
    if ($ext === 'docx') {
        $text = extractTextFromDocx($filePath);
    } elseif ($ext === 'pdf') {
        $text = extractTextFromPdf($filePath);
    }

    if (empty(trim($text))) {
        return 0;
    }

    // Parse events from the extracted text
    $events = parseCalendarEvents($text);
    $inserted = 0;

    foreach ($events as $ev) {
        try {
            // Skip events that already exist (same title + date)
            $check = $pdo->prepare("SELECT COUNT(*) FROM academic_calendar_events WHERE title = ? AND event_date = ?");
            $check->execute([$ev['title'], $ev['event_date']]);
            if ($check->fetchColumn() > 0) {
                continue;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO academic_calendar_events (title, event_date, end_date, event_type, color, description)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $ev['title'],
                $ev['event_date'],
                $ev['end_date'],
                $ev['event_type'],
                $ev['color'],
                $ev['description'] ?? ''
            ]);
            $inserted++;
        } catch (Exception $e) {
            error_log("Calendar event insert skipped: " . $e->getMessage());
        }
    }

    return $inserted;
}

/**
 * Extract plain text from a DOCX file using ZipArchive.
 */
function extractTextFromDocx(string $filePath): string {
    if (!class_exists('ZipArchive')) {
        error_log("ZipArchive not available — cannot parse DOCX.");
        return '';
    }
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        error_log("Failed to open DOCX as ZIP.");
        return '';
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === false) {
        error_log("word/document.xml not found in DOCX.");
        return '';
    }
    // Strip XML tags to get raw text, decode HTML entities
    $text = strip_tags($xml);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    // Normalize whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    $text = preg_replace('/[^\x20-\x7E\x0A\x0D]/', '', $text);
    return $text;
}

/**
 * Extract plain text from a PDF file.
 * Tries pdftotext (if available), falls back to basic text extraction.
 */
function extractTextFromPdf(string $filePath): string {
    // Try pdftotext command-line tool
    $pdftotext = trim(shell_exec('where pdftotext 2>nul || which pdftotext 2>/dev/null'));
    if (!empty($pdftotext)) {
        $escaped = escapeshellarg($filePath);
        $output = shell_exec("pdftotext $escaped - 2>/dev/null");
        if ($output !== null && strlen(trim($output)) > 50) {
            return $output;
        }
    }

    // Fallback: basic PDF text extraction (reads text objects)
    $content = file_get_contents($filePath);
    if ($content === false) return '';

    $text = '';
    // Match text between parentheses in PDF streams (BT...ET)
    preg_match_all('/\(([^)]*)\)/s', $content, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $match) {
            // Unescape PDF escape sequences
            $unescaped = preg_replace('/\\\\([nrtf\\\\()])/', '$1', $match);
            $text .= $unescaped . "\n";
        }
    }
    return $text;
}

/**
 * Parse structured text into calendar event entries.
 * Handles multi-date ranges and keyword-based type detection.
 */
function parseCalendarEvents(string $text): array {
    $events = [];
    $lines = explode("\n", $text);

    // Type mapping based on keywords (ordered by specificity)
    $typeRules = [
        'holiday'  => ['holiday', 'public holiday', 'bank holiday', 'eid', 'christmas', 'easter', 'independence', 'festival'],
        'break'    => ['break', 'vacation', 'mid-term', 'recess', 'half term', 'closed'],
        'exam'     => ['exam', 'examination', 'test', 'assessment', 'quiz', 'ca test', 'mock', 'bce', 'final exam', 'mid-semester'],
        'training' => ['training', 'workshop', 'meeting', 'conference', 'seminar', 'orientation', 'inset', 'development'],
        'event'    => ['reporting', 'opening', 'closing', 'sports', 'games', 'competition', 'speech', 'prize', 'celebr', 'ceremon', 'bash', 'open day', 'visit', 'excursion', 'cultural', 'debate', 'spelling', 'science fair', 'maths week', 'reading day'],
    ];

    // Date patterns (both ordinal and numeric)
    $datePatterns = [
        // "Monday, 2nd June, 2025" or "Monday, 2nd June 2025"
        '/\b(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday),\s+(\d{1,2})(?:st|nd|rd|th)?\s+(\w+),?\s+(\d{4})\b/i',
        // "2nd June, 2025" (no day name)
        '/\b(\d{1,2})(?:st|nd|rd|th)?\s+(\w+),?\s+(\d{4})\b/i',
        // "2025-06-02"
        '/\b(\d{4})-(\d{2})-(\d{2})\b/',
        // "June 2, 2025" or "June 2nd, 2025"
        '/\b(\w+)\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b/i',
    ];

    // Multi-date span within a line: "3rd June — 5th August, 2025"
    $spanPattern = '/^.*?(\d{1,2})(?:st|nd|rd|th)?\s+(\w+)\s*[' . "\x{2013}\x{2014}" . '—-]\s*(\d{1,2})(?:st|nd|rd|th)?\s+(\w+),?\s+(\d{4}).*$/u';

    $monthNames = [
        'january' => '01', 'february' => '02', 'march' => '03', 'april' => '04',
        'may' => '05', 'june' => '06', 'july' => '07', 'august' => '08',
        'september' => '09', 'october' => '10', 'november' => '11', 'december' => '12',
        'jan' => '01', 'feb' => '02', 'mar' => '03', 'apr' => '04',
        'jun' => '06', 'jul' => '07', 'aug' => '08', 'sep' => '09',
        'oct' => '10', 'nov' => '11', 'dec' => '12',
    ];

    $monthAbbr = [
        'jan' => 'January', 'feb' => 'February', 'mar' => 'March', 'apr' => 'April',
        'may' => 'May', 'jun' => 'June', 'jul' => 'July', 'aug' => 'August',
        'sep' => 'September', 'oct' => 'October', 'nov' => 'November', 'dec' => 'December',
    ];

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        // Skip header/boilerplate lines
        if (preg_match('/^(term|academic|year|page|tel|email|website|p\.o\.|box|digital address|location|vision|mission|motto|Values|Core Values|school|chariot)/i', $line)) {
            continue;
        }
        // Skip lines that are just numbers or very short
        if (strlen($line) < 8) continue;
        if (preg_match('/^\d+$/', $line)) continue;

        // Determine event type by keyword matching on the full line
        $eventType = 'event';
        foreach ($typeRules as $type => $keywords) {
            foreach ($keywords as $kw) {
                if (stripos($line, $kw) !== false) {
                    $eventType = $type;
                    break 2;
                }
            }
        }

        // Color mapping
        $colorMap = [
            'holiday'  => '#e74c3c',
            'break'    => '#95a5a6',
            'exam'     => '#e67e22',
            'training' => '#1abc9c',
            'event'    => '#3498db',
        ];
        $color = $colorMap[$eventType] ?? '#3498db';

        // --- Try to match multi-date span first ---
        // Match: "3rd June — 5th August, 2025"
        $stdLine = str_replace(['–', '—', '−'], '-', $line);
        $stdLine = preg_replace('/\s+-\s+/', ' — ', $stdLine);

        if (preg_match('/^.*?(\d{1,2})(?:st|nd|rd|th)?\s+(\w+)\s*[—–-]\s*(\d{1,2})(?:st|nd|rd|th)?\s+(\w+),?\s+(\d{4}).*$/ui', $stdLine, $m)) {
            $startDay   = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $startMonth = strtolower($m[2]);
            $endDay     = str_pad($m[3], 2, '0', STR_PAD_LEFT);
            $endMonth   = strtolower($m[4]);
            $year       = $m[5];

            if (isset($monthNames[$startMonth]) && isset($monthNames[$endMonth])) {
                $startDate = "$year-{$monthNames[$startMonth]}-$startDay";
                $endDate   = "$year-{$monthNames[$endMonth]}-$endDay";

                // Extract title: text before the date pattern
                $title = preg_replace('/\s*[—–-].*$/', '', $line);
                $title = trim(preg_replace('/\b(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\b/i', '', $title));
                $title = trim(preg_replace('/^\d{1,2}(?:st|nd|rd|th)?\s+\w+/i', '', $title));
                $title = trim($title);
                if (empty($title)) {
                    $title = substr($line, 0, strpos($line, '—') ?: 40);
                    $title = trim($title);
                }
                if (empty($title)) continue;

                $events[] = [
                    'title'       => $title,
                    'event_date'  => $startDate,
                    'end_date'    => $endDate,
                    'event_type'  => $eventType,
                    'color'       => $color,
                    'description' => '',
                ];
                continue;
            }
        }

        // --- Single-date matches ---
        $matched = false;

        // Pattern: "Monday, 2nd June, 2025 — Event Title"
        if (preg_match('/\b(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday),\s+(\d{1,2})(?:st|nd|rd|th)?\s+(\w+),?\s+(\d{4})\b/i', $line, $m)) {
            $day   = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $month = strtolower($m[2]);
            $year  = $m[3];

            if (isset($monthNames[$month])) {
                $dateStr = "$year-{$monthNames[$month]}-$day";
                // Extract title after the date
                $title = preg_replace('/\b(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday),\s+\d{1,2}(?:st|nd|rd|th)?\s+\w+,?\s+\d{4}\s*[—–-]?\s*/i', '', $line);
                $title = trim($title);
                if (empty($title)) continue;

                $events[] = [
                    'title'       => $title,
                    'event_date'  => $dateStr,
                    'end_date'    => null,
                    'event_type'  => $eventType,
                    'color'       => $color,
                    'description' => '',
                ];
                $matched = true;
            }
        }

        if ($matched) continue;

        // Pattern: "2nd June, 2025 — Event Title" (no day name)
        if (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s+(\w+),?\s+(\d{4})\b/i', $line, $m)) {
            $day   = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $month = strtolower($m[2]);
            $year  = $m[3];

            if (isset($monthNames[$month])) {
                $dateStr = "$year-{$monthNames[$month]}-$day";
                $title = preg_replace('/\b\d{1,2}(?:st|nd|rd|th)?\s+\w+,?\s+\d{4}\s*[—–-]?\s*/i', '', $line);
                $title = trim($title);
                if (empty($title)) continue;

                $events[] = [
                    'title'       => $title,
                    'event_date'  => $dateStr,
                    'end_date'    => null,
                    'event_type'  => $eventType,
                    'color'       => $color,
                    'description' => '',
                ];
                $matched = true;
            }
        }

        if ($matched) continue;

        // Pattern: "June 2, 2025 — Event Title" (month name first)
        if (preg_match('/\b(\w+)\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b/i', $line, $m)) {
            $month = strtolower($m[1]);
            $day   = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $year  = $m[3];

            if (isset($monthNames[$month])) {
                $dateStr = "$year-{$monthNames[$month]}-$day";
                $title = preg_replace('/\b\w+\s+\d{1,2}(?:st|nd|rd|th)?,?\s+\d{4}\s*[—–-]?\s*/i', '', $line);
                $title = trim($title);
                if (empty($title)) continue;

                $events[] = [
                    'title'       => $title,
                    'event_date'  => $dateStr,
                    'end_date'    => null,
                    'event_type'  => $eventType,
                    'color'       => $color,
                    'description' => '',
                ];
            }
        }
    }

    return $events;
}

// ==========================================
// Handle POST Actions
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_request_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_event') {
            $title = sanitize($_POST['title']);
            $event_date = sanitize($_POST['event_date']);
            $end_date = !empty($_POST['end_date']) ? sanitize($_POST['end_date']) : null;
            $event_type = sanitize($_POST['event_type']);
            $color = !empty($_POST['color']) ? sanitize($_POST['color']) : '#3498db';
            $description = sanitize($_POST['description'] ?? '');

            if (empty($title) || empty($event_date)) {
                $error = 'Title and event date are required.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO academic_calendar_events (title, event_date, end_date, event_type, color, description) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $event_date, $end_date, $event_type, $color, $description]);
                $message = "Event '$title' added successfully.";
            }

        } elseif ($action === 'edit_event') {
            $id = (int)$_POST['id'];
            $title = sanitize($_POST['title']);
            $event_date = sanitize($_POST['event_date']);
            $end_date = !empty($_POST['end_date']) ? sanitize($_POST['end_date']) : null;
            $event_type = sanitize($_POST['event_type']);
            $color = sanitize($_POST['color']);
            $description = sanitize($_POST['description'] ?? '');

            if (empty($title) || empty($event_date)) {
                $error = 'Title and event date are required.';
            } else {
                $stmt = $pdo->prepare("UPDATE academic_calendar_events SET title = ?, event_date = ?, end_date = ?, event_type = ?, color = ?, description = ? WHERE id = ?");
                $stmt->execute([$title, $event_date, $end_date, $event_type, $color, $description, $id]);
                $message = "Event updated successfully.";
            }

        } elseif ($action === 'delete_event') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM academic_calendar_events WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Event deleted successfully.";

        } elseif ($action === 'upload_calendar_file') {
            if (isset($_FILES['calendar_file']) && $_FILES['calendar_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['calendar_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['docx', 'pdf'];
                if (!in_array($ext, $allowed_ext)) {
                    $error = 'Only DOCX and PDF files are accepted.';
                } else {
                    $filename = 'academic_calendar_' . time() . '.' . $ext;
                    $tmpPath = $file['tmp_name'];
                    $fileData = file_get_contents($tmpPath);
                    $contentType = $ext === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

                    global $supabase;

                    // Step 1: Ensure the 'resources' bucket exists (idempotent)
                    try {
                        $supabase->createBucket('resources', true, null, 20 * 1024 * 1024);
                    } catch (Exception $bucketErr) {
                        // Bucket creation failed — still try upload in case it exists
                        error_log("Bucket creation warning: " . $bucketErr->getMessage());
                    }

                    // Step 2: Upload the file to Supabase Storage
                    try {
                        $supabase->uploadFile('resources', $filename, $fileData, $contentType);
                        $fileUrl = $supabase->getPublicUrl('resources', $filename);

                        // Step 3: Store file reference in resources table
                        $stmt = $pdo->prepare("INSERT INTO resources (title, description, file_url, category) VALUES (?, ?, ?, ?)");
                        $stmt->execute(['Academic Calendar - 3rd Term 2025/2026', 'Download the full termly planner with all events', $fileUrl, 'Academic']);
                        $message = 'Calendar file uploaded successfully.';

                        // Step 4: Auto-extract events from the file
                        $importCount = extractCalendarEventsFromFile($tmpPath, $ext, $pdo);
                        if ($importCount > 0) {
                            $message .= " $importCount events were extracted and saved.";
                        }
                    } catch (Exception $e) {
                        $error = 'Upload failed: ' . $e->getMessage();
                        error_log("Calendar file upload error: " . $e->getMessage());
                    }
                }
            } else {
                $error = 'No file was uploaded or an error occurred.';
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        error_log("Academic Calendar POST error: " . $e->getMessage());
    }
}

// ==========================================
// Fetch events
// ==========================================
$events = [];
try {
    $stmt = $pdo->query("SELECT * FROM academic_calendar_events ORDER BY event_date ASC");
    $events = $stmt->fetchAll();
} catch (Exception $e) {
    $events = [];
}

// Group events by month
$grouped_by_month = [];
foreach ($events as $ev) {
    $month_key = date('Y-m', strtotime($ev['event_date']));
    $month_label = date('F Y', strtotime($ev['event_date']));
    if (!isset($grouped_by_month[$month_key])) {
        $grouped_by_month[$month_key] = ['label' => $month_label, 'events' => []];
    }
    $grouped_by_month[$month_key]['events'][] = $ev;
}
ksort($grouped_by_month);

// Quick stats
$total_events = count($events);
$calendar_start = $settings['academic_calendar_start'] ?? '—';
$calendar_end = $settings['academic_calendar_end'] ?? '—';

// Color badges by type
$type_colors = [
    'event'    => '#3498db',
    'holiday'  => '#e74c3c',
    'exam'     => '#e67e22',
    'training' => '#1abc9c',
    'break'    => '#95a5a6',
];
$type_labels = [
    'event'    => 'Event',
    'holiday'  => 'Holiday',
    'exam'     => 'Exam',
    'training' => 'Training',
    'break'    => 'Break',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Calendar Management — <?php echo htmlspecialchars($school_name); ?> Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            text-align: center;
        }
        .stat-card .stat-icon {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin-bottom: 8px;
        }
        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        .stat-card .stat-label {
            font-size: 0.82rem;
            color: #888;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
        }
        .form-box {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 20px;
        }
        .form-box h3 {
            margin-bottom: 15px;
            font-size: 1rem;
            color: var(--primary-color);
        }
        .form-box .form-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .form-box .form-row .form-control {
            flex: 1;
            min-width: 140px;
        }
        .inline-form { display: inline; }
        .type-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #fff;
        }
        .color-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            vertical-align: middle;
            margin-right: 4px;
            border: 1px solid rgba(0,0,0,0.1);
        }
        .month-header {
            background: #f0f7ff;
            border-left: 4px solid var(--primary-color);
            padding: 12px 16px;
            border-radius: 4px;
            margin: 20px 0 10px;
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        .month-header:first-of-type {
            margin-top: 0;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #888;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ccc;
        }
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            transition: border-color 0.2s;
        }
        .upload-area:hover {
            border-color: var(--primary-color);
        }
        .upload-area i {
            font-size: 2rem;
            color: #bbb;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php echo renderSidebar('academic_calendar', $school_name); ?>

        <main class="main-content" id="main-content">
            <div class="top-bar">
                <h2><i class="fas fa-calendar-alt"></i> Academic Calendar Management</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                    <div class="stat-value"><?php echo $total_events; ?></div>
                    <div class="stat-label">Events This Term</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-play-circle"></i></div>
                    <div class="stat-value"><?php echo htmlspecialchars($calendar_start); ?></div>
                    <div class="stat-label">Calendar Start</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-stop-circle"></i></div>
                    <div class="stat-value"><?php echo htmlspecialchars($calendar_end); ?></div>
                    <div class="stat-label">Calendar End</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
                    <div class="stat-value"><?php echo $settings['calendar_term_label'] ?? '3rd Term'; ?></div>
                    <div class="stat-label">Current Term</div>
                </div>
            </div>

            <!-- Forms: Add Event + Upload File -->
            <div class="form-grid">
                <!-- Add Event Form -->
                <div class="form-box">
                    <h3><i class="fas fa-plus-circle"></i> Add New Event</h3>
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_event">
                        <div class="form-row">
                            <div>
                                <label class="fs-small fw-600 color-muted mb-5">Title *</label>
                                <input type="text" name="title" class="form-control" placeholder="e.g. Spelling Bee" required>
                            </div>
                            <div>
                                <label class="fs-small fw-600 color-muted mb-5">Date *</label>
                                <input type="date" name="event_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label class="fs-small fw-600 color-muted mb-5">End Date</label>
                                <input type="date" name="end_date" class="form-control">
                            </div>
                            <div>
                                <label class="fs-small fw-600 color-muted mb-5">Type</label>
                                <select name="event_type" class="form-control">
                                    <option value="event">Event</option>
                                    <option value="holiday">Holiday</option>
                                    <option value="exam">Exam</option>
                                    <option value="training">Training</option>
                                    <option value="break">Break</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label class="fs-small fw-600 color-muted mb-5">Color</label>
                                <input type="color" name="color" class="form-control" value="#3498db" style="height: 40px; padding: 3px;">
                            </div>
                            <div style="flex: 2;">
                                <label class="fs-small fw-600 color-muted mb-5">Description</label>
                                <input type="text" name="description" class="form-control" placeholder="Optional description">
                            </div>
                        </div>
                        <button type="submit" class="btn-primary" style="margin-top: 8px;"><i class="fas fa-plus"></i> Add Event</button>
                    </form>
                </div>

                <!-- Upload Calendar File -->
                <div class="form-box">
                    <h3><i class="fas fa-upload"></i> Upload Calendar Planner</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="upload_calendar_file">
                        <div class="upload-area">
                            <i class="fas fa-file-upload"></i>
                            <p style="margin-bottom: 10px; color: #888;">Upload the termly planner (DOCX or PDF)</p>
                            <input type="file" name="calendar_file" accept=".docx,.pdf" required style="margin: 0 auto; display: block;">
                            <p class="fs-small color-muted mt-10">The file will be stored in Supabase Storage and linked from the Resources section.</p>
                        </div>
                        <button type="submit" class="btn-primary" style="margin-top: 12px;"><i class="fas fa-upload"></i> Upload File</button>
                    </form>
                </div>
            </div>

            <!-- Events Table -->
            <div class="card">
                <div class="card-header" style="padding: 20px;">
                    <h3><i class="fas fa-list"></i> Scheduled Events</h3>
                </div>
                <div class="card-content">
                    <?php if (empty($events)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>No events scheduled yet.</p>
                            <p class="fs-small color-muted mt-5">Use the form above to add events to the academic calendar.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($grouped_by_month as $month_key => $month_data): ?>
                            <div class="month-header">
                                <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($month_data['label']); ?>
                                <span style="float: right; font-weight: 400; font-size: 0.85rem; color: #888;"><?php echo count($month_data['events']); ?> event(s)</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;">#</th>
                                            <th>Title</th>
                                            <th style="width: 180px;">Date Range</th>
                                            <th style="width: 100px;">Type</th>
                                            <th style="width: 70px;">Color</th>
                                            <th style="width: 140px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($month_data['events'] as $i => $ev): ?>
                                        <tr>
                                            <td><?php echo $i + 1; ?></td>
                                            <td><strong><?php echo htmlspecialchars($ev['title']); ?></strong>
                                                <?php if (!empty($ev['description'])): ?>
                                                    <br><span class="fs-small color-muted"><?php echo htmlspecialchars($ev['description']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($ev['event_date'])); ?>
                                                <?php if (!empty($ev['end_date'])): ?>
                                                    &mdash; <?php echo date('M d, Y', strtotime($ev['end_date'])); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php $bg = $type_colors[$ev['event_type']] ?? '#3498db'; ?>
                                                <?php $label = $type_labels[$ev['event_type']] ?? ucfirst($ev['event_type']); ?>
                                                <span class="type-badge" style="background: <?php echo $bg; ?>"><?php echo $label; ?></span>
                                            </td>
                                            <td>
                                                <span class="color-dot" style="background: <?php echo htmlspecialchars($ev['color'] ?? '#3498db'); ?>"></span>
                                            </td>
                                            <td>
                                                <button onclick="toggleEdit(<?php echo $ev['id']; ?>)" class="btn-admin-action btn-admin-secondary btn-admin-sm"><i class="fas fa-pen"></i></button>
                                                <form method="POST" class="inline-form" onsubmit="return confirm('Delete event &quot;<?php echo htmlspecialchars($ev['title'], ENT_QUOTES); ?>&quot;?');">
                                                    <?php csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete_event">
                                                    <input type="hidden" name="id" value="<?php echo $ev['id']; ?>">
                                                    <button type="submit" class="btn-admin-action btn-admin-danger btn-admin-sm"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        <tr id="edit-row-<?php echo $ev['id']; ?>" style="display: none;">
                                            <td colspan="6" style="background: #f8f9fa; padding: 10px 15px;">
                                                <form method="POST" class="flex items-center gap-10" style="flex-wrap: wrap;">
                                                    <?php csrf_field(); ?>
                                                    <input type="hidden" name="action" value="edit_event">
                                                    <input type="hidden" name="id" value="<?php echo $ev['id']; ?>">
                                                    <div>
                                                        <label class="fs-small fw-600 color-muted">Title</label>
                                                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($ev['title']); ?>" required style="min-width: 160px;">
                                                    </div>
                                                    <div>
                                                        <label class="fs-small fw-600 color-muted">Date</label>
                                                        <input type="date" name="event_date" class="form-control" value="<?php echo $ev['event_date']; ?>" required>
                                                    </div>
                                                    <div>
                                                        <label class="fs-small fw-600 color-muted">End Date</label>
                                                        <input type="date" name="end_date" class="form-control" value="<?php echo $ev['end_date'] ?? ''; ?>">
                                                    </div>
                                                    <div>
                                                        <label class="fs-small fw-600 color-muted">Type</label>
                                                        <select name="event_type" class="form-control">
                                                            <?php foreach ($type_labels as $tk => $tl): ?>
                                                                <option value="<?php echo $tk; ?>" <?php echo $ev['event_type'] === $tk ? 'selected' : ''; ?>><?php echo $tl; ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="fs-small fw-600 color-muted">Color</label>
                                                        <input type="color" name="color" class="form-control" value="<?php echo htmlspecialchars($ev['color'] ?? '#3498db'); ?>" style="height: 36px; padding: 2px;">
                                                    </div>
                                                    <div>
                                                        <label class="fs-small fw-600 color-muted">Description</label>
                                                        <input type="text" name="description" class="form-control" value="<?php echo htmlspecialchars($ev['description'] ?? ''); ?>" style="min-width: 140px;">
                                                    </div>
                                                    <button type="submit" class="btn-admin-action btn-admin-sm" style="margin-top: 18px;"><i class="fas fa-save"></i></button>
                                                    <button type="button" onclick="toggleEdit(<?php echo $ev['id']; ?>)" class="btn-admin-action btn-admin-secondary btn-admin-sm" style="margin-top: 18px;"><i class="fas fa-times"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
    function toggleEdit(id) {
        var row = document.getElementById('edit-row-' + id);
        if (row) {
            row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
        }
    }
    </script>
</body>
</html>
