<?php
/**
 * PrintFlow — Historical Data Seeder v2
 * =======================================
 * Populates the database with realistic historical transaction data
 * from January 2024 to the present for accurate analytics.
 *
 * What this script does:
 *  1. Inserts/verifies 10 printing service products
 *  2. Adds customer `address` column (concatenated from city/barangay parts)
 *  3. Creates order_items for the ~2124 orders that have none
 *  4. Generates NEW orders if total is below 800 (with full items)
 *  5. Updates customer transaction counts
 *
 * Usage:
 *   php database/seed_orders_data.php
 *   php database/seed_orders_data.php --dry-run
 *   php database/seed_orders_data.php --force-new-orders   (add 300 extra orders)
 *
 * Safe to run multiple times (idempotent).
 */

set_time_limit(600);
ini_set('memory_limit', '256M');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$DRY_RUN        = in_array('--dry-run',          $argv ?? []);
$FORCE_NEW      = in_array('--force-new-orders',  $argv ?? []);

if ($DRY_RUN) echo "[DRY RUN — no writes]\n\n";

// ── Connect ───────────────────────────────────────────────────────────────────
$conn = new mysqli('localhost', 'root', '1234', 'printflow_1');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error . "\n");
$conn->set_charset('utf8mb4');
$conn->query("SET SESSION sql_mode = ''");          // suppress strict enum errors
echo "Connected to printflow_1\n\n";

// ── Helpers ───────────────────────────────────────────────────────────────────
function rf($min, $max) { return $min + mt_rand() / mt_getrandmax() * ($max - $min); }
function wpick(array $pool, string $wk = 'weight') {
    $tot = array_sum(array_column($pool, $wk));
    $r = mt_rand(0, max(1, $tot - 1));
    $c = 0;
    foreach ($pool as $item) { $c += $item[$wk]; if ($r < $c) return $item; }
    return end($pool);
}

// ═══════════════════════════════════════════════════════════════════════════════
// STEP 1 — Ensure 10 Printing Service Products Exist
// ═══════════════════════════════════════════════════════════════════════════════
echo "=== STEP 1: Printing Service Products ===\n";

/**
 * These names must match job_orders.service_type enum EXACTLY
 * so that the service_type→product mapping works.
 */
$service_products = [
    [
        'name'     => 'Tarpaulin Printing',
        'sku'      => 'SVC-TARP',
        'category' => 'Wide Format',
        'desc'     => 'Custom tarpaulin printing for events, promotions, and signage.',
        'price'    => 1200.00,
        'price_min'=> 500,   'price_max' => 3500,
        'qty_min'  => 1,     'qty_max'   => 10,
    ],
    [
        'name'     => 'T-shirt Printing',
        'sku'      => 'SVC-TSHIRT',
        'category' => 'Garments',
        'desc'     => 'Custom T-shirt printing with sublimation or DTF transfer.',
        'price'    => 350.00,
        'price_min'=> 200,   'price_max' => 800,
        'qty_min'  => 1,     'qty_max'   => 50,
    ],
    [
        'name'     => 'Decals/Stickers (Print/Cut)',
        'sku'      => 'SVC-DECAL',
        'category' => 'Stickers',
        'desc'     => 'Vinyl decals and custom cut stickers.',
        'price'    => 500.00,
        'price_min'=> 150,   'price_max' => 1200,
        'qty_min'  => 5,     'qty_max'   => 100,
    ],
    [
        'name'     => 'Glass Stickers / Wall / Frosted Stickers',
        'sku'      => 'SVC-GLASS',
        'category' => 'Stickers',
        'desc'     => 'Glass stickers, wall graphics, and frosted window stickers.',
        'price'    => 800.00,
        'price_min'=> 200,   'price_max' => 1500,
        'qty_min'  => 1,     'qty_max'   => 20,
    ],
    [
        'name'     => 'Transparent Stickers',
        'sku'      => 'SVC-TRANSPARENT',
        'category' => 'Stickers',
        'desc'     => 'Clear transparent sticker printing.',
        'price'    => 300.00,
        'price_min'=> 150,   'price_max' => 900,
        'qty_min'  => 5,     'qty_max'   => 100,
    ],
    [
        'name'     => 'Layouts',
        'sku'      => 'SVC-LAYOUT',
        'category' => 'Design Services',
        'desc'     => 'Graphic design and layout services.',
        'price'    => 350.00,
        'price_min'=> 200,   'price_max' => 500,
        'qty_min'  => 1,     'qty_max'   => 5,
    ],
    [
        'name'     => 'Reflectorized (Subdivision Stickers/Signages)',
        'sku'      => 'SVC-REFLECTORIZED',
        'category' => 'Signage',
        'desc'     => 'Reflectorized stickers for subdivisions, roads, and safety signage.',
        'price'    => 1000.00,
        'price_min'=> 300,   'price_max' => 2000,
        'qty_min'  => 1,     'qty_max'   => 30,
    ],
    [
        'name'     => 'Stickers on Sintraboard',
        'sku'      => 'SVC-SINTRA-STICKER',
        'category' => 'Sintraboard',
        'desc'     => 'Sticker printing mounted on sintraboard panels.',
        'price'    => 600.00,
        'price_min'=> 300,   'price_max' => 1500,
        'qty_min'  => 1,     'qty_max'   => 20,
    ],
    [
        'name'     => 'Sintraboard Standees',
        'sku'      => 'SVC-STANDEE',
        'category' => 'Sintraboard',
        'desc'     => 'Custom sintraboard standees for events and promotions.',
        'price'    => 1200.00,
        'price_min'=> 500,   'price_max' => 2500,
        'qty_min'  => 1,     'qty_max'   => 10,
    ],
    [
        'name'     => 'Souvenirs',
        'sku'      => 'SVC-SOUVENIR',
        'category' => 'Souvenirs',
        'desc'     => 'Custom printed souvenirs: mugs, keychains, and more.',
        'price'    => 250.00,
        'price_min'=> 100,   'price_max' => 700,
        'qty_min'  => 5,     'qty_max'   => 100,
    ],
];

// Build service → product_id map
$svc_to_pid = [];

foreach ($service_products as $sp) {
    $name = $conn->real_escape_string($sp['name']);
    $sku  = $conn->real_escape_string($sp['sku']);

    $row = $conn->query("SELECT product_id FROM products WHERE name='$name' LIMIT 1")->fetch_assoc();
    if ($row) {
        $pid = (int)$row['product_id'];
        echo "  ✓ Exists: {$sp['name']} (id=$pid)\n";
        $svc_to_pid[$sp['name']] = $pid;
        continue;
    }

    if ($DRY_RUN) {
        echo "  [DRY] Would insert: {$sp['name']}\n";
        $svc_to_pid[$sp['name']] = 0;
        continue;
    }

    $desc  = $conn->real_escape_string($sp['desc']);
    $cat   = $conn->real_escape_string($sp['category']);
    $price = (float)$sp['price'];
    $now   = date('Y-m-d H:i:s');

    $conn->query("INSERT INTO products
        (sku, name, category, description, price, stock_quantity, status, created_at, updated_at)
        VALUES ('$sku','$name','$cat','$desc',$price, 9999, 'Activated', '$now','$now')");

    if ($conn->insert_id) {
        $pid = (int)$conn->insert_id;
        echo "  + Inserted: {$sp['name']} (id=$pid)\n";
        $svc_to_pid[$sp['name']] = $pid;
    } else {
        echo "  ⚠ Failed to insert {$sp['name']}: " . $conn->error . "\n";
    }
}

echo "  Product map ready: " . count($svc_to_pid) . " services\n\n";

// ═══════════════════════════════════════════════════════════════════════════════
// STEP 2 — Add `address` column to customers (for report location queries)
// ═══════════════════════════════════════════════════════════════════════════════
echo "=== STEP 2: Customer address column ===\n";

$col_exists = $conn->query(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA='printflow_1' AND TABLE_NAME='customers' AND COLUMN_NAME='address'"
)->num_rows > 0;

if (!$col_exists) {
    if (!$DRY_RUN) {
        $conn->query("ALTER TABLE customers ADD COLUMN `address` varchar(300) DEFAULT NULL AFTER postal_code");
        echo "  + Added column: customers.address\n";
    } else {
        echo "  [DRY] Would add customers.address column\n";
    }
} else {
    echo "  ✓ customers.address already exists\n";
}

// Populate address for all customers who have city set but address is NULL/empty
if (!$DRY_RUN) {
    $updated = $conn->query("
        UPDATE customers
        SET address = CONCAT_WS(', ',
            NULLIF(TRIM(street_address),''),
            NULLIF(TRIM(barangay),''),
            NULLIF(TRIM(city),''),
            NULLIF(TRIM(province),''),
            NULLIF(TRIM(postal_code),'')
        )
        WHERE city IS NOT NULL
          AND (address IS NULL OR TRIM(address) = '')
    ");
    echo "  ✓ Populated address for customers with city data. (rows: {$conn->affected_rows})\n";
}
echo "\n";

// ═══════════════════════════════════════════════════════════════════════════════
// STEP 3 — Create order_items for existing orders that have none
// ═══════════════════════════════════════════════════════════════════════════════
echo "=== STEP 3: Creating order_items for existing orders ===\n";

// Strategy A: Orders with linked job_orders (use service_type → product_id)
$linked_res = $conn->query("
    SELECT o.order_id,
           o.total_amount,
           jo.service_type,
           jo.quantity,
           jo.price_per_piece,
           jo.estimated_total
    FROM orders o
    JOIN job_orders jo ON jo.order_id = o.order_id
    WHERE NOT EXISTS (
        SELECT 1 FROM order_items oi WHERE oi.order_id = o.order_id
    )
    ORDER BY o.order_id
");

$items_created = 0;
$items_skipped = 0;

if (!$DRY_RUN) {
    $ins_item = $conn->prepare(
        "INSERT INTO order_items (order_id, product_id, quantity, unit_price)
         VALUES (?, ?, ?, ?)"
    );
}

while ($row = $linked_res->fetch_assoc()) {
    $oid     = (int)$row['order_id'];
    $svc     = $row['service_type'];
    $qty     = max(1, (int)$row['quantity']);
    $total   = (float)$row['total_amount'];

    // Resolve product_id
    $pid = $svc_to_pid[$svc] ?? 0;
    if ($pid === 0) {
        // Service not in map — use a fallback (first available svc product)
        $pid = !empty($svc_to_pid) ? reset($svc_to_pid) : 1;
        $items_skipped++;
    }

    // unit_price: use price_per_piece if set, else derive from total/qty
    $unit_price = (float)($row['price_per_piece'] ?? 0);
    if ($unit_price <= 0) {
        $unit_price = $qty > 0 ? round($total / $qty, 2) : $total;
    }
    if ($unit_price <= 0) $unit_price = 1.00;

    if ($DRY_RUN) {
        $items_created++;
        continue;
    }

    $ins_item->bind_param('iidd', $oid, $pid, $qty, $unit_price);
    if ($ins_item->execute()) {
        $items_created++;
    } else {
        $items_skipped++;
    }
}

if (!$DRY_RUN && isset($ins_item)) $ins_item->close();
echo "  Strategy A (linked): $items_created created, $items_skipped skipped\n";

// Strategy B: Remaining orders without job_order links and still no items
$orphan_res = $conn->query("
    SELECT o.order_id, o.total_amount, o.order_date
    FROM orders o
    WHERE NOT EXISTS (
        SELECT 1 FROM order_items oi WHERE oi.order_id = o.order_id
    )
    ORDER BY o.order_id
");

// Build a weighted list from service_products for random assignment
$weighted_svcs = [];
$svc_weights = [
    'Tarpaulin Printing' => 30, 'T-shirt Printing' => 20,
    'Decals/Stickers (Print/Cut)' => 12, 'Glass Stickers / Wall / Frosted Stickers' => 7,
    'Transparent Stickers' => 6, 'Layouts' => 5,
    'Reflectorized (Subdivision Stickers/Signages)' => 5, 'Stickers on Sintraboard' => 5,
    'Sintraboard Standees' => 5, 'Souvenirs' => 5,
];
foreach ($svc_weights as $sn => $sw) {
    for ($w = 0; $w < $sw; $w++) $weighted_svcs[] = $sn;
}

$orphan_created = 0;
$orphan_skipped = 0;

if (!$DRY_RUN) {
    $ins_item2 = $conn->prepare(
        "INSERT INTO order_items (order_id, product_id, quantity, unit_price)
         VALUES (?, ?, ?, ?)"
    );
}

// Build a product lookup map for all service products
$svc_conf_map = [];
foreach ($service_products as $sp) $svc_conf_map[$sp['name']] = $sp;

while ($row = $orphan_res->fetch_assoc()) {
    $oid   = (int)$row['order_id'];
    $total = (float)$row['total_amount'];
    $month = (int)date('n', strtotime($row['order_date']));

    // Apply seasonal pick
    $svc = $weighted_svcs[mt_rand(0, count($weighted_svcs) - 1)];
    $pid = $svc_to_pid[$svc] ?? (reset($svc_to_pid) ?: 1);

    $conf = $svc_conf_map[$svc] ?? ['qty_min' => 1, 'qty_max' => 5, 'price_min' => 200, 'price_max' => 1500];
    $qty  = mt_rand($conf['qty_min'], min($conf['qty_max'], 20));

    // Derive unit_price from order total (keeps totals consistent)
    $unit_price = $qty > 0 ? round($total / $qty, 2) : (float)$conf['price'];
    if ($unit_price <= 0) $unit_price = (float)$conf['price'];

    if ($DRY_RUN) { $orphan_created++; continue; }

    $ins_item2->bind_param('iidd', $oid, $pid, $qty, $unit_price);
    if ($ins_item2->execute()) {
        $orphan_created++;
    } else {
        $orphan_skipped++;
    }
}

if (!$DRY_RUN && isset($ins_item2)) $ins_item2->close();
echo "  Strategy B (orphan): $orphan_created created, $orphan_skipped skipped\n\n";

// ═══════════════════════════════════════════════════════════════════════════════
// STEP 4 — Optionally generate NEW orders (if --force-new-orders or total < 800)
// ═══════════════════════════════════════════════════════════════════════════════
$current_count = (int)$conn->query("SELECT COUNT(*) as c FROM orders WHERE YEAR(order_date) >= 2024")->fetch_assoc()['c'];
echo "=== STEP 4: New orders (current 2024+ count: $current_count) ===\n";

$NEW_ORDERS_TARGET = 0;
if ($FORCE_NEW) {
    $NEW_ORDERS_TARGET = 300;
    echo "  --force-new-orders: will generate $NEW_ORDERS_TARGET new orders\n";
} elseif ($current_count < 800) {
    $NEW_ORDERS_TARGET = 800 - $current_count;
    echo "  Below 800 threshold — generating $NEW_ORDERS_TARGET new orders\n";
} else {
    echo "  Sufficient data exists ($current_count orders). Skipping new order generation.\n";
}

if ($NEW_ORDERS_TARGET > 0) {
    // Load customer IDs
    $cust_ids = [];
    $cr = $conn->query("SELECT customer_id FROM customers WHERE status IN ('Active','Activated') ORDER BY customer_id");
    while ($r = $cr->fetch_assoc()) $cust_ids[] = (int)$r['customer_id'];
    if (empty($cust_ids)) die("  ERROR: No customers found. Run seed_historical_data.php first.\n");

    // Load branches
    $branches = [];
    $br = $conn->query("SELECT id, branch_name FROM branches ORDER BY id");
    while ($r = $br->fetch_assoc()) {
        $branches[] = ['id' => (int)$r['id'], 'name' => $r['branch_name'], 'weight' => 0];
    }
    // Assign weights: first branch gets 45%, second 30%, third 15%, rest split remaining
    $bw = [45, 30, 15, 10];
    foreach ($branches as $i => &$b) $b['weight'] = $bw[$i] ?? 5;
    unset($b);

    // Monthly schedule to distribute orders
    $months = [];
    $now_ts = time();
    for ($y = 2024; $y <= (int)date('Y'); $y++) {
        $max_m = ($y == (int)date('Y')) ? (int)date('n') : 12;
        for ($m = 1; $m <= $max_m; $m++) {
            $months[] = ['year' => $y, 'month' => $m];
        }
    }

    $base_per_month = (int)ceil($NEW_ORDERS_TARGET / max(1, count($months)));

    $status_map = ['Completed','Completed','Completed','Completed','Ready for Pickup','Processing','Cancelled','Pending'];
    $pay_map    = ['Paid','Paid','Paid','Unpaid'];

    if (!$DRY_RUN) {
        $ins_ord  = $conn->prepare("INSERT INTO orders
            (customer_id, branch_id, order_date, total_amount, status, payment_status, payment_method, notes, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?)");
        $ins_itm3 = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?,?,?,?)");
    }

    $new_ord_count = 0;
    $remaining = $NEW_ORDERS_TARGET;

    foreach ($months as $ym) {
        if ($remaining <= 0) break;
        $y = $ym['year']; $m = $ym['month'];
        $days = cal_days_in_month(CAL_GREGORIAN, $m, $y);
        $m_start = mktime(8,0,0,$m,1,$y);
        $m_end   = min(mktime(20,0,0,$m,$days,$y), $now_ts - 3600);
        if ($m_start >= $m_end) continue;

        $count = min($remaining, $base_per_month);

        for ($i = 0; $i < $count; $i++) {
            $ord_ts = mt_rand($m_start, $m_end);
            $ord_dt = date('Y-m-d H:i:s', $ord_ts);
            $upd_dt = date('Y-m-d H:i:s', min($ord_ts + mt_rand(3600, 86400*3), $now_ts));

            // Pick service with seasonal weight
            $svc = $weighted_svcs[mt_rand(0, count($weighted_svcs) - 1)];
            $pid = $svc_to_pid[$svc] ?? (reset($svc_to_pid) ?: 1);
            $conf = $svc_conf_map[$svc] ?? ['qty_min'=>1,'qty_max'=>5,'price_min'=>300,'price_max'=>1500];

            $qty   = mt_rand($conf['qty_min'], min($conf['qty_max'], 20));
            $price = round(rf($conf['price_min'], $conf['price_max']), 2);
            $total = round($price * $qty, 2);

            $days_ago = ($now_ts - $ord_ts) / 86400;
            if ($days_ago > 120)     { $status = 'Completed';        $pay_s = 'Paid'; }
            elseif ($days_ago > 30)  { $status = mt_rand(0,99)<70 ? 'Completed' : 'Ready for Pickup'; $pay_s = mt_rand(0,99)<80 ? 'Paid' : 'Unpaid'; }
            else                     { $status = $status_map[mt_rand(0,count($status_map)-1)]; $pay_s = $pay_map[mt_rand(0,count($pay_map)-1)]; }

            $cid    = $cust_ids[mt_rand(0, count($cust_ids)-1)];
            $bid    = (int)wpick($branches)['id'];
            $pm     = ['Cash','Cash','Cash','GCash','GCash','Maya'][mt_rand(0,5)];
            $notes  = [null,null,null,'Rush order.','Customer will pick up.','Delivery requested.'][mt_rand(0,5)];

            if ($DRY_RUN) { $new_ord_count++; $remaining--; continue; }

            $ins_ord->bind_param('iisdsssss', $cid, $bid, $ord_dt, $total, $status, $pay_s, $pm, $notes, $upd_dt);
            if ($ins_ord->execute()) {
                $oid2 = (int)$conn->insert_id;
                $ins_itm3->bind_param('iidd', $oid2, $pid, $qty, $price);
                $ins_itm3->execute();
                $new_ord_count++;
                $remaining--;
            }
        }
    }

    if (!$DRY_RUN) { $ins_ord->close(); $ins_itm3->close(); }
    echo "  Generated $new_ord_count new orders with order_items.\n";
}
echo "\n";

// ═══════════════════════════════════════════════════════════════════════════════
// STEP 5 — Update customer transaction counts
// ═══════════════════════════════════════════════════════════════════════════════
echo "=== STEP 5: Update customer stats ===\n";
if (!$DRY_RUN) {
    $conn->query("
        UPDATE customers c
        SET transaction_count = (
            SELECT COUNT(*) FROM orders o
            WHERE o.customer_id = c.customer_id
              AND o.status IN ('Completed','Ready for Pickup','Processing')
        )
    ");
    $conn->query("UPDATE customers SET customer_type='REGULAR' WHERE transaction_count >= 5");
    echo "  ✓ Customer transaction counts and types updated.\n";
} else {
    echo "  [DRY] Would update transaction_count and customer_type.\n";
}

// ═══════════════════════════════════════════════════════════════════════════════
// SUMMARY
// ═══════════════════════════════════════════════════════════════════════════════
$final_orders     = (int)$conn->query("SELECT COUNT(*) FROM orders WHERE YEAR(order_date)>=2024")->fetch_assoc()['COUNT(*)'];
$final_items      = (int)$conn->query("SELECT COUNT(*) FROM order_items")->fetch_assoc()['COUNT(*)'];
$final_customers  = (int)$conn->query("SELECT COUNT(*) FROM customers")->fetch_assoc()['COUNT(*)'];
$covered_orders   = (int)$conn->query("SELECT COUNT(DISTINCT order_id) FROM order_items")->fetch_assoc()['COUNT(DISTINCT order_id)'];

echo "\n" . str_repeat('═', 55) . "\n";
echo "  SEED COMPLETE\n";
echo str_repeat('═', 55) . "\n";
echo "  2024+ Orders         : $final_orders\n";
echo "  Order items total    : $final_items\n";
echo "  Orders with items    : $covered_orders\n";
echo "  Customers            : $final_customers\n";
echo str_repeat('═', 55) . "\n";
if ($DRY_RUN) echo "\n⚠  DRY RUN — no data was written.\n";
echo "\nDone!\n";
