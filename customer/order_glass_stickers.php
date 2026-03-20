<?php
/**
 * Glass & Wall Sticker Printing - Service Order Form
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
        'branch_id' => trim($_POST['branch_id'] ?? '1'),
        'surface_type' => trim($_POST['surface_type'] ?? ''),
        'sticker_type' => trim($_POST['sticker_type'] ?? ''),
        'width' => trim($_POST['width'] ?? ''),
        'height' => trim($_POST['height'] ?? ''),
        'unit' => trim($_POST['unit'] ?? 'Inches'),
        'coverage_type' => trim($_POST['coverage_type'] ?? 'Custom Size'),
        'total_glass_width' => trim($_POST['total_glass_width'] ?? ''),
        'total_glass_height' => trim($_POST['total_glass_height'] ?? ''),
        'panel_count' => trim($_POST['panel_count'] ?? ''),
        'design_service' => trim($_POST['design_service'] ?? ''),
        'design_concept' => trim($_POST['design_concept'] ?? ''),
        'installation_option' => trim($_POST['installation_option'] ?? ''),
        'installation_address' => trim($_POST['installation_address'] ?? ''),
        'floor_level' => trim($_POST['floor_level'] ?? ''),
        'location_type' => trim($_POST['location_type'] ?? ''),
        'print_coverage' => trim($_POST['print_coverage'] ?? ''),
        'quantity' => (int)($_POST['quantity'] ?? 1),
        'notes' => trim($_POST['notes'] ?? ''),
        'total_price' => trim($_POST['hidden_total_price'] ?? '0'),
    ];

    // Basic Validation
    if (empty($fields['surface_type']) || empty($fields['sticker_type']) || $fields['quantity'] < 1) {
        $error = 'Please fill in all required fields.';
    } elseif (!isset($_FILES['design_file']) || $_FILES['design_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your design.';
    } else {
        $valid = service_order_validate_file($_FILES['design_file']);
        if (!$valid['ok']) {
            $error = $valid['error'];
        } else {
            // Standard Product Integration (Glass Stickers ID: 11)
            $product_id = 11;
            $product_name = 'Glass & Wall Sticker Printing';
            $price_per_unit = (float)$fields['total_price'] / $fields['quantity'];

            // Process file for session
            $tmp_dir = __DIR__ . '/../uploads/temp';
            if (!is_dir($tmp_dir)) mkdir($tmp_dir, 0755, true);
            
            $db_data = file_get_contents($_FILES['design_file']['tmp_name']);
            $ext = pathinfo($_FILES['design_file']['name'], PATHINFO_EXTENSION);
            $tmp_filename = uniqid('glass_') . '.' . $ext;
            $tmp_path = $tmp_dir . '/' . $tmp_filename;
            file_put_contents($tmp_path, $db_data);
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $_FILES['design_file']['tmp_name']);
            finfo_close($finfo);

            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }

            $item_key = $product_id . '_' . time();

            // Structure customization fields for the filter
            $customization = [];
            foreach ($fields as $k => $v) {
                if ($k !== 'total_price' && $k !== 'quantity' && $k !== 'branch_id') {
                    $customization[$k] = $v;
                }
            }

            $_SESSION['cart'][$item_key] = [
                'product_id'     => $product_id,
                'branch_id'      => $fields['branch_id'],
                'name'           => $product_name,
                'category'       => 'Glass & Wall Sticker Printing',
                'price'          => $price_per_unit,
                'quantity'       => $fields['quantity'],
                'image'          => '🪟',
                'customization'  => $customization,
                'design_notes'   => $fields['notes'],
                'design_tmp_path'=> $tmp_path,
                'design_mime'    => $mime,
                'design_name'    => $_FILES['design_file']['name'],
                'reference_tmp_path' => null,
                'reference_mime'     => null,
                'reference_name'     => null
            ];

            if (isset($_POST['buy_now'])) {
                redirect(BASE_URL . '/customer/order_review.php?item=' . urlencode($item_key));
            } else {
                redirect(BASE_URL . '/customer/cart.php');
            }
        }
    }
}

$page_title = 'Order Glass & Wall Sticker - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width: 800px;">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Glass & Wall Sticker Printing</h1>
            <a href="services.php" class="text-sm font-bold text-black border-b-2 border-black">← Back to Services</a>
        </div>

        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="orderForm" class="space-y-6">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="hidden_total_price" id="hidden_total_price" value="0">

            <!-- Left Side: Customization Forms -->
            <div class="space-y-6">
                <!-- SECTION 1 – Surface & Material -->
                <div class="card p-6">
                    <div class="flex items-center gap-2 mb-6">
                        <span class="text-2xl">🪟</span>
                        <h2 class="text-xl font-bold uppercase tracking-wider">Surface & Material</h2>
                    </div>
                    
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-3 uppercase">Branch *</label>
                            <select name="branch_id" class="input-field w-full" required>
                                <?php foreach($branches as $b): ?>
                                    <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-3 uppercase">Surface Type (Required)</label>
                            <div class="grid grid-cols-1 gap-2">
                                <?php foreach(['Glass (Window / Door / Storefront)', 'Wall (Painted / Concrete)', 'Frosted Glass (Privacy type)', 'Mirror', 'Acrylic / Panel'] as $surface): ?>
                                <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" name="surface_type" value="<?php echo $surface; ?>" class="w-4 h-4 text-black" required <?php echo (($_POST['surface_type'] ?? '') === $surface) ? 'checked' : ''; ?>>
                                    <span class="ml-3 text-sm font-medium"><?php echo $surface; ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">Sticker Type / Material (Required)</label>
                            <select name="sticker_type" id="sticker_type" class="input-field w-full" required onchange="calculatePrice()">
                                <option value="" disabled selected>Select Material</option>
                                <option value="Clear Sticker (Transparent)">Clear Sticker (Transparent)</option>
                                <option value="Frosted Sticker (Privacy Film)">Frosted Sticker (Privacy Film)</option>
                                <option value="Opaque Vinyl">Opaque Vinyl</option>
                                <option value="One-Way Vision">One-Way Vision</option>
                                <option value="Removable Vinyl">Removable Vinyl</option>
                                <option value="Permanent Vinyl">Permanent Vinyl</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2 – Size Specifications -->
                <div class="card p-6">
                    <div class="flex items-center gap-2 mb-6">
                        <span class="text-2xl">📏</span>
                        <h2 class="text-xl font-bold uppercase tracking-wider">Size Specifications</h2>
                    </div>

                    <div class="space-y-6">
                        <div class="flex gap-4">
                            <label class="flex-1 flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors" id="labelOptionA">
                                <input type="radio" name="coverage_type" value="Custom Size" class="w-4 h-4 text-black" checked onchange="toggleSizeOptions()">
                                <span class="ml-3 font-bold uppercase text-sm">Option A – Custom Size</span>
                            </label>
                            <label class="flex-1 flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors" id="labelOptionB">
                                <input type="radio" name="coverage_type" value="Coverage Type" class="w-4 h-4 text-black" onchange="toggleSizeOptions()">
                                <span class="ml-3 font-bold uppercase text-sm">Option B – Coverage Type</span>
                            </label>
                        </div>

                        <!-- Option A Fields -->
                        <div id="sectionOptionA" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Width *</label>
                                <input type="number" name="width" id="width" step="0.1" min="0.1" class="input-field w-full" oninput="calculatePrice()">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Height *</label>
                                <input type="number" name="height" id="height" step="0.1" min="0.1" class="input-field w-full" oninput="calculatePrice()">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Unit *</label>
                                <select name="unit" id="unit" class="input-field w-full" onchange="calculatePrice()">
                                    <option value="Inches">Inches</option>
                                    <option value="Feet">Feet</option>
                                    <option value="Centimeters">Centimeters</option>
                                </select>
                            </div>
                        </div>

                        <!-- Option B Fields -->
                        <div id="sectionOptionB" class="hidden space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <?php foreach(['Full Glass Coverage', 'Half Glass', 'Custom Area'] as $coverage): ?>
                                <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" name="coverage_detail" value="<?php echo $coverage; ?>" class="w-4 h-4 text-black" onchange="toggleCoverageDetails()">
                                    <span class="ml-3 text-sm font-medium"><?php echo $coverage; ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>

                            <div id="fullGlassFields" class="hidden grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-gray-50 rounded-lg">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Total Glass Width</label>
                                    <input type="number" name="total_glass_width" id="total_glass_width" class="input-field w-full" oninput="calculatePrice()">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Total Glass Height</label>
                                    <input type="number" name="total_glass_height" id="total_glass_height" class="input-field w-full" oninput="calculatePrice()">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Panel Count</label>
                                    <input type="number" name="panel_count" id="panel_count" min="1" class="input-field w-full" value="1" oninput="calculatePrice()">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3 – Design & Installation -->
                <div class="card p-6">
                    <div class="flex items-center gap-2 mb-6">
                        <span class="text-2xl">🎨</span>
                        <h2 class="text-xl font-bold uppercase tracking-wider">Design & Installation</h2>
                    </div>

                    <div class="space-y-6">
                        <div class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center bg-gray-50">
                            <label class="block cursor-pointer">
                                <span class="block text-2xl mb-2">�</span>
                                <span class="block text-xs font-bold text-black uppercase mb-1">Upload Your File (Design, Image, or PDF) – Max 5MB</span>
                                <input type="file" name="design_file" id="design_file" accept=".jpg,.jpeg,.png,.pdf" class="hidden" onchange="updateFileName(this)" required>
                                <span id="fileNameDisplay" class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full hidden"></span>
                                <span class="btn-primary inline-block py-1.5 px-4 rounded-lg cursor-pointer mt-2 text-xs">Browse Files</span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-2 uppercase">Print Coverage *</label>
                            <select name="print_coverage" class="input-field w-full" required onchange="calculatePrice()">
                                <option value="" disabled selected>Select Print Coverage</option>
                                <option value="Full Color Print">Full Color Print</option>
                                <option value="Cut Only (Vinyl Letters Only)">Cut Only (Vinyl Letters Only)</option>
                                <option value="Frosted with Cut Logo">Frosted with Cut Logo</option>
                                <option value="Logo + Text Only">Logo + Text Only</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                <input type="radio" name="installation_option" value="Pickup Only (Client installs)" class="w-4 h-4 text-black" checked onchange="toggleInstallationFields(); calculatePrice();">
                                <span class="ml-3 text-xs font-bold uppercase">Pickup Only</span>
                            </label>
                            <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                <input type="radio" name="installation_option" value="With Installation Service" class="w-4 h-4 text-black" onchange="toggleInstallationFields(); calculatePrice();">
                                <span class="ml-3 text-xs font-bold uppercase">Installation</span>
                            </label>
                        </div>

                        <div id="installationFields" class="hidden p-4 bg-gray-50 rounded-lg space-y-4">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 mb-1 uppercase tracking-wider">Installation Address *</label>
                                <textarea name="installation_address" rows="2" class="input-field w-full text-sm" placeholder="Full address for installation..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 4 – Quantity & Notes -->
                <div class="card p-6">
                    <div class="flex items-center gap-2 mb-6">
                        <span class="text-2xl">📝</span>
                        <h2 class="text-xl font-bold uppercase tracking-wider">Order Details</h2>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Quantity *</label>
                            <input type="number" name="quantity" id="quantity" min="1" value="<?php echo (int)($_GET['qty'] ?? 1); ?>" class="input-field w-full font-bold" required oninput="calculatePrice()">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Additional Notes</label>
                            <textarea name="notes" rows="3" class="input-field w-full text-sm" placeholder="Any special instructions..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Submit Button Group -->
                <div style="display:flex; gap:1rem; margin-top:2rem;">
                    <!-- Buy Now Button (Solid) -->
                    <button type="submit" name="buy_now" value="1" style="flex:1; height: 56px; display: flex; align-items: center; justify-content: center; background: #0a2530; color: #ffffff; font-weight: 800; border-radius: 12px; border: none; cursor: pointer; transition: all 0.2s; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.02em; box-shadow: 4px 4px 0px rgba(10, 37, 48, 0.1);">
                        Buy Now
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSizeOptions() {
    const coverage = document.querySelector('input[name="coverage_type"]:checked').value;
    const sectionA = document.getElementById('sectionOptionA');
    const sectionB = document.getElementById('sectionOptionB');
    
    if (coverage === 'Custom Size') {
        sectionA.classList.remove('hidden');
        sectionB.classList.add('hidden');
    } else {
        sectionA.classList.add('hidden');
        sectionB.classList.remove('hidden');
    }
    calculatePrice();
}

function toggleCoverageDetails() {
    const detail = document.querySelector('input[name="coverage_detail"]:checked')?.value;
    const fullGlassFields = document.getElementById('fullGlassFields');
    
    if (detail === 'Full Glass Coverage') {
        fullGlassFields.classList.remove('hidden');
    } else {
        fullGlassFields.classList.add('hidden');
    }
    calculatePrice();
}

function toggleDesignService() {
    const service = document.querySelector('input[name="design_service"]:checked').value;
    const conceptSection = document.getElementById('designConceptSection');
    
    if (service === 'I need layout/design service') {
        conceptSection.classList.remove('hidden');
    } else {
        conceptSection.classList.add('hidden');
    }
}

function toggleInstallationFields() {
    const option = document.querySelector('input[name="installation_option"]:checked').value;
    const fields = document.getElementById('installationFields');
    
    if (option === 'With Installation Service') {
        fields.classList.remove('hidden');
    } else {
        fields.classList.add('hidden');
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
    const stickerType = document.getElementById('sticker_type').value;
    const coverageType = document.querySelector('input[name="coverage_type"]:checked').value;
    const quantity = parseInt(document.getElementById('quantity').value) || 1;
    const installOption = document.querySelector('input[name="installation_option"]:checked').value;

    let sqft = 0;
    
    if (coverageType === 'Custom Size') {
        const w = parseFloat(document.getElementById('width').value) || 0;
        const h = parseFloat(document.getElementById('height').value) || 0;
        const unit = document.getElementById('unit').value;
        
        if (unit === 'Inches') sqft = (w * h) / 144;
        else if (unit === 'Feet') sqft = w * h;
        else if (unit === 'Centimeters') sqft = (w * h) / 929.03;
    } else {
        const detail = document.querySelector('input[name="coverage_detail"]:checked')?.value;
        if (detail === 'Full Glass Coverage') {
            const w = parseFloat(document.getElementById('total_glass_width').value) || 0;
            const h = parseFloat(document.getElementById('total_glass_height').value) || 0;
            const panels = parseInt(document.getElementById('panel_count').value) || 1;
            sqft = w * h * panels; // Assuming coverage width/height is given in feet by default for coverage type
        }
    }

    // BASE RATES per SqFt (Estimated)
    let rate = 45; // Default Opaque Vinyl
    if (stickerType.includes('Frosted')) rate = 65;
    if (stickerType.includes('Clear')) rate = 55;
    if (stickerType.includes('One-Way')) rate = 85;
    if (stickerType.includes('Permanent')) rate = 60;
    
    let subtotal = sqft * rate * quantity;
    
    // Installation Fee (Estimated base + sqft surcharge)
    if (installOption === 'With Installation Service') {
        subtotal += 500; // Base visit fee
        subtotal += (sqft * 15); // Labor per sqft
    }

    const total = subtotal > 0 ? subtotal : 0;
    
    // Update Sidebar/Display
    const priceDisplay = document.getElementById('priceDisplay');
    if (priceDisplay) priceDisplay.textContent = '₱' + total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    const hiddenTotal = document.getElementById('hidden_total_price');
    if (hiddenTotal) hiddenTotal.value = total.toFixed(2);

    // Update Sidebar Detail Fields
    const summaryBaseRate = document.getElementById('summaryBaseRate');
    if (summaryBaseRate) summaryBaseRate.textContent = '₱' + rate.toFixed(2);

    const summarySqft = document.getElementById('summarySqft');
    if (summarySqft) summarySqft.textContent = sqft.toFixed(2) + ' sqft';

    const summaryInstallRow = document.getElementById('summaryInstallRow');
    const summaryInstallFee = document.getElementById('summaryInstallFee');
    if (summaryInstallRow && summaryInstallFee) {
        if (installOption === 'With Installation Service') {
            summaryInstallRow.style.display = 'flex';
            summaryInstallFee.textContent = '₱' + (500 + (sqft * 15)).toFixed(2);
        } else {
            summaryInstallRow.style.display = 'none';
        }
    }
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
.order-modal-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.45); /* Soft dark overlay without blur */
    display: flex; align-items: center; justify-content: center;
    z-index: 1000;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

