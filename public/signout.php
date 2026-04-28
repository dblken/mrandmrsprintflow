<?php
/**
 * Logout Handler
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
SessionManager::start();

if (function_exists('printflow_clear_remember_token')) {
    printflow_clear_remember_token();
}

SessionManager::destroy();
SessionManager::setNoCacheHeaders();

header("Location: /");
exit();
