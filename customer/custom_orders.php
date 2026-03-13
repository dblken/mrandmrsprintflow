<?php
/**
 * Customer - My Custom Orders List
 * PrintFlow - Printing Shop PWA
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');
$customer_id = get_user_id();

// Fetch job orders for this customer
$orders = db_query("SELECT id, job_title, service_type, status, payment_status, created_at, estimated_total FROM job_orders WHERE customer_id = ? ORDER BY created_at DESC", 'i', [$customer_id]);

$page_title = 'My Custom Orders - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width: 1100px;">
        
        <div class="flex justify-between items-end mb-6">
            <div>
                <h1 class="ct-page-title" style="margin-bottom: 0;">My Custom Orders</h1>
                <p class="text-gray-500 text-sm mt-1">Track the status and make payments for your custom printing requests.</p>
            </div>
            <a href="services.php" class="text-sm font-bold text-black border-b-2 border-black hover:text-indigo-600 hover:border-indigo-600 transition-colors pb-1">Request New Customization &rarr;</a>
        </div>

        <div class="card overflow-hidden border border-gray-100 shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-600 font-bold border-b-2 border-gray-100">
                        <tr>
                            <th class="py-4 px-5">ID</th>
                            <th class="py-4 px-5">Details</th>
                            <th class="py-4 px-5">Date Requested</th>
                            <th class="py-4 px-5">Status</th>
                            <th class="py-4 px-5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="5" class="py-12 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                    <p class="text-base font-semibold text-gray-600">No custom orders found</p>
                                    <p class="text-sm mt-1">You haven't requested any custom services yet.</p>
                                    <a href="services.php" class="mt-4 px-4 py-2 bg-indigo-50 text-indigo-700 font-bold rounded-lg hover:bg-indigo-100 transition-colors">Browse Services</a>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $o): ?>
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-4 px-5 font-mono font-bold text-gray-500">#<?php echo $o['id']; ?></td>
                                <td class="py-4 px-5">
                                    <div class="font-bold text-gray-900"><?php echo htmlspecialchars($o['job_title'] ?: $o['service_type']); ?></div>
                                    <div class="text-xs text-gray-500 mt-0.5"><?php echo htmlspecialchars($o['service_type']); ?></div>
                                </td>
                                <td class="py-4 px-5 whitespace-nowrap text-gray-600">
                                    <?php echo format_date($o['created_at']); ?>
                                </td>
                                <td class="py-4 px-5 whitespace-nowrap">
                                    <?php echo status_badge($o['status'], 'order'); ?>
                                </td>
                                <td class="py-4 px-5 text-right whitespace-nowrap">
                                    <?php if ($o['status'] === 'TO_PAY'): ?>
                                        <a href="job_payment.php?id=<?php echo $o['id']; ?>" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white font-bold text-xs rounded-lg hover:bg-indigo-700 transition shadow-sm">
                                            Pay Now
                                            <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                        </a>
                                    <?php else: ?>
                                        <a href="job_payment.php?id=<?php echo $o['id']; ?>" class="inline-flex shadow-sm items-center px-3 py-1.5 bg-white border border-gray-200 text-gray-700 font-bold text-xs rounded-lg hover:bg-gray-50 hover:border-gray-300 transition">
                                            View Details
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
