<?php
/**
 * Logout handler — destroys session fully and redirects to home.
 */
require_once __DIR__ . '/../includes/session_manager.php';

SessionManager::start();

// Log the logout action before destroying session data
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../includes/db.php';
    $uid = (int)$_SESSION['user_id'];
    $utype = $_SESSION['user_type'] ?? 'Unknown';
    try {
        db_execute("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'Logout', 'User logged out', NOW())", 'i', [$uid]);
    } catch (Throwable $e) {
        // Logging failure must never block logout
    }
}

SessionManager::destroy();
SessionManager::setNoCacheHeaders();

// Load config for correct redirect path
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}
$base_path = defined('BASE_PATH') ? BASE_PATH : '';

header('Location: ' . $base_path . '/');
exit();
