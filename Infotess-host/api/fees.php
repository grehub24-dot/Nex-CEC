<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';
$current_year = $settings['current_academic_year'] ?? date('Y') . '/' . (date('Y') + 1);
$current_term = $settings['current_term'] ?? '1';

// Fetch fee structure
$fees = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM fee_structures WHERE academic_year = ? AND term = ? ORDER BY is_mandatory DESC, title ASC");
    $stmt->execute([$current_year, $current_term]);
    $fees = $stmt->fetchAll();
} catch (Exception $e) {}

$total_fees = 0;
$mandatory_total = 0;
foreach ($fees as $f) {
    $total_fees += $f['amount'];
    if ($f['is_mandatory']) $mandatory_total += $f['amount'];
}
?>

<div class="hero" style="height: 40vh; background: linear-gradient(rgba(26,82,118,0.85), rgba(46,134,193,0.85));">
    <h1>Fees & Payment Information</h1>
    <p>Official fee schedule for <?php echo htmlspecialchars($school_name); ?></p>
</div>

<div class="section">
    <div class="container">
        
        <!-- Fee Breakdown -->
        <h2 class="section-title" style="text-align: left;">Fee Structure — <?php echo htmlspecialchars($current_year); ?> Term <?php echo htmlspecialchars($current_term); ?></h2>
        
        <?php if (!empty($fees)): ?>
            <div class="table-responsive">
                <table class="table" style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                    <thead>
                        <tr style="background: var(--primary-color); color: white;">
                            <th style="padding: 15px; text-align: left;">Fee Type</th>
                            <th style="padding: 15px; text-align: right;">Amount (GHS)</th>
                            <th style="padding: 15px; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fees as $fee): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 15px;">
                                <strong><?php echo htmlspecialchars($fee['title']); ?></strong>
                                <?php if ($fee['is_mandatory']): ?>
                                    <span style="background: #e74c3c; color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 3px; margin-left: 8px;">Required</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px; text-align: right; font-weight: bold;">GHS <?php echo number_format($fee['amount'], 2); ?></td>
                            <td style="padding: 15px; text-align: center;"><?php echo $fee['is_mandatory'] ? 'Mandatory' : 'Optional'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background: #f8f9fa; font-weight: bold;">
                            <td style="padding: 15px;">Total Fees</td>
                            <td style="padding: 15px; text-align: right;">GHS <?php echo number_format($total_fees, 2); ?></td>
                            <td style="padding: 15px; text-align: center;"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info" style="background: #e3f2fd; padding: 20px; border-radius: 5px; margin-bottom: 30px;">
                <i class="fas fa-info-circle"></i> Fee structure for this term will be published soon. Contact the school office for details.
            </div>
        <?php endif; ?>

        <!-- Payment Methods -->
        <h3 class="section-title" style="text-align: left; margin-top: 40px;">How to Pay</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            <div class="card">
                <div class="card-content">
                    <h3><i class="fas fa-building" style="color: var(--primary-color);"></i> School Finance Office</h3>
                    <p>Pay cash or mobile money directly at the school finance office during office hours (Mon-Fri, 7:30 AM - 4:00 PM).</p>
                </div>
            </div>
            <div class="card">
                <div class="card-content">
                    <h3><i class="fas fa-mobile-alt" style="color: var(--primary-color);"></i> Mobile Money</h3>
                    <p>Send payment via MTN MoMo, Vodafone Cash, or AirtelTigo Money. Use your child's <strong>Index Number</strong> as the payment reference.</p>
                </div>
            </div>
            <div class="card">
                <div class="card-content">
                    <h3><i class="fas fa-building" style="color: var(--primary-color);"></i> Bank Transfer</h3>
                    <p>Transfer to the school's bank account. Contact the finance office for account details. Use the student's Index Number as reference.</p>
                </div>
            </div>
        </div>

        <!-- Important Notes -->
        <div style="background: #fff3cd; color: #856404; padding: 20px; border-radius: 8px; border: 1px solid #ffeeba; margin-top: 30px;">
            <strong><i class="fas fa-exclamation-triangle"></i> Important:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <li>All students must be enrolled and have a valid Index Number before making payments.</li>
                <li>Always provide the student's <strong>Index Number</strong> as payment reference.</li>
                <li>Receipts will be issued and sent to the registered email address.</li>
                <li>Contact the finance office for any questions or payment-related issues.</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="enroll.php" class="btn-primary" style="display: inline-block; margin-right: 10px;">Enroll Now</a>
            <a href="contact.php" class="btn-primary" style="display: inline-block; background: transparent; color: var(--primary-color); border: 2px solid var(--primary-color);">Contact Us</a>
        </div>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
