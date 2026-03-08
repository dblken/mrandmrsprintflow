<?php
/**
 * Admin Storefront Management
 * PrintFlow - Printing Shop PWA
 * Manage customer-facing product details (Images, Featured Status, Public Visibility)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$success = '';
$error = '';

// Handle Image Upload & Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    

    if (isset($_POST['toggle_featured'])) {
        $product_id = (int)$_POST['product_id'];
        $current_status = (int)$_POST['current_status'];
        $new_status = $current_status ? 0 : 1;
        
        db_execute("UPDATE products SET is_featured = ? WHERE product_id = ?", 'ii', [$new_status, $product_id]);
        $success = "Product featured status updated.";
    }
    
    // Update Product Image
    if (isset($_POST['upload_image'])) {
        $product_id = (int)$_POST['product_id'];
        
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
            $file = $_FILES['product_image'];
            
            if (!in_array($file['type'], $allowed)) {
                $error = "Invalid file type. Only JPG, PNG, and WEBP allowed.";
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = "File too large. Max 5MB.";
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'product_' . $product_id . '_' . time() . '.' . $ext;
                $upload_dir = __DIR__ . '/../public/assets/uploads/products/';
                
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                // Get old image to delete
                $old_img = db_query("SELECT product_image FROM products WHERE product_id = ?", 'i', [$product_id]);
                if (!empty($old_img[0]['product_image'])) {
                    $old_path = $upload_dir . $old_img[0]['product_image'];
                    if (file_exists($old_path)) unlink($old_path);
                }
                
                if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                    db_execute("UPDATE products SET product_image = ? WHERE product_id = ?", 'si', [$filename, $product_id]);
                    $success = "Product image updated successfully.";
                } else {
                    $error = "Failed to upload image.";
                }
            }
        }
    }
}

// Get ALL products (storefront manages images/featured for all products)
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$search = trim($_GET['search'] ?? '');
$filter_stock = $_GET['stock'] ?? '';

$where = "WHERE 1=1";
$params = [];
$types = '';

if ($search) {
    $where .= " AND (name LIKE ? OR category LIKE ? OR sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "sss";
}

if ($filter_stock === 'low') {
    $where .= " AND stock_quantity <= 10 AND stock_quantity > 0";
} elseif ($filter_stock === 'out') {
    $where .= " AND stock_quantity <= 0";
}

$total_products = db_query("SELECT COUNT(*) as total FROM products $where", $types, $params)[0]['total'] ?? 0;
$total_pages = max(1, ceil($total_products / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$products = db_query("SELECT * FROM products $where ORDER BY is_featured DESC, stock_quantity ASC, name ASC LIMIT $per_page OFFSET $offset", $types, $params);

// Stock summary counts
$stock_summary = db_query("SELECT 
    SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock,
    SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= 10 THEN 1 ELSE 0 END) as low_stock
    FROM products")[0] ?? ['out_of_stock' => 0, 'low_stock' => 0];

$page_title = 'Storefront Management - Admin';
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
        .product-card-row {
            display: grid;
            grid-template-columns: 80px 1fr auto auto;
            gap: 20px;
            align-items: center;
            padding: 16px;
            border-bottom: 1px solid #f3f4f6;
            background: white;
            transition: all 0.2s;
        }
        .product-card-row:hover { background: #f9fafb; }
        .product-img-box {
            width: 80px; height: 80px;
            border-radius: 8px;
            background: #f3f4f6;
            overflow: hidden;
            display: flex; align-items: center; justify-content: center;
            border: 1px solid #e5e7eb;
            position: relative;
            cursor: pointer;
        }
        .product-img-box img { width: 100%; height: 100%; object-fit: cover; }
        .product-img-box:hover .upload-overlay { opacity: 1; }
        .upload-overlay {
            position: absolute; inset: 0;
            background: rgba(0,0,0,0.5);
            display: flex; align-items: center; justify-content: center;
            color: white; opacity: 0;
            transition: opacity 0.2s;
        }
        .featured-star {
            cursor: pointer;
            color: #d1d5db;
            transition: all 0.2s;
        }
        .featured-star.active { color: #fbbf24; fill: #fbbf24; }
        .featured-star:hover { transform: scale(1.1); }
        </style>
        <style>
        /* Add Product Modal */
        #add-product-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        #add-product-modal-overlay.active { display: flex; }
        #add-product-modal {
            background: white;
            border-radius: 12px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
            width: 100%;
            max-width: 580px;
            max-height: 90vh;
            overflow-y: auto;
        }
        #add-product-modal .modal-hdr {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        #add-product-modal .modal-bdy { padding: 24px; }
        #add-product-modal .fg { margin-bottom: 16px; }
        #add-product-modal .fg label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 6px;
        }
        #add-product-modal .fg input,
        #add-product-modal .fg select,
        #add-product-modal .fg textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }
        #add-product-modal .fg input:focus,
        #add-product-modal .fg select:focus,
        #add-product-modal .fg textarea:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
        }
        #add-product-modal .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        #add-product-modal .modal-ftr {
            display: flex; gap: 12px; margin-top: 20px;
        }
        #add-product-modal .modal-ftr button {
            flex: 1; padding: 11px; border-radius: 8px;
            font-size: 14px; font-weight: 600; cursor: pointer; border: none;
        }
        @media(max-width:560px) { #add-product-modal .row2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <header style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1 class="page-title" style="margin:0;">Storefront Management</h1>
            <div style="display:flex; gap:12px; align-items:center;">
                <div class="search-box">
                    <form method="GET" style="display:flex; gap:10px;">
                        <input type="text" name="search" id="searchInput" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>" class="input-field" style="width:200px; height:38px;">
                        <select name="stock" class="input-field" style="height:38px; width:150px;">
                            <option value="">All Stock</option>
                            <option value="low" <?php echo $filter_stock === 'low' ? 'selected' : ''; ?>>Low Stock (≤10)</option>
                            <option value="out" <?php echo $filter_stock === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                        <button type="submit" class="btn-secondary" style="height:38px; padding:0 15px;">Filter</button>
                    </form>
                </div>
                <a href="/printflow/admin/products_management.php" 
                   class="btn-primary"
                   style="height:38px; padding:0 20px; display:inline-flex; align-items:center; text-decoration:none;">
                    + Add New Product
                </a>
            </div>
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

            <?php if ($stock_summary['out_of_stock'] > 0 || $stock_summary['low_stock'] > 0): ?>
            <div style="display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
                <?php if ($stock_summary['out_of_stock'] > 0): ?>
                <div style="background:#fef2f2; border:1px solid #fca5a5; color:#dc2626; padding:10px 16px; border-radius:8px; font-size:14px; font-weight:600;">
                    ⚠️ <?php echo $stock_summary['out_of_stock']; ?> product(s) are <strong>out of stock</strong>
                    &mdash; <a href="?stock=out" style="color:#dc2626; text-decoration:underline;">View</a>
                </div>
                <?php endif; ?>
                <?php if ($stock_summary['low_stock'] > 0): ?>
                <div style="background:#fffbeb; border:1px solid #fcd34d; color:#92400e; padding:10px 16px; border-radius:8px; font-size:14px; font-weight:600;">
                    ⚠️ <?php echo $stock_summary['low_stock']; ?> product(s) have <strong>low stock</strong> (≤10 units)
                    &mdash; <a href="?stock=low" style="color:#92400e; text-decoration:underline;">View</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <div style="display:grid; grid-template-columns: 80px 1fr auto auto; gap:20px; padding:12px 16px; background:#f9fafb; border-bottom:1px solid #e5e7eb; font-weight:600; font-size:13px; color:#6b7280; text-transform:uppercase;">
                    <div>Image</div>
                    <div>Product Details</div>
                    <div style="text-align:center;">Featured</div>
                    <div style="text-align:right;">Actions</div>
                </div>

                <?php if (empty($products)): ?>
                    <div id="emptyProductsMessage" style="padding:40px; text-align:center; color:#6b7280;">
                        <?php echo $search ? 'No products found matching "' . htmlspecialchars($search) . '"' : 'No products found.'; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <div class="product-card-row">
                            <!-- Image Upload -->
                            <form method="POST" enctype="multipart/form-data" id="form-img-<?php echo $product['product_id']; ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="upload_image" value="1">
                                <input type="hidden" name="product_image_update" value="1">
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                <input type="file" name="product_image" id="file-<?php echo $product['product_id']; ?>" style="display:none;" onchange="document.getElementById('form-img-<?php echo $product['product_id']; ?>').submit()">
                                
                                <div class="product-img-box" onclick="document.getElementById('file-<?php echo $product['product_id']; ?>').click()">
                                    <?php if (!empty($product['product_image'])): ?>
                                        <img src="/printflow/public/assets/uploads/products/<?php echo $product['product_image']; ?>?t=<?php echo time(); ?>">
                                    <?php else: ?>
                                        <span style="font-size:24px;">📦</span>
                                    <?php endif; ?>
                                    <div class="upload-overlay">
                                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    </div>
                                </div>
                            </form>

                            <!-- Details -->
                            <div>
                                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:4px;">
                                    <h3 style="font-size:15px; font-weight:600; color:#1f2937; margin:0;"><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <?php if ($product['status'] === 'Deactivated'): ?>
                                        <span style="background:#f3f4f6; color:#6b7280; font-size:11px; font-weight:600; padding:2px 8px; border-radius:10px;">DEACTIVATED</span>
                                    <?php endif; ?>
                                    <?php if ($product['stock_quantity'] <= 0): ?>
                                        <span style="background:#fef2f2; color:#dc2626; font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px;">⚠ OUT OF STOCK</span>
                                    <?php elseif ($product['stock_quantity'] <= 10): ?>
                                        <span style="background:#fffbeb; color:#92400e; font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px;">⚠ LOW STOCK (<?php echo $product['stock_quantity']; ?>)</span>
                                    <?php else: ?>
                                        <span style="background:#f0fdf4; color:#166534; font-size:11px; font-weight:600; padding:2px 8px; border-radius:10px;">✓ In Stock (<?php echo $product['stock_quantity']; ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <p style="font-size:13px; color:#6b7280; margin:0 0 2px;"><?php echo htmlspecialchars($product['category']); ?> • <?php echo format_currency($product['price']); ?></p>
                                <p style="font-size:11px; color:#9ca3af; margin:0;">SKU: <?php echo htmlspecialchars($product['sku'] ?? '—'); ?></p>
                            </div>

                            <!-- Featured Toggle -->
                            <div style="text-align:center;">
                                <form method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="toggle_featured" value="1">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $product['is_featured']; ?>">
                                    <button type="submit" style="background:none; border:none; padding:0;" title="<?php echo $product['is_featured'] ? 'Remove from Featured' : 'Mark as Featured'; ?>">
                                        <svg class="featured-star <?php echo $product['is_featured'] ? 'active' : ''; ?>" width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>

                            <!-- Actions -->
                            <div style="text-align:right;">
                                <a href="/printflow/admin/products_management.php?edit=<?php echo $product['product_id']; ?>" 
                                   class="btn-secondary" 
                                   style="padding:6px 12px; font-size:13px; text-decoration:none; display:inline-block;">
                                    ✏ Edit Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div id="storefrontPagination">
                    <?php echo render_pagination($page, $total_pages, array_filter(['search' => $search, 'stock' => $filter_stock])); ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Real-time Search for Storefront Management
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const productRows = document.querySelectorAll('.product-card-row');
    const emptyMessage = document.getElementById('emptyProductsMessage');
    const pagination = document.getElementById('storefrontPagination');
    
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            let visibleCount = 0;
            
            productRows.forEach(row => {
                const productText = row.textContent.toLowerCase();
                
                if (productText.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Handle empty state and pagination
            if (searchTerm && visibleCount === 0) {
                if (emptyMessage) {
                    emptyMessage.textContent = `No products found matching "${e.target.value}"`;
                    emptyMessage.style.display = 'block';
                }
                if (pagination) pagination.style.display = 'none';
            } else if (visibleCount === 0 && !searchTerm) {
                if (emptyMessage) {
                    emptyMessage.textContent = 'No products found.';
                    emptyMessage.style.display = 'block';
                }
                if (pagination) pagination.style.display = 'none';
            } else {
                if (emptyMessage) emptyMessage.style.display = 'none';
                if (pagination) pagination.style.display = searchTerm ? 'none' : '';
            }
        });
    }
});
</script>

</body>
</html>
