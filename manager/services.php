<?php
/**
 * Manager — Services (shared admin page).
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('Manager');
define('MANAGER_PANEL', true);
require __DIR__ . '/../admin/services_management.php';
