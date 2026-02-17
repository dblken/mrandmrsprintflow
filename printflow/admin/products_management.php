<?php
/**
 * Admin Products Management Page
 * PrintFlow - Printing Shop PWA  
 * Full CRUD for products
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$current_user = get_logged_in_user();

$error = '';
$success = '';

// Handle product creation/update/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['create_product'])) {
        $name = sanitize($_POST['name'] ?? '');
        $sku = sanitize($_POST['sku'] ?? '');
        $category = sanitize($_POST['category'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
        $status = $_POST['status'] ?? 'Activated';
        
        db_execute("INSERT INTO products (name, sku, category, description, price, stock_quantity, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            'ssssdis', [$name, $sku, $category, $description, $price, $stock_quantity, $status]);
        
        $success = 'Product created successfully!';
    } elseif (isset($_POST['update_product'])) {
        $product_id = (int)$_POST['product_id'];
        $name = sanitize($_POST['name']);
        $price = (float)$_POST['price'];
        $stock_quantity = (int)$_POST['stock_quantity'];
        $status = $_POST['status'];
        
        db_execute("UPDATE products SET name = ?, price = ?, stock_quantity = ?, status = ?, updated_at = NOW() WHERE product_id = ?",
            'sdiis', [$name, $price, $stock_quantity, $status, $product_id]);
        
        $success = 'Product updated successfully!';
    } elseif (isset($_POST['delete_product'])) {
        $product_id = (int)$_POST['product_id'];
        db_execute("UPDATE products SET status = 'Deactivated' WHERE product_id = ?", 'i', [$product_id]);
        $success = 'Product deactivated successfully!';
    }
}

// Get all products
$products = db_query("SELECT * FROM products ORDER BY created_at DESC");

$page_title = 'Products Management - Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Products Management</h1>
            <button 
                @click="$dispatch('open-product-modal', { mode: 'create' })"
                class="btn-primary"
            >
                + Add New Product
            </button>
        </header>

        <main>
            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Products Table -->
            <div class="card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2">
                                <th class="text-left py-3">ID</th>
                                <th class="text-left py-3">SKU</th>
                                <th class="text-left py-3">Name</th>
                                <th class="text-left py-3">Category</th>
                                <th class="text-left py-3">Price</th>
                                <th class="text-left py-3">Stock</th>
                                <th class="text-left py-3">Status</th>
                                <th class="text-right py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3"><?php echo $product['product_id']; ?></td>
                                    <td class="py-3 font-mono text-xs"><?php echo htmlspecialchars($product['sku']); ?></td>
                                    <td class="py-3 font-medium"><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td class="py-3"><?php echo htmlspecialchars($product['category']); ?></td>
                                    <td class="py-3 font-semibold"><?php echo format_currency($product['price']); ?></td>
                                    <td class="py-3">
                                        <?php if ($product['stock_quantity'] < 10): ?>
                                            <span class="text-red-600 font-bold"><?php echo $product['stock_quantity']; ?></span>
                                        <?php else: ?>
                                            <span><?php echo $product['stock_quantity']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3"><?php echo status_badge($product['status'], 'order'); ?></td>
                                    <td class="py-3 text-right space-x-2">
                                        <button class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">Edit</button>
                                        <form method="POST" class="inline" onsubmit="return confirm('Deactivate this product?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                            <button type="submit" name="delete_product" class="text-red-600 hover:text-red-700 text-sm font-medium">Deactivate</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Product Modal (Simplified - would need full implementation) -->
<div x-data="{ showModal: false, mode: 'create' }" 
     @open-product-modal.window="showModal = true; mode = $event.detail.mode"
     x-show="showModal"
     class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
     style="display: none;">
    <div class="bg-white rounded-lg p-6 max-w-2xl w-full mx-4" @click.away="showModal = false">
        <h3 class="text-xl font-bold mb-4">Add New Product</h3>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="create_product" value="1">
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Product Name *</label>
                    <input type="text" name="name" class="input-field" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">SKU *</label>
                    <input type="text" name="sku" class="input-field" required>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Category *</label>
                    <select name="category" class="input-field" required>
                        <option value="Tarpaulin">Tarpaulin</option>
                        <option value="T-Shirt">T-Shirt</option>
                        <option value="Stickers">Stickers</option>
                        <option value="Sintraboard">Sintraboard</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Price *</label>
                    <input type="number" step="0.01" name="price" class="input-field" required>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">Description</label>
                <textarea name="description" rows="3" class="input-field"></textarea>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Stock Quantity *</label>
                    <input type="number" name="stock_quantity" class="input-field" value="0" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Status *</label>
                    <select name="status" class="input-field">
                        <option value="Activated">Activated</option>
                        <option value="Deactivated">Deactivated</option>
                    </select>
                </div>
            </div>
            
            <div class="flex gap-2">
                <button type="button" @click="showModal = false" class="btn-secondary flex-1">Cancel</button>
                <button type="submit" class="btn-primary flex-1">Create Product</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
