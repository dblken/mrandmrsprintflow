<?php
/**
 * Admin Reports Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$page_title = 'Reports - Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Reports & Analytics</h1>
            <button class="btn-primary">
                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Export CSV
            </button>
        </header>

        <main>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Sales Report Card -->
                <div class="card">
                    <h3 class="text-lg font-bold mb-4">Sales Report</h3>
                    <div class="h-64 bg-gray-100 rounded flex items-center justify-center text-gray-500">
                        [Sales Chart Placeholder]
                    </div>
                    <div class="mt-4 flex justify-end">
                        <a href="#" class="text-indigo-600 font-medium hover:underline">View Detailed Report</a>
                    </div>
                </div>

                <!-- Inventory Report Card -->
                <div class="card">
                    <h3 class="text-lg font-bold mb-4">Inventory & Stock</h3>
                    <div class="h-64 bg-gray-100 rounded flex items-center justify-center text-gray-500">
                        [Inventory Chart Placeholder]
                    </div>
                    <div class="mt-4 flex justify-end">
                        <a href="#" class="text-indigo-600 font-medium hover:underline">View Detailed Report</a>
                    </div>
                </div>
            </div>

            <!-- Detailed Data Table -->
            <div class="card">
                <h3 class="text-lg font-bold mb-4">Recent Transactions</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2">
                                <th class="text-left py-3">Date</th>
                                <th class="text-left py-3">Order ID</th>
                                <th class="text-left py-3">Type</th>
                                <th class="text-left py-3">Amount</th>
                                <th class="text-left py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5" class="py-4 text-center text-gray-500">Select a report type to view data</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

</body>
</html>
