<?php
require_once 'includes/db.php';

$receipt_number = $_GET['receipt'] ?? '';
$status = 'invalid';
$data = null;

if ($receipt_number) {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE receipt_number = ?");
    $stmt->execute([$receipt_number]);
    $data = $stmt->fetch();
    
    if ($data) {
        // Enrich with student data (two-step lookup for Supabase compatibility)
        $s = $pdo->prepare("SELECT full_name, admission_number, class_name FROM students WHERE id = ?");
        $s->execute([$data['student_id']]);
        $stu = $s->fetch();
        if ($stu) {
            $data['full_name'] = $stu['full_name'];
            $data['admission_number'] = $stu['admission_number'];
            $data['class_name'] = $stu['class_name'] ?? '-';
        }
        $status = 'valid';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Receipt — School Portal</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .verify-box {
            max-width: 500px;
            margin: 50px auto;
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .icon-valid { color: green; font-size: 4rem; margin-bottom: 20px; }
        .icon-invalid { color: red; font-size: 4rem; margin-bottom: 20px; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php require_once 'includes/header.php'; ?>

    <div class="verify-box">
        <?php if ($status === 'valid'): ?>
            <i class="fas fa-check-circle icon-valid"></i>
            <h2>Valid Receipt</h2>
            <p>The receipt <strong><?php echo htmlspecialchars($receipt_number); ?></strong> is authentic.</p>
            <div style="text-align: left; margin-top: 20px; background: #f9f9f9; padding: 15px; border-radius: 5px;">
                <p><strong>Student:</strong> <?php echo htmlspecialchars($data['full_name']); ?></p>
                <p><strong>Index No:</strong> <?php echo htmlspecialchars($data['admission_number']); ?></p>
                <p><strong>Class:</strong> <?php echo htmlspecialchars($data['class_name'] ?? '-'); ?></p>
                <p><strong>Amount:</strong> GHS <?php echo number_format($data['amount'], 2); ?></p>
                <p><strong>Date:</strong> <?php echo htmlspecialchars($data['payment_date']); ?></p>
            </div>
        <?php else: ?>
            <i class="fas fa-times-circle icon-invalid"></i>
            <h2>Invalid Receipt</h2>
            <p>The receipt number provided could not be verified in our system.</p>
        <?php endif; ?>
    </div>

    <?php require_once 'includes/footer.php'; ?>
</body>
</html>
