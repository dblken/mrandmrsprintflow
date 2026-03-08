<?php
/**
 * FAQ Page â€” PrintFlow
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$faqs = db_query("SELECT * FROM faq WHERE status = 'Activated' ORDER BY faq_id ASC");

$shop_cfg_path = __DIR__ . '/assets/uploads/shop_config.json';
$shop_cfg = file_exists($shop_cfg_path) ? (json_decode(file_get_contents($shop_cfg_path), true) ?: []) : [];
$support_email = htmlspecialchars($shop_cfg['email'] ?? 'support@printflow.com');

$page_title = 'FAQ - PrintFlow';
$use_landing_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ============================================================
     HERO
     ============================================================ -->
<section class="lp-mini-hero" style="padding-top:0;padding-bottom:4rem;">
    <?php $nav_header_class = 'lp-hero-nav sticky top-0 z-50'; require __DIR__ . '/../includes/nav-header.php'; ?>
    <div class="lp-mini-hero-inner" style="padding-top:4rem;">
        <div class="lp-wrap" style="text-align:center;">
            <p class="lp-hero-tag" style="margin-bottom:1.25rem;">âœ¦ Help Center</p>
            <h1 style="font-size:clamp(2.2rem,5vw,3.4rem);font-weight:800;color:#fff;letter-spacing:-0.03em;margin-bottom:1rem;line-height:1.1;">
                Frequently Asked <span style="color:var(--lp-accent-l);">Questions</span>
            </h1>
            <p style="font-size:1.0625rem;color:var(--lp-muted);max-width:520px;margin:0 auto;line-height:1.7;">
                Quick answers to common questions about our printing services, ordering, and pickup.
            </p>
        </div>
    </div>
</section>

<!-- ============================================================
     FAQ ACCORDION â€” clean minimal style (Image 2)
     ============================================================ --><style>[x-cloak]{display:none!important}</style><section style="background:#fff;padding:4rem 0 5rem;">
    <div style="max-width:760px;margin:0 auto;padding:0 1.5rem;">

        <!-- Section label -->
        <div style="text-align:center;margin-bottom:2.75rem;">
            <p style="font-size:.8rem;font-weight:700;color:#32a1c4;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.5rem;">Support</p>
            <h2 style="font-size:1.75rem;font-weight:400;color:#374151;margin:0;">
                Frequently Asked <strong style="font-weight:800;color:#111827;">Questions</strong>
            </h2>
        </div>

        <?php if (empty($faqs)): ?>
        <div style="text-align:center;padding:3rem 2rem;border:1px solid #e5e7eb;border-radius:1rem;background:#fafafa;">
            <svg style="width:48px;height:48px;color:#d1d5db;margin:0 auto 1rem;display:block;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p style="color:#6b7280;font-size:1rem;">No questions available yet. Check back soon.</p>
        </div>
        <?php else: ?>

        <!-- FAQ List -->
        <div x-data="{ open: null }">
            <?php foreach ($faqs as $idx => $faq): ?>
            <div style="border-bottom:1px solid #e5e7eb;">
                <button type="button"
                        @click="open = (open === <?php echo $idx; ?>) ? null : <?php echo $idx; ?>"
                        style="width:100%;display:flex;justify-content:space-between;align-items:center;padding:1.25rem 0;background:none;border:none;cursor:pointer;text-align:left;gap:1.25rem;"
                        :style="open === <?php echo $idx; ?> ? 'color:#111827' : 'color:#374151'">
                    <span style="font-size:.9875rem;font-weight:500;line-height:1.5;flex:1;"><?php echo htmlspecialchars($faq['question']); ?></span>
                    <span style="flex-shrink:0;width:28px;height:28px;border-radius:50%;border:1px solid #d1d5db;display:flex;align-items:center;justify-content:center;transition:all .25s;"
                          :style="open === <?php echo $idx; ?> ? 'border-color:#32a1c4;background:#eef7fa;' : ''">
                        <svg style="width:13px;height:13px;transition:transform .3s;color:#6b7280;"
                             :style="open === <?php echo $idx; ?> ? 'transform:rotate(180deg);color:#32a1c4;' : ''"
                             fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </span>
                </button>
                <div x-show="open === <?php echo $idx; ?>" x-cloak
                     style="padding:0 0 1.25rem;color:#6b7280;font-size:.9375rem;line-height:1.75;padding-right:3rem;">
                    <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>

        <!-- Still have questions -->
        <div style="margin-top:3.5rem;text-align:center;padding:2.5rem 2rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:1.25rem;">
            <div style="width:52px;height:52px;background:#eef7fa;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.125rem;">
                <svg style="width:24px;height:24px;color:#32a1c4;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
            </div>
            <h3 style="font-size:1.125rem;font-weight:700;color:#111827;margin:0 0 .5rem;">Still have questions?</h3>
            <p style="color:#6b7280;margin:0 0 1.5rem;font-size:.9375rem;">Can&rsquo;t find what you&rsquo;re looking for? Our team is happy to help.</p>
            <a href="mailto:<?php echo $support_email; ?>"
               style="display:inline-flex;align-items:center;gap:.5rem;background:#32a1c4;color:#fff;padding:.7rem 1.75rem;border-radius:.625rem;font-size:.9375rem;font-weight:700;transition:background .2s;"
               onmouseover="this.style.background='#2a82a3'" onmouseout="this.style.background='#32a1c4'">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Contact Support
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
