<?php
/**
 * Google OAuth: redirect to Google for login, then callback to find/create customer and log in.
 */
require_once __DIR__ . '/../includes/google-oauth-config.php';
require_once __DIR__ . '/../includes/auth.php';

$base_url = '/printflow';
$redirect_uri = $base_url . '/google-auth/';
$client_id = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
$client_secret = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '';

if (empty($client_id) || empty($client_secret)) {
    header('Location: ' . $base_url . '/?auth_modal=login&error=' . urlencode('Google sign-in is not configured.'));
    exit;
}

if (is_logged_in()) {
    $ut = get_user_type();
    if ($ut === 'Admin') header('Location: ' . $base_url . '/admin/dashboard.php');
    elseif ($ut === 'Staff') header('Location: ' . $base_url . '/staff/dashboard.php');
    else header('Location: ' . $base_url . '/customer/services.php');
    exit;
}

// Build redirect URI with current host for OAuth
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$redirect_uri_full = $scheme . '://' . $host . $redirect_uri;

$code = $_GET['code'] ?? '';
$error_param = $_GET['error'] ?? '';

if ($error_param === 'access_denied') {
    header('Location: ' . $base_url . '/');
    exit;
}
if ($error_param) {
    header('Location: ' . $base_url . '/?auth_modal=login&error=' . urlencode('Google sign-in was cancelled or failed.'));
    exit;
}

// Step 1: No code -> redirect to Google
if ($code === '') {
    $state = bin2hex(random_bytes(16));
    // Store state in a SameSite=Lax cookie so it survives the redirect back from Google.
    // The main session cookie uses SameSite=Strict and may not be sent on OAuth callback.
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('oauth_state', $state, [
        'expires' => time() + 600, // 10 min
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['google_oauth_state'] = $state;
    $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri_full,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'offline',
        'prompt' => 'consent'
    ]);
    header('Location: ' . $url);
    exit;
}

// Step 2: Exchange code for tokens
$state_sent = $_GET['state'] ?? '';
$state_from_cookie = $_COOKIE['oauth_state'] ?? '';
// Accept state from either session (if available) or cookie (SameSite=Lax survives OAuth redirect)
$state_valid = false;
if ($state_sent !== '') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['google_oauth_state']) && hash_equals($_SESSION['google_oauth_state'], $state_sent)) {
        $state_valid = true;
    } elseif ($state_from_cookie !== '' && hash_equals($state_from_cookie, $state_sent)) {
        $state_valid = true;
    }
}
// Clear the state cookie (one-time use)
setcookie('oauth_state', '', ['expires' => time() - 3600, 'path' => '/']);
if (!$state_valid) {
    if (session_status() === PHP_SESSION_ACTIVE) unset($_SESSION['google_oauth_state']);
    header('Location: ' . $base_url . '/?auth_modal=login&error=' . urlencode('Invalid state. Please try again.'));
    exit;
}
if (session_status() === PHP_SESSION_ACTIVE) unset($_SESSION['google_oauth_state']);

$token_url = 'https://oauth2.googleapis.com/token';
$token_body = [
    'code' => $code,
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri' => $redirect_uri_full,
    'grant_type' => 'authorization_code'
];
$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($token_body)
    ]
]);
$token_response = @file_get_contents($token_url, false, $ctx);
if ($token_response === false) {
    header('Location: ' . $base_url . '/?auth_modal=login&error=' . urlencode('Could not connect to Google. Try again.'));
    exit;
}
$token_data = json_decode($token_response, true);
if (empty($token_data['access_token'])) {
    header('Location: ' . $base_url . '/?auth_modal=login&error=' . urlencode('Google sign-in failed. Try again.'));
    exit;
}

// Get user info (email, name)
$userinfo_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . urlencode($token_data['access_token']);
$user_response = @file_get_contents($userinfo_url);
if ($user_response === false) {
    header('Location: ' . $base_url . '/?auth_modal=login&error=' . urlencode('Could not get profile from Google.'));
    exit;
}
$user = json_decode($user_response, true);
if (empty($user['email'])) {
    header('Location: ' . $base_url . '/?auth_modal=login&error=' . urlencode('Google did not provide an email.'));
    exit;
}

$email = $user['email'];
$first_name = $user['given_name'] ?? '';
$last_name = $user['family_name'] ?? '';

$result = login_customer_by_google($email, $first_name, $last_name);
if ($result['success']) {
    // Use absolute URL so redirect works reliably after OAuth callback
    $redirect_url = $scheme . '://' . $host . $result['redirect'];
    header('Location: ' . $redirect_url);
    exit;
}
header('Location: ' . $base_url . '/?auth_modal=login&error=' . urlencode($result['message']));
exit;

