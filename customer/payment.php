<?php
/**
 * Customer Order Payment Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/order_ui_helper.php';

require_role('Customer');

$order_id = (int)($_GET['order_id'] ?? 0);
$customer_id = get_user_id();
$is_job_order = false;

// Mark notification as read if parameter present
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?", 'ii', [$notification_id, $customer_id]);
}

if (!$order_id) {
    die('<div style="text-align:center; padding: 50px; font-family: sans-serif;">
            <h2 style="color: #e11d48;">Invalid Order</h2>
            <p>The order ID is missing or invalid.</p>
            <a href="orders.php" style="color: #2563eb; text-decoration: none; font-weight: bold;">Back to My Orders</a>
         </div>');
}

// 1. First check regular orders
$order_result = db_query("
    SELECT * FROM orders 
    WHERE order_id = ? AND customer_id = ?
", 'ii', [$order_id, $customer_id]);

if (!empty($order_result)) {
    $order = $order_result[0];
    
    // Get order items
    $has_product_image = !empty(db_query("SHOW COLUMNS FROM products LIKE 'product_image'"));
    $has_photo_path = !empty(db_query("SHOW COLUMNS FROM products LIKE 'photo_path'"));
    $product_image_select = "'' AS product_image";
    if ($has_product_image && $has_photo_path) {
        $product_image_select = "COALESCE(p.photo_path, p.product_image) AS product_image";
    } elseif ($has_product_image) {
        $product_image_select = "p.product_image AS product_image";
    } elseif ($has_photo_path) {
        $product_image_select = "p.photo_path AS product_image";
    }

    $items = db_query("
        SELECT oi.*, p.name as product_name, p.category, {$product_image_select}
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
    ", 'i', [$order_id]);
    
    $total_amount = (float)$order['total_amount'];
    $payment_status = $order['payment_status']; // 'Paid', 'Unpaid'
    $order_status = $order['status'];
    $show_payment_form = ($payment_status !== 'Paid' && !in_array($order_status, ['Downpayment Submitted', 'To Verify', 'Cancelled']));
    
} else {
    // 2. Fallback to job orders
    $job_result = db_query("
        SELECT * FROM job_orders 
        WHERE id = ? AND customer_id = ?
    ", 'ii', [$order_id, $customer_id]);
    
    if (empty($job_result)) {
        die('<div style="text-align:center; padding: 50px; font-family: sans-serif;">
                <h2 style="color: #e11d48;">Order Not Found</h2>
                <p>The requested order was not found or you do not have permission to view it.</p>
                <a href="orders.php" style="color: #2563eb; text-decoration: none; font-weight: bold;">Back to My Orders</a>
             </div>');
    }
    
    $order = $job_result[0];
    $is_job_order = true;
    $total_amount = (float)$order['estimated_total'];
    $payment_status = $order['payment_status']; // 'PAID', 'UNPAID', 'PARTIAL'
    $order_status = $order['status'];
    
    // Normalize status names for consistent UI
    if ($payment_status === 'PAID') $payment_status = 'Paid';
    if ($payment_status === 'UNPAID') $payment_status = 'Unpaid';
    
    $show_payment_form = ($order['payment_status'] !== 'PAID' && $order['payment_proof_status'] !== 'SUBMITTED' && $order_status !== 'CANCELLED');
}

$page_title = "Payment - Order #{$order_id}";
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .payment-container {
        max-width: 900px;
        margin: 0 auto;
        padding-bottom: 5rem;
    }
    .payment-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .payment-card {
        background: #ffffff;
        border-radius: 20px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        overflow: hidden;
        border: 1px solid #f1f5f9;
        margin-bottom: 2rem;
    }
    .payment-section-title {
        font-size: 1.125rem;
        font-weight: 800;
        color: #1a202c;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .amount-badge {
        background: #f1f5f9;
        color: #0a2530;
        padding: 1.5rem;
        border-radius: 16px;
        text-align: center;
        margin-bottom: 2rem;
    }
    .amount-label {
        font-size: 0.875rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
    }
    .amount-value {
        font-size: 2.5rem;
        font-weight: 900;
        color: #0a2530;
    }
    .pm-tab-btn {
        flex: 1;
        padding: 12px;
        border-radius: 12px;
        border: 2px solid #e2e8f0;
        background: #f8fafc;
        color: #64748b;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        text-align: center;
    }
    .pm-tab-btn.active {
        border-color: #0a2530;
        background: #0a2530;
        color: #ffffff;
    }
    .input-group {
        margin-bottom: 1.5rem;
    }
    .input-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 700;
        color: #374151;
        margin-bottom: 0.5rem;
    }
    .custom-input {
        width: 100%;
        padding: 12px 16px;
        background: #f9fafb;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-weight: 600;
        color: #111827;
        transition: all 0.2s;
    }
    .custom-input:focus {
        border-color: #0a2530;
        outline: none;
        background: #ffffff;
    }
    .dropzone {
        border: 2px dashed #e2e8f0;
        border-radius: 16px;
        padding: 2.5rem 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        background: #f8fafc;
    }
    .dropzone:hover {
        border-color: #0a2530;
        background: #f1f5f9;
    }
</style>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4 payment-container">
        
        <div class="payment-header">
            <a href="order_details.php?id=<?php echo $order_id; ?>" class="btn-secondary" style="padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; display: flex; align-items: center; gap: 4px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back
            </a>
            <h1 class="ct-page-title" style="margin:0; flex:1; text-align:center;">Complete Payment</h1>
            <div style="min-width: 100px; text-align: right;">
                <?php echo status_badge($order['status'], 'order'); ?>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 380px; gap: 2rem;">
            
            <div class="space-y-6">
                <!-- Order Items Summary -->
                <div class="payment-card p-6">
                    <h2 class="payment-section-title">
                        <span>📦</span> Order Summary
                    </h2>
                    <div class="space-y-4">
                        <?php if (!$is_job_order): ?>
                            <?php foreach ($items as $item): ?>
                                <?php render_order_item_clean($item, false, true); ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Job Order item style -->
                            <div class="card" style="padding: 0; overflow: hidden; border: 1px solid #e2e8f0; margin-bottom: 1.25rem;">
                                <div style="padding: 1rem; display: flex; gap: 1rem; align-items: flex-start; border-bottom: 1px solid #f3f4f6;">
                                    <div style="width: 120px; height: 120px; border-radius: 10px; overflow: hidden; background: #f9fafb; border: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <?php if (!empty($order['artwork_path'])): ?>
                                            <img src="/printflow/<?php echo htmlspecialchars($order['artwork_path']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <span style="font-size: 2rem;">🛠️</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <h3 style="font-size: 1.25rem; font-weight: 700; color: #111827; margin-bottom: 0.25rem; word-wrap: break-word;"><?php echo htmlspecialchars($order['job_title']); ?></h3>
                                        <div style="font-size: 0.8rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; word-wrap: break-word;">
                                            <?php echo htmlspecialchars($order['service_type']); ?>
                                        </div>
                                        <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
                                            <div style="min-width: 90px;">
                                                <div style="font-size: 0.9rem; color: #111827; font-weight: 700;">Quantity: <?php echo $order['quantity']; ?></div>
                                            </div>
                                            <div style="min-width: 150px;">
                                                <div style="font-size: 0.9rem; color: #4F46E5; font-weight: 700;">Est. Total: <?php echo format_currency($total_amount); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div style="padding: 1rem; background: #fafafa;">
                                    <h4 style="font-size: 0.8rem; font-weight: 700; color: #374151; margin-bottom: 0.75rem;">Specifications</h4>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.75rem;">
                                        <div style="background: #fff; border: 1px solid #e5e7eb; padding: 0.5rem 0.75rem; border-radius: 8px;">
                                            <div style="font-size: 0.65rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 2px;">Size</div>
                                            <div style="font-size: 0.85rem; font-weight: 600; color: #111827;"><?php echo htmlspecialchars($order['width_ft'] . ' x ' . $order['height_ft']); ?> ft</div>
                                        </div>
                                        <?php if (!empty($order['notes'])): ?>
                                            <div style="grid-column: 1 / -1; margin-top: 0.5rem; padding: 1rem; background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px;">
                                                <div style="font-size: 0.75rem; font-weight: 700; color: #92400e; margin-bottom: 4px;">📝 Notes</div>
                                                <div style="font-size: 0.9rem; color: #92400e; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div>
                <!-- Payment Submission Form -->
                <div class="payment-card p-6" style="position: sticky; top: 1.5rem;">
                    <div class="amount-badge">
                        <div class="amount-label">Amount Due</div>
                        <div class="amount-value"><?php echo format_currency($total_amount); ?></div>
                    </div>

                    <?php if ($payment_status === 'Paid'): ?>
                        <div style="text-align: center; padding: 2rem;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                            <h3 style="font-weight: 800; color: #059669; margin-bottom: 0.5rem;">Payment Completed</h3>
                            <p style="color: #64748b; font-size: 0.875rem;">This order has already been fully paid.</p>
                            <a href="<?php echo !$is_job_order ? 'order_details.php?id=' . $order_id : 'services.php'; ?>" class="btn-primary w-full mt-6 text-center block" style="text-decoration: none;">View Order Details</a>
                        </div>
                    <?php elseif (!$show_payment_form): ?>
                        <div style="text-align: center; padding: 2rem;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">⏳</div>
                            <h3 style="font-weight: 800; color: #1e293b; margin-bottom: 0.5rem;">Payment Verifying</h3>
                            <p style="color: #64748b; font-size: 0.875rem;">Your payment proof is currently under review by our staff.</p>
                            <a href="<?php echo !$is_job_order ? 'order_details.php?id=' . $order_id : 'services.php'; ?>" class="btn-primary w-full mt-6 text-center block" style="text-decoration: none;">Track Order Status</a>
                        </div>
                    <?php else: ?>
                        <form id="paymentForm" enctype="multipart/form-data">
                            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                            <input type="hidden" name="is_job" value="<?php echo $is_job_order ? '1' : '0'; ?>">
                            <?php echo csrf_field(); ?>

                            <h2 class="payment-section-title" style="margin-bottom: 1rem; font-size: 1rem;">1. Choose Method</h2>
                            
                            <?php 
                            $qr_dir = __DIR__ . '/../public/assets/uploads/qr/';
                            $payment_cfg_path = $qr_dir . 'payment_methods.json';
                            $payment_methods = file_exists($payment_cfg_path) ? json_decode(file_get_contents($payment_cfg_path), true) : [];
                            $enabled_methods = array_filter($payment_methods ?: [], function($m) { return !empty($m['enabled']); });
                            ?>

                            <?php if (empty($enabled_methods)): ?>
                                <div style="background: #fff1f2; border: 1px solid #ffe4e6; border-radius: 12px; padding: 1rem; color: #be123c; font-size: 0.875rem; font-weight: 600; margin-bottom: 1.5rem;">
                                    Online payment is currently unavailable. Please contact the shop.
                                </div>
                            <?php else: ?>
                                <div style="display: flex; gap: 8px; margin-bottom: 1.5rem;">
                                    <?php $first = true; foreach ($enabled_methods as $index => $pm): ?>
                                        <button type="button" onclick="selectPM(<?php echo $index; ?>)" id="btn-pm-<?php echo $index; ?>" class="pm-tab-btn <?php echo $first ? 'active' : ''; ?>">
                                            <?php echo htmlspecialchars($pm['provider']); ?>
                                        </button>
                                    <?php $first = false; endforeach; ?>
                                </div>

                                <div id="pm-details-container" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem; text-align: center;">
                                    <?php $first = true; foreach ($enabled_methods as $index => $pm): ?>
                                        <div id="pm-info-<?php echo $index; ?>" style="display: <?php echo $first ? 'block' : 'none'; ?>;">
                                            <?php if (!empty($pm['file'])): ?>
                                                <img src="/printflow/public/assets/uploads/qr/<?php echo htmlspecialchars($pm['file']); ?>" style="width: 150px; height: 150px; object-fit: contain; margin: 0 auto 1rem; display: block; border-radius: 12px; border: 4px solid #fff;">
                                            <?php endif; ?>
                                            <div style="font-weight: 800; color: #1e293b; font-size: 1.1rem;"><?php echo htmlspecialchars($pm['provider']); ?></div>
                                            <div style="color: #64748b; font-size: 0.875rem; font-weight: 600; margin-top: 4px;"><?php echo htmlspecialchars($pm['label']); ?></div>
                                        </div>
                                    <?php $first = false; endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <h2 class="payment-section-title" style="margin-bottom: 1rem; font-size: 1rem;">2. Payment Info</h2>
                            
                            <div class="input-group">
                                <label class="input-label">Amount Paid (PHP)</label>
                                <input type="number" name="amount" id="paymentAmountInput" step="0.01" class="custom-input" 
                                       value="<?php echo number_format($order['total_amount'], 2, '.', ''); ?>" 
                                       min="<?php echo number_format($order['total_amount'] * 0.5, 2, '.', ''); ?>" required>
                                <p style="font-size: 0.75rem; color: #64748b; margin-top: 6px; font-weight: 600;">Min. 50% Downpayment required: <?php echo format_currency($order['total_amount'] * 0.5); ?></p>
                            </div>

                            <div class="input-group">
                                <label class="input-label">Upload Reference Receipt</label>
                                <input type="file" name="payment_proof" id="proofInput" style="display: none;" accept="image/*" required>
                                <div id="dropzone" class="dropzone" onclick="document.getElementById('proofInput').click()">
                                    <div id="placeholder" style="display: block;">
                                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">📸</div>
                                        <div style="font-weight: 700; color: #1e293b; font-size: 0.875rem;">Click to upload receipt</div>
                                        <div style="font-size: 0.75rem; color: #64748b;">JPG, PNG or PDF</div>
                                    </div>
                                    <div id="preview" style="display: none; align-items: center; justify-content: center; flex-direction: column;">
                                        <img id="previewImg" src="" style="max-height: 120px; border-radius: 8px; margin-bottom: 10px;">
                                        <p id="fileName" style="font-size: 0.8125rem; font-weight: 700; color: #1e293b;"></p>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" id="submitBtn" class="btn-primary w-full py-4 text-center block font-black uppercase tracking-widest mt-4" <?php echo empty($enabled_methods) ? 'disabled style="opacity:0.5;"' : ''; ?>>
                                Submit Payment Proof
                            </button>

                            <p style="text-align: center; font-size: 0.75rem; color: #94a3b8; font-weight: 600; margin-top: 1rem;">
                                <svg style="width: 12px; height: 12px; display: inline; vertical-align: middle;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path></svg>
                                Secure Payment Verification
                            </p>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    function selectPM(idx) {
        document.querySelectorAll('.pm-tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('btn-pm-' + idx).classList.add('active');
        
        document.querySelectorAll('[id^="pm-info-"]').forEach(i => i.style.display = 'none');
        document.getElementById('pm-info-' + idx).style.display = 'block';
    }

    const proofInput = document.getElementById('proofInput');
    const placeholder = document.getElementById('placeholder');
    const preview = document.getElementById('preview');
    const previewImg = document.getElementById('previewImg');
    const fileName = document.getElementById('fileName');

    if (proofInput) {
        proofInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                fileName.textContent = file.name;
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImg.src = e.target.result;
                    placeholder.style.display = 'none';
                    preview.style.display = 'flex';
                };
                reader.readAsDataURL(file);
            }
        });
    }

    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span style="display:flex; align-items:center; justify-content:center; gap:8px;">Uploading...</span>';

            const formData = new FormData(this);
            
            fetch('api_submit_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessModal(
                        'Payment Success',
                        'Your payment proof has been submitted and is now under review. We\'ll notify you once verified!',
                        'order_details.php?id=<?php echo $order_id; ?>',
                        'services.php',
                        'View Order',
                        'Back to Services',
                        'services.php',
                        4000
                    );
                } else {
                    alert('Error: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = 'Submit Payment Proof';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An unexpected error occurred. Please try again.');
                btn.disabled = false;
                btn.textContent = 'Submit Payment Proof';
            });
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
