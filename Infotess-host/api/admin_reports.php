<?php
require_once 'includes/db.php';

// Enforce access control
requireAccess('reports');

// Fetch Settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

// Generate Report Logic
$report_type = $_GET['type'] ?? '';
$data = [];
$headers = [];

if ($report_type) {
    if ($report_type === 'payments_per_class') {
        // Two-step lookup for Supabase: fetch payments, then enrich with student class
        $all_payments = $pdo->query("SELECT student_id, amount FROM payments")->fetchAll();
        $class_map = [];
        $seen_students = [];
        foreach ($all_payments as $p) {
            if (!isset($seen_students[$p['student_id']])) {
                $s = $pdo->prepare("SELECT class_name FROM students WHERE id = ?");
                $s->execute([$p['student_id']]);
                $stu = $s->fetch();
                $seen_students[$p['student_id']] = $stu ? ($stu['class_name'] ?: 'Unknown') : 'Unknown';
            }
            $class_name = $seen_students[$p['student_id']];
            if (!isset($class_map[$class_name])) {
                $class_map[$class_name] = ['class' => $class_name, 'payment_count' => 0, 'total_amount' => 0];
            }
            $class_map[$class_name]['payment_count']++;
            $class_map[$class_name]['total_amount'] += (float)$p['amount'];
        }
        $data = array_values($class_map);
        $headers = ['Class', 'Payment Count', 'Total Amount'];
    } elseif ($report_type === 'payments_per_year') {
        // Bridge doesn't support COUNT/SUM/GROUP BY — fetch all, group & count in PHP
        $all_payments_for_report = $pdo->query("SELECT * FROM payments");
        $all_payments_for_report = $all_payments_for_report ? $all_payments_for_report->fetchAll() : [];
        $year_map = [];
        foreach ($all_payments_for_report as $p) {
            $yr = $p['academic_year'] ?? 'Unknown';
            if (!isset($year_map[$yr])) {
                $year_map[$yr] = ['academic_year' => $yr, 'payment_count' => 0, 'total_amount' => 0];
            }
            $year_map[$yr]['payment_count']++;
            $year_map[$yr]['total_amount'] += (float)($p['amount'] ?? 0);
        }
        $data = array_values($year_map);
        $headers = ['Academic Year', 'Payment Count', 'Total Amount'];
    } elseif ($report_type === 'payments_per_term') {
        // Bridge doesn't support COUNT/SUM/GROUP BY — fetch all, group & count in PHP
        $all_payments_for_report = $pdo->query("SELECT * FROM payments");
        $all_payments_for_report = $all_payments_for_report ? $all_payments_for_report->fetchAll() : [];
        $term_map = [];
        foreach ($all_payments_for_report as $p) {
            $sem = $p['semester'] ?? 'Unknown';
            if (!isset($term_map[$sem])) {
                $term_map[$sem] = ['semester' => $sem, 'payment_count' => 0, 'total_amount' => 0];
            }
            $term_map[$sem]['payment_count']++;
            $term_map[$sem]['total_amount'] += (float)($p['amount'] ?? 0);
        }
        $data = array_values($term_map);
        $headers = ['Term', 'Payment Count', 'Total Amount'];
    }
}

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $data) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . $report_type . '_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
            <?php echo renderSidebar('reports', $school_name); ?>

        <main class="main-content">
            <div class="top-bar">
                <h2>System Reports</h2>
            </div>

            <div class="report-filters card">
                <h3>Generate Report</h3>
                <form method="GET" action="">
                    <select name="type" required style="padding: 10px; margin-right: 10px;">
                        <option value="">Select Report Type</option>
                        <option value="payments_per_class" <?php echo $report_type == 'payments_per_class' ? 'selected' : ''; ?>>Payments per Class</option>
                        <option value="payments_per_year" <?php echo $report_type == 'payments_per_year' ? 'selected' : ''; ?>>Payments per Academic Year</option>
                        <option value="payments_per_term" <?php echo $report_type == 'payments_per_term' ? 'selected' : ''; ?>>Payments per Term</option>
                    </select>
                    <button type="submit" class="btn-admin-action"><i class="fas fa-chart-line"></i> View Report</button>
                </form>
            </div>

            <?php if ($data): ?>
                <div class="card" style="margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3>Report Results</h3>
                        <div>
                            <a href="?type=<?php echo $report_type; ?>&export=csv" class="btn-primary" style="background: #28a745;">Export CSV</a>
                            <button onclick="window.print()" class="btn-primary" style="background: #17a2b8;">Print PDF</button>
                        </div>
                    </div>
                    
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f4f4f4; text-align: left;">
                                <?php foreach ($headers as $header): ?>
                                    <th style="padding: 10px; border-bottom: 2px solid #ddd;"><?php echo $header; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <?php foreach ($row as $cell): ?>
                                        <td style="padding: 10px; border-bottom: 1px solid #ddd;"><?php echo htmlspecialchars($cell); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Chart -->
                <div class="card" style="margin-top: 20px;">
                    <canvas id="reportChart"></canvas>
                </div>
                
                <script>
                    const ctx = document.getElementById('reportChart').getContext('2d');
                    const data = <?php echo json_encode($data); ?>;
                    const labels = data.map(item => Object.values(item)[0]);
                    const values = data.map(item => Object.values(item)[2]); // Total Amount

                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Total Amount (GHS)',
                                data: values,
                                backgroundColor: 'rgba(0, 51, 102, 0.6)',
                                borderColor: 'rgba(0, 51, 102, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });
                </script>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
