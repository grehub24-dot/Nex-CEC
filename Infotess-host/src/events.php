<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

$stmt = $pdo->query("SELECT * FROM events ORDER BY event_date DESC");
$events = $stmt->fetchAll();
?>

<div class="hero" style="height: 40vh; background: linear-gradient(rgba(0,51,102,0.8), rgba(0,51,102,0.8)), url('images/aamusted.jpg') center/cover;">
    <h1>Upcoming Events</h1>
    <p>Join us for our upcoming academic and social gatherings</p>
</div>

<div class="section">
    <div class="container">
        <h2 class="section-title">Calendar of Events</h2>
        
        <?php if (empty($events)): ?>
            <div class="card" style="padding: 25px; text-align: center;">
                <p>No events are available yet.</p>
            </div>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($events as $event): ?>
                    <div class="card">
                        <div class="card-content">
                            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                <div style="background: var(--secondary-color); color: var(--primary-color); padding: 10px; border-radius: 5px; text-align: center; margin-right: 15px; min-width: 60px;">
                                    <div style="font-size: 1.5rem; font-weight: bold;"><?php echo date('d', strtotime((string)$event['event_date'])); ?></div>
                                    <div style="font-size: 0.8rem; text-transform: uppercase;"><?php echo date('M', strtotime((string)$event['event_date'])); ?></div>
                                </div>
                                <div>
                                    <h3 style="margin: 0; font-size: 1.1rem; color: var(--primary-color);"><?php echo htmlspecialchars($event['title']); ?></h3>
                                    <span style="font-size: 0.9rem; color: #666;"><i class="fas fa-clock"></i> <?php echo date('l, Y', strtotime((string)$event['event_date'])); ?></span>
                                </div>
                            </div>

                            <p style="margin-bottom: 10px;"><strong><i class="fas fa-map-marker-alt" style="color: var(--secondary-color);"></i> Location:</strong> <?php echo htmlspecialchars($event['location'] ?? 'TBA'); ?></p>
                            <p style="color: #555; line-height: 1.6; margin-bottom: 15px;"><?php echo htmlspecialchars((string)($event['description'] ?? '')); ?></p>
                            <?php if (!empty($event['source_url'])): ?>
                                <a href="<?php echo htmlspecialchars($event['source_url']); ?>" target="_blank" style="display: inline-block; color: var(--secondary-color); font-weight: bold;">View Event Details &rarr;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="https://aamusted.edu.gh/" target="_blank" class="btn-primary">View Official AAMUSTED Site</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
