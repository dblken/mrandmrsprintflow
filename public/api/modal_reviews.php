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
$aliases = printflow_service_name_aliases($service_name);
$schema = printflow_review_schema();

if ($schema['service_col'] === '' || empty($aliases)) {
    echo json_encode(['reviews' => [], 'avg' => 0, 'count' => 0]);
    exit;
}

$placeholders = implode(',', array_fill(0, count($aliases), '?'));
$types = str_repeat('s', count($aliases));
$message_col = $schema['message_col'];
$user_col = $schema['user_col'];
$created_col = $schema['created_col'] !== '' ? $schema['created_col'] : 'NOW()';

$rows = db_query(
    "SELECT r.rating,
            r.{$message_col} AS message,
            {$created_col} AS created_at,
            COALESCE(c.first_name, u.first_name, 'Customer') AS first_name,
            COALESCE(c.last_name, u.last_name, '') AS last_name
     FROM reviews r
     LEFT JOIN customers c ON c.customer_id = r.{$user_col}
     LEFT JOIN users u ON u.user_id = r.{$user_col}
     WHERE r.{$schema['service_col']} COLLATE utf8mb4_unicode_ci IN ($placeholders)
     ORDER BY {$created_col} DESC
     LIMIT 5",
    $types,
    $aliases
) ?: [];

$stats = printflow_get_service_review_stats($service_name);

echo json_encode([
    'reviews' => $rows,
    'avg' => round((float)($stats['avg_rating'] ?? 0), 1),
    'count' => (int)($stats['review_count'] ?? 0),
]);
