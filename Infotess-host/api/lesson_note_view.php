<?php
/**
 * Lesson Note Viewer — shared branded page used by staff, parents, and admins.
 * GET /lesson_note_view.php?id=X
 */
require_once 'includes/db.php';

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$note_id = (int)($_GET['id'] ?? 0);
if ($note_id <= 0) {
    echo '<div class="container" style="padding:100px 0;text-align:center;"><h2>Invalid lesson note ID</h2><a href="javascript:history.back()">Go back</a></div>';
    exit;
}

// Fetch the lesson note
$note = null;
try {
    $stmt = $pdo->prepare("
        SELECT ln.*, s.name AS subject_name, c.name AS class_name, st.fullname AS author_name
        FROM lesson_notes ln
        LEFT JOIN subjects s ON ln.subject_id = s.id
        LEFT JOIN classes c ON ln.class_id = c.id
        LEFT JOIN staff st ON ln.created_by = st.id
        WHERE ln.id = ? AND ln.is_active = 1
    ");
    $stmt->execute([$note_id]);
    $note = $stmt->fetch();
} catch (Exception $e) {}

if (!$note) {
    echo '<div class="container" style="padding:100px 0;text-align:center;"><h2>Lesson note not found</h2><a href="javascript:history.back()">Go back</a></div>';
    exit;
}

// Get attached resources
$attached_resources = [];
try {
    $stmt = $pdo->prepare("
        SELECT lnr.label, lnr.sort_order, rl.id, rl.title, rl.url, rl.source, rl.embed_type
        FROM lesson_note_resources lnr
        JOIN resource_links rl ON lnr.resource_link_id = rl.id
        WHERE lnr.lesson_note_id = ?
        ORDER BY lnr.sort_order
    ");
    $stmt->execute([$note_id]);
    $attached_resources = $stmt->fetchAll();
} catch (Exception $e) {}

// Parse key concepts
$key_concepts = [];
if (!empty($note['key_concepts'])) {
    $key_concepts = array_filter(array_map('trim', explode("\n", $note['key_concepts'])));
}

function sanitize($s) { return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($note['title']); ?> — <?php echo sanitize($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .view-container { max-width: 850px; margin: 30px auto; padding: 25px; }
        .back-link { display: inline-block; margin-bottom: 18px; color: var(--secondary-color); text-decoration: none; font-size: 0.9rem; }
        .back-link i { margin-right: 5px; }
        .back-link:hover { text-decoration: underline; }
        
        .note-header { border-bottom: 3px solid var(--primary-color); padding-bottom: 15px; margin-bottom: 25px; }
        .note-header h1 { font-size: 1.6rem; color: #222; margin-bottom: 8px; line-height: 1.3; }
        .note-meta { font-size: 0.85rem; color: #888; display: flex; flex-wrap: wrap; gap: 16px; }
        .note-meta i { width: 14px; margin-right: 3px; }
        .badge-all { background: #e8f5e9; color: #2e7d32; padding: 2px 10px; border-radius: 10px; font-size: 0.7rem; font-weight: 600; display: inline-block; }
        .badge-class { background: #e3f2fd; color: #0d47a1; padding: 2px 10px; border-radius: 10px; font-size: 0.7rem; font-weight: 600; display: inline-block; }

        .concepts-box {
            background: #f0f7ff;
            border-left: 4px solid var(--secondary-color);
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        .concepts-box h3 { font-size: 0.9rem; color: var(--secondary-color); margin-bottom: 8px; }
        .concepts-box ul { margin: 0; padding-left: 20px; }
        .concepts-box li { font-size: 0.9rem; color: #333; margin-bottom: 4px; }

        .note-body {
            font-size: 1rem;
            line-height: 1.8;
            color: #333;
        }
        .note-body h1 { font-size: 1.4rem; margin-top: 25px; margin-bottom: 10px; color: #222; }
        .note-body h2 { font-size: 1.2rem; margin-top: 22px; margin-bottom: 8px; color: #333; }
        .note-body h3 { font-size: 1.05rem; margin-top: 18px; margin-bottom: 6px; color: #444; }
        .note-body p { margin-bottom: 12px; }
        .note-body ul, .note-body ol { margin-bottom: 12px; padding-left: 22px; }
        .note-body blockquote {
            border-left: 4px solid var(--primary-color);
            padding: 10px 16px;
            margin: 15px 0;
            background: #f9f9f9;
            border-radius: 4px;
            color: #555;
        }
        .note-body pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 14px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 0.85rem;
            margin: 15px 0;
        }
        .note-body code { background: #f0f0f0; padding: 2px 6px; border-radius: 4px; font-size: 0.88rem; }
        .note-body pre code { background: transparent; padding: 0; color: inherit; }
        .note-body img { max-width: 100%; border-radius: 8px; margin: 10px 0; }

        .resources-section {
            margin-top: 35px;
            padding-top: 20px;
            border-top: 2px solid #eee;
        }
        .resources-section h3 { font-size: 1rem; color: #444; margin-bottom: 12px; }
        .resource-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background: #f9f9f9;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        .resource-item i { color: var(--secondary-color); width: 18px; }
        .resource-item .res-info { flex: 1; }
        .resource-item .res-info .res-title { font-weight: 600; font-size: 0.9rem; color: #333; }
        .resource-item .res-info .res-source { font-size: 0.78rem; color: #999; }

        .no-sidebar .dashboard-container { display: block; }
        .no-sidebar .main-content { margin-left: 0; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <main class="main-content" style="margin-left:0;">
            <div class="view-container">
                <a href="javascript:history.back()" class="back-link"><i class="fas fa-arrow-left"></i> Back</a>

                <div class="note-header">
                    <h1><?php echo sanitize($note['title']); ?></h1>
                    <div class="note-meta">
                        <span><i class="fas fa-layer-group"></i> <?php echo sanitize($note['subject_name'] ?? 'General'); ?></span>
                        <span><i class="fas fa-users"></i>
                            <span class="<?php echo $note['class_id'] ? 'badge-class' : 'badge-all'; ?>">
                                <?php echo $note['class_id'] ? sanitize($note['class_name']) : 'All Classes'; ?>
                            </span>
                        </span>
                        <span><i class="fas fa-user"></i> <?php echo sanitize($note['author_name'] ?? 'Unknown'); ?></span>
                        <?php if ($note['updated_at']): ?>
                            <span><i class="fas fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($note['updated_at'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($key_concepts)): ?>
                <div class="concepts-box">
                    <h3><i class="fas fa-lightbulb"></i> Key Concepts</h3>
                    <ul>
                        <?php foreach ($key_concepts as $concept): ?>
                            <li><?php echo sanitize($concept); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="note-body ql-editor">
                    <?php echo $note['content']; ?>
                </div>

                <?php if (!empty($attached_resources)): ?>
                <div class="resources-section">
                    <h3><i class="fas fa-paperclip"></i> Attached Resources (<?php echo count($attached_resources); ?>)</h3>
                    <?php foreach ($attached_resources as $res): 
                        $embed_url = 'resource.php?id=' . $res['id'];
                        $redirect_url = 'resource_redirect.php?id=' . $res['id'];
                        $target = $res['embed_type'] === 'redirect' ? $redirect_url : $embed_url;
                    ?>
                    <a href="<?php echo $target; ?>" target="_blank" class="resource-item" style="text-decoration:none;">
                        <i class="fas fa-external-link-alt"></i>
                        <div class="res-info">
                            <div class="res-title"><?php echo sanitize($res['title']); ?></div>
                            <div class="res-source"><?php echo sanitize($res['source'] ?? ''); ?> · <?php echo $res['embed_type'] === 'redirect' ? 'Opens in new tab' : 'View'; ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div style="margin-top:30px;text-align:center;font-size:0.8rem;color:#aaa;">
                    &copy; <?php echo date('Y'); ?> <?php echo sanitize($school_name); ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
