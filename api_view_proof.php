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
];

if (strpos($normalized_file, '/printflow/') === 0) {
    $candidates[] = __DIR__ . substr($normalized_file, strlen('/printflow'));
}
if (strpos($normalized_file, 'uploads/') === 0) {
    $candidates[] = __DIR__ . '/' . $normalized_file;
}
$uploads_pos = stripos($normalized_file, '/uploads/');
if ($uploads_pos !== false) {
    $candidates[] = __DIR__ . substr($normalized_file, $uploads_pos);
}

$filepath = '';
$uploads_root = realpath(__DIR__ . '/uploads');
foreach ($candidates as $candidate) {
    if (!is_string($candidate) || $candidate === '' || !file_exists($candidate)) {
        continue;
    }
    $real = realpath($candidate);
    if ($real === false || $uploads_root === false) {
        continue;
    }
    if (strpos($real, $uploads_root) !== 0) {
        continue;
    }
    $filepath = $real;
    break;
}

if ($filepath === '') {
    http_response_code(404);
    die('File not found');
}

// Check authorization
// 1. Is it Admin or Staff?
$is_staff = isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['Admin', 'Staff']);

// 2. If Customer, do they own the customization associated with this file?
$is_owner = false;
if (!$is_staff && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Customer') {
    $customer_id = $_SESSION['user_id'];
    $check = db_query(
        "SELECT id FROM job_orders 
         WHERE customer_id = ? 
           AND (payment_proof_path = ? OR payment_proof_path LIKE CONCAT('%', ?, '%'))
         LIMIT 1",
        'iss',
        [$customer_id, $basename, $basename]
    );
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
