<?php
/**
 * Google OAuth 2.0 config for Sign in with Google.
 *
 * 1. Go to https://console.cloud.google.com/apis/credentials
 * 2. Create OAuth 2.0 Client ID (Web application)
 * 3. Add Authorized redirect URI: https://YOUR_DOMAIN/printflow/google-auth/
 *    (for local: http://localhost/printflow/google-auth/)
 * 4. Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET below (or define them before including this file)
 */
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', '146218015828-uqbdobsfhal777338bheoiabda8fgr7n.apps.googleusercontent.com');
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', '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');
}
