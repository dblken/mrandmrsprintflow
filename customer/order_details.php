<?php
/**
 * Customer Order Details Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/order_ui_helper.php';

require_role('Customer');

$order_id = (int)($_GET['id'] ?? 0);
$customer_id = get_user_id();
// Mark notification as read if parameter present
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?", 'ii', [$notification_id, $customer_id]);
}

if (!$order_id) {
    redirect('orders.php');
}

// Get order details (ensure it belongs to the customer)
$order_result = db_query("
    SELECT * FROM orders 
    WHERE order_id = ? AND customer_id = ?
", 'ii', [$order_id, $customer_id]);

if (empty($order_result)) {
    // Order not found or doesn't belong to customer
    redirect('orders.php');
}
$order = $order_result[0];

// Get order items
$items = db_query("
    SELECT oi.*, p.name as product_name, p.category
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
", 'i', [$order_id]);

$page_title = "Order #{$order_id} - PrintFlow";
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/printflow/public/assets/css/chat.css">
<?php include __DIR__ . '/../includes/order_chat.php'; ?>

<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width:1100px;">
        
        <div style="display:flex; align-items:center; gap:1.5rem; margin-bottom:2rem;">
            <a href="orders.php" style="color:#6b7280; text-decoration:none; font-size:0.9rem; font-weight:700;">← Back to My Orders</a>
            <h1 class="ct-page-title" style="margin:0; flex:1; text-align:center;">Order Detail — #<?php echo $order_id; ?></h1>
            <div style="width:120px; text-align:right;">
                <?php echo status_badge($order['status'], 'order'); ?>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 340px; gap:2.5rem; align-items:start;">
            
            <!-- Left: Order Items & details -->
            <div style="display:flex; flex-direction:column; gap:2rem;">
                
                <!-- Date Alert / Breadcrumb equivalent -->
                <div style="padding:1.25rem; background:#000; color:#fff; border-radius:12px; font-weight:900; font-size:0.9rem; display:flex; justify-content:space-between; align-items:center;">
                    <span>Order Date: <?php echo format_datetime($order['order_date']); ?></span>
                    <button type="button" onclick="openOrderChat(<?php echo $order_id; ?>, 'PrintFlow Support')" style="background:#fff; color:#000; border:none; padding:6px 16px; border-radius:6px; font-weight:900; cursor:pointer; font-size:0.8rem;">
                        Message Support
                    </button>
                </div>



        <!-- Revision Required Alert -->
        <?php if ($order['status'] === 'For Revision'): ?>

            <div style="background-color: #eff6ff; border: 1px solid #dbeafe; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; display: flex; gap: 1rem; align-items: center;">
                <div style="background: #2563eb; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.25rem;">ℹ️</div>
                <div>
                    <h3 style="color: #1e40af; font-weight: 700; font-size: 1rem; margin-bottom: 0.25rem;">Revision Required</h3>
                    <p style="color: #1e3a8a; font-size: 0.875rem; line-height: 1.5; margin-bottom:0.5rem;">
                        The shop has requested a revision for this order. Please review the reason below, update your order details, and resubmit.
                    </p>
                    <div style="background:white; border:1px solid #bfdbfe; padding:12px; border-radius:8px; font-size:0.9rem; color:#1e40af;">
                        <strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($order['revision_reason'] ?? 'Not specified')); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Cancellation Alert for Cancelled Orders -->
        <?php if ($order['status'] === 'Cancelled'): ?>
            <div style="background-color: #fef2f2; border: 1px solid #fee2e2; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; display: flex; gap: 1rem; align-items: center;">
                <div style="background: #ef4444; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.25rem;">✕</div>
                <div>
                    <h3 style="color: #991b1b; font-weight: 700; font-size: 1rem; margin-bottom: 0.25rem;">This order has been cancelled</h3>
                    <p style="color: #b91c1c; font-size: 0.875rem; line-height: 1.5;">
                        <strong>Cancelled By:</strong> <?php echo htmlspecialchars($order['cancelled_by'] ?? 'N/A'); ?><br>
                        <strong>Reason:</strong> <?php echo htmlspecialchars($order['cancel_reason'] ?? 'Not specified'); ?><br>
                        <strong>Date:</strong> <?php echo !empty($order['cancelled_at']) ? format_datetime($order['cancelled_at']) : 'N/A'; ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Downpayment Required Alert (Only show if status is 'To Pay') -->
        <?php if ($order['status'] === 'To Pay' && $order['payment_status'] === 'Unpaid'): ?>
            <div style="background-color: #fff7ed; border: 1px solid #ffedd5; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; display: flex; gap: 1rem; align-items: center; border-left: 4px solid #f97316;">
                <div style="background: #f97316; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.25rem;">💳</div>
                <div style="flex: 1;">
                    <h3 style="color: #9a3412; font-weight: 700; font-size: 1rem; margin-bottom: 0.25rem;">Mandatory Downpayment Required</h3>
                    <p style="color: #c2410c; font-size: 0.875rem; line-height: 1.5; margin-bottom: 0.75rem;">
                        Your order will <strong>NOT</strong> be processed unless you pay at least 50% downpayment (<?php echo format_currency($order['total_amount'] * 0.5); ?>). 
                        Please upload your proof of payment to begin production.
                    </p>
                    <button onclick="openPaymentModal()" style="background:#f97316; color:white; border:none; padding:8px 16px; border-radius:8px; font-weight:500; font-family:inherit; font-size:0.875rem; cursor:pointer; transition:background 0.2s;" onmouseover="this.style.background='#ea580c'" onmouseout="this.style.background='#f97316'">
                        Submit downpayment proof
                    </button>>
                </div>
            </div>
        <?php endif; ?>

        <!-- Payment Submitted Status Alert -->
        <?php if ($order['status'] === 'Downpayment Submitted'): ?>
            <div style="background-color: #f0fdf4; border: 1px solid #dcfce7; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; display: flex; gap: 1rem; align-items: center; border-left: 4px solid #22c55e;">
                <div style="background: #22c55e; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.25rem;">⏳</div>
                <div>
                    <h3 style="color: #166534; font-weight: 700; font-size: 1rem; margin-bottom: 0.25rem;">Payment Submitted Successfully</h3>
                    <p style="color: #15803d; font-size: 0.875rem; line-height: 1.5;">
                        Waiting for staff verification. You will be notified once your downpayment has been approved.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Payment Modal -->
        <style>
            /* Custom scrollbar for modal */
            #paymentModal .card::-webkit-scrollbar {
                width: 8px;
            }
            #paymentModal .card::-webkit-scrollbar-track {
                background: #f1f5f9; 
                border-radius: 10px;
            }
            #paymentModal .card::-webkit-scrollbar-thumb {
                background: #cbd5e1; 
                border-radius: 10px;
            }
            #paymentModal .card::-webkit-scrollbar-thumb:hover {
                background: #94a3b8; 
            }
        </style>
        <div id="paymentModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center; padding:20px;">
            <div class="card" style="max-width:500px; width:100%; position:relative; border-radius: 20px; padding: 2rem; max-height: 90vh; overflow-y: auto;">
                <h2 style="font-size:1.5rem; font-weight:800; margin-bottom:0.5rem; color:#111827; display: flex; align-items: center; gap: 10px;">
                    Submit Payment
                </h2>
                <?php 
                $qr_dir = __DIR__ . '/../public/assets/uploads/qr/';
                $payment_cfg_path = $qr_dir . 'payment_methods.json';
                $payment_methods = file_exists($payment_cfg_path) ? json_decode(file_get_contents($payment_cfg_path), true) : [];
                if (!is_array($payment_methods)) $payment_methods = [];
                $enabled_methods = array_filter($payment_methods, function($m) { return !empty($m['enabled']); });

                if (empty($enabled_methods)): 
                ?>
                    <div style="background: #fef2f2; border: 1px solid #fee2e2; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; color: #b91c1c; font-size: 0.9rem;">
                        No online payment methods are currently configured by the shop. Please contact support.
                    </div>
                <?php else: ?>
                    <p style="color:#6b7280; font-size:0.9rem; margin-bottom:1rem;">Select a payment method and transfer at least 50% downpayment to start the process.</p>
                    
                    <!-- Payment Methods Tabs/Selector -->
                    <div style="display: flex; gap: 8px; margin-bottom: 1rem; overflow-x: auto; padding-bottom: 4px;">
                        <?php $first = true; foreach ($enabled_methods as $index => $pm): ?>
                            <button type="button" onclick="selectPaymentMethod(<?php echo $index; ?>)" id="btn-pm-<?php echo $index; ?>" class="pm-tab-btn" style="flex: 1; padding: 10px; border-radius: 10px; border: 2px solid <?php echo $first ? '#4F46E5' : '#e5e7eb'; ?>; background: <?php echo $first ? '#e0e7ff' : '#f9fafb'; ?>; color: <?php echo $first ? '#4F46E5' : '#4b5563'; ?>; font-weight: 700; font-family: inherit; font-size: 0.85rem; cursor: pointer; transition: all 0.2s; white-space: nowrap;">
                                <?php echo htmlspecialchars($pm['provider']); ?>
                            </button>
                        <?php $first = false; endforeach; ?>
                    </div>

                    <!-- Payment Provider Details -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem; text-align: center; min-height: 120px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                        <?php $first = true; foreach ($enabled_methods as $index => $pm): ?>
                            <div id="pm-details-<?php echo $index; ?>" style="display: <?php echo $first ? 'block' : 'none'; ?>; width: 100%;">
                                <?php if (!empty($pm['file'])): ?>
                                    <img src="/printflow/public/assets/uploads/qr/<?php echo htmlspecialchars($pm['file']); ?>?t=<?php echo time(); ?>" style="width: 120px; height: 120px; object-fit: contain; border-radius: 12px; border: 2px solid #e2e8f0; margin: 0 auto 10px auto; display: block; background: white;" alt="QR Code">
                                <?php endif; ?>
                                <div style="font-size: 1.1rem; font-weight: 800; color: #1e293b; margin-bottom: 4px;"><?php echo htmlspecialchars($pm['provider']); ?></div>
                                <?php if (!empty($pm['label'])): ?>
                                    <div style="font-size: 0.85rem; color: #64748b; font-weight: 600;"><?php echo htmlspecialchars($pm['label']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php $first = false; endforeach; ?>
                    </div>
                <?php endif; ?>

                <form id="paymentForm" enctype="multipart/form-data">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <?php echo csrf_field(); ?>
                    
                    <div style="margin-bottom:1.25rem;">
                        <label style="display:block; font-size:0.875rem; font-weight:700; color: #374151; margin-bottom:0.5rem;">Amount to Pay (PHP)</label>
                        <input type="number" name="amount" step="0.01" class="input-field" 
                               value="<?php echo number_format($order['total_amount'] * 0.5, 2, '.', ''); ?>" 
                               min="<?php echo number_format($order['total_amount'] * 0.5, 2, '.', ''); ?>" 
                               style="width:100%; font-size: 1.1rem; font-weight: 700; color: #4F46E5;" required>
                        <p style="font-size: 0.75rem; color: #6b7280; margin-top: 8px;">Min. 50%: <?php echo format_currency($order['total_amount'] * 0.5); ?></p>
                    </div>
                    
                    <div style="margin-bottom:1.5rem;">
                        <label style="display:block; font-size:0.875rem; font-weight:700; color: #374151; margin-bottom:0.5rem;">Upload Proof of Payment</label>
                        <div id="dropzone" style="border: 2px dashed #e2e8f0; border-radius: 12px; padding: 1.5rem; text-align: center; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#4F46E5'; this.style.background='#f5f3ff'" onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='transparent'">
                            <input type="file" name="payment_proof" id="proofInput" style="display: none;" accept="image/*" required>
                            <div id="uploadPlaceholder">
                                <span style="font-size: 2rem;">📸</span>
                                <p style="font-size: 0.875rem; color: #64748b; margin-top: 8px;">Click to upload or drag image</p>
                            </div>
                            <div id="filePreview" style="display: none; align-items: center; justify-content: center; flex-direction: column;">
                                <img id="previewImg" src="" style="max-height: 100px; border-radius: 8px; margin-bottom: 8px;">
                                <p id="fileName" style="font-size: 0.8rem; color: #1e293b; font-weight: 600;"></p>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display:flex; justify-content:flex-end; gap:12px;">
                        <button type="button" onclick="closePaymentModal()" class="btn-secondary" style="border-radius: 10px; font-weight: 500; font-family: inherit; font-size: 0.9375rem;">Cancel</button>
                        <button type="submit" id="submitPaymentBtn" class="btn-primary" style="background:#4F46E5; color:white; border-radius: 10px; padding: 10px 24px; font-weight: 500; font-family: inherit; font-size: 0.9375rem;">Submit payment</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Cancellation Modal -->
        <div id="cancelModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center; padding:20px;">
            <div class="card" style="max-width:500px; width:100%; position:relative;">
                <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:1rem; color:#111827;">Cancel Order #<?php echo $order_id; ?></h2>
                <p style="color:#6b7280; font-size:0.875rem; margin-bottom:1.5rem;">Please tell us why you want to cancel this order. This cannot be undone.</p>
                
                <form action="cancel_order.php" method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    
                    <div style="margin-bottom:1.5rem;">
                        <label style="display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.75rem;">Reason for Cancellation</label>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            <label style="display:flex; align-items:center; gap:8px; font-size:0.9rem; cursor:pointer;">
                                <input type="radio" name="reason" value="Wrong item ordered" required> Wrong item ordered
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:0.9rem; cursor:pointer;">
                                <input type="radio" name="reason" value="Found better price elsewhere"> Found better price elsewhere
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:0.9rem; cursor:pointer;">
                                <input type="radio" name="reason" value="Changed my mind"> Changed my mind
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; font-size:0.9rem; cursor:pointer;">
                                <input type="radio" name="reason" value="Other"> Other (Please specify below)
                            </label>
                        </div>
                    </div>
                    
                    <div style="margin-bottom:1.5rem;">
                        <label style="display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.5rem;">Additional Details (Optional)</label>
                        <textarea name="details" class="input-field" style="width:100%; min-height:80px; font-size:0.9rem;" placeholder="e.g. personal issue..."></textarea>
                    </div>
                    
                    <div style="display:flex; justify-content:flex-end; gap:12px;">
                        <button type="button" onclick="closeCancelModal()" class="btn-secondary">Keep Order</button>
                        <button type="submit" name="confirm_cancel" class="btn-primary" style="background:#dc2626; color:white;">Confirm Cancellation</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function openCancelModal() {
                document.getElementById('cancelModal').style.display = 'flex';
            }
            function closeCancelModal() {
                document.getElementById('cancelModal').style.display = 'none';
            }

            function openPaymentModal() {
                document.getElementById('paymentModal').style.display = 'flex';
            }
            function closePaymentModal() {
                document.getElementById('paymentModal').style.display = 'none';
            }

            function selectPaymentMethod(selectedIndex) {
                // Reset all tabs
                document.querySelectorAll('.pm-tab-btn').forEach(btn => {
                    btn.style.borderColor = '#e5e7eb';
                    btn.style.backgroundColor = '#f9fafb';
                    btn.style.color = '#4b5563';
                });
                
                // Set active tab
                const activeBtn = document.getElementById('btn-pm-' + selectedIndex);
                if (activeBtn) {
                    activeBtn.style.borderColor = '#4F46E5';
                    activeBtn.style.backgroundColor = '#e0e7ff';
                    activeBtn.style.color = '#4F46E5';
                }

                // Hide all details
                document.querySelectorAll('[id^="pm-details-"]').forEach(el => {
                    el.style.display = 'none';
                });
                
                // Show active details
                const activeDetails = document.getElementById('pm-details-' + selectedIndex);
                if (activeDetails) {
                    activeDetails.style.display = 'block';
                }
            }

            // File upload UI handling
            const dropzone = document.getElementById('dropzone');
            const proofInput = document.getElementById('proofInput');

            // Auto-open payment modal if ?pay=1 is in URL
            window.addEventListener('DOMContentLoaded', () => {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('pay') === '1') {
                    openPaymentModal();
                }
            });
            const uploadPlaceholder = document.getElementById('uploadPlaceholder');
            const filePreview = document.getElementById('filePreview');
            const previewImg = document.getElementById('previewImg');
            const fileName = document.getElementById('fileName');

            if (dropzone) {
                dropzone.addEventListener('click', () => proofInput.click());
                proofInput.addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    if (file) {
                        fileName.textContent = file.name;
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            previewImg.src = e.target.result;
                            uploadPlaceholder.style.display = 'none';
                            filePreview.style.display = 'flex';
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            // AJAX Submission
            const paymentForm = document.getElementById('paymentForm');
            if (paymentForm) {
                paymentForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const btn = document.getElementById('submitPaymentBtn');
                    btn.disabled = true;
                    btn.textContent = 'Submitting...';

                    const formData = new FormData(this);
                    
                    fetch('api_submit_payment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showSuccessModal(
                                '✅ Payment Submitted',
                                data.message,
                                'order_details.php?id=<?php echo $order_id; ?>',
                                'orders.php',
                                'View Order',
                                'Back to Orders'
                            );
                            closePaymentModal();
                        } else {
                            alert('Error: ' + data.message);
                            btn.disabled = false;
                            btn.textContent = 'Submit Payment';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An unexpected error occurred. Please try again.');
                        btn.disabled = false;
                        btn.textContent = 'Submit Payment';
                    });
                });
            }

            // Trigger success modal if success message exists
            window.addEventListener('DOMContentLoaded', () => {
                <?php if (isset($_SESSION['success'])): 
                    $msg = $_SESSION['success'];
                    unset($_SESSION['success']);
                ?>
                showSuccessModal(
                    '✅ Action Completed',
                    '<?php echo addslashes($msg); ?>',
                    'orders.php',
                    'services.php',
                    'View My Orders',
                    'Go to Dashboard'
                );
                <?php endif; ?>
            });
        </script>

                <!-- Items List -->
                <div style="display:flex; flex-direction:column;">
                    <?php foreach ($items as $item): ?>
                        <?php render_order_item_neubrutalism($item, false); ?>
                    <?php endforeach; ?>
                </div>

                <!-- Customer Details Card (Consistent with Review) -->
                <div class="card" style="border: 2px solid #000; box-shadow: 8px 8px 0px #000; padding: 2.5rem; background: #fff;">
                    <h3 style="font-size:1.1rem; font-weight:900; color:black; margin-bottom:1.5rem; display:flex; align-items:center; gap:12px; text-transform:uppercase; letter-spacing:0.04em;">
                        <span style="display:flex; align-items:center; justify-content:center; width:32px; height:32px; background:black; color:white; border-radius:6px; font-size:1rem;">📋</span>
                        Customer Information
                    </h3>
                    <?php 
                    $cust_res = db_query("SELECT * FROM users WHERE user_id = ?", 'i', [$order['customer_id']]);
                    $customer_info = $cust_res[0] ?? [];
                    ?>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:2.5rem;">
                        <div>
                            <div style="font-size:0.75rem; color:#6b7280; text-transform:uppercase; letter-spacing:.1em; font-weight:800; margin-bottom:8px;">Full Name</div>
                            <div style="font-weight:900; color:black; font-size:1.2rem;"><?php echo htmlspecialchars(($customer_info['first_name'] ?? '') . ' ' . ($customer_info['last_name'] ?? '')); ?></div>
                        </div>
                        <div>
                            <div style="font-size:0.75rem; color:#6b7280; text-transform:uppercase; letter-spacing:.1em; font-weight:800; margin-bottom:8px;">Phone Number</div>
                            <div style="font-weight:900; color:black; font-size:1.2rem;"><?php echo htmlspecialchars($customer_info['contact_number'] ?? '—'); ?></div>
                        </div>
                        <div>
                            <div style="font-size:0.75rem; color:#6b7280; text-transform:uppercase; letter-spacing:.1em; font-weight:800; margin-bottom:8px;">Email Address</div>
                            <div style="font-weight:900; color:black; font-size:1.2rem;"><?php echo htmlspecialchars($customer_info['email'] ?? ''); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Order Info & Totals -->
            <div style="display:flex; flex-direction:column; gap:1.5rem; position:sticky; top:20px;">
                <div class="card" style="padding:2rem; border: 2px solid #000; box-shadow: 8px 8px 0px #000;">
                    <h2 style="font-size:1rem; font-weight:900; color:black; margin:0 0 1.5rem 0; text-transform:uppercase; letter-spacing:0.06em; border-bottom:2px solid #000; padding-bottom:1rem;">Order Summary</h2>

                    <div style="display:flex; justify-content:space-between; font-size:1rem; margin-bottom:0.75rem; color:#000; font-weight:700;">
                        <span>Status</span>
                        <span><?php echo status_badge($order['status'], 'order'); ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:1rem; margin-bottom:0.75rem; color:#000; font-weight:700;">
                        <span>Payment</span>
                        <span><?php echo status_badge($order['payment_status'], 'payment'); ?></span>
                    </div>
                    
                    <div style="border-top:3px solid black; padding-top:1.25rem; margin-top:1.25rem; margin-bottom:2rem; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-weight:900; font-size:1rem; text-transform:uppercase;">Grand Total</span>
                        <span style="font-size:1.8rem; font-weight:900; color:black; letter-spacing:-0.03em;"><?php echo format_currency($order['total_amount']); ?></span>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:0.75rem;">
                        <?php if ($order['payment_status'] === 'Unpaid' && !in_array($order['status'], ['Downpayment Submitted', 'Cancelled'])): ?>
                            <button type="button" onclick="openPaymentModal()" style="width:100%; padding:14px; background:#000; color:#fff; font-size:1rem; font-weight:900; border:none; border-radius:10px; cursor:pointer; text-transform:uppercase;">
                                Pay now
                            </button>
                        <?php endif; ?>

                        <?php if ($order['status'] === 'For Revision'): ?>
                            <a href="edit_order.php?id=<?php echo $order_id; ?>" style="width:100%; padding:14px; background:#f59e0b; color:#fff; font-size:1rem; font-weight:900; border:2px solid #000; border-radius:10px; cursor:pointer; text-transform:uppercase; text-decoration:none; text-align:center;">
                                Edit order
                            </a>
                        <?php endif; ?>

                        <?php if (can_customer_cancel_order($order)): ?>
                            <button type="button" onclick="openCancelModal()" style="width:100%; padding:12px; background:white; color:#dc2626; font-size:0.9rem; font-weight:900; border:2px solid #fecaca; border-radius:10px; cursor:pointer;">
                                ✕ Cancel order
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($order['notes'])): ?>
                    <div style="padding:1.5rem; background:#fffbeb; border:2px solid #000; border-radius:12px; box-shadow:4px 4px 0px #000;">
                        <div style="font-size:0.75rem; font-weight:900; text-transform:uppercase; color:#92400e; margin-bottom:8px;">Order Notes</div>
                        <div style="font-size:0.95rem; color:#b45309; line-height:1.5; font-weight:700;">
                            <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<script>
window.addEventListener('DOMContentLoaded', () => {
    // Check for chat=open parameter to auto-trigger modal
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('chat') === 'open') {
        const unreadChatCount = <?php echo get_unread_chat_count($order_id, 'Customer'); ?>;
        // Only auto-open if there are actually unread messages, to prevent pop-on-refresh
        if (unreadChatCount > 0) {
            setTimeout(() => {
                openOrderChat(<?php echo $order_id; ?>, 'PrintFlow Support');
            }, 500); // Small delay to ensure everything is ready
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

