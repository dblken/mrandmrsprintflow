<?php
/**
 * Admin Reports & Analytics — PrintFlow
 * Modern BI dashboard with strict branch filtering + predictive analytics.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';
require_once __DIR__ . '/../includes/branch_ui.php';

require_role(['Admin', 'Manager']);

$reports_href_base = rtrim(AUTH_REDIRECT_BASE, '/') . '/admin/reports.php';

// ── Branch context ────────────────────────────────────────────────────────────
$branchCtx = init_branch_context(false);
$branchId  = $branchCtx['selected_branch_id'];   // 'all' | int
$branchName = $branchCtx['branch_name'];
[$bSql, $bTypes, $bParams] = branch_where_parts('o', $branchId);

// ── Date range ────────────────────────────────────────────────────────────────
$from  = date('Y-m-d', strtotime($_GET['from'] ?? date('Y-m-01')));
$to    = date('Y-m-d', strtotime($_GET['to']   ?? date('Y-m-d')));
$toEnd = $to . ' 23:59:59';

// ── Chart sort (value_desc|value_asc|month_asc|month_desc) ────────────────────
$chart_sort = $_GET['chart_sort'] ?? 'value_desc';
$valid_sorts = ['value_desc','value_asc','month_asc','month_desc'];
if (!in_array($chart_sort, $valid_sorts)) $chart_sort = 'value_desc';

// ── Sales trend metric (revenue|orders) ───────────────────────────────────────
$trend_metric = $_GET['trend_metric'] ?? 'revenue';
if (!in_array($trend_metric, ['revenue','orders'])) $trend_metric = 'revenue';

$y_cal = (int) date('Y');
$heatmap_year = isset($_GET['heatmap_year']) ? (int) $_GET['heatmap_year'] : $y_cal;
if ($heatmap_year < $y_cal - 8 || $heatmap_year > $y_cal + 1) {
    $heatmap_year = $y_cal;
}

/** Stable reports URL query (explicit keys + full path fixes Turbo / relative ? links). */
function reports_page_query(array $overrides = []): string {
    $keys = ['from', 'to', 'branch_id', 'chart_sort', 'trend_metric', 'txn_pay', 'txn_page', 'heatmap_year'];
    $q = [];
    foreach ($keys as $k) {
        if (array_key_exists($k, $overrides)) {
            if ($overrides[$k] !== null && $overrides[$k] !== '') {
                $q[$k] = $overrides[$k];
            }
        } elseif (isset($_GET[$k]) && $_GET[$k] !== '') {
            $q[$k] = $_GET[$k];
        }
    }
    return http_build_query($q);
}

// ── Recent transactions payment filter ───────────────────────────────────────
$txn_payment_filter = $_GET['txn_pay'] ?? 'all';
$txn_pay_valid = ['all','paid','unpaid','pending'];
if (!in_array($txn_payment_filter, $txn_pay_valid)) $txn_payment_filter = 'all';

// ── Forecast helpers ──────────────────────────────────────────────────────────

/** Simple trend-based 3-month forecast from a historical array. */
function pf_forecast3(array $hist): array {
    $n = count($hist);
    if ($n < 3) return array_fill(0, 3, 0);
    $last3 = array_slice($hist, -3);
    $avg   = array_sum($last3) / 3.0;
    $slope = ($last3[2] - $last3[0]) / 2.0;
    return [
        max(0, (int) round($avg + $slope * 1)),
        max(0, (int) round($avg + $slope * 2)),
        max(0, (int) round($avg + $slope * 3)),
    ];
}

/** Single-step linear regression forecast for revenue/orders. */
function pf_linreg(array $values): float {
    $n = count($values);
    if ($n < 2) return max(0, (float)end($values));
    $sumX = $sumY = $sumXY = $sumXX = 0;
    for ($i = 0; $i < $n; $i++) {
        $sumX += $i; $sumY += $values[$i];
        $sumXY += $i * $values[$i]; $sumXX += $i * $i;
    }
    $d = $n * $sumXX - $sumX * $sumX;
    if ($d == 0) return max(0, array_sum($values) / $n);
    $slope = ($n * $sumXY - $sumX * $sumY) / $d;
    $b     = ($sumY - $slope * $sumX) / $n;
    return max(0, round($b + $slope * $n, 2));
}

// ── 1. Branch empty check ─────────────────────────────────────────────────────
try {
    [$bEc,$bEt,$bEp] = branch_where_parts('o', $branchId);
    $branch_total = (int) (db_query(
        "SELECT COUNT(*) as cnt FROM orders o WHERE 1=1$bEc",
        $bEt, $bEp
    )[0]['cnt'] ?? 0);
} catch(Exception $e){ $branch_total = 0; }
$branch_empty = $branch_total === 0;

// ── 2. KPI — current period ───────────────────────────────────────────────────
$total_orders = $revenue = $paid_orders = $avg_val = 0;
if (!$branch_empty) {
    try {
        [$b,$bt,$bp] = branch_where_parts('o', $branchId);
        $row = db_query(
            "SELECT COUNT(*) as total_orders,
                    SUM(CASE WHEN o.payment_status='Paid' THEN o.total_amount ELSE 0 END) as revenue,
                    SUM(CASE WHEN o.payment_status='Paid' THEN 1 ELSE 0 END) as paid,
                    AVG(CASE WHEN o.payment_status='Paid' THEN o.total_amount ELSE NULL END) as avg_val
             FROM orders o WHERE o.order_date BETWEEN ? AND ?$b",
            'ss'.$bt, array_merge([$from,$toEnd],$bp)
        )[0] ?? [];
        $total_orders = (int)   ($row['total_orders'] ?? 0);
        $revenue      = (float) ($row['revenue']      ?? 0);
        $paid_orders  = (int)   ($row['paid']         ?? 0);
        $avg_val      = (float) ($row['avg_val']      ?? 0);
    } catch(Exception $e){}
}

// Previous period for trend arrows
$days     = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);
$prevFrom = date('Y-m-d', strtotime($from) - $days * 86400);
$prevToEnd = date('Y-m-d', strtotime($from) - 86400) . ' 23:59:59';
$prev_orders = $prev_revenue = 0;
if (!$branch_empty) {
    try {
        [$b,$bt,$bp] = branch_where_parts('o', $branchId);
        $pr = db_query(
            "SELECT COUNT(*) as total_orders,
                    SUM(CASE WHEN o.payment_status='Paid' THEN o.total_amount ELSE 0 END) as revenue
             FROM orders o WHERE o.order_date BETWEEN ? AND ?$b",
            'ss'.$bt, array_merge([$prevFrom,$prevToEnd],$bp)
        )[0] ?? [];
        $prev_orders  = (int)($pr['total_orders'] ?? 0);
        $prev_revenue = (float)($pr['revenue']    ?? 0);
    } catch(Exception $e){}
}
$orders_delta  = $prev_orders  > 0 ? round((($total_orders - $prev_orders)  / $prev_orders)  * 100, 1) : null;
$revenue_delta = $prev_revenue > 0 ? round((($revenue      - $prev_revenue) / $prev_revenue) * 100, 1) : null;

// ── 3. Top KPI labels ─────────────────────────────────────────────────────────
$top_kpi_product = $top_kpi_location = null;
if (!$branch_empty && $total_orders > 0) {
    try {
        [$b,$bt,$bp] = branch_where_parts('o', $branchId);
        $top_kpi_product = db_query(
            "SELECT p.name, SUM(oi.quantity) as qty FROM order_items oi
             JOIN products p ON oi.product_id=p.product_id
             JOIN orders o ON oi.order_id=o.order_id
             WHERE o.order_date BETWEEN ? AND ?$b
             GROUP BY p.product_id ORDER BY qty DESC LIMIT 1",
            'ss'.$bt, array_merge([$from,$toEnd],$bp)
        )[0] ?? null;
    } catch(Exception $e){}

    try {
        [$b,$bt,$bp] = branch_where_parts('o', $branchId);
        $top_kpi_location = db_query(
            "SELECT TRIM(c.city) as city, COUNT(*) as cnt
             FROM orders o JOIN customers c ON o.customer_id=c.customer_id
             WHERE o.order_date BETWEEN ? AND ?
               AND c.city IS NOT NULL AND TRIM(c.city) != ''$b
             GROUP BY c.city HAVING LENGTH(TRIM(c.city)) > 2
             ORDER BY cnt DESC LIMIT 1",
            'ss'.$bt, array_merge([$from,$toEnd],$bp)
        )[0] ?? null;
    } catch(Exception $e){}
}

// ── 4. 12-Month rolling sales trend ──────────────────────────────────────────
$trend12_labels = $trend12_revenues = $trend12_orders = [];
if (!$branch_empty) {
    try {
        [$b,$bt,$bp] = branch_where_parts('o', $branchId);
        $raw12 = db_query(
            "SELECT DATE_FORMAT(o.order_date,'%Y-%m') as mon,
                    COUNT(*) as orders,
                    SUM(CASE WHEN o.payment_status='Paid' THEN o.total_amount ELSE 0 END) as revenue
             FROM orders o
             WHERE o.order_date >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 11 MONTH),'%Y-%m-01')$b
             GROUP BY mon ORDER BY mon",
            $bt, $bp
        ) ?: [];
    } catch(Exception $e){ $raw12 = []; }

    for ($i = 11; $i >= 0; $i--) {
        $key = date('Y-m', strtotime("-$i months"));
        $trend12_labels[] = date('M Y', strtotime($key.'-01'));
        $found = current(array_filter($raw12, fn($r) => $r['mon'] === $key)) ?: [];
        $trend12_revenues[] = (float)($found['revenue'] ?? 0);
        $trend12_orders[]   = (int)  ($found['orders']  ?? 0);
    }
}
$forecast_revenue = !empty($trend12_revenues) ? pf_linreg($trend12_revenues) : 0;
$forecast_orders  = !empty($trend12_orders)   ? (int)pf_linreg($trend12_orders) : 0;
$next_month_label = date('M Y', strtotime('+1 month'));
// Apply month sort to 12-month trend
if ($chart_sort === 'month_desc' && !empty($trend12_labels)) {
    $trend12_labels  = array_reverse($trend12_labels);
    $trend12_revenues = array_reverse($trend12_revenues);
    $trend12_orders   = array_reverse($trend12_orders);
}

// ── 5. Per-product forecast (last 6 months → next 3 months) ─────────────────
$fc_hist_labels = $fc_fore_labels = [];
for ($i = 5; $i >= 0; $i--) $fc_hist_labels[] = date('M y', strtotime("-$i months"));
for ($i = 1; $i <= 3; $i++)  $fc_fore_labels[] = date('M y', strtotime("+$i months"));
$fc_all_labels = array_merge($fc_hist_labels, $fc_fore_labels); // 9 labels

$fc_series_data   = [];   // [product => [hist=>[], fore=>[]]]
$fc_total_history = 0;
if (!$branch_empty) {
    try {
        [$b,$bt,$bp] = branch_where_parts('o', $branchId);
        $fcRaw = db_query(
            "SELECT p.name AS product,
                    DATE_FORMAT(o.order_date,'%Y-%m') as mon,
                    COUNT(*) as orders
             FROM order_items oi
             JOIN products p ON oi.product_id=p.product_id
             JOIN orders o ON oi.order_id=o.order_id
             WHERE o.order_date >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 5 MONTH),'%Y-%m-01')
               AND o.order_date <  DATE_FORMAT(NOW(),'%Y-%m-01') + INTERVAL 1 MONTH$b
             GROUP BY p.product_id, p.name, mon ORDER BY p.name, mon",
            $bt, $bp
        ) ?: [];
    } catch(Exception $e){ $fcRaw = []; }

    // Group by product
    $fcByProd = [];
    foreach ($fcRaw as $r) {
        $fcByProd[$r['product']][$r['mon']] = (int)$r['orders'];
    }

    // Sort by total, take top 6
    $fcTotals = [];
    foreach ($fcByProd as $prod => $data) $fcTotals[$prod] = array_sum($data);
    arsort($fcTotals);
    $topProdFc = array_slice($fcTotals, 0, 6, true);

    foreach ($topProdFc as $prod => $_) {
        $hist = [];
        for ($i = 5; $i >= 0; $i--) {
            $k = date('Y-m', strtotime("-$i months"));
            $v = $fcByProd[$prod][$k] ?? 0;
            $hist[] = $v;
            $fc_total_history += $v;
        }
        $fore = pf_forecast3($hist);
        $lastHist = $hist[5] ?? 0;
        $lastFore = $fore[2] ?? 0;
        $demand = 'moderate';
        if ($lastFore > $lastHist * 1.15) $demand = 'high';
        elseif ($lastFore < $lastHist * 0.85 && $lastHist > 0) $demand = 'declining';
        $fc_series_data[$prod] = [
            'hist' => $hist,
            'fore' => $fore,
            'demand' => $demand,
        ];
    }
}
$can_forecast = $fc_total_history >= 20;

// ── 6. Best selling products ──────────────────────────────────────────────────
$top_products = [];
if (!$branch_empty && $total_orders > 0) {
    try {
        [$b,$bt,$bp] = branch_where_parts('o', $branchId);
        $top_products = db_query(
            "SELECT p.product_id, p.name AS product_name,
                    SUM(oi.quantity) as qty_sold,
                    SUM(oi.quantity * oi.unit_price) as revenue
             FROM order_items oi
             JOIN products p ON oi.product_id=p.product_id
             JOIN orders o ON oi.order_id=o.order_id
             WHERE o.order_date BETWEEN ? AND ?$b
             GROUP BY p.product_id, p.name ORDER BY qty_sold DESC LIMIT 10",
            'ss'.$bt, array_merge([$from,$toEnd],$bp)
        ) ?: [];
        if ($chart_sort === 'value_asc') $top_products = array_reverse($top_products);
    } catch(Exception $e){}
}

// ── 7. Revenue distribution (donut) ──────────────────────────────────────────
$rev_donut = array_slice($top_products, 0, 7);
$donut_palette = ['#00232b', '#53C5E0', '#0F4C5C', '#3498DB', '#6C5CE7', '#3A86A8', '#8ED6E6', '#6B7C85', '#F39C12', '#2ECC71'];
$rev_donut_total = 0.0;
foreach ($rev_donut as $rd) {
    $rev_donut_total += round((float)($rd['revenue'] ?? 0), 2);
}

// ── 8. Order status ───────────────────────────────────────────────────────────
$status_data = [];
if (!$branch_empty && $total_orders > 0) {
    try {
        [$b,$bt,$bp] = branch_where_parts('o', $branchId);
        $status_data = db_query(
            "SELECT o.status, COUNT(*) as cnt FROM orders o
             WHERE o.order_date BETWEEN ? AND ?$b
             GROUP BY o.status ORDER BY cnt DESC",
            'ss'.$bt, array_merge([$from,$toEnd],$bp)
        ) ?: [];
        if ($chart_sort === 'value_asc') $status_data = array_reverse($status_data);
    } catch(Exception $e){}
}

// ── 9. Seasonal heatmap (current year, by branch) ────────────────────────────
$heatmap_products = [];
if (!$branch_empty) {
    try {
        [$b,$bt,$bp] = branch_where_parts('o', $branchId);
        $hmTypes = 'i' . $bt;
        $hmParams = array_merge([$heatmap_year], $bp);
        $hmRaw = db_query(
            "SELECT p.name AS product, MONTH(o.order_date) as mo, SUM(oi.quantity) as qty
             FROM order_items oi
             JOIN products p ON oi.product_id=p.product_id
             JOIN orders o ON oi.order_id=o.order_id
             WHERE YEAR(o.order_date) = ?$b
             GROUP BY p.product_id, p.name, MONTH(o.order_date)
             ORDER BY p.name, mo",
            $hmTypes, $hmParams
        ) ?: [];
    } catch(Exception $e){ $hmRaw = []; }

    foreach ($hmRaw as $r) {
        $p = $r['product'];
        if (!isset($heatmap_products[$p])) $heatmap_products[$p] = array_fill(1, 12, 0);
        $heatmap_products[$p][(int)$r['mo']] += (int)$r['qty'];
    }
    arsort($heatmap_products);
    $heatmap_products = array_slice($heatmap_products, 0, 8, true);
    if ($chart_sort === 'value_asc') $heatmap_products = array_reverse($heatmap_products, true);
}

// ── 10. Customer locations ────────────────────────────────────────────────────
$customer_locations = [];
if (!$branch_empty && $total_orders > 0) {
    try {
        [$b,$bt,$bp] = branch_where_parts('o', $branchId);
        $customer_locations = db_query(
            "SELECT TRIM(c.city) as city,
                    COUNT(DISTINCT o.order_id) as orders
             FROM orders o JOIN customers c ON o.customer_id=c.customer_id
             WHERE o.order_date BETWEEN ? AND ?
               AND c.city IS NOT NULL AND TRIM(c.city) != ''$b
             GROUP BY c.city HAVING LENGTH(TRIM(c.city)) > 2
             ORDER BY orders DESC LIMIT 12",
            'ss'.$bt, array_merge([$from,$toEnd],$bp)
        ) ?: [];
        if ($chart_sort === 'value_asc') $customer_locations = array_reverse($customer_locations);
    } catch(Exception $e){}
}

// ── 11. Customization usage ───────────────────────────────────────────────────
$custom_usage = [];
if (!$branch_empty && $total_orders > 0) {
    try {
        [$b,$bt,$bp] = branch_where_parts('o', $branchId);
        $custom_usage = db_query(
            "SELECT p.name AS product,
                    SUM(CASE WHEN od.design_id IS NOT NULL THEN oi.quantity ELSE 0 END) AS custom_count,
                    SUM(CASE WHEN od.design_id IS NULL     THEN oi.quantity ELSE 0 END) AS template_count
             FROM order_items oi
             JOIN products p ON oi.product_id=p.product_id
             JOIN orders o ON oi.order_id=o.order_id
             LEFT JOIN order_designs od ON o.order_id=od.order_id
             WHERE o.order_date BETWEEN ? AND ?$b
             GROUP BY p.product_id, p.name
             HAVING (custom_count + template_count) > 0
             ORDER BY (custom_count + template_count) DESC LIMIT 8",
            'ss'.$bt, array_merge([$from,$toEnd],$bp)
        ) ?: [];
        if ($chart_sort === 'value_asc') $custom_usage = array_reverse($custom_usage);
    } catch(Exception $e){}
}

// ── 12. Branch performance (all branches, date-filtered) ─────────────────────
$branch_perf = [];
try {
    $branch_perf = db_query(
        "SELECT b.branch_name,
                COUNT(o.order_id) AS orders,
                SUM(CASE WHEN o.payment_status='Paid' THEN o.total_amount ELSE 0 END) AS revenue
         FROM orders o JOIN branches b ON o.branch_id=b.id
         WHERE o.order_date BETWEEN ? AND ?
         GROUP BY b.id, b.branch_name ORDER BY revenue DESC",
        'ss', [$from, $toEnd]
    ) ?: [];
    if ($chart_sort === 'value_asc') $branch_perf = array_reverse($branch_perf);
} catch(Exception $e){}

// ── 13. Top customers ─────────────────────────────────────────────────────────
$top_customers = [];
if (!$branch_empty && $total_orders > 0) {
    try {
        [$b,$bt,$bp] = branch_where_parts('o', $branchId);
        $top_customers = db_query(
            "SELECT CONCAT(c.first_name,' ',c.last_name) as name, c.email,
                    COUNT(o.order_id) as orders, SUM(o.total_amount) as spent
             FROM customers c JOIN orders o ON c.customer_id=o.customer_id
             WHERE o.order_date BETWEEN ? AND ?$b
             GROUP BY c.customer_id ORDER BY spent DESC LIMIT 8",
            'ss'.$bt, array_merge([$from,$toEnd],$bp)
        ) ?: [];
        if ($chart_sort === 'value_asc') $top_customers = array_reverse($top_customers);
    } catch(Exception $e){}
}

// ── 14. Inventory alerts ──────────────────────────────────────────────────────
$low_stock = [];
try {
    $low_stock = db_query(
        "SELECT i.name, i.unit_of_measure as unit,
                COALESCE((SELECT SUM(IF(t.direction='IN',t.quantity,-t.quantity))
                          FROM inventory_transactions t WHERE t.item_id=i.item_id),0) as soh,
                i.reorder_level
         FROM inventory_items i
         WHERE i.reorder_level > 0
           AND COALESCE((SELECT SUM(IF(t.direction='IN',t.quantity,-t.quantity))
                         FROM inventory_transactions t WHERE t.item_id=i.item_id),0) <= i.reorder_level
         ORDER BY soh ASC LIMIT 8"
    ) ?: [];
} catch(Exception $e){}

// ── 15. Recent transactions ───────────────────────────────────────────────────
$txn_page = max(1,(int)($_GET['txn_page'] ?? 1));
$txn_per  = 10;
$txn_count = $txn_pages = 0;
$recent_orders = [];
$txn_pay_sql = '';
if ($txn_payment_filter === 'paid')     $txn_pay_sql = " AND o.payment_status = 'Paid'";
elseif ($txn_payment_filter === 'unpaid') $txn_pay_sql = " AND (o.payment_status IS NULL OR (o.payment_status != 'Paid' AND o.payment_status != 'Pending'))";
elseif ($txn_payment_filter === 'pending') $txn_pay_sql = " AND o.payment_status = 'Pending'";
if (!$branch_empty) {
    try {
        [$b,$bt,$bp] = branch_where_parts('o', $branchId);
        $txn_count = (int)(db_query(
            "SELECT COUNT(*) as cnt FROM orders o WHERE o.order_date BETWEEN ? AND ?$b$txn_pay_sql",
            'ss'.$bt, array_merge([$from,$toEnd],$bp)
        )[0]['cnt'] ?? 0);
        $txn_pages  = max(1, ceil($txn_count / $txn_per));
        $txn_page   = min($txn_page, $txn_pages);
        $txn_offset = ($txn_page-1) * $txn_per;
        [$b2,$bt2,$bp2] = branch_where_parts('o', $branchId);
        $recent_orders = db_query(
            "SELECT o.order_id, CONCAT(c.first_name,' ',c.last_name) as customer_name,
                    o.order_date, o.total_amount, o.payment_status, o.status
             FROM orders o LEFT JOIN customers c ON o.customer_id=c.customer_id
             WHERE o.order_date BETWEEN ? AND ?$b2$txn_pay_sql
             ORDER BY o.order_date DESC LIMIT $txn_per OFFSET $txn_offset",
            'ss'.$bt2, array_merge([$from,$toEnd],$bp2)
        ) ?: [];
    } catch(Exception $e){}
}

// ── 16. Customer count ────────────────────────────────────────────────────────
$cust_total = 0;
try { $cust_total = (int)(db_query("SELECT COUNT(*) as cnt FROM customers")[0]['cnt'] ?? 0); } catch(Exception $e){}

// ── 17. Seasonal event insights ───────────────────────────────────────────────
$month_now = (int)date('n');
$seasonal_events = [
    ['months'=>[3,4,5],  'icon'=>'🎓','event'=>'Graduation Season',    'services'=>['Tarpaulin Printing','Layouts / Graphic Layout Services']],
    ['months'=>[4,5],    'icon'=>'🗳️','event'=>'Election Season',       'services'=>['Tarpaulin Printing','Reflectorized Stickers / Signages']],
    ['months'=>[11,12],  'icon'=>'🎄','event'=>'Holiday Season',        'services'=>['Souvenirs','Stickers on Sintraboard']],
    ['months'=>[2],      'icon'=>'💝','event'=> "Valentine's Season",   'services'=>['Stickers','Transparent Stickers']],
    ['months'=>[6,10],   'icon'=>'📚','event'=>'School Opening Season', 'services'=>['Layouts / Graphic Layout Services','T-shirt Printing']],
    ['months'=>[7,8,9],  'icon'=>'🌞','event'=>'Midyear Peak',          'services'=>['Decals / Stickers (Print & Cut)','Sintraboard Standees']],
];
$active_events = [];
foreach ($seasonal_events as $ev) {
    if (in_array($month_now, $ev['months'])) $active_events[] = $ev;
}

// ── 18. Auto insights ─────────────────────────────────────────────────────────
$insights = [];
if (!$branch_empty) {
    if (!empty($top_products))
        $insights[] = "<strong>{$top_products[0]['product_name']}</strong> is the top-selling service with <strong>".number_format((int)$top_products[0]['qty_sold'])."</strong> units in this period.";
    if (!empty($customer_locations))
        $insights[] = "Most orders originate from <strong>".htmlspecialchars(trim($customer_locations[0]['city']))."</strong> ({$customer_locations[0]['orders']} orders).";
    if ($forecast_revenue > 0)
        $insights[] = "Next month (<strong>$next_month_label</strong>) revenue forecast: <strong>₱".number_format($forecast_revenue,0)."</strong> based on 12-month trend.";
    if (!empty($custom_usage) && (int)$custom_usage[0]['custom_count'] > (int)$custom_usage[0]['template_count'])
        $insights[] = "<strong>".htmlspecialchars($custom_usage[0]['product'])."</strong> shows the highest custom design upload rate.";
    if (!empty($branch_perf) && count($branch_perf) > 1)
        $insights[] = "<strong>".htmlspecialchars($branch_perf[0]['branch_name'])."</strong> leads all branches with ₱".number_format((float)$branch_perf[0]['revenue'],0)." revenue.";
    if ($revenue_delta !== null) {
        if ($revenue_delta > 10)
            $insights[] = "Revenue is up <strong>{$revenue_delta}%</strong> vs. the previous period — strong growth momentum.";
        elseif ($revenue_delta < -10)
            $insights[] = "Revenue dropped <strong>".abs($revenue_delta)."%</strong> vs. the previous period — consider a promotional push.";
    }
}
foreach ($active_events as $ev) {
    $svcs = implode(' and ', array_map(fn($s)=>"<strong>$s</strong>", $ev['services']));
    $insights[] = "{$ev['icon']} <strong>{$ev['event']}</strong> is active — expect increased demand for {$svcs}.";
}

$page_title = 'Reports & Analytics — Admin';
$last_updated = date('M j, Y g:i A');

// ── Period empty (branch has orders but none in date range) ─────────────────
$period_empty = (!$branch_empty && $total_orders === 0);

// ── Top products: prev month qty for trend % ───────────────────────────────
$top_products_prev = [];
if (!$branch_empty && !empty($top_products)) {
    try {
        [$b,$bt,$bp] = branch_where_parts('o', $branchId);
        $prevMonthStart = date('Y-m-01', strtotime($from . ' -1 month'));
        $prevMonthEnd   = date('Y-m-t', strtotime($from . ' -1 month')) . ' 23:59:59';
        $prevRows = db_query(
            "SELECT p.product_id, SUM(oi.quantity) as qty
             FROM order_items oi JOIN products p ON oi.product_id=p.product_id
             JOIN orders o ON oi.order_id=o.order_id
             WHERE o.order_date BETWEEN ? AND ?$b
             GROUP BY p.product_id",
            'ss'.$bt, array_merge([$prevMonthStart,$prevMonthEnd],$bp)
        ) ?: [];
        foreach ($prevRows as $r) $top_products_prev[(int)$r['product_id']] = (int)$r['qty'];
    } catch(Exception $e){}
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $page_title; ?></title>
<?php require_once __DIR__ . '/../includes/favicon_links.php'; ?>
<link rel="stylesheet" href="/printflow/public/assets/css/output.css">
<script src="/printflow/public/assets/js/alpine.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js"></script>
<?php include __DIR__ . '/../includes/admin_style.php'; ?>
<?php render_branch_css(); ?>
<style>
/* ── Layout ─────────────────────────── */
.ana-wrap { display:flex; flex-direction:column; gap:24px; }
.ana-grid  { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.ana-grid3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; }
@media(max-width:960px){ .ana-grid,.ana-grid3{ grid-template-columns:1fr; } }

/* ── Card (SaaS-style) ───────────────── */
.ana-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.05); transition:box-shadow .2s; }
.ana-card:hover { box-shadow:0 4px 12px rgba(0,0,0,.08); }
.ana-hd   { display:flex; align-items:center; justify-content:space-between; padding:18px 20px; border-bottom:1px solid #f3f4f6; gap:10px; flex-wrap:wrap; }
.ana-hd h3{ margin:0; font-size:14px; font-weight:700; color:#1f2937; display:flex; align-items:center; gap:8px; white-space:nowrap; }
.ana-hd h3 svg{ width:16px; height:16px; color:#53C5E0; flex-shrink:0; }
.ana-bd   { padding:20px; }
.ana-bd-0 { padding:0; }

/* ── KPI (modern SaaS) ───────────────── */
.kpi-row  { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }
@media(max-width:900px){ .kpi-row{ grid-template-columns:repeat(2,1fr); } }
.kpi-card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:20px 22px; position:relative; overflow:hidden; transition:all .2s; box-shadow:0 1px 3px rgba(0,0,0,.04); cursor:help; }
.kpi-card:hover { box-shadow:0 4px 14px rgba(0,0,0,.08); }
.kpi-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
.kpi-ind::before  { background:linear-gradient(90deg,#00232b,#53C5E0); }
.kpi-em::before   { background:linear-gradient(90deg,#059669,#34d399); }
.kpi-amb::before  { background:linear-gradient(90deg,#f59e0b,#fcd34d); }
.kpi-vio::before  { background:linear-gradient(90deg,#7c3aed,#a78bfa); }
.kpi-lbl  { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:#6b7280; margin-bottom:6px; }
.kpi-val  { font-size:26px; font-weight:800; color:#111827; line-height:1.15; margin-bottom:6px; letter-spacing:-.02em; }
.kpi-sub  { font-size:12px; color:#6b7280; display:flex; align-items:center; gap:4px; flex-wrap:wrap; line-height:1.4; }
.kpi-updated { font-size:10px; color:#9ca3af; margin-top:10px; }
.t-up     { color:#059669; font-weight:700; }
.t-dn     { color:#dc2626; font-weight:700; }
.t-fl     { color:#6b7280; font-weight:500; }

/* ── Toolbar (Filter / Sort / Print) ─── */
.toolbar-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; height: 38px;
    border: 1px solid #e5e7eb; background: #fff; border-radius: 8px;
    font-size: 13px; font-weight: 500; color: #374151; cursor: pointer;
    transition: all 0.15s; white-space: nowrap; box-sizing: border-box;
}
.toolbar-btn:hover { border-color: #9ca3af; background: #f9fafb; }
.toolbar-btn.active { border-color: #00232b; color: #00232b; background: #ecf8fb; }
.toolbar-btn svg { flex-shrink: 0; }
.filter-panel {
    position: absolute; top: calc(100% + 6px); right: 0; width: 320px;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.12); z-index: 200; overflow: hidden;
}
.filter-panel-header { padding: 14px 18px; border-bottom: 1px solid #f3f4f6; font-size: 14px; font-weight: 700; color: #111827; }
.filter-section { padding: 14px 18px; border-bottom: 1px solid #f3f4f6; }
.filter-section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.filter-section-label { font-size: 13px; font-weight: 600; color: #374151; }
.filter-reset-link { font-size: 12px; font-weight: 600; color: #0d9488; cursor: pointer; background: none; border: none; padding: 0; }
.filter-reset-link:hover { text-decoration: underline; }
.filter-input { width: 100%; height: 34px; border: 1px solid #e5e7eb; border-radius: 7px; font-size: 13px; padding: 0 10px; color: #1f2937; box-sizing: border-box; }
.filter-input:focus { outline: none; border-color: #0d9488; }
.filter-date-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.filter-date-label { font-size: 11px; color: #6b7280; margin-bottom: 4px; }
.filter-select { width: 100%; height: 34px; border: 1px solid #e5e7eb; border-radius: 7px; font-size: 13px; padding: 0 10px; color: #1f2937; background: #fff; box-sizing: border-box; cursor: pointer; }
.filter-select:focus { outline: none; border-color: #0d9488; }
.filter-actions { display: flex; gap: 8px; padding: 14px 18px; border-top: 1px solid #f3f4f6; }
.filter-btn-reset { flex: 1; height: 36px; border: 1px solid #e5e7eb; background: #fff; border-radius: 8px; font-size: 13px; font-weight: 500; color: #374151; cursor: pointer; }
.filter-btn-reset:hover { background: #f9fafb; }
.filter-btn-apply { flex: 1; height: 36px; border: none; background: #0d9488; color: #fff; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.filter-btn-apply:hover { background: #0f766e; }
.filter-badge { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; background: #0d9488; color: #fff; border-radius: 50%; font-size: 10px; font-weight: 700; }
.sort-dropdown { position: absolute; top: calc(100% + 6px); right: 0; min-width: 200px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.12); z-index: 200; padding: 6px 0; }
.sort-option { display: flex; align-items: center; gap: 8px; padding: 9px 16px; font-size: 13px; color: #374151; cursor: pointer; transition: background 0.1s; }
.sort-option:hover { background: #f9fafb; }
.sort-option.selected { color: #0d9488; font-weight: 600; background: #f0fdfa; }
.sort-option .check { margin-left: auto; color: #0d9488; }
[x-cloak] { display: none !important; }

/* ── Empty state ────────────────────── */
.empty-state { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:56px 24px; text-align:center; }
.empty-icon  { width:56px; height:56px; border-radius:50%; background:#f3f4f6; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; }
.empty-title { font-size:16px; font-weight:700; color:#1f2937; margin-bottom:6px; }
.empty-sub   { font-size:13px; color:#6b7280; max-width:340px; }
.empty-kpi   { font-size:24px; font-weight:800; color:#d1d5db; }

/* ── Chart boxes ────────────────────── */
.ch-box     { width:100%; position:relative; }
.ch-empty   { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; color:#9ca3af; font-size:13px; padding:40px 16px; }
.ch-empty svg{ opacity:.35; }

/* ── Revenue donut (layout + custom legend) ───────────────────────── */
.rev-donut-card-hd { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.rev-donut-growth { font-size:12px; font-weight:700; white-space:nowrap; padding:4px 10px; border-radius:8px; background:#E5EEF2; color:#0F4C5C; }
.rev-donut-growth.up { background:#d1fae5; color:#047857; }
.rev-donut-growth.dn { background:#fee2e2; color:#b91c1c; }
.rev-donut-body { padding-top:4px; }
.rev-donut-row { display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:center; gap:20px 28px; }
.rev-donut-chart-wrap { flex:0 0 auto; width:min(100%,260px); height:240px; margin:0 auto; }
.rev-donut-legend-wrap { flex:1 1 220px; min-width:180px; max-width:420px; margin:0 auto; }
.rev-donut-legend { list-style:none; margin:0; padding:0; column-count:2; column-gap:20px; font-size:12px; }
@media (max-width:640px) {
    .rev-donut-legend { column-count:1; }
}
.rev-donut-legend li { break-inside:avoid; display:flex; align-items:flex-start; gap:10px; padding:8px 0; border-bottom:1px solid #f3f4f6; }
.rev-donut-legend li:last-child { border-bottom:none; }
.rev-donut-swatch { flex:0 0 10px; width:10px; height:10px; border-radius:3px; margin-top:3px; }
.rev-donut-legend-txt { flex:1; min-width:0; line-height:1.35; word-wrap:break-word; overflow-wrap:anywhere; color:#374151; font-weight:600; }
.rev-donut-legend-meta { display:block; font-size:11px; font-weight:500; color:#6B7C85; margin-top:2px; }

/* Heatmap year control */
.heatmap-year-label { font-size:12px; font-weight:600; color:#6b7280; white-space:nowrap; }
.heatmap-year-select { min-width:5.5rem; }

/* Chart loading (Apex) — skeleton until render completes */
.ch-box.pf-chart-loading::after {
    content:'';
    position:absolute; inset:0; border-radius:8px;
    background:linear-gradient(90deg,#f1f5f9 0%,#e2e8f0 45%,#f8fafc 55%,#f1f5f9 100%);
    background-size:200% 100%;
    animation:pf-chart-shimmer 1.1s ease-in-out infinite;
    pointer-events:none; z-index:3;
}
@keyframes pf-chart-shimmer {
    0% { background-position:100% 0; opacity:1; }
    100% { background-position:-100% 0; opacity:.85; }
}
.ch-box.pf-chart-loading .apexcharts-canvas { opacity:0; }
.ch-box:not(.pf-chart-loading) .apexcharts-canvas { transition:opacity .35s ease; }
.ch-box.pf-chart-reveal-done .apexcharts-inner { animation:pf-chart-fade-in 1.05s cubic-bezier(0.22, 1, 0.36, 1); }
@keyframes pf-chart-fade-in { from { opacity:.45; } to { opacity:1; } }
#ch-heatmap .apexcharts-heatmap-rect { transition:fill 0.85s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.55s ease; }
/* Promote chart SVG layer so scroll + Apex intro animations stay smoother */
.main-content .ch-box .apexcharts-inner { transform:translateZ(0); }

/* Customer locations — top city */
.top-location-pill { font-size:11px; font-weight:600; color:#0F4C5C; background:#E5EEF2; padding:5px 12px; border-radius:8px; border:1px solid #cfe8ef; }

/* ApexCharts — readable tooltips & crosshair (avoid white-on-white) */
.apexcharts-tooltip { color:#f8fafc !important; background:#1e293b !important; border:1px solid #334155 !important; box-shadow:0 8px 24px rgba(0,0,0,.2) !important; }
.apexcharts-tooltip-title { color:#e2e8f0 !important; border-bottom:1px solid #334155 !important; }
.apexcharts-tooltip-series-group { padding:4px 0 !important; }
.apexcharts-tooltip-y-group { color:#f8fafc !important; }
.apexcharts-tooltip-marker { color: inherit !important; }
/* Sales trend — strong crosshair + dark toolbar/zoom glyphs (Apex defaults can be near-white) */
#ch-trend .apexcharts-xcrosshairs,
#ch-trend .apexcharts-xcrosshairs line { stroke:#001018 !important; stroke-width:2px !important; opacity:1 !important; }
#ch-trend .apexcharts-ycrosshairs { stroke:#0F4C5C !important; stroke-width:1px !important; stroke-dasharray:4 3 !important; opacity:0.75 !important; }
#ch-trend .apexcharts-marker,
#ch-trend .apexcharts-marker path { fill:#00232b !important; stroke:#53C5E0 !important; stroke-width:2px !important; }
#ch-trend .apexcharts-toolbar { z-index:12; }
#ch-trend .apexcharts-toolbar svg,
#ch-trend .apexcharts-toolbar svg line,
#ch-trend .apexcharts-toolbar svg path,
#ch-trend .apexcharts-toolbar svg polyline,
#ch-trend .apexcharts-toolbar svg rect { stroke:#00232b !important; fill:#00232b !important; color:#00232b !important; }
#ch-trend .apexcharts-zoom-icon svg,
#ch-trend .apexcharts-pan-icon svg,
#ch-trend .apexcharts-reset-icon svg { stroke:#00232b !important; fill:none !important; }
.apexcharts-xcrosshairs { stroke:#00232b !important; stroke-width:1px !important; opacity:0.85 !important; }
.apexcharts-ycrosshairs { stroke:#6B7C85 !important; stroke-dasharray:4 3 !important; opacity:0.5 !important; }

/* ── Tables ─────────────────────────── */
.rpt-tbl { width:100%; border-collapse:collapse; }
.rpt-tbl th { padding:8px 14px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#6b7280; background:#f9fafb; text-align:left; border-bottom:2px solid #e5e7eb; }
.rpt-tbl td { padding:9px 14px; font-size:13px; border-bottom:1px solid #f3f4f6; color:#374151; }
.rpt-tbl tr:hover td{ background:#f8fafc; }
.rpt-tbl-clickable tbody tr{ transition:background .15s; }
.rpt-tbl-clickable tbody tr:hover{ background:#f1f5f9 !important; }
.num      { text-align:right; font-variant-numeric:tabular-nums; font-weight:600; }

/* ── Badges ─────────────────────────── */
.badge { display:inline-block; padding:2px 9px; border-radius:20px; font-size:11px; font-weight:700; }
.b-green  { background:#d1fae5; color:#059669; }
.b-yellow { background:#fef3c7; color:#d97706; }
.b-blue   { background:#dbeafe; color:#2563eb; }
.b-cyan   { background:#cffafe; color:#0e7490; }
.b-red    { background:#fee2e2; color:#dc2626; }
.b-gray   { background:#f3f4f6; color:#6b7280; }
.b-purple { background:#ede9fe; color:#7c3aed; }

/* ── Insights ───────────────────────── */
.ins-panel { background:linear-gradient(135deg,#001018 0%,#00232b 38%,#0F4C5C 68%,#3A86A8 100%); border-radius:14px; padding:22px 26px; color:#fff; }
.ins-title { font-size:14px; font-weight:700; margin-bottom:14px; display:flex; align-items:center; gap:7px; }
.ins-list  { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:9px; }
.ins-list li{ font-size:13px; line-height:1.55; opacity:.9; display:flex; gap:9px; align-items:flex-start; }
.ins-list li::before{ content:'→'; color:#53C5E0; font-weight:700; flex-shrink:0; }
.ins-list strong{ color:#b8eaf4; }

/* ── Forecast chips ─────────────────── */
.fc-row  { display:flex; gap:14px; flex-wrap:wrap; margin-top:16px; }
.fc-chip { background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.15); border-radius:10px; padding:12px 16px; flex:1; min-width:140px; }
.fc-lbl  { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:rgba(255,255,255,.55); margin-bottom:4px; }
.fc-val  { font-size:20px; font-weight:800; color:#e8f8fc; line-height:1.1; }
.fc-sub  { font-size:11px; color:rgba(255,255,255,.45); margin-top:2px; }

/* ── Seasonal event badges ──────────── */
.ev-badge { display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.2); border-radius:20px; padding:5px 12px; font-size:12px; font-weight:600; margin:4px 4px 0 0; }

/* ── Forecast product bars ──────────── */
.fc-prod-bar { height:6px; background:rgba(83,197,224,.25); border-radius:3px; overflow:hidden; }
.fc-prod-fill{ height:100%; border-radius:3px; }

/* ── Stock bar ──────────────────────── */
.sk-bar      { height:5px; background:#f3f4f6; border-radius:3px; overflow:hidden; }
.sk-fill     { height:100%; border-radius:3px; }
.sk-good     { background:#10b981; }
.sk-warn     { background:#f59e0b; }
.sk-danger   { background:#ef4444; }

/* ── Print-only elements (hidden on screen) ───────────────────────────────── */
.print-report-header, .print-report-footer { display:none; }

/* ── Print-optimized layout ───────────────────────────────────────────────── */
@media print {
    .sidebar,.mobile-header,header,.no-print,.branch-context-banner{ display:none !important; }
    .main-content{ margin-left:0 !important; padding:0 !important; }
    .ana-wrap{ gap:16px !important; }
    .ana-card{ break-inside:avoid; margin-bottom:16px; box-shadow:none !important; border:1px solid #ddd !important; }
    .print-page-break{ page-break-before:always; }
    .print-page-break:first-of-type{ page-break-before:auto; }
    .ana-card:hover{ box-shadow:none !important; }
    .ana-grid,.ana-grid3{ display:block !important; }
    .print-hide{ display:none !important; }
    .print-report-header{ display:block !important; margin-bottom:20px; padding-bottom:16px; border-bottom:2px solid #333; }
    .print-report-footer{ display:block !important; margin-top:24px; padding-top:12px; border-top:1px solid #999; font-size:11px; color:#666; text-align:center; }
    .kpi-card{ box-shadow:none !important; border:1px solid #ddd !important; }
    .kpi-card::before{ display:none !important; }
    .kpi-lbl{ color:#555 !important; }
    .kpi-val{ color:#111 !important; }
    .kpi-sub,.kpi-updated{ color:#666 !important; }
    .t-up,.t-dn{ color:#333 !important; }
    .rpt-tbl th,.rpt-tbl td{ padding:10px 12px !important; font-size:12px !important; color:#222 !important; }
    .rpt-tbl th{ background:#f0f0f0 !important; color:#333 !important; }
    .rpt-tbl tr:hover td{ background:#fff !important; }
    .badge{ background:#e5e5e5 !important; color:#333 !important; border:1px solid #ccc; }
    .ins-panel{ background:#f5f5f5 !important; color:#333 !important; border:1px solid #ddd; }
    .ins-panel .fc-chip{ background:#fff !important; border:1px solid #ddd !important; }
    .ins-panel .fc-lbl,.ins-panel .fc-sub{ color:#666 !important; }
    .ins-panel .fc-val{ color:#111 !important; }
    .ins-list li::before{ color:#666 !important; }
    .ins-list strong{ color:#111 !important; }
    @page{ margin:1.5cm; size:A4; }
    body{ -webkit-print-color-adjust:exact; print-color-adjust:exact; background:#fff !important; }
    .rpt-tbl{ width:100% !important; }
    .rpt-tbl th,.rpt-tbl td{ word-wrap:break-word; overflow-wrap:break-word; }
    .ch-box{ min-height:200px !important; }
    .ana-wrap{ max-width:100% !important; }
}
</style>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <div class="main-content">
        <header>
            <h1 class="page-title">Reports & Analytics</h1>
            <?php render_branch_selector($branchCtx); ?>
        </header>
        <main>
            <?php render_branch_context_banner($branchCtx['branch_name']); ?>

            <!-- ── Print Report Header (visible only when printing) ── -->
            <div class="print-report-header">
                <h1 style="margin:0 0 8px;font-size:22px;font-weight:800;color:#111;">PrintFlow Sales Report</h1>
                <div style="font-size:13px;color:#444;line-height:1.6;">
                    <strong>Branch:</strong> <?php echo htmlspecialchars($branchName); ?> &nbsp;|&nbsp;
                    <strong>Date Range:</strong> <?php echo date('M j, Y',strtotime($from)); ?> – <?php echo date('M j, Y',strtotime($to)); ?> &nbsp;|&nbsp;
                    <strong>Generated:</strong> <?php echo date('F j, Y g:i A'); ?>
                </div>
            </div>

            <!-- ── Toolbar: Filter, Sort, Print ── -->
            <div class="no-print" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:22px;" x-data="reportsFilterPanel()">
                <div style="font-size:13px;color:#6b7280;">
                    <?php echo htmlspecialchars($branchName); ?> &nbsp;·&nbsp;
                    <?php echo date('M d, Y',strtotime($from)); ?> – <?php echo date('M d, Y',strtotime($to)); ?>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <!-- Sort -->
                    <div style="position:relative;">
                        <button class="toolbar-btn" :class="{active: sortOpen}" @click="sortOpen = !sortOpen; filterOpen = false" style="height:38px;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="9" y1="18" x2="15" y2="18"/>
                            </svg>
                            Sort
                        </button>
                        <div class="sort-dropdown" x-show="sortOpen" x-cloak @click.outside="sortOpen = false">
                            <?php foreach (['value_desc'=>'By value (highest first)','value_asc'=>'By value (lowest first)','month_asc'=>'By month (Jan→Dec)','month_desc'=>'By month (Dec→Jan)'] as $key => $label): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['chart_sort'=>$key])); ?>" class="sort-option" style="text-decoration:none;" :class="{ 'selected': '<?php echo $chart_sort; ?>' === '<?php echo $key; ?>' }">
                                <?php echo htmlspecialchars($label); ?>
                                <?php if ($chart_sort === $key): ?><svg class="check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><?php endif; ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <!-- Filter -->
                    <div style="position:relative;">
                        <button class="toolbar-btn" :class="{active: filterOpen || hasActiveFilters}" @click="filterOpen = !filterOpen; sortOpen = false" style="height:38px;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                            </svg>
                            Filter
                            <span x-show="hasActiveFilters"><span class="filter-badge" x-text="filterCount"></span></span>
                        </button>
                        <div class="filter-panel" x-show="filterOpen" x-cloak @click.outside="filterOpen = false">
                            <div class="filter-panel-header">Filter</div>
                            <form method="GET" id="reportsFilterForm">
                                <?php if ($branchId !== 'all'): ?><input type="hidden" name="branch_id" value="<?php echo (int)$branchId; ?>"><?php endif; ?>
                                <input type="hidden" name="chart_sort" value="<?php echo htmlspecialchars($chart_sort); ?>">
                                <input type="hidden" name="trend_metric" value="<?php echo htmlspecialchars($trend_metric); ?>">
                                <input type="hidden" name="txn_pay" value="<?php echo htmlspecialchars($txn_payment_filter); ?>">
                                <div class="filter-section">
                                    <div class="filter-section-head">
                                        <span class="filter-section-label">Date range</span>
                                        <button type="button" class="filter-reset-link" @click="resetDateRange()">Reset</button>
                                    </div>
                                    <div class="filter-date-row">
                                        <div>
                                            <div class="filter-date-label">From:</div>
                                            <input type="date" name="from" id="fp_from" class="filter-input" value="<?php echo htmlspecialchars($from); ?>">
                                        </div>
                                        <div>
                                            <div class="filter-date-label">To:</div>
                                            <input type="date" name="to" id="fp_to" class="filter-input" value="<?php echo htmlspecialchars($to); ?>">
                                        </div>
                                    </div>
                                    <div style="margin-top:10px;">
                                        <div class="filter-date-label">Quick presets</div>
                                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
                                            <button type="button" class="toolbar-btn" style="height:30px;font-size:11px;padding:0 10px;" @click="setPreset('last_7')">Last 7 days</button>
                                            <button type="button" class="toolbar-btn" style="height:30px;font-size:11px;padding:0 10px;" @click="setPreset('last_30')">Last 30 days</button>
                                            <button type="button" class="toolbar-btn" style="height:30px;font-size:11px;padding:0 10px;" @click="setPreset('this_month')">This month</button>
                                            <button type="button" class="toolbar-btn" style="height:30px;font-size:11px;padding:0 10px;" @click="setPreset('last_3')">Last 3 months</button>
                                            <button type="button" class="toolbar-btn" style="height:30px;font-size:11px;padding:0 10px;" @click="setPreset('last_6')">Last 6 months</button>
                                            <button type="button" class="toolbar-btn" style="height:30px;font-size:11px;padding:0 10px;" @click="setPreset('last_12')">Last 12 months</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="filter-actions">
                                    <button type="button" class="filter-btn-reset" @click="resetFilters()">Reset</button>
                                    <button type="submit" class="filter-btn-apply">Apply</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- Export -->
                    <div style="position:relative;" x-data="{exportOpen:false}">
                        <button class="toolbar-btn" @click="exportOpen=!exportOpen" style="height:38px;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Export
                        </button>
                        <div class="sort-dropdown" x-show="exportOpen" x-cloak @click.outside="exportOpen=false" style="min-width:180px;">
                            <?php
                            $exportBase = '/printflow/admin/reports_export.php?from='.urlencode($from).'&to='.urlencode($to).($branchId !== 'all' ? '&branch_id='.(int)$branchId : '');
                            ?>
                            <a href="<?php echo $exportBase; ?>&report=sales" class="sort-option" style="text-decoration:none;">CSV – Sales</a>
                            <a href="<?php echo $exportBase; ?>&report=orders" class="sort-option" style="text-decoration:none;">CSV – Orders</a>
                            <a href="<?php echo $exportBase; ?>&report=customers" class="sort-option" style="text-decoration:none;">CSV – Customers</a>
                            <?php
                            $excelBase = '/printflow/admin/reports_export_excel.php?from='.urlencode($from).'&to='.urlencode($to).($branchId !== 'all' ? '&branch_id='.(int)$branchId : '');
                            $printUrl = '/printflow/admin/reports_print.php?report=orders&from='.urlencode($from).'&to='.urlencode($to).'&branch_id='.($branchId === 'all' ? 'all' : (int)$branchId);
                            $printCustUrl = '/printflow/admin/reports_print.php?report=customers&from='.urlencode($from).'&to='.urlencode($to).'&branch_id='.($branchId === 'all' ? 'all' : (int)$branchId);
                            ?>
                            <a href="<?php echo $excelBase; ?>&report=orders" class="sort-option" style="text-decoration:none;">Excel – Orders</a>
                            <a href="<?php echo $excelBase; ?>&report=customers" class="sort-option" style="text-decoration:none;">Excel – Customers</a>
                            <a href="<?php echo $printUrl; ?>" target="_blank" class="sort-option" style="text-decoration:none;">Print – Orders</a>
                            <a href="<?php echo $printCustUrl; ?>" target="_blank" class="sort-option" style="text-decoration:none;">Print – Customers</a>
                        </div>
                    </div>
                    <!-- Print Report -->
                    <a href="<?php echo $printUrl ?? '/printflow/admin/reports_print.php?report=orders&from='.urlencode($from).'&to='.urlencode($to).'&branch_id='.($branchId === 'all' ? 'all' : (int)$branchId); ?>" target="_blank" class="toolbar-btn" style="height:38px;display:inline-flex;align-items:center;gap:8px;text-decoration:none;color:inherit;" title="Open print-optimized report">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Print Report
                    </a>
                </div>
            </div>

            <div class="ana-wrap">

            <?php if ($branch_empty): ?>
            <!-- ══ BRANCH EMPTY STATE ════════════════════════════════════════ -->
            <!-- KPI row shows zeroes -->
            <div class="kpi-row">
                <?php foreach ([
                    ['kpi-em',  'Total Orders',           '0',         'No transactions recorded'],
                    ['kpi-ind', 'Total Revenue',          '₱0',        'No paid orders'],
                    ['kpi-amb', 'Top Selling Service',    '—',         'No orders yet'],
                    ['kpi-vio', 'Top Customer Location',  '—',         'No location data'],
                ] as [$cls,$lbl,$val,$sub]): ?>
                <div class="kpi-card <?php echo $cls; ?>">
                    <div class="kpi-lbl"><?php echo $lbl; ?></div>
                    <div class="kpi-val empty-kpi"><?php echo $val; ?></div>
                    <div class="kpi-sub"><?php echo $sub; ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="ana-card">
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg width="28" height="28" fill="none" stroke="#9ca3af" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="empty-title">No sales data available for <?php echo htmlspecialchars($branchName); ?></div>
                    <div class="empty-sub">Reports and charts will appear once transactions are recorded for this branch.</div>
                </div>
            </div>

            <?php else: ?>
            <!-- ══ KPI ROW ═══════════════════════════════════════════════════ -->
            <div class="kpi-row">
                <!-- Total Orders -->
                <div class="kpi-card kpi-em" title="Count of all orders in the selected date range. Compare to previous period of equal length.">
                    <div class="kpi-lbl">Total Orders</div>
                    <div class="kpi-val"><?php echo number_format($total_orders); ?></div>
                    <div class="kpi-sub">
                        <?php if ($orders_delta !== null): ?>
                            <?php if ($orders_delta > 0): ?><span class="t-up">↑ <?php echo $orders_delta; ?>%</span>
                            <?php elseif ($orders_delta < 0): ?><span class="t-dn">↓ <?php echo abs($orders_delta); ?>%</span>
                            <?php else: ?><span class="t-fl">—</span><?php endif; ?>
                        <?php endif; ?>
                        vs prev period
                    </div>
                    <div class="kpi-updated">Last updated: <?php echo $last_updated; ?></div>
                </div>
                <!-- Revenue -->
                <div class="kpi-card kpi-ind" title="Sum of total_amount for orders with payment_status = Paid. Excludes unpaid/pending orders.">
                    <div class="kpi-lbl">Total Revenue</div>
                    <div class="kpi-val">₱<?php echo number_format($revenue, 0); ?></div>
                    <div class="kpi-sub">
                        <?php if ($revenue_delta !== null): ?>
                            <?php if ($revenue_delta > 0): ?><span class="t-up">↑ <?php echo $revenue_delta; ?>%</span>
                            <?php elseif ($revenue_delta < 0): ?><span class="t-dn">↓ <?php echo abs($revenue_delta); ?>%</span>
                            <?php else: ?><span class="t-fl">—</span><?php endif; ?>
                        <?php endif; ?>
                        vs last period · <?php echo $paid_orders; ?> paid
                    </div>
                </div>
                <!-- Top Product -->
                <div class="kpi-card kpi-amb" title="Product with highest quantity sold in the selected period.">
                    <div class="kpi-lbl">Top Selling Service</div>
                    <div class="kpi-val" style="font-size:15px;margin-top:4px;line-height:1.3;">
                        <?php echo $top_kpi_product ? htmlspecialchars(mb_substr($top_kpi_product['name'],0,22)) : '—'; ?>
                    </div>
                    <div class="kpi-sub"><?php echo $top_kpi_product ? number_format((int)$top_kpi_product['qty']).' units' : 'No data yet'; ?></div>
                </div>
                <!-- Top Location -->
                <div class="kpi-card kpi-vio" title="City with the most orders based on customer address.">
                    <div class="kpi-lbl">Top Customer Location</div>
                    <div class="kpi-val" style="font-size:15px;margin-top:4px;line-height:1.3;">
                        <?php echo $top_kpi_location ? htmlspecialchars(mb_substr(trim($top_kpi_location['city']),0,20)) : '—'; ?>
                    </div>
                    <div class="kpi-sub"><?php echo $top_kpi_location ? $top_kpi_location['cnt'].' orders' : 'No address data'; ?></div>
                </div>
            </div>

            <!-- ══ SALES TREND (12-Month) ════════════════════════════════════ -->
            <div class="ana-card">
                <div class="ana-hd">
                    <h3>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        12-Month Sales Trend
                    </h3>
                    <div style="display:flex;align-items:center;gap:8px;" class="no-print">
                        <span style="font-size:11px;color:#6b7280;">Show:</span>
                        <a href="<?php echo htmlspecialchars($reports_href_base.'?'.reports_page_query(['trend_metric'=>'revenue'])); ?>" class="toolbar-btn <?php echo $trend_metric==='revenue'?'active':''; ?>" style="height:32px;font-size:12px;padding:0 12px;">Revenue</a>
                        <a href="<?php echo htmlspecialchars($reports_href_base.'?'.reports_page_query(['trend_metric'=>'orders'])); ?>" class="toolbar-btn <?php echo $trend_metric==='orders'?'active':''; ?>" style="height:32px;font-size:12px;padding:0 12px;">Orders</a>
                        <span style="font-size:11px;color:#9ca3af;">· <?php echo $next_month_label; ?> forecast</span>
                    </div>
                </div>
                <div class="ana-bd">
                    <div class="ch-box" style="height:300px;"><div id="ch-trend"></div></div>
                </div>
            </div>

            <!-- ══ PRODUCT DEMAND FORECAST ═══════════════════════════════════ -->
            <div class="ana-card print-hide">
                <div class="ana-hd">
                    <h3>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Product Demand Forecast — Next 3 Months
                    </h3>
                    <div style="display:flex;align-items:center;gap:12px;font-size:11px;color:#6b7280;">
                        <span title="Solid lines = actual historical data. Dashed lines = predicted demand based on 6-month trend.">— Solid = Actual · - - Dashed = Forecast</span>
                    </div>
                </div>
                <div class="ana-bd">
                    <?php if (!$can_forecast): ?>
                    <div class="ch-empty">
                        <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <div style="font-weight:600;color:#6b7280;">Not enough data to generate a forecast</div>
                        <div style="font-size:12px;">Predictions will appear once at least <strong>20 orders</strong> are recorded in the last 6 months.</div>
                    </div>
                    <?php else: ?>
                    <div style="display:grid;grid-template-columns:1fr 280px;gap:24px;align-items:start;">
                        <div class="ch-box" style="height:290px;"><div id="ch-forecast"></div></div>
                        <div>
                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#9ca3af;margin-bottom:12px;">Top Predicted Demand</div>
                            <?php
                            $fc_colors = ['#00232b','#53C5E0','#0F4C5C','#3498DB','#6C5CE7','#3A86A8'];
                            $fc_max = 1;
                            foreach ($fc_series_data as $pd) $fc_max = max($fc_max, max($pd['fore']));
                            $fc_i = 0;
                            $demand_badges = ['high'=>'🔥 High Demand','moderate'=>'⚠️ Moderate','declining'=>'⬇️ Declining'];
                            foreach ($fc_series_data as $prod => $pd):
                                $pct = $fc_max > 0 ? round(max($pd['fore']) / $fc_max * 100) : 0;
                                $col = $fc_colors[$fc_i % count($fc_colors)];
                                $badge = $demand_badges[$pd['demand'] ?? 'moderate'] ?? '⚠️ Moderate';
                                $fc_i++;
                            ?>
                            <div style="margin-bottom:12px;">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;gap:6px;">
                                    <span style="font-size:12px;font-weight:600;color:#374151;"><?php echo htmlspecialchars(mb_substr($prod,0,18)); ?></span>
                                    <span style="font-size:10px;color:#6b7280;white-space:nowrap;" title="Demand trend: <?php echo $pd['demand']; ?>"><?php echo $badge; ?></span>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <div class="fc-prod-bar" style="flex:1;"><div class="fc-prod-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $col; ?>;"></div></div>
                                    <span style="font-size:11px;color:#6b7280;min-width:32px;">~<?php echo number_format(max($pd['fore'])); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ══ BEST SERVICES (H-Bar) | REVENUE DONUT ════════════════════ -->
            <div class="ana-grid">
                <div class="ana-card print-hide">
                    <div class="ana-hd">
                        <h3><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>Best Selling Services</h3>
                    </div>
                    <div class="ana-bd">
                        <?php if (!empty($top_products)): ?>
                        <div class="ch-box" style="height:280px;"><div id="ch-products"></div></div>
                        <?php else: ?>
                        <div class="ch-empty"><svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>No product data for this period</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ana-card">
                    <div class="ana-hd rev-donut-card-hd">
                        <h3 style="margin:0;"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/></svg>Revenue Distribution</h3>
                        <?php if (!empty($rev_donut) && $revenue_delta !== null): ?>
                        <span class="rev-donut-growth <?php echo $revenue_delta > 0 ? 'up' : ($revenue_delta < 0 ? 'dn' : ''); ?>">vs prior period: <?php echo $revenue_delta > 0 ? '+' : ''; ?><?php echo htmlspecialchars((string)$revenue_delta); ?>%</span>
                        <?php endif; ?>
                    </div>
                    <div class="ana-bd rev-donut-body">
                        <?php if (!empty($rev_donut)): ?>
                        <div class="rev-donut-row">
                            <div class="rev-donut-chart-wrap ch-box"><div id="ch-donut"></div></div>
                            <div class="rev-donut-legend-wrap">
                                <ul class="rev-donut-legend" aria-label="Revenue by service">
                                    <?php
                                    $di = 0;
                                    foreach ($rev_donut as $rd):
                                        $amt = round((float)($rd['revenue'] ?? 0), 2);
                                        $pct = $rev_donut_total > 0 ? round(($amt / $rev_donut_total) * 100, 1) : 0;
                                        $col = $donut_palette[$di % count($donut_palette)];
                                        $nm = (string)($rd['product_name'] ?? '');
                                        $short = $nm;
                                        if (mb_strlen($nm) > 36) {
                                            $short = rtrim(mb_substr($nm, 0, 36)) . '…';
                                        }
                                        $di++;
                                    ?>
                                    <li>
                                        <span class="rev-donut-swatch" style="background:<?php echo htmlspecialchars($col); ?>;"></span>
                                        <div class="rev-donut-legend-txt">
                                            <?php echo htmlspecialchars($short); ?>
                                            <span class="rev-donut-legend-meta">₱<?php echo number_format($amt, 0); ?> · <?php echo htmlspecialchars((string)$pct); ?>%</span>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="ch-empty"><svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/></svg>No revenue data for this period</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ══ SEASONAL HEATMAP ══════════════════════════════════════════ -->
            <div class="ana-card print-hide">
                <div class="ana-hd" style="align-items:center;">
                    <h3><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>Seasonal Demand Heatmap — <?php echo (int)$heatmap_year; ?></h3>
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <label class="heatmap-year-label" for="pf-heatmap-year">Year</label>
                        <select id="pf-heatmap-year" class="chart-select heatmap-year-select" aria-label="Heatmap year">
                            <?php for ($yy = $y_cal + 1; $yy >= $y_cal - 8; $yy--): ?>
                            <option value="<?php echo $yy; ?>" <?php echo $yy === (int)$heatmap_year ? 'selected' : ''; ?>><?php echo $yy; ?></option>
                            <?php endfor; ?>
                        </select>
                        <span style="font-size:11px;color:#6b7280;">Darker = higher volume</span>
                    </div>
                </div>
                <div class="ana-bd">
                    <?php if (!empty($heatmap_products)): ?>
                    <div class="ch-box" style="height:<?php echo max(200,count($heatmap_products)*46+50); ?>px;"><div id="ch-heatmap"></div></div>
                    <?php else: ?>
                    <div class="ch-empty"><svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>No heatmap data for <?php echo (int)$heatmap_year; ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ══ CUSTOMER LOCATIONS | CUSTOMIZATION USAGE ════════════════ -->
            <div class="ana-grid print-hide">
                <div class="ana-card">
                    <div class="ana-hd">
                        <h3><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Customer Locations</h3>
                        <?php if (!empty($customer_locations)): ?>
                        <span class="top-location-pill">Top Location: <?php echo htmlspecialchars(trim($customer_locations[0]['city'])); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="ana-bd">
                        <?php if (!empty($customer_locations)): ?>
                        <div class="ch-box" style="height:280px;"><div id="ch-locs"></div></div>
                        <?php else: ?>
                        <div class="ch-empty"><svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>No location data available</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ana-card">
                    <div class="ana-hd">
                        <h3><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14"/></svg>Customization Usage</h3>
                    </div>
                    <div class="ana-bd">
                        <?php if (!empty($custom_usage)): ?>
                        <div class="ch-box" style="height:280px;"><div id="ch-custom"></div></div>
                        <?php else: ?>
                        <div class="ch-empty"><svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16"/></svg>No customization data for this period</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ══ BRANCH COMPARISON ════════════════════════════════════════ -->
            <?php if (count($branch_perf) > 1): ?>
            <div class="ana-card print-hide">
                <div class="ana-hd">
                    <h3><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>Branch Performance Comparison</h3>
                </div>
                <div class="ana-bd">
                    <div class="ch-box" style="height:270px;"><div id="ch-branches"></div></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ══ ORDER STATUS | TOP CUSTOMERS ══════════════════════════════ -->
            <div class="ana-grid print-hide">
                <div class="ana-card">
                    <div class="ana-hd">
                        <h3><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>Order Status Breakdown</h3>
                    </div>
                    <div class="ana-bd">
                        <?php if (!empty($status_data)): ?>
                        <div class="ch-box" style="height:280px;"><div id="ch-status"></div></div>
                        <?php else: ?>
                        <div class="ch-empty">No orders for this period</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ana-card">
                    <div class="ana-hd">
                        <h3><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Top Customers</h3>
                    </div>
                    <div class="ana-bd ana-bd-0">
                        <?php if (!empty($top_customers)): ?>
                        <table class="rpt-tbl">
                            <thead><tr><th>#</th><th>Customer</th><th class="num">Orders</th><th class="num">Spent</th></tr></thead>
                            <tbody>
                            <?php foreach ($top_customers as $i => $tc): ?>
                            <tr>
                                <td style="color:#9ca3af;font-weight:700;"><?php echo $i+1; ?></td>
                                <td><div style="font-weight:600;"><?php echo htmlspecialchars($tc['name']); ?></div><div style="font-size:11px;color:#9ca3af;"><?php echo htmlspecialchars($tc['email']); ?></div></td>
                                <td class="num"><?php echo (int)$tc['orders']; ?></td>
                                <td class="num" style="color:#059669;">₱<?php echo number_format((float)$tc['spent'],2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="ch-empty">No customer data for this period</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ══ AI INSIGHTS + FORECAST PANEL ════════════════════════════ -->
            <div class="ins-panel print-hide">
                <div class="ins-title" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                    <span style="display:flex;align-items:center;gap:8px;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        Business Insights &amp; <?php echo $next_month_label; ?> Forecast
                    </span>
                    <a href="#ch-forecast" class="toolbar-btn" style="height:36px;background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.3);color:#fff;">View Detailed Forecast</a>
                </div>

                <?php if (!empty($active_events)): ?>
                <div style="margin-bottom:14px;">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:rgba(255,255,255,.5);margin-bottom:8px;">Active Seasonal Events</div>
                    <?php foreach ($active_events as $ev): ?>
                    <span class="ev-badge"><?php echo $ev['icon']; ?> <?php echo htmlspecialchars($ev['event']); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="fc-row">
                    <div class="fc-chip">
                        <div class="fc-lbl">📈 Predicted Revenue</div>
                        <div class="fc-val">₱<?php echo number_format($forecast_revenue,0); ?></div>
                        <div class="fc-sub"><?php echo $next_month_label; ?></div>
                    </div>
                    <div class="fc-chip">
                        <div class="fc-lbl">Predicted Orders</div>
                        <div class="fc-val"><?php echo number_format($forecast_orders); ?></div>
                        <div class="fc-sub"><?php echo $next_month_label; ?></div>
                    </div>
                    <?php if ($top_kpi_product): ?>
                    <div class="fc-chip">
                        <div class="fc-lbl">Top Forecast Service</div>
                        <div class="fc-val" style="font-size:14px;line-height:1.3;"><?php echo htmlspecialchars(mb_substr($top_kpi_product['name'],0,20)); ?></div>
                        <div class="fc-sub">Highest historical demand</div>
                    </div>
                    <?php endif; ?>
                    <div class="fc-chip">
                        <div class="fc-lbl">💰 Avg Order Value</div>
                        <div class="fc-val">₱<?php echo number_format($avg_val,0); ?></div>
                        <div class="fc-sub">This period</div>
                    </div>
                </div>

                <?php if (!empty($insights)): ?>
                <ul class="ins-list" style="margin-top:18px;line-height:1.7;">
                    <?php foreach ($insights as $ins): ?>
                    <li><?php echo $ins; ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>

            <!-- ══ INVENTORY ALERTS ════════════════════════════════════════ -->
            <?php if (!empty($low_stock)): ?>
            <div class="ana-card print-hide">
                <div class="ana-hd">
                    <h3 style="color:#ef4444;"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#ef4444;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>Low Stock Alerts</h3>
                    <span style="font-size:11px;color:#ef4444;"><?php echo count($low_stock); ?> item<?php echo count($low_stock)>1?'s':''; ?> need attention</span>
                </div>
                <div class="ana-bd ana-bd-0">
                    <table class="rpt-tbl">
                        <thead><tr><th>Item</th><th class="num">Stock on Hand</th><th class="num">Reorder Level</th><th style="width:100px;">Level</th></tr></thead>
                        <tbody>
                        <?php foreach ($low_stock as $ls):
                            $soh = (float)$ls['soh']; $rl = (float)$ls['reorder_level'];
                            $pct = $rl > 0 ? min(100, round($soh/$rl*100)) : 0;
                            $cls = $soh<=0 ? 'sk-danger' : ($pct<=50?'sk-warn':'sk-good');
                        ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($ls['name']); ?> <span style="font-size:11px;color:#9ca3af;"><?php echo htmlspecialchars($ls['unit']); ?></span></td>
                            <td class="num" style="color:<?php echo $soh<=0?'#ef4444':'#d97706'; ?>;"><?php echo number_format($soh,1); ?></td>
                            <td class="num" style="color:#6b7280;"><?php echo number_format($rl,1); ?></td>
                            <td><div class="sk-bar"><div class="sk-fill <?php echo $cls; ?>" style="width:<?php echo max(3,$pct); ?>%;"></div></div></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- ══ RECENT TRANSACTIONS ════════════════════════════════════ -->
            <div class="ana-card print-page-break">
                <div class="ana-hd">
                    <h3><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>Recent Transactions</h3>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <?php
                        $txn_base = array_merge($_GET, ['txn_page'=>1]);
                        $tabs = [['all','All'],['paid','Paid'],['unpaid','Unpaid'],['pending','Pending']];
                        foreach ($tabs as [$k,$l]):
                            $txn_base['txn_pay'] = $k;
                            $url = '?'.http_build_query($txn_base);
                            $act = $txn_payment_filter === $k ? 'active' : '';
                        ?>
                        <a href="<?php echo htmlspecialchars($url); ?>" class="toolbar-btn <?php echo $act; ?>" style="height:32px;font-size:12px;padding:0 12px;"><?php echo $l; ?></a>
                        <?php endforeach; ?>
                        <span style="font-size:12px;color:#6b7280;margin-left:8px;"><?php echo number_format($txn_count); ?> orders</span>
                    </div>
                </div>
                <div class="ana-bd ana-bd-0">
                    <?php if (!empty($recent_orders)): ?>
                    <div style="overflow-x:auto;">
                        <table class="rpt-tbl rpt-tbl-clickable">
                            <thead><tr><th>Order #</th><th>Customer</th><th>Date</th><th class="num">Amount</th><th>Payment</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($recent_orders as $ro):
                                $pb = match($ro['payment_status']) { 'Paid'=>'b-green','Pending'=>'b-yellow', default=>'b-red' };
                                $sb = match($ro['status']) { 'Completed'=>'b-green','Processing'=>'b-blue','Pending'=>'b-yellow','Ready for Pickup'=>'b-cyan','Cancelled'=>'b-red','Design Approved'=>'b-purple', default=>'b-gray' };
                                $orderUrl = '/printflow/admin/orders_management.php?order_id='.(int)$ro['order_id'];
                            ?>
                            <tr onclick="window.location.href='<?php echo htmlspecialchars($orderUrl); ?>'" style="cursor:pointer;">
                                <td style="font-weight:700;color:#00232b;">#<?php echo $ro['order_id']; ?></td>
                                <td style="font-weight:500;"><?php echo htmlspecialchars($ro['customer_name']); ?></td>
                                <td style="color:#6b7280;white-space:nowrap;"><?php echo date('M d, Y',strtotime($ro['order_date'])); ?></td>
                                <td class="num">₱<?php echo number_format((float)$ro['total_amount'],2); ?></td>
                                <td><span class="badge <?php echo $pb; ?>"><?php echo $ro['payment_status']; ?></span></td>
                                <td><span class="badge <?php echo $sb; ?>"><?php echo $ro['status']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php echo render_pagination($txn_page, $txn_pages, array_filter(['from'=>$from,'to'=>$to,'txn_pay'=>$txn_payment_filter,'branch_id'=>$branchId !== 'all' ? $branchId : null,'chart_sort'=>$chart_sort,'trend_metric'=>$trend_metric,'heatmap_year'=>$heatmap_year]), 'txn_page'); ?>
                    <?php else: ?>
                    <div class="ch-empty">No transactions for this period</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php endif; /* branch_empty */ ?>

            <!-- ── Print Footer (visible only when printing) ── -->
            <div class="print-report-footer">
                Generated by PrintFlow System &nbsp;·&nbsp; <?php echo date('F j, Y'); ?>
            </div>

            </div><!-- /.ana-wrap -->
        </main>
    </div>
</div>

<?php include __DIR__ . '/../includes/reports_analytics_scripts.php'; ?>
</body>
</html>
