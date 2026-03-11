<?php
/**
 * Order Review & Confirm Page
 * PrintFlow — Shown when customer clicks "Buy Now"
 * Displays full order summary with design image preview,
 * customization details, price, and Cancel / Confirm buttons.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

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
            $order_sql = "INSERT INTO orders (customer_id, order_date, total_amount, downpayment_amount, status, payment_status, payment_type, notes)
                          VALUES (?, NOW(), ?, ?, 'Pending Review', ?, ?, ?)";
            $order_id  = db_execute($order_sql, 'iddsss', [$customer_id, $subtotal, $downpayment_amount, $payment_status, $payment_type, $notes]);

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

$page_title      = 'Review Your Order — PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen py-8">
<div class="container mx-auto px-4" style="max-width:860px;">

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

        <div style="display:grid; grid-template-columns: 1fr 320px; gap:1.5rem; align-items:start;">

            <!-- Left: Unified Order Detail Container -->
            <div class="card" style="display:flex; flex-direction:column; padding:0; overflow:hidden;">
                
                <!-- Section 1: Product Essential Info -->
                <div style="padding:2rem; border-bottom:1px solid #f3f4f6; display:flex; gap:1.75rem; align-items:flex-start;">
                    <!-- Design image preview or product icon -->
                    <div style="flex-shrink:0; width:160px; height:160px; border-radius:12px; overflow:hidden; border:2px solid black; background:#f9fafb; display:flex; align-items:center; justify-content:center; box-shadow: 4px 4px 0px rgba(0,0,0,0.1);">
                        <?php if ($design_preview_src): ?>
                            <img src="<?php echo $design_preview_src; ?>"
                                 alt="Your uploaded design"
                                 style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <span style="font-size:4rem;">📦</span>
                        <?php endif; ?>
                    </div>

                    <div style="flex:1; min-width:0; padding-top:4px;">
                        <div style="font-size:1.4rem; font-weight:800; color:black; margin-bottom:6px; letter-spacing:-0.01em;">
                            <?php echo htmlspecialchars($item['name']); ?>
                        </div>
                        <div style="font-size:0.85rem; color:#6b7280; margin-bottom:1.5rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Product Order</div>

                        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:1.5rem;">
                            <div>
                                <div style="font-size:0.7rem; color:#9ca3af; text-transform:uppercase; letter-spacing:.06em; font-weight:700; margin-bottom:4px;">Unit Price</div>
                                <div style="font-weight:800; color:black; font-size:1.15rem;"><?php echo format_currency($item['price']); ?></div>
                            </div>
                            <div>
                                <div style="font-size:0.7rem; color:#9ca3af; text-transform:uppercase; letter-spacing:.06em; font-weight:700; margin-bottom:4px;">Quantity</div>
                                <div style="font-weight:800; color:black; font-size:1.15rem;"><?php echo (int)$item['quantity']; ?></div>
                            </div>
                            <div>
                                <div style="font-size:0.7rem; color:#9ca3af; text-transform:uppercase; letter-spacing:.06em; font-weight:700; margin-bottom:4px;">Subtotal</div>
                                <div style="font-weight:800; color:black; font-size:1.15rem;"><?php echo format_currency($subtotal); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Customization Details -->
                <div style="padding:2rem; border-bottom:1px solid #f3f4f6; background:#fcfcfc;">
                    <h3 style="font-size:1rem; font-weight:800; color:black; margin-bottom:1.5rem; display:flex; align-items:center; gap:10px; text-transform:uppercase; letter-spacing:0.02em;">
                        <span style="display:flex; align-items:center; justify-content:center; width:28px; height:28px; background:black; color:white; border-radius:4px; font-size:0.9rem;">🎨</span>
                        Customization Details
                    </h3>
                    
                    <?php
                    $custom = $item['customization'] ?? [];
                    $label_skip = ['design_upload', 'reference_upload', 'notes']; 
                    $has_custom = !empty(array_filter($custom, fn($val, $key) => !in_array($key, $label_skip) && !empty($val), ARRAY_FILTER_USE_BOTH));
                    ?>

                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:1.5rem; margin-bottom:1.5rem;">
                        <?php if ($has_custom): ?>
                            <?php 
                            $allowed_fields = [
                                'T-Shirts' => [
                                    'shirt_source', 'shirt_type', 'shop_shirt_color', 'shop_shirt_size', 
                                    'client_shirt_color', 'client_shirt_material', 'material_disclaimer',
                                    'printing_method', 'shirt_placement', 'print_size', 
                                    'tshirt_custom_width', 'tshirt_custom_height', 'tshirt_design_description'
                                ],
                                'Tarpaulin' => [
                                    'tarp_size_option', 'tarp_preset_size', 'tarp_width', 'tarp_height', 'tarp_unit',
                                    'tarp_material', 'tarp_finish', 'tarp_edges', 'tarp_grommet_option', 
                                    'tarp_grommet_instructions', 'tarp_design_service', 'tarp_design_description'
                                ],
                                'Decals & Stickers' => [
                                    'vehicle_brand', 'vehicle_model', 'vehicle_year', 'placement', 
                                    'size_option', 'custom_width', 'custom_height', 'material_type', 'design_description'
                                ],
                                'Stickers' => [
                                    'vehicle_brand', 'vehicle_model', 'vehicle_year', 'placement', 
                                    'size_option', 'custom_width', 'custom_height', 'material_type', 'design_description'
                                ],
                                'Sintraboard & Standees' => [
                                    'width', 'height', 'thickness', 'stand_type', 'lamination', 'cut_type', 'design_description', 'flat_type', 'Sintra_Type'
                                ],
                                'Glass & Wall Sticker Printing' => [
                                    'surface_type', 'sticker_type', 'coverage_type', 'coverage_detail', 'width', 'height', 'unit', 'total_glass_width', 
                                    'total_glass_height', 'panel_count', 'design_service', 'design_concept', 'installation_option', 
                                    'installation_address', 'floor_level', 'location_type', 'print_coverage'
                                ],
                                'Reflectorized Signage' => [
                                    'product_type', 'subdivision_name', 'temp_plate_number', 'temp_plate_text', 'mv_file_number', 'dealer_name',
                                    'gate_pass_subdivision', 'gate_pass_number', 'gate_pass_plate', 'gate_pass_year', 'gate_pass_vehicle_type',
                                    'serial_number', 'dimensions', 'unit', 'shape', 'material_type', 'bg_color', 'text_color',
                                    'reflective_color', 'text_content', 'arrow_direction', 'with_numbering', 'other_instructions'
                                ],
                                'Merchandise' => [
                                    'design_description'
                                ]
                            ];

                            $cat_name = $item['category'] ?? '';
                            $cat_lower = strtolower($cat_name);
                            if (strpos($cat_lower, 'glass') !== false) $cat_name = 'Glass & Wall Sticker Printing';
                            elseif (strpos($cat_lower, 'shirt') !== false) $cat_name = 'T-Shirts';
                            elseif (strpos($cat_lower, 'tarpaulin') !== false) $cat_name = 'Tarpaulin';
                            elseif (strpos($cat_lower, 'decal') !== false || strpos($cat_lower, 'sticker') !== false) $cat_name = 'Decals & Stickers';
                            elseif (strpos($cat_lower, 'sintra') !== false) $cat_name = 'Sintraboard & Standees';
                            elseif (strpos($cat_lower, 'reflectorized') !== false) $cat_name = 'Reflectorized Signage';
                            
                            $product_type = $custom['product_type'] ?? '';
                            $is_temp_plate = ($cat_name === 'Reflectorized Signage' && $product_type === 'Plate Number / Temporary Plate');
                            $is_gate_pass = ($cat_name === 'Reflectorized Signage' && strpos($product_type, 'Gate Pass') !== false);
                            $is_street_signage = ($cat_name === 'Reflectorized Signage' && strpos($product_type, 'Street') !== false);

                            $current_allowed = $allowed_fields[$cat_name] ?? [];

                            // Pre-process Reflectorized fields to be more readable
                            $reflectorized_map = [
                                'product_type' => 'Product Type',
                                'subdivision_name' => 'Subdivision Name',
                                'temp_plate_number' => 'Plate Number',
                                'temp_plate_text' => 'Label',
                                'mv_file_number' => 'MV File No.',
                                'dealer_name' => 'Dealer Name',
                                'gate_pass_subdivision' => 'Company/Subdivision',
                                'gate_pass_number' => 'Sticker No.',
                                'gate_pass_plate' => 'Vehicle Plate',
                                'gate_pass_year' => 'Validity',
                                'dimensions' => 'Dimensions',
                                'unit' => 'Unit',
                                'shape' => 'Shape',
                                'material_type' => 'Material',
                                'bg_color' => 'Background Color',
                                'text_color' => 'Text Color',
                                'reflective_color' => 'Reflective Color',
                                'text_content' => 'Text Content',
                                'arrow_direction' => 'Arrow Direction',
                                'with_numbering' => 'Serial Numbering',
                                'other_instructions' => 'Special Instructions',
                                'quantity_gatepass' => 'Quantity'
                            ];

                            $gate_pass_exclusions = ['bg_color', 'text_color', 'reflective_color', 'text_content', 'arrow_direction', 'with_numbering', 'install_service', 'need_proof', 'quantity', 'temp_plate_text', 'product_type', 'dimensions', 'unit', 'shape', 'material_type', 'service_type'];
                            $temp_plate_exclusions = ['unit', 'arrow_direction', 'bg_color', 'text_color', 'quantity', 'with_border', 'rounded_corners', 'with_numbering', 'install_service', 'need_proof', 'material_type', 'shape', 'dimensions', 'product_type', 'service_type'];
                            $street_signage_exclusions = ['bg_color', 'text_color', 'reflective_color', 'with_numbering', 'starting_number', 'mounting_option', 'temp_plate_text', 'product_type', 'dimensions', 'unit', 'shape', 'material_type', 'service_type'];


                            foreach ($custom as $key => $value): 
                                if (empty($value) || in_array($key, $label_skip)) continue;
                                if ($cat_name !== 'Reflectorized Signage' && !empty($current_allowed) && !in_array($key, $current_allowed)) continue;
                                
                                // Conditional exclusions for Temporary Plates
                                if ($is_temp_plate && (in_array($key, $temp_plate_exclusions) || strtolower($value) === 'inches' || $key === 'reflective_color')) continue;
                                if ($is_temp_plate && $key === 'text_content' && empty($value)) continue;

                                // Conditional exclusions for Gate Pass
                                if ($is_gate_pass && in_array($key, $gate_pass_exclusions)) continue;

                                // Conditional exclusions for Street Signage
                                if ($is_street_signage && in_array($key, $street_signage_exclusions)) continue;

                                if ($key === 'with_border' && $value === 'No') continue;
                                if ($key === 'rounded_corners' && $value === 'No') continue;
                                if ($key === 'with_numbering' && $value === 'No') continue;
                                if ($key === 'install_service' && $value === 'No') continue;
                                if ($key === 'need_proof' && $value === 'No') continue;

                                $clean_key = preg_replace('/^(tarp|shirt|shop|client|vehicle|tshirt)_/', '', $key);
                                $label = $reflectorized_map[$key] ?? ucwords(str_replace(['_', '-'], ' ', $clean_key));
                                if ($clean_key === 'dob') $label = 'Date of Birth';
                                if ($clean_key === 'oem') $label = 'OEM Size';
                                if ($key === 'temp_plate_number') $label = 'Plate Number';
                                if ($key === 'temp_plate_text') $label = 'Label';
                            ?>
                                <div style="border-left:3px solid black; padding-left:12px;">
                                    <div style="font-size:0.7rem; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;"><?php echo $label; ?></div>
                                    <div style="font-size:1rem; color:black; font-weight:600; word-break:break-word;"><?php echo htmlspecialchars($value); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="color:#9ca3af; font-style:italic; font-size:0.9rem;">No specific customizations provided.</div>
                        <?php endif; ?>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem; padding-top:1.5rem; border-top:1px dashed #e5e7eb; <?php echo ($is_temp_plate && !$design_preview_src && !$ref_preview_src) ? 'display:none;' : ''; ?>">
                        <!-- Main Design -->
                        <?php if ($design_preview_src || !$is_temp_plate): ?>
                        <div>
                            <div style="font-size:0.7rem; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.75rem;">Final Design Preview</div>
                            <?php if ($design_preview_src): ?>
                                <div style="background:white; border:2px solid black; border-radius:8px; padding:10px; display:inline-block; max-width:100%; box-shadow: 4px 4px 0px rgba(0,0,0,0.05); box-sizing: border-box; overflow: hidden;">
                                    <img src="<?php echo $design_preview_src; ?>" alt="Design Preview" style="max-width:100%; max-height:240px; border-radius:4px; display:block; object-fit:contain;">
                                </div>
                            <?php else: ?>
                                <div style="font-size:0.875rem; color:#ef4444; background:#fef2f2; padding:15px; border-radius:8px; border:2px dashed #fecaca; font-weight:600;">
                                    ⚠️ No design uploaded.
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Reference Image -->
                        <?php if ($ref_preview_src): ?>
                        <div>
                            <div style="font-size:0.7rem; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.75rem;">Reference Image</div>
                            <div style="background:white; border:2px solid black; border-radius:8px; padding:10px; display:inline-block; max-width:100%; box-shadow: 4px 4px 0px rgba(0,0,0,0.05); box-sizing: border-box; overflow: hidden;">
                                <img src="<?php echo $ref_preview_src; ?>" alt="Reference Preview" style="max-width:100%; max-height:240px; border-radius:4px; display:block; object-fit:contain;">
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Section 3: Notes & Contact (Stacked) -->
                <div style="display:flex; flex-direction:column; gap:0;">
                    <!-- Order Notes -->
                    <div style="padding:2rem; border-bottom:1px solid #f3f4f6;">
                        <h3 style="font-size:0.95rem; font-weight:800; color:black; margin-bottom:1.25rem; display:flex; align-items:center; gap:10px; text-transform:uppercase; letter-spacing:0.02em;">
                            <span style="display:flex; align-items:center; justify-content:center; width:28px; height:28px; background:black; color:white; border-radius:4px; font-size:0.9rem;">📝</span>
                            Order Notes
                        </h3>
                        <textarea name="notes" class="input-field" 
                                  style="width:100%; min-height:100px; resize:vertical; font-size:1rem; background:#f9fafb; border:1px solid #e5e7eb; padding:1rem; line-height:1.5; border-radius:8px;" 
                                  placeholder="Add special instructions or general notes for this order..."><?php echo htmlspecialchars($item['customization']['notes'] ?? ''); ?></textarea>
                    </div>

                    <!-- Contact Info -->
                    <div style="padding:2rem; background:#fcfcfc;">
                        <h3 style="font-size:0.95rem; font-weight:800; color:black; margin-bottom:1.5rem; display:flex; align-items:center; gap:10px; text-transform:uppercase; letter-spacing:0.02em;">
                            <span style="display:flex; align-items:center; justify-content:center; width:28px; height:28px; background:black; color:white; border-radius:4px; font-size:0.9rem;">📋</span>
                            Contact Information
                        </h3>
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:2rem;">
                            <div>
                                <div style="font-size:0.7rem; color:#9ca3af; text-transform:uppercase; letter-spacing:.08em; font-weight:700; margin-bottom:6px;">Full Name</div>
                                <div style="font-weight:700; color:black; font-size:1.1rem; display:flex; align-items:center; gap:10px;">
                                    <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                    <?php if ($customer_type === 'regular'): ?>
                                        <span style="font-size:0.65rem; color:#15803d; background:#dcfce7; padding:2px 10px; border-radius:4px; border:1px solid #bbf7d0; font-weight:600;">Regular Customer</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <div style="font-size:0.7rem; color:#9ca3af; text-transform:uppercase; letter-spacing:.08em; font-weight:700; margin-bottom:6px;">Phone Number</div>
                                <div style="font-weight:700; color:black; font-size:1.1rem;"><?php echo htmlspecialchars($customer['contact_number'] ?? '—'); ?></div>
                            </div>
                            <div>
                                <div style="font-size:0.7rem; color:#9ca3af; text-transform:uppercase; letter-spacing:.08em; font-weight:700; margin-bottom:6px;">Email Address</div>
                                <div style="font-weight:700; color:black; font-size:1.1rem;"><?php echo htmlspecialchars($customer['email']); ?></div>
                            </div>
                        </div>
                        <div style="margin-top:2rem; padding-top:1.25rem; border-top:1px solid #f3f4f6; font-size:0.8rem; color:#9ca3af; font-style:italic;">
                            Need to update these details? Please edit your profile before confirming the order.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Summary & actions -->
            <div style="display:flex; flex-direction:column; gap:1rem; position:sticky; top:100px;">
                <div class="card" style="padding:1.75rem;">
                    <h2 style="font-size:0.9rem; font-weight:800; color:black; margin:0 0 1.25rem 0; text-transform:uppercase; letter-spacing:0.04em; border-bottom:1px solid #f3f4f6; padding-bottom:0.75rem;">Order Summary</h2>

                    <div style="display:flex; justify-content:space-between; font-size:0.9rem; margin-bottom:0.75rem; color:#6b7280; font-weight:600;">
                        <span><?php echo htmlspecialchars($item['name']); ?> × <?php echo (int)$item['quantity']; ?></span>
                        <span style="color:black;"><?php echo format_currency($subtotal); ?></span>
                    </div>

                    <div style="border-top:2px solid black; padding-top:1rem; margin-top:1rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-weight:800; font-size:1rem; text-transform:uppercase;">Grand Total</span>
                        <span style="font-size:1.6rem; font-weight:900; color:black; letter-spacing:-0.02em;"><?php echo format_currency($subtotal); ?></span>
                    </div>

                    <?php if ($customer_type === 'new'): ?>
                        <div style="background:#fff1f2; border:1px solid #fecaca; border-radius:8px; padding:12px 14px; font-size:0.8rem; color:#b91c1c; margin-bottom:1.25rem;">
                            ⚠️ <strong>New Customer Policy</strong><br>
                            To process your order, <strong>full payment (PHP <?php echo number_format($subtotal, 2); ?>)</strong> is required upfront. You'll become a 'Regular' customer after 3 successful orders!
                        </div>
                    <?php else: ?>
                        <div style="background:#f0fdf4; border:1px solid #dcfce7; border-radius:8px; padding:10px 14px; font-size:0.8rem; color:#15803d; margin-bottom:1.25rem;">
                            ✅ <strong>Regular Customer Benefit</strong><br>
                            Choose your preferred payment option below:
                        </div>

                        <div style="margin-bottom: 2rem; display: flex; flex-direction: column; gap: 0.85rem;">
                            <label style="display: flex; align-items: center; gap: 12px; padding: 12px; border: 2px solid #e5e7eb; border-radius: 10px; cursor: pointer; transition: all 0.2s; background: white;" onmouseover="this.style.borderColor='black'; this.style.background='#fcfcfc'" onmouseout="this.style.borderColor=this.querySelector('input').checked ? 'black' : '#e5e7eb'; this.style.background=this.querySelector('input').checked ? '#fcfcfc' : 'white'">
                                <input type="radio" name="payment_choice" value="full" style="width: 18px; height: 18px; accent-color: black; cursor: pointer;" onchange="document.querySelectorAll('label').forEach(l => l.style.borderColor='#e5e7eb'); this.parentElement.style.borderColor='black';">
                                <div style="flex: 1;">
                                    <div style="font-weight: 800; font-size: 0.9rem; color: black; letter-spacing:-0.01em;">Full Payment (100%)</div>
                                    <div style="font-size: 0.75rem; color: #6b7280; font-weight:600;">Immediately Pay <?php echo format_currency($subtotal); ?></div>
                                </div>
                            </label>

                            <label style="display: flex; align-items: center; gap: 12px; padding: 12px; border: 2px solid black; background: #fcfcfc; border-radius: 10px; cursor: pointer; transition: all 0.2s;">
                                <input type="radio" name="payment_choice" value="half" checked style="width: 18px; height: 18px; accent-color: black; cursor: pointer;" onchange="document.querySelectorAll('label').forEach(l => l.style.borderColor='#e5e7eb'); this.parentElement.style.borderColor='black';">
                                <div style="flex: 1;">
                                    <div style="font-weight: 800; font-size: 0.9rem; color: black; letter-spacing:-0.01em;">Half Payment (50%)</div>
                                    <div style="font-size: 0.75rem; color: #6b7280; font-weight:600;">Pay <?php echo format_currency($subtotal * 0.5); ?> now, rest on pickup</div>
                                </div>
                            </label>

                            <label style="display: flex; align-items: center; gap: 12px; padding: 12px; border: 2px solid #e5e7eb; border-radius: 10px; cursor: pointer; transition: all 0.2s; background: white;" onmouseover="this.style.borderColor='black'; this.style.background='#fcfcfc'" onmouseout="this.style.borderColor=this.querySelector('input').checked ? 'black' : '#e5e7eb'; this.style.background=this.querySelector('input').checked ? '#fcfcfc' : 'white'">
                                <input type="radio" name="payment_choice" value="pickup" style="width: 18px; height: 18px; accent-color: black; cursor: pointer;" onchange="document.querySelectorAll('label').forEach(l => l.style.borderColor='#e5e7eb'); this.parentElement.style.borderColor='black';">
                                <div style="flex: 1;">
                                    <div style="font-weight: 800; font-size: 0.9rem; color: black; letter-spacing:-0.01em;">Upon Pick Up (0%)</div>
                                    <div style="font-size: 0.75rem; color: #6b7280; font-weight:600;">Pay full amount on pick up</div>
                                </div>
                            </label>
                        </div>
                    <?php endif; ?>

                    <!-- Place Order -->
                    <button type="submit" name="confirm_order"
                            style="width:100%; padding:14px; background:linear-gradient(135deg,#4F46E5,#7C3AED); color:#fff; font-size:1rem; font-weight:700; border:none; border-radius:10px; cursor:pointer; letter-spacing:.02em; transition:opacity .2s;"
                            onmouseover="this.style.opacity='.9'" onmouseout="this.style.opacity='1'">
                        ✅ Confirm &amp; Place Order
                    </button>

                    <!-- Cancel -->
                    <a href="?item=<?php echo urlencode($item_key); ?>&cancel=1"
                       onclick="return confirm('Cancel this order? Your selections will be lost.');"
                       style="display:block; text-align:center; margin-top:0.85rem; font-size:0.875rem; color:#ef4444; text-decoration:none; font-weight:600; padding:10px; border:1px solid #fecaca; border-radius:8px; transition:background .2s;"
                       onmouseover="this.style.background='#fff1f2'" onmouseout="this.style.background='transparent'">
                        ✕ Cancel Order
                    </a>
                </div>

                <!-- Safety note -->
                <div style="font-size:0.78rem; color:#9ca3af; text-align:center; line-height:1.6;">
                    🔒 Your order details are secure.<br>
                    You can always cancel from My Orders after placing.
                </div>
            </div>
        </div>
    </form>
</div>
</div>

<style>
@media (max-width: 700px) {
    form > div { grid-template-columns: 1fr !important; }
    .sticky-aside { position:static !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

