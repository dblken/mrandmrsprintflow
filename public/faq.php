<?php
/**
 * FAQ Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Get all activated FAQs
$faqs = db_query("SELECT * FROM faq WHERE status = 'Activated' ORDER BY faq_id ASC");

$page_title = 'Frequently Asked Questions - PrintFlow';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Remove explicit nav-header include, as header.php already provides it for non-landing pages -->

<style>
    body { background-color: #ffffff; color: #1f2937; }
    
    .faq-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 1rem;
        transition: all 0.3s ease;
        overflow: hidden;
    }
    .faq-card:hover {
        border-color: #53C5E0;
        box-shadow: 0 10px 15px -3px rgba(83, 197, 224, 0.1);
    }
    .faq-card.open {
        border-color: #53C5E0;
        box-shadow: 0 4px 6px -1px rgba(83, 197, 224, 0.1);
    }
    .faq-header {
        padding: 1.5rem;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        user-select: none;
    }
    .faq-icon {
        width: 24px;
        height: 24px;
        color: #9ca3af;
        transition: transform 0.3s, color 0.3s;
    }
    .faq-card:hover .faq-icon {
        color: #53C5E0;
    }
    .faq-card.open .faq-icon {
        transform: rotate(180deg);
        color: #53C5E0;
    }
    .faq-body {
        padding: 0 1.5rem 1.5rem 1.5rem;
        color: #6b7280;
        line-height: 1.6;
        border-top: 1px solid #f3f4f6;
    }
    
    .btn-theme-primary {
        background-color: #53C5E0 !important;
        color: white !important;
        box-shadow: 0 4px 14px 0 rgba(83, 197, 224, 0.39) !important;
        transition: all 0.2s ease !important;
        border: none !important;
    }
    .btn-theme-primary:hover {
        background-color: #32a1c4 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 6px 20px rgba(83, 197, 224, 0.23) !important;
    }
    
    .support-card {
        background: #f0fbfd;
        border: 1px solid #d5f1f6;
        border-radius: 1rem;
    }
</style>

<div class="min-h-screen py-12">
    <div class="container mx-auto px-4">
        <div class="max-w-3xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-12">
                <h1 class="text-4xl font-extrabold text-gray-900 mb-4 tracking-tight">Frequently Asked Questions</h1>
                <p class="text-lg text-gray-500 max-w-xl mx-auto">Find quick answers to common questions about our printing services, shipping, and ordering process.</p>
            </div>

            <!-- FAQ List using Alpine.js -->
            <div class="space-y-4">
                <?php if (empty($faqs)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-2xl border border-gray-100">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <p class="text-xl font-semibold text-gray-700">No FAQs available yet.</p>
                        <p class="text-gray-500 mt-2">Please check back later.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($faqs as $index => $faq): ?>
                        <div class="faq-card" x-data="{ open: false }" :class="{ 'open': open }">
                            <div class="faq-header" @click="open = !open">
                                <h3 class="text-lg font-bold text-gray-900 pr-4">
                                    <?php echo htmlspecialchars($faq['question']); ?>
                                </h3>
                                <svg class="faq-icon flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                            
                            <div x-show="open" x-collapse x-cloak>
                                <div class="faq-body pt-4">
                                    <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Contact Section -->
            <div class="support-card mt-12 p-8 text-center shadow-sm">
                <div class="w-16 h-16 mx-auto bg-white rounded-full flex items-center justify-center mb-6 shadow-sm text-primary-500">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-3">Still have questions?</h3>
                <p class="text-gray-600 mb-8 max-w-md mx-auto text-lg">Can't find the answer you're looking for? Please contact our friendly support team.</p>
                <a href="mailto:support@printflow.com" class="btn btn-theme-primary px-8 py-3 rounded-xl font-bold inline-block">Contact Support Team</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
