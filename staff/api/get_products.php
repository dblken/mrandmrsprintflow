<?php
/**
 * POS Products API
 * Fetches all active products for the POS interface.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !in_array(get_user_type(), ['Admin', 'Staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $products = db_query("
        SELECT product_id, name as product_name, sku, category, price, stock_quantity, image_url as product_image
        FROM products 
        WHERE status = 'Activated'
        ORDER BY name ASC
    ");
    
    echo json_encode(['success' => true, 'products' => $products]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
