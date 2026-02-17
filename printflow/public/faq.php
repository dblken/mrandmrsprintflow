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

<div class="min-h-screen bg-gray-50 py-8">
    <div class="container mx-auto px-4">
        <div class="max-w-3xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-gray-900 mb-2">Frequently Asked Questions</h1>
                <p class="text-gray-600">Find answers to common questions about our services</p>
            </div>

            <!-- FAQ List -->
            <div class="space-y-4">
                <?php if (empty($faqs)): ?>
                    <div class="card text-center py-8">
                        <p class="text-gray-600">No FAQs available at the moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($faqs as $faq): ?>
                        <div class="card" x-data="{ open: false }">
                            <div class="flex items-start justify-between cursor-pointer" @click="open = !open">
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                        <?php echo htmlspecialchars($faq['question']); ?>
                                    </h3>
                                </div>
                                <svg class="w-6 h-6 text-gray-400 flex-shrink-0 ml-4 transition-transform" :class="{ 'transform rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                            
                            <div x-show="open" x-collapse class="mt-4 pt-4 border-t border-gray-200">
                                <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Contact Section -->
            <div class="card mt-8 bg-indigo-50 border border-indigo-200">
                <div class="text-center">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Still have questions?</h3>
                    <p class="text-gray-600 mb-4">Can't find the answer you're looking for? Please chat with our  team.</p>
                    <a href="mailto:support@printflow.com" class="btn-primary">Contact Support</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
