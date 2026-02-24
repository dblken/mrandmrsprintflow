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

// ── Fetch Categories ────────────────────────────────────
$categories = [];
try {
    $categories = db_query("SELECT DISTINCT category FROM products WHERE status = 'Activated' AND category IS NOT NULL ORDER BY category ASC");
} catch (Exception $e) { }

// ── Fetch Customers for dropdown ────────────────────────
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
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Main CSS -->
    <link rel="stylesheet" href="/printflow/public/assets/css/style.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    
    <style>
        /* POS Specific Layout */
        body { margin: 0; padding: 0; overflow-y: hidden; }
        .pos-container {
            display: flex;
            flex: 1;
            height: 100%;
            background-color: #f1f5f9;
        }
        
        /* Left Panel - Product Grid */
        .pos-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border-right: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        
        /* Top Navigation in POS */
        .pos-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            background: #fff;
            box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);
            z-index: 10;
        }
        .pos-header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .pos-back-btn {
            color: #64748b;
            text-decoration: none;
            font-size: 1.25rem;
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }
        .pos-back-btn:hover { background: #f1f5f9; color: #0f172a; }
        
        .pos-search-bar {
            padding: 1rem 1.5rem;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            gap: 1rem;
        }
        .pos-search-input {
            flex: 1;
            padding: 0.625rem 1rem 0.625rem 2.5rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            width: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="%2394a3b8" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>') no-repeat 0.75rem center;
            background-size: 1rem;
        }
        .pos-search-input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
        .pos-category-select {
            padding: 0.625rem 1rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            background-color: #fff;
            min-width: 150px;
        }

        .pos-products {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 1rem;
            align-content: start;
        }
        .pos-product-card {
            background: #fff;
            border-radius: 0.75rem;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            user-select: none;
        }
        .pos-product-card:hover { border-color: #6366f1; transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); }
        .pos-product-card:active { transform: translateY(0); }
        .pos-product-card.no-stock { opacity: 0.6; cursor: not-allowed; border-color: #fca5a5; }
        .pos-product-img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            background: #f1f5f9;
        }
        .pos-product-info {
            padding: 0.75rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .pos-product-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .pos-product-price { color: #6366f1; font-weight: 700; font-size: 0.875rem; margin-top: auto; }
        .pos-product-stock { font-size: 0.75rem; color: #64748b; margin-top: 0.25rem; }

        /* Right Panel - Cart */
        .pos-right {
            width: 400px;
            background: #fff;
            display: flex;
            flex-direction: column;
            box-shadow: -4px 0 15px rgba(0,0,0,0.05);
            z-index: 20;
        }
        .pos-cart-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pos-cart-header h2 { margin: 0; font-size: 1.125rem; font-weight: 600; color: #0f172a; }
        
        /* Customer Selection block */
        .pos-customer-block {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .pos-customer-select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        .pos-add-customer-btn {
            background: none; border: none; padding: 0; margin: 0;
            color: #6366f1; font-size: 0.75rem; font-weight: 600; cursor: pointer;
        }
        .pos-add-customer-btn:hover { text-decoration: underline; }

        .pos-cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 1rem 1.5rem;
        }
        .pos-empty-cart {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #94a3b8;
            text-align: center;
        }
        .pos-empty-cart i { font-size: 3rem; margin-bottom: 1rem; color: #cbd5e1; }
        
        .pos-cart-item {
            display: flex;
            align-items: flex-start;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px dashed #e2e8f0;
        }
        .pos-cart-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .pos-cart-item-info { flex: 1; padding-right: 1rem; }
        .pos-cart-item-name { font-size: 0.875rem; font-weight: 600; color: #1e293b; margin-bottom: 0.25rem; }
        .pos-cart-item-price { font-size: 0.75rem; color: #64748b; }
        .pos-cart-item-qty {
            display: flex;
            align-items: center;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            overflow: hidden;
        }
        .pos-qty-btn {
            background: #f8fafc; border: none; width: 28px; height: 28px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: #475569; transition: all 0.15s;
        }
        .pos-qty-btn:hover { background: #e2e8f0; }
        .pos-qty-input {
            width: 32px; height: 28px; border: none; border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;
            text-align: center; font-size: 0.875rem; padding: 0;
            -moz-appearance: textfield;
        }
        .pos-qty-input::-webkit-outer-spin-button, .pos-qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .pos-cart-item-total { font-weight: 600; color: #0f172a; font-size: 0.875rem; text-align: right; min-width: 60px; margin-left: 1rem; }
        .pos-cart-item-remove { 
            color: #ef4444; background: none; border: none; cursor: pointer; padding: 0.5rem; margin-left: 0.5rem;
            opacity: 0.5; transition: opacity 0.2s;
        }
        .pos-cart-item-remove:hover { opacity: 1; }

        .pos-checkout-panel {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 1.5rem;
        }
        .pos-summary-row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.875rem; color: #475569; }
        .pos-summary-total { display: flex; justify-content: space-between; margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed #cbd5e1; font-size: 1.25rem; font-weight: 700; color: #0f172a; }
        
        .pos-payment-methods { display: flex; gap: 0.5rem; margin: 1.25rem 0;}
        .pos-payment-btn { 
            flex: 1; padding: 0.625rem; border: 1px solid #cbd5e1; background: #fff; border-radius: 0.375rem;
            font-size: 0.75rem; font-weight: 600; color: #475569; cursor: pointer; transition: all 0.2s;
            text-align: center;
        }
        .pos-payment-btn.active { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8; }
        
        .pos-tender-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
        .pos-tender-input { width: 120px; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; text-align: right; font-weight: 600; }
        
        .pos-checkout-btn {
            width: 100%; padding: 1rem; background: #6366f1; color: white; border: none; border-radius: 0.5rem;
            font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s;
            display: flex; justify-content: center; align-items: center; gap: 0.5rem;
        }
        .pos-checkout-btn:hover { background: #4f46e5; }
        .pos-checkout-btn:disabled { background: #94a3b8; cursor: not-allowed; opacity: 0.7; }

        /* Quick Add Modal */
        .pos-modal-backdrop {
            display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 100;
            align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.2s;
        }
        .pos-modal-backdrop.show { display: flex; opacity: 1; }
        .pos-modal { background: #fff; width: 100%; max-width: 400px; border-radius: 0.75rem; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); transform: scale(0.95); transition: transform 0.2s; }
        .pos-modal-backdrop.show .pos-modal { transform: scale(1); }
        .pos-modal-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .pos-modal-header h3 { margin: 0; font-size: 1.125rem; font-weight: 600; }
        .pos-modal-body { padding: 1.5rem; }
        .pos-modal-footer { padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0; background: #f8fafc; display: flex; justify-content: flex-end; gap: 0.75rem; }
        .pos-form-group { margin-bottom: 1rem; }
        .pos-form-group label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.375rem; color: #334155; }
        .pos-form-group input { width: 100%; padding: 0.625rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; }
        .pos-btn-secondary { padding: 0.625rem 1rem; background: #fff; border: 1px solid #cbd5e1; border-radius: 0.375rem; cursor: pointer; }
        .pos-btn-primary { padding: 0.625rem 1.5rem; background: #6366f1; color: #fff; border: none; border-radius: 0.375rem; cursor: pointer; font-weight: 500; }

        /* Receipt Toast/Overlay */
        .receipt-toast {
            position: fixed; top: 1.5rem; right: 1.5rem; background: #10b981; color: white;
            padding: 1rem 1.5rem; border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            display: flex; align-items: center; gap: 0.75rem; font-weight: 500;
            transform: translateX(120%); transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 50;
        }
        .receipt-toast.show { transform: translateX(0); }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" style="display: flex; flex-direction: column; overflow: hidden; height: 100vh;">
        <div class="pos-container">
        
        <!-- LEFT PANEL: Products -->
        <div class="pos-left">
            <div class="pos-header">
                <div class="pos-header-left">
                    <h1 style="margin:0; font-size:1.25rem; font-weight:600; color:#0f172a;">PrintFlow POS</h1>
                </div>
            <div style="font-size:0.875rem; color:#475569;">
                <i class="far fa-user-circle"></i> <?php echo htmlspecialchars($user_name); ?>
            </div>
        </div>

        <div class="pos-search-bar">
            <input type="text" id="pos-search" class="pos-search-input" placeholder="Search products by name or SKU...">
            <select id="pos-category" class="pos-category-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['category']); ?>"><?php echo htmlspecialchars($cat['category']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="pos-products" id="pos-products-grid">
            <!-- Loading skeleton -->
            <div style="text-align:center; grid-column: 1 / -1; padding: 2rem; color: #94a3b8;">
                <i class="fas fa-circle-notch fa-spin fa-2x"></i><br><br>Loading products...
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL: Cart -->
    <div class="pos-right">
        <div class="pos-cart-header">
            <h2>Current Sale</h2>
            <button class="pos-add-customer-btn" onclick="clearCart()" style="color:#ef4444;"><i class="fas fa-trash-alt"></i> Clear</button>
        </div>
        
        <div class="pos-customer-block">
            <label style="display:block;font-size:0.75rem;font-weight:600;color:#64748b;margin-bottom:0.25rem;text-transform:uppercase;">Customer</label>
            <select id="pos-customer" class="pos-customer-select">
                <option value="guest">Walk-in Customer (Guest)</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?php echo $c['customer_id']; ?>">
                        <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name'] . ($c['contact_number'] ? ' (' . $c['contact_number'] . ')' : '')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div style="text-align:right;">
                <button type="button" class="pos-add-customer-btn" onclick="openNewCustomerModal()"><i class="fas fa-plus"></i> New Customer</button>
            </div>
        </div>

        <div class="pos-cart-items" id="pos-cart-items">
            <div class="pos-empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <p>Select products to add to cart</p>
            </div>
        </div>

        <div class="pos-checkout-panel">
            <div class="pos-summary-row">
                <span>Subtotal</span>
                <span id="pos-subtotal">₱0.00</span>
            </div>
            <!-- Discount could go here later -->
            
            <div class="pos-summary-total">
                <span>Total</span>
                <span id="pos-total">₱0.00</span>
            </div>
            
            <div class="pos-payment-methods">
                <button type="button" class="pos-payment-btn active" onclick="setPaymentMethod('Cash')" id="pm-cash">Cash</button>
                <button type="button" class="pos-payment-btn" onclick="setPaymentMethod('GCash')" id="pm-gcash">GCash</button>
                <button type="button" class="pos-payment-btn" onclick="setPaymentMethod('Bank Transfer')" id="pm-bank">Bank Transfer</button>
            </div>
            <input type="hidden" id="pos-active-pm" value="Cash">
            
            <div class="pos-tender-row" id="pos-tender-row">
                <label style="font-size:0.875rem;color:#475569;font-weight:500;">Amount Tendered</label>
                <div>
                    <span style="color:#94a3b8;font-size:0.875rem;">₱</span>
                    <input type="number" id="pos-tendered" class="pos-tender-input" placeholder="0.00" oninput="calculateChange()">
                </div>
            </div>
            <div class="pos-summary-row" id="pos-change-row" style="margin-top:-0.75rem;margin-bottom:1.25rem;">
                <span>Change</span>
                <span id="pos-change" style="font-weight:600;color:#10b981;">₱0.00</span>
            </div>

            <button type="button" class="pos-checkout-btn" id="pos-checkout-btn" onclick="processCheckout()" disabled>
                <i class="fas fa-check-circle"></i> Complete Sale
            </button>
        </div>
        </div>
    </div>
</div>

<!-- Modal: Quick Add Customer -->
<div class="pos-modal-backdrop" id="new-customer-modal">
    <div class="pos-modal">
        <div class="pos-modal-header">
            <h3>Add Walk-in Customer</h3>
            <button type="button" class="pos-add-customer-btn" style="font-size:1.25rem;color:#64748b;" onclick="closeNewCustomerModal()">&times;</button>
        </div>
        <div class="pos-modal-body">
            <div class="pos-form-group">
                <label>First Name</label>
                <input type="text" id="nc-first" placeholder="Juan">
            </div>
            <div class="pos-form-group">
                <label>Last Name</label>
                <input type="text" id="nc-last" placeholder="Dela Cruz">
            </div>
            <div class="pos-form-group">
                <label>Phone Number (Optional)</label>
                <input type="tel" id="nc-phone" placeholder="09xxxxxxxxx">
            </div>
            <div class="pos-form-group">
                <label>Email (Optional)</label>
                <input type="email" id="nc-email" placeholder="juan@example.com">
            </div>
        </div>
        <div class="pos-modal-footer">
            <button type="button" class="pos-btn-secondary" onclick="closeNewCustomerModal()">Cancel</button>
            <button type="button" class="pos-btn-primary" onclick="saveNewCustomer()" id="nc-save-btn">Save & Select</button>
        </div>
    </div>
</div>

<div class="receipt-toast" id="receipt-toast">
    <i class="fas fa-check-circle fa-lg"></i>
    <div>
        <div style="font-size:1rem;">Sale Completed!</div>
        <div style="font-size:0.75rem;opacity:0.9;">Order #<span id="toast-order-id">--</span> has been processed.</div>
    </div>
</div>

<script>
// --- STATE ---
let products = [];
let cart = []; // array of {product_id, name, price, qty, stock}
let currentTotal = 0;

// --- INITIAL LOAD ---
document.addEventListener('DOMContentLoaded', () => {
    fetchProducts();
    
    // Search bindings
    document.getElementById('pos-search').addEventListener('input', renderProducts);
    document.getElementById('pos-category').addEventListener('change', renderProducts);
});

// --- API FETCH ---
async function fetchProducts() {
    try {
        const res = await fetch('/printflow/staff/api/get_products.php');
        const data = await res.json();
        if (data.success) {
            products = data.products;
            renderProducts();
        } else {
            document.getElementById('pos-products-grid').innerHTML = '<div style="grid-column:1/-1;color:#dc2626;padding:1rem;">Failed to load products.</div>';
        }
    } catch (e) {
        document.getElementById('pos-products-grid').innerHTML = '<div style="grid-column:1/-1;color:#dc2626;padding:1rem;">Network error.</div>';
    }
}

// --- RENDER PRODUCTS ---
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
    
    if (filtered.length === 0) {
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#64748b;padding:2rem;">No products found.</div>';
        return;
    }
    
    filtered.forEach(p => {
        const outOfStock = p.stock_quantity <= 0;
        const img = p.product_image ? '/printflow/' + p.product_image : '/printflow/public/assets/images/placeholder.jpg';
        
        const card = document.createElement('div');
        card.className = `pos-product-card ${outOfStock ? 'no-stock' : ''}`;
        if (!outOfStock) {
            card.onclick = () => addToCart(p);
        }
        
        card.innerHTML = `
            <img src="${img}" alt="${p.product_name}" class="pos-product-img" onerror="this.src='/printflow/public/assets/images/placeholder.jpg'">
            <div class="pos-product-info">
                <div class="pos-product-name">${p.product_name}</div>
                <div class="pos-product-price">₱${parseFloat(p.price).toFixed(2)}</div>
                <div class="pos-product-stock">Stock: ${p.stock_quantity > 0 ? p.stock_quantity : '<span style="color:#ef4444;">Out of Stock</span>'}</div>
            </div>
        `;
        grid.appendChild(card);
    });
}

// --- CART LOGIC ---
function addToCart(prod) {
    const existing = cart.find(i => i.product_id === prod.product_id);
    if (existing) {
        if (existing.qty < prod.stock_quantity) {
            existing.qty++;
        } else {
            alert('Cannot add more. Not enough stock.');
        }
    } else {
        cart.push({
            product_id: prod.product_id,
            name: prod.product_name,
            price: parseFloat(prod.price),
            qty: 1,
            stock: prod.stock_quantity
        });
    }
    renderCart();
}

function updateQty(id, delta) {
    const item = cart.find(i => i.product_id === id);
    if (!item) return;
    
    const newQty = item.qty + delta;
    if (newQty <= 0) {
        removeFromCart(id);
    } else if (newQty > item.stock) {
        alert('Cannot add more. Not enough stock.');
    } else {
        item.qty = newQty;
        renderCart();
    }
}

function removeFromCart(id) {
    cart = cart.filter(i => i.product_id !== id);
    renderCart();
}

function clearCart() {
    if(cart.length > 0 && confirm('Are you sure you want to clear the current sale?')) {
        cart = [];
        document.getElementById('pos-customer').value = 'guest';
        document.getElementById('pos-tendered').value = '';
        renderCart();
    }
}

function renderCart() {
    const itemsCont = document.getElementById('pos-cart-items');
    
    if (cart.length === 0) {
        itemsCont.innerHTML = `
            <div class="pos-empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <p>Select products to add to cart</p>
            </div>
        `;
        currentTotal = 0;
    } else {
        itemsCont.innerHTML = '';
        currentTotal = 0;
        
        cart.forEach(item => {
            const itemTotal = item.price * item.qty;
            currentTotal += itemTotal;
            
            const div = document.createElement('div');
            div.className = 'pos-cart-item';
            div.innerHTML = `
                <div class="pos-cart-item-info">
                    <div class="pos-cart-item-name">${item.name}</div>
                    <div class="pos-cart-item-price">₱${item.price.toFixed(2)}</div>
                </div>
                <div class="pos-cart-item-qty">
                    <button type="button" class="pos-qty-btn" onclick="updateQty(${item.product_id}, -1)"><i class="fas fa-minus" style="font-size:0.625rem;"></i></button>
                    <input type="text" class="pos-qty-input" value="${item.qty}" readonly>
                    <button type="button" class="pos-qty-btn" onclick="updateQty(${item.product_id}, 1)"><i class="fas fa-plus" style="font-size:0.625rem;"></i></button>
                </div>
                <div class="pos-cart-item-total">₱${itemTotal.toFixed(2)}</div>
                <button type="button" class="pos-cart-item-remove" onclick="removeFromCart(${item.product_id})"><i class="fas fa-times"></i></button>
            `;
            itemsCont.appendChild(div);
        });
    }
    
    // Update Totals
    const fTotal = '₱' + currentTotal.toFixed(2);
    document.getElementById('pos-subtotal').textContent = fTotal;
    document.getElementById('pos-total').textContent = fTotal;
    
    calculateChange();
    updateCheckoutState();
}

// --- PAYMENTS & CHECKOUT ---
function setPaymentMethod(method) {
    document.getElementById('pos-active-pm').value = method;
    document.querySelectorAll('.pos-payment-btn').forEach(b => b.classList.remove('active'));
    
    if (method === 'Cash') document.getElementById('pm-cash').classList.add('active');
    if (method === 'GCash') document.getElementById('pm-gcash').classList.add('active');
    if (method === 'Bank Transfer') document.getElementById('pm-bank').classList.add('active');
    
    // Show/hide tender row
    const tRow = document.getElementById('pos-tender-row');
    const cRow = document.getElementById('pos-change-row');
    if (method === 'Cash') {
        tRow.style.display = 'flex';
        cRow.style.display = 'flex';
    } else {
        tRow.style.display = 'none';
        cRow.style.display = 'none';
        document.getElementById('pos-tendered').value = '';
    }
    
    calculateChange();
    updateCheckoutState();
}

function calculateChange() {
    if (currentTotal === 0) {
        document.getElementById('pos-change').textContent = '₱0.00';
        return;
    }
    
    const method = document.getElementById('pos-active-pm').value;
    if (method === 'Cash') {
        const tendered = parseFloat(document.getElementById('pos-tendered').value) || 0;
        const change = tendered - currentTotal;
        document.getElementById('pos-change').textContent = change >= 0 ? `₱${change.toFixed(2)}` : '₱0.00';
        if (change < 0 && tendered > 0) {
            document.getElementById('pos-change').style.color = '#ef4444'; // red if insufficient
        } else {
            document.getElementById('pos-change').style.color = '#10b981'; // green
        }
    }
    updateCheckoutState();
}

function updateCheckoutState() {
    const btn = document.getElementById('pos-checkout-btn');
    if (cart.length === 0) {
        btn.disabled = true;
        return;
    }
    
    const method = document.getElementById('pos-active-pm').value;
    if (method === 'Cash') {
        const tendered = parseFloat(document.getElementById('pos-tendered').value) || 0;
        btn.disabled = tendered < currentTotal;
    } else {
        btn.disabled = false;
    }
}

async function processCheckout() {
    if (cart.length === 0) return;
    
    const btn = document.getElementById('pos-checkout-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    const customer_id = document.getElementById('pos-customer').value;
    const payment_method = document.getElementById('pos-active-pm').value;
    const amount_tendered = document.getElementById('pos-tendered').value || currentTotal;
    
    const payload = {
        action: 'walkin_checkout',
        customer_id: customer_id,
        payment_method: payment_method,
        amount_tendered: amount_tendered,
        items: cart.map(i => ({ id: i.product_id, qty: i.qty, price: i.price }))
    };
    
    try {
        const res = await fetch('/printflow/staff/api/pos_checkout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const data = await res.json();
        if (data.success) {
            // Show toast
            document.getElementById('toast-order-id').textContent = data.order_id;
            const toast = document.getElementById('receipt-toast');
            toast.classList.add('show');
            setTimeout(() => { toast.classList.remove('show'); }, 4000);
            
            // Clear cart & refetch products for new sizes
            cart = [];
            document.getElementById('pos-customer').value = 'guest';
            document.getElementById('pos-tendered').value = '';
            setPaymentMethod('Cash');
            renderCart();
            fetchProducts();
            
        } else {
            alert('Checkout failed: ' + (data.message || 'Unknown error'));
        }
    } catch (e) {
        alert('Network error during checkout.');
    } finally {
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Complete Sale';
        updateCheckoutState();
    }
}

// --- NEW CUSTOMER MODAL ---
function openNewCustomerModal() {
    document.getElementById('new-customer-modal').classList.add('show');
    document.getElementById('nc-first').focus();
}

function closeNewCustomerModal() {
    document.getElementById('new-customer-modal').classList.remove('show');
    document.getElementById('nc-first').value = '';
    document.getElementById('nc-last').value = '';
    document.getElementById('nc-phone').value = '';
    document.getElementById('nc-email').value = '';
}

async function saveNewCustomer() {
    const first = document.getElementById('nc-first').value.trim();
    const last = document.getElementById('nc-last').value.trim();
    const phone = document.getElementById('nc-phone').value.trim();
    const email = document.getElementById('nc-email').value.trim();
    
    if (!first || !last) {
        alert('First and Last name are required.');
        return;
    }
    
    const btn = document.getElementById('nc-save-btn');
    btn.disabled = true;
    btn.textContent = 'Saving...';
    
    try {
        const res = await fetch('/printflow/staff/api/pos_add_customer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ first_name: first, last_name: last, contact_number: phone, email: email })
        });
        const data = await res.json();
        
        if (data.success) {
            // Add to dropdown and select
            const sel = document.getElementById('pos-customer');
            const opt = document.createElement('option');
            opt.value = data.customer_id;
            opt.textContent = `${first} ${last} ${phone ? '('+phone+')' : ''}`;
            sel.appendChild(opt);
            sel.value = data.customer_id;
            
            closeNewCustomerModal();
        } else {
            alert(data.message || 'Failed to add customer');
        }
    } catch (e) {
        alert('Error communicating with server.');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Save & Select';
    }
}
</script>
</body>
</html>
