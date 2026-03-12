<?php
/**
 * Checkout Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

$all_cart_items = $_SESSION['cart'] ?? [];
$cart_items = array_filter($all_cart_items, function($item) {
    return ($item['selected'] ?? true);
});

if (empty($cart_items)) {
    redirect('cart.php');
}

$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}

$customer_id = get_user_id();
$customer = db_query("SELECT * FROM customers WHERE customer_id = ?", 'i', [$customer_id])[0];

// Fetch cancel count for downpayment check (needed on both GET and POST)
$cancel_count = get_customer_cancel_count($customer_id);
$is_restricted = is_customer_restricted($customer_id);
$customer_type = $customer['customer_type'] ?? 'new';

// Handle Order Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    global $conn; // needed for send_long_data BLOB insertion
    
    // Check restriction AGAIN at submission
    $cancel_count = get_customer_cancel_count($customer_id);
    $is_restricted = is_customer_restricted($customer_id);
    
    if ($is_restricted) {
        $error = "🚫 Your account is restricted from placing new orders.";
    } elseif (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        // Calculate mandatory downpayment and payment type
        $downpayment_amount = 0;
        $payment_type = 'full_payment';
        $payment_status = 'Unpaid'; // Default

        if ($customer_type === 'new') {
            $downpayment_amount = $total;
            $payment_type = 'full_payment';
        } else {
            // Regular customers can choose
            $payment_choice = $_POST['payment_choice'] ?? 'half';
            if ($payment_choice === 'full') {
                $downpayment_amount = $total;
                $payment_type = 'full_payment';
            } elseif ($payment_choice === 'half') {
                $downpayment_amount = $total * 0.5;
                $payment_type = '50_percent';
            } elseif ($payment_choice === 'pickup') {
                $downpayment_amount = 0;
                $payment_type = 'upon_pickup';
                $payment_status = 'Unpaid'; // Don't require immediate payment for pickup
            } else {
                $downpayment_amount = $total * 0.5; // Default to half
                $payment_type = '50_percent';
            }
        }

        // Start Transaction (if supported, otherwise manual checks)
        // 1. Create Order
        // Extract branch_id from the first item in the cart or from the POST selector
        $branch_id = (int)($_POST['order_branch_id'] ?? 1);
        
        if (!empty($cart_items)) {
            foreach ($cart_items as $item) {
                if (!empty($item['branch_id'])) {
                    $branch_id = (int)$item['branch_id'];
                    break;
                }
                if (isset($item['customization']['Branch_ID'])) {
                    $branch_id = (int)$item['customization']['Branch_ID'];
                    break;
                }
            }
        }

        $notes = $_POST['notes'] ?? null;
        $order_sql = "INSERT INTO orders (customer_id, branch_id, order_date, total_amount, downpayment_amount, status, payment_status, payment_type, notes) 
                      VALUES (?, ?, NOW(), ?, ?, 'Pending Review', ?, ?, ?)";
        
        $payment_method = $_POST['payment_method'] ?? 'pay_later';
        
        // Removed payment_method from query as column doesn't exist
        $order_id = db_execute($order_sql, 'iiddsss', [$customer_id, $branch_id, $total, $downpayment_amount, $payment_status, $payment_type, $notes]);
        
        if ($order_id) {
            // 2. Insert Order Items (design stored as LONGBLOB, never on disk)
            $inserted_order_item_ids = [];
            foreach ($cart_items as $pid => $item) {
                $custom_data    = isset($item['customization']) ? json_encode($item['customization']) : null;
                $design_binary  = null;
                $design_mime    = $item['design_mime']   ?? null;
                $design_name    = $item['design_name']   ?? null;

                // Read binary from temp file (session only stores path, not raw bytes)
                if (!empty($item['design_tmp_path']) && file_exists($item['design_tmp_path'])) {
                    $design_binary = file_get_contents($item['design_tmp_path']);
                }

                if ($design_binary) {
                    // INSERT with BLOB using send_long_data
                    $item_stmt = $conn->prepare(
                        "INSERT INTO order_items (order_id, product_id, quantity, unit_price, customization_data, design_image, design_image_mime, design_image_name)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    if ($item_stmt) {
                        $null = NULL;
                        $item_stmt->bind_param('iiidsbss',
                            $order_id,
                            $item['product_id'],
                            $item['quantity'],
                            $item['price'],
                            $custom_data,
                            $null,          // placeholder for BLOB
                            $design_mime,
                            $design_name
                        );
                        $item_stmt->send_long_data(5, $design_binary);
                        $item_stmt->execute();
                        $inserted_order_item_ids[$pid] = $conn->insert_id;
                        $item_stmt->close();
                    }
                } else {
                    // No design uploaded — insert without BLOB
                    $inserted_order_item_ids[$pid] = db_execute(
                        "INSERT INTO order_items (order_id, product_id, quantity, unit_price, customization_data)
                         VALUES (?, ?, ?, ?, ?)",
                        'iiids',
                        [$order_id, $item['product_id'], $item['quantity'], $item['price'], $custom_data]
                    );
                }
            }
            
            // 3. Clean up temp design files and clear Cart
            foreach ($cart_items as $ci) {
                if (!empty($ci['design_tmp_path']) && file_exists($ci['design_tmp_path'])) {
                    @unlink($ci['design_tmp_path']);
                }
            }
            
            // 4. Auto-create Job Orders for Production Workflow
            foreach ($cart_items as $pid => $item) {
                // Determine service type from item category or name
                $service_type = $item['category'] ?? 'General';
                $cat_lower = strtolower($service_type . ' ' . ($item['name'] ?? ''));
                if (strpos($cat_lower, 'tarpaulin') !== false) $service_type = 'Tarpaulin Printing';
                elseif (strpos($cat_lower, 'reflectorized') !== false) $service_type = 'Reflectorized Signage';
                elseif (strpos($cat_lower, 'sintraboard') !== false || strpos($cat_lower, 'standee') !== false) $service_type = 'Sintraboard Standees';
                elseif (strpos($cat_lower, 't-shirt') !== false || strpos($cat_lower, 'shirt') !== false) $service_type = 'T-shirt Printing';
                elseif (strpos($cat_lower, 'sticker') !== false || strpos($cat_lower, 'decal') !== false) $service_type = 'Decals/Stickers';
                elseif (strpos($cat_lower, 'souvenir') !== false) $service_type = 'Souvenirs';
                
                // Parse dimensions from customization data
                $custom = $item['customization'] ?? [];
                $dimensions = $custom['dimensions'] ?? '';
                $width_ft = 0; $height_ft = 0;
                if ($dimensions && strpos($dimensions, 'x') !== false) {
                    $parts = array_map('trim', explode('x', strtolower($dimensions)));
                    $width_ft  = (float)($parts[0] ?? 0);
                    $height_ft = (float)($parts[1] ?? 0);
                } elseif ($dimensions && strpos($dimensions, '×') !== false) {
                    $parts = array_map('trim', explode('×', $dimensions));
                    $width_ft  = (float)($parts[0] ?? 0);
                    $height_ft = (float)($parts[1] ?? 0);
                }
                
                $job_title = $item['name'] ?? $service_type;
                $job_qty   = (int)($item['quantity'] ?? 1);
                $cust_type = ($customer_type === 'regular') ? 'REGULAR' : 'NEW';
                $oi_id     = $inserted_order_item_ids[$pid] ?? null;
                
                db_execute(
                    "INSERT INTO job_orders (job_title, service_type, customer_id, order_item_id, width_ft, height_ft, quantity, status, customer_type, estimated_total, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, NOW())",
                    'ssiiddisd',
                    [$job_title, $service_type, $customer_id, $oi_id, $width_ft, $height_ft, $job_qty, $cust_type, $item['price'] * $job_qty]
                );
            }
            
            unset($_SESSION['cart']);
            
            // 5. Notification
            create_notification($customer_id, 'Customer', "Order #{$order_id} placed successfully!", 'Order', true, false, $order_id);
            
            // Notify Staff
            $staff_users = db_query("SELECT user_id FROM users WHERE role = 'Staff' AND status = 'Activated'");
            foreach ($staff_users as $staff) {
                create_notification($staff['user_id'], 'Staff', "New Order #{$order_id} received from {$customer['first_name']}!", 'Order', false, false, $order_id);
            }
            
            $_SESSION['success'] = "Your order #{$order_id} has been placed successfully! Our team will review it shortly. You can track the status here.";
            
            // Redirect to the new order's details page
            redirect("order_details.php?id=$order_id");
        } else {
            $error = "Failed to place order. Please try again.";
        }
    } else {
        $error = "Invalid request.";
    }
}

$page_title = 'Checkout - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width:1100px;">
        <h1 class="ct-page-title">Checkout</h1>

        <form method="POST" style="display:grid; grid-template-columns: 1fr 340px; gap:2rem;">
            <?php echo csrf_field(); ?>
            
            <div style="display:flex; flex-direction:column; gap:1.5rem;">
                <?php if (isset($error)): ?>
                    <div class="alert-error"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Customer Info -->
                <div class="card">
                    <h2 style="font-size:1.1rem; font-weight:600; margin-bottom:1rem; border-bottom:1px solid #f3f4f6; padding-bottom:0.5rem;">Contact Information</h2>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Name
                                <?php if ($customer_type === 'regular'): ?>
                                    <span style="font-size:0.7rem; color:#15803d; font-weight:700; background:#dcfce7; padding:2px 6px; border-radius:4px; margin-left:6px;">Regular Customer – Flexible Payment Available</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" class="input-field" value="<?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>" disabled style="background:#f9fafb;">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="text" class="input-field" value="<?php echo htmlspecialchars($customer['email']); ?>" disabled style="background:#f9fafb;">
                        </div>
                        <div style="grid-column:span 2;">
                            <label class="block text-sm font-medium text-gray-700">Phone</label>
                            <input type="text" class="input-field" value="<?php echo htmlspecialchars($customer['contact_number']); ?>" disabled style="background:#f9fafb;">
                        </div>
                    </div>
                    <p style="font-size:0.8rem; color:#6b7280; margin-top:10px;">* Please update your profile if this information is incorrect.</p>
                </div>

                <!-- Branch Selection -->
                <div class="card">
                    <h2 style="font-size:1.1rem; font-weight:600; margin-bottom:1rem; border-bottom:1px solid #f3f4f6; padding-bottom:0.5rem;">Select Branch</h2>
                    <p style="font-size:0.85rem; color:#6b7280; margin-bottom:0.75rem;">Which branch will process your order?</p>
                    <?php 
                    $branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'"); 
                    // Try to pre-select based on cart
                    $preset_branch = 1;
                    if (!empty($cart_items)) {
                        foreach($cart_items as $ci) {
                            if (!empty($ci['branch_id'])) { $preset_branch = $ci['branch_id']; break; }
                        }
                    }
                    ?>
                    <select name="order_branch_id" class="input-field" required>
                        <?php foreach($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo ($b['id'] == $preset_branch) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Payment Method -->
                <div class="card">
                    <h2 style="font-size:1.1rem; font-weight:600; margin-bottom:1rem; border-bottom:1px solid #f3f4f6; padding-bottom:0.5rem;">Payment Policy</h2>
                    
                    <?php if ($customer_type === 'new'): ?>
                        <div style="background:#fff1f2; border:1px solid #fecaca; border-radius:8px; padding:12px 14px; font-size:0.85rem; color:#b91c1c;">
                            ⚠️ <strong>New Customer Policy</strong><br>
                            To process your order, <strong>full payment (PHP <?php echo number_format($total, 2); ?>)</strong> is required upfront. You'll become a 'Regular' customer after 3 successful orders!
                        </div>
                    <?php else: ?>
                        <div style="background:#f0fdf4; border:1px solid #dcfce7; border-radius:8px; padding:10px 14px; font-size:0.85rem; color:#15803d; margin-bottom:1.25rem;">
                            ✅ <strong>Regular Customer Benefit</strong><br>
                            Choose your preferred payment option:
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <label style="display: flex; align-items: center; gap: 10px; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                                <input type="radio" name="payment_choice" value="full" style="width: 18px; height: 18px; cursor: pointer;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 700; font-size: 0.9rem; color: #1f2937;">Full Payment (100%)</div>
                                    <div style="font-size: 0.75rem; color: #6b7280;">Pay <?php echo format_currency($total); ?> now</div>
                                </div>
                            </label>

                            <label style="display: flex; align-items: center; gap: 10px; padding: 10px; border: 2px solid #4F46E5; background: #f5f3ff; border-radius: 8px; cursor: pointer;">
                                <input type="radio" name="payment_choice" value="half" checked style="width: 18px; height: 18px; cursor: pointer;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 700; font-size: 0.9rem; color: #4F46E5;">Half Payment (50%)</div>
                                    <div style="font-size: 0.75rem; color: #6b7280;">Pay <?php echo format_currency($total * 0.5); ?> now, balance upon pickup</div>
                                </div>
                            </label>

                            <label style="display: flex; align-items: center; gap: 10px; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                                <input type="radio" name="payment_choice" value="pickup" style="width: 18px; height: 18px; cursor: pointer;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 700; font-size: 0.9rem; color: #1f2937;">Upon Pick Up (0%)</div>
                                    <div style="font-size: 0.75rem; color: #6b7280;">Pay full amount upon picking up your order</div>
                                </div>
                            </label>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Global Order Notes -->
                <div class="card">
                    <h2 style="font-size:1.1rem; font-weight:600; margin-bottom:1rem; border-bottom:1px solid #f3f4f6; padding-bottom:0.5rem;">Order Notes (Optional)</h2>
                    <p style="font-size:0.85rem; color:#6b7280; margin-bottom:0.75rem;">Add any special instructions or general notes for your entire order here.</p>
                    <textarea name="notes" class="input-field" style="width:100%; min-height:100px; resize:vertical; font-size:0.9rem;" placeholder="e.g. Please wrap carefully, I will pick up around 5pm..."></textarea>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="card" style="height:fit-content;">
                <h2 style="font-size:1.1rem; font-weight:600; margin-bottom:1rem;">Order Summary</h2>
                <div style="margin-bottom:1.5rem; display:flex; flex-direction:column; gap:1rem;">
                    <?php foreach ($cart_items as $item):
                        $item_total     = $item['price'] * $item['quantity'];
                        $custom         = $item['customization'] ?? [];
                        $design_preview = null;
                        if (!empty($item['design_tmp_path']) && file_exists($item['design_tmp_path']) && !empty($item['design_mime'])) {
                            $bin = file_get_contents($item['design_tmp_path']);
                            if ($bin) $design_preview = 'data:' . $item['design_mime'] . ';base64,' . base64_encode($bin);
                        }
                    ?>
                        <div style="border:1px solid #f3f4f6; border-radius:10px; padding:0.85rem; display:flex; gap:0.85rem; align-items:flex-start;">
                            <!-- Thumbnail -->
                            <div style="flex-shrink:0; width:58px; height:58px; border-radius:8px; overflow:hidden; background:#f3f4f6; display:flex; align-items:center; justify-content:center; border:1px solid #e5e7eb;">
                                <?php if ($design_preview): ?>
                                    <img src="<?php echo $design_preview; ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                <?php else: ?>
                                    <span style="font-size:1.8rem;">📦</span>
                                <?php endif; ?>
                            </div>
                            <!-- Info -->
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:600; font-size:0.9rem; color:#1f2937; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($item['name']); ?></div>
                                <?php if (!empty($custom)): ?>
                                <div style="font-size:0.75rem; color:#6b7280; margin-top:3px;">
                                    <?php foreach ($custom as $k => $v):
                                        if ($v === '') continue;
                                    ?>
                                        <span style="background:#f3f4f6; padding:1px 6px; border-radius:4px; margin-right:4px;"><?php echo htmlspecialchars(ucfirst(str_replace('_',' ',$k))); ?>: <strong><?php echo htmlspecialchars($v); ?></strong></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <div style="font-size:0.78rem; color:#9ca3af; margin-top:4px;">Qty: <?php echo (int)$item['quantity']; ?> × <?php echo format_currency($item['price']); ?></div>
                            </div>
                            <!-- Price -->
                            <div style="font-weight:700; color:#4F46E5; white-space:nowrap;"><?php echo format_currency($item_total); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="border-top:1px solid #f3f4f6; padding-top:1rem; margin-bottom:1.5rem; display:flex; flex-direction:column; gap:0.5rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-weight:600; color:#6b7280;">Subtotal</span>
                        <span style="font-weight:600;"><?php echo format_currency($total); ?></span>
                    </div>
                    <?php if ($customer_type === 'new'): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-weight:600; color:#b91c1c;">Mandatory Downpayment</span>
                            <span style="font-weight:700; color:#b91c1c;"><?php echo format_currency($total); ?></span>
                        </div>
                    <?php else: ?>
                        <p style="font-size: 0.75rem; color: #6b7280; border-top: 1px dashed #e5e7eb; padding-top: 0.5rem; margin-top: 0.5rem;">
                            Regular customers can choose their downpayment above.
                        </p>
                    <?php endif; ?>
                    
                    <div style="display:flex; justify-content:space-between; align-items:center; border-top:2px solid #e5e7eb; padding-top:0.5rem; margin-top:0.5rem;">
                        <span style="font-weight:600;">Total</span>
                        <span style="font-size:1.5rem; font-weight:700; color:#4F46E5;"><?php echo format_currency($total); ?></span>
                    </div>
                </div>
                
                <button type="submit" name="place_order" class="btn-primary" style="width:100%;">Place Order</button>
                <a href="cart.php" style="display:block; text-align:center; font-size:0.875rem; color:#6b7280; margin-top:1rem; text-decoration:none;">Returns to Cart</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

