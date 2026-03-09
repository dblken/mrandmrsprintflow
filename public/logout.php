<?php
/**
 * Logout handler — destroys session fully and redirects to home.
 */
require_once __DIR__ . '/../includes/session_manager.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the logout action before destroying session data
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../includes/db.php';
    $uid = (int)$_SESSION['user_id'];
    $utype = $_SESSION['user_type'] ?? 'Unknown';
    try {
        $conn->query("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES ({$uid}, 'Logout', 'User logged out', NOW())");
    } catch (Throwable $e) {
        // Logging failure must never block logout
    }
}

SessionManager::destroy();
SessionManager::setNoCacheHeaders();
header('Location: /printflow/');
exit();
