<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

// Fetch Alumni from Database
$stmt = $pdo->query("SELECT * FROM alumni ORDER BY graduation_year DESC");
$alumni = $stmt->fetchAll();
?>

<style>
    .alumni-grid {
        grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
    }
    .alumni-card {
        border-radius: 14px;
        box-shadow: 0 10px 25px rgba(0, 51, 102, 0.12);
    }
    .alumni-card:hover {
        transform: translateY(-8px);
    }
    .alumni-photo-wrap {
        padding: 28px 20px 12px;
        text-align: center;
    }
    .alumni-photo {
        width: 130px;
        height: 130px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid var(--secondary-color);
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
    }
    .alumni-meta {
        display: inline-block;
        margin-top: 10px;
        background: rgba(0, 51, 102, 0.08);
        color: var(--primary-color);
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .alumni-role {
        margin: 12px 0 0;
        line-height: 1.5;
    }
    .alumni-role strong {
        color: var(--primary-color);
    }
    .alumni-quote {
        margin-top: 14px;
        padding: 12px 14px;
        background: #f7f9fc;
        border-left: 3px solid var(--secondary-color);
        border-radius: 8px;
        font-style: italic;
        font-size: 0.92rem;
    }
</style>

<div class="hero" style="height: 50vh;">
    <h1>Alumni Network</h1>
    <p>Connect with former students and industry professionals.</p>
</div>

<div class="section">
    <div class="container">
        <?php if (empty($alumni)): ?>
            <p style="text-align: center;">Our alumni records are being updated. Check back soon!</p>
        <?php else: ?>
            <div class="card-grid alumni-grid">
                <?php foreach ($alumni as $alum): ?>
                <div class="card alumni-card">
                    <div class="alumni-photo-wrap">
                        <img src="<?php echo htmlspecialchars($alum['image_url'] ?: 'images/aamusted.jpg'); ?>" alt="<?php echo htmlspecialchars($alum['full_name']); ?>" class="alumni-photo">
                        <div class="alumni-meta">Class of <?php echo htmlspecialchars((string)$alum['graduation_year']); ?></div>
                    </div>
                    <div class="card-content">
                        <h3 class="card-title"><?php echo htmlspecialchars($alum['full_name']); ?></h3>
                        <p class="alumni-role">
                            <strong><?php echo htmlspecialchars($alum['position'] ?? 'Alumni Member'); ?></strong>
                            <?php if (!empty($alum['company'])): ?>
                                at <?php echo htmlspecialchars($alum['company']); ?>
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($alum['testimonial'])): ?>
                            <p class="alumni-quote">"<?php echo htmlspecialchars($alum['testimonial']); ?>"</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
