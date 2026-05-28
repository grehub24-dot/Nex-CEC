<?php
/**
 * ONE-TIME MIGRATION: Backfill parent_students links for existing students.
 *
 * PROBLEM:
 *   Before commit b9611be, only admin_enrollments.php created parent_students
 *   links. Manual registration (admin_students.php) and bulk import
 *   (admin_bulk_import.php) did not. This script backfills those missing links.
 *
 * WHAT IT DOES:
 *   1. Finds all students who do NOT have a parent_students link
 *   2. For each student with a guardian_email:
 *      a. Checks if a users account already exists with that email (any role)
 *      b. If not, creates a new parent user account with a random password
 *      c. Sends a welcome email (new accounts only)
 *      d. Inserts the parent_students link
 *   3. Also detects staff dual-role: if a staff member's phone/name matches
 *      a student's guardian info, links using the staff's existing user_id
 *
 * USAGE:
 *   1. Visit this page in admin: /admin/migrate_parent_students
 *   2. Review the dry-run preview
 *   3. Click "Execute Migration" to run
 *   4. Delete this file after successful migration
 *
 * SAFE TO RE-RUN: skips already-linked records.
 */

require_once 'includes/db.php';
require_once 'includes/Mailer.php';
requireAccess('admin_settings');  // restrict to super admins / settings access

// Fetch school name
$school_name = 'Nex CEC';
$stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'school_name'");
$row = $stmt->fetch();
if ($row && !empty($row['setting_value'])) {
    $school_name = $row['setting_value'];
}

$message = '';
$error   = '';
$results = [];

// ---------------------------------------------------------------------------
// Handle POST: Execute migration
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'migrate') {
    try {
        // --- Step 1: Email-based (guardian_email ↔ users.email) ---
        $emailLinked = 0;
        $emailStmt = $pdo->prepare("
            SELECT s.id AS student_id, s.full_name AS student_name,
                   s.guardian_email, s.guardian_name, s.guardian_relationship,
                   s.guardian_phone_primary,
                   u.id AS existing_user_id, u.role AS existing_role
            FROM students s
            JOIN users u ON TRIM(LOWER(u.email)) = TRIM(LOWER(s.guardian_email))
            WHERE s.guardian_email IS NOT NULL AND TRIM(s.guardian_email) != ''
              AND NOT EXISTS (
                SELECT 1 FROM parent_students ps
                WHERE ps.parent_user_id = u.id AND ps.student_id = s.id
              )
            ORDER BY s.full_name
        ");
        $emailStmt->execute();
        while ($row = $emailStmt->fetch()) {
            $parentUserId = (int)$row['existing_user_id'];
            $studentId    = (int)$row['student_id'];
            $relationship = !empty($row['guardian_relationship']) ? $row['guardian_relationship'] : 'Guardian';

            // Insert link
            $ins = $pdo->prepare("INSERT INTO parent_students (parent_user_id, student_id, relationship, is_primary) VALUES (?, ?, ?, ?)");
            $ins->execute([$parentUserId, $studentId, $relationship, true]);
            $emailLinked++;

            $results[] = "EMAIL: Linked {$row['student_name']} (student #{$studentId}) ↔ user #{$parentUserId} ({$row['guardian_email']}, role: {$row['existing_role']})";
        }

        // --- Step 2: Create missing parent user accounts ---
        $createdUsers = 0;
        $createdLinks = 0;
        $createStmt = $pdo->prepare("
            SELECT s.id AS student_id, s.full_name AS student_name,
                   s.guardian_email, s.guardian_name, s.guardian_relationship,
                   s.guardian_phone_primary
            FROM students s
            WHERE s.guardian_email IS NOT NULL AND TRIM(s.guardian_email) != ''
              AND NOT EXISTS (
                SELECT 1 FROM users u WHERE TRIM(LOWER(u.email)) = TRIM(LOWER(s.guardian_email))
              )
              AND NOT EXISTS (
                SELECT 1 FROM parent_students ps WHERE ps.student_id = s.id
              )
            ORDER BY s.full_name
        ");
        $createStmt->execute();
        while ($row = $createStmt->fetch()) {
            $guardianEmail = trim($row['guardian_email']);
            $studentId     = (int)$row['student_id'];
            $guardianName  = $row['guardian_name'] ?? '';
            $relationship  = !empty($row['guardian_relationship']) ? $row['guardian_relationship'] : 'Guardian';

            // Create parent user account
            $autoPassword = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);
            $passwordHash = password_hash($autoPassword, PASSWORD_DEFAULT);

            $ins = $pdo->prepare("INSERT INTO users (email, password, role, status) VALUES (?, ?, 'parent', 'active')");
            $ins->execute([$guardianEmail, $passwordHash]);
            $parentUserId = (int)$pdo->lastInsertId();
            $createdUsers++;

            // Insert parent_students link
            $ins = $pdo->prepare("INSERT INTO parent_students (parent_user_id, student_id, relationship, is_primary) VALUES (?, ?, ?, ?)");
            $ins->execute([$parentUserId, $studentId, $relationship, true]);
            $createdLinks++;

            // Send welcome email
            try {
                $appUrl = getAppUrl();
                $subject = "Parent Portal Access — $school_name";
                $html = "
                <!DOCTYPE html>
                <html>
                <head><meta charset='UTF-8'></head>
                <body style='font-family:Arial,sans-serif;background:#f4f4f4;padding:20px;'>
                    <div style='max-width:600px;margin:0 auto;background:white;border-radius:8px;overflow:hidden;'>
                        <div style='background:linear-gradient(to right,#1a5276,#2e86c1);color:white;text-align:center;padding:40px 20px;'>
                            <h1 style='margin:0;font-size:24px;'>Welcome to $school_name</h1>
                            <p style='margin-top:8px;opacity:0.9;'>Parent Portal Access</p>
                        </div>
                        <div style='padding:30px;color:#333;font-size:14px;'>
                            <p>Dear " . htmlspecialchars($guardianName ?: 'Parent/Guardian', ENT_QUOTES, 'UTF-8') . ",</p>
                            <p>A parent portal account has been created for you to track your child's progress.</p>
                            <div style='background:#f0f7ff;border:1px solid #b8d9e8;border-radius:6px;padding:20px;margin:20px 0;'>
                                <div style='font-size:12px;color:#666;'>Email</div>
                                <div style='font-size:18px;font-weight:bold;color:#1a5276;'>" . htmlspecialchars($guardianEmail, ENT_QUOTES, 'UTF-8') . "</div>
                                <div style='font-size:12px;color:#666;margin-top:12px;'>Temporary Password</div>
                                <div style='font-size:18px;font-weight:bold;color:#1a5276;'>" . htmlspecialchars($autoPassword, ENT_QUOTES, 'UTF-8') . "</div>
                            </div>
                            <p style='text-align:center;'>
                                <a href='" . htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8') . "/login.php' style='display:inline-block;background:#27ae60;color:white;padding:12px 25px;text-decoration:none;border-radius:6px;font-weight:bold;'>Login to Parent Portal</a>
                            </p>
                            <p style='font-size:12px;color:#999;'>Please log in and change your password immediately.</p>
                        </div>
                        <div style='text-align:center;padding:30px;font-size:12px;color:#666;border-top:1px solid #eee;'>
                            $school_name &bull; Parent Portal
                        </div>
                    </div>
                </body>
                </html>";

                $mailer = new Mailer();
                $mailer->sendHTML($guardianEmail, $subject, $html);
            } catch (Exception $e) {
                error_log("migrate_parent_students: welcome email failed for $guardianEmail: " . $e->getMessage());
            }

            $results[] = "CREATED: User account + link for {$row['student_name']} (student #{$studentId}) ↔ {$guardianEmail}";
        }

        // --- Step 3: Staff phone/name match (dual-role detection) ---
        $staffLinked = 0;
        // Phone match
        $phoneStmt = $pdo->prepare("
            SELECT s.id AS student_id, s.full_name AS student_name,
                   s.guardian_name, s.guardian_relationship, s.guardian_phone_primary,
                   st.full_name AS staff_name, st.user_id
            FROM students s
            JOIN staff st ON TRIM(st.phone) = TRIM(s.guardian_phone_primary)
            WHERE s.guardian_phone_primary IS NOT NULL AND TRIM(s.guardian_phone_primary) != ''
              AND st.user_id IS NOT NULL
              AND NOT EXISTS (
                SELECT 1 FROM parent_students ps WHERE ps.parent_user_id = st.user_id AND ps.student_id = s.id
              )
            ORDER BY s.full_name
        ");
        $phoneStmt->execute();
        while ($row = $phoneStmt->fetch()) {
            $studentId   = (int)$row['student_id'];
            $parentUserId = (int)$row['user_id'];
            $relationship = !empty($row['guardian_relationship']) ? $row['guardian_relationship'] : 'Guardian';

            $ins = $pdo->prepare("INSERT INTO parent_students (parent_user_id, student_id, relationship, is_primary) VALUES (?, ?, ?, ?)");
            $ins->execute([$parentUserId, $studentId, $relationship, true]);
            $staffLinked++;

            $results[] = "STAFF-PHONE: Linked {$row['student_name']} (student #{$studentId}) ↔ staff {$row['staff_name']} (user #{$parentUserId}) via phone match";
        }

        // Name match (lower confidence)
        $nameLinked = 0;
        $nameStmt = $pdo->prepare("
            SELECT s.id AS student_id, s.full_name AS student_name,
                   s.guardian_name, s.guardian_relationship,
                   st.full_name AS staff_name, st.user_id
            FROM students s
            JOIN staff st ON TRIM(LOWER(st.full_name)) = TRIM(LOWER(s.guardian_name))
            WHERE s.guardian_name IS NOT NULL AND TRIM(s.guardian_name) != ''
              AND st.user_id IS NOT NULL
              AND NOT EXISTS (
                SELECT 1 FROM parent_students ps WHERE ps.parent_user_id = st.user_id AND ps.student_id = s.id
              )
              -- Skip already matched by email or phone above
              AND NOT EXISTS (
                SELECT 1 FROM parent_students ps2 WHERE ps2.student_id = s.id
              )
            ORDER BY s.full_name
        ");
        $nameStmt->execute();
        while ($row = $nameStmt->fetch()) {
            $studentId   = (int)$row['student_id'];
            $parentUserId = (int)$row['user_id'];
            $relationship = !empty($row['guardian_relationship']) ? $row['guardian_relationship'] : 'Guardian';

            $ins = $pdo->prepare("INSERT INTO parent_students (parent_user_id, student_id, relationship, is_primary) VALUES (?, ?, ?, ?)");
            $ins->execute([$parentUserId, $studentId, $relationship, true]);
            $nameLinked++;

            $results[] = "STAFF-NAME: Linked {$row['student_name']} (student #{$studentId}) ↔ staff {$row['staff_name']} (user #{$parentUserId}) via name match";
        }

        $message = "Migration complete!";
        $message .= " Email-linked: $emailLinked | New users created: $createdUsers | New links from creation: $createdLinks";
        $message .= " | Staff-phone: $staffLinked | Staff-name: $nameLinked";
        $message .= " | Total new parent_students rows: " . ($emailLinked + $createdLinks + $staffLinked + $nameLinked);

    } catch (Exception $e) {
        $error = "Migration failed: " . $e->getMessage();
        error_log("admin_migrate_parent_students: " . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// Dry-run preview counts (always shown)
// ---------------------------------------------------------------------------

// Count students without parent_students link
$totalUnlinked = 0;
$emailMatchCount = 0;
$needCreateCount = 0;
$staffPhoneCount = 0;
$staffNameCount = 0;

try {
    $stmt = $pdo->query("
        SELECT COUNT(*) AS cnt FROM students s
        WHERE NOT EXISTS (SELECT 1 FROM parent_students ps WHERE ps.student_id = s.id)
    ");
    $totalUnlinked = (int)$stmt->fetch()['cnt'];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt FROM students s
        JOIN users u ON TRIM(LOWER(u.email)) = TRIM(LOWER(s.guardian_email))
        WHERE s.guardian_email IS NOT NULL AND TRIM(s.guardian_email) != ''
          AND NOT EXISTS (SELECT 1 FROM parent_students ps WHERE ps.parent_user_id = u.id AND ps.student_id = s.id)
    ");
    $stmt->execute();
    $emailMatchCount = (int)$stmt->fetch()['cnt'];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt FROM students s
        WHERE s.guardian_email IS NOT NULL AND TRIM(s.guardian_email) != ''
          AND NOT EXISTS (SELECT 1 FROM users u WHERE TRIM(LOWER(u.email)) = TRIM(LOWER(s.guardian_email)))
          AND NOT EXISTS (SELECT 1 FROM parent_students ps WHERE ps.student_id = s.id)
    ");
    $stmt->execute();
    $needCreateCount = (int)$stmt->fetch()['cnt'];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt FROM students s
        JOIN staff st ON TRIM(st.phone) = TRIM(s.guardian_phone_primary)
        WHERE s.guardian_phone_primary IS NOT NULL AND TRIM(s.guardian_phone_primary) != ''
          AND st.user_id IS NOT NULL
          AND NOT EXISTS (SELECT 1 FROM parent_students ps WHERE ps.parent_user_id = st.user_id AND ps.student_id = s.id)
    ");
    $stmt->execute();
    $staffPhoneCount = (int)$stmt->fetch()['cnt'];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt FROM students s
        JOIN staff st ON TRIM(LOWER(st.full_name)) = TRIM(LOWER(s.guardian_name))
        WHERE s.guardian_name IS NOT NULL AND TRIM(s.guardian_name) != ''
          AND st.user_id IS NOT NULL
          AND NOT EXISTS (SELECT 1 FROM parent_students ps WHERE ps.parent_user_id = st.user_id AND ps.student_id = s.id)
          AND NOT EXISTS (SELECT 1 FROM parent_students ps2 WHERE ps2.student_id = s.id)
    ");
    $stmt->execute();
    $staffNameCount = (int)$stmt->fetch()['cnt'];

} catch (Exception $e) {
    $error = "Preview query failed: " . $e->getMessage();
}

// Current link count
$currentLinks = 0;
$stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM parent_students");
$currentLinks = (int)$stmt->fetch()['cnt'];

// Students without any guardian_email
$noEmailCount = 0;
$stmt = $pdo->query("
    SELECT COUNT(*) AS cnt FROM students s
    WHERE (s.guardian_email IS NULL OR TRIM(s.guardian_email) = '')
      AND NOT EXISTS (SELECT 1 FROM parent_students ps WHERE ps.student_id = s.id)
");
$noEmailCount = (int)$stmt->fetch()['cnt'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrate Parent-Student Links — Nex CEC</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .subtitle { color: #666; font-size: 14px; margin-bottom: 20px; }
        .stat { display: inline-block; background: #e8f0fe; padding: 12px 20px; border-radius: 8px; margin: 4px; text-align: center; }
        .stat .num { font-size: 28px; font-weight: bold; color: #1a73e8; }
        .stat .label { font-size: 12px; color: #555; }
        .stat.warn { background: #fef7e0; }
        .stat.warn .num { color: #e37400; }
        .stat.good { background: #e6f4ea; }
        .stat.good .num { color: #137333; }
        .stat.danger { background: #fce8e6; }
        .stat.danger .num { color: #c5221f; }
        .results { background: #1e1e1e; color: #d4d4d4; font-family: 'Consolas', 'Monaco', monospace; font-size: 12px; padding: 16px; border-radius: 8px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
        .results .email { color: #6fc3df; }
        .results .created { color: #6a9955; }
        .results .staff { color: #dcdcaa; }
        .btn { display: inline-block; padding: 12px 28px; border-radius: 8px; font-size: 16px; font-weight: 600; border: none; cursor: pointer; }
        .btn-primary { background: #1a73e8; color: white; }
        .btn-primary:hover { background: #1557b0; }
        .btn-primary:disabled { background: #a8c7fa; cursor: not-allowed; }
        .msg { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        .msg-success { background: #e6f4ea; color: #137333; border: 1px solid #ceead6; }
        .msg-error { background: #fce8e6; color: #c5221f; border: 1px solid #f5c6cb; }
        .note { background: #fef7e0; border: 1px solid #fde9b3; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #5f4b00; margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { color: #555; font-weight: 600; font-size: 12px; text-transform: uppercase; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>🔗 Parent-Student Link Migration</h1>
        <p class="subtitle">
            Backfill missing <code>parent_students</code> links for students registered
            before the automatic linking was implemented in the registration flows.
        </p>

        <?php if ($message): ?>
            <div class="msg msg-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="msg msg-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div style="margin: 20px 0;">
            <div class="stat <?= $currentLinks > 3 ? 'good' : 'warn' ?>">
                <div class="num"><?= $currentLinks ?></div>
                <div class="label">Existing Links</div>
            </div>
            <div class="stat <?= $totalUnlinked > 0 ? 'danger' : 'good' ?>">
                <div class="num"><?= $totalUnlinked ?></div>
                <div class="label">Unlinked Students</div>
            </div>
            <div class="stat">
                <div class="num"><?= $emailMatchCount ?></div>
                <div class="label">Email Match (link existing)</div>
            </div>
            <div class="stat warn">
                <div class="num"><?= $needCreateCount ?></div>
                <div class="label">Need New User Account</div>
            </div>
            <div class="stat">
                <div class="num"><?= $staffPhoneCount ?></div>
                <div class="label">Staff Phone Match</div>
            </div>
            <div class="stat">
                <div class="num"><?= $staffNameCount ?></div>
                <div class="label">Staff Name Match</div>
            </div>
            <?php if ($noEmailCount > 0): ?>
                <div class="stat warn">
                    <div class="num"><?= $noEmailCount ?></div>
                    <div class="label">No Guardian Email (skipped)</div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($results): ?>
            <h3 style="margin-top: 24px;">📋 Migration Log</h3>
            <div class="results">
                <?php foreach ($results as $line): ?>
                    <?php if (strpos($line, 'EMAIL:') === 0): ?>
                        <div class="email"><?= htmlspecialchars($line) ?></div>
                    <?php elseif (strpos($line, 'CREATED:') === 0): ?>
                        <div class="created"><?= htmlspecialchars($line) ?></div>
                    <?php elseif (strpos($line, 'STAFF-') === 0): ?>
                        <div class="staff"><?= htmlspecialchars($line) ?></div>
                    <?php else: ?>
                        <div><?= htmlspecialchars($line) ?></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Preview tables -->
        <?php if ($totalUnlinked > 0 && !$results): ?>
            <details style="margin-top: 16px;">
                <summary style="cursor:pointer;font-weight:600;font-size:14px;">
                    👁️ Preview: Students that will be linked
                </summary>

                <?php if ($emailMatchCount > 0): ?>
                <h4 style="margin:16px 0 4px;">Email Match</h4>
                <table>
                    <tr><th>#</th><th>Student</th><th>Guardian Email</th><th>Existing User</th></tr>
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT s.full_name AS sn, s.guardian_email, u.email AS ue, u.role
                        FROM students s
                        JOIN users u ON TRIM(LOWER(u.email)) = TRIM(LOWER(s.guardian_email))
                        WHERE s.guardian_email IS NOT NULL AND TRIM(s.guardian_email) != ''
                          AND NOT EXISTS (SELECT 1 FROM parent_students ps WHERE ps.parent_user_id = u.id AND ps.student_id = s.id)
                        ORDER BY s.full_name
                        LIMIT 50
                    ");
                    $stmt->execute();
                    $i = 0;
                    while ($r = $stmt->fetch()): $i++; ?>
                    <tr>
                        <td><?= $i ?></td>
                        <td><?= htmlspecialchars($r['sn']) ?></td>
                        <td><?= htmlspecialchars($r['guardian_email']) ?></td>
                        <td><?= htmlspecialchars($r['ue']) ?> (<?= htmlspecialchars($r['role']) ?>)</td>
                    </tr>
                    <?php endwhile; ?>
                </table>
                <?php endif; ?>

                <?php if ($needCreateCount > 0): ?>
                <h4 style="margin:16px 0 4px;">New Parent Accounts Needed</h4>
                <table>
                    <tr><th>#</th><th>Student</th><th>Guardian Email</th><th>Guardian Name</th></tr>
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT s.full_name, s.guardian_email, s.guardian_name
                        FROM students s
                        WHERE s.guardian_email IS NOT NULL AND TRIM(s.guardian_email) != ''
                          AND NOT EXISTS (SELECT 1 FROM users u WHERE TRIM(LOWER(u.email)) = TRIM(LOWER(s.guardian_email)))
                          AND NOT EXISTS (SELECT 1 FROM parent_students ps WHERE ps.student_id = s.id)
                        ORDER BY s.full_name
                        LIMIT 50
                    ");
                    $stmt->execute();
                    $i = 0;
                    while ($r = $stmt->fetch()): $i++; ?>
                    <tr>
                        <td><?= $i ?></td>
                        <td><?= htmlspecialchars($r['full_name']) ?></td>
                        <td><?= htmlspecialchars($r['guardian_email']) ?></td>
                        <td><?= htmlspecialchars($r['guardian_name'] ?: '—') ?></td>
                    </tr>
                    <?php endwhile; ?>
                </table>
                <?php endif; ?>
            </details>
        <?php endif; ?>

        <!-- Action button -->
        <?php if ($totalUnlinked > 0): ?>
            <form method="POST" style="margin-top: 24px;" onsubmit="return confirm('This will create user accounts and send welcome emails to parents. Proceed?');">
                <input type="hidden" name="action" value="migrate">
                <button type="submit" class="btn btn-primary">
                    🚀 Execute Migration
                </button>
                <span style="font-size:13px;color:#666;margin-left:12px;">
                    Links <strong><?= $emailMatchCount + $needCreateCount + $staffPhoneCount + $staffNameCount ?></strong> students
                    <?php if ($needCreateCount > 0): ?>
                        &bull; Creates <strong><?= $needCreateCount ?></strong> new user accounts
                        &bull; Sends welcome emails
                    <?php endif; ?>
                </span>
            </form>
            <div class="note">
                <strong>⚠️ Safe to re-run:</strong> Skips already-linked records. New user accounts
                get random passwords sent via email. Students without a guardian_email are skipped.
                Delete this file (<code>admin_migrate_parent_students.php</code>) after successful migration.
            </div>
        <?php else: ?>
            <div class="msg msg-success" style="margin-top:20px;">
                ✅ All students have parent_students links! Nothing to migrate.
                You can delete this file.
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
