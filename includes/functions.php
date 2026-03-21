<?php
/**
 * Helper Functions
 * PrintFlow - Printing Shop PWA
 */

// Set Timezone – adjust this based on your location
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_sms_config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send email notification using PHPMailer
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email body (HTML)
 * @param bool $is_html Whether message is HTML (default: true)
 * @return bool
 */
function send_email($to, $subject, $message, $is_html = true) {
    // Check if email is enabled
    if (!EMAIL_ENABLED) {
        error_log("Email sending disabled. Would send to: {$to}");
        return false;
    }
    
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        if (EMAIL_SERVICE === 'smtp') {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port       = SMTP_PORT;
        } elseif (EMAIL_SERVICE === 'sendmail') {
            $mail->isSendmail();
        } else {
            // Use PHP's mail() function
            $mail->isMail();
        }
        
        // Recipients
        $mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
        
        // Content
        $mail->isHTML($is_html);
        $mail->Subject = $subject;
        
        if ($is_html) {
            $mail->Body = $message;
            $mail->AltBody = strip_tags($message);
        } else {
            $mail->Body = $message;
        }
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Failed to send email to {$to}: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send SMS notification
 * @param string $phone Phone number
 * @param string $message SMS message
 * @return bool
 */
function send_sms($phone, $message) {
    // Check if SMS is enabled
    if (!SMS_ENABLED) {
        error_log("SMS sending disabled. Would send to: {$phone} - Message: {$message}");
        return false;
    }
    
    try {
        if (SMS_SERVICE === 'semaphore') {
            // Semaphore SMS API (Philippines)
            $url = 'https://api.semaphore.co/api/v4/messages';
            $data = [
                'apikey' => SEMAPHORE_API_KEY,
                'number' => $phone,
                'message' => $message,
                'sendername' => SEMAPHORE_SENDER_NAME
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                return true;
            } else {
                error_log("Semaphore SMS failed: " . $response);
                return false;
            }
            
        } elseif (SMS_SERVICE === 'twilio') {
            // Twilio SMS API
            require_once __DIR__ . '/../vendor/autoload.php';
            
            $twilio = new \Twilio\Rest\Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);
            $twilio->messages->create($phone, [
                'from' => TWILIO_PHONE_NUMBER,
                'body' => $message
            ]);
            
            return true;
            
        } else {
            error_log("No SMS service configured. Message to {$phone}: {$message}");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Failed to send SMS to {$phone}: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a notification
 * @param int $user_id User or Customer ID
 * @param string $user_type 'Customer' or 'User'
 * @param string $message Notification message
 * @param string $type Notification type ('Order', 'Stock', 'System', 'Message')
 * @param bool $send_email Whether to send email
 * @param bool $send_sms Whether to send SMS
 * @return bool|int
 */
function create_notification($user_id, $user_type, $message, $type = 'System', $send_email = false, $send_sms = false, $data_id = null) {
    $customer_id = $user_type === 'Customer' ? $user_id : null;
    $staff_user_id = $user_type !== 'Customer' ? $user_id : null;
    
    $sql = "INSERT INTO notifications (user_id, customer_id, message, type, data_id, is_read, send_email, send_sms) 
            VALUES (?, ?, ?, ?, ?, 0, ?, ?)";
    
    $result = db_execute($sql, 'iissiii', [
        $staff_user_id,
        $customer_id,
        $message,
        $type,
        $data_id,
        $send_email ? 1 : 0,
        $send_sms ? 1 : 0
    ]);
    
    if ($result && $send_email) {
        // Get user email
        if ($user_type === 'Customer') {
            $user = db_query("SELECT email FROM customers WHERE customer_id = ?", 'i', [$user_id]);
        } else {
            $user = db_query("SELECT email FROM users WHERE user_id = ?", 'i', [$user_id]);
        }
        
        if (!empty($user)) {
            send_email($user[0]['email'], "PrintFlow Notification", $message);
        }
    }

    // ── Web Push dispatch ────────────────────────────────────────────────────
    if ($result) {
        $push_helper = __DIR__ . '/push_helper.php';
        if (file_exists($push_helper)) {
            require_once $push_helper;
            if (function_exists('push_notify_user') && function_exists('push_url_for_type')) {
                push_notify_user((int)$user_id, $user_type, [
                    'body' => $message,
                    'tag'  => 'pf-' . strtolower($type) . '-' . ($data_id ?? $result),
                    'url'  => push_url_for_type($type, $data_id, $user_type),
                ]);
            }
        }
    }

    return $result;
}

/**
 * Log user activity
 * @param int $user_id
 * @param string $action Action performed
 * @param string $details Additional details
 * @return bool|int
 */
function log_activity($user_id, $action, $details = '') {
    // Simple version - only log if table exists and has correct structure
    // Silently fail if logging doesn't work to prevent breaking the app
    try {
        $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
        return db_execute($sql, 'iss', [$user_id, $action, $details]);
    } catch (Exception $e) {
        error_log("Activity log failed: " . $e->getMessage());
        return true; // Don't break the app if logging fails
    }
}

/**
 * Get customer ID from session
 * @return int|null
 */
function get_customer_id() {
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Customer') {
        return $_SESSION['user_id'] ?? null;
    }
    return null;
}

/**
 * Load customer cart from database into session.
 * Call after customer login or when session cart is empty.
 * @param int $customer_id
 * @return void
 */
function load_customer_cart_into_session($customer_id) {
    if (!$customer_id) return;
    $rows = db_query("SELECT product_id, variant_id, quantity FROM customer_cart WHERE customer_id = ?", 'i', [$customer_id]);
    if (empty($rows)) return;
    $_SESSION['cart'] = [];
    foreach ($rows as $r) {
        $pid = (int)$r['product_id'];
        $vid = isset($r['variant_id']) && $r['variant_id'] !== '' && $r['variant_id'] !== null ? (int)$r['variant_id'] : null;
        $qty = max(0, (int)$r['quantity']);
        if ($qty <= 0 || $pid <= 0) continue;
        $product = db_query("SELECT name, price, category FROM products WHERE product_id = ? AND status = 'Activated'", 'i', [$pid]);
        if (empty($product)) continue;
        $product = $product[0];
        $price = (float)$product['price'];
        $variant_name = '';
        if ($vid) {
            $v = db_query("SELECT variant_name, price FROM product_variants WHERE variant_id = ? AND product_id = ? AND status = 'Active'", 'ii', [$vid, $pid]);
            if (!empty($v)) {
                $variant_name = $v[0]['variant_name'] ?? '';
                $price = (float)$v[0]['price'];
            }
        }
        $key = $pid . '_' . ($vid ?? '0');
        $_SESSION['cart'][$key] = [
            'product_id' => $pid,
            'variant_id' => $vid,
            'name' => $product['name'],
            'category' => $product['category'] ?? '',
            'variant_name' => $variant_name,
            'quantity' => $qty,
            'price' => $price,
        ];
    }
}

/**
 * Sync session cart to customer_cart table.
 * @param int $customer_id
 * @return void
 */
function sync_cart_to_db($customer_id) {
    if (!$customer_id) return;
    db_execute("DELETE FROM customer_cart WHERE customer_id = ?", 'i', [$customer_id]);
    if (empty($_SESSION['cart'])) return;
    foreach ($_SESSION['cart'] as $key => $item) {
        $qty = (int)($item['quantity'] ?? 0);
        if ($qty <= 0) continue;
        $pid = (int)($item['product_id'] ?? 0);
        $vid = isset($item['variant_id']) && $item['variant_id'] !== null ? (int)$item['variant_id'] : 0;
        if ($pid <= 0) continue;
        db_execute("INSERT INTO customer_cart (customer_id, product_id, variant_id, quantity, updated_at) VALUES (?, ?, ?, ?, NOW())", 'iiii', [$customer_id, $pid, $vid, $qty]);
    }
}

/**
 * Get customer cancellation count (last 30 days)
 * @param int $customer_id
 * @return int
 */
function get_customer_cancel_count($customer_id) {
    if (!$customer_id) return 0;
    $sql = "SELECT COUNT(*) as count FROM orders WHERE customer_id = ? AND status = 'Cancelled' AND order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $result = db_query($sql, 'i', [$customer_id]);
    return (int)($result[0]['count'] ?? 0);
}

/**
 * Check if customer is restricted due to cancellations
 * @param int $customer_id
 * @return bool
 */
function is_customer_restricted($customer_id) {
    if (!$customer_id) return false;
    // Check for hard restriction in DB first
    $customer = db_query("SELECT is_restricted FROM customers WHERE customer_id = ?", 'i', [$customer_id]);
    if (!empty($customer) && $customer[0]['is_restricted']) return true;

    // Automatic restriction based on cancellation count (7+)
    return get_customer_cancel_count($customer_id) >= 7;
}



/**
 * Validate file upload
 * @param array $file $_FILES array element
 * @param array $allowed_types Allowed MIME types
 * @param int $max_size Max file size in bytes
 * @return array ['valid' => bool, 'message' => string, 'file_info' => array]
 */
function validate_file_upload($file, $allowed_types = [], $max_size = 10485760) {
    // Default allowed types for design files
    if (empty($allowed_types)) {
        $allowed_types = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'application/pdf',
            'image/svg+xml'
        ];
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'message' => 'File upload error'];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $max_mb = $max_size / 1048576;
        return ['valid' => false, 'message' => "File too large. Maximum size is {$max_mb}MB"];
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['valid' => false, 'message' => 'Invalid file type'];
    }
    
    return [
        'valid' => true,
        'message' => 'File is valid',
        'file_info' => [
            'name' => $file['name'],
            'size' => $file['size'],
            'type' => $mime_type,
            'tmp_name' => $file['tmp_name']
        ]
    ];
}

/**
 * Upload file to server
 * @param array $file $_FILES array element
 * @param array $allowed_extensions Array of allowed extensions (e.g., ['jpg', 'png', 'pdf'])
 * @param string $destination Directory name under uploads/ (e.g., 'designs', 'payments')
 * @param string|null $new_name Optional new filename
 * @return array ['success' => bool, 'message' => string, 'error' => string, 'file_path' => string]
 */
function upload_file($file, $allowed_extensions = [], $destination = 'uploads', $new_name = null) {
    // Check for upload errors  
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error'];
    }
    
    // Check file size (5MB default)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 5MB'];
    }
    
    // Check extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!empty($allowed_extensions) && !in_array($ext, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    // Create destination directory if it doesn't exist
    $upload_dir = __DIR__ . '/../uploads/' . $destination;
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate filename
    if ($new_name === null) {
        $new_name = uniqid() . '_' . time() . '.' . $ext;
    }
    
    $target_path = $upload_dir . '/' . $new_name;
    $relative_path = '/printflow/uploads/' . $destination . '/' . $new_name;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return [
            'success' => true,
            'message' => 'File uploaded successfully',
            'file_path' => $relative_path,
            'file_name' => $new_name
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to upload file'];
}

/**
 * Format currency
 * @param float $amount
 * @param string $currency
 * @return string
 */
function format_currency($amount, $currency = 'PHP') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Format date
 * @param string $date
 * @param string $format
 * @return string
 */
function format_date($date, $format = 'F j, Y') {
    return date($format, strtotime($date));
}

/**
 * Format datetime
 * @param string $datetime
 * @param string $format
 * @return string
 */
function format_datetime($datetime, $format = 'F j, Y g:i A') {
    return date($format, strtotime($datetime));
}

/**
 * Get time ago
 * @param string $datetime
 * @return string
 */
function time_ago($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    $periods = [
        'year' => 31536000,
        'month' => 2592000,
        'week' => 604800,
        'day' => 86400,
        'hour' => 3600,
        'minute' => 60,
        'second' => 1
    ];
    
    foreach ($periods as $key => $value) {
        $result = floor($difference / $value);
        
        if ($result >= 1) {
            return $result . ' ' . $key . ($result > 1 ? 's' : '') . ' ago';
        }
    }
    
    return 'Just now';
}

/**
 * Generate status badge HTML
 * @param string $status
 * @param string $type 'order', 'payment', 'design'
 * @return string
 */
function status_badge($status, $type = 'order') {
    $colors = [
        'order' => [
            'Pending' => 'bg-yellow-100 text-yellow-800',
            'Pending Review' => 'bg-yellow-100 text-yellow-800',
            'Processing' => 'bg-blue-100 text-blue-800',
            'Ready for Pickup' => 'bg-green-100 text-green-800',
            'Completed' => 'bg-green-100 text-green-800',
            'Cancelled' => 'bg-red-100 text-red-800'
        ],
        'payment' => [
            'Unpaid' => 'bg-red-100 text-red-800',
            'Paid' => 'bg-green-100 text-green-800',
            'Refunded' => 'bg-gray-100 text-gray-800'
        ],
        'design' => [
            'Pending' => 'bg-yellow-100 text-yellow-800',
            'Approved' => 'bg-green-100 text-green-800',
            'Rejected' => 'bg-red-100 text-red-800'
        ]
    ];
    
    $color = $colors[$type][$status] ?? 'bg-gray-100 text-gray-800';
    // Display "Pending" instead of "Pending Review" for consistency
    $display = ($status === 'Pending Review') ? 'Pending' : $status;
    
    return "<span class='px-2 py-1 text-xs font-semibold rounded-full {$color}'>" . htmlspecialchars($display) . "</span>";
}

/**
 * Sanitize input
 * @param string $input
 * @return string
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Normalize branch name for Add/Edit: trim, strip trailing "Branch", title-case
 * System auto-appends " Branch" — user should not type it.
 */
function normalize_branch_name($name) {
    $name = trim($name);
    $name = preg_replace('/\s+Branch\s*$/i', '', $name);
    return ucwords(strtolower($name));
}

/**
 * Redirect to URL
 * @param string $url
 */
function redirect($url) {
    header("Location: {$url}");
    exit();
}

/**
 * Get unread notification count
 * @param int $user_id
 * @param string $user_type
 * @return int
 */
function get_unread_notification_count($user_id, $user_type) {
    if ($user_type === 'Customer') {
        $result = db_query("SELECT COUNT(*) as count FROM notifications WHERE customer_id = ? AND is_read = 0", 'i', [$user_id]);
    } else {
        $result = db_query("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", 'i', [$user_id]);
    }
    
    return $result[0]['count'] ?? 0;
}

/**
 * Get count of unread chat messages for an order
 * @param int $order_id
 * @param string $viewer_role 'Customer' or 'Staff'
 * @return int
 */
function get_unread_chat_count($order_id, $viewer_role) {
    // If viewer is Customer, they haven't read messages from Staff
    // If viewer is Staff, they haven't read messages from Customer
    $sender_role = ($viewer_role === 'Customer') ? 'Staff' : 'Customer';
    
    $sql = "SELECT COUNT(*) as count FROM order_messages 
            WHERE order_id = ? AND sender = ? AND read_receipt = 0";
    $result = db_query($sql, 'is', [$order_id, $sender_role]);
    
    return $result[0]['count'] ?? 0;
}

/**
 * Generate random order number
 * @return string
 */
function generate_order_number() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Check if product is low stock
 * @param int $product_id
 * @param int $threshold Default threshold
 * @return bool
 */
function is_low_stock($product_id, $threshold = 10) {
    $result = db_query("SELECT stock_quantity FROM products WHERE product_id = ?", 'i', [$product_id]);
    
    if (empty($result)) {
        return false;
    }
    
    return $result[0]['stock_quantity'] <= $threshold;
}

/**
 * Compute stock status from quantity and low_stock_level (not stored in DB)
 * @param int $stock_quantity
 * @param int $low_stock_level
 * @return string "Out of Stock"|"Low Stock"|"In Stock"
 */
function get_stock_status($stock_quantity, $low_stock_level = 10) {
    $qty = (int) $stock_quantity;
    $low = (int) ($low_stock_level ?? 10);
    if ($qty <= 0) return 'Out of Stock';
    if ($qty <= $low) return 'Low Stock';
    return 'In Stock';
}

/**
 * Get app setting
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function get_setting($key, $default = null) {
    $result = db_query("SELECT value FROM settings WHERE key_name = ?", 's', [$key]);
    
    if (empty($result)) {
        return $default;
    }
    
    return $result[0]['value'];
}

/**
 * Set app setting
 * @param string $key
 * @param mixed $value
 * @return bool
 */
function set_setting($key, $value) {
    $existing = db_query("SELECT setting_id FROM settings WHERE key_name = ?", 's', [$key]);
    
    if (empty($existing)) {
        return db_execute("INSERT INTO settings (key_name, value) VALUES (?, ?)", 'ss', [$key, $value]);
    } else {
        return db_execute("UPDATE settings SET value = ? WHERE key_name = ?", 'ss', [$value, $key]);
    }
}

/**
 * Render pagination UI
 * @param int $current_page Current page number
 * @param int $total_pages Total number of pages
 * @param array $extra_params Extra query parameters to preserve (e.g. search, filters)
 * @param string $page_param Query param name for page number (default: 'page')
 * @return string HTML string
 */
function render_pagination($current_page, $total_pages, $extra_params = [], $page_param = 'page') {
    $current_page = (int)$current_page;
    $total_pages   = (int)$total_pages;

    if ($total_pages <= 1) return '';
    
    $params = $extra_params;
    unset($params[$page_param]);

    // Build page range: always show current ±2 pages, plus first and last
    $window = 2;
    $pages = [];

    // Always include first page
    $pages[] = 1;

    // Pages around current
    $range_start = max(2, $current_page - $window);
    $range_end   = min($total_pages - 1, $current_page + $window);
    for ($i = $range_start; $i <= $range_end; $i++) {
        $pages[] = $i;
    }

    // Always include last page
    if ($total_pages > 1) {
        $pages[] = $total_pages;
    }

    $pages = array_unique($pages);
    sort($pages);

    // Shared button styles
    $base_btn  = 'display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:6px;border:1px solid #e5e7eb;background:white;color:#374151;text-decoration:none;font-size:13px;font-weight:500;transition:all 0.2s;';
    $active_btn = 'display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:6px;border:1px solid #0d9488;background:#0d9488;color:white;text-decoration:none;font-size:13px;font-weight:600;';
    $hover = ' onmouseover="this.style.background=\'#f5f7fa\'" onmouseout="this.style.background=\'white\'"';
    $ellipsis = '<span style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;font-size:13px;color:#9ca3af;letter-spacing:1px;">···</span>';

    $html = '<div style="display:flex; align-items:center; justify-content:center; gap:4px; margin-top:20px; padding-top:16px; border-top:1px solid #f3f4f6;">';

    // Previous button
    if ($current_page > 1) {
        $params[$page_param] = $current_page - 1;
        $url = '?' . http_build_query($params);
        $html .= '<a href="' . htmlspecialchars($url) . '" style="' . $base_btn . '"' . $hover . '>
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>';
    }

    $prev_page = null;
    foreach ($pages as $p) {
        // Insert ellipsis if there's a gap
        if ($prev_page !== null && $p - $prev_page > 1) {
            $html .= $ellipsis;
        }

        $params['page'] = $p;
        $url = '?' . http_build_query($params);
        if ($p === $current_page) {
            $html .= '<a href="' . htmlspecialchars($url) . '" style="' . $active_btn . '">' . $p . '</a>';
        } else {
            $html .= '<a href="' . htmlspecialchars($url) . '" style="' . $base_btn . '"' . $hover . '>' . $p . '</a>';
        }

        $prev_page = $p;
    }

    // Next button
    if ($current_page < $total_pages) {
        $params[$page_param] = $current_page + 1;
        $url = '?' . http_build_query($params);
        $html .= '<a href="' . htmlspecialchars($url) . '" style="' . $base_btn . '"' . $hover . '>
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>';
    }

    $html .= '</div>';
    return $html;
}

/**
 * Alias for render_pagination (backward compatibility)
 */
function get_pagination_links($current_page, $total_pages, $extra_params = []) {
    return render_pagination($current_page, $total_pages, $extra_params);
}

/**
 * Determine if a customer can cancel an order based on its status.
 */
function can_customer_cancel_order($order) {
    if (!$order) return false;
    $status = $order['status'] ?? '';
    // Customers can cancel unless production has started or payment is being verified
    $allowed_statuses = ['Pending', 'To Pay', 'For Revision', 'Pending Verification'];
    return in_array($status, $allowed_statuses);
}

/**
 * Get base URL for the application
 * @return string
 */
function get_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    $path = rtrim($path, '/');
    return $protocol . '://' . $host . $path;
}
