<?php
/**
 * Staff Chat Dashboard - Conversations + Active Chat
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Staff', 'Admin', 'Manager']);

$page_title = 'Chats - PrintFlow';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/staff_sidebar.php';
?>
<div class="flex-1 p-6">
    <div class="chat-dashboard" style="display: grid; grid-template-columns: 320px 1fr; gap: 0; height: calc(100vh - 8rem); border-radius: 1rem; overflow: hidden; border: 1px solid #e5e7eb; background: #fff;">
        <!-- Left: Conversation List -->
        <div class="chat-list-panel" style="border-right: 1px solid #e5e7eb; overflow-y: auto;">
            <div class="p-4 border-b border-gray-100">
                <h2 class="font-bold text-lg text-gray-900">Conversations</h2>
                <p class="text-sm text-gray-500">Click to open chat</p>
            </div>
            <div id="staffConversationsList" class="divide-y divide-gray-100">
                <div class="p-6 text-center text-gray-500 text-sm">Loading...</div>
            </div>
        </div>

        <!-- Right: Active Chat or Placeholder -->
        <div class="chat-main-panel" style="display: flex; flex-direction: column; min-width: 0;">
            <div id="chatPlaceholder" class="flex-1 flex items-center justify-center text-gray-400 p-8">
                <div class="text-center">
                    <div class="text-5xl mb-3">💬</div>
                    <p class="font-medium">Select a conversation</p>
                    <p class="text-sm mt-1">Choose an order from the list to start chatting</p>
                </div>
            </div>
            <div id="chatActive" style="display: none; flex: 1; flex-direction: column; min-height: 0;">
                <!-- Chat header -->
                <div class="chat-header-bar px-4 py-3 border-b border-gray-200 flex items-center justify-between bg-white">
                    <div>
                        <h3 id="activeOrderTitle" class="font-bold text-gray-900">Order #0</h3>
                        <p id="activeOrderMeta" class="text-sm text-gray-500">—</p>
                    </div>
                    <a id="activeOrderDetailsLink" href="#" class="text-sm font-medium text-[#0a2530] hover:underline">Order Details</a>
                </div>
                <!-- Messages -->
                <div id="staffChatMessages" style="flex: 1; overflow-y: auto; padding: 1rem; background: #f8fafc;"></div>
                <!-- Image preview -->
                <div id="staffImagePreview" style="display: none; padding: 0.5rem 1rem; background: #fff; border-top: 1px solid #e5e7eb;"></div>
                <!-- Input -->
                <div class="p-3 bg-white border-t border-gray-200 flex items-center gap-2">
                    <label class="cursor-pointer p-2 rounded-lg hover:bg-gray-100">
                        <input type="file" id="staffImageInput" accept="image/*" multiple style="display:none">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </label>
                    <input type="text" id="staffTextInput" placeholder="Type a message..." class="flex-1 px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-[#0a2530]" autocomplete="off">
                    <button type="button" id="staffSendBtn" class="p-2.5 bg-[#0a2530] text-white rounded-xl hover:opacity-90">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media (max-width: 768px) {
    .chat-dashboard { grid-template-columns: 1fr !important; }
    .chat-list-panel { max-height: 40vh; }
}
</style>

<script>
const baseUrl = '<?php echo BASE_URL; ?>';
let activeOrderId = null;
let lastMessageId = 0;
let staffPollInterval = null;
let staffSelectedImages = [];

function loadConversations() {
    fetch('/printflow/public/api/chat/list_conversations.php')
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('staffConversationsList');
            list.innerHTML = '';
            if (!data.success || !data.conversations || data.conversations.length === 0) {
                list.innerHTML = '<div class="p-6 text-center text-gray-500">No conversations</div>';
                return;
            }
            data.conversations.forEach(c => {
                const a = document.createElement('a');
                a.href = '#';
                a.className = 'block p-4 hover:bg-gray-50 transition ' + (activeOrderId === c.order_id ? 'bg-blue-50' : '');
                a.onclick = (e) => { e.preventDefault(); openStaffChat(c); };
                a.innerHTML = `
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <span class="font-bold text-gray-900">Order #${c.order_id}</span>
                            ${c.unread_count > 0 ? `<span class="ml-2 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded">${c.unread_count}</span>` : ''}
                            <p class="text-sm text-gray-600 truncate">${escapeHtml(c.customer_name || 'Customer')} • ${escapeHtml(c.service_name || 'Order')}</p>
                            ${c.last_message ? `<p class="text-xs text-gray-500 truncate">${escapeHtml(c.last_message)}</p>` : ''}
                        </div>
                        <span class="text-xs text-gray-400">${formatTime(c.last_message_at)}</span>
                    </div>
                `;
                list.appendChild(a);
            });
        });
}

function openStaffChat(c) {
    activeOrderId = c.order_id;
    lastMessageId = 0;
    staffSelectedImages = [];
    document.getElementById('chatPlaceholder').style.display = 'none';
    document.getElementById('chatActive').style.display = 'flex';
    document.getElementById('activeOrderTitle').textContent = 'Order #' + c.order_id;
    document.getElementById('activeOrderMeta').textContent = (c.customer_name || 'Customer') + ' • ' + (c.service_name || 'Order') + ' • ' + c.status;
    document.getElementById('activeOrderDetailsLink').href = baseUrl + '/staff/order_details.php?id=' + c.order_id;
    document.getElementById('staffChatMessages').innerHTML = '';
    document.getElementById('staffTextInput').value = '';
    renderStaffPreviews();
    loadStaffMessages();
    if (staffPollInterval) clearInterval(staffPollInterval);
    staffPollInterval = setInterval(loadStaffMessages, 3000);
    loadConversations();
}

function loadStaffMessages() {
    if (!activeOrderId) return;
    fetch('/printflow/public/api/chat/fetch_messages.php?order_id=' + activeOrderId + '&last_id=' + lastMessageId)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.messages && data.messages.length > 0) {
                data.messages.forEach(m => appendStaffMessage(m));
                lastMessageId = data.messages[data.messages.length - 1].id;
                document.getElementById('staffChatMessages').scrollTop = document.getElementById('staffChatMessages').scrollHeight;
            }
        });
}

function appendStaffMessage(msg) {
    const box = document.getElementById('staffChatMessages');
    const div = document.createElement('div');
    const isSystem = msg.is_system || false;
    const isSelf = msg.is_self; // Staff = self
    const align = isSystem ? 'center' : (isSelf ? 'flex-end' : 'flex-start');
    div.style.display = 'flex';
    div.style.flexDirection = 'column';
    div.style.alignSelf = align;
    div.style.maxWidth = isSystem ? '90%' : '85%';
    div.style.marginBottom = '0.5rem';

    let html = '';
    if (msg.image_path) html += `<img src="${msg.image_path}" style="max-width:100%;border-radius:12px;">`;
    if (msg.message) {
        const bg = isSystem ? '#e0f2fe' : (isSelf ? '#0a2530' : '#fff');
        const color = isSystem ? '#0c4a6e' : (isSelf ? '#fff' : '#111');
        html += `<div style="padding:0.6rem 1rem;border-radius:16px;background:${bg};color:${color};font-size:0.9rem;">${escapeHtml(msg.message)}</div>`;
    }
    html += `<span style="font-size:0.65rem;color:#94a3b8;">${msg.created_at}</span>`;
    div.innerHTML = html;
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
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

    fetch('/printflow/public/api/chat/send_message.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) loadStaffMessages();
            else alert(data.error || 'Failed');
        });
}

function renderStaffPreviews() {
    const area = document.getElementById('staffImagePreview');
    if (staffSelectedImages.length === 0) { area.style.display = 'none'; area.innerHTML = ''; return; }
    area.style.display = 'block';
    area.innerHTML = staffSelectedImages.map((f, i) => {
        const url = URL.createObjectURL(f);
        return `<span style="position:relative;display:inline-block"><img src="${url}" style="width:50px;height:50px;object-fit:cover;border-radius:6px;"><button type="button" onclick="staffSelectedImages.splice(${i},1);renderStaffPreviews()" style="position:absolute;top:-4px;right:-4px;background:#ef4444;color:#fff;border:none;width:18px;height:18px;border-radius:50%;cursor:pointer;font-size:12px;">×</button></span>`;
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

document.getElementById('staffImageInput').addEventListener('change', function() {
    for (let i = 0; i < this.files.length; i++) staffSelectedImages.push(this.files[i]);
    renderStaffPreviews();
    this.value = '';
});
document.getElementById('staffSendBtn').addEventListener('click', sendStaffMessage);
document.getElementById('staffTextInput').addEventListener('keypress', e => { if (e.key === 'Enter') sendStaffMessage(); });

loadConversations();

// Auto-open chat if order_id in URL
const urlOrderId = new URLSearchParams(window.location.search).get('order_id');
if (urlOrderId) {
    fetch('/printflow/public/api/chat/list_conversations.php')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.conversations) {
                const c = data.conversations.find(x => x.order_id == urlOrderId);
                if (c) openStaffChat(c);
                else {
                    openStaffChat({
                        order_id: parseInt(urlOrderId),
                        status: '',
                        customer_name: 'Customer',
                        service_name: 'Order',
                        last_message: '',
                        last_message_at: '',
                        unread_count: 0
                    });
                }
            }
        });
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
