<?php
/**
 * Staff Dashboard API
 * Returns real-time statistics and filtered data (JSON only).
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Keep output JSON-clean even if an include echoes/warns.
ob_start();

$__pf_debug_requested = isset($_GET['debug']) && (string)$_GET['debug'] === '1';
$__pf_debug_allowed = false; // enabled after auth check
$__pf_captured_errors = [];

set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$__pf_captured_errors) {
    $__pf_captured_errors[] = [
        'type' => (int)$errno,
        'message' => (string)$errstr,
        'file' => (string)$errfile,
        'line' => (int)$errline,
    ];
    return true; // prevent default output into the response body
});

set_exception_handler(function ($e) use (&$__pf_debug_allowed) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    $payload = ['success' => false, 'error' => 'Server error'];
    if ($__pf_debug_allowed) {
        $payload['debug'] = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
});

register_shutdown_function(function () use (&$__pf_debug_allowed, &$__pf_debug_requested, &$__pf_captured_errors) {
    $output = '';
    if (ob_get_level()) {
        $output = (string)ob_get_clean();
    }

    $err = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if ($err && in_array((int)$err['type'], $fatalTypes, true)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        $payload = ['success' => false, 'error' => 'Server error'];
        if ($__pf_debug_allowed || $__pf_debug_requested) {
            $payload['debug'] = ['fatal' => $err];
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return;
    }

    if ($output !== '') {
        $decoded = json_decode($output, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            $payload = ['success' => false, 'error' => 'Invalid API response format'];
            if ($__pf_debug_allowed || $__pf_debug_requested) {
                $payload['debug'] = [
                    'raw_output' => $output,
                    'captured' => $__pf_captured_errors,
                ];
            }
            echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            return;
        }

        if (($__pf_debug_allowed || $__pf_debug_requested) && is_array($decoded) && $__pf_captured_errors) {
            $decoded['debug'] = $decoded['debug'] ?? [];
            $decoded['debug']['captured'] = $__pf_captured_errors;
            $output = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo $output;
});

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';

if (!has_role('Staff')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$__pf_debug_allowed = $__pf_debug_requested; // staff-authenticated endpoint

$staffCtx = init_branch_context();
$staffBranchId = $staffCtx['selected_branch_id'] === 'all'
    ? (int)($_SESSION['branch_id'] ?? 1)
    : (int)$staffCtx['selected_branch_id'];

// --- Inputs ---
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$status_filter = (string)($_GET['status'] ?? '');
$search_filter = (string)($_GET['search'] ?? '');
$timeframe = (string)($_GET['timeframe'] ?? 'today');

// --- Timeframe Logic (prepared-safe) ---
$today = date('Y-m-d');

$display_label = "Today (" . date('F j, Y') . ")";
$short_label = 'Today';
$time_sql = "DATE(o.order_date) = CURDATE()";
$time_sql_no_alias = "DATE(order_date) = CURDATE()";
$time_types = '';
$time_params = [];

if ($timeframe === 'week') {
    $start = date('Y-m-d', strtotime('monday this week'));
    $end = date('Y-m-d', strtotime('sunday this week'));

    $time_sql = "DATE(o.order_date) BETWEEN ? AND ?";
    $time_sql_no_alias = "DATE(order_date) BETWEEN ? AND ?";
    $time_types = 'ss';
    $time_params = [$start, $end];

    $start_day = date('j', strtotime($start));
    $end_day = date('j', strtotime($end));
    $start_month = date('F', strtotime($start));
    $end_month = date('F', strtotime($end));
    $year = date('Y', strtotime($end));

    $display_label = ($start_month === $end_month)
        ? "This Week ($start_month $start_day-$end_day, $year)"
        : "This Week ($start_month $start_day - $end_month $end_day, $year)";
    $short_label = 'This Week';
} elseif ($timeframe === 'month') {
    $start = date('Y-m-01');
    $end = date('Y-m-t');

    $time_sql = "DATE(o.order_date) BETWEEN ? AND ?";
    $time_sql_no_alias = "DATE(order_date) BETWEEN ? AND ?";
    $time_types = 'ss';
    $time_params = [$start, $end];

    $display_label = "This Month (" . date('F Y') . ")";
    $short_label = 'This Month';
} elseif ($timeframe === 'all') {
    $time_sql = "1=1";
    $time_sql_no_alias = "1=1";
    $time_types = '';
    $time_params = [];

    $display_label = 'All Time';
    $short_label = 'All Time';
} elseif ($timeframe !== 'today') {
    // Default: last 7 days
    $start = date('Y-m-d', strtotime('-6 days'));
    $end = $today;

    $time_sql = "DATE(o.order_date) BETWEEN ? AND ?";
    $time_sql_no_alias = "DATE(order_date) BETWEEN ? AND ?";
    $time_types = 'ss';
    $time_params = [$start, $end];

    $display_label = "Last 7 Days (" . date('M j', strtotime($start)) . "-" . date('M j, Y', strtotime($end)) . ")";
    $short_label = 'Last 7 Days';
}

// --- Status filter for chart ---
$status_sql = "o.status != 'Cancelled'";
$status_types = '';
$status_params = [];
if ($status_filter !== '') {
    if ($status_filter === 'Cancelled') {
        $status_sql = "o.status = 'Cancelled'";
    } else {
        $status_sql = "o.status = ?";
        $status_types = 's';
        $status_params = [$status_filter];
    }
}

// 1) Stats
$res_products = db_query(
    "SELECT COUNT(DISTINCT o.order_id) as count
     FROM orders o
     JOIN order_items oi ON o.order_id = oi.order_id
     JOIN products p ON oi.product_id = p.product_id
     WHERE o.status = 'Completed' AND o.branch_id = ? AND o.order_type = 'product' AND {$time_sql}",
    'i' . $time_types,
    array_merge([$staffBranchId], $time_params)
);
$completed_products = (int)($res_products[0]['count'] ?? 0);

$res_custom = db_query(
    "SELECT COUNT(DISTINCT o.order_id) as count
     FROM orders o
     JOIN order_items oi ON o.order_id = oi.order_id
     LEFT JOIN job_orders jo ON oi.order_item_id = jo.order_item_id
     LEFT JOIN services s ON oi.product_id = s.service_id
     WHERE o.status = 'Completed' AND o.branch_id = ? AND {$time_sql}
       AND (s.service_id IS NOT NULL OR jo.id IS NOT NULL OR o.order_type = 'custom')",
    'i' . $time_types,
    array_merge([$staffBranchId], $time_params)
);
$completed_custom = (int)($res_custom[0]['count'] ?? 0);

$res_rev = db_query(
    "SELECT COALESCE(SUM(total_amount), 0) as total
     FROM orders
     WHERE {$time_sql_no_alias} AND status != 'Cancelled' AND branch_id = ?",
    $time_types . 'i',
    array_merge($time_params, [$staffBranchId])
);
$total_revenue = (float)($res_rev[0]['total'] ?? 0);

// 2) Chart (aggregated in one query to avoid timeouts)
$chart_labels = [];
$chart_values = [];
$chart_title = 'Revenue Trend';

if ($timeframe === 'today') {
    $chart_title = "Today's Performance (Hourly)";
    $rows = db_query(
        "SELECT HOUR(o.order_date) AS k, COALESCE(SUM(o.total_amount), 0) AS total
         FROM orders o
         WHERE o.branch_id = ? AND {$status_sql} AND DATE(o.order_date) = CURDATE()
         GROUP BY HOUR(o.order_date)
         ORDER BY k ASC",
        'i' . $status_types,
        array_merge([$staffBranchId], $status_params)
    );

    $map = [];
    foreach ($rows as $r) $map[(int)$r['k']] = (float)$r['total'];

    for ($h = 0; $h < 24; $h++) {
        $chart_labels[] = str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00';
        $chart_values[] = (float)($map[$h] ?? 0);
    }
} elseif ($timeframe === 'week') {
    $chart_title = "Weekly Trend (Mon-Sun)";
    $start = $time_params[0] ?? date('Y-m-d', strtotime('monday this week'));
    $end = $time_params[1] ?? date('Y-m-d', strtotime('sunday this week'));

    $rows = db_query(
        "SELECT DATE(o.order_date) AS d, COALESCE(SUM(o.total_amount), 0) AS total
         FROM orders o
         WHERE o.branch_id = ? AND {$status_sql} AND DATE(o.order_date) BETWEEN ? AND ?
         GROUP BY DATE(o.order_date)
         ORDER BY d ASC",
        'i' . $status_types . 'ss',
        array_merge([$staffBranchId], $status_params, [$start, $end])
    );

    $map = [];
    foreach ($rows as $r) $map[(string)$r['d']] = (float)$r['total'];

    $monday_ts = strtotime($start);
    for ($i = 0; $i < 7; $i++) {
        $d = date('Y-m-d', strtotime("+$i day", $monday_ts));
        $chart_labels[] = date('D', strtotime($d));
        $chart_values[] = (float)($map[$d] ?? 0);
    }
} elseif ($timeframe === 'month') {
    $chart_title = "Monthly Performance (Daily)";
    $start = $time_params[0] ?? date('Y-m-01');
    $end = $time_params[1] ?? date('Y-m-t');

    $rows = db_query(
        "SELECT DATE(o.order_date) AS d, COALESCE(SUM(o.total_amount), 0) AS total
         FROM orders o
         WHERE o.branch_id = ? AND {$status_sql} AND DATE(o.order_date) BETWEEN ? AND ?
         GROUP BY DATE(o.order_date)
         ORDER BY d ASC",
        'i' . $status_types . 'ss',
        array_merge([$staffBranchId], $status_params, [$start, $end])
    );

    $map = [];
    foreach ($rows as $r) $map[(string)$r['d']] = (float)$r['total'];

    $days_in_month = (int)date('t', strtotime($start));
    for ($day = 1; $day <= $days_in_month; $day++) {
        $d = date('Y-m-', strtotime($start)) . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
        $chart_labels[] = $day;
        $chart_values[] = (float)($map[$d] ?? 0);
    }
} elseif ($timeframe === 'all') {
    $chart_title = "All Time (Last 30 Days Trend)";
    $start = date('Y-m-d', strtotime('-29 days'));
    $end = $today;

    $rows = db_query(
        "SELECT DATE(o.order_date) AS d, COALESCE(SUM(o.total_amount), 0) AS total
         FROM orders o
         WHERE o.branch_id = ? AND {$status_sql} AND DATE(o.order_date) BETWEEN ? AND ?
         GROUP BY DATE(o.order_date)
         ORDER BY d ASC",
        'i' . $status_types . 'ss',
        array_merge([$staffBranchId], $status_params, [$start, $end])
    );

    $map = [];
    foreach ($rows as $r) $map[(string)$r['d']] = (float)$r['total'];

    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $chart_labels[] = date('M j', strtotime($d));
        $chart_values[] = (float)($map[$d] ?? 0);
    }
} else {
    // Default: last 7 days
    $chart_title = "Last 7 Days (Trend)";
    $start = $time_params[0] ?? date('Y-m-d', strtotime('-6 days'));
    $end = $time_params[1] ?? $today;

    $rows = db_query(
        "SELECT DATE(o.order_date) AS d, COALESCE(SUM(o.total_amount), 0) AS total
         FROM orders o
         WHERE o.branch_id = ? AND {$status_sql} AND DATE(o.order_date) BETWEEN ? AND ?
         GROUP BY DATE(o.order_date)
         ORDER BY d ASC",
        'i' . $status_types . 'ss',
        array_merge([$staffBranchId], $status_params, [$start, $end])
    );

    $map = [];
    foreach ($rows as $r) $map[(string)$r['d']] = (float)$r['total'];

    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $chart_labels[] = date('D', strtotime($d));
        $chart_values[] = (float)($map[$d] ?? 0);
    }
}

// 3) Top sales/services
$top_services = db_query(
    "SELECT
        TRIM(REPLACE(REPLACE(REPLACE(COALESCE(jo.service_type, s.name, p.name), ' Printing', ''), ' (Print/Cut)', ''), ' Print', '')) as name,
        COUNT(DISTINCT oi.order_item_id) as order_count
     FROM order_items oi
     JOIN orders o ON oi.order_id = o.order_id
     LEFT JOIN job_orders jo ON oi.order_item_id = jo.order_item_id
     LEFT JOIN products p ON (oi.product_id = p.product_id AND o.order_type = 'product')
     LEFT JOIN services s ON ((oi.product_id = s.service_id AND o.order_type = 'custom') OR (jo.service_type = s.name AND s.status = 'Activated'))
     WHERE o.branch_id = ? AND {$time_sql}
       AND (
           (p.product_id IS NOT NULL AND p.status = 'Activated')
           OR (s.service_id IS NOT NULL AND s.status = 'Activated')
           OR (jo.id IS NOT NULL AND EXISTS (SELECT 1 FROM services WHERE name = jo.service_type AND status = 'Activated'))
       )
     GROUP BY name
     ORDER BY order_count DESC
     LIMIT 10",
    'i' . $time_types,
    array_merge([$staffBranchId], $time_params)
);

// 4) Recent orders list (filters + pagination)
$sql_cond = " WHERE o.branch_id = ?";
$params = [$staffBranchId];
$types = "i";

if ($status_filter !== '') {
    $sql_cond .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($timeframe !== 'all') {
    $sql_cond .= " AND " . $time_sql;
    $params = array_merge($params, $time_params);
    $types .= $time_types;
}

if ($search_filter !== '') {
    $sql_cond .= " AND (o.order_id LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
    $lk = '%' . $search_filter . '%';
    $params[] = $lk;
    $params[] = $lk;
    $types .= "ss";
}

$res_rows = db_query(
    "SELECT COUNT(*) as count FROM orders o LEFT JOIN customers c ON o.customer_id = c.customer_id" . $sql_cond,
    $types,
    $params
);
$total_rows = (int)($res_rows[0]['count'] ?? 0);

$orders = db_query(
    "SELECT o.order_id,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            (SELECT COALESCE(p.name, 'Custom Service')
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.product_id
             WHERE oi.order_id = o.order_id
             LIMIT 1) as service_type,
            o.order_date,
            o.total_amount,
            o.status
     FROM orders o
     LEFT JOIN customers c ON o.customer_id = c.customer_id
     {$sql_cond}
     ORDER BY o.order_date DESC
     LIMIT {$limit} OFFSET {$offset}",
    $types,
    $params
);

$peso = '₱';
foreach ($orders as &$order) {
    $order['status_html'] = function_exists('status_badge')
        ? status_badge($order['status'], 'order')
        : ('<span class="status-badge">' . $order['status'] . '</span>');
    $order['formatted_date'] = date('M d, Y', strtotime((string)$order['order_date']));
    $order['formatted_total'] = $peso . number_format((float)$order['total_amount'], 2);
    $order['manage_url'] = "customizations.php?order_id={$order['order_id']}&status=" . urlencode((string)$order['status']) . "&job_type=ORDER";
}
unset($order);

echo json_encode([
    'success' => true,
    'stats' => [
        'revenue' => $total_revenue,
        'formatted_revenue' => $peso . number_format($total_revenue, 2),
        'completed_products' => $completed_products,
        'completed_custom' => $completed_custom,
    ],
    'chart' => [
        'labels' => $chart_labels,
        'values' => $chart_values,
        'title' => $chart_title,
    ],
    'top_services' => $top_services ?: [],
    'orders' => $orders ?: [],
    'pagination' => [
        'current_page' => $page,
        'total_pages' => (int)ceil($total_rows / $limit),
        'total_rows' => $total_rows,
    ],
    'timeframe_label' => $display_label,
    'short_label' => $short_label,
], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

