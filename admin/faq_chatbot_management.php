<?php
/**
 * Admin FAQ/Chatbot Management
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$error = '';
$success = '';

// Auto-create table if not exists
db_execute("CREATE TABLE IF NOT EXISTS chatbot_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) DEFAULT 'Guest',
    customer_email VARCHAR(150) DEFAULT NULL,
    question TEXT NOT NULL,
    admin_reply TEXT DEFAULT NULL,
    status ENUM('pending','answered') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    replied_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Success from redirect (inquiry reply/delete)
if (isset($_GET['replied'])) $success = 'Reply sent successfully!';
if (isset($_GET['deleted'])) $success = 'Inquiry deleted!';

// Handle FAQ creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_faq']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $question = sanitize($_POST['question']);
    $answer   = sanitize($_POST['answer']);
    $status   = $_POST['status'];
    db_execute("INSERT INTO faq (question, answer, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())", 'sss', [$question, $answer, $status]);
    $success = 'FAQ created successfully!';
}

// Handle FAQ update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_faq']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $faq_id   = (int)$_POST['faq_id'];
    $question = sanitize($_POST['question']);
    $answer   = sanitize($_POST['answer']);
    $status   = $_POST['status'];
    db_execute("UPDATE faq SET question = ?, answer = ?, status = ?, updated_at = NOW() WHERE faq_id = ?", 'sssi', [$question, $answer, $status, $faq_id]);
    $success = 'FAQ updated successfully!';
}

// Handle FAQ delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_faq']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $faq_id = (int)$_POST['faq_id'];
    db_execute("DELETE FROM faq WHERE faq_id = ?", 'i', [$faq_id]);
    $success = 'FAQ deleted successfully!';
}

// Handle inquiry reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_inquiry']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $inq_id = (int)$_POST['inquiry_id'];
    $reply  = sanitize($_POST['admin_reply']);
    if (!empty($reply)) {
        db_execute("UPDATE chatbot_inquiries SET admin_reply = ?, status = 'answered', replied_at = NOW() WHERE id = ?", 'si', [$reply, $inq_id]);
        header('Location: faq_chatbot_management.php?tab=inquiries&replied=1');
        exit;
    }
}

// Handle inquiry delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_inquiry']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $inq_id = (int)$_POST['inquiry_id'];
    db_execute("DELETE FROM chatbot_inquiries WHERE id = ?", 'i', [$inq_id]);
    header('Location: faq_chatbot_management.php?tab=inquiries&deleted=1');
    exit;
}

$faqs = db_query("SELECT * FROM faq ORDER BY created_at DESC");
$stat_total      = count($faqs);
$stat_active     = count(array_filter($faqs, fn($f) => $f['status'] === 'Activated'));
$stat_inactive   = $stat_total - $stat_active;

// Fetch chatbot inquiries
$inquiries = db_query("SELECT * FROM chatbot_inquiries ORDER BY created_at DESC") ?: [];
$inq_pending  = count(array_filter($inquiries, fn($i) => $i['status'] === 'pending'));
$inq_answered = count(array_filter($inquiries, fn($i) => $i['status'] === 'answered'));

$active_tab = $_GET['tab'] ?? 'responses';
$page_title = 'Chatbot Management - Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        /* Tab Navigation */
        .cb-tabs { display:flex; gap:0; margin-bottom:24px; border-bottom:2px solid #e5e7eb; }
        .cb-tab { padding:12px 24px; font-size:14px; font-weight:600; color:#6b7280; background:none; border:none; cursor:pointer; position:relative; transition:all .2s; }
        .cb-tab:hover { color:#4f46e5; }
        .cb-tab.active { color:#4f46e5; }
        .cb-tab.active::after { content:''; position:absolute; bottom:-2px; left:0; right:0; height:2px; background:#4f46e5; border-radius:2px 2px 0 0; }
        .cb-tab .tab-badge { display:inline-flex; align-items:center; justify-content:center; min-width:20px; height:20px; padding:0 6px; border-radius:10px; font-size:11px; font-weight:700; margin-left:6px; }
        .cb-tab .tab-badge.pending { background:#fef3c7; color:#92400e; }
        .cb-tab-content { display:none; }
        .cb-tab-content.active { display:block; }

        /* KPI Cards */
        .kpi-row { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; }
        @media(max-width:768px) { .kpi-row { grid-template-columns:1fr 1fr; } }
        .kpi-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:18px 20px; position:relative; overflow:hidden; }
        .kpi-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
        .kpi-card.indigo::before { background:linear-gradient(90deg,#6366f1,#818cf8); }
        .kpi-card.emerald::before { background:linear-gradient(90deg,#059669,#34d399); }
        .kpi-card.rose::before { background:linear-gradient(90deg,#e11d48,#fb7185); }
        .kpi-card.amber::before { background:linear-gradient(90deg,#f59e0b,#fbbf24); }
        .kpi-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:#9ca3af; margin-bottom:6px; }
        .kpi-value { font-size:26px; font-weight:800; color:#1f2937; }
        .kpi-sub { font-size:12px; color:#6b7280; margin-top:4px; }

        /* FAQ Card */
        .faq-item { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px 24px; margin-bottom:12px; display:flex; align-items:flex-start; justify-content:space-between; gap:16px; transition:box-shadow .15s; }
        .faq-item:hover { box-shadow:0 2px 8px rgba(0,0,0,0.07); }
        .faq-question { font-size:15px; font-weight:600; color:#111827; margin-bottom:6px; }
        .faq-answer { font-size:14px; color:#6b7280; line-height:1.6; margin-bottom:10px; }
        .faq-meta { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .faq-badge { display:inline-block; padding:2px 10px; border-radius:20px; font-size:12px; font-weight:600; }
        .faq-badge.active { background:#dcfce7; color:#166534; }
        .faq-badge.inactive { background:#fee2e2; color:#991b1b; }
        .faq-actions { display:flex; gap:8px; flex-shrink:0; }
        .btn-edit { padding:6px 14px; border:1.5px solid #6366f1; color:#6366f1; background:transparent; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; transition:all .18s; }
        .btn-edit:hover { background:#6366f1; color:#fff; }
        .btn-del { padding:6px 14px; border:1.5px solid #e11d48; color:#e11d48; background:transparent; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; transition:all .18s; }
        .btn-del:hover { background:#e11d48; color:#fff; }

        /* Inquiry Card */
        .inq-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px 24px; margin-bottom:12px; transition:box-shadow .15s; }
        .inq-card:hover { box-shadow:0 2px 8px rgba(0,0,0,0.07); }
        .inq-card.pending { border-left:3px solid #f59e0b; }
        .inq-card.answered { border-left:3px solid #059669; }
        .inq-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:10px; }
        .inq-status { display:inline-block; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:700; }
        .inq-status.pending { background:#fef3c7; color:#92400e; }
        .inq-status.answered { background:#dcfce7; color:#166534; }
        .inq-question { font-size:15px; font-weight:600; color:#111827; line-height:1.5; margin-bottom:6px; }
        .inq-meta { font-size:12px; color:#9ca3af; margin-bottom:12px; }
        .inq-reply-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; margin-top:10px; }
        .inq-reply-label { font-size:11px; font-weight:700; color:#059669; text-transform:uppercase; letter-spacing:.4px; margin-bottom:6px; }
        .inq-reply-text { font-size:14px; color:#374151; line-height:1.6; }

        /* Modal */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9900; align-items:center; justify-content:center; padding:16px; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:#fff; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,0.15); width:100%; max-width:560px; max-height:90vh; overflow-y:auto; }
        .modal-hdr { display:flex; align-items:center; justify-content:space-between; padding:20px 24px 16px; border-bottom:1px solid #f3f4f6; }
        .modal-hdr h2 { font-size:16px; font-weight:700; color:#111827; margin:0; }
        .modal-hdr button { background:none; border:none; font-size:20px; color:#9ca3af; cursor:pointer; }
        .modal-hdr button:hover { color:#374151; }
        .modal-bdy { padding:20px 24px; }
        .f-group { margin-bottom:16px; }
        .f-group label { display:block; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px; }
        .f-group input, .f-group select, .f-group textarea { width:100%; padding:9px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; color:#111827; background:#fafafa; outline:none; transition:border-color .15s; box-sizing:border-box; }
        .f-group input:focus, .f-group select:focus, .f-group textarea:focus { border-color:#6366f1; background:#fff; }
        .f-group textarea { resize:vertical; min-height:100px; }
        .modal-ftr { display:flex; justify-content:flex-end; gap:10px; padding:16px 24px; border-top:1px solid #f3f4f6; }
        .btn-cancel { padding:9px 18px; border:1px solid #e5e7eb; background:#fff; border-radius:8px; font-size:14px; font-weight:600; color:#374151; cursor:pointer; }
        .btn-submit { padding:9px 22px; border:none; border-radius:8px; background:#4f46e5; color:#fff; font-size:14px; font-weight:600; cursor:pointer; }
        .btn-submit:hover { opacity:.88; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <header>
            <h1 class="page-title">Chatbot Management</h1>
            <button id="btn-add-faq" class="btn-primary" style="<?php echo $active_tab === 'inquiries' ? 'display:none;' : ''; ?>">+ Add Response</button>
        </header>

        <main>
            <?php if ($success): ?>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <div class="cb-tabs">
                <a href="?tab=responses" class="cb-tab <?php echo $active_tab === 'responses' ? 'active' : ''; ?>">Responses</a>
                <a href="?tab=inquiries" class="cb-tab <?php echo $active_tab === 'inquiries' ? 'active' : ''; ?>">
                    Customer Inquiries
                    <?php if ($inq_pending > 0): ?>
                        <span class="tab-badge pending"><?php echo $inq_pending; ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- ═══════ TAB 1: RESPONSES ═══════ -->
            <div class="cb-tab-content <?php echo $active_tab === 'responses' ? 'active' : ''; ?>">
                <!-- KPI Row -->
                <div class="kpi-row">
                    <div class="kpi-card indigo">
                        <div class="kpi-label">Total Responses</div>
                        <div class="kpi-value"><?php echo $stat_total; ?></div>
                        <div class="kpi-sub">All entries</div>
                    </div>
                    <div class="kpi-card emerald">
                        <div class="kpi-label">Active / Public</div>
                        <div class="kpi-value"><?php echo $stat_active; ?></div>
                        <div class="kpi-sub">Shown to customers</div>
                    </div>
                    <div class="kpi-card rose">
                        <div class="kpi-label">Inactive / Hidden</div>
                        <div class="kpi-value"><?php echo $stat_inactive; ?></div>
                        <div class="kpi-sub">Not visible</div>
                    </div>
                </div>

                <!-- FAQ List -->
                <?php if (empty($faqs)): ?>
                    <div class="card" style="text-align:center;padding:48px 24px;color:#9ca3af;">
                        <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 12px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p style="font-size:15px;font-weight:600;margin-bottom:4px;">No responses yet</p>
                        <p style="font-size:13px;">Add your first chatbot response to help customers get answers quickly.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($faqs as $faq): ?>
                        <div class="faq-item">
                            <div style="flex:1;">
                                <div class="faq-question"><?php echo htmlspecialchars($faq['question']); ?></div>
                                <div class="faq-answer"><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></div>
                                <div class="faq-meta">
                                    <span class="faq-badge <?php echo $faq['status'] === 'Activated' ? 'active' : 'inactive'; ?>">
                                        <?php echo $faq['status'] === 'Activated' ? 'Public' : 'Hidden'; ?>
                                    </span>
                                    <span style="font-size:12px;color:#9ca3af;">Updated <?php echo format_date($faq['updated_at']); ?></span>
                                </div>
                            </div>
                            <div class="faq-actions">
                                <button class="btn-edit" onclick="openEdit(<?php echo $faq['faq_id']; ?>, <?php echo htmlspecialchars(json_encode($faq['question'])); ?>, <?php echo htmlspecialchars(json_encode($faq['answer'])); ?>, '<?php echo $faq['status']; ?>')">Edit</button>
                                <form method="POST" onsubmit="return confirm('Delete this response?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="faq_id" value="<?php echo $faq['faq_id']; ?>">
                                    <button type="submit" name="delete_faq" class="btn-del">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- ═══════ TAB 2: CUSTOMER INQUIRIES ═══════ -->
            <div class="cb-tab-content <?php echo $active_tab === 'inquiries' ? 'active' : ''; ?>">
                <!-- KPI Row -->
                <div class="kpi-row" style="grid-template-columns:repeat(3,1fr);">
                    <div class="kpi-card amber">
                        <div class="kpi-label">Pending</div>
                        <div class="kpi-value"><?php echo $inq_pending; ?></div>
                        <div class="kpi-sub">Awaiting reply</div>
                    </div>
                    <div class="kpi-card emerald">
                        <div class="kpi-label">Answered</div>
                        <div class="kpi-value"><?php echo $inq_answered; ?></div>
                        <div class="kpi-sub">Replied</div>
                    </div>
                    <div class="kpi-card indigo">
                        <div class="kpi-label">Total Inquiries</div>
                        <div class="kpi-value"><?php echo count($inquiries); ?></div>
                        <div class="kpi-sub">All time</div>
                    </div>
                </div>

                <?php if (empty($inquiries)): ?>
                    <div class="card" style="text-align:center;padding:48px 24px;color:#9ca3af;">
                        <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 12px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-2.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                        <p style="font-size:15px;font-weight:600;margin-bottom:4px;">No inquiries yet</p>
                        <p style="font-size:13px;">Customer questions from the chatbot will appear here.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($inquiries as $inq): ?>
                        <div class="inq-card <?php echo $inq['status']; ?>">
                            <div class="inq-header">
                                <div>
                                    <span class="inq-status <?php echo $inq['status']; ?>">
                                        <?php echo $inq['status'] === 'pending' ? '⏳ Pending' : '✅ Answered'; ?>
                                    </span>
                                </div>
                                <div style="display:flex;gap:8px;align-items:center;">
                                    <span class="inq-meta" style="margin:0;"><?php echo date('M j, Y g:ia', strtotime($inq['created_at'])); ?></span>
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this inquiry?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="inquiry_id" value="<?php echo $inq['id']; ?>">
                                        <input type="hidden" name="tab" value="inquiries">
                                        <button type="submit" name="delete_inquiry" class="btn-del" style="padding:4px 10px;font-size:11px;">✕</button>
                                    </form>
                                </div>
                            </div>
                            <div class="inq-question">"<?php echo htmlspecialchars($inq['question']); ?>"</div>
                            <div class="inq-meta">
                                From: <?php echo htmlspecialchars($inq['customer_name'] ?: 'Guest'); ?>
                                <?php if (!empty($inq['customer_email'])): ?>
                                    • <?php echo htmlspecialchars($inq['customer_email']); ?>
                                <?php endif; ?>
                            </div>

                            <?php if ($inq['status'] === 'answered'): ?>
                                <div class="inq-reply-box">
                                    <div class="inq-reply-label">✅ Admin Reply</div>
                                    <div class="inq-reply-text"><?php echo nl2br(htmlspecialchars($inq['admin_reply'])); ?></div>
                                    <div style="font-size:11px;color:#9ca3af;margin-top:6px;">Replied <?php echo date('M j, Y g:ia', strtotime($inq['replied_at'])); ?></div>
                                </div>
                            <?php else: ?>
                                <form method="POST" style="margin-top:10px;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="reply_inquiry" value="1">
                                    <input type="hidden" name="inquiry_id" value="<?php echo $inq['id']; ?>">
                                    <div style="display:flex;gap:8px;align-items:flex-start;">
                                        <textarea name="admin_reply" required placeholder="Type your reply..." style="flex:1;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;min-height:60px;resize:vertical;outline:none;font-family:inherit;" onfocus="this.style.borderColor='#4f46e5'"></textarea>
                                        <button type="submit" class="btn-submit" style="padding:10px 18px;white-space:nowrap;">Send Reply</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Add FAQ Modal -->
<div id="modal-add" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-hdr">
            <h2>Add New Response</h2>
            <button onclick="document.getElementById('modal-add').classList.remove('open')">&times;</button>
        </div>
        <div class="modal-bdy">
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="create_faq" value="1">
                <div class="f-group">
                    <label>Question *</label>
                    <input type="text" name="question" required placeholder="e.g. What are your operating hours?">
                </div>
                <div class="f-group">
                    <label>Answer *</label>
                    <textarea name="answer" required placeholder="Write the answer here..."></textarea>
                </div>
                <div class="f-group">
                    <label>Visibility</label>
                    <select name="status">
                        <option value="Activated">Public (Active)</option>
                        <option value="Deactivated">Hidden (Inactive)</option>
                    </select>
                </div>
                <div class="modal-ftr">
                    <button type="button" class="btn-cancel" onclick="document.getElementById('modal-add').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn-submit">Create Response</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit FAQ Modal -->
<div id="modal-edit" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-hdr">
            <h2>Edit Response</h2>
            <button onclick="document.getElementById('modal-edit').classList.remove('open')">&times;</button>
        </div>
        <div class="modal-bdy">
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="update_faq" value="1">
                <input type="hidden" name="faq_id" id="edit-faq-id">
                <div class="f-group">
                    <label>Question *</label>
                    <input type="text" name="question" id="edit-question" required>
                </div>
                <div class="f-group">
                    <label>Answer *</label>
                    <textarea name="answer" id="edit-answer" required></textarea>
                </div>
                <div class="f-group">
                    <label>Visibility</label>
                    <select name="status" id="edit-status">
                        <option value="Activated">Public (Active)</option>
                        <option value="Deactivated">Hidden (Inactive)</option>
                    </select>
                </div>
                <div class="modal-ftr">
                    <button type="button" class="btn-cancel" onclick="document.getElementById('modal-edit').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn-submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-add-faq').addEventListener('click', () => {
    document.getElementById('modal-add').classList.add('open');
});

function openEdit(id, question, answer, status) {
    document.getElementById('edit-faq-id').value = id;
    document.getElementById('edit-question').value = question;
    document.getElementById('edit-answer').value = answer;
    document.getElementById('edit-status').value = status;
    document.getElementById('modal-edit').classList.add('open');
}

// Close modals on backdrop click
['modal-add','modal-edit'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('open');
    });
});
</script>
</body>
</html>
