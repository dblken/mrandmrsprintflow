<?php
/**
 * Admin Customers Management  
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$current_user = get_logged_in_user();

// Get all customers
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT * FROM customers WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

if (!empty($status_filter)) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$sql .= " ORDER BY created_at DESC";

$customers = db_query($sql, $types, $params);

// Get statistics
$total_customers = db_query("SELECT COUNT(*) as count FROM customers")[0]['count'];
$active_customers = db_query("SELECT COUNT(*) as count FROM customers WHERE status = 'Activated'")[0]['count'];

$page_title = 'Customers Management - Admin';
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
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Customers Management</h1>
            <button class="btn-secondary">Export CSV</button>
        </header>

        <main>
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="card border-l-4 border-indigo-500">
                    <p class="text-sm text-gray-600">Total Customers</p>
                    <p class="text-3xl font-bold text-indigo-600"><?php echo $total_customers; ?></p>
                </div>
                <div class="card border-l-4 border-green-500">
                    <p class="text-sm text-gray-600">Active Customers</p>
                    <p class="text-3xl font-bold text-green-600"><?php echo $active_customers; ?></p>
                </div>
                <div class="card border-l-4 border-gray-500">
                    <p class="text-sm text-gray-600">Inactive</p>
                    <p class="text-3xl font-bold text-gray-600"><?php echo $total_customers - $active_customers; ?></p>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Search</label>
                        <input type="text" name="search" class="input-field" placeholder="Name or email..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Status</label>
                        <select name="status" class="input-field">
                            <option value="">All Statuses</option>
                            <option value="Activated" <?php echo $status_filter === 'Activated' ? 'selected' : ''; ?>>Activated</option>
                            <option value="Deactivated" <?php echo $status_filter === 'Deactivated' ? 'selected' : ''; ?>>Deactivated</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="btn-primary w-full">Apply Filters</button>
                    </div>
                </form>
            </div>

            <!-- Customers Table -->
            <div class="card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2">
                                <th class="text-left py-3">ID</th>
                                <th class="text-left py-3">Name</th>
                                <th class="text-left py-3">Email</th>
                                <th class="text-left py-3">Contact</th>
                                <th class="text-left py-3">Registered</th>
                                <th class="text-left py-3">Status</th>
                                <th class="text-right py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3">#<?php echo $customer['customer_id']; ?></td>
                                    <td class="py-3 font-medium">
                                        <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                    </td>
                                    <td class="py-3"><?php echo htmlspecialchars($customer['email']); ?></td>
                                    <td class="py-3"><?php echo htmlspecialchars($customer['contact_number'] ?? 'N/A'); ?></td>
                                    <td class="py-3"><?php echo format_date($customer['created_at']); ?></td>
                                    <td class="py-3"><?php echo status_badge($customer['status'], 'order'); ?></td>
                                    <td class="py-3 text-right">
                                        <a href="customer_orders.php?id=<?php echo $customer['customer_id']; ?>" class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">
                                            View Orders
                                        </a>
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

</body>
</html>
