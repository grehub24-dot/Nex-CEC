<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

$staff_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch staff from database
$stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ? AND status = 'active'");
$stmt->execute([$staff_id]);
$staff = $stmt->fetch();

$settings = fetchSettings($pdo);
$school_name = $settings['school_name'] ?? 'Nex CEC Basic School';

if (!$staff) {
    echo '<div class="container" style="padding: 100px 0; text-align: center;"><h2>Staff Member Not Found</h2><a href="department.php" class="btn-primary">Back to About Us</a></div>';
    require_once 'includes/footer.php';
    exit;
}
?>

<div class="section" style="background: var(--light-bg);">
    <div class="container">
        <a href="department.php" style="display: inline-block; margin-bottom: 20px; color: var(--primary-color); font-weight: bold;">&larr; Back to About Us</a>

        <div class="card" style="display: flex; flex-direction: column; overflow: hidden;">
            <div style="display: flex; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px; max-width: 400px; background: var(--color-primary); display: flex; align-items: center; justify-content: center; padding: 40px;">
                    <div style="width: 150px; height: 150px; border-radius: 50%; background: rgba(255,255,255,0.2); color: white; display: flex; align-items: center; justify-content: center; font-size: 4rem;">
                        <i class="fas fa-user-tie"></i>
                    </div>
                </div>
                <div style="flex: 2; padding: 40px; min-width: 300px;">
                    <h1 style="color: var(--primary-color); margin-bottom: 5px;"><?php echo htmlspecialchars($staff['full_name']); ?></h1>
                    <h3 style="color: var(--secondary-color); margin-bottom: 5px;"><?php echo htmlspecialchars($staff['position'] ?? 'Staff'); ?></h3>
                    <?php if (!empty($staff['department'])): ?>
                        <p style="color: #888; margin-bottom: 20px;"><?php echo htmlspecialchars($staff['department']); ?></p>
                    <?php endif; ?>

                    <div style="margin-bottom: 30px;">
                        <h4 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">Biography</h4>
                        <p style="line-height: 1.8; color: #555;">
                            <?php echo htmlspecialchars($staff['full_name']); ?> is a dedicated member of the <?php echo htmlspecialchars($school_name); ?> team.
                            <?php if (!empty($staff['position'])): ?>
                                Serving as <?php echo htmlspecialchars($staff['position']); ?>,
                            <?php endif; ?>
                            <?php echo htmlspecialchars($staff['full_name']); ?> is committed to providing quality education and supporting the holistic development of every child.
                        </p>
                    </div>

                    <div style="margin-bottom: 30px;">
                        <h4 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">Contact Information</h4>
                        <?php if (!empty($staff['email'])): ?>
                            <p><i class="fas fa-envelope" style="width: 25px; color: var(--primary-color);"></i> <a href="mailto:<?php echo htmlspecialchars($staff['email']); ?>"><?php echo htmlspecialchars($staff['email']); ?></a></p>
                        <?php endif; ?>
                        <?php if (!empty($staff['phone'])): ?>
                            <p><i class="fas fa-phone" style="width: 25px; color: var(--primary-color);"></i> <?php echo htmlspecialchars($staff['phone']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($staff['address'])): ?>
                            <p><i class="fas fa-map-marker-alt" style="width: 25px; color: var(--primary-color);"></i> <?php echo htmlspecialchars($staff['address']); ?></p>
                        <?php endif; ?>
                        <p><i class="fas fa-school" style="width: 25px; color: var(--primary-color);"></i> <?php echo htmlspecialchars($school_name); ?></p>
                    </div>

                    <?php if (!empty($staff['qualification'])): ?>
                    <div>
                        <h4 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">Qualification</h4>
                        <span style="background: #e9ecef; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; color: #495057;"><?php echo htmlspecialchars($staff['qualification']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
