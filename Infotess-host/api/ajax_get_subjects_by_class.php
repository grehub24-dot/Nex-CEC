<?php
/**
 * AJAX endpoint: Returns subjects filtered by the selected class's educational category.
 * 
 * GET /ajax_get_subjects_by_class.php?class_id=5
 * 
 * Uses the subject-to-category mapping stored in system_settings (set via admin_subjects.php).
 * Returns JSON: [{"id": 1, "name": "English Language", "code": "ENG", ...}, ...]
 */

require_once 'includes/db.php';

// Only allow GET requests with a valid class_id
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
if ($class_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

// Map class names to educational categories (must match admin_subjects.php)
$class_category_map = [
    'Creche'  => 'creche',
    'Nursery 1' => 'nursery',
    'Nursery 2' => 'nursery',
    'KG 1'    => 'kindergarten',
    'KG 2'    => 'kindergarten',
    'Basic 1' => 'primary', 'Basic 2' => 'primary', 'Basic 3' => 'primary',
    'Basic 4' => 'primary', 'Basic 5' => 'primary', 'Basic 6' => 'primary',
    'JHS 1'   => 'jhs',     'JHS 2'   => 'jhs',     'JHS 3'   => 'jhs',
];

// Get the class name
$class_name = '';
try {
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([$class_id]);
    $classRow = $stmt->fetch();
    $class_name = $classRow ? ($classRow['name'] ?? '') : '';
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

if (!$class_name) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

// Load subject-to-category mapping
$subject_category_mapping = [];
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute(['subject_categories']);
    $row = $stmt->fetch();
    if ($row && !empty($row['setting_value'])) {
        $decoded = json_decode($row['setting_value'], true);
        if (is_array($decoded)) {
            $subject_category_mapping = $decoded;
        }
    }
} catch (Exception $e) {}

// Get all subjects
$all_subjects = [];
try {
    $result = $pdo->query("SELECT * FROM subjects");
    $all_subjects = $result ? $result->fetchAll() : [];
} catch (Exception $e) {}

// Filter by category
$filtered = [];
$category_key = $class_category_map[$class_name] ?? null;

if ($category_key && isset($subject_category_mapping[$category_key]) && !empty($subject_category_mapping[$category_key])) {
    $allowed_ids = array_map('intval', $subject_category_mapping[$category_key]);
    foreach ($all_subjects as $s) {
        if (in_array((int)$s['id'], $allowed_ids)) {
            $filtered[] = $s;
        }
    }
}

// Sort by name
usort($filtered, function($a, $b) {
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

// Return JSON
header('Content-Type: application/json');
echo json_encode(array_values($filtered));
