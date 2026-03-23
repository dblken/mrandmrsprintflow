<?php
/**
 * Admin Products Management Page
 * PrintFlow - Printing Shop PWA  
 * Full CRUD for products
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'Manager']);

$current_user = get_logged_in_user();

$error = '';
$success = '';

/**
 * Handle product photo upload
 * @param array $file The $_FILES array element
 * @param int|null $product_id For updating existing photo
 * @return string|null Path to uploaded file or null
 */
function handle_product_photo_upload($file, $product_id = null) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // No file uploaded
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error: ' . $file['error']);
    }
    
    // Check file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File size must be less than 5MB');
    }
    
    // Check MIME type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed');
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = __DIR__ . '/../uploads/products';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'product_' . time() . '_' . uniqid() . '.' . $ext;
    $target_path = $upload_dir . '/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        throw new Exception('Failed to upload file to server');
    }
    
    return '/printflow/uploads/products/' . $filename;
}

// Handle product creation/update/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['create_product'])) {
        $name = preg_replace('/\s+/', ' ', trim($_POST['name'] ?? ''));
        $sku = trim($_POST['sku'] ?? '');
        $category = sanitize($_POST['category'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
        $product_type = in_array($_POST['product_type'] ?? '', ['fixed','custom']) ? $_POST['product_type'] : 'custom';
        $low_stock_level = (int)($_POST['low_stock_level'] ?? 10);
        if ($low_stock_level < 1) $low_stock_level = 10;
        $statusRaw = trim((string)($_POST['status'] ?? ''));
        $status = ($statusRaw === 'Deactivated') ? 'Deactivated' : 'Activated';

        // Server-side validation
        if (strlen($name) < 2) {
            $error = $name ? 'Product name must be at least 2 characters.' : 'Product name is required.';
        } elseif (strlen($name) > 100) {
            $error = 'Product name must not exceed 100 characters.';
        } elseif (preg_match('/^\d+$/', $name)) {
            $error = 'Product name cannot contain only numbers.';
        } elseif (strlen($description) > 500) {
            $error = 'Description must not exceed 500 characters.';
        } elseif ($price < 1.00 || $price > 1000000) {
            $error = $price <= 0 ? 'Price is required and must be greater than 0.' : 'Price must be between ₱1.00 and ₱1,000,000.00.';
        } elseif ($stock_quantity < 0) {
            $error = 'Quantity must be a non-negative whole number.';
        } elseif ($low_stock_level < 0) {
            $error = 'Low stock level must be a non-negative whole number.';
        } elseif ($low_stock_level > $stock_quantity) {
            $error = 'Low stock level cannot exceed quantity.';
        } elseif (empty($category) || $category === '-- Select Category --') {
            $error = 'Please select a category.';
        } elseif (empty($_FILES['photo']['name']) || ($_FILES['photo']['error'] ?? 0) === UPLOAD_ERR_NO_FILE) {
            $error = 'Product photo is required.';
        } else {
            // Allow empty SKU - treat as NULL
            $sku_val = $sku !== '' ? trim($sku) : null;
            if ($sku_val !== null && $sku_val !== '') {
                // Check for duplicate SKU
                $exists = db_query("SELECT product_id FROM products WHERE sku = ?", 's', [$sku_val]);
                if (!empty($exists)) {
                    $error = "A product with SKU '" . htmlspecialchars($sku_val) . "' already exists.";
                }
            }

            if (!$error) {
                try {
                    // Handle photo upload
                    $photo_path = handle_product_photo_upload($_FILES['photo'] ?? null);
                    
                    $result = db_execute(
                        "INSERT INTO products (name, sku, category, product_type, description, price, stock_quantity, low_stock_level, status, photo_path, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                        'sssssdiiss',
                        [$name, $sku_val, $category, $product_type, $description, $price, $stock_quantity, $low_stock_level, $status, $photo_path]
                    );

                    if ($result) {
                        $success = "Product '$name' created successfully!";
                    } else {
                        global $conn;
                        $error = "Failed to create product. DB error: " . $conn->error;
                    }
                } catch (Exception $e) {
                    $error = "Upload error: " . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['update_product'])) {
        $product_id = (int)$_POST['product_id'];
        $name = preg_replace('/\s+/', ' ', trim($_POST['name'] ?? ''));
        $sku = trim($_POST['sku'] ?? '');
        $category = sanitize($_POST['category'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
        $product_type = in_array($_POST['product_type'] ?? '', ['fixed','custom']) ? $_POST['product_type'] : 'custom';
        $low_stock_level = (int)($_POST['low_stock_level'] ?? 10);
        $statusRaw = trim((string)($_POST['status'] ?? ''));
        $status = ($statusRaw === 'Deactivated') ? 'Deactivated' : 'Activated';

        $sku_val = $sku !== '' ? trim($sku) : null;

        // Server-side validation for update (photo optional)
        if (strlen($name) < 2) {
            $error = $name ? 'Product name must be at least 2 characters.' : 'Product name is required.';
        } elseif (strlen($name) > 100) {
            $error = 'Product name must not exceed 100 characters.';
        } elseif (preg_match('/^\d+$/', $name)) {
            $error = 'Product name cannot contain only numbers.';
        } elseif (strlen($description) > 500) {
            $error = 'Description must not exceed 500 characters.';
        } elseif ($price < 1.00 || $price > 1000000) {
            $error = $price <= 0 ? 'Price is required and must be greater than 0.' : 'Price must be between ₱1.00 and ₱1,000,000.00.';
        } elseif ($stock_quantity < 0 || $stock_quantity != floor($stock_quantity)) {
            $error = 'Quantity must be a non-negative whole number.';
        } elseif ($low_stock_level < 0 || $low_stock_level != floor($low_stock_level)) {
            $error = 'Low stock level must be a non-negative whole number.';
        } elseif ($low_stock_level > $stock_quantity) {
            $error = 'Low stock level cannot exceed quantity.';
        } elseif (empty($category) || $category === '-- Select Category --') {
            $error = 'Please select a category.';
        } else {
            if ($sku_val !== null && $sku_val !== '') {
                $exists = db_query("SELECT product_id FROM products WHERE sku = ? AND product_id != ?", 'si', [$sku_val, $product_id]);
                if (!empty($exists)) {
                    $error = "A product with SKU '" . htmlspecialchars($sku_val) . "' already exists.";
                }
            }
        }

        if (!$error) {
        try {
            // Handle photo upload (only if a new file is provided)
            $photo_path = handle_product_photo_upload($_FILES['photo'] ?? null);
            
            if ($photo_path) {
                // Update with new photo
                $result = db_execute(
                    "UPDATE products SET name = ?, sku = ?, category = ?, product_type = ?, description = ?, price = ?, stock_quantity = ?, low_stock_level = ?, status = ?, photo_path = ?, updated_at = NOW() WHERE product_id = ?",
                    'sssssdiissi',
                    [$name, $sku_val, $category, $product_type, $description, $price, $stock_quantity, $low_stock_level, $status, $photo_path, $product_id]
                );
            } else {
                // Update without changing photo
                $result = db_execute(
                    "UPDATE products SET name = ?, sku = ?, category = ?, product_type = ?, description = ?, price = ?, stock_quantity = ?, low_stock_level = ?, status = ?, updated_at = NOW() WHERE product_id = ?",
                    'sssssdiisi',
                    [$name, $sku_val, $category, $product_type, $description, $price, $stock_quantity, $low_stock_level, $status, $product_id]
                );
            }

            if ($result) {
                $success = "Product '$name' updated successfully!";
            } else {
                global $conn;
                $error = "Failed to update product. DB error: " . $conn->error;
            }
        } catch (Exception $e) {
            $error = "Upload error: " . $e->getMessage();
        }
    }
} elseif (isset($_POST['archive_product'])) {
    $product_id = (int)$_POST['product_id'];
    db_execute("UPDATE products SET status = 'Archived', updated_at = NOW() WHERE product_id = ?", 'i', [$product_id]);
    $success = 'Product archived successfully!';
} elseif (isset($_POST['restore_product'])) {
    $product_id = (int)$_POST['product_id'];
    db_execute("UPDATE products SET status = 'Activated', updated_at = NOW() WHERE product_id = ?", 'i', [$product_id]);
    $success = 'Product restored successfully!';
} elseif (isset($_POST['delete_product'])) {
    $product_id = (int)$_POST['product_id'];
    $current = db_query("SELECT status FROM products WHERE product_id = ?", 'i', [$product_id]);
    $status = $current[0]['status'] ?? '';
    
    if ($status === 'Archived') {
        db_execute("DELETE FROM products WHERE product_id = ?", 'i', [$product_id]);
        $success = 'Product deleted permanently!';
    } else {
        $new_status = ($status === 'Activated') ? 'Deactivated' : 'Activated';
        db_execute("UPDATE products SET status = ?, updated_at = NOW() WHERE product_id = ?", 'si', [$new_status, $product_id]);
        $success = 'Product ' . strtolower($new_status) . ' successfully!';
    }
}
}

// Handle AJAX for Archive Storage Modal
if (isset($_GET['get_archived'])) {
    header('Content-Type: application/json');
    $archived = db_query("SELECT * FROM products WHERE status = 'Archived' ORDER BY updated_at DESC");
    
    $html = '<table class="orders-table" style="width:100%;">';
    $html .= '<thead><tr><th>SKU</th><th>Name</th><th>Category</th><th style="text-align:right;">Actions</th></tr></thead>';
    $html .= '<tbody>';
    
    if (empty($archived)) {
        $html .= '<tr><td colspan="4" style="padding:40px;text-align:center;color:#9ca3af;">No archived products found.</td></tr>';
    } else {
        foreach ($archived as $p) {
            $html .= '<tr>';
            $html .= '<td style="font-family:monospace;font-size:12px;">' . htmlspecialchars($p['sku'] ?? '—') . '</td>';
            $html .= '<td style="font-weight:500; max-width:300px; word-break: break-word; overflow-wrap: anywhere;">' . htmlspecialchars($p['name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($p['category'] ?? '—') . '</td>';
            $html .= '<td style="text-align:right;white-space:nowrap;">';
            $html .= '<form method="POST" class="inline product-status-form" data-pf-skip-guard style="display:inline-block;margin-right:4px;" data-action="Restore" data-product-name="' . htmlspecialchars($p['name'], ENT_QUOTES) . '" onsubmit="showProductStatusModal(event, this);return false;">';
            $html .= csrf_field();
            $html .= '<input type="hidden" name="product_id" value="' . $p['product_id'] . '">';
            $html .= '<button type="submit" name="restore_product" class="btn-action teal">Restore</button></form>';
            $html .= '<form method="POST" class="inline product-status-form" data-pf-skip-guard style="display:inline-block;" data-action="Delete Permanently" data-product-name="' . htmlspecialchars($p['name'], ENT_QUOTES) . '" onsubmit="showProductStatusModal(event, this);return false;">';
            $html .= csrf_field();
            $html .= '<input type="hidden" name="product_id" value="' . $p['product_id'] . '">';
            $html .= '<button type="submit" name="delete_product" class="btn-action red">Delete</button></form>';
            $html .= '</td></tr>';
        }
    }
    $html .= '</tbody></table>';
    
    echo json_encode(['success' => true, 'html' => $html]);
    exit;
}

// Get all products
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 10;
$search        = trim($_GET['search'] ?? '');
$cat_filter    = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$stock_filter  = $_GET['stock_status'] ?? '';
$sort_by       = $_GET['sort'] ?? 'newest';
$date_from     = $_GET['date_from'] ?? '';
$date_to       = $_GET['date_to'] ?? '';

$sql    = "SELECT * FROM products WHERE status != 'Archived'";
$params = []; $types = '';

if ($search) {
    $like = '%'.$search.'%';
    $sql .= " AND (name LIKE ? OR sku LIKE ?)";
    $params = array_merge($params, [$like, $like]);
    $types .= 'ss';
}
if ($cat_filter) {
    $sql .= " AND category = ?";
    $params[] = $cat_filter;
    $types .= 's';
}
if ($status_filter) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= 's';
}
if ($stock_filter === 'out_of_stock') {
    $sql .= " AND stock_quantity <= 0";
} elseif ($stock_filter === 'low_stock') {
    $sql .= " AND stock_quantity > 0 AND stock_quantity <= COALESCE(low_stock_level, 10)";
} elseif ($stock_filter === 'in_stock') {
    $sql .= " AND stock_quantity > COALESCE(low_stock_level, 10)";
}
if (!empty($date_from)) {
    $sql .= " AND DATE(created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}
if (!empty($date_to)) {
    $sql .= " AND DATE(created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$count_sql = str_replace('SELECT *', 'SELECT COUNT(*) as total', $sql);
$total_products = db_query($count_sql, $types ?: null, $params ?: null)[0]['total'] ?? 0;
$total_pages = max(1, ceil($total_products / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$order_clause = match($sort_by) {
    'oldest' => "created_at ASC",
    'az'     => "name ASC",
    'za'     => "name DESC",
    default  => "created_at DESC"
};
$sql .= " ORDER BY $order_clause LIMIT $per_page OFFSET $offset";
$products = db_query($sql, $types ?: null, $params ?: null) ?: [];

$page_title = 'Products Management - Admin';

// Summary stats
$stat_total     = db_query("SELECT COUNT(*) as c FROM products WHERE status != 'Archived'")[0]['c'] ?? 0;
$stat_active    = db_query("SELECT COUNT(*) as c FROM products WHERE status='Activated'")[0]['c'] ?? 0;
$stat_inactive  = db_query("SELECT COUNT(*) as c FROM products WHERE status='Deactivated'")[0]['c'] ?? 0;
$stat_low_stock = db_query("SELECT COUNT(*) as c FROM products WHERE status != 'Archived' AND stock_quantity <= COALESCE(low_stock_level, 10)")[0]['c'] ?? 0;

// Distinct categories for filter
$categories = db_query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' AND status != 'Archived' ORDER BY category ASC") ?: [];

// AJAX response
if (isset($_GET['ajax'])) {
    ob_start();
    ?>
    <table class="orders-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>SKU</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Stock Status</th>
                <th>Status</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody id="productsTableBody">
            <?php if (empty($products)): ?>
                <tr><td colspan="9" style="padding:40px;text-align:center;color:#9ca3af;font-size:14px;">No products found.</td></tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <?php
                    $low = (int)($product['low_stock_level'] ?? 10);
                    $stockStatus = get_stock_status($product['stock_quantity'], $low);
                    $isLowOrOut = in_array($stockStatus, ['Low Stock','Out of Stock']);
                    $stockBadge = match($stockStatus) {
                        'In Stock' => 'background:#dcfce7;color:#166534;',
                        'Low Stock' => 'background:#fef9c3;color:#854d0e;',
                        'Out of Stock' => 'background:#fee2e2;color:#991b1b;',
                        default => 'background:#f3f4f6;color:#374151;'
                    };
                    ?>
                    <tr class="<?php echo $isLowOrOut ? 'low-stock-row' : ''; ?>" onclick="openViewModal(<?php echo htmlspecialchars(json_encode($product), ENT_QUOTES); ?>)">
                        <td style="color:#1f2937;"><?php echo $product['product_id']; ?></td>
                        <td style="font-family:monospace;font-size:12px;"><?php echo htmlspecialchars($product['sku'] ?? '—'); ?></td>
                        <td style="font-weight:500;color:#1f2937;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['category'] ?? '—'); ?></td>
                        <td style="font-weight:600;color:#1f2937;white-space:nowrap;">₱<?php echo number_format($product['price'], 2); ?></td>
                        <td>
                            <span style="font-weight:<?php echo $isLowOrOut ? 'bold' : '400'; ?>;color:<?php echo $stockStatus === 'Out of Stock' ? '#dc2626' : ($stockStatus === 'Low Stock' ? '#b45309' : '#374151'); ?>;"><?php echo $product['stock_quantity']; ?></span>
                        </td>
                        <td>
                            <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;<?php echo $stockBadge; ?>"><?php echo $stockStatus; ?></span>
                        </td>
                        <td>
                            <?php $sc = match($product['status']) { 'Activated' => 'background:#dcfce7;color:#166534;', 'Deactivated' => 'background:#fee2e2;color:#991b1b;', default => 'background:#fef9c3;color:#854d0e;' }; ?>
                            <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;<?php echo $sc; ?>"><?php echo $product['status']; ?></span>
                        </td>
                        <td style="text-align:right;white-space:nowrap;" onclick="event.stopPropagation();">
                            <button class="btn-action blue" onclick='openModal("edit", <?php echo htmlspecialchars(json_encode($product), ENT_QUOTES); ?>)'>Edit</button>
                            
                            <?php if ($product['status'] !== 'Archived'): ?>
                                <form method="POST" class="inline product-status-form" data-pf-skip-guard data-action="<?php echo $product['status'] === 'Activated' ? 'Deactivate' : 'Activate'; ?>" data-product-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>" onsubmit="showProductStatusModal(event, this);return false;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <button type="submit" name="delete_product" class="btn-action <?php echo $product['status'] === 'Activated' ? 'red' : 'teal'; ?>">
                                        <?php echo $product['status'] === 'Activated' ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                <?php if ($product['status'] === 'Deactivated'): ?>
                                    <form method="POST" class="inline product-status-form" data-pf-skip-guard data-action="Archive" data-product-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>" onsubmit="showProductStatusModal(event, this);return false;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                        <button type="submit" name="archive_product" class="btn-action gray">Archive</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <form method="POST" class="inline product-status-form" data-pf-skip-guard data-action="Restore" data-product-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>" onsubmit="showProductStatusModal(event, this);return false;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <button type="submit" name="restore_product" class="btn-action teal">Restore</button>
                                </form>
                                <form method="POST" class="inline product-status-form" data-pf-skip-guard data-action="Delete Permanently" data-product-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>" onsubmit="showProductStatusModal(event, this);return false;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <button type="submit" name="delete_product" class="btn-action red">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
    $table_html = ob_get_clean();
    ob_start();
    $pp = array_filter(['search'=>$search,'category'=>$cat_filter,'status'=>$status_filter,'stock_status'=>$stock_filter,'sort'=>$sort_by,'date_from'=>$date_from,'date_to'=>$date_to], function($v) { return $v !== null && $v !== ''; });
    echo render_pagination($page, $total_pages, $pp);
    $pagination_html = ob_get_clean();
    echo json_encode([
        'success'    => true,
        'table'      => $table_html,
        'pagination' => $pagination_html,
        'count'      => number_format($total_products),
        'badge'      => count(array_filter([$search,$cat_filter,$status_filter,$stock_filter,$date_from,$date_to], function($v) { return $v !== null && $v !== ''; }))
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <script src="/printflow/public/assets/js/alpine.min.js" defer></script>
    <script src="/printflow/public/assets/js/product-form-validation.js" defer></script>
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
        .btn-action.gray { color: #6b7280; border-color: #d1d5db; }
        .btn-action.gray:hover { background: #6b7280; color: white; }

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
        .kpi-sub { font-size:12px; color:#6b7280; margin-top:4px; }



        [x-cloak] { display: none !important; }

        /* ── Toolbar Buttons (Sort / Filter) ─── */
        .toolbar-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            transition: all 0.15s;
            white-space: nowrap;
        }
        .toolbar-btn:hover { border-color: #9ca3af; background: #f9fafb; }
        .toolbar-btn.active { border-color: #0d9488; color: #0d9488; background: #f0fdfa; }
        .toolbar-btn svg { flex-shrink: 0; }

        /* Sort Dropdown */
        .sort-dropdown {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            width: 180px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            z-index: 200;
            padding: 6px;
        }
        .sort-option {
            padding: 9px 12px;
            font-size: 13px;
            color: #4b5563;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .sort-option:hover { background: #f9fafb; color: #111827; }
        .sort-option.selected { background: #f0fdfa; color: #0d9488; font-weight: 600; }
        .sort-option svg.check { color: #0d9488; }

        /* Filter Panel */
        .filter-panel {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            width: 320px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            z-index: 200;
            overflow: hidden;
        }
        .filter-panel-header {
            padding: 14px 18px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }
        .filter-section {
            padding: 14px 18px;
            border-bottom: 1px solid #f3f4f6;
        }
        .filter-section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .filter-section-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }
        .filter-reset-link {
            font-size: 12px;
            font-weight: 600;
            color: #0d9488;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }
        .filter-reset-link:hover { text-decoration: underline; }
        .filter-input {
            width: 100%;
            height: 34px;
            border: 1px solid #e5e7eb;
            border-radius: 7px;
            font-size: 13px;
            padding: 0 10px;
            color: #1f2937;
            box-sizing: border-box;
            transition: border-color 0.15s;
        }
        .filter-input:focus { outline: none; border-color: #0d9488; }
        .filter-date-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .filter-date-label { font-size: 11px; color: #6b7280; margin-bottom: 4px; }
        .filter-select {
            width: 100%;
            height: 34px;
            border: 1px solid #e5e7eb;
            border-radius: 7px;
            font-size: 13px;
            padding: 0 10px;
            color: #1f2937;
            background: #fff;
            box-sizing: border-box;
            cursor: pointer;
        }
        .filter-select:focus { outline: none; border-color: #0d9488; }
        .filter-search-wrap { position: relative; }
        .filter-search-input {
            width: 100%;
            height: 34px;
            border: 1px solid #e5e7eb;
            border-radius: 7px;
            font-size: 13px;
            padding: 0 12px;
            color: #1f2937;
            box-sizing: border-box;
        }
        .filter-search-input:focus { outline: none; border-color: #0d9488; }
        .filter-actions {
            display: flex;
            gap: 8px;
            padding: 14px 18px;
            border-top: 1px solid #f3f4f6;
        }
        .filter-btn-reset {
            flex: 1;
            height: 36px;
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
        }
        .filter-btn-reset:hover { background: #f9fafb; }
        .filter-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            background: #0d9488;
            color: #fff;
            border-radius: 50%;
            font-size: 10px;
            font-weight: 700;
        }
        #product-modal-overlay,
        #view-product-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        #product-modal-overlay.active,
        #view-product-modal-overlay.active {
            display: flex;
        }
        #product-modal { max-width: 580px; }
        #view-product-modal { max-width: 800px; }
        #product-modal,
        #view-product-modal {
            background: white;
            border-radius: 12px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }
        #product-modal .modal-header,
        #view-product-modal .modal-header {
            padding: 18px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        #product-modal .modal-body,
        #view-product-modal .modal-body {
            padding: 18px 20px 20px;
        }
        #product-modal .form-row,
        #view-product-modal .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 12px;
        }
        #product-modal .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 14px;
            margin-bottom: 12px;
        }
        #product-modal .form-group,
        #view-product-modal .form-group {
            margin-bottom: 12px;
        }
        #product-modal .form-group:last-child,
        #view-product-modal .form-group:last-child { margin-bottom: 0; }
        #product-modal .form-row .form-group,
        #product-modal .form-row-3 .form-group,
        #view-product-modal .form-row .form-group { margin-bottom: 0; }
        #product-modal .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 4px;
            color: #374151;
        }
        #product-modal .form-group input,
        #product-modal .form-group select,
        #product-modal .form-group textarea {
            width: 100%;
            padding: 8px 11px;
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
            border-color: #0d9488;
            box-shadow: 0 0 0 2px rgba(13,148,136,0.15);
        }
        #product-modal .form-group small {
            display: block;
            color: #6b7280;
            font-size: 11px;
            margin-top: 3px;
            line-height: 1.3;
        }
        #product-modal .form-group.has-error input,
        #product-modal .form-group.has-error select,
        #product-modal .form-group.has-error textarea { border-color: #ef4444 !important; }
        #product-modal .form-group.has-success input,
        #product-modal .form-group.has-success select,
        #product-modal .form-group.has-success textarea { border-color: #22c55e !important; }
        #product-modal .field-error { display: block; font-size: 12px; color: #ef4444; margin-top: 4px; min-height: 18px; }
        #product-modal .form-group.has-error .field-error { display: block; }
        /* Validation states */
        #product-modal .form-group.has-error input,
        #product-modal .form-group.has-error select,
        #product-modal .form-group.has-error textarea { border-color: #ef4444; }
        #product-modal .form-group.has-success input,
        #product-modal .form-group.has-success select,
        #product-modal .form-group.has-success textarea { border-color: #22c55e; }
        #product-modal .field-error { display: block; color: #ef4444; font-size: 12px; margin-top: 4px; }
        #product-modal .field-success { display: block; color: #22c55e; font-size: 12px; margin-top: 4px; }
        /* Form validation states */
        #product-modal .form-group.has-error input,
        #product-modal .form-group.has-error select,
        #product-modal .form-group.has-error textarea { border-color: #ef4444 !important; }
        #product-modal .form-group.has-success input,
        #product-modal .form-group.has-success select,
        #product-modal .form-group.has-success textarea { border-color: #22c55e !important; }
        #product-modal .field-error { display: block; font-size: 12px; color: #dc2626; margin-top: 4px; }
        #product-modal .field-success { display: inline-block; font-size: 12px; color: #16a34a; margin-left: 6px; }
        #product-modal .btn-save:disabled { opacity: 0.6; cursor: not-allowed; }
        /* Validation states */
        #product-modal .form-group.has-error input,
        #product-modal .form-group.has-error select,
        #product-modal .form-group.has-error textarea { border-color: #ef4444 !important; }
        #product-modal .form-group.has-success input,
        #product-modal .form-group.has-success select,
        #product-modal .form-group.has-success textarea { border-color: #10b981 !important; }
        #product-modal .field-error { display: block; color: #ef4444; font-size: 12px; margin-top: 4px; }
        #product-modal .field-success { display: block; color: #10b981; font-size: 11px; margin-top: 4px; }
        #product-modal .field-error { display:block; font-size:12px; color:#dc2626; margin-top:4px; }
        #product-modal .field-success { display:block; font-size:12px; color:#16a34a; margin-top:4px; }
        #product-modal .form-group.has-error input,
        #product-modal .form-group.has-error select,
        #product-modal .form-group.has-error textarea { border-color:#dc2626; }
        #product-modal .form-group.has-success input,
        #product-modal .form-group.has-success select { border-color:#16a34a; }
        #product-modal .form-group.has-error input,
        #product-modal .form-group.has-error select,
        #product-modal .form-group.has-error textarea {
            border-color: #ef4444;
            box-shadow: 0 0 0 2px rgba(239,68,68,0.15);
        }
        #product-modal .form-group.has-success input,
        #product-modal .form-group.has-success select,
        #product-modal .form-group.has-success textarea {
            border-color: #10b981;
        }
        #product-modal .field-error {
            display: block;
            color: #dc2626;
            font-size: 12px;
            margin-top: 4px;
            line-height: 1.3;
        }
        #product-modal .field-success {
            color: #059669;
            font-size: 11px;
            margin-top: 2px;
        }
        /* Validation states */
        #product-modal .form-group.has-error input,
        #product-modal .form-group.has-error select,
        #product-modal .form-group.has-error textarea { border-color: #ef4444; }
        #product-modal .form-group.has-success input,
        #product-modal .form-group.has-success select,
        #product-modal .form-group.has-success textarea { border-color: #22c55e; }
        #product-modal .field-error { display: block; font-size: 12px; color: #dc2626; margin-top: 4px; }
        #product-modal .field-success { display: block; font-size: 11px; color: #16a34a; margin-top: 4px; }
        /* Validation states */
        #product-modal .form-group.has-error input,
        #product-modal .form-group.has-error select,
        #product-modal .form-group.has-error textarea {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 2px rgba(239,68,68,0.15);
        }
        #product-modal .form-group.has-success input,
        #product-modal .form-group.has-success select,
        #product-modal .form-group.has-success textarea {
            border-color: #22c55e !important;
        }
        #product-modal .field-error {
            display: block;
            color: #dc2626;
            font-size: 12px;
            margin-top: 4px;
        }
        #product-modal .field-success {
            color: #16a34a;
            font-size: 12px;
            margin-top: 4px;
        }
        /* Validation states */
        #product-modal .form-group.has-error input,
        #product-modal .form-group.has-error select,
        #product-modal .form-group.has-error textarea { border-color: #ef4444 !important; }
        #product-modal .form-group.has-success input,
        #product-modal .form-group.has-success select,
        #product-modal .form-group.has-success textarea { border-color: #22c55e !important; }
        .field-error { display: block; font-size: 12px; color: #ef4444; margin-top: 4px; }
        .field-success { display: block; font-size: 11px; color: #22c55e; margin-top: 4px; }
        /* Validation states */
        #product-modal .form-group.has-error input,
        #product-modal .form-group.has-error select,
        #product-modal .form-group.has-error textarea { border-color: #ef4444 !important; box-shadow: 0 0 0 2px rgba(239,68,68,0.15); }
        #product-modal .form-group.has-success input,
        #product-modal .form-group.has-success select,
        #product-modal .form-group.has-success textarea { border-color: #10b981 !important; }
        #product-modal .field-error { display: block; color: #dc2626; font-size: 12px; margin-top: 4px; }
        #product-modal .field-success { display: block; color: #059669; font-size: 11px; margin-top: 4px; }
        #product-modal .btn-save:disabled { opacity: 0.6; cursor: not-allowed; }
        #product-modal .modal-footer,
        #view-product-modal .modal-footer {
            display: flex;
            gap: 10px;
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid #e5e7eb;
        }
        #product-modal .modal-footer button,
        #view-product-modal .modal-footer button {
            flex: 1;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        #product-modal .btn-cancel,
        #view-product-modal .btn-cancel {
            background: #f3f4f6;
            color: #374151;
        }
        #product-modal .btn-cancel:hover,
        #view-product-modal .btn-cancel:hover { background: #e5e7eb; color: #111827; }
        #product-modal .btn-save {
            background: #0d9488;
            color: white;
        }
        #product-modal .btn-save:hover { background: #0f766e; }
        #product-modal .btn-save:hover { background: #374151; }
        #close-modal-btn,
        #close-view-modal-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            padding: 4px;
            line-height: 1;
        }
        #close-modal-btn:hover,
        #close-view-modal-btn:hover { color: #374151; }

        /* View Modal Specifics */
        .view-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }
        .view-value-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 14px;
            color: #374151;
            font-size: 14px;
            min-height: 38px;
            display: block; /* Changed from flex to block for text wrapping */
            word-break: break-all;
            overflow-wrap: break-word;
            box-sizing: border-box;
        }
        @media (max-width: 600px) {
            #product-modal .form-row { grid-template-columns: 1fr; }
            #product-modal .form-row-3 { grid-template-columns: 1fr; }
        }

        /* Orders-style table */
        .orders-table { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: auto; }
        .orders-table th { padding: 12px 16px; font-size: 13px; font-weight: 600; color: #6b7280; text-align: left; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
        .orders-table td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; color: #374151; }
        .orders-table tbody tr { cursor: pointer; transition: background 0.1s; }
        .orders-table tbody tr:hover { background: #f9fafb; }
        .orders-table tbody tr:last-child td { border-bottom: none; }

        /* Low stock row highlight */
        .low-stock-row td { background-color: #fff5f5 !important; color: #374151 !important; }
        .low-stock-row:hover td { background-color: #fee2e2 !important; }

        /* Add Product blue text */
        .toolbar-btn.btn-add-product { color: #3b82f6; border-color: #3b82f6; }
        .toolbar-btn.btn-add-product:hover { background: #3b82f6; color: #fff; border-color: #3b82f6; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include defined('MANAGER_PANEL') ? __DIR__ . '/../includes/manager_sidebar.php' : __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Products Management</h1>
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
                    <div class="kpi-sub">At or below threshold</div>
                </div>
            </div>

            <div class="card">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:20px;" x-data="filterPanel()">
                    <h3 style="font-size:16px;font-weight:700;color:#1f2937;margin:0;" id="productsListHeader">Products List</h3>

                    <div style="display:flex; align-items:center; gap:8px;">
                        <button class="toolbar-btn" type="button" onclick="openModal('create')" style="height:38px; border-color:#3b82f6; color:#3b82f6;">Add Item</button>
                        <button class="toolbar-btn" type="button" onclick="window.openArchiveModal()" style="height:38px; border-color:#6b7280; color:#6b7280; display:flex; align-items:center; gap:6px;">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                            </svg>
                            Archived Items
                        </button>

                        <!-- Sort Button -->
                        <div style="position:relative;">
                            <button type="button" class="toolbar-btn" :class="{active: sortOpen}" @click="sortOpen = !sortOpen; filterOpen = false" style="height:38px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="9" y1="18" x2="15" y2="18"/>
                                </svg>
                                Sort by
                            </button>
                            <div class="sort-dropdown" x-show="sortOpen" x-cloak @click.outside="sortOpen = false">
                                <?php $sorts = ['newest'=>'Newest to Oldest','oldest'=>'Oldest to Newest','az'=>'A → Z','za'=>'Z → A']; foreach ($sorts as $key => $label): ?>
                                <div class="sort-option" :class="{ 'selected': activeSort === '<?php echo $key; ?>' }" onclick="applySortFilter('<?php echo $key; ?>')">
                                    <?php echo htmlspecialchars($label); ?>
                                    <svg x-show="activeSort === '<?php echo $key; ?>'" class="check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Filter Button -->
                        <div style="position:relative;">
                            <button type="button" class="toolbar-btn" :class="{active: filterOpen || hasActiveFilters}" @click="filterOpen = !filterOpen; sortOpen = false" style="height:38px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                                </svg>
                                Filter
                                <span id="filterBadgeContainer">
                                    <?php $afc = count(array_filter([$search,$cat_filter,$status_filter,$stock_filter,$date_from,$date_to], function($v) { return $v !== null && $v !== ''; })); if ($afc > 0): ?>
                                    <span class="filter-badge"><?php echo $afc; ?></span>
                                    <?php endif; ?>
                                </span>
                            </button>

                            <!-- Filter Panel -->
                            <div class="filter-panel" x-show="filterOpen" x-cloak @click.outside="filterOpen = false">
                                <div class="filter-panel-header">Filter</div>

                                <!-- Date Range -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Date range</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['date_from','date_to'])">Reset</button>
                                    </div>
                                    <div class="filter-date-row">
                                        <div>
                                            <div class="filter-date-label">From:</div>
                                            <input type="date" id="fp_date_from" class="filter-input" value="<?php echo htmlspecialchars($date_from); ?>">
                                        </div>
                                        <div>
                                            <div class="filter-date-label">To:</div>
                                            <input type="date" id="fp_date_to" class="filter-input" value="<?php echo htmlspecialchars($date_to); ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Category -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Category</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['category'])">Reset</button>
                                    </div>
                                    <select id="fp_category" class="filter-select">
                                        <option value="">All categories</option>
                                        <?php foreach($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $cat_filter===$cat['category']?'selected':''; ?>><?php echo htmlspecialchars($cat['category']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Status -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Status</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['status'])">Reset</button>
                                    </div>
                                    <select id="fp_status" class="filter-select">
                                        <option value="">All statuses</option>
                                        <option value="Activated" <?php echo $status_filter==='Activated'?'selected':''; ?>>Activated</option>
                                        <option value="Deactivated" <?php echo $status_filter==='Deactivated'?'selected':''; ?>>Deactivated</option>
                                    </select>
                                </div>

                                <!-- Stock Status -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Stock Status</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['stock_status'])">Reset</button>
                                    </div>
                                    <select id="fp_stock_status" class="filter-select">
                                        <option value="">All stock</option>
                                        <option value="in_stock" <?php echo $stock_filter==='in_stock'?'selected':''; ?>>In Stock</option>
                                        <option value="low_stock" <?php echo $stock_filter==='low_stock'?'selected':''; ?>>Low Stock</option>
                                        <option value="out_of_stock" <?php echo $stock_filter==='out_of_stock'?'selected':''; ?>>Out of Stock</option>
                                    </select>
                                </div>

                                <!-- Keyword Search -->
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Keyword search</span>
                                        <button class="filter-reset-link" onclick="resetFilterField(['search'])">Reset</button>
                                    </div>
                                    <div class="filter-search-wrap">
                                        <input type="text" id="fp_search" class="filter-search-input" placeholder="Search by name or SKU..." value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="filter-actions">
                                    <button class="filter-btn-reset" style="width:100%;" onclick="applyFilters(true)">Reset all filters</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="productsTableContainer">
                <div class="overflow-x-auto">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>SKU</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Stock Status</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="productsTableBody">
                            <?php if (empty($products)): ?>
                                <tr id="emptyProductsRow">
                                    <td colspan="9" style="padding:40px;text-align:center;color:#9ca3af;font-size:14px;">
                                        <?php echo $search ? 'No products found matching "' . htmlspecialchars($search) . '"' : 'No products yet.'; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <?php
                                    $low = (int)($product['low_stock_level'] ?? 10);
                                    $stockStatus = get_stock_status($product['stock_quantity'], $low);
                                    $isLowOrOut = in_array($stockStatus, ['Low Stock','Out of Stock']);
                                    $stockBadge = match($stockStatus) {
                                        'In Stock' => 'background:#dcfce7;color:#166534;',
                                        'Low Stock' => 'background:#fef9c3;color:#854d0e;',
                                        'Out of Stock' => 'background:#fee2e2;color:#991b1b;',
                                        default => 'background:#f3f4f6;color:#374151;'
                                    };
                                    ?>
                                    <tr class="<?php echo $isLowOrOut ? 'low-stock-row' : ''; ?>" onclick="openViewModal(<?php echo htmlspecialchars(json_encode($product), ENT_QUOTES); ?>)">
                                        <td style="color:#1f2937;"><?php echo $product['product_id']; ?></td>
                                        <td style="font-family:monospace;font-size:12px;"><?php echo htmlspecialchars($product['sku'] ?? '—'); ?></td>
                                        <td style="font-weight:500;color:#1f2937;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category'] ?? '—'); ?></td>
                                        <td style="font-weight:600;color:#1f2937;white-space:nowrap;">₱<?php echo number_format($product['price'], 2); ?></td>
                                        <td>
                                            <span style="font-weight:<?php echo $isLowOrOut ? 'bold' : '400'; ?>;color:<?php echo $stockStatus === 'Out of Stock' ? '#dc2626' : ($stockStatus === 'Low Stock' ? '#b45309' : '#374151'); ?>;"><?php echo $product['stock_quantity']; ?></span>
                                        </td>
                                        <td>
                                            <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;<?php echo $stockBadge; ?>"><?php echo $stockStatus; ?></span>
                                        </td>
                                        <td>
                                            <?php
                                                $sc = match($product['status']) {
                                                    'Activated'   => 'background:#dcfce7;color:#166534;',
                                                    'Deactivated' => 'background:#fee2e2;color:#991b1b;',
                                                    'Archived'    => 'background:#f3f4f6;color:#374151;',
                                                    default       => 'background:#fef9c3;color:#854d0e;'
                                                };
                                            ?>
                                            <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;<?php echo $sc; ?>">
                                                <?php echo $product['status']; ?>
                                            </span>
                                        </td>
                                        <td style="text-align:right;white-space:nowrap;" onclick="event.stopPropagation();">
                                            <button class="btn-action blue"
                                                onclick='openModal("edit", <?php echo htmlspecialchars(json_encode($product), ENT_QUOTES); ?>)'>Edit</button>
                                            
                                            <?php if ($product['status'] !== 'Archived'): ?>
                                                <form method="POST" class="inline product-status-form" data-pf-skip-guard data-action="<?php echo $product['status'] === 'Activated' ? 'Deactivate' : 'Activate'; ?>" data-product-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>" onsubmit="showProductStatusModal(event, this);return false;">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                    <button type="submit" name="delete_product" class="btn-action <?php echo $product['status'] === 'Activated' ? 'red' : 'teal'; ?>">
                                                        <?php echo $product['status'] === 'Activated' ? 'Deactivate' : 'Activate'; ?>
                                                    </button>
                                                </form>
                                                <?php if ($product['status'] === 'Deactivated'): ?>
                                                    <form method="POST" class="inline product-status-form" data-pf-skip-guard data-action="Archive" data-product-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>" onsubmit="showProductStatusModal(event, this);return false;">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                        <button type="submit" name="archive_product" class="btn-action gray">Archive</button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <form method="POST" class="inline product-status-form" data-pf-skip-guard data-action="Restore" data-product-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>" onsubmit="showProductStatusModal(event, this);return false;">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                    <button type="submit" name="restore_product" class="btn-action teal">Restore</button>
                                                </form>
                                                <form method="POST" class="inline product-status-form" data-pf-skip-guard data-action="Delete Permanently" data-product-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>" onsubmit="showProductStatusModal(event, this);return false;">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                    <button type="submit" name="delete_product" class="btn-action red">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div id="productsPagination">
                    <?php
                    $pagination_params = array_filter(['search'=>$search,'category'=>$cat_filter,'status'=>$status_filter,'stock_status'=>$stock_filter,'sort'=>$sort_by,'date_from'=>$date_from,'date_to'=>$date_to], function($v) { return $v !== null && $v !== ''; });
                    echo render_pagination($page, $total_pages, $pagination_params);
                    ?>
                </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Product Status Confirmation Modal -->
<div id="productStatusConfirmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10100;align-items:center;justify-content:center;padding:16px;flex-direction:column;pointer-events:auto;">
    <div style="background:white;border-radius:16px;padding:26px;max-width:420px;width:100%;box-shadow:0 25px 50px rgba(0,0,0,0.25);text-align:center;">
        <div id="productStatusConfirmIcon" style="width:48px;height:48px;background:#f3f4f6;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#6b7280;">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <h3 id="productStatusConfirmTitle" style="font-size:18px;font-weight:700;color:#1f2937;margin:0 0 8px;">Confirm Action</h3>
        <p id="productStatusConfirmText" style="font-size:14px;color:#4b5563;margin:0 0 16px;line-height:1.5;word-break:break-word;overflow-wrap:anywhere;">Are you sure you want to proceed?</p>
        
        <div id="productStatusInfoBox" style="font-size:12px;color:#6b7280;background:#f9fafb;padding:12px;border-radius:10px;margin-bottom:24px;text-align:left;border:1px solid #e5e7eb;line-height:1.5;">
            <div style="font-weight:700;margin-bottom:4px;color:#374151;display:flex;align-items:center;gap:5px;">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                What happens next?
            </div>
            <div id="productStatusInfoText">Action details will appear here.</div>
        </div>

        <div style="display:flex;gap:12px;justify-content:center;">
            <button type="button" id="productStatusConfirmCancel" style="flex:1;padding:12px 16px;border:1px solid #e5e7eb;background:white;border-radius:10px;font-size:14px;font-weight:600;color:#4b5563;cursor:pointer;transition:all 0.2s;">Cancel</button>
            <button type="button" id="productStatusConfirmOk" style="flex:1;padding:12px 16px;border:none;background:#3b82f6;border-radius:10px;font-size:14px;font-weight:600;color:white;cursor:pointer;transition:all 0.2s;">Confirm</button>
        </div>
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
            <form method="POST" id="product-form" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" id="modal-mode-input" name="create_product" value="1">
                <input type="hidden" id="modal-product-id" name="product_id" value="">

                <div class="form-row">
                    <div class="form-group" id="fg-name">
                        <label for="modal-name">Product Name <span style="color:red">*</span></label>
                        <input type="text" id="modal-name" name="name" placeholder="e.g. Custom Tarpaulin">
                        <span id="err-name" class="field-error"></span>
                    </div>
                    <div class="form-group">
                        <label for="modal-sku">SKU</label>
                        <input type="text" id="modal-sku" name="sku" placeholder="e.g. TARP001 (optional)" readonly style="background-color:#f3f4f6; cursor:not-allowed;">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" id="fg-category">
                        <label for="modal-category">Category <span style="color:red">*</span></label>
                        <select id="modal-category" name="category">
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
                        <span id="err-category" class="field-error"></span>
                    </div>
                    <div class="form-group" id="fg-price">
                        <label for="modal-price">Price (₱) <span style="color:red">*</span></label>
                        <input type="number" id="modal-price" name="price" step="0.01" min="0.01" placeholder="0.00">
                        <span id="err-price" class="field-error"></span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" id="fg-description" style="grid-column:1/-1;">
                        <label for="modal-description">Description</label>
                        <textarea id="modal-description" name="description" rows="2" maxlength="500" placeholder="Optional description (max 500 chars)..."></textarea>
                        <span id="err-description" class="field-error"></span>
                    </div>
                </div>

                <div class="form-group" id="fg-photo">
                    <label for="modal-photo">Product Photo <span style="color:red">*</span></label>
                    <div style="display:flex; align-items:flex-start; gap:12px;">
                        <div id="photo-preview" style="width:110px; height:82px; flex-shrink:0; border:2px dashed #d1d5db; border-radius:8px; background:#f9fafb; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                            <img id="photo-preview-img" src="" alt="" style="width:100%; height:100%; object-fit:cover; display:none;">
                            <span id="photo-preview-text" style="color:#9ca3af; font-size:11px; text-align:center; padding:4px;">No photo</span>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <input type="file" id="modal-photo" name="photo" accept="image/jpeg,image/jpg,image/png,image/gif" style="padding:6px 8px; border:1px solid #e5e7eb; border-radius:6px; cursor:pointer; font-size:12px;">
                            <small>JPG, PNG, GIF only. Max 5MB.</small>
                        </div>
                    </div>
                    <span id="err-photo" class="field-error"></span>
                </div>

                <div class="form-row-3">
                    <div class="form-group" id="fg-stock">
                        <label for="modal-stock">Quantity <span style="color:#dc2626">*</span></label>
                        <input type="number" id="modal-stock" name="stock_quantity" min="0" value="0" step="1">
                        <span id="err-stock" class="field-error"></span>
                    </div>
                    <div class="form-group" id="fg-low-stock">
                        <label for="modal-low-stock">Low Stock Level <span style="color:#dc2626">*</span></label>
                        <input type="number" id="modal-low-stock" name="low_stock_level" min="0" value="10" step="1">
                        <small>Warn when stock falls below this. Must be ≤ Quantity.</small>
                        <span id="err-low-stock" class="field-error"></span>
                    </div>
                    <div class="form-group">
                        <label for="modal-product-type">Product Type <span style="color:red">*</span></label>
                        <select id="modal-product-type" name="product_type" required>
                            <option value="custom">Service (Customizable)</option>
                            <option value="fixed">Fixed Product</option>
                        </select>
                        <small>Fixed product vs service/customizable</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="modal-status">Status</label>
                    <select id="modal-status" name="status">
                        <option value="Activated">Activated</option>
                        <option value="Deactivated">Deactivated</option>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeProductModal()">Cancel</button>
                    <button type="submit" id="modal-submit-btn" class="btn-save">Create Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Product Modal -->
<div id="view-product-modal-overlay" onclick="handleViewOverlayClick(event)">
    <div id="view-product-modal">
        <div class="modal-header">
            <h3 style="font-size:18px; font-weight:700; margin:0; display:flex; align-items:center; gap:8px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Product Details
            </h3>
            <button id="close-view-modal-btn" onclick="closeViewModal()">
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal-body" style="padding:24px; overflow-x:hidden;">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px; min-width:0;">
                <!-- Left Side: Details -->
                <div style="display:flex; flex-direction:column; gap:16px; min-width:0;">
                    <div>
                        <label class="view-label">Product Name</label>
                        <div id="view-product-name" class="view-value-box" style="font-size:18px; font-weight:700; color:#111827;">-</div>
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                        <div>
                            <label class="view-label">SKU</label>
                            <div id="view-product-sku" class="view-value-box" style="font-family:monospace;">-</div>
                        </div>
                        <div>
                            <label class="view-label">Category</label>
                            <div id="view-product-category" class="view-value-box">-</div>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                        <div>
                            <label class="view-label">Price</label>
                            <div id="view-product-price" class="view-value-box" style="color:#0d9488; font-weight:700; font-size:16px;">-</div>
                        </div>
                        <div>
                            <label class="view-label">Stock Status</label>
                            <div id="view-product-stock-status" style="height:38px; display:flex; align-items:center; justify-content:center; border-radius:8px; font-size:12px; font-weight:700;">-</div>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                        <div>
                            <label class="view-label">Current Quantity</label>
                            <div id="view-product-stock" class="view-value-box" style="font-weight:700;">-</div>
                        </div>
                        <div>
                            <label class="view-label">Low Stock Warning</label>
                            <div id="view-product-low-stock" class="view-value-box">-</div>
                        </div>
                    </div>

                    <div>
                        <label class="view-label">Product Visibility</label>
                        <div id="view-product-status" style="height:38px; display:flex; align-items:center; justify-content:center; border-radius:8px; font-size:12px; font-weight:700;">-</div>
                    </div>
                </div>

                <!-- Right Side: Photo & Description -->
                <div style="display:flex; flex-direction:column; gap:16px; min-width:0;">
                    <div>
                        <label class="view-label">Product Photo</label>
                        <div style="width:100%; height:200px; border-radius:12px; border:1px solid #e5e7eb; background:#f9fafb; overflow:hidden; display:flex; align-items:center; justify-content:center;">
                            <img id="view-product-photo-img" src="" alt="" style="width:100%; height:100%; object-fit:cover; display:none;">
                            <span id="view-product-photo-text" style="color:#9ca3af; font-size:12px; text-align:center; padding:20px;">No photo available</span>
                        </div>
                    </div>
                    <div>
                        <label class="view-label">Description</label>
                        <div id="view-product-description" class="view-value-box" style="min-height:80px; font-size:13px; line-height:1.5; white-space:pre-wrap;">-</div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="margin-top:24px; justify-content:flex-end;">
                <button type="button" class="btn-cancel" onclick="closeViewModal()" style="max-width:120px;">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// ── Filter & Sort JS (products_management.php) ────────────────────────────
let activeSort = '<?php echo $sort_by; ?>';
let searchDebounceTimer = null;

function filterPanel() {
    return {
        sortOpen: false,
        filterOpen: false,
        activeSort: activeSort,
        get hasActiveFilters() {
            return document.getElementById('fp_date_from')?.value ||
                   document.getElementById('fp_date_to')?.value ||
                   document.getElementById('fp_category')?.value ||
                   document.getElementById('fp_status')?.value ||
                   document.getElementById('fp_stock_status')?.value ||
                   document.getElementById('fp_search')?.value;
        }
    };
}

function buildFilterURL(page = 1) {
    const params = new URLSearchParams();
    params.set('page', page);
    const df = document.getElementById('fp_date_from')?.value; if (df) params.set('date_from', df);
    const dt = document.getElementById('fp_date_to')?.value;   if (dt) params.set('date_to', dt);
    const cat = document.getElementById('fp_category')?.value; if (cat) params.set('category', cat);
    const st = document.getElementById('fp_status')?.value;   if (st) params.set('status', st);
    const stock = document.getElementById('fp_stock_status')?.value; if (stock) params.set('stock_status', stock);
    const s = document.getElementById('fp_search')?.value;     if (s) params.set('search', s);
    if (activeSort !== 'newest') params.set('sort', activeSort);
    return '?' + params.toString();
}

function fetchUpdatedTable(page = 1) {
    const url = buildFilterURL(page) + '&ajax=1';
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const wrap = document.getElementById('productsTableContainer');
            if (wrap) {
                wrap.innerHTML = data.table + '<div id="productsPagination">' + data.pagination + '</div>';
                if (typeof Alpine !== 'undefined' && typeof Alpine.initTree === 'function') {
                    try {
                        Alpine.initTree(wrap);
                    } catch (e) {
                        console.error(e);
                    }
                }
            }
            // productsCount element replaced with heading - no update needed
            // Badge
            const cont = document.getElementById('filterBadgeContainer');
            cont.innerHTML = data.badge > 0 ? '<span class="filter-badge">' + data.badge + '</span>' : '';
            history.replaceState(null, '', buildFilterURL(page));
        })
        .catch(console.error);
}

function applyFilters(reset = false) {
    if (reset) {
        ['fp_date_from','fp_date_to','fp_category','fp_status','fp_stock_status','fp_search'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        activeSort = 'newest';
    }
    fetchUpdatedTable(1);
}

function resetFilterField(fields) {
    fields.forEach(f => {
        const map = { date_from:'fp_date_from', date_to:'fp_date_to', category:'fp_category', status:'fp_status', stock_status:'fp_stock_status', search:'fp_search' };
        const el = document.getElementById(map[f] || 'fp_' + f);
        if (el) el.value = '';
    });
    fetchUpdatedTable(1);
}

function applySortFilter(sortKey) {
    activeSort = sortKey;
    fetchUpdatedTable(1);
    // Update alpine data
    const alpineEl = document.querySelector('[x-data="filterPanel()"]');
    if (alpineEl && alpineEl._x_dataStack) {
        alpineEl._x_dataStack[0].activeSort = sortKey;
        alpineEl._x_dataStack[0].sortOpen   = false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    ['fp_date_from','fp_date_to','fp_category','fp_status','fp_stock_status'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', () => fetchUpdatedTable());
    });
    const searchInput = document.getElementById('fp_search');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => fetchUpdatedTable(), 500);
        });
    }
});

function openModal(mode, product) {
    var overlay = document.getElementById('product-modal-overlay');
    var title   = document.getElementById('modal-title');
    var modeInput = document.getElementById('modal-mode-input');
    var submitBtn = document.getElementById('modal-submit-btn');
    var categorySelect = document.getElementById('modal-category');

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
        document.getElementById('modal-low-stock').value   = product.low_stock_level ?? 10;
        document.getElementById('modal-product-type').value = product.product_type || 'custom';
        document.getElementById('modal-status').value      = product.status || 'Activated';
        
        // Load existing photo preview if available
        var photoImg = document.getElementById('photo-preview-img');
        var photoText = document.getElementById('photo-preview-text');
        if (product.photo_path && product.photo_path.trim() !== '') {
            photoImg.src = product.photo_path;
            photoImg.style.display = 'block';
            photoText.style.display = 'none';
        } else {
            photoImg.style.display = 'none';
            photoText.style.display = 'block';
        }
        
        // Remove auto-generate listener for edit mode
        categorySelect.removeEventListener('change', autoGenerateSKU);
    } else {
        title.textContent = 'Add New Product';
        modeInput.name = 'create_product';
        submitBtn.textContent = 'Create Product';
        document.getElementById('modal-product-id').value = '';
        document.getElementById('modal-stock').value = '0';
        document.getElementById('modal-low-stock').value = '10';
        document.getElementById('modal-product-type').value = 'custom';
        document.getElementById('modal-status').value = 'Activated';
        
        // Clear photo preview for new product
        var photoImg = document.getElementById('photo-preview-img');
        var photoText = document.getElementById('photo-preview-text');
        photoImg.style.display = 'none';
        photoText.style.display = 'block';
        document.getElementById('modal-photo').value = '';
        
        // Add auto-generate listener for create mode
        categorySelect.addEventListener('change', autoGenerateSKU);
    }

    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    document.getElementById('modal-name').focus();
}

function closeProductModal() {
    document.getElementById('product-modal-overlay').classList.remove('active');
    document.body.style.overflow = '';
    // Remove listener when closing modal
    document.getElementById('modal-category').removeEventListener('change', autoGenerateSKU);
}

/**
 * Auto-generate SKU based on selected category
 */
function autoGenerateSKU(event) {
    var category = event.target.value;
    
    if (!category) {
        document.getElementById('modal-sku').value = '';
        return;
    }

    // Show loading state
    var skuInput = document.getElementById('modal-sku');
    skuInput.placeholder = 'Generating...';
    skuInput.style.opacity = '0.6';

    // Call API to generate SKU
    fetch('/printflow/admin/api_generate_product_sku.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'category=' + encodeURIComponent(category)
    })
    .then(response => response.json())
    .then(data => {
        skuInput.style.opacity = '1';
        skuInput.placeholder = 'e.g. TARP001 (auto-generated)';
        
        if (data.success) {
            skuInput.value = data.sku;
        } else {
            console.error('SKU generation failed:', data.error);
            skuInput.value = '';
            skuInput.placeholder = 'Failed to generate SKU';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        skuInput.style.opacity = '1';
        skuInput.placeholder = 'Error generating SKU';
        skuInput.value = '';
    });
}

/**
 * Handle product photo file selection and preview
 */
document.addEventListener('DOMContentLoaded', function() {
    var photoInput = document.getElementById('modal-photo');
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (file) {
                // Check file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    this.value = '';
                    return;
                }
                
                // Check file type
                var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPG, PNG, or GIF)');
                    this.value = '';
                    return;
                }
                
                // Show preview
                var reader = new FileReader();
                reader.onload = function(event) {
                    var previewImg = document.getElementById('photo-preview-img');
                    var previewText = document.getElementById('photo-preview-text');
                    previewImg.src = event.target.result;
                    previewImg.style.display = 'block';
                    previewText.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });
    }
});



var _productStatusForm = null;
var _productStatusButtonName = null;

function showProductStatusModal(event, form) {
    if (event) event.preventDefault();
    var action = form.getAttribute('data-action') || 'proceed';
    var productName = form.getAttribute('data-product-name') || 'this product';
    _productStatusForm = form;
    
    var btn = form.querySelector('button[type="submit"]');
    _productStatusButtonName = btn ? btn.getAttribute('name') : null;

    var modal = document.getElementById('productStatusConfirmModal');
    var title = document.getElementById('productStatusConfirmTitle');
    var text  = document.getElementById('productStatusConfirmText');
    var info  = document.getElementById('productStatusInfoText');
    var box   = document.getElementById('productStatusInfoBox');
    var okBtn = document.getElementById('productStatusConfirmOk');
    var icon  = document.getElementById('productStatusConfirmIcon');

    // Close other modals to prevent overlap (as requested)
    if (typeof window.closeArchiveModal === 'function') window.closeArchiveModal();
    closeProductModal(); 
    closeViewModal();

    title.textContent = 'Confirm ' + action;
    text.innerHTML = 'Are you sure you want to ' + action.toLowerCase() + ' <br><strong style="color:#111827;">' + productName + '</strong>?';
    
    // Consequence logic
    var msg = "";
    var themeColor = "#3b82f6"; // Default blue
    
    if (action === 'Deactivate') {
        msg = "Deactivating this product will hide it from the POS and Online Ordering, but its records will remain in the inventory.";
        themeColor = "#ef4444"; // Red for deactivation
    } else if (action === 'Activate') {
        msg = "This will make the product visible again in the POS and Online Ordering for customers and staff.";
        themeColor = "#14b8a6"; // Teal for activation
    } else if (action === 'Archive') {
        msg = "Archiving will remove this item from the main products list. You can still access and restore it from the <em>Archive Storage</em>.";
        themeColor = "#6b7280"; // Gray for archive
    } else if (action === 'Restore') {
        msg = "This will bring the product back to the active list and make it available for use in the system.";
        themeColor = "#14b8a6"; 
    } else if (action === 'Delete Permanently') {
        msg = "<strong>Warning:</strong> This action is permanent and cannot be undone. All associated product data will be removed.";
        themeColor = "#ef4444";
        box.style.background = "#fff5f5";
        box.style.borderColor = "#fecaca";
        box.style.color = "#991b1b";
    }

    if (action !== 'Delete Permanently') {
        box.style.background = "#f9fafb";
        box.style.borderColor = "#e5e7eb";
        box.style.color = "#6b7280";
    }

    info.innerHTML = msg;
    okBtn.style.background = themeColor;
    icon.style.color = themeColor;
    icon.style.background = themeColor + '15'; // 10% opacity

    modal.style.display = 'flex';
}

function closeProductStatusModal() {
    var modal = document.getElementById('productStatusConfirmModal');
    modal.style.display = 'none';
    _productStatusForm = null;
    _productStatusButtonName = null;
}

document.addEventListener('DOMContentLoaded', function() {
    var cancelBtn = document.getElementById('productStatusConfirmCancel');
    var okBtn = document.getElementById('productStatusConfirmOk');
    var modal = document.getElementById('productStatusConfirmModal');
    if (cancelBtn) cancelBtn.addEventListener('click', closeProductStatusModal);
    if (okBtn) okBtn.addEventListener('click', function() {
        if (_productStatusForm) {
            if (_productStatusButtonName) {
                var hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = _productStatusButtonName;
                hiddenInput.value = '1';
                _productStatusForm.appendChild(hiddenInput);
            }
            _productStatusForm.submit();
        }
        closeProductStatusModal();
    });
    if (modal) modal.addEventListener('click', function(e) {
        if (e.target === modal) closeProductStatusModal();
    });
});

function handleOverlayClick(event) {
    if (event.target === document.getElementById('product-modal-overlay')) {
        closeProductModal();
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var archiveOv = document.getElementById('archive-storage-overlay');
        if (archiveOv && archiveOv.style.display === 'flex') {
            if (typeof window.closeArchiveModal === 'function') window.closeArchiveModal();
            return;
        }
        var statusModal = document.getElementById('productStatusConfirmModal');
        if (statusModal && statusModal.style.display === 'flex') {
            closeProductStatusModal();
        } else {
            closeProductModal();
            closeViewModal();
        }
    }
});

// View Modal Functions
function openViewModal(product) {
    var overlay = document.getElementById('view-product-modal-overlay');
    var name = document.getElementById('view-product-name');
    var sku = document.getElementById('view-product-sku');
    var category = document.getElementById('view-product-category');
    var price = document.getElementById('view-product-price');
    var stock = document.getElementById('view-product-stock');
    var status = document.getElementById('view-product-status');
    var description = document.getElementById('view-product-description');

    // Set product data
    name.textContent = product.name || '-';
    sku.textContent = product.sku || '—';
    category.textContent = product.category || '—';
    price.textContent = '₱' + (product.price || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    stock.textContent = product.stock_quantity || '0';
    document.getElementById('view-product-low-stock').textContent = product.low_stock_level ?? 10;

    // Stock Status (auto-computed)
    var stockStatusEl = document.getElementById('view-product-stock-status');
    var qty = parseInt(product.stock_quantity || 0, 10);
    var lowLevel = parseInt(product.low_stock_level || 10, 10);
    var stockStatusText = qty <= 0 ? 'Out of Stock' : (qty <= lowLevel ? 'Low Stock' : 'In Stock');
    stockStatusEl.textContent = stockStatusText;
    stockStatusEl.style.background = stockStatusText === 'In Stock' ? '#dcfce7' : (stockStatusText === 'Low Stock' ? '#fef9c3' : '#fee2e2');
    stockStatusEl.style.color = stockStatusText === 'In Stock' ? '#166534' : (stockStatusText === 'Low Stock' ? '#854d0e' : '#991b1b');

    // Set status (Activated/Deactivated) with styling
    status.textContent = product.status || 'Activated';
    if (product.status === 'Activated') {
        status.style.background = '#dcfce7';
        status.style.color = '#166534';
    } else if (product.status === 'Deactivated') {
        status.style.background = '#fee2e2';
        status.style.color = '#991b1b';
    } else {
        status.style.background = '#fef9c3';
        status.style.color = '#854d0e';
    }
    
    // Set product photo
    var photoImg = document.getElementById('view-product-photo-img');
    var photoText = document.getElementById('view-product-photo-text');
    if (product.photo_path && product.photo_path.trim() !== '') {
        photoImg.src = product.photo_path;
        photoImg.style.display = 'block';
        photoText.style.display = 'none';
    } else {
        photoImg.style.display = 'none';
        photoText.style.display = 'block';
    }
    
    description.textContent = product.description || 'No description provided.';

    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeViewModal() {
    document.getElementById('view-product-modal-overlay').classList.remove('active');
    document.body.style.overflow = '';
}

function handleViewOverlayClick(event) {
    if (event.target === document.getElementById('view-product-modal-overlay')) {
        closeViewModal();
    }
}

// Helper function to format currency (similar to PHP format_currency)
function formatCurrency(amount) {
    return '₱' + (amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<!-- Archived Items Modal (z-index above status modal 10000, logout 9999, pf-fg portal 10030+) -->
<div id="archive-storage-overlay" role="dialog" aria-modal="true" aria-labelledby="archive-storage-title" onclick="if (event.target === this) window.closeArchiveModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10090;align-items:center;justify-content:center;padding:16px;pointer-events:auto;">
    <div id="archive-storage-modal" onclick="event.stopPropagation()" style="background:white;border-radius:16px;width:100%;max-width:900px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 25px 50px rgba(0,0,0,0.25);pointer-events:auto;">
        <div class="modal-header" style="padding:20px 24px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="background:#f3f4f6;padding:8px;border-radius:10px;color:#6b7280;">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                    </svg>
                </div>
                <div>
                    <h3 id="archive-storage-title" style="font-size:18px;font-weight:700;color:#1f2937;margin:0;">Archived Items</h3>
                    <p style="font-size:12px;color:#6b7280;margin:0;">Manage archived products here.</p>
                </div>
            </div>
            <button type="button" onclick="window.closeArchiveModal()" style="background:none;border:none;cursor:pointer;color:#9ca3af;padding:4px;line-height:1;">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal-body" style="padding:0;overflow-y:auto;flex:1;">
            <div id="archived-products-container" style="min-height:200px;">
                <!-- Content loaded via AJAX -->
                <div style="padding:60px;text-align:center;color:#9ca3af;">
                    <div class="spinner" style="margin:0 auto 12px;"></div>
                    <p>Loading archived products...</p>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="padding:16px 24px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;">
            <button type="button" class="btn-cancel" onclick="window.closeArchiveModal()" style="padding:8px 20px;background:#f3f4f6;border:none;border-radius:8px;color:#374151;font-weight:600;cursor:pointer;">Close</button>
        </div>
    </div>
</div>

<script>
// Archive Modal Functions (on window for Turbo + inline onclick)
window.openArchiveModal = function openArchiveModal() {
    var el = document.getElementById('archive-storage-overlay');
    if (!el) return;
    el.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    fetchArchivedProducts();
};

window.closeArchiveModal = function closeArchiveModal() {
    var el = document.getElementById('archive-storage-overlay');
    if (el) el.style.display = 'none';
    document.body.style.overflow = '';
};

function fetchArchivedProducts() {
    const container = document.getElementById('archived-products-container');
    fetch('?get_archived=1')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                container.innerHTML = data.html;
            } else {
                container.innerHTML = '<div style="padding:40px;text-align:center;color:#ef4444;">Failed to load archived products.</div>';
            }
        })
        .catch(err => {
            console.error(err);
            container.innerHTML = '<div style="padding:40px;text-align:center;color:#ef4444;">Error loading archived products.</div>';
        });
}

/**
 * First visit: defer Alpine may leave filterPanel() without _x_dataStack briefly.
 * After AJAX table replace: reinject Alpine directives on new markup where present.
 */
function ensureProductsAlpineBoot() {
    if (typeof Alpine === 'undefined' || typeof Alpine.initTree !== 'function') return;
    var fp = document.querySelector('[x-data="filterPanel()"]');
    if (fp && !fp._x_dataStack) {
        try {
            Alpine.initTree(fp);
        } catch (e) {
            console.error(e);
        }
    }
    var tbl = document.getElementById('productsTableContainer');
    if (tbl) {
        try {
            Alpine.initTree(tbl);
        } catch (e2) {
            console.error(e2);
        }
    }
}

window.printflowInitProductsPage = ensureProductsAlpineBoot;

(function scheduleProductsAlpineFirstVisit() {
    function tick() {
        ensureProductsAlpineBoot();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', schedule);
    } else {
        schedule();
    }
    function schedule() {
        tick();
        queueMicrotask(tick);
        setTimeout(tick, 0);
        requestAnimationFrame(function () {
            requestAnimationFrame(tick);
        });
        setTimeout(tick, 150);
    }
})();
</script>

</body>
</html>
