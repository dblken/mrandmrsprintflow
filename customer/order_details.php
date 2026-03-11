<?php
/**
 * Customer Order Details Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

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
        <a href="orders.php" class="back-link" style="display:inline-flex; align-items:center; gap:6px; color:#6b7280; margin-bottom:1rem; text-decoration:none;">← Back to My Orders</a>
        
        <div style="display:flex; justify-content:space-between; align-items:flex-end; gap:20px; margin-bottom:2rem; flex-wrap:wrap;">
            <div style="flex:1; min-width:200px;">
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
                    <h1 class="ct-page-title" style="margin:0;">Order #<?php echo $order_id; ?></h1>
                    <?php echo status_badge($order['status'], 'order'); ?>
                </div>
                <p style="margin:0; font-size:0.875rem; color:#6b7280;">Placed on <?php echo format_datetime($order['order_date']); ?></p>
            </div>
            
            <div style="display:flex; gap:12px; align-items:center;">
                <button type="button" onclick="openOrderChat(<?php echo $order_id; ?>, 'PrintFlow Support')" class="btn-primary" style="background:#4F46E5; color:white; border:none; padding:10px 20px; border-radius:10px; font-weight: 500; font-family: inherit; font-size: 0.9375rem; box-shadow:0 4px 6px -1px rgba(79,70,229,0.2);">
                    Message
                </button>

                <?php if ($order['status'] === 'For Revision'): ?>
                    <a href="edit_order.php?id=<?php echo $order_id; ?>" class="btn-primary" style="background:linear-gradient(135deg,#d97706,#f59e0b); color:white; border:none; padding:10px 20px; border-radius:10px; font-weight: 500; font-family: inherit; font-size: 0.9375rem; box-shadow:0 4px 6px -1px rgba(217,119,6,0.2);">
                        Edit order
                    </a>
                <?php endif; ?>

                <?php if (can_customer_cancel_order($order)): ?>
                    <button type="button" onclick="openCancelModal()" class="btn-secondary" style="color:#dc2626; border-color:#fecaca; padding:10px 20px; border-radius:10px; font-weight: 500; font-family: inherit; font-size: 0.9375rem;">
                        Cancel order
                    </button>
                <?php endif; ?>
            </div>
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

        <div class="card" style="margin-bottom:2rem;">
            <h2 style="font-size:1.1rem; font-weight:600; margin-bottom:1rem; border-bottom:1px solid #f3f4f6; padding-bottom:0.5rem;">Order Information</h2>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1.5rem;">
                <div>
                    <label style="display:block; font-size:0.875rem; color:#6b7280; margin-bottom:0.25rem;">Date Placed</label>
                    <div style="font-weight:600;"><?php echo format_datetime($order['order_date']); ?></div>
                </div>
                <div>
                    <label style="display:block; font-size:0.875rem; color:#6b7280; margin-bottom:0.25rem;">Total Amount</label>
                    <div style="font-weight:600;"><?php echo format_currency($order['total_amount']); ?></div>
                </div>
                <div>
                    <label style="display:block; font-size:0.875rem; color:#6b7280; margin-bottom:0.25rem;">Payment Status</label>
                    <div style="font-weight:600;"><?php echo status_badge($order['payment_status'], 'payment'); ?></div>
                </div>
                <div>
                    <label style="display:block; font-size:0.875rem; color:#6b7280; margin-bottom:0.25rem;">Estimated Completion</label>
                    <div style="font-weight:600;"><?php echo ($order['estimated_completion'] ?? null) ? format_date($order['estimated_completion']) : 'TBD'; ?></div>
                </div>
            </div>

            <?php if (!empty($order['notes'])): ?>
                <div style="margin-top:1.5rem; padding:1.25rem; background:#fffbeb; border:1px solid #fef3c7; border-radius:12px;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:0.5rem;">
                        <span style="font-size:1.1rem;">📝</span>
                        <h3 style="font-size:0.95rem; font-weight:700; color:#92400e; margin:0;">Your Order Notes</h3>
                    </div>
                    <div style="font-size:0.9rem; color:#b45309; line-height:1.5;">
                        <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card" style="padding:0; overflow:hidden;">
            <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #f3f4f6; display:flex; justify-content:space-between; align-items:center;">
                <h2 style="font-size:1.1rem; font-weight:700; color:#111827; margin:0;">Order Items</h2>
                <div style="font-size:0.875rem; color:#6b7280;"><?php echo count($items); ?> Items</div>
            </div>
            <div class="overflow-x-auto">
                <table style="width:100%; border-collapse:collapse;">
                    <thead style="background:#f9fafb; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;">
                        <tr>
                            <th style="padding:1rem 1.5rem; text-align:left; font-weight:600;">Product & Customization</th>
                            <th style="padding:1rem; text-align:center; font-weight:600;">Price</th>
                            <th style="padding:1rem; text-align:center; font-weight:600;">Quantity</th>
                            <th style="padding:1rem 1.5rem; text-align:right; font-weight:600;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody style="font-size:0.95rem;">
                        <?php foreach ($items as $item): ?>
                            <tr style="border-bottom:1px solid #f3f4f6;">
                                <td style="padding:1.5rem;">
                                    <div style="display:flex; gap:1rem; align-items:flex-start; justify-content:center;">
                                        <?php if (!empty($item['design_image']) || !empty($item['design_file'])): ?>
                                            <?php $design_url = "/printflow/public/serve_design.php?type=order_item&id=" . (int)$item['order_item_id']; ?>
                                            <a href="<?php echo $design_url; ?>" target="_blank" style="display: block; width:60px; height:60px; border-radius: 8px; overflow: hidden; border: 2px solid white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); flex-shrink:0;">
                                                <img src="<?php echo $design_url; ?>"
                                                     style="width:100%; height:100%; object-fit:cover;" 
                                                     alt="Design">
                                            </a>
                                        <?php else: ?>
                                            <div style="width:60px; height:60px; background:#f3f4f6; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; border:1px solid #e5e7eb; overflow:hidden;">
                                                <span style="font-size:1.5rem;">📦</span>
                                            </div>
                                        <?php endif; ?>
                                         <div style="min-width:0;">
                                             <?php 
                                                $p_name = $item['product_name'];
                                                $c_data = json_decode($item['customization_data'] ?? '{}', true);
                                                if (empty($p_name) || $p_name === 'Custom Order' || $p_name === 'Custom Product') {
                                                    if (!empty($c_data['service_type'])) {
                                                        $p_name = $c_data['service_type'];
                                                        if (!empty($c_data['product_type'])) {
                                                            $p_name .= " (" . $c_data['product_type'] . ")";
                                                        }
                                                    } else {
                                                        $p_name = "Custom Order";
                                                    }
                                                }
                                             ?>
                                             <div style="font-weight:700; color:#111827; margin-bottom:2px;"><?php echo htmlspecialchars($p_name); ?></div>
                                             <div style="font-size:0.75rem; color:#6b7280; margin-bottom:8px;"><?php echo htmlspecialchars($item['category'] ?: 'Signage'); ?></div>
                                            
                                            <div style="display: flex; gap: 2rem; margin-top: 12px; align-items: flex-start; justify-content:center;">
                                                <!-- Left: Customization Details Grid -->
                                                <div style="flex: 1.8; min-width: 0;">
                                                     <?php 
                                                        if (!empty($item['customization_data'])): 
                                                            $c_desc = '';
                                                            if ($c_data):
                                                        ?>
                                                                <div style="font-weight: 800; color: #0f172a; font-size: 1.25rem; margin-bottom: 0.5rem; text-align: left;"><?php echo htmlspecialchars($p_name); ?></div>
                                                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
                                                                    <div style="font-size: 0.75rem; font-weight: 800; color: #475569; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 12px; letter-spacing: 0.05em;">Product Specifications</div>
                                                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px;">
                                                                        <?php 
                                                                            foreach ($c_data as $ck => $cv):
                                                                                if (empty($cv) || $cv === 'No' || $cv === 'None' || $cv === 'none' || $ck === 'design_upload' || $ck === 'reference_upload') continue;
                                                                                
                                                                                // Specific exclusions for Reflectorized Temporary Plates
                                                                                $is_reflectorized = (strpos(strtolower($item['category'] ?? ''), 'reflectorized') !== false) || 
                                                                                                   (strpos(strtolower($c_data['service_type'] ?? ''), 'reflectorized') !== false);
                                                                                $is_temp_plate = strpos($c_data['product_type'] ?? '', 'Temporary Plate') !== false; $is_gate_pass = strpos($c_data['product_type'] ?? '', 'Gate Pass') !== false;
                                                                                $is_street_signage = strpos($c_data['product_type'] ?? '', 'Street') !== false;
                                                                                 $exclusions = ['unit', 'bg_color', 'text_color', 'arrow_direction', 'quantity', 'material_type', 'shape', 'with_border', 'rounded_corners', 'with_numbering', 'install_service', 'need_proof', 'reflective_color', 'inches', 'service_type', 'product_type', 'dimensions']; $gate_pass_only_exclusions = ['bg_color', 'text_color', 'reflective_color', 'text_content', 'arrow_direction', 'with_numbering', 'install_service', 'need_proof', 'temp_plate_text', 'product_type', 'dimensions', 'unit', 'shape', 'material_type', 'service_type'];
                                                                                 $street_signage_only_exclusions = ['bg_color', 'text_color', 'reflective_color', 'with_numbering', 'starting_number', 'mounting_option', 'temp_plate_text', 'product_type', 'dimensions', 'unit', 'shape', 'material_type', 'service_type'];
                                                                                
                                                                                if ($is_reflectorized && $is_temp_plate && (in_array($ck, $exclusions) || strtolower($cv) === 'inches')) continue;
                                                                                if ($is_reflectorized && $is_gate_pass && (in_array($ck, $gate_pass_only_exclusions) || $ck === 'quantity_gatepass')) continue;
                                                                                if ($is_reflectorized && $is_street_signage && in_array($ck, $street_signage_only_exclusions)) continue;
                                                                                
                                                                                if (strpos(strtolower($ck), 'description') !== false || $ck === 'notes'):
                                                                                    $c_desc = $cv;
                                                                                    continue;
                                                                                endif;
                                                                        ?>
                                                                        <div>
                                                                        <div style="font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.025em;"><?php echo ucwords(str_replace('_', ' ', $ck)); ?></div>
                                                                        <div style="font-size: 1rem; font-weight: 700; color: #1e293b; word-break: break-word; overflow-wrap: anywhere;"><?php echo htmlspecialchars($cv); ?></div>
                                                                    </div>
                                                                <?php 
                                                                        endforeach;
                                                                    endif;
                                                                ?>
                                                            </div>
                                                            
                                                            <?php if ($c_desc): ?>
                                                                <div style="margin-top: 12px; padding: 12px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px;">
                                                                    <div style="font-size: 0.75rem; font-weight: 800; color: #92400e; text-transform: uppercase; margin-bottom: 4px;">📝 Your Notes</div>
                                                                    <div style="font-size: 0.95rem; color: #b45309; line-height: 1.5; font-weight: 600; word-break: break-word; overflow-wrap: anywhere;"><?php echo nl2br(htmlspecialchars($c_desc)); ?></div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Right: Design Indicators & Previews -->
                                                <div style="flex: 1.2; min-width: 0; display: flex; flex-direction: column; gap: 12px;">
                                                    <?php if (!empty($item['design_image']) || !empty($item['design_file'])): ?>
                                                        <div style="background: #f0fdf4; border: 1px solid #dcfce7; border-radius: 12px; padding: 12px;">
                                                            <div style="font-size: 0.75rem; font-weight: 800; color: #166534; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.025em;">Final Design</div>
                                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                                <div style="width: 50px; height: 50px; border-radius: 8px; overflow: hidden; border: 2px solid #bbf7d0; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                                                    <img src="<?php echo $design_url; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                                                </div>
                                                                <div>
                                                                    <div style="font-size: 0.85rem; color: #15803d; font-weight: 800;">✅ Uploaded</div>
                                                                    <a href="<?php echo $design_url; ?>" target="_blank" style="font-size: 0.75rem; color: #166534; font-weight: 700; text-decoration: underline;">View Full Size</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($item['reference_image_file'])): ?>
                                                        <div style="background: #eff6ff; border: 1px solid #dbeafe; border-radius: 12px; padding: 12px;">
                                                            <div style="font-size: 0.75rem; font-weight: 800; color: #1e40af; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.025em;">Reference</div>
                                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                                <?php $ref_url = "/printflow/public/serve_design.php?type=order_item&id=" . (int)$item['order_item_id'] . "&field=reference"; ?>
                                                                <div style="width: 50px; height: 50px; border-radius: 8px; overflow: hidden; border: 2px solid #bfdbfe; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                                                    <img src="<?php echo $ref_url; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                                                </div>
                                                                <div>
                                                                    <div style="font-size: 0.85rem; color: #1e40af; font-weight: 800;">ℹ️ Reference</div>
                                                                    <a href="<?php echo $ref_url; ?>" target="_blank" style="font-size: 0.75rem; color: #2563eb; font-weight: 700; text-decoration: underline;">View Full Size</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding:1rem; text-align:center; color:#4b5563;">
                                    <?php echo format_currency($item['unit_price']); ?>
                                </td>
                                <td style="padding:1rem; text-align:center; font-weight:600; color:#111827;">
                                    <?php echo $item['quantity']; ?>
                                </td>
                                <td style="padding:1rem 1.5rem; text-align:right; font-weight:700; color:#111827;">
                                    <?php echo format_currency($item['unit_price'] * $item['quantity']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="padding:1.5rem; background:#f9fafb; border-top:2px solid #f3f4f6;">
                <div style="max-width:300px; margin-left:auto; display:flex; flex-direction:column; gap:12px;">
                    <div style="display:flex; justify-content:space-between; font-size:0.95rem; color:#6b7280;">
                        <span>Total Items Value</span>
                        <span><?php echo format_currency($order['total_amount']); ?></span>
                    </div>
                    <?php if (($order['downpayment_amount'] ?? 0) > 0): ?>
                        <div style="display:flex; justify-content:space-between; font-size:0.95rem; color:#92400e;">
                            <span>Downpayment Required</span>
                            <span><?php echo format_currency($order['downpayment_amount']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #e5e7eb; padding-top:12px; margin-top:4px;">
                        <span style="font-weight:700; color:#111827;">Total Amount</span>
                        <span style="font-size:1.5rem; font-weight:800; color:#4F46E5;"><?php echo format_currency($order['total_amount']); ?></span>
                    </div>
                    <?php if ($order['payment_status'] === 'Unpaid' && !in_array($order['status'], ['Downpayment Submitted', 'Cancelled'])): ?>
                        <div style="margin-top: 1rem; width: 100%;">
                            <button type="button" onclick="openPaymentModal()" class="btn-primary" style="background:linear-gradient(135deg,#10b981,#059669); color:white; border:none; padding:12px; border-radius:10px; font-weight: 500; font-family: inherit; font-size: 0.9375rem; box-shadow:0 4px 6px -1px rgba(16,185,129,0.2); width: 100%; text-align: center;">
                                Pay now
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
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

