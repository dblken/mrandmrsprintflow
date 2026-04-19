<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');
ensure_ratings_table_exists();

if (!defined('BASE_URL')) define('BASE_URL', '/printflow');

$customer_id = get_user_id();
$order_id = (int)($_GET['order_id'] ?? 0);

if ($order_id <= 0) {
	redirect(BASE_URL . '/customer/orders.php?tab=completed');
}

$review_cols_raw = db_query("SHOW COLUMNS FROM reviews") ?: [];
$review_cols = array_map(static function ($col) {
	return (string)($col['Field'] ?? '');
}, $review_cols_raw);
$review_cols = array_filter($review_cols, static fn($v) => $v !== '');
$review_user_col = in_array('user_id', $review_cols, true) ? 'user_id' : (in_array('customer_id', $review_cols, true) ? 'customer_id' : 'user_id');
$review_message_col = in_array('comment', $review_cols, true) ? 'comment' : (in_array('message', $review_cols, true) ? 'message' : 'comment');
$review_has_service = in_array('service_type', $review_cols, true);

$select_cols = "id, rating, {$review_message_col} AS review_message, created_at";
if ($review_has_service) {
	$select_cols .= ", service_type";
}

$review_rows = db_query(
	"SELECT {$select_cols} FROM reviews WHERE order_id = ? AND {$review_user_col} = ? LIMIT 1",
	'ii',
	[$order_id, $customer_id]
);

$review = !empty($review_rows) ? $review_rows[0] : null;
$images = [];
if ($review && !empty($review['id'])) {
	$images = db_query("SELECT image_path FROM review_images WHERE review_id = ?", 'i', [(int)$review['id']]) ?: [];
}

$page_title = 'Review - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen py-10" style="background:#ffffff;">
	<div class="container mx-auto px-4" style="max-width: 900px;">
		<div style="display:flex; align-items:center; justify-content:space-between; gap: 1rem; margin-bottom: 1.5rem;">
			<a href="<?php echo BASE_URL; ?>/customer/orders.php?tab=completed" class="btn-secondary" style="padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
				<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
				Back
			</a>
			<h1 style="margin:0; font-size: 1.4rem; font-weight: 800; color: #0f172a;">Your Review</h1>
			<div style="font-size: 0.9rem; color:#64748b; font-weight:700;">Order #<?php echo (int)$order_id; ?></div>
		</div>

		<?php if (!$review): ?>
			<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem;">
				<p style="margin:0; font-weight:700; color:#0f172a;">No review found for this order yet.</p>
				<p style="margin:0.5rem 0 0; color:#64748b;">You can leave a review from the order rating page.</p>
				<a href="<?php echo BASE_URL; ?>/customer/rate_order.php?order_id=<?php echo (int)$order_id; ?>" class="btn-primary" style="margin-top: 1rem; display:inline-block; text-decoration:none;">Rate Order</a>
			</div>
		<?php else: ?>
			<div style="background:#ffffff; border:1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem;">
				<div style="display:flex; align-items:center; gap: 0.75rem; margin-bottom: 1rem;">
					<div style="font-size: 1.1rem; font-weight: 800; color:#0f172a;">Rating:</div>
					<div style="font-size: 1.1rem; font-weight: 800; color:#f59e0b;">
						<?php echo str_repeat('★', (int)($review['rating'] ?? 0)); ?>
						<?php echo str_repeat('☆', max(0, 5 - (int)($review['rating'] ?? 0))); ?>
					</div>
				</div>

				<?php if (!empty($review['service_type'])): ?>
					<div style="font-size:0.9rem; color:#475569; font-weight:700; margin-bottom: 0.75rem;">
						Service: <?php echo htmlspecialchars($review['service_type']); ?>
					</div>
				<?php endif; ?>

				<div style="font-size:0.95rem; color:#0f172a; line-height:1.6; font-weight:600; white-space:pre-wrap;">
					<?php echo htmlspecialchars((string)($review['review_message'] ?? '')); ?>
				</div>

				<?php if (!empty($images)): ?>
					<div style="margin-top: 1.25rem;">
						<div style="font-size:0.8rem; font-weight:800; color:#64748b; text-transform:uppercase; margin-bottom:0.5rem;">Photos</div>
						<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.75rem;">
							<?php foreach ($images as $img): ?>
								<div style="border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#f8fafc;">
									<img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="Review image" style="width:100%; height:100%; object-fit:cover; display:block;">
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
