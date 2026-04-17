<?php
/**
 * Service Order Success Page
 * Shown after customer submits a service order
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('Customer');

$order_id = (int)($_SESSION['order_success_id'] ?? $_GET['id'] ?? 0);
unset($_SESSION['order_success_id']);

if (!$order_id) {
    redirect(BASE_URL . '/customer/services.php');
}

$page_title = 'Order Submitted - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen py-12 flex items-center justify-center">
    <div class="container mx-auto px-4 text-center" style="max-width: 480px;">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 text-green-600 mb-6">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Order Submitted Successfully</h1>
        <p class="text-gray-600 mb-6">Your service order #<?php echo $order_id; ?> has been received and is pending review.</p>
        <a href="<?php echo BASE_URL; ?>/customer/service_orders.php" class="btn-primary inline-block">View My Service Orders</a>
        <p class="mt-4"><a href="<?php echo BASE_URL; ?>/customer/services.php" class="text-indigo-600 hover:underline">Back to Services</a></p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

