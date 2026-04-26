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
// Set Authorized redirect URI to: http://localhost<?php echo BASE_PATH; ?>/public/google_auth.php?action=callback
// ================================================================
define('GOOGLE_CLIENT_ID',     'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI',  'http://localhost<?php echo BASE_PATH; ?>/public/google_auth.php?action=callback');

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
            redirect(BASE_PATH . '/public/login.php?error=' . urlencode('Google authentication cancelled.'));
        }
        
        // Exchange code for access token
        $tokenData = exchangeCodeForToken($code);
        if (!$tokenData) {
            redirect(BASE_PATH . '/public/login.php?error=' . urlencode('Google authentication failed.'));
        }
        
        // Get user info from Google
        $googleUser = getGoogleUserInfo($tokenData['access_token']);
        if (!$googleUser || empty($googleUser['email'])) {
            redirect(BASE_PATH . '/public/login.php?error=' . urlencode('Could not retrieve Google profile.'));
        }
        
        $email = $googleUser['email'];
        $name  = $googleUser['name'] ?? 'Google User';
        $parts = explode(' ', $name, 2);
        $firstName = $parts[0] ?? 'User';
        $lastName  = $parts[1] ?? '';
        unset($_SESSION['google_auth_intent']);

        $loginResult = login_customer_by_google($email, $firstName, $lastName);
        if (!empty($loginResult['success'])) {
            $dest = $loginResult['redirect'] ?? (BASE_PATH . '/customer/services.php');
            header('Location: ' . $dest);
            exit;
        }
        redirect(BASE_PATH . '/public/login.php?error=' . urlencode($loginResult['message'] ?? 'Google sign-in failed. Please try again.'));
        break;

    default:
        redirect(BASE_PATH . '/public/login.php');
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

