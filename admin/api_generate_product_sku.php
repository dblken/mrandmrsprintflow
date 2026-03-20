<?php
/**
 * Admin Product SKU Generator API
 * PrintFlow - Printing Shop PWA
 * Auto-generates SKU based on product category
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Access Control
require_role(['Admin', 'Manager']);

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get category from POST
$category = sanitize($_POST['category'] ?? '');

if (empty($category)) {
    echo json_encode(['success' => false, 'error' => 'Category is required']);
    exit;
}

// Category prefixes for SKU

$category_prefixes = [
    'Tarpaulin' => 'TAR',
    'T-Shirt' => 'TSH',
    'Stickers' => 'STK',
    'Sintraboard' => 'SINT',
    'Apparel' => 'APP',
    'Signage' => 'SIG',
    'Merchandise' => 'MER',
    'Print' => 'PRI'
];

// Get prefix for this category
$prefix = $category_prefixes[$category] ?? substr(strtoupper(str_replace(' ', '', $category)), 0, 3);

try {
    // Get the highest product number for this category
    $result = db_query(
        "SELECT MAX(CAST(SUBSTRING(sku, LENGTH(?) + 1) AS UNSIGNED)) as max_num 
         FROM products 
         WHERE sku LIKE ? AND sku != ''",
        'ss',
        [$prefix, $prefix . '%']
    );

    $max_num = 0;
    if (!empty($result) && !empty($result[0]['max_num'])) {
        $max_num = (int)$result[0]['max_num'];
    }

    // Generate next SKU
    $next_num = $max_num + 1;
    $new_sku = $prefix . str_pad($next_num, 3, '0', STR_PAD_LEFT);

    echo json_encode([
        'success' => true,
        'sku' => $new_sku,
        'prefix' => $prefix,
        'next_number' => $next_num
    ]);

} catch (Exception $e) {
    error_log("SKU generation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to generate SKU']);
}
