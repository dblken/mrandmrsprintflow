<?php
/**
 * Gate: require customers to finish their profile before ordering.
 * Include this after require_role('Customer') and before ID verification gates.
 */
if (get_user_type() === 'Customer' && !is_profile_complete()) {
    $current_path = $_SERVER['REQUEST_URI'] ?? '';
    $return_to = '';
    if (is_string($current_path) && $current_path !== '') {
        $path = parse_url($current_path, PHP_URL_PATH);
        if (is_string($path) && preg_match('#^(/[^/?#]+)?/customer/[A-Za-z0-9_\-/]+\.php$#', $path)) {
            $query = parse_url($current_path, PHP_URL_QUERY);
            $return_to = $path . ($query ? '?' . $query : '');
        }
    }

    if ($return_to !== '') {
        $_SESSION['profile_return_after_complete'] = $return_to;
    }

    $target = rtrim(AUTH_REDIRECT_BASE, '/') . '/customer/profile.php?complete_profile=1';
    if ($return_to !== '') {
        $target .= '&return=' . rawurlencode($return_to);
    }

    header('Location: ' . $target, true, 302);
    exit;
}
