<?php
/**
 * Fetch all shared media for a specific conversation.
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/branch_context.php';
require_once __DIR__ . '/../../../includes/ensure_chat_schema.php';

// Global Output Buffer to trap notices
ob_start();

header('Content-Type: application/json');

if (!is_logged_in()) {
    ob_end_clean();
    echo json_encode(['success' => false, 'media' => []]);
    exit();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$user_id = get_user_id();
$user_type = get_user_type();

if (!$order_id) {
    ob_end_clean();
    echo json_encode(['success' => false, 'media' => []]);
    exit();
}

// Security: Check if customer belongs to the order
if ($user_type === 'Customer') {
    $check_sql = "SELECT order_id FROM orders WHERE order_id = ? AND customer_id = ?";
    $check = db_query($check_sql, 'ii', [$order_id, $user_id]);
    if (empty($check)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'media' => []]);
        exit();
    }
} else {
    ob_end_clean();
    ob_start();
    printflow_assert_order_branch_access($order_id);
}

// Fetch all media using proper columns
$sql = "SELECT COALESCE(message_file, image_path, file_path) as media_path, file_type, message_type
        FROM order_messages 
        WHERE order_id = ? 
        AND (message_file IS NOT NULL OR image_path IS NOT NULL OR file_path IS NOT NULL)
        AND message_type != 'voice'
        ORDER BY created_at DESC";
$media = db_query($sql, 'i', [$order_id]);

if ($media === false) {
    ob_end_clean();
    echo json_encode(['success' => true, 'media' => []]);
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
    $f_type = strtolower($item['file_type'] ?? '');
    $m_type = strtolower($item['message_type'] ?? '');
    
    // STRICT VOICE FILTER: Never show voice messages in media gallery
    if ($f_type === 'voice' || $m_type === 'voice') continue;

    $path = $item['media_path'] ?? '';
    if (!$path) continue;
    
    // Clean path from query strings or legacy tags
    $clean_path = explode('?', $path)[0];
    $ext = strtolower(pathinfo($clean_path, PATHINFO_EXTENSION));
    
    // Robust detection: prioritize extension
    if (in_array($ext, ['mp4', 'mov', 'avi'])) {
        $f_type = 'video';
    } elseif ($ext === 'webm') {
        $f_type = 'video';
    } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $f_type = 'image';
    } elseif ($f_type === 'image' || $m_type === 'image') {
        $f_type = 'image';
    } elseif ($f_type === 'video' || $m_type === 'video') {
        $f_type = 'video';
    }
    
    if ($f_type !== 'image' && $f_type !== 'video') continue;

    $public_url = pf_chat_media_public_url($path);
    $results[] = [
        'message_file' => $public_url,
        'file_type' => $f_type
    ];
}

ob_end_clean();
echo json_encode([
    'success' => true,
    'media' => $results
]);
exit();
?>
