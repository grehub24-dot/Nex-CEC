<?php
/**
 * AJAX endpoint: returns lesson note data as JSON for the edit modal.
 * GET /ajax_get_lesson_note.php?id=X
 */
require_once 'includes/db.php';

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, title, content, key_concepts, subject_id, class_id, sort_order, is_active FROM lesson_notes WHERE id = ?");
    $stmt->execute([$id]);
    $note = $stmt->fetch();

    if (!$note) {
        echo json_encode(['error' => 'Not found']);
        exit;
    }

    // Get attached resource IDs
    $res_stmt = $pdo->prepare("SELECT resource_link_id FROM lesson_note_resources WHERE lesson_note_id = ? ORDER BY sort_order");
    $res_stmt->execute([$id]);
    $resource_ids = [];
    while ($row = $res_stmt->fetch()) {
        $resource_ids[] = (int)$row['resource_link_id'];
    }

    $note['resource_ids'] = $resource_ids;
    echo json_encode($note);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
