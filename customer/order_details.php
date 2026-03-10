<?php
/**
 * Customer Order Details Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

$customer_id = get_customer_id();
$order_id    = (int)($_GET['id'] ?? 0);

if (!$order_id) {
    redirect('/printflow/customer/orders.php');
}

// Get order — ensure it belongs to this customer
$order_result = db_query(
    "SELECT * FROM orders WHERE order_id = ? AND customer_id = ?",
    'ii', [$order_id, $customer_id]
);
if (empty($order_result)) {
    redirect('/printflow/customer/orders.php');
}
$order = $order_result[0];

// Get order items with product + variant info
$items = db_query(
    "SELECT oi.*, p.name AS product_name, p.category,
            pv.variant_name
     FROM order_items oi
     LEFT JOIN products p ON p.product_id = oi.product_id
     LEFT JOIN product_variants pv ON pv.variant_id = oi.variant_id
     WHERE oi.order_id = ?
     ORDER BY oi.item_id ASC",
    'i', [$order_id]
);

$page_title       = "Order #{$order_id} - PrintFlow";
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width:820px;">

        <a href="orders.php" style="display:inline-flex;align-items:center;gap:6px;font-size:0.85rem;color:#6b7280;margin-bottom:0.75rem;">
            ← Back to My Orders
        </a>
        <h1 class="ct-page-title" style="margin-top:4px;">Order #<?php echo $order_id; ?></h1>

        <!-- Status Row -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:1rem;margin-bottom:1.5rem;">
            <div class="card" style="padding:16px 20px;">
                <p style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:#9ca3af;margin-bottom:6px;">Order Status</p>
                <?php echo status_badge($order['status'], 'order'); ?>
            </div>
            <div class="card" style="padding:16px 20px;">
                <p style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:#9ca3af;margin-bottom:6px;">Payment</p>
                <?php echo status_badge($order['payment_status'], 'payment'); ?>
            </div>
            <div class="card" style="padding:16px 20px;">
                <p style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:#9ca3af;margin-bottom:6px;">Order Date</p>
                <p style="font-size:0.9rem;font-weight:600;color:#1f2937;"><?php echo format_datetime($order['order_date']); ?></p>
            </div>
            <div class="card" style="padding:16px 20px;">
                <p style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:#9ca3af;margin-bottom:6px;">Est. Completion</p>
                <p style="font-size:0.9rem;font-weight:600;color:#1f2937;"><?php echo $order['estimated_completion'] ? format_date($order['estimated_completion']) : 'TBD'; ?></p>
            </div>
        </div>

        <!-- Order Items -->
        <div class="card" style="margin-bottom:1.5rem;">
            <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem;color:#1f2937;">Items</h2>
            <?php if (empty($items)): ?>
                <p style="color:#9ca3af;font-size:0.875rem;">No items found.</p>
            <?php else: ?>
                <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                    <thead>
                        <tr style="border-bottom:2px solid #e5e7eb;">
                            <th style="text-align:left;padding:10px 0;color:#6b7280;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;">Product</th>
                            <th style="text-align:center;padding:10px 0;color:#6b7280;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;">Qty</th>
                            <th style="text-align:right;padding:10px 0;color:#6b7280;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;">Unit Price</th>
                            <th style="text-align:right;padding:10px 0;color:#6b7280;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:12px 0;">
                                <div style="font-weight:600;color:#1f2937;"><?php echo htmlspecialchars($item['product_name'] ?? 'Unknown'); ?></div>
                                <?php if (!empty($item['variant_name'])): ?>
                                <div style="margin-top:3px;">
                                    <span style="display:inline-flex;align-items:center;gap:4px;background:#e0e7ff;color:#3730a3;padding:2px 10px;border-radius:20px;font-size:0.72rem;font-weight:500;">
                                        📐 <?php echo htmlspecialchars($item['variant_name']); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <div style="font-size:0.75rem;color:#9ca3af;margin-top:2px;"><?php echo htmlspecialchars($item['category'] ?? ''); ?></div>
                            </td>
                            <td style="padding:12px 0;text-align:center;font-weight:600;"><?php echo (int)$item['quantity']; ?></td>
                            <td style="padding:12px 0;text-align:right;color:#6b7280;"><?php echo format_currency($item['unit_price']); ?></td>
                            <td style="padding:12px 0;text-align:right;font-weight:700;color:#1f2937;"><?php echo format_currency($item['quantity'] * $item['unit_price']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="padding:14px 0;text-align:right;font-weight:700;font-size:0.95rem;">Total</td>
                            <td style="padding:14px 0;text-align:right;font-weight:800;font-size:1.1rem;color:#4F46E5;"><?php echo format_currency($order['total_amount']); ?></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>

        <!-- Notes -->
        <?php if (!empty($order['notes'])): ?>
        <div class="card" style="margin-bottom:1.5rem;">
            <h2 style="font-size:1rem;font-weight:700;margin-bottom:0.75rem;color:#1f2937;">Order Notes</h2>
            <p style="font-size:0.875rem;color:#4b5563;"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
        </div>
        <?php endif; ?>

        <!-- Payment CTA -->
        <?php if (in_array($order['payment_status'], ['Unpaid', 'Pending'])): ?>
        <div style="background:linear-gradient(135deg,#4F46E5,#7c3aed);border-radius:14px;padding:20px 24px;color:#fff;display:flex;align-items:center;justify-content:space-between;gap:1rem;">
            <div>
                <p style="font-weight:700;margin-bottom:4px;">Payment Required</p>
                <p style="font-size:0.85rem;opacity:0.85;">Upload your payment proof to process this order.</p>
            </div>
            <a href="payment_confirmation.php?order_id=<?php echo $order_id; ?>"
               style="background:#fff;color:#4F46E5;padding:10px 20px;border-radius:8px;font-weight:600;font-size:0.875rem;white-space:nowrap;">
                Upload Payment
            </a>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
