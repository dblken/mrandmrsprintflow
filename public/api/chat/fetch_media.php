<?php
/**
 * Fetch all shared media for a specific conversation.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/branch_context.php';
require_once __DIR__ . '/../../../includes/ensure_order_messages.php';

// Global Output Buffer to trap notices
ob_start();

header('Content-Type: application/json');

if (!is_logged_in()) {
    ob_end_clean();
    echo json_encode([]);
    exit();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

$user_id = get_user_id();
$user_type = get_user_type();

if (!$order_id) {
    ob_end_clean();
    echo json_encode([]);
    exit();
}

// Security: Check if customer belongs to the order
if ($user_type === 'Customer') {
    $check_sql = "SELECT order_id FROM orders WHERE order_id = ? AND customer_id = ?";
    $check = db_query($check_sql, 'ii', [$order_id, $user_id]);
    if (empty($check)) {
        ob_end_clean();
        echo json_encode([]);
        exit();
    }
} else {
    ob_end_clean();
    ob_start();
    printflow_assert_order_branch_access($order_id);
}

// Fetch all media using proper columns
$sql = "SELECT message_file, file_type 
        FROM order_messages 
        WHERE order_id = ? 
        AND file_type NOT IN ('none', 'text')
        AND message_type != 'voice'
        AND message_file IS NOT NULL
        ORDER BY created_at DESC";
$media = db_query($sql, 'i', [$order_id]);

if ($media === false) {
    ob_end_clean();
    echo json_encode([]);
    exit();
}

function pf_chat_media_public_url(?string $path): string {
    $path = trim((string)$path);
    if ($path === '' || preg_match('#^(https?:|data:)#i', $path)) {
        return $path;
    }

    $path = str_replace('<?php echo $base_path; ?>', '', $path);
    $path = preg_replace('#/+#', '/', $path);
    $base = rtrim(defined('BASE_PATH') ? BASE_PATH : (defined('AUTH_REDIRECT_BASE') ? AUTH_REDIRECT_BASE : '/printflow'), '/');

    if ($base === '' && strpos($path, '/printflow/') === 0) {
        $path = substr($path, strlen('/printflow'));
    }
    if ($base !== '' && strpos($path, $base . '/') === 0) {
        return $path;
    }
    if ($path !== '' && $path[0] === '/') {
        return $base . $path;
    }

    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
}

// Prepend BASE_URL if needed
$results = [];
foreach ($media as $item) {
    $path = pf_chat_media_public_url($item['message_file'] ?? '');
    $results[] = [
        'message_file' => $path,
        'file_type' => $item['file_type']
    ];
}

ob_end_clean();
echo json_encode($results);
