<?php
/**
 * Admin: Seed / Reprice Transactions
 *
 * Part 1 — Update pricing on existing orders to realistic values.
 * Part 2 — Generate historical transaction records 2021–2026.
 *
 * Admin-only · CSRF-protected · transaction-wrapped · preview before execute.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin']);
set_time_limit(300);
ini_set('memory_limit', '256M');

$base_path    = pf_app_base_path();
$current_user = get_logged_in_user();

// ─────────────────────────────────────────────────────────────────────────────
// CONFIGURATION
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Price range per product/service category.
 * Each entry: [min_unit_price, max_unit_price].
 */
function pf_seed_price_range(string $category): array {
    $tiers = [
        'Tarpaulin'            => [800,  2500],
        'T-Shirt'              => [350,  800],
        'Stickers'             => [260,  600],
        'Signage'              => [800,  2500],
        'Sintraboard'          => [800,  2500],
        'Sintraboard Standees' => [900,  2800],
        'Merchandise'          => [260,  700],
        'Apparel'              => [350,  900],
        'Design Services'      => [350,  1500],
        'Souvenirs'            => [260,  700],
        'Print'                => [350,  1200],
    ];
    foreach ($tiers as $key => $range) {
        if (stripos($category, $key) !== false) return $range;
    }
    return [260, 2500]; // default
}

/**
 * Monthly order volume targets (year => [month => count]).
 * Gradual growth from 2021 → 2026.
 */
function pf_seed_schedule(): array {
    return [
        2021 => [1=>4, 2=>4, 3=>5, 4=>4, 5=>5, 6=>5, 7=>5, 8=>6, 9=>5, 10=>6, 11=>6, 12=>7],          // 62
        2022 => [1=>7, 2=>8, 3=>8, 4=>9, 5=>9, 6=>10, 7=>10, 8=>11, 9=>10, 10=>11, 11=>12, 12=>13],    // 118
        2023 => [1=>13, 2=>14, 3=>15, 4=>15, 5=>16, 6=>17, 7=>17, 8=>18, 9=>18, 10=>20, 11=>20, 12=>22], // 195
        2024 => [1=>22, 2=>23, 3=>25, 4=>24, 5=>27, 6=>28, 7=>28, 8=>30, 9=>29, 10=>32, 11=>31, 12=>34], // 333
        2025 => [1=>34, 2=>36, 3=>38, 4=>39, 5=>42, 6=>43, 7=>44, 8=>46, 9=>47, 10=>50, 11=>49, 12=>52], // 520
        2026 => [1=>53, 2=>55, 3=>57, 4=>54, 5=>28],                                                     // 247
    ];
}

/**
 * Status distribution for orders.
 * Recent orders (< 6 months ago) get a mix; older ones lean Completed.
 */
function pf_seed_order_status(int $year, int $month): array {
    $now     = new \DateTime();
    $orderDt = new \DateTime("{$year}-{$month}-01");
    $diffM   = ($now->format('Y') - $year) * 12 + ($now->format('n') - $month);

    if ($diffM <= 2) {
        // Very recent: mixed active statuses
        return [
            ['status' => 'Pending',          'weight' => 15],
            ['status' => 'Processing',        'weight' => 25],
            ['status' => 'Ready for Pickup',  'weight' => 20],
            ['status' => 'Completed',         'weight' => 30],
            ['status' => 'Cancelled',         'weight' => 10],
        ];
    }
    if ($diffM <= 6) {
        return [
            ['status' => 'Processing',        'weight' => 5],
            ['status' => 'Ready for Pickup',  'weight' => 5],
            ['status' => 'Completed',         'weight' => 78],
            ['status' => 'Cancelled',         'weight' => 12],
        ];
    }
    // Older: mostly completed
    return [
        ['status' => 'Completed',  'weight' => 85],
        ['status' => 'Cancelled',  'weight' => 15],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/** Weighted random pick from array of ['status'=>..., 'weight'=>...] */
function pf_seed_weighted_pick(array $items): string {
    $total = array_sum(array_column($items, 'weight'));
    $r     = mt_rand(1, $total);
    $cum   = 0;
    foreach ($items as $item) {
        $cum += $item['weight'];
        if ($r <= $cum) return $item['status'];
    }
    return $items[0]['status'];
}

/** Random int in range */
function pf_seed_rand(int $min, int $max): int {
    return mt_rand($min, $max);
}

/** Random float price rounded to 2 decimal places */
function pf_seed_rand_price(int $min, int $max): float {
    // Use increments of 50 for realism
    if ($max < $min) {
        return (float)max(50, $max);
    }
    $steps = (int)(($max - $min) / 50);
    return (float)($min + mt_rand(0, $steps) * 50);
}

/** Demo order amounts should stay inside this range unless a real existing order has unusual quantities. */
function pf_seed_amount_bounds(): array {
    return [260.00, 5100.00];
}

/** Exact enum values accepted by job_orders.service_type. */
function pf_seed_job_service_types(): array {
    return [
        'Tarpaulin Printing',
        'T-shirt Printing',
        'Decals/Stickers (Print/Cut)',
        'Glass Stickers / Wall / Frosted Stickers',
        'Transparent Stickers',
        'Layouts',
        'Reflectorized (Subdivision Stickers/Signages)',
        'Stickers on Sintraboard',
        'Sintraboard Standees',
        'Souvenirs',
    ];
}

/** Price tier for job-order service names. */
function pf_seed_job_service_range(string $serviceType): array {
    if (stripos($serviceType, 'Tarpaulin') !== false) return [800, 2500];
    if (stripos($serviceType, 'T-shirt') !== false) return [350, 900];
    if (stripos($serviceType, 'Sticker') !== false) return [260, 800];
    if (stripos($serviceType, 'Layout') !== false) return [350, 1500];
    if (stripos($serviceType, 'Sintraboard') !== false) return [900, 2800];
    if (stripos($serviceType, 'Souvenir') !== false) return [260, 700];
    return [260, 2500];
}

/** Build a realistic job-order pricing payload whose estimated_total stays in range. */
function pf_seed_job_pricing(string $serviceType, int $qty = 1): array {
    [$floor, $cap] = pf_seed_amount_bounds();
    [$min, $max] = pf_seed_job_service_range($serviceType);
    $qty = max(1, min($qty, 8));
    $unit = pf_seed_unit_price_for_quantity($serviceType, $qty, $cap);
    $total = max($floor, min($cap, $unit * $qty));

    $width = null;
    $height = null;
    $sqft = null;
    $pricePerSqft = null;
    $pricePerPiece = $unit;

    if (stripos($serviceType, 'Tarpaulin') !== false || stripos($serviceType, 'Sintraboard') !== false) {
        $width = mt_rand(2, 5);
        $height = mt_rand(3, 8);
        $sqft = $width * $height * $qty;
        $pricePerSqft = round($total / max(1, $sqft), 2);
        $pricePerPiece = null;
    }

    return [
        'quantity' => $qty,
        'width_ft' => $width,
        'height_ft' => $height,
        'total_sqft' => $sqft,
        'price_per_sqft' => $pricePerSqft,
        'price_per_piece' => $pricePerPiece,
        'estimated_total' => round($total, 2),
    ];
}

function pf_seed_job_status_from_order(string $orderStatus): string {
    return match ($orderStatus) {
        'Completed' => 'COMPLETED',
        'Cancelled' => 'CANCELLED',
        'Processing' => 'IN_PRODUCTION',
        'Ready for Pickup' => 'TO_RECEIVE',
        'Pending' => pf_seed_weighted_pick([
            ['status' => 'PENDING', 'weight' => 55],
            ['status' => 'APPROVED', 'weight' => 30],
            ['status' => 'TO_PAY', 'weight' => 15],
        ]),
        default => 'PENDING',
    };
}

function pf_seed_job_payment_status(string $orderStatus): string {
    return match ($orderStatus) {
        'Completed', 'Ready for Pickup' => 'PAID',
        'Processing' => pf_seed_weighted_pick([
            ['status' => 'PAID', 'weight' => 55],
            ['status' => 'PARTIAL', 'weight' => 30],
            ['status' => 'PENDING_VERIFICATION', 'weight' => 15],
        ]),
        'Cancelled' => 'UNPAID',
        default => pf_seed_weighted_pick([
            ['status' => 'UNPAID', 'weight' => 70],
            ['status' => 'PENDING_VERIFICATION', 'weight' => 20],
            ['status' => 'PARTIAL', 'weight' => 10],
        ]),
    };
}

function pf_seed_customer_name(array $customer): string {
    return trim((string)($customer['first_name'] ?? '') . ' ' . (string)($customer['last_name'] ?? '')) ?: 'Walk-in Customer';
}

function pf_seed_placeholder_where(): string {
    return "(sku LIKE 'SYS-DELETED-PRODUCT%' OR name LIKE 'SYS-DELETED-PRODUCT%' OR name LIKE '%Deleted Product%')";
}

function pf_seed_placeholder_products(): array {
    return db_query(
        "SELECT product_id, sku, name, category
         FROM products
         WHERE " . pf_seed_placeholder_where() . "
         ORDER BY product_id"
    ) ?: [];
}

function pf_seed_active_replacement_products(): array {
    return db_query(
        "SELECT product_id, sku, name, category, product_type, price
         FROM products
         WHERE status = 'Activated'
           AND NOT " . pf_seed_placeholder_where() . "
         ORDER BY product_id"
    ) ?: [];
}

function pf_seed_guess_category_from_item(array $item): string {
    $haystack = strtolower(implode(' ', [
        (string)($item['sku'] ?? ''),
        (string)($item['customization_data'] ?? ''),
        (string)($item['placeholder_sku'] ?? ''),
        (string)($item['placeholder_name'] ?? ''),
    ]));

    if (str_contains($haystack, 'tarp')) return 'Tarpaulin';
    if (str_contains($haystack, 'shirt') || str_contains($haystack, 'apparel')) return 'T-Shirt';
    if (str_contains($haystack, 'sticker') || str_contains($haystack, 'decal')) return 'Stickers';
    if (str_contains($haystack, 'sintra')) return 'Sintraboard';
    if (str_contains($haystack, 'sign')) return 'Signage';
    if (str_contains($haystack, 'mug') || str_contains($haystack, 'souvenir')) return 'Merchandise';

    return '';
}

function pf_seed_pick_replacement_product(array $item, array $products): ?array {
    if (empty($products)) return null;

    $sku = trim((string)($item['sku'] ?? ''));
    if ($sku !== '') {
        foreach ($products as $product) {
            if (strcasecmp((string)$product['sku'], $sku) === 0) {
                return $product;
            }
        }
    }

    $category = pf_seed_guess_category_from_item($item);
    if ($category !== '') {
        $matches = array_values(array_filter($products, function ($product) use ($category) {
            return stripos((string)$product['category'], $category) !== false
                || stripos((string)$product['name'], $category) !== false;
        }));
        if (!empty($matches)) {
            return $matches[array_rand($matches)];
        }
    }

    return $products[array_rand($products)];
}

function pf_seed_pick_product_for_service(string $serviceType, array $products): ?array {
    if (empty($products)) return null;

    $needle = strtolower($serviceType);
    $categoryHints = [];
    if (str_contains($needle, 'tarp')) $categoryHints[] = 'Tarpaulin';
    if (str_contains($needle, 'shirt')) $categoryHints[] = 'T-Shirt';
    if (str_contains($needle, 'sticker') || str_contains($needle, 'decal')) $categoryHints[] = 'Stickers';
    if (str_contains($needle, 'sintra')) $categoryHints[] = 'Sintraboard';
    if (str_contains($needle, 'sign') || str_contains($needle, 'reflector')) $categoryHints[] = 'Signage';
    if (str_contains($needle, 'layout')) $categoryHints[] = 'Design Services';
    if (str_contains($needle, 'souvenir')) $categoryHints[] = 'Souvenirs';

    foreach ($categoryHints as $hint) {
        $matches = array_values(array_filter($products, function ($product) use ($hint) {
            return stripos((string)$product['category'], $hint) !== false
                || stripos((string)$product['name'], $hint) !== false;
        }));
        if (!empty($matches)) return $matches[array_rand($matches)];
    }

    return $products[array_rand($products)];
}

function pf_seed_table_exists(string $table): bool {
    $r = db_query(
        "SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
        's',
        [$table]
    );
    return !empty($r) && (int)($r[0]['c'] ?? 0) > 0;
}

function pf_seed_column_exists(string $table, string $column): bool {
    $r = db_query(
        "SELECT COUNT(*) AS c
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
        'ss',
        [$table, $column]
    );
    return !empty($r) && (int)($r[0]['c'] ?? 0) > 0;
}

function pf_seed_ids_csv(array $ids): string {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
    return implode(',', $ids);
}

function pf_seed_count_where_in(string $table, string $column, array $ids): int {
    $csv = pf_seed_ids_csv($ids);
    if ($csv === '' || !pf_seed_table_exists($table) || !pf_seed_column_exists($table, $column)) return 0;
    $r = db_query("SELECT COUNT(*) AS c FROM `{$table}` WHERE `{$column}` IN ({$csv})");
    return (int)($r[0]['c'] ?? 0);
}

function pf_seed_backup_and_delete_where_in(mysqli $conn, string $table, string $column, array $ids, string $backupPrefix): int {
    $csv = pf_seed_ids_csv($ids);
    if ($csv === '' || !pf_seed_table_exists($table) || !pf_seed_column_exists($table, $column)) return 0;

    $count = pf_seed_count_where_in($table, $column, $ids);
    if ($count <= 0) return 0;

    $conn->query("CREATE TABLE IF NOT EXISTS `{$backupPrefix}_{$table}` AS SELECT * FROM `{$table}` WHERE `{$column}` IN ({$csv})");
    $conn->query("DELETE FROM `{$table}` WHERE `{$column}` IN ({$csv})");
    return $count;
}

/** Random datetime within a given year/month, skewing toward weekdays */
function pf_seed_rand_date(int $year, int $month, ?string $after = null): string {
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    // Cap at today for 2026
    $maxDay = $daysInMonth;
    if ($year === 2026 && $month === (int)date('n') && $year === (int)date('Y')) {
        $maxDay = (int)date('j');
    }
    if ($maxDay < 1) $maxDay = 1;

    $attempts = 0;
    do {
        $day  = mt_rand(1, $maxDay);
        $h    = mt_rand(7, 19);
        $m    = mt_rand(0, 59);
        $s    = mt_rand(0, 59);
        $dt   = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $h, $m, $s);
        $attempts++;
    } while ($after !== null && $dt <= $after && $attempts < 20);

    return $dt;
}

/** Load reference data from DB */
function pf_seed_load_refs(): array {
    // Active products
    $products = db_query(
        "SELECT product_id, sku, name, category, product_type, price FROM products
         WHERE status = 'Activated' AND product_id NOT IN (
            SELECT product_id FROM products WHERE name LIKE 'SYS-%'
         ) ORDER BY product_id"
    );

    // Active customers
    $customers = db_query(
        "SELECT customer_id, first_name, last_name, customer_type FROM customers WHERE status = 'Active' LIMIT 300"
    );
    $customer_ids = array_column($customers, 'customer_id');

    // Active branches
    $branches = db_query("SELECT id FROM branches WHERE status = 'Active'");
    $branch_ids = array_column($branches, 'id');
    if (empty($branch_ids)) $branch_ids = [1];

    // Payment methods
    $pms = db_query("SELECT payment_method_id, name FROM payment_methods WHERE status = 'Activated'");
    if (empty($pms)) $pms = [['payment_method_id' => 1, 'name' => 'Cash']];

    // Service types for customizations
    $service_types = ['Tarpaulin Printing', 'T-Shirt Printing', 'Sticker Printing',
                      'Sintraboard Standee', 'Layout Design', 'Reflectorized Sticker',
                      'Souvenir Printing', 'Glass/Wall Sticker'];

    return [
        'products'      => $products,
        'customers'     => $customers,
        'customer_ids'  => $customer_ids,
        'branch_ids'    => $branch_ids,
        'payment_methods' => $pms,
        'service_types' => $service_types,
        'job_service_types' => pf_seed_job_service_types(),
    ];
}

/**
 * Pick a random unit price for a product's category,
 * adjusted by a realism multiplier (product's original price as anchor).
 */
function pf_seed_unit_price(array $product): float {
    [$min, $max] = pf_seed_price_range($product['category']);
    // Anchor slightly around the product's original price
    $anchor   = (float)($product['price'] ?? 500);
    $adj_min  = max($min, (int)($anchor * 0.7));
    $adj_max  = min($max, (int)($anchor * 3.0));
    if ($adj_min >= $adj_max) { $adj_min = $min; $adj_max = $max; }
    return pf_seed_rand_price($adj_min, $adj_max);
}

/** Pick a unit price that keeps the line/order amount under the demo cap. */
function pf_seed_unit_price_for_quantity(string $category, int $qty, float $remaining_budget = 5100.00): float {
    [$min, $max] = pf_seed_price_range($category);
    $qty = max(1, $qty);
    $cap = (int)floor($remaining_budget / $qty);

    // For bulk items, unit price may need to go below the category floor so the displayed amount stays realistic.
    $max_unit = min($max, max(50, $cap));
    $min_unit = min($min, $max_unit);

    return pf_seed_rand_price((int)$min_unit, (int)$max_unit);
}

/** Random order quantity appropriate for product category */
function pf_seed_quantity(string $category): int {
    // Keep demo totals realistic; bulk appears sometimes, but not enough to create 20K+ amounts.
    $bulky = ['Tarpaulin', 'Signage', 'Sintraboard', 'Sintraboard Standees'];
    foreach ($bulky as $b) {
        if (stripos($category, $b) !== false) return mt_rand(1, 2);
    }
    if (stripos($category, 'Sticker') !== false) return mt_rand(1, 10);
    if (stripos($category, 'Merchandise') !== false || stripos($category, 'Souvenir') !== false) {
        return mt_rand(1, 8);
    }
    return mt_rand(1, 5);
}

// ─────────────────────────────────────────────────────────────────────────────
// PART 1 — PRICING UPDATE
// ─────────────────────────────────────────────────────────────────────────────

function pf_seed_part1_preview(): array {
    $orders = db_query("SELECT COUNT(*) AS c FROM orders");
    $items  = db_query("SELECT COUNT(*) AS c FROM order_items");
    $jobs   = db_query("SELECT COUNT(*) AS c FROM job_orders");

    // Sample: first 5 orders with their items
    $sample = db_query(
        "SELECT o.order_id, o.total_amount, o.status,
                oi.order_item_id, oi.unit_price, oi.quantity,
                p.name AS product_name, p.category
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.order_id
         LEFT JOIN products p ON p.product_id = oi.product_id
         ORDER BY o.order_id
         LIMIT 10"
    );

    // Calculate what the new prices would look like
    $sample_preview = [];
    foreach ($sample as $row) {
        $new_unit = pf_seed_unit_price_for_quantity(
            (string)($row['category'] ?? 'default'),
            (int)$row['quantity'],
            5100.00
        );
        $new_total_item = round($new_unit * (int)$row['quantity'], 2);
        $sample_preview[] = [
            'order_id'     => $row['order_id'],
            'product'      => $row['product_name'] ?? 'Unknown',
            'category'     => $row['category'] ?? '—',
            'old_unit'     => number_format((float)$row['unit_price'], 2),
            'new_unit'     => number_format($new_unit, 2),
            'qty'          => $row['quantity'],
            'old_total'    => number_format((float)$row['unit_price'] * (int)$row['quantity'], 2),
            'new_total'    => number_format($new_total_item, 2),
        ];
    }

    return [
        'order_count'      => (int)($orders[0]['c'] ?? 0),
        'item_count'       => (int)($items[0]['c'] ?? 0),
        'job_count'        => (int)($jobs[0]['c'] ?? 0),
        'sample'           => $sample_preview,
    ];
}

function pf_seed_part1_execute(): array {
    global $conn;

    // Build a map: order_id → [item_id → {product, category}]
    $items = db_query(
        "SELECT oi.order_item_id, oi.order_id, oi.quantity,
                p.category
         FROM order_items oi
         LEFT JOIN products p ON p.product_id = oi.product_id"
    );

    // Group by order
    $order_items_map = [];
    foreach ($items as $item) {
        $oid = (int)$item['order_id'];
        $order_items_map[$oid][] = $item;
    }

    // Execute in a transaction
    $conn->begin_transaction();
    $updated_items  = 0;
    $updated_orders = 0;
    $updated_jobs   = 0;

    try {
        foreach ($order_items_map as $order_id => $order_items) {
            $order_total = 0.0;
            $remaining_budget = 5100.00;
            $item_count = count($order_items);
            foreach ($order_items as $idx => $item) {
                $cat      = (string)($item['category'] ?? 'default');
                $qty       = (int)$item['quantity'];
                $slots_left = max(1, $item_count - $idx);
                $line_budget = max(260.00, $remaining_budget / $slots_left);
                $new_price = pf_seed_unit_price_for_quantity($cat, $qty, $line_budget);
                $order_total += $new_price * $qty;
                $remaining_budget = max(0.00, 5100.00 - $order_total);

                $conn->query(sprintf(
                    "UPDATE order_items SET unit_price = %.2f WHERE order_item_id = %d",
                    $new_price,
                    (int)$item['order_item_id']
                ));
                $updated_items++;
            }

            // Determine payment type and downpayment for this order
            $pt = pf_seed_weighted_pick([
                ['status' => 'full_payment',  'weight' => 60],
                ['status' => '50_percent',    'weight' => 30],
                ['status' => 'upon_pickup',   'weight' => 10],
            ]);
            $dp = match ($pt) {
                '50_percent'  => round($order_total * 0.5, 2),
                'upon_pickup' => 0.00,
                default       => round($order_total, 2),
            };

            $conn->query(sprintf(
                "UPDATE orders SET total_amount = %.2f, downpayment_amount = %.2f, payment_type = '%s' WHERE order_id = %d",
                round($order_total, 2), $dp, $pt, $order_id
            ));
            $updated_orders++;
        }

        // Reprice job/customization records used by admin/customizations.php.
        $jobs = db_query("SELECT id, service_type, quantity, payment_status FROM job_orders");
        foreach ($jobs as $job) {
            $jobPricing = pf_seed_job_pricing((string)$job['service_type'], (int)($job['quantity'] ?? 1));
            $estimated = (float)$jobPricing['estimated_total'];
            $paymentStatus = (string)($job['payment_status'] ?? 'UNPAID');
            $amountPaid = match ($paymentStatus) {
                'PAID' => $estimated,
                'PARTIAL', 'PENDING_VERIFICATION' => round($estimated * 0.5, 2),
                default => 0.00,
            };
            $requiredPayment = match ($paymentStatus) {
                'PAID' => 0.00,
                'PARTIAL', 'PENDING_VERIFICATION' => round($estimated * 0.5, 2),
                default => $estimated,
            };

            $conn->query(sprintf(
                "UPDATE job_orders
                    SET quantity = %d,
                        width_ft = %s,
                        height_ft = %s,
                        total_sqft = %s,
                        price_per_sqft = %s,
                        price_per_piece = %s,
                        estimated_total = %.2f,
                        amount_paid = %.2f,
                        required_payment = %.2f
                  WHERE id = %d",
                (int)$jobPricing['quantity'],
                $jobPricing['width_ft'] === null ? 'NULL' : number_format((float)$jobPricing['width_ft'], 2, '.', ''),
                $jobPricing['height_ft'] === null ? 'NULL' : number_format((float)$jobPricing['height_ft'], 2, '.', ''),
                $jobPricing['total_sqft'] === null ? 'NULL' : number_format((float)$jobPricing['total_sqft'], 2, '.', ''),
                $jobPricing['price_per_sqft'] === null ? 'NULL' : number_format((float)$jobPricing['price_per_sqft'], 2, '.', ''),
                $jobPricing['price_per_piece'] === null ? 'NULL' : number_format((float)$jobPricing['price_per_piece'], 2, '.', ''),
                $estimated,
                $amountPaid,
                $requiredPayment,
                (int)$job['id']
            ));
            $updated_jobs++;
        }

        $conn->commit();
        return [
            'ok'             => true,
            'updated_orders' => $updated_orders,
            'updated_items'  => $updated_items,
            'updated_jobs'   => $updated_jobs,
        ];
    } catch (\Throwable $e) {
        $conn->rollback();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PART 2 — HISTORICAL DATA GENERATION
// ─────────────────────────────────────────────────────────────────────────────

function pf_seed_part2_preview(): array {
    $schedule = pf_seed_schedule();
    $year_totals = [];
    $grand_total = 0;
    foreach ($schedule as $year => $months) {
        $t = array_sum($months);
        $year_totals[$year] = $t;
        $grand_total += $t;
    }

    $refs = pf_seed_load_refs();
    $prod_sample = array_slice($refs['products'], 0, 5);
    $sample_items = [];
    foreach ($prod_sample as $p) {
        [$mn, $mx] = pf_seed_price_range($p['category']);
        $sample_items[] = [
            'name'      => $p['name'],
            'category'  => $p['category'],
            'price_min' => $mn,
            'price_max' => $mx,
        ];
    }

    return [
        'year_totals'  => $year_totals,
        'grand_total'  => $grand_total,
        'product_count'=> count($refs['products']),
        'customer_count'=> count($refs['customer_ids']),
        'branch_count' => count($refs['branch_ids']),
        'sample_items' => $sample_items,
    ];
}

function pf_seed_part2_execute(): array {
    global $conn;

    $schedule  = pf_seed_schedule();
    $refs      = pf_seed_load_refs();

    $products     = $refs['products'];
    $customers    = $refs['customers'];
    $customer_ids = $refs['customer_ids'];
    $branch_ids   = $refs['branch_ids'];
    $pms          = $refs['payment_methods'];
    $service_types = $refs['service_types'];
    $job_service_types = $refs['job_service_types'];

    if (empty($products) || empty($customer_ids)) {
        return ['ok' => false, 'error' => 'No active products or customers found.'];
    }

    $prod_count  = count($products);
    $cust_count  = count($customer_ids);
    $branch_count = count($branch_ids);
    $pm_count    = count($pms);
    $customer_map = [];
    foreach ($customers as $customer) {
        $customer_map[(int)$customer['customer_id']] = $customer;
    }

    $inserted_orders  = 0;
    $inserted_items   = 0;
    $inserted_hist    = 0;
    $inserted_custom  = 0;
    $inserted_svc     = 0;
    $inserted_jobs    = 0;
    $year_counts      = [];
    $errors           = [];

    // Backup tables before generation
    $ts = date('YmdHis');
    foreach (['orders', 'order_items', 'order_status_history', 'customizations', 'service_orders', 'job_orders'] as $t) {
        $exists = db_query("SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?", 's', [$t]);
        if (!empty($exists) && (int)$exists[0]['c'] > 0) {
            $cnt = db_query("SELECT COUNT(*) AS c FROM `{$t}`");
            if ((int)$cnt[0]['c'] > 0) {
                @$conn->query("CREATE TABLE IF NOT EXISTS `backup_seed_{$ts}_{$t}` SELECT * FROM `{$t}`");
            }
        }
    }

    $conn->begin_transaction();

    try {
        foreach ($schedule as $year => $months) {
            $year_counts[$year] = 0;

            foreach ($months as $month => $count) {
                for ($i = 0; $i < $count; $i++) {
                    // ── Pick random params ──
                    $cust_id  = $customer_ids[mt_rand(0, $cust_count - 1)];
                    $branch_i = $branch_ids[mt_rand(0, $branch_count - 1)];
                    $pm       = $pms[mt_rand(0, $pm_count - 1)];
                    $order_dt = pf_seed_rand_date($year, $month);
                    $status   = pf_seed_weighted_pick(pf_seed_order_status($year, $month));
                    $is_svc   = (mt_rand(1, 10) <= 3); // 30% service orders

                    // ── Service order (separate table) ──
                    if ($is_svc) {
                        $svc_name  = $job_service_types[mt_rand(0, count($job_service_types) - 1)];
                        $jobPricing = pf_seed_job_pricing($svc_name, pf_seed_quantity($svc_name));
                        $svc_price = (float)$jobPricing['estimated_total'];
                        $svc_status = $status;
                        $conn->query(sprintf(
                            "INSERT INTO service_orders (service_name, customer_id, branch_id, status, total_price, created_at, updated_at)
                             VALUES ('%s', %d, %d, '%s', %.2f, '%s', '%s')",
                            $conn->real_escape_string($svc_name),
                            $cust_id, $branch_i,
                            $conn->real_escape_string($svc_status),
                            $svc_price, $order_dt, $order_dt
                        ));
                        $inserted_svc++;

                        $customer = $customer_map[(int)$cust_id] ?? [];
                        $jobStatus = pf_seed_job_status_from_order($status);
                        $jobPayStatus = pf_seed_job_payment_status($status);
                        $amountPaid = $jobPayStatus === 'PAID' ? $svc_price : (in_array($jobPayStatus, ['PARTIAL', 'PENDING_VERIFICATION'], true) ? round($svc_price * 0.5, 2) : 0.00);
                        $requiredPayment = max(0.00, $svc_price - $amountPaid);
                        $dueDate = date('Y-m-d H:i:s', strtotime($order_dt . ' +' . mt_rand(2, 7) . ' days'));
                        $conn->query(sprintf(
                            "INSERT INTO job_orders
                                (customer_id, branch_id, job_title, customer_name, service_type, status, customer_type,
                                 width_ft, height_ft, quantity, total_sqft, price_per_sqft, price_per_piece,
                                 estimated_total, amount_paid, required_payment, payment_status,
                                 due_date, priority, created_at, updated_at, payment_method)
                             VALUES
                                (%d, %d, '%s', '%s', '%s', '%s', '%s',
                                 %s, %s, %d, %s, %s, %s,
                                 %.2f, %.2f, %.2f, '%s',
                                 '%s', '%s', '%s', '%s', '%s')",
                            $cust_id,
                            $branch_i,
                            $conn->real_escape_string($svc_name),
                            $conn->real_escape_string(pf_seed_customer_name($customer)),
                            $conn->real_escape_string($svc_name),
                            $conn->real_escape_string($jobStatus),
                            $conn->real_escape_string(strtoupper((string)($customer['customer_type'] ?? 'NEW')) === 'REGULAR' ? 'REGULAR' : 'NEW'),
                            $jobPricing['width_ft'] === null ? 'NULL' : number_format((float)$jobPricing['width_ft'], 2, '.', ''),
                            $jobPricing['height_ft'] === null ? 'NULL' : number_format((float)$jobPricing['height_ft'], 2, '.', ''),
                            (int)$jobPricing['quantity'],
                            $jobPricing['total_sqft'] === null ? 'NULL' : number_format((float)$jobPricing['total_sqft'], 2, '.', ''),
                            $jobPricing['price_per_sqft'] === null ? 'NULL' : number_format((float)$jobPricing['price_per_sqft'], 2, '.', ''),
                            $jobPricing['price_per_piece'] === null ? 'NULL' : number_format((float)$jobPricing['price_per_piece'], 2, '.', ''),
                            $svc_price,
                            $amountPaid,
                            $requiredPayment,
                            $conn->real_escape_string($jobPayStatus),
                            $dueDate,
                            $conn->real_escape_string(pf_seed_weighted_pick([
                                ['status' => 'NORMAL', 'weight' => 75],
                                ['status' => 'HIGH', 'weight' => 15],
                                ['status' => 'LOW', 'weight' => 10],
                            ])),
                            $order_dt,
                            $order_dt,
                            $conn->real_escape_string($pm['name'])
                        ));
                        $inserted_jobs++;
                        // Don't also create a product order for this iteration
                        $year_counts[$year]++;
                        continue;
                    }

                    // ── Product order ──
                    $order_type = (mt_rand(1, 10) <= 6) ? 'product' : 'custom';
                    $order_src  = pf_seed_weighted_pick([
                        ['status' => 'customer',  'weight' => 60],
                        ['status' => 'pos',       'weight' => 25],
                        ['status' => 'walk-in',   'weight' => 15],
                    ]);

                    // Payment
                    $pay_type = pf_seed_weighted_pick([
                        ['status' => 'full_payment', 'weight' => 60],
                        ['status' => '50_percent',   'weight' => 30],
                        ['status' => 'upon_pickup',  'weight' => 10],
                    ]);
                    $pay_status = match (true) {
                        $status === 'Completed'                  => 'Paid',
                        $status === 'Cancelled'                  => (mt_rand(1,3) === 1 ? 'Refunded' : 'Unpaid'),
                        $status === 'Processing'                 => (mt_rand(1,3) === 1 ? 'Pending Verification' : 'Paid'),
                        $status === 'Ready for Pickup'           => 'Paid',
                        default                                   => 'Unpaid',
                    };

                    // Design status
                    $design_status = match ($status) {
                        'Completed'       => 'Approved',
                        'Cancelled'       => 'Cancelled',
                        'Processing'      => (mt_rand(0,1) ? 'Approved' : 'Pending'),
                        'Ready for Pickup'=> 'Approved',
                        default           => 'Pending',
                    };

                    // PM name
                    $pm_name = $pm['name'];

                    // ── Insert order (no total yet) ──
                    $conn->query(sprintf(
                        "INSERT INTO orders (customer_id, order_date, updated_at, total_amount, downpayment_amount,
                             status, payment_status, payment_method_id, payment_method,
                             branch_id, design_status, payment_type, order_type, order_source)
                         VALUES (%d, '%s', '%s', 0.00, 0.00, '%s', '%s', %d, '%s', %d, '%s', '%s', '%s', '%s')",
                        $cust_id, $order_dt, $order_dt,
                        $conn->real_escape_string($status),
                        $conn->real_escape_string($pay_status),
                        (int)$pm['payment_method_id'],
                        $conn->real_escape_string($pm_name),
                        $branch_i,
                        $conn->real_escape_string($design_status),
                        $conn->real_escape_string($pay_type),
                        $conn->real_escape_string($order_type),
                        $conn->real_escape_string($order_src)
                    ));
                    $order_id = (int)$conn->insert_id;
                    if ($order_id <= 0) continue;
                    $inserted_orders++;

                    // ── Insert 1–2 order items ──
                    $num_items   = (mt_rand(1, 10) <= 8) ? 1 : 2;
                    $order_total = 0.0;
                    $picked_prods = [];

                    for ($j = 0; $j < $num_items; $j++) {
                        // Avoid duplicate product in same order
                        $attempts = 0;
                        do {
                            $prod = $products[mt_rand(0, $prod_count - 1)];
                            $attempts++;
                        } while (in_array($prod['product_id'], $picked_prods) && $attempts < 10);
                        $picked_prods[] = $prod['product_id'];

                        $qty        = pf_seed_quantity($prod['category']);
                        $slots_left = max(1, $num_items - $j);
                        $remaining_budget = max(260.00, 5100.00 - $order_total);
                        $line_budget = $remaining_budget / $slots_left;
                        $unit_price = pf_seed_unit_price_for_quantity($prod['category'], $qty, $line_budget);
                        $order_total += $unit_price * $qty;

                        // Shifted item date (slightly after order date)
                        $item_dt = pf_seed_rand_date($year, $month, $order_dt);

                        $conn->query(sprintf(
                            "INSERT INTO order_items (order_id, product_id, quantity, unit_price, sku)
                             VALUES (%d, %d, %d, %.2f, %s)",
                            $order_id,
                            (int)$prod['product_id'],
                            $qty,
                            $unit_price,
                            $prod['sku'] ? ("'" . $conn->real_escape_string($prod['sku'] ?? '') . "'") : 'NULL'
                        ));
                        $item_id = (int)$conn->insert_id;
                        $inserted_items++;

                        // ── Customization for 'custom' type orders ──
                        if ($order_type === 'custom' && $item_id > 0) {
                            $svc_type = $service_types[mt_rand(0, count($service_types) - 1)];
                            $details  = json_encode([
                                'size'    => pf_seed_weighted_pick([
                                    ['status' => '2x3 ft',  'weight' => 20],
                                    ['status' => '3x4 ft',  'weight' => 25],
                                    ['status' => '4x6 ft',  'weight' => 20],
                                    ['status' => '5x8 ft',  'weight' => 15],
                                    ['status' => '8x10 ft', 'weight' => 10],
                                    ['status' => 'custom',  'weight' => 10],
                                ]),
                                'material' => pf_seed_weighted_pick([
                                    ['status' => 'Tarpaulin 8oz',   'weight' => 30],
                                    ['status' => 'Tarpaulin 10oz',  'weight' => 25],
                                    ['status' => 'Vinyl',           'weight' => 20],
                                    ['status' => 'Sintraboard 3mm', 'weight' => 15],
                                    ['status' => 'Cotton',          'weight' => 10],
                                ]),
                                'quantity' => $qty,
                                'notes'    => '',
                            ]);
                            $cust_status = match ($status) {
                                'Completed'       => 'Completed',
                                'Cancelled'       => 'Cancelled',
                                'Processing'      => 'Approved',
                                'Ready for Pickup'=> 'Completed',
                                default           => 'Pending Review',
                            };

                            $conn->query(sprintf(
                                "INSERT INTO customizations (order_id, order_item_id, customer_id, service_type, customization_details, status, created_at, updated_at)
                                 VALUES (%d, %d, %d, '%s', '%s', '%s', '%s', '%s')",
                                $order_id, $item_id, $cust_id,
                                $conn->real_escape_string($svc_type),
                                $conn->real_escape_string($details),
                                $conn->real_escape_string($cust_status),
                                $order_dt, $order_dt
                            ));
                            $inserted_custom++;

                            $jobSvcType = $job_service_types[mt_rand(0, count($job_service_types) - 1)];
                            $jobPricing = pf_seed_job_pricing($jobSvcType, $qty);
                            $jobTotal = min(5100.00, max(260.00, (float)$jobPricing['estimated_total']));
                            $jobStatus = pf_seed_job_status_from_order($status);
                            $jobPayStatus = pf_seed_job_payment_status($status);
                            $amountPaid = $jobPayStatus === 'PAID' ? $jobTotal : (in_array($jobPayStatus, ['PARTIAL', 'PENDING_VERIFICATION'], true) ? round($jobTotal * 0.5, 2) : 0.00);
                            $requiredPayment = max(0.00, $jobTotal - $amountPaid);
                            $customer = $customer_map[(int)$cust_id] ?? [];
                            $dueDate = date('Y-m-d H:i:s', strtotime($order_dt . ' +' . mt_rand(2, 7) . ' days'));

                            $conn->query(sprintf(
                                "INSERT INTO job_orders
                                    (order_id, customer_id, order_item_id, branch_id, job_title, customer_name, service_type, status, customer_type,
                                     width_ft, height_ft, quantity, total_sqft, price_per_sqft, price_per_piece,
                                     estimated_total, amount_paid, required_payment, payment_status,
                                     due_date, priority, created_at, updated_at, payment_method)
                                 VALUES
                                    (%d, %d, %d, %d, '%s', '%s', '%s', '%s', '%s',
                                     %s, %s, %d, %s, %s, %s,
                                     %.2f, %.2f, %.2f, '%s',
                                     '%s', '%s', '%s', '%s', '%s')",
                                $order_id,
                                $cust_id,
                                $item_id,
                                $branch_i,
                                $conn->real_escape_string($jobSvcType),
                                $conn->real_escape_string(pf_seed_customer_name($customer)),
                                $conn->real_escape_string($jobSvcType),
                                $conn->real_escape_string($jobStatus),
                                $conn->real_escape_string(strtoupper((string)($customer['customer_type'] ?? 'NEW')) === 'REGULAR' ? 'REGULAR' : 'NEW'),
                                $jobPricing['width_ft'] === null ? 'NULL' : number_format((float)$jobPricing['width_ft'], 2, '.', ''),
                                $jobPricing['height_ft'] === null ? 'NULL' : number_format((float)$jobPricing['height_ft'], 2, '.', ''),
                                (int)$jobPricing['quantity'],
                                $jobPricing['total_sqft'] === null ? 'NULL' : number_format((float)$jobPricing['total_sqft'], 2, '.', ''),
                                $jobPricing['price_per_sqft'] === null ? 'NULL' : number_format((float)$jobPricing['price_per_sqft'], 2, '.', ''),
                                $jobPricing['price_per_piece'] === null ? 'NULL' : number_format((float)$jobPricing['price_per_piece'], 2, '.', ''),
                                $jobTotal,
                                $amountPaid,
                                $requiredPayment,
                                $conn->real_escape_string($jobPayStatus),
                                $dueDate,
                                $conn->real_escape_string(pf_seed_weighted_pick([
                                    ['status' => 'NORMAL', 'weight' => 75],
                                    ['status' => 'HIGH', 'weight' => 15],
                                    ['status' => 'LOW', 'weight' => 10],
                                ])),
                                $order_dt,
                                $order_dt,
                                $conn->real_escape_string($pm_name)
                            ));
                            $inserted_jobs++;
                        }
                    }

                    // ── Update order total ──
                    $dp = match ($pay_type) {
                        '50_percent'  => round($order_total * 0.5, 2),
                        'upon_pickup' => 0.00,
                        default       => round($order_total, 2),
                    };
                    $conn->query(sprintf(
                        "UPDATE orders SET total_amount = %.2f, downpayment_amount = %.2f WHERE order_id = %d",
                        round($order_total, 2), $dp, $order_id
                    ));

                    // ── Status history ──
                    $hist_steps = match ($status) {
                        'Completed'      => ['Pending', 'Processing', 'Ready for Pickup', 'Completed'],
                        'Cancelled'      => ['Pending', 'Cancelled'],
                        'Processing'     => ['Pending', 'Processing'],
                        'Ready for Pickup' => ['Pending', 'Processing', 'Ready for Pickup'],
                        default          => ['Pending'],
                    };

                    $prev_dt = $order_dt;
                    for ($h = 0; $h < count($hist_steps); $h++) {
                        $old = ($h === 0) ? 'Pending' : $hist_steps[$h - 1];
                        $new = $hist_steps[$h];
                        if ($h > 0 && $old === $new) continue;
                        $by  = pf_seed_weighted_pick([
                            ['status' => 'Staff', 'weight' => 60],
                            ['status' => 'Admin', 'weight' => 30],
                            ['status' => 'Customer', 'weight' => 10],
                        ]);
                        $hist_dt = pf_seed_rand_date($year, $month, $prev_dt);
                        $conn->query(sprintf(
                            "INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, changed_at)
                             VALUES (%d, '%s', '%s', '%s', '%s')",
                            $order_id,
                            $conn->real_escape_string($old),
                            $conn->real_escape_string($new),
                            $conn->real_escape_string($by),
                            $hist_dt
                        ));
                        $inserted_hist++;
                        $prev_dt = $hist_dt;
                    }

                    $year_counts[$year]++;
                } // end for $count
            } // end foreach months
        } // end foreach schedule

        $conn->commit();

    } catch (\Throwable $e) {
        $conn->rollback();
        return ['ok' => false, 'error' => $e->getMessage(), 'year_counts' => $year_counts];
    }

    return [
        'ok'              => true,
        'year_counts'     => $year_counts,
        'inserted_orders' => $inserted_orders,
        'inserted_items'  => $inserted_items,
        'inserted_hist'   => $inserted_hist,
        'inserted_custom' => $inserted_custom,
        'inserted_svc'    => $inserted_svc,
        'inserted_jobs'   => $inserted_jobs,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// PART 3 — REPAIR SYS-DELETED-PRODUCT REFERENCES
// ─────────────────────────────────────────────────────────────────────────────

function pf_seed_repair_deleted_preview(): array {
    $placeholders = pf_seed_placeholder_products();
    $placeholderIds = array_map('intval', array_column($placeholders, 'product_id'));

    $linkedCount = 0;
    $samples = [];
    if (!empty($placeholderIds)) {
        $idList = implode(',', $placeholderIds);
        $count = db_query("SELECT COUNT(*) AS c FROM order_items WHERE product_id IN ({$idList})");
        $linkedCount = (int)($count[0]['c'] ?? 0);
        $samples = db_query(
            "SELECT oi.order_item_id, oi.order_id, oi.sku, oi.quantity, p.sku AS placeholder_sku, p.name AS placeholder_name
             FROM order_items oi
             JOIN products p ON p.product_id = oi.product_id
             WHERE oi.product_id IN ({$idList})
             ORDER BY oi.order_item_id DESC
             LIMIT 10"
        ) ?: [];
    }

    return [
        'placeholder_count' => count($placeholders),
        'linked_items' => $linkedCount,
        'replacement_count' => count(pf_seed_active_replacement_products()),
        'placeholders' => $placeholders,
        'samples' => $samples,
    ];
}

function pf_seed_repair_deleted_execute(): array {
    global $conn;

    $placeholders = pf_seed_placeholder_products();
    $placeholderIds = array_map('intval', array_column($placeholders, 'product_id'));
    $products = pf_seed_active_replacement_products();

    if (empty($placeholderIds)) {
        return ['ok' => true, 'repaired' => 0, 'message' => 'No SYS-DELETED-PRODUCT placeholders found.'];
    }
    if (empty($products)) {
        return ['ok' => false, 'error' => 'No active replacement products found.'];
    }

    $idList = implode(',', $placeholderIds);
    $items = db_query(
        "SELECT oi.order_item_id, oi.order_id, oi.product_id, oi.sku, oi.quantity, oi.customization_data,
                p.sku AS placeholder_sku, p.name AS placeholder_name
         FROM order_items oi
         JOIN products p ON p.product_id = oi.product_id
         WHERE oi.product_id IN ({$idList})"
    ) ?: [];

    $ts = date('YmdHis');
    $conn->begin_transaction();

    $repaired = 0;
    $byProduct = [];

    try {
        if (!empty($items)) {
            $conn->query(
                "CREATE TABLE IF NOT EXISTS `backup_repair_deleted_product_{$ts}_order_items` AS
                 SELECT * FROM order_items WHERE product_id IN ({$idList})"
            );
        }

        foreach ($items as $item) {
            $replacement = pf_seed_pick_replacement_product($item, $products);
            if (!$replacement) continue;

            $qty = max(1, (int)$item['quantity']);
            $unitPrice = pf_seed_unit_price_for_quantity((string)$replacement['category'], $qty, 5100.00);
            $skuSql = trim((string)$replacement['sku']) !== ''
                ? "'" . $conn->real_escape_string((string)$replacement['sku']) . "'"
                : 'NULL';

            $conn->query(sprintf(
                "UPDATE order_items
                    SET product_id = %d,
                        sku = %s,
                        unit_price = %.2f
                  WHERE order_item_id = %d",
                (int)$replacement['product_id'],
                $skuSql,
                $unitPrice,
                (int)$item['order_item_id']
            ));

            $oid = (int)$item['order_id'];
            $total = db_query(
                "SELECT SUM(quantity * unit_price) AS total FROM order_items WHERE order_id = ?",
                'i',
                [$oid]
            );
            $orderTotal = round((float)($total[0]['total'] ?? 0), 2);
            $conn->query(sprintf(
                "UPDATE orders
                    SET total_amount = %.2f,
                        downpayment_amount = CASE
                            WHEN payment_type = '50_percent' THEN %.2f
                            WHEN payment_type = 'upon_pickup' THEN 0.00
                            ELSE %.2f
                        END
                  WHERE order_id = %d",
                $orderTotal,
                round($orderTotal * 0.5, 2),
                $orderTotal,
                $oid
            ));

            $byProduct[$replacement['name']] = ($byProduct[$replacement['name']] ?? 0) + 1;
            $repaired++;
        }

        $conn->commit();
    } catch (\Throwable $e) {
        $conn->rollback();
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    return [
        'ok' => true,
        'repaired' => $repaired,
        'replacement_summary' => $byProduct,
    ];
}

function pf_seed_deleted_transaction_ids(): array {
    $placeholders = pf_seed_placeholder_products();
    $placeholderIds = array_map('intval', array_column($placeholders, 'product_id'));
    if (empty($placeholderIds)) {
        return [
            'placeholder_ids' => [],
            'order_ids' => [],
            'order_item_ids' => [],
            'pos_transaction_ids' => [],
            'pos_item_ids' => [],
        ];
    }

    $idList = implode(',', $placeholderIds);
    $orderRows = db_query("
        SELECT DISTINCT order_id
        FROM order_items
        WHERE product_id IN ({$idList}) AND order_id IS NOT NULL
    ") ?: [];
    $itemRows = db_query("
        SELECT order_item_id
        FROM order_items
        WHERE product_id IN ({$idList})
    ") ?: [];

    $posTransactionIds = [];
    $posItemIds = [];
    if (pf_seed_table_exists('pos_items') && pf_seed_column_exists('pos_items', 'product_id')) {
        $posItemPk = pf_seed_column_exists('pos_items', 'id') ? 'id' : (pf_seed_column_exists('pos_items', 'pos_item_id') ? 'pos_item_id' : '');
        $posTxnCol = pf_seed_column_exists('pos_items', 'transaction_id') ? 'transaction_id' : (pf_seed_column_exists('pos_items', 'pos_transaction_id') ? 'pos_transaction_id' : '');
        $selectCols = [];
        if ($posItemPk !== '') $selectCols[] = "`{$posItemPk}` AS item_id";
        if ($posTxnCol !== '') $selectCols[] = "`{$posTxnCol}` AS txn_id";
        if (empty($selectCols)) $selectCols[] = 'product_id';

        $posItemRows = db_query("SELECT " . implode(', ', $selectCols) . " FROM pos_items WHERE product_id IN ({$idList})") ?: [];
        foreach ($posItemRows as $row) {
            $posItemIds[] = (int)($row['item_id'] ?? 0);
            $posTransactionIds[] = (int)($row['txn_id'] ?? 0);
        }
    }

    return [
        'placeholder_ids' => $placeholderIds,
        'order_ids' => array_values(array_unique(array_map('intval', array_column($orderRows, 'order_id')))),
        'order_item_ids' => array_values(array_unique(array_map('intval', array_column($itemRows, 'order_item_id')))),
        'pos_transaction_ids' => array_values(array_unique(array_filter($posTransactionIds))),
        'pos_item_ids' => array_values(array_unique(array_filter($posItemIds))),
    ];
}

function pf_seed_delete_deleted_transactions_preview(): array {
    $ids = pf_seed_deleted_transaction_ids();
    $orderIds = $ids['order_ids'];
    $orderItemIds = $ids['order_item_ids'];
    $posTransactionIds = $ids['pos_transaction_ids'];

    $samples = [];
    $csv = pf_seed_ids_csv($orderIds);
    if ($csv !== '') {
        $samples = db_query(
            "SELECT o.order_id, o.order_date, o.total_amount, o.status, o.payment_status,
                    CONCAT(c.first_name, ' ', c.last_name) AS customer_name
             FROM orders o
             LEFT JOIN customers c ON c.customer_id = o.customer_id
             WHERE o.order_id IN ({$csv})
             ORDER BY o.order_date DESC
             LIMIT 10"
        ) ?: [];
    }

    return [
        'placeholder_count' => count($ids['placeholder_ids']),
        'order_count' => count($orderIds),
        'order_item_count' => count($orderItemIds),
        'customization_count' => pf_seed_count_where_in('customizations', 'order_id', $orderIds),
        'job_order_count' => pf_seed_count_where_in('job_orders', 'order_id', $orderIds) + pf_seed_count_where_in('job_orders', 'order_item_id', $orderItemIds),
        'status_history_count' => pf_seed_count_where_in('order_status_history', 'order_id', $orderIds),
        'pos_transaction_count' => count($posTransactionIds),
        'pos_item_count' => count($ids['pos_item_ids']),
        'samples' => $samples,
    ];
}

function pf_seed_delete_deleted_transactions_execute(): array {
    global $conn;

    $ids = pf_seed_deleted_transaction_ids();
    $orderIds = $ids['order_ids'];
    $orderItemIds = $ids['order_item_ids'];
    $posTransactionIds = $ids['pos_transaction_ids'];
    $posItemIds = $ids['pos_item_ids'];

    if (empty($orderIds) && empty($posTransactionIds)) {
        return ['ok' => true, 'deleted_orders' => 0, 'deleted_pos_transactions' => 0, 'message' => 'No transactions with deleted products found.'];
    }

    $ts = date('YmdHis');
    $backup = "backup_delete_deleted_product_txn_{$ts}";
    $deleted = [];

    $conn->begin_transaction();
    try {
        // Delete children that hang off order_messages/reviews before their parents.
        $messageIds = [];
        if (pf_seed_table_exists('order_messages') && pf_seed_column_exists('order_messages', 'message_id')) {
            $msgCsv = pf_seed_ids_csv($orderIds);
            if ($msgCsv !== '') {
                $rows = db_query("SELECT message_id FROM order_messages WHERE order_id IN ({$msgCsv})") ?: [];
                $messageIds = array_values(array_unique(array_map('intval', array_column($rows, 'message_id'))));
            }
        }
        $deleted['message_reactions'] = pf_seed_backup_and_delete_where_in($conn, 'message_reactions', 'message_id', $messageIds, $backup);

        $reviewIds = [];
        if (pf_seed_table_exists('reviews') && pf_seed_column_exists('reviews', 'order_id')) {
            $revCsv = pf_seed_ids_csv($orderIds);
            if ($revCsv !== '') {
                $rows = db_query("SELECT review_id FROM reviews WHERE order_id IN ({$revCsv})") ?: [];
                $reviewIds = array_values(array_unique(array_map('intval', array_column($rows, 'review_id'))));
            }
        }
        foreach (['review_images', 'review_replies', 'review_helpful'] as $table) {
            $deleted[$table] = pf_seed_backup_and_delete_where_in($conn, $table, 'review_id', $reviewIds, $backup);
        }
        $deleted['reviews'] = pf_seed_backup_and_delete_where_in($conn, 'reviews', 'review_id', $reviewIds, $backup);
        $deleted['ratings'] = pf_seed_backup_and_delete_where_in($conn, 'ratings', 'order_id', $orderIds, $backup);

        // Delete children that reference order_id first.
        foreach ([
            ['order_tarp_details', 'order_id'],
            ['order_designs', 'order_id'],
            ['order_messages', 'order_id'],
            ['order_notes', 'order_id'],
            ['order_status_history', 'order_id'],
            ['service_order_details', 'order_id'],
            ['service_order_files', 'order_id'],
            ['service_orders', 'id'], // not linked to orders in this schema; skipped effectively unless ids match service ids
            ['customizations', 'order_id'],
            ['job_order_files', 'job_order_id'],
            ['job_order_ink_usage', 'job_order_id'],
            ['job_order_materials', 'job_order_id'],
        ] as [$table, $column]) {
            // job_order_* children need job ids, not order ids; handled after collecting linked jobs.
            if (str_starts_with($table, 'job_order_')) continue;
            if ($table === 'service_orders') continue;
            $deleted[$table] = ($deleted[$table] ?? 0) + pf_seed_backup_and_delete_where_in($conn, $table, $column, $orderIds, $backup);
        }

        // Collect and delete job orders related to those orders/items.
        $jobIds = [];
        if (pf_seed_table_exists('job_orders')) {
            $clauses = [];
            if (pf_seed_ids_csv($orderIds) !== '' && pf_seed_column_exists('job_orders', 'order_id')) $clauses[] = 'order_id IN (' . pf_seed_ids_csv($orderIds) . ')';
            if (pf_seed_ids_csv($orderItemIds) !== '' && pf_seed_column_exists('job_orders', 'order_item_id')) $clauses[] = 'order_item_id IN (' . pf_seed_ids_csv($orderItemIds) . ')';
            if (!empty($clauses)) {
                $jobRows = db_query('SELECT id FROM job_orders WHERE ' . implode(' OR ', $clauses)) ?: [];
                $jobIds = array_values(array_unique(array_map('intval', array_column($jobRows, 'id'))));
            }
        }
        foreach (['job_order_files', 'job_order_ink_usage', 'job_order_materials'] as $table) {
            $deleted[$table] = ($deleted[$table] ?? 0) + pf_seed_backup_and_delete_where_in($conn, $table, 'job_order_id', $jobIds, $backup);
        }
        $deleted['job_orders'] = pf_seed_backup_and_delete_where_in($conn, 'job_orders', 'id', $jobIds, $backup);

        // Delete order items, then parent orders.
        $deleted['order_items'] = pf_seed_backup_and_delete_where_in($conn, 'order_items', 'order_id', $orderIds, $backup);
        $deleted['orders'] = pf_seed_backup_and_delete_where_in($conn, 'orders', 'order_id', $orderIds, $backup);

        // POS transactions linked to placeholder product.
        $posItemPk = pf_seed_column_exists('pos_items', 'id') ? 'id' : (pf_seed_column_exists('pos_items', 'pos_item_id') ? 'pos_item_id' : '');
        $posTxnPk = pf_seed_column_exists('pos_transactions', 'id') ? 'id' : (pf_seed_column_exists('pos_transactions', 'transaction_id') ? 'transaction_id' : (pf_seed_column_exists('pos_transactions', 'pos_transaction_id') ? 'pos_transaction_id' : ''));
        if ($posItemPk !== '') {
            $deleted['pos_items'] = pf_seed_backup_and_delete_where_in($conn, 'pos_items', $posItemPk, $posItemIds, $backup);
        }
        if ($posTxnPk !== '') {
            $deleted['pos_transactions'] = pf_seed_backup_and_delete_where_in($conn, 'pos_transactions', $posTxnPk, $posTransactionIds, $backup);
        }

        $conn->commit();
    } catch (\Throwable $e) {
        $conn->rollback();
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    return [
        'ok' => true,
        'deleted_orders' => count($orderIds),
        'deleted_order_items' => $deleted['order_items'] ?? 0,
        'deleted_pos_transactions' => count($posTransactionIds),
        'deleted' => $deleted,
    ];
}

function pf_seed_normalize_job_codes_preview(): array {
    $rows = db_query(
        "SELECT jo.id, jo.customer_id, jo.branch_id, jo.service_type, jo.estimated_total, jo.status, jo.created_at,
                jo.customer_name
         FROM job_orders jo
         WHERE (jo.order_id IS NULL OR jo.order_id = 0)
         ORDER BY jo.id DESC
         LIMIT 10"
    ) ?: [];
    $count = db_query("SELECT COUNT(*) AS c FROM job_orders WHERE order_id IS NULL OR order_id = 0");

    return [
        'job_count' => (int)($count[0]['c'] ?? 0),
        'active_product_count' => count(pf_seed_active_replacement_products()),
        'samples' => $rows,
    ];
}

function pf_seed_normalize_job_codes_execute(): array {
    global $conn;

    $jobs = db_query(
        "SELECT jo.*
         FROM job_orders jo
         WHERE jo.order_id IS NULL OR jo.order_id = 0
         ORDER BY jo.id ASC"
    ) ?: [];
    if (empty($jobs)) {
        return ['ok' => true, 'normalized' => 0, 'message' => 'No standalone job order codes found.'];
    }

    $products = pf_seed_active_replacement_products();
    if (empty($products)) {
        return ['ok' => false, 'error' => 'No active products found for code normalization.'];
    }

    $ts = date('YmdHis');
    $conn->begin_transaction();
    $normalized = 0;

    try {
        $conn->query("CREATE TABLE IF NOT EXISTS `backup_normalize_job_codes_{$ts}_job_orders` AS SELECT * FROM job_orders WHERE order_id IS NULL OR order_id = 0");

        foreach ($jobs as $job) {
            $product = pf_seed_pick_product_for_service((string)$job['service_type'], $products);
            if (!$product) continue;

            $customerId = max(1, (int)($job['customer_id'] ?? 0));
            $branchId = max(1, (int)($job['branch_id'] ?? 1));
            $createdAt = (string)($job['created_at'] ?? date('Y-m-d H:i:s'));
            $updatedAt = (string)($job['updated_at'] ?? $createdAt);
            $total = (float)($job['estimated_total'] ?? 0);
            if ($total <= 0) {
                $total = pf_seed_rand_price(260, 2500);
            }
            $total = max(260.00, min(5100.00, $total));
            $qty = max(1, min(8, (int)($job['quantity'] ?? 1)));
            $unit = round($total / $qty, 2);
            $orderStatus = match ((string)($job['status'] ?? 'PENDING')) {
                'COMPLETED' => 'Completed',
                'CANCELLED' => 'Cancelled',
                'IN_PRODUCTION' => 'Processing',
                'TO_RECEIVE' => 'Ready for Pickup',
                default => 'Pending',
            };
            $paymentStatus = match ((string)($job['payment_status'] ?? 'UNPAID')) {
                'PAID' => 'Paid',
                'PENDING_VERIFICATION' => 'Pending Verification',
                default => 'Unpaid',
            };
            $paymentType = $paymentStatus === 'Paid' ? 'full_payment' : '50_percent';
            $downpayment = $paymentStatus === 'Paid' ? $total : round($total * 0.5, 2);

            $conn->query(sprintf(
                "INSERT INTO orders
                    (customer_id, order_date, updated_at, total_amount, downpayment_amount, status, payment_status,
                     branch_id, design_status, payment_type, order_type, order_source)
                 VALUES
                    (%d, '%s', '%s', %.2f, %.2f, '%s', '%s', %d, '%s', '%s', 'custom', 'customer')",
                $customerId,
                $conn->real_escape_string($createdAt),
                $conn->real_escape_string($updatedAt),
                $total,
                $downpayment,
                $conn->real_escape_string($orderStatus),
                $conn->real_escape_string($paymentStatus),
                $branchId,
                $conn->real_escape_string($orderStatus === 'Completed' ? 'Approved' : 'Pending'),
                $conn->real_escape_string($paymentType)
            ));
            $orderId = (int)$conn->insert_id;
            if ($orderId <= 0) continue;

            $skuSql = trim((string)$product['sku']) !== ''
                ? "'" . $conn->real_escape_string((string)$product['sku']) . "'"
                : 'NULL';
            $conn->query(sprintf(
                "INSERT INTO order_items (order_id, product_id, quantity, unit_price, sku)
                 VALUES (%d, %d, %d, %.2f, %s)",
                $orderId,
                (int)$product['product_id'],
                $qty,
                $unit,
                $skuSql
            ));
            $orderItemId = (int)$conn->insert_id;

            $conn->query(sprintf(
                "UPDATE job_orders
                 SET order_id = %d, order_item_id = %d, estimated_total = %.2f, updated_at = NOW()
                 WHERE id = %d",
                $orderId,
                $orderItemId,
                $total,
                (int)$job['id']
            ));

            $normalized++;
        }

        $conn->commit();
    } catch (\Throwable $e) {
        $conn->rollback();
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    return ['ok' => true, 'normalized' => $normalized];
}

// ─────────────────────────────────────────────────────────────────────────────
// POST HANDLER
// ─────────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = trim($_POST['csrf_token'] ?? '');
    if (!verify_csrf_token($csrf)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'CSRF mismatch.']);
        exit;
    }

    header('Content-Type: application/json');
    $action = trim($_POST['action'] ?? '');

    switch ($action) {
        case 'part1_preview':
            echo json_encode(['ok' => true, 'data' => pf_seed_part1_preview()]);
            break;

        case 'part1_execute':
            if (($_POST['confirm'] ?? '') !== '1') {
                echo json_encode(['ok' => false, 'error' => 'Confirmation required.']);
                break;
            }
            $r = pf_seed_part1_execute();
            if ($r['ok'] && function_exists('log_activity')) {
                log_activity((int)($GLOBALS['current_user']['user_id'] ?? 0),
                    'Repriced Orders', 'Updated pricing on existing orders to realistic values.');
            }
            echo json_encode($r);
            break;

        case 'part2_preview':
            echo json_encode(['ok' => true, 'data' => pf_seed_part2_preview()]);
            break;

        case 'part2_execute':
            if (($_POST['confirm'] ?? '') !== '1') {
                echo json_encode(['ok' => false, 'error' => 'Confirmation required.']);
                break;
            }
            $r = pf_seed_part2_execute();
            if ($r['ok'] && function_exists('log_activity')) {
                log_activity((int)($GLOBALS['current_user']['user_id'] ?? 0),
                    'Seeded Historical Data', 'Generated historical transaction data 2021–2026.');
            }
            echo json_encode($r);
            break;

        case 'repair_deleted_preview':
            echo json_encode(['ok' => true, 'data' => pf_seed_repair_deleted_preview()]);
            break;

        case 'repair_deleted_execute':
            if (($_POST['confirm'] ?? '') !== '1') {
                echo json_encode(['ok' => false, 'error' => 'Confirmation required.']);
                break;
            }
            $r = pf_seed_repair_deleted_execute();
            if ($r['ok'] && function_exists('log_activity')) {
                log_activity((int)($GLOBALS['current_user']['user_id'] ?? 0),
                    'Repaired Deleted Product References', 'Reassigned SYS-DELETED-PRODUCT order items to active products.');
            }
            echo json_encode($r);
            break;

        case 'delete_deleted_txn_preview':
            echo json_encode(['ok' => true, 'data' => pf_seed_delete_deleted_transactions_preview()]);
            break;

        case 'delete_deleted_txn_execute':
            if (($_POST['confirm'] ?? '') !== '1') {
                echo json_encode(['ok' => false, 'error' => 'Confirmation required.']);
                break;
            }
            $r = pf_seed_delete_deleted_transactions_execute();
            if ($r['ok'] && function_exists('log_activity')) {
                log_activity((int)($GLOBALS['current_user']['user_id'] ?? 0),
                    'Deleted Transactions With Deleted Products', 'Deleted transactions linked to SYS-DELETED-PRODUCT placeholders.');
            }
            echo json_encode($r);
            break;

        case 'normalize_job_codes_preview':
            echo json_encode(['ok' => true, 'data' => pf_seed_normalize_job_codes_preview()]);
            break;

        case 'normalize_job_codes_execute':
            if (($_POST['confirm'] ?? '') !== '1') {
                echo json_encode(['ok' => false, 'error' => 'Confirmation required.']);
                break;
            }
            $r = pf_seed_normalize_job_codes_execute();
            if ($r['ok'] && function_exists('log_activity')) {
                log_activity((int)($GLOBALS['current_user']['user_id'] ?? 0),
                    'Normalized Job Order Codes', 'Linked standalone JO records to generated order codes.');
            }
            echo json_encode($r);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// GET — Render page
// ─────────────────────────────────────────────────────────────────────────────

$csrf_token   = generate_csrf_token();
$current_page = 'seed_transactions';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seed Transactions — PrintFlow Admin</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($base_path) ?>/admin/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .seed-wrap { max-width: 900px; margin: 0 auto; padding: 32px 20px 60px; }

        .part-tabs { display: flex; gap: 4px; margin-bottom: 28px; border-bottom: 2px solid #e2e8f0; }
        .tab-btn {
            padding: 10px 22px;
            border: none; background: none; cursor: pointer;
            font-size: .93rem; font-weight: 600; color: #718096;
            border-bottom: 3px solid transparent; margin-bottom: -2px;
            transition: color .15s, border-color .15s;
        }
        .tab-btn.active { color: #4299e1; border-bottom-color: #4299e1; }
        .tab-btn:hover:not(.active) { color: #4a5568; }

        .part-header {
            display: flex; align-items: flex-start; gap: 16px;
            background: #fff; border: 1.5px solid #e2e8f0;
            border-radius: 12px; padding: 20px 24px; margin-bottom: 20px;
        }
        .part-icon { font-size: 2rem; }
        .part-header h3 { margin: 0 0 4px; font-size: 1.1rem; color: #2d3748; }
        .part-header p  { margin: 0; color: #718096; font-size: .88rem; }

        .info-card {
            background: #ebf8ff; border: 1.5px solid #4299e1;
            border-radius: 10px; padding: 14px 18px; margin-bottom: 16px;
            font-size: .88rem; color: #2b6cb0;
        }

        .preview-table { width: 100%; border-collapse: collapse; font-size: .85rem; margin: 12px 0; }
        .preview-table th {
            background: #f7fafc; color: #4a5568; font-weight: 700;
            padding: 8px 10px; text-align: left; border-bottom: 2px solid #e2e8f0;
        }
        .preview-table td { padding: 7px 10px; border-bottom: 1px solid #edf2f7; }
        .preview-table tr:hover td { background: #f7fafc; }

        .year-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px; margin: 16px 0;
        }
        .year-card {
            background: #fff; border: 1.5px solid #e2e8f0; border-radius: 10px;
            padding: 14px; text-align: center;
        }
        .year-card .yr { font-size: .78rem; color: #a0aec0; font-weight: 700; text-transform: uppercase; }
        .year-card .cnt { font-size: 1.8rem; font-weight: 800; color: #4299e1; }
        .year-card .sub { font-size: .75rem; color: #718096; }

        .btn-preview {
            background: #4299e1; color: #fff; border: none;
            padding: 10px 24px; border-radius: 8px; font-size: .95rem;
            font-weight: 600; cursor: pointer; transition: background .15s;
        }
        .btn-preview:hover:not(:disabled) { background: #3182ce; }
        .btn-preview:disabled { opacity: .5; cursor: default; }

        .btn-execute {
            background: #38a169; color: #fff; border: none;
            padding: 11px 28px; border-radius: 8px; font-size: .95rem;
            font-weight: 700; cursor: pointer; transition: background .15s;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-execute:hover:not(:disabled) { background: #2f855a; }
        .btn-execute:disabled { opacity: .5; cursor: default; }

        .btn-cancel {
            background: transparent; border: 1.5px solid #cbd5e0;
            color: #4a5568; padding: 10px 20px; border-radius: 8px;
            font-size: .9rem; cursor: pointer;
        }
        .btn-cancel:hover { background: #f7fafc; }

        .confirm-box {
            background: #fffbeb; border: 2px solid #d69e2e;
            border-radius: 10px; padding: 16px 20px; margin: 16px 0;
        }
        .confirm-box label {
            display: flex; align-items: center; gap: 10px;
            font-weight: 600; color: #744210; cursor: pointer; font-size: .9rem;
        }
        .confirm-box input[type=checkbox] { width: 16px; height: 16px; }

        .success-banner {
            background: #f0fff4; border: 2px solid #48bb78;
            border-radius: 10px; padding: 16px 20px; margin-bottom: 16px;
        }
        .success-banner h4 { color: #276749; margin: 0 0 4px; }
        .success-banner p  { color: #2f855a; margin: 0; font-size: .88rem; }

        .error-banner {
            background: #fff5f5; border: 2px solid #e53e3e;
            border-radius: 10px; padding: 16px 20px; margin-bottom: 16px;
        }
        .error-banner p { color: #c53030; margin: 0; font-weight: 600; }

        .stat-row { display: flex; gap: 16px; flex-wrap: wrap; margin: 12px 0; }
        .stat-box {
            flex: 1; min-width: 130px;
            background: #f7fafc; border: 1.5px solid #e2e8f0;
            border-radius: 10px; padding: 14px 16px; text-align: center;
        }
        .stat-box .val { font-size: 1.6rem; font-weight: 800; color: #4299e1; }
        .stat-box .lbl { font-size: .75rem; color: #718096; font-weight: 600; text-transform: uppercase; }

        .old-price { color: #a0aec0; text-decoration: line-through; }
        .new-price { color: #38a169; font-weight: 600; }

        .spinner { display: inline-block; width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,.3); border-top-color: #fff;
            border-radius: 50%; animation: spin .6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        [v-cloak] { display: none; }
    </style>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body>
<?php if (file_exists(__DIR__ . '/admin_header.php')) require_once __DIR__ . '/admin_header.php'; ?>

<div class="seed-wrap" x-data="seedApp()" x-cloak>

    <h2 style="margin:0 0 4px;font-size:1.35rem;color:#2d3748;">
        <i class="fas fa-database" style="color:#4299e1;"></i> Seed & Reprice Transactions
    </h2>
    <p style="margin:0 0 24px;color:#718096;font-size:.9rem;">
        Generate realistic historical demo data from 2021 to 2026. Repricing is optional and only changes peso amounts, not order counts.
    </p>

    <!-- Tabs -->
    <div class="part-tabs">
        <button class="tab-btn" :class="{ active: tab === 2 }" @click="switchTab(2)">
            <i class="fas fa-chart-line"></i> Add Historical Data
        </button>
        <button class="tab-btn" :class="{ active: tab === 1 }" @click="switchTab(1)">
            <i class="fas fa-tags"></i> Optional — Reprice Existing Orders
        </button>
        <button class="tab-btn" :class="{ active: tab === 3 }" @click="switchTab(3)">
            <i class="fas fa-wrench"></i> Repair Deleted Product Links
        </button>
    </div>

    <!-- ───────────────────────── PART 1 ───────────────────────── -->
    <div x-show="tab === 1">
        <div class="part-header">
            <div class="part-icon">💰</div>
            <div>
                <h3>Update Pricing on Existing Orders</h3>
                <p>Assigns realistic unit prices (₱260–₱5,100) to existing order items and recalculates totals. This does <strong>not</strong> add orders, so dashboard order counts will stay the same.</p>
            </div>
        </div>

        <!-- Idle -->
        <template x-if="p1.state === 'idle'">
            <div>
                <button class="btn-preview" @click="part1Preview()" :disabled="p1.loading">
                    <template x-if="p1.loading"><span class="spinner"></span></template>
                    <template x-if="!p1.loading"><i class="fas fa-eye"></i></template>
                    <span x-text="p1.loading ? ' Loading preview…' : ' Preview Changes'"></span>
                </button>
                <div x-show="p1.error" x-text="p1.error" style="color:#c53030;margin-top:12px;font-weight:600;"></div>
            </div>
        </template>

        <!-- Preview shown -->
        <template x-if="p1.state === 'preview'">
            <div>
                <div class="stat-row">
                    <div class="stat-box">
                        <div class="val" x-text="p1.data.order_count.toLocaleString()"></div>
                        <div class="lbl">Orders</div>
                    </div>
                    <div class="stat-box">
                        <div class="val" x-text="p1.data.item_count.toLocaleString()"></div>
                        <div class="lbl">Order Items</div>
                    </div>
                    <div class="stat-box">
                        <div class="val" x-text="p1.data.job_count.toLocaleString()"></div>
                        <div class="lbl">Job Orders</div>
                    </div>
                </div>

                <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;color:#718096;margin:16px 0 6px;">
                    Sample — Before / After (randomized per execution)
                </div>
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Qty</th>
                            <th>Old Unit</th>
                            <th>New Unit</th>
                            <th>New Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in p1.data.sample" :key="row.order_id + '-' + row.product">
                            <tr>
                                <td x-text="'#' + row.order_id"></td>
                                <td x-text="row.product"></td>
                                <td x-text="row.category"></td>
                                <td x-text="row.qty"></td>
                                <td><span class="old-price" x-text="'₱' + row.old_unit"></span></td>
                                <td><span class="new-price" x-text="'₱' + row.new_unit"></span></td>
                                <td><strong x-text="'₱' + row.new_total"></strong></td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <div x-show="p1.data.order_count === 0" class="info-card" style="margin-top:8px;">
                    <i class="fas fa-circle-info"></i> No orders exist yet. Generate historical data first (Part 2), then run the repricer.
                </div>

                <div class="confirm-box" x-show="p1.data.order_count > 0">
                    <label>
                        <input type="checkbox" x-model="p1.confirmed">
                        I understand this will overwrite all existing order prices. This cannot be undone without a DB backup.
                    </label>
                </div>

                <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap;">
                    <button class="btn-cancel" @click="p1.state='idle'">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button class="btn-preview" @click="part1Preview()">
                        <i class="fas fa-rotate"></i> Regenerate Preview
                    </button>
                    <button class="btn-execute" @click="part1Execute()"
                            :disabled="!p1.confirmed || p1.loading || p1.data.order_count === 0">
                        <template x-if="p1.loading"><span class="spinner"></span></template>
                        <template x-if="!p1.loading"><i class="fas fa-check"></i></template>
                        <span x-text="p1.loading ? 'Updating prices…' : 'Apply Pricing Update'"></span>
                    </button>
                </div>
                <div x-show="p1.error" x-text="p1.error" style="color:#c53030;margin-top:12px;font-weight:600;"></div>
            </div>
        </template>

        <!-- Done -->
        <template x-if="p1.state === 'done'">
            <div>
                <div class="success-banner" x-show="p1.result && p1.result.ok">
                    <h4><i class="fas fa-circle-check"></i> Pricing updated successfully</h4>
                    <p>
                        <strong x-text="p1.result ? p1.result.updated_orders : 0"></strong> orders and
                        <strong x-text="p1.result ? p1.result.updated_items : 0"></strong> line items repriced with realistic values.
                        <strong x-text="p1.result ? p1.result.updated_jobs : 0"></strong> customization/job order amounts were normalized too.
                        Order counts did not change because this step only updates prices.
                    </p>
                </div>
                <div class="error-banner" x-show="p1.result && !p1.result.ok">
                    <p x-text="p1.result ? p1.result.error : ''"></p>
                </div>
                <button class="btn-cancel" @click="p1.state='idle'; p1.confirmed=false; p1.result=null">
                    <i class="fas fa-rotate-left"></i> Run Again
                </button>
                <button class="btn-execute" style="margin-left:10px;" @click="tab=2; p2.state='idle'">
                    <i class="fas fa-chart-line"></i> Add Historical Data Now
                </button>
            </div>
        </template>
    </div>

    <!-- ───────────────────────── PART 2 ───────────────────────── -->
    <div x-show="tab === 2">
        <div class="part-header">
            <div class="part-icon">📈</div>
            <div>
                <h3>Add Historical Transaction Data (2021–2026)</h3>
                <p>Inserts realistic orders, order items, customizations, service orders, and status history distributed across 6 years. This is the step that increases the Orders Management totals.</p>
            </div>
        </div>

        <!-- Idle -->
        <template x-if="p2.state === 'idle'">
            <div>
                <button class="btn-preview" @click="part2Preview()" :disabled="p2.loading">
                    <template x-if="p2.loading"><span class="spinner"></span></template>
                    <template x-if="!p2.loading"><i class="fas fa-eye"></i></template>
                    <span x-text="p2.loading ? ' Loading preview…' : ' Preview Generation Plan'"></span>
                </button>
                <div x-show="p2.error" x-text="p2.error" style="color:#c53030;margin-top:12px;font-weight:600;"></div>
            </div>
        </template>

        <!-- Preview -->
        <template x-if="p2.state === 'preview'">
            <div>
                <div class="info-card">
                    <i class="fas fa-circle-info"></i>
                    Using <strong x-text="p2.data.product_count"></strong> active products,
                    <strong x-text="p2.data.customer_count"></strong> customers,
                    <strong x-text="p2.data.branch_count"></strong> branches.
                    Approx. <strong x-text="p2.data.grand_total.toLocaleString()"></strong>–<strong x-text="(p2.data.grand_total * 2).toLocaleString()"></strong> order items will be created, with displayed order amounts capped around ₱5,100.
                </div>

                <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;color:#718096;margin:0 0 8px;">
                    Orders per year
                </div>
                <div class="year-grid">
                    <template x-for="(count, year) in p2.data.year_totals" :key="year">
                        <div class="year-card">
                            <div class="yr" x-text="year"></div>
                            <div class="cnt" x-text="count"></div>
                            <div class="sub">orders</div>
                        </div>
                    </template>
                    <div class="year-card" style="border-color:#4299e1;background:#ebf8ff;">
                        <div class="yr" style="color:#4299e1;">Total</div>
                        <div class="cnt" x-text="p2.data.grand_total.toLocaleString()"></div>
                        <div class="sub">orders</div>
                    </div>
                </div>

                <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;color:#718096;margin:16px 0 6px;">
                    Sample products &amp; price ranges
                </div>
                <table class="preview-table">
                    <thead>
                        <tr><th>Product</th><th>Category</th><th>Price Range</th></tr>
                    </thead>
                    <tbody>
                        <template x-for="item in p2.data.sample_items" :key="item.name">
                            <tr>
                                <td x-text="item.name"></td>
                                <td x-text="item.category"></td>
                                <td><span class="new-price" x-text="'₱' + item.price_min.toLocaleString() + ' – ₱' + item.price_max.toLocaleString()"></span></td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <div class="confirm-box">
                    <label>
                        <input type="checkbox" x-model="p2.confirmed">
                        I want to insert <strong x-text="p2.data.grand_total.toLocaleString()"></strong> historical orders into this database. Existing data is NOT deleted.
                    </label>
                </div>

                <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap;">
                    <button class="btn-cancel" @click="p2.state='idle'">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button class="btn-execute" @click="part2Execute()"
                            :disabled="!p2.confirmed || p2.loading">
                        <template x-if="p2.loading"><span class="spinner"></span></template>
                        <template x-if="!p2.loading"><i class="fas fa-database"></i></template>
                        <span x-text="p2.loading ? 'Generating… (may take 30–60s)' : 'Generate Historical Data'"></span>
                    </button>
                </div>
                <div x-show="p2.error" x-text="p2.error" style="color:#c53030;margin-top:12px;font-weight:600;"></div>
            </div>
        </template>

        <!-- Executing (waiting) -->
        <template x-if="p2.state === 'executing'">
            <div style="text-align:center;padding:60px 0;">
                <div style="font-size:3rem;"><i class="fas fa-gear fa-spin" style="color:#4299e1;"></i></div>
                <div style="font-size:1.1rem;color:#4a5568;font-weight:600;margin-top:16px;">Generating historical data…</div>
                <div style="color:#a0aec0;font-size:.85rem;margin-top:8px;">This may take 30–90 seconds. Do not close this page.</div>
            </div>
        </template>

        <!-- Done -->
        <template x-if="p2.state === 'done'">
            <div>
                <div class="success-banner" x-show="p2.result && p2.result.ok">
                    <h4><i class="fas fa-circle-check"></i> Historical data generated successfully!</h4>
                    <p>
                        <strong x-text="p2.result ? p2.result.inserted_orders.toLocaleString() : 0"></strong> orders ·
                        <strong x-text="p2.result ? p2.result.inserted_items.toLocaleString() : 0"></strong> order items ·
                        <strong x-text="p2.result ? p2.result.inserted_hist.toLocaleString() : 0"></strong> status history entries ·
                        <strong x-text="p2.result ? p2.result.inserted_custom.toLocaleString() : 0"></strong> customizations ·
                        <strong x-text="p2.result ? p2.result.inserted_svc.toLocaleString() : 0"></strong> service orders ·
                        <strong x-text="p2.result ? p2.result.inserted_jobs.toLocaleString() : 0"></strong> job orders
                    </p>
                </div>
                <div class="error-banner" x-show="p2.result && !p2.result.ok">
                    <p x-text="'Error: ' + (p2.result ? p2.result.error : 'Unknown error')"></p>
                </div>

                <!-- Year breakdown -->
                <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;color:#718096;margin:16px 0 8px;" x-show="p2.result && p2.result.ok">
                    Orders inserted per year
                </div>
                <div class="year-grid" x-show="p2.result && p2.result.ok">
                    <template x-for="(count, year) in (p2.result ? p2.result.year_counts : {})" :key="year">
                        <div class="year-card" style="border-color:#48bb78;">
                            <div class="yr" x-text="year"></div>
                            <div class="cnt" style="color:#38a169;" x-text="count"></div>
                            <div class="sub">inserted</div>
                        </div>
                    </template>
                </div>

                <!-- Verification checklist -->
                <div style="margin-top:20px;" x-show="p2.result && p2.result.ok">
                    <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;color:#718096;margin-bottom:8px;">
                        <i class="fas fa-clipboard-check"></i> Verification Checklist
                    </div>
                    <ul style="list-style:none;padding:0;margin:0;">
                        <li style="padding:7px 12px;border-radius:8px;background:#f0fff4;color:#276749;margin-bottom:4px;font-size:.88rem;">
                            <i class="fas fa-check-circle"></i> Orders distributed across 2021–2026 (gradual growth)
                        </li>
                        <li style="padding:7px 12px;border-radius:8px;background:#f0fff4;color:#276749;margin-bottom:4px;font-size:.88rem;">
                            <i class="fas fa-check-circle"></i> All order_items link to valid product IDs
                        </li>
                        <li style="padding:7px 12px;border-radius:8px;background:#f0fff4;color:#276749;margin-bottom:4px;font-size:.88rem;">
                            <i class="fas fa-check-circle"></i> All orders link to existing customers and branches
                        </li>
                        <li style="padding:7px 12px;border-radius:8px;background:#f0fff4;color:#276749;margin-bottom:4px;font-size:.88rem;">
                            <i class="fas fa-check-circle"></i> Customizations linked to valid order_id + order_item_id
                        </li>
                        <li style="padding:7px 12px;border-radius:8px;background:#f0fff4;color:#276749;margin-bottom:4px;font-size:.88rem;">
                            <i class="fas fa-check-circle"></i> Status history follows valid progression (Pending → ... → Completed)
                        </li>
                        <li style="padding:7px 12px;border-radius:8px;background:#f0fff4;color:#276749;margin-bottom:4px;font-size:.88rem;">
                            <i class="fas fa-check-circle"></i> Price totals = unit_price × quantity (no mismatched values)
                        </li>
                        <li style="padding:7px 12px;border-radius:8px;background:#f0fff4;color:#276749;margin-bottom:4px;font-size:.88rem;">
                            <i class="fas fa-check-circle"></i> No users, products, services, or settings were modified
                        </li>
                    </ul>
                </div>

                <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;">
                    <a href="<?= htmlspecialchars($base_path) ?>/admin/orders_management.php"
                       style="text-decoration:none;" class="btn-preview">
                        <i class="fas fa-box"></i> View Orders
                    </a>
                    <a href="<?= htmlspecialchars($base_path) ?>/admin/customizations.php"
                       style="text-decoration:none;" class="btn-preview" >
                        <i class="fas fa-palette"></i> View Customizations
                    </a>
                    <button class="btn-cancel" @click="p2.state='idle'; p2.confirmed=false; p2.result=null; tab=1">
                        Run Part 1 (Reprice)
                    </button>
                </div>
            </div>
        </template>
    </div>

    <!-- ───────────────────────── PART 3 ───────────────────────── -->
    <div x-show="tab === 3">
        <div class="part-header">
            <div class="part-icon">🔧</div>
            <div>
                <h3>Repair Deleted Product Links</h3>
                <p>Finds order items pointing to <strong>SYS-DELETED-PRODUCT</strong> placeholders and safely relinks them to real active products. Existing data is not deleted.</p>
            </div>
        </div>

        <template x-if="repair.state === 'idle'">
            <div>
                <button class="btn-preview" @click="repairPreview()" :disabled="repair.loading">
                    <template x-if="repair.loading"><span class="spinner"></span></template>
                    <template x-if="!repair.loading"><i class="fas fa-eye"></i></template>
                    <span x-text="repair.loading ? ' Checking…' : ' Preview Deleted Product Links'"></span>
                </button>
                <div x-show="repair.error" x-text="repair.error" style="color:#c53030;margin-top:12px;font-weight:600;"></div>
            </div>
        </template>

        <template x-if="repair.state === 'preview'">
            <div>
                <div class="stat-row">
                    <div class="stat-box">
                        <div class="val" x-text="repair.data.placeholder_count.toLocaleString()"></div>
                        <div class="lbl">Placeholders</div>
                    </div>
                    <div class="stat-box">
                        <div class="val" x-text="repair.data.linked_items.toLocaleString()"></div>
                        <div class="lbl">Linked Items</div>
                    </div>
                    <div class="stat-box">
                        <div class="val" x-text="repair.data.replacement_count.toLocaleString()"></div>
                        <div class="lbl">Active Products</div>
                    </div>
                </div>

                <div class="info-card" x-show="repair.data.linked_items > 0">
                    <i class="fas fa-circle-info"></i>
                    The repair first tries exact SKU matching. If no exact product exists, it guesses by item/category keywords, then uses a random active product as a safe fallback.
                </div>
                <div class="info-card" x-show="repair.data.linked_items === 0">
                    <i class="fas fa-check-circle"></i>
                    No order items are linked to SYS-DELETED-PRODUCT placeholders.
                </div>

                <table class="preview-table" x-show="repair.data.samples.length > 0">
                    <thead>
                        <tr><th>Order Item</th><th>Order</th><th>Stored SKU</th><th>Placeholder</th><th>Qty</th></tr>
                    </thead>
                    <tbody>
                        <template x-for="row in repair.data.samples" :key="row.order_item_id">
                            <tr>
                                <td x-text="'#' + row.order_item_id"></td>
                                <td x-text="'#' + row.order_id"></td>
                                <td x-text="row.sku || '—'"></td>
                                <td x-text="row.placeholder_sku || row.placeholder_name"></td>
                                <td x-text="row.quantity"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <div class="confirm-box" x-show="repair.data.linked_items > 0">
                    <label>
                        <input type="checkbox" x-model="repair.confirmed">
                        I want to relink these placeholder order items to active products and recalculate order totals.
                    </label>
                </div>

                <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap;">
                    <button class="btn-cancel" @click="repair.state='idle'">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button class="btn-execute" @click="repairExecute()"
                            :disabled="!repair.confirmed || repair.loading || repair.data.linked_items === 0">
                        <template x-if="repair.loading"><span class="spinner"></span></template>
                        <template x-if="!repair.loading"><i class="fas fa-wrench"></i></template>
                        <span x-text="repair.loading ? 'Repairing…' : 'Repair Deleted Product Links'"></span>
                    </button>
                </div>
                <div x-show="repair.error" x-text="repair.error" style="color:#c53030;margin-top:12px;font-weight:600;"></div>
            </div>
        </template>

        <template x-if="repair.state === 'done'">
            <div>
                <div class="success-banner" x-show="repair.result && repair.result.ok">
                    <h4><i class="fas fa-circle-check"></i> Deleted product links repaired</h4>
                    <p>
                        <strong x-text="repair.result ? repair.result.repaired.toLocaleString() : 0"></strong>
                        order items were relinked to real active products and order totals were recalculated.
                    </p>
                </div>
                <div class="error-banner" x-show="repair.result && !repair.result.ok">
                    <p x-text="repair.result ? repair.result.error : ''"></p>
                </div>
                <button class="btn-cancel" @click="repair.state='idle'; repair.confirmed=false; repair.result=null">
                    <i class="fas fa-rotate-left"></i> Check Again
                </button>
            </div>
        </template>

        <div style="margin-top:28px;padding-top:20px;border-top:1px solid #e2e8f0;">
            <div class="part-header">
                <div class="part-icon">#️⃣</div>
                <div>
                    <h3>Normalize Job Order Codes</h3>
                    <p>Converts standalone <strong>JO-00000</strong> demo records into linked order-code records, so they follow the same SKU/order-code style used by the system.</p>
                </div>
            </div>

            <template x-if="codes.state === 'idle'">
                <div>
                    <button class="btn-preview" @click="codesPreview()" :disabled="codes.loading">
                        <template x-if="codes.loading"><span class="spinner"></span></template>
                        <template x-if="!codes.loading"><i class="fas fa-eye"></i></template>
                        <span x-text="codes.loading ? ' Checking…' : ' Preview JO Codes To Normalize'"></span>
                    </button>
                    <div x-show="codes.error" x-text="codes.error" style="color:#c53030;margin-top:12px;font-weight:600;"></div>
                </div>
            </template>

            <template x-if="codes.state === 'preview'">
                <div>
                    <div class="stat-row">
                        <div class="stat-box"><div class="val" x-text="codes.data.job_count.toLocaleString()"></div><div class="lbl">Standalone JO Codes</div></div>
                        <div class="stat-box"><div class="val" x-text="codes.data.active_product_count.toLocaleString()"></div><div class="lbl">Active Products</div></div>
                    </div>

                    <table class="preview-table" x-show="codes.data.samples.length > 0">
                        <thead><tr><th>Current Code</th><th>Customer</th><th>Service</th><th>Status</th><th>Amount</th></tr></thead>
                        <tbody>
                            <template x-for="row in codes.data.samples" :key="row.id">
                                <tr>
                                    <td x-text="'JO-' + String(row.id).padStart(5, '0')"></td>
                                    <td x-text="row.customer_name || 'Walk-in Customer'"></td>
                                    <td x-text="row.service_type"></td>
                                    <td x-text="row.status"></td>
                                    <td x-text="'₱' + parseFloat(row.estimated_total || 0).toFixed(2)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <div class="info-card" x-show="codes.data.job_count === 0">
                        <i class="fas fa-check-circle"></i> No standalone JO codes found.
                    </div>

                    <div class="confirm-box" x-show="codes.data.job_count > 0">
                        <label>
                            <input type="checkbox" x-model="codes.confirmed">
                            I want to link these standalone job orders to generated order records so their displayed codes match the system order-code style.
                        </label>
                    </div>

                    <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap;">
                        <button class="btn-cancel" @click="codes.state='idle'">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button class="btn-execute" @click="codesExecute()" :disabled="!codes.confirmed || codes.loading || codes.data.job_count === 0">
                            <template x-if="codes.loading"><span class="spinner"></span></template>
                            <template x-if="!codes.loading"><i class="fas fa-link"></i></template>
                            <span x-text="codes.loading ? 'Normalizing…' : 'Normalize Job Order Codes'"></span>
                        </button>
                    </div>
                    <div x-show="codes.error" x-text="codes.error" style="color:#c53030;margin-top:12px;font-weight:600;"></div>
                </div>
            </template>

            <template x-if="codes.state === 'done'">
                <div>
                    <div class="success-banner" x-show="codes.result && codes.result.ok">
                        <h4><i class="fas fa-circle-check"></i> Job order codes normalized</h4>
                        <p><strong x-text="codes.result ? codes.result.normalized.toLocaleString() : 0"></strong> standalone job orders were linked to proper order codes.</p>
                    </div>
                    <div class="error-banner" x-show="codes.result && !codes.result.ok">
                        <p x-text="codes.result ? codes.result.error : ''"></p>
                    </div>
                    <button class="btn-cancel" @click="codes.state='idle'; codes.confirmed=false; codes.result=null">
                        <i class="fas fa-rotate-left"></i> Check Again
                    </button>
                </div>
            </template>
        </div>

        <div style="margin-top:28px;padding-top:20px;border-top:1px solid #e2e8f0;">
            <div class="part-header" style="border-color:#fed7d7;background:#fff5f5;">
                <div class="part-icon">🗑️</div>
                <div>
                    <h3 style="color:#c53030;">Delete Transactions With Deleted Items</h3>
                    <p>If you prefer not to repair them, this deletes only the full transactions that contain SYS-DELETED-PRODUCT items. A backup table is created first.</p>
                </div>
            </div>

            <template x-if="del.state === 'idle'">
                <div>
                    <button class="btn-preview" @click="deletePreview()" :disabled="del.loading">
                        <template x-if="del.loading"><span class="spinner"></span></template>
                        <template x-if="!del.loading"><i class="fas fa-eye"></i></template>
                        <span x-text="del.loading ? ' Checking…' : ' Preview Transactions To Delete'"></span>
                    </button>
                    <div x-show="del.error" x-text="del.error" style="color:#c53030;margin-top:12px;font-weight:600;"></div>
                </div>
            </template>

            <template x-if="del.state === 'preview'">
                <div>
                    <div class="stat-row">
                        <div class="stat-box"><div class="val" x-text="del.data.order_count.toLocaleString()"></div><div class="lbl">Orders</div></div>
                        <div class="stat-box"><div class="val" x-text="del.data.order_item_count.toLocaleString()"></div><div class="lbl">Order Items</div></div>
                        <div class="stat-box"><div class="val" x-text="del.data.job_order_count.toLocaleString()"></div><div class="lbl">Job Orders</div></div>
                        <div class="stat-box"><div class="val" x-text="del.data.pos_transaction_count.toLocaleString()"></div><div class="lbl">POS Txns</div></div>
                    </div>

                    <table class="preview-table" x-show="del.data.samples.length > 0">
                        <thead>
                            <tr><th>Order</th><th>Customer</th><th>Date</th><th>Status</th><th>Amount</th></tr>
                        </thead>
                        <tbody>
                            <template x-for="row in del.data.samples" :key="row.order_id">
                                <tr>
                                    <td x-text="'#' + row.order_id"></td>
                                    <td x-text="row.customer_name || 'Walk-in Customer'"></td>
                                    <td x-text="row.order_date"></td>
                                    <td x-text="row.status"></td>
                                    <td x-text="'₱' + parseFloat(row.total_amount || 0).toFixed(2)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <div class="info-card" x-show="del.data.order_count === 0 && del.data.pos_transaction_count === 0">
                        <i class="fas fa-check-circle"></i> No transactions with deleted products were found.
                    </div>

                    <div class="confirm-box" x-show="del.data.order_count > 0 || del.data.pos_transaction_count > 0" style="border-color:#e53e3e;background:#fff5f5;">
                        <label style="color:#c53030;">
                            <input type="checkbox" x-model="del.confirmed">
                            I understand this will delete the affected transactions, not the products/services/users.
                        </label>
                    </div>

                    <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap;">
                        <button class="btn-cancel" @click="del.state='idle'">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button class="btn-execute" style="background:#e53e3e;" @click="deleteExecute()"
                                :disabled="!del.confirmed || del.loading || (del.data.order_count === 0 && del.data.pos_transaction_count === 0)">
                            <template x-if="del.loading"><span class="spinner"></span></template>
                            <template x-if="!del.loading"><i class="fas fa-trash"></i></template>
                            <span x-text="del.loading ? 'Deleting…' : 'Delete Affected Transactions'"></span>
                        </button>
                    </div>
                    <div x-show="del.error" x-text="del.error" style="color:#c53030;margin-top:12px;font-weight:600;"></div>
                </div>
            </template>

            <template x-if="del.state === 'done'">
                <div>
                    <div class="success-banner" x-show="del.result && del.result.ok">
                        <h4><i class="fas fa-circle-check"></i> Affected transactions deleted</h4>
                        <p>
                            Deleted <strong x-text="del.result ? del.result.deleted_orders.toLocaleString() : 0"></strong> orders and
                            <strong x-text="del.result ? del.result.deleted_order_items.toLocaleString() : 0"></strong> order items linked to deleted products.
                        </p>
                    </div>
                    <div class="error-banner" x-show="del.result && !del.result.ok">
                        <p x-text="del.result ? del.result.error : ''"></p>
                    </div>
                    <button class="btn-cancel" @click="del.state='idle'; del.confirmed=false; del.result=null">
                        <i class="fas fa-rotate-left"></i> Check Again
                    </button>
                </div>
            </template>
        </div>
    </div>

</div><!-- /seed-wrap -->

<script>
function seedApp() {
    return {
        tab: 2,
        p1: { state: 'idle', loading: false, data: null, confirmed: false, result: null, error: '' },
        p2: { state: 'idle', loading: false, data: null, confirmed: false, result: null, error: '' },
        repair: { state: 'idle', loading: false, data: null, confirmed: false, result: null, error: '' },
        del: { state: 'idle', loading: false, data: null, confirmed: false, result: null, error: '' },
        codes: { state: 'idle', loading: false, data: null, confirmed: false, result: null, error: '' },

        switchTab(t) {
            this.tab = t;
        },

        async part1Preview() {
            this.p1.loading = true; this.p1.error = '';
            try {
                const fd = new FormData();
                fd.append('csrf_token', <?= json_encode($csrf_token) ?>);
                fd.append('action', 'part1_preview');
                const res  = await fetch(location.href, { method: 'POST', body: fd });
                const json = await res.json();
                if (!json.ok) throw new Error(json.error || 'Preview failed.');
                this.p1.data  = json.data;
                this.p1.state = 'preview';
            } catch (e) {
                this.p1.error = e.message;
            } finally {
                this.p1.loading = false;
            }
        },

        async part1Execute() {
            if (!this.p1.confirmed) return;
            this.p1.loading = true; this.p1.error = '';
            try {
                const fd = new FormData();
                fd.append('csrf_token', <?= json_encode($csrf_token) ?>);
                fd.append('action', 'part1_execute');
                fd.append('confirm', '1');
                const res  = await fetch(location.href, { method: 'POST', body: fd });
                const json = await res.json();
                this.p1.result = json;
                this.p1.state  = 'done';
            } catch (e) {
                this.p1.error = e.message;
            } finally {
                this.p1.loading = false;
            }
        },

        async part2Preview() {
            this.p2.loading = true; this.p2.error = '';
            try {
                const fd = new FormData();
                fd.append('csrf_token', <?= json_encode($csrf_token) ?>);
                fd.append('action', 'part2_preview');
                const res  = await fetch(location.href, { method: 'POST', body: fd });
                const json = await res.json();
                if (!json.ok) throw new Error(json.error || 'Preview failed.');
                this.p2.data  = json.data;
                this.p2.state = 'preview';
            } catch (e) {
                this.p2.error = e.message;
            } finally {
                this.p2.loading = false;
            }
        },

        async part2Execute() {
            if (!this.p2.confirmed) return;
            this.p2.loading = true; this.p2.error = '';
            this.p2.state   = 'executing';
            try {
                const fd = new FormData();
                fd.append('csrf_token', <?= json_encode($csrf_token) ?>);
                fd.append('action', 'part2_execute');
                fd.append('confirm', '1');
                const res  = await fetch(location.href, {
                    method: 'POST', body: fd,
                });
                const json = await res.json();
                this.p2.result = json;
                this.p2.state  = 'done';
            } catch (e) {
                this.p2.error  = e.message;
                this.p2.result = { ok: false, error: e.message };
                this.p2.state  = 'done';
            } finally {
                this.p2.loading = false;
            }
        },

        async repairPreview() {
            this.repair.loading = true; this.repair.error = '';
            try {
                const fd = new FormData();
                fd.append('csrf_token', <?= json_encode($csrf_token) ?>);
                fd.append('action', 'repair_deleted_preview');
                const res  = await fetch(location.href, { method: 'POST', body: fd });
                const json = await res.json();
                if (!json.ok) throw new Error(json.error || 'Preview failed.');
                this.repair.data = json.data;
                this.repair.state = 'preview';
            } catch (e) {
                this.repair.error = e.message;
            } finally {
                this.repair.loading = false;
            }
        },

        async repairExecute() {
            if (!this.repair.confirmed) return;
            this.repair.loading = true; this.repair.error = '';
            try {
                const fd = new FormData();
                fd.append('csrf_token', <?= json_encode($csrf_token) ?>);
                fd.append('action', 'repair_deleted_execute');
                fd.append('confirm', '1');
                const res  = await fetch(location.href, { method: 'POST', body: fd });
                const json = await res.json();
                this.repair.result = json;
                this.repair.state = 'done';
            } catch (e) {
                this.repair.error = e.message;
                this.repair.result = { ok: false, error: e.message };
                this.repair.state = 'done';
            } finally {
                this.repair.loading = false;
            }
        },

        async deletePreview() {
            this.del.loading = true; this.del.error = '';
            try {
                const fd = new FormData();
                fd.append('csrf_token', <?= json_encode($csrf_token) ?>);
                fd.append('action', 'delete_deleted_txn_preview');
                const res  = await fetch(location.href, { method: 'POST', body: fd });
                const json = await res.json();
                if (!json.ok) throw new Error(json.error || 'Preview failed.');
                this.del.data = json.data;
                this.del.state = 'preview';
            } catch (e) {
                this.del.error = e.message;
            } finally {
                this.del.loading = false;
            }
        },

        async deleteExecute() {
            if (!this.del.confirmed) return;
            this.del.loading = true; this.del.error = '';
            try {
                const fd = new FormData();
                fd.append('csrf_token', <?= json_encode($csrf_token) ?>);
                fd.append('action', 'delete_deleted_txn_execute');
                fd.append('confirm', '1');
                const res  = await fetch(location.href, { method: 'POST', body: fd });
                const json = await res.json();
                this.del.result = json;
                this.del.state = 'done';
            } catch (e) {
                this.del.error = e.message;
                this.del.result = { ok: false, error: e.message };
                this.del.state = 'done';
            } finally {
                this.del.loading = false;
            }
        },

        async codesPreview() {
            this.codes.loading = true; this.codes.error = '';
            try {
                const fd = new FormData();
                fd.append('csrf_token', <?= json_encode($csrf_token) ?>);
                fd.append('action', 'normalize_job_codes_preview');
                const res  = await fetch(location.href, { method: 'POST', body: fd });
                const json = await res.json();
                if (!json.ok) throw new Error(json.error || 'Preview failed.');
                this.codes.data = json.data;
                this.codes.state = 'preview';
            } catch (e) {
                this.codes.error = e.message;
            } finally {
                this.codes.loading = false;
            }
        },

        async codesExecute() {
            if (!this.codes.confirmed) return;
            this.codes.loading = true; this.codes.error = '';
            try {
                const fd = new FormData();
                fd.append('csrf_token', <?= json_encode($csrf_token) ?>);
                fd.append('action', 'normalize_job_codes_execute');
                fd.append('confirm', '1');
                const res  = await fetch(location.href, { method: 'POST', body: fd });
                const json = await res.json();
                this.codes.result = json;
                this.codes.state = 'done';
            } catch (e) {
                this.codes.error = e.message;
                this.codes.result = { ok: false, error: e.message };
                this.codes.state = 'done';
            } finally {
                this.codes.loading = false;
            }
        },
    };
}
</script>

<?php if (file_exists(__DIR__ . '/admin_footer.php')) require_once __DIR__ . '/admin_footer.php'; ?>
</body>
</html>
