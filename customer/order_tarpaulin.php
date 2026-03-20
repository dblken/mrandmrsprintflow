<?php
/**
 * Tarpaulin Printing - Service Order Form
 * PrintFlow - Service-Based Ordering
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 1);
    $width = trim($_POST['width'] ?? '');
    $height = trim($_POST['height'] ?? '');
    $finish = trim($_POST['finish'] ?? 'Matte');
    $with_eyelets = trim($_POST['with_eyelets'] ?? 'No');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($width) || empty($height) || $quantity < 1) {
        $error = 'Please fill in Width, Height, and Quantity.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            $item_key = 'tarp_' . time() . '_' . rand(100, 999);
            
            $original_name = $_FILES['design_file']['name'];
            $mime = $valid['mime'];
            $ext = pathinfo($original_name, PATHINFO_EXTENSION);
            $new_name = uniqid('tmp_') . '.' . $ext;
            $tmp_dest = __DIR__ . '/../uploads/temp/' . $new_name;
            
            if (move_uploaded_file($_FILES['design_file']['tmp_name'], $tmp_dest)) {
                // Calculate Price:ft² @ 20 PHP (Example)
                $area = (float)$width * (float)$height;
                $unit_price = 20.00; // Base price per sq ft
                
                $_SESSION['cart'][$item_key] = [
                    'type' => 'Service',
                    'name' => 'Tarpaulin Printing',
                    'price' => $area * $unit_price,
                    'quantity' => $quantity,
                    'category' => 'Tarpaulin',
                    'branch_id' => $branch_id,
                    'design_tmp_path' => $tmp_dest,
                    'design_name' => $original_name,
                    'design_mime' => $mime,
                    'customization' => [
                        'width' => $width,
                        'height' => $height,
                        'finish' => $finish,
                        'with_eyelets' => $with_eyelets,
                        'notes' => $notes
                    ]
                ];
                
                if (isset($_POST['buy_now'])) {
                    redirect("order_review.php?item=" . urlencode($item_key));
                } else {
                    redirect("cart.php");
                }
            } else {
                $error = 'Failed to process uploaded file.';
            }
        }
    }
}

$page_title = 'Order Tarpaulin - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width: 640px;">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Tarpaulin Printing</h1>
        <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div class="card">
            <form action="" method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" class="input-field" required>
                        <?php foreach($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Dimensions (ft) *</label>
                    <p style="font-size: 0.75rem; color: #6b7280; margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Select a preset or enter custom dimensions <strong>(Width × Height)</strong></p>
                    
                    <!-- Preset Dimensions -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(90px, 1fr)); gap: 0.5rem; margin-bottom: 1rem;">
                        <button type="button" class="dimension-btn" data-width="3" data-height="4" onclick="selectDimension(3, 4, event)" style="padding: 0.65rem; border: 2px solid #d1d5db; background: #ffffff; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s; font-size: 0.85rem; color: #374151;">3×4</button>
                        <button type="button" class="dimension-btn" data-width="4" data-height="6" onclick="selectDimension(4, 6, event)" style="padding: 0.65rem; border: 2px solid #d1d5db; background: #ffffff; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s; font-size: 0.85rem; color: #374151;">4×6</button>
                        <button type="button" class="dimension-btn" data-width="5" data-height="8" onclick="selectDimension(5, 8, event)" style="padding: 0.65rem; border: 2px solid #d1d5db; background: #ffffff; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s; font-size: 0.85rem; color: #374151;">5×8</button>
                        <button type="button" class="dimension-btn" data-width="6" data-height="8" onclick="selectDimension(6, 8, event)" style="padding: 0.65rem; border: 2px solid #d1d5db; background: #ffffff; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s; font-size: 0.85rem; color: #374151;">6×8</button>
                    </div>

                    <!-- Custom Dimensions -->
                    <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 0.5rem; align-items: flex-end;">
                        <div>
                            <label style="font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.25rem;">Width</label>
                            <input type="number" id="width_input" name="width" step="0.1" min="0.1" class="input-field" placeholder="3" required>
                        </div>
                        <div style="text-align: center; color: #9ca3af; font-weight: 600; margin-bottom: 0.65rem;">×</div>
                        <div>
                            <label style="font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.25rem;">Height (max 6)</label>
                            <input type="number" id="height_input" name="height" step="0.1" min="0.1" max="6" class="input-field" placeholder="4" required onchange="validateHeight()">
                        </div>
                    </div>
                </div>

                <style>
                    .dimension-btn { color: #374151; }
                    .dimension-btn:hover { border-color: #0a2530; background: #f3f4f6; transform: translateY(-2px); }
                    .dimension-btn.active { border-color: #0a2530; background: #0a2530; color: #ffffff; }
                </style>
                
                <!-- Finish Type, With Eyelets, and Quantity in Same Row -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Finish Type</label>
                        <select name="finish" class="input-field">
                            <option value="Matte">Matte</option>
                            <option value="Glossy">Glossy</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">With Eyelets?</label>
                        <select name="with_eyelets" class="input-field">
                            <option value="No">No</option>
                            <option value="Yes">Yes</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.5rem;">Quantity *</label>
                        <div style="display: flex; align-items: center; height: 42px; border: 1px solid #d1d5db; border-radius: 6px; background: #ffffff; overflow: hidden;">
                            <button type="button" onclick="decreaseQty()" style="flex: 0 0 42px; height: 42px; border: none; background: #f3f4f6; color: #374151; font-weight: 800; font-size: 1.2rem; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">−</button>
                            <input type="number" id="quantity-input" name="quantity" min="1" value="<?php echo (int)($_POST['quantity'] ?? ($_GET['qty'] ?? 1)); ?>" style="flex: 1; border: none; text-align: center; font-weight: 700; font-size: 1rem; outline: none; background: transparent;" onchange="validateQuantity()">
                            <button type="button" onclick="increaseQty()" style="flex: 0 0 42px; height: 42px; border: none; background: #f3f4f6; color: #374151; font-weight: 800; font-size: 1.2rem; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">+</button>
                        </div>
                    </div>
                </div>

                <script>
                function selectDimension(w, h, event) {
                    event.preventDefault();
                    document.querySelectorAll('.dimension-btn').forEach(btn => btn.classList.remove('active'));
                    event.target.closest('button').classList.add('active');
                    document.getElementById('width_input').value = w;
                    document.getElementById('height_input').value = h;
                }
                
                function validateHeight() {
                    const heightInput = document.getElementById('height_input');
                    if (parseFloat(heightInput.value) > 6) heightInput.value = 6;
                }

                function increaseQty() {
                    const input = document.getElementById('quantity-input');
                    const val = parseInt(input.value) || 1;
                    input.value = val + 1;
                }

                function decreaseQty() {
                    const input = document.getElementById('quantity-input');
                    const val = parseInt(input.value) || 1;
                    if (val > 1) {
                        input.value = val - 1;
                    }
                }

                function validateQuantity() {
                    const input = document.getElementById('quantity-input');
                    let val = parseInt(input.value) || 1;
                    if (val < 1) val = 1;
                    input.value = val;
                }
                </script>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design * (JPG, PNG, PDF - max 5MB)</label>
                    <input type="file" name="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="input-field"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; margin-top: 2rem;">
                    <!-- Buy Now Button (Small & Clean) -->
                    <button type="submit" name="buy_now" value="1" style="padding: 0.65rem 1.75rem; background: #0a2530; color: #ffffff; font-weight: 600; border-radius: 8px; border: none; cursor: pointer; transition: all 0.2s; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;" onmouseover="this.style.background='#0d3038'; this.style.transform='translateY(-1px)'" onmouseout="this.style.background='#0a2530'; this.style.transform='translateY(0)'">
                        Buy Now
                    </button>
                </div>
            </form>
        </div>
        <p class="mt-4 text-sm text-gray-500 text-center"><a href="<?php echo BASE_URL; ?>/customer/services.php" class="text-indigo-600 hover:underline">← Back to Services</a></p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
