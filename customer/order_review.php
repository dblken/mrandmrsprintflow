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

            // [MODIFIED] Payment Policy Logic
            $downpayment_amount = 0;
            $payment_type = 'full_payment';
            $payment_status = 'Unpaid'; // Default

            if ($customer_type === 'new') {
                // New customers must pay 100% before confirmation
                $downpayment_amount = $subtotal;
                $payment_type = 'full_payment';
            } else {
                // Regular customers can choose
                $payment_choice = $_POST['payment_choice'] ?? 'half';
                if ($payment_choice === 'full') {
                    $downpayment_amount = $subtotal;
                    $payment_type = 'full_payment';
                } elseif ($payment_choice === 'half') {
                    $downpayment_amount = $subtotal * 0.5;
                    $payment_type = '50_percent';
                } elseif ($payment_choice === 'pickup') {
                    $downpayment_amount = 0;
                    $payment_type = 'upon_pickup';
                    $payment_status = 'Unpaid'; // Don't require immediate payment for pickup
                } else {
                    $downpayment_amount = $subtotal * 0.5; // Default to half
                    $payment_type = '50_percent';
                }
            }

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

<div class="min-h-screen py-8">
<div class="container mx-auto px-4" style="max-width:960px;">

    <!-- Header -->
    <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.75rem;">
        <a href="products.php" style="color:#6b7280; text-decoration:none; font-size:0.9rem;">← Back to Products</a>
        <h1 class="ct-page-title" style="margin:0; flex:1; text-align:center;">Review Your Order</h1>
        <span style="width:120px;"></span>
    </div>

    <?php if ($order_error): ?>
        <div class="alert-error" style="margin-bottom:1.5rem;"><?php echo htmlspecialchars($order_error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrf_field(); ?>

        <div style="display:grid; grid-template-columns: 1fr 340px; gap:2rem; align-items:start;">

            <!-- Left: Unified Order Detail Container -->
            <div style="flex:1; min-width:0;">
                <?php render_order_item_neubrutalism($item, true); ?>
                
                <!-- Contact Info Section -->
                <div class="card" style="border: 2px solid #000; box-shadow: 8px 8px 0px #000; padding: 2.5rem; background: #fff;">
                    <h3 style="font-size:1.1rem; font-weight:900; color:black; margin-bottom:1.5rem; display:flex; align-items:center; gap:12px; text-transform:uppercase; letter-spacing:0.04em;">
                        <span style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; background:black; color:white; border-radius:6px; font-size:1rem;">📋</span>
                        Contact Information
                    </h3>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:2.5rem;">
                        <div>
                            <div style="font-size:0.75rem; color:#6b7280; text-transform:uppercase; letter-spacing:.1em; font-weight:800; margin-bottom:8px;">Full Name</div>
                            <div style="font-weight:900; color:black; font-size:1.2rem; display:flex; align-items:center; gap:12px;">
                                <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size:0.75rem; color:#6b7280; text-transform:uppercase; letter-spacing:.1em; font-weight:800; margin-bottom:8px;">Phone Number</div>
                            <div style="font-weight:900; color:black; font-size:1.2rem;"><?php echo htmlspecialchars($customer['contact_number'] ?? '—'); ?></div>
                        </div>
                        <div>
                            <div style="font-size:0.75rem; color:#6b7280; text-transform:uppercase; letter-spacing:.1em; font-weight:800; margin-bottom:8px;">Email Address</div>
                            <div style="font-weight:900; color:black; font-size:1.2rem;"><?php echo htmlspecialchars($customer['email']); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Summary & actions -->
            <div style="display:flex; flex-direction:column; gap:1.5rem; position:sticky; top:100px;">
                <div class="card" style="padding:2rem; border: 2px solid #000; box-shadow: 8px 8px 0px #000;">
                    <h2 style="font-size:1rem; font-weight:900; color:black; margin:0 0 1.5rem 0; text-transform:uppercase; letter-spacing:0.06em; border-bottom:2px solid #000; padding-bottom:1rem;">Order Summary</h2>

                    <div style="display:flex; justify-content:space-between; font-size:1rem; margin-bottom:1rem; color:#000; font-weight:700;">
                        <span>Subtotal</span>
                        <span><?php echo format_currency($subtotal); ?></span>
                    </div>

                    <div style="border-top:3px solid black; padding-top:1.25rem; margin-top:1rem; margin-bottom:2rem; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-weight:900; font-size:1rem; text-transform:uppercase;">Grand Total</span>
                        <span style="font-size:1.8rem; font-weight:900; color:black; letter-spacing:-0.03em;"><?php echo format_currency($subtotal); ?></span>
                    </div>

                    <?php if ($customer_type === 'new'): ?>
                        <div style="background:#fff1f2; border:2px solid #b91c1c; border-radius:10px; padding:16px; font-size:0.85rem; color:#b91c1c; margin-bottom:1.5rem;">
                            ⚠️ <strong>New Customer Policy</strong><br>
                            To process your order, <strong>full payment (PHP <?php echo number_format($subtotal, 2); ?>)</strong> is required upfront.
                        </div>
                    <?php else: ?>
                        <div style="margin-bottom: 2rem; display: flex; flex-direction: column; gap: 1rem;">
                            <span style="font-weight:900; font-size:0.75rem; text-transform:uppercase; color:#6b7280; letter-spacing:0.05em;">Payment Strategy</span>
                            
                            <label style="display: flex; align-items: center; gap: 14px; padding: 14px; border: 2px solid #000; border-radius: 10px; cursor: pointer; transition: all 0.2s; background: white;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=this.querySelector('input').checked ? '#000' : 'white'">
                                <input type="radio" name="payment_choice" value="full" style="accent-color: #000;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 900; font-size: 0.95rem;">Full Payment</div>
                                </div>
                            </label>

                            <label style="display: flex; align-items: center; gap: 14px; padding: 14px; border: 2px solid #000; border-radius: 10px; cursor: pointer; transition: all 0.2s; background: #000; color: #fff;">
                                <input type="radio" name="payment_choice" value="half" checked style="accent-color: #fff;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 900; font-size: 0.95rem;">Half Payment (50%)</div>
                                </div>
                            </label>

                            <label style="display: flex; align-items: center; gap: 14px; padding: 14px; border: 2px solid #000; border-radius: 10px; cursor: pointer; transition: all 0.2s; background: white;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=this.querySelector('input').checked ? '#000' : 'white'">
                                <input type="radio" name="payment_choice" value="pickup" style="accent-color: #000;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 900; font-size: 0.95rem;">Upon Pick Up</div>
                                </div>
                            </label>
                        </div>
                    <?php endif; ?>

                    <!-- Place Order -->
                    <button type="submit" name="confirm_order"
                            style="width:100%; padding:18px; background:#000; color:#fff; font-size:1.1rem; font-weight:900; border:none; border-radius:12px; cursor:pointer; letter-spacing:.04em; transition:transform 0.1s; text-transform:uppercase;"
                            onmousedown="this.style.transform='scale(0.98)'" onmouseup="this.style.transform='scale(1)'">
                        Place Your Order Now
                    </button>

                    <!-- Cancel -->
                    <a href="?item=<?php echo urlencode($item_key); ?>&cancel=1"
                       onclick="return confirm('Cancel this order?');"
                       style="display:block; text-align:center; margin-top:1.25rem; font-size:0.9rem; color:#ef4444; text-decoration:none; font-weight:800; padding:12px; border:2px solid #fecaca; border-radius:10px; transition:background .2s;"
                       onmouseover="this.style.background='#fff1f2'" onmouseout="this.style.background='transparent'">
                        ✕ Cancel Order
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

