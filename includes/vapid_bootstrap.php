<?php
/**
 * Load VAPID configuration.
 *
 * Prefer the deploy-time config file when present, but fall back to the
 * checked-in keypair so push setup still works if the config file is missing.
 */
function printflow_vapid_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $fallback = [
        'public_key'  => 'BNWdrGY6s82PIxJrzc0hN9Uq-IL0DvwRbUzV2EnpPYDM-S0-D21B6kb6Cpt_7_6M2daUG-dDVzqHi8Mx7sLESPk',
        'private_key' => "-----BEGIN PRIVATE KEY-----\nMIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgY/p7coWydEEXvXWg\nrJj9tWZ9Bs4JZG5oaosdyr6+KvehRANCAATVnaxmOrPNjyMSa83NITfVKviC9A78\nEW1M1dhJ6T2AzPktPg9tQepG+gqbf+/+jNnWlBvnQ1c6h4vDMe7CxEj5\n-----END PRIVATE KEY-----\n",
        'subject'     => 'mailto:support@printflow.com',
    ];

    $cfgFile = __DIR__ . '/vapid_config.php';
    if (file_exists($cfgFile)) {
        $loaded = require $cfgFile;
        if (is_array($loaded)) {
            $cfg = array_merge($fallback, $loaded);
            return $cfg;
        }
    }

    $cfg = $fallback;
    return $cfg;
}
