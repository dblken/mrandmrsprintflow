<?php
/**
 * Gate: require customer ID to be verified before proceeding.
 * Include this after require_role('Customer').
 */
if (get_user_type() === 'Customer' && !is_customer_id_verified()) {
    $id_status = 'None';
    $cid = get_user_id();
    if ($cid) {
        $r = db_query("SELECT id_status FROM customers WHERE customer_id = ?", 'i', [$cid]);
        $id_status = $r[0]['id_status'] ?? 'None';
    }

    $is_pending = $id_status === 'Pending';
    $msg = $is_pending
        ? 'Your ID is currently under review. You can place orders once it is approved.'
        : 'Please submit a valid ID before placing an order. This helps us keep every PrintFlow order secure.';

    $base = defined('BASE_URL')
        ? rtrim((string)BASE_URL, '/')
        : (defined('BASE_PATH') ? rtrim((string)BASE_PATH, '/') : '');

    $page_title = 'ID Verification Required - PrintFlow';
    $use_customer_css = true;
    require_once __DIR__ . '/header.php';
    ?>
    <div style="min-height:68vh;background:#f8fafc;"></div>

    <div id="id-required-backdrop" style="position:fixed;inset:0;background:rgba(15,23,42,.62);z-index:100000;display:flex;align-items:center;justify-content:center;padding:1rem;">
        <div role="dialog" aria-modal="true" aria-labelledby="id-required-title" style="width:min(460px,100%);background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 24px 70px rgba(15,23,42,.28);padding:28px;text-align:center;">
            <div style="width:64px;height:64px;border-radius:18px;background:#ecfeff;color:#0e7490;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
                <svg width="30" height="30" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/>
                </svg>
            </div>
            <h2 id="id-required-title" style="font-size:1.35rem;font-weight:800;color:#0f172a;margin:0 0 8px;">ID Verification Required</h2>
            <p style="font-size:.95rem;line-height:1.6;color:#64748b;margin:0 0 22px;"><?php echo htmlspecialchars($msg); ?></p>
            <?php if (!$is_pending): ?>
                <a href="<?php echo htmlspecialchars($base); ?>/customer/profile.php#section-id" style="display:flex;align-items:center;justify-content:center;min-height:44px;background:#0891b2;color:#fff;border-radius:10px;text-decoration:none;font-weight:800;margin-bottom:10px;">Submit ID</a>
            <?php else: ?>
                <a href="<?php echo htmlspecialchars($base); ?>/customer/profile.php#section-id" style="display:flex;align-items:center;justify-content:center;min-height:44px;background:#0891b2;color:#fff;border-radius:10px;text-decoration:none;font-weight:800;margin-bottom:10px;">View ID Status</a>
            <?php endif; ?>
            <a href="<?php echo htmlspecialchars($base); ?>/customer/services.php" style="display:flex;align-items:center;justify-content:center;min-height:42px;color:#475569;text-decoration:none;font-weight:700;">Back to Services</a>
        </div>
    </div>
    <script>
    (function() {
        var modalTitle = document.getElementById('id-required-title');
        if (modalTitle && typeof modalTitle.focus === 'function') {
            modalTitle.setAttribute('tabindex', '-1');
            modalTitle.focus();
        }
    })();
    </script>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}
