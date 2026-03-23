<?php
/**
 * Customer Products Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

// Get filter parameters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build query — match admin catalog for customers: show all Activated products (fixed + custom).
// Deactivated / Archived are excluded so only “live” admin rows appear here.
$sql = "SELECT p.*, (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.product_id AND pv.status = 'Active') as variant_count FROM products p WHERE p.status = 'Activated'";
$params = [];
$types = '';

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= 's';
}

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

// Pagination settings
$items_per_page = 12;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

// Count total items for pagination
$count_sql = "SELECT COUNT(*) as total FROM products WHERE status = 'Activated'";
$count_params = [];
$count_types = '';

if (!empty($category)) {
    $count_sql .= " AND category = ?";
    $count_params[] = $category;
    $count_types .= 's';
}

if (!empty($search)) {
    $count_sql .= " AND (name LIKE ? OR description LIKE ?)";
    $count_params[] = '%' . $search . '%';
    $count_params[] = '%' . $search . '%';
    $count_types .= 'ss';
}

$total_result = db_query($count_sql, $count_types, $count_params);
$total_items = $total_result[0]['total'] ?? 0;
$total_pages = ceil($total_items / $items_per_page);

$sql .= " ORDER BY name ASC LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$types .= 'ii';

$products = db_query($sql, $types, $params);

$page_title = 'Products - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width:1100px;">
        <h1 class="ct-page-title">Browse Products</h1>

        <!-- Filters -->
        <div class="mb-8 mt-4">
            <form method="GET" style="display:flex; gap:0.75rem; align-items:center; max-width: 33.33%;">
                <div style="flex-grow: 1;">
                    <input type="text" name="search" class="input-field" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>" style="width: 100%; border-radius: 8px;">
                </div>
                <button type="submit" class="btn-primary" style="height:42px; padding: 0 1.5rem; border-radius: 8px;">Search</button>
            </form>
        </div>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
            <div class="ct-empty">
                <div class="ct-empty-icon">📦</div>
                <p>No products found</p>
            </div>
        <?php else: ?>
            <div class="ct-product-grid">
                <?php foreach ($products as $product): 
                    // Prefer admin-uploaded photo_path, then legacy product_image, then files on disk
                    $display_img = "";
                    if (!empty($product['photo_path'])) {
                        $display_img = $product['photo_path'];
                        if ($display_img !== '' && $display_img[0] !== '/') {
                            $display_img = '/' . ltrim($display_img, '/');
                        }
                    } elseif (!empty($product['product_image'])) {
                        $display_img = $product['product_image'];
                    } else {
                        // Fall back to old method of checking public/images/products directory
                        $img_path = "../public/images/products/product_" . $product['product_id'];
                        if (file_exists($img_path . ".jpg")) {
                            $display_img = "/printflow/public/images/products/product_" . $product['product_id'] . ".jpg";
                        } elseif (file_exists($img_path . ".png")) {
                            $display_img = "/printflow/public/images/products/product_" . $product['product_id'] . ".png";
                        }
                    }
                    if (!$display_img) $display_img = "/printflow/public/assets/images/placeholder.png";

                    $order_link = "order_create.php?product_id=" . $product['product_id'];
                    $esc_name = addslashes($product['name']);
                    $esc_cat = addslashes($product['category']);
                    $esc_img = addslashes($display_img);
                    $esc_link = addslashes($order_link);
                    $formatted_price = format_currency($product['price']);
                    $stock_count = (int)$product['stock_quantity'];
                    $has_variants = (int)($product['variant_count'] ?? 0) > 0;
                ?>
                    <div class="ct-product-card" onclick="openProductModal('<?php echo $esc_name; ?>', '<?php echo $esc_cat; ?>', '<?php echo $esc_img; ?>', '<?php echo $esc_link; ?>', <?php echo $product['price']; ?>, <?php echo $stock_count; ?>)" style="cursor: pointer;">
                        <div class="ct-product-img">
                            <div class="ct-product-img-inner">
                                <img src="<?php echo $display_img; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width:100%; height:100%; object-fit:cover; border-radius:0.5rem;">
                            </div>
                        </div>
                        <div class="ct-product-body" <?php echo ($product['category'] === 'Decals & Stickers') ? 'style="text-align: center;"' : ''; ?>>
                            <?php if ($product['category'] !== 'Decals & Stickers'): ?>
                                <span class="ct-product-category"><?php echo htmlspecialchars($product['category']); ?></span>
                            <?php endif; ?>
                            
                            <h3 class="ct-product-name" <?php echo ($product['category'] === 'Decals & Stickers') ? 'style="margin-bottom: 1.5rem; height: auto; font-weight: 700; font-size: 1.1rem;"' : ''; ?>><?php echo htmlspecialchars($product['name']); ?></h3>
                            
                            <?php if ($product['category'] !== 'Decals & Stickers'): ?>
                                <p class="ct-product-price"><?php echo format_currency($product['price']); ?></p>
                            <?php endif; ?>

                            <div class="ct-product-actions" <?php echo ($product['category'] === 'Decals & Stickers') ? 'style="display: flex; justify-content: center;"' : ''; ?>>
                                <?php if ($product['category'] !== 'Decals & Stickers'): ?>
                                    <div style="display:flex; align-items:center;">
                                        <?php if ($product['stock_quantity'] > 0): ?>
                                            <button 
                                                title="<?php echo $has_variants ? 'View Options' : 'Buy Now'; ?>" 
                                                onclick="event.stopPropagation(); this.closest('.ct-product-card').click();"
                                                style="background:none; border:none; padding:8px; cursor:pointer; color:#4F46E5; display:flex; align-items:center; justify-content:center; border-radius:8px; transition:all 0.2s; background: rgba(79, 70, 229, 0.05);"
                                                onmouseover="this.style.background='rgba(79, 70, 229, 0.1)'; this.style.transform='scale(1.1)'"
                                                onmouseout="this.style.background='rgba(79, 70, 229, 0.05)'; this.style.transform='scale(1)'"
                                            >
                                                <?php if ($has_variants): ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" style="width:24px; height:24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                    </svg>
                                                <?php else: ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" style="width:24px; height:24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                                    </svg>
                                                <?php endif; ?>
                                            </button>
                                        <?php else: ?>
                                            <div style="padding:8px; color:#cbd5e1; cursor:not-allowed;">
                                                <svg xmlns="http://www.w3.org/2000/svg" style="width:24px; height:24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <a href="<?php echo $order_link; ?>" 
                                   onclick="event.stopPropagation();" 
                                   class="ct-view-product-btn" 
                                   style="width: 100%; text-align: center; text-decoration: none;">
                                    BUY NOW
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <div class="mt-8">
                <?php echo get_pagination_links($current_page, $total_pages, ['category' => $category, 'search' => $search]); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Product Detail Modal -->
<div id="product-modal" style="display: none; position: fixed; inset: 0; align-items: center; justify-content: center; z-index: 9999999; padding: 1.5rem; transition: opacity 0.2s ease;">
    <!-- Backdrop (Soft dark tint to highlight modal) -->
    <div onclick="closeProductModal()" style="position: absolute; inset: 0; background-color: rgba(0, 0, 0, 0.45);"></div>
    
    <!-- Modal Content (Wider fixed size with internal scroll) -->
    <div id="product-modal-content" style="position: relative; background-color: #ffffff; border-radius: 1.5rem; width: 750px; max-width: 100%; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); transform: translateY(20px); transition: all 0.3s ease;">
        
        <style>
            /* Modal Internal Scrollbar */
            #product-modal-scroll-body::-webkit-scrollbar { width: 6px; }
            #product-modal-scroll-body::-webkit-scrollbar-track { background: transparent; }
            #product-modal-scroll-body::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
            #product-modal-scroll-body::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
        </style>
        
        <!-- Close Button -->
        <button onclick="closeProductModal()" style="position: absolute; top: 1rem; right: 1rem; z-index: 100; padding: 0.5rem; background: #ffffff; border-radius: 9999px; border: none; cursor: pointer; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center;">
            <svg style="width: 1.5rem; height: 1.5rem; color: #1f2937;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <!-- Scrollable Body Section -->
        <div id="product-modal-scroll-body" style="overflow-y: auto; flex: 1; display: flex; flex-direction: column;">
            <!-- Image Section (Matched Aspect Ratio) -->
            <div style="width: 100%; height: 420px; position: relative; background: #f3f4f6; flex-shrink: 0;">
                <img id="modal-img" src="" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                <div style="position: absolute; top: 1.25rem; left: 1.25rem; z-index: 10;">
                    <span id="modal-category" style="padding: 0.35rem 0.85rem; background: #ffffff; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; border-radius: 0.5rem; color: #4F46E5; box-shadow: 0 4px 6px rgba(0,0,0,0.1); letter-spacing: 0.05em;">Category</span>
                </div>
            </div>

            <!-- Info Section (Same as Card Body) -->
            <div style="padding: 2.25rem; display: flex; flex-direction: column; background: #ffffff;">
                <h2 id="modal-name" style="font-size: 1.75rem; font-weight: 800; color: #111827; margin: 0 0 0.85rem 0; line-height: 1.2;">Product Name</h2>
                
                <div id="modal-price-container" style="margin-bottom: 1.25rem;">
                    <p id="modal-price" style="font-size: 1.5rem; font-weight: 800; color: #111827; margin: 0;"></p>
                    <div id="modal-stock" style="margin-top: 0.5rem; font-size: 0.85rem; font-weight: 600;"></div>
                </div>

                <p style="color: #4b5563; margin-bottom: 2rem; line-height: 1.7; font-size: 0.95rem;">
                    Select this product and proceed to place your order. For custom designs and sizes, visit our Services page.
                </p>

                <div style="margin-top: auto; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                    <a id="modal-action-btn" href="#" style="display: flex; align-items: center; justify-content: center; padding: 1.15rem 2rem; background: #111827; color: #ffffff; font-weight: 700; border-radius: 0.75rem; text-decoration: none; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.15); font-size: 1rem;">
                        BUY NOW
                        <svg style="width: 1.25rem; height: 1.25rem; margin-left: 0.75rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openProductModal(name, category, img, link, price, stock) {
    document.getElementById('modal-name').textContent = name || '';
    document.getElementById('modal-category').textContent = category || '';
    document.getElementById('modal-img').src = img || '';
    document.getElementById('modal-action-btn').href = link || '#';
    
    // Format price correctly
    const formattedPrice = new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
    }).format(price);
    
    document.getElementById('modal-price').textContent = formattedPrice;
    
    const stockEl = document.getElementById('modal-stock');
    if (stock > 0) {
        stockEl.innerHTML = '<span style="color: #10B981;">✓ In Stock (' + stock + ' available)</span>';
    } else {
        stockEl.innerHTML = '<span style="color: #EF4444;">✕ Out of Stock</span>';
    }
    
    const modal = document.getElementById('product-modal');
    const content = document.getElementById('product-modal-content');
    
    modal.style.display = 'flex';
    void modal.offsetWidth;
    
    modal.style.opacity = '1';
    modal.style.pointerEvents = 'auto';
    content.style.transform = 'translateY(0)';
    
    document.body.style.overflow = 'hidden';
}

function closeProductModal() {
    const modal = document.getElementById('product-modal');
    const content = document.getElementById('product-modal-content');
    
    modal.style.opacity = '0';
    modal.style.pointerEvents = 'none';
    content.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }, 300);
}
</script>

<script>
const PF_CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';

async function addToCart(productId) {
    try {
        const response = await fetch('api_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add',
                product_id: productId,
                quantity: 1,
                csrf_token: PF_CSRF_TOKEN
            })
        });
        
        const data = await response.json();
        if (data.success) {
            updateCartBadge(data.cart_count);
            // Optional: Show a small toast notification
            showToast('Added to cart!');
        } else {
            alert(data.message || 'Failed to add to cart');
        }
    } catch (err) {
        console.error('Cart Error:', err);
    }
}

function updateCartBadge(count) {
    const badge = document.getElementById('cart-count-badge');
    if (!badge) return;
    
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'flex';
        // Add a little pop animation
        badge.animate([
            { transform: 'scale(1)' },
            { transform: 'scale(1.3)' },
            { transform: 'scale(1)' }
        ], { duration: 300 });
    } else {
        badge.style.display = 'none';
    }
}

function showToast(msg) {
    let toast = document.getElementById('pf-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'pf-toast';
        document.body.appendChild(toast);
    }
    
    toast.textContent = msg;
    toast.style.cssText = `
        position: fixed;
        bottom: 2rem;
        left: 50%;
        transform: translateX(-50%);
        background: #1e293b;
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 9999px;
        font-weight: 600;
        font-size: 0.875rem;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        z-index: 1000;
        transition: all 0.3s;
        opacity: 0;
    `;
    
    setTimeout(() => toast.style.opacity = '1', 10);
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 2000);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

