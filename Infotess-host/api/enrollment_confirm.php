<?php
require_once 'includes/db.php';

$ref = isset($_GET['ref']) ? trim($_GET['ref']) : '';
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

if (empty($ref)) {
    header('Location: register.php');
    exit;
}

// Fetch the enrollment to confirm it exists
$stmt = $pdo->prepare("SELECT * FROM students WHERE enrollment_id = ?");
$stmt->execute([$ref]);
$student = $stmt->fetch();

if (!$student) {
    die("Enrollment not found. Please check your reference number.");
}

// Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';
$school_address = $settings['school_address'] ?? 'School Address, City, Ghana';
$school_phone = $settings['school_phone'] ?? '+233 XX XXX XXXX';

require_once 'includes/header.php';
?>

<style>
    .confirm-container {
        max-width: 800px;
        margin: 40px auto;
        padding: 0 20px;
    }
    .success-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        padding: 40px;
        text-align: center;
    }
    .success-icon {
        width: 80px;
        height: 80px;
        background: #27ae60;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
    }
    .success-icon i {
        font-size: 40px;
        color: white;
    }
    .success-card h2 {
        color: #1a5276;
        font-size: 24px;
        margin-bottom: 10px;
    }
    .success-card .subtitle {
        color: #666;
        font-size: 15px;
        margin-bottom: 25px;
    }
    .ref-display {
        background: #f0f7ff;
        border: 2px dashed #1a5276;
        border-radius: 8px;
        padding: 20px;
        margin: 20px 0;
    }
    .ref-display .label {
        font-size: 13px;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .ref-display .ref-number {
        font-size: 28px;
        font-weight: bold;
        color: #1a5276;
        letter-spacing: 3px;
        margin: 5px 0;
    }
    .ref-display .note {
        font-size: 13px;
        color: #888;
    }
    .download-links {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin: 30px 0;
    }
    .download-card {
        background: #f8f9fa;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        transition: all 0.2s;
        text-decoration: none;
        color: #333;
    }
    .download-card:hover {
        border-color: #1a5276;
        background: #f0f7ff;
        transform: translateY(-2px);
    }
    .download-card i {
        font-size: 32px;
        color: #1a5276;
        margin-bottom: 10px;
    }
    .download-card h4 {
        font-size: 14px;
        margin-bottom: 5px;
    }
    .download-card p {
        font-size: 12px;
        color: #888;
    }
    .what-next {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 25px;
        text-align: left;
        margin-top: 25px;
    }
    .what-next h3 {
        color: #1a5276;
        font-size: 16px;
        margin-bottom: 15px;
    }
    .what-next ol {
        padding-left: 20px;
        line-height: 2;
        font-size: 14px;
        color: #555;
    }
    .what-next ol li strong {
        color: #333;
    }
    .btn-primary {
        display: inline-block;
        padding: 12px 30px;
        background: #1a5276;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        font-weight: bold;
        font-size: 15px;
        margin-top: 20px;
    }
    .btn-primary:hover {
        background: #143c58;
    }
    @media (max-width: 600px) {
        .download-links {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="confirm-container">
    <div class="success-card">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        <h2>Enrollment Submitted Successfully!</h2>
        <p class="subtitle">
            Thank you for submitting the enrollment form for 
            <strong><?php echo htmlspecialchars($student['full_name'] ?? ''); ?></strong> 
            at <?php echo htmlspecialchars($school_name); ?>.
        </p>

        <div class="ref-display">
            <div class="label">Your Enrollment Reference Number</div>
            <div class="ref-number"><?php echo htmlspecialchars($ref); ?></div>
            <div class="note">Save this number — you'll need it for all future correspondence</div>
        </div>

        <h3 style="margin-top: 20px; color: #1a5276;">Download Documents</h3>
        <div class="download-links">
            <a href="enrollment_print.php?ref=<?php echo urlencode($ref); ?>" class="download-card" target="_blank">
                <i class="fas fa-file-alt"></i>
                <h4>Filled Enrollment Form</h4>
                <p>Print and bring to school</p>
            </a>
            <a href="enrollment_bill.php?ref=<?php echo urlencode($ref); ?>" class="download-card" target="_blank">
                <i class="fas fa-file-invoice"></i>
                <h4>Fee Bill</h4>
                <p>Shows admission + prospectus fees</p>
            </a>
            <a href="enrollment_blank_form.php" class="download-card" target="_blank">
                <i class="fas fa-file"></i>
                <h4>Blank Enrollment Form</h4>
                <p>Fill by hand if preferred</p>
            </a>
            <a href="javascript:void(0)" class="download-card" onclick="window.print()">
                <i class="fas fa-print"></i>
                <h4>Print This Page</h4>
                <p>Save confirmation details</p>
            </a>
        </div>

        <div class="what-next">
            <h3><i class="fas fa-clock"></i> What Happens Next?</h3>
            <ol>
                <li><strong>Download the fee bill</strong> from the links above.</li>
                <li><strong>Bring the bill</strong> to the school's finance office to make payment.</li>
                <li><strong>Payment methods:</strong> Cash, Mobile Money, or Bank Transfer at the school.</li>
                <li><strong>After payment,</strong> the school will complete the enrollment and assign an admission number.</li>
                <li><strong>You will receive</strong> portal login credentials via SMS and email to access your child's information.</li>
            </ol>
        </div>

        <?php if (!empty($email)): ?>
            <div style="margin-top: 20px; padding: 15px; background: #e8f4f8; border-radius: 6px; font-size: 14px; color: #1a5276;">
                <i class="fas fa-envelope"></i> 
                A confirmation email has been sent to <strong><?php echo htmlspecialchars($email); ?></strong> 
                with the fee bill and enrollment details.
            </div>
        <?php endif; ?>

        <div style="margin-top: 25px;">
            <p style="font-size: 13px; color: #888; margin-bottom: 15px;">
                For any enquiries, please contact the school administration:
            </p>
            <p style="font-size: 14px; color: #555;">
                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($school_address); ?><br>
                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($school_phone); ?>
            </p>
            <a href="index.php" class="btn-primary"><i class="fas fa-home"></i> Return to Homepage</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
