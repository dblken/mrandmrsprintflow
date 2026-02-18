<?php
/**
 * Authentication System
 * PrintFlow - Printing Shop PWA
 *
 * Role redirects: change REDIRECT_BASE if the app is not at /printflow (e.g. on production).
 */

// Base path for redirects (no trailing slash). Change this if app lives at a different path.
if (!defined('AUTH_REDIRECT_BASE')) {
    define('AUTH_REDIRECT_BASE', '/printflow');
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db.php';

// Try to include functions.php
$functions_path = __DIR__ . '/functions.php';
if (file_exists($functions_path)) {
    require_once $functions_path;
}

// Fallback: Define log_activity if it still doesn't exist to prevent fatal error
if (!function_exists('log_activity')) {
    function log_activity($user_id, $action, $details = '') {
        // Silently fail if function is missing, but don't crash the app
        error_log("Warning: log_activity function missing. Action: $action");
        return false;
    }
}

/**
 * Check if user is logged in
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

/**
 * Check if user is Admin
 * @return bool
 */
function is_admin() {
    return is_logged_in() && $_SESSION['user_type'] === 'Admin';
}

/**
 * Check if user is Staff
 * @return bool
 */
function is_staff() {
    return is_logged_in() && $_SESSION['user_type'] === 'Staff';
}

/**
 * Check if user is Customer
 * @return bool
 */
function is_customer() {
    return is_logged_in() && $_SESSION['user_type'] === 'Customer';
}

/**
 * Get current user ID
 * @return int|null
 */
function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user type
 * @return string|null
 */
function get_user_type() {
    return $_SESSION['user_type'] ?? null;
}

/**
 * Get current logged in user data
 * @return array|null
 */
function get_logged_in_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    $user_id = get_user_id();
    $user_type = get_user_type();
    
    if ($user_type === 'Customer') {
        $result = db_query("SELECT * FROM customers WHERE customer_id = ?", 'i', [$user_id]);
    } else {
        $result = db_query("SELECT * FROM users WHERE user_id = ?", 'i', [$user_id]);
    }
    
    return $result[0] ?? null;
}

/**
 * Login user (Admin/Staff)
 * @param string $email
 * @param string $password
 * @return array ['success' => bool, 'message' => string, 'redirect' => string]
 */
function login_user($email, $password) {
    $result = db_query("SELECT * FROM users WHERE email = ? AND status IN ('Activated', 'Pending')", 's', [$email]);
    
    if (empty($result)) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    $user = $result[0];
    
    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_type'] = $user['role']; // 'Admin' or 'Staff'
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_status'] = $user['status']; // 'Activated' or 'Pending'
    
    // Log activity
    // log_activity($user['user_id'], 'Login', 'User logged in');
    
    // Determine redirect based on role and status
    if ($user['role'] === 'Admin') {
        $redirect = AUTH_REDIRECT_BASE . '/admin/dashboard.php';
    } elseif ($user['status'] === 'Pending') {
        // Pending staff can only see profile to complete their information
        $redirect = AUTH_REDIRECT_BASE . '/staff/profile.php';
    } else {
        $redirect = AUTH_REDIRECT_BASE . '/staff/dashboard.php';
    }
    
    return [
        'success' => true,
        'message' => 'Login successful',
        'redirect' => $redirect
    ];
}

/**
 * Login customer
 * @param string $email
 * @param string $password
 * @return array ['success' => bool, 'message' => string, 'redirect' => string]
 */
function login_customer($email, $password) {
    $result = db_query("SELECT * FROM customers WHERE email = ? AND status = 'Activated'", 's', [$email]);
    
    if (empty($result)) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    $customer = $result[0];
    
    if (!password_verify($password, $customer['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    // Set session variables
    $_SESSION['user_id'] = $customer['customer_id'];
    $_SESSION['user_type'] = 'Customer';
    $_SESSION['user_name'] = $customer['first_name'] . ' ' . $customer['last_name'];
    $_SESSION['user_email'] = $customer['email'];
    
    return [
        'success' => true,
        'message' => 'Login successful',
        'redirect' => AUTH_REDIRECT_BASE . '/customer/dashboard.php'
    ];
}

/**
 * Login or register customer using Google profile (no password). Finds by email or creates new.
 * @param string $email
 * @param string $first_name
 * @param string $last_name
 * @return array ['success' => bool, 'message' => string, 'redirect' => string]
 */
function login_customer_by_google($email, $first_name, $last_name) {
    $email = trim($email);
    $first_name = trim($first_name) ?: 'User';
    $last_name = trim($last_name) ?: '';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email from Google'];
    }
    $existing = db_query("SELECT * FROM customers WHERE email = ? AND status = 'Activated'", 's', [$email]);
    if (!empty($existing)) {
        $customer = $existing[0];
        $_SESSION['user_id'] = $customer['customer_id'];
        $_SESSION['user_type'] = 'Customer';
        $_SESSION['user_name'] = ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '');
        $_SESSION['user_email'] = $customer['email'];
        return ['success' => true, 'message' => 'Login successful', 'redirect' => AUTH_REDIRECT_BASE . '/customer/dashboard.php'];
    }
    $password_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
    $sql = "INSERT INTO customers (first_name, middle_name, last_name, dob, gender, email, contact_number, password_hash, status) VALUES (?, '', ?, NULL, NULL, ?, NULL, ?, 'Activated')";
    $cid = db_execute($sql, 'ssss', [$first_name, $last_name, $email, $password_hash]);
    if (!$cid) {
        return ['success' => false, 'message' => 'Could not create account. Please try again.'];
    }
    $_SESSION['user_id'] = $cid;
    $_SESSION['user_type'] = 'Customer';
    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
    $_SESSION['user_email'] = $email;
    return ['success' => true, 'message' => 'Account created', 'redirect' => AUTH_REDIRECT_BASE . '/customer/dashboard.php'];
}

/**
 * Unified login function (detects user type automatically)
 * @param string $email
 * @param string $password
 * @return array
 */
function login($email, $password) {
    // Try customer login first
    $customer_result = login_customer($email, $password);
    if ($customer_result['success']) {
        return $customer_result;
    }
    
    // Try user (Admin/Staff) login
    $user_result = login_user($email, $password);
    if ($user_result['success']) {
        return $user_result;
    }
    
    return ['success' => false, 'message' => 'Invalid email or password'];
}


/**
 * Register a new customer
 * @param array $data
 * @return array ['success' => bool, 'message' => string]
 */
function register_customer($data) {
    // Check if email already exists
    $existing = db_query("SELECT customer_id FROM customers WHERE email = ?", 's', [$data['email']]);
    if (!empty($existing)) {
        return ['success' => false, 'message' => 'Email already registered'];
    }
    
    // Hash password
    $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);
    
    // Insert customer
    $sql = "INSERT INTO customers (first_name, middle_name, last_name, dob, gender, email, contact_number, password_hash, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Activated')";
    
    $result = db_execute($sql, 'ssssssss', [
        $data['first_name'],
        $data['middle_name'] ?? null,
        $data['last_name'],
        $data['dob'] ?? null,
        $data['gender'] ?? null,
        $data['email'],
        $data['contact_number'] ?? null,
        $password_hash
    ]);
    
    if ($result) {
        // Auto-login after registration
        $_SESSION['user_id'] = $result;
        $_SESSION['user_type'] = 'Customer';
        $_SESSION['user_name'] = $data['first_name'] . ' ' . $data['last_name'];
        $_SESSION['user_email'] = $data['email'];
        
        return ['success' => true, 'message' => 'Registration successful'];
    }
    
    return ['success' => false, 'message' => 'Registration failed. Please try again.'];
}

/**
 * Require authentication (redirect to login if not logged in)
 */
function require_auth() {
    if (!is_logged_in()) {
        header('Location: ' . AUTH_REDIRECT_BASE . '/');
        exit();
    }
}

/**
 * Require specific role (redirect if user doesn't have the role)
 * @param string|array $roles Allowed roles (e.g., 'Admin' or ['Admin', 'Staff'])
 */
function require_role($roles) {
    require_auth();
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    $user_type = get_user_type();
    
    if (!in_array($user_type, $roles)) {
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>');
    }
}

/**
 * Generate CSRF token
 * @return string
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token HTML input
 * @return string
 */
function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}
