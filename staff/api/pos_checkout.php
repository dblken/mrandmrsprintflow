<?php
/**
 * POS Checkout API for Staff Walk-in Interface
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// Ensure only staff/admin can access
if (!is_logged_in() || !in_array(get_user_type(), ['Admin', 'Staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON POST payload
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['action']) || $input['action'] !== 'walkin_checkout') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$customer_id = $input['customer_id'] ?? 'guest';
$payment_method = sanitize($input['payment_method'] ?? 'Cash');
$amount_tendered = floatval($input['amount_tendered'] ?? 0);
$items = $input['items'] ?? [];

if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty']);
    exit;
}

// Map payment method to ID
$payment_method_id = 1; // Default to 1 (Cash/Counter)
try {
    $pm_res = db_query("SELECT payment_method_id FROM payment_methods WHERE method_name = ? AND status='Active' LIMIT 1", 's', [$payment_method]);
    if (!empty($pm_res)) {
        $payment_method_id = $pm_res[0]['payment_method_id'];
    }
} catch (Exception $e) { }

// If customer is guest, set customer_id to null
$db_customer_id = ($customer_id === 'guest') ? null : intval($customer_id);
$branch_id = $_SESSION['branch_id'] ?? 1;

global $conn;
$conn->begin_transaction();

try {
    // 1. Calculate and verify total amount
    $total_amount = 0;
    $processed_items = [];
    
    foreach ($items as $item) {
        $pid = intval($item['id']);
        $qty = intval($item['qty']);
        
        $prod = db_query("SELECT price, stock_quantity, sku, name FROM products WHERE product_id = ?", 'i', [$pid]);
        if (empty($prod)) {
            throw new Exception("Product ID $pid not found.");
        }
        $p = $prod[0];
        
        if ($p['stock_quantity'] < $qty) {
            throw new Exception("Not enough stock for {$p['name']}. Available: {$p['stock_quantity']}");
        }
        
        $price = floatval($p['price']);
        $total_amount += ($price * $qty);
        
        $processed_items[] = [
            'product_id' => $pid,
            'qty' => $qty,
            'price' => $price,
            'sku' => $p['sku']
        ];
    }
    
    // Check if tendered amount covers total (for Cash)
    if ($payment_method === 'Cash' && $amount_tendered < $total_amount) {
        throw new Exception("Amount tendered is insufficient.");
    }

    // 2. Create Order
    $sql_order = "INSERT INTO orders (customer_id, branch_id, order_date, total_amount, status, payment_status, payment_method_id, payment_reference) 
                  VALUES (?, ?, NOW(), ?, 'Completed', 'Paid', ?, ?)";
    
    // Generate a quick reference for POS
    $ref = 'POS-' . strtoupper(substr(uniqid(), -6));
    
    $stmt = $conn->prepare($sql_order);
    $status = 'Completed'; 
    $payment_status = 'Paid';
    $stmt->bind_param("iiddis", $db_customer_id, $branch_id, $total_amount, $payment_method_id, $ref);
    $stmt->execute();
    $order_id = $stmt->insert_id;
    $stmt->close();
    
    if (!$order_id) {
        throw new Exception("Failed to create order record.");
    }
    
    // 3. Create Order Items & Deduct Stock
    $sql_item = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, sku) VALUES (?, ?, ?, ?, ?)";
    $stmt_item = $conn->prepare($sql_item);
    
    $sql_stock = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?";
    $stmt_stock = $conn->prepare($sql_stock);
    
    foreach ($processed_items as $pi) {
        // Insert item
        $stmt_item->bind_param("iiids", $order_id, $pi['product_id'], $pi['qty'], $pi['price'], $pi['sku']);
        $stmt_item->execute();
        
        // Deduct stock
        $stmt_stock->bind_param("ii", $pi['qty'], $pi['product_id']);
        $stmt_stock->execute();
    }
    
    $stmt_item->close();
    $stmt_stock->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Checkout complete',
        'order_id' => $order_id,
        'total' => $total_amount,
        'change' => ($payment_method==='Cash') ? ($amount_tendered - $total_amount) : 0
    ]);
    
} catch (Exception $e) {
    if ($conn) $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
