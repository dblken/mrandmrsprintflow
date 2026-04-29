<?php
/**
 * API: POS Checkout Process
 * Path: staff/api/pos_checkout.php
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/product_branch_stock.php';
require_once __DIR__ . '/../../includes/JobOrderService.php';

/**
 * POS customization rows must resolve to real job_orders, but JobOrderService
 * manages its own transactions, so run this only after the outer POS checkout
 * transaction has committed.
 */
function pos_sync_customization_jobs_after_commit(int $orderId, string $targetStatus): void {
    if ($orderId <= 0 || $targetStatus === '') {
        return;
    }

    JobOrderService::ensureJobsForStoreOrder($orderId);
    $jobs = db_query(
        "SELECT id FROM job_orders WHERE order_id = ? AND status NOT IN ('COMPLETED', 'CANCELLED') ORDER BY id ASC",
        'i',
        [$orderId]
    ) ?: [];

    foreach ($jobs as $job) {
        JobOrderService::updateStatus((int)$job['id'], $targetStatus);
    }
}

// Require staff or admin role
if (!has_role(['Admin', 'Staff'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

header('Content-Type: application/json');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data.']);
    exit;
}

// Handle create_pending_customization action
if (isset($data['action']) && $data['action'] === 'create_pending_customization') {
    $customer_id = $data['customer_id'] === 'guest' ? null : (int)$data['customer_id'];
    $post_commit_order_id = 0;
    $transaction_open = false;
    
    if ($customer_id === null) {
        global $conn;
        $res = db_query("SELECT customer_id FROM customers WHERE email='walkin@pos.local' LIMIT 1");
        if (!empty($res)) {
            $customer_id = (int)$res[0]['customer_id'];
        } else {
            db_execute("INSERT INTO customers (first_name, last_name, email, password_hash, status) VALUES ('Walk-in', 'Guest', 'walkin@pos.local', '', 'Active')");
            $customer_id = $conn->insert_id;
        }
    }
    
    $item = $data['item'];
    $product_id = (int)$item['id'];
    $name = $item['name'] ?? 'Service';
    $qty = (int)($item['qty'] ?? 1);
    $customization = $item['customization'] ?? [];
    
    // Mark as POS source
    $customization['source'] = 'POS';
    
    try {
        global $conn;
        $conn->begin_transaction();
        $transaction_open = true;
        
        $branch_id = (int)($_SESSION['branch_id'] ?? 1);
        if ($branch_id < 1) $branch_id = 1;
        
        // Create order with status 'Approved' (skipped initial Pending verification for POS)
        // Will be updated to 'Paid' when checkout completes
        $order_result = db_execute(
            "INSERT INTO orders (customer_id, branch_id, reference_id, total_amount, status, payment_status, payment_method, order_date, updated_at, order_type, order_source) 
             VALUES (?, ?, ?, 0, 'Approved', 'Unpaid', 'Cash', NOW(), NOW(), 'custom', 'pos')",
            'iii',
            [$customer_id, $branch_id, $product_id]
        );
        
        if (!$order_result) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to create order.']);
            exit;
        }
        
        $order_id = $conn->insert_id;
        
        // Store order_id in session for later update during checkout
        if (!isset($_SESSION['pos_pending_orders'])) {
            $_SESSION['pos_pending_orders'] = [];
        }
        $_SESSION['pos_pending_orders'][$product_id] = $order_id;
        
        // Create order item with price = 0
        $customization_json = json_encode($customization ?: new stdClass());
        $item_result = db_execute(
            "INSERT INTO order_items (order_id, product_id, quantity, unit_price, customization_data) VALUES (?, ?, ?, 0, ?)",
            'iiis',
            [$order_id, null, $qty, $customization_json]
        );
        
        if (!$item_result) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to add order item.']);
            exit;
        }
        
        $order_item_id = $conn->insert_id;
        
        // Create customization entry with status 'Approved'
        $details_json = json_encode($customization ?: new stdClass());
        $customization_result = db_execute(
            "INSERT INTO customizations (order_id, order_item_id, customer_id, service_type, customization_details, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'Approved', NOW(), NOW())",
            'iiiss',
            [$order_id, $order_item_id, $customer_id, $name, $details_json]
        );
        
        if (!$customization_result) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to create customization entry.']);
            exit;
        }
        
        $customization_id = $conn->insert_id;

        $conn->commit();
        $transaction_open = false;
        $post_commit_order_id = $order_id;
        pos_sync_customization_jobs_after_commit($post_commit_order_id, 'APPROVED');
        echo json_encode(['success' => true, 'customization_id' => $customization_id, 'order_id' => $order_id]);
        exit;
        
    } catch (Exception $e) {
        if ($transaction_open && isset($conn)) {
            $conn->rollback();
        }
        if ($post_commit_order_id > 0) {
            error_log('PrintFlow POS customization sync failed for order #' . $post_commit_order_id . ': ' . $e->getMessage());
            echo json_encode([
                'success' => true,
                'customization_id' => $customization_id ?? null,
                'order_id' => $post_commit_order_id,
                'warning' => 'Customization was created, but production sync needs follow-up.'
            ]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

if (empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data. Cart is empty.']);
    exit;
}

$customer_id = $data['customer_id'] === 'guest' ? null : (int)$data['customer_id'];

if ($customer_id === null) {
    global $conn;
    $res = db_query("SELECT customer_id FROM customers WHERE email='walkin@pos.local' LIMIT 1");
    if (!empty($res)) {
        $customer_id = (int)$res[0]['customer_id'];
    } else {
        db_execute("INSERT INTO customers (first_name, last_name, email, password_hash, status) VALUES ('Walk-in', 'Guest', 'walkin@pos.local', '', 'Active')");
        $customer_id = $conn->insert_id;
    }
}
$payment_method = sanitize($data['payment_method'] ?? 'Cash');
$reference_number = sanitize($data['reference_number'] ?? '');
$amount_tendered = (float)($data['amount_tendered'] ?? 0);
$items = $data['items'];

if ($payment_method !== 'Cash' && empty($reference_number)) {
    echo json_encode(['success' => false, 'message' => "Reference number is required for $payment_method."]);
    exit;
}
if ($amount_tendered > 1000000) {
    echo json_encode(['success' => false, 'message' => 'Amount paid exceeds maximum limit of ₱1,000,000.']);
    exit;
}

printflow_ensure_product_branch_stock_table();
$pos_branch_id = (int)($_SESSION['branch_id'] ?? 0);

// Calculate total and verify stock
$total_amount = 0;
$products_cache = [];
foreach ($items as $item) {
    $product_id = (int)$item['id'];
    $qty = (int)$item['qty'];
    $is_service_item = isset($item['is_service']) && $item['is_service'];

    $product = db_query("SELECT price, name FROM products WHERE product_id = ?", 'i', [$product_id]);
    if (!$product) {
        if ($is_service_item) {
            // Service IDs may not exist in products table — use name/price from payload
            $products_cache[$product_id] = ['name' => $item['name'] ?? 'Service', 'price' => (float)($item['price'] ?? 0)];
            $total_amount += (float)($item['price'] ?? 0) * $qty;
            continue;
        }
        echo json_encode(['success' => false, 'message' => 'Product not found: ' . $product_id]);
        exit;
    }
    $p = $product[0];
    $products_cache[$product_id] = $p;

    // Skip stock check for services
    if (!$is_service_item) {
        [$effStock] = printflow_product_effective_stock($product_id, $pos_branch_id);
        if ($effStock < $qty) {
            echo json_encode(['success' => false, 'message' => 'Insufficient stock for ' . $p['name']]);
            exit;
        }
    }

    $price = (float)($item['price'] ?? $p['price']);
    $total_amount += $price * $qty;
}

try {
    global $conn;
    $post_commit_job_sync = [];
    $transaction_open = false;
    $conn->begin_transaction();
    $transaction_open = true;

    // Create Order
    // For POS walk-ins, we use status 'In Production' and payment_status 'Paid', skipping 'To Pay' and 'To Verify'
    $branch_id = (int)($_SESSION['branch_id'] ?? 1);
    if ($branch_id < 1) $branch_id = 1;

    // Determine order_type based on cart content
    $has_service = false;
    foreach ($items as $item) {
        if (isset($item['is_service']) && $item['is_service']) {
            $has_service = true;
            break;
        }
    }
    $order_type = $has_service ? 'custom' : 'product';
    $reference_id = $items[0]['id'] ?? null;

    $order_result = db_execute(
        "INSERT INTO orders (customer_id, branch_id, reference_id, total_amount, status, payment_status, payment_method, payment_reference, order_date, updated_at, order_type, order_source) 
         VALUES (?, ?, ?, ?, 'In Production', 'Paid', ?, ?, NOW(), NOW(), ?, 'pos')",
        'iiidsss',
        [$customer_id, $branch_id, $reference_id, $total_amount, $payment_method, $reference_number, $order_type]
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
        $p = $products_cache[$product_id] ?? null;
        $prod_name = $p['name'] ?? 'Product';

        $name = $item['name'] ?? $prod_name;
        
        // Detect if this specific item is a service or customized product
        $is_service = (isset($item['is_service']) && $item['is_service']);
        
        // Determine if we should keep the product_id (only for actual products in the DB)
        $is_actual_product = isset($products_cache[$product_id]);

        $custom_details = $item['customization'] ?? [];
        if (!is_array($custom_details)) $custom_details = [];
        $customization_json = json_encode($custom_details ?: new stdClass());

        $item_result = db_execute(
            "INSERT INTO order_items (order_id, product_id, quantity, unit_price, customization_data) VALUES (?, ?, ?, ?, ?)",
            'iiids',
            [$order_id, $is_actual_product ? $product_id : null, $qty, $price, $customization_json]
        );

        if (!$item_result) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to add order items.']);
            exit;
        }
        
        $order_item_id = $conn->insert_id;

        // Always create customization entry for service items
        if ($is_service) {
            $details = $custom_details;
            $details['source'] = 'POS'; // Mark as POS purchase
            $details_json = json_encode($details ?: new stdClass());
            $customization_result = db_execute(
                "INSERT INTO customizations (order_id, order_item_id, customer_id, service_type, customization_details, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'In Production', NOW(), NOW())",
                'iiiss',
                [$order_id, $order_item_id, $customer_id, $name, $details_json]
            );
            if (!$customization_result) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to create customization entry.']);
                exit;
            }
            $last_customization_id = $conn->insert_id;

            $post_commit_job_sync[$order_id] = 'IN_PRODUCTION';
            
            // Update any existing pending customization orders for this item to mark as paid
            if (isset($_SESSION['pos_pending_orders'][$product_id])) {
                $pending_order_id = $_SESSION['pos_pending_orders'][$product_id];
                db_execute(
                    "UPDATE orders SET payment_status = 'Paid', payment_method = ?, total_amount = ?, status = 'In Production', updated_at = NOW() WHERE order_id = ?",
                    'sdi',
                    [$payment_method, $price * $qty, $pending_order_id]
                );
                // Update customization with the price — also move to In Production
                db_execute(
                    "UPDATE customizations SET status = 'In Production' WHERE order_id = ?",
                    'i',
                    [$pending_order_id]
                );
                unset($_SESSION['pos_pending_orders'][$product_id]);
            }
        }

        // Deduct stock is removed here as per user request to deduct only when status is COMPLETED
    }

    $conn->commit();
    $transaction_open = false;
    foreach ($post_commit_job_sync as $sync_order_id => $sync_status) {
        pos_sync_customization_jobs_after_commit((int)$sync_order_id, (string)$sync_status);
    }
    echo json_encode(['success' => true, 'order_id' => $order_id, 'customization_id' => $last_customization_id ?? null, 'message' => 'Sale completed successfully.']);

} catch (Exception $e) {
    if ($transaction_open && isset($conn)) {
        $conn->rollback();
    }
    if (!empty($order_id) && !$transaction_open) {
        error_log('PrintFlow POS checkout post-commit sync failed for order #' . (int)$order_id . ': ' . $e->getMessage());
        echo json_encode([
            'success' => true,
            'order_id' => (int)$order_id,
            'customization_id' => $last_customization_id ?? null,
            'message' => 'Sale completed successfully.',
            'warning' => 'Production sync needs follow-up.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
