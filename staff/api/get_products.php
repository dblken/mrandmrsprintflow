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
    $products = db_query("
        SELECT 
            product_id, 
            name as product_name, 
            sku, 
            category, 
            price, 
            stock_quantity, 
            product_image 
        FROM products 
        WHERE status = 'Activated' 
        AND category IN ('Tarpaulin', 'T-Shirt', 'Stickers', 'Glass/Wall', 'Transparent Stickers', 'Reflectorized', 'Sintraboard', 'Standees', 'Souvenirs', 'Apparel', 'Signage', 'Merchandise', 'Decals & Stickers', 'T-Shirt Printing')
        ORDER BY category ASC, name ASC
    ");

    echo json_encode(['success' => true, 'products' => ($products ?: [])]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
