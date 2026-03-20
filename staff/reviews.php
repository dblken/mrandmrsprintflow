<?php
/**
 * Staff Reviews Page
 * Shows customer ratings and feedback for completed orders.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Staff', 'Admin']);
require_once __DIR__ . '/../includes/staff_pending_check.php';
ensure_ratings_table_exists();

$total_row = db_query("SELECT COUNT(*) AS total FROM ratings");
$total_items = (int)($total_row[0]['total'] ?? 0);

$sql = "
    SELECT
        r.id,
        r.order_id,
        r.service_type,
        r.rating,
        r.comment,
        r.image,
        r.created_at,
        c.first_name,
        c.last_name
    FROM ratings r
    INNER JOIN orders o ON o.order_id = r.order_id
    INNER JOIN customers c ON c.customer_id = r.customer_id
    ORDER BY r.created_at DESC
";
$reviews = db_query($sql) ?: [];

$service_rows = db_query("SELECT DISTINCT service_type FROM ratings WHERE service_type IS NOT NULL AND service_type != '' ORDER BY service_type ASC") ?: [];
$service_options = array_map(static fn($r) => (string)$r['service_type'], $service_rows);

function stars_text($value) {
    $v = max(1, min(5, (int)$value));
    return str_repeat('★', $v) . str_repeat('☆', 5 - $v);
}

$page_title = 'Customer Reviews - Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .rv-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; }
        .rv-toolbar { padding:16px; border-bottom:1px solid #f1f5f9; display:flex; gap:10px; flex-wrap:wrap; align-items:end; }
        .rv-group { display:flex; flex-direction:column; gap:6px; min-width:180px; }
        .rv-label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#64748b; font-weight:800; }
        .rv-input, .rv-select { border:1px solid #cbd5e1; border-radius:10px; padding:9px 11px; font-size:13px; min-height:38px; }
        .rv-btn { border:none; border-radius:10px; padding:10px 14px; font-size:13px; font-weight:700; cursor:pointer; }
        .rv-btn.primary { background:#0a2530; color:#fff; }
        .rv-btn.light { background:#f8fafc; color:#334155; border:1px solid #cbd5e1; text-decoration:none; display:inline-flex; align-items:center; }
        .rv-table-wrap { overflow:auto; }
        .rv-table { width:100%; border-collapse:separate; border-spacing:0; min-width:920px; }
        .rv-table th { background:#f8fafc; color:#64748b; font-size:11px; letter-spacing:.05em; text-transform:uppercase; font-weight:800; text-align:left; padding:12px 14px; border-bottom:1px solid #e5e7eb; }
        .rv-table td { padding:12px 14px; border-bottom:1px solid #f1f5f9; vertical-align:top; font-size:13px; color:#334155; }
        .rv-stars { color:#f59e0b; font-size:16px; letter-spacing:1px; white-space:nowrap; }
        .rv-comment { max-width:320px; line-height:1.5; white-space:normal; overflow-wrap:anywhere; }
        .rv-chip { display:inline-flex; align-items:center; border-radius:999px; border:1px solid #e2e8f0; padding:3px 10px; font-size:11px; font-weight:700; background:#f8fafc; color:#334155; }
        .rv-thumb { width:48px; height:48px; border-radius:8px; border:1px solid #e2e8f0; object-fit:cover; cursor:pointer; }
        .rv-empty { text-align:center; color:#94a3b8; font-size:14px; padding:34px 12px; }
        .rv-pager { padding:14px 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; }
        .rv-modal { position:fixed; inset:0; background:rgba(2,6,23,.66); z-index:200000; display:none; align-items:center; justify-content:center; padding:20px; }
        .rv-modal.open { display:flex; }
        .rv-modal img { max-width:90vw; max-height:84vh; border-radius:14px; border:2px solid #fff; box-shadow:0 20px 45px rgba(0,0,0,.5); }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php if (($_SESSION['user_type'] ?? '') === 'Admin'): ?>
        <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <?php else: ?>
        <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>
    <?php endif; ?>

    <div class="main-content">
        <header>
            <h1 class="page-title">Customer Reviews</h1>
        </header>
        <main>
            <div class="rv-card">
                <div class="rv-toolbar">
                    <div class="rv-group" style="min-width:220px;">
                        <label class="rv-label" for="search">Search customer/order</label>
                        <input id="search" class="rv-input" type="text" value="" placeholder="Type customer name or order #">
                    </div>
                    <div class="rv-group">
                        <label class="rv-label" for="service_type">Service Type</label>
                        <select id="service_type" class="rv-select">
                            <option value="">All Services</option>
                            <?php foreach ($service_options as $service): ?>
                                <option value="<?php echo htmlspecialchars($service); ?>">
                                    <?php echo htmlspecialchars($service); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rv-group" style="min-width:120px;">
                        <label class="rv-label" for="rating">Rating</label>
                        <select id="rating" class="rv-select">
                            <option value="0">All Stars</option>
                            <?php for ($r = 5; $r >= 1; $r--): ?>
                                <option value="<?php echo $r; ?>"><?php echo $r; ?> Star<?php echo $r > 1 ? 's' : ''; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="button" id="resetFiltersBtn" class="rv-btn light">Reset</button>
                </div>

                <div class="rv-table-wrap">
                    <table class="rv-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Service</th>
                                <th>Customer</th>
                                <th>Rating</th>
                                <th>Comment</th>
                                <th>Image</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="reviewsTbody">
                            <?php if (empty($reviews)): ?>
                                <tr><td colspan="7" class="rv-empty">No reviews found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($reviews as $review): ?>
                                    <tr class="review-row"
                                        data-order-id="<?php echo (int)$review['order_id']; ?>"
                                        data-customer="<?php echo htmlspecialchars(strtolower(trim(($review['first_name'] ?? '') . ' ' . ($review['last_name'] ?? '')))); ?>"
                                        data-service="<?php echo htmlspecialchars(strtolower((string)($review['service_type'] ?: 'General Service'))); ?>"
                                        data-rating="<?php echo (int)$review['rating']; ?>">
                                        <td>
                                            <a href="/printflow/staff/customizations.php?order_id=<?php echo (int)$review['order_id']; ?>" style="font-weight:800;color:#0a2530;text-decoration:none;">
                                                #ORD-<?php echo str_pad((string)$review['order_id'], 5, '0', STR_PAD_LEFT); ?>
                                            </a>
                                        </td>
                                        <td><span class="rv-chip"><?php echo htmlspecialchars($review['service_type'] ?: 'General Service'); ?></span></td>
                                        <td><?php echo htmlspecialchars(trim(($review['first_name'] ?? '') . ' ' . ($review['last_name'] ?? ''))); ?></td>
                                        <td><span class="rv-stars"><?php echo htmlspecialchars(stars_text((int)$review['rating'])); ?></span></td>
                                        <td class="rv-comment"><?php echo nl2br(htmlspecialchars((string)($review['comment'] ?? ''))); ?></td>
                                        <td>
                                            <?php if (!empty($review['image'])): ?>
                                                <img src="<?php echo htmlspecialchars($review['image']); ?>" class="rv-thumb" alt="Review image" onclick="openReviewImage('<?php echo htmlspecialchars($review['image'], ENT_QUOTES); ?>')">
                                            <?php else: ?>
                                                <span style="color:#94a3b8;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo format_datetime($review['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="rv-pager">
                    <div id="reviewsCountText" style="font-size:12px; color:#64748b;">Showing <?php echo count($reviews); ?> of <?php echo $total_items; ?> review(s)</div>
                </div>
            </div>
        </main>
    </div>
</div>

<div id="reviewImageModal" class="rv-modal" onclick="closeReviewImage()">
    <img id="reviewImageFull" src="" alt="Review Image">
</div>

<script>
function openReviewImage(src) {
    const modal = document.getElementById('reviewImageModal');
    const img = document.getElementById('reviewImageFull');
    img.src = src || '';
    modal.classList.add('open');
}
function closeReviewImage() {
    const modal = document.getElementById('reviewImageModal');
    const img = document.getElementById('reviewImageFull');
    modal.classList.remove('open');
    img.src = '';
}

function applyLiveReviewFilters() {
    const tbody = document.getElementById('reviewsTbody');
    if (!tbody) return;
    const searchEl = document.getElementById('search');
    const serviceEl = document.getElementById('service_type');
    const ratingEl = document.getElementById('rating');
    const countEl = document.getElementById('reviewsCountText');

    const q = String(searchEl?.value || '').trim().toLowerCase();
    const service = String(serviceEl?.value || '').trim().toLowerCase();
    const rating = Number(ratingEl?.value || 0);
    const rows = Array.from(tbody.querySelectorAll('.review-row'));
    let shown = 0;

    rows.forEach((row) => {
        const orderId = String(row.dataset.orderId || '');
        const customer = String(row.dataset.customer || '');
        const rowService = String(row.dataset.service || '');
        const rowRating = Number(row.dataset.rating || 0);

        const matchSearch = !q || orderId.includes(q) || customer.includes(q);
        const matchService = !service || rowService === service;
        const matchRating = !rating || rowRating === rating;
        const visible = matchSearch && matchService && matchRating;
        row.style.display = visible ? '' : 'none';
        if (visible) shown += 1;
    });

    let emptyRow = tbody.querySelector('.rv-empty-live-row');
    if (shown === 0) {
        if (!emptyRow) {
            emptyRow = document.createElement('tr');
            emptyRow.className = 'rv-empty-live-row';
            emptyRow.innerHTML = '<td colspan="7" class="rv-empty">No reviews found for the current filter.</td>';
            tbody.appendChild(emptyRow);
        }
    } else if (emptyRow) {
        emptyRow.remove();
    }

    if (countEl) {
        const total = rows.length;
        countEl.textContent = `Showing ${shown} of ${total} review(s)`;
    }
}

let reviewsFilterTimer = null;
function scheduleLiveReviewFilter() {
    if (reviewsFilterTimer) window.clearTimeout(reviewsFilterTimer);
    reviewsFilterTimer = window.setTimeout(applyLiveReviewFilters, 100);
}

document.addEventListener('DOMContentLoaded', function () {
    const searchEl = document.getElementById('search');
    const serviceEl = document.getElementById('service_type');
    const ratingEl = document.getElementById('rating');
    const resetBtn = document.getElementById('resetFiltersBtn');
    if (searchEl) searchEl.addEventListener('input', scheduleLiveReviewFilter);
    if (serviceEl) serviceEl.addEventListener('change', applyLiveReviewFilters);
    if (ratingEl) ratingEl.addEventListener('change', applyLiveReviewFilters);
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (searchEl) searchEl.value = '';
            if (serviceEl) serviceEl.value = '';
            if (ratingEl) ratingEl.value = '0';
            applyLiveReviewFilters();
        });
    }
    applyLiveReviewFilters();
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeReviewImage();
});
</script>
</body>
</html>
