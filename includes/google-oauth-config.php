<?php
/**
 * Google OAuth 2.0 config for Sign in with Google.
 *
 * 1. Go to https://console.cloud.google.com/apis/credentials
 * 2. Create OAuth 2.0 Client ID (Web application)
 * 3. Add Authorized redirect URI: https://YOUR_DOMAIN/google-auth/
 *    (for local: http://localhost/google-auth/)
 * 4. Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in your .env file or server environment
 */

// Production Google OAuth credentials
$production_google_client_id = '146218015828-nq8mvkqbs5mnmscgtqchjhoeqd8pnm7l.apps.googleusercontent.com';

if (!defined('GOOGLE_CLIENT_ID')) {
    $client_id = getenv('GOOGLE_CLIENT_ID') ?: '';
    // Use production client ID if no env var set and we're on production domain
    if (empty($client_id) && isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'mrandmrsprintflow.com') !== false) {
        $client_id = $production_google_client_id;
    }
    define('GOOGLE_CLIENT_ID', $client_id);
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
}
