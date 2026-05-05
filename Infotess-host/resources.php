<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

$stmt = $pdo->query("SELECT * FROM student_resources ORDER BY created_at DESC");
$resources = $stmt->fetchAll();
?>

<div class="hero" style="height: 50vh;">
    <h1>Student Resources</h1>
    <p>Access verified AAMUSTED portals, academic tools, and downloadable materials.</p>
</div>

<div class="section">
    <div class="container">
        <?php if (empty($resources)): ?>
            <div class="card" style="text-align: center; padding: 40px;">
                <i class="fas fa-folder-open" style="font-size: 3rem; color: #ccc; margin-bottom: 20px;"></i>
                <p>No resource is available yet.</p>
            </div>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($resources as $res): ?>
                    <div class="card">
                        <div class="card-content" style="display: flex; align-items: center; gap: 20px;">
                            <div style="font-size: 2.5rem; color: var(--primary-color);">
                                <i class="fas <?php echo strpos(strtolower((string)$res['resource_type']), 'pdf') !== false ? 'fa-file-pdf' : (strpos(strtolower((string)$res['resource_type']), 'doc') !== false ? 'fa-file-word' : (strpos(strtolower((string)$res['resource_type']), 'lms') !== false ? 'fa-graduation-cap' : 'fa-link')); ?>"></i>
                            </div>
                            <div style="flex: 1;">
                                <h3 class="card-title" style="margin-bottom: 5px;"><?php echo htmlspecialchars($res['title']); ?></h3>
                                <p style="font-size: 0.9rem; margin-bottom: 10px;"><?php echo htmlspecialchars($res['description']); ?></p>
                                <?php if (preg_match('/^https?:\/\//i', (string)$res['file_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($res['file_url']); ?>" class="btn-login" style="padding: 5px 15px; font-size: 0.8rem; display: inline-block;" target="_blank" rel="noopener noreferrer">
                                        <i class="fas fa-arrow-up-right-from-square"></i> Open <?php echo htmlspecialchars($res['resource_type']); ?>
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo htmlspecialchars($res['file_url']); ?>" class="btn-login" style="padding: 5px 15px; font-size: 0.8rem; display: inline-block;" download>
                                        <i class="fas fa-download"></i> Download <?php echo htmlspecialchars($res['resource_type']); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
