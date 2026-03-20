<?php
/**
 * PrintFlow Historical Data Seeder
 * ================================
 * Generates 800–1500 realistic historical job orders from Jan 2024 to present.
 *
 * Features:
 *  - Seasonal demand patterns (graduation, holidays, school opening)
 *  - Laguna-area customer addresses with barangay names
 *  - Branch distribution across 4 branches
 *  - Realistic service pricing (per spec)
 *  - 30% repeat customers, 70% new
 *  - Older orders mostly Completed, recent ones mixed
 *
 * Usage: php seed_historical_data.php [--dry-run]
 *        (Run from c:\xampp\htdocs\printflow\)
 */

set_time_limit(300);
ini_set('memory_limit', '256M');

// ─── CONFIG ─────────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '1234');
define('DB_NAME', 'printflow_1');

// Target: between 900 and 1100 orders total
define('TARGET_ORDERS', 1000);

$DRY_RUN = in_array('--dry-run', $argv ?? []);
if ($DRY_RUN) echo "[DRY RUN MODE - No DB changes will be made]\n\n";

// ─── CONNECT ─────────────────────────────────────────────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}
$conn->set_charset('utf8mb4');
echo "✅ Connected to database: " . DB_NAME . "\n";

// ─── STEP 1: ADD ADDRESS COLUMNS TO CUSTOMERS TABLE ─────────────────────────
echo "\n[Step 1] Adding address columns to customers table...\n";

$address_cols_needed = [
    'street_address' => "varchar(200) DEFAULT NULL",
    'barangay'       => "varchar(100) DEFAULT NULL",
    'city'           => "varchar(100) DEFAULT NULL",
    'province'       => "varchar(100) DEFAULT NULL",
    'postal_code'    => "varchar(10)  DEFAULT NULL",
];

if (!$DRY_RUN) {
    // Check which columns already exist
    $existing_cols_res = $conn->query(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'customers'"
    );
    $existing_cols = [];
    while ($ec = $existing_cols_res->fetch_assoc()) {
        $existing_cols[] = $ec['COLUMN_NAME'];
    }

    foreach ($address_cols_needed as $col_name => $col_def) {
        if (!in_array($col_name, $existing_cols)) {
            $sql = "ALTER TABLE customers ADD COLUMN `$col_name` $col_def";
            if (!$conn->query($sql)) {
                echo "  Warning adding $col_name: " . $conn->error . "\n";
            } else {
                echo "  + Added column: $col_name\n";
            }
        } else {
            echo "  ✓ Column already exists: $col_name\n";
        }
    }
    echo "  ✅ Address columns ready.\n";
} else {
    echo "  [DRY] Would add: street_address, barangay, city, province, postal_code\n";
}

// ─── DATA TABLES ─────────────────────────────────────────────────────────────

// Branch IDs from live DB
$branches = [
    ['id' => 1, 'name' => 'Main Branch',     'weight' => 45],
    ['id' => 2, 'name' => 'Mandaue Branch',  'weight' => 30],
    ['id' => 3, 'name' => 'Cebu Branch',     'weight' => 15],
    ['id' => 4, 'name' => 'Los Banos Branch','weight' => 10],
];

// Service types exactly as in the ENUM
$services = [
    'Tarpaulin Printing' => [
        'weight' => 30,
        'price_min' => 500, 'price_max' => 3500,
        'qty_min' => 1, 'qty_max' => 10,
        'has_artwork' => 0.70,
    ],
    'T-shirt Printing' => [
        'weight' => 20,
        'price_min' => 200, 'price_max' => 800,
        'qty_min' => 1, 'qty_max' => 50,
        'has_artwork' => 0.65,
    ],
    'Decals/Stickers (Print/Cut)' => [
        'weight' => 12,
        'price_min' => 150, 'price_max' => 1200,
        'qty_min' => 5, 'qty_max' => 100,
        'has_artwork' => 0.55,
    ],
    'Glass Stickers / Wall / Frosted Stickers' => [
        'weight' => 7,
        'price_min' => 200, 'price_max' => 1500,
        'qty_min' => 1, 'qty_max' => 20,
        'has_artwork' => 0.50,
    ],
    'Transparent Stickers' => [
        'weight' => 6,
        'price_min' => 150, 'price_max' => 900,
        'qty_min' => 5, 'qty_max' => 100,
        'has_artwork' => 0.45,
    ],
    'Layouts' => [
        'weight' => 5,
        'price_min' => 200, 'price_max' => 500,
        'qty_min' => 1, 'qty_max' => 5,
        'has_artwork' => 0.30,
    ],
    'Reflectorized (Subdivision Stickers/Signages)' => [
        'weight' => 5,
        'price_min' => 300, 'price_max' => 2000,
        'qty_min' => 1, 'qty_max' => 30,
        'has_artwork' => 0.60,
    ],
    'Stickers on Sintraboard' => [
        'weight' => 5,
        'price_min' => 300, 'price_max' => 1500,
        'qty_min' => 1, 'qty_max' => 20,
        'has_artwork' => 0.50,
    ],
    'Sintraboard Standees' => [
        'weight' => 5,
        'price_min' => 500, 'price_max' => 2500,
        'qty_min' => 1, 'qty_max' => 10,
        'has_artwork' => 0.60,
    ],
    'Souvenirs' => [
        'weight' => 5,
        'price_min' => 100, 'price_max' => 700,
        'qty_min' => 5, 'qty_max' => 100,
        'has_artwork' => 0.25,
    ],
];

// Laguna cities and barangays
$locations = [
    ['city' => 'Cabuyao',    'weight' => 40, 'postal' => '4025', 'barangays' => [
        'Banlic','Pulo','Bigaa','Sala','Pittland','Mamatid','Marinig','Uno','Dos']],
    ['city' => 'Calamba',    'weight' => 25, 'postal' => '4027', 'barangays' => [
        'Parian','Bucal','Real','Canlubang','Majada Out','Paciano Rizal','Halang','Barandal']],
    ['city' => 'Biñan',      'weight' => 15, 'postal' => '4024', 'barangays' => [
        'Poblacion','Canlalay','Platero','Timbao','Soro Soro','Malaban','Mamplasan']],
    ['city' => 'Santa Rosa', 'weight' => 10, 'postal' => '4026', 'barangays' => [
        'Aplaya','Balibago','Dila','Tagapo','Labas','Macabling','Pulong Santa Cruz']],
    ['city' => 'San Pedro',  'weight' => 5, 'postal' => '4023', 'barangays' => [
        'Landayan','Calendola','Cuyab','Estrella','Pacita 1','Pacita 2']],
    ['city' => 'Los Baños',  'weight' => 5, 'postal' => '4030', 'barangays' => [
        'Batong Malake','Bambang','Bayog','Anos','Lalakay','Putho Tuntungin']],
];

$street_patterns = [
    'Blk %d Lot %d Sampaguita St.',
    '%d Mabini St.',
    '%d Rizal St.',
    'Lot %d Orchid St.',
    'Blk %d Phase %d Camella Homes',
    '%d Bonifacio St.',
    'Blk %d Lot %d Mahogany Ave.',
    '%d Del Pilar St.',
    'Lot %d Narra St.',
    '%d Jose Abad Santos Ave.',
    'Blk %d Lot %d Rosal St.',
    '%d Gen. Luna St.',
    'Unit %d Puregold Residences',
    'Blk %d Phase %d Springville',
];

$first_names = [
    'Maria','Jose','Juan','Ana','Rosa','Carlos','Maricel','Mark','Lovely','Angelo',
    'Jenny','RJ','Kristine','Ryan','Donna','Patrick','Hazel','Jerome','Jessa','Michael',
    'Christian','Nica','Jayson','Bea','Rodel','Marianne','Noel','Charmaine','Ronald','Mariz',
    'Emilio','Gracelyn','Danilo','Fatima','Richard','Lorena','Gerald','Roselyn','Francis','Imelda',
    'Edward','Lorelei','Dennis','Melanie','Roberto','Cynthia','Anthony','Marjorie','Ramon','Kriselle',
    'Arnel','Jovelyn','Ferdinand','Erlinda','Roy','Sonia','Bennie','Flordeliza','Leo','Mylene',
    'Rene','Gloria','Efren','Norma','Alfredo','Resurreccion','Jaime','Vilma','Arthur','Ligaya',
];

$last_names = [
    'Santos','Reyes','Cruz','Garcia','Torres','Flores','Villanueva','Ramos','Mendoza','Castro',
    'Bautista','Aquino','Dela Cruz','Tan','Hernandez','Villafuerte','Dizon','Abad','Manalo','Gutierrez',
    'Magno','Cabrera','Luna','Hipolito','Navarro','Pablo','Perez','Vergara','Serrano','Baluyot',
    'Bello','Tuazon','Ocampo','Samson','Eugenio','Pascual','Andres','Castillo','Aguilar','Llanes',
    'Macapagal','Salazar','Lacson','Policarpio','Velasco','Alcantara','Coronel','Alcala','Javier','Medina',
];

$payment_methods = ['Cash', 'GCash', 'Maya'];

// ─── HELPER FUNCTIONS ─────────────────────────────────────────────────────────

function rand_float($min, $max) {
    return $min + mt_rand() / mt_getrandmax() * ($max - $min);
}

function weighted_pick(array $items, string $weight_key = 'weight') {
    $total = array_sum(array_column($items, $weight_key));
    $r = mt_rand(0, $total - 1);
    $cumulative = 0;
    foreach ($items as $item) {
        $cumulative += $item[$weight_key];
        if ($r < $cumulative) return $item;
    }
    return $items[array_key_last($items)];
}

function random_street(): string {
    global $street_patterns;
    $pattern = $street_patterns[array_rand($street_patterns)];
    // Replace %d with random numbers
    return preg_replace_callback('/%d/', function() {
        return mt_rand(1, 99);
    }, $pattern);
}

function random_location(): array {
    global $locations;
    $loc = weighted_pick($locations);
    $brgy = $loc['barangays'][array_rand($loc['barangays'])];
    return [
        'street'   => random_street(),
        'barangay' => 'Brgy. ' . $brgy,
        'city'     => $loc['city'],
        'province' => 'Laguna',
        'postal'   => $loc['postal'],
    ];
}

/**
 * Get the seasonal multiplier for a given service in a given month.
 * Returns a float between 0.5 and 2.0
 */
function seasonal_multiplier(string $service, int $month): float {
    $mult = 1.0;
    switch ($month) {
        case 3: // March - Graduation
        case 4: // April - Graduation
            if (in_array($service, ['Tarpaulin Printing', 'T-shirt Printing', 'Souvenirs'])) {
                $mult = rand_float(1.4, 1.7);
            }
            break;
        case 5: // May - Election
            if (in_array($service, ['Tarpaulin Printing', 'Decals/Stickers (Print/Cut)', 'Reflectorized (Subdivision Stickers/Signages)'])) {
                $mult = rand_float(1.5, 2.0);
            }
            break;
        case 6: // June - School Opening
        case 7: // July - School Opening
            if (in_array($service, ['Decals/Stickers (Print/Cut)', 'Stickers on Sintraboard', 'Sintraboard Standees'])) {
                $mult = rand_float(1.3, 1.6);
            }
            break;
        case 11: // November - Holiday
        case 12: // December - Holiday
            if (in_array($service, ['Souvenirs', 'T-shirt Printing', 'Tarpaulin Printing'])) {
                $mult = rand_float(1.4, 1.8);
            }
            break;
        case 1: // January - Slow
        case 2: // February - Slow
            $mult = rand_float(0.6, 0.85);
            break;
    }
    return $mult;
}

/**
 * Return base orders/month for a given month (before seasonal adjustments).
 */
function base_orders_for_month(int $year, int $month): int {
    // Monthly patterns: Jan=slow, Mar/Apr/May/Dec=peak, rest=medium
    $base = [
        1 => 45, 2 => 50, 3 => 90, 4 => 85, 5 => 100,
        6 => 80, 7 => 75, 8 => 70, 9 => 65, 10 => 70,
        11 => 90, 12 => 110,
    ];

    $b = $base[$month] ?? 70;

    // Scale by year slightly (growth)
    if ($year == 2025) $b = (int)($b * 1.10);
    if ($year == 2026) $b = (int)($b * 1.18);

    return $b;
}

function pick_branch_id(array $branches): int {
    $b = weighted_pick($branches);
    return (int)$b['id'];
}

function pick_payment(): string {
    global $payment_methods;
    $weights = [55, 30, 15]; // Cash, GCash, Maya
    $r = mt_rand(0, 99);
    if ($r < 55) return 'Cash';
    if ($r < 85) return 'GCash';
    return 'Maya';
}

/**
 * Determine order status based on order date.
 * Older orders → Completed, recent → mix
 */
function pick_status(string $order_date_str): array {
    $order_ts = strtotime($order_date_str);
    $now_ts   = time();
    $days_ago = ($now_ts - $order_ts) / 86400;

    if ($days_ago > 120) {
        // Old orders: mostly Completed, some Cancelled
        $r = mt_rand(0, 99);
        if ($r < 88) return ['COMPLETED', 'PAID'];
        if ($r < 96) return ['CANCELLED', 'UNPAID'];
        return ['COMPLETED', 'PARTIAL'];
    } elseif ($days_ago > 30) {
        $r = mt_rand(0, 99);
        if ($r < 70) return ['COMPLETED', 'PAID'];
        if ($r < 80) return ['TO_RECEIVE', 'PAID'];
        if ($r < 88) return ['IN_PRODUCTION', 'PARTIAL'];
        if ($r < 94) return ['TO_PAY', 'UNPAID'];
        return ['CANCELLED', 'UNPAID'];
    } else {
        $r = mt_rand(0, 99);
        if ($r < 35) return ['COMPLETED', 'PAID'];
        if ($r < 50) return ['TO_RECEIVE', 'PAID'];
        if ($r < 65) return ['IN_PRODUCTION', 'PARTIAL'];
        if ($r < 80) return ['TO_PAY', 'UNPAID'];
        if ($r < 90) return ['APPROVED', 'UNPAID'];
        return ['PENDING', 'UNPAID'];
    }
}

function pick_priority(): string {
    $r = mt_rand(0, 99);
    if ($r < 70) return 'NORMAL';
    if ($r < 88) return 'HIGH';
    return 'LOW';
}

// ─── STEP 2: GENERATE CUSTOMERS ───────────────────────────────────────────────
echo "\n[Step 2] Generating customer pool...\n";

$NUM_CUSTOMERS = 250;
$customer_ids  = [];

// Get existing customers from DB
$existing_res = $conn->query("SELECT customer_id, city FROM customers ORDER BY customer_id ASC");
$existing_customers = [];
while ($row = $existing_res->fetch_assoc()) {
    $existing_customers[] = (int)$row['customer_id'];
}

$new_customer_count = 0;

if (!$DRY_RUN) {
    // Update existing customers with addresses if missing
    $existing_res2 = $conn->query("SELECT customer_id FROM customers WHERE city IS NULL ORDER BY customer_id");
    if ($existing_res2) {
        while ($row = $existing_res2->fetch_assoc()) {
            $loc = random_location();
            $stmt = $conn->prepare("UPDATE customers SET street_address=?, barangay=?, city=?, province=?, postal_code=? WHERE customer_id=?");
            $stmt->bind_param('sssssi', $loc['street'], $loc['barangay'], $loc['city'], $loc['province'], $loc['postal'], $row['customer_id']);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Insert new customers up to NUM_CUSTOMERS
    $need = max(0, $NUM_CUSTOMERS - count($existing_customers));
    echo "  Existing customers: " . count($existing_customers) . ", Creating $need new...\n";

    for ($i = 0; $i < $need; $i++) {
        $fn = $GLOBALS['first_names'][array_rand($GLOBALS['first_names'])];
        $ln = $GLOBALS['last_names'][array_rand($GLOBALS['last_names'])];
        $loc = random_location();
        $email_base = strtolower(preg_replace('/\s+/', '.', $fn . '.' . $ln));
        $email = $email_base . mt_rand(100, 9999) . '@gmail.com';
        $gender = mt_rand(0, 1) ? 'Male' : 'Female';
        $dob_year = mt_rand(1975, 2003);
        $dob = $dob_year . '-' . str_pad(mt_rand(1,12),2,'0',STR_PAD_LEFT) . '-' . str_pad(mt_rand(1,28),2,'0',STR_PAD_LEFT);
        $contact = '09' . str_pad(mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
        $ctype = (mt_rand(0, 99) < 30) ? 'REGULAR' : 'NEW';
        $tx_count = ($ctype === 'REGULAR') ? mt_rand(5, 30) : mt_rand(0, 4);
        $created = date('Y-m-d H:i:s', mt_rand(strtotime('2020-01-01'), strtotime('2024-01-01')));
        $pwd_hash = password_hash('Customer@123', PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT IGNORE INTO customers 
            (first_name, last_name, email, gender, dob, contact_number, password_hash,
             street_address, barangay, city, province, postal_code,
             customer_type, transaction_count, status, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Active',?,?)");
        // 16 params: 13×s, 1×i (tx_count), 2×s
        $stmt->bind_param('sssssssssssssiss',
            $fn, $ln, $email, $gender, $dob, $contact, $pwd_hash,
            $loc['street'], $loc['barangay'], $loc['city'], $loc['province'], $loc['postal'],
            $ctype, $tx_count, $created, $created
        );
        if ($stmt->execute()) {
            $cid = (int)$conn->insert_id;
            if ($cid > 0) {
                $existing_customers[] = $cid;
                $new_customer_count++;
            }
        }
        $stmt->close();
    }

    echo "  ✅ Customer pool ready: " . count($existing_customers) . " customers ($new_customer_count new)\n";

    // Reload all customer IDs
    $customer_ids = [];
    $res = $conn->query("SELECT customer_id FROM customers WHERE status='Active' ORDER BY customer_id");
    while ($row = $res->fetch_assoc()) {
        $customer_ids[] = (int)$row['customer_id'];
    }
} else {
    echo "  [DRY] Would create " . max(0, $NUM_CUSTOMERS - count($existing_customers)) . " customers\n";
    $customer_ids = $existing_customers ?: [1, 2, 3];
}

if (empty($customer_ids)) {
    die("ERROR: No customer IDs available. Aborting.\n");
}

// ─── STEP 3: BUILD MONTHLY ORDER SCHEDULE ─────────────────────────────────────
echo "\n[Step 3] Building monthly order schedule (Jan 2024 – Mar 2026)...\n";

$months = [];
$start_year  = 2024; $start_month = 1;
$end_year    = 2026; $end_month   = 3;

$y = $start_year; $m = $start_month;
while ($y < $end_year || ($y === $end_year && $m <= $end_month)) {
    $months[] = ['year' => $y, 'month' => $m];
    $m++;
    if ($m > 12) { $m = 1; $y++; }
}

$total_base = 0;
$schedule   = [];
foreach ($months as $ym) {
    $base = base_orders_for_month($ym['year'], $ym['month']);
    $schedule[] = ['year' => $ym['year'], 'month' => $ym['month'], 'count' => $base];
    $total_base += $base;
}

// Scale to TARGET_ORDERS
$scale = TARGET_ORDERS / max($total_base, 1);
$total_orders = 0;
foreach ($schedule as &$s) {
    $s['count'] = max(20, (int)round($s['count'] * $scale));
    $total_orders += $s['count'];
}
unset($s);

echo "  Months: " . count($months) . ", Estimated orders: $total_orders\n";

// ─── STEP 4: INSERT JOB ORDERS ────────────────────────────────────────────────
echo "\n[Step 4] Inserting job orders...\n";

$service_keys  = array_keys($services);
$service_weights = array_column($services, 'weight', null);

// Build weighted service picker
function pick_service(array $services, string $month_service_hint = ''): string {
    global $service_keys, $service_weights;
    $total = array_sum($services);
    $r = mt_rand(0, $total - 1);
    $cum = 0;
    foreach ($services as $k => $w) {
        $cum += $w;
        if ($r < $cum) return $k;
    }
    return array_key_last($services);
}

$orders_inserted = 0;
$errors = 0;

// Prepare INSERT for job_orders
$job_order_sql = "INSERT INTO job_orders
    (customer_id, branch_id, job_title, customer_name, service_type, status, customer_type,
     width_ft, height_ft, quantity, total_sqft, price_per_piece, estimated_total,
     amount_paid, required_payment, payment_status, payment_method, notes, priority,
     artwork_path, created_by, created_at, updated_at, due_date)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

if (!$DRY_RUN) {
    $stmt_jo = $conn->prepare($job_order_sql);
    if (!$stmt_jo) {
        die("Prepare failed for job_orders: " . $conn->error . "\n");
    }
}

// Build a weighted list for service repeat picking
$weighted_service_list = [];
foreach ($services as $svc_name => $svc_conf) {
    for ($w = 0; $w < $svc_conf['weight']; $w++) {
        $weighted_service_list[] = $svc_name;
    }
}

// Regular customers list (repeat customers)
$repeat_customer_pool = array_slice($customer_ids, 0, min(75, (int)(count($customer_ids) * 0.3)));
$new_customer_pool    = $customer_ids;

// Pre-cache all customer data to avoid N+1 queries
$customer_cache = [];
$cache_res = $conn->query("SELECT customer_id, first_name, last_name, customer_type FROM customers");
if ($cache_res) {
    while ($row = $cache_res->fetch_assoc()) {
        $customer_cache[(int)$row['customer_id']] = $row;
    }
}

foreach ($schedule as $ym) {
    $y = $ym['year'];
    $mo = $ym['month'];
    $count = $ym['count'];
    $month_label = date('F Y', mktime(0, 0, 0, $mo, 1, $y));

    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $mo, $y);
    $month_start_ts = mktime(8, 0, 0, $mo, 1, $y);
    $month_end_ts   = mktime(20, 0, 0, $mo, $days_in_month, $y);

    // Don't go past current date
    if ($month_end_ts > time()) {
        $month_end_ts = time() - 3600;
    }
    if ($month_start_ts > $month_end_ts) continue;

    $inserted_this_month = 0;

    for ($i = 0; $i < $count; $i++) {
        // Pick random timestamp within month
        $order_ts   = mt_rand($month_start_ts, $month_end_ts);
        $order_date = date('Y-m-d H:i:s', $order_ts);
        $updated_at = date('Y-m-d H:i:s', $order_ts + mt_rand(3600, 172800));
        if (strtotime($updated_at) > time()) {
            $updated_at = date('Y-m-d H:i:s');
        }

        // Pick service with seasonal weighting
        $svc_name   = $weighted_service_list[mt_rand(0, count($weighted_service_list) - 1)];
        $svc        = $services[$svc_name];
        $s_mult     = seasonal_multiplier($svc_name, $mo);

        // 30% chance of repeat customer
        if (!empty($repeat_customer_pool) && mt_rand(0, 99) < 30) {
            $cust_id = $repeat_customer_pool[array_rand($repeat_customer_pool)];
        } else {
            $cust_id = $new_customer_pool[array_rand($new_customer_pool)];
        }

        // Customer name (from cache)
        $cust_row  = $customer_cache[$cust_id] ?? ['first_name' => 'Walk', 'last_name' => 'In', 'customer_type' => 'NEW'];
        $cust_name = trim(($cust_row['first_name'] ?? 'Walk') . ' ' . ($cust_row['last_name'] ?? 'In'));
        $cust_type = $cust_row['customer_type'] ?? 'NEW';

        // Quantity with seasonal bump
        $qty     = (int)max($svc['qty_min'], round(mt_rand($svc['qty_min'], $svc['qty_max']) * ($s_mult > 1 ? 1 : 1)));
        $price   = round(rand_float($svc['price_min'] * $s_mult, $svc['price_max'] * $s_mult), 2);
        $total   = round($price * $qty, 2);

        // For tarpaulin: dimensions
        $width   = in_array($svc_name, ['Tarpaulin Printing', 'Stickers on Sintraboard', 'Sintraboard Standees']) ? rand_float(2, 8) : 0;
        $height  = ($width > 0) ? rand_float(2, 12) : 0;
        $sqft    = round($width * $height * $qty, 2);

        // Status and payment based on age
        [$status, $pay_status] = pick_status($order_date);

        // Amount paid
        $amount_paid = 0;
        if ($pay_status === 'PAID') {
            $amount_paid = $total;
        } elseif ($pay_status === 'PARTIAL') {
            $amount_paid = round($total * 0.5, 2);
        }

        $required_payment = ($cust_type === 'REGULAR') ? round($total * 0.5, 2) : $total;

        $pay_method = pick_payment();
        $branch_id  = pick_branch_id($branches);
        $priority   = pick_priority();

        // Artwork path if applicable
        $has_artwork = (mt_rand(0, 99) < (int)($svc['has_artwork'] * 100));
        $artwork_path = $has_artwork ? 'uploads/designs/seed_artwork_' . mt_rand(1000, 9999) . '.jpg' : null;

        // Job title
        $job_title = $qty . 'x ' . $svc_name;
        if ($width > 0) {
            $job_title .= ' (' . round($width, 1) . 'ft x ' . round($height, 1) . 'ft)';
        }

        // Due date (3–14 days after order date)
        $due_ts  = $order_ts + mt_rand(3 * 86400, 14 * 86400);
        $due_date = date('Y-m-d H:i:s', min($due_ts, time() + 30 * 86400));

        // Notes (some orders get notes)
        $notes_pool = [
            null, null, null, null,
            'Rush order, please prioritize.',
            'Customer will pick up.',
            'Delivery requested.',
            'Special packaging needed.',
            'Design to be approved by customer first.',
            'Call customer before printing.',
            'Use glossy finish.',
            'Use matte finish.',
        ];
        $notes = $notes_pool[array_rand($notes_pool)];

        // Created by (staff)
        $created_by = [1, 2, 3, 4, 5, 6][array_rand([1, 2, 3, 4, 5, 6])];

        if (!$DRY_RUN) {
            // 24 params: ii(2) + sssss(5) + dd(2) + i(1) + ddddd(5) + sssss(5) + i(1) + sss(3) = 24
            $stmt_jo->bind_param(
                'iisssssddidddddsssssisss',
                $cust_id, $branch_id, $job_title, $cust_name, $svc_name, $status, $cust_type,
                $width, $height, $qty, $sqft,
                $price, $total,
                $amount_paid, $required_payment,
                $pay_status, $pay_method,
                $notes, $priority,
                $artwork_path, $created_by,
                $order_date, $updated_at, $due_date
            );

            if ($stmt_jo->execute()) {
                $orders_inserted++;
                $inserted_this_month++;
            } else {
                $errors++;
                if ($errors <= 5) echo "  ⚠️  Error inserting order: " . $stmt_jo->error . "\n";
            }
        } else {
            $orders_inserted++;
            $inserted_this_month++;
        }
    }

    echo "  $month_label: $inserted_this_month orders\n";
}

if (!$DRY_RUN && isset($stmt_jo)) {
    $stmt_jo->close();
}

// ─── STEP 5: ALSO INSERT INTO `orders` TABLE (for compatibility with existing reports) ─
echo "\n[Step 5] Inserting compatible records into `orders` table...\n";

// Build a mapping: same data into the legacy `orders` table
// We only do this in a targeted subset so it doesn't bloat too much
// The legacy table uses different status enum, so we map

$status_map = [
    'COMPLETED'     => 'Completed',
    'TO_RECEIVE'    => 'Ready for Pickup',
    'IN_PRODUCTION' => 'Processing',
    'TO_PAY'        => 'To Pay',
    'APPROVED'      => 'Processing',
    'PENDING'       => 'Pending',
    'CANCELLED'     => 'Cancelled',
];

$pay_status_map = [
    'PAID'     => 'Paid',
    'UNPAID'   => 'Unpaid',
    'PARTIAL'  => 'Paid',
];

if (!$DRY_RUN) {
    // Pull the job_orders we just inserted (only those with valid customer)
    $newly_inserted = $conn->query("
        SELECT jo.id, jo.customer_id, jo.created_at, jo.updated_at,
               jo.estimated_total, jo.status, jo.payment_status,
               jo.payment_method, jo.branch_id, jo.notes, jo.order_id
        FROM job_orders jo
        WHERE jo.created_at >= '2024-01-01 00:00:00'
        AND jo.order_id IS NULL
        AND jo.customer_id IS NOT NULL
        AND jo.customer_id > 0
        ORDER BY jo.id ASC
    ");

    if ($newly_inserted) {
        $orders_linked = 0;
        $orders_skipped = 0;
        $order_stmt = $conn->prepare("INSERT INTO orders
            (customer_id, order_date, total_amount, status, payment_status, payment_method, branch_id, notes, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?)");
        $link_stmt = $conn->prepare("UPDATE job_orders SET order_id = ? WHERE id = ?");

        // Suppress FK errors from mysqli exceptions
        $conn->query("SET SESSION sql_mode = ''");

        while ($jo = $newly_inserted->fetch_assoc()) {
            $cust_id_ord    = (int)$jo['customer_id'];
            $ord_status     = $status_map[$jo['status']] ?? 'Pending';
            $ord_pay_status = $pay_status_map[$jo['payment_status']] ?? 'Unpaid';
            $ord_date       = $jo['created_at'];
            $total          = (float)$jo['estimated_total'];
            $branch_id_ord  = (int)$jo['branch_id'];
            $ord_notes      = $jo['notes'];
            $upd            = $jo['updated_at'];
            $pay_meth       = $jo['payment_method'] ?? 'Cash';

            if ($cust_id_ord <= 0) { $orders_skipped++; continue; }

            $order_stmt->bind_param('isdsssiss',
                $cust_id_ord,
                $ord_date,
                $total,
                $ord_status,
                $ord_pay_status,
                $pay_meth,
                $branch_id_ord,
                $ord_notes,
                $upd
            );

            if ($order_stmt->execute()) {
                $new_order_id = (int)$conn->insert_id;
                $link_stmt->bind_param('ii', $new_order_id, $jo['id']);
                $link_stmt->execute();
                $orders_linked++;
            } else {
                $orders_skipped++;
            }
        }

        $order_stmt->close();
        $link_stmt->close();
        echo "  ✅ Linked $orders_linked records to orders table. (Skipped: $orders_skipped)\n";
    } else {
        echo "  ⚠️  Could not fetch newly inserted job orders: " . $conn->error . "\n";
    }
} else {
    echo "  [DRY] Would link job_orders -> orders table.\n";
}


// ─── STEP 6: UPDATE CUSTOMER TRANSACTION COUNTS ──────────────────────────────
echo "\n[Step 6] Updating customer transaction counts and types...\n";
if (!$DRY_RUN) {
    $conn->query("
        UPDATE customers c
        SET transaction_count = (
            SELECT COUNT(*) FROM job_orders jo
            WHERE jo.customer_id = c.customer_id AND jo.status = 'COMPLETED'
        )
    ");
    $conn->query("
        UPDATE customers SET customer_type = 'REGULAR'
        WHERE transaction_count >= 5
    ");
    echo "  ✅ Customer types updated.\n";
}

// ─── SUMMARY ─────────────────────────────────────────────────────────────────
echo "\n" . str_repeat('=', 55) . "\n";
echo "  SEED COMPLETE\n";
echo str_repeat('=', 55) . "\n";
echo "  Orders inserted : $orders_inserted\n";
echo "  Errors          : $errors\n";
echo "  Customers       : " . count($customer_ids) . " ({$new_customer_count} new)\n";
echo "  Date range      : Jan 2024 – " . date('M Y') . "\n";
echo str_repeat('=', 55) . "\n";
if ($DRY_RUN) echo "\n⚠️  This was a DRY RUN. No data was written.\n";
echo "\nDone!\n";
