<?php
/**
 * API: Get Products for POS
 * Path: staff/api/get_products.php
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

try {
    $rows = db_query("
        SELECT 
            product_id, 
            name as product_name, 
            sku, 
            category, 
            price, 
            stock_quantity, 
            COALESCE(low_stock_level, 10) as low_stock_level,
            product_image 
        FROM products 
        WHERE status = 'Activated' 
        AND category IN ('Tarpaulin', 'T-Shirt', 'Stickers', 'Glass/Wall', 'Transparent Stickers', 'Reflectorized', 'Sintraboard', 'Standees', 'Souvenirs', 'Apparel', 'Signage', 'Merchandise', 'Decals & Stickers', 'T-Shirt Printing')
        ORDER BY category ASC, name ASC
    ");
    $products = [];
    foreach ($rows ?: [] as $p) {
        $p['stock_status'] = get_stock_status($p['stock_quantity'], $p['low_stock_level']);
        $p['quantity'] = (int) ($p['stock_quantity'] ?? 0);
        $products[] = $p;
    }
    echo json_encode(['success' => true, 'products' => $products]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
