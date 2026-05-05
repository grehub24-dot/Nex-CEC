<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Method not allowed'], 405);
}

$actor = require_student();
$studentId = (int)$actor['student_id'];

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$limit = max(1, min(200, $limit));

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT payment_id, amount, academic_year, semester, payment_date, payment_method, receipt_number, created_at\n'
        . 'FROM payments\n'
        . 'WHERE student_id = ?\n'
        . 'ORDER BY payment_id DESC\n'
        . 'LIMIT ?'
    );
    $stmt->execute([$studentId, $limit]);

    $rows = $stmt->fetchAll();
    json_response(['ok' => true, 'payments' => $rows]);
} catch (Throwable $e) {
    json_response(['error' => 'Failed to fetch payments'], 500);
}
