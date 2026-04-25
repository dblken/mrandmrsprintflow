<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$service_id = (int)($_GET['service_id'] ?? 0);
if ($service_id <= 0) {
    echo json_encode(['reviews' => [], 'avg' => 0, 'count' => 0]);
    exit;
}

$service_rows = db_query("SELECT name FROM services WHERE service_id = ? LIMIT 1", 'i', [$service_id]) ?: [];
if (empty($service_rows)) {
    echo json_encode(['reviews' => [], 'avg' => 0, 'count' => 0]);
    exit;
}

$service_name = (string)$service_rows[0]['name'];
$rows = printflow_get_service_reviews($service_name, 5);
$rows = array_map(static function ($row) {
    return [
        'rating' => $row['rating'] ?? 0,
        'message' => $row['comment'] ?? ($row['review_message'] ?? ''),
        'created_at' => $row['created_at'] ?? '',
        'first_name' => $row['first_name'] ?? 'Customer',
        'last_name' => $row['last_name'] ?? '',
    ];
}, $rows);

$stats = printflow_get_service_review_stats($service_name);

echo json_encode([
    'reviews' => $rows,
    'avg' => round((float)($stats['avg_rating'] ?? 0), 1),
    'count' => (int)($stats['review_count'] ?? 0),
]);
