<?php
/**
 * Order Review & Confirm Page
 * PrintFlow — Shown when customer clicks "Buy Now"
 * Displays full order summary with design image preview,
 * customization details, price, and Cancel / Confirm buttons.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/order_ui_helper.php';

require_role('Customer');

// ── Accept the "buy_now" item key from session ──────────────────
$item_key = $_GET['item'] ?? '';
$cart     = $_SESSION['cart'] ?? [];

if (!$item_key || !isset($cart[$item_key])) {
    redirect('products.php');
}

$item        = $cart[$item_key];
$customer_id = get_user_id();
$customer    = db_query("SELECT * FROM customers WHERE customer_id = ?", 'i', [$customer_id])[0] ?? [];
$customer_type = $customer['customer_type'] ?? 'new';

// ── Handle Place Order FIRST (to allow clearing cart without trigger redirect) ──
$order_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $order_error = 'Invalid request. Please try again.';
    } else {
        // Fetch cart again for current POST request
        $item        = $cart[$item_key] ?? null;
        if ($item) {
            $subtotal = $item['price'] * $item['quantity'];

            // Check restriction AGAIN at submission
            $cancel_count = get_customer_cancel_count($customer_id);
            $is_restricted = is_customer_restricted($customer_id);

            if ($is_restricted) {
                $order_error = "🚫 Your account is restricted from placing new orders.";
            } else {
                global $conn;
                $downpayment_amount = 0;
                $payment_type = 'full_payment';
                $payment_status = 'Unpaid';

                $notes = $item['customization']['notes'] ?? $item['customization']['additional_notes'] ?? null;
                $branch_id = $item['branch_id'] ?? null;
                $order_sql = "INSERT INTO orders (customer_id, branch_id, order_date, total_amount, downpayment_amount, status, payment_status, payment_type, notes)
                              VALUES (?, ?, NOW(), ?, ?, 'Pending Review', ?, ?, ?)";
                $order_id  = db_execute($order_sql, 'iiddsss', [$customer_id, $branch_id, $subtotal, $downpayment_amount, $payment_status, $payment_type, $notes]);

                if ($order_id) {
                    $custom = $item['customization'] ?? [];
                    if (empty($custom['service_type']) && !empty($item['name']) && ($item['type'] ?? '') === 'Service') {
                        $custom['service_type'] = $item['name'];
                    }
                    $custom_data   = json_encode($custom);
                    $design_binary = null;
                    $design_mime   = $item['design_mime']   ?? null;
                    $design_name   = $item['design_name']   ?? null;
                    
                    $upload_dir = __DIR__ . '/../uploads/orders';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                    $design_file_path = null;
                    $reference_file_path = null;

                    if (!empty($item['design_tmp_path']) && file_exists($item['design_tmp_path'])) {
                        $design_binary = file_get_contents($item['design_tmp_path']);
                        $ext = strtolower(pathinfo($design_name, PATHINFO_EXTENSION));
                        $new_name = uniqid('design_') . '_' . time() . '.' . $ext;
                        if (copy($item['design_tmp_path'], $upload_dir . '/' . $new_name)) {
                            $design_file_path = '/printflow/uploads/orders/' . $new_name;
                        }
                    }

                    if (!empty($item['reference_tmp_path']) && file_exists($item['reference_tmp_path'])) {
                        $ref_name = $item['reference_name'] ?? 'reference.jpg';
                        $ext = strtolower(pathinfo($ref_name, PATHINFO_EXTENSION));
                        $new_name = uniqid('ref_') . '_' . time() . '.' . $ext;
                        if (copy($item['reference_tmp_path'], $upload_dir . '/' . $new_name)) {
                            $reference_file_path = '/printflow/uploads/orders/' . $new_name;
                        }
                    }

                    $product_id = !empty($item['product_id']) ? (int)$item['product_id'] : null;
                    if ($product_id === null) {
                        $service_type = $custom['service_type'] ?? $item['name'] ?? '';
                        $service_product_map = [
                            'Tarpaulin Printing' => 4,
                            'T-Shirt Printing' => 1,
                            'Glass & Wall Sticker Printing' => 3,
                            'Transparent Sticker Printing' => 3,
                            'Decals / Stickers' => 3,
                            'Sintraboard Standees' => 3,
                            'Layout Design Service' => 3,
                        ];
                        $product_id = $service_product_map[$service_type] ?? 3;
                    }

                    if ($design_binary) {
                        $stmt = $conn->prepare(
                            "INSERT INTO order_items (order_id, product_id, quantity, unit_price, customization_data, 
                                                    design_image, design_image_mime, design_image_name, design_file, reference_image_file)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                        );
                        if ($stmt) {
                            $null = NULL;
                            $stmt->bind_param('iiidssssss', $order_id, $product_id, $item['quantity'], $item['price'], $custom_data, $null, $design_mime, $design_name, $design_file_path, $reference_file_path);
                            $stmt->send_long_data(5, $design_binary);
                            $stmt->execute();
                            $stmt->close();
                        }
                    } else {
                        db_execute(
                            "INSERT INTO order_items (order_id, product_id, quantity, unit_price, customization_data, design_file, reference_image_file) 
                             VALUES (?, ?, ?, ?, ?, ?, ?)",
                            'iiidsss',
                            [$order_id, $product_id, $item['quantity'], $item['price'], $custom_data, $design_file_path, $reference_file_path]
                        );
                    }

                    if (!empty($item['design_tmp_path']) && file_exists($item['design_tmp_path'])) @unlink($item['design_tmp_path']);
                    if (!empty($item['reference_tmp_path']) && file_exists($item['reference_tmp_path'])) @unlink($item['reference_tmp_path']);
                    unset($_SESSION['cart'][$item_key]);

                    $welcomeMsg = "Your order #{$order_id} has been placed successfully! Our team will review it shortly.";
                    create_notification($customer_id, 'Customer', $welcomeMsg, 'Order', true, false, $order_id);
                    add_order_system_message($order_id, $welcomeMsg);
                    notify_staff_new_order((int)$order_id, (string)($customer['first_name'] ?? 'Customer'));

                    $_SESSION['success'] = "Your order #{$order_id} has been placed successfully!";
                    $order_placed_id = $order_id;
                } else {
                    $order_error = 'Failed to place order. Please try again.';
                }
            }
        }
    }
}

$cart = $_SESSION['cart'] ?? [];
if (!$item_key || (!isset($cart[$item_key]) && !isset($order_placed_id))) {
    redirect('products.php');
}

$item     = $cart[$item_key] ?? null;
$subtotal = $item ? ($item['price'] * $item['quantity']) : 0;

// ── Build design preview (base64 for inline display) ───────────
$design_preview_src = null;
if (!isset($order_placed_id) && !empty($item['design_tmp_path']) && file_exists($item['design_tmp_path']) && !empty($item['design_mime'])) {
    $binary = file_get_contents($item['design_tmp_path']);
    if ($binary) {
        $design_preview_src = 'data:' . $item['design_mime'] . ';base64,' . base64_encode($binary);
    }
}

$ref_preview_src = null;
if (!empty($item['reference_tmp_path']) && file_exists($item['reference_tmp_path']) && !empty($item['reference_mime'])) {
    $binary = file_get_contents($item['reference_tmp_path']);
    if ($binary) {
        $ref_preview_src = 'data:' . $item['reference_mime'] . ';base64,' . base64_encode($binary);
    }
}

// Fetch branch name
$branch_name = 'Multiple/Selected Branch';
if (!empty($item['branch_id'])) {
    $b = db_query("SELECT branch_name FROM branches WHERE id = ?", 'i', [$item['branch_id']])[0] ?? [];
    if (!empty($b)) $branch_name = $b['branch_name'];
}

$page_title      = 'Review Your Order — PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .order-container { max-width: 650px; margin: 0 auto; }
    .compact-section { margin-bottom: 1.25rem; }
    .compact-card { padding: 1.25rem !important; }

    /* Success Modal Styles */
    .success-modal-overlay {
        position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(8px); z-index: 9999;
        display: flex; align-items: center; justify-content: center;
        opacity: 0; pointer-events: none; transition: opacity 0.4s ease;
    }
    .success-modal-overlay.active { opacity: 1; pointer-events: auto; }
    
    .success-modal-card {
        background: white; width: 90%; max-width: 400px; padding: 40px 30px;
        border-radius: 24px; text-align: center;
        transform: scale(0.9); transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }
    .success-modal-overlay.active .success-modal-card { transform: scale(1); }

    .success-icon-wrap {
        width: 80px; height: 80px; background: #ecfdf5; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 24px; color: #10b981;
    }
    .success-checkmark { font-size: 3rem; animation: checkmarkScale 0.5s ease 0.2s both; }
    @keyframes checkmarkScale { 
        0% { transform: scale(0); opacity: 0; }
        60% { transform: scale(1.2); }
        100% { transform: scale(1); opacity: 1; }
    }

    .success-title { font-size: 1.25rem; font-weight: 800; color: #1e293b; margin-bottom: 8px; }
    .success-msg { font-size: 0.95rem; color: #64748b; line-height: 1.5; margin-bottom: 24px; }
    
    .loading-bar-wrap { width: 100%; height: 6px; background: #f1f5f9; border-radius: 10px; overflow: hidden; margin-bottom: 8px; }
    .loading-bar-fill { width: 0%; height: 100%; background: #10b981; transition: width 3s linear; }
    .redirect-msg { font-size: 0.75rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
</style>

<!-- Success Modal -->
<div id="successModal" class="success-modal-overlay <?php echo isset($order_placed_id) ? 'active' : ''; ?>">
    <div class="success-modal-card">
        <div class="success-icon-wrap">
            <span class="success-checkmark">✓</span>
        </div>
        <h2 class="success-title">Order Placed Successfully!</h2>
        <p class="success-msg">Your order <strong>#<?php echo $order_placed_id ?? ''; ?></strong> has been sent to our team for review. You'll receive a notification shortly.</p>
        
        <div class="loading-bar-wrap">
            <div id="loadingBar" class="loading-bar-fill"></div>
        </div>
        <p class="redirect-msg">Redirecting to services...</p>
    </div>
</div>

<script>
    window.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('successModal');
        const bar = document.getElementById('loadingBar');
        
        if (modal && modal.classList.contains('active')) {
            // Start loading bar animation
            setTimeout(() => {
                bar.style.width = '100%';
            }, 100);

            // Redirect after 3 seconds
            setTimeout(() => {
                window.location.href = '/printflow/customer/services.php';
            }, 3100);
        }
    });
</script>

<div class="min-h-screen py-8">
    <?php if (!isset($order_placed_id)): ?>
    <div class="container mx-auto px-4 order-container">
        <h1 class="ct-page-title" style="text-align: center; margin-bottom: 2rem;">Review Your Order</h1>

        <form method="POST">
            <?php echo csrf_field(); ?>
            
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <?php if ($order_error): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($order_error); ?></div>
                <?php endif; ?>

                <!-- 1. Order Summary (Prominent, no price) -->
                <div class="card compact-card">
                    <h2 style="font-size:1rem; font-weight:700; margin-bottom:1rem; color:#111827; display:flex; align-items:center; gap:8px;">
                        <span>🛒</span> Order Summary
                    </h2>
                    <?php render_order_item_clean($item, true, false); ?>

                    <!-- Pricing Notice (replaces price display) -->
                    <div style="margin-top:1rem; background:linear-gradient(135deg,#f0f9ff,#e0f2fe); border:1px solid #bae6fd; border-left:4px solid #0ea5e9; border-radius:10px; padding:14px 16px; display:flex; gap:12px; align-items:flex-start;">
                        <span style="font-size:1.25rem; flex-shrink:0;">ℹ️</span>
                        <div>
                            <div style="font-size:0.82rem; font-weight:700; color:#0c4a6e; margin-bottom:3px;">Price will be confirmed by the shop</div>
                            <div style="font-size:0.75rem; color:#0369a1; line-height:1.5;">Your order will be reviewed and priced by our team. Payment options will be available once your order reaches the <strong>To Pay</strong> stage.</div>
                        </div>
                    </div>
                </div>

                <!-- 2. Contact Information -->
                <div class="card compact-card">
                    <h2 style="font-size:1rem; font-weight:700; margin-bottom:1rem; color:#111827; border-bottom:1px solid #f3f4f6; padding-bottom:0.5rem; display:flex; align-items:center; gap:8px;">
                        <span>👤</span> Contact Information
                    </h2>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:4px;">Full Name</label>
                            <input type="text" class="input-field" value="<?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>" disabled style="background:#f9fafb; font-weight:600; font-size:0.85rem; padding:8px 12px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:4px;">Email Address</label>
                            <input type="text" class="input-field" value="<?php echo htmlspecialchars($customer['email']); ?>" disabled style="background:#f9fafb; font-weight:600; font-size:0.85rem; padding:8px 12px;">
                        </div>
                        <div style="grid-column:span 2;">
                            <label style="display:block; font-size:0.7rem; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:4px;">Phone Number</label>
                            <input type="text" class="input-field" value="<?php echo htmlspecialchars($customer['contact_number'] ?? '—'); ?>" disabled style="background:#f9fafb; font-weight:600; font-size:0.85rem; padding:8px 12px;">
                        </div>
                    </div>
                </div>

                <!-- 3. Payment Policy Notice (no options shown yet) -->
                <div class="card compact-card" style="background:linear-gradient(135deg,#fffbeb,#fef9c3); border:1px solid #fde68a;">
                    <h2 style="font-size:1rem; font-weight:700; margin-bottom:0.75rem; color:#92400e; display:flex; align-items:center; gap:8px;">
                        <span>💳</span> Payment Policy
                    </h2>
                    <p style="font-size:0.82rem; color:#78350f; line-height:1.6; margin:0;">
                        Payment options (100% Full Payment or 50% Downpayment) will become available once staff reviews your order and sets the price.
                        You will receive a notification when your order is ready for payment.
                    </p>
                </div>

                <!-- 4. Final Actions -->
                <div style="margin-top:0.5rem; display:flex; flex-direction:column; gap:10px;">
                    <button type="submit" name="confirm_order" class="btn-primary" style="width:100%; padding:12px; font-weight:800; font-size:0.92rem; border-radius:10px; background:#0a2530; text-transform:uppercase; letter-spacing:0.03em;">Buy Now</button>
                    
                    <a href="?item=<?php echo urlencode($item_key); ?>&cancel=1" 
                       onclick="return confirm('Cancel this order?');"
                       style="display:inline-flex; align-items:center; justify-content:center; width:100%; border:1px solid #e2e8f0; border-radius:10px; font-size:0.85rem; color:#64748b; text-decoration:none; font-weight:700; padding:11px 14px; transition:all 0.2s;"
                       onmouseover="this.style.color='#ef4444'; this.style.borderColor='#fecaca'; this.style.background='#fef2f2';" 
                       onmouseout="this.style.color='#64748b'; this.style.borderColor='#e2e8f0'; this.style.background='transparent';">
                        ✕ Cancel Order
                    </a>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

