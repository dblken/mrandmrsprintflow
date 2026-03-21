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
    // activity_logs.user_id has FK to users.user_id only.
    // Customer IDs are from customers.customer_id and can violate FK.
    // Logging must never break request flow.
    try {
        $resolved_user_id = 0;

        if (is_numeric($user_id)) {
            $candidate = (int)$user_id;
            if ($candidate > 0) {
                $exists = db_query("SELECT user_id FROM users WHERE user_id = ? LIMIT 1", 'i', [$candidate]);
                if (!empty($exists)) {
                    $resolved_user_id = $candidate;
                }
            }
        }

        // If the provided ID is not a valid staff/admin user, skip insert safely.
        if ($resolved_user_id <= 0) {
            return true;
        }

        $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
        $result = db_execute($sql, 'iss', [$resolved_user_id, (string)$action, (string)$details]);
        return $result !== false;
    } catch (Throwable $e) {
        error_log("Activity log failed: " . $e->getMessage());
        return true; // Never block main feature when activity log fails
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
 * Ensure ratings table exists.
 * One rating entry per order.
 * @return void
 */
function ensure_ratings_table_exists() {
    static $ensured = false;
    if ($ensured) return;

    db_execute("
        CREATE TABLE IF NOT EXISTS ratings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            customer_id INT NOT NULL,
            service_type VARCHAR(150) DEFAULT NULL,
            rating TINYINT NOT NULL,
            comment TEXT DEFAULT NULL,
            image VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_rating_order (order_id),
            KEY idx_rating_customer (customer_id),
            KEY idx_rating_value (rating),
            CONSTRAINT fk_ratings_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
            CONSTRAINT fk_ratings_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
            CONSTRAINT chk_ratings_value CHECK (rating BETWEEN 1 AND 5)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $ensured = true;
}

/**
 * Ensure `orders.status` enum contains required values.
 * Safe no-op if column is not enum or values already exist.
 * @param array $values
 * @return bool
 */
function ensure_order_status_values(array $values) {
    static $already_checked = [];
    $missing = array_values(array_filter(array_map('strval', $values), fn($v) => $v !== ''));
    if (empty($missing)) return true;
    sort($missing);
    $cache_key = implode('|', $missing);
    if (isset($already_checked[$cache_key])) return $already_checked[$cache_key];

    try {
        $col = db_query("SHOW COLUMNS FROM orders LIKE 'status'");
        if (empty($col[0]['Type'])) {
            return $already_checked[$cache_key] = false;
        }
        $type = (string)$col[0]['Type'];
        if (stripos($type, 'enum(') !== 0) {
            return $already_checked[$cache_key] = false;
        }

        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $type, $m);
        $current = array_map(static function ($v) {
            return str_replace("\\'", "'", (string)$v);
        }, $m[1] ?? []);
        $all = $current;
        foreach ($missing as $v) {
            if (!in_array($v, $all, true)) $all[] = $v;
        }
        if (count($all) === count($current)) {
            return $already_checked[$cache_key] = true;
        }

        $escaped = array_map(static function ($v) {
            return "'" . str_replace("'", "\\'", (string)$v) . "'";
        }, $all);
        $default = in_array('Pending', $all, true) ? 'Pending' : $all[0];
        $sql = "ALTER TABLE orders MODIFY COLUMN status ENUM(" . implode(',', $escaped) . ") DEFAULT '" . str_replace("'", "\\'", $default) . "'";
        db_execute($sql);

        return $already_checked[$cache_key] = true;
    } catch (Throwable $e) {
        error_log('ensure_order_status_values failed: ' . $e->getMessage());
        return $already_checked[$cache_key] = false;
    }
}

/**
 * Friendly customer-facing status notification message.
 * @param int $order_id
 * @param string $status
 * @return array{type:string,message:string}
 */
function get_order_status_notification_payload($order_id, $status) {
    $order_id = (int)$order_id;
    $status = (string)$status;
    // Keep notification type enum-compatible across deployments.
    $type = 'Order';
    $base_url = defined('BASE_URL') ? BASE_URL : '/printflow';

    $map = [
        'Pending' => "Your order has been received and is pending confirmation.",
        'Pending Review' => "Your order has been received and is pending confirmation.",
        'Pending Approval' => "Your order has been received and is pending confirmation.",
        'For Revision' => "Your order needs revision. Please review the request details.",
        'Approved' => "Your order has been approved and will proceed to payment.",
        'To Pay' => "Your order is now ready for payment.",
        'To Verify' => "Your payment is currently being verified.",
        'Downpayment Submitted' => "Your payment is currently being verified.",
        'Pending Verification' => "Your payment is currently being verified.",
        'Processing' => "Your order is now being processed.",
        'In Production' => "Your order is now being processed.",
        'Printing' => "Your order is now being processed.",
        'Ready for Pickup' => "Your order is ready for pickup.",
        'Completed' => "Your order has been completed. You may now rate your experience.",
        'To Rate' => "Your order has been completed. You may now rate your experience.",
        'Rated' => "Thank you for rating your completed order.",
        'Cancelled' => "Your order has been cancelled."
    ];

    $message = $map[$status] ?? "Your order #{$order_id} status has been updated to: {$status}";
    if ($status === 'Completed' || $status === 'To Rate') {
        $message .= " Rate here: " . $base_url . "/customer/rate_order.php?order_id={$order_id}";
    }

    return ['type' => $type, 'message' => $message];
}

/**
 * Add a system message to an order's chat thread.
 * Call this when order status changes, payment verified, etc.
 *
 * @param int    $order_id
 * @param string $message
 * @return bool
 */
function add_order_system_message($order_id, $message) {
    $order_id = (int)$order_id;
    $message = trim($message);
    if (!$order_id || $message === '') return false;

    $sql = "INSERT INTO order_messages (order_id, sender, sender_id, message, message_type, read_receipt)
            VALUES (?, 'System', 0, ?, 'text', 1)";
    return (bool) db_execute($sql, 'is', [$order_id, $message]);
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
    // Map job order statuses to display-friendly format
    $job_order_status_map = [
        'PENDING' => 'Pending',
        'APPROVED' => 'Approved',
        'TO_PAY' => 'To Pay',
        'VERIFY_PAY' => 'To Verify',
        'IN_PRODUCTION' => 'Processing',
        'TO_RECEIVE' => 'Ready for Pickup',
        'COMPLETED' => 'Completed',
        'CANCELLED' => 'Cancelled'
    ];
    
    // Map job order payment statuses
    $job_order_payment_status_map = [
        'UNPAID' => 'Unpaid',
        'PENDING_VERIFICATION' => 'Pending Verification',
        'PARTIAL' => 'Partially Paid',
        'PAID' => 'Paid'
    ];
    
    // Convert job order status if needed
    if (isset($job_order_status_map[$status])) {
        $status = $job_order_status_map[$status];
    }
    
    // Convert job order payment status if needed
    if ($type === 'payment' && isset($job_order_payment_status_map[$status])) {
        $status = $job_order_payment_status_map[$status];
    }
    
    $colors = [
        'order' => [
            'Pending' => 'bg-yellow-100 text-yellow-800',
            'Pending Review' => 'bg-yellow-100 text-yellow-800',
            'Approved' => 'bg-blue-100 text-blue-800',
            'To Pay' => 'bg-indigo-100 text-indigo-800',
            'To Verify' => 'bg-orange-100 text-orange-800',
            'Downpayment Submitted' => 'bg-purple-100 text-purple-800',
            'Pending Verification' => 'bg-orange-100 text-orange-800',
            'Processing' => 'bg-blue-100 text-blue-800',
            'For Revision' => 'bg-pink-100 text-pink-800',
            'Ready for Pickup' => 'bg-teal-100 text-teal-800',
            'Completed' => 'bg-green-100 text-green-800',
            'To Rate' => 'bg-purple-100 text-purple-800',
            'Rated' => 'bg-emerald-100 text-emerald-800',
            'Cancelled' => 'bg-red-100 text-red-800'
        ],
        'payment' => [
            'Unpaid' => 'bg-red-100 text-red-800',
            'Partially Paid' => 'bg-yellow-100 text-yellow-800',
            'Paid' => 'bg-green-100 text-green-800',
            'Refunded' => 'bg-gray-100 text-gray-800',
            'Pending Verification' => 'bg-orange-100 text-orange-800'
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
function render_pagination($current_page, $total_pages, $extra_params = []) {
    $current_page = (int)$current_page;

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

/**
 * Detects the service name based on customization keys if not explicitly provided.
 */
function normalize_service_name($name, $fallback = 'Custom Order') {
    $clean = trim((string)$name);
    if ($clean === '') return $fallback;

    $normalized = strtolower(preg_replace('/\s+/', ' ', $clean));
    $map = [
        'custom order' => $fallback,
        'customer order' => $fallback,
        'order item' => $fallback,
        'service order' => $fallback,
        'tshirt' => 'T-Shirt Printing',
        't-shirt' => 'T-Shirt Printing',
        't shirts' => 'T-Shirt Printing',
        't-shirts' => 'T-Shirt Printing',
        'tarpaulin' => 'Tarpaulin',
        'decal' => 'Decals',
        'decals' => 'Decals',
        'sticker' => 'Stickers',
        'stickers' => 'Stickers',
        'glass/wall' => 'Glass/Wall Stickers',
        'glass stickers' => 'Glass/Wall Stickers',
        'transparent' => 'Transparent',
        'transparent sticker' => 'Transparent',
        'transparent sticker printing' => 'Transparent',
        'reflectorized' => 'Reflectorized',
        'sintraboard' => 'Sintraboard',
        'standees' => 'Standees',
        'standee' => 'Standees',
        'souvenir' => 'Souvenirs',
        'souvenirs' => 'Souvenirs'
    ];

    if (isset($map[$normalized])) {
        return $map[$normalized];
    }

    return ucwords($clean);
}

function get_service_name_from_customization($custom, $fallback = 'Custom Order') {
    if (!$custom) return $fallback;
    $custom = is_string($custom) ? json_decode($custom, true) : $custom;
    
    if (!empty($custom['service_type'])) {
        return normalize_service_name($custom['service_type'], $fallback);
    }
    
    // Heuristics based on common customization fields
    if (isset($custom['print_placement']) || isset($custom['tshirt_color']) || isset($custom['tshirt_size'])) {
        return normalize_service_name('T-Shirt Printing', $fallback);
    }
    if (isset($custom['width']) && isset($custom['height']) && (isset($custom['finish']) || isset($custom['with_eyelets']))) {
        return normalize_service_name('Tarpaulin', $fallback);
    }
    if (isset($custom['surface_application']) && isset($custom['dimensions']) && isset($custom['layout'])) {
        return normalize_service_name('Transparent Sticker Printing', $fallback);
    }
    if (isset($custom['surface_type']) && (isset($custom['laminate_option']) || isset($custom['lamination']))) {
        return normalize_service_name('Glass & Wall Sticker Printing', $fallback);
    }
    if (isset($custom['shape']) && isset($custom['size']) && (isset($custom['waterproof']) || isset($custom['sticker_type']) || isset($custom['laminate_option']))) {
        return normalize_service_name('Stickers', $fallback);
    }
    if (isset($custom['sintraboard_thickness']) || isset($custom['is_standee'])) {
        return normalize_service_name('Sintraboard', $fallback);
    }
    
    return normalize_service_name($fallback, $fallback);
}

/**
 * Service image mapping - SAME as Services page ($core_services).
 * Source of truth: /customer/services.php
 */
function get_services_image_map() {
    $base = '/printflow/public';
    return [
        'tarpaulin'   => $base . '/images/products/product_42.jpg',
        't-shirt'     => $base . '/images/products/product_31.jpg',
        'shirt'       => $base . '/images/products/product_31.jpg',
        'stickers'    => $base . '/images/products/product_21.jpg',
        'sticker'     => $base . '/images/products/product_21.jpg',
        'decal'       => $base . '/images/products/product_21.jpg',
        'glass'       => $base . '/images/products/Glass Stickers  Wall  Frosted Stickers.png',
        'frosted'     => $base . '/images/products/Glass Stickers  Wall  Frosted Stickers.png',
        'wall'        => $base . '/images/products/Glass Stickers  Wall  Frosted Stickers.png',
        'transparent' => $base . '/images/products/product_26.jpg',
        'reflectorized' => $base . '/images/products/signage.jpg',
        'signage'     => $base . '/images/products/signage.jpg',
        'sintraboard' => $base . '/images/products/standeeflat.jpg',
        'standee'     => $base . '/images/services/Sintraboard Standees.jpg',
        'souvenir'    => $base . '/assets/images/placeholder.jpg',
    ];
}

/**
 * Get service image URL for Orders/Notifications - exact same images as Services page.
 * @param string $service_type_or_name e.g. "T-Shirt Printing", "Tarpaulin", "Custom T-Shirt"
 * @return string URL path to image (same file as Services page)
 */
function get_service_image_url($service_type_or_name) {
    $cat = strtolower(trim(preg_replace('/\s+/', ' ', (string)$service_type_or_name)));
    if ($cat === '') return '/printflow/public/assets/images/placeholder.jpg';

    $map = get_services_image_map();
    foreach ($map as $keyword => $img) {
        if (strpos($cat, $keyword) !== false) {
            return $img;
        }
    }

    return '/printflow/public/assets/images/placeholder.jpg';
}
