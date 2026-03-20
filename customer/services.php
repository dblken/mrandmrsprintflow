<?php
/**
 * Customer Services
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require customer access
require_role('Customer');

$customer_id = get_user_id();
$csrf_token = generate_csrf_token();

$featured_products = db_query("
    SELECT * FROM products
    WHERE product_type = 'custom' 
    AND category IN ('Decals', 'Stickers', 'Decals & Stickers', 'Merchandise')
    AND status = 'Activated'
    ORDER BY name ASC
", '', []);

$tshirt_products = db_query("
    SELECT * FROM products
    WHERE product_type = 'custom' 
    AND category IN ('T-Shirt', 'Apparel', 'T-Shirts')
    AND status = 'Activated'
    ORDER BY name ASC
", '', []);

$tarpaulin_products = db_query("
    SELECT * FROM products
    WHERE product_type = 'custom' 
    AND category = 'Tarpaulin'
    AND status = 'Activated'
    ORDER BY name ASC
", '', []);

$feed_products = db_query("
    SELECT * FROM products 
    WHERE product_type = 'custom' 
    AND category IN ('Sintraboard', 'Signage', 'Sintraboard Flat')
    AND status = 'Activated' 
    ORDER BY name ASC
", '', []);



$page_title = 'Services - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$core_services = [
    ['name' => 'Tarpaulin', 'category' => 'Signage', 'img' => '/printflow/public/images/products/product_42.jpg', 'link' => 'order_tarpaulin.php'],
    ['name' => 'T-Shirt', 'category' => 'Apparel', 'img' => '/printflow/public/images/products/product_31.jpg', 'link' => 'order_tshirt.php'],
    ['name' => 'Stickers', 'category' => 'Decals', 'img' => '/printflow/public/images/products/product_21.jpg', 'link' => 'order_stickers.php'],
    ['name' => 'Glass/Wall', 'category' => 'Decals', 'img' => '/printflow/public/images/products/Glass Stickers  Wall  Frosted Stickers.png', 'link' => 'order_glass_stickers.php'],
    ['name' => 'Transparent', 'category' => 'Decals', 'img' => '/printflow/public/images/products/product_26.jpg', 'link' => 'order_transparent.php'],
    ['name' => 'Reflectorized', 'category' => 'Signage', 'img' => '/printflow/public/images/products/signage.jpg', 'link' => 'order_reflectorized.php'],
    ['name' => 'Sintraboard', 'category' => 'Signage', 'img' => '/printflow/public/images/products/standeeflat.jpg', 'link' => 'order_sintraboard.php'],
    ['name' => 'Standees', 'category' => 'Signage', 'img' => '/printflow/public/images/services/Sintraboard Standees.jpg', 'link' => 'order_standees.php'],
    ['name' => 'Souvenirs', 'category' => 'Merchandise', 'img' => '/printflow/public/assets/images/placeholder.jpg', 'link' => 'order_souvenirs.php']
];

// Reusable card template function
function render_service_card($name, $category, $img, $link, $is_service = true, $price = null, $stock = null) {
    if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $img)) {
        $img = '/printflow/public/assets/images/placeholder.jpg';
    }
    // Escape values for JS safely using json_encode
    $json_name = htmlspecialchars(json_encode($name), ENT_QUOTES, 'UTF-8');
    $json_category = htmlspecialchars(json_encode($category), ENT_QUOTES, 'UTF-8');
    $json_img = htmlspecialchars(json_encode($img), ENT_QUOTES, 'UTF-8');
    $json_link = htmlspecialchars(json_encode($link), ENT_QUOTES, 'UTF-8');
    $json_price = htmlspecialchars(json_encode($price !== null ? format_currency($price) : ''), ENT_QUOTES, 'UTF-8');
    $json_stock = htmlspecialchars(json_encode($stock !== null ? (string)$stock : ''), ENT_QUOTES, 'UTF-8');
    $is_service_str = $is_service ? 'true' : 'false';
    ?>
    <div class="ct-product-card cursor-pointer group" onclick="openServiceModal(<?php echo $json_name; ?>, <?php echo $json_category; ?>, <?php echo $json_img; ?>, <?php echo $json_link; ?>, <?php echo $is_service_str; ?>, <?php echo $json_price; ?>, <?php echo $json_stock; ?>)">
        <div class="ct-product-img overflow-hidden">
            <div class="ct-product-img-inner transition-transform duration-500 group-hover:scale-110">
                <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($name); ?>" style="width:100%; height:100%; object-fit:cover; border-radius:0.5rem;">
            </div>
        </div>
        <div class="ct-product-body" style="text-align: center;">
            <span class="ct-product-category"><?php echo htmlspecialchars($category); ?></span>
            <h3 class="ct-product-name" style="<?php echo $is_service ? 'margin-bottom: 1.5rem;' : 'margin-bottom: 0.5rem;'; ?> height: auto; font-weight: 700; font-size: 1.1rem;">
                <?php echo htmlspecialchars($name); ?>
            </h3>
            
            <?php if (!$is_service && $price !== null): ?>
                <p class="ct-product-price" style="margin-bottom: 1rem;"><?php echo format_currency($price); ?></p>
            <?php endif; ?>

            <div class="ct-product-actions" style="display: flex; flex-direction: column; gap: 0.5rem;">
                <?php if (!$is_service && $stock !== null): ?>
                    <div style="font-size: 0.85rem; font-weight: 600;">
                        <?php if ($stock > 0): ?>
                            <span style="color: #10B981;">✓ In Stock</span>
                        <?php else: ?>
                            <span style="color: #EF4444;">✕ Out of Stock</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <span class="ct-view-product-btn" style="width: 100%; text-align: center; pointer-events: none;">
                    VIEW DETAILS
                </span>
            </div>
        </div>
    </div>
    <?php
}
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width:1100px;">

        <!-- Main Services Grid -->
        <div class="flex justify-between items-end mb-4 mt-4">
            <div>
                <h1 class="ct-page-title" style="margin-bottom: 0;">Order a Service</h1>
            </div>
        </div>
        
        <!-- Filters (Search bar) -->
        <div class="mb-10">
            <form action="products.php" method="GET" style="display:flex; gap:0.75rem; align-items:center; max-width: 33.33%;">
                <div style="flex-grow: 1;">
                    <input type="text" name="search" class="input-field" placeholder="Search services..." style="width: 100%; border-radius: 8px;">
                </div>
                <button type="submit" class="btn-primary" style="height:42px; padding: 0 1.5rem; border-radius: 8px;">Search</button>
            </form>
        </div>

        <div class="ct-product-grid mb-12">
            <?php foreach ($core_services as $srv): ?>
                <?php render_service_card($srv['name'], $srv['category'], $srv['img'], $srv['link']); ?>
            <?php endforeach; ?>
        </div>

        <!-- Decals & Stickers -->
        <?php if (!empty($featured_products)): ?>
        <h2 class="ct-section-title mb-6 mt-12 pt-8 border-t border-gray-100">Decals & Stickers Showcase</h2>
        <div class="ct-product-grid mb-12">
            <?php foreach ($featured_products as $product): ?>
                <?php 
                $img_link = "/printflow/public/images/products/product_" . $product['product_id'];
                $img_path = __DIR__ . "/../public/images/products/product_" . $product['product_id'];
                $display_img = "/printflow/public/assets/images/placeholder.jpg";
                if (file_exists($img_path . ".jpg")) { $display_img = $img_link . ".jpg"; }
                elseif (file_exists($img_path . ".png")) { $display_img = $img_link . ".png"; }
                
                render_service_card($product['name'], $product['category'], $display_img, "order_create.php?product_id=".$product['product_id'], false, $product['price'], $product['stock_quantity']);
                ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- T-Shirt Customization Grid -->
        <?php if (!empty($tshirt_products)): ?>
        <h2 class="ct-section-title mb-6 mt-12 pt-8 border-t border-gray-100">Apparel Customization</h2>
        <div class="ct-product-grid mb-12">
            <?php foreach ($tshirt_products as $product): ?>
                <?php 
                render_service_card($product['name'], $product['category'], "/printflow/public/images/products/product_".$product['product_id'].".jpg", "order_create.php?product_id=".$product['product_id'], false, $product['price'], $product['stock_quantity']);
                ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Tarpaulin "JUST FOR YOU" Section -->
        <?php if (!empty($tarpaulin_products)): ?>
        <h2 class="ct-section-title mb-6 mt-12 pt-8 border-t border-gray-100">Tarpaulin & Layout Selection</h2>
        <div class="ct-product-grid mb-12">
            <?php foreach ($tarpaulin_products as $product): ?>
                <?php 
                render_service_card($product['name'], $product['category'], "/printflow/public/images/products/product_".$product['product_id'].".jpg", "order_create.php?product_id=".$product['product_id'], false, $product['price'], $product['stock_quantity']);
                ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Sintraboard Flat Section -->
        <?php if (!empty($feed_products)): ?>
        <h2 class="ct-section-title mb-6 mt-12 pt-8 border-t border-gray-100">Sintraboard Flats</h2>
        <div class="ct-product-grid mb-12">
            <?php foreach ($feed_products as $product): 
                $img = !empty($product['product_image']) ? "/printflow/" . $product['product_image'] : '/printflow/public/assets/images/placeholder.jpg';
                render_service_card($product['name'], $product['category'], $img, "order_create.php?product_id=".$product['product_id'], false, $product['price'], $product['stock_quantity']);
            endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Service Detail Modal -->
<div id="service-modal" style="display: none; position: fixed; inset: 0; align-items: center; justify-content: center; z-index: 9999999; padding: 1.5rem; transition: opacity 0.2s ease;">
    <!-- Backdrop (Soft dark tint to highlight modal) -->
    <div onclick="closeServiceModal()" style="position: absolute; inset: 0; background-color: rgba(0, 0, 0, 0.45);"></div>
    
    <!-- Modal Content (Wider fixed size with internal scroll) -->
    <div id="service-modal-content" style="position: relative; background-color: #ffffff; border-radius: 1.5rem; width: 750px; max-width: 100%; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); transform: translateY(20px); transition: all 0.3s ease;">
        
        <style>
            /* Modal Internal Scrollbar */
            #service-modal-scroll-body::-webkit-scrollbar { width: 6px; }
            #service-modal-scroll-body::-webkit-scrollbar-track { background: transparent; }
            #service-modal-scroll-body::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
            #service-modal-scroll-body::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
            .modal-action-row {
                display: flex;
                align-items: stretch;
                gap: 1rem;
            }
            .modal-qty-block {
                display: flex;
                align-items: center;
                border: 2px solid #e5e7eb;
                border-radius: 0.75rem;
                height: 48px;
                flex-shrink: 0;
            }
            .modal-qty-btn {
                width: 44px;
                height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: transparent;
                border: none;
                cursor: pointer;
                font-size: 1.2rem;
                color: #111827;
                font-weight: 700;
                transition: all 0.2s;
            }
            .modal-action-buttons {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0.75rem;
                flex: 1;
            }
            .modal-action-btn {
                height: 48px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                font-weight: 700;
                border-radius: 0.75rem;
                border: none;
                cursor: pointer;
                transition: all 0.2s;
                font-size: 0.9rem;
                text-transform: uppercase;
                letter-spacing: 0.02em;
            }
            @media (max-width: 640px) {
                .modal-action-row {
                    flex-direction: column;
                    align-items: stretch;
                }
                .modal-qty-block {
                    justify-content: center;
                    width: 100%;
                }
                .modal-action-buttons {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        
        <!-- Close Button -->
        <button onclick="closeServiceModal()" style="position: absolute; top: 1rem; right: 1rem; z-index: 100; padding: 0.5rem; background: #ffffff; border-radius: 9999px; border: none; cursor: pointer; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center;">
            <svg style="width: 1.5rem; height: 1.5rem; color: #1f2937;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <!-- Scrollable Body Section -->
        <div id="service-modal-scroll-body" style="overflow-y: auto; flex: 1; display: flex; flex-direction: column;">
            <!-- Image Section (Fixed Aspect Ratio) -->
            <div style="width: 100%; height: 420px; position: relative; background: #f3f4f6; flex-shrink: 0;">
                <img id="modal-img" src="" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                <div style="position: absolute; top: 1.25rem; left: 1.25rem; z-index: 10;">
                    <span id="modal-category" style="padding: 0.35rem 0.85rem; background: #ffffff; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; border-radius: 0.5rem; color: #4F46E5; box-shadow: 0 4px 6px rgba(0,0,0,0.1); letter-spacing: 0.05em;">Category</span>
                </div>
            </div>

            <!-- Info Section (Same as Card Body) -->
            <div style="padding: 2.25rem; display: flex; flex-direction: column; background: #ffffff;">
                <h2 id="modal-name" style="font-size: 1.75rem; font-weight: 800; color: #111827; margin: 0 0 0.85rem 0; line-height: 1.2;">Service Name</h2>
                
                <div id="modal-price-container" style="margin-bottom: 1.25rem; display: none;">
                    <p id="modal-price" style="font-size: 1.5rem; font-weight: 800; color: #111827; margin: 0;"></p>
                    <div id="modal-stock" style="margin-top: 0.5rem; font-size: 0.85rem; font-weight: 600;"></div>
                </div>

                <p style="color: #4b5563; margin-bottom: 2rem; line-height: 1.7; font-size: 0.95rem;">
                    Choose this service to start your customization. You will be able to select specific materials, sizes, and upload your layout on the next page to complete your order.
                </p>

                <!-- Action Buttons Section -->
                <div style="margin-top: auto; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                    <!-- Quantity Selector & Action Buttons -->
                    <div id="modal-cart-section" style="display: none;">
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #6b7280; text-transform: uppercase; margin-bottom: 0.75rem; letter-spacing: 0.05em;">Quantity</label>
                        
                        <!-- Controls Row -->
                        <div class="modal-action-row">
                            <!-- Quantity Controls -->
                            <div class="modal-qty-block">
                                <button type="button" onclick="decreaseModalQuantity()" class="modal-qty-btn" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">
                                    −
                                </button>
                                <span id="modal-quantity-display" style="width: 50px; height: 44px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb; color: #111827;">1</span>
                                <button type="button" onclick="increaseModalQuantity()" class="modal-qty-btn" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">
                                    +
                                </button>
                            </div>

                            <!-- Action Buttons -->
                            <div class="modal-action-buttons">
                                <!-- Add to Cart Button -->
                                <button type="button" onclick="addServiceToCart()" class="modal-action-btn" style="background: #111827; color: #ffffff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.15);">
                                    <svg style="width: 1.2rem; height: 1.2rem;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="9" cy="21" r="1"></circle>
                                        <circle cx="20" cy="21" r="1"></circle>
                                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                    </svg>
                                    Add to Cart
                                </button>

                                <!-- Buy Now Button -->
                                <button type="button" onclick="buyNowService()" class="modal-action-btn" style="background: #0a2530; color: #ffffff; box-shadow: 0 4px 6px -1px rgba(10, 37, 48, 0.15);">
                                    <svg style="width: 1.2rem; height: 1.2rem;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 19c-5 1.5-5-2.5-7-4m14 6v-3.87a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 17.25 8.75h-7.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 4.125 2.75h-1.5A3.375 3.375 0 0 0 -0.75 6.125v7.5A3.375 3.375 0 0 0 2.625 17h15.75A3.375 3.375 0 0 0 21.75 13.625Z"></path>
                                    </svg>
                                    Buy Now
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// CSRF Token
const SERVICE_MODAL_CSRF = '<?php echo $csrf_token; ?>';
let modalQuantity = 1;
let currentModalData = {};

function openServiceModal(name, category, img, link, is_service, price, stock) {
    document.getElementById('modal-name').textContent = name || '';
    document.getElementById('modal-category').textContent = category || '';
    document.getElementById('modal-img').src = img || '';
    
    // Store current modal data for cart operations
    currentModalData = {
        name: name,
        category: category,
        img: img,
        link: link,
        is_service: is_service,
        price: price,
        stock: stock
    };
    
    // Reset quantity
    modalQuantity = 1;
    
    const priceContainer = document.getElementById('modal-price-container');
    const cartSection = document.getElementById('modal-cart-section');
    
    // Show quantity and cart for all items (services and products)
    cartSection.style.display = 'flex';
    document.getElementById('modal-quantity-display').textContent = '1';
    
    // Show price and stock only for products
    if (is_service === false && price !== '') {
        priceContainer.style.display = 'block';
        document.getElementById('modal-price').textContent = price;
        
        const stockEl = document.getElementById('modal-stock');
        if (stock !== '' && parseInt(stock) > 0) {
            stockEl.innerHTML = '<span style="color: #10B981;">✓ In Stock (' + stock + ' available)</span>';
        } else {
            stockEl.innerHTML = '<span style="color: #EF4444;">✕ Out of Stock</span>';
        }
    } else {
        priceContainer.style.display = 'none';
    }
    
    const modal = document.getElementById('service-modal');
    const content = document.getElementById('service-modal-content');
    
    // Show modal container
    modal.style.display = 'flex';
    // Trigger reflow to animate
    void modal.offsetWidth;
    
    // Explicit inline animations
    modal.style.opacity = '1';
    modal.style.pointerEvents = 'auto';
    content.style.transform = 'translateY(0)';
    
    document.body.style.overflow = 'hidden';
}

function closeServiceModal() {
    const modal = document.getElementById('service-modal');
    const content = document.getElementById('service-modal-content');
    
    modal.style.opacity = '0';
    modal.style.pointerEvents = 'none';
    content.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }, 300);
}

// Quantity Control Functions
function increaseModalQuantity() {
    modalQuantity = Math.min(modalQuantity + 1, 999);
    document.getElementById('modal-quantity-display').textContent = modalQuantity;
}

function decreaseModalQuantity() {
    modalQuantity = Math.max(modalQuantity - 1, 1);
    document.getElementById('modal-quantity-display').textContent = modalQuantity;
}

// Buy Now Function
function buyNowService() {
    if (!currentModalData.link) {
        alert('Unable to proceed. Service information missing.');
        return;
    }
    
    const link = currentModalData.link;
    const is_service = currentModalData.is_service;
    
    // For services and products, redirect to checkout/customization with quantity
    const separator = link.includes('?') ? '&' : '?';
    window.location.href = link + separator + 'qty=' + modalQuantity;
}

// Add to Cart Function
async function addServiceToCart() {
    if (!currentModalData.link) {
        alert('Unable to proceed. Service information missing.');
        return;
    }
    
    const link = currentModalData.link;
    const is_service = currentModalData.is_service;
    
    // Get the button and show loading state
    const cartBtn = event.target.closest('button');
    if (!cartBtn) return;
    
    const originalText = cartBtn.innerHTML;
    cartBtn.disabled = true;
    cartBtn.innerHTML = '<svg style="width: 1.2rem; height: 1.2rem; animation: spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"></circle><path d="M12 5v2m0 10v2M7 12H5m12 0h2M8.22 8.22l1.41 1.41m5.74 5.74l1.41 1.41M8.22 15.78l1.41-1.41m5.74-5.74l1.41-1.41"></path></svg> Adding...';
    
    try {
        // For services (custom orders), add to cart with quantity
        if (is_service === true) {
            // Services don't have product_id, so we store order details separately
            // Redirect to customization page with quantity
            const separator = link.includes('?') ? '&' : '?';
            
            // Show success
            cartBtn.innerHTML = '✓ Added to Cart!';
            cartBtn.style.background = '#10B981';
            
            setTimeout(() => {
                closeServiceModal();
                window.location.href = link + separator + 'qty=' + modalQuantity;
            }, 1000);
        } else {
            // This is a fixed product - add via API
            const urlParams = new URLSearchParams(new URL(currentModalData.link, window.location.origin).search);
            const productId = parseInt(urlParams.get('product_id') || '0');
            
            if (productId <= 0) {
                alert('Unable to add to cart. Product ID not found.');
                cartBtn.disabled = false;
                cartBtn.innerHTML = originalText;
                return;
            }
            
            const response = await fetch('/printflow/customer/api_cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add',
                    product_id: productId,
                    quantity: modalQuantity,
                    csrf_token: SERVICE_MODAL_CSRF
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Show success message
                cartBtn.innerHTML = '✓ Added to Cart!';
                cartBtn.style.background = '#10B981';
                
                // Update cart badge if function exists
                if (window.updateCartBadge) {
                    updateCartBadge(data.cart_count || 0);
                }
                
                // Close modal after 1.5 seconds
                setTimeout(() => {
                    closeServiceModal();
                    // Reset button state
                    cartBtn.disabled = false;
                    cartBtn.innerHTML = originalText;
                    cartBtn.style.background = '#111827';
                }, 1500);
            } else {
                alert(data.message || 'Failed to add to cart');
                cartBtn.disabled = false;
                cartBtn.innerHTML = originalText;
            }
        }
    } catch (err) {
        console.error('Error adding to cart:', err);
        alert('An error occurred. Please try again.');
        cartBtn.disabled = false;
        cartBtn.innerHTML = originalText;
    }
}

// Add CSS animation for loading spinner
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

// Cart Badge Update Function
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
