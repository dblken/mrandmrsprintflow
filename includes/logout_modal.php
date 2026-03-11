<?php
/**
 * Logout confirmation modal. Include once per layout (header/footer or sidebar).
 * Logout links with data-logout-confirm will open this modal instead of navigating directly.
 */
$logout_modal_logout_url = isset($url_logout) ? $url_logout : (defined('BASE_URL') ? BASE_URL . '/public/logout.php' : '/printflow/public/logout.php');
?>
<div id="logout-modal-backdrop" class="logout-modal-backdrop" aria-hidden="true"></div>
<div id="logout-modal" class="logout-modal" role="dialog" aria-labelledby="logout-modal-title" aria-modal="true" aria-hidden="true">
    <div class="logout-modal-content">
        <h2 id="logout-modal-title" class="logout-modal-title">Logout</h2>
        <p class="logout-modal-text">Are you sure you want to logout?</p>
        <div class="logout-modal-actions">
            <button type="button" id="logout-modal-cancel" class="logout-modal-btn logout-modal-btn-cancel">Cancel</button>
            <a href="<?php echo htmlspecialchars($logout_modal_logout_url); ?>" id="logout-modal-confirm" class="logout-modal-btn logout-modal-btn-confirm">Yes, Logout</a>
        </div>
    </div>
</div>
<style>
.logout-modal-backdrop {
    position: fixed; inset: 0; z-index: 9998;
    background: rgba(15, 23, 42, 0.5);
    opacity: 0; visibility: hidden;
    transition: opacity 0.2s ease, visibility 0.2s ease;
}
.logout-modal-backdrop.is-open { opacity: 1; visibility: visible; }
.logout-modal {
    position: fixed; left: 50%; top: 50%;
    transform: translate(-50%, -50%);
    z-index: 9999;
    width: 90%; max-width: 24rem;
    background: #fff;
    border-radius: 1rem;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    opacity: 0; visibility: hidden;
    transition: opacity 0.2s ease, visibility 0.2s ease;
}
.logout-modal.is-open { opacity: 1; visibility: visible; }
.logout-modal-content { padding: 1.5rem 1.5rem 1.25rem; }
.logout-modal-title { margin: 0 0 0.5rem; font-size: 1.25rem; font-weight: 700; color: #0f172a; }
.logout-modal-text { margin: 0 0 1.25rem; font-size: 0.9375rem; color: #64748b; }
.logout-modal-actions { display: flex; gap: 0.75rem; justify-content: flex-end; flex-wrap: wrap; }
.logout-modal-btn {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 0.5rem 1rem; font-size: 0.9375rem; font-weight: 500;
    border-radius: 0.5rem; cursor: pointer; text-decoration: none;
    border: none; transition: background 0.2s, color 0.2s;
}
.logout-modal-btn-cancel {
    background: #f1f5f9; color: #475569;
}
.logout-modal-btn-cancel:hover { background: #e2e8f0; color: #1e293b; }
.logout-modal-btn-confirm {
    background: #dc2626; color: #fff;
}
.logout-modal-btn-confirm:hover { background: #b91c1c; color: #fff; }
</style>
<script>
(function() {
    var backdrop = document.getElementById('logout-modal-backdrop');
    var modal = document.getElementById('logout-modal');
    var cancelBtn = document.getElementById('logout-modal-cancel');
    var confirmLink = document.getElementById('logout-modal-confirm');
    function openModal(url) {
        if (confirmLink) confirmLink.href = url || confirmLink.href;
        if (backdrop) backdrop.classList.add('is-open');
        if (modal) { modal.classList.add('is-open'); modal.setAttribute('aria-hidden', 'false'); }
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        if (backdrop) backdrop.classList.remove('is-open');
        if (modal) { modal.classList.remove('is-open'); modal.setAttribute('aria-hidden', 'true'); }
        document.body.style.overflow = '';
    }
    document.addEventListener('click', function(e) {
        var link = e.target.closest('[data-logout-confirm]');
        if (link && link.tagName === 'A' && link.href) {
            e.preventDefault();
            openModal(link.getAttribute('href'));
        }
    });
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (backdrop) backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) closeModal();
    });
})();
</script>
