<?php
/**
 * Customer Rate Order Page
 * Shopee-style rating + optional review image
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');
ensure_ratings_table_exists();
ensure_order_status_values(['To Rate', 'Rated']);

$customer_id = get_user_id();
$order_id = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);

if (isset($_GET['mark_read'])) {
    $notif_id = (int)$_GET['mark_read'];
    if ($notif_id > 0) {
        db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?", 'ii', [$notif_id, $customer_id]);
    }
}

if ($order_id <= 0) {
    $_SESSION['error'] = 'Invalid order selected for rating.';
    redirect('/printflow/customer/orders.php?tab=completed');
}

$order_rows = db_query("
    SELECT o.order_id, o.customer_id, o.status,
           (SELECT oi.customization_data FROM order_items oi WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) AS customization_data,
           (SELECT p.name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) AS product_name,
           (SELECT oi.order_item_id FROM order_items oi WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) AS first_item_id,
           (SELECT IF(oi.design_image IS NOT NULL AND oi.design_image != '', 1, 0) FROM order_items oi WHERE oi.order_id = o.order_id ORDER BY oi.order_item_id ASC LIMIT 1) AS first_item_has_design
    FROM orders o
    WHERE o.order_id = ? AND o.customer_id = ?
    LIMIT 1
", 'ii', [$order_id, $customer_id]);

if (empty($order_rows)) {
    $_SESSION['error'] = 'Order not found.';
    redirect('/printflow/customer/orders.php?tab=completed');
}

$order = $order_rows[0];
if (!in_array((string)$order['status'], ['Completed', 'To Rate', 'Rated'], true)) {
    $_SESSION['error'] = 'You can only rate completed orders.';
    redirect('/printflow/customer/orders.php');
}

$existing = db_query("SELECT id, rating, comment, image, created_at FROM ratings WHERE order_id = ? LIMIT 1", 'i', [$order_id]);
$already_rated = !empty($existing);
$existing_rating = $already_rated ? (int)$existing[0]['rating'] : 0;

function resolve_service_type_label(array $order): string {
    $service = '';
    if (!empty($order['customization_data'])) {
        $json = json_decode((string)$order['customization_data'], true);
        if (is_array($json)) {
            $service = (string)($json['service_type'] ?? $json['product_type'] ?? '');
        }
    }
    if ($service === '') {
        $service = (string)($order['product_name'] ?? 'Print Service');
    }
    return normalize_service_name($service, 'Print Service');
}

$service_type_label = resolve_service_type_label($order);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } elseif ($already_rated) {
        $error = 'You already rated this order.';
    } else {
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim((string)($_POST['comment'] ?? ''));
        if ($rating < 1 || $rating > 5) {
            $error = 'Please select a star rating from 1 to 5.';
        } else {
            if (mb_strlen($comment) > 1200) {
                $comment = mb_substr($comment, 0, 1200);
            }

            $image_path = null;
            if (!empty($_FILES['review_image']['name'])) {
                $upload = upload_file($_FILES['review_image'], ['jpg', 'jpeg', 'png', 'webp'], 'reviews');
                if (empty($upload['success'])) {
                    $error = $upload['error'] ?? 'Failed to upload review image.';
                } else {
                    $image_path = $upload['file_path'] ?? null;
                }
            }

            if ($error === '') {
                try {
                    db_execute(
                        "INSERT INTO ratings (order_id, customer_id, service_type, rating, comment, image, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, NOW())",
                        'iisiss',
                        [$order_id, $customer_id, $service_type_label, $rating, $comment, $image_path]
                    );

                    $staff_users = db_query("SELECT user_id FROM users WHERE role = 'Staff' AND status = 'Activated'") ?: [];
                    $staff_msg = "Customer submitted a rating for Order #{$order_id}: {$rating}/5 stars.";
                    foreach ($staff_users as $staff) {
                        create_notification((int)$staff['user_id'], 'Staff', $staff_msg, 'Order', false, false, $order_id);
                    }

                    $_SESSION['success'] = 'Thank you! Your rating has been submitted.';
                    redirect('/printflow/customer/orders.php?tab=completed');
                } catch (Throwable $e) {
                    if (stripos($e->getMessage(), 'uniq_rating_order') !== false) {
                        $error = 'You already rated this order.';
                    } else {
                        $error = 'Could not submit your review. Please try again.';
                    }
                }
            }
        }
    }
}

$page_title = 'Rate Order - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.rate-wrap { max-width: 760px; margin: 0 auto; padding: 1rem; }
.rate-card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:24px; box-shadow:0 12px 30px rgba(15,23,42,0.07); }
.rate-title { font-size:1.35rem; font-weight:800; color:#0f172a; margin:0 0 6px; }
.rate-sub { font-size:0.9rem; color:#64748b; margin:0 0 18px; }
.rate-stars { display:flex; gap:8px; margin-bottom:14px; }
.rate-star-btn { width:42px; height:42px; border:1px solid #e2e8f0; border-radius:10px; background:#fff; color:#cbd5e1; font-size:24px; line-height:1; cursor:pointer; transition:all .15s; }
.rate-star-btn:hover { border-color:#f59e0b; color:#f59e0b; transform:translateY(-1px); }
.rate-star-btn.active { border-color:#f59e0b; background:#fffbeb; color:#f59e0b; }
.rate-label { display:block; font-size:11px; font-weight:800; letter-spacing:0.05em; text-transform:uppercase; color:#64748b; margin-bottom:6px; }
.rate-textarea { width:100%; min-height:130px; border:1px solid #cbd5e1; border-radius:12px; padding:12px 14px; font-size:14px; resize:vertical; outline:none; }
.rate-textarea:focus { border-color:#0a2530; box-shadow:0 0 0 3px rgba(10,37,48,.08); }
.rate-file { width:100%; border:1px solid #cbd5e1; border-radius:12px; padding:10px; font-size:13px; background:#fff; }
.rate-file-preview { margin-top:10px; max-width:220px; border:1px solid #e2e8f0; border-radius:10px; display:none; }
.rate-actions { margin-top:18px; display:flex; gap:10px; flex-wrap:wrap; }
.rate-btn-primary { background:#0a2530; color:#fff; border:none; border-radius:10px; padding:11px 18px; font-weight:800; cursor:pointer; }
.rate-btn-secondary { background:#f8fafc; color:#334155; border:1px solid #cbd5e1; border-radius:10px; padding:10px 16px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; }
.rate-info { background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px; margin-bottom:16px; }
.rate-error { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; border-radius:12px; padding:10px 12px; margin-bottom:14px; font-size:13px; }
.rated-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-top:14px; }
.rate-design-preview { margin-bottom:18px; padding:14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; }
.rate-design-preview img { max-width:100%; width:280px; height:auto; max-height:320px; object-fit:contain; border-radius:10px; border:1px solid #e2e8f0; display:block; }
.rated-stars { font-size:20px; color:#f59e0b; letter-spacing:2px; }
@media (max-width: 640px) { .rate-card { padding:18px; } .rate-stars { gap:6px; } .rate-star-btn { width:38px; height:38px; font-size:21px; } }
</style>

<div class="min-h-screen py-8 bg-gray-50">
    <div class="rate-wrap">
        <div class="rate-card">
            <h1 class="rate-title">Rate Your Order</h1>
            <p class="rate-sub">Order #<?php echo (int)$order_id; ?> - <?php echo htmlspecialchars($service_type_label); ?></p>

            <?php
            $show_order_design = !empty($order['first_item_has_design']) && !empty($order['first_item_id']);
            if ($show_order_design):
                $design_url = '/printflow/public/serve_design.php?type=order_item&id=' . (int)$order['first_item_id'];
            ?>
            <div class="rate-design-preview">
                <div style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:.05em; margin-bottom:8px;">Order Design Preview</div>
                <img src="<?php echo htmlspecialchars($design_url); ?>" alt="Order design" onerror="this.style.display='none'; this.parentElement.style.display='none';">
            </div>
            <?php endif; ?>

            <?php if ($already_rated): ?>
                <div class="rated-box">
                    <div style="font-size:12px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:.05em;">You already rated this order</div>
                    <div class="rated-stars"><?php echo str_repeat('★', $existing_rating) . str_repeat('☆', max(0, 5 - $existing_rating)); ?></div>
                    <?php if (!empty($existing[0]['comment'])): ?>
                        <p style="margin:8px 0 0; color:#334155; font-size:14px; word-break:break-word; overflow-wrap:anywhere;"><?php echo nl2br(htmlspecialchars($existing[0]['comment'])); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($existing[0]['image'])): 
                        $img_src = (strpos($existing[0]['image'], '/') === 0) ? $existing[0]['image'] : '/' . ltrim($existing[0]['image'], '/');
                    ?>
                        <div style="margin-top:10px;">
                            <div style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px;">Your Review Image</div>
                            <img src="<?php echo htmlspecialchars($img_src); ?>" alt="Review image" style="max-width:280px; max-height:240px; object-fit:contain; border-radius:10px; border:1px solid #e2e8f0; display:block;">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="rate-actions">
                    <a class="rate-btn-secondary" href="/printflow/customer/orders.php?tab=completed">Back to Completed Orders</a>
                </div>
            <?php else: ?>
                <?php if ($error !== ''): ?><div class="rate-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

                <div class="rate-info">
                    Rating is optional, but your feedback helps us improve service quality.
                </div>

                <form method="POST" enctype="multipart/form-data" id="ratingForm">
                    <input type="hidden" name="order_id" value="<?php echo (int)$order_id; ?>">
                    <input type="hidden" id="ratingInput" name="rating" value="">
                    <?php echo csrf_field(); ?>

                    <label class="rate-label">Star Rating</label>
                    <div class="rate-stars" id="starButtons">
                        <button type="button" class="rate-star-btn" data-value="1">★</button>
                        <button type="button" class="rate-star-btn" data-value="2">★</button>
                        <button type="button" class="rate-star-btn" data-value="3">★</button>
                        <button type="button" class="rate-star-btn" data-value="4">★</button>
                        <button type="button" class="rate-star-btn" data-value="5">★</button>
                    </div>

                    <label class="rate-label" for="commentInput">Comment / Review</label>
                    <textarea id="commentInput" class="rate-textarea" name="comment" placeholder="Share your experience with the service quality, design, and delivery..."></textarea>

                    <div style="margin-top:14px;">
                        <label class="rate-label" for="imageInput">Upload Image (Optional)</label>
                        <input id="imageInput" class="rate-file" type="file" name="review_image" accept="image/jpeg,image/png,image/webp,image/jpg">
                        <img id="imagePreview" class="rate-file-preview" alt="Preview">
                    </div>

                    <div class="rate-actions">
                        <button type="submit" class="rate-btn-primary">Submit Review</button>
                        <a class="rate-btn-secondary" href="/printflow/customer/orders.php?tab=completed">Skip for now</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    const stars = Array.from(document.querySelectorAll('.rate-star-btn'));
    const ratingInput = document.getElementById('ratingInput');
    const form = document.getElementById('ratingForm');
    const imageInput = document.getElementById('imageInput');
    const imagePreview = document.getElementById('imagePreview');

    function paintStars(value) {
        stars.forEach((btn, idx) => {
            btn.classList.toggle('active', idx < value);
        });
    }

    stars.forEach((btn) => {
        btn.addEventListener('click', function () {
            const value = Number(this.dataset.value || 0);
            ratingInput.value = String(value);
            paintStars(value);
        });
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            const rating = Number(ratingInput.value || 0);
            if (rating < 1 || rating > 5) {
                e.preventDefault();
                alert('Please select a star rating first.');
            }
        });
    }

    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) {
                imagePreview.style.display = 'none';
                imagePreview.removeAttribute('src');
                return;
            }
            const reader = new FileReader();
            reader.onload = function (e) {
                imagePreview.src = e.target && e.target.result ? e.target.result : '';
                imagePreview.style.display = imagePreview.src ? 'block' : 'none';
            };
            reader.readAsDataURL(file);
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
