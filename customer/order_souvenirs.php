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
        </div>
        <div class="card p-6">
            <form id="souvenirForm" method="POST" enctype="multipart/form-data" class="space-y-5">
                <?php echo csrf_field(); ?>

                <!-- Branch -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch_id" class="input-field w-full" required>
                        <?php foreach($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Souvenir Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type *</label>
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

                <!-- Custom Print -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Custom Print?</label>
                    <div class="opt-btn-group">
                        <label class="opt-btn-wrap">
                            <input type="radio" name="custom_print" value="No" checked onchange="toggleDesignUpload(); souvenirUpdateOpt(this)">
                            <span>No</span>
                        </label>
                        <label class="opt-btn-wrap">
                            <input type="radio" name="custom_print" value="Yes" onchange="toggleDesignUpload(); souvenirUpdateOpt(this)">
                            <span>Yes – I have a design</span>
                        </label>
                    </div>
                </div>

                <!-- Lamination -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Lamination</label>
                    <div class="opt-btn-group">
                        <label class="opt-btn-wrap">
                            <input type="radio" name="lamination" value="With Lamination" onchange="souvenirUpdateOpt(this)">
                            <span>With Lamination</span>
                        </label>
                        <label class="opt-btn-wrap">
                            <input type="radio" name="lamination" value="Without Lamination" checked onchange="souvenirUpdateOpt(this)">
                            <span>Without Lamination</span>
                        </label>
                    </div>
                </div>

                <!-- Needed Date -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Needed Date *</label>
                    <input type="date" name="needed_date" id="needed_date" class="input-field" required min="<?php echo date('Y-m-d'); ?>">
                    <p class="souvenir-hint">When you need the order ready.</p>
                </div>

                <!-- Design Upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        📎 Upload Your File (Design, Image, or PDF) – Max 5MB
                        <span id="upload-hint" class="font-normal normal-case text-sm ml-1 text-gray-400">(Optional)</span>
                    </label>
                    <div id="upload-drop-zone"
                         class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center bg-gray-50 hover:bg-white transition-colors"
                         style="cursor:pointer;"
                         onclick="document.getElementById('design_file').click()">
                        <div class="pointer-events-none">
                            <span class="block text-3xl mb-2">📎</span>
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

                <!-- Quantity -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                    <input type="number" name="quantity" min="1" class="input-field w-full" required value="<?php echo max(1, (int)($_GET['qty'] ?? 1)); ?>">
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes / Special Instructions</label>
                    <textarea name="notes" rows="3" class="input-field w-full"
                              placeholder="e.g., preferred colors, text to print, placement..."></textarea>
                </div>

                <!-- Buttons -->
                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 0.75rem; margin-top: 2rem; flex-wrap: wrap;">
                    <a href="<?php echo BASE_URL; ?>/customer/services.php" style="height: 48px; min-width: 140px; padding: 0 1.25rem; display: inline-flex; align-items: center; justify-content: center; background: #f8fafc; color: #0f172a; font-weight: 700; font-size: 0.9rem; border-radius: 10px; border: 1px solid #cbd5e1; text-decoration: none; transition: all 0.2s;">Back to Services</a>
                    <button type="button" onclick="submitSouvenirOrder('buy_now')" style="height: 48px; min-width: 140px; padding: 0 1.25rem; background: #0a2530; color: #ffffff; font-weight: 800; font-size: 0.9rem; border-radius: 10px; border: none; cursor: pointer; transition: all 0.2s; text-transform: uppercase; letter-spacing: 0.02em;">Buy Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function souvenirUpdateOpt(input) {
    const name = input.name;
    document.querySelectorAll('input[name="' + name + '"]').forEach(function(r) {
        const wrap = r.closest('.opt-btn-wrap');
        if (wrap) { wrap.classList.remove('active'); if (r.checked) wrap.classList.add('active'); }
    });
}

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

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.opt-btn-wrap').forEach(function(w) {
        if (w.querySelector('input:checked')) w.classList.add('active');
    });
});

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
    border: 2px solid #d1d5db;
    border-radius: 8px;
    padding: 0.65rem 1rem;
    font-size: 0.9rem;
    width: 100%;
    transition: border-color 0.2s, box-shadow 0.2s;
    background: white;
}
.input-field:focus {
    border-color: #0a2530;
    outline: none;
    box-shadow: 0 0 0 2px rgba(10,37,48,0.2);
}
.card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 1.25rem;
}
.souvenir-hint { font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem; }
.opt-btn-wrap { padding: 0.55rem 1rem; border: 2px solid #d1d5db; background: #fff; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.875rem; color: #374151; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 0.4rem; }
.opt-btn-wrap:hover { border-color: #0a2530; background: #f9fafb; }
.opt-btn-wrap:has(input:checked), .opt-btn-wrap.active { border-color: #0a2530; box-shadow: 0 0 0 2px rgba(10,37,48,0.2); background: #fff; }
.opt-btn-wrap input { margin: 0; position: absolute; opacity: 0; pointer-events: none; }
.opt-btn-group { display: flex; flex-wrap: wrap; gap: 0.5rem; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
