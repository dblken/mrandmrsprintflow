<?php
/**
 * Public Products Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

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

// Get all categories
$categories = db_query("SELECT DISTINCT category FROM products WHERE status = 'Activated' ORDER BY category ASC");

$page_title = 'Products - PrintFlow';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="container mx-auto px-4">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">Our Products & Services</h1>
            <p class="text-gray-600">Browse our wide range of printing services</p>
        </div>

        <!-- Filters -->
        <div class="card mb-6">
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Search -->
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input 
                        type="text" 
                        id="search" 
                        name="search" 
                        class="input-field" 
                        placeholder="Search products..." 
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                </div>

                <!-- Category Filter -->
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select id="category" name="category" class="input-field">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter Button -->
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
                        <!-- Product Image Placeholder -->
                        <div class="bg-gray-200 h-48 rounded-lg mb-4 flex items-center justify-center text-gray-400">
                            <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>

                        <!-- Product Info -->
                        <div>
                            <div class="mb-2">
                                <span class="badge bg-indigo-100 text-indigo-800"><?php echo htmlspecialchars($product['category']); ?></span>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?php echo htmlspecialchars($product['description']); ?></p>
                            
                            <div class="flex items-center justify-between">
                                <span class="text-2xl font-bold text-indigo-600"><?php echo format_currency($product['price']); ?></span>
                                <?php if ($product['stock_quantity'] > 0): ?>
                                    <span class="text-sm text-green-600">In Stock</span>
                                <?php else: ?>
                                    <span class="text-sm text-red-600">Out of Stock</span>
                                <?php endif; ?>
                            </div>

                            <?php if (is_logged_in() && is_customer()): ?>
                                <a href="/printflow/customer/order.php?product_id=<?php echo $product['product_id']; ?>" class="btn-primary w-full mt-4 block text-center">
                                    Order Now
                                </a>
                            <?php else: ?>
                                <a href="#" data-auth-modal="login" class="btn-secondary w-full mt-4 block text-center">
                                    Login to Order
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
