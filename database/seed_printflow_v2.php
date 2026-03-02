<?php
/**
 * PrintFlow v2 — Seed Script
 * Seeds all inv_categories, inv_items, sample inv_rolls, and service_material_rules
 * Run ONCE after migration_printflow_v2.sql
 * Access: http://localhost/printflow/database/seed_printflow_v2.php
 */
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/plain; charset=UTF-8');
set_time_limit(60);

$errors = [];
$log = [];

function seed_log($msg) { echo $msg . "\n"; flush(); }
function seed_exec($sql, $params = [], $types = '') {
    global $conn;
    if ($params) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $r = $stmt->execute();
        $stmt->close();
        return $r;
    }
    return $conn->query($sql);
}
function seed_insert_ignore($table, $cols, $vals, $types) {
    global $conn;
    $placeholders = implode(',', array_fill(0, count($vals), '?'));
    $colStr = implode(',', array_map(fn($c) => "`$c`", $cols));
    $sql = "INSERT IGNORE INTO `$table` ($colStr) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { seed_log("PREPARE FAILED: " . $conn->error . " SQL: $sql"); return false; }
    $stmt->bind_param($types, ...$vals);
    $r = $stmt->execute();
    if (!$r) seed_log("INSERT FAILED: " . $stmt->error);
    $stmt->close();
    return $r;
}

seed_log("==============================================");
seed_log("PrintFlow v2 Seed Script");
seed_log("==============================================\n");

// ============================================================
// CATEGORIES
// ============================================================
seed_log("--- Seeding Categories ---");
$categories = [
    [1, 'PLATE (pcs)'],
    [2, 'TARPAULIN (FT)'],
    [3, 'PRINTED STKR'],
    [4, 'INK TARP'],
    [5, 'INK L120'],
    [6, 'INK L130'],
    [7, 'RUBBER / VINYL / TSHIRT'],
    [8, 'STICKER (Colored)'],
];
foreach ($categories as [$sort, $name]) {
    seed_insert_ignore('inv_categories', ['name','sort_order'], [$name, $sort], 'si');
    seed_log("  Category: $name");
}

// ============================================================
// LOOK UP CATEGORY IDs
// ============================================================
$catMap = [];
$res = $conn->query("SELECT id, name FROM inv_categories");
while ($row = $res->fetch_assoc()) {
    $catMap[$row['name']] = (int)$row['id'];
}
seed_log("\nCategory IDs mapped: " . implode(', ', array_map(fn($k,$v) => "$k=$v", array_keys($catMap), $catMap)) . "\n");

// ============================================================
// ITEMS
// format: [category_name, item_name, uom, track_by_roll, default_roll_length_ft, reorder_level]
// ============================================================
seed_log("--- Seeding Items ---");

$items = [
    // PLATE (pcs) — piece-based, 12 types
    ['PLATE (pcs)', 'AC MC',   'pcs', 0, null, 5],
    ['PLATE (pcs)', 'AC PH',   'pcs', 0, null, 5],
    ['PLATE (pcs)', 'AC EURO', 'pcs', 0, null, 5],
    ['PLATE (pcs)', 'AC THAI', 'pcs', 0, null, 5],
    ['PLATE (pcs)', 'AC NMC',  'pcs', 0, null, 5],
    ['PLATE (pcs)', 'AC HOME', 'pcs', 0, null, 5],
    ['PLATE (pcs)', 'SP MC',   'pcs', 0, null, 5],
    ['PLATE (pcs)', 'SP PH',   'pcs', 0, null, 5],
    ['PLATE (pcs)', 'SP EURO', 'pcs', 0, null, 5],
    ['PLATE (pcs)', 'SP THAI', 'pcs', 0, null, 5],
    ['PLATE (pcs)', 'SP NMC',  'pcs', 0, null, 5],
    ['PLATE (pcs)', 'SP HOME', 'pcs', 0, null, 5],

    // TARPAULIN (FT) — roll-based, 164 ft rolls
    ['TARPAULIN (FT)', '3FT Tarpaulin', 'ft', 1, 164.00, 50],
    ['TARPAULIN (FT)', '4FT Tarpaulin', 'ft', 1, 164.00, 50],
    ['TARPAULIN (FT)', '5FT Tarpaulin', 'ft', 1, 164.00, 50],
    ['TARPAULIN (FT)', '6FT Tarpaulin', 'ft', 1, 164.00, 50],

    // PRINTED STKR — roll-based
    ['PRINTED STKR', '3M REFLECTIVE',       'ft', 1, 164.00, 20],
    ['PRINTED STKR', 'NEXJET',              'ft', 1, 164.00, 20],
    ['PRINTED STKR', 'TRANSPARENT',         'ft', 1, 164.00, 20],
    ['PRINTED STKR', 'GLOSS LAMINATE',      'ft', 1, 164.00, 20],
    ['PRINTED STKR', 'MATTE LAMINATE',      'ft', 1, 164.00, 20],
    ['PRINTED STKR', 'HOLOGRAM',            'ft', 1, 164.00, 10],
    ['PRINTED STKR', 'PP STKR MATTE 98',   'ft', 1, 164.00, 20],

    // INK TARP — bottle/container
    ['INK TARP', 'INK TARP BLUE',   'bottle', 0, null, 2],
    ['INK TARP', 'INK TARP RED',    'bottle', 0, null, 2],
    ['INK TARP', 'INK TARP BLACK',  'bottle', 0, null, 2],
    ['INK TARP', 'INK TARP YELLOW', 'bottle', 0, null, 2],

    // INK L120 — bottle/container
    ['INK L120', 'INK L120 BLUE',   'bottle', 0, null, 2],
    ['INK L120', 'INK L120 RED',    'bottle', 0, null, 2],
    ['INK L120', 'INK L120 BLACK',  'bottle', 0, null, 2],
    ['INK L120', 'INK L120 YELLOW', 'bottle', 0, null, 2],

    // INK L130 — bottle/container
    ['INK L130', 'INK L130 BLUE',   'bottle', 0, null, 2],
    ['INK L130', 'INK L130 RED',    'bottle', 0, null, 2],
    ['INK L130', 'INK L130 BLACK',  'bottle', 0, null, 2],
    ['INK L130', 'INK L130 YELLOW', 'bottle', 0, null, 2],

    // RUBBER / VINYL / TSHIRT — pcs
    ['RUBBER / VINYL / TSHIRT', 'PVC ID',  'pcs', 0, null, 10],
    ['RUBBER / VINYL / TSHIRT', 'MUG',     'pcs', 0, null, 10],
    ['RUBBER / VINYL / TSHIRT', 'BOX MUG', 'pcs', 0, null, 10],
    ['RUBBER / VINYL / TSHIRT', 'VINYL BLUE',    'pcs', 0, null, 10],
    ['RUBBER / VINYL / TSHIRT', 'VINYL RED',     'pcs', 0, null, 10],
    ['RUBBER / VINYL / TSHIRT', 'VINYL YELLOW',  'pcs', 0, null, 10],
    ['RUBBER / VINYL / TSHIRT', 'VINYL GREEN',   'pcs', 0, null, 10],
    ['RUBBER / VINYL / TSHIRT', 'VINYL ORANGE',  'pcs', 0, null, 10],
    ['RUBBER / VINYL / TSHIRT', 'VINYL WHITE',   'pcs', 0, null, 10],
    ['RUBBER / VINYL / TSHIRT', 'VINYL BLACK',   'pcs', 0, null, 10],
    ['RUBBER / VINYL / TSHIRT', 'VINYL PINK',    'pcs', 0, null, 10],
    ['RUBBER / VINYL / TSHIRT', 'TSHIRT WHITE',  'pcs', 0, null, 10],
    ['RUBBER / VINYL / TSHIRT', 'TSHIRT BLACK',  'pcs', 0, null, 10],
    ['RUBBER / VINYL / TSHIRT', 'TSHIRT GRAY',   'pcs', 0, null, 10],
    ['RUBBER / VINYL / TSHIRT', 'TSHIRT BLUE',   'pcs', 0, null, 10],

    // STICKER (Colored) — ft-based
    ['STICKER (Colored)', 'STICKER BLACK', 'ft', 0, null, 5],
    ['STICKER (Colored)', 'STICKER WHITE', 'ft', 0, null, 5],
    ['STICKER (Colored)', 'STICKER RED',   'ft', 0, null, 5],
    ['STICKER (Colored)', 'STICKER BLUE',  'ft', 0, null, 5],
    ['STICKER (Colored)', 'STICKER GREEN', 'ft', 0, null, 5],
    ['STICKER (Colored)', 'STICKER GOLD',  'ft', 0, null, 5],
    ['STICKER (Colored)', 'STICKER SILVER','ft', 0, null, 5],
    ['STICKER (Colored)', 'STICKER YELLOW','ft', 0, null, 5],
];

// Build item ID map for seeding service_material_rules
$itemMap = []; // name => id

foreach ($items as [$catName, $itemName, $uom, $trackByRoll, $rollLen, $reorder]) {
    $catId = $catMap[$catName] ?? null;
    if (!$catId) { seed_log("  SKIP (no category): $itemName"); continue; }

    $sql = "INSERT INTO inv_items (category_id, name, unit_of_measure, track_by_roll, default_roll_length_ft, reorder_level, status)
            VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE')
            ON DUPLICATE KEY UPDATE
                category_id = VALUES(category_id),
                unit_of_measure = VALUES(unit_of_measure),
                track_by_roll = VALUES(track_by_roll),
                default_roll_length_ft = VALUES(default_roll_length_ft),
                reorder_level = VALUES(reorder_level)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issidd", $catId, $itemName, $uom, $trackByRoll, $rollLen, $reorder);
    if ($stmt->execute()) {
        $itemId = $stmt->insert_id ?: null;
        seed_log("  Item: [$catName] $itemName ($uom) track_by_roll=$trackByRoll");
    } else {
        seed_log("  FAILED: $itemName — " . $stmt->error);
    }
    $stmt->close();
}

// Refresh item IDs from DB
$res = $conn->query("SELECT id, name FROM inv_items");
while ($row = $res->fetch_assoc()) {
    $itemMap[$row['name']] = (int)$row['id'];
}

// ============================================================
// SAMPLE ROLLS (one open roll per tarpaulin width + each sticker type)
// Admin can add more via the UI
// ============================================================
seed_log("\n--- Seeding Sample Rolls ---");

$rollItems = [
    '3FT Tarpaulin'    => [164.00, 164.00],
    '4FT Tarpaulin'    => [164.00, 164.00],
    '5FT Tarpaulin'    => [164.00, 164.00],
    '6FT Tarpaulin'    => [164.00, 164.00],
    '3M REFLECTIVE'    => [164.00, 164.00],
    'NEXJET'           => [164.00, 164.00],
    'TRANSPARENT'      => [164.00, 164.00],
    'GLOSS LAMINATE'   => [164.00, 164.00],
    'MATTE LAMINATE'   => [164.00, 164.00],
    'HOLOGRAM'         => [164.00, 164.00],
    'PP STKR MATTE 98' => [164.00, 164.00],
];

foreach ($rollItems as $itemName => [$total, $remaining]) {
    $itemId = $itemMap[$itemName] ?? null;
    if (!$itemId) { seed_log("  SKIP roll (item not found): $itemName"); continue; }

    // Check if a roll already exists for this item
    $check = $conn->prepare("SELECT id FROM inv_rolls WHERE item_id = ? AND status = 'OPEN' LIMIT 1");
    $check->bind_param("i", $itemId);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing) {
        seed_log("  Roll exists for: $itemName — skipping");
        continue;
    }

    $sql = "INSERT INTO inv_rolls (item_id, total_length_ft, remaining_length_ft, status, received_at)
            VALUES (?, ?, ?, 'OPEN', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("idd", $itemId, $total, $remaining);
    if ($stmt->execute()) {
        seed_log("  Roll seeded: $itemName ({$total} ft)");
    } else {
        seed_log("  FAILED roll: $itemName — " . $stmt->error);
    }
    $stmt->close();
}

// ============================================================
// SERVICE → MATERIAL RULES
// ============================================================
seed_log("\n--- Seeding Service Material Rules ---");

// Truncate and re-seed (safe for idempotent re-run)
$conn->query("DELETE FROM service_material_rules");

$rules = [
    // [service_type, item_name, rule_type]

    // Tarpaulin Printing — needs ANY open tarpaulin roll
    ['Tarpaulin Printing', '3FT Tarpaulin',  'REQUIRED'],
    ['Tarpaulin Printing', '4FT Tarpaulin',  'REQUIRED'],
    ['Tarpaulin Printing', '5FT Tarpaulin',  'REQUIRED'],
    ['Tarpaulin Printing', '6FT Tarpaulin',  'REQUIRED'],
    // Inks optional
    ['Tarpaulin Printing', 'INK TARP BLUE',   'OPTIONAL'],
    ['Tarpaulin Printing', 'INK TARP RED',    'OPTIONAL'],
    ['Tarpaulin Printing', 'INK TARP BLACK',  'OPTIONAL'],
    ['Tarpaulin Printing', 'INK TARP YELLOW', 'OPTIONAL'],

    // T-shirt Printing — needs vinyl or tshirt stock
    ['T-shirt Printing', 'TSHIRT WHITE', 'REQUIRED'],
    ['T-shirt Printing', 'TSHIRT BLACK', 'REQUIRED'],
    ['T-shirt Printing', 'TSHIRT GRAY',  'REQUIRED'],
    ['T-shirt Printing', 'TSHIRT BLUE',  'REQUIRED'],
    ['T-shirt Printing', 'VINYL BLUE',   'OPTIONAL'],
    ['T-shirt Printing', 'VINYL WHITE',  'OPTIONAL'],
    ['T-shirt Printing', 'VINYL BLACK',  'OPTIONAL'],

    // Decals/Stickers — NEXJET base or hologram
    ['Decals/Stickers (Print/Cut)', 'NEXJET',        'REQUIRED'],
    ['Decals/Stickers (Print/Cut)', 'GLOSS LAMINATE','OPTIONAL'],
    ['Decals/Stickers (Print/Cut)', 'MATTE LAMINATE','OPTIONAL'],
    ['Decals/Stickers (Print/Cut)', 'HOLOGRAM',      'OPTIONAL'],

    // Glass Stickers / Wall / Frosted
    ['Glass Stickers / Wall / Frosted Stickers', 'MATTE LAMINATE',  'REQUIRED'],
    ['Glass Stickers / Wall / Frosted Stickers', 'GLOSS LAMINATE',  'REQUIRED'],

    // Transparent Stickers
    ['Transparent Stickers', 'TRANSPARENT', 'REQUIRED'],

    // Layouts — no material required (always available)
    // (We simply DON'T add rules for this service; availability logic returns true for 0 rules)

    // Reflectorized
    ['Reflectorized (Subdivision Stickers/Signages)', '3M REFLECTIVE', 'REQUIRED'],

    // Stickers on Sintraboard
    ['Stickers on Sintraboard', 'PP STKR MATTE 98', 'REQUIRED'],
    ['Stickers on Sintraboard', 'NEXJET',            'OPTIONAL'],

    // Sintraboard Standees
    ['Sintraboard Standees', 'PP STKR MATTE 98', 'REQUIRED'],
    // Plate variants as optional
    ['Sintraboard Standees', 'AC MC',   'OPTIONAL'],
    ['Sintraboard Standees', 'SP MC',   'OPTIONAL'],

    // Souvenirs — MUG or BOX MUG
    ['Souvenirs', 'MUG',     'REQUIRED'],
    ['Souvenirs', 'BOX MUG', 'REQUIRED'],
];

$ruleInsert = $conn->prepare(
    "INSERT INTO service_material_rules (service_type, item_id, rule_type) VALUES (?, ?, ?)"
);

foreach ($rules as [$service, $itemName, $ruleType]) {
    $itemId = $itemMap[$itemName] ?? null;
    if (!$itemId) { seed_log("  SKIP rule (item not found): $itemName for $service"); continue; }

    $ruleInsert->bind_param("sis", $service, $itemId, $ruleType);
    if ($ruleInsert->execute()) {
        seed_log("  Rule: [$service] → $itemName ($ruleType)");
    } else {
        seed_log("  FAIL rule: [$service] → $itemName — " . $ruleInsert->error);
    }
}
$ruleInsert->close();

seed_log("\n==============================================");
seed_log("SEED COMPLETE.");
seed_log("==============================================");
