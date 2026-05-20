<?php
require_once 'includes/db.php';

// Enforce access control
requireAccess('module_settings');

// Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_request_csrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_executive') {
        $name = sanitize($_POST['full_name']);
        $pos = sanitize($_POST['position']);
        $image_url = upload_to_supabase_storage($_FILES['image'] ?? [], 'executives', '', 'images/aamusted.jpg');

        $stmt = $pdo->prepare("INSERT INTO executives (full_name, position, image_url) VALUES (?, ?, ?)");
        $stmt->execute([$name, $pos, $image_url]);
        $message = "Executive added successfully!";
    } elseif ($action === 'add_alumni') {
        $name = sanitize($_POST['full_name']);
        $year = sanitize($_POST['graduation_year']);
        $image_url = upload_to_supabase_storage($_FILES['image'] ?? [], 'alumni', '', 'images/aamusted.jpg');

        $stmt = $pdo->prepare("INSERT INTO alumni (full_name, graduation_year, image_url) VALUES (?, ?, ?)");
        $stmt->execute([$name, $year, $image_url]);
        $message = "Alumni added successfully!";
    } elseif ($action === 'add_gallery') {
        $title = sanitize($_POST['title']);
        $image_url = upload_to_supabase_storage($_FILES['image'] ?? [], 'gallery', '', 'images/gallery-placeholder.png');

        $stmt = $pdo->prepare("INSERT INTO gallery (title, image_url) VALUES (?, ?)");
        $stmt->execute([$title, $image_url]);
        $message = "Gallery item added successfully!";
    } elseif ($action === 'add_project') {
        $title = sanitize($_POST['title']);
        $desc = sanitize($_POST['description']);
        $status = sanitize($_POST['status']);
        $date = sanitize($_POST['project_date']);
        $image_url = upload_to_supabase_storage($_FILES['image'] ?? [], 'projects', '', 'images/project-placeholder.png');

        $stmt = $pdo->prepare("INSERT INTO projects (title, description, status, project_date, image_url) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $desc, $status, $date, $image_url]);
        $message = "Project added successfully!";
    } elseif ($action === 'respond_contact') {
        $sub_id = intval($_POST['submission_id']);
        $response = sanitize($_POST['response']);
        // Bridge doesn't support NOW() in SET — use PHP timestamp
        $stmt = $pdo->prepare("UPDATE contact_submissions SET response = ?, responded_at = ? WHERE id = ?");
        $stmt->execute([$response, date('Y-m-d H:i:s'), $sub_id]);
        $redirectPage = isset($_POST['sub_page']) ? '?sub_page=' . (int)$_POST['sub_page'] : '';
        header("Location: module_settings.php{$redirectPage}");
        exit;
    } elseif ($action === 'update_contact_submission') {
        $id = intval($_POST['id']);
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $subject = sanitize($_POST['subject']);
        $msg = sanitize($_POST['message']);
        $response = sanitize($_POST['response'] ?? '');
        $stmt = $pdo->prepare("UPDATE contact_submissions SET name = ?, email = ?, subject = ?, message = ?, response = ? WHERE id = ?");
        $stmt->execute([$name, $email, $subject, $msg, $response, $id]);
        $redirectPage = isset($_POST['sub_page']) ? '?sub_page=' . (int)$_POST['sub_page'] : '';
        header("Location: module_settings.php{$redirectPage}");
        exit;
    } elseif ($action === 'delete_executive') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM executives WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Executive deleted successfully!";
    } elseif ($action === 'update_executive') {
        $id = intval($_POST['id']);
        $name = sanitize($_POST['full_name']);
        $pos = sanitize($_POST['position']);
        $image_url = upload_to_supabase_storage($_FILES['image'] ?? [], 'executives', '', sanitize($_POST['current_image_url'] ?? 'images/aamusted.jpg'));

        $stmt = $pdo->prepare("UPDATE executives SET full_name = ?, position = ?, image_url = ? WHERE id = ?");
        $stmt->execute([$name, $pos, $image_url, $id]);
        $message = "Executive updated successfully!";
    } elseif ($action === 'delete_alumni') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM alumni WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Alumni deleted successfully!";
    } elseif ($action === 'update_alumni') {
        $id = intval($_POST['id']);
        $name = sanitize($_POST['full_name']);
        $year = sanitize($_POST['graduation_year']);
        $image_url = upload_to_supabase_storage($_FILES['image'] ?? [], 'alumni', '', sanitize($_POST['current_image_url'] ?? 'images/aamusted.jpg'));

        $stmt = $pdo->prepare("UPDATE alumni SET full_name = ?, graduation_year = ?, image_url = ? WHERE id = ?");
        $stmt->execute([$name, $year, $image_url, $id]);
        $message = "Alumni updated successfully!";
    } elseif ($action === 'delete_gallery') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM gallery WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Gallery item deleted successfully!";
    } elseif ($action === 'update_gallery') {
        $id = intval($_POST['id']);
        $title = sanitize($_POST['title']);
        $image_url = upload_to_supabase_storage($_FILES['image'] ?? [], 'gallery', '', sanitize($_POST['current_image_url'] ?? 'images/gallery-placeholder.png'));

        $stmt = $pdo->prepare("UPDATE gallery SET title = ?, image_url = ? WHERE id = ?");
        $stmt->execute([$title, $image_url, $id]);
        $message = "Gallery item updated successfully!";
    } elseif ($action === 'delete_project') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Project deleted successfully!";
    } elseif ($action === 'update_project') {
        $id = intval($_POST['id']);
        $title = sanitize($_POST['title']);
        $desc = sanitize($_POST['description']);
        $status = sanitize($_POST['status']);
        $date = sanitize($_POST['project_date']);
        $image_url = upload_to_supabase_storage($_FILES['image'] ?? [], 'projects', '', sanitize($_POST['current_image_url'] ?? 'images/project-placeholder.png'));

        $stmt = $pdo->prepare("UPDATE projects SET title = ?, description = ?, status = ?, project_date = ?, image_url = ? WHERE id = ?");
        $stmt->execute([$title, $desc, $status, $date, $image_url, $id]);
        $message = "Project updated successfully!";
    } elseif ($action === 'setup_tables') {
        $migrationFile = __DIR__ . '/migrate_module_tables.sql';
        if (!file_exists($migrationFile)) {
            $error = "Migration SQL file not found. Upload migrate_module_tables.sql first.";
        } else {
            $sql = file_get_contents($migrationFile);
            if (empty($sql)) {
                $error = "Migration SQL file is empty.";
            } else {
                global $supabase;
                try {
                    $supabase->executeSql($sql);
                    $message = "All module tables created successfully! Redirecting...";
                    echo '<meta http-equiv="refresh" content="1">';
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    // If PAT is not set, provide manual SQL instructions inline
                    if (strpos($errorMsg, 'Cannot execute SQL') !== false) {
                        $error = 'To create the tables automatically, set SUPABASE_PAT in your .env file. Otherwise, copy the SQL below and run it in your Supabase Dashboard SQL Editor.';
                        $showManualSql = true;
                    } else {
                        $error = "Database error: " . htmlspecialchars($errorMsg);
                    }
                }
            }
        }
    }
}

// Detect which module tables exist
$required_tables = ['executives', 'alumni', 'gallery', 'projects', 'contact_submissions'];
$table_status = [];
$all_tables_exist = true;
foreach ($required_tables as $tbl) {
    try {
        $pdo->query("SELECT 1 FROM $tbl LIMIT 1");
        $table_status[$tbl] = true;
    } catch (Exception $e) {
        $table_status[$tbl] = false;
        $all_tables_exist = false;
    }
}

// Fetch Data for Tables (wrapped for missing tables)
$executives = []; try { $executives = $pdo->query("SELECT * FROM executives")->fetchAll(); } catch (Exception $e) { $executives = []; }
$alumni = []; try { $alumni = $pdo->query("SELECT * FROM alumni")->fetchAll(); } catch (Exception $e) { $alumni = []; }
$gallery = []; try { $gallery = $pdo->query("SELECT * FROM gallery ORDER BY created_at DESC")->fetchAll(); } catch (Exception $e) { $gallery = []; }
$projects = []; try { $projects = $pdo->query("SELECT * FROM projects ORDER BY project_date DESC")->fetchAll(); } catch (Exception $e) { $projects = []; }
$submissions = []; try { $submissions = $pdo->query("SELECT * FROM contact_submissions ORDER BY created_at DESC")->fetchAll(); } catch (Exception $e) { $submissions = []; }

// Pagination for contact submissions
$sub_limit = 15;
$sub_page = isset($_GET['sub_page']) ? max(1, (int)$_GET['sub_page']) : 1;
$sub_offset = ($sub_page - 1) * $sub_limit;
$sub_total = count($submissions);
$sub_total_pages = max(1, ceil($sub_total / $sub_limit));
$submissions_paginated = array_slice($submissions, $sub_offset, $sub_limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Module Settings - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== Setup Banner ===== */
        .setup-banner {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 2px solid #ffcc00;
            border-radius: 16px;
            padding: 28px 32px;
            margin-bottom: 28px;
            text-align: center;
        }
        .setup-banner h3 {
            color: #003366;
            font-size: 1.3rem;
            margin-bottom: 8px;
        }
        .setup-banner h3 i {
            color: #ffcc00;
            background: #003366;
            padding: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .setup-banner p {
            color: #5a4a00;
            font-size: 0.92rem;
            margin-bottom: 16px;
        }
        .table-status-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-bottom: 18px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 600;
        }
        .status-ok { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-missing { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .btn-setup {
            background: #003366;
            color: #ffcc00;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-setup:hover { background: #002244; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,51,102,0.3); }
        .setup-note {
            margin-top: 12px;
            font-size: 0.8rem;
            color: #856404 !important;
        }
        .setup-note code {
            background: #fff3cd;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.78rem;
        }
        .manual-sql-box {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 16px;
            border-radius: 8px;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 0.75rem;
            line-height: 1.5;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
            text-align: left;
            margin-top: 12px;
            border: 1px solid #333;
        }

        /* ===== Modals ===== */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fff;
            margin: 8% auto;
            padding: 28px;
            border-radius: 16px;
            width: 440px;
            max-width: 92vw;
            position: relative;
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
            border-top: 4px solid #ffcc00;
        }
        .modal-content h3 {
            color: #003366;
            font-size: 1.2rem;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
        }
        .close-btn {
            position: absolute;
            right: 18px;
            top: 14px;
            font-size: 26px;
            cursor: pointer;
            color: #999;
            transition: color 0.2s;
            line-height: 1;
        }
        .close-btn:hover { color: #003366; }
        .upload-preview {
            width: 110px;
            height: 110px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #d4dbe3;
            margin: 0 auto 10px auto;
            display: block;
            background: #f3f6f9;
        }
        .upload-file-name {
            margin-top: 8px;
            font-size: 0.82rem;
            text-align: center;
            color: #4b5563;
        }

        /* ===== Table Enhancements ===== */
        .empty-table-msg {
            text-align: center;
            padding: 28px 16px;
            color: #888;
            font-size: 0.9rem;
        }
        .empty-table-msg i {
            font-size: 2rem;
            color: #ccc;
            display: block;
            margin-bottom: 10px;
        }
        .card h3 {
            color: #003366;
            font-size: 1.1rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card h3 i {
            color: #ffcc00;
            background: #003366;
            padding: 6px;
            border-radius: 8px;
            font-size: 0.85rem;
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* ===== Top Bar Enhancements ===== */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }
        .top-bar h2 {
            color: #003366;
            font-size: 1.4rem;
            margin: 0;
        }
        .btn-admin-action {
            background: #003366;
            color: #fff;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-admin-action:hover { background: #002244; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,51,102,0.25); }
        .btn-admin-secondary { background: #6c757d; }
        .btn-admin-secondary:hover { background: #5a6268; }
        .btn-admin-sm { padding: 6px 12px; font-size: 0.78rem; }
        .btn-gold-action {
            background: #ffcc00;
            color: #003366;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-gold-action:hover { background: #e6b800; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(255,204,0,0.3); }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
            <?php echo renderSidebar('module_settings', $school_name); ?>

        <main class="main-content">
            <div class="top-bar">
                <h2><i class="fas fa-cog" style="color:#ffcc00;background:#003366;padding:8px;border-radius:10px;margin-right:8px;"></i> Module & Contact Settings</h2>
                <div style="display:flex; gap:8px; flex-wrap: wrap;">
                    <button onclick="document.getElementById('execModal').style.display='block'" class="btn-admin-action"><i class="fas fa-user-tie"></i> Add Executive</button>
                    <button onclick="document.getElementById('alumniModal').style.display='block'" class="btn-admin-action"><i class="fas fa-graduation-cap"></i> Add Alumni</button>
                    <button onclick="document.getElementById('galleryModal').style.display='block'" class="btn-admin-action"><i class="fas fa-images"></i> Add Gallery</button>
                    <button onclick="document.getElementById('projectModal').style.display='block'" class="btn-admin-action"><i class="fas fa-project-diagram"></i> Add Project</button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (isset($showManualSql) && $showManualSql): ?>
                <div class="manual-sql-box"><?php echo htmlspecialchars(file_get_contents(__DIR__ . '/migrate_module_tables.sql')); ?></div>
            <?php endif; ?>

            <?php if (!$all_tables_exist): ?>
            <div class="setup-banner">
                <h3><i class="fas fa-database"></i> Database Setup Required</h3>
                <p>Some module tables are missing. These are needed to store executives, alumni, gallery items, projects, and contact form submissions.</p>
                <div class="table-status-list">
                    <?php foreach ($table_status as $tbl => $exists): ?>
                    <span class="status-badge <?php echo $exists ? 'status-ok' : 'status-missing'; ?>">
                        <?php echo $exists ? '&#10003;' : '&#10007;'; ?> <?php echo ucfirst(str_replace('_', ' ', $tbl)); ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <form method="POST" style="display:inline;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="setup_tables">
                    <button type="submit" class="btn-setup"><i class="fas fa-play"></i> Create Tables Now</button>
                </form>
                <p class="setup-note">If the button fails, set <code>SUPABASE_PAT</code> in your <code>.env</code> file, or copy the SQL from <code>migrate_module_tables.sql</code> and run it in your Supabase SQL Editor.</p>
            </div>
            <?php endif; ?>

            <!-- Contact Submissions -->
            <div class="section">
                <div class="card">
                    <h3>Contact Us Submissions</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Response</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($submissions_paginated) > 0): ?>
                                <?php foreach ($submissions_paginated as $sub): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sub['name']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['email']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['subject']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['message']); ?></td>
                                        <td><?php echo $sub['response'] ? htmlspecialchars($sub['response']) : '<em style="color:#999;">Pending</em>'; ?></td>
                                        <td>
                                            <button onclick="document.getElementById('resp-<?php echo $sub['id']; ?>').style.display='block'" class="btn-gold-action btn-admin-sm"><i class="fas fa-reply"></i> Respond</button>
                                            <button onclick="document.getElementById('edit-sub-<?php echo $sub['id']; ?>').style.display='block'" class="btn-admin-action btn-admin-secondary btn-admin-sm" style="margin-top:6px;"><i class="fas fa-pen"></i> Edit</button>
                                            <div id="resp-<?php echo $sub['id']; ?>" style="display:none; margin-top:10px;">
                                                <form method="POST">
                                                    <?php csrf_field(); ?>
                                                    <input type="hidden" name="sub_page" value="<?php echo $sub_page; ?>">
                                                    <input type="hidden" name="action" value="respond_contact">
                                                    <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                                    <textarea name="response" class="form-control" placeholder="Type response..."></textarea>
                                                    <button type="submit" class="btn-submit">Submit Response</button>
                                                </form>
                                            </div>
                                            <div id="edit-sub-<?php echo $sub['id']; ?>" style="display:none; margin-top:10px;">
                                                <form method="POST">
                                                    <?php csrf_field(); ?>
                                                    <input type="hidden" name="sub_page" value="<?php echo $sub_page; ?>">
                                                    <input type="hidden" name="action" value="update_contact_submission">
                                                    <input type="hidden" name="id" value="<?php echo $sub['id']; ?>">
                                                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($sub['name']); ?>" style="margin-bottom:8px;" required>
                                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($sub['email']); ?>" style="margin-bottom:8px;" required>
                                                    <input type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars($sub['subject']); ?>" style="margin-bottom:8px;" required>
                                                    <textarea name="message" class="form-control" rows="3" style="margin-bottom:8px;" required><?php echo htmlspecialchars($sub['message']); ?></textarea>
                                                    <textarea name="response" class="form-control" rows="3" placeholder="Response (optional)" style="margin-bottom:8px;"><?php echo htmlspecialchars($sub['response'] ?? ''); ?></textarea>
                                                    <button type="submit" class="btn-submit">Save Changes</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-table-msg">
                                            <i class="fas fa-inbox"></i>
                                            No contact submissions yet.
                                            <?php if (!$all_tables_exist): ?>
                                                <br><span style="font-size:0.82rem;color:#999;">Setup the database above to enable contact form submissions.</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:15px; flex-wrap:wrap; gap:10px;">
                        <span style="color:#666; font-size:0.85rem;">
                            <?php if ($sub_total > 0): ?>
                                Showing <?php echo count($submissions_paginated); ?> of <?php echo $sub_total; ?> submissions
                                (Page <?php echo $sub_page; ?> of <?php echo $sub_total_pages; ?>)
                            <?php else: ?>
                                No contact submissions received yet
                            <?php endif; ?>
                        </span>
                        <div style="display:flex; gap:5px; align-items:center;">
                            <?php if ($sub_page > 1): ?>
                                <a href="?sub_page=1" style="padding:6px 10px; border:1px solid #ddd; border-radius:4px; text-decoration:none; color:#000; font-size:0.85rem;" title="First">&laquo;&laquo;</a>
                                <a href="?sub_page=<?php echo $sub_page - 1; ?>" style="padding:6px 10px; border:1px solid #ddd; border-radius:4px; text-decoration:none; color:#000; font-size:0.85rem;" title="Previous">&laquo;</a>
                            <?php endif; ?>
                            <?php
                            $start_page = max(1, $sub_page - 2);
                            $end_page = min($sub_total_pages, $sub_page + 2);
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?sub_page=<?php echo $i; ?>" style="padding:6px 12px; border:1px solid #ddd; border-radius:4px; text-decoration:none; <?php echo $i === $sub_page ? 'background:#003366; color:#fff; border-color:#003366;' : 'color:#000;'; ?> font-size:0.85rem;"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <?php if ($sub_page < $sub_total_pages): ?>
                                <a href="?sub_page=<?php echo $sub_page + 1; ?>" style="padding:6px 10px; border:1px solid #ddd; border-radius:4px; text-decoration:none; color:#000; font-size:0.85rem;" title="Next">&raquo;</a>
                                <a href="?sub_page=<?php echo $sub_total_pages; ?>" style="padding:6px 10px; border:1px solid #ddd; border-radius:4px; text-decoration:none; color:#000; font-size:0.85rem;" title="Last">&raquo;&raquo;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section" style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div class="card">
                    <h3>Current Executives</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($executives) > 0): ?>
                                <?php foreach ($executives as $exec): ?>
                                    <tr>
                                        <td><img src="<?php echo resolve_storage_url($exec['image_url'] ?? ''); ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover;"></td>
                                        <td><?php echo htmlspecialchars($exec['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($exec['position']); ?></td>
                                        <td>
                                            <button type="button" onclick="document.getElementById('edit-exec-<?php echo $exec['id']; ?>').style.display='block'" class="btn-admin-action btn-admin-secondary btn-admin-sm"><i class="fas fa-pen"></i> Edit</button>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this executive?');" style="display:inline;">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_executive">
                                                <input type="hidden" name="id" value="<?php echo $exec['id']; ?>">
                                                <button type="submit" class="btn-login" style="background:#dc3545; padding: 5px 10px; font-size: 0.8rem;"><i class="fas fa-trash"></i></button>
                                            </form>
                                            <div id="edit-exec-<?php echo $exec['id']; ?>" style="display:none; margin-top:10px;">
                                                <form method="POST" enctype="multipart/form-data">
                                                    <?php csrf_field(); ?>
                                                    <input type="hidden" name="action" value="update_executive">
                                                    <input type="hidden" name="id" value="<?php echo $exec['id']; ?>">
                                                    <input type="hidden" name="current_image_url" value="<?php echo htmlspecialchars($exec['image_url'] ?: 'images/aamusted.jpg'); ?>">
                                                    <img id="editExecPreview-<?php echo $exec['id']; ?>" src="<?php echo resolve_storage_url($exec['image_url'] ?? ''); ?>" class="upload-preview" alt="Executive image">
                                                    <input type="file" name="image" class="form-control image-upload-input" accept="image/*" data-preview-target="editExecPreview-<?php echo $exec['id']; ?>" data-file-name-target="editExecFileName-<?php echo $exec['id']; ?>" data-default-src="<?php echo resolve_storage_url($exec['image_url'] ?? ''); ?>" style="margin-bottom:8px;">
                                                    <div id="editExecFileName-<?php echo $exec['id']; ?>" class="upload-file-name" style="margin-bottom:8px;">No image selected</div>
                                                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($exec['full_name']); ?>" style="margin-bottom:8px;" required>
                                                    <input type="text" name="position" class="form-control" value="<?php echo htmlspecialchars($exec['position']); ?>" style="margin-bottom:8px;" required>
                                                    <button type="submit" class="btn-submit">Save Changes</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-table-msg">
                                            <i class="fas fa-user-tie"></i>
                                            No executives added yet.
                                            <?php if (!$all_tables_exist): ?><br><span style="font-size:0.82rem;color:#999;">Complete database setup above first.</span><?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card">
                    <h3>Current Alumni</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Year</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($alumni) > 0): ?>
                                <?php foreach ($alumni as $alum): ?>
                                    <tr>
                                        <td><img src="<?php echo resolve_storage_url($alum['image_url'] ?? ''); ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover;"></td>
                                        <td><?php echo htmlspecialchars($alum['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($alum['graduation_year']); ?></td>
                                        <td>
                                            <button type="button" onclick="document.getElementById('edit-alum-<?php echo $alum['id']; ?>').style.display='block'" class="btn-admin-action btn-admin-secondary btn-admin-sm"><i class="fas fa-pen"></i> Edit</button>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this alumni?');" style="display:inline;">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_alumni">
                                                <input type="hidden" name="id" value="<?php echo $alum['id']; ?>">
                                                <button type="submit" class="btn-login" style="background:#dc3545; padding: 5px 10px; font-size: 0.8rem;"><i class="fas fa-trash"></i></button>
                                            </form>
                                            <div id="edit-alum-<?php echo $alum['id']; ?>" style="display:none; margin-top:10px;">
                                                <form method="POST" enctype="multipart/form-data">
                                                    <?php csrf_field(); ?>
                                                    <input type="hidden" name="action" value="update_alumni">
                                                    <input type="hidden" name="id" value="<?php echo $alum['id']; ?>">
                                                    <input type="hidden" name="current_image_url" value="<?php echo htmlspecialchars($alum['image_url'] ?: 'images/aamusted.jpg'); ?>">
                                                    <img id="editAlumPreview-<?php echo $alum['id']; ?>" src="<?php echo resolve_storage_url($alum['image_url'] ?? ''); ?>" class="upload-preview" alt="Alumni image">
                                                    <input type="file" name="image" class="form-control image-upload-input" accept="image/*" data-preview-target="editAlumPreview-<?php echo $alum['id']; ?>" data-file-name-target="editAlumFileName-<?php echo $alum['id']; ?>" data-default-src="<?php echo resolve_storage_url($alum['image_url'] ?? ''); ?>" style="margin-bottom:8px;">
                                                    <div id="editAlumFileName-<?php echo $alum['id']; ?>" class="upload-file-name" style="margin-bottom:8px;">No image selected</div>
                                                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($alum['full_name']); ?>" style="margin-bottom:8px;" required>
                                                    <input type="text" name="graduation_year" class="form-control" value="<?php echo htmlspecialchars($alum['graduation_year']); ?>" style="margin-bottom:8px;" required>
                                                    <button type="submit" class="btn-submit">Save Changes</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-table-msg">
                                            <i class="fas fa-graduation-cap"></i>
                                            No alumni added yet.
                                            <?php if (!$all_tables_exist): ?><br><span style="font-size:0.82rem;color:#999;">Complete database setup above first.</span><?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="section" style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top: 30px;">
                <div class="card">
                    <h3>Current Gallery Items</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($projects) > 0): ?>
                                <?php foreach ($projects as $proj): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($proj['year'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($proj['title']); ?></td>
                                        <td>
                                            <button type="button" onclick="document.getElementById('edit-proj-<?php echo $proj['id']; ?>').style.display='block'" class="btn-admin-action btn-admin-secondary btn-admin-sm"><i class="fas fa-pen"></i> Edit</button>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this project?');" style="display:inline;">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_project">
                                                <input type="hidden" name="id" value="<?php echo $proj['id']; ?>">
                                                <button type="submit" class="btn-login" style="background:#dc3545; padding: 5px 10px; font-size: 0.8rem;"><i class="fas fa-trash"></i></button>
                                            </form>
                                            <div id="edit-proj-<?php echo $proj['id']; ?>" style="display:none; margin-top:10px;">
                                                <form method="POST">
                                                    <?php csrf_field(); ?>
                                                    <input type="hidden" name="action" value="update_project">
                                                    <input type="hidden" name="id" value="<?php echo $proj['id']; ?>">
                                                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($proj['title']); ?>" style="margin-bottom:8px;" required>
                                                    <textarea name="description" class="form-control" rows="3" style="margin-bottom:8px;" required><?php echo htmlspecialchars($proj['description']); ?></textarea>
                                                    <input type="text" name="year" class="form-control" value="<?php echo htmlspecialchars($proj['year'] ?? ''); ?>" style="margin-bottom:8px;">
                                                    <button type="submit" class="btn-submit">Save Changes</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3">
                                        <div class="empty-table-msg">
                                            <i class="fas fa-project-diagram"></i>
                                            No projects added yet.
                                            <?php if (!$all_tables_exist): ?><br><span style="font-size:0.82rem;color:#999;">Complete database setup above first.</span><?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card">
                    <h3>Current Projects</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($gallery_items) > 0): ?>
                                <?php foreach ($gallery_items as $g): ?>
                                    <tr>
                                        <td><img src="<?php echo resolve_storage_url($g['image_url']); ?>" style="width:60px; height:40px; object-fit:cover; border-radius:4px;"></td>
                                        <td><?php echo htmlspecialchars($g['caption']); ?></td>
                                        <td><?php echo htmlspecialchars($g['category']); ?></td>
                                        <td>
                                            <button type="button" onclick="document.getElementById('edit-gallery-<?php echo $g['id']; ?>').style.display='block'" class="btn-admin-action btn-admin-secondary btn-admin-sm"><i class="fas fa-pen"></i> Edit</button>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this gallery item?');" style="display:inline;">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_gallery">
                                                <input type="hidden" name="id" value="<?php echo $g['id']; ?>">
                                                <button type="submit" class="btn-login" style="background:#dc3545; padding: 5px 10px; font-size: 0.8rem;"><i class="fas fa-trash"></i></button>
                                            </form>
                                            <div id="edit-gallery-<?php echo $g['id']; ?>" style="display:none; margin-top:10px;">
                                                <form method="POST" enctype="multipart/form-data">
                                                    <?php csrf_field(); ?>
                                                    <input type="hidden" name="action" value="update_gallery">
                                                    <input type="hidden" name="id" value="<?php echo $g['id']; ?>">
                                                    <input type="hidden" name="current_image_url" value="<?php echo htmlspecialchars($g['image_url']); ?>">
                                                    <img id="editGalleryPreview-<?php echo $g['id']; ?>" src="<?php echo resolve_storage_url($g['image_url']); ?>" class="upload-preview" alt="Gallery image">
                                                    <input type="file" name="image" class="form-control image-upload-input" accept="image/*" data-preview-target="editGalleryPreview-<?php echo $g['id']; ?>" data-file-name-target="editGalleryFileName-<?php echo $g['id']; ?>" data-default-src="<?php echo resolve_storage_url($g['image_url']); ?>" style="margin-bottom:8px;">
                                                    <div id="editGalleryFileName-<?php echo $g['id']; ?>" class="upload-file-name" style="margin-bottom:8px;">No image selected</div>
                                                    <input type="text" name="caption" class="form-control" value="<?php echo htmlspecialchars($g['caption']); ?>" style="margin-bottom:8px;" required>
                                                    <select name="category" class="form-control" style="margin-bottom:8px;" required>
                                                        <option value="School" <?php echo $g['category'] == 'School' ? 'selected' : ''; ?>>School</option>
                                                        <option value="Sports" <?php echo $g['category'] == 'Sports' ? 'selected' : ''; ?>>Sports</option>
                                                        <option value="Events" <?php echo $g['category'] == 'Events' ? 'selected' : ''; ?>>Events</option>
                                                        <option value="Graduation" <?php echo $g['category'] == 'Graduation' ? 'selected' : ''; ?>>Graduation</option>
                                                        <option value="Infrastructure" <?php echo $g['category'] == 'Infrastructure' ? 'selected' : ''; ?>>Infrastructure</option>
                                                        <option value="Cultural" <?php echo $g['category'] == 'Cultural' ? 'selected' : ''; ?>>Cultural</option>
                                                    </select>
                                                    <button type="submit" class="btn-submit">Save Changes</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-table-msg">
                                            <i class="fas fa-images"></i>
                                            No gallery images uploaded yet.
                                            <?php if (!$all_tables_exist): ?><br><span style="font-size:0.82rem;color:#999;">Complete database setup above first.</span><?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Executive Modal -->
    <div id="execModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('execModal').style.display='none'">&times;</span>
            <h3>Add Executive</h3>
            <form method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="add_executive">
                <div class="form-group">
                    <label>Profile Picture</label>
                    <img id="execImagePreview" src="../images/aamusted.jpg" alt="Executive Preview" class="upload-preview">
                    <input type="file" name="image" class="form-control image-upload-input" accept="image/*" data-preview-target="execImagePreview" data-file-name-target="execImageFileName" data-default-src="../images/aamusted.jpg">
                    <div id="execImageFileName" class="upload-file-name">No image selected</div>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control" placeholder="Enter Full Name" required>
                </div>
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" name="position" class="form-control" placeholder="Enter Position" required>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%;">Add Executive</button>
            </form>
        </div>
    </div>

    <!-- Alumni Modal -->
    <div id="alumniModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('alumniModal').style.display='none'">&times;</span>
            <h3>Add Alumni</h3>
            <form method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="add_alumni">
                <div class="form-group">
                    <label>Profile Picture</label>
                    <img id="alumniImagePreview" src="../images/aamusted.jpg" alt="Alumni Preview" class="upload-preview">
                    <input type="file" name="image" class="form-control image-upload-input" accept="image/*" data-preview-target="alumniImagePreview" data-file-name-target="alumniImageFileName" data-default-src="../images/aamusted.jpg">
                    <div id="alumniImageFileName" class="upload-file-name">No image selected</div>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control" placeholder="Enter Full Name" required>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <input type="text" name="graduation_year" class="form-control" placeholder="Enter Graduation Year" required>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%;">Add Alumni</button>
            </form>
        </div>
    </div>

    <!-- Project Modal -->
    <div id="projectModal" class="modal">
        <div class="modal-content" style="width: 500px;">
            <span class="close-btn" onclick="document.getElementById('projectModal').style.display='none'">&times;</span>
            <h3>Add Project</h3>
            <form method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="add_project">
                <div class="form-group">
                    <label>Project Image</label>
                    <img id="projectImagePreview" src="../images/aamusted.jpg" alt="Project Preview" class="upload-preview">
                    <input type="file" name="image" class="form-control image-upload-input" accept="image/*" data-preview-target="projectImagePreview" data-file-name-target="projectImageFileName" data-default-src="../images/aamusted.jpg">
                    <div id="projectImageFileName" class="upload-file-name">No image selected</div>
                </div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" placeholder="Project Title" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Brief description"></textarea>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="completed">Completed</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="planned">Planned</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="project_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%;">Add Project</button>
            </form>
        </div>
    </div>

    <!-- Gallery Modal -->
    <div id="galleryModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('galleryModal').style.display='none'">&times;</span>
            <h3>Add Gallery Item</h3>
            <form method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="add_gallery">
                <div class="form-group">
                    <label>Image</label>
                    <img id="galleryImagePreview" src="../images/aamusted.jpg" alt="Gallery Preview" class="upload-preview">
                    <input type="file" name="image" class="form-control image-upload-input" accept="image/*" required data-preview-target="galleryImagePreview" data-file-name-target="galleryImageFileName" data-default-src="../images/aamusted.jpg">
                    <div id="galleryImageFileName" class="upload-file-name">No image selected</div>
                </div>
                <div class="form-group">
                    <label>Title/Caption</label>
                    <input type="text" name="title" class="form-control" placeholder="Image Title" required>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%;">Add to Gallery</button>
            </form>
        </div>
    </div>

    <script>
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = "none";
            }
        }

        document.querySelectorAll('.image-upload-input').forEach(function(input) {
            input.addEventListener('change', function() {
                const previewId = input.getAttribute('data-preview-target');
                const fileNameId = input.getAttribute('data-file-name-target');
                const defaultSrc = input.getAttribute('data-default-src') || '../images/aamusted.jpg';
                const preview = previewId ? document.getElementById(previewId) : null;
                const fileNameLabel = fileNameId ? document.getElementById(fileNameId) : null;
                const file = input.files && input.files[0] ? input.files[0] : null;

                if (!file) {
                    if (preview) preview.src = defaultSrc;
                    if (fileNameLabel) fileNameLabel.textContent = 'No image selected';
                    return;
                }

                if (fileNameLabel) fileNameLabel.textContent = file.name;

                if (!file.type.startsWith('image/')) {
                    if (preview) preview.src = defaultSrc;
                    if (fileNameLabel) fileNameLabel.textContent = 'Please select an image file';
                    input.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(event) {
                    if (preview) preview.src = event.target.result;
                };
                reader.readAsDataURL(file);
            });
        });
    </script>
</body>
</html>
