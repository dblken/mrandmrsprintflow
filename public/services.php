<?php
/**
 * Public Services Page
 * PrintFlow - Printing Shop PWA
 */

$page_title = 'Our Services - PrintFlow';
$use_landing_css = true;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';
?>

<!-- ============================================================
     HERO — mini style, consistent with dark nav theme
     ============================================================ -->
<section class="lp-mini-hero" style="padding-top: 0; padding-bottom: 5rem;">
    <?php $nav_header_class = 'lp-hero-nav sticky top-0 z-50'; require __DIR__ . '/../includes/nav-header.php'; ?>
    <div class="lp-mini-hero-inner" style="padding-top: 4rem;">
        <div class="lp-wrap" style="text-align:center;">
            <p class="lp-hero-tag" style="margin-bottom:1.5rem;">✦ What We Offer</p>
            <h1 style="font-size:clamp(2.2rem,5vw,3.5rem); font-weight:800; color:#fff; letter-spacing:-0.03em; margin-bottom:1.25rem; line-height:1.1;">
                Premium <span style="color:var(--lp-accent-l);">Printing Services</span><br>Built for Your Brand
            </h1>
            <p style="font-size:1.0625rem; color:var(--lp-muted); max-width:620px; margin:0 auto 2.5rem; line-height:1.7;">
                From bold tarpaulins to crisp business cards — we deliver every order with precision, speed, and craftsmanship you can trust.
            </p>
            <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
                <a href="/printflow/public/products.php" class="lp-btn lp-btn-primary">Browse All Products</a>
                <?php if (!is_logged_in()): ?>
                    <a href="#" data-auth-modal="register" class="lp-btn lp-btn-outline">Get Started Free</a>
                <?php endif; ?>
            </div>

            <!-- Quick stats bar -->
            <div style="display:flex; justify-content:center; flex-wrap:wrap; gap:0; margin-top:3.5rem; border-top:1px solid var(--lp-border); padding-top:2.5rem;">
                <div style="padding:0 2.5rem; border-right:1px solid var(--lp-border);">
                    <p style="font-size:1.875rem; font-weight:800; color:#fff; line-height:1; margin-bottom:.3rem;">500+</p>
                    <p style="font-size:.8125rem; color:var(--lp-muted);">Happy Clients</p>
                </div>
                <div style="padding:0 2.5rem; border-right:1px solid var(--lp-border);">
                    <p style="font-size:1.875rem; font-weight:800; color:#fff; line-height:1; margin-bottom:.3rem;">10K+</p>
                    <p style="font-size:.8125rem; color:var(--lp-muted);">Orders Completed</p>
                </div>
                <div style="padding:0 2.5rem; border-right:1px solid var(--lp-border);">
                    <p style="font-size:1.875rem; font-weight:800; color:#fff; line-height:1; margin-bottom:.3rem;">6</p>
                    <p style="font-size:.8125rem; color:var(--lp-muted);">Service Categories</p>
                </div>
                <div style="padding:0 2.5rem;">
                    <p style="font-size:1.875rem; font-weight:800; color:#fff; line-height:1; margin-bottom:.3rem;">24/7</p>
                    <p style="font-size:.8125rem; color:var(--lp-muted);">Support Available</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     SERVICES GRID
     ============================================================ -->
<section class="lp-section" style="padding-top:5rem; padding-bottom:5rem;">
    <div class="lp-wrap">
        <div class="lp-heading-wrap">
            <p class="lp-heading-label">Our Specialties</p>
            <h2 class="lp-heading">Everything You Need to Print</h2>
            <p class="lp-heading-desc">Six dedicated service categories — each handled by specialists using the right technology for the job.</p>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px,1fr)); gap:1.75rem;">

            <!-- Apparel -->
            <div class="lp-card" style="display:flex; flex-direction:column;">
                <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem;">
                    <div class="lp-card-icon" style="flex-shrink:0; background:rgba(99,102,241,.15); color:#818cf8;">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                    </div>
                    <div>
                        <span style="font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#818cf8; background:rgba(99,102,241,.12); padding:.2rem .6rem; border-radius:999px;">Apparel</span>
                    </div>
                </div>
                <h3 class="lp-card-title" style="font-size:1.3rem; margin-bottom:.6rem;">Apparel & T-Shirts</h3>
                <p class="lp-card-text" style="flex:1;">Custom t-shirts, hoodies, polo shirts, and team uniforms. We use premium breathable fabric with DTF and Silkscreen printing for vibrant, long-lasting graphics that stay bold wash after wash.</p>
                <ul style="margin-bottom:1.5rem; display:flex; flex-direction:column; gap:.4rem;">
                    <li style="font-size:.875rem; color:var(--lp-muted); display:flex; align-items:center; gap:.5rem;"><span style="color:#818cf8;">✓</span> DTF &amp; Silkscreen printing</li>
                    <li style="font-size:.875rem; color:var(--lp-muted); display:flex; align-items:center; gap:.5rem;"><span style="color:#818cf8;">✓</span> Bulk discounts available</li>
                    <li style="font-size:.875rem; color:var(--lp-muted); display:flex; align-items:center; gap:.5rem;"><span style="color:#818cf8;">✓</span> Full-color &amp; spot color</li>
                </ul>
                <a href="/printflow/public/products.php?category=Apparel" class="lp-card-link" style="display:inline-flex; align-items:center; gap:.4rem; margin-top:auto;">
                    Explore Apparel <svg style="width:1rem;height:1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>

            <!-- Large Format Signage -->
            <div class="lp-card" style="display:flex; flex-direction:column;">
                <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem;">
                    <div class="lp-card-icon" style="flex-shrink:0; background:rgba(14,165,233,.15); color:#38bdf8;">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <div>
                        <span style="font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#38bdf8; background:rgba(14,165,233,.12); padding:.2rem .6rem; border-radius:999px;">Signage</span>
                    </div>
                </div>
                <h3 class="lp-card-title" style="font-size:1.3rem; margin-bottom:.6rem;">Large Format Signage</h3>
                <p class="lp-card-text" style="flex:1;">High-resolution tarpaulins, Sintraboard standees, and banners. Crafted with weatherproof UV-resistant inks and reinforced materials for demanding indoor and outdoor environments.</p>
                <ul style="margin-bottom:1.5rem; display:flex; flex-direction:column; gap:.4rem;">
                    <li style="font-size:.875rem; color:var(--lp-muted); display:flex; align-items:center; gap:.5rem;"><span style="color:#38bdf8;">✓</span> UV &amp; weatherproof inks</li>
                    <li style="font-size:.875rem; color:var(--lp-muted); display:flex; align-items:center; gap:.5rem;"><span style="color:#38bdf8;">✓</span> Custom sizes &amp; dimensions</li>
                    <li style="font-size:.875rem; color:var(--lp-muted); display:flex; align-items:center; gap:.5rem;"><span style="color:#38bdf8;">✓</span> With or without grommets</li>
                </ul>
                <a href="/printflow/public/products.php?category=Tarpaulin" class="lp-card-link" style="display:inline-flex; align-items:center; gap:.4rem; margin-top:auto; color:#38bdf8;">
                    Explore Signage <svg style="width:1rem;height:1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>

            <!-- Stickers & Decals -->
            <div class="lp-card" style="display:flex; flex-direction:column;">
                <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem;">
                    <div class="lp-card-icon" style="flex-shrink:0; background:rgba(16,185,129,.15); color:#34d399;">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    </div>
                    <div>
                        <span style="font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#34d399; background:rgba(16,185,129,.12); padding:.2rem .6rem; border-radius:999px;">Stickers</span>
                    </div>
                </div>
                <h3 class="lp-card-title" style="font-size:1.3rem; margin-bottom:.6rem;">Stickers & Decals</h3>
                <p class="lp-card-text" style="flex:1;">Custom die-cut stickers, product labels, and vehicle decals. 100% waterproof and smudge-proof — available in gloss, matte, holographic, or clear finishes for any surface.</p>
                <ul style="margin-bottom:1.5rem; display:flex; flex-direction:column; gap:.4rem;">
                    <li style="font-size:.875rem; color:var(--lp-muted); display:flex; align-items:center; gap:.5rem;"><span style="color:#34d399;">✓</span> Die-cut to any shape</li>
                    <li style="font-size:.875rem; color:var(--lp-muted); display:flex; align-items:center; gap:.5rem;"><span style="color:#34d399;">✓</span> Waterproof &amp; smudge-proof</li>
                    <li style="font-size:.875rem; color:var(--lp-muted); display:flex; align-items:center; gap:.5rem;"><span style="color:#34d399;">✓</span> Gloss, matte &amp; clear options</li>
                </ul>
                <a href="/printflow/public/products.php?category=Stickers" class="lp-card-link" style="display:inline-flex; align-items:center; gap:.4rem; margin-top:auto; color:#34d399;">
                    Explore Stickers <svg style="width:1rem;height:1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>

            <!-- Corporate Merchandise -->
            <div class="lp-card" style="display:flex; flex-direction:column;">
                <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem;">
                    <div class="lp-card-icon" style="flex-shrink:0; background:rgba(168,85,247,.15); color:#c084fc;">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                    </div>
                    <div>
                        <span style="font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#c084fc; background:rgba(168,85,247,.12); padding:.2rem .6rem; border-radius:999px;">Merchandise</span>
                    </div>
                </div>
                <h3 class="lp-card-title" style="font-size:1.3rem; margin-bottom:.6rem;">Corporate Merchandise</h3>
                <p class="lp-card-text" style="flex:1;">Branded mugs, keychains, tote bags, lanyards, and promotional giveaways. Perfect for corporate events, product launches, and building lasting brand awareness.</p>
                <ul style="margin-bottom:1.5rem; display:flex; flex-direction:column; gap:.4rem;">
                    <li style="font-size:.875rem; color:var(--lp-muted); display:flex; align-items:center; gap:.5rem;"><span style="color:#c084fc;">✓</span> Bulk corporate orders</li>
                    <li style="font-size:.875rem; color:var(--lp-muted); display:flex; align-items:center; gap:.5rem;"><span style="color:#c084fc;">✓</span> Custom logo &amp; branding</li>
                    <li style="font-size:.875rem; color:var(--lp-muted); display:flex; align-items:center; gap:.5rem;"><span style="color:#c084fc;">✓</span> Gift packaging available</li>
                </ul>
                <a href="/printflow/public/products.php?category=Merchandise" class="lp-card-link" style="display:inline-flex; align-items:center; gap:.4rem; margin-top:auto; color:#c084fc;">
                    Explore Merch <svg style="width:1rem;height:1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>

            <!-- Document Printing -->
            <div class="lp-card" style="display:flex; flex-direction:column;">
                <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem;">
                    <div class="lp-card-icon" style="flex-shrink:0; background:rgba(245,158,11,.15); color:#fbbf24;">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <span style="font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#fbbf24; background:rgba(245,158,11,.12); padding:.2rem .6rem; border-radius:999px;">Documents</span>
                    </div>
                </div>
                <h3 class="lp-card-title" style="font-size:1.3rem; margin-bottom:.6rem;">Document Printing</h3>
                <p class="lp-card-text" style="flex:1;">High-volume laser and inkjet printing for modules, office manuals, business cards, brochures, and flyers. Crisp text, accurate colors, and fast turnaround every time.</p>
                <ul style="margin-bottom:1.5rem; display:flex; flex-direction:column; gap:.4rem;">
                    <li style="font-size:.875rem; color:var(--lp-muted); display:flex; align-items:center; gap:.5rem;"><span style="color:#fbbf24;">✓</span> B&amp;W &amp; full-color options</li>
                    <li style="font-size:.875rem; color:var(--lp-muted); display:flex; align-items:center; gap:.5rem;"><span style="color:#fbbf24;">✓</span> Binding &amp; finishing</li>
                    <li style="font-size:.875rem; color:var(--lp-muted); display:flex; align-items:center; gap:.5rem;"><span style="color:#fbbf24;">✓</span> A4, A3 &amp; custom paper sizes</li>
                </ul>
                <a href="/printflow/public/products.php" class="lp-card-link" style="display:inline-flex; align-items:center; gap:.4rem; margin-top:auto; color:#fbbf24;">
                    Explore Documents <svg style="width:1rem;height:1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>

            <!-- Custom Design Service -->
            <div class="lp-card" style="display:flex; flex-direction:column;">
                <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem;">
                    <div class="lp-card-icon" style="flex-shrink:0; background:rgba(236,72,153,.15); color:#f472b6;">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    </div>
                    <div>
                        <span style="font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#f472b6; background:rgba(236,72,153,.12); padding:.2rem .6rem; border-radius:999px;">Design</span>
                    </div>
                </div>
                <h3 class="lp-card-title" style="font-size:1.3rem; margin-bottom:.6rem;">Custom Layout & Design</h3>
                <p class="lp-card-text" style="flex:1;">No artwork? No problem. Our graphic designers draft, layout, and perfect your vision from scratch — whether it's a logo, a banner layout, or a full brand package.</p>
                <ul style="margin-bottom:1.5rem; display:flex; flex-direction:column; gap:.4rem;">
                    <li style="font-size:.875rem; color:var(--lp-muted); display:flex; align-items:center; gap:.5rem;"><span style="color:#f472b6;">✓</span> Dedicated design team</li>
                    <li style="font-size:.875rem; color:var(--lp-muted); display:flex; align-items:center; gap:.5rem;"><span style="color:#f472b6;">✓</span> Revisions included</li>
                    <li style="font-size:.875rem; color:var(--lp-muted); display:flex; align-items:center; gap:.5rem;"><span style="color:#f472b6;">✓</span> Print-ready file delivery</li>
                </ul>
                <a href="/printflow/public/products.php" class="lp-card-link" style="display:inline-flex; align-items:center; gap:.4rem; margin-top:auto; color:#f472b6;">
                    Learn More <svg style="width:1rem;height:1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>

        </div>
    </div>
</section>

<!-- ============================================================
     HOW IT WORKS
     ============================================================ -->
<section class="lp-section-light" style="padding-top:5rem; padding-bottom:5rem;">
    <div class="lp-wrap">
        <div class="lp-heading-wrap">
            <p class="lp-heading-label">Simple Process</p>
            <h2 class="lp-heading">How It Works</h2>
            <p class="lp-heading-desc">Getting your prints has never been easier. Follow these four steps from order to delivery.</p>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:2rem; position:relative;">

            <!-- Step 1 -->
            <div style="text-align:center; position:relative;">
                <div style="width:4rem; height:4rem; border-radius:50%; background:rgba(83,197,224,.15); border:2px solid rgba(83,197,224,.3); display:flex; align-items:center; justify-content:center; margin:0 auto 1.5rem; position:relative; z-index:1;">
                    <span style="font-size:1.25rem; font-weight:800; color:var(--lp-accent-l);">01</span>
                </div>
                <h4 style="font-size:1.0625rem; font-weight:700; color:#1e293b; margin-bottom:.6rem;">Choose a Service</h4>
                <p style="font-size:.9rem; color:#475569; line-height:1.6;">Browse our catalog and select the product or service that fits your needs. Filter by category, size, or printing method.</p>
            </div>

            <!-- Step 2 -->
            <div style="text-align:center; position:relative;">
                <div style="width:4rem; height:4rem; border-radius:50%; background:rgba(83,197,224,.15); border:2px solid rgba(83,197,224,.3); display:flex; align-items:center; justify-content:center; margin:0 auto 1.5rem; position:relative; z-index:1;">
                    <span style="font-size:1.25rem; font-weight:800; color:var(--lp-accent-l);">02</span>
                </div>
                <h4 style="font-size:1.0625rem; font-weight:700; color:#1e293b; margin-bottom:.6rem;">Upload Your Design</h4>
                <p style="font-size:.9rem; color:#475569; line-height:1.6;">Upload your artwork or brief our design team. We accept PDF, PNG, AI, PSD, and most standard print formats.</p>
            </div>

            <!-- Step 3 -->
            <div style="text-align:center; position:relative;">
                <div style="width:4rem; height:4rem; border-radius:50%; background:rgba(83,197,224,.15); border:2px solid rgba(83,197,224,.3); display:flex; align-items:center; justify-content:center; margin:0 auto 1.5rem; position:relative; z-index:1;">
                    <span style="font-size:1.25rem; font-weight:800; color:var(--lp-accent-l);">03</span>
                </div>
                <h4 style="font-size:1.0625rem; font-weight:700; color:#1e293b; margin-bottom:.6rem;">Review & Confirm</h4>
                <p style="font-size:.9rem; color:#475569; line-height:1.6;">We send a proof for your approval. Once confirmed, our production team gets to work immediately.</p>
            </div>

            <!-- Step 4 -->
            <div style="text-align:center; position:relative;">
                <div style="width:4rem; height:4rem; border-radius:50%; background:rgba(83,197,224,.15); border:2px solid rgba(83,197,224,.3); display:flex; align-items:center; justify-content:center; margin:0 auto 1.5rem; position:relative; z-index:1;">
                    <span style="font-size:1.25rem; font-weight:800; color:var(--lp-accent-l);">04</span>
                </div>
                <h4 style="font-size:1.0625rem; font-weight:700; color:#1e293b; margin-bottom:.6rem;">Pick Up or Deliver</h4>
                <p style="font-size:.9rem; color:#475569; line-height:1.6;">Choose store pickup or have your order delivered. Track your order in real-time from your customer dashboard.</p>
            </div>

        </div>
    </div>
</section>

<!-- ============================================================
     WHY CHOOSE US — Two-column feature
     ============================================================ -->
<section class="lp-section" style="padding-top:5rem; padding-bottom:5rem;">
    <div class="lp-wrap">
        <div class="lp-two-col">

            <!-- Left: feature visual box -->
            <div class="lp-order-1">
                <div class="lp-feature-box">
                    <div class="lp-feature-box-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                    </div>
                    <h3 class="lp-feature-box-title">Quality Guaranteed</h3>
                    <p style="font-size:.95rem; color:var(--lp-muted); margin-top:.75rem; line-height:1.6;">Every order undergoes a strict quality-check before it leaves our shop — or we reprint it at no extra cost.</p>
                    <div style="display:flex; justify-content:center; gap:2rem; margin-top:2rem; padding-top:1.5rem; border-top:1px solid var(--lp-border);">
                        <div style="text-align:center;">
                            <p style="font-size:1.5rem; font-weight:800; color:var(--lp-accent-l);">99%</p>
                            <p style="font-size:.8rem; color:var(--lp-muted);">Satisfaction rate</p>
                        </div>
                        <div style="text-align:center;">
                            <p style="font-size:1.5rem; font-weight:800; color:var(--lp-accent-l);">&lt;24h</p>
                            <p style="font-size:.8rem; color:var(--lp-muted);">Rush turnaround</p>
                        </div>
                        <div style="text-align:center;">
                            <p style="font-size:1.5rem; font-weight:800; color:var(--lp-accent-l);">0%</p>
                            <p style="font-size:.8rem; color:var(--lp-muted);">Hidden fees</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: benefit list -->
            <div class="lp-order-2">
                <p class="lp-heading-label" style="text-align:left; margin-bottom:.5rem;">Why Choose Us</p>
                <h2 class="lp-heading" style="font-size:2rem; text-align:left; margin-bottom:1rem;">Print Partners You Can Rely On</h2>
                <p class="lp-heading-desc" style="text-align:left; margin:0 0 1.5rem;">Our clients come back because we deliver quality, transparency, and speed — every single time.</p>

                <ul class="lp-list">
                    <li>
                        <div class="lp-list-icon indigo">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <div>
                            <p class="lp-list-title">Fast Turnaround</p>
                            <p class="lp-list-desc">Standard orders ready in 1–3 business days. Rush options available for urgent deadlines.</p>
                        </div>
                    </li>
                    <li>
                        <div class="lp-list-icon green">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <p class="lp-list-title">Premium Materials</p>
                            <p class="lp-list-desc">We source only top-grade substrates and inks, ensuring every print looks exceptional and lasts.</p>
                        </div>
                    </li>
                    <li>
                        <div class="lp-list-icon amber">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </div>
                        <div>
                            <p class="lp-list-title">Real-Time Order Tracking</p>
                            <p class="lp-list-desc">Follow your order from submission to ready-for-pickup with live status updates in your dashboard.</p>
                        </div>
                    </li>
                </ul>

                <a href="/printflow/public/products.php" class="lp-btn lp-btn-primary lp-mt" style="display:inline-flex;">
                    Start Your Order
                    <svg style="width:1.1rem;height:1.1rem; margin-left:.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>

        </div>
    </div>
</section>

<!-- ============================================================
     PRINT TECHNOLOGIES STRIP
     ============================================================ -->
<section class="lp-section-light" style="padding-top:4rem; padding-bottom:4rem;">
    <div class="lp-wrap">
        <div class="lp-heading-wrap" style="margin-bottom:3rem;">
            <p class="lp-heading-label">Our Technology</p>
            <h2 class="lp-heading" style="font-size:1.875rem;">Powered by Professional Print Tech</h2>
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:1rem; justify-content:center;">
            <?php
            $techs = [
                ['label'=>'DTF Printing',        'desc'=>'Direct-to-Film',          'color'=>'rgba(99,102,241,.15)',  'text'=>'#818cf8'],
                ['label'=>'Silkscreen',           'desc'=>'Screen Printing',         'color'=>'rgba(14,165,233,.15)', 'text'=>'#38bdf8'],
                ['label'=>'UV Inkjet',            'desc'=>'Large Format',            'color'=>'rgba(16,185,129,.15)', 'text'=>'#34d399'],
                ['label'=>'Laser Print',          'desc'=>'Document & Card Stock',   'color'=>'rgba(245,158,11,.15)', 'text'=>'#fbbf24'],
                ['label'=>'Sublimation',          'desc'=>'Polyester & Merch',       'color'=>'rgba(168,85,247,.15)', 'text'=>'#c084fc'],
                ['label'=>'Die Cutting',          'desc'=>'Stickers & Labels',       'color'=>'rgba(236,72,153,.15)', 'text'=>'#f472b6'],
                ['label'=>'Cold Lamination',      'desc'=>'Finishing & Protection',  'color'=>'rgba(83,197,224,.15)', 'text'=>'var(--lp-accent-l)'],
                ['label'=>'Embroidery',           'desc'=>'Apparel Detailing',       'color'=>'rgba(251,146,60,.15)', 'text'=>'#fb923c'],
            ];
            foreach ($techs as $tech): ?>
            <div style="background:<?= $tech['color'] ?>; border:1px solid #e2e8f0; border-radius:.75rem; padding:.85rem 1.4rem; display:flex; flex-direction:column; align-items:center; min-width:130px; text-align:center; transition:transform .2s;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
                <p style="font-size:.9375rem; font-weight:700; color:<?= $tech['text'] ?>; margin-bottom:.2rem;"><?= $tech['label'] ?></p>
                <p style="font-size:.75rem; color:#64748b;"><?= $tech['desc'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================
     CTA
     ============================================================ -->
<section class="lp-section-cta" style="padding-top:5rem; padding-bottom:5rem;">
    <div class="lp-wrap">
        <div class="lp-cta-inner">
            <p class="lp-hero-tag" style="margin-bottom:1.5rem; display:inline-flex;">✦ Ready to Print?</p>
            <h2 class="lp-cta-title">Need a Custom Printing Job?</h2>
            <p class="lp-cta-desc">Can't find exactly what you're looking for? Reach out for bulk orders, specialized materials, unique dimensions, or a full design consultation.</p>
            <div class="lp-cta-btns" style="flex-wrap:wrap;">
                <?php if (!is_logged_in()): ?>
                    <a href="#" data-auth-modal="register" class="lp-btn lp-btn-primary">Create Free Account</a>
                    <a href="/printflow/public/products.php" class="lp-btn lp-btn-outline">Browse Products</a>
                <?php else: ?>
                    <a href="<?php echo $base_url; ?>/<?php echo strtolower($user_type); ?>/dashboard.php" class="lp-btn lp-btn-primary">Go to Dashboard</a>
                    <a href="/printflow/public/products.php" class="lp-btn lp-btn-outline">Browse Products</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
