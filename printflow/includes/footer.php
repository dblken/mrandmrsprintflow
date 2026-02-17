</main>

    <!-- Footer: layout and design (self-contained so it always displays correctly) -->
    <style>
        .ft-footer { width: 100%; background: #1e293b; color: #e2e8f0; margin-top: 2.5rem; box-sizing: border-box; }
        .ft-wrap { max-width: 1100px; margin: 0 auto; padding: 2.5rem 1.5rem; box-sizing: border-box; }
        .ft-grid { display: grid; grid-template-columns: 1fr; gap: 2rem; }
        @media (min-width: 768px) { .ft-grid { grid-template-columns: repeat(4, 1fr); gap: 2.5rem; } }
        .ft-brand { font-size: 1.25rem; font-weight: 700; color: #a5b4fc; margin: 0 0 0.5rem 0; }
        .ft-desc { font-size: 0.875rem; color: #94a3b8; line-height: 1.55; margin: 0; max-width: 260px; }
        .ft-title { font-size: 0.9375rem; font-weight: 700; color: #ffffff; margin: 0 0 1rem 0; text-transform: uppercase; letter-spacing: 0.03em; }
        .ft-list { list-style: none; padding: 0; margin: 0; }
        .ft-list li { margin-bottom: 0.5rem; }
        .ft-list a { font-size: 0.875rem; color: #94a3b8; text-decoration: none; }
        .ft-list a:hover { color: #c7d2fe; }
        .ft-list-item { font-size: 0.875rem; color: #94a3b8; display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem; }
        .ft-list-item .ft-ico { flex-shrink: 0; width: 1em; font-size: 0.875rem; color: #a5b4fc; }
        .ft-social { display: flex; gap: 0.75rem; margin-top: 1rem; flex-wrap: wrap; }
        .ft-social a { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; background: rgba(255,255,255,0.08); color: #e2e8f0; border-radius: 50%; text-decoration: none; transition: background 0.2s, color 0.2s; }
        .ft-social a:hover { background: #4f46e5; color: #fff; }
        .ft-social svg { width: 18px; height: 18px; display: block; }
        .ft-hr { border: 0; border-top: 1px solid rgba(255,255,255,0.12); margin: 2rem 0 1.25rem 0; }
        .ft-bottom { display: flex; flex-direction: column; gap: 0.5rem; text-align: center; font-size: 0.8125rem; color: #94a3b8; }
        @media (min-width: 768px) { .ft-bottom { flex-direction: row; justify-content: space-between; align-items: center; text-align: left; } }
    </style>
    <footer class="ft-footer">
        <div class="ft-wrap">
            <div class="ft-grid">
                <div>
                    <h3 class="ft-brand">PrintFlow</h3>
                    <p class="ft-desc">Your trusted printing shop for tarpaulins, t-shirts, stickers, and custom designs. Quality prints, delivered on time.</p>
                    <div class="ft-social">
                        <a href="#" aria-label="Facebook"><svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
                        <a href="#" aria-label="Twitter"><svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg></a>
                        <a href="#" aria-label="Instagram"><svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.268 4.771 1.691 5.077 4.907.06 1.281.076 1.665.076 4.849 0 3.185-.015 3.569-.074 4.814-.306 3.218-1.825 4.634-5.066 4.921-1.277.058-1.649.07-4.859.07-3.211 0-3.586-.012-4.859-.074-3.302-.287-4.771-1.697-5.077-4.907-.06-1.281-.076-1.665-.076-4.849 0-3.185.015-3.569.074-4.814.306-3.218 1.825-4.634 5.066-4.921 1.277-.058 1.649-.07 4.859-.07zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg></a>
                    </div>
                </div>
                <div>
                    <h3 class="ft-title">Quick Links</h3>
                    <ul class="ft-list">
                        <li><a href="<?php echo $url_products; ?>">Products</a></li>
                        <li><a href="<?php echo $url_faq; ?>">FAQ</a></li>
                        <?php if (!$is_logged_in): ?>
                        <li><a href="#" data-auth-modal="login">Login</a></li>
                        <li><a href="#" data-auth-modal="register">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h3 class="ft-title">Our Services</h3>
                    <ul class="ft-list">
                        <li class="ft-list-item"><span class="ft-ico">✓</span> Tarpaulin Printing</li>
                        <li class="ft-list-item"><span class="ft-ico">✓</span> T-shirt Printing</li>
                        <li class="ft-list-item"><span class="ft-ico">✓</span> Stickers & Decals</li>
                        <li class="ft-list-item"><span class="ft-ico">✓</span> Sintraboard Standees</li>
                        <li class="ft-list-item"><span class="ft-ico">✓</span> Custom Layouts</li>
                    </ul>
                </div>
                <div>
                    <h3 class="ft-title">Contact</h3>
                    <ul class="ft-list">
                        <li class="ft-list-item"><span class="ft-ico">✉</span> <a href="mailto:support@printflow.com">support@printflow.com</a></li>
                        <li class="ft-list-item"><span class="ft-ico">☎</span> <a href="tel:+631234567890">+63 123 456 7890</a></li>
                        <li class="ft-list-item"><span class="ft-ico">⌖</span> Metro Manila, Philippines</li>
                    </ul>
                </div>
            </div>
            <hr class="ft-hr">
            <div class="ft-bottom">
                <p>&copy; <?php echo date('Y'); ?> PrintFlow. All rights reserved.</p>
                <p>Made with ♥ for quality printing</p>
            </div>
        </div>
    </footer>

    <?php if (!$is_logged_in): ?>
    <?php
    require_once __DIR__ . '/google-oauth-config.php';
    $google_client_id = defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== '' ? GOOGLE_CLIENT_ID : null;
    require_once __DIR__ . '/auth-modals.php';
    ?>
    <?php endif; ?>

    <!-- Alpine.js for dropdowns -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <?php if (!empty($use_landing_css)): ?>
    <!-- Hero nav: hide header on scroll; scroll hint at bottom; scroll-to-top lower right -->
    <script>
    (function() {
        var header = document.getElementById('main-header');
        var hint = document.getElementById('lp-scroll-hint');
        var scrollTopBtn = document.getElementById('lp-scroll-top');
        var hideThreshold = 120;
        var showThreshold = 50;
        var scrollTopShowAt = 200;
        function update() {
            var y = window.scrollY;
            if (header && header.classList.contains('lp-hero-nav')) {
                if (y > hideThreshold) header.classList.add('lp-header-hidden');
                else if (y <= showThreshold) header.classList.remove('lp-header-hidden');
            }
            if (hint) {
                if (y > 80) hint.classList.add('lp-scroll-hint-hidden');
                else hint.classList.remove('lp-scroll-hint-hidden');
            }
            if (scrollTopBtn) {
                if (y > scrollTopShowAt) scrollTopBtn.classList.remove('lp-scroll-top-hidden');
                else scrollTopBtn.classList.add('lp-scroll-top-hidden');
            }
        }
        if (scrollTopBtn) {
            scrollTopBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }
        window.addEventListener('scroll', update, { passive: true });
        update();
    })();
    </script>
    <?php endif; ?>

    <!-- PWA -->
    <script src="<?php echo $base_url; ?>/public/assets/js/pwa.js"></script>
</body>
</html>
