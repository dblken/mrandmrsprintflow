<?php
/**
 * Public Products Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect Admin, Manager, and Staff away from public products
redirect_admin_staff_from_public();

// Get filter parameters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT * FROM products WHERE status = 'Activated'";
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

$sql .= " ORDER BY category ASC, name ASC";
$products = db_query($sql, $types, $params);

// Get all categories
$categories = db_query("SELECT DISTINCT category FROM products WHERE status = 'Activated' ORDER BY category ASC");

// Get featured products for the hero carousel
$featured_products = db_query("SELECT * FROM products WHERE status = 'Activated' AND is_featured = 1 AND product_image IS NOT NULL ORDER BY name ASC LIMIT 8");

// Group products by category
$products_by_category = [];
foreach (($products ?: []) as $p) {
    $products_by_category[$p['category'] ?? 'Other'][] = $p;
}

$page_title = 'Products - PrintFlow';
$use_landing_css = true;
require_once __DIR__ . '/../includes/header.php';

function public_product_image_url(array $product, string $base_path): string
{
    $raw = trim((string)($product['photo_path'] ?? $product['product_image'] ?? ''));
    $raw = preg_replace('/<\?php\s+echo\s+\$[a-z_]+;?\s*\?>/i', '', $raw);
    $raw = trim((string)$raw);
    $root = dirname(__DIR__);

    $url_for_existing = static function (string $relative) use ($root, $base_path): ?string {
        $relative = '/' . ltrim($relative, '/');
        if (file_exists($root . $relative)) {
            return rtrim($base_path, '/') . $relative;
        }
        return null;
    };

    if ($raw !== '') {
        if (preg_match('#^https?://#i', $raw)) {
            return $raw;
        }

        $clean = '/' . ltrim($raw, '/');
        if ($base_path !== '' && str_starts_with($clean, rtrim($base_path, '/') . '/')) {
            $clean = substr($clean, strlen(rtrim($base_path, '/')));
        }

        foreach ([
            $clean,
            '/uploads/products/' . basename($clean),
            '/public/assets/uploads/products/' . basename($clean),
            '/public/images/products/' . basename($clean),
        ] as $candidate) {
            $resolved = $url_for_existing($candidate);
            if ($resolved !== null) {
                return $resolved;
            }
        }
    }

    $product_id = (int)($product['product_id'] ?? 0);
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $resolved = $url_for_existing('/public/images/products/product_' . $product_id . '.' . $ext);
        if ($resolved !== null) {
            return $resolved;
        }
    }

    if (function_exists('get_service_image_url')) {
        return get_service_image_url((string)($product['category'] ?? $product['name'] ?? ''));
    }

    return rtrim($base_path, '/') . '/public/assets/images/services/default.png';
}
?>

<!-- ============================================================
     HERO — mini hero matching about/services style
     ============================================================ -->
<section class="lp-mini-hero" style="padding-top:0; padding-bottom:5rem;">
    <?php $nav_header_class = 'lp-hero-nav sticky top-0 z-50'; require __DIR__ . '/../includes/nav-header.php'; ?>
    <div class="lp-mini-hero-inner" style="padding-top:4rem;">
        <div class="lp-wrap" style="text-align:center;">
            <p class="lp-hero-tag" style="margin-bottom:1.25rem;">✦ Our Catalog</p>
            <h1 class="lp-products-hero-title" style="font-size:clamp(2.2rem,5vw,3.5rem); font-weight:800; color:#fff; letter-spacing:-0.03em; margin-bottom:1.25rem; line-height:1.1;">
                Browse Our <span style="color:var(--lp-accent-l);">Products</span>
            </h1>
            <p class="lp-products-hero-desc" style="font-size:1.0625rem; color:var(--lp-muted); max-width:540px; margin:0 auto 2.5rem; line-height:1.7;">
                From tarpaulins to T-shirts, stickers to signage — find the perfect print solution for your next project.
            </p>
            <div class="lp-products-hero-actions" style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap; width:100%;">
                <a href="#products-grid" class="lp-btn lp-btn-primary">Browse All Products</a>
                <?php if (!is_logged_in()): ?>
                <a href="#" data-auth-modal="register" class="lp-btn lp-btn-outline">Get Started Free</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     PRODUCTS BY CATEGORY (alternating sections)
     ============================================================ -->
<?php if (empty($products_by_category)): ?>
<section style="background:#f8fafc;padding:5rem 0;">
    <div class="lp-wrap" style="text-align:center;">
        <div style="width:80px;height:80px;background:#e2e8f0;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;">
            <svg style="width:36px;height:36px;color:#94a3b8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h3 style="font-size:1.25rem;font-weight:700;color:#374151;margin-bottom:.5rem;">No products found</h3>
        <p style="color:#9ca3af;max-width:320px;margin:0 auto;">Try adjusting your search or filter criteria.</p>
        <?php if (!empty($search) || !empty($category)): ?>
            <a href="products.php" style="display:inline-block;margin-top:1.25rem;color:#32a1c4;font-weight:600;text-decoration:underline;">Clear all filters</a>
        <?php endif; ?>
    </div>
</section>
<?php else: ?>
<?php
$section_i = 0;
foreach ($products_by_category as $cat_name => $cat_products):
    $dark = ($section_i % 2 === 0);
    $section_i++;
    $bg = $dark ? 'var(--lp-bg2)' : '#f8fafc';
    $card_bg = $dark ? 'var(--lp-surface)' : '#fff';
    $card_border = $dark ? '1px solid rgba(83,197,224,0.12)' : '1px solid #e2e8f0';
    $heading_col = $dark ? '#fff' : '#0f172a';
    $sub_col = $dark ? 'var(--lp-muted)' : '#64748b';
    $name_col = $dark ? '#fff' : '#0f172a';
    $desc_col = $dark ? 'var(--lp-muted)' : '#64748b';
?>
<section style="padding:4rem 0;background:<?php echo $bg; ?>;">
    <div class="lp-wrap">
        <!-- Category heading -->
        <div class="lp-products-category-head" style="display:flex;align-items:center;gap:1rem;margin-bottom:2.5rem;flex-wrap:wrap;">
            <div style="flex:1; min-width:0;">
                <h2 style="font-size:1.6rem;font-weight:800;color:<?php echo $heading_col; ?>;letter-spacing:-0.02em;margin:0 0 .25rem;">
                    <?php echo htmlspecialchars($cat_name); ?>
                </h2>
                <p style="font-size:.875rem;color:<?php echo $sub_col; ?>;margin:0;"><?php echo count($cat_products); ?> product<?php echo count($cat_products) !== 1 ? 's' : ''; ?> available</p>
            </div>
            <div class="lp-products-category-line" style="height:3px;flex:1;max-width:220px;background:linear-gradient(90deg,var(--lp-accent),transparent);border-radius:2px;opacity:.5;"></div>
        </div>

        <!-- Card grid -->
        <div class="lp-products-category-grid"<?php echo $section_i === 1 ? ' id="products-grid"' : ''; ?> style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(270px,100%),1fr));gap:1.5rem;">
            <?php foreach ($cat_products as $product): ?>
            <div class="lp-products-card" style="background:<?php echo $card_bg; ?>;border:<?php echo $card_border; ?>;border-radius:1.125rem;overflow:hidden;display:flex;flex-direction:column;transition:transform .2s,box-shadow .2s;cursor:default;"
                 onmouseover="this.style.transform='translateY(-5px)';this.style.boxShadow='0 12px 36px rgba(50,161,196,0.18)';"
                 onmouseout="this.style.transform='';this.style.boxShadow='';">

                <!-- Image -->
                <?php $product_image_url = public_product_image_url($product, $base_path); ?>
                <div style="width:100%;aspect-ratio:4/3;overflow:hidden;position:relative;background:#1a2535;">
                    <img src="<?php echo htmlspecialchars($product_image_url); ?>"
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         style="width:100%;height:100%;object-fit:cover;transition:transform .3s;"
                         loading="lazy"
                         onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($base_path); ?>/public/assets/images/services/default.png';"
                         onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    <?php if ($product['is_featured'] ?? 0): ?>
                    <span style="position:absolute;top:.75rem;left:.75rem;background:var(--lp-accent);color:#fff;font-size:.65rem;font-weight:800;letter-spacing:.08em;padding:.25rem .7rem;border-radius:20px;text-transform:uppercase;">★ Popular</span>
                    <?php endif; ?>
                </div>

                <!-- Body -->
                <div class="lp-products-card-body" style="padding:1.25rem;display:flex;flex-direction:column;flex:1;gap:.5rem;">
                    <div style="font-size:.75rem;font-weight:700;color:var(--lp-accent);text-transform:uppercase;letter-spacing:.07em;"><?php echo htmlspecialchars($product['category']); ?></div>
                    <h3 style="font-size:1.0625rem;font-weight:700;color:<?php echo $name_col; ?>;margin:0;line-height:1.3;"><?php echo htmlspecialchars($product['name']); ?></h3>
                    <?php if (!empty($product['description'])): ?>
                    <p style="font-size:.875rem;color:<?php echo $desc_col; ?>;line-height:1.6;margin:0;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;flex:1;"><?php echo htmlspecialchars($product['description']); ?></p>
                    <?php else: ?>
                    <div style="flex:1;"></div>
                    <?php endif; ?>
                    <div class="lp-products-card-footer" style="display:flex;align-items:center;justify-content:space-between;margin-top:.75rem;padding-top:.875rem;border-top:1px solid <?php echo $dark ? 'rgba(83,197,224,0.1)' : '#f1f5f9'; ?>;">
                        <div>
                            <div style="font-size:1.5rem;font-weight:800;color:<?php echo $dark ? '#fff' : '#0f172a'; ?>;line-height:1;">₱<?php echo number_format($product['price'], 2); ?></div>
                            <div style="font-size:.75rem;color:<?php echo $desc_col; ?>;margin-top:2px;">Starting price</div>
                        </div>
                        <?php if (is_logged_in() && is_customer()): ?>
                            <a href="<?php echo $base_path; ?>/customer/order.php?product_id=<?php echo $product['product_id']; ?>"
                               style="display:inline-flex;align-items:center;gap:.4rem;background:var(--lp-accent);color:#fff;padding:.55rem 1.125rem;border-radius:.625rem;font-size:.875rem;font-weight:700;transition:background .2s;"
                               onmouseover="this.style.background='#2a82a3'" onmouseout="this.style.background='var(--lp-accent)'">
                                Order
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        <?php else: ?>
                            <a href="#" data-auth-modal="login"
                               style="display:inline-flex;align-items:center;gap:.4rem;background:<?php echo $dark ? 'rgba(50,161,196,0.15)' : '#eef7fa'; ?>;color:var(--lp-accent);padding:.55rem 1.125rem;border-radius:.625rem;font-size:.875rem;font-weight:700;transition:background .2s;border:1px solid rgba(50,161,196,0.25);"
                               onmouseover="this.style.background='var(--lp-accent)';this.style.color='#fff'" onmouseout="this.style.background='<?php echo $dark ? 'rgba(50,161,196,0.15)' : '#eef7fa'; ?>'; this.style.color='var(--lp-accent)'">
                                Order
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endforeach; ?>
<?php endif; ?>

<!-- CTA -->
<section class="lp-section-cta">
    <div class="lp-wrap">
        <div class="lp-cta-inner">
            <h2 class="lp-cta-title">Ready to Place Your Order?</h2>
            <p class="lp-cta-desc">Get in touch or create an account to start ordering high-quality prints today.</p>
            <div class="lp-cta-btns">
                <?php if (!is_logged_in()): ?>
                    <a href="#" data-auth-modal="register" class="lp-btn lp-btn-primary">Create Free Account</a>
                    <a href="<?php echo $base_path; ?>/public/services.php" class="lp-btn lp-btn-outline">View Services</a>
                <?php else: ?>
                    <a href="<?php echo $base_path; ?>/public/services.php" class="lp-btn lp-btn-primary">View All Services</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
    // Realtime search debounce
    var searchInput = document.getElementById('search-input');
    var categorySelect = document.getElementById('category-select');
    var filterForm = document.getElementById('filter-form');
    if (searchInput && filterForm) {
        var debounceTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function(){ filterForm.submit(); }, 420);
        });
    }
    if (categorySelect && filterForm) {
        categorySelect.addEventListener('change', function(){ filterForm.submit(); });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php require_once __DIR__ . '/../includes/auth-modals.php'; ?>
