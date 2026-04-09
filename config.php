<?php
/**
 * PrintFlow Configuration
 * This file handles environment-specific settings
 */

// Load environment variables from .env file (local development)
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!getenv($name)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Detect environment
$is_production = (
    isset($_SERVER['HTTP_HOST']) && 
    (strpos($_SERVER['HTTP_HOST'], 'mrandmrsprintflow.com') !== false ||
     strpos($_SERVER['HTTP_HOST'], 'hostinger') !== false)
);

// Set base path based on environment
if ($is_production) {
    // Production: domain root
    define('BASE_PATH', '');
    define('BASE_URL', '');
} else {
    // Local development: /printflow subdirectory
    define('BASE_PATH', '/printflow');
    define('BASE_URL', '/printflow');
}

// Asset paths
define('ASSET_PATH', BASE_PATH . '/public/assets');
define('UPLOAD_PATH', BASE_PATH . '/uploads');

// Full URLs for absolute links
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('SITE_URL', $protocol . '://' . $host . BASE_PATH);
