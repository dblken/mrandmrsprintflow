<?php
/**
 * Staff Chat Dashboard - Messenger-style conversations + active chat
 * Dedicated staff messaging UI (no customer layout)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Staff', 'Admin', 'Manager']);

if (!defined('BASE_URL')) define('BASE_URL', '/printflow');

$page_title = 'Chats - PrintFlow';
$current_user = get_logged_in_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .chat-dashboard { display: grid; grid-template-columns: 320px 1fr; gap: 0; height: calc(100vh - 6rem); border-radius: 1rem; overflow: hidden; border: 1px solid #e5e7eb; background: #fff; }
        .chat-list-panel { border-right: 1px solid #e5e7eb; overflow-y: auto; transition: transform 0.25s ease; min-height: 0; }
        .chat-main-panel { display: flex; flex-direction: column; min-width: 0; min-height: 0; position: relative; overflow: hidden; }
        #chatActive { flex: 1; flex-direction: column; min-height: 0; overflow: hidden; }
        #staffChatMessages { flex: 1; min-height: 0; overflow-y: auto; overflow-x: hidden; padding: 1rem; background: #f1f5f9; display: flex; flex-direction: column; gap: 0.5rem; scroll-behavior: smooth; }
        #staffChatMessages img { max-height: 280px; width: auto; object-fit: contain; }
        #staffSendBtn { background: #0a2530 !important; color: #fff !important; border: none; border-radius: 12px; padding: 0.75rem 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: opacity 0.2s; }
        #staffSendBtn:hover { opacity: 0.9; }
        #staffSendBtn:active { opacity: 0.85; }
        .chat-conv-item { display: block; padding: 0.875rem 1rem; color: inherit; text-decoration: none; border-bottom: 1px solid #f1f5f9; transition: background 0.15s; }
        .chat-conv-item:hover { background: #f8fafc; }
        .chat-conv-item.active { background: #eff6ff; }
        .chat-bubble-self { background: #0a2530; color: #fff; border-radius: 16px 16px 4px 16px; padding: 0.6rem 1rem; }
        .chat-bubble-other { background: #fff; color: #111; border: 1px solid #e5e7eb; border-radius: 16px 16px 16px 4px; padding: 0.6rem 1rem; }
        .chat-bubble-system { background: #e0f2fe; color: #0c4a6e; border-radius: 12px; padding: 0.5rem 1rem; font-size: 0.85rem; }
        @media (max-width: 1023px) {
            .chat-dashboard { grid-template-columns: 1fr !important; height: calc(100vh - 5rem); }
            .chat-list-panel { position: absolute !important; top: 0; left: 0; bottom: 0; width: 280px !important; max-width: 85vw; z-index: 50; background: #fff; transform: translateX(-100%); box-shadow: none; }
            .chat-list-panel.mobile-open { transform: translateX(0); box-shadow: 4px 0 20px rgba(0,0,0,0.15); }
            .chat-list-overlay { display: none; }
            .chat-list-overlay.mobile-open { display: block; position: absolute; inset: 0; background: rgba(0,0,0,0.4); z-index: 40; }
            .chat-mobile-toggle { display: block !important; }
        }
        @media (min-width: 1024px) {
            .chat-list-overlay { display: none !important; }
            .chat-mobile-toggle { display: none !important; }
        }
        .order-details-modal-overlay { cursor: pointer; }
        .order-details-modal-content { cursor: default; }
        .order-details-section { margin-bottom: 1rem; }
    </style>
</head>
<body data-turbo="false">

<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <div class="main-content">
        <header style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h1 class="page-title" style="margin:0;">Chats</h1>
            <span style="font-size:14px; color:#6b7280;">Manage customer conversations</span>
        </header>

        <div class="chat-dashboard" style="position:relative;">
            <div id="chatListOverlay" class="chat-list-overlay" aria-hidden="true"></div>

            <!-- Left: Conversation List -->
            <div class="chat-list-panel">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between gap-2">
                    <div>
                        <h2 class="font-bold text-lg text-gray-900">Conversations</h2>
                        <p class="text-sm text-gray-500">Click to open chat</p>
                    </div>
                    <button type="button" id="chatListCloseBtn" class="chat-mobile-toggle lg:hidden p-2 rounded-lg hover:bg-gray-100 text-gray-600" aria-label="Close" style="display:none;">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div id="staffConversationsList" class="divide-y divide-gray-100">
                    <div class="p-6 text-center text-gray-500 text-sm">Loading...</div>
                </div>
            </div>

            <!-- Right: Active Chat -->
            <div class="chat-main-panel">
                <div class="chat-mobile-toggle lg:hidden absolute top-4 left-4 z-10" id="chatListOpenBtn" style="display:none;">
                    <button type="button" class="p-2 rounded-lg bg-white shadow border border-gray-200 hover:bg-gray-50 text-gray-700" aria-label="Open conversations">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                </div>
                <div id="chatPlaceholder" class="flex-1 flex items-center justify-center text-gray-400 p-8">
                    <div class="text-center">
                        <div class="text-5xl mb-3">💬</div>
                        <p class="font-medium">Select a conversation</p>
                        <p class="text-sm mt-1">Choose an order from the list to start chatting</p>
                    </div>
                </div>
                <div id="chatActive" style="display: none; flex: 1; flex-direction: column; min-height: 0; overflow: hidden;">
                    <div class="chat-header-bar px-4 py-3 border-b border-gray-200 flex items-center justify-between gap-3 bg-white flex-shrink-0">
                        <button type="button" id="chatHeaderMenuBtn" class="chat-mobile-toggle p-2 -ml-2 rounded-lg hover:bg-gray-100 text-gray-600 lg:hidden" aria-label="Open conversations">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <div class="min-w-0 flex-1">
                            <h3 id="activeOrderTitle" class="font-bold text-gray-900">Order #0</h3>
                            <p id="activeOrderMeta" class="text-sm text-gray-500">—</p>
                        </div>
                        <a id="activeOrderDetailsLink" href="#" class="text-sm font-medium text-[#0a2530] hover:underline flex-shrink-0">Order & Customer Details</a>
                    </div>
                    <div id="staffChatMessages"></div>
                    <div id="staffImagePreview" style="display: none; padding: 0.5rem 1rem; background: #fff; border-top: 1px solid #e5e7eb; flex-shrink: 0;"></div>
                    <div class="p-3 bg-white border-t border-gray-200 flex items-center gap-2 flex-shrink-0">
                        <label class="cursor-pointer p-2 rounded-lg hover:bg-gray-100">
                            <input type="file" id="staffImageInput" accept="image/*" multiple style="display:none">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </label>
                        <input type="text" id="staffTextInput" placeholder="Type a message..." class="flex-1 px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-[#0a2530]" autocomplete="off">
                        <button type="button" id="staffSendBtn">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lightbox for images -->
<div id="chatLightbox" onclick="this.style.display='none'" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:9999;align-items:center;justify-content:center;padding:1rem;cursor:pointer;">
    <img id="chatLightboxImg" src="" alt="Enlarged" style="max-width:100%;max-height:90vh;border-radius:8px;">
</div>

<!-- Order Details Modal -->
<div id="orderDetailsModal" class="order-details-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;padding:1rem;">
    <div class="order-details-modal-content" onclick="event.stopPropagation()" style="background:#fff;border-radius:16px;max-width:560px;width:100%;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="padding:1rem 1.25rem;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
            <h2 style="margin:0;font-size:1.25rem;font-weight:700;color:#111827;">Order & Customer Details</h2>
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
let activeOrderId = null;
let lastMessageId = 0;
let staffPollInterval = null;
let listPollInterval = null;
let staffSelectedImages = [];

function loadConversations() {
    fetch(window.baseUrl + '/public/api/chat/list_conversations.php', { credentials: 'same-origin' })
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.text();
        })
        .then(text => {
            let data;
            try { data = JSON.parse(text); } catch (e) { throw new Error('Invalid response'); }
            const list = document.getElementById('staffConversationsList');
            list.innerHTML = '';
            if (!data.success) {
                list.innerHTML = '<div class="p-6 text-center text-amber-600 text-sm">Unable to load. <a href="#" onclick="loadConversations();return false;" class="underline">Retry</a></div>';
                return;
            }
            if (!data.conversations || data.conversations.length === 0) {
                list.innerHTML = '<div class="p-6 text-center text-gray-500"><p class="font-medium">No conversations yet</p><p class="text-sm mt-1">When customers message about orders, they appear here.</p></div>';
                return;
            }
            data.conversations.forEach(c => {
                const a = document.createElement('a');
                a.href = '#';
                a.dataset.orderId = c.order_id;
                a.className = 'chat-conv-item ' + (activeOrderId === c.order_id ? 'active' : '');
                a.onclick = e => { e.preventDefault(); openStaffChat(c); };
                a.innerHTML = `
                    <div class="flex items-start justify-between gap-2" style="min-width:0;">
                        <div class="min-w-0 flex-1" style="flex:1;">
                            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                <span class="font-bold text-gray-900" style="font-size:0.95rem;">${escapeHtml(c.customer_name || 'Customer')}</span>
                                ${c.unread_count > 0 ? `<span style="background:#ef4444;color:white;font-size:10px;font-weight:700;padding:2px 6px;border-radius:10px;">${c.unread_count}</span>` : ''}
                            </div>
                            <p style="font-size:0.8rem;color:#6b7280;margin:2px 0 0 0;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(c.service_name || 'Order')}</p>
                            ${c.last_message ? `<p style="font-size:0.75rem;color:#9ca3af;margin:2px 0 0 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(c.last_message)}</p>` : ''}
                        </div>
                        <span style="font-size:0.7rem;color:#9ca3af;flex-shrink:0;">${formatTime(c.last_message_at)}</span>
                    </div>
                `;
                list.appendChild(a);
            });
        })
        .catch(() => {
            const list = document.getElementById('staffConversationsList');
            if (list) list.innerHTML = '<div class="p-6 text-center text-amber-600 text-sm">Could not load. <a href="#" onclick="loadConversations();return false;" class="underline">Retry</a></div>';
        });
}

function openStaffChat(c) {
    activeOrderId = c.order_id;
    lastMessageId = 0;
    staffSelectedImages = [];
    document.getElementById('chatPlaceholder').style.display = 'none';
    document.getElementById('chatActive').style.display = 'flex';
    document.getElementById('activeOrderTitle').textContent = (c.customer_name || 'Customer');
    document.getElementById('activeOrderMeta').textContent = 'Order #' + c.order_id + ' • ' + (c.service_name || 'Order') + (c.status ? ' • ' + c.status : '');
    const detailsLink = document.getElementById('activeOrderDetailsLink');
    detailsLink.href = '#';
    detailsLink.onclick = (e) => { e.preventDefault(); if (activeOrderId) openOrderDetailsModal(activeOrderId); };
    document.getElementById('staffChatMessages').innerHTML = '';
    document.getElementById('staffTextInput').value = '';
    renderStaffPreviews();
    loadStaffMessages();
    if (staffPollInterval) clearInterval(staffPollInterval);
    staffPollInterval = setInterval(loadStaffMessages, 3000);
    loadConversations();
    document.querySelectorAll('.chat-conv-item').forEach(el => {
        el.classList.toggle('active', el.dataset.orderId == c.order_id);
    });
    if (window.chatCloseList) window.chatCloseList();
}

function scrollStaffChatToBottom(smooth) {
    const box = document.getElementById('staffChatMessages');
    if (!box) return;
    const scroll = () => { box.scrollTop = box.scrollHeight; };
    if (smooth) {
        box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
    } else {
        requestAnimationFrame(() => { scroll(); requestAnimationFrame(scroll); });
    }
}

function loadStaffMessages() {
    if (!activeOrderId) return;
    const box = document.getElementById('staffChatMessages');
    fetch(window.baseUrl + '/public/api/chat/fetch_messages.php?order_id=' + activeOrderId + '&last_id=' + lastMessageId, { credentials: 'same-origin' })
        .then(r => r.ok ? r.json() : Promise.reject())
        .then(data => {
            if (data.success && data.messages && data.messages.length > 0) {
                const ph = box.querySelector('p.chat-empty-hint');
                if (ph) ph.remove();
                data.messages.forEach(m => appendStaffMessage(m));
                lastMessageId = data.messages[data.messages.length - 1].id;
                scrollStaffChatToBottom(false);
            } else if (lastMessageId === 0 && box && !box.querySelector('.chat-empty-hint')) {
                const empty = document.createElement('p');
                empty.className = 'chat-empty-hint';
                empty.style.cssText = 'text-align:center;color:#94a3b8;font-size:0.875rem;padding:1.5rem;';
                empty.textContent = 'No messages yet. Start the conversation!';
                box.appendChild(empty);
                scrollStaffChatToBottom(false);
            }
        })
        .catch(() => {});
}

function appendStaffMessage(msg) {
    const box = document.getElementById('staffChatMessages');
    const ph = box.querySelector('p.chat-empty-hint');
    if (ph) ph.remove();
    const div = document.createElement('div');
    const isSystem = msg.is_system || false;
    const isSelf = msg.is_self;
    div.style.cssText = 'display:flex;flex-direction:column;align-self:' + (isSystem ? 'center' : (isSelf ? 'flex-end' : 'flex-start')) + ';max-width:' + (isSystem ? '90%' : '85%') + ';margin-bottom:0.5rem;';
    let html = '';
    if (msg.image_path) {
        const src = msg.image_path.indexOf('/') === 0 ? msg.image_path : (window.baseUrl + '/' + msg.image_path.replace(/^\//, ''));
        html += '<img src="' + src + '" onclick="document.getElementById(\'chatLightboxImg\').src=this.src;document.getElementById(\'chatLightbox\').style.display=\'flex\'" style="max-width:100%;border-radius:12px;cursor:pointer;">';
    }
    if (msg.message) {
        const cls = isSystem ? 'chat-bubble-system' : (isSelf ? 'chat-bubble-self' : 'chat-bubble-other');
        html += '<div class="' + cls + '">' + escapeHtml(msg.message) + '</div>';
    }
    html += '<span style="font-size:0.65rem;color:#94a3b8;margin-top:2px;">' + (msg.created_at || '') + '</span>';
    div.innerHTML = html;
    box.appendChild(div);
    scrollStaffChatToBottom(false);
}

function sendStaffMessage() {
    if (!activeOrderId) return;
    const input = document.getElementById('staffTextInput');
    const text = input.value.trim();
    if (!text && staffSelectedImages.length === 0) return;
    const fd = new FormData();
    fd.append('order_id', activeOrderId);
    if (text) fd.append('message', text);
    staffSelectedImages.forEach(f => fd.append('image[]', f));
    input.value = '';
    staffSelectedImages = [];
    renderStaffPreviews();
    fetch(window.baseUrl + '/public/api/chat/send_message.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (data.success) { loadStaffMessages(); scrollStaffChatToBottom(true); }
            else alert(data.error || 'Failed');
        });
}

function renderStaffPreviews() {
    const area = document.getElementById('staffImagePreview');
    if (staffSelectedImages.length === 0) { area.style.display = 'none'; area.innerHTML = ''; return; }
    area.style.display = 'flex';
    area.innerHTML = staffSelectedImages.map((f, i) => {
        const url = URL.createObjectURL(f);
        return '<span style="position:relative"><img src="' + url + '" style="width:50px;height:50px;object-fit:cover;border-radius:6px;"><button type="button" onclick="staffSelectedImages.splice(' + i + ',1);renderStaffPreviews()" style="position:absolute;top:-4px;right:-4px;background:#ef4444;color:#fff;border:none;width:18px;height:18px;border-radius:50%;cursor:pointer;">×</button></span>';
    }).join('');
}

function formatTime(d) {
    if (!d) return '';
    const dt = new Date(d);
    const now = new Date();
    const diff = (now - dt) / 1000;
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
            const cust = data.customer || {};
            const o = data.order;
            const items = data.items || [];
            let html = '';

            // Section 1: Customer Information
            html += '<div class="order-details-section" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:1.25rem;margin-bottom:1.25rem;">';
            html += '<div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#1e40af;margin-bottom:0.75rem;">Customer Information</div>';
            html += '<div style="font-size:0.95rem;line-height:1.6;color:#1e293b;">';
            if (cust.full_name) html += '<div style="font-weight:600;margin-bottom:0.25rem;">' + escapeHtml(cust.full_name) + '</div>';
            if (cust.contact_number) html += '<div>' + escapeHtml(cust.contact_number) + '</div>';
            if (cust.email) html += '<div>' + escapeHtml(cust.email) + '</div>';
            if (cust.address) html += '<div style="margin-top:0.5rem;">' + escapeHtml(cust.address) + '</div>';
            if (!cust.full_name && !cust.contact_number && !cust.email && !cust.address) html += '<div style="color:#64748b;">No customer info</div>';
            html += '</div></div>';

            // Section 2: Order Details
            html += '<div class="order-details-section" style="margin-bottom:1rem;"><div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:0.75rem;">Order Details</div>';
            html += '<div><strong>Order #' + o.order_id + '</strong><br><span style="font-size:0.875rem;color:#6b7280;">' + escapeHtml(o.order_date) + ' • ' + escapeHtml(o.status) + '</span>';
            if (o.total_amount) html += '<br><span style="font-weight:700;color:#0a2530;">' + escapeHtml(o.total_amount) + '</span>';
            html += '</div></div>';
            if (o.notes) html += '<div class="order-details-section" style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:1rem;"><strong>📝 Notes</strong><p style="margin:0.5rem 0 0;font-size:0.9rem;color:#92400e;line-height:1.5;">' + escapeHtml(o.notes).replace(/\n/g, '<br>') + '</p></div>';
            if (o.revision_reason) html += '<div class="order-details-section" style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:1rem;"><strong>⚠️ Revision</strong><p style="margin:0.5rem 0 0;font-size:0.9rem;color:#991b1b;">' + escapeHtml(o.revision_reason).replace(/\n/g, '<br>') + '</p></div>';
            items.forEach(function(item) {
                html += '<div class="order-details-item" style="border:1px solid #e5e7eb;border-radius:12px;padding:1rem;margin-bottom:1rem;">';
                html += '<div style="display:flex;gap:1rem;align-items:flex-start;">';
                if (item.design_url) {
                    html += '<div><img src="' + item.design_url.replace(/"/g, '&quot;') + '" style="width:80px;height:80px;object-fit:cover;border-radius:8px;flex-shrink:0;" alt=""><a href="' + item.design_url.replace(/"/g, '&quot;') + '" target="_blank" download style="display:block;font-size:0.7rem;color:#0a2530;margin-top:4px;">Download</a></div>';
                } else html += '<div style="width:80px;height:80px;background:#f3f4f6;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">📦</div>';
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
                // Only show item-level notes when order has no notes (avoid duplicate)
                const itemNote = cust.notes || cust.additional_notes || cust.design_description || cust.tshirt_design_description || cust.tarp_design_description || cust.design_notes;
                if (itemNote && !o.notes) html += '<div style="margin-top:0.75rem;padding:0.75rem;background:#fffbeb;border-radius:8px;font-size:0.85rem;color:#92400e;">' + escapeHtml(itemNote).replace(/\n/g, '<br>') + '</div>';
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

document.getElementById('staffImageInput').addEventListener('change', function() {
    for (let i = 0; i < this.files.length; i++) staffSelectedImages.push(this.files[i]);
    renderStaffPreviews();
    this.value = '';
});
document.getElementById('staffSendBtn').addEventListener('click', sendStaffMessage);
document.getElementById('staffTextInput').addEventListener('keypress', e => { if (e.key === 'Enter') sendStaffMessage(); });

loadConversations();
listPollInterval = setInterval(loadConversations, 5000);

// Mobile drawer
(function() {
    const panel = document.querySelector('.chat-list-panel');
    const overlay = document.getElementById('chatListOverlay');
    const openBtn = document.getElementById('chatListOpenBtn');
    const closeBtn = document.getElementById('chatListCloseBtn');
    const headerMenuBtn = document.getElementById('chatHeaderMenuBtn');
    if (!panel || !overlay) return;
    function isMobile() { return window.innerWidth < 1024; }
    function openList() { if (!isMobile()) return; panel.classList.add('mobile-open'); overlay.classList.add('mobile-open'); if (closeBtn) closeBtn.style.display = ''; }
    function closeList() { panel.classList.remove('mobile-open'); overlay.classList.remove('mobile-open'); if (closeBtn) closeBtn.style.display = 'none'; }
    if (openBtn) openBtn.querySelector('button')?.addEventListener('click', openList);
    if (headerMenuBtn) headerMenuBtn.addEventListener('click', openList);
    if (closeBtn) closeBtn.addEventListener('click', closeList);
    overlay.addEventListener('click', closeList);
    document.getElementById('staffConversationsList')?.addEventListener('click', function(e) {
        if (isMobile() && e.target.closest('a')) setTimeout(closeList, 100);
    });
    window.addEventListener('resize', () => { if (!isMobile()) closeList(); });
    if (isMobile() && document.getElementById('chatPlaceholder')?.style.display !== 'none') openList();
    window.chatCloseList = closeList;
})();

document.addEventListener('turbo:load', function() {
    if (activeOrderId) scrollStaffChatToBottom(false);
});

// URL order_id
const urlOrderId = new URLSearchParams(window.location.search).get('order_id');
if (urlOrderId) {
    fetch(window.baseUrl + '/public/api/chat/list_conversations.php', { credentials: 'same-origin' })
        .then(r => r.ok ? r.json() : null)
        .then(data => {
            if (data?.success && data.conversations) {
                const c = data.conversations.find(x => x.order_id == urlOrderId);
                if (c) openStaffChat(c);
                else openStaffChat({ order_id: parseInt(urlOrderId), status: '', customer_name: 'Customer', service_name: 'Order', last_message: '', last_message_at: '', unread_count: 0 });
            }
            if (window.innerWidth < 1024 && window.chatCloseList) window.chatCloseList();
        });
}
</script>
</body>
</html>
