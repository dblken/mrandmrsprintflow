<?php
/**
 * Customer Chat - Two-panel Messenger-style (conversation list + active chat)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
if (!defined('BASE_URL')) define('BASE_URL', '/printflow');

require_role('Customer');

$order_id = (int)($_GET['order_id'] ?? 0);
$customer_id = get_user_id();

// Mark notification as read when coming from notification link
if (isset($_GET['mark_read']) && $order_id) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?", 'ii', [$notification_id, $customer_id]);
    redirect(BASE_URL . '/customer/chat.php?order_id=' . $order_id);
}

// If order_id provided, verify access
if ($order_id) {
    $order = db_query("SELECT o.order_id, o.status, o.customer_id FROM orders o WHERE o.order_id = ? AND o.customer_id = ?", 'ii', [$order_id, $customer_id]);
    if (empty($order)) {
        redirect(BASE_URL . '/customer/messages.php');
    }
}

$page_title = $order_id ? "Chat - Order #{$order_id}" : 'Messages - PrintFlow';
$use_customer_css = true;
$use_chat_page = true;
$is_chat_page = true;
require_once __DIR__ . '/../includes/header.php';
?>
<style>
body.chat-page main#main-content { padding: 0 !important; min-height: 0 !important; }
body.chat-page #chat-outer { width: 100%; max-width: 960px; margin: 0 auto; min-height: calc(100vh - 64px); }
@media (max-width: 768px) { body.chat-page #chat-outer { max-width: 100%; } }
.customer-chat-dashboard { display: grid; grid-template-columns: 280px 1fr; gap: 0; height: calc(100vh - 64px); border-radius: 0 0 1rem 1rem; overflow: hidden; border: 1px solid #e5e7eb; border-top: none; background: #fff; }
.customer-chat-list { border-right: 1px solid #e5e7eb; overflow-y: auto; background: #fff; }
.customer-chat-main { display: flex; flex-direction: column; min-width: 0; min-height: 0; }
.customer-chat-item { display: block; padding: 0.875rem 1rem; color: inherit; text-decoration: none; border-bottom: 1px solid #f1f5f9; transition: background 0.15s; cursor: pointer; }
.customer-chat-item:hover { background: #f8fafc; }
.customer-chat-item.active { background: #eff6ff; }
.customer-chat-top-bar { background: #0a2530; color: #fff; padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-shrink: 0; }
.customer-chat-top-bar a { color: rgba(255,255,255,0.9); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; }
.customer-chat-top-bar a:hover { color: #fff; }
.customer-chat-top-bar .chat-center { flex: 1; min-width: 0; text-align: center; }
#customerChatMessages { flex: 1; min-height: 0; overflow-y: auto; padding: 1rem; background: #f1f5f9; display: flex; flex-direction: column; gap: 0.75rem; scroll-behavior: smooth; }
#customerChatMessages img { max-height: 280px; width: auto; object-fit: contain; }
#customerChatInputRow { padding: 0.75rem 1rem; background: #fff; border-top: 1px solid #e2e8f0; flex-shrink: 0; display: flex; align-items: center; gap: 0.5rem; }
#customerChatTextInput { flex: 1; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 1rem; outline: none; }
#customerChatTextInput:focus { border-color: #0a2530; }
#customerChatSendBtn { background: #0a2530; color: #fff; border: none; border-radius: 12px; padding: 0.75rem 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: opacity 0.2s; }
#customerChatSendBtn:hover { opacity: 0.9; }
.order-details-modal-overlay { cursor: pointer; }
.order-details-modal-content { cursor: default; }
.order-details-section { margin-bottom: 1rem; }
@media (max-width: 767px) {
    .customer-chat-dashboard { grid-template-columns: 1fr; }
    .customer-chat-list { position: absolute; top: 0; left: 0; bottom: 0; width: 280px; max-width: 85vw; z-index: 50; transform: translateX(-100%); transition: transform 0.25s; box-shadow: none; }
    .customer-chat-list.mobile-open { transform: translateX(0); box-shadow: 4px 0 20px rgba(0,0,0,0.15); }
    .customer-chat-overlay { display: none; }
    .customer-chat-overlay.mobile-open { display: block; position: absolute; inset: 0; background: rgba(0,0,0,0.4); z-index: 40; }
    .customer-chat-toggle { display: block !important; }
}
@media (min-width: 768px) {
    .customer-chat-overlay { display: none !important; }
    .customer-chat-toggle { display: none !important; }
}
</style>
<div id="chat-outer">
    <div class="customer-chat-dashboard" style="position:relative;">
        <div id="customerChatOverlay" class="customer-chat-overlay"></div>

        <!-- Left: Conversation List -->
        <div class="customer-chat-list">
            <div class="p-4 border-b border-gray-100">
                <h2 class="font-bold text-gray-900">Conversations</h2>
                <p class="text-sm text-gray-500">Click to open chat</p>
            </div>
            <div id="customerConversationsList">
                <div class="p-6 text-center text-gray-500 text-sm">Loading...</div>
            </div>
        </div>

        <!-- Right: Active Chat -->
        <div class="customer-chat-main">
            <div class="customer-chat-toggle absolute top-4 left-4 z-10" id="customerListOpenBtn" style="display:none;">
                <button type="button" class="p-2 rounded-lg bg-white shadow border border-gray-200 hover:bg-gray-50 text-gray-700" aria-label="Open conversations">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
            <div id="customerChatPlaceholder" class="flex-1 flex items-center justify-center text-gray-400 p-8">
                <div class="text-center">
                    <div class="text-5xl mb-3">💬</div>
                    <p class="font-medium">Select a conversation</p>
                    <p class="text-sm mt-1">Choose an order from the list to start chatting</p>
                </div>
            </div>
            <div id="customerChatActive" style="display:none;flex:1;flex-direction:column;min-height:0;overflow:hidden;">
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between bg-white flex-shrink-0">
                    <div class="min-w-0 flex-1">
                        <h3 id="customerOrderTitle" class="font-bold text-gray-900 m-0 truncate" style="font-size:1rem;">—</h3>
                        <p id="customerOrderMeta" class="text-sm text-gray-500 m-0 mt-0.5 truncate" style="font-size:0.85rem;">—</p>
                    </div>
                    <a id="customerOrderDetailsLink" href="#" class="text-sm font-medium text-[#0a2530] hover:underline flex-shrink-0 ml-3">Order Details</a>
                </div>
                <div id="customerChatMessages"></div>
                <div id="customerChatImagePreview" style="display:none;padding:0.5rem 1rem;background:#fff;border-top:1px solid #e5e7eb;flex-shrink:0;"></div>
                <div id="customerChatInputRow">
                    <label class="cursor-pointer p-2 rounded-lg hover:bg-gray-100" style="display:flex;">
                        <input type="file" id="customerChatImageInput" accept="image/*" multiple style="display:none">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </label>
                    <input type="text" id="customerChatTextInput" placeholder="Type a message..." autocomplete="off">
                    <button type="button" id="customerChatSendBtn">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="chatLightbox" onclick="this.style.display='none'" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:9999;align-items:center;justify-content:center;padding:1rem;cursor:pointer;">
    <img id="chatLightboxImg" src="" alt="Enlarged" style="max-width:100%;max-height:90vh;border-radius:8px;">
</div>

<!-- Order Details Modal -->
<div id="orderDetailsModal" class="order-details-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;padding:1rem;">
    <div class="order-details-modal-content" onclick="event.stopPropagation()" style="background:#fff;border-radius:16px;max-width:560px;width:100%;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="padding:1rem 1.25rem;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
            <h2 style="margin:0;font-size:1.25rem;font-weight:700;color:#111827;">Order Details</h2>
            <button type="button" onclick="closeOrderDetailsModal()" aria-label="Close" style="background:transparent;border:none;cursor:pointer;padding:0.5rem;border-radius:8px;color:#6b7280;" onmouseover="this.style.background='#f3f4f6';this.style.color='#111'" onmouseout="this.style.background='transparent';this.style.color='#6b7280'">
                <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div id="orderDetailsModalBody" style="flex:1;overflow-y:auto;padding:1.25rem;">
            <div class="text-center py-8 text-gray-500" id="orderDetailsLoading">Loading...</div>
            <div id="orderDetailsContent" style="display:none;"></div>
        </div>
    </div>
</div>

<script>
window.baseUrl = window.baseUrl || '<?php echo BASE_URL; ?>';
var activeOrderId = window.activeOrderId ?? <?php echo $order_id ? (int)$order_id : 'null'; ?>;
window.activeOrderId = activeOrderId;
let lastMessageId = 0;
let pollInterval = null;
let listPollInterval = null;
let selectedChatImages = [];

function loadConversations() {
    fetch(window.baseUrl + '/public/api/chat/list_conversations.php', { credentials: 'same-origin' })
        .then(r => r.ok ? r.text() : Promise.reject())
        .then(text => {
            let data;
            try { data = JSON.parse(text); } catch (e) { return; }
            const list = document.getElementById('customerConversationsList');
            list.innerHTML = '';
            if (!data.success || !data.conversations || data.conversations.length === 0) {
                list.innerHTML = '<div class="p-6 text-center text-gray-500"><p class="font-medium">No conversations yet</p><p class="text-sm mt-1">When you place an order, you can chat about it here.</p></div>';
                return;
            }
            data.conversations.forEach(c => {
                const a = document.createElement('a');
                a.href = '#';
                a.className = 'customer-chat-item ' + (activeOrderId === c.order_id ? 'active' : '');
                a.onclick = e => { e.preventDefault(); openCustomerChat(c); };
                a.innerHTML = `
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <span class="font-bold text-gray-900" style="font-size:0.95rem;">${escapeHtml(c.staff_name || 'PrintFlow Team')}</span>
                            ${c.unread_count > 0 ? `<span class="ml-2 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full font-semibold">${c.unread_count}</span>` : ''}
                            <p class="text-sm text-gray-500 truncate mt-0.5">${escapeHtml(c.service_name || 'Order')}</p>
                            ${c.last_message ? `<p class="text-xs text-gray-400 truncate mt-0.5">${escapeHtml(c.last_message)}</p>` : ''}
                        </div>
                        <span class="text-xs text-gray-400 flex-shrink-0">${formatTime(c.last_message_at)}</span>
                    </div>
                `;
                list.appendChild(a);
            });
        })
        .catch(() => {
            document.getElementById('customerConversationsList').innerHTML = '<div class="p-6 text-center text-amber-600 text-sm">Could not load. <a href="#" onclick="loadConversations();return false;" class="underline">Retry</a></div>';
        });
}

function openCustomerChat(c) {
    activeOrderId = c.order_id;
    lastMessageId = 0;
    selectedChatImages = [];
    document.getElementById('customerChatPlaceholder').style.display = 'none';
    document.getElementById('customerChatActive').style.display = 'flex';
    document.getElementById('customerOrderTitle').textContent = (c.staff_name || 'PrintFlow Team');
    document.getElementById('customerOrderMeta').textContent = (c.service_name || 'Order');
    const detailsLink = document.getElementById('customerOrderDetailsLink');
    detailsLink.href = '#';
    detailsLink.onclick = (e) => { e.preventDefault(); if (activeOrderId) openOrderDetailsModal(activeOrderId); };
    document.getElementById('customerChatMessages').innerHTML = '';
    document.getElementById('customerChatTextInput').value = '';
    renderPreviews();
    loadMessages();
    history.replaceState(null, '', window.baseUrl + '/customer/chat.php?order_id=' + c.order_id);
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(loadMessages, 4000);
    loadConversations();
    if (window.customerChatCloseList) window.customerChatCloseList();
}

function scrollChatToBottom(smooth) {
    const box = document.getElementById('customerChatMessages');
    if (!box) return;
    const scroll = () => { box.scrollTop = box.scrollHeight; };
    if (smooth) {
        box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
    } else {
        requestAnimationFrame(() => { scroll(); requestAnimationFrame(scroll); });
    }
}

function loadMessages() {
    if (!activeOrderId) return;
    const box = document.getElementById('customerChatMessages');
    fetch(window.baseUrl + '/public/api/chat/fetch_messages.php?order_id=' + activeOrderId + '&last_id=' + lastMessageId, { credentials: 'same-origin' })
        .then(r => r.ok ? r.json() : Promise.reject())
        .then(data => {
            if (data.success && data.messages && data.messages.length > 0) {
                const ph = box.querySelector('p.chat-empty-hint');
                if (ph) ph.remove();
                data.messages.forEach(m => appendMessage(m));
                lastMessageId = data.messages[data.messages.length - 1].id;
                scrollChatToBottom(false);
            } else if (lastMessageId === 0 && box && !box.querySelector('.chat-empty-hint')) {
                const ph = box.querySelector('p');
                if (!ph || !ph.classList.contains('chat-empty-hint')) {
                    const empty = document.createElement('p');
                    empty.className = 'chat-empty-hint';
                    empty.style.cssText = 'text-align:center;color:#94a3b8;font-size:0.875rem;padding:1.5rem;';
                    empty.textContent = 'No messages yet. Start the conversation!';
                    box.appendChild(empty);
                }
                scrollChatToBottom(false);
            }
        })
        .catch(() => {});
}

function appendMessage(msg) {
    const box = document.getElementById('customerChatMessages');
    const ph = box.querySelector('p.chat-empty-hint, p');
    if (ph) ph.remove();
    const div = document.createElement('div');
    const isSystem = msg.is_system || false;
    const isSelf = msg.is_self;
    const align = isSystem ? 'center' : (isSelf ? 'flex-end' : 'flex-start');
    div.style.cssText = 'display:flex;flex-direction:column;align-self:' + align + ';max-width:' + (isSystem ? '90%' : '85%') + ';margin-bottom:0.5rem;';
    let html = '';
    if (msg.image_path) html += '<img src="' + msg.image_path + '" onclick="document.getElementById(\'chatLightboxImg\').src=this.src;document.getElementById(\'chatLightbox\').style.display=\'flex\'" style="max-width:100%;border-radius:12px;cursor:pointer;">';
    if (msg.message) {
        const bg = isSystem ? '#e0f2fe' : (isSelf ? '#0a2530' : '#fff');
        const color = isSystem ? '#0c4a6e' : (isSelf ? '#fff' : '#111');
        const border = isSelf ? '' : ';border:1px solid #e5e7eb';
        html += '<div style="padding:0.6rem 1rem;border-radius:16px;background:' + bg + ';color:' + color + border + ';font-size:0.9rem;">' + escapeHtml(msg.message) + '</div>';
    }
    html += '<span style="font-size:0.65rem;color:#94a3b8;margin-top:2px;">' + (msg.created_at || '') + '</span>';
    div.innerHTML = html;
    box.appendChild(div);
    scrollChatToBottom(false);
}

function sendMessage() {
    if (!activeOrderId) return;
    const input = document.getElementById('customerChatTextInput');
    const text = input.value.trim();
    if (!text && selectedChatImages.length === 0) return;
    const fd = new FormData();
    fd.append('order_id', activeOrderId);
    if (text) fd.append('message', text);
    selectedChatImages.forEach(f => fd.append('image[]', f));
    input.value = '';
    selectedChatImages = [];
    renderPreviews();
    fetch(window.baseUrl + '/public/api/chat/send_message.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (data.success) { loadMessages(); scrollChatToBottom(true); }
            else alert(data.error || 'Failed to send');
        })
        .catch(() => alert('Failed to send'));
}

function renderPreviews() {
    const area = document.getElementById('customerChatImagePreview');
    if (selectedChatImages.length === 0) { area.style.display = 'none'; area.innerHTML = ''; return; }
    area.style.display = 'flex';
    area.innerHTML = selectedChatImages.map((f, i) => {
        const url = URL.createObjectURL(f);
        return '<div style="position:relative"><img src="' + url + '" style="width:60px;height:60px;object-fit:cover;border-radius:8px;"><button type="button" onclick="selectedChatImages.splice(' + i + ',1);renderPreviews()" style="position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;border:none;width:20px;height:20px;border-radius:50%;cursor:pointer;">×</button></div>';
    }).join('');
}

function formatTime(d) {
    if (!d) return '';
    const dt = new Date(d);
    const now = new Date();
    const diff = (now - dt) / 1000;
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff/60) + 'm';
    if (diff < 86400) return Math.floor(diff/3600) + 'h';
    return Math.floor(diff/86400) + 'd';
}
function escapeHtml(t) {
    const d = document.createElement('div');
    d.textContent = t || '';
    return d.innerHTML;
}

function openOrderDetailsModal(orderId) {
    const modal = document.getElementById('orderDetailsModal');
    const loading = document.getElementById('orderDetailsLoading');
    const content = document.getElementById('orderDetailsContent');
    modal.style.display = 'flex';
    loading.style.display = 'block';
    content.style.display = 'none';
    content.innerHTML = '';
    fetch(window.baseUrl + '/public/api/chat/order_details.php?order_id=' + orderId, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            loading.style.display = 'none';
            if (!data.success || !data.order) {
                content.innerHTML = '<p class="text-amber-600">Could not load order details.</p>';
                content.style.display = 'block';
                return;
            }
            const o = data.order;
            const items = data.items || [];
            let html = '';
            html += '<div class="order-details-section"><strong>Order #' + o.order_id + '</strong><br><span style="font-size:0.875rem;color:#6b7280;">' + escapeHtml(o.order_date) + ' • ' + escapeHtml(o.status) + '</span>';
            if (o.total_amount) html += '<br><span style="font-weight:700;color:#0a2530;">' + escapeHtml(o.total_amount) + '</span>';
            html += '</div>';
            if (o.notes) html += '<div class="order-details-section" style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:1rem;"><strong>📝 Notes</strong><p style="margin:0.5rem 0 0;font-size:0.9rem;color:#92400e;line-height:1.5;">' + escapeHtml(o.notes).replace(/\n/g, '<br>') + '</p></div>';
            if (o.revision_reason) html += '<div class="order-details-section" style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:1rem;"><strong>⚠️ Revision</strong><p style="margin:0.5rem 0 0;font-size:0.9rem;color:#991b1b;">' + escapeHtml(o.revision_reason).replace(/\n/g, '<br>') + '</p></div>';
            items.forEach(function(item) {
                html += '<div class="order-details-item" style="border:1px solid #e5e7eb;border-radius:12px;padding:1rem;margin-bottom:1rem;">';
                html += '<div style="display:flex;gap:1rem;align-items:flex-start;">';
                if (item.design_url) html += '<img src="' + item.design_url.replace(/"/g, '&quot;') + '" style="width:80px;height:80px;object-fit:cover;border-radius:8px;flex-shrink:0;" alt="">';
                else html += '<div style="width:80px;height:80px;background:#f3f4f6;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">📦</div>';
                html += '<div style="flex:1;min-width:0;"><h4 style="margin:0;font-size:1rem;font-weight:700;">' + escapeHtml(item.service_name) + '</h4>';
                if (item.category) html += '<span style="font-size:0.75rem;color:#6b7280;text-transform:uppercase;">' + escapeHtml(item.category) + '</span><br>';
                html += 'Qty: ' + item.quantity;
                if (item.subtotal) html += ' • ' + escapeHtml(item.subtotal);
                html += '</div></div>';
                const cust = item.customization || {};
                const skip = ['design_upload','reference_upload','notes','additional_notes','Branch_ID','service_type','product_type','unit'];
                const specs = Object.entries(cust).filter(([k,v]) => v !== '' && v != null && !skip.includes(k) && k.indexOf('description') === -1);
                if (specs.length) {
                    html += '<div style="margin-top:1rem;display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:0.5rem;">';
                    specs.forEach(function([k,v]) {
                        const label = k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                        html += '<div style="background:#f9fafb;padding:0.5rem;border-radius:6px;font-size:0.8rem;"><span style="color:#6b7280;">' + escapeHtml(label) + '</span><br><strong>' + escapeHtml(String(v)) + '</strong></div>';
                    });
                    html += '</div>';
                }
                const note = cust.notes || cust.additional_notes || cust.design_description || cust.tshirt_design_description || cust.tarp_design_description || cust.design_notes;
                if (note && (!o.notes || String(note).trim() !== String(o.notes).trim())) html += '<div style="margin-top:0.75rem;padding:0.75rem;background:#fffbeb;border-radius:8px;font-size:0.85rem;color:#92400e;">' + escapeHtml(note).replace(/\n/g, '<br>') + '</div>';
                if (item.reference_url) html += '<div style="margin-top:0.75rem;"><span style="font-size:0.75rem;color:#6b7280;">Reference:</span><br><img src="' + item.reference_url.replace(/"/g, '&quot;') + '" style="max-width:120px;max-height:120px;border-radius:8px;border:1px solid #e5e7eb;" alt="Reference"></div>';
                html += '</div>';
            });
            content.innerHTML = html || '<p class="text-gray-500">No items.</p>';
            content.style.display = 'block';
        })
        .catch(() => {
            loading.style.display = 'none';
            content.innerHTML = '<p class="text-amber-600">Failed to load order details.</p>';
            content.style.display = 'block';
        });
}
function closeOrderDetailsModal() {
    document.getElementById('orderDetailsModal').style.display = 'none';
}
document.getElementById('orderDetailsModal').addEventListener('click', function(e) {
    if (e.target === this) closeOrderDetailsModal();
});

document.getElementById('customerChatImageInput').addEventListener('change', function() {
    for (let i = 0; i < this.files.length; i++) selectedChatImages.push(this.files[i]);
    renderPreviews();
    this.value = '';
});
document.getElementById('customerChatSendBtn').addEventListener('click', sendMessage);
document.getElementById('customerChatTextInput').addEventListener('keypress', e => { if (e.key === 'Enter') sendMessage(); });

loadConversations();
listPollInterval = setInterval(loadConversations, 6000);

document.addEventListener('turbo:load', function() {
    if (activeOrderId) scrollChatToBottom(false);
});

if (activeOrderId) {
    fetch(window.baseUrl + '/public/api/chat/list_conversations.php', { credentials: 'same-origin' })
        .then(r => r.ok ? r.json() : null)
        .then(data => {
            if (data?.success && data.conversations) {
                const c = data.conversations.find(x => x.order_id == activeOrderId);
                if (c) openCustomerChat(c);
                else openCustomerChat({ order_id: activeOrderId, status: '', service_name: 'Order', staff_name: 'PrintFlow Team', last_message: '', last_message_at: '', unread_count: 0 });
            }
        });
}

(function() {
    const list = document.querySelector('.customer-chat-list');
    const overlay = document.getElementById('customerChatOverlay');
    const openBtn = document.getElementById('customerListOpenBtn');
    if (!list || !overlay) return;
    function isMobile() { return window.innerWidth < 768; }
    function openL() { if (isMobile()) { list.classList.add('mobile-open'); overlay.classList.add('mobile-open'); } }
    function closeL() { list.classList.remove('mobile-open'); overlay.classList.remove('mobile-open'); }
    if (openBtn) openBtn.querySelector('button')?.addEventListener('click', openL);
    overlay.addEventListener('click', closeL);
    document.getElementById('customerConversationsList')?.addEventListener('click', function(e) {
        if (isMobile() && e.target.closest('a')) setTimeout(closeL, 100);
    });
    window.addEventListener('resize', () => { if (!isMobile()) closeL(); });
    if (isMobile() && !activeOrderId) openL();
    window.customerChatCloseList = closeL;
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
