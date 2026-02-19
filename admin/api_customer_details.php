<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'No customer ID provided']);
    exit;
}

$id = intval($_GET['id']);

try {
    $customer = db_query("SELECT * FROM customers WHERE customer_id = ?", "i", [$id]);

    if (empty($customer)) {
        echo json_encode(['success' => false, 'error' => 'Customer not found']);
        exit;
    }
    
    $c = $customer[0];
    
    // Format Data
    $data = [
        'customer_id' => $c['customer_id'],
        'first_name' => $c['first_name'],
        'last_name' => $c['last_name'],
        'email' => $c['email'],
        'phone' => $c['contact_number'] ?? 'N/A',
        'address' => $c['address'] ?? 'N/A',
        'created_at' => date('M j, Y g:i A', strtotime($c['created_at'])),
        'initial' => strtoupper(substr($c['first_name'], 0, 1))
    ];

    echo json_encode(['success' => true, 'customer' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
