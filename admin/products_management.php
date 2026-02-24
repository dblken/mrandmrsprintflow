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
        $sku = trim($_POST['sku'] ?? '');
        $category = sanitize($_POST['category'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['Activated','Deactivated']) ? $_POST['status'] : 'Activated';

        if (!$name) {
            $error = 'Product name is required.';
        } elseif ($price <= 0) {
            $error = 'Price must be greater than zero.';
        } else {
            // Allow empty SKU - treat as NULL
            $sku_val = $sku !== '' ? $sku : null;
            if ($sku_val !== null) {
                // Check for duplicate SKU
                $exists = db_query("SELECT product_id FROM products WHERE sku = ?", 's', [$sku_val]);
                if (!empty($exists)) {
                    $error = "A product with SKU '$sku_val' already exists.";
                }
            }

            if (!$error) {
                $result = db_execute(
                    "INSERT INTO products (name, sku, category, description, price, stock_quantity, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    'ssssdis',
                    [$name, $sku_val, $category, $description, $price, $stock_quantity, $status]
                );

                if ($result) {
                    $success = "Product '$name' created successfully!";
                } else {
                    global $conn;
                    $error = "Failed to create product. DB error: " . $conn->error;
                }
            }
        }
    } elseif (isset($_POST['update_product'])) {
        $product_id = (int)$_POST['product_id'];
        $name = sanitize($_POST['name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $category = sanitize($_POST['category'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['Activated','Deactivated']) ? $_POST['status'] : 'Activated';

        $sku_val = $sku !== '' ? $sku : null;

        $result = db_execute(
            "UPDATE products SET name = ?, sku = ?, category = ?, description = ?, price = ?, stock_quantity = ?, status = ?, updated_at = NOW() WHERE product_id = ?",
            'ssssdisi',
            [$name, $sku_val, $category, $description, $price, $stock_quantity, $status, $product_id]
        );

        if ($result) {
            $success = "Product '$name' updated successfully!";
        } else {
            global $conn;
            $error = "Failed to update product. DB error: " . $conn->error;
        }
    } elseif (isset($_POST['delete_product'])) {
        $product_id = (int)$_POST['product_id'];
        $current = db_query("SELECT status FROM products WHERE product_id = ?", 'i', [$product_id]);
        $new_status = (($current[0]['status'] ?? 'Activated') === 'Activated') ? 'Deactivated' : 'Activated';
        db_execute("UPDATE products SET status = ?, updated_at = NOW() WHERE product_id = ?", 'si', [$new_status, $product_id]);
        $success = 'Product ' . strtolower($new_status) . ' successfully!';
    }
}

// Get all products
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$search = trim($_GET['search'] ?? '');
if ($search) {
    $like = '%' . $search . '%';
    $total_products = db_query("SELECT COUNT(*) as total FROM products WHERE name LIKE ? OR sku LIKE ?", 'ss', [$like, $like])[0]['total'] ?? 0;
} else {
    $total_products = db_query("SELECT COUNT(*) as total FROM products")[0]['total'] ?? 0;
}
$total_pages = max(1, ceil($total_products / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

if ($search) {
    $like = '%' . $search . '%';
    $products = db_query("SELECT * FROM products WHERE name LIKE ? OR sku LIKE ? ORDER BY created_at DESC LIMIT $per_page OFFSET $offset", 'ss', [$like, $like]);
} else {
    $products = db_query("SELECT * FROM products ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
}

$page_title = 'Products Management - Admin';

// Summary stats
$stat_total      = db_query("SELECT COUNT(*) as c FROM products")[0]['c'] ?? 0;
$stat_active     = db_query("SELECT COUNT(*) as c FROM products WHERE status='Activated'")[0]['c'] ?? 0;
$stat_inactive   = db_query("SELECT COUNT(*) as c FROM products WHERE status='Deactivated'")[0]['c'] ?? 0;
$stat_low_stock  = db_query("SELECT COUNT(*) as c FROM products WHERE stock_quantity < 10")[0]['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 12px;
            min-width: 80px;
            border: 1px solid transparent;
            background: transparent;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-action.teal { color: #14b8a6; border-color: #14b8a6; }
        .btn-action.teal:hover { background: #14b8a6; color: white; }
        .btn-action.blue { color: #3b82f6; border-color: #3b82f6; }
        .btn-action.blue:hover { background: #3b82f6; color: white; }
        .btn-action.red { color: #ef4444; border-color: #ef4444; }
        .btn-action.red:hover { background: #ef4444; color: white; }

        /* KPI Row */
        .kpi-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
        @media(max-width:900px) { .kpi-row { grid-template-columns:repeat(2,1fr); } }
        .kpi-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:18px 20px; position:relative; overflow:hidden; }
        .kpi-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
        .kpi-card.indigo::before { background:linear-gradient(90deg,#6366f1,#818cf8); }
        .kpi-card.emerald::before { background:linear-gradient(90deg,#059669,#34d399); }
        .kpi-card.rose::before { background:linear-gradient(90deg,#e11d48,#fb7185); }
        .kpi-card.amber::before { background:linear-gradient(90deg,#f59e0b,#fbbf24); }
        .kpi-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:#9ca3af; margin-bottom:6px; }
        .kpi-value { font-size:26px; font-weight:800; color:#1f2937; }
        .kpi-sub { font-size:12px; color:#6b7280; margin-top:4px; }

        /* Search bar inside card */
        .table-toolbar { display:flex; align-items:center; justify-content:space-between; padding:14px 0 16px; border-bottom:1px solid #f3f4f6; margin-bottom:0; gap:12px; flex-wrap:wrap; }
        .search-wrap { position:relative; flex:1; max-width:380px; }
        .search-wrap svg { position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:#9ca3af; pointer-events:none; }
        .search-wrap input { width:100%; padding:8px 12px 8px 36px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; background:#fafafa; outline:none; box-sizing:border-box; transition:border-color .15s; }
        .search-wrap input:focus { border-color:#6366f1; background:#fff; }
        .search-actions { display:flex; gap:8px; }

        /* Modal styles */
        #product-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        #product-modal-overlay.active {
            display: flex;
        }
        #product-modal {
            background: white;
            border-radius: 12px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
            width: 100%;
            max-width: 640px;
            max-height: 90vh;
            overflow-y: auto;
        }
        #product-modal .modal-header {
            padding: 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        #product-modal .modal-body {
            padding: 24px;
        }
        #product-modal .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        #product-modal .form-group {
            margin-bottom: 16px;
        }
        #product-modal .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 6px;
            color: #374151;
        }
        #product-modal .form-group input,
        #product-modal .form-group select,
        #product-modal .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        #product-modal .form-group input:focus,
        #product-modal .form-group select:focus,
        #product-modal .form-group textarea:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        #product-modal .modal-footer {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        #product-modal .modal-footer button {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
        }
        #product-modal .btn-cancel {
            background: #f3f4f6;
            color: #374151;
        }
        #product-modal .btn-cancel:hover { background: #e5e7eb; }
        #product-modal .btn-save {
            background: #1f2937;
            color: white;
        }
        #product-modal .btn-save:hover { background: #374151; }
        #close-modal-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            padding: 4px;
            line-height: 1;
        }
        #close-modal-btn:hover { color: #374151; }
        @media (max-width: 600px) {
            #product-modal .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Products Management</h1>
            <button onclick="openProductModal('create')" class="btn-primary">
                + Add New Product
            </button>
        </header>

        <main>
            <?php if ($success): ?>
                <div style="background:#f0fdf4; border:1px solid #86efac; color:#166534; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
                    ✓ <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div style="background:#fef2f2; border:1px solid #fca5a5; color:#dc2626; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
                    ✗ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- KPI Summary Cards -->
            <div class="kpi-row">
                <div class="kpi-card indigo">
                    <div class="kpi-label">Total Products</div>
                    <div class="kpi-value"><?php echo $stat_total; ?></div>
                    <div class="kpi-sub">All products</div>
                </div>
                <div class="kpi-card emerald">
                    <div class="kpi-label">Active</div>
                    <div class="kpi-value"><?php echo $stat_active; ?></div>
                    <div class="kpi-sub">Visible to customers</div>
                </div>
                <div class="kpi-card rose">
                    <div class="kpi-label">Inactive</div>
                    <div class="kpi-value"><?php echo $stat_inactive; ?></div>
                    <div class="kpi-sub">Deactivated</div>
                </div>
                <div class="kpi-card amber">
                    <div class="kpi-label">Low Stock</div>
                    <div class="kpi-value"><?php echo $stat_low_stock; ?></div>
                    <div class="kpi-sub">Below 10 units</div>
                </div>
            </div>

            <!-- Products Table with Search -->
            <div class="card">
                <div class="table-toolbar">
                    <form method="GET" style="display:contents;">
                        <div class="search-wrap">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                            <input type="text" name="search" placeholder="Search by name or SKU..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="search-actions">
                            <button type="submit" class="btn-action blue" style="min-width:unset;">Search</button>
                            <?php if ($search): ?><a href="?" class="btn-action" style="min-width:unset;border-color:#e5e7eb;color:#6b7280;">Clear</a><?php endif; ?>
                        </div>
                    </form>
                </div>
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
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="8" class="py-8 text-center text-gray-500">
                                        <?php echo $search ? 'No products found matching "' . htmlspecialchars($search) . '"' : 'No products yet.'; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="py-3"><?php echo $product['product_id']; ?></td>
                                        <td class="py-3 font-mono text-xs"><?php echo htmlspecialchars($product['sku'] ?? '—'); ?></td>
                                        <td class="py-3 font-medium"><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td class="py-3"><?php echo htmlspecialchars($product['category'] ?? '—'); ?></td>
                                        <td class="py-3 font-semibold"><?php echo format_currency($product['price']); ?></td>
                                        <td class="py-3">
                                            <?php if ($product['stock_quantity'] < 10): ?>
                                                <span style="color:#dc2626; font-weight:bold;"><?php echo $product['stock_quantity']; ?></span>
                                            <?php else: ?>
                                                <span><?php echo $product['stock_quantity']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3"><?php echo status_badge($product['status'], 'order'); ?></td>
                                        <td class="py-3 text-right" style="white-space:nowrap;">
                                            <a href="/printflow/admin/product_variants.php?product_id=<?php echo $product['product_id']; ?>"
                                               class="btn-action teal">Manage Variants</a>
                                            <button class="btn-action blue"
                                                onclick='openProductModal("edit", <?php echo htmlspecialchars(json_encode($product), ENT_QUOTES); ?>)'>Edit</button>
                                            <form method="POST" class="inline" onsubmit="return confirm('<?php echo $product['status'] === 'Activated' ? 'Deactivate' : 'Activate'; ?> this product?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                <button type="submit" name="delete_product" class="btn-action <?php echo $product['status'] === 'Activated' ? 'red' : 'teal'; ?>">
                                                    <?php echo $product['status'] === 'Activated' ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php echo render_pagination($page, $total_pages, $search ? ['search' => $search] : []); ?>
            </div>
        </main>
    </div>
</div>

<!-- Add/Edit Product Modal -->
<div id="product-modal-overlay" onclick="handleOverlayClick(event)">
    <div id="product-modal">
        <div class="modal-header">
            <h3 id="modal-title" style="font-size:18px; font-weight:700; margin:0;">Add New Product</h3>
            <button id="close-modal-btn" onclick="closeProductModal()">
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" id="product-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" id="modal-mode-input" name="create_product" value="1">
                <input type="hidden" id="modal-product-id" name="product_id" value="">

                <div class="form-row">
                    <div class="form-group">
                        <label for="modal-name">Product Name <span style="color:red">*</span></label>
                        <input type="text" id="modal-name" name="name" required placeholder="e.g. Custom Tarpaulin">
                    </div>
                    <div class="form-group">
                        <label for="modal-sku">SKU</label>
                        <input type="text" id="modal-sku" name="sku" placeholder="e.g. TARP001 (optional)">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="modal-category">Category <span style="color:red">*</span></label>
                        <select id="modal-category" name="category" required>
                            <option value="">-- Select Category --</option>
                            <option value="Tarpaulin">Tarpaulin</option>
                            <option value="T-Shirt">T-Shirt</option>
                            <option value="Stickers">Stickers</option>
                            <option value="Sintraboard">Sintraboard</option>
                            <option value="Apparel">Apparel</option>
                            <option value="Signage">Signage</option>
                            <option value="Merchandise">Merchandise</option>
                            <option value="Print">Print</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="modal-price">Price (PHP) <span style="color:red">*</span></label>
                        <input type="number" id="modal-price" name="price" step="0.01" min="0.01" required placeholder="0.00">
                    </div>
                </div>

                <div class="form-group">
                    <label for="modal-description">Description</label>
                    <textarea id="modal-description" name="description" rows="3" placeholder="Optional description..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="modal-stock">Stock Quantity <span style="color:red">*</span></label>
                        <input type="number" id="modal-stock" name="stock_quantity" min="0" required value="0">
                    </div>
                    <div class="form-group">
                        <label for="modal-status">Status</label>
                        <select id="modal-status" name="status">
                            <option value="Activated">Activated</option>
                            <option value="Deactivated">Deactivated</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeProductModal()">Cancel</button>
                    <button type="submit" id="modal-submit-btn" class="btn-save">Create Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openProductModal(mode, product) {
    var overlay = document.getElementById('product-modal-overlay');
    var title   = document.getElementById('modal-title');
    var modeInput = document.getElementById('modal-mode-input');
    var submitBtn = document.getElementById('modal-submit-btn');

    // Clear form
    document.getElementById('product-form').reset();

    if (mode === 'edit' && product) {
        title.textContent = 'Edit Product';
        modeInput.name = 'update_product';
        submitBtn.textContent = 'Save Changes';

        document.getElementById('modal-product-id').value  = product.product_id || '';
        document.getElementById('modal-name').value        = product.name || '';
        document.getElementById('modal-sku').value         = product.sku || '';
        document.getElementById('modal-category').value    = product.category || '';
        document.getElementById('modal-price').value       = product.price || '';
        document.getElementById('modal-description').value = product.description || '';
        document.getElementById('modal-stock').value       = product.stock_quantity || 0;
        document.getElementById('modal-status').value      = product.status || 'Activated';
    } else {
        title.textContent = 'Add New Product';
        modeInput.name = 'create_product';
        submitBtn.textContent = 'Create Product';
        document.getElementById('modal-product-id').value = '';
        document.getElementById('modal-stock').value = '0';
        document.getElementById('modal-status').value = 'Activated';
    }

    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    document.getElementById('modal-name').focus();
}

function closeProductModal() {
    document.getElementById('product-modal-overlay').classList.remove('active');
    document.body.style.overflow = '';
}

function handleOverlayClick(event) {
    if (event.target === document.getElementById('product-modal-overlay')) {
        closeProductModal();
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeProductModal();
});
</script>

</body>
</html>
