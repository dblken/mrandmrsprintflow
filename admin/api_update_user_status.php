<?php
/**
 * Admin Update User Status & Info API
 * PrintFlow - Printing Shop PWA
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'Manager']);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    echo json_encode(['success' => false, 'error' => 'Invalid method']); 
    exit; 
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['csrf_token']) || $data['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']); 
    exit;
}

$user_id = (int)($data['user_id'] ?? 0);
$action = $data['action'] ?? '';

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'User ID is required']); 
    exit;
}

if ($action === 'toggle_status') {
    $current_status = $data['current_status'] ?? 'Activated';
    $new_status = ($current_status === 'Activated') ? 'Deactivated' : 'Activated';
    
    // Prevent deactivating oneself
    if ($user_id === $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'error' => 'Cannot deactivate your own account']); 
        exit;
    }

    $ok = db_execute("UPDATE users SET status = ? WHERE user_id = ?", 'si', [$new_status, $user_id]);
    if ($ok) {
        echo json_encode(['success' => true, 'new_status' => $new_status, 'message' => "User account successfully {$new_status}."]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update status.']);
    }
} elseif ($action === 'update_info') {
    $first_name     = sanitize($data['first_name'] ?? '');
    $middle_name    = sanitize($data['middle_name'] ?? '');
    $last_name      = sanitize($data['last_name'] ?? '');
    $contact_number = sanitize($data['contact_number'] ?? '');
    $address        = sanitize($data['address'] ?? '');
    $gender         = sanitize($data['gender'] ?? '');
    $dob            = sanitize($data['dob'] ?? '');
    $role           = sanitize($data['role'] ?? '');
    $branch_id      = !empty($data['branch_id']) ? (int)$data['branch_id'] : null;
    
    if ($role === 'Admin') $branch_id = null;
    
    if (empty($first_name) || empty($last_name)) {
        echo json_encode(['success' => false, 'error' => 'Name fields cannot be empty']); 
        exit;
    }

    $status         = in_array($data['status'] ?? '', ['Activated','Pending','Deactivated']) ? $data['status'] : 'Pending';

    $ok = db_execute(
        "UPDATE users SET first_name=?, middle_name=?, last_name=?, contact_number=?, address=?, gender=?, dob=?, role=?, branch_id=?, status=? WHERE user_id=?",
        'ssssssssssii',
        [$first_name, $middle_name ?: '', $last_name, $contact_number ?: '', $address ?: '', $gender ?: '', $dob ?: null, $role, $branch_id, $status, $user_id]
    );
    
    echo json_encode(['success' => true, 'message' => "User info updated successfully."]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
