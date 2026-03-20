<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $order_id = (int)($_POST['order_id'] ?? 0);
    $customer_id = get_user_id();

    if (!$order_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
        exit;
    }

    // Verify order ownership
    $order = db_query("SELECT status FROM orders WHERE order_id = ? AND customer_id = ?", 'ii', [$order_id, $customer_id]);
    if (empty($order)) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }

    if (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
        exit;
    }

    $file = $_FILES['design_file'];
    
    // Validate file
    $validation = validate_file_upload($file);
    if (!$validation['valid']) {
        echo json_encode(['success' => false, 'error' => $validation['message']]);
        exit;
    }

    // Read file content for BLOB
    $file_data = file_get_contents($file['tmp_name']);
    $mime_type = $file['type'];
    $file_name = $file['name'];

    // Update the FIRST item in the order with the new design (simplification for this project)
    // In a multi-item order, we'd ideally specify which item to update, 
    // but the current UI implies one primary design per order review flow.
    $item_result = db_query("SELECT order_item_id FROM order_items WHERE order_id = ? ORDER BY order_item_id ASC LIMIT 1", 'i', [$order_id]);
    
    if (empty($item_result)) {
        echo json_encode(['success' => false, 'error' => 'No items found for this order']);
        exit;
    }
    $order_item_id = $item_result[0]['order_item_id'];

    $sql = "UPDATE order_items 
            SET design_image = ?, design_image_mime = ?, design_image_name = ? 
            WHERE order_item_id = ?";
    
    $success = db_execute($sql, 'bssi', [$file_data, $mime_type, $file_name, $order_item_id]);

    if ($success) {
        // Set design status to Revision Submitted and order status back to Pending
        db_execute(
            "UPDATE orders SET design_status = 'Revision Submitted', status = 'Pending' WHERE order_id = ?",
            'i', [$order_id]
        );

        log_activity($customer_id, 'Design Re-upload', "Customer re-uploaded design for Order #$order_id");

        // Notify Staff
        create_notification(
            null, // Multi-channel or Admin
            'User', 
            "📤 Customer has re-uploaded a design for Order #$order_id. Please review.", 
            'Order', 
            false, 
            false, 
            $order_id
        );

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save design to database']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}

