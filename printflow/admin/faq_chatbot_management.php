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

// Handle FAQ creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_faq']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $question = sanitize($_POST['question']);
    $answer = sanitize($_POST['answer']);
    $status = $_POST['status'];
    
    db_execute("INSERT INTO faq (question, answer, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())",
        'sss', [$question, $answer, $status]);
    
    $success = 'FAQ created successfully!';
}

// Handle FAQ update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_faq']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $faq_id = (int)$_POST['faq_id'];
    $question = sanitize($_POST['question']);
    $answer = sanitize($_POST['answer']);
    $status = $_POST['status'];
    
    db_execute("UPDATE faq SET question = ?, answer = ?, status = ?, updated_at = NOW() WHERE faq_id = ?",
        'sssi', [$question, $answer, $status, $faq_id]);
    
    $success = 'FAQ updated successfully!';
}

// Handle FAQ delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_faq']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $faq_id = (int)$_POST['faq_id'];
    db_execute("DELETE FROM faq WHERE faq_id = ?", 'i', [$faq_id]);
    $success = 'FAQ deleted successfully!';
}

// Get all FAQs
$faqs = db_query("SELECT * FROM faq ORDER BY created_at DESC");

$page_title = 'FAQ Management - Admin';
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
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">FAQ & Chatbot</h1>
            <button 
                @click="$dispatch('open-faq-modal')"
                class="btn-primary"
            >
                + Add New FAQ
            </button>
        </header>

        <main>
            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- FAQs List -->
            <div class="space-y-4">
                <?php if (empty($faqs)): ?>
                    <div class="card text-center py-12">
                        <p class="text-gray-600">No FAQs yet. Create your first FAQ!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($faqs as $faq): ?>
                        <div class="card">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="font-bold text-lg mb-2"><?php echo htmlspecialchars($faq['question']); ?></h3>
                                    <p class="text-gray-700 mb-3"><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></p>
                                    <div class="flex items-center gap-4 text-sm text-gray-600">
                                        <span><?php echo status_badge($faq['status'], 'order'); ?></span>
                                        <span>Updated: <?php echo format_date($faq['updated_at']); ?></span>
                                    </div>
                                </div>
                                <div class="ml-4 space-x-2">
                                    <button class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">Edit</button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this FAQ?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="faq_id" value="<?php echo $faq['faq_id']; ?>">
                                        <button type="submit" name="delete_faq" class="text-red-600 hover:text-red-700 text-sm font-medium">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Add/Edit FAQ Modal -->
<div x-data="{ showModal: false, mode: 'create' }" 
     @open-faq-modal.window="showModal = true; mode = $event.detail.mode"
     x-show="showModal"
     class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
     style="display: none;">
    <div class="bg-white rounded-lg p-6 max-w-2xl w-full mx-4" @click.away="showModal = false">
        <h3 class="text-xl font-bold mb-4">Add New FAQ</h3>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="create_faq" value="1">
            
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">Question *</label>
                <input type="text" name="question" class="input-field" required>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">Answer *</label>
                <textarea name="answer" rows="4" class="input-field" required></textarea>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">Status *</label>
                <select name="status" class="input-field" required>
                    <option value="Activated">Activated (Public)</option>
                    <option value="Deactivated">Deactivated (Hidden)</option>
                </select>
            </div>
            
            <div class="flex gap-2">
                <button type="button" @click="showModal = false" class="btn-secondary flex-1">Cancel</button>
                <button type="submit" class="btn-primary flex-1">Create FAQ</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
