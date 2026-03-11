<?php
/**
 * Transparent Stickers - Service Order Form
 * PrintFlow - Service-Based Ordering
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';

require_role('Customer');
$customer_id = get_user_id();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect all fields
    $fields = [
        'surface_application' => trim($_POST['surface_application'] ?? ''),
        'dimensions' => trim($_POST['dimensions'] ?? ''),
        'shape' => trim($_POST['shape'] ?? ''),
        'design_ready' => trim($_POST['design_ready'] ?? ''),
        'design_notes' => trim($_POST['design_notes'] ?? ''),
        'transparency_preference' => trim($_POST['transparency_preference'] ?? ''),
        'quantity' => (int)($_POST['quantity'] ?? 1),
        'bulk_discount_request' => trim($_POST['bulk_discount_request'] ?? ''),
        'finishing_lamination' => isset($_POST['finishing_lamination']) ? implode(', ', $_POST['finishing_lamination']) : 'None',
        'additional_notes' => trim($_POST['additional_notes'] ?? ''),
        'optional_addons' => isset($_POST['optional_addons']) ? implode(', ', $_POST['optional_addons']) : 'None',
        'total_price' => trim($_POST['hidden_total_price'] ?? '0'),
    ];

    // Basic Validation
    if (empty($fields['surface_application']) || empty($fields['dimensions']) || empty($fields['shape']) || $fields['quantity'] < 1) {
        $error = 'Please fill in all required fields.';
    } elseif ($fields['design_ready'] === 'Yes' && (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK)) {
        $error = 'Please upload your design file.';
    } else {
        $files = [];
        if (isset($_FILES['design_file']) && $_FILES['design_file']['error'] === UPLOAD_ERR_OK) {
            $valid = service_order_validate_file($_FILES['design_file']);
            if (!$valid['ok']) {
                $error = $valid['error'];
            } else {
                $files = [['file' => $_FILES['design_file'], 'prefix' => 'design']];
            }
        }

        if (empty($error)) {
            $result = service_order_create('Transparent Stickers', $customer_id, $fields, $files);
            if ($result['success']) {
                $_SESSION['order_success_id'] = $result['order_id'];
                redirect(BASE_URL . '/customer/order_success.php?service=transparent_stickers');
            }
            $error = $result['error'] ?: 'Failed to submit order.';
        }
    }
}

$page_title = 'Order Transparent Stickers - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width: 800px;">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Transparent Sticker Printing</h1>
            <a href="services.php" class="text-sm font-bold text-black border-b-2 border-black">← Back to Services</a>
        </div>

        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="orderForm" class="space-y-8">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="hidden_total_price" id="hidden_total_price" value="0">

            <!-- 1️⃣ Surface / Application -->
            <div class="card p-6">
                <div class="flex items-center gap-2 mb-6">
                    <span class="text-2xl">📦</span>
                    <h2 class="text-xl font-bold uppercase tracking-wider">1. Surface / Application</h2>
                </div>
                <p class="text-gray-600 text-sm mb-4 font-bold">Where will the sticker be applied? (Required)</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?php foreach(['Glass (Window / Door / Storefront)', 'Plastic / Acrylic', 'Metal', 'Smooth Painted Wall', 'Mirror'] as $surface): ?>
                    <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                        <input type="radio" name="surface_application" value="<?php echo $surface; ?>" class="w-4 h-4 text-black border-gray-300 focus:ring-black" required <?php echo (($_POST['surface_application'] ?? '') === $surface) ? 'checked' : ''; ?>>
                        <span class="ml-3 text-sm font-medium"><?php echo $surface; ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 2️⃣ Sticker Size & Shape -->
            <div class="card p-6">
                <div class="flex items-center gap-2 mb-6">
                    <span class="text-2xl">📏</span>
                    <h2 class="text-xl font-bold uppercase tracking-wider">2. Sticker Size & Shape</h2>
                </div>
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">Dimensions (cm or inches) *</label>
                        <input type="text" name="dimensions" id="dimensions" class="input-field w-full" placeholder="e.g. 5x5 cm or 2x2 inches" required oninput="calculatePrice()">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-3 uppercase">Shape options (Required)</label>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <?php foreach(['Rectangle / Square', 'Circle / Oval', 'Custom Shape'] as $shape): ?>
                            <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                <input type="radio" name="shape" value="<?php echo $shape; ?>" class="w-4 h-4 text-black border-gray-300" required onchange="calculatePrice()">
                                <span class="ml-3 text-sm font-medium"><?php echo $shape; ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">* For Custom Shape, please ensure your design upload includes the cut line.</p>
                    </div>
                </div>
            </div>

            <!-- 3️⃣ Design / Artwork -->
            <div class="card p-6">
                <div class="flex items-center gap-2 mb-6">
                    <span class="text-2xl">🎨</span>
                    <h2 class="text-xl font-bold uppercase tracking-wider">3. Design / Artwork</h2>
                </div>
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-3 uppercase">Do you have a design ready?</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                <input type="radio" name="design_ready" value="Yes" class="w-4 h-4 text-black" checked onchange="toggleDesignUpload()">
                                <span class="ml-3 text-sm font-medium">Yes (I will upload design)</span>
                            </label>
                            <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                <input type="radio" name="design_ready" value="No" class="w-4 h-4 text-black" onchange="toggleDesignUpload()">
                                <span class="ml-3 text-sm font-medium">No (We will design for you)</span>
                            </label>
                        </div>
                    </div>

                    <div id="designUploadSection" class="border-2 border-dashed border-gray-200 rounded-xl p-8 text-center bg-gray-50 hover:bg-white transition-colors">
                        <label class="block cursor-pointer">
                            <span class="block text-4xl mb-4">📤</span>
                            <span class="block text-sm font-bold text-black uppercase mb-1">Upload Design</span>
                            <span class="block text-xs text-gray-500 mb-4">PNG, JPEG, AI, PDF (Max: 10MB)</span>
                            <input type="file" name="design_file" id="design_file" accept=".png,.jpg,.jpeg,.ai,.pdf" class="hidden" onchange="updateFileName(this)">
                            <span id="fileNameDisplay" class="text-xs font-bold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full hidden"></span>
                            <span class="btn-primary inline-block py-2 px-6 rounded-full cursor-pointer mt-2">Browse Files</span>
                        </label>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">Design Notes / Instructions</label>
                        <textarea name="design_notes" rows="3" class="input-field w-full" placeholder="e.g., colors, text, logos, specific fonts"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">Transparency preference</label>
                        <select name="transparency_preference" class="input-field w-full" required>
                            <option value="Fully Transparent Background" selected>Fully Transparent Background</option>
                            <option value="Partially Transparent">Partially Transparent</option>
                            <option value="Frosted / Semi-transparent Effect">Frosted / Semi-transparent Effect</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 4️⃣ Quantity -->
            <div class="card p-6">
                <div class="flex items-center gap-2 mb-6">
                    <span class="text-2xl">📦</span>
                    <h2 class="text-xl font-bold uppercase tracking-wider">4. Quantity</h2>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">Number of stickers required *</label>
                        <input type="number" name="quantity" id="quantity" min="1" value="1" class="input-field w-full text-lg font-bold" required oninput="calculatePrice()">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">Any bulk discount requests? (Optional)</label>
                        <input type="text" name="bulk_discount_request" class="input-field w-full" placeholder="Specify if ordering in high volume">
                    </div>
                </div>
            </div>

            <!-- 5️⃣ Finishing & Lamination -->
            <div class="card p-6">
                <div class="flex items-center gap-2 mb-6">
                    <span class="text-2xl">✨</span>
                    <h2 class="text-xl font-bold uppercase tracking-wider">5. Finishing & Lamination</h2>
                </div>
                <p class="text-gray-600 text-sm mb-4 font-bold">Lamination / Protection options (optional)</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <?php foreach(['Matte Finish', 'Glossy Finish', 'UV Protection / Weatherproof'] as $opt): ?>
                    <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                        <input type="checkbox" name="finishing_lamination[]" value="<?php echo $opt; ?>" class="w-4 h-4 text-black border-gray-300 rounded" onchange="calculatePrice()">
                        <span class="ml-3 text-sm font-medium"><?php echo $opt; ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 6️⃣ Additional Notes -->
            <div class="card p-6">
                <div class="flex items-center gap-2 mb-6">
                    <span class="text-2xl">📝</span>
                    <h2 class="text-xl font-bold uppercase tracking-wider">6. Additional Notes</h2>
                </div>
                <textarea name="additional_notes" rows="4" class="input-field w-full" placeholder="Color matching requirements, specific text fonts, placement instructions, other special instructions..."></textarea>
                
                <div class="mt-6 space-y-3">
                    <p class="text-sm font-bold text-gray-700 uppercase">✅ Optional Add-ons:</p>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="optional_addons[]" value="Proof / mockup for approval" class="w-4 h-4 text-black rounded">
                        <span class="text-sm">Proof / mockup for approval before printing</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="optional_addons[]" value="Individual shapes cutting" class="w-4 h-4 text-black rounded">
                        <span class="text-sm">Cutting into individual shapes vs sheet format</span>
                    </label>
                </div>
            </div>

            <!-- Sticky Pricing & Submit Button -->
            <div class="sticky bottom-4 z-50">
                <div class="bg-black text-white p-6 rounded-2xl shadow-2xl flex items-center justify-between border border-gray-800">
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Estimated Total Price</p>
                        <p class="text-3xl font-black text-white" id="priceDisplay">₱0.00</p>
                    </div>
                    <button type="submit" class="bg-white text-black px-10 py-4 rounded-xl font-black text-lg uppercase tracking-wider hover:bg-gray-200 transition-colors">
                        Place Order Now
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function toggleDesignUpload() {
    const ready = document.querySelector('input[name="design_ready"]:checked').value;
    const uploadSection = document.getElementById('designUploadSection');
    if (ready === 'Yes') {
        uploadSection.classList.remove('hidden');
    } else {
        uploadSection.classList.add('hidden');
    }
}

function updateFileName(input) {
    const fileName = input.files[0] ? input.files[0].name : '';
    const display = document.getElementById('fileNameDisplay');
    if (fileName) {
        display.textContent = '📄 ' + fileName;
        display.classList.remove('hidden');
    } else {
        display.classList.add('hidden');
    }
}

function calculatePrice() {
    const quantity = parseInt(document.getElementById('quantity').value) || 1;
    const dimText = document.getElementById('dimensions').value;
    const shape = document.querySelector('input[name="shape"]:checked')?.value || 'Rectangle';
    
    // Simple price logic for transparent stickers
    // Base rate approx ₱1.50 - ₱3.00 per square inch depending on complexity
    let area = 4; // Default 2x2
    const matches = dimText.match(/(\d+(\.\d+)?)/g);
    if (matches && matches.length >= 2) {
        area = parseFloat(matches[0]) * parseFloat(matches[1]);
    }

    let rate = 2.5; // Base rate
    if (shape === 'Custom Shape') rate += 0.5;
    
    const finishing = document.querySelectorAll('input[name="finishing_lamination[]"]:checked');
    rate += (finishing.length * 0.5);

    let total = area * rate * quantity;
    
    // Minimum charge
    if (total > 0 && total < 150) total = 150;

    document.getElementById('priceDisplay').textContent = '₱' + total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('hidden_total_price').value = total.toFixed(2);
}

// Initial calculation
calculatePrice();
</script>

<style>
.card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 1.25rem;
    transition: all 0.3s ease;
}
.input-field {
    border: 1px solid #d1d5db;
    border-radius: 0.75rem;
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
}
.input-field:focus {
    border-color: black;
    outline: none;
    box-shadow: 0 0 0 2px rgba(0,0,0,0.05);
}
.btn-primary {
    background: black;
    color: white;
    font-weight: 700;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

