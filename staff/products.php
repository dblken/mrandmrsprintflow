<?php
/**
 * Staff Products (Inventory) Page
 * PrintFlow - Printing Shop PWA
 * Read-only view for staff
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');
require_once __DIR__ . '/../includes/staff_pending_check.php';

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

// Pagination settings
$items_per_page = 15;
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
    $count_sql .= " AND (name LIKE ? OR sku LIKE ?)";
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
$categories = db_query("SELECT DISTINCT category FROM products WHERE status = 'Activated' ORDER BY category ASC");

$page_title = 'Products & Inventory - Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Products & Inventory</h1>
        </header>

        <main>
            <!-- Filters -->
            <div class="card">
                <form method="GET" id="productFilterForm" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:flex-end;">
                    <div>
                        <label>Search</label>
                        <input type="text" id="productSearchInput" name="search" class="input-field" placeholder="Name or SKU..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div>
                        <label>Category</label>
                        <select name="category" id="productCategorySelect" class="input-field">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <!-- Products Table -->
            <div class="card">
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Name</th>
                                 <th>Category</th>
                                <th>Type</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="productsTableBody">
                            <?php foreach ($products as $product): ?>
                                <tr data-name="<?php echo htmlspecialchars(strtolower($product['name'])); ?>"
                                    data-sku="<?php echo htmlspecialchars(strtolower($product['sku'])); ?>"
                                    data-category="<?php echo htmlspecialchars(strtolower($product['category'])); ?>">
                                    <td style="font-family:monospace; font-size:12px;"><?php echo htmlspecialchars($product['sku']); ?></td>
                                    <td style="font-weight:500;"><?php echo htmlspecialchars($product['name']); ?></td>
                                     <td><?php echo htmlspecialchars($product['category']); ?></td>
                                    <td><span class="badge <?php echo $product['product_type'] === 'fixed' ? 'badge-blue' : 'badge-purple'; ?>"><?php echo ucfirst($product['product_type']); ?></span></td>
                                    <td style="font-weight:600;"><?php echo format_currency($product['price']); ?></td>
                                    <td>
                                        <?php if ($product['stock_quantity'] < 10): ?>
                                            <span style="color:#dc2626; font-weight:700;"><?php echo $product['stock_quantity']; ?></span>
                                            <span style="font-size:11px; color:#dc2626; font-weight:600;">LOW</span>
                                        <?php else: ?>
                                            <span style="color:#16a34a;"><?php echo $product['stock_quantity']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo status_badge($product['status'], 'order'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php echo get_pagination_links($current_page, $total_pages, ['category' => $category, 'search' => $search]); ?>
        </main>
    </div>
</div>

<script>
const productSearch = document.getElementById('productSearchInput');
const productCategory = document.getElementById('productCategorySelect');
const productsTableBody = document.getElementById('productsTableBody');
const productRows = productsTableBody ? Array.from(productsTableBody.querySelectorAll('tr')) : [];

function filterProductsLocally() {
    const q = (productSearch?.value || '').trim().toLowerCase();
    const cat = (productCategory?.value || '').trim().toLowerCase();

    productRows.forEach((row) => {
        const name = row.getAttribute('data-name') || '';
        const sku = row.getAttribute('data-sku') || '';
        const category = row.getAttribute('data-category') || '';
        const matchesText = q === '' || name.includes(q) || sku.includes(q);
        const matchesCategory = cat === '' || category === cat;
        row.style.display = (matchesText && matchesCategory) ? '' : 'none';
    });
}

if (productSearch) {
    productSearch.addEventListener('input', filterProductsLocally);
}
if (productCategory) {
    productCategory.addEventListener('change', filterProductsLocally);
}
filterProductsLocally();
</script>

</body>
</html>
