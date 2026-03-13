<?php
/**
 * API Endpoint - Get Active FAQs
 * Returns active FAQs as JSON for the chatbot
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

try {
    // Fetch only activated FAQs
    $faqs = db_query("SELECT faq_id, question, answer FROM faq WHERE status = 'Activated' ORDER BY faq_id ASC");
    
    if (!$faqs) {
        throw new Exception('Failed to fetch FAQs');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $faqs
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to load FAQs'
    ]);
}
?>
