<?php
/**
 * AJAX: Revenue Chart Data
 * Returns JSON: { labels, revenue, orders }
 * ?period=today|weekly|monthly|6months|yearly
 * ?year=YYYY  (optional, for monthly/6months/yearly)
 * ?month=M    (optional, 1-12, for monthly)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Check admin session (auth.php already started session)
if (empty($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin') {
    echo json_encode(['error' => 'Unauthorized', 'labels' => [], 'revenue' => [], 'orders' => []]);
    exit;
}

$period = $_GET['period'] ?? 'monthly';
$year   = max(2020, min(2030, (int)($_GET['year'] ?? date('Y'))));
$month  = max(1, min(12, (int)($_GET['month'] ?? date('n'))));

// Branch filter — safe integer cast prevents SQL injection
$branch_raw   = $_GET['branch_id'] ?? 'all';
$branch_int   = ($branch_raw !== 'all' && ctype_digit((string)$branch_raw)) ? (int)$branch_raw : null;
$oFilter      = $branch_int ? " AND branch_id = $branch_int" : '';   // for orders table
$jFilter      = $branch_int ? " AND branch_id = $branch_int" : '';   // for job_orders table

try {
    switch ($period) {
        case 'today':
            $rows = db_query(
                "SELECT DATE_FORMAT(order_date, '%H:00') as label,
                        COALESCE(SUM(total_amount), 0) as revenue,
                        COUNT(*) as orders
                 FROM (
                     SELECT order_date, total_amount FROM orders WHERE payment_status = 'Paid'$oFilter
                     UNION ALL
                     SELECT created_at as order_date, amount_paid as total_amount FROM job_orders WHERE payment_status = 'PAID'$jFilter
                 ) combined
                 WHERE DATE(order_date) = CURDATE()
                 GROUP BY HOUR(order_date), DATE_FORMAT(order_date, '%H:00')
                 ORDER BY HOUR(order_date)"
            ) ?: [];
            $rows = _fillHours($rows);
            break;

        case 'weekly':
            $rows = db_query(
                "SELECT DATE_FORMAT(order_date, '%a %d') as label,
                        COALESCE(SUM(total_amount), 0) as revenue,
                        COUNT(*) as orders
                 FROM (
                     SELECT order_date, total_amount FROM orders WHERE payment_status = 'Paid'$oFilter
                     UNION ALL
                     SELECT created_at as order_date, amount_paid as total_amount FROM job_orders WHERE payment_status = 'PAID'$jFilter
                 ) combined
                 WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                 GROUP BY DATE(order_date), DATE_FORMAT(order_date, '%a %d')
                 ORDER BY DATE(order_date)"
            ) ?: [];
            $rows = _fillWeek($rows);
            break;

        case 'monthly':
            $rows = db_query(
                "SELECT DATE(order_date) as dt, COALESCE(SUM(total_amount), 0) as revenue, COUNT(*) as orders
                 FROM (
                     SELECT order_date, total_amount FROM orders WHERE payment_status = 'Paid'$oFilter
                     UNION ALL
                     SELECT created_at as order_date, amount_paid as total_amount FROM job_orders WHERE payment_status = 'PAID'$jFilter
                 ) combined
                 WHERE MONTH(order_date) = $month AND YEAR(order_date) = $year
                 GROUP BY DATE(order_date)
                 ORDER BY dt"
            ) ?: [];
            $rows = _fillMonth($rows, $year, $month);
            break;

        case '6months':
            $rows = db_query(
                "SELECT YEAR(order_date) as y, MONTH(order_date) as m,
                        DATE_FORMAT(order_date, '%b %Y') as label,
                        COALESCE(SUM(total_amount), 0) as revenue,
                        COUNT(*) as orders
                 FROM (
                     SELECT order_date, total_amount FROM orders WHERE payment_status = 'Paid'$oFilter
                     UNION ALL
                     SELECT created_at as order_date, amount_paid as total_amount FROM job_orders WHERE payment_status = 'PAID'$jFilter
                 ) combined
                 WHERE order_date >= DATE_SUB(CONCAT($year,'-',$month,'-01'), INTERVAL 5 MONTH)
                   AND order_date <= LAST_DAY(CONCAT($year,'-',$month,'-01'))
                 GROUP BY YEAR(order_date), MONTH(order_date), DATE_FORMAT(order_date, '%b %Y')
                 ORDER BY y, m"
            ) ?: [];
            $rows = _fill6Months($rows, $year, $month);
            break;

        case 'yearly':
            $rows = db_query(
                "SELECT MONTH(order_date) as m, DATE_FORMAT(order_date, '%b') as label,
                        COALESCE(SUM(total_amount), 0) as revenue,
                        COUNT(*) as orders
                 FROM (
                     SELECT order_date, total_amount FROM orders WHERE payment_status = 'Paid'$oFilter
                     UNION ALL
                     SELECT created_at as order_date, amount_paid as total_amount FROM job_orders WHERE payment_status = 'PAID'$jFilter
                 ) combined
                 WHERE YEAR(order_date) = $year
                 GROUP BY MONTH(order_date), DATE_FORMAT(order_date, '%b')
                 ORDER BY m"
            ) ?: [];
            $rows = _fillYear($rows, $year);
            break;

        default:
            echo json_encode(['error' => 'Invalid period', 'labels' => [], 'revenue' => [], 'orders' => []]);
            exit;
    }

    echo json_encode([
        'labels'  => array_map(fn($r) => $r['label'],   $rows),
        'revenue' => array_map(fn($r) => (float)$r['revenue'], $rows),
        'orders'  => array_map(fn($r) => (int)$r['orders'],    $rows),
    ]);

} catch (Throwable $e) {
    error_log('api_revenue_chart error: ' . $e->getMessage());
    echo json_encode([
        'error'   => 'Server error',
        'message' => $e->getMessage(),
        'labels'  => [],
        'revenue' => [],
        'orders'  => []
    ]);
}

function _fillHours($rows) {
    $map = [];
    foreach ($rows as $r) { $map[$r['label']] = $r; }
    $out = [];
    for ($h = 0; $h < 24; $h++) {
        $lbl = sprintf('%02d:00', $h);
        $out[] = $map[$lbl] ?? ['label' => $lbl, 'revenue' => 0, 'orders' => 0];
    }
    return $out;
}

function _fillWeek($rows) {
    $map = [];
    foreach ($rows as $r) { $map[$r['label']] = $r; }
    $out = [];
    for ($i = 0; $i < 7; $i++) {
        $ts = strtotime("today - " . (6 - $i) . " days");
        $d = date('D', $ts) . ' ' . date('d', $ts); // Match MySQL '%a %d'
        $out[] = $map[$d] ?? ['label' => $d, 'revenue' => 0, 'orders' => 0];
    }
    return $out;
}

function _fillMonth($rows, $year, $month) {
    $map = [];
    foreach ($rows as $r) {
        $day = (int)date('j', strtotime($r['dt']));
        $map[$day] = ['label' => date('M j', strtotime($r['dt'])), 'revenue' => (float)$r['revenue'], 'orders' => (int)$r['orders']];
    }
    $days = (int)date('t', strtotime("$year-$month-01"));
    $out = [];
    for ($d = 1; $d <= $days; $d++) {
        $lbl = date('M j', strtotime("$year-$month-$d"));
        $out[] = $map[$d] ?? ['label' => $lbl, 'revenue' => 0, 'orders' => 0];
    }
    return $out;
}

function _fill6Months($rows, $year, $month) {
    $map = [];
    foreach ($rows as $r) { $map[$r['y'] . '-' . $r['m']] = $r; }
    $out = [];
    $dt = strtotime("$year-$month-01 -5 months");
    for ($i = 0; $i < 6; $i++) {
        $m = (int)date('n', $dt);
        $y = (int)date('Y', $dt);
        $key = "$y-$m";
        $out[] = $map[$key] ?? ['label' => date('M Y', $dt), 'revenue' => 0, 'orders' => 0];
        $dt = strtotime('+1 month', $dt);
    }
    return $out;
}

function _fillYear($rows, $year) {
    $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $map = [];
    foreach ($rows as $r) { $map[(int)$r['m']] = $r; }
    $out = [];
    for ($m = 1; $m <= 12; $m++) {
        $out[] = $map[$m] ?? ['label' => $months[$m-1], 'revenue' => 0, 'orders' => 0];
    }
    return $out;
}
