<?php
/**
 * Token-protected web runner for background push queue processing.
 *
 * Recommended for shared hosting web cron jobs.
 * Configure PRINTFLOW_PUSH_QUEUE_TOKEN in environment or server config.
 */

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/push_queue_helper.php';

header('Content-Type: application/json');

$configuredToken = '';
if (function_exists('printflow_env')) {
    $configuredToken = (string)(printflow_env('PRINTFLOW_PUSH_QUEUE_TOKEN') ?: '');
}

if ($configuredToken === '') {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => 'Queue token is not configured',
    ]);
    exit;
}

$providedToken = (string)($_GET['token'] ?? $_POST['token'] ?? '');
if ($providedToken === '' || !hash_equals($configuredToken, $providedToken)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Forbidden',
    ]);
    exit;
}

$summary = printflow_process_push_queue(50);

echo json_encode([
    'success' => true,
    'summary' => $summary,
]);
