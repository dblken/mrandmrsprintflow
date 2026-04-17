<?php
/**
 * Manager — Inventory Ledger
 * Thin wrapper: sets MANAGER_PANEL then delegates to shared admin ledger page.
 * Managers see only transactions for their assigned branch.
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('Manager');

// Force Manager to their assigned branch (no "All Branches" option)
$manager_user = get_logged_in_user();
$manager_branch_id = (int)($manager_user['branch_id'] ?? 0);

if ($manager_branch_id <= 0) {
    die('<div style="padding:40px;text-align:center;"><h2>No Branch Assigned</h2><p>Your account is not assigned to a branch. Please contact an administrator.</p></div>');
}

// Lock the branch selection to manager's assigned branch
$_SESSION['selected_branch_id'] = $manager_branch_id;
$_GET['branch_id'] = $manager_branch_id; // Force branch filter

define('MANAGER_PANEL', true);
require __DIR__ . '/../admin/inv_transactions_ledger.php';
