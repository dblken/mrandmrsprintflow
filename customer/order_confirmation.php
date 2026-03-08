<?php
/**
 * Customer: Order Confirmation
 * Displays success message and order summary after submission.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Customer', 'Staff', 'Admin']);
$page_title = 'Order Received - PrintFlow';

$id = (int)($_GET['id'] ?? 0);
$order = db_query("SELECT jo.*, c.first_name, c.last_name FROM job_orders jo LEFT JOIN customers c ON jo.customer_id = c.customer_id WHERE jo.id = ?", 'i', [$id]);

if (!$order) {
    header('Location: dashboard.php');
    exit;
}

$jo = $order[0];
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php'; 
?>

<div class="py-16">
    <div class="container mx-auto px-4 max-w-2xl">
        <div class="text-center mb-12">
            <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl">
                <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <h1 class="ct-page-title mb-2">Order Received!</h1>
            <p class="text-gray-500">Your customization has been successfully submitted and is now awaiting verification by our staff.</p>
        </div>

        <div class="ct-card border-2 border-dashed border-indigo-100 bg-white shadow-xl shadow-indigo-50/50 mb-8 overflow-hidden">
            <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center text-white">
                <span class="text-xs font-black uppercase tracking-widest">Job Reference No.</span>
                <span class="text-xl font-black">#JO-<?php echo $jo['id']; ?></span>
            </div>
            
            <div class="p-8 space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-[10px] text-gray-400 font-black uppercase tracking-widest block mb-1">Service Type</span>
                        <div class="font-bold text-gray-800"><?php echo htmlspecialchars($jo['service_type']); ?></div>
                    </div>
                    <div>
                        <span class="text-[10px] text-gray-400 font-black uppercase tracking-widest block mb-1">Status</span>
                        <div class="inline-flex px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded text-[10px] font-black uppercase"><?php echo $jo['status']; ?></div>
                    </div>
                    <div>
                        <span class="text-[10px] text-gray-400 font-black uppercase tracking-widest block mb-1">Specs</span>
                        <div class="text-xs font-medium text-gray-600">
                             <?php if($jo['width_ft'] > 0): ?>
                                <?php echo (float)$jo['width_ft']; ?>' x <?php echo (float)$jo['height_ft']; ?>' • 
                            <?php endif; ?>
                            Qty: <?php echo $jo['quantity']; ?>
                        </div>
                    </div>
                    <div>
                        <span class="text-[10px] text-gray-400 font-black uppercase tracking-widest block mb-1">Estimated Total</span>
                        <div class="font-bold text-indigo-600">₱<?php echo number_format($jo['estimated_total'], 2); ?></div>
                    </div>
                </div>

                <div class="p-4 bg-gray-50 rounded-xl border">
                    <span class="text-[10px] text-gray-400 font-black uppercase tracking-widest block mb-2">Next Steps</span>
                    <ul class="text-xs space-y-2 text-gray-600">
                        <li class="flex gap-2"><span>1.</span> Our team will review your artwork and specifications.</li>
                        <li class="flex gap-2"><span>2.</span> You will receive a notification once the job is approved.</li>
                        <li class="flex gap-2"><span>3.</span> Please proceed with the required downpayment to start production.</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-4">
            <a href="orders.php" class="ct-btn-primary flex-1">Track My Orders</a>
            <a href="new_job_order.php" class="ct-btn-outline flex-1">Place Another Order</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
