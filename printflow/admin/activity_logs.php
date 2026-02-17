<?php
/**
 * Admin Activity Logs Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

// Get filter parameters
$user_filter = $_GET['user'] ?? '';
$action_filter = $_GET['action'] ?? '';

// Build query
// Build query
$sql = "SELECT al.log_id, al.user_id, al.action AS action_type, al.details AS description, al.created_at, 
        CONCAT(u.first_name, ' ', u.last_name) as user_name, u.role 
        FROM activity_logs al 
        LEFT JOIN users u ON al.user_id = u.user_id 
        WHERE 1=1";
$params = [];
$types = '';

if (!empty($user_filter)) {
    $sql .= " AND al.user_id = ?";
    $params[] = (int)$user_filter;
    $types .= 'i';
}

if (!empty($action_filter)) {
    $sql .= " AND al.action LIKE ?";
    $params[] = '%' . $action_filter . '%';
    $types .= 's';
}

$sql .= " ORDER BY al.created_at DESC LIMIT 200";

$logs = db_query($sql, $types, $params);

// Get unique users for filter
$users = db_query("SELECT DISTINCT user_id, CONCAT(first_name, ' ', last_name) as name FROM users ORDER BY name ASC");

$page_title = 'Activity Logs - Admin';
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
            <h1 class="page-title">Activity Logs</h1>
            <button class="btn-secondary" onclick="window.print()">
                Print Logs
            </button>
        </header>

        <main>
            <!-- Filters -->
            <div class="card mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Filter by User</label>
                        <select name="user" class="input-field">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>" <?php echo $user_filter == $user['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Filter by Action</label>
                        <input type="text" name="action" class="input-field" placeholder="e.g. Login, Order, Product..." value="<?php echo htmlspecialchars($action_filter); ?>">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="btn-primary w-full">Apply Filters</button>
                    </div>
                </form>
            </div>

            <!-- Activity Logs Table -->
            <div class="card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2">
                                <th class="text-left py-3">Timestamp</th>
                                <th class="text-left py-3">User</th>
                                <th class="text-left py-3">Role</th>
                                <th class="text-left py-3">Action</th>
                                <th class="text-left py-3">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-gray-500">No activity logs found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="py-3 text-xs"><?php echo format_datetime($log['created_at']); ?></td>
                                        <td class="py-3 font-medium"><?php echo htmlspecialchars($log['user_name'] ?? 'N/A'); ?></td>
                                        <td class="py-3">
                                            <span class="badge <?php echo $log['role'] === 'Admin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                <?php echo $log['role'] ?? 'N/A'; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 font-semibold"><?php echo htmlspecialchars($log['action_type']); ?></td>
                                        <td class="py-3"><?php echo htmlspecialchars($log['description']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 text-sm text-gray-600">
                    Showing latest 200 activities
                </div>
            </div>
        </main>
    </div>
</div>

</body>
</html>
