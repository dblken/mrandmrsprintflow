<?php
/**
 * Staff Products (Inventory) Page
 * PrintFlow - Printing Shop PWA
 * Read-only view for staff
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');

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
    $sql .= " AND (name LIKE ? OR sku LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

$sql .= " ORDER BY name ASC";

$products = db_query($sql, $types, $params);
$categories = db_query("SELECT DISTINCT category FROM products WHERE status = 'Activated' ORDER BY category ASC");

$page_title = 'Products & Inventory - Staff';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="container mx-auto px-4">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Products & Inventory</h1>

        <!-- Filters -->
        <div class="card mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" class="input-field" placeholder="Name or SKU..." value="<?php echo htmlspecialchars($search); ?>">
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

        <!-- Products Table -->
        <div class="card">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b-2">
                            <th class="text-left py-3">SKU</th>
                            <th class="text-left py-3">Name</th>
                            <th class="text-left py-3">Category</th>
                            <th class="text-left py-3">Price</th>
                            <th class="text-left py-3">Stock</th>
                            <th class="text-left py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 font-mono text-xs"><?php echo htmlspecialchars($product['sku']); ?></td>
                                <td class="py-3 font-medium"><?php echo htmlspecialchars($product['name']); ?></td>
                                <td class="py-3"><?php echo htmlspecialchars($product['category']); ?></td>
                                <td class="py-3 font-semibold"><?php echo format_currency($product['price']); ?></td>
                                <td class="py-3">
                                    <?php if ($product['stock_quantity'] < 10): ?>
                                        <span class="text-red-600 font-bold"><?php echo $product['stock_quantity']; ?></span>
                                        <span class="text-xs text-red-600">LOW</span>
                                    <?php else: ?>
                                        <span class="text-green-600"><?php echo $product['stock_quantity']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3"><?php echo status_badge($product['status'], 'order'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
