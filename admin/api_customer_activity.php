<?php
/**
 * Admin Customer Activity API
 * PrintFlow - Printing Shop PWA
 * Returns customer activity logs as JSON
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'Manager']);

header('Content-Type: application/json');

$customer_id = (int)($_GET['customer_id'] ?? 0);

if (!$customer_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid customer ID']);
    exit;
}

// Get customer activity logs
$activities = db_query("
    SELECT * FROM activity_logs 
    WHERE details LIKE ? OR details LIKE ?
    ORDER BY created_at DESC 
    LIMIT 20
", 'ss', [
    "%customer_id:{$customer_id}%", 
    "%Customer ID: {$customer_id}%"
]) ?: [];

// Add some basic activities for demonstration
$basic_activities = [
    [
        'id' => 'reg_' . $customer_id,
        'action' => 'Account Created',
        'details' => 'Customer registered account',
        'created_at' => db_query("SELECT created_at FROM customers WHERE customer_id = ?", 'i', [$customer_id])[0]['created_at'] ?? date('Y-m-d H:i:s')
    ]
];

$all_activities = array_merge($basic_activities, $activities);

echo json_encode(['success' => true, 'data' => $all_activities]);
?>
