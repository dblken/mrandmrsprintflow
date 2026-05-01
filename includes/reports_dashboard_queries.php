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

/** Simple trend-based 3-month forecast from a historical array. */
function pf_forecast3(array $hist): array {
    $n = count($hist);
    if ($n < 3) return array_fill(0, 3, 0);
    $last3 = array_slice($hist, -3);
    $avg   = array_sum($last3) / 3.0;
    $slope = ($last3[2] - $last3[0]) / 2.0;
    $fore  = [];
    for ($i = 1; $i <= 3; $i++) {
        $fore[] = max(0, (int) round($avg + $slope * $i));
    }
    return $fore;
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

function pf_reports_lookup_key(string $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    $value = preg_replace('/\s+/u', ' ', $value);
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
}

function pf_reports_clean_category_label(?string $value, string $fallback = 'Uncategorized'): string {
    $label = trim(preg_replace('/\s+/u', ' ', (string) $value));
    return $label !== '' ? $label : $fallback;
}

/**
 * @return list<string>
 */
function pf_reports_service_lookup_aliases(string $name): array {
    $aliases = [];
    $raw = trim((string) $name);
    if ($raw === '') {
        return [];
    }

    $aliases[] = $raw;

    if (function_exists('normalize_service_name')) {
        $aliases[] = normalize_service_name($raw, $raw);
    }
    if (function_exists('printflow_precise_service_name_aliases')) {
        $aliases = array_merge($aliases, printflow_precise_service_name_aliases($raw));
    }
    if (function_exists('printflow_service_name_aliases')) {
        $aliases = array_merge($aliases, printflow_service_name_aliases($raw));
    }

    $out = [];
    $seen = [];
    foreach ($aliases as $alias) {
        $alias = trim((string) $alias);
        if ($alias === '') {
            continue;
        }
        $key = pf_reports_lookup_key($alias);
        if ($key === '' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $alias;
    }
    return $out;
}

/**
 * @return array{lookup: array<string, string>, categories: array<string, string>}
 */
function pf_reports_service_category_registry(): array {
    static $registry = null;
    if ($registry !== null) {
        return $registry;
    }

    if (!function_exists('printflow_default_customer_service_catalog')) {
        $catalogPath = __DIR__ . '/customer_service_catalog.php';
        if (file_exists($catalogPath)) {
            require_once $catalogPath;
        }
    }

    $lookup = [];
    $categories = [];

    $register = static function (string $serviceName, string $category) use (&$lookup, &$categories): void {
        $category = pf_reports_clean_category_label($category, 'Uncategorized');
        $catKey = pf_reports_lookup_key($category);
        if ($catKey === '') {
            return;
        }
        $categories[$catKey] = $category;
        foreach (pf_reports_service_lookup_aliases($serviceName) as $alias) {
            $aliasKey = pf_reports_lookup_key($alias);
            if ($aliasKey === '') {
                continue;
            }
            $lookup[$aliasKey] = $category;
        }
    };

    if (function_exists('printflow_default_customer_service_catalog')) {
        foreach (printflow_default_customer_service_catalog() as $row) {
            $serviceName = trim((string) ($row['name'] ?? ''));
            $category = trim((string) ($row['category'] ?? ''));
            if ($serviceName === '' || $category === '') {
                continue;
            }
            $register($serviceName, $category);
        }
    }

    try {
        $rows = db_query(
            "SELECT name, category
             FROM services
             WHERE name IS NOT NULL AND TRIM(name) != ''
               AND category IS NOT NULL AND TRIM(category) != ''"
        ) ?: [];
        foreach ($rows as $row) {
            $serviceName = trim((string) ($row['name'] ?? ''));
            $category = trim((string) ($row['category'] ?? ''));
            if ($serviceName === '' || $category === '') {
                continue;
            }
            $register($serviceName, $category);
        }
    } catch (Throwable $e) {
    }

    $registry = ['lookup' => $lookup, 'categories' => $categories];
    return $registry;
}

/**
 * @param array<string, string> $lookup
 * @param array<string, string> $categories
 * @return array{category: string, mapped: bool}
 */
function pf_reports_resolve_service_category(string $serviceName, array $lookup, array $categories): array {
    foreach (pf_reports_service_lookup_aliases($serviceName) as $alias) {
        $key = pf_reports_lookup_key($alias);
        if ($key !== '' && isset($lookup[$key])) {
            return ['category' => $lookup[$key], 'mapped' => true];
        }
        if ($key !== '' && isset($categories[$key])) {
            return ['category' => $categories[$key], 'mapped' => true];
        }
    }
    return ['category' => 'Unmapped Services', 'mapped' => false];
}

/**
 * @param array<string, string> $lookup
 * @param array<string, string> $categories
 * @return array{category: string, mapped: bool}
 */
function pf_reports_resolve_product_category(string $productName, string $rawCategory, array $lookup, array $categories): array {
    $rawCategory = trim((string) $rawCategory);
    if ($rawCategory !== '') {
        $rawKey = pf_reports_lookup_key($rawCategory);
        if ($rawKey !== '' && isset($categories[$rawKey])) {
            return ['category' => $categories[$rawKey], 'mapped' => true];
        }
        $fromCategory = pf_reports_resolve_service_category($rawCategory, $lookup, $categories);
        if ($fromCategory['mapped']) {
            return $fromCategory;
        }
    }

    $combined = trim($rawCategory . ' ' . $productName);
    $fromProductName = pf_reports_resolve_service_category($combined !== '' ? $combined : $productName, $lookup, $categories);
    if ($fromProductName['mapped']) {
        return $fromProductName;
    }

    if ($rawCategory !== '') {
        return ['category' => pf_reports_clean_category_label($rawCategory), 'mapped' => false];
    }

    return ['category' => 'Uncategorized Products', 'mapped' => false];
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
        $datePart = "";
        $dParams = [];
        $dTypes = "";
        if ($from !== '' && $toEnd !== '') {
            $datePart = " AND o.order_date BETWEEN ? AND ?";
            $dParams = [$from, $toEnd];
            $dTypes = "ss";
        } elseif ($from !== '') {
            $datePart = " AND o.order_date >= ?";
            $dParams = [$from];
            $dTypes = "s";
        } elseif ($toEnd !== '') {
            $datePart = " AND o.order_date <= ?";
            $dParams = [$toEnd];
            $dTypes = "s";
        }

        $rows = db_query(
            "SELECT p.product_id, p.name AS product_name,
                    SUM(oi.quantity) as qty_sold,
                    SUM(oi.quantity * oi.unit_price) as revenue
             FROM order_items oi
             JOIN products p ON oi.product_id = p.product_id
             JOIN orders o ON oi.order_id = o.order_id
             WHERE o.payment_status = 'Paid' {$datePart} {$b}
             GROUP BY p.product_id, p.name",
            $dTypes . $bt,
            array_merge($dParams, $bp)
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
        $jDatePart = "";
        $jdParams = [];
        $jdTypes = "";
        if ($from !== '' && $toEnd !== '') {
            $jDatePart = " AND jo.created_at BETWEEN ? AND ?";
            $jdParams = [$from, $toEnd];
            $jdTypes = "ss";
        } elseif ($from !== '') {
            $jDatePart = " AND jo.created_at >= ?";
            $jdParams = [$from];
            $jdTypes = "s";
        } elseif ($toEnd !== '') {
            $jDatePart = " AND jo.created_at <= ?";
            $jdParams = [$toEnd];
            $jdTypes = "s";
        }

        $jrows = db_query(
            "SELECT COALESCE(NULLIF(TRIM(jo.service_type), ''), 'Customization') AS svc,
                    SUM(COALESCE(jo.quantity, 1)) as qty_sold,
                    SUM(CASE WHEN jo.payment_status = 'PAID'
                        THEN COALESCE(jo.amount_paid, jo.estimated_total, 0) ELSE 0 END) as revenue
             FROM job_orders jo
             WHERE 1=1 {$jDatePart} {$bj}
             GROUP BY COALESCE(NULLIF(TRIM(jo.service_type), ''), 'Customization')",
            $jdTypes . $btj,
            array_merge($jdParams, $bpj)
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
 * Category dashboard:
 * - store products use products.category, normalized through the same service-name aliases
 * - job orders resolve through configured service categories
 *
 * @return array{
 *   rows: list<array{
 *     category:string,
 *     qty:int,
 *     revenue:float,
 *     store_qty:int,
 *     store_revenue:float,
 *     job_qty:int,
 *     job_revenue:float,
 *     share_pct:float
 *   }>,
 *   meta: array{
 *     total_revenue:float,
 *     total_units:int,
 *     total_categories:int,
 *     store_revenue:float,
 *     store_units:int,
 *     job_revenue:float,
 *     job_units:int,
 *     mapped_job_rows:int,
 *     unmapped_job_rows:int,
 *     mapped_job_units:int,
 *     unmapped_job_units:int
 *   }
 * }
 */
function pf_reports_category_breakdown_merged(string $from, string $toEnd, $branchId): array {
    $registry = pf_reports_service_category_registry();
    $lookup = $registry['lookup'];
    $categories = $registry['categories'];
    $agg = [];
    $meta = [
        'total_revenue' => 0.0,
        'total_units' => 0,
        'total_categories' => 0,
        'store_revenue' => 0.0,
        'store_units' => 0,
        'job_revenue' => 0.0,
        'job_units' => 0,
        'mapped_job_rows' => 0,
        'unmapped_job_rows' => 0,
        'mapped_job_units' => 0,
        'unmapped_job_units' => 0,
    ];

    $accumulate = static function (string $category, string $source, int $qty, float $revenue) use (&$agg): void {
        $category = pf_reports_clean_category_label($category);
        $key = pf_reports_lookup_key($category);
        if ($key === '') {
            $key = pf_reports_lookup_key('Uncategorized');
            $category = 'Uncategorized';
        }
        if (!isset($agg[$key])) {
            $agg[$key] = [
                'category' => $category,
                'qty' => 0,
                'revenue' => 0.0,
                'store_qty' => 0,
                'store_revenue' => 0.0,
                'job_qty' => 0,
                'job_revenue' => 0.0,
                'share_pct' => 0.0,
            ];
        }
        $agg[$key]['qty'] += $qty;
        $agg[$key]['revenue'] += $revenue;
        if ($source === 'store') {
            $agg[$key]['store_qty'] += $qty;
            $agg[$key]['store_revenue'] += $revenue;
        } else {
            $agg[$key]['job_qty'] += $qty;
            $agg[$key]['job_revenue'] += $revenue;
        }
    };

    try {
        [$b, $bt, $bp] = branch_where_parts('o', $branchId);
        $datePart = '';
        $params = [];
        $types = '';
        if ($from !== '' && $toEnd !== '') {
            $datePart = ' AND o.order_date BETWEEN ? AND ?';
            $params = [$from, $toEnd];
            $types = 'ss';
        } elseif ($from !== '') {
            $datePart = ' AND o.order_date >= ?';
            $params = [$from];
            $types = 's';
        } elseif ($toEnd !== '') {
            $datePart = ' AND o.order_date <= ?';
            $params = [$toEnd];
            $types = 's';
        }

        $rows = db_query(
            "SELECT p.product_id,
                    p.name AS product_name,
                    COALESCE(NULLIF(TRIM(p.category), ''), '') AS raw_category,
                    SUM(oi.quantity) AS qty_sold,
                    SUM(oi.quantity * oi.unit_price) AS revenue
             FROM order_items oi
             JOIN products p ON oi.product_id = p.product_id
             JOIN orders o ON oi.order_id = o.order_id
             WHERE o.payment_status = 'Paid' {$datePart} {$b}
             GROUP BY p.product_id, p.name, p.category",
            $types . $bt,
            array_merge($params, $bp)
        ) ?: [];

        foreach ($rows as $row) {
            $qty = (int) ($row['qty_sold'] ?? 0);
            $revenue = (float) ($row['revenue'] ?? 0);
            if ($qty <= 0 && $revenue <= 0) {
                continue;
            }
            $resolved = pf_reports_resolve_product_category(
                (string) ($row['product_name'] ?? ''),
                (string) ($row['raw_category'] ?? ''),
                $lookup,
                $categories
            );
            $accumulate($resolved['category'], 'store', $qty, $revenue);
            $meta['store_units'] += $qty;
            $meta['store_revenue'] += $revenue;
        }
    } catch (Throwable $e) {
    }

    try {
        [$bj, $btj, $bpj] = branch_where_parts('jo', $branchId);
        $jDatePart = '';
        $jParams = [];
        $jTypes = '';
        if ($from !== '' && $toEnd !== '') {
            $jDatePart = ' AND jo.created_at BETWEEN ? AND ?';
            $jParams = [$from, $toEnd];
            $jTypes = 'ss';
        } elseif ($from !== '') {
            $jDatePart = ' AND jo.created_at >= ?';
            $jParams = [$from];
            $jTypes = 's';
        } elseif ($toEnd !== '') {
            $jDatePart = ' AND jo.created_at <= ?';
            $jParams = [$toEnd];
            $jTypes = 's';
        }

        $jrows = db_query(
            "SELECT COALESCE(NULLIF(TRIM(jo.service_type), ''), 'Customization') AS service_type,
                    COUNT(*) AS job_rows,
                    SUM(COALESCE(NULLIF(jo.quantity, 0), 1)) AS qty_sold,
                    SUM(COALESCE(NULLIF(jo.amount_paid, 0), jo.estimated_total, 0)) AS revenue
             FROM job_orders jo
             WHERE (jo.payment_status = 'PAID' OR jo.status = 'COMPLETED') {$jDatePart} {$bj}
             GROUP BY COALESCE(NULLIF(TRIM(jo.service_type), ''), 'Customization')",
            $jTypes . $btj,
            array_merge($jParams, $bpj)
        ) ?: [];

        foreach ($jrows as $row) {
            $serviceType = (string) ($row['service_type'] ?? 'Customization');
            $jobRows = (int) ($row['job_rows'] ?? 0);
            $qty = (int) ($row['qty_sold'] ?? 0);
            $revenue = (float) ($row['revenue'] ?? 0);
            if ($jobRows <= 0 && $qty <= 0 && $revenue <= 0) {
                continue;
            }

            $resolved = pf_reports_resolve_service_category($serviceType, $lookup, $categories);
            $accumulate($resolved['category'], 'job', $qty, $revenue);
            $meta['job_units'] += $qty;
            $meta['job_revenue'] += $revenue;
            if ($resolved['mapped']) {
                $meta['mapped_job_rows'] += $jobRows;
                $meta['mapped_job_units'] += $qty;
            } else {
                $meta['unmapped_job_rows'] += $jobRows;
                $meta['unmapped_job_units'] += $qty;
            }
        }
    } catch (Throwable $e) {
    }

    $rows = array_values($agg);
    $meta['total_revenue'] = round($meta['store_revenue'] + $meta['job_revenue'], 2);
    $meta['total_units'] = (int) ($meta['store_units'] + $meta['job_units']);

    usort($rows, static function (array $a, array $b): int {
        $byRevenue = ($b['revenue'] <=> $a['revenue']);
        if ($byRevenue !== 0) {
            return $byRevenue;
        }
        $byQty = ($b['qty'] <=> $a['qty']);
        if ($byQty !== 0) {
            return $byQty;
        }
        return strcmp((string) $a['category'], (string) $b['category']);
    });

    foreach ($rows as &$row) {
        $row['revenue'] = round((float) $row['revenue'], 2);
        $row['store_revenue'] = round((float) $row['store_revenue'], 2);
        $row['job_revenue'] = round((float) $row['job_revenue'], 2);
        $row['share_pct'] = $meta['total_revenue'] > 0
            ? round(((float) $row['revenue'] / $meta['total_revenue']) * 100, 1)
            : 0.0;
    }
    unset($row);

    $meta['total_categories'] = count($rows);

    return ['rows' => $rows, 'meta' => $meta];
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
function pf_reports_branch_performance_merged(string $from, string $toEnd, $branchId = 'all'): array {
    $map = [];
    $names = [];
    $branchIdInt = ($branchId === 'all' || $branchId === null) ? 0 : (int)$branchId;
    $branchWhere = '';
    $branchTypes = '';
    $branchParams = [];
    if ($branchIdInt > 0) {
        $branchWhere = ' AND id = ?';
        $branchTypes = 'i';
        $branchParams = [$branchIdInt];
    }

    // 1. Initialize map with ALL registered branches
    try {
        $allBranches = db_query(
            'SELECT id, branch_name FROM branches WHERE 1=1' . $branchWhere . ' ORDER BY id',
            $branchTypes ?: null,
            $branchParams ?: null
        ) ?: [];
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
        $oDatePart = "";
        $odParams = [];
        $odTypes = "";
        if ($from !== '' && $toEnd !== '') {
            $oDatePart = " AND o.order_date BETWEEN ? AND ?";
            $odParams = [$from, $toEnd];
            $odTypes = "ss";
        } elseif ($from !== '') {
            $oDatePart = " AND o.order_date >= ?";
            $odParams = [$from];
            $odTypes = "s";
        } elseif ($toEnd !== '') {
            $oDatePart = " AND o.order_date <= ?";
            $odParams = [$toEnd];
            $odTypes = "s";
        }
        if ($branchIdInt > 0) {
            $oDatePart .= " AND o.branch_id = ?";
            $odParams[] = $branchIdInt;
            $odTypes .= "i";
        }

        $oRows = db_query(
            "SELECT o.branch_id,
                    COUNT(*) AS ord,
                    SUM(CASE WHEN o.payment_status = 'Paid' THEN o.total_amount ELSE 0 END) AS rev
             FROM orders o
             WHERE o.branch_id IS NOT NULL {$oDatePart}
             GROUP BY o.branch_id",
            $odTypes,
            $odParams
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
        $jDatePart = "";
        $jdParams = [];
        $jdTypes = "";
        if ($from !== '' && $toEnd !== '') {
            $jDatePart = " AND jo.created_at BETWEEN ? AND ?";
            $jdParams = [$from, $toEnd];
            $jdTypes = "ss";
        } elseif ($from !== '') {
            $jDatePart = " AND jo.created_at >= ?";
            $jdParams = [$from];
            $jdTypes = "s";
        } elseif ($toEnd !== '') {
            $jDatePart = " AND jo.created_at <= ?";
            $jdParams = [$toEnd];
            $jdTypes = "s";
        }
        if ($branchIdInt > 0) {
            $jDatePart .= " AND jo.branch_id = ?";
            $jdParams[] = $branchIdInt;
            $jdTypes .= "i";
        }

        $jRows = db_query(
            "SELECT jo.branch_id,
                    COUNT(*) AS ord,
                    SUM(CASE WHEN jo.payment_status = 'PAID'
                        THEN COALESCE(jo.amount_paid, jo.estimated_total, 0) ELSE 0 END) AS rev
             FROM job_orders jo
             WHERE jo.branch_id IS NOT NULL {$jDatePart}
             GROUP BY jo.branch_id",
            $jdTypes,
            $jdParams
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
/**
 * Daily or monthly sales series for a specific period (from -> toEnd).
 * Merges Store Orders + Customization Jobs.
 *
 * @param string $from  'YYYY-MM-DD'
 * @param string $toEnd 'YYYY-MM-DD 23:59:59'
 * @return array{labels:string[], revStore:float[], revCustom:float[], orders:int[]}
 */
function pf_reports_period_sales_merged(string $from, string $toEnd, $branchId): array {
    $labels = []; $revStore = []; $revCustom = []; $orders = [];
    
    // Validate inputs
    if (empty($from) && empty($toEnd)) {
        // If no date range specified, use last 30 days as fallback
        $from = date('Y-m-d', strtotime('-30 days'));
        $toEnd = date('Y-m-d') . ' 23:59:59';
    }
    
    // 1. Determine grouping (Daily if < 90 days, else Monthly)
    $tsFrom = strtotime($from ?: '2020-01-01');
    $tsTo   = strtotime($toEnd ?: date('Y-m-d H:i:s'));
    
    if ($tsFrom === false || $tsTo === false) {
        error_log('[PrintFlow] Invalid date format in pf_reports_period_sales_merged: from=' . $from . ', to=' . $toEnd);
        return ['labels' => [], 'revStore' => [], 'revCustom' => [], 'orders' => []];
    }
    
    $days   = ($tsTo - $tsFrom) / 86400;
    $groupBy = ($days > 90) ? 'MONTH' : 'DAY';
    
    error_log('[PrintFlow] Sales chart query params: from=' . $from . ', to=' . $toEnd . ', branchId=' . $branchId . ', groupBy=' . $groupBy . ', days=' . $days);

    // 2. Fetch Store Orders
    $mapStore = [];
    try {
        [$b, $bt, $bp] = branch_where_parts('o', $branchId);
        $datePart = ""; $dPs = []; $dTs = "";
        if ($from !== '' && $toEnd !== '') {
            $datePart = " AND o.order_date BETWEEN ? AND ?";
            $dPs = [$from, $toEnd]; $dTs = "ss";
        } elseif ($from !== '') {
            $datePart = " AND o.order_date >= ?";
            $dPs = [$from]; $dTs = "s";
        } elseif ($toEnd !== '') {
            $datePart = " AND o.order_date <= ?";
            $dPs = [$toEnd]; $dTs = "s";
        }

        $fmt = ($groupBy === 'MONTH') ? '%Y-%m' : '%Y-%m-%d';
        $sql = "SELECT DATE_FORMAT(o.order_date,'{$fmt}') as d,
                       COUNT(*) as cnt,
                       SUM(CASE WHEN o.payment_status='Paid' THEN o.total_amount ELSE 0 END) as rev
                FROM orders o WHERE 1=1 {$datePart} {$b}
                GROUP BY d ORDER BY d";
        
        error_log('[PrintFlow] Store orders SQL: ' . $sql . ' | Types: ' . ($dTs . $bt) . ' | Params: ' . json_encode(array_merge($dPs, $bp)));
        
        $rows = db_query($sql, $dTs . $bt, array_merge($dPs, $bp)) ?: [];
        error_log('[PrintFlow] Store orders result count: ' . count($rows));
        
        foreach ($rows as $r) {
            $mapStore[$r['d']] = ['cnt' => (int)$r['cnt'], 'rev' => (float)$r['rev']];
        }
    } catch (Throwable $e) {
        error_log('[PrintFlow] Error fetching store orders: ' . $e->getMessage());
    }

    // 3. Fetch Customization Jobs
    $mapJobs = [];
    try {
        [$bj, $btj, $bpj] = branch_where_parts('jo', $branchId);
        $jDatePart = ""; $jdPs = []; $jdTs = "";
        if ($from !== '' && $toEnd !== '') {
            $jDatePart = " AND jo.created_at BETWEEN ? AND ?";
            $jdPs = [$from, $toEnd]; $jdTs = "ss";
        } elseif ($from !== '') {
            $jDatePart = " AND jo.created_at >= ?";
            $jdPs = [$from]; $jdTs = "s";
        } elseif ($toEnd !== '') {
            $jDatePart = " AND jo.created_at <= ?";
            $jdPs = [$toEnd]; $jdTs = "s";
        }

        $fmt = ($groupBy === 'MONTH') ? '%Y-%m' : '%Y-%m-%d';
        $sql = "SELECT DATE_FORMAT(jo.created_at,'{$fmt}') as d,
                       COUNT(*) as cnt,
                       SUM(CASE WHEN jo.payment_status='PAID' THEN COALESCE(jo.amount_paid, jo.estimated_total, 0) ELSE 0 END) as rev
                FROM job_orders jo WHERE 1=1 {$jDatePart} {$bj}
                GROUP BY d ORDER BY d";
        
        $jrows = db_query($sql, $jdTs . $btj, array_merge($jdPs, $bpj)) ?: [];
        error_log('[PrintFlow] Job orders result count: ' . count($jrows));
        
        foreach ($jrows as $r) {
            $mapJobs[$r['d']] = ['cnt' => (int)$r['cnt'], 'rev' => (float)$r['rev']];
        }
    } catch (Throwable $e) {
        error_log('[PrintFlow] Error fetching job orders: ' . $e->getMessage());
    }

    // 4. Generate linear range and fill
    if ($groupBy === 'MONTH') {
        $curr = date('Y-m', $tsFrom);
        $end  = date('Y-m', $tsTo);
        while ($curr <= $end) {
            $labels[] = date('M Y', strtotime($curr . '-01'));
            $s = $mapStore[$curr] ?? ['cnt'=>0,'rev'=>0];
            $j = $mapJobs[$curr] ?? ['cnt'=>0,'rev'=>0];
            $revStore[]  = (float)$s['rev'];
            $revCustom[] = (float)$j['rev'];
            $orders[]    = (int)($s['cnt'] + $j['cnt']);
            $curr = date('Y-m', strtotime($curr . '-01 +1 month'));
        }
    } else {
        // Daily
        $curr = date('Y-m-d', $tsFrom);
        $end  = date('Y-m-d', $tsTo);
        while ($curr <= $end) {
            $labels[] = date('M d', strtotime($curr));
            $s = $mapStore[$curr] ?? ['cnt'=>0,'rev'=>0];
            $j = $mapJobs[$curr] ?? ['cnt'=>0,'rev'=>0];
            $revStore[]  = (float)$s['rev'];
            $revCustom[] = (float)$j['rev'];
            $orders[]    = (int)($s['cnt'] + $j['cnt']);
            $curr = date('Y-m-d', strtotime($curr . ' +1 day'));
        }
    }
    
    $result = [
        'labels'    => $labels,
        'revStore'  => $revStore,
        'revCustom' => $revCustom,
        'orders'    => $orders
    ];
    
    error_log('[PrintFlow] Final sales chart result: ' . json_encode([
        'labels_count' => count($labels),
        'sample_labels' => array_slice($labels, 0, 3),
        'revStore_sum' => array_sum($revStore),
        'revCustom_sum' => array_sum($revCustom),
        'orders_sum' => array_sum($orders)
    ]));

    return $result;
}
