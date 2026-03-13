<?php
/**
 * Add default FAQs to the database if they don't exist
 * Run this once to seed the FAQ table with PrintFlow-specific FAQs
 */

require_once __DIR__ . '/includes/db.php';

$default_faqs = [
    [
        'question' => 'What is PrintFlow?',
        'answer' => 'PrintFlow is your trusted online printing shop offering high-quality custom printing services including tarpaulins, apparel, stickers, signage, and more. We deliver professional results with fast turnaround times.'
    ],
    [
        'question' => 'Where are you located?',
        'answer' => 'We are located in the heart of the business district. For exact directions and directions to our pickup point, please visit our About page or contact us directly through the contact form.'
    ],
    [
        'question' => 'How can I place an order?',
        'answer' => 'To place an order, simply sign up for a free account, browse our products, select your items, upload your design or choose from our templates, and proceed to checkout. Our easy-to-use interface guides you through each step.'
    ],
    [
        'question' => 'Do you accept custom designs?',
        'answer' => 'Yes! We welcome custom designs. You can upload your own design files in PNG, JPG, or PDF format when placing an order. Our design team can also assist with modifications if needed.'
    ],
    [
        'question' => 'What are your payment options?',
        'answer' => 'We accept multiple payment methods including Cash (at pickup), GCash, Maya, and Credit Card (Visa/Mastercard). Choose the option that works best for you during checkout.'
    ],
    [
        'question' => 'What are the minimum order quantities?',
        'answer' => 'Minimum order quantities vary by product. Most items can be ordered as singles, while bulk orders qualify for special discounts. Check individual product pages for specific minimums.'
    ],
    [
        'question' => 'How fast can you print my order?',
        'answer' => 'Most orders are completed within 24-48 business hours. Rush orders may be available for a small additional fee. The exact timeline depends on your product selection and design complexity.'
    ],
    [
        'question' => 'Do you offer delivery?',
        'answer' => 'Currently, we offer convenient pickup at our location. Delivery options may be available for bulk orders. Contact our customer support team to discuss delivery arrangements.'
    ],
    [
        'question' => 'Can I track my order?',
        'answer' => 'Yes! Once you place an order, you can track its status in real-time through your customer dashboard. You\'ll receive notifications at each stage of production.'
    ],
    [
        'question' => 'What if I\'m not satisfied with my order?',
        'answer' => 'We guarantee customer satisfaction! If there\'s an issue with your order due to our error, we\'ll reprint it for free. Contact our support team within 7 days of pickup.'
    ],
    [
        'question' => 'Are your materials eco-friendly?',
        'answer' => 'We use high-quality, durable materials designed to last. We\'re committed to sustainability and offer eco-conscious options for many of our products.'
    ],
    [
        'question' => 'Do you offer bulk or corporate discounts?',
        'answer' => 'Absolutely! We offer competitive pricing for bulk orders and corporate clients. Contact our sales team for a custom quote on large projects.'
    ]
];

try {
    // Check if FAQs already exist
    $existing = db_query("SELECT COUNT(*) as count FROM faq");
    
    if ($existing && count($existing) > 0 && $existing[0]['count'] > 2) {
        echo "Default FAQs already exist in the database. Skipping insertion.";
        exit;
    }

    // Insert default FAQs
    $inserted = 0;
    foreach ($default_faqs as $faq) {
        $result = db_execute(
            "INSERT INTO faq (question, answer, status, created_at, updated_at) VALUES (?, ?, 'Activated', NOW(), NOW())",
            'ss',
            [$faq['question'], $faq['answer']]
        );
        if ($result) $inserted++;
    }

    echo "Successfully inserted $inserted default FAQs into the database.";
} catch (Exception $e) {
    echo "Error inserting FAQs: " . $e->getMessage();
}
?>
