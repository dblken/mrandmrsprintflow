<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

// Load config first — production needs empty BASE_URL, localhost needs /printflow
if (!defined('BASE_URL') && file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}
if (!defined('BASE_URL')) define('BASE_URL', '');

$user_id    = get_user_id();
$user_name  = $_SESSION['user_name'] ?? 'Customer';
$user_avatar = $_SESSION['user_avatar'] ?? '';
$initial_order_id = $_GET['order_id'] ?? null;

$page_title = 'My Messages - PrintFlow';
$use_customer_css = true;
$is_chat_page = true;
$disable_turbo = true;
require_once __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<!-- Socket.IO and Call System now loaded globally via header.php -->

<style>
    :root {
        --pf-navy: #f8fafc;
        --pf-navy-card: #ffffff;
        --pf-cyan: #0a2530;
        --pf-cyan-glow: rgba(10,37,48,0.08);
        --pf-border: #e2e8f0;
        --pf-dim: #64748b;
        --pf-self-bubble: linear-gradient(135deg,#0a2530,#0f172a);
    }

    /* Layout — fill viewport below the site header */
    .hidden { display: none !important; }
    body.chat-page { overflow: hidden !important; background: var(--pf-navy); }
    body.chat-page #main-content { padding: 0 !important; min-height: 0 !important; overflow: hidden !important; display: flex; flex-direction: column; }
    body.chat-page #main-header { position: sticky; top: 0; z-index: 100; }
    body.chat-page .text-white { color: #0f172a !important; }

    /* Prevent layout shift from scrollbar appearance/disappearance */
    html { overflow-y: scroll; }
    body.chat-page { overflow-y: hidden !important; }

    #chat-root {
        display: grid;
        grid-template-columns: 350px 1fr;
        height: 100%;
        overflow: hidden;
        background: var(--pf-navy);
        font-family: 'Inter', sans-serif;
        border: 1px solid var(--pf-border);
        border-radius: 24px;
        box-shadow: 0 20px 45px rgba(15,23,42,0.08);
    }

    .chat-shell {
        width: 100%;
        max-width: 1100px;
        margin: 0 auto;
        padding: 1.25rem 1rem;
        height: calc(100vh - 65px);
        box-sizing: border-box;
    }

    /* ── Sidebar ── */
    .cs-sidebar { display:flex; flex-direction:column; background:#f8fafc; border-right:1px solid var(--pf-border); overflow:hidden; }
    .cs-sidebar-top { padding:1.25rem 1rem; border-bottom:1px solid var(--pf-border); flex-shrink:0; }
    .cs-sidebar-top h2 { font-size:1.1rem; font-weight:800; color:#0f172a; margin:0 0 .9rem; }
    .cs-search { position:relative; }
    .cs-search i { position:absolute; left:.75rem; top:50%; transform:translateY(-50%); color:#94a3b8; opacity:.8; }
    .cs-search input { width:100%; box-sizing:border-box; background:#fff; border:1px solid var(--pf-border); border-radius:12px; padding:.55rem .75rem .55rem 2.25rem; font-size:.85rem; color:#0f172a; outline:none; transition:.2s; }
    .cs-search input:focus { border-color:var(--pf-cyan); box-shadow:0 0 0 3px rgba(10,37,48,0.08); }

    .cs-tabs { display:flex; gap:6px; padding:.75rem 1rem; border-bottom:1px solid var(--pf-border); flex-shrink:0; }
    .cs-tab { flex:1; text-align:center; padding:.4rem 0; border-radius:8px; font-size:.75rem; font-weight:700; color:var(--pf-dim); cursor:pointer; background:transparent; border:none; transition:.2s; }
    .cs-tab.active { background:#fff; color:var(--pf-cyan); border:1px solid var(--pf-border); box-shadow:0 2px 8px rgba(15,23,42,0.06); }

    .cs-list { flex:1; overflow-y:auto; padding:.5rem; }
    .cs-list::-webkit-scrollbar { width:3px; }
    .cs-list::-webkit-scrollbar-thumb { background:var(--pf-border); border-radius:10px; }

    .conv-card { display:flex; gap:11px; padding:12px 14px; border-radius:14px; margin-bottom:3px; cursor:pointer; border:1px solid transparent; transition:.18s; }
    .conv-card:hover { background:#f1f5f9; }
    .conv-card.active { background:#fff; border-color:var(--pf-border); box-shadow:0 4px 12px rgba(15,23,42,0.06); }
    .conv-av { width:44px; height:44px; border-radius:11px; background:#f1f5f9; border:1px solid var(--pf-border); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.95rem; color:var(--pf-cyan); flex-shrink:0; overflow:hidden; }
    .conv-av img { width:100%; height:100%; object-fit:cover; }
    .conv-info { flex:1; min-width:0; }
    .conv-top { display:flex; justify-content:space-between; align-items:baseline; gap:4px; }
    .conv-name { font-size:.88rem; font-weight:700; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .conv-time { font-size:.65rem; color:#94a3b8; font-weight:700; flex-shrink:0; }
    .conv-sub { font-size:.68rem; color:var(--pf-cyan); font-weight:800; text-transform:uppercase; letter-spacing:.04em; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; opacity:.9; }
    .conv-prev { font-size:.75rem; color:var(--pf-dim); margin-top:4px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; opacity:.9; }

    /* ── Main Chat Window ── */
    .cs-window { display:flex; flex-direction:column; overflow:hidden; background:#fff; position:relative; }
    .cs-header { display:flex; align-items:center; gap:12px; padding:1rem 1.5rem; border-bottom:1px solid var(--pf-border); background:#fff; z-index:20; flex-shrink:0; }
    .cs-header-info { flex:1; min-width:0; }
    .cs-header-name { font-size:1rem; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:8px; min-width:0; }
    .cs-header-name #hName { display:block; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .cs-header-name #hOnline { flex-shrink:0; }
    .cs-header-meta { font-size:.75rem; color:var(--pf-dim); font-weight:700; opacity:.9; margin:0; }

    .cs-h-actions { display: flex; gap: 8px; }
    .cs-mobile-back {
        display: none;
        width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--pf-border);
        background: #fff; color: #64748b; align-items:center; justify-content:center; cursor:pointer; font-size: 1rem;
        transition: .2s; flex-shrink: 0;
    }
    .cs-mobile-back:hover { background:#f8fafc; color:#0f172a; }
    .cs-h-btn {
        width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--pf-border);
        background: #fff; color: #64748b;
        display: flex; align-items:center; justify-content:center; cursor:pointer; font-size: 1rem; transition:.2s;
    }
    .cs-h-btn:hover { background: #f8fafc; color: #0f172a; }

    .h-menu-wrap { position:relative; }
    .h-dropdown { display:none; position:absolute; top:calc(100% + 8px); right:0; background:#fff; border:1px solid var(--pf-border); border-radius:13px; width:170px; z-index:200; overflow:hidden; box-shadow:0 12px 30px rgba(15,23,42,0.12); }
    .h-dropdown.show { display:block; }
    .h-drop-item { padding:10px 16px; font-size:.84rem; font-weight:700; color:#0f172a; cursor:pointer; display:flex; align-items:center; gap:10px; transition:.15s; }
    .h-drop-item:hover { background:#f1f5f9; color:var(--pf-cyan); }

    /* Messages Area */
    #messagesArea { flex:1; overflow-y:auto; padding:1.5rem; display:flex; flex-direction:column; gap:4px; background:#f8fafc; scroll-behavior:smooth; }
    #messagesArea::-webkit-scrollbar { width:4px; }
    #messagesArea::-webkit-scrollbar-thumb { background:var(--pf-border); border-radius:10px; }

    /* Bubbles & Grouping */
    .brow { display:flex; width:100%; align-items:flex-end; gap:8px; margin-bottom:12px; position:relative; transition: margin 0.2s; }
    .brow.self { flex-direction:row-reverse; }
    .brow.system { justify-content:flex-start; margin-bottom: 16px; }

    .brow.grouped-msg { margin-bottom: 2px !important; }
    .brow.grouped-msg-next .b-meta { display: none !important; }
    .brow.grouped-msg-next .conv-av { visibility: hidden; }

    .b-col { max-width:75%; position:relative; }
    .brow.self .b-col { display:grid; justify-items:end; }
    .brow.other .b-col { display:flex; flex-direction:column; align-items:flex-start; }

    .bubble { display:inline-block; padding:10px 16px; border-radius:20px; font-size:.9rem; font-weight:500; line-height:1.45; max-width:100%; word-break:break-word; position: relative; }
    .brow.self .bubble { background: var(--pf-self-bubble); border:1px solid rgba(10,37,48,0.15); border-radius:20px 20px 4px 20px; color: #fff; }
    .brow.other .bubble { background:#fff; border:1px solid var(--pf-border); border-radius:20px 20px 20px 4px; color: #1e293b; }

    .brow.grouped-msg.other .bubble, .brow.grouped-msg.other .voice-bubble-player { border-radius: 20px 20px 4px 4px; }
    .brow.grouped-msg-next.other .bubble, .brow.grouped-msg-next.other .voice-bubble-player { border-radius: 4px 20px 20px 4px; }
    .brow.grouped-msg.self .bubble, .brow.grouped-msg.self .voice-bubble-player { border-radius: 20px 20px 4px 4px; }
    .brow.grouped-msg-next.self .bubble, .brow.grouped-msg-next.self .voice-bubble-player { border-radius: 20px 4px 4px 20px; }

    .brow.system .bubble { background:#f1f5f9; color:var(--pf-dim); font-size:.78rem; border:none; border-radius:10px; padding:4px 12px; font-weight:700; letter-spacing:.04em; }

    .b-meta { font-size:.65rem; color:var(--pf-dim); font-weight:700; opacity:.8; margin-top:6px; display:flex; gap:4px; }
    .brow.self .b-meta { justify-content:flex-end; }

    /* Call Log Bubbles (Messenger Style) */
    .call-log-bubble { display:flex; align-items:center; gap:12px; padding:12px 18px; border-radius:22px; font-size:.88rem; font-weight:600; cursor:default; user-select:none; min-width:180px; transition: all 0.2s; }
    .brow.other .call-log-bubble { background:#fff; color:#1e293b; border:1px solid var(--pf-border); box-shadow: 0 4px 10px rgba(0,0,0,0.03); }
    .brow.self .call-log-bubble { background:#fff; color:#0f172a; border:1px solid rgba(0,0,0,0.08); box-shadow: 0 4px 10px rgba(0,0,0,0.03); }
    
    .call-log-icon { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:1.15rem; transition: all 0.2s; }
    .call-log-icon.missed { background:#fff1f2 !important; color:#e11d48 !important; }
    .call-log-icon.ended { background:#f0fdfa !important; color:#0d9488 !important; }
    
    .call-log-details { display:flex; flex-direction:column; gap:0px; flex: 1; }
    .call-log-title { font-weight:800; font-size:.92rem; line-height: 1.2; }
    .call-log-status { font-size:.75rem; font-weight:700; opacity:0.5; line-height: 1.2; }

    .brow.order-update-card { margin:10px 0; }
    .brow.order-update-card.other { justify-content:flex-start; }
    .brow.order-update-card.self { justify-content:flex-end; }
    .order-update-bubble {
        display:flex;
        gap:12px;
        align-items:flex-start;
        width:min(100%, 420px);
        background:linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border:1px solid #d9e6ee;
        border-radius:18px 18px 18px 6px;
        padding:12px;
        position:relative;
        box-shadow:0 10px 24px rgba(15,23,42,0.06);
        cursor:pointer;
        transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .brow.self.order-update-card .order-update-bubble {
        border-radius:18px 18px 6px 18px;
        background:linear-gradient(180deg, #f3fbff 0%, #e8f7ff 100%);
    }
    .order-update-bubble:hover { transform:translateY(-1px); box-shadow:0 14px 28px rgba(15,23,42,0.1); border-color:#7dd3d8; }
    .order-update-bubble:active { transform:translateY(0); }
    .order-update-bubble.read-only { cursor:default; }
    .order-thumb-wrap { width:58px; height:58px; border-radius:14px; overflow:hidden; background:#eaf2f7; border:1px solid #d9e6ee; flex-shrink:0; }
    .order-thumb { width:100%; height:100%; object-fit:cover; display:block; }
    .order-text { flex:1; min-width:0; }
    .order-update-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 9px; border-radius:999px; background:#e6f8f7; color:#0f766e; font-size:.62rem; font-weight:900; letter-spacing:.08em; text-transform:uppercase; margin-bottom:8px; }
    .order-update-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; flex-wrap:wrap; }
    .order-update-head .order-update-badge { margin-bottom:0; }
    .order-status-pill { display:inline-flex; align-items:center; justify-content:center; padding:4px 10px; border-radius:999px; font-size:.66rem; font-weight:900; letter-spacing:.04em; white-space:nowrap; }
    .order-status-pill.tone-pending { background:#fff7ed; color:#c2410c; }
    .order-status-pill.tone-approved { background:#eff6ff; color:#1d4ed8; }
    .order-status-pill.tone-payment { background:#eef2ff; color:#4338ca; }
    .order-status-pill.tone-production { background:#ecfeff; color:#0f766e; }
    .order-status-pill.tone-ready { background:#ecfccb; color:#3f6212; }
    .order-status-pill.tone-complete { background:#dcfce7; color:#166534; }
    .order-status-pill.tone-alert { background:#fef2f2; color:#b91c1c; }
    .order-status-pill.tone-neutral { background:#f1f5f9; color:#475569; }
    .order-title { font-size:.9rem; font-weight:900; color:#0f172a; margin-bottom:4px; line-height:1.2; }
    .order-message { font-size:.8rem; color:#475569; line-height:1.45; word-break:break-word; }
    .order-update-meta { display:flex; justify-content:space-between; align-items:center; gap:10px; margin-top:10px; flex-wrap:wrap; }
    .order-update-time { font-size:.68rem; color:#94a3b8; font-weight:800; }
    .order-update-cta { font-size:.68rem; font-weight:900; color:#0891b2; text-transform:uppercase; letter-spacing:.06em; }

    /* Action Bar (Messenger Style) */
    .brow:hover .b-actions, .brow.has-active-menu .b-actions { opacity:1; pointer-events:auto; }
    .b-actions {
        opacity:0; pointer-events:none; display:flex; align-items: center; gap:4px;
        position:absolute; top:50%; transform:translateY(-50%); z-index:100; transition:.2s;
        background: #fff; border: 1px solid var(--pf-border);
        border-radius:999px; padding:4px 8px; box-shadow:0 4px 20px rgba(15,23,42,0.12);
    }
    .brow.other .b-actions { left:calc(100% + 12px); }
    .brow.self  .b-actions { right:calc(100% + 12px); flex-direction:row-reverse; }

    .ab { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#64748b; cursor:pointer; font-size:1.1rem; transition:.15s; }
    .ab:hover { background: #f1f5f9; color: #0f172a; }

    /* More Menu Sub-Menu */
    .more-menu {
        display:none; position:absolute; top:100%; right:0; background:#fff;
        border:1px solid var(--pf-border); border-radius:12px; width:160px; z-index:151;
        overflow:hidden; box-shadow:0 12px 30px rgba(15,23,42,0.12); margin-top: 8px;
    }
    .more-menu.show { display:block; animation: menuFade 0.2s ease; }
    .mi { padding:10px 16px; font-size:.85rem; font-weight:700; color:#475569; cursor:pointer; display:flex; align-items:center; gap:10px; transition:.15s; text-align: left; }
    .mi:hover { background:#f1f5f9; color:var(--pf-cyan); }

    /* Reactions Attached to Bubble */
    .react-display {
        display:flex; gap:4px; position: absolute; bottom: -10px; z-index: 10;
        background: #fff; border: 1px solid var(--pf-border); border-radius: 999px; padding: 3px 10px;
        box-shadow: 0 4px 8px rgba(15,23,42,0.12); cursor: default; white-space: nowrap;
    }
    .brow.self .react-display { right: 8px; }
    .brow.other .react-display { left: 8px; }
    .react-chip { font-size:.85rem; display:flex; align-items:center; gap:4px; color: #0f172a; }
    .react-chip b { font-weight: 800; font-size: 0.75rem; color: var(--pf-cyan); }

    /* Reaction Picker */
    .react-picker {
        display:none; position:absolute; bottom:calc(100% + 12px); left:50%; transform:translateX(-50%);
        background:#fff; border:1px solid var(--pf-border); border-radius:999px; padding:0 18px;
        gap:10px; z-index:150; box-shadow:0 12px 40px rgba(15,23,42,0.12); height: 50px; align-items: center; justify-content: center;
        animation: pickerPop 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .react-picker.show { display:flex; }
    .react-picker span { font-size:1.6rem; cursor:pointer; transition:.15s; margin: 0 4px; }
    .react-picker span:hover { transform:scale(1.3) translateY(-4px); }

    /* Seen Indicator */
    .seen-wrapper { display:flex; width:100%; margin-top:2px; min-height:16px; align-items:center; }
    .brow.self .seen-wrapper { justify-content: flex-end; }
    .seen-avatar { width: 14px; height: 14px; border-radius: 50%; object-fit: cover; border: 1px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.12); }

    /* Reply Sub-Area */
    #replyBox {
        display:none; background:#f8fafc; border-top:1px solid var(--pf-border);
        padding:10px 1.5rem; justify-content:space-between; align-items:center; gap:10px;
    }
    .reply-wrap { border-left:3px solid var(--pf-cyan); padding-left:12px; overflow:hidden; }
    .reply-head { font-size:.7rem; font-weight:800; color:var(--pf-cyan); margin-bottom:2px; }
    .reply-preview { font-size:.85rem; color:var(--pf-dim); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:400px; }
    .reply-close { background:transparent; border:none; color:var(--pf-dim); cursor:pointer; font-size:1.2rem; }

    /* ── Window Footer (Compact Staff Layout) ── */
    .cs-footer { padding: 0.75rem 1.25rem; border-top: 1px solid var(--pf-border); background:#fff; flex-shrink:0; z-index:20; }
    .chat-input-area { display: flex; align-items: center; gap: 10px; width: 100%; max-width: 900px; margin: 0 auto; }

    .mic-btn {
        width: 40px; height: 40px; border-radius: 12px; background: #f8fafc; border: 1px solid var(--pf-border);
        color: var(--pf-dim); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1rem; transition: all 0.2s; flex-shrink: 0;
    }
    .mic-btn:hover { background: #f1f5f9; color: var(--pf-cyan); }
    .mic-btn.recording {
        background: rgba(239, 68, 68, 0.12); border-color: rgba(239,68,68,0.5); color: #ef4444;
        box-shadow: 0 0 15px rgba(239,68,68,0.25);
        animation: pulse-rec 1.5s infinite;
    }

    .input-bar {
        flex: 1; display: flex; align-items: center; gap: 10px; background: #f1f5f9; border: 2px solid transparent;
        border-radius: 16px; padding: 4px 4px 4px 12px; transition: all 0.2s; position: relative;
    }
    .input-bar:focus-within { background: #fff; border-color: var(--pf-cyan); box-shadow:0 10px 15px -3px rgba(15,23,42,0.08); }

    .recording-panel {
        flex: 1; display: flex; align-items: center; gap: 12px; background: rgba(239,68,68,0.05);
        border: 1px solid rgba(239,68,68,0.1); border-radius: 14px; padding: 4px 12px; margin: 0 4px;
        overflow: hidden;
    }
    .rec-pulse { width: 8px; height: 8px; background: #ef4444; border-radius: 50%; animation: pulse-dot 1s infinite; }
    .rec-timer { font-family: 'JetBrains Mono', monospace; font-weight: 800; color: #ef4444; font-size: 0.85rem; min-width: 40px; }
    #recordingCanvas { flex: 1; height: 30px; }

    #voicePreviewArea {
        display: none; align-items: center; gap: 10px; background: #fff;
        border: 1px solid var(--pf-border); border-radius: 14px; padding: 6px 12px; margin: 0 4px; flex: 1;
    }
    .play-pause-btn {
        width: 32px; height: 32px; border-radius: 50%; background: var(--pf-cyan); color: #fff;
        border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s;
    }
    .v-waveform-container { flex: 1; height: 30px; position: relative; cursor: pointer; display: flex; align-items: center; }
    .v-waveform-canvas { width: 100%; height: 100%; display: block; }
    .v-duration { font-size: 11px; font-weight: 700; color: var(--pf-dim); min-width: 35px; }

    .footer-action-btn {
        width: 38px; height: 38px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
        color: var(--pf-dim); cursor: pointer; transition: all 0.15s; background: transparent; flex-shrink: 0;
    }
    .footer-action-btn:hover { color: var(--pf-cyan); background: #f1f5f9; }

    #customerMsgInput {
        flex: 1; background: transparent; border: none !important; outline: none !important; color: #0f172a;
        font-size: 0.95rem; font-weight: 500; padding: 10px 0; font-family: inherit; line-height: 1.4;
        resize: none; max-height: 120px;
    }
    #customerMsgInput::placeholder { color: #94a3b8; }

    .char-counter { font-size: 10px; font-weight: 800; color: var(--pf-dim); opacity: 0.7; white-space: nowrap; align-self: center; }

    .btn-send {
        background: #0a2530; color: #fff; border: none; width: 44px; height: 44px; border-radius: 14px;
        display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; flex-shrink: 0;
        box-shadow: 0 2px 10px rgba(10,37,48,0.2);
    }
    .btn-send:hover { background: #0f172a; transform: scale(1.05); }
    .btn-send.hidden { display: none; }

    /* Voice Bubble Style */
    .voice-bubble-player { display: flex; align-items: center; gap: 12px; padding: 8px 14px; border-radius: 20px; min-width: 220px; }
    .brow.self .voice-bubble-player { background: var(--pf-self-bubble); color: #fff; border: 1px solid rgba(10,37,48,0.15); border-radius: 20px 20px 4px 20px; }
    .brow.other .voice-bubble-player { background: #fff; color: #1e293b; border: 1px solid var(--pf-border); border-radius: 20px 20px 20px 4px; }
    .play-pause-bubble { width: 32px; height: 32px; border-radius: 50%; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
    .brow.self .play-pause-bubble { background: #fff; color: #0a2530; }
    .brow.other .play-pause-bubble { background: var(--pf-cyan); color: #fff; }

    @keyframes pulse-rec { 0%{box-shadow:0 0 0 0 rgba(239,68,68,.4)} 70%{box-shadow:0 0 0 10px rgba(239,68,68,0)} 100%{box-shadow:0 0 0 0 rgba(239,68,68,0)} }
    @keyframes pulse-dot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(1.2); } }
    @keyframes pickerPop { from { opacity: 0; transform: translateX(-50%) scale(0.8) translateY(10px); } to { opacity: 1; transform: translateX(-50%) scale(1) translateY(0); } }
    @keyframes menuFade { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

    /* Forward Modal CSS */
    #pfFwdModal { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.45); z-index:2000; align-items:center; justify-content:center; }
    #pfFwdModal.show { display:flex; }
    .fwd-panel { background:#fff; border:1px solid var(--pf-border); border-radius:32px; width:100%; max-width:480px; box-shadow:0 40px 100px rgba(15,23,42,0.2); display:flex; flex-direction:column; overflow:hidden; }
    .fwd-header { padding:1.25rem 1.5rem; border-bottom:1px solid var(--pf-border); display:flex; justify-content:space-between; align-items:center; }
    .fwd-search-wrap { padding:1rem 1.5rem; border-bottom:1px solid var(--pf-border); }
    .fwd-search-input { width:100%; height:44px; background:#f8fafc; border:1px solid var(--pf-border); border-radius:14px; padding:0 1rem 0 2.5rem; color:#0f172a; font-size:0.9rem; outline:none; transition:.2s; }
    .fwd-search-input:focus { border-color:var(--pf-cyan); background:#fff; box-shadow:0 0 0 3px rgba(10,37,48,0.08); }
    .fwd-preview-section { padding:0.75rem 1.5rem; background:#f8fafc; border-bottom:1px solid var(--pf-border); }
    .fwd-preview-label { font-size:0.65rem; color:var(--pf-cyan); font-weight:800; text-transform:uppercase; margin-bottom:4px; letter-spacing:0.05em; }
    .fwd-body { flex:1; max-height:380px; overflow-y:auto; padding:1rem 1.25rem; display:flex; flex-direction:column; gap:8px; }
    .fwd-body::-webkit-scrollbar { width:4px; }
    .fwd-body::-webkit-scrollbar-thumb { background:var(--pf-border); border-radius:10px; }

    .details-modal-overlay { display:none !important; position:fixed; inset:0; background:rgba(15,23,42,0.75); z-index:3000; align-items:center; justify-content:center; padding:1.5rem; backdrop-filter:blur(8px); }
    .details-modal-overlay.active { display:flex !important; }
    .details-modal-panel { background:#fff; border-radius:32px; width:min(100%, 1100px); max-height:min(88vh, 920px); overflow:hidden; box-shadow:0 40px 80px -15px rgba(0,0,0,0.4); border:1px solid rgba(255,255,255,0.1); display:flex; flex-direction:column; }
    .details-modal-header { padding:1.25rem 2rem; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; background:#fff; flex-shrink:0; }
    .details-modal-content { display:grid; grid-template-columns:minmax(250px, 290px) minmax(0, 1fr); flex:1; overflow:hidden; min-height:0; }
    .details-sidebar { background:linear-gradient(180deg,#f8fbff 0%, #f1f5f9 100%); border-right:1px solid #eef2f7; padding:1.5rem; overflow-y:auto; }
    .details-main { padding:1.5rem; overflow-y:auto; background:#fff; }
    .pf-mini-card { background:#fff; border-radius:20px; padding:1.25rem; border:1px solid #eef2f6; box-shadow:0 4px 6px -1px rgba(0,0,0,0.02); }
    .pf-spec-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(120px, 1fr)); gap:.5rem; margin-top:.75rem; }
    .pf-spec-box { background:#f8fafc; border:1px solid #f1f5f9; padding:8px 10px; border-radius:12px; overflow:hidden; min-width:0; }
    .pf-spec-key { font-size:8px; font-weight:900; color:#94a3b8; text-transform:uppercase; margin-bottom:3px; letter-spacing:.05em; }
    .pf-spec-val { font-size:10.5px; font-weight:800; color:#1e293b; line-height:1.3; overflow-wrap:break-word; }
    .details-main-heading { position:sticky; top:0; z-index:2; background:#fff; padding:0 0 1rem; font-size:9px; font-weight:900; color:#94a3b8; text-transform:uppercase; letter-spacing:.1em; margin-bottom:1rem; }
    .details-items { display:flex; flex-direction:column; gap:1rem; }
    .detail-order-card { background:#fff; border:1px solid #f1f5f9; border-radius:20px; padding:1rem; box-shadow:0 12px 32px rgba(15,23,42,0.04); }
    .detail-order-top { display:grid; grid-template-columns:112px minmax(0, 1fr); gap:1rem; align-items:start; }
    .detail-order-thumb { width:112px; height:112px; border-radius:16px; background:#f8fafc; border:1px solid #f1f5f9; overflow:hidden; display:flex; align-items:center; justify-content:center; }
    .detail-order-thumb img { width:100%; height:100%; object-fit:cover; }
    .detail-order-body { min-width:0; display:flex; flex-direction:column; gap:.9rem; }
    .detail-order-summary { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
    .detail-order-title { font-size:1.05rem; font-weight:900; color:#1e293b; line-height:1.2; word-break:break-word; }
    .detail-order-meta { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
    .detail-order-chip { background:#f1f5f9; color:#475569; border-radius:999px; padding:.35rem .7rem; font-size:.72rem; font-weight:800; letter-spacing:.02em; }
    .detail-order-chip.category { background:#ecfeff; color:#0f766e; text-transform:uppercase; }
    .detail-order-price { min-width:120px; text-align:right; }
    .detail-order-price .pf-spec-key { margin-bottom:2px; font-size:9px; }
    .detail-order-price strong { display:block; font-size:1.05rem; font-weight:900; color:#0ea5a5; line-height:1.2; word-break:break-word; overflow-wrap:break-word; white-space:normal; }
    .fwd-footer { padding:1.25rem 1.5rem; border-top:1px solid var(--pf-border); display:flex; justify-content:flex-end; gap:12px; }

    .fwd-list-item { display:flex; align-items:center; gap:12px; padding:10px 14px; border-radius:16px; transition:.15s; cursor:pointer; background:#fff; border:1px solid var(--pf-border); }
    .fwd-list-item:hover { background:#f8fafc; border-color:#cbd5e1; }
    .fwd-list-item.selected { background:rgba(10,37,48,0.06); border-color:rgba(10,37,48,0.35); }
    .fwd-check-circle { width:20px; height:20px; border-radius:50%; border:2px solid rgba(10,37,48,0.25); display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:.2s; }
    .selected .fwd-check-circle { background:var(--pf-cyan); border-color:var(--pf-cyan); }

    #galleryPanel {
        position: absolute; right: 0; top: 0; bottom: 0; width: 340px;
        background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(30px);
        border-left: 1px solid rgba(0,0,0,0.06); z-index: 1000;
        display: none; flex-direction: column; 
        box-shadow: -15px 0 40px rgba(0,0,0,0.12);
        transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        transform: translateX(100%);
    }
    #galleryPanel.show { display: flex; transform: translateX(0); }
    .gal-tabs { display: flex; padding: 0.5rem 1rem; gap: 8px; border-bottom: 1px solid rgba(0,0,0,0.05); }
    .gal-tab { 
        flex: 1; padding: 8px; font-size: 0.75rem; font-weight: 700; text-align: center; 
        border-radius: 12px; cursor: pointer; transition: all 0.2s; color: #64748b; border: 1px solid transparent;
    }
    .gal-tab.active { background: #fff; color: #0a2530; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
    .gal-grid { flex: 1; overflow-y: auto; display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; padding: 1.5rem; align-content: flex-start; }
    .gal-item { aspect-ratio: 1; border-radius: 18px; overflow: hidden; cursor: pointer; position: relative; transition: all 0.3s; border: 1px solid rgba(0,0,0,0.05); background: #f1f5f9; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .gal-item:hover { transform: translateY(-4px) scale(1.02); box-shadow: 0 12px 24px rgba(0,0,0,0.1); border-color: var(--pf-cyan); }
    .gal-item img, .gal-item video { width: 100%; height: 100%; object-fit: cover; }
    .gal-vid-badge { position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.6); color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1); }

    @media (max-width: 768px) {
        body.chat-page #main-content {
            overflow: visible !important;
        }

        .chat-shell {
            max-width: 100%;
            padding: 0;
            height: calc(100dvh - 65px);
        }

        #chat-root {
            grid-template-columns: 1fr;
            border: none;
            border-radius: 0;
            box-shadow: none;
        }
        .details-modal-overlay { padding:.75rem; align-items:flex-end; }
        .details-modal-panel { max-height:min(92vh, 920px); border-radius:24px 24px 0 0; overflow:hidden; }
        .details-modal-header,
        .details-main,
        .details-sidebar { padding:1rem; }
        .details-modal-content { grid-template-columns:1fr; overflow-y:auto; overflow-x:hidden; }
        .details-sidebar { border-right:none; border-bottom:1px solid #eef2f7; overflow:visible; }
        .details-main { overflow:visible; }
        .details-main-heading { padding-bottom:.75rem; margin-bottom:.85rem; border-bottom:1px solid #f1f5f9; }
        .detail-order-top { grid-template-columns:1fr; }
        .detail-order-thumb { width:100%; max-width:240px; height:auto; aspect-ratio:1 / 1; }
        .detail-order-price { min-width:0; width:100%; text-align:left; }

        .cs-sidebar,
        .cs-window {
            min-width: 0;
            height: 100%;
        }

        .cs-window {
            display: none;
        }

        #chat-root.chat-open .cs-sidebar {
            display: none;
        }

        #chat-root.chat-open .cs-window {
            display: flex;
        }

        .cs-sidebar-top,
        .cs-tabs {
            padding-left: 0.875rem;
            padding-right: 0.875rem;
        }

        .cs-list {
            padding: 0.5rem 0.625rem 0.875rem;
        }

        .conv-card {
            padding: 12px;
            border-radius: 12px;
        }

        .cs-mobile-back {
            display: inline-flex;
        }

        .cs-header {
            padding: 0.875rem;
            gap: 10px;
        }

        .cs-header-name {
            font-size: 0.95rem;
        }

        .cs-header-meta {
            font-size: 0.7rem;
        }

        .cs-h-actions {
            gap: 6px;
        }

        .cs-h-btn {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            font-size: 0.92rem;
        }

        #messagesArea {
            padding: 1rem 0.875rem;
        }

        .b-col {
            max-width: 88%;
        }

        .bubble {
            font-size: 0.88rem;
            padding: 10px 14px;
        }

        .cs-footer {
            padding: 0.75rem 0.875rem calc(0.75rem + env(safe-area-inset-bottom));
        }

        .chat-input-area {
            gap: 8px;
        }

        .input-bar {
            padding: 4px 4px 4px 10px;
            border-radius: 14px;
            min-width: 0;
        }

        #customerMsgInput {
            font-size: 0.92rem;
            min-width: 0;
        }

        .footer-action-btn,
        .mic-btn,
        .btn-send {
            width: 40px;
            height: 40px;
            border-radius: 12px;
        }

        #galleryPanel {
            width: 100%;
            max-width: 100%;
        }

        .b-actions {
            position: static;
            transform: none;
            margin-top: 6px;
            align-self: flex-start;
            opacity: 0;
            pointer-events: none;
        }

        .brow.self .b-actions,
        .brow.other .b-actions {
            left: auto;
            right: auto;
        }

        .brow.self .b-actions {
            align-self: flex-end;
        }

        .brow.has-active-menu .b-actions {
            opacity: 1;
            pointer-events: auto;
        }

        .react-picker {
            left: 50%;
            right: auto;
            transform: translateX(-50%);
            top: auto !important;
            bottom: calc(100% + 8px) !important;
            margin: 0 !important;
            padding: 8px 10px;
            gap: 6px;
            height: auto;
            max-width: min(260px, calc(100vw - 48px));
            flex-wrap: wrap;
            border-radius: 20px;
        }

        .more-menu {
            left: auto;
            right: 0;
            width: 148px;
        }

        #welcome {
            display: none !important;
        }
    }

    /* Pinned Messages Styles */
    .pinned-badge { position: absolute; top: -10px; right: -10px; width: 22px; height: 22px; background: #ef4444; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; border: 2px solid #fff; box-shadow: 0 4px 12px rgba(239,68,68,0.4); z-index: 10; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
    .pinned-badge i { transform: rotate(45deg); }
    .pin-bar-active { background: rgba(239,68,68,0.06) !important; color: #b91c1c !important; cursor: pointer; }
    .details-modal-overlay { display: none !important; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.75); z-index: 10000; align-items: center; justify-content: center; padding: 1.5rem; backdrop-filter: blur(8px); transition: all 0.3s; }
    .details-modal-overlay.active { display: flex !important; }
    .details-modal-panel { background: #fff; border-radius: 32px; width: min(100%, 1100px); max-height: min(88vh, 920px); overflow: hidden; box-shadow: 0 40px 80px -15px rgba(0, 0, 0, 0.4); position: relative; border: 1px solid rgba(255,255,255,0.1); display: flex; flex-direction: column; }
    .details-modal-header { padding: 1.25rem 2rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; background: #fff; z-index: 10; flex-shrink: 0; }
    @keyframes highlightStaffMsg { 0% { background: rgba(14,165,233,0.2); } 100% { background: transparent; } }
</style>

<div class="chat-shell">
<!-- Lightbox / Modal Viewer (Part 4) -->
<div id="chatLightbox" onclick="closeLightbox()" style="display:none;position:fixed;inset:0;background:rgba(10,15,30,0.97);z-index:9000;align-items:center;justify-content:center;padding:2rem;cursor:pointer;">
    <div style="position:relative; max-width:95vw; max-height:95vh;display:flex;flex-direction:column;align-items:center;" onclick="event.stopPropagation()">
        <img id="lightboxImg" src="" style="max-width:100%;max-height:80vh;border-radius:1rem;box-shadow:0 0 60px rgba(0,0,0,0.6);display:none;object-fit:contain;">
        <video id="lightboxVideo" controls style="max-width:100%;max-height:80vh;border-radius:1rem;box-shadow:0 0 60px rgba(0,0,0,0.6);display:none;background:#000;outline:none;" preload="metadata"></video>
        <div style="display:flex; justify-content:center; gap:1.5rem; margin-top:1.5rem;">
            <a id="lightboxDownload" href="" download class="cs-h-btn" style="width:auto; padding:0 20px; background:#fff; color:#0a2530; font-weight:700; text-decoration:none;">Download</a>
            <button onclick="closeLightbox()" class="cs-h-btn" style="width:auto; padding:0 20px; background:#fff; color:#0a2530; font-weight:700;">Close</button>
        </div>
    </div>
</div>

<div id="chat-root">
    <!-- ══ Sidebar ══ -->
    <aside class="cs-sidebar">
        <div class="cs-sidebar-top">
            <h2>My Messages</h2>
            <div class="cs-search"><i class="bi bi-search"></i><input type="text" id="convSearch" placeholder="Search orders…" oninput="loadConvs()"></div>
        </div>
        <div class="cs-tabs"><button class="cs-tab active" id="tabActive" onclick="switchTab(false)">Active</button><button class="cs-tab" id="tabArchived" onclick="switchTab(true)">Archived</button></div>
        <div class="cs-list" id="convList"></div>
    </aside>

    <!-- ══ Chat Window ── -->
    <section class="cs-window">
        <div id="welcome" class="flex-1 flex items-center justify-center text-left p-12">
        <div>
            <div class="text-5xl opacity-20 text-white mb-6"><i class="bi bi-chat-heart-fill"></i></div>
            <h3 class="text-3xl font-black text-white letter-spacing-tight">Get in Touch</h3>
            <p class="text-white opacity-50 max-w-xs mt-3 font-bold text-lg leading-snug">Please select an order to start chatting. You can contact our admin or staff directly if you encounter any issues.</p>
        </div>
    </div>
        
        <div id="chatInterface" style="display:none;flex:1;flex-direction:column;overflow:hidden;">
            <header class="cs-header">
                <button type="button" class="cs-mobile-back" onclick="closeChatMobile()"><i class="bi bi-arrow-left"></i></button>
                <div id="hAvatar" class="conv-av"></div>
                <div class="cs-header-info"><h3 class="cs-header-name"><span id="hName">...</span><span id="hOnline" style="width:10px;height:10px;background:#22c55e;border-radius:50%;display:none;margin-left:8px;"></span></h3><p class="cs-header-meta" id="hMeta">...</p></div>
                <div class="cs-h-actions">
                    <button class="cs-h-btn" onclick="initiateCall('voice')"><i class="bi bi-telephone-fill"></i></button>
                    <button class="cs-h-btn" onclick="initiateCall('video')"><i class="bi bi-camera-video-fill"></i></button>
                    <div class="h-menu-wrap">
                        <button class="cs-h-btn" onclick="toggleHMenu(event)"><i class="bi bi-three-dots-vertical"></i></button>
                        <div class="h-dropdown" id="hDropdown">
                            <div class="h-drop-item" onclick="openGallery()"><i class="bi bi-images"></i> Shared Media</div>
                            <div class="h-drop-item" id="archItem" onclick="toggleArchive()"><i class="bi bi-archive"></i> Archive</div>
                            <div class="h-drop-item" onclick="openOrderDetails(activeId)"><i class="bi bi-info-circle"></i> Order Details</div>
                        </div>
                    </div>
                </div>
            </header>

            <div id="pinnedBar" style="display:none; background:var(--pf-navy-card); border-bottom:1px solid var(--pf-border); padding:10px 1.5rem; align-items:center; justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:8px;"><i class="bi bi-pin-angle-fill" style="color:var(--pf-cyan);"></i><span id="pinnedTxt" style="font-size:0.75rem; font-weight:800; color:#0f172a;">0 pinned messages</span></div>
            </div>

            <div id="messagesArea"></div>

            <div id="galleryPanel">
                <div class="gal-head"><span style="font-weight:800;font-size:1.1rem;color:#0f172a;">Shared Media</span><button onclick="closeGallery()" style="background:transparent;border:none;color:#64748b;font-size:1.5rem;cursor:pointer;"><i class="bi bi-x"></i></button></div>
                <div class="gal-tabs">
                    <div class="gal-tab active" id="galTabImg" onclick="switchGalleryTab('image')">Images</div>
                    <div class="gal-tab" id="galTabVid" onclick="switchGalleryTab('video')">Videos</div>
                </div>
                <div class="gal-grid" id="galleryGrid"></div>
            </div>

            <div id="replyBox">
                <div class="reply-wrap">
                    <div class="reply-head" id="replyHead">Replying to message</div>
                    <div class="reply-preview" id="replyPreviewTxt">...</div>
                </div>
                <button class="reply-close" onclick="cancelReply()"><i class="bi bi-x-circle-fill"></i></button>
            </div>

            <footer class="cs-footer">
                <div class="chat-input-area">
                    <button class="mic-btn" id="micBtnMain" title="Hold to Record">
                        <i class="bi bi-mic" id="micIconMain"></i>
                    </button>
                    
                    <div class="input-bar flex-1" id="inputBarMain" style="position:relative; display:flex; align-items:flex-end; gap:10px;">
                        <label class="footer-action-btn" title="Send Image or Video" style="margin-bottom:6px !important;">
                            <input type="file" id="customerMediaInput" multiple style="display:none;" onchange="onImgSelected()">
                            <i class="bi bi-image"></i>
                        </label>
                        <textarea id="customerMsgInput" class="chat-input" placeholder="Type a message..." autocomplete="off" maxlength="500" rows="1" style="background:transparent; border:none; outline:none; color:#1e293b; flex:1; resize:none; font-family:inherit; padding:10px 0; font-weight: 500;"></textarea>
                        <span id="customerCharCount" class="char-counter">0/500</span>
                    </div>

                    <div class="recording-panel hidden" id="recordStatusMain" style="flex:1; display:flex; align-items:center; gap:12px; background:rgba(239,68,68,0.05); border:1px solid rgba(239,68,68,0.1); border-radius:14px; padding:4px 12px; margin:0 4px; overflow:hidden;">
                        <div class="rec-pulse-dot" style="width:8px; height:8px; background:#ef4444; border-radius:50%;"></div>
                        <canvas id="recordingCanvasMain" style="flex:1; height:30px;"></canvas>
                        <span class="rec-timer" id="timerMain" style="font-family:monospace; font-weight:700; color:#ef4444; font-size:0.85rem;">0:00</span>
                    </div>

                    <div id="voicePreviewAreaMain" style="display:none; align-items:center; gap:10px; background:rgba(255,255,255,0.05); border:1px solid var(--pf-border); border-radius:14px; padding:6px 12px; margin:0 4px; flex:1;">
                        <button type="button" class="play-pause-btn" onclick="togglePreviewPlayback()">
                            <i class="bi bi-play-fill" id="previewPlayIconMain"></i>
                        </button>
                        <div class="v-waveform-container" style="flex:1; height:24px; position:relative; cursor:pointer;">
                            <canvas id="previewWaveformCanvasMain" class="v-waveform-canvas" style="width:100%; height:100%;"></canvas>
                        </div>
                        <span class="v-duration" id="previewDurationMain" style="font-size:11px; font-weight:700; color:var(--pf-dim);">0:00</span>
                        <button class="footer-action-btn" onclick="cancelRecording()" style="color:#ef4444; border:none; background:transparent;"><i class="bi bi-trash3"></i></button>
                    </div>

                    <button id="customerSendBtn" class="btn-send" onclick="sendMsg()">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
                <div id="customerImgPreview" style="display:none;margin-top:0.6rem;gap:10px;flex-wrap:wrap;justify-content:center;padding:5px;"></div>
            </footer>
        </div>
    </section>
</div>
</div>

    <div id="pfFwdModal" class="hidden">
        <div class="fwd-panel">
            <div class="fwd-header">
                <h3 class="font-black text-xl" style="color:#0f172a;">Forward Message</h3>
                <button onclick="closeFwd()" style="background:transparent; border:none; color:#64748b; cursor:pointer; font-size:1.5rem; padding:0; width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:8px; transition:all 0.2s;" onmouseover="this.style.background='#f1f5f9'; this.style.color='#0f172a';" onmouseout="this.style.background='transparent'; this.style.color='#64748b';"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="fwd-search-wrap">
                <div style="position:relative;">
                    <i class="bi bi-search" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--pf-cyan); opacity:0.6; font-size:0.9rem;"></i>
                    <input type="text" id="fwdSearch" class="fwd-search-input" placeholder="Search orders or names..." oninput="debounceFwdSearch(this.value)">
                </div>
            </div>
            <div class="fwd-preview-section">
                <div class="fwd-preview-label">Preview</div>
                <div id="fwdPreview" style="font-size:0.85rem; color:#334155; opacity:0.8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"></div>
            </div>
            <div id="fwdList" class="fwd-body"></div>
            <div class="fwd-footer">
                <button onclick="closeFwd()" style="padding:0 20px; height:44px; border-radius:14px; border:1px solid var(--pf-border); background:transparent; color:var(--pf-dim); font-weight:700; font-size:0.9rem; cursor:pointer;">Cancel</button>
                <button id="fwdSendBtn" onclick="doForward()" disabled style="padding:0 32px; height:44px; border-radius:14px; border:1px solid var(--pf-border); background:#0a2530; color:#fff; font-weight:700; font-size:0.9rem; cursor:pointer; display:flex; align-items:center; gap:8px; transition:all 0.2s;">Send <i class="bi bi-send-fill"></i></button>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="detailsModal" class="details-modal-overlay" onclick="closeDetailsModal()">
        <div class="details-modal-panel" onclick="event.stopPropagation()">
            <div class="details-modal-header">
                <div>
                   <h2 style="color:#0f172a; font-size:1.1rem; font-weight:900; margin:0;">Customer Order Overview</h2>
                   <p style="color:#94a3b8; font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.12em; margin-top:2px;">Production Specifications</p>
                </div>
                <button type="button" onclick="closeDetailsModal()" style="background:transparent; border:none; color:#64748b; cursor:pointer; font-size:1.5rem;"><i class="bi bi-x-lg"></i></button>
            </div>
            <div id="detailsBody" class="details-modal-content"></div>
        </div>
    </div>

<script>
window.onerror = function(msg, url, line) {
    console.error("[PrintFlow][JS] Error:", msg, "at", url, ":", line);
    return false;
};

window.PF_CALL_SERVER_URL = <?= json_encode(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ':3000') ?>;
const BASE = <?= json_encode(BASE_URL) ?>;
const CURRENT_USER_TYPE = 'customer';
const ME_ID = <?= json_encode((int)$user_id) ?>;
const ME_NAME = <?= json_encode($user_name) ?>;
const ME_AVATAR = <?= json_encode(get_profile_image($user_avatar)) ?>;
const DEFAULT_PROFILE_IMAGE = `${BASE}/public/assets/uploads/profiles/default.png`;
const PROFILE_IMAGE_ONERROR = `this.onerror=null;this.src='${DEFAULT_PROFILE_IMAGE}'`;
const EMOJIS = {like:'👍', love:'❤️', haha:'😂', wow:'😮', sad:'😢', angry:'😡'};

let activeId = null, lastId = 0, pollTimer = null, isSendingMessage = false;
window.__initialOrderId = <?= json_encode($initial_order_id) ?>;

// --- PrintFlow Call System Initialization ---
(function() {
    function initPFCall() {
        if (window.__PFCallBootstrapped) {
            return;
        }
        if (window.PFCall && typeof window.PFCall.init === "function") {
            window.__PFCallBootstrapped = true;
            window.PFCall.init({
                userId: ME_ID,
                userType: 'customer',
                userName: ME_NAME,
                userAvatar: ME_AVATAR,
                basePath: BASE
            });
            window.PFCallReady = true;
            document.dispatchEvent(new CustomEvent('PFCallGlobalReady'));
            window.dispatchEvent(new CustomEvent('PFCallGlobalReady'));
        } else {
            setTimeout(initPFCall, 100);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPFCall);
    } else {
        initPFCall();
    }
})();


let initialOrderHandled = false;

let isArchView = false, isConvArch = false, uploads = [], pfc = null;
let partnerAvatarUrl = '', replyId = null;

// Recording Globals
let mediaRecorder, audioChunks = [], timerInterval, animationId, audioCtx, analyser, source, previewAudio, pendingVoiceBlob = null;
const MAX_REC_DURATION = 60; 

async function api(url, method = 'GET', body = null) {
    try {
        const opts = { method };
        if (body) opts.body = (body instanceof FormData) ? body : JSON.stringify(body);
        const r = await fetch(BASE + url, opts);
        return await r.json();
    } catch(e) { return { success: false, error: e.message }; }
}

function resolveAppUrl(path, fallback = '') {
    if (!path || path === 'null' || path === 'undefined') return fallback;
    const value = String(path).trim();
    if (!value) return fallback;
    if (/^(https?:)?\/\//i.test(value) || value.startsWith('data:') || value.startsWith('blob:')) return value;
    if (value.startsWith(BASE + '/')) return value;
    if (value.startsWith('/')) return value;
    if (value.startsWith('printflow/')) return '/' + value;
    return `${BASE}/${value.replace(/^\/+/, '')}`;
}

function resolveProfileUrl(path) {
    if (!path || path === 'null' || path === 'undefined') return DEFAULT_PROFILE_IMAGE;
    const value = String(path).trim();
    if (!value) return DEFAULT_PROFILE_IMAGE;
    if (/^(https?:)?\/\//i.test(value) || value.startsWith('data:') || value.startsWith('blob:')) return value;
    if (value.startsWith(BASE + '/')) return value;
    if (value.startsWith('/')) return value;
    if (value.startsWith('printflow/')) return '/' + value;
    if (value.startsWith('public/') || value.startsWith('assets/')) {
        return `${BASE}/${value.replace(/^\/+/, '')}`;
    }
    return `${BASE}/public/assets/uploads/profiles/${value.replace(/^\/+/, '')}`;
}

function getCanvasContext(id) {
    const canvas = typeof id === 'string' ? document.getElementById(id) : id;
    if (!canvas) return { canvas: null, ctx: null };
    const ctx = typeof canvas.getContext === 'function' ? canvas.getContext('2d') : null;
    return { canvas, ctx };
}

function closeAudioContextSafely(context) {
    if (context && context.state !== 'closed') {
        context.close().catch(() => {});
    }
}

function switchTab(archived) {
    isArchView = archived;
    const tabActive = document.getElementById('tabActive');
    const tabArchived = document.getElementById('tabArchived');
    if (tabActive) tabActive.classList.toggle('active', !archived);
    if (tabArchived) tabArchived.classList.toggle('active', archived);
    loadConvs();
}

function isMobileChatView() {
    return window.matchMedia('(max-width: 768px)').matches;
}

function closeChatMobile() {
    const root = document.getElementById('chat-root');
    if (root) root.classList.remove('chat-open');
}

function openChatMobile() {
    const root = document.getElementById('chat-root');
    if (root && isMobileChatView()) root.classList.add('chat-open');
}

function tryOpenInitialConversation(conversations) {
    if (initialOrderHandled || !window.__initialOrderId) return;
    const initialId = parseInt(window.__initialOrderId, 10);
    if (!initialId) {
        initialOrderHandled = true;
        return;
    }

    const match = Array.isArray(conversations)
        ? conversations.find(c => parseInt(c.order_id, 10) === initialId)
        : null;

    if (match) {
        initialOrderHandled = true;
        openChat(
            match.order_id,
            match.staff_name || 'PrintFlow Team',
            match.product_name || 'Order',
            match.is_archived ? 1 : 0,
            match.staff_avatar || ''
        );
        return;
    }

    api(`/public/api/chat/order_details.php?order_id=${initialId}`).then(res => {
        if (!res || !res.success || !res.order) return;
        initialOrderHandled = true;
        const firstItem = Array.isArray(res.items) && res.items.length ? res.items[0] : null;
        openChat(
            initialId,
            'PrintFlow Team',
            firstItem?.product_name || 'Order',
            0,
            ''
        );
    });
}

function loadConvs() {
    const searchInput = document.getElementById('convSearch');
    const q = searchInput ? searchInput.value : '';
    api(`/public/api/chat/list_conversations.php?archived=${isArchView?1:0}&q=${encodeURIComponent(q)}`).then(res => {
        const list = document.getElementById('convList');
        if (!list) return;
        if (!res.success || !res.conversations || !res.conversations.length) {
            tryOpenInitialConversation([]);
            list.innerHTML = `
            <div class="p-12 text-center">
                <div class="text-5xl opacity-10 text-white mb-4"><i class="bi bi-patch-question-fill"></i></div>
                <div class="text-white opacity-40 font-bold text-sm">No ${isArchView?'archived':'active'} orders found.</div>
            </div>`;
            return;
        }
        tryOpenInitialConversation(res.conversations);
        list.innerHTML = res.conversations.map(c => {
            const name = c.staff_name || 'PrintFlow Team';
            const active = activeId === c.order_id ? 'active' : '';
            return `
            <div class="conv-card ${active}" onclick="openChat(${c.order_id},'${esc(name)}','${esc(c.product_name||'Order')}',${c.is_archived?1:0},'${esc(c.staff_avatar||'')}')">
                <div class="conv-av">${c.staff_avatar ? `<img src="${resolveProfileUrl(c.staff_avatar)}" onerror="${PROFILE_IMAGE_ONERROR}">` : (name === 'PrintFlow Team' ? `<img src="${BASE}/public/assets/images/favicon.png" style="width:24px;height:24px;object-fit:contain;opacity:0.8;">` : `<span>${name[0].toUpperCase()}</span>`)}</div>
                <div class="conv-info">
                    <div class="conv-top"><span class="conv-name">${esc(name)}</span><span class="conv-time">${fmtTimeAgo(c.last_message_at)}</span></div>
                    <div class="conv-sub">ORDER #${c.order_id} · ${esc(c.product_name||'Order')}</div>
                    <div class="conv-prev">${esc(c.last_message||'No messages yet')}</div>
                </div>
            </div>`;
        }).join('');
    });
}

function openChat(id, name, meta, archived, avatar = '') {
    activeId = id; lastId = 0; isConvArch = !!archived; partnerAvatarUrl = avatar ? resolveProfileUrl(avatar) : '';
    openChatMobile();
    document.getElementById('welcome').style.display = 'none';
    document.getElementById('chatInterface').style.display = 'flex';
    document.getElementById('hName').textContent = name;
    document.getElementById('hMeta').textContent = 'Order #' + id + ' · ' + meta;
    const hAv = document.getElementById('hAvatar');
    hAv.innerHTML = avatar 
        ? `<img src="${resolveProfileUrl(avatar)}" style="width:100%;height:100%;object-fit:cover;" onerror="${PROFILE_IMAGE_ONERROR}">` 
        : (name === 'PrintFlow Team' 
            ? `<img src="${BASE}/public/assets/images/favicon.png" style="width:28px;height:28px;object-fit:contain;opacity:0.9;">`
            : `<span>${name[0].toUpperCase()}</span>`);
    updateArchUI(archived);
    document.getElementById('messagesArea').innerHTML = '';
    loadMsgs();
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(loadMsgs, 2000);
}

function updateArchUI(arch) {
    isConvArch = !!arch;
    document.getElementById('archItem').innerHTML = arch ? '<i class="bi bi-arrow-up-circle"></i> Unarchive' : '<i class="bi bi-archive"></i> Archive';
}

function scrollToBottom(instant = false) {
    const box = document.getElementById('messagesArea');
    if (!box) return;
    if (instant) {
        box.style.scrollBehavior = 'auto';
        box.scrollTop = box.scrollHeight;
        box.style.scrollBehavior = 'smooth';
    } else {
        // Only auto-scroll if user is near bottom
        const threshold = 150;
        const isNearBottom = (box.scrollHeight - box.scrollTop - box.clientHeight) < threshold;
        if (isNearBottom) {
            box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
        }
    }
}

function loadMsgs() {
    if (!activeId) return;
    const isFirstLoad = (lastId === 0);
    const box = document.getElementById('messagesArea');
    api(`/public/api/chat/fetch_messages.php?order_id=${activeId}&last_id=${lastId}&is_active=1`).then(res => {
        if (!res.success) return;
        if (isFirstLoad) box.innerHTML = '';
        
        const rxMap = {};
        (res.reactions || []).forEach(r => { if (!rxMap[r.message_id]) rxMap[r.message_id] = []; rxMap[r.message_id].push(r); });

        res.messages.forEach(m => {
            appendMsgUI(m);
            lastId = Math.max(lastId, m.id);
        });

        Object.keys(rxMap).forEach(mid => renderReactions(mid, rxMap[mid]));
        document.getElementById('hOnline').style.display = res.partner.is_online ? 'inline-block' : 'none';
        updatePinnedBar(res.pinned_messages || []);
        if (res.last_seen_message_id) updateSeenIndicator(res.last_seen_message_id);
        
        if (res.messages.length) {
            if (isFirstLoad) {
                // Instant scroll to bottom on first load
                requestAnimationFrame(() => scrollToBottom(true));
            } else {
                // Smooth scroll if already at bottom
                scrollToBottom(false);
            }
        }
    });
}

function updatePinnedBar(pinned) {
    const bar = document.getElementById('pinnedBar');
    const text = document.getElementById('pinnedTxt');
    if (!bar || !text) return;
    if (!pinned || pinned.length === 0) {
        bar.style.display = 'none';
        bar.classList.remove('pin-bar-active');
        return;
    }
    bar.style.display = 'flex';
    bar.classList.add('pin-bar-active');
    text.textContent = pinned.length === 1 ? '1 pinned message' : `${pinned.length} pinned messages`;
    bar.onclick = () => openPinnedModal(pinned);
}

function openPinnedModal(pinned) {
    if (!document.getElementById('pinnedModal')) {
        const div = document.createElement('div');
        div.id = 'pinnedModal';
        div.className = 'details-modal-overlay';
        div.innerHTML = `
            <div class="details-modal-panel" style="max-width:450px;">
                <div class="details-modal-header">
                    <h2 style="font-size:1.1rem; font-weight:900; color:#1e293b; margin:0;">Pinned Messages</h2>
                    <button type="button" onclick="document.getElementById('pinnedModal').classList.remove('active')" style="border:none; background:transparent; cursor:pointer;">
                         <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg>
                    </button>
                </div>
                <div id="pinnedList" style="padding:1.5rem; max-height:500px; overflow-y:auto; display:flex; flex-direction:column; gap:10px;"></div>
            </div>
        `;
        document.body.appendChild(div);
    }
    const modal = document.getElementById('pinnedModal');
    modal.classList.add('active');
    const list = document.getElementById('pinnedList');
    
    list.innerHTML = pinned.map(m => {
        let mediaHtml = '';
        if (m.message_type === 'voice') {
            const src = resolveAppUrl(m.message_file || m.file_path || m.image_path);
            mediaHtml = `<div style="margin-top:8px; background:#e2e8f0; padding:8px; border-radius:12px; display:flex; align-items:center; gap:10px;">
                <audio controls src="${src}" style="height:30px; width:100%; outline:none;"></audio>
            </div>`;
        } else if (m.message_type === 'video' || m.file_type === 'video') {
            const src = resolveAppUrl(m.message_file || m.file_path || m.image_path);
            mediaHtml = `<div style="margin-top:8px; border-radius:12px; overflow:hidden; background:#000;">
                <video src="${src}" controls style="width:100%; max-height:200px; display:block;"
                    onerror="this.insertAdjacentHTML('afterend', '<div style=\'padding:10px; background:#f1f5f9; border-radius:8px; font-size:0.8rem; color:#64748b; text-align:center;\'><i class=\'bi bi-exclamation-triangle-fill\'></i> Video unavailable</div>'); this.style.display=\'none\';">
                </video>
            </div>`;
        } else if (m.message_type === 'image' || m.image_path) {
            const src = resolveAppUrl(m.image_path || m.message_file || m.file_path);
            mediaHtml = `<div style="margin-top:8px; border-radius:12px; overflow:hidden; background:#f1f5f9;">
                <img src="${src}" style="max-width:100%; max-height:200px; object-fit:contain; display:block;">
            </div>`;
        }

        return `
        <div style="padding:12px; border-radius:12px; background:#f8fafc; border:1px solid #e2e8f0; cursor:pointer; transition:all 0.2s;" onclick="goToMessage(${m.id}); document.getElementById('pinnedModal').classList.remove('active')">
            <div style="font-size:0.7rem; color:#000000; font-weight:800; margin-bottom:4px;">${m.sender_name} • ${fmtShort(m.created_at)}</div>
            ${m.message ? `<div style="font-size:0.95rem; color:#000000; line-height:1.4; word-break:break-word; overflow-wrap:anywhere;">${esc(m.message)}</div>` : ''}
            ${mediaHtml}
        </div>`;
    }).join('');
}

function goToMessage(id) {
    const el = document.getElementById(`ms-${id}`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.style.animation = 'highlightStaffMsg 2s ease';
    }
}

function getOrderUpdateActionLabel(actionType) {
    return 'Open order';
}

function normalizeSenderType(value) {
    const senderType = String(value || '').toLowerCase();
    return senderType === 'customer' || senderType === 'staff' ? senderType : '';
}

function getMessageSide(message) {
    const senderType = normalizeSenderType(message?.sender_type);
    if (senderType) {
        return senderType === CURRENT_USER_TYPE ? 'self' : 'other';
    }
    return (message?.is_system && message?.message_type !== 'order_update') ? 'system' : (message?.is_self ? 'self' : 'other');
}

function getMessageSenderKey(message) {
    const senderType = normalizeSenderType(message?.sender_type);
    if (senderType) {
        return senderType;
    }
    if (message?.is_system && message?.message_type !== 'order_update') {
        return 'system';
    }
    return String(message?.sender || '').toLowerCase() || (message?.is_self ? 'self' : 'other');
}

function getOrderStatusTone(statusText) {
    const normalized = String(statusText || '').toLowerCase();
    if (normalized.includes('cancel') || normalized.includes('reject')) return 'alert';
    if (normalized.includes('complete')) return 'complete';
    if (normalized.includes('pickup') || normalized.includes('receive') || normalized.includes('ready')) return 'ready';
    if (normalized.includes('production')) return 'production';
    if (normalized.includes('pay') || normalized.includes('verify')) return 'payment';
    if (normalized.includes('approved')) return 'approved';
    if (normalized.includes('pending') || normalized.includes('review') || normalized.includes('revision')) return 'pending';
    return 'neutral';
}

function getOrderCardData(message) {
    const orderUpdate = message.order_update || {};
    let meta = {};
    try { meta = JSON.parse(message.meta_json || '{}'); } catch (e) {}

    return {
        orderId: Number(orderUpdate.order_id || meta.order_id || activeId || 0),
        productName: orderUpdate.product_name || meta.product_name || 'Order update',
        statusLabel: orderUpdate.status || meta.order_status || orderUpdate.payment_status || meta.payment_status || 'Status updated',
        thumbnail: orderUpdate.thumbnail || message.thumbnail || '',
        messageText: orderUpdate.description || message.message || '',
    };
}

function renderOrderUpdateMessage(m) {
    const actionType = m.action_type || 'view_status';
    const orderCard = getOrderCardData(m);
    const statusTone = getOrderStatusTone(orderCard.statusLabel);

    return `
        <div class="b-col">
            <div class="order-update-bubble" onclick="handleOrderUpdateClick(${orderCard.orderId})" onkeydown="if(event.key==='Enter' || event.key===' '){event.preventDefault();handleOrderUpdateClick(${orderCard.orderId});}" role="button" tabindex="0" title="Open order details">
                <div class="order-thumb-wrap">
                    <img src="${resolveAppUrl(orderCard.thumbnail, `${BASE}/public/assets/images/services/default.png`)}" class="order-thumb" onerror="this.onerror=null;this.src='${BASE}/public/assets/images/services/default.png'">
                </div>
                <div class="order-text">
                    <div class="order-update-head">
                        <div class="order-update-badge">Order update</div>
                        <div class="order-status-pill tone-${statusTone}">${esc(orderCard.statusLabel)}</div>
                    </div>
                    <div class="order-title">${esc(orderCard.productName)}</div>
                    <div class="order-message">${esc(orderCard.messageText)}</div>
                    <div class="order-update-meta">
                        <span class="order-update-time">${fmtShort(m.created_at)}</span>
                        <span class="order-update-cta">${esc(getOrderUpdateActionLabel(actionType))}</span>
                    </div>
                </div>
            </div>
        </div>`;
}

function appendMsgUI(m) {
    const box = document.getElementById('messagesArea');
    if (document.getElementById(`ms-${m.id}`)) return;

    // Messenger Grouping Logic
    const prevRow = box.lastElementChild;
    const messageTimeKey = m.created_at_full || m.created_at;
    const currentMin = getMinute(messageTimeKey);
    const prevMin = prevRow ? getMinute(prevRow.getAttribute('data-time')) : null;
    
    const isCallLog = m.message_type === 'call_log' || m.message_type === 'call_event' || /voice call|video call|missed|declined|busy/i.test(m.message);
    const rowSide = getMessageSide(m);
    const senderKey = getMessageSenderKey(m);
    const rowClass = (rowSide === 'system' && !isCallLog) ? 'system' : rowSide;
    const isSelf = rowClass === 'self';

    if (m.message_type === 'order_update') {
        const row = document.createElement('div');
        row.id = `ms-${m.id}`;
        row.className = `brow order-update-card ${rowSide === 'system' ? 'other' : rowSide}`;
        row.setAttribute('data-sender', senderKey);
        row.setAttribute('data-time', messageTimeKey);
        row.innerHTML = renderOrderUpdateMessage(m);
        box.appendChild(row);
        return;
    }

    const isGrouped = prevRow && !prevRow.classList.contains('order-update-card') && rowClass !== 'system' &&
                      prevRow.getAttribute('data-sender') === senderKey &&
                      currentMin === prevMin;

    const row = document.createElement('div');
    row.id = `ms-${m.id}`;
    row.className = `brow ${rowClass}`;
    row.setAttribute('data-sender', senderKey);
    row.setAttribute('data-time', messageTimeKey);

    if (isGrouped) {
        prevRow.classList.add('grouped-msg');
        row.classList.add('grouped-msg-next');
    }

    if (m.is_system && !isCallLog) {
        row.innerHTML = `<div class="b-col"><div class="bubble">${esc(m.message)}</div></div>`;
        box.appendChild(row); return;
    }

    const msgB64 = btoa(unescape(encodeURIComponent(m.message || '')));
    const avHtml = (!isSelf) ? `<div class="conv-av" style="width:32px; height:32px; border-radius:50%; align-self:flex-end;">${m.sender_avatar ? `<img src="${resolveProfileUrl(m.sender_avatar)}" style="border-radius:50%;" onerror="${PROFILE_IMAGE_ONERROR}">` : `<span>${(m.sender_name||'S')[0].toUpperCase()}</span>`}</div>` : '';
    
    let contentHtml = '';
    if (isCallLog) {
        const isVideo = m.message.toLowerCase().includes('video');
        const isMissed = m.message.toLowerCase().includes('missed') || m.message.toLowerCase().includes('declined') || m.message.toLowerCase().includes('busy') || m.message.toLowerCase().includes('no answer');
        const icon = isVideo ? '<i class="bi bi-camera-video-fill"></i>' : '<i class="bi bi-telephone-fill"></i>';
        const statusText = isSelf ? 'Outgoing' : 'Incoming';

        contentHtml = `
            <div class="call-log-bubble">
                <div class="call-log-icon ${isMissed ? 'missed' : 'ended'}">${icon}</div>
                <div class="call-log-details">
                    <div class="call-log-title" style="${isMissed ? 'color: #e11d48;' : 'color: #0d9488;'}">${esc(m.message)}</div>
                    <div class="call-log-status">${statusText}</div>
                </div>
            </div>
        `;
    } else if (m.message_type === 'voice') {
        const audioSrc = resolveAppUrl(m.message_file || m.file_path || m.image_path);
        contentHtml = `
        <div class="voice-bubble-player" id="v-p-${m.id}">
            <button class="play-pause-bubble" onclick="toggleVoicePlayer(${m.id}, '${audioSrc}')">
                <i class="bi bi-play-fill" id="v-icon-${m.id}"></i>
            </button>
            <div class="v-waveform-container" onclick="seekVoice(${m.id}, event)">
                <canvas class="v-waveform-canvas" id="v-canvas-${m.id}"></canvas>
            </div>
            <span class="v-duration" id="v-dur-${m.id}">${m.duration > 0 ? fmtDuration(m.duration) : '0:00'}</span>
            <audio id="v-audio-${m.id}" src="${audioSrc}" ontimeupdate="updateVoiceProgress(${m.id})" onended="resetVoicePlayer(${m.id})" onloadedmetadata="initVoiceDuration(${m.id})" onerror="handleVoiceAudioError(${m.id})"></audio>
        </div>`;
        setTimeout(() => drawWaveformFromUrl(audioSrc, `v-canvas-${m.id}`, isSelf ? 'rgba(255,255,255,0.7)' : 'rgba(83,197,224,0.7)', m.id), 50);
    } else if (m.message_type === 'video' || m.file_type === 'video') {
        const videoSrc = resolveAppUrl(m.message_file || m.file_path || m.image_path);
        contentHtml = `
            <div class="chat-video-wrapper" onclick="zoomVideo('${videoSrc.replace(/'/g, "\\'")}')" style="position:relative;cursor:pointer;border-radius:12px;overflow:hidden;max-width:250px;background:#000;margin-bottom:5px;">
                <video src="${videoSrc}" style="width:100%;display:block;border-radius:12px;" preload="metadata" muted playsinline
                    onerror="this.insertAdjacentHTML('afterend', '<div style=\'padding:20px; color:#fff; font-size:0.8rem; text-align:center;\'><i class=\'bi bi-play-btn\'></i><br>Video unavailable</div>'); this.style.display=\'none\';">
                </video>
                <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
                    <div style="width:40px;height:40px;background:rgba(0,0,0,0.5);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                        <svg width="20" height="20" fill="white" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    </div>
                </div>
            </div>
            ${m.message ? `<span>${esc(m.message)}</span>` : ''}
        `;
    } else if (m.message_type === 'image' || m.image_path) {
        const imgSrc = resolveAppUrl(m.image_path || m.message_file || m.file_path);
        contentHtml = `
            <img class="chat-img" src="${imgSrc}" onclick="zoomImg('${imgSrc.replace(/'/g, "\\'")}')" style="max-width:250px; border-radius:12px; margin-bottom:5px; display:block; cursor:pointer;">
            ${m.message ? `<span>${esc(m.message)}</span>` : ''}
        `;
    } else {
        contentHtml = `${m.message ? `<span>${esc(m.message)}</span>` : ''}`;
    }

    row.innerHTML = `
        ${avHtml}
        <div class="b-col">
            <div class="b-actions">
                <div class="ab" onclick="toggleReact(${m.id},event)" style="position:relative;"><i class="bi bi-emoji-smile"></i><div class="react-picker" id="rp-${m.id}">${Object.entries(EMOJIS).map(([k,v])=>`<span onclick="react(${m.id},'${k}')">${v}</span>`).join('')}</div></div>
                <div class="ab" onclick="initReply(${m.id},'${msgB64}')"><i class="bi bi-reply-fill"></i></div>
                <div class="ab" style="position:relative;" onclick="toggleMore(${m.id},event)"><i class="bi bi-three-dots"></i><div class="more-menu" id="mm-${m.id}"><div class="mi" onclick="pinMsg(${m.id})"><i class="bi ${m.is_pinned == 1 ? 'bi-pin-angle-fill' : 'bi-pin-angle'}"></i> ${m.is_pinned == 1 ? 'Unpin' : 'Pin'}</div><div class="mi" onclick="initFwd(${m.id},'${msgB64}','${m.message_type}')"><i class="bi bi-arrow-right"></i> Forward</div></div></div>
            </div>
            <div class="bubble" style="position:relative;">
                ${m.is_pinned == 1 ? `<div class="pinned-badge" title="Pinned Message"><i class="bi bi-pin-fill"></i></div>` : ''}
                ${m.is_forwarded ? `<div style="font-size:0.65rem; color:var(--pf-dim); margin-bottom:4px; font-style:italic; display:flex; align-items:center; gap:3px;"><i class="bi bi-arrow-90deg-right"></i> Forwarded</div>` : ''}
                ${m.reply_id ? `<div style="background:#f1f5f9; padding:6px 10px; border-radius:8px; border-left:3px solid var(--pf-cyan); font-size:0.75rem; color:var(--pf-dim); margin-bottom:6px; cursor:pointer;" onclick="document.getElementById('ms-${m.reply_id}')?.scrollIntoView({behavior:'smooth',block:'center'})">↳ Replying: ${esc(m.reply_message||'Attachment')}</div>` : ''}
                ${contentHtml}
                <div class="react-display" id="rd-${m.id}" style="display:none;"></div>
            </div>
            <div class="b-meta">${fmtShort(m.created_at)}</div>
            ${isSelf ? `<div class="seen-wrapper" id="sw-${m.id}"></div>` : ''}
        </div>`;
    box.appendChild(row);
    bindMobileMessageHold(row);
}

function getMinute(d) {
    if(!d) return null;
    const raw = String(d);
    let date = new Date(raw.replace(/-/g,'/'));
    if (isNaN(date) && (raw.includes('AM') || raw.includes('PM'))) {
        date = new Date(`${new Date().toDateString()} ${raw}`);
    }
    if(isNaN(date)) return null;
    return date.getFullYear() + '-' + (date.getMonth()+1) + '-' + date.getDate() + ' ' + date.getHours() + ':' + date.getMinutes();
}

function initReply(id, msgB64) {
    replyId = id;
    const txt = decodeURIComponent(escape(atob(msgB64)));
    document.getElementById('replyBox').style.display = 'flex';
    document.getElementById('replyPreviewTxt').textContent = txt || 'Attachment';
    document.getElementById('customerMsgInput').focus();
    closeAllMenus();
}

function cancelReply() {
    replyId = null;
    document.getElementById('replyBox').style.display = 'none';
}


/**
 * MESSENGER-STYLE HOLD-TO-RECORD LOGIC
 */
function initRecordingEvents() {
    const micBtn = document.getElementById("micBtnMain");
    if (!micBtn || micBtn.dataset.pfRecordingInit === '1') return;
    micBtn.dataset.pfRecordingInit = '1';

    const start = (e) => { e.preventDefault(); window.startRecording(); };
    if (window.PointerEvent) {
        micBtn.addEventListener("pointerdown", start);
    } else {
        micBtn.addEventListener("mousedown", start);
        micBtn.addEventListener("touchstart", start, { passive: false });
    }

    if (!window.__pfCustomerChatRecordingReleaseBound) {
        window.__pfCustomerChatRecordingReleaseBound = true;
        const stop = () => {
            if (mediaRecorder && mediaRecorder.state === "recording") {
                window.stopRecording();
            }
        };
        if (window.PointerEvent) {
            window.addEventListener("pointerup", stop);
            window.addEventListener("pointercancel", stop);
        } else {
            window.addEventListener("mouseup", stop);
            window.addEventListener("touchend", stop);
            window.addEventListener("touchcancel", stop);
        }
        window.addEventListener("blur", stop);
        document.addEventListener("visibilitychange", () => {
            if (document.hidden) stop();
        });
    }
}

window.startRecording = async function() {
    if (mediaRecorder && mediaRecorder.state === "recording") return;
    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
        alert("Microphone access denied");
        return;
    }
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const recorderOptions = MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
            ? { mimeType: 'audio/webm;codecs=opus' }
            : undefined;
        mediaRecorder = recorderOptions ? new MediaRecorder(stream, recorderOptions) : new MediaRecorder(stream);
        mediaRecorder.start(250);
        audioChunks = [];
        let seconds = 0;

        const recordStatus = document.getElementById("recordStatusMain");
        const inputBar = document.getElementById("inputBarMain");
        const micBtn = document.getElementById("micBtnMain");
        const micIcon = document.getElementById("micIconMain");
        if (recordStatus) recordStatus.classList.remove("hidden");
        if (inputBar) inputBar.classList.add("hidden");
        if (micBtn) micBtn.classList.add("recording");
        if (micIcon) micIcon.className = "bi bi-stop-fill";

        timerInterval = setInterval(() => {
            seconds++;
            const timer = document.getElementById("timerMain");
            if (timer) timer.textContent = fmtDuration(seconds);
            if (seconds >= MAX_REC_DURATION) stopRecording();
        }, 1000);

        mediaRecorder.ondataavailable = e => {
            if (e.data && e.data.size > 0) audioChunks.push(e.data);
        };
        mediaRecorder.onstop = showVoicePreview;
        startVisualizer(stream);
    } catch (e) {
        alert("Microphone access denied");
    }
};

window.stopRecording = function() {
    if (mediaRecorder && mediaRecorder.state === "recording") {
        mediaRecorder.stop();
        mediaRecorder.stream.getTracks().forEach(t => t.stop());
    }
    clearInterval(timerInterval);
    stopVisualizer();
    const recordStatus = document.getElementById("recordStatusMain");
    const micBtn = document.getElementById("micBtnMain");
    const micIcon = document.getElementById("micIconMain");
    if (recordStatus) recordStatus.classList.add("hidden");
    if (micBtn) micBtn.classList.remove("recording");
    if (micIcon) micIcon.className = "bi bi-mic";
};

function updateSeenIndicator(lastSeenId) {
    document.querySelectorAll('.seen-wrapper').forEach(el => el.innerHTML = '');
    const selfRows = [...document.querySelectorAll('.brow.self')];
    let lastSeenRow = null;
    selfRows.forEach(row => {
        const id = parseInt(row.id.replace('ms-', ''));
        if (id <= lastSeenId) lastSeenRow = row;
    });
    if (lastSeenRow) {
        const wrap = lastSeenRow.querySelector('.seen-wrapper');
        if (wrap) wrap.innerHTML = partnerAvatarUrl ? `<img src="${partnerAvatarUrl}" class="seen-avatar" title="Seen" onerror="${PROFILE_IMAGE_ONERROR}">` : `<span style="font-size:10px; color:var(--pf-dim); font-weight:800; opacity:0.6;">✓ Seen</span>`;
    }
}

function renderReactions(id, rx) {
    const el = document.getElementById('rd-' + id); if (!el) return;
    if (!rx || !rx.length) { el.style.display = 'none'; return; }
    const counts = {}; rx.forEach(r => counts[r.reaction_type] = (counts[r.reaction_type]||0)+1);
    el.innerHTML = Object.entries(counts).map(([t, c]) => `<div class="react-chip">${EMOJIS[t]||t}${c>1?` <b>${c}</b>`:''}</div>`).join('');
    el.style.display = 'flex';
}



function sendMsg() {
    if (pendingVoiceBlob) { sendVoice(); return; }
    const input = document.getElementById('customerMsgInput'), txt = input.value.trim();
    const btn = document.getElementById('customerSendBtn');
    if ((!txt && !uploads.length) || isSendingMessage || !activeId || (btn && btn.disabled)) return;
    if (txt.length > 500) {
        showToast('Message cannot exceed 500 characters.', 'warning');
        return;
    }
    isSendingMessage = true;
    btn.disabled = true;
    const fd = new FormData(); fd.append('order_id', activeId);
    if (txt) fd.append('message', txt);
    if (replyId) fd.append('reply_id', replyId);
    uploads.forEach(f => fd.append('image[]', f));
    api('/public/api/chat/send_message.php', 'POST', fd).then(res => {
        if (res.success) { 
            input.value = ''; uploads = []; 
            document.getElementById('customerImgPreview').style.display='none'; 
            cancelReply();
            loadMsgs(); 
        } else {
            showToast(res.error || 'Failed to send message.', 'error');
        }
    }).catch(err => {
        showToast(err?.message || 'Failed to send message.', 'error');
    }).finally(() => {
        isSendingMessage = false;
        btn.disabled = false;
        document.getElementById('customerCharCount').textContent = input.value.length + '/500';
        input.style.height = 'auto';
        input.focus();
    });
}

function cancelRecording() {
    if (mediaRecorder && mediaRecorder.state === "recording") {
        mediaRecorder.onstop = null;
        mediaRecorder.stop();
        mediaRecorder.stream.getTracks().forEach(t => t.stop());
    }
    if (previewAudio) { previewAudio.pause(); previewAudio = null; }
    pendingVoiceBlob = null;
    const previewArea = document.getElementById("voicePreviewAreaMain");
    const inputBar = document.getElementById("inputBarMain");
    const micBtn = document.getElementById("micBtnMain");
    if (previewArea) previewArea.style.display = 'none';
    if (inputBar) inputBar.classList.remove("hidden");
    if (micBtn) micBtn.style.display = 'flex';
    window.stopRecording();
}

function showVoicePreview() {
    pendingVoiceBlob = new Blob(audioChunks, { type: 'audio/webm' });
    if (pendingVoiceBlob.size < 100) { pendingVoiceBlob = null; return; }
    const previewArea = document.getElementById("voicePreviewAreaMain");
    const inputBar = document.getElementById("inputBarMain");
    if (previewArea) previewArea.style.display = 'flex';
    if (inputBar) inputBar.classList.add("hidden");
    
    drawWaveformPreview(pendingVoiceBlob, 'previewWaveformCanvasMain');
    const temp = new Audio(URL.createObjectURL(pendingVoiceBlob));
    temp.onloadedmetadata = () => {
        const duration = document.getElementById("previewDurationMain");
        if (duration) duration.textContent = fmtDuration(temp.duration);
    };
    temp.onerror = () => {
        const duration = document.getElementById("previewDurationMain");
        if (duration) duration.textContent = '0:00';
    };
}

function sendVoice() {
    if (!pendingVoiceBlob) return;
    const btn = document.getElementById('customerSendBtn');
    const oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i class='bi bi-hourglass-split animate-spin'></i>`;

    const fd = new FormData();
    fd.append("voice", pendingVoiceBlob);
    fd.append("order_id", activeId);
    if (replyId) fd.append("reply_id", replyId);

    fetch(BASE + "/public/api/chat/send_voice.php", { method: "POST", body: fd })
    .then(r => r.json()).then(res => {
        if (res.success) { cancelRecording(); loadMsgs(); }
        else showToast(res.error || "Upload failed");
    }).finally(() => {
        btn.disabled = false;
        btn.innerHTML = oldHtml;
    });
}

function togglePreviewPlayback() {
    if (!pendingVoiceBlob) return;
    const icon = document.getElementById("previewPlayIconMain");
    if (!icon) return;
    if (!previewAudio) {
        previewAudio = new Audio(URL.createObjectURL(pendingVoiceBlob));
        previewAudio.onended = () => { icon.className = "bi bi-play-fill"; previewAudio = null; };
    }
    if (previewAudio.paused) { previewAudio.play().catch(() => {}); icon.className = "bi bi-pause-fill"; }
    else { previewAudio.pause(); icon.className = "bi bi-play-fill"; }
}

function startVisualizer(stream) {
    const { canvas, ctx } = getCanvasContext("recordingCanvasMain");
    if (!canvas || !ctx) return;
    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    analyser = audioCtx.createAnalyser();
    source = audioCtx.createMediaStreamSource(stream);
    source.connect(analyser);
    const data = new Uint8Array(analyser.frequencyBinCount);
    function draw() {
        if (!analyser) return;
        analyser.getByteFrequencyData(data);
        ctx.clearRect(0,0,canvas.width,canvas.height);
        const w = (canvas.width / data.length) * 2.5;
        let x = 0;
        for (let i = 0; i < data.length; i++) {
            const h = (data[i] / 255) * canvas.height;
            ctx.fillStyle = '#ef4444';
            ctx.fillRect(x, canvas.height - h, w, h);
            x += w + 1;
        }
        animationId = requestAnimationFrame(draw);
    }
    draw();
}
function stopVisualizer() {
    if (animationId) cancelAnimationFrame(animationId);
    animationId = null;
    closeAudioContextSafely(audioCtx);
    audioCtx = null;
    analyser = null;
    source = null;
}

async function drawWaveformPreview(blob, canvasId) {
    if (!blob || !blob.size) return;
    const { canvas, ctx } = getCanvasContext(canvasId);
    if (!canvas || !ctx) return;

    let aCtx = null;
    try {
        const buffer = await blob.arrayBuffer();
        if (!buffer.byteLength) return;
        aCtx = new (window.AudioContext || window.webkitAudioContext)();
        const audioBuf = await aCtx.decodeAudioData(buffer);
        const raw = audioBuf.getChannelData(0);
        const samples = 50;
        const blockSize = Math.max(1, Math.floor(raw.length / samples));
        const filtered = [];

        for (let i = 0; i < samples; i++) {
            let sum = 0;
            for (let j = 0; j < blockSize; j++) {
                sum += Math.abs(raw[(blockSize * i) + j] || 0);
            }
            filtered.push(sum / blockSize);
        }

        if (!filtered.length) return;

        const peak = Math.max(...filtered) || 1;
        const mult = peak ? Math.pow(peak, -1) : 1;
        ctx.clearRect(0,0,canvas.width,canvas.height);
        const w = canvas.width / samples;
        filtered.forEach((n,i) => {
            const h = n * mult * canvas.height;
            ctx.fillStyle = '#53c5e0';
            ctx.fillRect(i * w, (canvas.height - h) / 2, w - 1, h);
        });
    } catch (e) {
        if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
    } finally {
        closeAudioContextSafely(aCtx);
    }
}

// Voice Player Shared Logic
const vCache = {};
async function drawWaveformFromUrl(url, canvasId, color, msgId = null) {
    if (!url) return;
    if (vCache[url]) { drawDataToCanvas(canvasId, vCache[url], color); return; }
    let aCtx = null;
    try {
        const r = await fetch(url, { cache: 'no-store' });
        if (!r.ok) return;
        const buf = await r.arrayBuffer();
        if (!buf.byteLength) return;
        aCtx = new (window.AudioContext || window.webkitAudioContext)();
        const audioBuf = await aCtx.decodeAudioData(buf);
        const raw = audioBuf.getChannelData(0), samples = 60, blockSize = Math.max(1, Math.floor(raw.length/samples)), data = [];
        for(let i=0; i<samples; i++) {
            let sum=0; for(let j=0; j<blockSize; j++) sum+=Math.abs(raw[(blockSize*i)+j] || 0);
            data.push(sum/blockSize);
        }
        if (!data.length) return;
        const peak = Math.max(...data) || 1;
        const mult = peak ? Math.pow(peak, -1) : 1;
        vCache[url] = data.map(n => n * mult);
        drawDataToCanvas(canvasId, vCache[url], color);
        if (msgId) {
            const dur = document.getElementById(`v-dur-${msgId}`);
            if (dur && audioBuf.duration > 0) dur.textContent = fmtDuration(audioBuf.duration);
        }
    } catch(e) {
        return;
    } finally {
        closeAudioContextSafely(aCtx);
    }
}
function drawDataToCanvas(id, data, color, prog = 0) {
    if (!data || !data.length) return;
    const { canvas: cvs, ctx } = getCanvasContext(id);
    if(!cvs || !ctx) return;
    const w = cvs.width / data.length;
    ctx.clearRect(0,0,cvs.width,cvs.height);
    data.forEach((n,i) => {
        ctx.fillStyle = (i / data.length) < prog ? '#53c5e0' : color;
        const h = n * cvs.height;
        ctx.fillRect(i * w, (cvs.height - h) / 2, w - 1, h);
    });
}
window.toggleVoicePlayer = function(id, src) {
    const audio = document.getElementById(`v-audio-${id}`), icon = document.getElementById(`v-icon-${id}`);
    if (!audio || !icon) return;
    document.querySelectorAll('audio').forEach(a => { if(a.id !== `v-audio-${id}`) { a.pause(); const si = a.id.replace('v-audio-',''), sic = document.getElementById(`v-icon-${si}`); if(sic) sic.className="bi bi-play-fill"; }});
    if (audio.paused) { audio.play().catch(() => {}); icon.className="bi bi-pause-fill"; }
    else { audio.pause(); icon.className="bi bi-play-fill"; }
};
window.updateVoiceProgress = function(id) {
    const audio = document.getElementById(`v-audio-${id}`), cvs = document.getElementById(`v-canvas-${id}`), dur = document.getElementById(`v-dur-${id}`);
    if(!audio || !cvs) return;
    if (!Number.isFinite(audio.duration) || audio.duration <= 0 || !vCache[audio.src]) return;
    const prog = audio.currentTime / audio.duration;
    if (dur) dur.textContent = fmtDuration(audio.currentTime);
    const row = cvs.closest('.brow');
    const isSelf = row ? row.classList.contains('self') : false;
    drawDataToCanvas(cvs.id, vCache[audio.src], isSelf ? 'rgba(255,255,255,0.7)' : 'rgba(83,197,224,0.7)', prog);
};
window.resetVoicePlayer = id => { const i = document.getElementById(`v-icon-${id}`); if(i) i.className="bi bi-play-fill"; };
window.initVoiceDuration = id => {
    const a = document.getElementById(`v-audio-${id}`), d = document.getElementById(`v-dur-${id}`);
    if(a && d) d.textContent = fmtDuration(a.duration);
};
window.seekVoice = (id, e) => {
    const a = document.getElementById(`v-audio-${id}`);
    if(!a || !Number.isFinite(a.duration) || a.duration <= 0) return;
    const rect = e.currentTarget.getBoundingClientRect();
    a.currentTime = ((e.clientX - rect.left) / rect.width) * a.duration;
};
window.handleVoiceAudioError = id => {
    const duration = document.getElementById(`v-dur-${id}`);
    if (duration) duration.textContent = '0:00';
};

function fmtDuration(s) {
    const n = Number(s);
    if (!Number.isFinite(n) || n < 0) return '0:00';
    const m = Math.floor(n / 60);
    const sec = Math.floor(n % 60);
    return `${m}:${sec.toString().padStart(2,'0')}`;
}

// Gallery & Misc
function onImgSelected() {
    const input = document.getElementById('customerMediaInput');
    for (const f of input.files) uploads.push(f);
    const prev = document.getElementById('customerImgPreview');
    prev.style.display = 'flex';
    prev.innerHTML = uploads.map((f,i) => `<div style="position:relative;"><img src="${URL.createObjectURL(f)}" style="width:50px;height:50px;border-radius:10px;object-fit:cover;border:1px solid var(--pf-border);"><button onclick="uploads.splice(${i},1);onImgSelected()" style="position:absolute;top:-5px;right:-5px;width:18px;height:18px;border-radius:50%;background:#ef4444;color:#fff;border:none;font-size:10px;cursor:pointer;">×</button></div>`).join('');
    input.value = '';
}

function toggleReact(id, e) { e.stopPropagation(); const el = document.getElementById('rp-'+id); const cur = el.classList.contains('show'); closeAllMenus(); if(!cur) el.classList.add('show'); }
function react(id, type) { const fd = new FormData(); fd.append('message_id',id); fd.append('reaction_type',type); api('/public/api/chat/react_message.php','POST',fd).then(r=>loadMsgs()); closeAllMenus(); }
function toggleMore(id, e) { e.stopPropagation(); const el = document.getElementById('mm-'+id); const cur = el.classList.contains('show'); closeAllMenus(); if(!cur) el.classList.add('show'); }
function bindMobileMessageHold(row) {
    if (!row || row.dataset.mobileHoldBound === '1' || !window.matchMedia('(max-width: 768px)').matches) return;
    row.dataset.mobileHoldBound = '1';
    let holdTimer = null;
    let holdTriggered = false;
    const target = row.querySelector('.bubble, .voice-bubble-player, .call-log-bubble, .order-update-bubble');
    if (!target) return;

    const startHold = (event) => {
        if (event.target.closest('.b-actions, .react-picker, .more-menu, .react-display, a, button, audio, video')) return;
        holdTriggered = false;
        clearTimeout(holdTimer);
        holdTimer = setTimeout(() => {
            holdTriggered = true;
            closeAllMenus();
            row.classList.add('has-active-menu');
        }, 450);
    };

    const clearHold = () => clearTimeout(holdTimer);

    target.addEventListener('touchstart', startHold, { passive: true });
    target.addEventListener('touchend', clearHold);
    target.addEventListener('touchcancel', clearHold);
    target.addEventListener('touchmove', clearHold);
    target.addEventListener('contextmenu', (event) => {
        event.preventDefault();
        closeAllMenus();
        row.classList.add('has-active-menu');
    });

    row.addEventListener('click', (event) => {
        if (holdTriggered) {
            event.preventDefault();
            event.stopPropagation();
            holdTriggered = false;
        }
    }, true);
}
function pinMsg(id) { 
    const fd = new FormData(); 
    fd.append('message_id', id); 
    api('/public/api/chat/pin_message.php', 'POST', fd).then(r => {
        lastId = 0; // Force full refresh to update pin indicators
        loadMsgs();
    }); 
    closeAllMenus(); 
}

function goToMessage(id) {
    const el = document.getElementById(`ms-${id}`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.style.animation = 'highlightStaffMsg 2s ease';
    }
}

let fwdMsgData = null, selectedFwd = [];
function initFwd(id, msgB64, type) {
    fwdMsgData = { id, text: decodeURIComponent(escape(atob(msgB64))), type };
    selectedFwd = [];
    const modal = document.getElementById('pfFwdModal');
    modal.classList.remove('hidden');
    modal.classList.add('show');
    const preview = document.getElementById('fwdPreview');
    if (fwdMsgData.text) {
        preview.textContent = fwdMsgData.text;
    } else {
        const labels = { image: '📸 Image', video: '🎥 Video', voice: '🎤 Voice Message' };
        preview.textContent = labels[fwdMsgData.type] || '📸 Attachment';
    }
    const s = document.getElementById('fwdSearch');
    if(s) s.value = '';
    loadFwdList();
    closeAllMenus();
}
function closeFwd() { 
    const modal = document.getElementById('pfFwdModal');
    modal.classList.remove('show');
    modal.classList.add('hidden');
}
let fwdSearchTimer = null;
function debounceFwdSearch(q) {
    clearTimeout(fwdSearchTimer);
    fwdSearchTimer = setTimeout(() => loadFwdList(q), 300);
}
function loadFwdList(q = '') {
    api(`/public/api/chat/list_conversations.php?archived=0&q=${encodeURIComponent(q)}`).then(res => {
        const list = document.getElementById('fwdList');
        if (!res.conversations) {
            list.innerHTML = '<div class="p-8 text-center opacity-30 text-sm">No orders found</div>';
            return;
        }
        list.innerHTML = res.conversations.map(c => {
            const isSel = selectedFwd.includes(c.order_id);
            const name = c.staff_name || 'PrintFlow Team';
            const initial = name[0].toUpperCase();
            const avatarHtml = c.staff_avatar 
                ? `<img src="${resolveProfileUrl(c.staff_avatar)}" onerror="${PROFILE_IMAGE_ONERROR}">` 
                : (name === 'PrintFlow Team' 
                    ? `<img src="${BASE}/public/assets/images/favicon.png" style="width:20px;height:20px;object-fit:contain;opacity:0.8;">`
                    : `<span>${initial}</span>`);

            return `
            <div class="fwd-list-item ${isSel?'selected':''}" onclick="toggleFwdTarget(${c.order_id})">
                <div class="conv-av" style="width:38px;height:38px;background:#f1f5f9; border-radius:12px;">${avatarHtml}</div>
                <div style="flex:1; min-width:0;">
                    <div style="font-size:0.88rem; font-weight:800; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${esc(name)}</div>
                    <div style="font-size:0.75rem; color:var(--pf-dim); font-weight:700; opacity:0.9;">Order #${c.order_id} · ${esc(c.product_name||'Order')}</div>
                </div>
                <div class="fwd-check-circle">${isSel?'<i class="bi bi-check text-black" style="font-size:14px; font-weight:900;"></i>':''}</div>
            </div>`;
        }).join('');
    });
}
function toggleFwdTarget(id) {
    const idx = selectedFwd.indexOf(id);
    if (idx === -1) selectedFwd.push(id); else selectedFwd.splice(idx,1);
    const count = selectedFwd.length;
    document.getElementById('fwdSendBtn').disabled = count === 0;
    document.getElementById('fwdSendBtn').innerHTML = `Send ${count > 0 ? `(${count})` : ''} <i class="bi bi-send-fill" style="margin-left:4px;"></i>`;
    const q = document.getElementById('fwdSearch').value;
    loadFwdList(q);
}
async function doForward() {
    if (!fwdMsgData || !selectedFwd.length) return;
    const btn = document.getElementById('fwdSendBtn');
    btn.disabled = true; btn.textContent = 'Sending...';
    for (const tid of selectedFwd) {
        const fd = new FormData();
        fd.append('order_id', tid);
        fd.append('message_id', fwdMsgData.id);
        await api('/public/api/chat/forward_message.php', 'POST', fd);
    }
    closeFwd(); loadConvs();
}

function handleOrderUpdateClick(orderId) {
    if (!orderId) return;
    openOrderDetails(orderId);
}

function openOrderDetails(id) {
    if (!id) return;
    const modal = document.getElementById('detailsModal');
    const body = document.getElementById('detailsBody');
    modal.classList.add('active');
    body.innerHTML = `
        <div style="grid-column:1/-1; text-align:center; padding:3rem 0;">
            <div style="display:inline-block; width:32px; height:32px; border:3px solid #f1f5f9; border-top-color:#0ea5a5; border-radius:50%; animation:spin .8s linear infinite;"></div>
            <p style="font-size:11px; font-weight:800; color:#94a3b8; text-transform:uppercase; margin-top:1rem; letter-spacing:.1em;">Analyzing Workflow...</p>
        </div>`;
    api(`/public/api/chat/order_details.php?order_id=${id}`).then(data => {
        if (!data.success) {
            body.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:5rem; color:#ef4444; font-weight:800;">Access Denied: ${esc(data.error || 'Unknown')}</div>`;
            return;
        }
        const c = data.customer || {};
        const o = data.order || {};
        const items = data.items || [];
        const actionUrl = o.manage_url || `${BASE}/customer/orders.php?highlight=${o.order_id}`;
        const actionLabel = o.manage_url ? 'MANAGE ORDER' : 'VIEW ORDER';
        const compact = window.matchMedia('(max-width: 768px)').matches;
        body.innerHTML = `
            <div class="details-sidebar" style="gap:1rem; ${compact ? 'border-right:none;border-bottom:1px solid #eef2f7;padding:1rem;' : ''}">
                <div class="pf-mini-card" style="padding:.75rem;">
                    <div class="pf-spec-key" style="margin-bottom:6px; font-size:9px;">Customer Profile</div>
                    <div style="display:flex; align-items:center; gap:.75rem;">
                        <div style="width:52px; height:52px; border-radius:14px; background:#0ea5a5; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:1rem; overflow:hidden; flex-shrink:0;">
                            ${c.profile_picture ? `<img src="${c.profile_picture}" style="width:100%;height:100%;object-fit:cover;" onerror="${PROFILE_IMAGE_ONERROR}">` : esc((c.full_name || '?').charAt(0).toUpperCase())}
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:.85rem; font-weight:900; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${esc(c.full_name || 'Guest')}</div>
                            <div style="font-size:11px; font-weight:700; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${esc(c.email || '')}</div>
                        </div>
                    </div>
                </div>
                <div class="pf-mini-card" style="padding:.75rem;">
                    <div class="pf-spec-key" style="margin-bottom:6px; font-size:9px;">Workflow Status</div>
                    <div style="display:flex; align-items:center; justify-content:space-between; background:#f8fafc; padding:6px 10px; border-radius:8px; border:1px solid #f1f5f9;">
                         <div style="font-size:10px; font-weight:900; color:#1e293b;">${esc(o.status || 'Pending')}</div>
                         <span style="width:10px; height:10px; border-radius:50%; background:${o.status === 'Completed' ? '#10b981' : '#3b82f6'};"></span>
                    </div>
                </div>
                <div class="pf-mini-card" style="padding:.75rem;">
                    <div class="pf-spec-key" style="margin-bottom:6px; font-size:9px;">Payment Summary</div>
                    <div style="display:flex; align-items:center; justify-content:space-between; background:#f8fafc; padding:6px 10px; border-radius:8px; border:1px solid #f1f5f9;">
                         <div style="font-size:10px; font-weight:900; color:#1e293b;">${esc(o.payment_status || 'Unverified')}</div>
                         <span style="width:10px; height:10px; border-radius:50%; background:${o.payment_status === 'Paid' ? '#10b981' : '#f59e0b'};"></span>
                    </div>
                </div>
                <div class="pf-mini-card" style="background:#0f172a; color:#fff; border:none; padding:.75rem; margin-bottom:0;">
                     <div class="pf-spec-key" style="color:#22d3ee; margin-bottom:2px; font-size:9px;">Total</div>
                     <div style="font-size:1.1rem; font-weight:900; line-height:1; margin-bottom:.75rem;">${o.total_amount || 'To be finalized'}</div>
                     <a href="${actionUrl}" style="display:block; text-align:center; background:#0ea5a5; color:#fff; padding:8px; border-radius:10px; font-size:10px; font-weight:900; text-decoration:none;">${actionLabel}</a>
                </div>
            </div>
            <div class="details-main">
                <div class="details-main-heading" style="${compact ? 'padding:0 0 .85rem; border-bottom:1px solid #f1f5f9;' : ''}">Order Details</div>
                <div class="details-items">
                    ${items.length ? items.map(it => {
                        const specs = it.customization || {};
                        const entries = Object.entries(specs).filter(([k, v]) => v && v !== 'null' && typeof v !== 'object' && k !== 'service_type' && k !== 'branch_id');
                        let displayImg = it.design_url || `${BASE}/public/assets/images/services/default.png`;
                        const placement = specs.print_placement || specs.placement || '';
                        if (!it.design_url && placement.includes('Front Center')) displayImg = `${BASE}/public/assets/images/tshirt_replacement/Front Center Print.webp`;
                        if (!it.design_url && placement.includes('Sleeve')) displayImg = `${BASE}/public/assets/images/tshirt_replacement/Sleeve Print.webp`;
                        if (!it.design_url && placement.includes('Upper')) displayImg = `${BASE}/public/assets/images/tshirt_replacement/Back Upper Print.webp`;
                        return `
                        <div class="detail-order-card">
                            <div class="detail-order-top">
                                <div class="detail-order-thumb">
                                    <img src="${displayImg}" alt="${esc(it.product_name || 'Order Item')}" onerror="this.onerror=null; this.src='${BASE}/public/assets/images/services/default.png';">
                                </div>
                                <div class="detail-order-body">
                                    <div class="detail-order-summary">
                                        <div style="min-width:0; flex:1;">
                                            <div class="detail-order-title" title="${esc(it.product_name || 'Order Item')}">${esc(it.product_name || 'Order Item')}</div>
                                            <div class="detail-order-meta" style="margin-top:.65rem;">
                                                <span class="detail-order-chip category">${esc(it.category || 'Service')}</span>
                                                <span class="detail-order-chip">Units: ${it.quantity}</span>
                                            </div>
                                        </div>
                                        <div class="detail-order-price">
                                             <div class="pf-spec-key">Total</div>
                                             <strong>${it.subtotal || 'To be finalized'}</strong>
                                        </div>
                                    </div>
                                    <div class="pf-spec-grid" style="margin-top:0; gap:8px;">
                                        ${entries.map(([k, v]) => `
                                            <div class="pf-spec-box">
                                                <div class="pf-spec-key" style="font-size:8px;">${esc(k.replace(/_/g, ' ').replace('shirt ', ''))}</div>
                                                <div class="pf-spec-val" style="font-size:11px;">${esc(String(v))}</div>
                                            </div>`).join('')}
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    }).join('') : '<div style="text-align:center; padding:4rem; color:#cbd5e1; font-style:italic;">Order details are currently empty.</div>'}
                </div>
            </div>`;
    }).catch(err => {
        body.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:5rem; color:#ef4444; font-weight:800;">System Error: ${esc(err.message)}</div>`;
    });
}

function closeDetailsModal() {
    document.getElementById('detailsModal').classList.remove('active');
}

let activeGalleryTab = 'image';
let sharedMedia = [];

function switchGalleryTab(tab) {
    activeGalleryTab = tab;
    document.getElementById('galTabImg').classList.toggle('active', tab === 'image');
    document.getElementById('galTabVid').classList.toggle('active', tab === 'video');
    renderGallery();
}

function renderGallery() {
    const grid = document.getElementById('galleryGrid');
    if (!grid) return;
    const filtered = sharedMedia.filter(m => m.file_type === activeGalleryTab);
    
    if (filtered.length === 0) {
        grid.innerHTML = `
        <div style="grid-column: span 2; padding:5rem 1rem; text-align:center; color:rgba(0,0,0,0.25);">
            <i class="bi bi-${activeGalleryTab === 'image' ? 'images' : 'play-btn'}" style="font-size:3rem; display:block; margin-bottom:1rem; opacity:0.15;"></i>
            <div style="font-weight:800; font-size:0.9rem;">No shared ${activeGalleryTab}s</div>
            <div style="font-size:0.75rem; opacity:0.6; margin-top:4px; font-weight:600;">Images and videos from this chat appear here.</div>
        </div>`;
        return;
    }
    
    grid.innerHTML = filtered.map(m => {
        const isVid = m.file_type === 'video';
        const url = resolveAppUrl(m.message_file);
        if (isVid) {
            return `<div class="gal-item" onclick="zoomVideo('${url.replace(/'/g, "\\'")}')">
                <video src="${url}#t=0.1" preload="metadata" muted
                    onerror="this.insertAdjacentHTML('afterend', '<div style=\'height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; background:#f1f5f9; color:#94a3b8; font-size:0.7rem;\'><i class=\'bi bi-camera-video-off\' style=\'font-size:1.2rem;\'></i><span>Unavailable</span></div>\'); this.style.display=\'none\';">
                </video>
                <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.15);">
                    <i class="bi bi-play-circle-fill" style="color:#fff; font-size:1.5rem; filter:drop-shadow(0 2px 4px rgba(0,0,0,0.3));"></i>
                </div>
            </div>`;
        }
        return `<div class="gal-item" onclick="zoomImg('${url.replace(/'/g, "\\'")}')">
            <img src="${url}" loading="lazy">
        </div>`;
    }).join('');
}

function openGallery() {
    if (!activeId) return;
    const gallery = document.getElementById('galleryPanel'), grid = document.getElementById('galleryGrid');
    grid.innerHTML = '<div style="grid-column: span 2; padding:3rem; text-align:center;"><i class="bi bi-hourglass-split animate-spin text-2xl text-slate-300"></i></div>';
    gallery.classList.add('show');
    api(`/public/api/chat/fetch_media.php?order_id=${activeId}`).then(res => {
        if (res.success) {
            sharedMedia = res.media || [];
            renderGallery();
        }
    });
}

function closeGallery() { document.getElementById('galleryPanel').classList.remove('show'); }
function toggleArchive() { const fd = new FormData(); fd.append('order_id',activeId); fd.append('archive',isConvArch?0:1); api('/public/api/chat/set_archived.php','POST',fd).then(res=>{ if(res.success) { isConvArch=!isConvArch; updateArchUI(isConvArch); loadConvs(); }}); }
function toggleHMenu(e) { e.stopPropagation(); document.getElementById('hDropdown').classList.toggle('show'); }
function closeAllMenus() { document.querySelectorAll('.react-picker,.more-menu,.h-dropdown').forEach(el=>el.classList.remove('show')); }
if (!window.__pfCustomerChatCloseMenusBound) {
    window.__pfCustomerChatCloseMenusBound = true;
    window.addEventListener('click', () => {
        document.querySelectorAll('.brow').forEach(row => row.classList.remove('has-active-menu'));
        closeAllMenus();
    });
}

function initiateCall(type) {
    if (!activeId) return;
    // Sync activeId for call logging
    if (!window.PFCallState) window.PFCallState = {};
    window.PFCallState.activeId = activeId;
    const fd = new FormData(); fd.append('order_id', activeId);
    api('/public/api/chat/status.php','POST',fd).then(res => {
        if (!res || !res.partner) {
            alert('Staff is unavailable right now.');
            return;
        }
        window.PFCall.startCall(
            res.partner.id,
            'Staff',
            res.partner.name,
            resolveProfileUrl(res.partner.avatar),
            type
        );
    });
}

function esc(s) { if(!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmtTimeAgo(d) { if(!d) return ''; const t=new Date(d.replace(/-/g,'/')), diff=(Date.now()-t)/1000; if(diff<60) return 'now'; if(diff<3600) return Math.floor(diff/60)+'m'; if(diff<86400) return Math.floor(diff/3600)+'h'; return Math.floor(diff/86400)+'d'; }
function fmtShort(d) { if(!d) return ''; if(typeof d==='string' && (d.includes('AM')||d.includes('PM'))) return d; return new Date(d.replace(/-/g,'/')).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}); }

function initCustomerChatPage() {
    if (window.__pfCustomerChatInitialized) return;
    window.__pfCustomerChatInitialized = true;

    initRecordingEvents();

    const input = document.getElementById('customerMsgInput');
    if (input) {
        input.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
            const count = document.getElementById('customerCharCount');
            if (count) count.textContent = this.value.length + '/500';
        });
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMsg();
            }
        });
    }

    loadConvs();
}

function zoomImg(src) {
    const lb = document.getElementById('chatLightbox'), img = document.getElementById('lightboxImg'), vid = document.getElementById('lightboxVideo'), dl = document.getElementById('lightboxDownload');
    img.src = src; dl.href = src; img.style.display = 'block'; vid.style.display = 'none'; lb.style.display = 'flex';
}
function zoomVideo(src) {
    const lb = document.getElementById('chatLightbox'), img = document.getElementById('lightboxImg'), vid = document.getElementById('lightboxVideo'), dl = document.getElementById('lightboxDownload');
    vid.src = src; dl.href = src; img.style.display = 'none'; vid.style.display = 'block'; lb.style.display = 'flex'; vid.play();
}
function closeLightbox() {
    const lb = document.getElementById('chatLightbox'), vid = document.getElementById('lightboxVideo');
    lb.style.display = 'none'; vid.pause(); vid.src = '';
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCustomerChatPage, { once: true });
} else {
    initCustomerChatPage();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
