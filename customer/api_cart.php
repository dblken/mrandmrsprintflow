<?php
/**
 * Customer Cart API
 * PrintFlow - Printing Shop PWA
 *
 * AJAX-only endpoint (POST, JSON response, CSRF-protected).
 * Manages the session cart.
 *
 * Actions: add | update | remove | get_count | clear
 *
 * Session cart structure:
 *   $_SESSION['cart'][$cart_key] = [
 *       'product_id'   => int,
 *       'variant_id'   => int|null,
 *       'product_name' => string,
 *       'variant_name' => string,   // '' when no variant
 *       'quantity'     => int,
 *       'price'        => float,    // variant price OR product price
 *   ]
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Must be logged-in customer
if (!is_logged_in() || get_user_type() !== 'Customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Parse JSON or form data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// CSRF validation
if (!verify_csrf_token($input['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$action = $input['action'] ?? '';

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

/**
 * Make a unique cart key from product_id + variant_id
 */
function cart_key(int $product_id, ?int $variant_id): string {
    return $product_id . '_' . ($variant_id ?? '0');
}

/**
 * Total distinct item lines in cart
 */
function cart_count(): int {
    return array_sum(array_column($_SESSION['cart'], 'quantity'));
}

// -----------------------------------------------------------------------
// ADD
// -----------------------------------------------------------------------
if ($action === 'add') {
    $product_id = (int)($input['product_id'] ?? 0);
    $variant_id = isset($input['variant_id']) && $input['variant_id'] !== '' ? (int)$input['variant_id'] : null;
    $quantity   = max(1, (int)($input['quantity'] ?? 1));

    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product.']);
        exit;
    }

    // Validate product is active
    $product = db_query(
        "SELECT product_id, name, price FROM products WHERE product_id = ? AND status = 'Activated'",
        'i', [$product_id]
    );
    if (empty($product)) {
        echo json_encode(['success' => false, 'message' => 'Product not available.']);
        exit;
    }
    $product = $product[0];

    $price        = (float)$product['price'];
    $variant_name = '';

    if ($variant_id !== null) {
        // Validate variant: must be Active and belong to this product
        $variant = db_query(
            "SELECT variant_id, variant_name, price FROM product_variants
             WHERE variant_id = ? AND product_id = ? AND status = 'Active'",
            'ii', [$variant_id, $product_id]
        );
        if (empty($variant)) {
            echo json_encode(['success' => false, 'message' => 'Selected variant is not available.']);
            exit;
        }
        $price        = (float)$variant[0]['price'];
        $variant_name = $variant[0]['variant_name'];
    } else {
        // Product has no variant required? Check if there ARE active variants and force selection
        $has_variants = db_query(
            "SELECT COUNT(*) as cnt FROM product_variants WHERE product_id = ? AND status = 'Active'",
            'i', [$product_id]
        );
        if (!empty($has_variants) && (int)$has_variants[0]['cnt'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Please select a variant before adding to cart.']);
            exit;
        }
    }

    $key = cart_key($product_id, $variant_id);

    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$key] = [
            'product_id'   => $product_id,
            'variant_id'   => $variant_id,
            'product_name' => $product['name'],
            'variant_name' => $variant_name,
            'quantity'     => $quantity,
            'price'        => $price,
        ];
    }

    echo json_encode([
        'success'    => true,
        'message'    => 'Added to cart!',
        'cart_count' => cart_count(),
    ]);
    exit;
}

// -----------------------------------------------------------------------
// UPDATE QTY
// -----------------------------------------------------------------------
if ($action === 'update') {
    $key      = $input['cart_key'] ?? '';
    $quantity = (int)($input['quantity'] ?? 0);

    if (!isset($_SESSION['cart'][$key])) {
        echo json_encode(['success' => false, 'message' => 'Item not found.']);
        exit;
    }

    if ($quantity <= 0) {
        unset($_SESSION['cart'][$key]);
    } else {
        $_SESSION['cart'][$key]['quantity'] = $quantity;
    }

    echo json_encode([
        'success'    => true,
        'cart_count' => cart_count(),
    ]);
    exit;
}

// -----------------------------------------------------------------------
// REMOVE
// -----------------------------------------------------------------------
if ($action === 'remove') {
    $key = $input['cart_key'] ?? '';
    unset($_SESSION['cart'][$key]);
    echo json_encode([
        'success'    => true,
        'cart_count' => cart_count(),
    ]);
    exit;
}

// -----------------------------------------------------------------------
// GET COUNT
// -----------------------------------------------------------------------
if ($action === 'get_count') {
    echo json_encode([
        'success'    => true,
        'cart_count' => cart_count(),
    ]);
    exit;
}

// -----------------------------------------------------------------------
// CLEAR
// -----------------------------------------------------------------------
if ($action === 'clear') {
    $_SESSION['cart'] = [];
    echo json_encode(['success' => true, 'cart_count' => 0]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
