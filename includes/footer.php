<?php
// Load configs for the footer
$_ft_shop_path   = __DIR__ . '/../public/assets/uploads/shop_config.json';
$_ft_footer_path = __DIR__ . '/../public/assets/uploads/footer_config.json';
$_ft_shop   = file_exists($_ft_shop_path)   ? (json_decode(file_get_contents($_ft_shop_path),   true) ?: []) : [];
$_ft_footer = file_exists($_ft_footer_path) ? (json_decode(file_get_contents($_ft_footer_path), true) ?: []) : [];

$_ft_name            = !empty($_ft_shop['name'])               ? htmlspecialchars($_ft_shop['name'])          : 'PrintFlow';
$_ft_tagline         = !empty($_ft_footer['tagline'])          ? $_ft_footer['tagline']                       : 'Your trusted printing partner.';
$_ft_email           = !empty($_ft_footer['email'])            ? $_ft_footer['email']    : (!empty($_ft_shop['email'])  ? $_ft_shop['email']  : '');
$_ft_phone           = !empty($_ft_footer['phone'])            ? $_ft_footer['phone']    : (!empty($_ft_shop['phone'])  ? $_ft_shop['phone']  : '');
$_ft_hours           = !empty($_ft_footer['hours'])            ? $_ft_footer['hours']    : '';
$_ft_services        = !empty($_ft_footer['services'])         ? $_ft_footer['services'] : [];
$_ft_socials         = !empty($_ft_footer['social_links'])     ? $_ft_footer['social_links'] : [];
$_ft_branch_addrs    = !empty($_ft_footer['branch_addresses']) ? $_ft_footer['branch_addresses'] : [];

/**
 * Detect social platform name + SVG icon path from a URL.
 * Returns ['label'=>'Facebook', 'icon'=>'<path...>'] or null.
 */
function _ft_detect_social(string $url): array {
    $icons = [
        'facebook'  => ['Facebook',  '<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>'],
        'instagram' => ['Instagram', '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.268 4.771 1.691 5.077 4.907.06 1.281.076 1.665.076 4.849 0 3.185-.015 3.569-.074 4.814-.306 3.218-1.825 4.634-5.066 4.921-1.277.058-1.649.07-4.859.07-3.211 0-3.586-.012-4.859-.074-3.302-.287-4.771-1.697-5.077-4.907-.06-1.281-.076-1.665-.076-4.849 0-3.185.015-3.569.074-4.814.306-3.218 1.825-4.634 5.066-4.921 1.277-.058 1.649-.07 4.859-.07zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>'],
        'twitter'   => ['Twitter',   '<path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>'],
        'x.com'     => ['X',         '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.736-8.835L1.254 2.25H8.08l4.253 5.622L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>'],
        'youtube'   => ['YouTube',   '<path d="M23.495 6.205a3.007 3.007 0 00-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 00.527 6.205a31.247 31.247 0 00-.522 5.805 31.247 31.247 0 00.522 5.783 3.007 3.007 0 002.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 002.088-2.088 31.247 31.247 0 00.5-5.783 31.247 31.247 0 00-.5-5.805zM9.609 15.601V8.408l6.264 3.602z"/>'],
        'tiktok'    => ['TikTok',    '<path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.34 6.34 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.87a8.18 8.18 0 004.78 1.52V7a4.85 4.85 0 01-1.01-.31z"/>'],
        'linkedin'  => ['LinkedIn',  '<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>'],
        'pinterest' => ['Pinterest', '<path d="M12 0C5.373 0 0 5.373 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 01.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.632-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/>'],
        'shopee'    => ['Shopee',    '<path d="M12 2a5 5 0 015 5H7a5 5 0 015-5zm8.5 6H3.5l1 12.5A1.5 1.5 0 006 22h12a1.5 1.5 0 001.5-1.5L20.5 8zm-8.5 3a3 3 0 100 6 3 3 0 000-6z"/>'],
    ];
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
    $host = preg_replace('/^www\./', '', $host);
    foreach ($icons as $domain => [$label, $path]) {
        if (strpos($host, $domain) !== false) {
            return ['label' => $label, 'icon' => $path];
        }
    }
    // Fallback: use host as label, no icon
    return ['label' => ucfirst(explode('.', $host)[0] ?? 'Link'), 'icon' => null];
}
?>
</main>

    <!-- Footer: layout and design (self-contained so it always displays correctly) -->
    <style>
        .ft-footer { width: 100%; background: #00151b; color: #e2e8f0; margin-top: 2.5rem; box-sizing: border-box; border-top: none; }
        .ft-wrap { max-width: 1100px; margin: 0 auto; padding: 2.5rem 1.5rem; box-sizing: border-box; }
        .ft-grid { display: grid; grid-template-columns: 1fr; gap: 2rem; }
        @media (min-width: 768px) { .ft-grid { grid-template-columns: repeat(4, 1fr); gap: 2.5rem; } }
        .ft-brand { font-size: 1.25rem; font-weight: 700; color: #53C5E0; margin: 0 0 0.5rem 0; }
        .ft-desc { font-size: 0.875rem; color: #94a3b8; line-height: 1.55; margin: 0; max-width: 260px; }
        .ft-title { font-size: 0.9375rem; font-weight: 700; color: #ffffff; margin: 0 0 1rem 0; text-transform: uppercase; letter-spacing: 0.03em; }
        .ft-list { list-style: none; padding: 0; margin: 0; }
        .ft-list li { margin-bottom: 0.5rem; }
        .ft-list a { font-size: 0.875rem; color: #94a3b8; text-decoration: none; }
        .ft-list a:hover { color: #53C5E0; }
        .ft-list-item { font-size: 0.875rem; color: #94a3b8; display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.625rem; line-height: 1.4; }
        .ft-ico-svg { flex-shrink: 0; width: 15px; height: 15px; color: #53C5E0; margin-top: 1px; }
        .ft-social { display: flex; gap: 0.75rem; margin-top: 1rem; flex-wrap: wrap; }
        .ft-social a { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; background: rgba(255,255,255,0.08); color: #e2e8f0; border-radius: 50%; text-decoration: none; transition: background 0.2s, color 0.2s; font-size: 0.75rem; font-weight: 700; }
        .ft-social a:hover { background: #32a1c4; color: #fff; }
        .ft-social svg { width: 18px; height: 18px; display: block; }
        .ft-hr { border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 2rem 0 1.25rem 0; }
        .ft-bottom { display: flex; flex-direction: column; gap: 0.5rem; text-align: center; font-size: 0.8125rem; color: #94a3b8; }
        @media (min-width: 768px) { .ft-bottom { flex-direction: row; justify-content: space-between; align-items: center; text-align: left; } }
    </style>
    <footer class="ft-footer">
        <div class="ft-wrap">
            <div class="ft-grid">
                <!-- Brand + Tagline + Socials -->
                <div>
                    <h3 class="ft-brand"><?php echo $_ft_name; ?></h3>
                    <p class="ft-desc"><?php echo htmlspecialchars($_ft_tagline); ?></p>
                    <?php if (!empty($_ft_socials)): ?>
                    <div class="ft-social">
                        <?php foreach ($_ft_socials as $_s):
                            $_surl    = htmlspecialchars($_s['url'] ?? '');
                            $_sdetect = _ft_detect_social($_s['url'] ?? '');
                            $_slabel  = htmlspecialchars($_sdetect['label']);
                            $_sicon   = $_sdetect['icon'];
                        ?>
                        <a href="<?php echo $_surl; ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo $_slabel; ?>">
                            <?php if ($_sicon): ?>
                                <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><?php echo $_sicon; ?></svg>
                            <?php else: ?>
                                <?php echo strtoupper(mb_substr($_slabel, 0, 2)); ?>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Links -->
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

                <!-- Services (dynamic from admin) -->
                <div>
                    <h3 class="ft-title">Our Services</h3>
                    <?php if (!empty($_ft_services)): ?>
                    <ul class="ft-list">
                        <?php foreach ($_ft_services as $_svc): ?>
                        <li class="ft-list-item">
                            <span class="ft-ico">✓</span>
                            <?php echo htmlspecialchars($_svc); ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="ft-desc" style="font-style:italic;opacity:.6;">No services listed yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Contact (dynamic) -->
                <div>
                    <h3 class="ft-title">Contact</h3>
                    <ul class="ft-list">
                        <?php if (!empty($_ft_email)): ?>
                        <li class="ft-list-item">
                            <svg class="ft-ico-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <a href="mailto:<?php echo htmlspecialchars($_ft_email); ?>"><?php echo htmlspecialchars($_ft_email); ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($_ft_phone)): ?>
                        <li class="ft-list-item">
                            <svg class="ft-ico-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <a href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/','',$_ft_phone)); ?>"><?php echo htmlspecialchars($_ft_phone); ?></a>
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($_ft_branch_addrs)): ?>
                            <?php foreach ($_ft_branch_addrs as $_ba): ?>
                            <li class="ft-list-item">
                                <svg class="ft-ico-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span><?php echo nl2br(htmlspecialchars($_ba['address'] ?? '')); ?></span>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if (!empty($_ft_hours)): ?>
                        <li class="ft-list-item">
                            <svg class="ft-ico-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <?php echo htmlspecialchars($_ft_hours); ?>
                        </li>
                        <?php endif; ?>
                        <?php if (empty($_ft_email) && empty($_ft_phone) && empty($_ft_branch_addrs)): ?>
                        <li class="ft-list-item" style="opacity:.5;font-style:italic;">No contact info set yet.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <hr class="ft-hr">
            <div class="ft-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo $_ft_name; ?>. All rights reserved.</p>
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

    <!-- Scroll to Top (all non-admin pages) -->
    <?php if (!is_admin() && !is_staff()): ?>
    <?php if (empty($use_landing_css)): ?>
    <style>
    #lp-scroll-top{position:fixed;bottom:2rem;right:2rem;width:2.75rem;height:2.75rem;background:#00232b;color:#53C5E0;border:1px solid rgba(83,197,224,0.3);border-radius:50%;display:flex;align-items:center;justify-content:center;z-index:40;transition:all .3s;cursor:pointer;text-decoration:none;}
    #lp-scroll-top svg{width:1.25rem;height:1.25rem;}
    #lp-scroll-top:hover{background:#32a1c4;color:#fff;box-shadow:0 0 18px rgba(50,161,196,0.45);}
    #lp-scroll-top.lp-scroll-top-hidden{opacity:0;transform:translateY(20px);pointer-events:none;}
    </style>
    <?php endif; ?>
    <a href="#" class="lp-scroll-top lp-scroll-top-hidden" id="lp-scroll-top" aria-label="Scroll to top">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
    </a>
    <?php endif; ?>

    <script>
    (function() {
        var isLanding = document.documentElement.classList.contains('lp-page');
        var header = document.getElementById('main-header');
        var hint = document.getElementById('lp-scroll-hint');
        var scrollTopBtn = document.getElementById('lp-scroll-top');
        var scrollTopShowAt = 200;
        function update() {
            var y = window.scrollY;
            if (isLanding && header && header.classList.contains('lp-hero-nav')) {
                if (y > 120) header.classList.add('lp-header-hidden');
                else if (y <= 50) header.classList.remove('lp-header-hidden');
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

    <!-- PWA -->
    <script src="<?php echo $base_url; ?>/public/assets/js/pwa.js"></script>
</body>
</html>
