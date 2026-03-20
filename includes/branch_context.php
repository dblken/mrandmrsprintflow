<?php
/**
 * Branch Context System
 * PrintFlow - Multi-Branch Filtering
 *
 * Provides helpers for:
 *   - Determining which branches a user can see
 *   - Validating and normalising the selected branch session variable
 *   - Building safe SQL WHERE fragments for branch filtering
 *   - Rendering branch badges
 *
 * Usage:
 *   require_once __DIR__ . '/../includes/branch_context.php';
 *   $ctx = init_branch_context();           // resolve + store in session
 *   [$bSql, $bTypes, $bParams] = branch_where_parts('o', $ctx['selected_branch_id']);
 *
 * Session key: $_SESSION['selected_branch_id']  — 'all' | int
 */

if (!defined('BRANCH_CONTEXT_LOADED')) {
    define('BRANCH_CONTEXT_LOADED', true);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/** ─────────────────────────────────────────────────────
 *  Branch colours used for badges
 * ──────────────────────────────────────────────────── */
const BRANCH_BADGE_COLORS = [
    1 => ['bg' => '#dbeafe', 'text' => '#1d4ed8', 'label' => 'Main'],
    2 => ['bg' => '#dcfce7', 'text' => '#15803d', 'label' => 'QC'],
    3 => ['bg' => '#fef3c7', 'text' => '#b45309', 'label' => 'Makati'],
    4 => ['bg' => '#f3e8ff', 'text' => '#7e22ce', 'label' => 'BGC'],
    5 => ['bg' => '#ffedd5', 'text' => '#c2410c', 'label' => 'Ortigas'],
];

/** ─────────────────────────────────────────────────────
 *  1. get_all_branches()
 *  Returns all active branches from the DB.
 * ──────────────────────────────────────────────────── */
function get_all_branches(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $cache = db_query("SELECT id, branch_name FROM branches ORDER BY id ASC") ?: [];
    } catch (Exception $e) {
        $cache = [];
    }
    return $cache;
}

/** ─────────────────────────────────────────────────────
 *  2. get_user_allowed_branches($user_id, $role)
 *
 *  Admin  → 'all'
 *  Staff  → [branch_id] (single)
 *  Manager → [branch_id, ...]  (multiple)
 * ──────────────────────────────────────────────────── */
function get_user_allowed_branches(int $user_id, string $role) {
    if ($role === 'Admin') {
        return 'all';
    }

    // Look up assigned branch(es) from users table
    try {
        $row = db_query(
            "SELECT branch_id FROM users WHERE user_id = ?",
            'i', [$user_id]
        );
        $branch_id = (int)($row[0]['branch_id'] ?? 0);
        if ($branch_id > 0) {
            return [$branch_id];
        }
    } catch (Exception $e) {
        // fallthrough
    }

    // Fallback: return first available branch
    $branches = get_all_branches();
    if (!empty($branches)) {
        return [(int)$branches[0]['id']];
    }
    return [1];
}

/** ─────────────────────────────────────────────────────
 *  3. normalize_selected_branch($selected, $allowed, $requires_branch)
 *
 *  Ensures the chosen branch is valid for this user/page.
 *
 *  Rules:
 *   - If allowed === 'all' and page doesn't require branch → 'all' ok
 *   - If allowed === 'all' and page requires branch → return first available branch id
 *   - If allowed is array:
 *       - 'all' selected → return first allowed branch
 *       - selected not in allowed → fallback to first allowed
 *       - otherwise → return selected (int)
 * ──────────────────────────────────────────────────── */
function normalize_selected_branch($selected, $allowed, bool $requires_branch = false) {
    if ($allowed === 'all') {
        if ($requires_branch) {
            // Force a specific branch; default to first available
            $branches = get_all_branches();
            return !empty($branches) ? (int)$branches[0]['id'] : 1;
        }
        // Admin on an analytics page → can keep 'all'
        return ($selected === 'all' || $selected === null) ? 'all' : (int)$selected;
    }

    // Restricted user
    if ($selected === 'all' || $selected === null) {
        return (int)$allowed[0];
    }
    $selected_int = (int)$selected;
    if (in_array($selected_int, array_map('intval', $allowed), true)) {
        return $selected_int;
    }
    return (int)$allowed[0];
}

/** ─────────────────────────────────────────────────────
 *  4. init_branch_context(bool $page_requires_branch = false)
 *
 *  Call once at the top of each admin page.
 *  Handles GET ?branch_id= switch, stores in session,
 *  returns the resolved context array.
 *
 *  Returns:
 *   [
 *     'selected_branch_id' => 'all' | int,
 *     'allowed_branches'   => 'all' | int[],
 *     'branches_list'      => [...],   // all branches for the dropdown
 *     'branch_name'        => string,
 *   ]
 * ──────────────────────────────────────────────────── */
function init_branch_context(bool $page_requires_branch = false): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $user_id   = (int)($_SESSION['user_id'] ?? 0);
    $role      = $_SESSION['user_type'] ?? 'Staff';
    $allowed   = get_user_allowed_branches($user_id, $role);
    $branches  = get_all_branches();

    // Handle explicit switch from GET/POST
    if (isset($_GET['branch_id'])) {
        $switch = $_GET['branch_id'] === 'all' ? 'all' : (int)$_GET['branch_id'];
        $_SESSION['selected_branch_id'] = $switch;
    }

    $raw_selected = $_SESSION['selected_branch_id'] ?? 'all';
    $selected = normalize_selected_branch($raw_selected, $allowed, $page_requires_branch);
    $_SESSION['selected_branch_id'] = $selected;

    // Resolve human-readable name
    if ($selected === 'all') {
        $branch_name = 'All Branches';
    } else {
        $branch_name = 'Branch';
        foreach ($branches as $b) {
            if ((int)$b['id'] === (int)$selected) {
                $branch_name = $b['branch_name'];
                break;
            }
        }
    }

    return [
        'selected_branch_id' => $selected,
        'allowed_branches'   => $allowed,
        'branches_list'      => $branches,
        'branch_name'        => $branch_name,
    ];
}

/** ─────────────────────────────────────────────────────
 *  5. branch_where_parts($tableAlias, $branchContext)
 *
 *  Returns [$sqlFragment, $types, $params]
 *
 *  Example:
 *   [$ws, $wt, $wp] = branch_where_parts('o', 3);
 *   $sql .= $ws;   // " AND o.branch_id = ? "
 *   $types .= $wt; // "i"
 *   $params[] = ...; // merge $wp
 *
 *  If $branchContext === 'all':
 *   Returns ['', '', []]
 * ──────────────────────────────────────────────────── */
function branch_where_parts(string $tableAlias, $branchContext): array {
    if ($branchContext === 'all') {
        return ['', '', []];
    }
    $branch_id = (int)$branchContext;
    return [" AND {$tableAlias}.branch_id = ? ", 'i', [$branch_id]];
}

/**
 * Convenience wrapper — returns just the SQL fragment and appends
 * to the caller's flat $types string and $params array by reference.
 *
 * Usage:
 *   $sql .= branch_where('o', $ctx, $types, $params);
 */
function branch_where(string $tableAlias, $branchContext, string &$types, array &$params): string {
    [$sql, $t, $p] = branch_where_parts($tableAlias, $branchContext);
    $types  .= $t;
    $params  = array_merge($params, $p);
    return $sql;
}

/** ─────────────────────────────────────────────────────
 *  6. get_branch_badge_html($branch_id, $branch_name)
 *
 *  Returns the HTML for a colour-coded branch badge.
 * ──────────────────────────────────────────────────── */
function get_branch_badge_html(?int $branch_id, string $branch_name = ''): string {
    if (!$branch_id) return '';

    $colors = BRANCH_BADGE_COLORS[$branch_id] ?? ['bg' => '#f3f4f6', 'text' => '#374151', 'label' => ''];
    $display = htmlspecialchars($branch_name ?: $colors['label'] ?: "Branch #{$branch_id}");
    $bg      = htmlspecialchars($colors['bg']);
    $fg      = htmlspecialchars($colors['text']);

    return "<span class=\"branch-badge\" style=\"background:{$bg};color:{$fg};padding:3px 10px;border-radius:9999px;"
         . "font-size:11px;font-weight:600;white-space:nowrap;\">{$display}</span>";
}

/** ─────────────────────────────────────────────────────
 *  7. render_branch_context_banner($branchName)
 *
 *  Prints the "Viewing: ___" header banner.
 * ──────────────────────────────────────────────────── */
function render_branch_context_banner(string $branchName): void {
    // Hidden per user request to clean up UI
}
