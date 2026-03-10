<?php
/**
 * Customer Orders & Customizations Page
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

// 2. Get Customizations (New System)
$job_orders = db_query(
    "SELECT * FROM job_orders WHERE customer_id = ? ORDER BY created_at DESC",
    'i', [$customer_id]
) ?: [];

$page_title = 'My Orders & Customizations - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Hero Banner -->
<div style="background:#00151b;position:relative;overflow:hidden;padding:2.75rem 0 3.5rem;">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:700px;height:220px;background:radial-gradient(ellipse at center,rgba(50,161,196,0.18) 0%,rgba(83,197,224,0.06) 50%,transparent 75%);pointer-events:none;z-index:0;"></div>
    <div class="container mx-auto px-4" style="max-width:1100px;position:relative;z-index:1;text-align:center;">
        <p style="font-size:0.7rem;font-weight:700;color:rgba(83,197,224,0.8);text-transform:uppercase;letter-spacing:.12em;margin:0 0 .6rem;">My Account</p>
        <h1 style="font-size:clamp(1.75rem,3.5vw,2.75rem);font-weight:800;color:#fff;letter-spacing:-0.03em;margin:0 0 .75rem;line-height:1.1;">Orders &amp; Customizations</h1>
        <p style="font-size:0.9rem;color:rgba(255,255,255,0.45);max-width:480px;margin:0 auto 1.5rem;line-height:1.65;">Track your print orders and custom job requests all in one place.</p>
        <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;">
            <a href="products.php" style="display:inline-flex;align-items:center;gap:.45rem;background:rgba(83,197,224,0.15);border:1px solid rgba(83,197,224,0.3);color:#53c5e0;padding:.5rem 1.25rem;border-radius:.625rem;font-size:.8125rem;font-weight:600;transition:all .2s;" onmouseover="this.style.background='rgba(83,197,224,0.25)'" onmouseout="this.style.background='rgba(83,197,224,0.15)'">
                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Order
            </a>
            <a href="new_job_order.php" style="display:inline-flex;align-items:center;gap:.45rem;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);color:rgba(255,255,255,0.7);padding:.5rem 1.25rem;border-radius:.625rem;font-size:.8125rem;font-weight:600;transition:all .2s;" onmouseover="this.style.background='rgba(255,255,255,0.12)'" onmouseout="this.style.background='rgba(255,255,255,0.06)'">
                Customization
            </a>
        </div>
    </div>
</div>

<div class="min-h-screen" style="background:#f5f9fa;padding-top:2.5rem;padding-bottom:3rem;">
    <div class="container mx-auto px-4" style="max-width:1100px;">
        
        <!-- Section 1: Customizations (New System) -->
        <h2 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Customizations</h2>
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
                                <span class="bg-indigo-50 text-indigo-600 text-[10px] font-bold px-2 py-1 rounded-md uppercase mb-2 inline-block">Custom #<?php echo $jo['id']; ?></span>
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
