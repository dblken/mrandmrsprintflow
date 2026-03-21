<?php
/**
 * Customer Chat - Full page chat for an order
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
if (!defined('BASE_URL')) define('BASE_URL', '/printflow');

require_role('Customer');

$order_id = (int)($_GET['order_id'] ?? 0);
$customer_id = get_user_id();

if (!$order_id) {
    redirect(BASE_URL . '/customer/messages.php');
}

$order = db_query("SELECT o.order_id, o.status, o.customer_id FROM orders o WHERE o.order_id = ? AND o.customer_id = ?", 'ii', [$order_id, $customer_id]);
if (empty($order)) {
    redirect(BASE_URL . '/customer/messages.php');
}
$order = $order[0];

$service_name = 'Order';
$items = db_query("SELECT oi.customization_data, p.name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = ?", 'i', [$order_id]);
if (!empty($items)) {
    $custom = json_decode($items[0]['customization_data'] ?? '{}', true);
    $service_name = $custom['service_type'] ?? $items[0]['name'] ?? 'Order';
}

$page_title = "Chat - Order #{$order_id}";
$use_customer_css = true;
$is_chat_page = true;
require_once __DIR__ . '/../includes/header.php';
?>
<style>
body.chat-page main#main-content { padding: 0 !important; min-height: 0 !important; }
#chat-page-wrap { display: flex; flex-direction: column; min-height: calc(100vh - 64px); max-height: calc(100vh - 64px); background: #f1f5f9; }
#chat-page-wrap .chat-top-bar { background: #0a2530; color: #fff; padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-shrink: 0; }
#chat-page-wrap .chat-top-bar a { color: rgba(255,255,255,0.9); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; }
#chat-page-wrap .chat-top-bar a:hover { color: #fff; }
#chat-page-wrap .chat-top-bar .chat-center { flex: 1; min-width: 0; text-align: center; }
#chat-page-wrap .chat-top-bar h1 { margin: 0; font-size: 1.125rem; font-weight: 700; color: #fff; }
#chat-page-wrap .chat-top-bar .chat-meta { font-size: 0.875rem; color: rgba(255,255,255,0.8); margin-top: 2px; }
#chat-page-wrap .chat-top-bar .chat-status { display: inline-block; margin-top: 4px; padding: 2px 8px; font-size: 0.75rem; border-radius: 9999px; background: rgba(255,255,255,0.2); color: #fff; }
#chatMessages { flex: 1; min-height: 200px; overflow-y: auto; padding: 1rem; background: #f1f5f9; display: flex; flex-direction: column; gap: 0.75rem; }
#chat-input-row { padding: 0.75rem 1rem; background: #fff; border-top: 1px solid #e2e8f0; display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; }
#chatTextInput { flex: 1; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 1rem; outline: none; background: #fff; color: #111; }
#chatTextInput:focus { border-color: #0a2530; }
#chatSendBtn { padding: 0.75rem 1rem; background: #0a2530; color: #fff; border: none; border-radius: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
#chatSendBtn:hover { opacity: 0.9; }
</style>
<div id="chat-page-wrap">
    <!-- Top bar -->
    <div class="chat-top-bar">
        <a href="<?php echo BASE_URL; ?>/customer/messages.php">
            <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back
        </a>
        <div class="chat-center">
            <h1>Order #<?php echo $order_id; ?></h1>
            <p class="chat-meta"><?php echo htmlspecialchars($service_name); ?></p>
            <span class="chat-status"><?php echo htmlspecialchars($order['status']); ?></span>
        </div>
        <a href="<?php echo BASE_URL; ?>/customer/order_details.php?id=<?php echo $order_id; ?>">Order Details</a>
    </div>

    <!-- Chat area -->
    <div id="chatMessages">
        <p style="color:#64748b;font-size:0.9rem;margin:0;padding:0.5rem 0;">No messages yet. Say hello!</p>
    </div>

    <!-- Image preview area -->
    <div id="chatImagePreviewArea" style="display: none; padding: 0.5rem 1rem; background: #fff; border-top: 1px solid #e5e7eb; gap: 0.5rem; flex-wrap: wrap;"></div>

    <!-- Input area -->
    <div id="chat-input-row">
        <label style="cursor:pointer;padding:8px;border-radius:8px;display:flex;">
            <input type="file" id="chatImageInput" accept="image/*" multiple style="display:none">
            <svg style="width:24px;height:24px;color:#64748b;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </label>
        <input type="text" id="chatTextInput" placeholder="Type a message..." autocomplete="off">
        <button type="button" id="chatSendBtn">
            <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </button>
    </div>
</div>

<!-- Lightbox -->
<div id="chatLightbox" onclick="this.style.display='none'" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:9999;align-items:center;justify-content:center;padding:1rem;cursor:pointer;">
    <img id="chatLightboxImg" src="" alt="Enlarged" style="max-width:100%;max-height:90vh;border-radius:8px;">
</div>

<script>
const ORDER_ID = <?php echo $order_id; ?>;
let lastMessageId = 0;
let selectedChatImages = [];

async function fetchMessages() {
    try {
        const r = await fetch('/printflow/public/api/chat/fetch_messages.php?order_id=' + ORDER_ID + '&last_id=' + lastMessageId);
        const data = await r.json();
        if (data.success && data.messages && data.messages.length > 0) {
            const box = document.getElementById('chatMessages');
            data.messages.forEach(m => appendMessage(m));
            lastMessageId = data.messages[data.messages.length - 1].id;
            box.scrollTop = box.scrollHeight;
        }
    } catch (e) { console.error(e); }
}

function appendMessage(msg) {
    const box = document.getElementById('chatMessages');
    const placeholder = box.querySelector('p');
    if (placeholder) placeholder.remove();
    const div = document.createElement('div');
    const isSystem = msg.is_system || false;
    const isSelf = msg.is_self;
    const align = isSystem ? 'center' : (isSelf ? 'flex-end' : 'flex-start');
    div.style.display = 'flex';
    div.style.flexDirection = 'column';
    div.style.alignSelf = align;
    div.style.maxWidth = isSystem ? '90%' : '85%';
    div.style.marginBottom = '0.5rem';

    let html = '';
    if (msg.image_path) html += `<img src="${msg.image_path}" onclick="document.getElementById('chatLightboxImg').src=this.src;document.getElementById('chatLightbox').style.display='flex'" style="max-width:100%;border-radius:12px;cursor:pointer;">`;
    if (msg.message) {
        const bg = isSystem ? '#e0f2fe' : (isSelf ? '#0a2530' : '#fff');
        const color = isSystem ? '#0c4a6e' : (isSelf ? '#fff' : '#111');
        html += `<div style="padding:0.6rem 1rem;border-radius:16px;background:${bg};color:${color};font-size:0.9rem;">${escapeHtml(msg.message)}</div>`;
    }
    html += `<span style="font-size:0.65rem;color:#94a3b8;margin-top:2px;">${msg.created_at}</span>`;
    div.innerHTML = html;
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
}

function escapeHtml(t) {
    const d = document.createElement('div');
    d.textContent = t || '';
    return d.innerHTML;
}

async function sendMessage() {
    const input = document.getElementById('chatTextInput');
    const text = input.value.trim();
    if (!text && selectedChatImages.length === 0) return;

    const fd = new FormData();
    fd.append('order_id', ORDER_ID);
    if (text) fd.append('message', text);
    selectedChatImages.forEach(f => fd.append('image[]', f));
    input.value = '';
    selectedChatImages = [];
    renderPreviews();

    try {
        const r = await fetch('/printflow/public/api/chat/send_message.php', { method: 'POST', body: fd });
        const data = await r.json();
        if (data.success) fetchMessages();
        else alert(data.error || 'Failed to send');
    } catch (e) { alert('Failed to send'); }
}

function renderPreviews() {
    const area = document.getElementById('chatImagePreviewArea');
    if (selectedChatImages.length === 0) { area.style.display = 'none'; area.innerHTML = ''; return; }
    area.style.display = 'flex';
    area.innerHTML = selectedChatImages.map((f, i) => {
        const url = URL.createObjectURL(f);
        return `<div style="position:relative"><img src="${url}" style="width:60px;height:60px;object-fit:cover;border-radius:8px;"><button type="button" onclick="selectedChatImages.splice(${i},1);renderPreviews()" style="position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;border:none;width:20px;height:20px;border-radius:50%;cursor:pointer;font-size:14px;">×</button></div>`;
    }).join('');
}

document.getElementById('chatImageInput').addEventListener('change', function() {
    for (let i = 0; i < this.files.length; i++) selectedChatImages.push(this.files[i]);
    renderPreviews();
    this.value = '';
});

document.getElementById('chatSendBtn').addEventListener('click', sendMessage);
document.getElementById('chatTextInput').addEventListener('keypress', e => { if (e.key === 'Enter') sendMessage(); });

fetchMessages();
setInterval(fetchMessages, 4000);
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
