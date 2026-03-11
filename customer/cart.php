<?php
/**
 * Shopping Cart Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

// Handle updates/removals
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_cart'])) {
        foreach ($_POST['quantities'] as $pid => $qty) {
            if ($qty > 0 && isset($_SESSION['cart'][$pid])) {
                $_SESSION['cart'][$pid]['quantity'] = (int)$qty;
            }
        }
    } elseif (isset($_POST['remove_item'])) {
        $pid = $_POST['remove_item'];
        unset($_SESSION['cart'][$pid]);
    }
    header("Location: cart.php");
    exit;
}

$cart_items = $_SESSION['cart'] ?? [];
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}

$page_title = 'Shopping Cart - PrintFlow';
$use_customer_css = true;

// Ensure all items have a selection state
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $pid => &$item) {
        if (!isset($item['selected'])) {
            $item['selected'] = true;
        }
    }
    unset($item);
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* Modern Circular Quantity Selector */
    .qty-control {
        display: inline-flex;
        align-items: center;
        background: #f8fafc;
        border-radius: 9999px;
        padding: 4px;
        gap: 12px;
        border: 1px solid #e2e8f0;
    }
    .qty-btn {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: none;
        background: white;
        color: #1e293b;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .qty-btn:hover:not(:disabled) {
        background: #f1f5f9;
        transform: scale(1.05);
    }
    .qty-btn:active:not(:disabled) {
        transform: scale(0.95);
    }
    .qty-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .qty-val {
        font-weight: 700;
        font-size: 0.95rem;
        color: #1e293b;
        min-width: 20px;
        text-align: center;
    }

    /* Checkbox Styling */
    .cart-checkbox {
        width: 20px;
        height: 20px;
        border-radius: 6px;
        border: 2px solid #cbd5e1;
        cursor: pointer;
        accent-color: #4f46e5;
    }

    /* Trash Icon Styling */
    .trash-btn {
        color: #ef4444;
        background: rgba(239, 68, 68, 0.05);
        border: none;
        padding: 8px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .trash-btn:hover {
        background: rgba(239, 68, 68, 0.1);
        transform: scale(1.1);
    }
</style>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width:1100px;">
        <h1 class="ct-page-title">Shopping Cart</h1>

        <?php 
        $customer_id = get_user_id();
        $cancel_count = get_customer_cancel_count($customer_id);
        $is_restricted = is_customer_restricted($customer_id);
        
        if ($is_restricted): ?>
            <div style="background-color: #fef2f2; border: 1px solid #fee2e2; border-radius: 12px; padding: 1.25rem; margin-bottom: 2rem; color: #b91c1c; font-size: 0.95rem; display: flex; align-items: center; gap: 0.75rem;">
                <span style="font-size: 1.5rem;">🚫</span>
                <div><strong>Account Restricted:</strong> You are currently blocked from placing new orders due to excessive cancellations (7+). Please contact support.</div>
            </div>
        <?php elseif ($cancel_count >= 3): ?>
            <div style="background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 1.25rem; margin-bottom: 2rem; display: flex; gap: 0.75rem; align-items: flex-start;">
                <span style="font-size: 1.5rem;">⚠️</span>
                <div>
                    <h3 style="color: #92400e; font-weight: 700; font-size: 0.95rem; margin-bottom: 0.25rem;">Shopping Experience Warning</h3>
                    <p style="color: #b45309; font-size: 0.85rem; line-height: 1.5;">
                        You have <strong><?php echo $cancel_count; ?></strong> recent cancellations. 
                        <?php if ($cancel_count >= 4): ?>
                            Because you have 4 or more cancellations, <strong>'Pay Later' orders will require a 50% downpayment</strong> to proceed.
                        <?php else: ?>
                            Excessive cancellations may lead to payment restrictions or account suspension.
                        <?php endif; ?>
                        Complete a successful order to reset this counter!
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <div class="ct-empty">
                <div class="ct-empty-icon">🛒</div>
                <p>Your cart is empty</p>
                <a href="products.php" class="btn-primary">Start Shopping</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="card" style="padding:0;">
                    <div class="overflow-x-auto">
                        <table style="width:100%; border-collapse:collapse;">
                            <thead style="background:#f9fafb; font-size:0.875rem; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;">
                                <tr>
                                    <th style="padding:1rem; text-align:center; width: 50px;">
                                        <input type="checkbox" id="selectAll" class="cart-checkbox" onchange="toggleAll(this.checked)" <?php 
                                            $all_selected = true;
                                            foreach($cart_items as $item) if(!($item['selected']??true)) $all_selected = false;
                                            echo $all_selected ? 'checked' : '';
                                        ?>>
                                    </th>
                                    <th style="padding:1rem; text-align:left;">Product</th>
                                    <th style="padding:1rem; text-align:center;">Price</th>
                                    <th style="padding:1rem; text-align:center;">Quantity</th>
                                    <th style="padding:1rem; text-align:right;">Total</th>
                                    <th style="padding:1rem; width: 60px;"></th>
                                </tr>
                            </thead>
                            <tbody style="font-size:0.95rem;">
                                <?php foreach ($cart_items as $pid => $item): 
                                    $is_selected = $item['selected'] ?? true;
                                ?>
                                    <tr class="cart-row" data-id="<?php echo $pid; ?>" data-price="<?php echo $item['price']; ?>" style="border-bottom:1px solid #f3f4f6; transition: background 0.2s; <?php echo !$is_selected ? 'opacity: 0.6; background: #fafafa;' : ''; ?>">
                                        <td style="padding:1rem; text-align:center;">
                                            <input type="checkbox" class="cart-checkbox item-checkbox" onchange="toggleItem('<?php echo $pid; ?>', this.checked)" <?php echo $is_selected ? 'checked' : ''; ?>>
                                        </td>
                                        <td style="padding:1rem; display:flex; align-items:center; gap:1rem;">
                                            <?php
                                            $prod_id = (int)($item['product_id'] ?? 0);
                                            $product_img = "";
                                            
                                            // 1. Try explicit product ID
                                            if ($prod_id > 0) {
                                                $img_base = "../public/images/products/product_" . $prod_id;
                                                if (file_exists($img_base . ".jpg")) {
                                                    $product_img = "/printflow/public/images/products/product_" . $prod_id . ".jpg";
                                                } elseif (file_exists($img_base . ".png")) {
                                                    $product_img = "/printflow/public/images/products/product_" . $prod_id . ".png";
                                                }
                                            }
                                            
                                            // 2. Fallback based on category/service_type for Service Orders
                                            if (empty($product_img)) {
                                                $cat_lower = strtolower(($item['category'] ?? '') . ' ' . ($item['name'] ?? ''));
                                                if (strpos($cat_lower, 'reflectorized') !== false || strpos($cat_lower, 'signage') !== false) {
                                                    $product_img = "/printflow/public/images/products/signage.jpg";
                                                } elseif (strpos($cat_lower, 'tarpaulin') !== false) {
                                                    $product_img = "/printflow/public/images/products/product_41.jpg";
                                                } elseif (strpos($cat_lower, 'sintraboard') !== false || strpos($cat_lower, 'standee') !== false) {
                                                    $product_img = "/printflow/public/images/services/Sintraboard Standees.jpg";
                                                } elseif (strpos($cat_lower, 't-shirt') !== false || strpos($cat_lower, 'shirt') !== false) {
                                                    $product_img = "/printflow/public/images/products/product_31.jpg";
                                                } elseif (strpos($cat_lower, 'sticker') !== false || strpos($cat_lower, 'decal') !== false) {
                                                    if (strpos($cat_lower, 'glass') !== false || strpos($cat_lower, 'frosted') !== false) {
                                                        $product_img = "/printflow/public/images/products/Glass Stickers  Wall  Frosted Stickers.png";
                                                    } else {
                                                        $product_img = "/printflow/public/images/products/product_21.jpg";
                                                    }
                                                } elseif (strpos($cat_lower, 'souvenir') !== false) {
                                                    $product_img = "/printflow/public/assets/images/icon-192.png";
                                                }
                                            }
                                            ?>
                                            <div style="width:48px; height:48px; border-radius:6px; overflow:hidden; border:1px solid #e2e8f0; background:#f8fafc; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                                <?php if (!empty($product_img)): ?>
                                                    <img src="<?php echo $product_img; ?>" style="width:100%; height:100%; object-fit:cover;" alt="Product">
                                                <?php else: ?>
                                                    <img src="/printflow/public/assets/images/icon-192.png" style="width:70%; height:70%; object-fit:contain; opacity:0.8;" alt="Logo">
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:600;"><?php echo htmlspecialchars($item['name'] ?? 'Unknown Product'); ?></div>
                                                <?php if (!empty($item['category'])): ?>
                                                    <div style="font-size:0.75rem; color:#6b7280;"><?php echo htmlspecialchars($item['category']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td style="padding:1rem; text-align:center;">
                                            <?php echo format_currency($item['price']); ?>
                                        </td>
                                        <td style="padding:1rem; text-align:center;">
                                            <div class="qty-control">
                                                <button type="button" class="qty-btn" onclick="updateQty('<?php echo $pid; ?>', -1)" <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>−</button>
                                                <span class="qty-val" id="qty-<?php echo $pid; ?>"><?php echo $item['quantity']; ?></span>
                                                <button type="button" class="qty-btn" onclick="updateQty('<?php echo $pid; ?>', 1)">+</button>
                                            </div>
                                        </td>
                                        <td style="padding:1rem; text-align:right; font-weight:600;" id="total-<?php echo $pid; ?>">
                                            <?php echo format_currency($item['price'] * $item['quantity']); ?>
                                        </td>
                                        <td style="padding:1rem; text-align:center;">
                                            <button type="button" class="trash-btn" onclick="confirmRemove('<?php echo $pid; ?>')" title="Remove">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="padding:1.5rem; background:#f9fafb; display:flex; justify-content:space-between; align-items:center;">
                        <a href="products.php" class="btn-secondary" style="background:#fff; border:1px solid #d1d5db; padding:0.5rem 1.25rem; border-radius:6px; font-weight: 500; text-decoration: none; color: #374151;">Continue Shopping</a>
                        
                        <div style="text-align:right;">
                            <div style="font-size:0.875rem; color:#6b7280; margin-bottom:0.25rem;">Subtotal</div>
                            <div style="font-size:1.5rem; font-weight:700; color:#1f2937; margin-bottom:1rem;" id="cart-total"><?php echo format_currency($total); ?></div>
                            <?php if ($is_restricted): ?>
                                <button type="button" class="btn-primary" style="padding:0.75rem 2rem; opacity:0.5; cursor:not-allowed;" disabled>Proceed to Checkout</button>
                            <?php else: ?>
                                <a href="checkout.php" id="checkout-btn" class="btn-primary" style="padding:0.75rem 2rem; <?php echo $total <= 0 ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">Proceed to Checkout</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Remove Confirmation Modal -->
<div id="removeModal" style="display:none; position:fixed; inset:0; z-index:50; align-items:center; justify-content:center;">
    <div style="position:absolute; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(2px);" onclick="closeRemoveModal()"></div>
    <div style="position:relative; background:white; padding:2rem; border-radius:12px; max-width:400px; width:90%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); z-index:51;">
        <h3 style="font-size:1.25rem; font-weight:700; color:#111827; margin-bottom:0.5rem;">Remove from Cart?</h3>
        <p style="color:#4b5563; margin-bottom:1.5rem; line-height:1.5;">Are you sure you want to remove this item from your shopping cart?</p>
        <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
            <button type="button" onclick="closeRemoveModal()" style="padding:0.5rem 1.25rem; border-radius:8px; background:#f1f5f9; color:#475569; font-weight:600; border:none; cursor:pointer; transition:background 0.2s;">Cancel</button>
            <form method="POST" id="removeForm" style="margin:0;">
                <input type="hidden" name="remove_item" id="removeItemId" value="">
                <button type="submit" style="padding:0.5rem 1.25rem; border-radius:8px; background:#ef4444; color:white; font-weight:600; border:none; cursor:pointer; transition:background 0.2s;">Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
const PF_CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';

function confirmRemove(pid) {
    document.getElementById('removeItemId').value = pid;
    document.getElementById('removeModal').style.display = 'flex';
}
function closeRemoveModal() {
    document.getElementById('removeModal').style.display = 'none';
    document.getElementById('removeItemId').value = '';
}

async function updateQty(pid, delta) {
    const span = document.getElementById(`qty-${pid}`);
    if (!span) return;
    
    let currentQty = parseInt(span.textContent);
    let newQty = currentQty + delta;
    if (newQty < 1) return;
    
    // Optimistic UI
    span.textContent = newQty;
    const row = document.querySelector(`.cart-row[data-id="${pid}"]`);
    const price = parseFloat(row.dataset.price);
    const lineTotalSpan = document.getElementById(`total-${pid}`);
    lineTotalSpan.textContent = PHP(price * newQty);
    
    // Disable/Enable minus button
    const minusBtn = row.querySelector('.qty-btn:first-child');
    minusBtn.disabled = (newQty <= 1);
    
    recalculateTotal();

    try {
        const res = await fetch('api_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update',
                cart_key: pid,
                quantity: newQty,
                csrf_token: PF_CSRF_TOKEN
            })
        });
        const data = await res.json();
        if (!data.success) {
            alert(data.message || 'Failed to update quantity');
            // Revert on error
            span.textContent = currentQty;
            recalculateTotal();
        } else {
             // Update global count if needed
             if (window.updateCartBadge) updateCartBadge(data.cart_count);
        }
    } catch (err) {
        console.error(err);
    }
}

async function toggleItem(pid, selected) {
    const row = document.querySelector(`.cart-row[data-id="${pid}"]`);
    if (selected) {
        row.style.opacity = '1';
        row.style.background = 'transparent';
    } else {
        row.style.opacity = '0.6';
        row.style.background = '#fafafa';
    }
    
    checkSelectAllState();
    recalculateTotal();
    
    try {
        await fetch('api_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'toggle_select',
                cart_key: pid,
                selected: selected,
                csrf_token: PF_CSRF_TOKEN
            })
        });
    } catch (err) { console.error(err); }
}

async function toggleAll(selected) {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = selected;
        const pid = cb.closest('.cart-row').dataset.id;
        const row = cb.closest('.cart-row');
        if (selected) {
            row.style.opacity = '1';
            row.style.background = 'transparent';
        } else {
            row.style.opacity = '0.6';
            row.style.background = '#fafafa';
        }
    });
    
    recalculateTotal();
    
    try {
        await fetch('api_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'select_all',
                selected: selected,
                csrf_token: PF_CSRF_TOKEN
            })
        });
    } catch (err) { console.error(err); }
}

function checkSelectAllState() {
    const all = document.querySelectorAll('.item-checkbox');
    const checked = document.querySelectorAll('.item-checkbox:checked');
    document.getElementById('selectAll').checked = (all.length === checked.length);
}

function recalculateTotal() {
    let subtotal = 0;
    const rows = document.querySelectorAll('.cart-row');
    rows.forEach(row => {
        const checkbox = row.querySelector('.item-checkbox');
        if (checkbox.checked) {
            const pid = row.dataset.id;
            const price = parseFloat(row.dataset.price);
            const qty = parseInt(document.getElementById(`qty-${pid}`).textContent);
            subtotal += price * qty;
        }
    });
    
    document.getElementById('cart-total').textContent = PHP(subtotal);
    
    // Disable/Enable checkout button
    const checkoutBtn = document.getElementById('checkout-btn');
    if (checkoutBtn) {
        if (subtotal <= 0) {
            checkoutBtn.style.opacity = '0.5';
            checkoutBtn.style.pointerEvents = 'none';
        } else {
            checkoutBtn.style.opacity = '1';
            checkoutBtn.style.pointerEvents = 'auto';
        }
    }
}

function PHP(amount) {
    return 'PHP ' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

