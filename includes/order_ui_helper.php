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
function render_order_item_neubrutalism($item, $is_cart_item = false) {
    // 1. Data Normalization
    $name = $item['name'] ?? ($item['product_name'] ?? 'Custom Order');
    $category = $item['category'] ?? 'General';
    $unit_price = $is_cart_item ? $item['price'] : $item['unit_price'];
    $quantity = $item['quantity'];
    $subtotal = $unit_price * $quantity;
    
    // Customization data
    $custom = $is_cart_item ? ($item['customization'] ?? []) : json_decode($item['customization_data'] ?? '{}', true);
    
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
        $design_url = "/printflow/public/serve_design.php?type=order_item&id=" . (int)$item['order_item_id'];
        if (!empty($item['reference_image_file'])) {
            $ref_url = "/printflow/public/serve_design.php?type=order_item&id=" . (int)$item['order_item_id'] . "&field=reference";
        }
    }

    // Field Map for Labels
    $field_map = [
        'size' => 'Size',
        'color' => 'Color',
        'print_placement' => 'Placement',
        'design_type' => 'Design Type',
        'template' => 'Template',
        'width' => 'Width (ft)',
        'height' => 'Height (ft)',
        'finish' => 'Finish',
        'with_eyelets' => 'Eyelets',
        'shape' => 'Shape',
        'waterproof' => 'Waterproof',
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
            
            <div style="flex: 1;">
                <div style="font-size: 1.5rem; font-weight: 900; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($name); ?></div>
                <div style="font-size: 0.75rem; font-weight: 800; color: #6b7280; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 1rem;">
                    <?php echo htmlspecialchars($category); ?>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                    <div>
                        <div style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">Price</div>
                        <div style="font-weight: 900; font-size: 1.1rem;"><?php echo format_currency($unit_price); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">Qty</div>
                        <div style="font-weight: 900; font-size: 1.1rem;"><?php echo $quantity; ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">Subtotal</div>
                        <div style="font-weight: 900; font-size: 1.1rem;"><?php echo format_currency($subtotal); ?></div>
                    </div>
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
                ?>
                    <div style="border: 1px solid #000; padding: 0.75rem; border-radius: 6px; background: #fff;">
                        <div style="font-size: 0.6rem; font-weight: 800; color: #6b7280; text-transform: uppercase; margin-bottom: 2px;"><?php echo $label; ?></div>
                        <div style="font-size: 0.9rem; font-weight: 800; color: #000;"><?php echo htmlspecialchars($cv); ?></div>
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
                    <div style="font-size: 0.9rem; font-weight: 700; color: #b45309; line-height: 1.4;"><?php echo nl2br(htmlspecialchars($notes)); ?></div>
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
