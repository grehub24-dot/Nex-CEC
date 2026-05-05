<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

// Fetch Gallery from Database
$stmt = $pdo->query("SELECT * FROM gallery ORDER BY created_at DESC");
$gallery = $stmt->fetchAll();
?>

<div class="hero" style="height: 50vh;">
    <h1>Gallery</h1>
    <p>Photos from our recent events and student life.</p>
</div>

<div class="section">
    <div class="container">
        <?php if (empty($gallery)): ?>
            <div class="card" style="text-align: center; padding: 40px;">
                <i class="fas fa-images" style="font-size: 3rem; color: #ccc; margin-bottom: 20px;"></i>
                <p>No gallery items are available yet.</p>
            </div>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($gallery as $item): ?>
                <div class="card gallery-item">
                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" style="width: 100%; height: 250px; object-fit: cover; border-radius: 8px 8px 0 0;">
                    <div class="card-content">
                        <h4 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h4>
                        <p><small><?php echo htmlspecialchars($item['category']); ?></small></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
