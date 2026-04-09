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
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
}
