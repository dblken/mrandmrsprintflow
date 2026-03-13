<?php
/**
 * Souvenirs - Service Order Form
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/service_order_helper.php';
require_role('Customer');
$customer_id = get_user_id();

$page_title = 'Order Souvenirs - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';

$branches = db_query("SELECT id, branch_name FROM branches WHERE status = 'Active'");
?>
<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width: 640px;">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Souvenirs</h1>
            <a href="<?php echo BASE_URL; ?>/customer/services.php" class="text-sm font-bold text-black border-b-2 border-black">← Back to Services</a>
        </div>
        <div class="card p-6">
            <form id="souvenirForm" method="POST" enctype="multipart/form-data" class="space-y-5">
                <?php echo csrf_field(); ?>

                <!-- Branch -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Branch *</label>
                    <select name="branch_id" class="input-field w-full" required>
                        <?php foreach($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Souvenir Type -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Type *</label>
                    <select name="souvenir_type" class="input-field w-full" required>
                        <option value="Mug">Mug</option>
                        <option value="Keychain">Keychain</option>
                        <option value="Tote Bag">Tote Bag</option>
                        <option value="Pen">Pen</option>
                        <option value="Tumbler">Tumbler</option>
                        <option value="T-Shirt">T-Shirt</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <!-- Quantity -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Quantity *</label>
                    <input type="number" name="quantity" min="1" class="input-field w-full" required value="1">
                </div>

                <!-- Custom Print -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-2 uppercase">Custom Print?</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                            <input type="radio" name="custom_print" value="No" class="w-4 h-4 text-black" checked onchange="toggleDesignUpload()">
                            <span class="ml-3 text-sm font-bold">No</span>
                        </label>
                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                            <input type="radio" name="custom_print" value="Yes" class="w-4 h-4 text-black" onchange="toggleDesignUpload()">
                            <span class="ml-3 text-sm font-bold">Yes – I have a design</span>
                        </label>
                    </div>
                </div>

                <!-- Design Upload – always visible -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-2 uppercase">
                        Upload Design
                        <span id="upload-hint" class="font-normal normal-case text-xs ml-1 text-gray-400">(Optional)</span>
                    </label>
                    <div id="upload-drop-zone"
                         class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center bg-gray-50 hover:bg-white transition-colors"
                         style="cursor:pointer;"
                         onclick="document.getElementById('design_file').click()">
                        <div class="pointer-events-none">
                            <span class="block text-3xl mb-2">📤</span>
                            <span class="block text-xs font-bold text-black uppercase mb-1">Click or Drag &amp; Drop Your Design</span>
                            <span class="block text-xs text-gray-400 mb-3">JPG, PNG, PDF – Max 5MB</span>
                            <input type="file" name="design_file" id="design_file"
                                   accept=".jpg,.jpeg,.png,.pdf" class="hidden"
                                   onclick="event.stopPropagation()"
                                   onchange="updateFileName(this)">
                            <span id="fileNameDisplay"
                                  class="hidden text-xs font-bold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full mb-2 inline-block"></span>
                            <br>
                            <span class="inline-block mt-2 px-4 py-1.5 rounded-lg text-xs font-bold uppercase text-white"
                                  style="background:black;">Browse Files</span>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Notes / Special Instructions</label>
                    <textarea name="notes" rows="3" class="input-field w-full"
                              placeholder="e.g., preferred colors, text to print, placement..."></textarea>
                </div>

                <!-- Buttons -->
                <div style="display:flex; gap:1rem; margin-top:1rem;">
                    <button type="button" onclick="submitSouvenirOrder('add_to_cart')"
                            style="flex:1; padding:1rem; border-radius:8px; font-weight:800; font-size:0.9rem; text-transform:uppercase; background:white; border:2.5px solid black; color:black; cursor:pointer; transition:all 0.2s;"
                            onmouseover="this.style.background='black'; this.style.color='white';" onmouseout="this.style.background='white'; this.style.color='black';">
                        + Add to Cart
                    </button>
                    <button type="button" onclick="submitSouvenirOrder('buy_now')"
                            style="flex:1; padding:1rem; border-radius:8px; font-weight:800; font-size:0.9rem; text-transform:uppercase; background:black; border:2.5px solid black; color:white; cursor:pointer; transition:all 0.2s;"
                            onmouseover="this.style.background='white'; this.style.color='black';" onmouseout="this.style.background='black'; this.style.color='white';">
                        Review Your Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleDesignUpload() {
    const isYes = document.querySelector('input[name="custom_print"]:checked').value === 'Yes';
    const hint = document.getElementById('upload-hint');
    const fileInput = document.getElementById('design_file');
    const dropZone = document.getElementById('upload-drop-zone');

    if (isYes) {
        hint.textContent = '(Required)';
        hint.style.color = '#ef4444';
        hint.style.fontWeight = '700';
        fileInput.required = true;
        dropZone.style.borderColor = '#000';
        dropZone.style.background = '#fafafa';
    } else {
        hint.textContent = '(Optional)';
        hint.style.color = '#9ca3af';
        hint.style.fontWeight = 'normal';
        fileInput.required = false;
        dropZone.style.borderColor = '#e5e7eb';
        dropZone.style.background = '#f9fafb';
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

function submitSouvenirOrder(action) {
    const form = document.getElementById('souvenirForm');
    form.dataset.action = action;
    const event = new Event('submit', { cancelable: true });
    form.dispatchEvent(event);
}

document.getElementById('souvenirForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const action = this.dataset.action || 'buy_now';
    const buttons = this.querySelectorAll('button');
    buttons.forEach(btn => btn.disabled = true);

    const formData = new FormData(this);
    formData.append('action', action);

    fetch('api_add_to_cart_souvenirs.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (action === 'buy_now') {
                window.location.href = 'order_review.php?item=' + data.item_key;
            } else {
                window.location.href = 'cart.php';
            }
        } else {
            alert(data.message);
            buttons.forEach(btn => btn.disabled = false);
        }
    })
    .catch(err => {
        alert('An error occurred. Please try again.');
        console.error(err);
        buttons.forEach(btn => btn.disabled = false);
    });
});

// Drag and drop support
const dropZone = document.getElementById('upload-drop-zone');
dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.style.background = '#f3f4f6';
});
dropZone.addEventListener('dragleave', () => {
    dropZone.style.background = '#f9fafb';
});
dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.style.background = '#f9fafb';
    const file = e.dataTransfer.files[0];
    if (file) {
        const input = document.getElementById('design_file');
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        updateFileName(input);
    }
});
</script>

<style>
.input-field {
    border: 1px solid #d1d5db;
    border-radius: 0.75rem;
    padding: 0.65rem 1rem;
    font-size: 0.9rem;
    width: 100%;
    transition: border-color 0.2s;
    background: white;
}
.input-field:focus {
    border-color: black;
    outline: none;
    box-shadow: 0 0 0 2px rgba(0,0,0,0.05);
}
.card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 1.25rem;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
