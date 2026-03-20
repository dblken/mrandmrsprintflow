<?php
/**
 * Shared Helper for Order Item UI
 * PrintFlow - Neubrutalism Design System
 */

/**
 * Renders a single order item card in the Neubrutalism style.
 * Supports both cart items (session) and database items (order_items table).
 *
 * @param array $item The item data
 * @param bool $is_cart_item Whether this is from the session cart
 */
function render_order_item_neubrutalism($item, $is_cart_item = false, $show_price = true) {
    // 1. Data Normalization
    $custom = $is_cart_item ? ($item['customization'] ?? []) : json_decode($item['customization_data'] ?? '{}', true);
    $name = $item['name'] ?? ($item['product_name'] ?? null);
    if (empty($name) || in_array(strtolower(trim((string)$name)), ['custom order', 'customer order', 'service order', 'order item'])) {
        $name = get_service_name_from_customization($custom, $name ?: 'Custom Order');
    }
    $name = normalize_service_name($name, 'Order Item');
    $category = $item['category'] ?? 'General';
    $unit_price = $is_cart_item ? $item['price'] : $item['unit_price'];
    $quantity = $item['quantity'];
    $subtotal = $unit_price * $quantity;
    
    // Design previews
    $design_url = null;
    $ref_url = null;
    
    if ($is_cart_item) {
        if (!empty($item['design_tmp_path']) && file_exists($item['design_tmp_path']) && !empty($item['design_mime'])) {
            $binary = @file_get_contents($item['design_tmp_path']);
            if ($binary) $design_url = 'data:' . $item['design_mime'] . ';base64,' . base64_encode($binary);
        }
        if (!empty($item['reference_tmp_path']) && file_exists($item['reference_tmp_path']) && !empty($item['reference_mime'])) {
            $binary = @file_get_contents($item['reference_tmp_path']);
            if ($binary) $ref_url = 'data:' . $item['reference_mime'] . ';base64,' . base64_encode($binary);
        }
    } else {
        // First try to serve as a design image (either BLOB or File from order_items)
        $has_design = !empty($item['design_image']) || !empty($item['design_file']);
        
        if ($has_design) {
            $design_url = "/printflow/public/serve_design.php?type=order_item&id=" . (int)$item['order_item_id'];
        } else if (!empty($item['product_image'])) {
            // Fallback 1: Product catalog image (from Joined products table)
            $design_url = $item['product_image'];
        } else {
            // Fallback 2: Category based icons/placeholders
            $cat_lower = strtolower(($item['category'] ?? '') . ' ' . ($item['name'] ?? ''));
            if (strpos($cat_lower, 'reflectorized') !== false || strpos($cat_lower, 'signage') !== false || strpos($cat_lower, 'sticker') !== false) {
                $design_url = "/printflow/public/images/products/signage.jpg";
            } else if (strpos($cat_lower, 'tarpaulin') !== false) {
                $design_url = "/printflow/public/images/products/product_21.jpg"; // Use a known tarp product
            } else if (strpos($cat_lower, 't-shirt') !== false || strpos($cat_lower, 'tshirt') !== false || strpos($cat_lower, 'souvenir') !== false) {
                $design_url = "/printflow/public/images/products/product_22.jpg"; // Use a known mug/tshirt product
            }
        }

        if (!empty($item['reference_image_file'])) {
            $ref_url = "/printflow/public/serve_design.php?type=order_item&id=" . (int)$item['order_item_id'] . "&field=reference";
        }
    }

    // Field Map for Labels
    $field_map = [
        'size' => 'Size',
        'color' => 'Color',
        'shirt_color' => 'Color',
        'print_placement' => 'Placement',
        'design_type' => 'Design Type',
        'template' => 'Template',
        'width' => 'Width (ft)',
        'height' => 'Height (ft)',
        'finish' => 'Finish',
        'with_eyelets' => 'Eyelets',
        'shape' => 'Shape',
        'waterproof' => 'Waterproof',
        'Sintra_Type' => 'Sintraboard Type',
        'laminate_option' => 'Lamination Option',
        'lamination' => 'Lamination',
        'tshirt_provider' => 'T-Shirt Provider',
        'Stand_Type' => 'Stand Type',
        'Cut_Type' => 'Cut Type',
        'Thickness' => 'Thickness',
        'Lamination' => 'Lamination Type',
        'needed_date' => 'Needed Date',
    ];
    $skip = ['design_upload', 'reference_upload', 'notes', 'Branch_ID', 'service_type', 'product_type', 'unit'];
    
    ?>
    <div style="border: 2px solid #000; background: #fff; margin-bottom: 2rem; overflow: hidden; box-shadow: 8px 8px 0px rgba(0,0,0,1);">
        <!-- Top Section: Core Info -->
        <div style="padding: 1.5rem; border-bottom: 2px solid #000; display: flex; gap: 1.5rem; align-items: flex-start;">
            <div style="width: 120px; height: 120px; border: 2px solid #000; border-radius: 8px; overflow: hidden; background: #f3f4f6; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <?php if ($design_url): ?>
                    <img src="<?php echo $design_url; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <span style="font-size: 2.5rem;">📦</span>
                <?php endif; ?>
            </div>
            
            <div style="flex: 1; min-width: 0;">
                <div style="font-size: 1.5rem; font-weight: 900; margin-bottom: 0.25rem; word-wrap: break-word;"><?php echo htmlspecialchars($name); ?></div>
                <div style="font-size: 0.75rem; font-weight: 800; color: #6b7280; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 1rem; word-wrap: break-word;">
                    <?php echo htmlspecialchars($category); ?>
                </div>
                
                <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
                    <?php if ($show_price): ?>
                    <div style="min-width: 120px;">
                        <div style="font-size: 0.95rem; font-weight: 800;">Price: <?php echo format_currency($unit_price); ?></div>
                    </div>
                    <?php endif; ?>
                    <div style="min-width: 80px;">
                        <div style="font-size: 0.95rem; font-weight: 800;">Qty: <?php echo $quantity; ?></div>
                    </div>
                    <?php if ($show_price): ?>
                    <div style="min-width: 150px;">
                        <div style="font-size: 0.95rem; font-weight: 800;">Subtotal: <?php echo format_currency($subtotal); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Middle Section: Customization -->
        <div style="padding: 1.5rem; background: #fcfcfc;">
            <div style="font-size: 0.75rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; color: #000; display: flex; align-items: center; gap: 6px;">
                <span style="width: 8px; height: 8px; background: #000; border-radius: 50%;"></span>
                Specifications
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 1rem;">
                <?php 
                $has_specs = false;
                foreach ($custom as $ck => $cv): 
                    if (empty($cv) || in_array($ck, $skip) || strpos($ck, 'description') !== false) continue;
                    $has_specs = true;
                    $label = $field_map[$ck] ?? ucwords(str_replace(['_', '-'], ' ', $ck));
                    $display_val = ($ck === 'tshirt_provider' && $cv === 'shop') ? 'Shop will provide' : (($ck === 'tshirt_provider' && $cv === 'customer') ? 'Customer will provide' : $cv);
                ?>
                    <div style="border: 1px solid #000; padding: 0.75rem; border-radius: 6px; background: #fff;">
                        <div style="font-size: 0.6rem; font-weight: 800; color: #6b7280; text-transform: uppercase; margin-bottom: 2px;"><?php echo $label; ?></div>
                        <div style="font-size: 0.9rem; font-weight: 800; color: #000;"><?php echo htmlspecialchars($display_val); ?></div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (!$has_specs): ?>
                    <div style="font-size: 0.8rem; color: #9ca3af; font-style: italic;">No specific customizations.</div>
                <?php endif; ?>
            </div>

            <!-- Notes -->
            <?php 
            $notes = $custom['notes'] ?? ($custom['design_description'] ?? ($custom['tshirt_design_description'] ?? ($custom['tarp_design_description'] ?? ($custom['design_notes'] ?? null))));
            if ($notes):
            ?>
                <div style="margin-top: 1rem; padding: 1rem; background: #fffbeb; border: 1px solid #000; border-radius: 8px;">
                    <div style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: #92400e; margin-bottom: 4px;">📝 Notes</div>
                    <div style="font-size: 0.9rem; font-weight: 700; color: #b45309; line-height: 1.4; max-height: 120px; overflow-y: auto; overflow-x: hidden; word-break: break-word;"><?php echo nl2br(htmlspecialchars($notes)); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bottom Section: File Evidence -->
        <?php if ($ref_url): ?>
            <div style="padding: 1.25rem; background: #fff; border-top: 1px solid #000; border-style: dashed;">
                <div style="font-size: 0.75rem; font-weight: 900; text-transform: uppercase; margin-bottom: 0.75rem;">Reference Image</div>
                <div style="display: inline-block; padding: 6px; border: 2px solid #000; border-radius: 8px; background: white; box-shadow: 4px 4px 0px rgba(0,0,0,0.1);">
                    <img src="<?php echo $ref_url; ?>" style="max-width: 140px; border-radius: 4px; display: block;">
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Renders a single order item card in a clean, modern style.
 *
 * @param array $item The item data
 * @param bool $is_cart_item Whether this is from the session cart
 */
function render_order_item_clean($item, $is_cart_item = false, $show_price = true) {
    $name = $item['name'] ?? ($item['product_name'] ?? 'Order Item');
    $category = $item['category'] ?? 'General';
    $unit_price = $is_cart_item ? $item['price'] : $item['unit_price'];
    $quantity = $item['quantity'];
    $subtotal = $unit_price * $quantity;
    
    $custom = $is_cart_item ? ($item['customization'] ?? []) : json_decode($item['customization_data'] ?? '{}', true);
    if (empty($name) || in_array(strtolower(trim((string)$name)), ['custom order', 'customer order', 'service order', 'order item'])) {
        $name = get_service_name_from_customization($custom, 'Order Item');
    }
    $name = normalize_service_name($name, 'Order Item');
    
    $design_url = null;
    $ref_url = null;
    
    if ($is_cart_item) {
        if (!empty($item['design_tmp_path']) && file_exists($item['design_tmp_path']) && !empty($item['design_mime'])) {
            $binary = @file_get_contents($item['design_tmp_path']);
            if ($binary) $design_url = 'data:' . $item['design_mime'] . ';base64,' . base64_encode($binary);
        }
        if (!empty($item['reference_tmp_path']) && file_exists($item['reference_tmp_path']) && !empty($item['reference_mime'])) {
            $binary = @file_get_contents($item['reference_tmp_path']);
            if ($binary) $ref_url = 'data:' . $item['reference_mime'] . ';base64,' . base64_encode($binary);
        }
    } else {
        // First try to serve as a design image (either BLOB or File from order_items)
        $has_design = !empty($item['design_image']) || !empty($item['design_file']);
        
        if ($has_design) {
            $design_url = "/printflow/public/serve_design.php?type=order_item&id=" . (int)$item['order_item_id'];
        } else if (!empty($item['product_image'])) {
            // Fallback 1: Product catalog image (from Joined products table)
            $design_url = $item['product_image'];
        } else {
            // Fallback 2: Category based icons/placeholders
            $cat_lower = strtolower(($item['category'] ?? '') . ' ' . ($item['name'] ?? ''));
            if (strpos($cat_lower, 'reflectorized') !== false || strpos($cat_lower, 'signage') !== false || strpos($cat_lower, 'sticker') !== false) {
                $design_url = "/printflow/public/images/products/signage.jpg";
            } else if (strpos($cat_lower, 'tarpaulin') !== false) {
                $design_url = "/printflow/public/images/products/product_21.jpg"; // Use a known tarp product
            } else if (strpos($cat_lower, 't-shirt') !== false || strpos($cat_lower, 'tshirt') !== false || strpos($cat_lower, 'souvenir') !== false) {
                $design_url = "/printflow/public/images/products/product_22.jpg"; // Use a known mug/tshirt product
            }
        }

        if (!empty($item['reference_image_file'])) {
            $ref_url = "/printflow/public/serve_design.php?type=order_item&id=" . (int)$item['order_item_id'] . "&field=reference";
        }
    }

    $field_map = [
        'size' => 'Size',
        'color' => 'Color',
        'shirt_color' => 'Color',
        'print_placement' => 'Placement',
        'design_type' => 'Design Type',
        'template' => 'Template',
        'width' => 'Width (ft)',
        'height' => 'Height (ft)',
        'finish' => 'Finish',
        'with_eyelets' => 'Eyelets',
        'shape' => 'Shape',
        'waterproof' => 'Waterproof',
        'Sintra_Type' => 'Sintraboard Type',
        'laminate_option' => 'Lamination Option',
        'lamination' => 'Lamination',
        'tshirt_provider' => 'T-Shirt Provider',
        'Stand_Type' => 'Stand Type',
        'Cut_Type' => 'Cut Type',
        'Thickness' => 'Thickness',
        'Lamination' => 'Lamination Type',
        'needed_date' => 'Needed Date',
    ];
    $skip = ['design_upload', 'reference_upload', 'notes', 'Branch_ID', 'service_type', 'product_type', 'unit'];
    ?>
    <div class="card" style="padding: 0; overflow: hidden; border: 1px solid #e2e8f0; margin-bottom: 1.25rem;">
        <!-- Core Info -->
        <div style="padding: 1rem; display: flex; gap: 1rem; align-items: flex-start; border-bottom: 1px solid #f3f4f6;">
            <div style="width: 120px; height: 120px; border-radius: 10px; overflow: hidden; background: #f9fafb; border: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <?php if ($design_url): ?>
                    <img src="<?php echo $design_url; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <span style="font-size: 2rem;">📦</span>
                <?php endif; ?>
            </div>
            
            <div style="flex: 1; min-width: 0;">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #111827; margin-bottom: 0.25rem; word-wrap: break-word;"><?php echo htmlspecialchars($name); ?></h3>
                <div style="font-size: 0.8rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; word-wrap: break-word;">
                    <?php echo htmlspecialchars($category); ?>
                </div>
                
                <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
                    <?php if ($show_price): ?>
                    <div style="min-width: 140px;">
                        <div style="font-size: 0.9rem; color: #111827; font-weight: 700;">Unit Price: <?php echo format_currency($unit_price); ?></div>
                    </div>
                    <?php endif; ?>
                    <div style="min-width: 90px;">
                        <div style="font-size: 0.9rem; color: #111827; font-weight: 700;">Quantity: <?php echo $quantity; ?></div>
                    </div>
                    <?php if ($show_price): ?>
                    <div style="min-width: 150px;">
                        <div style="font-size: 0.9rem; color: #4F46E5; font-weight: 700;">Item Total: <?php echo format_currency($subtotal); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Specifications -->
        <div style="padding: 1rem; background: #fafafa;">
            <h4 style="font-size: 0.8rem; font-weight: 700; color: #374151; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 6px;">
                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Specifications
            </h4>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.75rem;">
                <?php 
                $has_specs = false;
                foreach ($custom as $ck => $cv): 
                    if (empty($cv) || in_array($ck, $skip) || strpos($ck, 'description') !== false) continue;
                    $has_specs = true;
                    $label = $field_map[$ck] ?? ucwords(str_replace(['_', '-'], ' ', $ck));
                    $display_val = ($ck === 'tshirt_provider' && $cv === 'shop') ? 'Shop will provide' : (($ck === 'tshirt_provider' && $cv === 'customer') ? 'Customer will provide' : $cv);
                ?>
                    <div style="background: #fff; border: 1px solid #e5e7eb; padding: 0.5rem 0.75rem; border-radius: 8px;">
                        <div style="font-size: 0.65rem; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 2px;"><?php echo $label; ?></div>
                        <div style="font-size: 0.85rem; font-weight: 600; color: #111827;"><?php echo htmlspecialchars($display_val); ?></div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (!$has_specs): ?>
                    <p style="font-size: 0.85rem; color: #9ca3af; font-style: italic;">No specific customizations.</p>
                <?php endif; ?>
            </div>

            <!-- Notes -->
            <?php 
            $notes = $custom['notes'] ?? ($custom['design_description'] ?? ($custom['tshirt_design_description'] ?? ($custom['tarp_design_description'] ?? ($custom['design_notes'] ?? null))));
            if ($notes):
            ?>
                <div style="margin-top: 1.25rem; padding: 1rem; background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: #92400e; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                        <span>📝</span> Design Description / Notes
                    </div>
                    <div style="font-size: 0.9rem; color: #92400e; line-height: 1.5; font-weight: 500; max-height: 120px; overflow-y: auto; overflow-x: hidden; word-break: break-word;"><?php echo nl2br(htmlspecialchars($notes)); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Evidence / Reference -->
        <?php if ($ref_url): ?>
            <div style="padding: 1.5rem; border-top: 1px solid #f3f4f6; background: #fff;">
                <div style="font-size: 0.85rem; font-weight: 700; color: #374151; margin-bottom: 1rem;">Reference Image</div>
                <div style="width: 120px; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb; padding: 4px;">
                    <img src="<?php echo $ref_url; ?>" style="width: 100%; height: auto; display: block; border-radius: 4px;">
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
