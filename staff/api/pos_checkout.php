<?php
/**
 * API: POS Checkout Process
 * Path: staff/api/pos_checkout.php
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Require staff or admin role
if (!has_role(['Admin', 'Staff'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

header('Content-Type: application/json');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data. Cart is empty.']);
    exit;
}

$customer_id = $data['customer_id'] === 'guest' ? null : (int)$data['customer_id'];
$payment_method = sanitize($data['payment_method'] ?? 'Cash');
$amount_tendered = (float)($data['amount_tendered'] ?? 0);
$items = $data['items'];

// Calculate total and verify stock
$total_amount = 0;
foreach ($items as $item) {
    $product_id = (int)$item['id'];
    $qty = (int)$item['qty'];
    
    $product = db_query("SELECT price, stock_quantity, name FROM products WHERE product_id = ?", 'i', [$product_id]);
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found: ' . $product_id]);
        exit;
    }
    
    if ($product[0]['stock_quantity'] < $qty) {
        echo json_encode(['success' => false, 'message' => 'Insufficient stock for ' . $product[0]['name']]);
        exit;
    }
    
    // For POS walk-ins, we trust the negotiated price sent from the frontend 
    // especially for "Services" or custom jobs.
    $price = (float)($item['price'] ?? $product[0]['price']);
    $total_amount += $price * $qty;
}

try {
    global $conn;
    $conn->begin_transaction();

    // Create Order
    // For POS walk-ins, we use status 'Completed' and payment_status 'Paid'
    $branch_id = $_SESSION['branch_id'] ?? 1; // Default to branch 1 if not set
    
    $order_result = db_execute(
        "INSERT INTO orders (customer_id, branch_id, total_amount, status, payment_status, payment_method, order_date, created_at, updated_at) 
         VALUES (?, ?, ?, 'Completed', 'Paid', ?, NOW(), NOW(), NOW())",
        'iids',
        [$customer_id, $branch_id, $total_amount, $payment_method]
    );

    if (!$order_result) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to create order.']);
        exit;
    }

    $order_id = $conn->insert_id;

    // Insert Order Items and Update Stock
    foreach ($items as $item) {
        $product_id = (int)$item['id'];
        $qty = (int)$item['qty'];
        $price = (float)$item['price'];

        $name = $item['name'] ?? $product[0]['name'];
        $notes = ($name !== $product[0]['name']) ? $name : null;

        // If customization data exists from the dynamic POS modal
        if (!empty($item['customization'])) {
            $custom_str = [];
            foreach ($item['customization'] as $k => $v) {
                if (!empty($v)) $custom_str[] = "$k: $v";
            }
            $custom_notes = implode("\n", $custom_str);
            $notes = $notes ? $notes . "\n\n" . $custom_notes : $custom_notes;
        }

        $item_result = db_execute(
            "INSERT INTO order_items (order_id, product_id, quantity, unit_price, design_notes) VALUES (?, ?, ?, ?, ?)",
            'iiids',
            [$order_id, $product_id, $qty, $price, $notes]
        );

        if (!$item_result) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to add order items.']);
            exit;
        }

        // Deduct stock
        $stock_result = db_execute(
            "UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?",
            'ii',
            [$qty, $product_id]
        );

        if (!$stock_result) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to update stock.']);
            exit;
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'order_id' => $order_id, 'message' => 'Sale completed successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
