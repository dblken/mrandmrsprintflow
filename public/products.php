<?php
/**
 * Public Products Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Get filter parameters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT * FROM products WHERE status = 'Activated'";
$params = [];
$types = '';

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= 's';
}

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

$sql .= " ORDER BY name ASC";
$products = db_query($sql, $types, $params);

// Get all categories
$categories = db_query("SELECT DISTINCT category FROM products WHERE status = 'Activated' ORDER BY category ASC");

// Get featured products for the slideshow
$featured_products = db_query("SELECT * FROM products WHERE status = 'Activated' AND is_featured = 1 AND product_image IS NOT NULL ORDER BY name ASC LIMIT 5");

$page_title = 'Products - PrintFlow';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Remove explicit nav-header include, as header.php already provides it for non-landing pages -->

<!-- Swiper CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<style>
    body { background-color: #ffffff; color: #1f2937; }
    
    /* Slideshow Styles */
    .slideshow-container {
        position: relative;
        overflow: hidden;
        border-radius: 1rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        background: #f8fafc;
    }
    .slide {
        display: none;
        animation: fade 0.8s;
        height: 400px;
    }
    .slide.active {
        display: block;
    }
    .slide img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .slide-content {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(transparent, rgba(0, 35, 43, 0.9));
        padding: 4rem 2rem 2rem;
        color: white;
    }
    .slide-title { font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; }
    .slide-desc { font-size: 1rem; opacity: 0.9; max-width: 600px; }
    .slide-btn-prev, .slide-btn-next {
        cursor: pointer;
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 48px;
        height: 48px;
        background: rgba(255,255,255,0.8);
        color: #00232b;
        font-weight: bold;
        font-size: 1.5rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        border: none;
        z-index: 10;
    }
    .slide-btn-prev:hover, .slide-btn-next:hover {
        background: #53C5E0;
        color: white;
    }
    .slide-btn-prev { left: 1rem; }
    .slide-btn-next { right: 1rem; }
    .slide-dots {
        position: absolute;
        bottom: 1rem;
        right: 2rem;
        display: flex;
        gap: 0.5rem;
    }
    .dot {
        cursor: pointer;
        height: 10px;
        width: 10px;
        margin: 0 2px;
        background-color: rgba(255,255,255,0.5);
        border-radius: 50%;
        display: inline-block;
        transition: background-color 0.3s ease;
    }
    .dot.active, .dot:hover { background-color: #53C5E0; }

    /* Interactive Swiper Products Setup */
    .swiper-container {
        width: 100%;
        padding-top: 30px;
        padding-bottom: 60px;
        overflow: hidden;
    }
    .swiper-slide {
        background-color: #0d0d0d;
        border: 1px solid #222;
        border-radius: 16px;
        width: 300px;
        display: flex;
        flex-direction: column;
        color: white;
        position: relative;
        box-shadow: 0 20px 40px rgba(0,0,0,0.6);
        transition: border-color 0.3s ease;
    }
    .swiper-slide::before {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        border-radius: 16px;
        background: radial-gradient(circle at 0% 0%, var(--card-glow, rgba(255,255,255,0.05)) 0%, transparent 60%);
        pointer-events: none;
        z-index: 0;
    }
    .swiper-slide::after {
        content: "";
        position: absolute;
        top: 15px; left: 15px;
        width: 20px; height: 20px;
        border-radius: 4px;
        background: var(--card-glow-solid, #333);
        box-shadow: 0 0 15px var(--card-glow, transparent);
        z-index: 1;
    }
    .swiper-slide:nth-child(3n+1) {
        --card-glow: rgba(65, 105, 225, 0.4);
        --card-glow-solid: #4169e1;
    }
    .swiper-slide:nth-child(3n+2) {
        --card-glow: rgba(200, 200, 200, 0.3);
        --card-glow-solid: #e0e0e0;
    }
    .swiper-slide:nth-child(3n) {
        --card-glow: rgba(46, 139, 87, 0.4);
        --card-glow-solid: #2e8b57;
    }
    
    .swiper-slide-active {
        border-color: rgba(255,255,255,0.2) !important;
    }

    /* Product Card Internals matching the Dark Theme */
    .prod-card-body {
        padding: 50px 20px 25px 20px; /* top padding to clear the 20x20 glow box */
        display: flex;
        flex-direction: column;
        flex: 1;
        position: relative;
        z-index: 2;
    }
    .prod-card-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: 10px;
    }
    .prod-card-price {
        font-size: 2rem;
        font-weight: 800;
        color: #fff;
        margin-bottom: 5px;
        display: flex;
        align-items: baseline;
    }
    .prod-card-price span {
        font-size: 0.9rem;
        font-weight: 500;
        color: #888;
        margin-left: 4px;
    }
    .prod-card-desc {
        font-size: 0.85rem;
        color: #888;
        line-height: 1.6;
        margin-bottom: 20px;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        flex: 1;
        min-height: 60px;
    }
    .prod-card-btn {
        display: block;
        text-align: center;
        width: 100%;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s;
        background: #222;
        color: #fff;
        border: 1px solid #333;
    }
    .swiper-slide-active .prod-card-btn {
        background: #fff;
        color: #000;
    }
    .prod-card-btn:hover {
        opacity: 0.9;
    }
    .prod-img-box {
        width: 100%;
        height: 130px;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 15px;
        border: 1px solid rgba(255,255,255,0.05);
        background: #1a1a1a;
    }
    .prod-img-box img {
        width: 100%; height: 100%; object-fit: cover;
    }

    /* Badge */
    .feature-badge {
        position: absolute;
        top: 15px; right: 15px;
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.2);
        color: #ccc;
        font-size: 0.65rem;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 20px;
        letter-spacing: 0.05em;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    /* Search / Filter bar */
    .filter-bar {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 1rem;
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .filter-input-wrap {
        position: relative;
        flex: 1 1 200px;
        min-width: 160px;
    }
    .filter-input-wrap svg {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        width: 18px;
        height: 18px;
        color: #9ca3af;
        pointer-events: none;
    }
    .filter-input {
        width: 100%;
        padding: 0.65rem 0.875rem 0.65rem 2.5rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.625rem;
        font-size: 0.9375rem;
        background: white;
        color: #1f2937;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .filter-input:focus {
        border-color: #53C5E0;
        box-shadow: 0 0 0 3px rgba(83,197,224,0.15);
    }
    .filter-select {
        padding: 0.65rem 2rem 0.65rem 0.875rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.625rem;
        font-size: 0.9375rem;
        background: white;
        color: #1f2937;
        outline: none;
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%239ca3af' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 14px;
        flex: 0 1 160px;
        transition: border-color 0.2s;
    }
    .filter-select:focus { border-color: #53C5E0; }
    .filter-btn {
        background: #53C5E0;
        color: white;
        border: none;
        padding: 0.65rem 1.5rem;
        border-radius: 0.625rem;
        font-weight: 600;
        font-size: 0.9375rem;
        cursor: pointer;
        transition: background 0.2s;
        white-space: nowrap;
    }
    .filter-btn:hover { background: #32a1c4; }

    /* Responsive */
    @media (max-width: 640px) {
        .swiper-slide { width: 260px; }
        .slide { height: 240px; }
        .slide-title { font-size: 1.25rem; }
        .filter-bar { flex-direction: column; gap: 0.75rem; }
        .filter-input-wrap, .filter-select, .filter-btn { width: 100%; flex: unset; }
    }

    @keyframes fade {
        from {opacity: .4}
        to   {opacity: 1}
    }
</style>

<main class="min-h-screen py-8">
    <div class="container mx-auto px-4 max-w-7xl">
        
        <?php if (!empty($featured_products)): ?>
        <!-- Slideshow Section -->
        <div class="mb-12">
            <h2 class="text-3xl font-extrabold text-gray-900 mb-6 relative inline-block">
                Featured Highlights
                <div class="absolute -bottom-2 left-0 w-1/3 h-1 bg-primary-500 rounded-full"></div>
            </h2>
            
            <div class="slideshow-container">
                <?php foreach ($featured_products as $index => $fp): ?>
                <div class="slide fade" data-index="<?php echo $index; ?>">
                    <img src="/printflow/public/assets/uploads/products/<?php echo htmlspecialchars($fp['product_image']); ?>" alt="<?php echo htmlspecialchars($fp['name']); ?>">
                    <div class="slide-content">
                        <span class="inline-block px-3 py-1 bg-primary-500 text-white text-xs font-bold uppercase tracking-wider rounded-full mb-3 shadow-lg">Featured</span>
                        <h2 class="slide-title"><?php echo htmlspecialchars($fp['name']); ?></h2>
                        <p class="slide-desc"><?php echo htmlspecialchars(mb_substr($fp['description'] ?? '', 0, 150)); ?>...</p>
                        <div class="mt-4">
                            <a href="/printflow/customer/order.php?product_id=<?php echo $fp['product_id']; ?>" class="btn px-8 py-3 rounded-lg font-bold text-gray-900 bg-white hover:bg-gray-100 transition shadow-lg">Order Now for ₱<?php echo number_format($fp['price'], 2); ?></a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <button class="slide-btn-prev" onclick="plusSlides(-1)">&#10094;</button>
                <button class="slide-btn-next" onclick="plusSlides(1)">&#10095;</button>
                
                <div class="slide-dots">
                    <?php foreach ($featured_products as $index => $fp): ?>
                        <span class="dot" onclick="currentSlide(<?php echo $index; ?>)"></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Header & Filters -->
        <div class="text-center mb-8 mt-10 pt-8 border-t border-gray-100">
            <h1 class="text-3xl font-extrabold text-gray-900 mb-1 tracking-tight">Products <span style="color:#53C5E0;">&amp; Services</span></h1>
            <p class="text-gray-400 text-base mb-6">Browse our complete catalog. Swipe to explore.</p>

            <!-- Filter Bar -->
            <form method="GET" action="" id="filter-form">
                <div class="filter-bar">
                    <div class="filter-input-wrap">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" id="search-input" name="search" class="filter-input" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                    </div>
                    <select name="category" id="category-select" class="filter-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="filter-btn">Filter</button>
                </div>
            </form>
        </div>

        <!-- Products Horizontal Train Grid -->
        <?php if (empty($products)): ?>
            <div class="text-center py-16 bg-white border border-gray-100 rounded-2xl shadow-sm">
                <div class="w-20 h-20 mx-auto bg-gray-50 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">No products found</h3>
                <p class="text-gray-500 max-w-sm mx-auto">We couldn't find any products matching your current filters. Try adjusting your search.</p>
                <?php if(!empty($search) || !empty($category)): ?>
                    <a href="products.php" class="inline-block mt-4 text-primary-600 font-semibold hover:text-primary-700">Clear all filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="swiper-container mt-6">
                <div class="swiper-wrapper">
                    <?php foreach ($products as $index => $product): ?>
                        <div class="swiper-slide">
                            <?php if ($product['is_featured'] ?? 0): ?>
                                <div class="feature-badge">★ POPULAR</div>
                            <?php endif; ?>
                            
                            <div class="prod-card-body">
                                <div class="prod-card-name"><?php echo htmlspecialchars($product['category']); ?></div>
                                <div class="prod-card-price">₱<?php echo number_format($product['price'], 2); ?> <span>/ start</span></div>
                                <div class="prod-card-desc"><?php echo htmlspecialchars($product['description']); ?></div>
                                
                                <?php if (!empty($product['product_image'])): ?>
                                <div class="prod-img-box">
                                    <img src="/printflow/public/assets/uploads/products/<?php echo htmlspecialchars($product['product_image']); ?>" alt="Product">
                                </div>
                                <?php endif; ?>

                                <?php if (is_logged_in() && is_customer()): ?>
                                    <a href="/printflow/customer/order.php?product_id=<?php echo $product['product_id']; ?>" class="prod-card-btn">Order Now</a>
                                <?php else: ?>
                                    <a href="#" data-auth-modal="login" class="prod-card-btn">Login to Order</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="mt-8 flex justify-center text-center">
                <p class="text-sm text-gray-500 max-w-lg mx-auto">
                    Swipe horizontally to view all our products. We offer high-quality printing solutions for individuals and businesses alike.
                </p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
    // Slideshow Logic
    let slideIndex = 0;
    let slideInterval;
    const slides = document.querySelectorAll(".slide");
    const dots = document.querySelectorAll(".dot");

    function showSlides(n) {
        if (!slides.length) return;
        
        if (n >= slides.length) {slideIndex = 0}
        if (n < 0) {slideIndex = slides.length - 1}
        
        slides.forEach(slide => slide.classList.remove('active'));
        dots.forEach(dot => dot.classList.remove('active'));
        
        slides[slideIndex].classList.add('active');
        if(dots.length) dots[slideIndex].classList.add('active');
    }

    function plusSlides(n) {
        clearInterval(slideInterval);
        showSlides(slideIndex += n);
        startSlideshow();
    }

    function currentSlide(n) {
        clearInterval(slideInterval);
        showSlides(slideIndex = n);
        startSlideshow();
    }

    function startSlideshow() {
        if (slides.length > 1) {
            slideInterval = setInterval(function() {
                showSlides(++slideIndex);
            }, 5000); // Change image every 5 seconds
        }
    }

    // Initialize slideshow
    if(slides.length > 0) {
        showSlides(slideIndex);
        startSlideshow();
    }

    // Initialize Swiper Coverflow
    if (document.querySelector('.swiper-container')) {
        new Swiper('.swiper-container', {
            effect: 'coverflow',
            grabCursor: true,
            centeredSlides: true,
            slidesPerView: 'auto',
            initialSlide: 1, // Start at the second slide to instantly show the center focus correctly
            coverflowEffect: {
                rotate: 0,
                stretch: 0,
                depth: 100,
                modifier: 2,
                slideShadows: true,
                scale: 0.88
            },
            keyboard: {
                enabled: true,
            },
        });
    }

    // Realtime search: debounce 350ms then submit filter form
    const searchInput = document.getElementById('search-input');
    const categorySelect = document.getElementById('category-select');
    const filterForm   = document.getElementById('filter-form');

    if (searchInput && filterForm) {
        let debounceTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                filterForm.submit();
            }, 380);
        });
    }
    if (categorySelect && filterForm) {
        categorySelect.addEventListener('change', function() {
            filterForm.submit();
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
