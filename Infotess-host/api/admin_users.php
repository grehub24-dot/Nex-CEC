<?php
require_once 'includes/db.php';

// Enforce access control
requireAccess('users');

// Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$message = '';
$error = '';

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_request_csrf();
    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        if ($user_id !== $_SESSION['user_id']) { // Prevent self-delete
            // Delete messages first to avoid FK violations
            $pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")->execute([$user_id, $user_id]);
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $message = "User deleted successfully.";
            } else {
                $error = "Failed to delete user.";
            }
        } else {
            $error = "You cannot delete your own account.";
        }
    }

    // Bulk delete users
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete_users') {
        $ids = $_POST['user_ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $deleted_count = 0;
            $error_count = 0;
            foreach ($ids as $rawId) {
                $userId = (int)$rawId;
                if ($userId <= 0) continue;
                // Skip super_admin and self
                if ($userId === (int)$_SESSION['user_id']) continue;
                try {
                    // Check if user is super_admin
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                    if (!$user) continue;
                    if ($user['role'] === 'super_admin') continue;

                    $pdo->beginTransaction();
                    // Delete messages referencing this user to avoid FK violations
                    $pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")->execute([$userId, $userId]);
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
                    $pdo->commit();
                    $deleted_count++;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_count++;
                }
            }
            $message = "$deleted_count user(s) deleted successfully.";
            if ($error_count > 0) {
                $message .= " $error_count failed.";
            }
        }
    }
}

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Fetch ALL users (bridge does not support COUNT(*)) then paginate in PHP
$allUsers = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$allUsers = $allUsers ? $allUsers->fetchAll() : [];
$total_rows = count($allUsers);
$total_pages = ceil($total_rows / $limit);
$offset = ($page - 1) * $limit;
$users = array_slice($allUsers, $offset, $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
            <?php echo renderSidebar('users', $school_name); ?>

        <main class="main-content">
            <div class="top-bar">
                <h2>User Management</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <h3 style="margin:0;">All Users</h3>
                    <span style="font-size:0.85rem; color:#666;">Total: <?php echo $total_rows; ?> users</span>
                </div>

                <!-- Bulk Delete Toolbar -->
                <form method="POST" action="users.php" id="userBulkForm">
                    <input type="hidden" name="action" value="bulk_delete_users">
                    <?php csrf_field(); ?>
                    <div style="margin-bottom:15px; display:flex; align-items:center; gap:10px;">
                        <button type="button" onclick="confirmUserBulkDelete()" class="btn-login" style="background:#e74c3c; color:#fff; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; font-size:0.9rem;">
                            <i class="fas fa-trash"></i> Delete Selected
                        </button>
                        <span id="userSelectedCount" style="color:#666; font-size:0.85rem;">0 selected</span>
                    </div>

                <table class="table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="userSelectAll" onchange="toggleUserAll(this)"></th>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td style="text-align:center;">
                                    <?php if ($user['role'] !== 'super_admin' && $user['id'] !== $_SESSION['user_id']): ?>
                                        <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" class="user-checkbox" onchange="updateUserSelectedCount()">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                                <td>
                                    <span class="badge <?php echo $user['role'] === 'admin' ? 'badge-primary' : 'badge-secondary'; ?>">
                                        <?php echo ucfirst($user['role'] ?? 'unknown'); ?>
                                    </span>
                                </td>
                                <td><?php echo $user['created_at']; ?></td>
                                <td>
                                    <?php if ($user['role'] !== 'super_admin' && $user['id'] !== $_SESSION['user_id']): ?>
                                        <form method="POST" onsubmit="return confirm('Are you sure?');" style="display:inline;">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn-admin-action btn-admin-danger btn-admin-sm"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </form>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div style="display:flex; justify-content:center; gap:5px; margin-top:20px; flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" style="display:inline-flex; align-items:center; gap:5px; padding:8px 16px; background:#f8f9fa; color:#000; border:1px solid #ddd; border-radius:6px; text-decoration:none; font-size:14px;">&laquo; Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" style="display:inline-flex; align-items:center; justify-content:center; min-width:38px; padding:8px 12px; background:<?php echo $i == $page ? '#1a5276' : '#f8f9fa'; ?>; color:<?php echo $i == $page ? '#fff' : '#000'; ?>; border:1px solid <?php echo $i == $page ? '#1a5276' : '#ddd'; ?>; border-radius:6px; text-decoration:none; font-size:14px; font-weight:<?php echo $i == $page ? '700' : '400'; ?>;"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" style="display:inline-flex; align-items:center; gap:5px; padding:8px 16px; background:#f8f9fa; color:#000; border:1px solid #ddd; border-radius:6px; text-decoration:none; font-size:14px;">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
    // Bulk delete functions
    function toggleUserAll(source) {
        var checkboxes = document.querySelectorAll('.user-checkbox');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = source.checked;
        }
        updateUserSelectedCount();
    }

    function updateUserSelectedCount() {
        var checkboxes = document.querySelectorAll('.user-checkbox:checked');
        document.getElementById('userSelectedCount').textContent = checkboxes.length + ' selected';
    }

    function confirmUserBulkDelete() {
        var checkboxes = document.querySelectorAll('.user-checkbox:checked');
        if (checkboxes.length === 0) {
            alert('Please select at least one user to delete.');
            return;
        }
        if (confirm('Are you sure you want to delete ' + checkboxes.length + ' selected user(s)? This action cannot be undone.')) {
            document.getElementById('userBulkForm').submit();
        }
    }
    </script>
</body>
</html>
