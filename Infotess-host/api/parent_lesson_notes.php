<?php
/**
 * Parent Lesson Notes — parents view lesson notes for their children's class.
 */
require_once 'includes/db.php';

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$user_id = $_SESSION['user_id'];

// Get linked students
$stmt = $pdo->prepare("
    SELECT s.id, s.first_name, s.last_name, s.class_id, c.name AS class_name
    FROM parent_students ps
    JOIN students s ON ps.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE ps.parent_user_id = ?
    ORDER BY s.first_name
");
$stmt->execute([$user_id]);
$students = $stmt->fetchAll();

// Collect unique class IDs
$class_ids = array_filter(array_unique(array_map(function($s) { return (int)$s['class_id']; }, $students)));
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// Load lesson notes
$lesson_notes = [];
$attached_map = [];

if (!empty($class_ids)) {
    $cp = implode(',', array_map('intval', $class_ids));

    try {
        $stmt = $pdo->query("
            SELECT ln.*, s.name AS subject_name, c.name AS class_name, st.fullname AS author_name
            FROM lesson_notes ln
            LEFT JOIN subjects s ON ln.subject_id = s.id
            LEFT JOIN classes c ON ln.class_id = c.id
            LEFT JOIN staff st ON ln.created_by = st.id
            WHERE ln.is_active = 1
              AND (ln.class_id IS NULL OR ln.class_id IN ($cp))
            ORDER BY ln.sort_order ASC, ln.updated_at DESC
        ");
        $lesson_notes = $stmt->fetchAll();
    } catch (Exception $e) {}

    // Attached resources
    try {
        $stmt = $pdo->query("SELECT lnr.lesson_note_id, rl.id, rl.title, rl.source FROM lesson_note_resources lnr JOIN resource_links rl ON lnr.resource_link_id = rl.id ORDER BY lnr.sort_order");
        while ($row = $stmt->fetch()) { $attached_map[$row['lesson_note_id']][] = $row; }
    } catch (Exception $e) {}
}

// Filter by selected class if needed
if ($selected_class_id > 0) {
    $lesson_notes = array_filter($lesson_notes, function($n) use ($selected_class_id) {
        return $n['class_id'] === null || (int)$n['class_id'] === $selected_class_id;
    });
}

function sanitize($s) { return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lesson Notes — <?php echo sanitize($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .page-header { margin-bottom: 25px; }
        .page-header h1 { font-size: 1.4rem; color: var(--primary-color); }

        .child-card {
            background: #f0f7ff;
            border: 2px solid #d0e3f7;
            border-radius: 10px;
            padding: 12px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .child-card i { font-size: 1.8rem; color: var(--secondary-color); }
        .child-card .child-info { flex: 1; }
        .child-card .child-info strong { font-size: 1rem; display: block; }
        .child-card .child-info span { font-size: 0.82rem; color: #888; }

        .filter-bar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }
        .filter-bar select, .filter-bar input { padding: 8px 14px; border: 2px solid #ddd; border-radius: 6px; font-size: 0.9rem; }
        .filter-bar select:focus, .filter-bar input:focus { border-color: var(--secondary-color); outline: none; }

        .lesson-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 14px;
            padding: 18px 22px;
            transition: box-shadow 0.2s;
            cursor: pointer;
            display: block;
            text-decoration: none;
            color: inherit;
        }
        .lesson-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
        .lesson-card h3 { font-size: 1rem; margin-bottom: 5px; color: #333; }
        .lesson-card .meta { font-size: 0.82rem; color: #888; display: flex; flex-wrap: wrap; gap: 10px; }
        .badge-all { background: #e8f5e9; color: #2e7d32; padding: 2px 10px; border-radius: 10px; font-size: 0.7rem; font-weight: 600; }
        .badge-class { background: #e3f2fd; color: #0d47a1; padding: 2px 10px; border-radius: 10px; font-size: 0.7rem; font-weight: 600; }
        .res-tag { display: inline-block; padding: 1px 7px; border-radius: 8px; font-size: 0.65rem; background: #f5f5f5; color: #666; margin: 2px 2px 0 0; }
        .content-preview { font-size: 0.83rem; color: #999; margin-top: 5px; max-height: 32px; overflow: hidden; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php renderParentSidebar(); ?>
        <main class="main-content">
            <div class="container">
                <div class="page-header">
                    <h1><i class="fas fa-book-open"></i> Lesson Notes</h1>
                    <p style="color:#888;font-size:0.88rem;">Lesson notes for your child(ren)'s classes</p>
                </div>

                <?php if (count($students) === 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>No linked students</h3>
                        <p>Your account is not linked to any students yet. Please contact the school.</p>
                    </div>
                <?php else: ?>
                    <!-- Student Summary Cards -->
                    <?php foreach ($students as $student): ?>
                        <div class="child-card">
                            <i class="fas fa-user-graduate"></i>
                            <div class="child-info">
                                <strong><?php echo sanitize($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                <span>Class: <?php echo sanitize($student['class_name'] ?? 'Unassigned'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Filter -->
                    <div class="filter-bar">
                        <select id="classFilter" onchange="applyFilters()">
                            <option value="0">All Classes</option>
                            <?php
                            $seen = [];
                            foreach ($students as $s):
                                $cid = (int)$s['class_id'];
                                if (!$cid || isset($seen[$cid])) continue;
                                $seen[$cid] = true;
                            ?>
                                <option value="<?php echo $cid; ?>" <?php echo $selected_class_id === $cid ? 'selected' : ''; ?>><?php echo sanitize($s['class_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="searchInput" placeholder="Search..." onkeyup="applyFilters()">
                    </div>

                    <?php if (count($lesson_notes) === 0): ?>
                        <div class="empty-state">
                            <i class="fas fa-book"></i>
                            <h3>No lesson notes available</h3>
                            <p>Teachers haven't posted lesson notes for your child's class yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($lesson_notes as $note):
                            $class_label = $note['class_id'] ? sanitize($note['class_name']) : 'All Classes';
                            $class_badge = $note['class_id'] ? 'badge-class' : 'badge-all';
                        ?>
                        <a href="../lesson_note_view.php?id=<?php echo $note['id']; ?>" class="lesson-card" data-search="<?php echo strtolower(sanitize($note['title'] . ' ' . ($note['subject_name'] ?? ''))); ?>">
                            <h3><?php echo sanitize($note['title']); ?></h3>
                            <div class="meta">
                                <span><i class="fas fa-layer-group"></i> <?php echo sanitize($note['subject_name'] ?? 'General'); ?></span>
                                <span><i class="fas fa-users"></i> <span class="<?php echo $class_badge; ?>"><?php echo $class_label; ?></span></span>
                                <span><i class="fas fa-user"></i> <?php echo sanitize($note['author_name'] ?? 'Unknown'); ?></span>
                                <?php if (!empty($attached_map[$note['id']])): ?>
                                    <span><i class="fas fa-link"></i> <?php echo count($attached_map[$note['id']]); ?> resources</span>
                                <?php endif; ?>
                            </div>
                            <div class="content-preview"><?php echo strip_tags($note['content']); ?></div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function applyFilters() {
            var q = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.lesson-card').forEach(function(card) {
                card.style.display = q && !card.dataset.search.includes(q) ? 'none' : '';
            });
        }
    </script>
</body>
</html>
