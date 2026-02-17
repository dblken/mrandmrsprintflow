<?php
/**
 * Pending Staff Check
 * Include this at the top of staff pages (except profile.php) to redirect 
 * pending staff to the profile page to complete their information.
 */

// Check if the current staff member has a Pending status
if (isset($_SESSION['user_status']) && $_SESSION['user_status'] === 'Pending') {
    // Re-verify from database in case admin has approved since login
    $user_id = get_user_id();
    $check = db_query("SELECT status FROM users WHERE user_id = ?", 'i', [$user_id]);
    if (!empty($check) && $check[0]['status'] === 'Pending') {
        redirect('/printflow/staff/profile.php');
        exit;
    } else {
        // Admin approved — update session
        $_SESSION['user_status'] = $check[0]['status'] ?? 'Activated';
    }
}
