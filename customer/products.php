<?php
/**
 * Customer Products Page
 * PrintFlow - Printing Shop PWA
 * (Products browsing for customers - similar to public but with order CTAs)
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
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="container mx-auto px-4">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Browse Products</h1>

        <!-- Filters -->
        <div class="card mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" class="input-field" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select name="category" class="input-field">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
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
                <?php foreach ($products as $product): ?>
                    <div class="card hover:shadow-lg transition">
                        <div class="bg-gray-200 h-48 rounded-lg mb-4 flex items-center justify-center">
                            <span class="text-gray-400 text-4xl">📦</span>
                        </div>

                        <div class="mb-2">
                            <span class="badge bg-indigo-100 text-indigo-800"><?php echo htmlspecialchars($product['category']); ?></span>
                        </div>
                        <h3 class="text-lg font-bold mb-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?>...</p>
                        
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-2xl font-bold text-indigo-600"><?php echo format_currency($product['price']); ?></span>
                            <?php if ($product['stock_quantity'] > 0): ?>
                                <span class="text-sm text-green-600">✓ In Stock</span>
                            <?php else: ?>
                                <span class="text-sm text-red-600">Out of Stock</span>
                            <?php endif; ?>
                        </div>

                        <a href="order_create.php?product_id=<?php echo $product['product_id']; ?>" class="btn-primary w-full block text-center">
                            Order Now
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
