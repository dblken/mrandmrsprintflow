<?php
/**
 * Manager — Customers
 * Thin wrapper: sets MANAGER_PANEL then delegates to shared admin customers page.
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('Manager');

// Customer management is intentionally hidden/disabled for managers.
header('Location: /printflow/manager/dashboard.php', true, 302);
exit();
