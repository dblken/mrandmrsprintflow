<?php
/**
 * AJAX API to Add Souvenirs Order to Cart/Session for Review
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_customer()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$fields = $_POST;
$branch_id = trim($fields['branch_id'] ?? '1');
$souvenir_type = trim($fields['souvenir_type'] ?? '');
$needed_date = trim($fields['needed_date'] ?? '');
$lamination = trim($fields['lamination'] ?? 'Without Lamination');
$quantity = (int)($fields['quantity'] ?? 1);
$custom_print = trim($fields['custom_print'] ?? 'No');
$notes = trim($fields['notes'] ?? '');

if (empty($souvenir_type) || empty($needed_date) || $quantity < 1) {
    echo json_encode(['success' => false, 'message' => 'Please fill in Type, Needed Date, and Quantity.']);
    exit;
}

$design_tmp_path = null;
$design_name = null;
$design_mime = null;

if ($custom_print === 'Yes') {
    if (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Please upload your design for custom print.']);
        exit;
    }
    
    $valid = service_order_validate_file($_FILES['design_file']);
    if (!$valid['ok']) {
        echo json_encode(['success' => false, 'message' => 'Design upload error: ' . $valid['error']]);
        exit;
    }
    
    $tmp_dir = service_order_temp_dir();
    $ext = pathinfo($_FILES['design_file']['name'], PATHINFO_EXTENSION);
    $tmp_filename = uniqid('souv_tmp_') . '.' . $ext;
    $design_tmp_path = $tmp_dir . DIRECTORY_SEPARATOR . $tmp_filename;
    
    if (move_uploaded_file($_FILES['design_file']['tmp_name'], $design_tmp_path)) {
        $design_name = $_FILES['design_file']['name'];
        $design_mime = $valid['mime'];
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to transform uploaded file.']);
        exit;
    }
}

$item_key = uniqid('item_');
$product_name = 'Souvenir: ' . $souvenir_type;

$cart_item = [
    'product_id' => 0,
    'branch_id'  => $branch_id,
    'name' => $product_name,
    'category' => 'Souvenirs',
    'price' => 0, // Pending review
    'quantity' => $quantity,
    'customization' => [
        'service_type' => 'Souvenirs',
        'souvenir_type' => $souvenir_type,
        'needed_date' => $needed_date,
        'lamination' => $lamination,
        'custom_print' => $custom_print,
        'notes' => $notes
    ],
    'design_tmp_path' => $design_tmp_path,
    'design_name' => $design_name,
    'design_mime' => $design_mime,
    'width' => '',
    'height' => '',
    'thickness' => '',
    'stand_type' => '',
    'cut_type' => '',
    'design_notes' => $notes
];

$_SESSION['cart'][$item_key] = $cart_item;

echo json_encode(['success' => true, 'item_key' => $item_key]);

