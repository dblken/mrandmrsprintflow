/**
 * Customer Inquiries Inbox - API-driven table + modal
 */
(function() {
    const API = '/printflow/admin/api_chatbot_conversations.php';
    let currentFilter = 'all';
    let currentPage = 1;
    let currentSearch = '';
    const perPage = 15;

    const container = document.getElementById('inq-table-wrap');
    const tableBody = document.getElementById('inq-tbody');
    const searchInput = document.getElementById('inq-search');
    const filterTabs = document.querySelectorAll('[data-filter]');
    const paginationEl = document.getElementById('inq-pagination');
    const modal = document.getElementById('modal-conversation');
    const modalTitle = document.getElementById('modal-conv-title');
    const modalMessages = document.getElementById('modal-conv-messages');
    const modalReplyInput = document.getElementById('modal-reply-input');
    const modalReplyBtn = document.getElementById('modal-reply-btn');
    const inqLoading = document.getElementById('inq-loading');
    const inqTable = document.getElementById('inq-table');

    if (!container) return;

    function truncate(str, len) {
        if (!str) return '';
        return str.length > len ? str.substring(0, len) + '...' : str;
    }

    function formatDate(d) {
        if (!d) return '';
        const dt = new Date(d);
        return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
    }

    function statusClass(s) {
        if (s === 'pending') return 'pending';
        if (s === 'answered') return 'answered';
        if (s === 'expired') return 'expired';
        return s || '';
    }

    function statusLabel(s) {
        if (s === 'pending') return 'Pending';
        if (s === 'answered') return 'Answered';
        if (s === 'expired') return 'Expired (24h inactive)';
        return s;
    }

    function buildUrl() {
        let url = API + '?filter=' + currentFilter + '&page=' + currentPage + '&per_page=' + perPage;
        if (currentSearch) url += '&search=' + encodeURIComponent(currentSearch);
        return url;
    }

    function setTableVisible(show) {
        var loadingEl = document.getElementById('inq-loading');
        var tableEl = document.getElementById('inq-table');
        if (loadingEl) loadingEl.style.display = show ? 'none' : 'block';
        if (tableEl) tableEl.style.display = show ? 'table' : 'none';
    }

    function loadConversations() {
        setTableVisible(false);
        tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:#9ca3af;">Loading...</td></tr>';
        fetch(buildUrl())
            .then(r => r.json())
            .then(data => {
                setTableVisible(true);
                if (!data.success) {
                    tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:#dc2626;">Failed to load conversations.</td></tr>';
                    return;
                }
                const list = data.conversations || [];
                const pag = data.pagination || {};
                if (list.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:48px 24px;color:#9ca3af;"><p style="font-weight:600;margin-bottom:4px;">No conversations yet</p><p style="font-size:13px;">Customer messages from support chat will appear here.</p></td></tr>';
                } else {
                    tableBody.innerHTML = list.map(c => `
                        <tr class="inbox-row" data-id="${c.id}" style="cursor:pointer;">
                            <td><span class="inbox-name">${escapeHtml(c.customer_name)}</span></td>
                            <td class="inbox-preview">${escapeHtml(truncate(c.last_message || c.last_message_preview || '', 50))}</td>
                            <td><span class="status-badge ${statusClass(c.status)}">${statusLabel(c.status)}</span></td>
                            <td class="inbox-date">${formatDate(c.last_activity_at)}</td>
                            <td><button type="button" class="btn-open" data-id="${c.id}" onclick="event.stopPropagation();">View</button></td>
                        </tr>
                    `).join('');
                    // Row click
                    tableBody.querySelectorAll('.inbox-row').forEach(row => {
                        row.addEventListener('click', () => openModal(parseInt(row.dataset.id)));
                    });
                    tableBody.querySelectorAll('.btn-open').forEach(btn => {
                        btn.addEventListener('click', (e) => { e.stopPropagation(); openModal(parseInt(btn.dataset.id)); });
                    });
                }
                renderPagination(pag);
            })
            .catch(() => {
                setTableVisible(true);
                tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:#dc2626;">Network error.</td></tr>';
            });
    }

    function renderPagination(pag) {
        if (!paginationEl) return;
        const totalPages = pag.total_pages || 1;
        const page = pag.page || 1;
        if (totalPages <= 1) {
            paginationEl.innerHTML = '';
            return;
        }
        let html = '<div class="inbox-pagination">';
        if (page > 1) html += `<button type="button" data-page="${page - 1}">Previous</button>`;
        html += ` <span>Page ${page} of ${totalPages}</span> `;
        if (page < totalPages) html += `<button type="button" data-page="${page + 1}">Next</button>`;
        html += '</div>';
        paginationEl.innerHTML = html;
        paginationEl.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => { currentPage = parseInt(btn.dataset.page); loadConversations(); });
        });
    }

    function escapeHtml(t) {
        const d = document.createElement('div');
        d.textContent = t || '';
        return d.innerHTML;
    }

    function openModal(convId) {
        modal.dataset.convId = convId;
        modalReplyInput.value = '';
        modalTitle.textContent = 'Loading...';
        modalMessages.innerHTML = '<div style="text-align:center;padding:24px;color:#9ca3af;">Loading...</div>';
        modal.classList.add('open');
        fetch(API + '?id=' + convId)
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.conversation) {
                    modalTitle.textContent = 'Error';
                    modalMessages.innerHTML = '<p style="color:#dc2626;">Failed to load conversation.</p>';
                    return;
                }
                const conv = data.conversation;
                const msgs = data.messages || [];
                modalTitle.textContent = conv.customer_name + (conv.customer_email ? ' (' + conv.customer_email + ')' : '');
                modalMessages.innerHTML = msgs.map(m => {
                    const isCustomer = m.sender_type === 'customer';
                    return `<div class="conv-msg ${isCustomer ? 'customer' : 'admin'}">
                        <div class="conv-msg-bubble">${escapeHtml(m.message)}</div>
                        <div class="conv-msg-time">${formatDate(m.created_at)}</div>
                    </div>`;
                }).join('');
                modalMessages.scrollTop = modalMessages.scrollHeight;
            })
            .catch(() => {
                modalTitle.textContent = 'Error';
                modalMessages.innerHTML = '<p style="color:#dc2626;">Network error.</p>';
            });
    }

    function sendReply() {
        const convId = parseInt(modal.dataset.convId);
        const msg = (modalReplyInput.value || '').trim();
        if (!convId || !msg) return;
        modalReplyBtn.disabled = true;
        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conversation_id: convId, message: msg })
        })
            .then(r => r.json())
            .then(data => {
                modalReplyBtn.disabled = false;
                if (data.success) {
                    modalReplyInput.value = '';
                    openModal(convId);
                    loadConversations();
                } else {
                    alert(data.error || 'Failed to send reply');
                }
            })
            .catch(() => { modalReplyBtn.disabled = false; alert('Network error'); });
    }

    function closeModal() {
        modal.classList.remove('open');
    }

    searchInput && searchInput.addEventListener('input', function() {
        currentSearch = this.value.trim();
        currentPage = 1;
        loadConversations();
    });

    filterTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            currentFilter = this.dataset.filter;
            currentPage = 1;
            filterTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            loadConversations();
        });
    });

    modal && modal.querySelector('.modal-close') && modal.querySelector('.modal-close').addEventListener('click', closeModal);
    modal && modal.addEventListener('click', function(e) { if (e.target === this) closeModal(); });
    modalReplyBtn && modalReplyBtn.addEventListener('click', sendReply);
    modalReplyInput && modalReplyInput.addEventListener('keypress', function(e) { if (e.key === 'Enter') sendReply(); });

    window.openInboxModal = openModal;
    loadConversations();
})();
