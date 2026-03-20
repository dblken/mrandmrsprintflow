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
    $birthday       = sanitize($data['dob'] ?? ''); // Maps from modal's 'dob' model
    $role           = sanitize($data['role'] ?? '');
    $branch_id      = !empty($data['branch_id']) ? (int)$data['branch_id'] : null;
    
    if ($role === 'Admin') $branch_id = null;
    
    // Server-side validation
    $errors = [];
    
    // Names
    if (empty($first_name) || !preg_match("/^[A-Za-z]+( [A-Za-z]+)*$/", $first_name) || strlen($first_name) < 2 || strlen($first_name) > 50) {
        $errors[] = 'Invalid first name (2-50 letters only)';
    }
    if (empty($last_name) || !preg_match("/^[A-Za-z]+( [A-Za-z]+)*$/", $last_name) || strlen($last_name) < 2 || strlen($last_name) > 50) {
        $errors[] = 'Invalid last name (2-50 letters only)';
    }
    
    // Contact Number
    if (empty($contact_number) || !preg_match("/^09\d{9}$/", $contact_number)) {
        $errors[] = 'Invalid contact number (09XXXXXXXXX)';
    }
    
    // Address
    if (empty($address) || strlen($address) < 5 || strlen($address) > 150) {
        $errors[] = 'Invalid address (5-150 chars)';
    }

    // Birthday
    if (!empty($birthday)) {
        try {
            $bday_date = new DateTime($birthday);
            $today = new DateTime();
            $age = $today->diff($bday_date)->y;
            if ($bday_date > $today) {
                $errors[] = 'Birthday cannot be a future date';
            } elseif ($age < 13) {
                $errors[] = 'User must be at least 13 years old';
            }
        } catch (Exception $e) {
            $errors[] = 'Invalid birthday format';
        }
    } else {
        $errors[] = 'Birthday is required';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'error' => implode(', ', $errors)]); 
        exit;
    }

    $status         = in_array($data['status'] ?? '', ['Activated','Pending','Deactivated']) ? $data['status'] : 'Pending';

    $ok = db_execute(
        "UPDATE users SET first_name=?, middle_name=?, last_name=?, contact_number=?, address=?, gender=?, birthday=?, role=?, branch_id=?, status=? WHERE user_id=?",
        "ssssssssisi",
        [$first_name, $middle_name ?: '', $last_name, $contact_number ?: '', $address ?: '', $gender ?: '', $birthday ?: null, $role, $branch_id, $status, $user_id]
    );
    
    if ($ok) {
        echo json_encode(['success' => true, 'message' => "User info updated successfully."]);
    } else {
        echo json_encode(['success' => false, 'error' => "Failed to update user information."]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
