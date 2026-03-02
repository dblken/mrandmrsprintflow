<?php
/**
 * Google OAuth Authentication Handler
 * PrintFlow - Printing Shop PWA
 * 
 * This uses Google's OAuth 2.0 for sign-in/sign-up.
 * Configure your Google Cloud Console credentials below.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// ================================================================
// GOOGLE OAUTH CONFIGURATION
// Create credentials at https://console.cloud.google.com/apis/credentials
// Set Authorized redirect URI to: http://localhost/printflow/public/google_auth.php?action=callback
// ================================================================
define('GOOGLE_CLIENT_ID',     'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI',  'http://localhost/printflow/public/google_auth.php?action=callback');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
    case 'register':
        // Store intent in session
        $_SESSION['google_auth_intent'] = $action;
        
        // Build Google OAuth URL
        $params = http_build_query([
            'client_id'     => GOOGLE_CLIENT_ID,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'access_type'   => 'offline',
            'prompt'        => 'select_account',
        ]);
        header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
        exit;

    case 'callback':
        $code = $_GET['code'] ?? '';
        if (!$code) {
            redirect('/printflow/public/login.php?error=' . urlencode('Google authentication cancelled.'));
        }
        
        // Exchange code for access token
        $tokenData = exchangeCodeForToken($code);
        if (!$tokenData) {
            redirect('/printflow/public/login.php?error=' . urlencode('Google authentication failed.'));
        }
        
        // Get user info from Google
        $googleUser = getGoogleUserInfo($tokenData['access_token']);
        if (!$googleUser || empty($googleUser['email'])) {
            redirect('/printflow/public/login.php?error=' . urlencode('Could not retrieve Google profile.'));
        }
        
        $email = $googleUser['email'];
        $name  = $googleUser['name'] ?? 'Google User';
        $parts = explode(' ', $name, 2);
        $firstName = $parts[0] ?? 'User';
        $lastName  = $parts[1] ?? '';
        
        $intent = $_SESSION['google_auth_intent'] ?? 'login';
        unset($_SESSION['google_auth_intent']);
        
        // Check if customer already exists
        $existing = db_query("SELECT * FROM customers WHERE email = ?", 's', [$email]);
        
        if ($existing) {
            // User exists — log them in directly
            $customer = $existing[0];
            $_SESSION['user_id']    = $customer['customer_id'];
            $_SESSION['user_type']  = 'Customer';
            $_SESSION['user_name']  = $customer['first_name'] . ' ' . ($customer['last_name'] ?? '');
            $_SESSION['user_email'] = $customer['email'];
            redirect('/printflow/customer/dashboard.php');
        } else {
            // Also check users table (Admin/Staff)
            $existingUser = db_query("SELECT * FROM users WHERE email = ?", 's', [$email]);
            if ($existingUser) {
                $user = $existingUser[0];
                $_SESSION['user_id']    = $user['user_id'];
                $_SESSION['user_type']  = $user['user_type'];
                $_SESSION['user_name']  = $user['first_name'] . ' ' . ($user['last_name'] ?? '');
                $_SESSION['user_email'] = $user['email'];
                
                if ($user['user_type'] === 'Admin') {
                    redirect('/printflow/admin/dashboard.php');
                } else {
                    redirect('/printflow/staff/dashboard.php');
                }
            }
            
            // New user — register as Customer
            $randomPassword = bin2hex(random_bytes(16));
            $passwordHash = password_hash($randomPassword, PASSWORD_BCRYPT);
            
            $result = db_execute(
                "INSERT INTO customers (first_name, last_name, email, password_hash, is_profile_complete) VALUES (?, ?, ?, ?, 0)",
                'ssss',
                [$firstName, $lastName, $email, $passwordHash]
            );
            
            if ($result) {
                $_SESSION['user_id']    = $result;
                $_SESSION['user_type']  = 'Customer';
                $_SESSION['user_name']  = $firstName . ' ' . $lastName;
                $_SESSION['user_email'] = $email;
                redirect('/printflow/customer/dashboard.php');
            } else {
                redirect('/printflow/public/register.php?error=' . urlencode('Registration failed. Please try again.'));
            }
        }
        break;

    default:
        redirect('/printflow/public/login.php');
}

// ================================================================
// Helper Functions
// ================================================================

function exchangeCodeForToken($code) {
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Google token exchange failed: " . $response);
        return null;
    }
    return json_decode($response, true);
}

function getGoogleUserInfo($accessToken) {
    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $accessToken"],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Google userinfo failed: " . $response);
        return null;
    }
    return json_decode($response, true);
}
?>
