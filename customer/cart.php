<?php
/**
 * Customer Cart Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart     = $_SESSION['cart'];
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$page_title      = 'My Cart - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Hero Banner -->
<div style="background:#00151b;position:relative;overflow:hidden;padding:2.75rem 0 3.5rem;">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:700px;height:220px;background:radial-gradient(ellipse at center,rgba(50,161,196,0.18) 0%,rgba(83,197,224,0.06) 50%,transparent 75%);pointer-events:none;z-index:0;"></div>
    <div class="container mx-auto px-4" style="max-width:900px;position:relative;z-index:1;text-align:center;">
        <p style="font-size:0.7rem;font-weight:700;color:rgba(83,197,224,0.8);text-transform:uppercase;letter-spacing:.12em;margin:0 0 .6rem;">Shopping</p>
        <h1 style="font-size:clamp(1.75rem,3.5vw,2.75rem);font-weight:800;color:#fff;letter-spacing:-0.03em;margin:0 0 .75rem;line-height:1.1;">
            My Cart<?php if (!empty($cart)): ?> <span style="font-size:1rem;font-weight:500;color:rgba(255,255,255,0.4);">(<?php echo count($cart); ?> item<?php echo count($cart) !== 1 ? 's' : ''; ?>)</span><?php endif; ?>
        </h1>
        <p style="font-size:0.9rem;color:rgba(255,255,255,0.45);max-width:420px;margin:0 auto;line-height:1.65;">Review your selected items and proceed to checkout.</p>
    </div>
</div>

<div class="min-h-screen" style="background:#f5f9fa;padding-top:2.5rem;padding-bottom:3rem;">
    <div class="container mx-auto px-4" style="max-width:900px;">

        <?php if (empty($cart)): ?>
            <div class="ct-empty">
                <div class="ct-empty-icon">🛒</div>
                <p>Your cart is empty</p>
                <a href="products.php" class="btn-primary" style="margin-top:1rem;">Browse Products</a>
            </div>
        <?php else: ?>
            <!-- Alert area for AJAX messages -->
            <div id="cart-alert" style="display:none; margin-bottom:1rem;"></div>

            <div style="display:grid; grid-template-columns:1fr 300px; gap:1.5rem; align-items:start;">
                <!-- Cart Items -->
                <div class="card" style="padding:0; overflow:hidden;">
                    <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                        <thead>
                            <tr style="background:#f9fafb; border-bottom:2px solid #e5e7eb;">
                                <th style="text-align:left; padding:14px 16px; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;">Product</th>
                                <th style="text-align:center; padding:14px 16px; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;">Qty</th>
                                <th style="text-align:right; padding:14px 16px; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;">Price</th>
                                <th style="text-align:right; padding:14px 16px; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;">Subtotal</th>
                                <th style="padding:14px 16px;"></th>
                            </tr>
                        </thead>
                        <tbody id="cart-tbody">
                            <?php foreach ($cart as $cart_key => $item): ?>
                            <tr class="cart-row" id="row-<?php echo htmlspecialchars($cart_key); ?>"
                                style="border-bottom:1px solid #f3f4f6;">
                                <td style="padding:16px;">
                                    <div style="font-weight:600; color:#1f2937;">
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                    </div>
                                    <?php if (!empty($item['variant_name'])): ?>
                                    <div style="font-size:0.78rem; color:#6b7280; margin-top:3px;">
                                        Variant: <?php echo htmlspecialchars($item['variant_name']); ?>
                                    </div>
                                    <?php endif; ?>
                                    <div style="font-size:0.78rem; color:#9ca3af; margin-top:2px;">
                                        <?php echo format_currency($item['price']); ?> each
                                    </div>
                                </td>
                                <td style="padding:16px; text-align:center;">
                                    <div style="display:flex; align-items:center; justify-content:center; gap:8px;">
                                        <button class="qty-btn" data-key="<?php echo htmlspecialchars($cart_key); ?>"
                                                data-delta="-1"
                                                style="width:28px;height:28px;border-radius:6px;border:1px solid #d1d5db;background:#fff;cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;">−</button>
                                        <span class="qty-val" id="qty-<?php echo htmlspecialchars($cart_key); ?>"
                                              style="font-weight:600; min-width:28px; text-align:center;">
                                            <?php echo $item['quantity']; ?>
                                        </span>
                                        <button class="qty-btn" data-key="<?php echo htmlspecialchars($cart_key); ?>"
                                                data-delta="1"
                                                style="width:28px;height:28px;border-radius:6px;border:1px solid #d1d5db;background:#fff;cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;">+</button>
                                    </div>
                                </td>
                                <td style="padding:16px; text-align:right; color:#6b7280;">
                                    <?php echo format_currency($item['price']); ?>
                                </td>
                                <td style="padding:16px; text-align:right; font-weight:600; color:#1f2937;"
                                    id="sub-<?php echo htmlspecialchars($cart_key); ?>">
                                    <?php echo format_currency($item['price'] * $item['quantity']); ?>
                                </td>
                                <td style="padding:16px; text-align:right;">
                                    <button class="remove-btn" data-key="<?php echo htmlspecialchars($cart_key); ?>"
                                            title="Remove"
                                            style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:18px;">✕</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Order Summary -->
                <div class="card" style="position:sticky;top:80px;">
                    <h3 style="font-size:1rem; font-weight:700; margin-bottom:1rem; color:#1f2937;">Order Summary</h3>
                    <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem; font-size:0.9rem; color:#6b7280;">
                        <span>Subtotal</span>
                        <span id="grand-subtotal" style="color:#1f2937; font-weight:600;">
                            <?php echo format_currency($subtotal); ?>
                        </span>
                    </div>
                    <div style="border-top:1px dashed #e5e7eb; margin:1rem 0;"></div>
                    <div style="display:flex; justify-content:space-between; font-size:1rem; font-weight:700; color:#1f2937; margin-bottom:1.25rem;">
                        <span>Total</span>
                        <span id="grand-total"><?php echo format_currency($subtotal); ?></span>
                    </div>
                    <a href="checkout.php" class="btn-primary" style="display:block; text-align:center;">
                        Proceed to Checkout
                    </a>
                    <a href="products.php" style="display:block; text-align:center; margin-top:0.75rem; font-size:0.85rem; color:#6b7280;">
                        ← Continue Shopping
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const CSRF = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
const cartPrices = <?php
    $prices = [];
    foreach ($cart as $k => $item) {
        $prices[$k] = $item['price'];
    }
    echo json_encode($prices);
?>;

function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function showAlert(msg, type = 'error') {
    const el = document.getElementById('cart-alert');
    if (!el) return;
    el.innerHTML = msg;
    el.style.display = 'block';
    el.style.cssText = `display:block;padding:12px 16px;border-radius:8px;font-size:0.875rem;margin-bottom:1rem;` +
        (type === 'success'
            ? 'background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;'
            : 'background:#fef2f2;border:1px solid #fecaca;color:#991b1b;');
    setTimeout(() => el.style.display = 'none', 3000);
}

function updateCart(action, cartKey, qty) {
    return fetch('/printflow/customer/api_cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action, cart_key: cartKey, quantity: qty, csrf_token: CSRF})
    }).then(r => r.json());
}

// Qty buttons
document.querySelectorAll('.qty-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const key      = btn.dataset.key;
        const delta    = parseInt(btn.dataset.delta);
        const qtyEl   = document.getElementById('qty-' + key);
        const newQty   = Math.max(0, parseInt(qtyEl.textContent) + delta);
        const data     = await updateCart('update', key, newQty);
        if (data.success) {
            if (newQty === 0) {
                document.getElementById('row-' + key)?.remove();
                if (!document.querySelector('.cart-row')) location.reload();
            } else {
                qtyEl.textContent = newQty;
                const subEl = document.getElementById('sub-' + key);
                if (subEl) subEl.textContent = formatCurrency((cartPrices[key] || 0) * newQty);
                recalcTotal();
            }
            updateBadge(data.cart_count);
        }
    });
});

// Remove buttons
document.querySelectorAll('.remove-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const key  = btn.dataset.key;
        const data = await updateCart('remove', key, 0);
        if (data.success) {
            document.getElementById('row-' + key)?.remove();
            updateBadge(data.cart_count);
            if (!document.querySelector('.cart-row')) location.reload();
            recalcTotal();
        }
    });
});

function recalcTotal() {
    let total = 0;
    document.querySelectorAll('.cart-row').forEach(row => {
        const key   = row.id.replace('row-', '');
        const qty   = parseInt(document.getElementById('qty-' + key)?.textContent || 0);
        const price = cartPrices[key] || 0;
        total += price * qty;
    });
    const fmt = formatCurrency(total);
    const sub = document.getElementById('grand-subtotal');
    const tot = document.getElementById('grand-total');
    if (sub) sub.textContent = fmt;
    if (tot) tot.textContent = fmt;
}

function updateBadge(count) {
    document.querySelectorAll('.cart-badge').forEach(el => {
        el.textContent = count;
        el.style.display = count > 0 ? 'flex' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
