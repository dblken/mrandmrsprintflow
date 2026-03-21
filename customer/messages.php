<?php
/**
 * Customer Messages - List of order conversations
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
if (!defined('BASE_URL')) define('BASE_URL', '/printflow');

require_role('Customer');

$page_title = 'Messages - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width: 640px;">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Messages</h1>
        </div>
        <div class="card" style="padding: 0; overflow: hidden; border-radius: 1rem;">
            <div id="conversationsList" class="divide-y divide-gray-100">
                <div class="p-8 text-center text-gray-500" id="loadingState">Loading conversations...</div>
            </div>
            <div id="emptyState" style="display: none;" class="p-12 text-center">
                <div class="text-6xl mb-4">💬</div>
                <p class="text-gray-600 font-medium">No conversations yet</p>
                <p class="text-gray-400 text-sm mt-1">When you place an order, you can chat with us about it here.</p>
                <a href="<?php echo BASE_URL; ?>/customer/orders.php" class="inline-block mt-4 px-6 py-2.5 bg-[#0a2530] text-white font-bold rounded-lg hover:opacity-90 transition">View My Orders</a>
            </div>
        </div>
    </div>
</div>

<script>
const baseUrl = '<?php echo BASE_URL; ?>';

fetch('/printflow/public/api/chat/list_conversations.php')
    .then(r => r.json())
    .then(data => {
        document.getElementById('loadingState').style.display = 'none';
        if (!data.success || !data.conversations || data.conversations.length === 0) {
            document.getElementById('emptyState').style.display = 'block';
            return;
        }
        const list = document.getElementById('conversationsList');
        list.innerHTML = '';
        list.classList.remove('divide-y');
        data.conversations.forEach(c => {
            const a = document.createElement('a');
            a.href = baseUrl + '/customer/chat.php?order_id=' + c.order_id;
            a.className = 'block p-4 hover:bg-gray-50 transition flex items-start gap-4';
            a.style.textDecoration = 'none';
            a.style.color = 'inherit';
            const time = c.last_message_at ? formatTime(c.last_message_at) : '';
            a.innerHTML = `
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-bold text-gray-900">Order #${c.order_id}</span>
                        ${c.unread_count > 0 ? `<span class="bg-red-500 text-white text-xs font-bold rounded-full px-2 py-0.5">${c.unread_count}</span>` : ''}
                    </div>
                    <p class="text-sm text-gray-600 mt-0.5">${escapeHtml(c.service_name || 'Order')}</p>
                    ${c.last_message ? `<p class="text-sm text-gray-500 truncate mt-1">${escapeHtml(c.last_message)}</p>` : ''}
                </div>
                <span class="text-xs text-gray-400 flex-shrink-0">${time}</span>
            `;
            list.appendChild(a);
        });
    })
    .catch(() => {
        document.getElementById('loadingState').innerHTML = 'Failed to load. <a href="#" onclick="location.reload()">Retry</a>';
    });

function formatTime(d) {
    const dt = new Date(d);
    const now = new Date();
    const diff = (now - dt) / 1000;
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff/60) + 'm ago';
    if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff/86400) + 'd ago';
    return dt.toLocaleDateString();
}
function escapeHtml(t) {
    const d = document.createElement('div');
    d.textContent = t || '';
    return d.innerHTML;
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
