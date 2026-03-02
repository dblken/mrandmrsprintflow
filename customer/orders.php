<?php
/**
 * Customer Orders & Job Requests Page
 * PrintFlow - Printing Shop PWA
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');
$customer_id = get_customer_id();

// 1. Get Regular Orders
$orders = db_query(
    "SELECT * FROM orders WHERE customer_id = ? ORDER BY order_date DESC",
    'i', [$customer_id]
) ?: [];

// 2. Get Job Requests (New System)
$job_orders = db_query(
    "SELECT * FROM job_orders WHERE customer_id = ? ORDER BY created_at DESC",
    'i', [$customer_id]
) ?: [];

$page_title = 'My Orders & Jobs - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen py-8" style="background:#f5f9fa;">
    <!-- Page Header Banner -->
    <div style="background:linear-gradient(135deg,#00232b,#0e7490);padding:2rem 0;margin-bottom:2rem;">
        <div class="container mx-auto px-4" style="max-width:1100px;">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
                <div>
                    <p style="font-size:0.75rem;font-weight:700;color:rgba(83,197,224,0.8);text-transform:uppercase;letter-spacing:.1em;margin-bottom:.35rem;">My Account</p>
                    <h1 style="font-size:1.75rem;font-weight:800;color:#fff;margin:0;letter-spacing:-0.02em;">Orders & Job Requests</h1>
                </div>
                <a href="products.php" style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(83,197,224,0.15);border:1px solid rgba(83,197,224,0.3);color:#53c5e0;padding:.55rem 1.25rem;border-radius:.625rem;font-size:.875rem;font-weight:600;transition:all .2s;"
                   onmouseover="this.style.background='rgba(83,197,224,0.25)'" onmouseout="this.style.background='rgba(83,197,224,0.15)'">
                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Order
                </a>
            </div>
        </div>
    </div>
    <div class="container mx-auto px-4" style="max-width:1100px;">
        
        <div class="flex justify-between items-center mb-10">
            <h1 class="text-3xl font-black text-gray-900">Track My Orders</h1>
            <a href="new_job_order.php" class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition">Create New Job</a>
        </div>

        <!-- Section 1: Custom Job Requests (New System) -->
        <h2 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Custom Job Requests</h2>
        <?php if (empty($job_orders)): ?>
            <div class="bg-white p-8 rounded-2xl border border-dashed border-gray-300 text-center mb-10">
                <p class="text-gray-400">No custom job requests yet.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4 mb-10">
                <?php foreach ($job_orders as $jo): ?>
                    <div class="bg-white p-6 rounded-2xl border hover:border-indigo-300 transition shadow-sm">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="bg-indigo-50 text-indigo-600 text-[10px] font-bold px-2 py-1 rounded-md uppercase mb-2 inline-block">Job #<?php echo $jo['id']; ?></span>
                                <h3 class="text-lg font-black text-gray-900"><?php echo htmlspecialchars($jo['service_type']); ?></h3>
                                <p class="text-sm text-gray-500 mt-1">
                                    <?php if($jo['width_ft'] > 0): ?>
                                        <?php echo (float)$jo['width_ft']; ?> x <?php echo (float)$jo['height_ft']; ?> ft • 
                                    <?php endif; ?>
                                    Qty: <?php echo $jo['quantity']; ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-tight
                                    <?php echo $jo['status'] === 'COMPLETED' ? 'bg-green-100 text-green-700' : 
                                              ($jo['status'] === 'PENDING' ? 'bg-yellow-100 text-yellow-700' : 'bg-indigo-100 text-indigo-700'); ?>">
                                    <?php echo $jo['status']; ?>
                                </span>
                                <p class="text-xs text-gray-400 mt-2"><?php echo format_datetime($jo['created_at']); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Section 2: Regular Store Orders -->
        <h2 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Store Orders</h2>
        <?php if (empty($orders)): ?>
            <div class="bg-white p-8 rounded-2xl border border-dashed border-gray-300 text-center">
                <p class="text-gray-400">No store orders found.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($orders as $order): ?>
                    <div class="bg-white p-6 rounded-2xl border shadow-sm">
                        <div class="flex justify-between mb-4">
                            <div>
                                <p class="text-sm font-bold text-gray-900">Order #<?php echo $order['order_id']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo format_datetime($order['order_date']); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-black text-indigo-600"><?php echo format_currency($order['total_amount']); ?></p>
                                <span class="text-[10px] font-bold uppercase text-gray-400"><?php echo $order['status']; ?></span>
                            </div>
                        </div>
                        <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="text-xs font-bold text-indigo-500 hover:underline">View Order Details →</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
