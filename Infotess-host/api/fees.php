<?php
require_once 'includes/header.php';

// Fetch fee structure
$fees = [];
try {
    $result = $pdo->query("SELECT id, class_name, term, amount, academic_year, description FROM fee_structure ORDER BY academic_year DESC, class_name ASC");
    if ($result && $result->rowCount() > 0) {
        $fees = $result->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Table may not exist
}

// Group by class
$grouped_fees = [];
foreach ($fees as $f) {
    $cls = $f['class_name'] ?? 'General';
    if (!isset($grouped_fees[$cls])) {
        $grouped_fees[$cls] = [];
    }
    $grouped_fees[$cls][] = $f;
}
?>

<!-- Hero Inner -->
<section class="hero-inner" style="background: linear-gradient(135deg, #002244 0%, #003366 50%, #004080 100%);">
    <div class="container" style="text-align: center; position: relative; z-index: 2;">
        <span class="badge-pill badge-gold" style="margin-bottom: 16px;">Transparent Pricing</span>
        <h1 style="font-size: 2.8rem; color: #fff; margin-bottom: 12px;">Fee Structure</h1>
        <p style="color: rgba(255,255,255,0.8); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">At Chariot Educational Complex, we believe in transparency. View our detailed fee structure for the current academic year below.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (!empty($grouped_fees)): ?>
        <?php foreach ($grouped_fees as $class => $entries): ?>
        <div class="animate-on-scroll" style="margin-bottom: 36px;">
            <h2 style="color: #003366; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size:1.3rem;">🎓</span> <?php echo htmlspecialchars($class); ?>
            </h2>
            <div style="overflow-x:auto;border-radius:16px;box-shadow:0 4px 20px rgba(0,51,102,0.06);border:1px solid #f0f0f0;">
                <table style="width:100%;border-collapse:collapse;background:#fff;">
                    <thead>
                        <tr style="background:linear-gradient(135deg,#003366,#004080);color:#ffcc00;">
                            <th style="padding:14px 20px;text-align:left;font-size:0.85rem;">Term</th>
                            <th style="padding:14px 20px;text-align:left;font-size:0.85rem;">Amount (GHS)</th>
                            <th style="padding:14px 20px;text-align:left;font-size:0.85rem;">Academic Year</th>
                            <?php if (!empty($entries[0]['description'])): ?>
                            <th style="padding:14px 20px;text-align:left;font-size:0.85rem;">Details</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $fee): ?>
                        <tr style="border-bottom:1px solid #f0f0f0;transition:background 0.2s;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background=''">
                            <td style="padding:14px 20px;font-weight:600;color:#003366;"><?php echo htmlspecialchars($fee['term']); ?></td>
                            <td style="padding:14px 20px;">
                                <strong style="color:#e63946;font-size:1.1rem;">₵<?php echo number_format($fee['amount'], 2); ?></strong>
                            </td>
                            <td style="padding:14px 20px;color:#666;"><?php echo htmlspecialchars($fee['academic_year']); ?></td>
                            <?php if (!empty($fee['description'])): ?>
                            <td style="padding:14px 20px;color:#888;font-size:0.85rem;"><?php echo htmlspecialchars($fee['description']); ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <!-- Default Fee Structure -->
        <div class="animate-on-scroll" style="margin-bottom: 36px;">
            <div style="text-align: center; margin-bottom: 32px;">
                <div style="font-size: 3rem; margin-bottom: 12px; opacity: 0.3;">💰</div>
                <h3 style="color: #003366; margin-bottom: 8px;">Fee Structure for 2024/2025 Academic Year</h3>
                <p style="color: #888; max-width: 500px; margin: 0 auto;">Below is the indicative fee structure. For the most current fees, please contact the school administration.</p>
            </div>

            <?php
            $sample_classes = [
                'Nursery 1 & 2' => ['1st Term' => 450.00, '2nd Term' => 400.00, '3rd Term' => 400.00],
                'Kindergarten 1 & 2' => ['1st Term' => 500.00, '2nd Term' => 450.00, '3rd Term' => 450.00],
                'Class 1 - 3' => ['1st Term' => 550.00, '2nd Term' => 500.00, '3rd Term' => 500.00],
                'Class 4 - 6' => ['1st Term' => 600.00, '2nd Term' => 550.00, '3rd Term' => 550.00],
            ];
            $academic_year = '2024/2025';
            ?>

            <?php foreach ($sample_classes as $class => $terms): ?>
            <div class="animate-on-scroll" style="margin-bottom: 24px;">
                <h3 style="color: #003366; margin-bottom: 12px; font-size: 1.1rem;"><?php echo htmlspecialchars($class); ?></h3>
                <div style="overflow-x:auto;border-radius:12px;box-shadow:0 4px 20px rgba(0,51,102,0.06);border:1px solid #f0f0f0;">
                    <table style="width:100%;border-collapse:collapse;background:#fff;">
                        <thead>
                            <tr style="background:linear-gradient(135deg,#003366,#004080);color:#ffcc00;">
                                <th style="padding:12px 20px;text-align:left;font-size:0.85rem;">Term</th>
                                <th style="padding:12px 20px;text-align:left;font-size:0.85rem;">Amount (GHS)</th>
                                <th style="padding:12px 20px;text-align:left;font-size:0.85rem;">Academic Year</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($terms as $term => $amount): ?>
                            <tr style="border-bottom:1px solid #f0f0f0;">
                                <td style="padding:12px 20px;font-weight:600;color:#003366;"><?php echo $term; ?></td>
                                <td style="padding:12px 20px;"><strong style="color:#e63946;">₵<?php echo number_format($amount, 2); ?></strong></td>
                                <td style="padding:12px 20px;color:#666;"><?php echo $academic_year; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Payment Policy -->
        <div class="animate-on-scroll" style="background:#f8f9fa;border-radius:16px;padding:32px;margin-top:24px;border:1px solid rgba(255,204,0,0.15);">
            <h3 style="color: #003366; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                <span>📋</span> Payment Policy
            </h3>
            <ul style="padding-left: 20px; font-size: 0.9rem; color: #555; line-height: 2;">
                <li>Fees are payable in full at the beginning of each term.</li>
                <li>Payment can be made via mobile money, bank transfer, or cash at the school office.</li>
                <li>A receipt will be issued for every payment made.</li>
                <li>For siblings, a discount may apply — please inquire at the admin office.</li>
                <li>Fee payment deadlines are communicated at the start of each term.</li>
                <li>For any inquiries regarding fees, please contact the school administration.</li>
            </ul>
        </div>
    </div>
</section>

<script>
(function() {
    var els = document.querySelectorAll('.animate-on-scroll');
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    els.forEach(function(el) { observer.observe(el); });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
