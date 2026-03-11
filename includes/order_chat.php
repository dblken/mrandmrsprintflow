<?php
/**
 * Shared Order Chat Component
 * PrintFlow - Order Chat System
 */
?>
<!-- Chat Modal Styles -->
<link rel="stylesheet" href="/printflow/public/assets/css/chat.css">

<!-- Chat Modal Overlay -->
<div id="chatModal" style="display: none; position: fixed; inset: 0; align-items: center; justify-content: center; z-index: 9999999; padding: 1.5rem; transition: opacity 0.2s ease;">
    <!-- Backdrop (Soft dark tint, NO BLUR) -->
    <div onclick="closeOrderChat()" style="position: absolute; inset: 0; background-color: rgba(0, 0, 0, 0.45);"></div>
    
    <!-- Modal Container -->
    <div id="chatModalContent" class="chat-container" style="position: relative; background-color: #ffffff; border-radius: 1.5rem; width: 500px; max-width: 100%; height: 650px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); transform: translateY(20px); transition: all 0.3s ease;">
        <div class="chat-header" style="padding: 1.25rem 1.5rem; background: #ffffff; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 id="chatOrderTitle" style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #111827; letter-spacing: -0.01em;">Order #—</h3>
                <div class="status-indicator">
                    <span id="partnerStatusDot" class="dot dot-offline"></span>
                    <span id="partnerStatusText">Offline</span>
                </div>
            </div>
            <button onclick="closeOrderChat()" class="chat-btn" style="color: #6b7280; padding: 0.5rem; border-radius: 9999px;">
                <svg style="width: 1.5rem; height: 1.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <div id="chatMessages" class="chat-messages" style="flex: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; background: #ffffff;">
            <!-- Messages load here -->
        </div>

        <div id="typingIndicator" class="typing-indicator" style="visibility: hidden; padding: 0.75rem 1.5rem; font-size: 0.75rem; color: #6b7280; font-style: italic; font-weight: 500; background: #ffffff;">
            Partner is typing...
        </div>

        <div class="chat-input-area" style="padding: 1.25rem 1.5rem; background: #ffffff !important; display: flex; align-items: center; gap: 0.75rem; border-top: 1px solid #f3f4f6;">
            <label class="chat-btn" style="padding: 0.5rem; margin: 0; border-radius: 9999px; cursor: pointer; color: #64748b;">
                <input type="file" id="chatImageInput" accept="image/*" style="display:none;">
                <svg style="width: 1.5rem; height: 1.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </label>
            <input type="text" id="chatTextInput" class="chat-input" placeholder="Type a message..." autocomplete="off" style="flex: 1; background: #ffffff !important; border: 1.5px solid #e2e8f0; border-radius: 9999px; padding: 0.75rem 1.25rem; color: #0f172a !important; font-size: 0.9375rem; outline: none;">
            <button id="chatSendBtn" class="chat-btn" title="Send (Enter)" style="color: #0084ff !important; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: none; border: none; cursor: pointer;">
                <svg style="width: 1.5rem; height: 1.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
            </button>
        </div>
    </div>
</div>

<!-- Image Lightbox -->
<div id="chatLightbox" class="chat-lightbox" onclick="this.style.display='none'">
    <img id="chatLightboxImg" src="" alt="Enlarged design">
</div>

<script>
let currentChatOrderId = null;
let lastMessageId = 0;
let chatPollingInterval = null;
let typingTimeout = null;
let isPartnerTyping = false;
let chatSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2358/2358-preview.mp3'); // Fallback public sound

function openOrderChat(orderId, headerTitle) {
    currentChatOrderId = orderId;
    lastMessageId = 0;
    document.getElementById('chatOrderTitle').innerText = headerTitle;
    document.getElementById('chatMessages').innerHTML = '';
    const modal = document.getElementById('chatModal');
    const content = document.getElementById('chatModalContent');
    
    modal.style.display = 'flex';
    void modal.offsetWidth; // Trigger reflow
    
    modal.style.opacity = '1';
    modal.style.pointerEvents = 'auto';
    content.style.transform = 'translateY(0)';
    
    document.body.style.overflow = 'hidden';
    document.getElementById('chatTextInput').focus();
    

    fetchMessages();
    
    // Start polling
    if (chatPollingInterval) clearInterval(chatPollingInterval);
    chatPollingInterval = setInterval(fetchMessages, 3000);
}

function closeOrderChat() {
    const modal = document.getElementById('chatModal');
    const content = document.getElementById('chatModalContent');
    
    modal.style.opacity = '0';
    modal.style.pointerEvents = 'none';
    content.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }, 300);
    
    clearInterval(chatPollingInterval);
    currentChatOrderId = null;
}

async function fetchMessages() {
    if (!currentChatOrderId) return;
    
    try {
        const response = await fetch(`/printflow/api/chat/fetch_messages.php?order_id=${currentChatOrderId}&last_id=${lastMessageId}`);
        const data = await response.json();
        
        if (data.success) {
            if (data.messages.length > 0) {
                const chatMessages = document.getElementById('chatMessages');
                data.messages.forEach(msg => {
                    appendMessage(msg);
                    lastMessageId = msg.id;
                });
                chatMessages.scrollTop = chatMessages.scrollHeight;
                
                // Play sound if new messages from partner
                const hasNewPartnerMsg = data.messages.some(m => !m.is_self);
                if (hasNewPartnerMsg) chatSound.play().catch(e => console.log('Audio play blocked'));
            }
            
            updatePartnerStatus(data.partner);
        }
    } catch (error) {
        console.error('Fetch error:', error);
    }
}

function appendMessage(msg) {
    const container = document.createElement('div');
    container.className = 'chat-bubble-container ' + (msg.is_self ? 'self' : 'other');
    
    // Inline styles to guarantee visibility and match "Messenger-like" redesign
    const isSelf = msg.is_self;
    const bubbleBg = isSelf ? '#0084ff' : '#f1f5f9';
    const textColor = isSelf ? '#ffffff' : '#1e293b';
    const alignSelf = isSelf ? 'flex-end' : 'flex-start';
    const borderRadius = isSelf ? '1.25rem 1.25rem 0.25rem 1.25rem' : '1.25rem 1.25rem 1.25rem 0.25rem';
    
    container.style.display = 'flex';
    container.style.flexDirection = 'column';
    container.style.maxWidth = '85%';
    container.style.alignSelf = alignSelf;
    container.style.marginBottom = '1rem';
    container.style.alignItems = isSelf ? 'flex-end' : 'flex-start';

    let contentHtml = '';
    if (msg.message_type === 'image' || msg.image_path) {
        contentHtml += `<img src="${msg.image_path}" class="chat-image" style="max-width: 100%; border-radius: 1rem; margin-bottom: 0.5rem; transition: transform 0.2s; cursor: pointer; border: 1px solid #f3f4f6;" onclick="showLightbox('${msg.image_path}')">`;
    }
    
    if (msg.message) {
        contentHtml += `<div class="chat-bubble" style="padding: 0.75rem 1rem; border-radius: ${borderRadius}; background: ${bubbleBg}; color: ${textColor}; font-size: 0.9375rem; line-height: 1.5; word-break: break-word; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
            ${escapeHtml(msg.message)}
        </div>`;
    }
    
    contentHtml += `<div class="chat-time" style="font-size: 0.7rem; color: #94a3b8; margin-top: 0.35rem;">${msg.created_at}</div>`;
    
    if (msg.is_self && msg.is_seen) {
        contentHtml += `<div class="chat-seen" style="font-size: 0.65rem; color: #6b7280; font-weight: 700; margin-top: 0.1rem;">Seen</div>`;
    }
    
    container.innerHTML = contentHtml;
    document.getElementById('chatMessages').appendChild(container);
}

function updatePartnerStatus(status) {
    const dot = document.getElementById('partnerStatusDot');
    const text = document.getElementById('partnerStatusText');
    const typing = document.getElementById('typingIndicator');
    
    if (status.is_online) {
        dot.className = 'dot dot-online';
        text.innerText = 'Online';
    } else {
        dot.className = 'dot dot-offline';
        text.innerText = 'Offline';
    }
    
    typing.style.visibility = status.is_typing ? 'visible' : 'hidden';
}

async function sendMessage() {
    const input = document.getElementById('chatTextInput');
    const message = input.value.trim();
    if (!message && !document.getElementById('chatImageInput').files[0]) return;
    
    const formData = new FormData();
    formData.append('order_id', currentChatOrderId);
    formData.append('message', message);
    
    const imageFile = document.getElementById('chatImageInput').files[0];
    if (imageFile) formData.append('image', imageFile);
    
    input.value = '';
    document.getElementById('chatImageInput').value = '';
    
    try {
        const response = await fetch('/printflow/api/chat/send_message.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            fetchMessages(); // Immediately pull back my message
        } else {
            alert(data.error || 'Failed to send message');
        }
    } catch (error) {
        console.error('Send error:', error);
    }
}

function handleTyping() {
    if (!currentChatOrderId) return;
    
    const formData = new FormData();
    formData.append('order_id', currentChatOrderId);
    formData.append('is_typing', 1);
    
    fetch('/printflow/api/chat/status.php', { method: 'POST', body: formData });
    
    if (typingTimeout) clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {
        const stopData = new FormData();
        stopData.append('order_id', currentChatOrderId);
        stopData.append('is_typing', 0);
        fetch('/printflow/api/chat/status.php', { method: 'POST', body: stopData });
    }, 3000);
}

function showLightbox(src) {
    document.getElementById('chatLightboxImg').src = src;
    document.getElementById('chatLightbox').style.display = 'flex';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Event Listeners
document.getElementById('chatTextInput').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') sendMessage();
    else handleTyping();
});

document.getElementById('chatSendBtn').addEventListener('click', sendMessage);

document.getElementById('chatImageInput').addEventListener('change', function() {
    if (this.files.length > 0) {
        sendMessage(); // Auto-send on file pick
    }
});
</script>
