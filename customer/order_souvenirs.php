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
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Souvenirs</h1>
        <div class="card">
            <form id="souvenirForm" method="POST" enctype="multipart/form-data">
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type *</label>
                    <select name="souvenir_type" class="input-field" required>
                        <option value="Mug">Mug</option><option value="Keychain">Keychain</option><option value="Tote Bag">Tote Bag</option><option value="Pen">Pen</option><option value="Other">Other</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                    <input type="number" name="quantity" min="1" class="input-field" required value="1">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Custom Print?</label>
                    <select name="custom_print" id="custom_print" class="input-field">
                        <option value="No">No</option><option value="Yes">Yes</option>
                    </select>
                </div>
                <div class="mb-4" id="design-wrap" style="display:none;">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload Design (JPG, PNG, PDF - max 5MB)</label>
                    <input type="file" name="design_file" id="design_file" accept=".jpg,.jpeg,.png,.pdf" class="input-field">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="input-field"></textarea>
                </div>
                <button type="submit" class="btn-primary w-full">Review Order</button>
            </form>
        </div>
        <p class="mt-4 text-sm text-gray-500 text-center"><a href="<?php echo BASE_URL; ?>/customer/services.php" class="text-indigo-600 hover:underline">← Back to Services</a></p>
    </div>
</div>
<script>
document.getElementById('custom_print').addEventListener('change', function() {
    document.getElementById('design-wrap').style.display = this.value === 'Yes' ? 'block' : 'none';
    document.getElementById('design_file').required = (this.value === 'Yes');
});

document.getElementById('souvenirForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerText = 'Processing...';

    const formData = new FormData(this);
    fetch('api_add_to_cart_souvenirs.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            window.location.href = 'order_review.php?item=' + data.item_key;
        } else {
            alert(data.message);
            btn.disabled = false;
            btn.innerText = 'Review Order';
        }
    })
    .catch(err => {
        alert('An error occurred. Please try again.');
        console.error(err);
        btn.disabled = false;
        btn.innerText = 'Review Order';
    });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

