<?php
/**
 * Customer Products Page
 * PrintFlow - Printing Shop PWA
 * Updated to match public products/ interface + cart/variant support
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

// Get filter parameters
$category = $_GET['category'] ?? '';
$search   = $_GET['search'] ?? '';

// Build query
$sql    = "SELECT * FROM products WHERE status = 'Activated'";
$params = [];
$types  = '';

if (!empty($category)) {
    $sql    .= " AND category = ?";
    $params[] = $category;
    $types   .= 's';
}

if (!empty($search)) {
    $sql        .= " AND (name LIKE ? OR description LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[]    = $search_term;
    $params[]    = $search_term;
    $types       .= 'ss';
}

$sql .= " ORDER BY name ASC";

$products   = db_query($sql, $types, $params) ?: [];
$categories = db_query("SELECT DISTINCT category FROM products WHERE status = 'Activated' ORDER BY category ASC") ?: [];

// Load active variants for all displayed products in one query
$variants_by_product = [];
if (!empty($products)) {
    $pids         = array_column($products, 'product_id');
    $placeholders = implode(',', array_fill(0, count($pids), '?'));
    $type_str     = str_repeat('i', count($pids));
    $all_variants = db_query(
        "SELECT variant_id, product_id, variant_name, price
         FROM product_variants
         WHERE product_id IN ($placeholders) AND status = 'Active'
         ORDER BY price ASC",
        $type_str, $pids
    ) ?: [];
    foreach ($all_variants as $v) {
        $variants_by_product[$v['product_id']][] = $v;
    }
}

// Cart count from session
$cart_count = 0;
if (!empty($_SESSION['cart'])) {
    $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
}

$page_title      = 'Products - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Hero Banner -->
<div style="background:#00151b;position:relative;overflow:hidden;padding:2.75rem 0 3.5rem;">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:700px;height:220px;background:radial-gradient(ellipse at center,rgba(50,161,196,0.18) 0%,rgba(83,197,224,0.06) 50%,transparent 75%);pointer-events:none;z-index:0;"></div>
    <div class="container mx-auto px-4" style="max-width:1100px;position:relative;z-index:1;text-align:center;">
        <p style="font-size:0.7rem;font-weight:700;color:rgba(83,197,224,0.8);text-transform:uppercase;letter-spacing:.12em;margin:0 0 .6rem;">&#10022; Our Catalog</p>
        <h1 style="font-size:clamp(1.75rem,3.5vw,2.75rem);font-weight:800;color:#fff;letter-spacing:-0.03em;margin:0 0 .75rem;line-height:1.1;">Browse Our <span style="color:rgba(83,197,224,0.9);">Products</span></h1>
        <p style="font-size:0.9rem;color:rgba(255,255,255,0.45);max-width:480px;margin:0 auto;line-height:1.65;">From tarpaulins to T-shirts, stickers to signage — find the perfect print solution for your next project.</p>
    </div>
</div>
<div id="toast"
     style="display:none;position:fixed;bottom:24px;right:24px;z-index:9999;
            background:#1f2937;color:#fff;padding:14px 20px;border-radius:10px;
            font-size:0.875rem;box-shadow:0 10px 25px rgba(0,0,0,.25);
            align-items:center;gap:10px;max-width:300px;">
    <span id="toast-msg"></span>
</div>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="container mx-auto px-4">

        <!-- Filters -->
        <div class="card mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" id="search" name="search" class="input-field"
                           placeholder="Search products..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select id="category" name="category" class="input-field">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                    <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="btn-primary w-full">Apply Filters</button>
                </div>
            </form>
        </div>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
            <div class="text-center py-12">
                <p class="text-gray-600 text-lg">No products found</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach ($products as $product):
                    $pid      = $product['product_id'];
                    $variants = $variants_by_product[$pid] ?? [];
                    $has_vars = !empty($variants);
                ?>
                    <div class="card hover:shadow-lg transition" id="card-<?php echo $pid; ?>">
                        <!-- Product Image -->
                        <div style="background:#f3f4f6;height:192px;border-radius:8px;margin-bottom:1rem;
                                    display:flex;align-items:center;justify-content:center;
                                    overflow:hidden;position:relative;">
                            <?php if (!empty($product['product_image'])): ?>
                                <img src="/printflow/public/assets/uploads/products/<?php echo htmlspecialchars($product['product_image']); ?>"
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            <?php endif; ?>
                            <?php if (!empty($product['is_featured'])): ?>
                                <span style="position:absolute;top:10px;right:10px;background:#fbbf24;color:white;
                                             padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700;">FEATURED</span>
                            <?php endif; ?>
                        </div>

                        <!-- Product Info -->
                        <div>
                            <div class="mb-2">
                                <span class="badge bg-indigo-100 text-indigo-800">
                                    <?php echo htmlspecialchars($product['category']); ?>
                                </span>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </h3>
                            <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                                <?php echo htmlspecialchars(substr($product['description'], 0, 120)); ?>...
                            </p>

                            <?php if ($has_vars): ?>
                                <!-- Variant dropdown -->
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Size / Variant</label>
                                    <select class="input-field variant-select" id="var-<?php echo $pid; ?>">
                                        <option value="" data-price="">— Choose variant —</option>
                                        <?php foreach ($variants as $v): ?>
                                            <option value="<?php echo $v['variant_id']; ?>"
                                                    data-price="<?php echo $v['price']; ?>">
                                                <?php echo htmlspecialchars($v['variant_name']); ?>
                                                — <?php echo format_currency($v['price']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- Dynamic price display -->
                                <div class="flex items-center justify-between mb-4">
                                    <span class="text-2xl font-bold text-indigo-600" id="price-<?php echo $pid; ?>"
                                          style="color:#9ca3af;font-size:1rem;">Select a variant</span>
                                </div>
                            <?php else: ?>
                                <!-- No variants: fixed price -->
                                <div class="flex items-center justify-between mb-4">
                                    <span class="text-2xl font-bold text-indigo-600">
                                        <?php echo format_currency($product['price']); ?>
                                    </span>
                                    <?php if ($product['stock_quantity'] > 0): ?>
                                        <span class="text-sm text-green-600 font-medium">✓ In Stock</span>
                                    <?php else: ?>
                                        <span class="text-sm text-red-600 font-medium">✕ Out of Stock</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Quantity + Add to Cart -->
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <label class="text-sm font-medium text-gray-700">Qty:</label>
                                    <input type="number" min="1" value="1" id="qty-<?php echo $pid; ?>"
                                           style="width:72px;padding:6px 10px;border:1px solid #d1d5db;border-radius:7px;
                                                  font-size:0.875rem;text-align:center;">
                                </div>
                                <button class="btn-primary w-full add-to-cart"
                                        data-product-id="<?php echo $pid; ?>"
                                        data-has-variants="<?php echo $has_vars ? '1' : '0'; ?>"
                                        <?php echo (!$has_vars && $product['stock_quantity'] <= 0) ? 'disabled' : ''; ?>>
                                    🛒 Add to Cart
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const CSRF = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;

function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
}

// Update price display when variant changes
document.querySelectorAll('.variant-select').forEach(sel => {
    sel.addEventListener('change', () => {
        const pid     = sel.id.replace('var-', '');
        const opt     = sel.options[sel.selectedIndex];
        const priceEl = document.getElementById('price-' + pid);
        if (!priceEl) return;
        const price = parseFloat(opt.dataset.price);
        if (isNaN(price)) {
            priceEl.style.color    = '#9ca3af';
            priceEl.style.fontSize = '1rem';
            priceEl.textContent    = 'Select a variant';
        } else {
            priceEl.style.color    = '#4F46E5';
            priceEl.style.fontSize = '1.5rem';
            priceEl.textContent    = formatCurrency(price);
        }
    });
});

// Add to cart
document.querySelectorAll('.add-to-cart').forEach(btn => {
    btn.addEventListener('click', async () => {
        const pid        = btn.dataset.productId;
        const hasVars    = btn.dataset.hasVariants === '1';
        const varSel     = document.getElementById('var-' + pid);
        const qtySel     = document.getElementById('qty-' + pid);
        const variant_id = varSel ? varSel.value : '';
        const quantity   = parseInt(qtySel?.value || 1);

        if (hasVars && !variant_id) {
            showToast('Please select a variant first.', 'error');
            varSel?.focus();
            return;
        }

        btn.disabled    = true;
        btn.textContent = 'Adding…';

        try {
            const resp = await fetch('/printflow/customer/api_cart.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action:      'add',
                    product_id:  parseInt(pid),
                    variant_id:  variant_id || null,
                    quantity:    quantity,
                    csrf_token:  CSRF
                })
            });
            const data = await resp.json();
            if (data.success) {
                showToast('✓ ' + data.message, 'success');
                updateBadge(data.cart_count);
            } else {
                showToast(data.message || 'Failed to add item.', 'error');
            }
        } catch (e) {
            showToast('Network error.', 'error');
        }

        btn.disabled    = false;
        btn.textContent = '🛒 Add to Cart';
    });
});

function showToast(msg, type = 'success') {
    const toast = document.getElementById('toast');
    const msgEl = document.getElementById('toast-msg');
    if (!toast || !msgEl) return;
    msgEl.textContent  = msg;
    toast.style.background = type === 'success' ? '#166534' : '#991b1b';
    toast.style.display    = 'flex';
    clearTimeout(toast._t);
    toast._t = setTimeout(() => toast.style.display = 'none', 3000);
}

function updateBadge(count) {
    document.querySelectorAll('.cart-badge').forEach(el => {
        el.textContent   = count;
        el.style.display = count > 0 ? 'flex' : 'none';
    });
    const badge = document.getElementById('cart-badge');
    if (badge) {
        badge.textContent   = count;
        badge.style.display = count > 0 ? 'flex' : 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
