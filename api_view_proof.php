<?php
/**
 * Protected Payment Proof Viewer
 * Serves files from outside direct web access
 */

require_once __DIR__ . '/includes/auth.php';

// Must be logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

$file = $_GET['file'] ?? '';
// Basic traversal protection
$file = basename($file);

if (empty($file)) {
    http_response_code(400);
    die('Bad Request');
}

// Ensure the file exists
$filepath = __DIR__ . '/uploads/secure_payments/' . $file;
if (!file_exists($filepath)) {
    http_response_code(404);
    die('File not found');
}

// Check authorization
// 1. Is it Admin or Staff?
$is_staff = isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['Admin', 'Staff']);

// 2. If Customer, do they own the job order associated with this file?
$is_owner = false;
if (!$is_staff && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Customer') {
    $customer_id = $_SESSION['user_id'];
    $check = db_query("SELECT id FROM job_orders WHERE customer_id = ? AND payment_proof_path = ? LIMIT 1", 'is', [$customer_id, $file]);
    if (!empty($check)) {
        $is_owner = true;
    }
}

if (!$is_staff && !$is_owner) {
    http_response_code(403);
    die('Forbidden');
}

// Serve the file
$mime = mime_content_type($filepath);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filepath));
// Force cache disable for dynamic checks
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

readfile($filepath);
exit;
