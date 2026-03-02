<?php
/**
 * Customer Checkout Page
 * PrintFlow - Printing Shop PWA
 *
 * Reviews cart, creates order + order_items (with variant_id), then redirects to payment confirmation.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

// Profile gate — must complete profile (real name, etc.) before ordering
if (!is_profile_complete()) {
    $_SESSION['flash_warning'] = 'Please complete your profile before placing an order.';
    redirect('/printflow/customer/profile.php?complete_profile=1');
}

// Empty cart → back to products
if (empty($_SESSION['cart'])) {
    redirect('/printflow/customer/products.php');
}

$customer_id = get_customer_id();
$cart        = $_SESSION['cart'];
$error       = '';

// -----------------------------------------------------------------------
// POST: Submit order
// -----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $notes = trim($_POST['notes'] ?? '');

    // Re-validate every cart item
    $valid_items = [];
    $total       = 0.0;
    $fail        = '';

    foreach ($cart as $cart_key => $item) {
        $pid = (int)$item['product_id'];
        $vid = $item['variant_id'] ? (int)$item['variant_id'] : null;
        $qty = (int)$item['quantity'];

        // Verify product is still active
        $prod = db_query(
            "SELECT product_id, name, price FROM products WHERE product_id = ? AND status = 'Activated'",
            'i', [$pid]
        );
        if (empty($prod)) {
            $fail = "Product \"{$item['product_name']}\" is no longer available.";
            break;
        }

        $price = (float)$prod[0]['price'];

        if ($vid !== null) {
            // Verify variant is still active and belongs to this product
            $var = db_query(
                "SELECT variant_id, price FROM product_variants
                 WHERE variant_id = ? AND product_id = ? AND status = 'Active'",
                'ii', [$vid, $pid]
            );
            if (empty($var)) {
                $fail = "Variant \"{$item['variant_name']}\" for \"{$item['product_name']}\" is no longer available.";
                break;
            }
            $price = (float)$var[0]['price'];
        }

        if ($qty < 1) {
            $fail = "Invalid quantity for \"{$item['product_name']}\".";
            break;
        }

        $valid_items[] = [
            'product_id' => $pid,
            'variant_id' => $vid,
            'quantity'   => $qty,
            'unit_price' => $price,
        ];
        $total += $price * $qty;
    }

    if ($fail) {
        $error = $fail;
    } elseif (empty($valid_items)) {
        $error = 'Cart is empty.';
    } else {
        // Create order in a transaction
        global $conn;
        $conn->begin_transaction();
        try {
            $branch_id = $_SESSION['branch_id'] ?? 1;
            // Insert order
            $order_id = db_execute(
                "INSERT INTO orders (customer_id, branch_id, total_amount, status, payment_status, notes, order_date)
                 VALUES (?, ?, ?, 'Pending', 'Unpaid', ?, NOW())",
                'iids', [$customer_id, $branch_id, $total, $notes]
            );

            if (!$order_id) {
                throw new RuntimeException('Failed to create order.');
            }

            // Insert order items
            foreach ($valid_items as $it) {
                $ok = db_execute(
                    "INSERT INTO order_items (order_id, product_id, variant_id, quantity, unit_price)
                     VALUES (?, ?, ?, ?, ?)",
                    'iiiid', [$order_id, $it['product_id'], $it['variant_id'], $it['quantity'], $it['unit_price']]
                );
                if (!$ok) {
                    throw new RuntimeException('Failed to insert order item.');
                }
            }

            $conn->commit();

            // Clear cart
            $_SESSION['cart'] = [];

            // Notify admin
            create_notification(null, 'Admin', "New order #{$order_id} received.", 'Order', false, false);

            redirect("/printflow/customer/payment_confirmation.php?order_id={$order_id}");
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Failed to place order. Please try again.';
            error_log('Checkout error: ' . $e->getMessage());
        }
    }
}

// Recalculate display totals
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$page_title       = 'Checkout - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width:900px;">
        <h1 class="ct-page-title">Checkout</h1>

        <?php if ($error): ?>
            <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:14px 18px;border-radius:10px;margin-bottom:1.5rem;font-size:0.9rem;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns:1fr 320px; gap:1.5rem; align-items:start;">

            <!-- Left: Order Review -->
            <div>
                <div class="card" style="margin-bottom:1.5rem;">
                    <h3 style="font-size:1rem; font-weight:700; margin-bottom:1rem; color:#1f2937;">Order Review</h3>
                    <table style="width:100%; border-collapse:collapse; font-size:0.875rem;">
                        <thead>
                            <tr style="border-bottom:2px solid #e5e7eb;">
                                <th style="text-align:left; padding:10px 0; color:#6b7280; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em;">Product</th>
                                <th style="text-align:center; padding:10px 0; color:#6b7280; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em;">Qty</th>
                                <th style="text-align:right; padding:10px 0; color:#6b7280; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart as $item): ?>
                            <tr style="border-bottom:1px solid #f3f4f6;">
                                <td style="padding:12px 0;">
                                    <div style="font-weight:600; color:#1f2937;"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <?php if (!empty($item['variant_name'])): ?>
                                    <div style="font-size:0.78rem; color:#6b7280; margin-top:2px;">
                                        Variant: <?php echo htmlspecialchars($item['variant_name']); ?>
                                    </div>
                                    <?php endif; ?>
                                    <div style="font-size:0.78rem; color:#9ca3af;"><?php echo format_currency($item['price']); ?> each</div>
                                </td>
                                <td style="padding:12px 0; text-align:center; font-weight:600;"><?php echo $item['quantity']; ?></td>
                                <td style="padding:12px 0; text-align:right; font-weight:700; color:#1f2937;"><?php echo format_currency($item['price'] * $item['quantity']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" style="padding:14px 0; text-align:right; font-weight:700; font-size:1rem;">Total</td>
                                <td style="padding:14px 0; text-align:right; font-weight:800; font-size:1.1rem; color:#4F46E5;">
                                    <?php echo format_currency($subtotal); ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Notes -->
                <div class="card">
                    <h3 style="font-size:1rem; font-weight:700; margin-bottom:0.75rem; color:#1f2937;">Order Notes</h3>
                    <p style="font-size:0.85rem; color:#6b7280; margin-bottom:0.75rem;">Special size requirements, design notes, or delivery instructions.</p>
                    <form method="POST" id="checkout-form">
                        <?php echo csrf_field(); ?>
                        <textarea name="notes" rows="4" class="input-field"
                                  placeholder="e.g. Please use matte finish. Delivery by Jan 30."
                                  style="resize:vertical;"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>

                        <div style="margin-top:1rem; display:flex; gap:1rem; align-items:center;">
                            <button type="submit" class="btn-primary" style="flex:1;">
                                Place Order
                            </button>
                            <a href="cart.php" style="font-size:0.85rem; color:#6b7280;">← Edit Cart</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right: Summary Card -->
            <div class="card" style="position:sticky; top:80px;">
                <h3 style="font-size:1rem; font-weight:700; margin-bottom:1rem; color:#1f2937;">Payment Info</h3>
                <div style="font-size:0.85rem; color:#6b7280; line-height:1.6; margin-bottom:1rem;">
                    After placing your order you will be redirected to upload your <strong>proof of payment</strong>.
                    Your order will be processed once payment is verified.
                </div>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:12px;border-radius:8px;font-size:0.82rem;">
                    ✓ Secure checkout — your cart is validated server-side before any order is created.
                </div>

                <div style="border-top:1px dashed #e5e7eb; margin:1.25rem 0;"></div>
                <div style="display:flex;justify-content:space-between;font-size:1rem;font-weight:700;color:#1f2937;">
                    <span>Total Due</span>
                    <span style="color:#4F46E5;"><?php echo format_currency($subtotal); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
