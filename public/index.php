<?php
/**
 * Home Page / Landing Page
 * PrintFlow - Printing Shop PWA
 * Uses standalone landing.css for layout and colors.
 */

$page_title = 'PrintFlow - Your Trusted Printing Shop';
$use_landing_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<section class="lp-hero">
    <?php $nav_header_class = 'lp-hero-nav sticky top-0 z-50'; require __DIR__ . '/../includes/nav-header.php'; ?>
    <div class="lp-wrap">
        <div class="lp-hero-inner">
            <div class="lp-hero-content">
                <p class="lp-hero-tag">Professional Printing Solutions</p>
                <h1 class="lp-hero-title">Print Your Ideas <span>with Precision</span></h1>
                <p class="lp-hero-desc">Transform your creative vision into reality. High-quality printing for tarpaulins, apparel, stickers, and custom designs—delivered on time.</p>
                <div class="lp-hero-btns">
                    <?php if (!is_logged_in()): ?>
                        <a href="#" data-auth-modal="register" class="lp-btn lp-btn-primary">Get Started Free</a>
                        <a href="<?php echo $url_products; ?>" class="lp-btn lp-btn-outline">Browse Products</a>
                    <?php else: ?>
                        <a href="<?php echo strtolower($user_type); ?>/dashboard.php" class="lp-btn lp-btn-primary">Go to Dashboard</a>
                    <?php endif; ?>
                </div>
                <div class="lp-stats">
                    <div><p class="lp-stat-num">500+</p><p class="lp-stat-label">Happy Clients</p></div>
                    <div><p class="lp-stat-num">10K+</p><p class="lp-stat-label">Orders Completed</p></div>
                    <div><p class="lp-stat-num">24/7</p><p class="lp-stat-label">Support</p></div>
                </div>
            </div>
            <div class="lp-hero-visual">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            </div>
        </div>
        <a href="#lp-services" class="lp-scroll-hint" id="lp-scroll-hint" aria-label="Scroll to content">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
        </a>
        <a href="#main-content" class="lp-scroll-top lp-scroll-top-hidden" id="lp-scroll-top" aria-label="Scroll to top">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
        </a>
    </div>
</section>

<section class="lp-section" id="lp-services">
    <div class="lp-wrap">
        <div class="lp-heading-wrap">
            <p class="lp-heading-label">What We Offer</p>
            <h2 class="lp-heading">Our Premium Services</h2>
            <p class="lp-heading-desc">Quality printing solutions tailored to your needs. From custom designs to bulk orders.</p>
        </div>
        <div class="lp-cards">
            <div class="lp-card">
                <div class="lp-card-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg></div>
                <h3 class="lp-card-title">Apparel & Merch</h3>
                <p class="lp-card-text">Custom t-shirts, hoodies, and uniforms with premium fabric and long-lasting prints.</p>
                <a href="<?php echo $url_products; ?>?category=Apparel" class="lp-card-link">Learn more →</a>
            </div>
            <div class="lp-card">
                <div class="lp-card-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg></div>
                <h3 class="lp-card-title">Business Signage</h3>
                <p class="lp-card-text">Tarpaulins, standees, and banners. Weatherproof materials for indoor and outdoor use.</p>
                <a href="<?php echo $url_products; ?>?category=Signage" class="lp-card-link">Learn more →</a>
            </div>
            <div class="lp-card">
                <div class="lp-card-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg></div>
                <h3 class="lp-card-title">Stickers & Decals</h3>
                <p class="lp-card-text">Custom stickers, labels, and decals. Waterproof, durable, any size or shape.</p>
                <a href="<?php echo $url_products; ?>?category=Stickers" class="lp-card-link">Learn more →</a>
            </div>
        </div>
    </div>
</section>

<section class="lp-section lp-section-white">
    <div class="lp-wrap">
        <div class="lp-two-col">
            <div class="lp-order-2">
                <div class="lp-feature-box">
                    <div class="lp-feature-box-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                    <p class="lp-feature-box-title">Quality Guaranteed</p>
                </div>
            </div>
            <div class="lp-order-1">
                <p class="lp-heading-label">Why Choose Us</p>
                <h2 class="lp-heading">Why Choose PrintFlow?</h2>
                <p class="lp-heading-desc">We combine cutting-edge printing technology with expertise to deliver exceptional results every time.</p>
                <ul class="lp-list">
                    <li>
                        <div class="lp-list-icon indigo"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div>
                        <div><h4 class="lp-list-title">Premium Quality Materials</h4><p class="lp-list-desc">Finest materials for vibrant colors and long-lasting durability.</p></div>
                    </li>
                    <li>
                        <div class="lp-list-icon green"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></div>
                        <div><h4 class="lp-list-title">Fast Turnaround</h4><p class="lp-list-desc">Most orders ready within 24–48 hours.</p></div>
                    </li>
                    <li>
                        <div class="lp-list-icon amber"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                        <div><h4 class="lp-list-title">Affordable Pricing</h4><p class="lp-list-desc">Competitive rates, no hidden fees, bulk discounts.</p></div>
                    </li>
                </ul>
                <div class="lp-mt"><a href="<?php echo $url_products; ?>" class="lp-btn lp-btn-primary">Get Started Today</a></div>
            </div>
        </div>
    </div>
</section>

<section class="lp-section-dark">
    <div class="lp-wrap">
        <div class="lp-cta-inner">
            <h2 class="lp-cta-title">Ready to Bring Your Ideas to Life?</h2>
            <p class="lp-cta-desc">Join hundreds of satisfied customers who trust PrintFlow for their printing needs.</p>
            <div class="lp-cta-btns">
                <?php if (!is_logged_in()): ?>
                    <a href="#" data-auth-modal="register" class="lp-btn lp-btn-primary">Create Free Account</a>
                    <a href="<?php echo $url_products; ?>" class="lp-btn lp-btn-outline">View Products</a>
                <?php else: ?>
                    <a href="<?php echo $url_products; ?>" class="lp-btn lp-btn-primary">Browse Products</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
