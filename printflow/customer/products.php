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

$sql .= " ORDER BY name ASC";

$products = db_query($sql, $types, $params);
$categories = db_query("SELECT DISTINCT category FROM products WHERE status = 'Activated' ORDER BY category ASC");

$page_title = 'Products - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width:1100px;">
        <h1 class="ct-page-title">Browse Products</h1>

        <!-- Filters -->
        <div class="ct-filter">
            <form method="GET" style="display:grid; grid-template-columns:1fr 1fr auto; gap:1rem; align-items:end;">
                <div>
                    <label style="display:block; font-size:0.75rem; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:0.4rem;">Search</label>
                    <input type="text" name="search" class="input-field" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div>
                    <label style="display:block; font-size:0.75rem; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:0.4rem;">Category</label>
                    <select name="category" class="input-field">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary" style="height:fit-content;">Apply</button>
            </form>
        </div>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
            <div class="ct-empty">
                <div class="ct-empty-icon">📦</div>
                <p>No products found</p>
            </div>
        <?php else: ?>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:1.5rem;">
                <?php foreach ($products as $product): ?>
                    <div class="ct-product-card">
                        <div class="ct-product-img">
                            <span>📦</span>
                        </div>
                        <div class="ct-product-body">
                            <span class="ct-product-category"><?php echo htmlspecialchars($product['category']); ?></span>
                            <h3 class="ct-product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="ct-product-desc"><?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?>...</p>

                            <p class="ct-product-price"><?php echo format_currency($product['price']); ?></p>
                            <?php if ($product['stock_quantity'] > 0): ?>
                                <span class="ct-product-stock in-stock">✓ In Stock</span>
                            <?php else: ?>
                                <span class="ct-product-stock out-stock">✕ Out of Stock</span>
                            <?php endif; ?>

                            <div class="ct-product-actions">
                                <a href="order_create.php?product_id=<?php echo $product['product_id']; ?>" class="ct-more-info">MORE INFO ›</a>
                                <a href="order_create.php?product_id=<?php echo $product['product_id']; ?>" class="btn-primary" style="padding:0.5rem 1.25rem; font-size:0.75rem;">ADD TO CART</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
