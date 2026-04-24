<?php
/**
 * Logout Handler
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/session_manager.php';
SessionManager::start();

SessionManager::destroy();
SessionManager::setNoCacheHeaders();

header("Location: /");
exit();
