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

$subtotal = $item['price'] * $item['quantity'];

// ── Handle Cancel ──────────────────────────────────────────────
if (isset($_GET['cancel'])) {
    // Clean up temp file
    if (!empty($item['design_tmp_path']) && file_exists($item['design_tmp_path'])) {
        @unlink($item['design_tmp_path']);
    }
    unset($_SESSION['cart'][$item_key]);
    redirect('products.php');
}

// ── Handle Place Order ─────────────────────────────────────────
$order_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $order_error = 'Invalid request. Please try again.';
    } else {
        // Check restriction AGAIN at submission
        $cancel_count = get_customer_cancel_count($customer_id);
        $is_restricted = is_customer_restricted($customer_id);

        if ($is_restricted) {
            $order_error = "🚫 Your account is restricted from placing new orders.";
        } else {
            global $conn;

            // Pricing and payment are determined AFTER staff review.
            // The review page does not collect payment choice from the customer.
            // Staff will set the price and move to 'To Pay' status when ready.
            $downpayment_amount = 0;
            $payment_type = 'tbd'; // Staff will finalize this
            $payment_status = 'Unpaid';

            // 1. Create order
            $notes = $_POST['notes'] ?? ($item['customization']['notes'] ?? null);
            $branch_id = $item['branch_id'] ?? null;
            $order_sql = "INSERT INTO orders (customer_id, branch_id, order_date, total_amount, downpayment_amount, status, payment_status, payment_type, notes)
                          VALUES (?, ?, NOW(), ?, ?, 'Pending Review', ?, ?, ?)";
            $order_id  = db_execute($order_sql, 'iiddsss', [$customer_id, $branch_id, $subtotal, $downpayment_amount, $payment_status, $payment_type, $notes]);

            if ($order_id) {
                $custom_data   = isset($item['customization']) ? json_encode($item['customization']) : null;
                $design_binary = null;
                $design_mime   = $item['design_mime']   ?? null;
                $design_name   = $item['design_name']   ?? null;
                
                $upload_dir = __DIR__ . '/../uploads/orders';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                $design_file_path = null;
                $reference_file_path = null;

                // 1. Handle Main Design
                if (!empty($item['design_tmp_path']) && file_exists($item['design_tmp_path'])) {
                    $design_binary = file_get_contents($item['design_tmp_path']);
                    $ext = strtolower(pathinfo($design_name, PATHINFO_EXTENSION));
                    $new_name = uniqid('design_') . '_' . time() . '.' . $ext;
                    if (copy($item['design_tmp_path'], $upload_dir . '/' . $new_name)) {
                        $design_file_path = '/printflow/uploads/orders/' . $new_name;
                    }
                }

                // 2. Handle Reference Image
                if (!empty($item['reference_tmp_path']) && file_exists($item['reference_tmp_path'])) {
                    $ref_name = $item['reference_name'] ?? 'reference.jpg';
                    $ext = strtolower(pathinfo($ref_name, PATHINFO_EXTENSION));
                    $new_name = uniqid('ref_') . '_' . time() . '.' . $ext;
                    if (copy($item['reference_tmp_path'], $upload_dir . '/' . $new_name)) {
                        $reference_file_path = '/printflow/uploads/orders/' . $new_name;
                    }
                }

                $product_id = !empty($item['product_id']) ? $item['product_id'] : NULL;

                if ($design_binary) {
                    $stmt = $conn->prepare(
                        "INSERT INTO order_items (order_id, product_id, quantity, unit_price, customization_data, 
                                                design_image, design_image_mime, design_image_name, design_file, reference_image_file)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    if ($stmt) {
                        $null = NULL;
                        $stmt->bind_param('iiidssssss',
                            $order_id,
                            $product_id,
                            $item['quantity'],
                            $item['price'],
                            $custom_data,
                            $null,
                            $design_mime,
                            $design_name,
                            $design_file_path,
                            $reference_file_path
                        );
                        $stmt->send_long_data(5, $design_binary);
                        $stmt->execute();
                        $stmt->close();
                    }
                } else {
                    db_execute(
                        "INSERT INTO order_items (order_id, product_id, quantity, unit_price, customization_data, design_file, reference_image_file) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)",
                        'iiidsss',
                        [
                            $order_id, $product_id, $item['quantity'], $item['price'], $custom_data, $design_file_path, $reference_file_path
                        ]
                    );
                }

                // Clean up temp files
                if (!empty($item['design_tmp_path']) && file_exists($item['design_tmp_path'])) {
                    @unlink($item['design_tmp_path']);
                }
                if (!empty($item['reference_tmp_path']) && file_exists($item['reference_tmp_path'])) {
                    @unlink($item['reference_tmp_path']);
                }
                unset($_SESSION['cart'][$item_key]);

                // Notifications
                create_notification($customer_id, 'Customer', "Order #{$order_id} placed successfully!", 'Order', true, false, $order_id);
                $staff_users = db_query("SELECT user_id FROM users WHERE role='Staff' AND status='Activated'");
                foreach ($staff_users as $staff) {
                    create_notification($staff['user_id'], 'Staff', "New Order #{$order_id} from {$customer['first_name']}!", 'Order', false, false, $order_id);
                }

                $_SESSION['success'] = "Your order #{$order_id} has been placed successfully! Our team will review it shortly. You can track the status here.";
                redirect("order_details.php?id=$order_id");
            } else {
                $order_error = 'Failed to place order. Please try again.';
            }
        }
    }
}

// ── Build design preview (base64 for inline display) ───────────
$design_preview_src = null;
if (!empty($item['design_tmp_path']) && file_exists($item['design_tmp_path']) && !empty($item['design_mime'])) {
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
</style>

<div class="min-h-screen py-8">
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
                            <div style="font-size:0.82rem; font-weight:700; color:#0c4a6e; margin-bottom:3px;">Pricing will be determined by staff</div>
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
                        Payment options (Full, 50% Downpayment, or Upon Pick Up) will become available once staff reviews your order and sets the price.
                        You will receive a notification when your order is ready for payment.
                    </p>
                </div>

                <!-- 4. Order Notes -->
                <div class="card compact-card">
                    <h2 style="font-size:1rem; font-weight:700; margin-bottom:0.75rem; border-bottom:1px solid #f3f4f6; padding-bottom:0.5rem; display:flex; align-items:center; gap:8px;">
                        <span>📝</span> Order Notes
                    </h2>
                    <textarea name="notes" class="input-field" style="width:100%; min-height:80px; resize:vertical; font-size:0.85rem; padding:10px;" placeholder="Add special instructions..."><?php echo htmlspecialchars($item['customization']['notes'] ?? ''); ?></textarea>
                </div>

                <!-- 5. Final Actions -->
                <div style="margin-top:0.5rem; text-align:center;">
                    <button type="submit" name="confirm_order" class="btn-primary" style="width:100%; padding:14px; font-weight:700; font-size:1.1rem; border-radius:12px; box-shadow:0 4px 6px -1px rgba(79, 70, 229, 0.2);">Confirm & Place Order</button>
                    
                    <a href="?item=<?php echo urlencode($item_key); ?>&cancel=1" 
                       onclick="return confirm('Cancel this order?');"
                       style="display:inline-block; margin-top:1.25rem; font-size:0.875rem; color:#6b7280; text-decoration:none; font-weight:600; padding:8px 16px; transition:all 0.2s;"
                       onmouseover="this.style.color='#ef4444'" 
                       onmouseout="this.style.color='#6b7280'">
                        ✕ Cancel Order
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

