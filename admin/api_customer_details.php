<?php
require_once __DIR__ . '/../includes/api_header.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'Manager']);
// Ensure $base_path is defined
if (!isset($base_path)) {
    if (file_exists(__DIR__ . '/../config.php')) {
        require_once __DIR__ . '/../config.php';
    }
    $base_path = defined('BASE_PATH') ? BASE_PATH : '/printflow';
}

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
    
    // Format profile picture path
    $profile_picture = null;
    if (!empty($c['profile_picture'])) {
        $profile_picture = $base_path . '/public/assets/uploads/profiles/' . $c['profile_picture'];
    }
    
    // Format Data
    $data = [
        'customer_id' => $c['customer_id'],
        'first_name' => $c['first_name'],
        'middle_name' => $c['middle_name'] ?? '',
        'last_name' => $c['last_name'],
        'email' => $c['email'],
        'contact_number' => $c['contact_number'] ?? '',
        'address' => $c['address'] ?? '',
        'dob' => $c['dob'] ? date('m/d/Y', strtotime($c['dob'])) : '',
        'gender' => $c['gender'] ?? '',
        'created_at' => date('M j, Y', strtotime($c['created_at'])),
        'profile_picture' => $profile_picture,
        'initial' => strtoupper(substr($c['first_name'], 0, 1)),
        'id_status' => $c['id_status'] ?? 'Unverified',
        'id_type'   => $c['id_type'] ?? '',
        'id_image'  => !empty($c['id_image']) ? $base_path . '/uploads/ids/' . $c['id_image'] : null,
        'id_reject_reason' => $c['id_reject_reason'] ?? ''
    ];

    echo json_encode(['success' => true, 'customer' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
