<?php
/**
 * Point of Sale (POS) - Staff Walk-in Interface
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Require staff or admin role
require_role(['Admin', 'Staff']);

$page_title = "Point of Sale (POS)";
$current_page = "pos";
$user_name = $_SESSION['user_name'] ?? 'Staff';

// Fetch Categories
$categories = [];
try {
    $categories = db_query("SELECT DISTINCT category FROM products WHERE status = 'Activated' AND category IS NOT NULL ORDER BY category ASC");
} catch (Exception $e) { }

// Fetch Customers
$customers = [];
try {
    $customers = db_query("SELECT customer_id, first_name, last_name, email, contact_number FROM customers ORDER BY first_name ASC, last_name ASC");
} catch (Exception $e) { }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - PrintFlow</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    
    <style>
        /* 
         * STABLE POS LAYOUT
         * We use absolute positioning inside a relative container to prevent ALL jumping/height shifts.
         */
        
        /* The container takes up exactly the available height minus the top bar */
        .pos-wrapper {
            position: relative;
            flex: 1;
            height: 100%;
            display: flex;
            background: #ffffff;
            border: none;
            overflow: hidden;
            margin: 0;
            min-height: 0; /* Critical for vertical scroll */
        }

        /* Left Side: Products */
        .pos-products-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #e2e8f0;
            background: #f8fafc;
            min-width: 0;
            min-height: 0;
        }
        
        .pos-search-header {
            padding: 20px;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .pos-search-box {
            position: relative;
            flex: 1;
        }
        
        .pos-search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        
        .pos-search-input {
            width: 100%;
            padding: 12px 16px 12px 36px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        
        .pos-search-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .pos-category-select {
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            font-size: 14px;
            min-width: 180px;
            outline: none;
            cursor: pointer;
        }

        .pos-products-grid {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            align-content: start;
            background: #f1f5f9;
        }
        
        /* Product Card */
        .pos-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.6);
            border-radius: 16px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        
        .pos-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border-color: #6366f1;
        }
        
        .pos-card.no-stock {
            opacity: 0.5;
            cursor: not-allowed;
            filter: grayscale(80%);
        }
        .pos-card.no-stock:hover { transform: none; box-shadow: none; }
        
        .pos-card-img-container {
            width: 100%;
            height: 140px;
            position: relative;
            background: #f1f5f9;
        }
        
        .pos-card-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .pos-card-price {
            position: absolute;
            bottom: 12px;
            right: 12px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(4px);
            padding: 6px 12px;
            border-radius: 10px;
            font-weight: 700;
            color: #4f46e5;
            font-size: 15px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        
        .pos-card-body {
            padding: 12px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        
        .pos-card-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.3;
        }
        
        .pos-card-stock {
            margin-top: auto;
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Right Side: Cart */
        .pos-cart-area {
            width: 420px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            border-left: 1px solid #e2e8f0;
            min-height: 0;
        }
        
        .pos-cart-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .pos-cart-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .pos-btn-clear {
            background: #fee2e2;
            color: #ef4444;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .pos-btn-clear:hover { background: #fca5a5; }

        .pos-customer-section {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        
        .pos-customer-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }
        
        .pos-btn-link {
            background: none;
            border: none;
            color: #6366f1;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            padding: 0;
        }
        .pos-btn-link:hover { text-decoration: underline; }

        .pos-cart-list {
            flex: 1;
            overflow-y: auto;
            padding: 16px 20px;
        }
        
        .pos-empty-state {
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            text-align: center;
        }
        
        .pos-empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            color: #cbd5e1;
        }
        
        .pos-cart-item {
            display: flex;
            align-items: center;
            padding: 10px 14px;
            border: 1px solid #f1f5f9;
            border-radius: 10px;
            margin-bottom: 8px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            transition: all 0.2s;
        }
        .pos-cart-item:hover {
            border-color: #6366f1;
            background: #f8fafc;
        }
        
        .pos-item-details { flex: 1; padding-right: 12px; min-width: 0; }
        .pos-item-name { font-size: 14px; font-weight: 600; color: #1e293b; margin-bottom: 4px; line-height: 1.2; word-wrap: break-word; overflow-wrap: break-word; }
        .pos-item-price { font-size: 12px; color: #64748b; }
        
        .pos-item-controls {
            display: flex;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .pos-qty-btn {
            background: none;
            border: none;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #475569;
        }
        .pos-qty-btn:hover { background: #e2e8f0; }
        
        .pos-qty-val {
            width: 30px;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            border: none;
            background: transparent;
            pointer-events: none;
        }
        
        .pos-item-total {
            font-weight: 700;
            font-size: 14px;
            min-width: 60px;
            text-align: right;
            margin-left: 12px;
        }
        
        .pos-item-remove {
            color: #ef4444;
            background: none;
            border: none;
            cursor: pointer;
            margin-left: 12px;
            padding: 4px;
            opacity: 0.6;
        }
        .pos-item-remove:hover { opacity: 1; }

        .pos-checkout-section {
            padding: 16px 20px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            flex-shrink: 0;
        }
        
        @media (max-height: 800px) {
            .pos-checkout-section { padding: 12px 20px; }
            .pos-payment-tabs { margin: 12px 0; }
            .pos-summary-total { margin-top: 12px; padding-top: 12px; font-size: 18px; }
            .service-btn { padding: 20px 16px; border-radius: 12px; font-size: 14px; gap: 8px; }
            .service-btn i { width: 48px; height: 48px; font-size: 24px; border-radius: 10px; }
            .pos-tender-group { margin-bottom: 12px; }
            .pos-btn-checkout { padding: 12px; }
        }
        
        .pos-summary-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
            color: #475569;
        }
        
        .pos-summary-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px dashed #cbd5e1;
            font-size: 20px;
            font-weight: 800;
            color: #1e293b;
        }
        

        
        .pos-tender-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .pos-tender-input {
            width: 140px;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            text-align: right;
            font-weight: 700;
            font-size: 16px;
            outline: none;
        }
        .pos-tender-input:focus { border-color: #6366f1; }


        
        .pos-btn-checkout {
            width: 100%;
            padding: 16px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .pos-btn-checkout:hover { background: #4338ca; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .pos-btn-checkout:disabled { background: #94a3b8; cursor: not-allowed; }

        .service-btn {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            padding: 32px 24px;
            border-radius: 20px;
            font-size: 16px;
            font-weight: 800;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease-out;
            color: #1e293b;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            position: relative;
            overflow: hidden;
            will-change: transform, box-shadow;
            backface-visibility: hidden;
            transform: translateZ(0);
        }
        .service-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0) 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .service-btn i {
            font-size: 32px;
            color: #4f46e5;
            background: #f5f3ff;
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            transition: all 0.3s ease-out;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.1);
        }
        .service-btn:hover {
            transform: translateY(-4px) scale(1.02);
            border-color: #6366f1;
            box-shadow: 0 20px 25px -5px rgba(99, 102, 241, 0.15), 0 10px 10px -5px rgba(99, 102, 241, 0.1);
        }
        .service-btn:hover::before {
            opacity: 1;
        }
        .service-btn:hover i {
            background: #4f46e5;
            color: #fff;
            transform: scale(1.05) rotate(-5deg);
            box-shadow: 0 10px 15px rgba(79, 70, 229, 0.2);
        }
        .service-btn.btn-other {
            grid-column: 2;
            background: #fdfdfd;
            border: 2px dashed #e2e8f0;
        }
        .service-btn.btn-other i {
            background: #f8fafc;
            color: #64748b;
        }
        .service-btn.btn-other:hover {
            border-style: solid;
            border-color: #6366f1;
        }

        .pos-services-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            padding: 32px;
            align-content: center;
            height: 100%;
        }

        /* Price Input Modal */
        #price-modal-overlay {
            display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:1000; align-items:center; justify-content:center;
        }
        .price-modal {
            background: #fff; width: 320px; border-radius: 20px; padding: 28px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); color: #1e293b; border: 1px solid #e2e8f0;
        }

        /* Hide scrollbar for grid to look cleaner */
        .pos-products-grid::-webkit-scrollbar, .pos-cart-list::-webkit-scrollbar { width: 6px; }
        .pos-products-grid::-webkit-scrollbar-thumb, .pos-cart-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php 
    if ($_SESSION['user_type'] === 'Staff') {
        include __DIR__ . '/../includes/staff_sidebar.php';
    } else {
        include __DIR__ . '/../includes/admin_sidebar.php';
    }
    ?>

    <div class="main-content" style="padding: 0; height: 100vh; overflow: hidden; display: flex; flex-direction: column; width: 100%; min-height: 0;">
        <main style="flex: 1; display: flex; flex-direction: column; width: 100%; min-height: 0;">
            <div class="pos-wrapper" style="width: 100%; flex: 1; min-height: 0;">
                
                <!-- LEFT: SERVICES -->
                <div class="pos-products-area" style="background:#fff;">
                    <div style="padding: 24px; border-bottom: 1px solid #e2e8f0; background: #fff;">
                        <h2 style="font-weight:700; font-size:18px; color:#1e293b; margin:0;">Available Services</h2>
                        <p style="font-size:13px; color:#64748b; margin-top:4px;">Quickly add a printing service to the order.</p>
                    </div>
                    
                    <div class="pos-services-grid" style="border-bottom:none; padding: 24px;">
                        <button class="service-btn" onclick="addQuickService('Tarpaulin', 'fas fa-map')">
                            <i class="fas fa-map"></i> Tarpaulin
                        </button>
                        <button class="service-btn" onclick="addQuickService('T-Shirt', 'fas fa-tshirt')">
                            <i class="fas fa-tshirt"></i> T-Shirt
                        </button>
                        <button class="service-btn" onclick="addQuickService('Stickers', 'fas fa-sticky-note')">
                            <i class="fas fa-sticky-note"></i> Stickers
                        </button>
                        <button class="service-btn" onclick="addQuickService('Glass/Wall', 'fas fa-window-maximize')">
                            <i class="fas fa-window-maximize"></i> Glass/Wall
                        </button>
                        <button class="service-btn" onclick="addQuickService('Transparent Stickers', 'fas fa-search-plus')">
                            <i class="fas fa-search-plus"></i> Transparent
                        </button>
                        <button class="service-btn" onclick="addQuickService('Reflectorized', 'fas fa-lightbulb')">
                            <i class="fas fa-lightbulb"></i> Reflectorized
                        </button>
                        <button class="service-btn" onclick="addQuickService('Sintraboard', 'fas fa-square')">
                            <i class="fas fa-square"></i> Sintraboard
                        </button>
                        <button class="service-btn" onclick="addQuickService('Standees', 'fas fa-user-tie')">
                            <i class="fas fa-user-tie"></i> Standees
                        </button>
                        <button class="service-btn" onclick="addQuickService('Souvenirs', 'fas fa-gift')">
                            <i class="fas fa-gift"></i> Souvenirs
                        </button>
                        <button class="service-btn btn-other" onclick="addOtherService()">
                            <i class="fas fa-plus-circle"></i> Other
                        </button>
                    </div>
                </div>
                
                <!-- RIGHT: CART -->
                <div class="pos-cart-area">
                    <div class="pos-cart-header">
                        <h2>Current Order</h2>
                        <button class="pos-btn-clear" onclick="clearCart()"><i class="fas fa-trash"></i> Clear</button>
                    </div>
                    
                    <div class="pos-customer-section">
                        <div class="pos-customer-label">
                            <span>Customer</span>
                            <button class="pos-btn-link" onclick="openNewCustomerModal()">+ New</button>
                        </div>
                        <select id="pos-customer" class="pos-category-select" style="width: 100%; min-width: unset;">
                            <option value="guest">Walk-in Customer (Guest)</option>
                            <?php foreach($customers as $c): ?>
                                <option value="<?= $c['customer_id'] ?>"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="pos-cart-list" id="pos-cart-items">
                        <div class="pos-empty-state">
                            <i class="fas fa-shopping-basket"></i>
                            <p>Cart is empty</p>
                        </div>
                    </div>
                    
                    <div class="pos-checkout-section">
                        <div class="pos-summary-line">
                            <span>Subtotal</span>
                            <span id="pos-subtotal">₱0.00</span>
                        </div>
                        
                        <div class="pos-summary-total">
                            <span id="pos-total">₱0.00</span>
                        </div>
                        
                        <input type="hidden" id="pos-active-pm" value="Cash">


                        <div class="pos-tender-group" id="tender-group">
                            <span style="font-weight: 600; font-size: 14px; color: #475569;">Tendered</span>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 12px; top: 12px; font-weight: 600; color: #94a3b8;">₱</span>
                                <input type="number" id="pos-tendered" class="pos-tender-input" placeholder="0.00" oninput="calculateChange()" style="padding-left: 28px;">
                            </div>
                        </div>
                        
                        <div class="pos-summary-line" id="change-group" style="margin-bottom: 20px; align-items: center;">
                            <span style="font-weight: 600; color: #475569;">Change</span>
                            <span id="pos-change" style="font-size: 20px; font-weight: 800; color: #10b981;">₱0.00</span>
                        </div>
                        
                        <button class="pos-btn-checkout" id="pos-checkout-btn" disabled onclick="processCheckout()">
                            <i class="fas fa-lock" id="checkout-icon"></i> <span id="checkout-text">Select Items</span>
                        </button>
                    </div>
                </div>

            </div> <!-- END pos-wrapper -->
        </main>
    </div>
</div>

<!-- POS Customization Modal -->
<div id="custom-modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#ffffff; width:450px; border-radius:20px; padding:28px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.15); border:1px solid #e2e8f0; transform:translateY(0); transition:all 0.3s; margin:16px; color:#1e293b;">
        <h3 id="cm-title" style="margin:0 0 20px 0; font-size:20px; font-weight:800; color:#0f172a; letter-spacing:-0.02em;">Product Customization</h3>
        
        <div id="cm-dynamic-fields" style="display:flex; flex-direction:column; gap:16px; margin-bottom:24px; max-height: 450px; overflow-y:auto; padding-right:8px;">
            <!-- Fields generated dynamically via JS -->
        </div>

        <div style="display:flex; justify-content:flex-end; gap:12px; border-top:1px solid #f1f5f9; padding-top:20px;">
            <button onclick="closeCustomModal()" style="padding:12px 20px; border:1px solid #e2e8f0; background:#f8fafc; border-radius:12px; cursor:pointer; font-weight:600; font-size:14px; color:#64748b; transition:all 0.2s;" onmouseover="this.style.background='#f1f5f9';this.style.color='#1e293b'" onmouseout="this.style.background='#f8fafc';this.style.color='#64748b'">Cancel</button>
            <button onclick="confirmCustomization()" style="padding:12px 28px; border:none; background:#4f46e5; color:white; border-radius:12px; cursor:pointer; font-weight:700; font-size:14px; box-shadow:0 10px 15px -3px rgba(79,70,229,0.3); transition:all 0.2s;" onmouseover="this.style.transform='translateY(-1px)';this.style.background='#4338ca'" onmouseout="this.style.transform='translateY(0)';this.style.background='#4f46e5'">Add to Cart</button>
        </div>
    </div>
</div>

<!-- Modal for New Customer -->
<div id="customer-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:999; align-items:center; justify-content:center;">
    <div style="background:#ffffff; width:400px; border-radius:20px; padding:28px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.15); color:#1e293b; border:1px solid #e2e8f0;">
        <div style="display:flex; justify-content:space-between; margin-bottom:24px;">
            <h3 style="margin:0; font-weight:800; color:#0f172a; font-size:20px; letter-spacing:-0.02em;">Add Customer</h3>
            <button onclick="closeCustomerModal()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#94a3b8; padding:4px;" onmouseover="this.style.color='#1e293b'" onmouseout="this.style.color='#94a3b8'">&times;</button>
        </div>
        <input type="text" id="nc-first" placeholder="First Name" style="width:100%; padding:14px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; color:#1e293b; outline:none; transition:all 0.2s;" onfocus="this.style.borderColor='#6366f1';this.style.background='#fff'">
        <input type="text" id="nc-last" placeholder="Last Name" style="width:100%; padding:14px; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; color:#1e293b; outline:none; transition:all 0.2s;" onfocus="this.style.borderColor='#6366f1';this.style.background='#fff'">
        <input type="tel" id="nc-phone" placeholder="Phone Number" style="width:100%; padding:14px; margin-bottom:24px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; color:#1e293b; outline:none; transition:all 0.2s;" onfocus="this.style.borderColor='#6366f1';this.style.background='#fff'">
        <button onclick="saveCustomer()" id="nc-save-btn" style="width:100%; background:#4f46e5; color:white; padding:14px; border:none; border-radius:12px; font-weight:700; cursor:pointer; box-shadow:0 10px 15px -3px rgba(79,70,229,0.3); transition:all 0.2s;" onmouseover="this.style.background='#4338ca'" onmouseout="this.style.background='#4f46e5'">Save Customer</button>
    </div>
</div>

<!-- Modal for Custom Price -->
<div id="price-modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:1000; align-items:center; justify-content:center;">
    <div class="price-modal" style="border-radius:20px; border:1px solid #e2e8f0;">
        <h3 id="pm-title" style="margin:0 0 12px 0; font-size:20px; font-weight:800; color:#0f172a; letter-spacing:-0.02em;">Set Price</h3>
        <div id="pm-name-group" style="margin-bottom: 24px; display:none;">
            <label style="display:block; font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:8px; letter-spacing:0.05em;">Service Name</label>
            <input type="text" id="pm-name-input" style="width:100%; padding:14px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; color:#1e293b; outline:none;" placeholder="e.g. Custom Frame">
        </div>
        <div style="margin-bottom:28px;">
            <label style="display:block; font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:8px; letter-spacing:0.05em;">Negotiated Price</label>
            <div style="position: relative;">
                <span style="position: absolute; left: 16px; top: 14px; font-weight: 700; color: #94a3b8;">₱</span>
                <input type="number" id="pm-price-input" style="width:100%; padding:14px 14px 14px 32px; border:1px solid #e2e8f0; border-radius:12px; font-weight:800; font-size:24px; background:#f8fafc; color:#1e293b; outline:none;" placeholder="0.00" step="0.01">
            </div>
        </div>
        <div style="display:flex; gap:12px;">
            <button onclick="closePriceModal()" style="flex:1; padding:14px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; color:#64748b; font-weight:700; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.background='#f1f5f9';this.style.color='#1e293b'" onmouseout="this.style.background='#f8fafc';this.style.color='#64748b'">Cancel</button>
            <button onclick="confirmPrice()" style="flex:1; padding:14px; border:none; border-radius:12px; background:#4f46e5; color:white; font-weight:700; cursor:pointer; box-shadow:0 10px 15px -3px rgba(79,70,229,0.3); transition:all 0.2s;" onmouseover="this.style.background='#4338ca';this.style.transform='translateY(-1px)'" onmouseout="this.style.background='#4f46e5';this.style.transform='translateY(0)'">Add Item</button>
        </div>
    </div>
</div>

<script>

let products = [];
let cart = [];
let currentTotal = 0;

document.addEventListener('DOMContentLoaded', () => {
    fetchProducts();
    document.getElementById('pos-search').addEventListener('input', renderProducts);
    document.getElementById('pos-category').addEventListener('change', renderProducts);
});

async function fetchProducts() {
    try {
        const res = await fetch('/printflow/staff/api/get_products.php');
        const data = await res.json();
        if(data.success) {
            products = data.products;
            renderProducts();
        } else {
            document.getElementById('pos-products-grid').innerHTML = '<p style="color:red; text-align:center; padding:20px;">Failed to load products.</p>';
        }
    } catch(e) {
        document.getElementById('pos-products-grid').innerHTML = '<p style="color:red; text-align:center; padding:20px;">Network error.</p>';
    }
}

// POS Dynamic Requirements Config
const serviceRequirements = {
    'T-Shirt': [
        { label: 'Size', type: 'select', name: 'size', options: ['S', 'M', 'L', 'XL', '2XL', '3XL'] },
        { label: 'Color', type: 'text', name: 'color', placeholder: 'e.g., Black, White' },
        { label: 'Print Placement', type: 'select', name: 'print_placement', options: ['Front', 'Back', 'Front & Back', 'Pocket'] }
    ],
    'Tarpaulin': [
        { label: 'Width (ft)', type: 'number', name: 'width_ft', placeholder: 'E.g., 2', step: '0.1' },
        { label: 'Height (ft)', type: 'number', name: 'height_ft', placeholder: 'E.g., 3', step: '0.1' },
        { label: 'Thickness/Type', type: 'select', name: 'thickness', options: ['10oz', '12oz', 'Others'] },
        { label: 'Finish', type: 'select', name: 'finish', options: ['With Eyelets', 'Wood Frame', 'None'] }
    ],
    'Sticker': [
        { label: 'Width (inches)', type: 'number', name: 'width_in', placeholder: 'E.g., 2', step: '0.1' },
        { label: 'Height (inches)', type: 'number', name: 'height_in', placeholder: 'E.g., 2', step: '0.1' },
        { label: 'Finish', type: 'select', name: 'finish', options: ['Glossy', 'Matte', 'Transparent'] },
        { label: 'Cut Type', type: 'select', name: 'cut_type', options: ['Kiss Cut', 'Die Cut'] }
    ],
    'Sintraboard': [
        { label: 'Width (in)', type: 'number', name: 'width_in' },
        { label: 'Height (in)', type: 'number', name: 'height_in' },
        { label: 'Thickness', type: 'select', name: 'thickness', options: ['3mm', '5mm'] }
    ],
    'Reflectorized': [
        { label: 'Shape', type: 'select', name: 'shape', options: ['Square', 'Rectangle', 'Circle', 'Triangle'] },
        { label: 'Background Color', type: 'text', name: 'bg_color' },
        { label: 'Text/Graphic Color', type: 'text', name: 'text_color' }
    ],
    'Souvenir': [
        { label: 'Type', type: 'select', name: 'type', options: ['Mug', 'Keychain', 'Tumbler', 'Pen', 'Button Pin', 'Other'] },
        { label: 'Details / Occasion', type: 'text', name: 'details' }
    ]
};

function getRequirementsForProduct(productName, category) {
    const term = (productName + " " + category).toLowerCase();
    if(term.includes('t-shirt') || term.includes('tshirt')) return serviceRequirements['T-Shirt'];
    if(term.includes('tarpaulin') || term.includes('tarp')) return serviceRequirements['Tarpaulin'];
    if(term.includes('sticker') || term.includes('decal')) return serviceRequirements['Sticker'];
    if(term.includes('sintraboard') || term.includes('standee')) return serviceRequirements['Sintraboard'];
    if(term.includes('reflectorized') || term.includes('signage')) return serviceRequirements['Reflectorized'];
    if(term.includes('souvenir') || term.includes('mug')) return serviceRequirements['Souvenir'];
    return null;
}

function renderProducts() {
    const grid = document.getElementById('pos-products-grid');
    const search = document.getElementById('pos-search').value.toLowerCase();
    const cat = document.getElementById('pos-category').value;
    
    grid.innerHTML = '';
    
    const filtered = products.filter(p => {
        const mSearch = p.product_name.toLowerCase().includes(search) || (p.sku && p.sku.toLowerCase().includes(search));
        const mCat = cat === '' || p.category === cat;
        return mSearch && mCat;
    });
    
    if(filtered.length === 0) {
        grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:40px; color:#94a3b8;">No products found.</div>';
        return;
    }
    
    filtered.forEach(p => {
        const outOfStock = p.stock_quantity <= 0;
        const img = p.product_image ? '/printflow/' + p.product_image : '/printflow/public/assets/images/placeholder.jpg';
        
        const card = document.createElement('div');
        card.className = `pos-card ${outOfStock ? 'no-stock' : ''}`;
        if(!outOfStock) card.onclick = () => addToCart(p);
        
        card.innerHTML = `
            <div class="pos-card-img-container">
                <img src="${img}" class="pos-card-img" onerror="this.onerror=null; this.src='/printflow/public/images/products/README.md'; this.outerHTML='<div style=\\'background:#f1f5f9; height:100%; display:flex; align-items:center; justify-content:center; font-size:32px;\\'>📦</div>';">
                <div class="pos-card-price">₱${parseFloat(p.price).toFixed(2)}</div>
            </div>
            <div class="pos-card-body">
                <div class="pos-card-title">${p.product_name}</div>
                <div class="pos-card-stock">
                    <i class="fas ${outOfStock ? 'fa-times-circle text-red' : 'fa-check-circle text-green'}" style="color:${outOfStock ? '#ef4444' : '#10b981'}"></i>
                    ${outOfStock ? 'Out of Stock' : p.stock_quantity + ' available'}
                </div>
            </div>
        `;
        grid.appendChild(card);
    });
}

function addToCart(p, overridePrice = null, overrideName = null) {
    const name = overrideName || p.product_name;
    const price = overridePrice !== null ? overridePrice : parseFloat(p.price);
    
    if(p.price == 0 && overridePrice === null) {
        openPriceModal(p);
        return;
    }
    
    // Check if exactly this item (ID + name + price) exists
    const existing = cart.find(i => i.product_id === p.product_id && i.name === name && i.price === price);
    
    if(existing) {
        if(existing.qty < p.stock_quantity || p.stock_quantity === null) existing.qty++;
        else alert('Not enough stock!');
    } else {
        cart.push({
            product_id: p.product_id,
            name: name,
            price: price,
            qty: 1,
            stock: p.stock_quantity
        });
    }
    renderCart();
}

let pendingCustomProduct = null;
let currentCustomRequirements = null;

function openCustomModal(product, requirements) {
    pendingCustomProduct = product;
    currentCustomRequirements = requirements;
    
    document.getElementById('cm-title').textContent = product.product_name + ' Details';
    const container = document.getElementById('cm-dynamic-fields');
    container.innerHTML = '';
    
    requirements.forEach((req, idx) => {
        const div = document.createElement('div');
        div.style.display = 'flex';
        div.style.flexDirection = 'column';
        div.style.gap = '4px';
        
        let label = `<label style="font-size:12px; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em;">${req.label}</label>`;
        let inputHtml = '';
        
        const baseClass = 'style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; outline:none;"';
        
        if (req.type === 'select') {
            inputHtml = `<select id="custom_field_${idx}" ${baseClass}>`;
            inputHtml += `<option value="">Select ${req.label}</option>`;
            req.options.forEach(opt => {
                inputHtml += `<option value="${opt}">${opt}</option>`;
            });
            inputHtml += `</select>`;
        } else {
            inputHtml = `<input type="${req.type}" id="custom_field_${idx}" placeholder="${req.placeholder || ''}" ${req.step ? `step="${req.step}"` : ''} ${baseClass}>`;
        }
        
        div.innerHTML = label + inputHtml;
        container.appendChild(div);
    });
    
    // Add generic Notes/Instructions field at the end
    const notesDiv = document.createElement('div');
    notesDiv.style.display = 'flex';
    notesDiv.style.flexDirection = 'column';
    notesDiv.style.gap = '4px';
    notesDiv.innerHTML = `
        <label style="font-size:12px; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em;">Special Instructions</label>
        <textarea id="custom_notes" rows="2" placeholder="Any additional details..." style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; outline:none; resize:vertical;"></textarea>
    `;
    container.appendChild(notesDiv);
    
    document.getElementById('custom-modal-overlay').style.display = 'flex';
}

function closeCustomModal() {
    document.getElementById('custom-modal-overlay').style.display = 'none';
    pendingCustomProduct = null;
    currentCustomRequirements = null;
}

function confirmCustomization() {
    if (!pendingCustomProduct || !currentCustomRequirements) return;
    
    const customization = {};
    let valid = true;
    
    currentCustomRequirements.forEach((req, idx) => {
        const val = document.getElementById(`custom_field_${idx}`).value;
        if (!val && req.type !== 'text') { // Basic validation
            valid = false;
        }
        customization[req.label] = val;
    });
    
    const notes = document.getElementById('custom_notes').value;
    if (notes) customization['Notes'] = notes;
    
    // Check if exactly this item exists WITH SAME CUSTOMIZATION
    const customString = JSON.stringify(customization);
    const existing = cart.find(i => i.product_id === pendingCustomProduct.product_id && i.price === parseFloat(pendingCustomProduct.price) && JSON.stringify(i.customization) === customString);
    
    if(existing) {
        if(existing.qty < pendingCustomProduct.stock_quantity || pendingCustomProduct.stock_quantity === null) existing.qty++;
        else alert('Not enough stock!');
    } else {
        cart.push({
            product_id: pendingCustomProduct.product_id,
            name: pendingCustomProduct.product_name,
            price: parseFloat(pendingCustomProduct.price),
            qty: 1,
            stock: pendingCustomProduct.stock_quantity,
            customization: customization
        });
    }
    
    renderCart();
    closeCustomModal();
}

let pendingProduct = null;
let isOtherService = false;

function openPriceModal(p, isOther = false) {
    pendingProduct = p;
    isOtherService = isOther;
    
    document.getElementById('pm-title').textContent = isOther ? 'Custom Service' : 'Set Service Price';
    document.getElementById('pm-name-group').style.display = isOther ? 'block' : 'none';
    document.getElementById('pm-name-input').value = isOther ? '' : p.product_name;
    document.getElementById('pm-price-input').value = p.price > 0 ? p.price : '';
    document.getElementById('price-modal-overlay').style.display = 'flex';
    
    const focusEl = isOther ? 'pm-name-input' : 'pm-price-input';
    setTimeout(() => document.getElementById(focusEl).focus(), 100);
}

function closePriceModal() {
    document.getElementById('price-modal-overlay').style.display = 'none';
    pendingProduct = null;
    isOtherService = false;
}

function confirmPrice() {
    const name = document.getElementById('pm-name-input').value.trim();
    const price = parseFloat(document.getElementById('pm-price-input').value);
    
    if(isOtherService && !name) return alert('Please enter a service name.');
    if(isNaN(price) || price < 0) return alert('Please enter a valid price.');
    
    addToCart(pendingProduct, price, name);
    closePriceModal();
}

function addQuickService(serviceName) {
    let p = products.find(prod => prod.category === serviceName || prod.product_name.includes(serviceName));
    if(!p) p = products.find(prod => prod.category.includes(serviceName));

    if(p) {
        const reqs = getRequirementsForProduct(p.product_name, p.category);
        if (reqs) {
            openCustomModal(p, reqs);
        } else {
            addToCart(p);
        }
    } else {
        // Create a temporary product object if not found in catalog
        const fallback = { product_id: 21, product_name: serviceName, category: serviceName, price: 0, stock_quantity: null };
        const reqs = getRequirementsForProduct(serviceName, serviceName);
        if (reqs) {
             openCustomModal(fallback, reqs);
        } else {
             addToCart(fallback);
        }
    }
}

function addOtherService() {
    const otherBase = { product_id: 21, product_name: 'Other', category: 'Other', price: 0, stock_quantity: null };
    openPriceModal(otherBase, true);
}

function updateQty(id, price, delta) {
    const item = cart.find(i => i.product_id === id && i.price === price);
    if(!item) return;
    const newQty = item.qty + delta;
    if(newQty <= 0) {
        cart = cart.filter(i => !(i.product_id === id && i.price === price));
    } else if(newQty > item.stock && item.stock !== null) {
        alert('Not enough stock!');
    } else {
        item.qty = newQty;
    }
    renderCart();
}

function removeFromCart(id, price) {
    cart = cart.filter(i => !(i.product_id === id && i.price === price));
    renderCart();
}

function clearCart() {
    if(cart.length > 0 && confirm('Clear current order?')) {
        cart = [];
        document.getElementById('pos-tendered').value = '';
        renderCart();
    }
}

function renderCart() {
    const cont = document.getElementById('pos-cart-items');
    currentTotal = 0;
    
    if(cart.length === 0) {
        cont.innerHTML = `<div class="pos-empty-state"><i class="fas fa-shopping-basket"></i><p>Cart is empty</p></div>`;
    } else {
        cont.innerHTML = '';
        cart.forEach((item, index) => {
            const rowTotal = item.price * item.qty;
            currentTotal += rowTotal;
            const div = document.createElement('div');
            div.className = 'pos-cart-item';
            
            let customHtml = '';
            if (item.customization) {
                const parts = [];
                for (const [key, val] of Object.entries(item.customization)) {
                    if(val) parts.push(`${key}: ${val}`);
                }
                if (parts.length > 0) {
                    customHtml = `<div style="font-size:11px; color:#64748b; margin-top:2px; line-height:1.2; word-break:break-word; max-height: 48px; overflow-y: auto;">${parts.join(' | ')}</div>`;
                }
            }
            
            div.innerHTML = `
                <div class="pos-item-details" style="flex:1;">
                    <div class="pos-item-name">${item.name}</div>
                    ${customHtml}
                    <div class="pos-item-price" style="margin-top:2px;">₱${item.price.toFixed(2)}</div>
                </div>
                <div class="pos-item-controls">
                    <button class="pos-qty-btn" onclick="updateQtyByCartIndex(${index}, -1)"><i class="fas fa-minus" style="font-size:10px;"></i></button>
                    <input class="pos-qty-val" value="${item.qty}" readonly>
                    <button class="pos-qty-btn" onclick="updateQtyByCartIndex(${index}, 1)"><i class="fas fa-plus" style="font-size:10px;"></i></button>
                </div>
                <div class="pos-item-total" style="width:70px; text-align:right;">₱${rowTotal.toFixed(2)}</div>
                <button class="pos-item-remove" onclick="removeByCartIndex(${index})"><i class="fas fa-times"></i></button>
            `;
            cont.appendChild(div);
        });
    }
    
    const fTotal = '₱' + currentTotal.toFixed(2);
    document.getElementById('pos-subtotal').textContent = fTotal;
    document.getElementById('pos-total').textContent = fTotal;
    
    calculateChange();
    updateCheckoutState();
}

function updateQtyByCartIndex(index, delta) {
    const item = cart[index];
    if(!item) return;
    const newQty = item.qty + delta;
    if(newQty <= 0) {
        cart.splice(index, 1);
    } else if(newQty > item.stock && item.stock !== null) {
        alert('Not enough stock!');
    } else {
        item.qty = newQty;
    }
    renderCart();
}

function removeByCartIndex(index) {
    cart.splice(index, 1);
    renderCart();
}

function setPaymentMethod(method) {
    document.getElementById('pos-active-pm').value = 'Cash';
    updateCheckoutState();
}

function calculateChange() {
    if(currentTotal === 0) {
        document.getElementById('pos-change').textContent = '₱0.00';
        return;
    }
    const tendered = parseFloat(document.getElementById('pos-tendered').value) || 0;
    const change = tendered - currentTotal;
    const changeEl = document.getElementById('pos-change');
    changeEl.textContent = change >= 0 ? `₱${change.toFixed(2)}` : '₱0.00';
    changeEl.style.color = (change < 0 && tendered > 0) ? '#ef4444' : '#10b981';
    
    updateCheckoutState();
}

function updateCheckoutState() {
    const btn = document.getElementById('pos-checkout-btn');
    const icon = document.getElementById('checkout-icon');
    const text = document.getElementById('checkout-text');
    
    if(cart.length === 0) {
        btn.disabled = true;
        icon.className = 'fas fa-lock';
        text.textContent = 'Select Items';
        return;
    }
    
    const tendered = parseFloat(document.getElementById('pos-tendered').value) || 0;
    const canCheckout = tendered >= currentTotal;
    
    btn.disabled = !canCheckout;
    icon.className = canCheckout ? 'fas fa-check-circle' : 'fas fa-lock';
    text.textContent = canCheckout ? 'Complete Sale' : 'Enter Valid Amount';
}

async function processCheckout() {
    if(cart.length === 0) return;
    
    const btn = document.getElementById('pos-checkout-btn');
    btn.disabled = true;
    document.getElementById('checkout-icon').className = 'fas fa-spinner fa-spin';
    document.getElementById('checkout-text').textContent = 'Processing...';
    
    const payload = {
        action: 'walkin_checkout',
        customer_id: document.getElementById('pos-customer').value,
        payment_method: document.getElementById('pos-active-pm').value,
        amount_tendered: document.getElementById('pos-tendered').value || currentTotal,
        items: cart.map(i => ({ id: i.product_id, qty: i.qty, price: i.price, customization: i.customization || null }))
    };
    
    try {
        const res = await fetch('/printflow/staff/api/pos_checkout.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if(data.success) {
            alert('Sale Completed! Order ID: ' + data.order_id);
            cart = [];
            document.getElementById('pos-customer').value = 'guest';
            document.getElementById('pos-tendered').value = '';
            setPaymentMethod('Cash');
            renderCart();
            fetchProducts(); // Refresh stock
        } else {
            alert('Checkout failed: ' + (data.message || 'Error'));
        }
    } catch(e) {
        alert('Network error.');
    } finally {
        updateCheckoutState();
    }
}

function openNewCustomerModal() {
    document.getElementById('customer-modal').style.display = 'flex';
}
function closeCustomerModal() {
    document.getElementById('customer-modal').style.display = 'none';
}
async function saveCustomer() {
    const first = document.getElementById('nc-first').value.trim();
    const last = document.getElementById('nc-last').value.trim();
    if(!first || !last) return alert('First and Last name required.');
    
    document.getElementById('nc-save-btn').textContent = 'Saving...';
    try {
        const res = await fetch('/printflow/staff/api/pos_add_customer.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                first_name: first, last_name: last,
                contact_number: document.getElementById('nc-phone').value.trim()
            })
        });
        const data = await res.json();
        if(data.success) {
            const sel = document.getElementById('pos-customer');
            const opt = document.createElement('option');
            opt.value = data.customer_id;
            opt.textContent = `${first} ${last}`;
            sel.appendChild(opt);
            sel.value = data.customer_id;
            closeCustomerModal();
            document.getElementById('nc-first').value = '';
            document.getElementById('nc-last').value = '';
            document.getElementById('nc-phone').value = '';
        } else {
            alert('Failed: ' + data.message);
        }
    } catch(e) {
        alert('Error.');
    } finally {
        document.getElementById('nc-save-btn').textContent = 'Save Customer';
    }
}
</script>

</body>
</html>
