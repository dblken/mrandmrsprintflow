<?php
/**
 * Receive lightweight push diagnostics from the service worker or frontend.
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/push_debug_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$eventType = substr(trim((string)($data['event_type'] ?? 'unknown')), 0, 80);
if ($eventType === '') {
    $eventType = 'unknown';
}

$payload = $data['payload'] ?? [];
if (!is_array($payload)) {
    $payload = ['value' => (string)$payload];
}

$userId = function_exists('is_logged_in') && is_logged_in() ? (int)(get_user_id() ?? 0) : 0;
$userType = function_exists('is_logged_in') && is_logged_in() ? (string)(get_user_type() ?? '') : '';
$endpoint = substr((string)($data['endpoint'] ?? ''), 0, 2048);

printflow_push_debug_log($eventType, $payload, $userId, $userType, $endpoint);

echo json_encode(['success' => true]);
