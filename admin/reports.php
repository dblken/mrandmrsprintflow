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

// ── Branch context ────────────────────────────────────────────────────────────
$branchCtx = init_branch_context(false);
$branchId  = $branchCtx['selected_branch_id'];   // 'all' | int
$branchName = $branchCtx['branch_name'];
[$bSql, $bTypes, $bParams] = branch_where_parts('o', $branchId);

// ── Date range ────────────────────────────────────────────────────────────────
$from  = date('Y-m-d', strtotime($_GET['from'] ?? date('Y-m-01')));
$to    = date('Y-m-d', strtotime($_GET['to']   ?? date('Y-m-d')));
$toEnd = $to . ' 23:59:59';

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
        $fc_series_data[$prod] = [
            'hist' => $hist,
            'fore' => pf_forecast3($hist),
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
            "SELECT p.name AS product_name,
                    SUM(oi.quantity) as qty_sold,
                    SUM(oi.quantity * oi.unit_price) as revenue
             FROM order_items oi
             JOIN products p ON oi.product_id=p.product_id
             JOIN orders o ON oi.order_id=o.order_id
             WHERE o.order_date BETWEEN ? AND ?$b
             GROUP BY p.product_id, p.name ORDER BY qty_sold DESC LIMIT 10",
            'ss'.$bt, array_merge([$from,$toEnd],$bp)
        ) ?: [];
    } catch(Exception $e){}
}

// ── 7. Revenue distribution (donut) ──────────────────────────────────────────
$rev_donut = array_slice($top_products, 0, 7);

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
    } catch(Exception $e){}
}

// ── 9. Seasonal heatmap (current year, by branch) ────────────────────────────
$heatmap_products = [];
if (!$branch_empty) {
    try {
        [$b,$bt,$bp] = branch_where_parts('o', $branchId);
        $hmRaw = db_query(
            "SELECT p.name AS product, MONTH(o.order_date) as mo, SUM(oi.quantity) as qty
             FROM order_items oi
             JOIN products p ON oi.product_id=p.product_id
             JOIN orders o ON oi.order_id=o.order_id
             WHERE YEAR(o.order_date) = YEAR(NOW())$b
             GROUP BY p.product_id, p.name, MONTH(o.order_date)
             ORDER BY p.name, mo",
            $bt, $bp
        ) ?: [];
    } catch(Exception $e){ $hmRaw = []; }

    foreach ($hmRaw as $r) {
        $p = $r['product'];
        if (!isset($heatmap_products[$p])) $heatmap_products[$p] = array_fill(1, 12, 0);
        $heatmap_products[$p][(int)$r['mo']] += (int)$r['qty'];
    }
    arsort($heatmap_products);
    $heatmap_products = array_slice($heatmap_products, 0, 8, true);
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
if (!$branch_empty) {
    try {
        [$b,$bt,$bp] = branch_where_parts('o', $branchId);
        $txn_count = (int)(db_query(
            "SELECT COUNT(*) as cnt FROM orders o WHERE o.order_date BETWEEN ? AND ?$b",
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
             WHERE o.order_date BETWEEN ? AND ?$b2
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $page_title; ?></title>
<link rel="stylesheet" href="/printflow/public/assets/css/output.css">
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js"></script>
<?php include __DIR__ . '/../includes/admin_style.php'; ?>
<?php render_branch_css(); ?>
<style>
/* ── Layout ─────────────────────────── */
.ana-wrap { display:flex; flex-direction:column; gap:22px; }
.ana-grid  { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.ana-grid3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; }
@media(max-width:960px){ .ana-grid,.ana-grid3{ grid-template-columns:1fr; } }

/* ── Card ───────────────────────────── */
.ana-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; }
.ana-hd   { display:flex; align-items:center; justify-content:space-between; padding:16px 20px 12px; border-bottom:1px solid #f3f4f6; gap:10px; flex-wrap:wrap; }
.ana-hd h3{ margin:0; font-size:13.5px; font-weight:700; color:#1f2937; display:flex; align-items:center; gap:7px; white-space:nowrap; }
.ana-hd h3 svg{ width:15px; height:15px; color:#6366f1; flex-shrink:0; }
.ana-bd   { padding:16px 20px 20px; }
.ana-bd-0 { padding:0; }

/* ── KPI ────────────────────────────── */
.kpi-row  { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; }
@media(max-width:900px){ .kpi-row{ grid-template-columns:repeat(2,1fr); } }
.kpi-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:18px 20px; position:relative; overflow:hidden; transition:box-shadow .2s; }
.kpi-card:hover { box-shadow:0 4px 12px rgba(0,0,0,.07); }
.kpi-card::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; }
.kpi-ind::before  { background:linear-gradient(90deg,#6366f1,#818cf8); }
.kpi-em::before   { background:linear-gradient(90deg,#059669,#34d399); }
.kpi-amb::before  { background:linear-gradient(90deg,#f59e0b,#fcd34d); }
.kpi-vio::before  { background:linear-gradient(90deg,#7c3aed,#a78bfa); }
.kpi-lbl  { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:#9ca3af; margin-bottom:8px; }
.kpi-val  { font-size:24px; font-weight:800; color:#1f2937; line-height:1.1; margin-bottom:5px; }
.kpi-sub  { font-size:12px; color:#6b7280; display:flex; align-items:center; gap:4px; flex-wrap:wrap; }
.t-up     { color:#059669; font-weight:700; }
.t-dn     { color:#ef4444; font-weight:700; }
.t-fl     { color:#6b7280; }

/* ── Filter bar ─────────────────────── */
.flt-bar  { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; }
.flt-ctrl { display:inline-flex; align-items:center; gap:10px; flex-wrap:wrap; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:7px 14px; }
.flt-ctrl label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#6b7280; }
.flt-ctrl input[type=date] { padding:5px 9px; border:1px solid #e5e7eb; border-radius:7px; font-size:12px; }
.flt-ctrl input[type=date]:focus { outline:none; border-color:#6366f1; }
.flt-btn  { padding:6px 16px; background:#6366f1; color:#fff; border:none; border-radius:7px; font-size:12px; font-weight:700; cursor:pointer; }

/* ── Empty state ────────────────────── */
.empty-state { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:56px 24px; text-align:center; }
.empty-icon  { width:56px; height:56px; border-radius:50%; background:#f3f4f6; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; }
.empty-title { font-size:16px; font-weight:700; color:#1f2937; margin-bottom:6px; }
.empty-sub   { font-size:13px; color:#6b7280; max-width:340px; }
.empty-kpi   { font-size:24px; font-weight:800; color:#d1d5db; }

/* ── Chart boxes ────────────────────── */
.ch-box     { width:100%; }
.ch-empty   { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; color:#9ca3af; font-size:13px; padding:40px 16px; }
.ch-empty svg{ opacity:.35; }

/* ── Tables ─────────────────────────── */
.rpt-tbl { width:100%; border-collapse:collapse; }
.rpt-tbl th { padding:8px 14px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#6b7280; background:#f9fafb; text-align:left; border-bottom:2px solid #e5e7eb; }
.rpt-tbl td { padding:9px 14px; font-size:13px; border-bottom:1px solid #f3f4f6; color:#374151; }
.rpt-tbl tr:hover td{ background:#fafafa; }
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
.ins-panel { background:linear-gradient(135deg,#1e1b4b 0%,#312e81 55%,#4338ca 100%); border-radius:14px; padding:22px 26px; color:#fff; }
.ins-title { font-size:14px; font-weight:700; margin-bottom:14px; display:flex; align-items:center; gap:7px; }
.ins-list  { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:9px; }
.ins-list li{ font-size:13px; line-height:1.55; opacity:.9; display:flex; gap:9px; align-items:flex-start; }
.ins-list li::before{ content:'→'; color:#818cf8; font-weight:700; flex-shrink:0; }
.ins-list strong{ color:#e0e7ff; }

/* ── Forecast chips ─────────────────── */
.fc-row  { display:flex; gap:14px; flex-wrap:wrap; margin-top:16px; }
.fc-chip { background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.15); border-radius:10px; padding:12px 16px; flex:1; min-width:140px; }
.fc-lbl  { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:rgba(255,255,255,.55); margin-bottom:4px; }
.fc-val  { font-size:20px; font-weight:800; color:#e0e7ff; line-height:1.1; }
.fc-sub  { font-size:11px; color:rgba(255,255,255,.45); margin-top:2px; }

/* ── Seasonal event badges ──────────── */
.ev-badge { display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.2); border-radius:20px; padding:5px 12px; font-size:12px; font-weight:600; margin:4px 4px 0 0; }

/* ── Forecast product bars ──────────── */
.fc-prod-bar { height:6px; background:#e0e7ff; border-radius:3px; overflow:hidden; }
.fc-prod-fill{ height:100%; border-radius:3px; }

/* ── Stock bar ──────────────────────── */
.sk-bar      { height:5px; background:#f3f4f6; border-radius:3px; overflow:hidden; }
.sk-fill     { height:100%; border-radius:3px; }
.sk-good     { background:#10b981; }
.sk-warn     { background:#f59e0b; }
.sk-danger   { background:#ef4444; }

/* ── Print ──────────────────────────── */
@media print {
    .sidebar,.mobile-header,header,.no-print{ display:none !important; }
    .main-content{ margin-left:0 !important; }
    .ana-card{ break-inside:avoid; margin-bottom:14px; }
    .ana-grid,.ana-grid3{ display:block !important; }
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
            <button class="btn-secondary no-print" onclick="window.print()" style="display:flex;align-items:center;gap:5px;">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print
            </button>
        </header>
        <main>
            <?php render_branch_context_banner($branchCtx['branch_name']); ?>

            <!-- ── Date Filter ── -->
            <div class="flt-bar no-print" style="margin-bottom:22px;">
                <div style="font-size:13px;color:#6b7280;">
                    <?php echo htmlspecialchars($branchName); ?> &nbsp;·&nbsp;
                    <?php echo date('M d, Y',strtotime($from)); ?> – <?php echo date('M d, Y',strtotime($to)); ?>
                </div>
                <form method="GET" class="flt-ctrl">
                    <?php if ($branchId !== 'all'): ?>
                    <input type="hidden" name="branch_id" value="<?php echo (int)$branchId; ?>">
                    <?php endif; ?>
                    <label>From</label>
                    <input type="date" name="from" value="<?php echo $from; ?>">
                    <label>To</label>
                    <input type="date" name="to"   value="<?php echo $to; ?>">
                    <button type="submit" class="flt-btn">Apply</button>
                </form>
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
                <div class="kpi-card kpi-em">
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
                </div>
                <!-- Revenue -->
                <div class="kpi-card kpi-ind">
                    <div class="kpi-lbl">Total Revenue</div>
                    <div class="kpi-val">₱<?php echo number_format($revenue, 0); ?></div>
                    <div class="kpi-sub">
                        <?php if ($revenue_delta !== null): ?>
                            <?php if ($revenue_delta > 0): ?><span class="t-up">↑ <?php echo $revenue_delta; ?>%</span>
                            <?php elseif ($revenue_delta < 0): ?><span class="t-dn">↓ <?php echo abs($revenue_delta); ?>%</span>
                            <?php else: ?><span class="t-fl">—</span><?php endif; ?>
                        <?php endif; ?>
                        <?php echo $paid_orders; ?> paid
                    </div>
                </div>
                <!-- Top Product -->
                <div class="kpi-card kpi-amb">
                    <div class="kpi-lbl">Top Selling Service</div>
                    <div class="kpi-val" style="font-size:15px;margin-top:4px;line-height:1.3;">
                        <?php echo $top_kpi_product ? htmlspecialchars(mb_substr($top_kpi_product['name'],0,22)) : '—'; ?>
                    </div>
                    <div class="kpi-sub"><?php echo $top_kpi_product ? number_format((int)$top_kpi_product['qty']).' units' : 'No data yet'; ?></div>
                </div>
                <!-- Top Location -->
                <div class="kpi-card kpi-vio">
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
                    <span style="font-size:11.5px;color:#6366f1;font-weight:600;">
                        <?php echo $next_month_label; ?> forecast included
                    </span>
                </div>
                <div class="ana-bd">
                    <div class="ch-box" style="height:290px;"><div id="ch-trend"></div></div>
                </div>
            </div>

            <!-- ══ PRODUCT DEMAND FORECAST ═══════════════════════════════════ -->
            <div class="ana-card">
                <div class="ana-hd">
                    <h3>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Product Demand Forecast — Next 3 Months
                    </h3>
                    <span style="font-size:11px;color:#6b7280;">Based on 6-month moving average · solid = actual · dashed = forecast</span>
                </div>
                <div class="ana-bd">
                    <?php if (!$can_forecast): ?>
                    <div class="ch-empty">
                        <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <div style="font-weight:600;color:#6b7280;">Not enough data to generate a forecast</div>
                        <div style="font-size:12px;">Predictions will appear once at least <strong>20 orders</strong> are recorded in the last 6 months.</div>
                    </div>
                    <?php else: ?>
                    <div style="display:grid;grid-template-columns:1fr 260px;gap:20px;align-items:start;">
                        <div class="ch-box" style="height:290px;"><div id="ch-forecast"></div></div>
                        <div>
                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#9ca3af;margin-bottom:12px;">Top Predicted Demand</div>
                            <?php
                            $fc_colors = ['#6366f1','#8b5cf6','#ec4899','#f97316','#10b981','#3b82f6'];
                            $fc_max = 1;
                            foreach ($fc_series_data as $pd) $fc_max = max($fc_max, max($pd['fore']));
                            $fc_i = 0;
                            foreach ($fc_series_data as $prod => $pd):
                                $pct = $fc_max > 0 ? round(max($pd['fore']) / $fc_max * 100) : 0;
                                $col = $fc_colors[$fc_i % count($fc_colors)]; $fc_i++;
                            ?>
                            <div style="margin-bottom:10px;">
                                <div style="display:flex;justify-content:space-between;margin-bottom:3px;">
                                    <span style="font-size:11.5px;font-weight:600;color:#374151;"><?php echo htmlspecialchars(mb_substr($prod,0,22)); ?></span>
                                    <span style="font-size:11px;color:#6b7280;">~<?php echo number_format(max($pd['fore'])); ?></span>
                                </div>
                                <div class="fc-prod-bar"><div class="fc-prod-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $col; ?>;"></div></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ══ BEST SERVICES (H-Bar) | REVENUE DONUT ════════════════════ -->
            <div class="ana-grid">
                <div class="ana-card">
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
                    <div class="ana-hd">
                        <h3><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/></svg>Revenue Distribution</h3>
                    </div>
                    <div class="ana-bd">
                        <?php if (!empty($rev_donut)): ?>
                        <div class="ch-box" style="height:280px;"><div id="ch-donut"></div></div>
                        <?php else: ?>
                        <div class="ch-empty"><svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/></svg>No revenue data for this period</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ══ SEASONAL HEATMAP ══════════════════════════════════════════ -->
            <div class="ana-card">
                <div class="ana-hd">
                    <h3><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>Seasonal Demand Heatmap — <?php echo date('Y'); ?></h3>
                    <span style="font-size:11px;color:#6b7280;">Darker color = higher demand volume</span>
                </div>
                <div class="ana-bd">
                    <?php if (!empty($heatmap_products)): ?>
                    <div class="ch-box" style="height:<?php echo max(200,count($heatmap_products)*46+50); ?>px;"><div id="ch-heatmap"></div></div>
                    <?php else: ?>
                    <div class="ch-empty"><svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>No heatmap data for <?php echo date('Y'); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ══ CUSTOMER LOCATIONS | CUSTOMIZATION USAGE ════════════════ -->
            <div class="ana-grid">
                <div class="ana-card">
                    <div class="ana-hd">
                        <h3><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Customer Locations</h3>
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
            <div class="ana-card">
                <div class="ana-hd">
                    <h3><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>Branch Performance Comparison</h3>
                </div>
                <div class="ana-bd">
                    <div class="ch-box" style="height:270px;"><div id="ch-branches"></div></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ══ ORDER STATUS | TOP CUSTOMERS ══════════════════════════════ -->
            <div class="ana-grid">
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
            <div class="ins-panel">
                <div class="ins-title">
                    <svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    Business Insights &amp; <?php echo $next_month_label; ?> Forecast
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
                        <div class="fc-lbl">Predicted Revenue</div>
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
                        <div class="fc-lbl">Avg Order Value</div>
                        <div class="fc-val">₱<?php echo number_format($avg_val,0); ?></div>
                        <div class="fc-sub">This period</div>
                    </div>
                </div>

                <?php if (!empty($insights)): ?>
                <ul class="ins-list" style="margin-top:18px;">
                    <?php foreach ($insights as $ins): ?>
                    <li><?php echo $ins; ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>

            <!-- ══ INVENTORY ALERTS ════════════════════════════════════════ -->
            <?php if (!empty($low_stock)): ?>
            <div class="ana-card">
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
            <div class="ana-card">
                <div class="ana-hd">
                    <h3><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>Recent Transactions</h3>
                    <span style="font-size:12px;color:#6b7280;"><?php echo number_format($txn_count); ?> orders</span>
                </div>
                <div class="ana-bd ana-bd-0">
                    <?php if (!empty($recent_orders)): ?>
                    <div style="overflow-x:auto;">
                        <table class="rpt-tbl">
                            <thead><tr><th>Order #</th><th>Customer</th><th>Date</th><th class="num">Amount</th><th>Payment</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($recent_orders as $ro):
                                $pb = match($ro['payment_status']) { 'Paid'=>'b-green','Pending'=>'b-yellow', default=>'b-red' };
                                $sb = match($ro['status']) { 'Completed'=>'b-green','Processing'=>'b-blue','Pending'=>'b-yellow','Ready for Pickup'=>'b-cyan','Cancelled'=>'b-red','Design Approved'=>'b-purple', default=>'b-gray' };
                            ?>
                            <tr>
                                <td style="font-weight:700;color:#6366f1;">#<?php echo $ro['order_id']; ?></td>
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
                    <?php echo render_pagination($txn_page, $txn_pages, ['from'=>$from,'to'=>$to,'txn_page'=>$txn_page]); ?>
                    <?php else: ?>
                    <div class="ch-empty">No transactions for this period</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php endif; /* branch_empty */ ?>

            </div><!-- /.ana-wrap -->
        </main>
    </div>
</div>

<script>
const PF_PAL = ['#6366f1','#8b5cf6','#ec4899','#f97316','#10b981','#3b82f6','#f59e0b','#14b8a6','#84cc16','#06b6d4'];
const PF_OPT = { toolbar:{show:false}, animations:{speed:500,animateGradually:{enabled:true,delay:80}}, fontFamily:'inherit' };

<?php if (!$branch_empty): ?>

// ── 1. Sales Trend ─────────────────────────────────────────────────────────
(function(){
    const labels   = <?php echo json_encode(array_merge($trend12_labels, [$next_month_label])); ?>;
    const revs     = <?php echo json_encode(array_merge($trend12_revenues, [$forecast_revenue])); ?>;
    const ords     = <?php echo json_encode(array_merge($trend12_orders, [$forecast_orders])); ?>;
    const fcast    = <?php echo json_encode($next_month_label); ?>;

    new ApexCharts(document.getElementById('ch-trend'), {
        chart: {...PF_OPT, type:'area', height:290},
        series:[
            {name:'Revenue (₱)', data:revs, type:'area'},
            {name:'Orders',      data:ords, type:'line'}
        ],
        xaxis:{categories:labels, labels:{rotate:-30, style:{fontSize:'10px'}}},
        yaxis:[
            {labels:{formatter:v=>'₱'+Number(v).toLocaleString()}},
            {opposite:true, labels:{formatter:v=>Math.round(v)}}
        ],
        colors:['#6366f1','#10b981'],
        fill:{type:['gradient','transparent'], gradient:{shadeIntensity:.5,opacityFrom:.3,opacityTo:.03}},
        stroke:{curve:'smooth', width:[2.5,2], dashArray:[0,4]},
        markers:{size:3, hover:{size:5}},
        tooltip:{shared:true, intersect:false,
            y:[{formatter:v=>'₱'+Number(v||0).toLocaleString(undefined,{minimumFractionDigits:0})},{formatter:v=>(v||0)+' orders'}]},
        annotations:{xaxis:[{x:fcast, borderColor:'#6366f1', strokeDashArray:4,
            label:{text:'Forecast',style:{color:'#fff',background:'#6366f1',fontSize:'10px'}}}]},
        legend:{position:'top',horizontalAlign:'right',fontSize:'12px'},
        grid:{borderColor:'#f3f4f6',strokeDashArray:4}
    }).render();
})();

// ── 2. Product Demand Forecast ─────────────────────────────────────────────
<?php if ($can_forecast && !empty($fc_series_data)): ?>
(function(){
    const allLabels = <?php echo json_encode($fc_all_labels); ?>;
    const fcastStart = <?php echo json_encode(reset($fc_fore_labels)); ?>;
    const series = [];
    const colors = [];
    const dashes = [];
    const pal = PF_PAL;
    let ci = 0;

    <?php
    $fc_js_data = [];
    foreach ($fc_series_data as $prod => $pd) {
        $fc_js_data[] = [
            'name' => $prod,
            'hist' => $pd['hist'],
            'fore' => $pd['fore'],
        ];
    }
    echo 'const fcData = '.json_encode($fc_js_data).";\n";
    ?>

    fcData.forEach(function(p, idx){
        const color = pal[idx % pal.length];
        // Historical series (6 points + 3 nulls)
        const histData = [...p.hist, null, null, null];
        // Forecast series (5 nulls + bridge + 3 forecast)
        const foreData = [...new Array(5).fill(null), p.hist[p.hist.length-1], ...p.fore];

        series.push({name: p.name + ' (actual)',   data: histData});
        series.push({name: p.name + ' (forecast)', data: foreData});
        colors.push(color, color);
        dashes.push(0, 6);
        ci++;
    });

    new ApexCharts(document.getElementById('ch-forecast'), {
        chart: {...PF_OPT, type:'line', height:290},
        series: series,
        xaxis: {categories: allLabels, labels:{style:{fontSize:'10px'}, rotate:-30}},
        yaxis: {labels:{formatter:v=>v!=null?Math.round(v):''}},
        colors: colors,
        stroke: {curve:'smooth', width:2, dashArray:dashes},
        markers:{size:2, hover:{size:4}},
        tooltip:{shared:false, intersect:true, y:{formatter:v=>v!=null?v+' orders':'-'}},
        annotations:{xaxis:[{x:fcastStart, borderColor:'#f59e0b', strokeDashArray:5,
            label:{text:'Forecast →',style:{color:'#fff',background:'#f59e0b',fontSize:'10px'}}}]},
        legend:{show:false},
        grid:{borderColor:'#f3f4f6',strokeDashArray:4}
    }).render();
})();
<?php endif; ?>

// ── 3. Best Selling Products (H-Bar) ───────────────────────────────────────
<?php if (!empty($top_products)): ?>
(function(){
    const names = <?php echo json_encode(array_map(fn($p)=>mb_substr($p['product_name'],0,30), $top_products)); ?>;
    const qtys  = <?php echo json_encode(array_map(fn($p)=>(int)$p['qty_sold'], $top_products)); ?>;
    new ApexCharts(document.getElementById('ch-products'), {
        chart:{...PF_OPT, type:'bar', height:280},
        plotOptions:{bar:{horizontal:true, borderRadius:5, distributed:true}},
        series:[{name:'Units Sold', data:qtys}],
        xaxis:{categories:names, labels:{style:{fontSize:'10px'}}},
        colors:PF_PAL, legend:{show:false},
        dataLabels:{enabled:true, offsetX:4, style:{fontSize:'10px',colors:['#374151']}},
        tooltip:{y:{formatter:v=>v+' units'}},
        grid:{borderColor:'#f3f4f6', xaxis:{lines:{show:true}}}
    }).render();
})();
<?php endif; ?>

// ── 4. Revenue Donut ───────────────────────────────────────────────────────
<?php if (!empty($rev_donut)): ?>
(function(){
    const labels = <?php echo json_encode(array_map(fn($p)=>$p['product_name'], $rev_donut)); ?>;
    const vals   = <?php echo json_encode(array_map(fn($p)=>round((float)$p['revenue'],2), $rev_donut)); ?>;
    const total  = vals.reduce((a,b)=>a+b,0);
    new ApexCharts(document.getElementById('ch-donut'), {
        chart:{...PF_OPT, type:'donut', height:280},
        series:vals, labels:labels, colors:PF_PAL,
        plotOptions:{pie:{donut:{size:'62%', labels:{show:true, total:{show:true, label:'Total',
            formatter:()=>'₱'+total.toLocaleString(undefined,{minimumFractionDigits:0})}}}}},
        tooltip:{y:{formatter:v=>'₱'+Number(v).toLocaleString(undefined,{minimumFractionDigits:2})}},
        legend:{position:'bottom', fontSize:'11px', itemMargin:{vertical:3}},
        dataLabels:{enabled:true, formatter:v=>v.toFixed(1)+'%', style:{fontSize:'10px'}}
    }).render();
})();
<?php endif; ?>

// ── 5. Seasonal Heatmap ────────────────────────────────────────────────────
<?php if (!empty($heatmap_products)): ?>
(function(){
    const series = <?php
        $hm = [];
        foreach ($heatmap_products as $prod => $mo) {
            $row = [];
            for ($m=1;$m<=12;$m++) {
                $row[] = ['x'=>date('M',mktime(0,0,0,$m,1)), 'y'=>(int)($mo[$m]??0)];
            }
            $hm[] = ['name'=>$prod, 'data'=>$row];
        }
        echo json_encode($hm);
    ?>;
    new ApexCharts(document.getElementById('ch-heatmap'), {
        chart:{...PF_OPT, type:'heatmap', height:<?php echo max(200,count($heatmap_products)*46+50); ?>},
        series:series, colors:['#6366f1'],
        plotOptions:{heatmap:{enableShades:true, shadeIntensity:.85, colorScale:{ranges:[
            {from:0,to:0,   color:'#f1f5f9', name:'None'},
            {from:1,to:5,   color:'#c7d2fe', name:'Low'},
            {from:6,to:15,  color:'#818cf8', name:'Medium'},
            {from:16,to:999,color:'#3730a3', name:'High'}
        ]}}},
        dataLabels:{enabled:true, style:{fontSize:'10px',colors:['#1e293b']}},
        tooltip:{y:{formatter:v=>v+' units'}},
        xaxis:{labels:{style:{fontSize:'10px'}}},
        yaxis:{labels:{style:{fontSize:'10px'}}},
        legend:{position:'bottom', fontSize:'11px'}
    }).render();
})();
<?php endif; ?>

// ── 6. Customer Locations ──────────────────────────────────────────────────
<?php if (!empty($customer_locations)): ?>
(function(){
    const cities = <?php echo json_encode(array_map(fn($l)=>trim($l['city']), $customer_locations)); ?>;
    const cnts   = <?php echo json_encode(array_map(fn($l)=>(int)$l['orders'], $customer_locations)); ?>;
    new ApexCharts(document.getElementById('ch-locs'), {
        chart:{...PF_OPT, type:'bar', height:280},
        series:[{name:'Orders', data:cnts}],
        xaxis:{categories:cities, labels:{style:{fontSize:'10px'}, rotate:-30}},
        colors:['#6366f1'],
        plotOptions:{bar:{borderRadius:6, columnWidth:'55%', distributed:true}},
        legend:{show:false},
        dataLabels:{enabled:true, style:{fontSize:'10px',colors:['#374151']}},
        tooltip:{y:{formatter:v=>v+' orders'}},
        grid:{borderColor:'#f3f4f6'}
    }).render();
})();
<?php endif; ?>

// ── 7. Customization Usage (Stacked H-Bar) ─────────────────────────────────
<?php if (!empty($custom_usage)): ?>
(function(){
    const prods = <?php echo json_encode(array_map(fn($c)=>mb_substr($c['product'],0,22),$custom_usage)); ?>;
    const cust  = <?php echo json_encode(array_map(fn($c)=>(int)$c['custom_count'],$custom_usage)); ?>;
    const tmpl  = <?php echo json_encode(array_map(fn($c)=>(int)$c['template_count'],$custom_usage)); ?>;
    new ApexCharts(document.getElementById('ch-custom'), {
        chart:{...PF_OPT, type:'bar', height:280, stacked:true},
        series:[{name:'Custom Upload',data:cust},{name:'Template / No Upload',data:tmpl}],
        xaxis:{categories:prods, labels:{style:{fontSize:'10px'}}},
        colors:['#6366f1','#e0e7ff'],
        plotOptions:{bar:{horizontal:true, borderRadius:4, barHeight:'60%'}},
        legend:{position:'bottom', fontSize:'11px'},
        tooltip:{shared:true, intersect:false},
        grid:{borderColor:'#f3f4f6'}
    }).render();
})();
<?php endif; ?>

// ── 8. Branch Comparison (Grouped Bar) ─────────────────────────────────────
<?php if (count($branch_perf) > 1): ?>
(function(){
    const branches = <?php echo json_encode(array_map(fn($b)=>$b['branch_name'],$branch_perf)); ?>;
    const revs     = <?php echo json_encode(array_map(fn($b)=>round((float)$b['revenue'],2),$branch_perf)); ?>;
    const ords     = <?php echo json_encode(array_map(fn($b)=>(int)$b['orders'],$branch_perf)); ?>;
    new ApexCharts(document.getElementById('ch-branches'), {
        chart:{...PF_OPT, type:'bar', height:270},
        series:[{name:'Revenue (₱)',data:revs},{name:'Orders',data:ords}],
        xaxis:{categories:branches, labels:{style:{fontSize:'11px'}}},
        yaxis:[
            {title:{text:'Revenue',style:{color:'#9ca3af'}}, labels:{formatter:v=>'₱'+v.toLocaleString()}},
            {opposite:true, title:{text:'Orders',style:{color:'#9ca3af'}}, labels:{formatter:v=>Math.round(v)}}
        ],
        colors:['#6366f1','#10b981'],
        plotOptions:{bar:{borderRadius:4, columnWidth:'45%'}},
        legend:{position:'top', fontSize:'12px'},
        tooltip:{shared:true, intersect:false,
            y:[{formatter:v=>'₱'+Number(v).toLocaleString(undefined,{minimumFractionDigits:2})},{formatter:v=>v+' orders'}]},
        grid:{borderColor:'#f3f4f6'}
    }).render();
})();
<?php endif; ?>

// ── 9. Order Status (Donut) ────────────────────────────────────────────────
<?php if (!empty($status_data)): ?>
(function(){
    const statusColors = {'Pending':'#f59e0b','Processing':'#3b82f6','Ready for Pickup':'#06b6d4','Completed':'#10b981','Cancelled':'#ef4444','Design Approved':'#8b5cf6'};
    const labels = <?php echo json_encode(array_map(fn($d)=>$d['status'],$status_data)); ?>;
    const vals   = <?php echo json_encode(array_map(fn($d)=>(int)$d['cnt'],$status_data)); ?>;
    const colors = labels.map(l=>statusColors[l]||'#9ca3af');
    new ApexCharts(document.getElementById('ch-status'), {
        chart:{...PF_OPT, type:'donut', height:280},
        series:vals, labels:labels, colors:colors,
        plotOptions:{pie:{donut:{size:'60%'}}},
        legend:{position:'bottom', fontSize:'11px', itemMargin:{vertical:3}},
        dataLabels:{enabled:true, formatter:v=>v.toFixed(1)+'%', style:{fontSize:'10px'}},
        tooltip:{y:{formatter:v=>v+' orders'}}
    }).render();
})();
<?php endif; ?>

<?php endif; /* !branch_empty */ ?>
</script>
</body>
</html>
