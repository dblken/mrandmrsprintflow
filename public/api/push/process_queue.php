<?php
/**
 * Token-protected web runner for background push queue processing.
 *
 * Recommended for shared hosting web cron jobs.
 * Configure PRINTFLOW_PUSH_QUEUE_TOKEN in environment or server config.
 */

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/push_queue_helper.php';
require_once __DIR__ . '/../../../includes/push_debug_helper.php';

header('Content-Type: application/json');

$configuredToken = '';
if (function_exists('printflow_env')) {
    $configuredToken = (string)(printflow_env('PRINTFLOW_PUSH_QUEUE_TOKEN') ?: '');
}

if ($configuredToken === '') {
    printflow_push_debug_log('push_queue_web_missing_token', []);
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => 'Queue token is not configured',
    ]);
    exit;
}

$providedToken = (string)($_GET['token'] ?? $_POST['token'] ?? '');
if ($providedToken === '' || !hash_equals($configuredToken, $providedToken)) {
    printflow_push_debug_log('push_queue_web_forbidden', []);
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Forbidden',
    ]);
    exit;
}

$summary = printflow_process_push_queue(50);
printflow_push_debug_log('push_queue_web_processed', $summary);

echo json_encode([
    'success' => true,
    'summary' => $summary,
]);
