<?php
/**
 * Shared analytics queries: merge product orders + customization (job_orders)
 * for reports dashboard consistency.
 */
if (defined('REPORTS_DASHBOARD_QUERIES_LOADED')) {
    return;
}
define('REPORTS_DASHBOARD_QUERIES_LOADED', true);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/branch_context.php';

/** True if branch filter has any orders or job_orders (lifetime). */
function pf_reports_branch_has_activity($branchId): bool {
    try {
        [$b, $bt, $bp] = branch_where_parts('o', $branchId);
        $c1 = (int) (db_query("SELECT COUNT(*) as c FROM orders o WHERE 1=1$b", $bt ?: null, $bp ?: null)[0]['c'] ?? 0);
        [$bj, $btj, $bpj] = branch_where_parts('jo', $branchId);
        $c2 = (int) (db_query("SELECT COUNT(*) as c FROM job_orders jo WHERE 1=1$bj", $btj ?: null, $bpj ?: null)[0]['c'] ?? 0);
        return ($c1 + $c2) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Calendar years (≤ current year) that have at least one paid order line or job order.
 *
 * @return list<int> newest first
 */
function pf_reports_heatmap_available_years($branchId): array {
    $years = [];
    try {
        [$b, $bt, $bp] = branch_where_parts('o', $branchId);
        $rows = db_query(
            "SELECT DISTINCT YEAR(o.order_date) AS y
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.order_id
             WHERE o.payment_status = 'Paid'
               AND YEAR(o.order_date) <= YEAR(CURDATE())$b",
            $bt ?: null,
            $bp ?: null
        ) ?: [];
        foreach ($rows as $r) {
            $years[(int) $r['y']] = true;
        }
    } catch (Throwable $e) {
    }
    try {
        [$bj, $btj, $bpj] = branch_where_parts('jo', $branchId);
        $jrows = db_query(
            "SELECT DISTINCT YEAR(jo.created_at) AS y
             FROM job_orders jo
             WHERE YEAR(jo.created_at) <= YEAR(CURDATE())$bj",
            $btj ?: null,
            $bpj ?: null
        ) ?: [];
        foreach ($jrows as $r) {
            $years[(int) $r['y']] = true;
        }
    } catch (Throwable $e) {
    }
    $out = array_keys($years);
    rsort($out, SORT_NUMERIC);
    return $out;
}

/**
 * Raw monthly qty sums for heatmap year (paid product lines + all job_orders, same as top-services job side).
 * Excludes months after the current month when viewing the current calendar year (DB CURDATE()).
 *
 * @return array<string, array<int,int>> service label => month 1..12 => qty
 */
function pf_reports_heatmap_sums_for_year(int $heatmap_year, $branchId): array {
    $heatmap_products = [];
    try {
        [$b, $bt, $bp] = branch_where_parts('o', $branchId);
        $hmTypes = 'i' . $bt;
        $hmParams = array_merge([$heatmap_year], $bp);
        $hmRaw = db_query(
            "SELECT p.name AS product, MONTH(o.order_date) AS mo, SUM(oi.quantity) AS qty
             FROM order_items oi
             JOIN products p ON oi.product_id = p.product_id
             JOIN orders o ON oi.order_id = o.order_id
             WHERE YEAR(o.order_date) = ?
               AND o.payment_status = 'Paid'
               AND (
                 YEAR(o.order_date) < YEAR(CURDATE())
                 OR MONTH(o.order_date) <= MONTH(CURDATE())
               )$b
             GROUP BY p.product_id, p.name, MONTH(o.order_date)
             ORDER BY p.name, mo",
            $hmTypes,
            $hmParams
        ) ?: [];
        foreach ($hmRaw as $r) {
            $p = (string) $r['product'];
            if (!isset($heatmap_products[$p])) {
                $heatmap_products[$p] = array_fill(1, 12, 0);
            }
            $heatmap_products[$p][(int) $r['mo']] += (int) $r['qty'];
        }
    } catch (Throwable $e) {
    }

    try {
        [$bj, $btj, $bpj] = branch_where_parts('jo', $branchId);
        $jTypes = 'i' . $btj;
        $jParams = array_merge([$heatmap_year], $bpj);
        $jobRaw = db_query(
            "SELECT COALESCE(NULLIF(TRIM(jo.service_type), ''), 'Customization') AS product,
                    MONTH(jo.created_at) AS mo,
                    SUM(COALESCE(jo.quantity, 1)) AS qty
             FROM job_orders jo
             WHERE YEAR(jo.created_at) = ?
               AND (
                 YEAR(jo.created_at) < YEAR(CURDATE())
                 OR MONTH(jo.created_at) <= MONTH(CURDATE())
               )$bj
             GROUP BY COALESCE(NULLIF(TRIM(jo.service_type), ''), 'Customization'), MONTH(jo.created_at)
             ORDER BY product, mo",
            $jTypes,
            $jParams
        ) ?: [];
        foreach ($jobRaw as $r) {
            $p = (string) $r['product'];
            if (!isset($heatmap_products[$p])) {
                $heatmap_products[$p] = array_fill(1, 12, 0);
            }
            $heatmap_products[$p][(int) $r['mo']] += (int) $r['qty'];
        }
    } catch (Throwable $e) {
    }

    return $heatmap_products;
}

/**
 * Add per-cell kind: value | empty | future (future = month not elapsed for selected year).
 *
 * @param array<string, array<int,int>> $sumsByProduct
 * @return array<string, array<int, array{qty:int, kind:string}>>
 */
function pf_reports_heatmap_build_cells(array $sumsByProduct, int $heatmap_year): array {
    [$yNow, $mNow] = pf_reports_heatmap_db_ym();
    $out = [];
    foreach ($sumsByProduct as $prod => $mo) {
        $out[$prod] = [];
        for ($m = 1; $m <= 12; $m++) {
            $qty = (int) ($mo[$m] ?? 0);
            if ($heatmap_year > $yNow) {
                $kind = 'future';
                $qty = 0;
            } elseif ($heatmap_year < $yNow) {
                $kind = $qty > 0 ? 'value' : 'empty';
            } elseif ($m > $mNow) {
                $kind = 'future';
                $qty = 0;
            } else {
                $kind = $qty > 0 ? 'value' : 'empty';
            }
            $out[$prod][$m] = ['qty' => $qty, 'kind' => $kind];
        }
    }
    return $out;
}

/**
 * Top 8 services by total units in year (same sums as heatmap; months excluded by SQL for current year).
 *
 * @return array<string, array<int, array{qty:int, kind:string}>>
 */
function pf_reports_heatmap_matrix(int $heatmap_year, $branchId): array {
    $sums = pf_reports_heatmap_sums_for_year($heatmap_year, $branchId);
    if ($sums === []) {
        return [];
    }
    uasort($sums, static function ($a, $b) {
        return array_sum($b) <=> array_sum($a);
    });
    $sums = array_slice($sums, 0, 8, true);
    return pf_reports_heatmap_build_cells($sums, $heatmap_year);
}

/**
 * Top lines: paid product items + paid customization jobs (merged by name).
 *
 * @return list<array{product_id:?int,product_name:string,qty_sold:int,revenue:float}>
 */
function pf_reports_top_products_merged(string $from, string $toEnd, $branchId, int $limit = 10): array {
    $agg = [];

    try {
        [$b, $bt, $bp] = branch_where_parts('o', $branchId);
        $rows = db_query(
            "SELECT p.product_id, p.name AS product_name,
                    SUM(oi.quantity) as qty_sold,
                    SUM(oi.quantity * oi.unit_price) as revenue
             FROM order_items oi
             JOIN products p ON oi.product_id = p.product_id
             JOIN orders o ON oi.order_id = o.order_id
             WHERE o.order_date BETWEEN ? AND ? AND o.payment_status = 'Paid'$b
             GROUP BY p.product_id, p.name",
            'ss' . $bt,
            array_merge([$from, $toEnd], $bp)
        ) ?: [];
        foreach ($rows as $r) {
            $pid = (int) $r['product_id'];
            $k = 'p:' . $pid;
            $agg[$k] = [
                'product_id' => $pid,
                'product_name' => (string) $r['product_name'],
                'qty_sold' => (int) $r['qty_sold'],
                'revenue' => (float) $r['revenue'],
            ];
        }
    } catch (Throwable $e) {
    }

    try {
        [$bj, $btj, $bpj] = branch_where_parts('jo', $branchId);
        $jrows = db_query(
            "SELECT COALESCE(NULLIF(TRIM(jo.service_type), ''), 'Customization') AS svc,
                    SUM(COALESCE(jo.quantity, 1)) as qty_sold,
                    SUM(CASE WHEN jo.payment_status = 'PAID'
                        THEN COALESCE(jo.amount_paid, jo.estimated_total, 0) ELSE 0 END) as revenue
             FROM job_orders jo
             WHERE jo.created_at BETWEEN ? AND ?$bj
             GROUP BY COALESCE(NULLIF(TRIM(jo.service_type), ''), 'Customization')",
            'ss' . $btj,
            array_merge([$from, $toEnd], $bpj)
        ) ?: [];
        foreach ($jrows as $r) {
            $name = (string) $r['svc'];
            $k = 's:' . mb_strtolower($name);
            $qty = (int) $r['qty_sold'];
            $rev = (float) $r['revenue'];
            if (!isset($agg[$k])) {
                $agg[$k] = [
                    'product_id' => null,
                    'product_name' => $name,
                    'qty_sold' => $qty,
                    'revenue' => $rev,
                ];
            } else {
                $agg[$k]['qty_sold'] += $qty;
                $agg[$k]['revenue'] += $rev;
            }
        }
    } catch (Throwable $e) {
    }

    $list = array_values($agg);
    usort($list, static fn ($a, $b) => $b['qty_sold'] <=> $a['qty_sold']);
    return array_slice($list, 0, $limit);
}

/**
 * @return list<array{
 *   branch_name:string,
 *   orders:int,
 *   revenue:float,
 *   orders_store:int,
 *   revenue_store:float,
 *   orders_jobs:int,
 *   revenue_jobs:float
 * }>
 */
function pf_reports_branch_performance_merged(string $from, string $toEnd): array {
    $map = [];
    $names = [];

    // 1. Initialize map with ALL registered branches
    try {
        $allBranches = db_query('SELECT id, branch_name FROM branches ORDER BY id') ?: [];
        foreach ($allBranches as $b) {
            $bid = (int) $b['id'];
            $rawName = (string) $b['branch_name'];
            $names[$bid] = $rawName;
            $map[$bid] = [
                'orders_store' => 0,
                'revenue_store' => 0.0,
                'orders_jobs' => 0,
                'revenue_jobs' => 0.0,
            ];
        }
    } catch (Throwable $e) {
    }

    // 2. Merge Store Orders data
    try {
        $oRows = db_query(
            "SELECT o.branch_id,
                    COUNT(*) AS ord,
                    SUM(CASE WHEN o.payment_status = 'Paid' THEN o.total_amount ELSE 0 END) AS rev
             FROM orders o
             WHERE o.order_date BETWEEN ? AND ? AND o.branch_id IS NOT NULL
             GROUP BY o.branch_id",
            'ss',
            [$from, $toEnd]
        ) ?: [];
        foreach ($oRows as $r) {
            $id = (int) $r['branch_id'];
            if (isset($map[$id])) {
                $map[$id]['orders_store'] = (int) $r['ord'];
                $map[$id]['revenue_store'] = (float) $r['rev'];
            }
        }
    } catch (Throwable $e) {
    }

    // 3. Merge Customization Jobs data
    try {
        $jRows = db_query(
            "SELECT jo.branch_id,
                    COUNT(*) AS ord,
                    SUM(CASE WHEN jo.payment_status = 'PAID'
                        THEN COALESCE(jo.amount_paid, jo.estimated_total, 0) ELSE 0 END) AS rev
             FROM job_orders jo
             WHERE jo.created_at BETWEEN ? AND ? AND jo.branch_id IS NOT NULL
             GROUP BY jo.branch_id",
            'ss',
            [$from, $toEnd]
        ) ?: [];
        foreach ($jRows as $r) {
            $id = (int) $r['branch_id'];
            if (isset($map[$id])) {
                $map[$id]['orders_jobs'] += (int) $r['ord'];
                $map[$id]['revenue_jobs'] += (float) $r['rev'];
            }
        }
    } catch (Throwable $e) {
    }

    // 4. Final aggregation and formatting
    $out = [];
    foreach ($map as $bid => $v) {
        $ord = (int) $v['orders_store'] + (int) $v['orders_jobs'];
        $rev = (float) $v['revenue_store'] + (float) $v['revenue_jobs'];

        $rawName = $names[$bid] ?? ('Branch #' . $bid);
        $pretty = function_exists('mb_convert_case')
            ? mb_convert_case(trim($rawName), MB_CASE_TITLE, 'UTF-8')
            : ucwords(strtolower(trim($rawName)));

        $out[] = [
            'branch_name' => $pretty,
            'orders' => $ord,
            'revenue' => $rev,
            'orders_store' => (int) $v['orders_store'],
            'revenue_store' => (float) $v['revenue_store'],
            'orders_jobs' => (int) $v['orders_jobs'],
            'revenue_jobs' => (float) $v['revenue_jobs'],
        ];
    }

    // Sort by revenue descending (branches with 0 revenue still included at bottom)
    usort($out, static fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
    return $out;
}

/** Server calendar year/month from DB (matches YEAR/MONTH(CURDATE()) in heatmap SQL). */
function pf_reports_heatmap_db_ym(): array {
    try {
        $r = db_query('SELECT YEAR(CURDATE()) AS y, MONTH(CURDATE()) AS m');
        if (!empty($r[0])) {
            return [(int) $r[0]['y'], (int) $r[0]['m']];
        }
    } catch (Throwable $e) {
    }
    return [(int) date('Y'), (int) date('n')];
}

/** @return list<string> Short month labels (Jan … Dec) for heatmap headers */
function pf_reports_heatmap_month_short_labels(): array {
    $out = [];
    for ($m = 1; $m <= 12; $m++) {
        $out[] = date('M', mktime(0, 0, 0, $m, 1));
    }
    return $out;
}

/** CSS tier for cells with kind=value: low|med|high based on max value in chart */
function pf_reports_heatmap_value_tier(int $v, int $max_v): string {
    if ($v <= 0 || $max_v <= 0) {
        return 'low';
    }
    $pct = ($v / $max_v) * 100;
    if ($pct <= 25) {
        return 'low';
    }
    if ($pct <= 65) {
        return 'med';
    }
    return 'high';
}

/**
 * HTML/CSS grid heatmap: fixed label column + 12 responsive month columns (no ApexCharts).
 *
 * @param array<string, array<int, array{qty:int, kind:string}>> $cellsByService
 */
function pf_reports_render_heatmap_html(array $cellsByService, int $displayYear): string {
    if ($cellsByService === []) {
        return '';
    }
    $months = pf_reports_heatmap_month_short_labels();
    [$yNow, $mNow] = pf_reports_heatmap_db_ym();
    $h = '<div class="pf-hm-root" id="pf-hm-root">';
    $h .= '<div class="pf-hm-grid" role="grid" aria-label="Seasonal demand by service and month">';
    $h .= '<div class="pf-hm-corner" aria-hidden="true"></div>';
    $h .= '<div class="pf-hm-months" role="row">';
    foreach ($months as $idx => $ml) {
        $mi = $idx + 1;
        $mh = 'pf-hm-month';
        if ($displayYear === $yNow && $mi > $mNow) {
            $mh .= ' pf-hm-month--future';
        }
        $h .= '<div class="' . $mh . '" role="columnheader">' . htmlspecialchars($ml, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    $h .= '</div>';

    // Global max for dynamic thresholds
    $max_v = 0;
    foreach ($cellsByService as $prod => $mo) {
        for ($m = 1; $m <= 12; $m++) {
            $max_v = max($max_v, (int)($mo[$m]['qty'] ?? 0));
        }
    }

    foreach ($cellsByService as $prod => $mo) {
        $prodE = htmlspecialchars((string) $prod, ENT_QUOTES, 'UTF-8');
        $h .= '<div class="pf-hm-label-col"><span class="pf-hm-label-text" title="' . $prodE . '">' . $prodE . '</span></div>';
        $h .= '<div class="pf-hm-tiles" role="row">';
        for ($m = 1; $m <= 12; $m++) {
            $cell = $mo[$m] ?? ['qty' => 0, 'kind' => 'empty'];
            $qty = (int) ($cell['qty'] ?? 0);
            $kind = (string) ($cell['kind'] ?? 'empty');
            $ml = $months[$m - 1];
            if ($kind === 'future') {
                $tip = htmlspecialchars("{$prod} · {$ml} — No data yet", ENT_QUOTES, 'UTF-8');
                $h .= '<div class="pf-hm-cell pf-hm-cell--future" role="gridcell" aria-disabled="true" title="' . $tip . '">';
                $h .= '<span class="pf-hm-val"></span></div>';
            } elseif ($kind === 'empty') {
                $tip = htmlspecialchars("{$prod} · {$ml} — No transactions", ENT_QUOTES, 'UTF-8');
                $h .= '<div class="pf-hm-cell pf-hm-cell--nodata" role="gridcell" tabindex="0" title="' . $tip . '">';
                $h .= '<span class="pf-hm-val"></span></div>';
            } else {
                $tier = pf_reports_heatmap_value_tier($qty, $max_v);
                $tip = htmlspecialchars("{$prod} · {$ml} · {$qty} units", ENT_QUOTES, 'UTF-8');
                $h .= '<div class="pf-hm-cell pf-hm-cell--' . $tier . '" role="gridcell" tabindex="0" title="' . $tip . '">';
                $h .= '<span class="pf-hm-val">' . htmlspecialchars((string) $qty, ENT_QUOTES, 'UTF-8') . '</span></div>';
            }
        }
        $h .= '</div>';
    }
    $h .= '</div></div>';
    return $h;
}
