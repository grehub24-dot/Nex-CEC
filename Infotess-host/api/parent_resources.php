<?php
/**
 * Parent Portal — Learning at Home Resources
 *
 * Shows curated educational resources (worksheets, games, videos)
 * filtered by each child's class. All external URLs are masked
 * through resource.php?id=X or resource_redirect.php?id=X.
 *
 * Access: parent (or dual-role staff/parent)
 * URL:    parent/resources.php
 */
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn() || !isParentOrDual()) {
    redirect('../login.php');
}

$parent_user_id = $_SESSION['user_id'];

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

// Fetch all children linked to this parent
$children = [];
try {
    $stmt = $pdo->prepare("SELECT student_id, relationship, is_primary FROM parent_students WHERE parent_user_id = ?");
    $stmt->execute([$parent_user_id]);
    $links = $stmt->fetchAll();
    foreach ($links as $link) {
        $stmt = $pdo->prepare("SELECT s.*, c.name AS class_name FROM students s LEFT JOIN classes c ON c.id = s.class_id WHERE s.id = ?");
        $stmt->execute([(int)$link['student_id']]);
        $student = $stmt->fetch();
        if ($student) {
            $student['relationship'] = $link['relationship'];
            $student['is_primary'] = $link['is_primary'];
            $children[] = $student;
        }
    }
} catch (Exception $e) {
    error_log("Parent resources fetch children error: " . $e->getMessage());
}

// Collect all unique class IDs from children
$child_class_ids = array_unique(array_filter(array_map(function($c) {
    return (int)($c['class_id'] ?? 0);
}, $children)));

// Fetch resources — filter by children's classes + global resources (class_id IS NULL)
// Also allow filtering by child (class_id)
$selected_class_id = (int)($_GET['class_id'] ?? 0);
$resources = [];

try {
    $query = "SELECT r.* FROM resource_links r WHERE r.is_active = 1";
    $params = [];

    if ($selected_class_id > 0 && in_array($selected_class_id, $child_class_ids)) {
        // Specific child's class
        $query .= " AND (r.class_id = ? OR r.class_id IS NULL)";
        $params[] = $selected_class_id;
    } elseif (!empty($child_class_ids)) {
        // All children's classes + global
        $placeholders = implode(',', array_fill(0, count($child_class_ids), '?'));
        $query .= " AND (r.class_id IN ($placeholders) OR r.class_id IS NULL)";
        $params = array_merge($params, $child_class_ids);
    }

    // Also filter by category if selected
    $selected_category = $_GET['category'] ?? '';
    if ($selected_category && in_array($selected_category, ['all','math','literacy','stem','ece','printables','sel','art'])) {
        if ($selected_category !== 'all') {
            $query .= " AND r.category = ?";
            $params[] = $selected_category;
        }
    }

    $query .= " ORDER BY r.sort_order, r.title";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $resources = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("parent_resources fetch error: " . $e->getMessage());
}

// Fetch unread message count
$unread_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM messages m WHERE (m.receiver_id = ? OR m.is_broadcast = 1) AND NOT EXISTS (SELECT 1 FROM message_reads mr WHERE mr.message_id = m.id AND mr.user_id = ?)");
    $stmt->execute([$parent_user_id, $parent_user_id]);
    $unread_count = (int)$stmt->fetch()['cnt'];
} catch (Exception $e) {}

$hasChildren = count($children) > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning at Home — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content { padding: 24px; }
        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 15px; margin-bottom: 24px;
        }
        .page-header h2 { margin: 0; font-size: 1.4rem; }
        .page-header h2 i { color: var(--primary-color); margin-right: 8px; }

        .child-selector {
            display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;
        }
        .child-btn {
            padding: 8px 20px; border-radius: 20px; font-size: 0.85rem;
            font-weight: 500; text-decoration: none; transition: all 0.2s;
            border: 2px solid #e0e0e0; background: #fff; color: #555;
        }
        .child-btn:hover { border-color: var(--primary-color); color: var(--primary-color); }
        .child-btn.active {
            background: var(--primary-color); color: #fff; border-color: var(--primary-color);
        }
        .child-btn i { margin-right: 6px; }

        .filter-bar {
            display: flex; gap: 12px; flex-wrap: wrap; align-items: center;
            margin-bottom: 24px; padding: 16px; background: #fff;
            border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .filter-bar label { font-weight: 600; font-size: 0.85rem; color: #555; }
        .filter-bar select {
            padding: 6px 12px; border: 1px solid #ddd; border-radius: 6px;
            font-size: 0.85rem; background: #fff;
        }
        .filter-bar .btn-filter {
            padding: 6px 16px; background: var(--primary-color); color: #fff;
            border: none; border-radius: 6px; cursor: pointer; font-size: 0.85rem;
        }
        .filter-bar .btn-filter:hover { opacity: 0.9; }

        .resource-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }
        .resource-card {
            background: #fff; border-radius: 12px; padding: 20px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            border: 1px solid #f0f0f0;
            transition: box-shadow 0.2s, transform 0.2s;
            display: flex; flex-direction: column;
        }
        .resource-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            transform: translateY(-2px);
        }
        .card-source {
            display: inline-block; padding: 2px 10px; border-radius: 10px;
            font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.5px; margin-bottom: 10px; width: fit-content;
        }
        .card-source.hand2mind { background: #e3f2fd; color: #0d47a1; }
        .card-source.pbskids { background: #fce4ec; color: #880e4f; }
        .card-source.kiddoworksheets { background: #e8f5e9; color: #1b5e20; }
        .card-source.other { background: #f5f5f5; color: #616161; }

        .card-title {
            font-size: 1rem; font-weight: 600; color: #222;
            margin-bottom: 8px; line-height: 1.4;
        }
        .card-desc {
            font-size: 0.85rem; color: #666; line-height: 1.5;
            margin-bottom: 12px; flex: 1;
        }
        .card-meta {
            display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 16px;
        }
        .card-tag {
            font-size: 0.7rem; padding: 2px 8px; border-radius: 6px;
            background: #f5f5f5; color: #666;
        }
        .card-tag i { margin-right: 3px; font-size: 0.6rem; }
        .card-actions { margin-top: auto; }
        .btn-open {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 20px; border-radius: 8px; font-size: 0.85rem;
            font-weight: 500; text-decoration: none; cursor: pointer;
            border: none; transition: all 0.2s; width: 100%;
            justify-content: center;
        }
        .btn-open.iframe {
            background: var(--primary-color); color: #fff;
        }
        .btn-open.iframe:hover { opacity: 0.9; }
        .btn-open.redirect {
            background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2;
        }
        .btn-open.redirect:hover { background: #ffe0b2; }

        .empty-state {
            text-align: center; padding: 60px 20px; color: #888;
        }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; color: #ccc; }
        .empty-state h3 { color: #555; margin-bottom: 8px; }

        .children-info {
            display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px;
            padding: 16px 20px; background: #fff; border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .child-info-card {
            display: flex; align-items: center; gap: 12px;
        }
        .child-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: #eef2ff; display: flex; align-items: center;
            justify-content: center; color: var(--primary-color); font-weight: 700;
            font-size: 1rem; flex-shrink: 0;
        }
        .child-details .name { font-weight: 600; font-size: 0.9rem; color: #333; }
        .child-details .class { font-size: 0.8rem; color: #888; }

        @media (max-width: 768px) {
            .resource-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .page-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php echo renderParentSidebar('resources', $school_name, $unread_count, '', $hasChildren); ?>

        <main class="main-content">
            <div class="page-header">
                <h2><i class="fas fa-home"></i> Learning at Home</h2>
                <span style="font-size:0.85rem; color:#888;">
                    Educational resources for your child's grade level
                </span>
            </div>

            <!-- Children info -->
            <?php if (!empty($children)): ?>
            <div class="children-info">
                <?php foreach ($children as $child):
                    $initials = strtoupper(substr($child['full_name'] ?? 'C', 0, 1));
                ?>
                <div class="child-info-card">
                    <div class="child-avatar"><?php echo htmlspecialchars($initials); ?></div>
                    <div class="child-details">
                        <div class="name"><?php echo htmlspecialchars($child['full_name'] ?? 'Unknown'); ?></div>
                        <div class="class"><?php echo htmlspecialchars($child['class_name'] ?? 'No class'); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Child class filter -->
            <div class="child-selector">
                <a href="resources.php" class="child-btn <?php echo $selected_class_id === 0 ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i> All My Children
                </a>
                <?php foreach ($children as $child):
                    $cid = (int)($child['class_id'] ?? 0);
                    if ($cid === 0) continue;
                ?>
                <a href="resources.php?class_id=<?php echo $cid; ?>"
                   class="child-btn <?php echo $selected_class_id === $cid ? 'active' : ''; ?>">
                    <i class="fas fa-child"></i> <?php echo htmlspecialchars($child['full_name'] ?? 'Child'); ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Category filter -->
            <div class="filter-bar">
                <label for="categoryFilter"><i class="fas fa-filter"></i> Type:</label>
                <select name="category" id="categoryFilter" onchange="applyFilters()">
                    <option value="all" <?php echo (!isset($_GET['category']) || $_GET['category'] === 'all') ? 'selected' : ''; ?>>All Types</option>
                    <option value="printables" <?php echo ($_GET['category'] ?? '') === 'printables' ? 'selected' : ''; ?>>📝 Worksheets & Printables</option>
                    <option value="math" <?php echo ($_GET['category'] ?? '') === 'math' ? 'selected' : ''; ?>>🔢 Math</option>
                    <option value="literacy" <?php echo ($_GET['category'] ?? '') === 'literacy' ? 'selected' : ''; ?>>📖 Literacy & Reading</option>
                    <option value="stem" <?php echo ($_GET['category'] ?? '') === 'stem' ? 'selected' : ''; ?>>🔬 Science & STEM</option>
                    <option value="ece" <?php echo ($_GET['category'] ?? '') === 'ece' ? 'selected' : ''; ?>>🧸 Early Childhood</option>
                    <option value="sel" <?php echo ($_GET['category'] ?? '') === 'sel' ? 'selected' : ''; ?>>💚 Social-Emotional</option>
                    <option value="art" <?php echo ($_GET['category'] ?? '') === 'art' ? 'selected' : ''; ?>>🎨 Art & Creative</option>
                </select>
            </div>

            <!-- Resource grid -->
            <?php if (empty($resources)): ?>
                <div class="empty-state">
                    <i class="fas fa-bookmark"></i>
                    <h3>No resources yet</h3>
                    <p>Learning resources for your child's class will appear here once added by the school.</p>
                </div>
            <?php else: ?>
                <div class="resource-grid">
                    <?php foreach ($resources as $r):
                        $src = $r['source'] ?: 'other';
                        $is_iframe = $r['embed_type'] === 'iframe';
                        $view_url = $is_iframe ? "../resource.php?id=" . (int)$r['id'] : "../resource_redirect.php?id=" . (int)$r['id'];
                    ?>
                    <div class="resource-card">
                        <span class="card-source <?php echo htmlspecialchars($src); ?>">
                            <?php echo htmlspecialchars($r['source'] ?: 'Resource'); ?>
                        </span>
                        <div class="card-title"><?php echo htmlspecialchars($r['title']); ?></div>
                        <?php if ($r['description']): ?>
                            <div class="card-desc"><?php echo htmlspecialchars($r['description']); ?></div>
                        <?php endif; ?>
                        <div class="card-meta">
                            <?php if ($r['category']): ?>
                                <span class="card-tag"><i class="fas fa-tag"></i> <?php echo htmlspecialchars(ucfirst($r['category'])); ?></span>
                            <?php endif; ?>
                            <span class="card-tag">
                                <i class="fas <?php echo $is_iframe ? 'fa-eye' : 'fa-external-link-alt'; ?>"></i>
                                <?php echo $is_iframe ? 'Opens in page' : 'Opens in new tab'; ?>
                            </span>
                        </div>
                        <div class="card-actions">
                            <a href="<?php echo $view_url; ?>" class="btn-open <?php echo $is_iframe ? 'iframe' : 'redirect'; ?>"
                               <?php echo !$is_iframe ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                <i class="fas <?php echo $is_iframe ? 'fa-eye' : 'fa-external-link-alt'; ?>"></i>
                                <?php echo $is_iframe ? 'Open Resource' : 'Launch Activity'; ?>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
    function applyFilters() {
        var category = document.getElementById('categoryFilter').value;
        var classId = <?php echo $selected_class_id ?: '0'; ?>;
        var params = [];
        if (classId > 0) params.push('class_id=' + classId);
        if (category && category !== 'all') params.push('category=' + encodeURIComponent(category));
        var url = 'resources.php';
        if (params.length > 0) url += '?' + params.join('&');
        window.location.href = url;
    }
    </script>
</body>
</html>
