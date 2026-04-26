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

$file = rawurldecode((string)($_GET['file'] ?? ''));
$normalized_file = str_replace('\\', '/', $file);
$basename = basename($normalized_file);

if (empty($basename)) {
    http_response_code(400);
    die('Bad Request');
}

// Resolve candidate locations safely.
$candidates = [
    __DIR__ . '/uploads/secure_payments/' . $basename,
    __DIR__ . '/uploads/payments/' . $basename,
    __DIR__ . '/public/assets/uploads/payments/' . $basename,
    __DIR__ . '/public/assets/uploads/secure_payments/' . $basename,
];

// If the file path is provided as a relative or absolute-looking path, try resolving it.
if (strpos($normalized_file, '/printflow/') === 0) {
    $sub = substr($normalized_file, strlen('/printflow'));
    $candidates[] = __DIR__ . $sub;
    $candidates[] = __DIR__ . '/public/assets' . $sub;
}

if (strpos($normalized_file, 'uploads/') === 0) {
    $candidates[] = __DIR__ . '/' . $normalized_file;
    $candidates[] = __DIR__ . '/public/assets/' . $normalized_file;
}

$uploads_pos = stripos($normalized_file, '/uploads/');
if ($uploads_pos !== false) {
    $sub = substr($normalized_file, $uploads_pos);
    $candidates[] = __DIR__ . $sub;
    $candidates[] = __DIR__ . '/public/assets' . $sub;
}

$filepath = '';
// Security: Ensure the file is within an 'uploads' directory.
foreach ($candidates as $candidate) {
    if (!is_string($candidate) || $candidate === '' || !file_exists($candidate)) {
        continue;
    }
    
    $real = realpath($candidate);
    if ($real === false) continue;
    
    $real_n = str_replace('\\', '/', strtolower($real));
    
    // Check if the path contains '/uploads/' to ensure it's in an allowed directory.
    // This is a more resilient check than comparing against a single uploads root.
    if (strpos($real_n, '/uploads/') !== false) {
        $filepath = $real;
        break;
    }
}

if ($filepath === '') {
    http_response_code(404);
    die('File not found');
}

// Check authorization
// 1. Staff-facing roles (customizations / orders)
$is_staff = isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['Admin', 'Staff', 'Manager'], true);

// 2. Customer: job_orders or store orders payment_proof
$is_owner = false;
if (!$is_staff && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Customer') {
    $customer_id = (int)$_SESSION['user_id'];
    $check = db_query(
        "SELECT id FROM job_orders 
         WHERE customer_id = ? 
           AND (payment_proof_path = ? OR payment_proof_path LIKE CONCAT('%', ?, '%'))
         LIMIT 1",
        'iss',
        [$customer_id, $normalized_file, $basename]
    );
    if (!empty($check)) {
        $is_owner = true;
    }
    if (!$is_owner) {
        $check_o = db_query(
            "SELECT order_id FROM orders 
             WHERE customer_id = ? 
               AND (payment_proof = ? OR payment_proof LIKE CONCAT('%', ?, '%'))
             LIMIT 1",
            'iss',
            [$customer_id, $normalized_file, $basename]
        );
        if (!empty($check_o)) {
            $is_owner = true;
        }
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
