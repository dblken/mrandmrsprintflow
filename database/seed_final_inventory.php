<?php
/**
 * Final Inventory Seeding Script
 */
require_once __DIR__ . '/../includes/db.php';

echo "Seeding Final Inventory Categories and Items...\n";

$data = [
    'PLATE' => [
        'uom' => 'pcs', 'track' => 0,
        'items' => ['AC MC', 'AC PH', 'AC EURO', 'AC THAI', 'AC NMC', 'AC HOME', 'SP MC', 'SP PH', 'SP EURO', 'SP THAI', 'SP NMC', 'SP HOME']
    ],
    'TARPAULIN' => [
        'uom' => 'ft', 'track' => 1, 'length' => 164.00,
        'items' => ['3FT', '4FT', '5FT', '6FT']
    ],
    'PRINTED STKR' => [
        'uom' => 'ft', 'track' => 1, 'length' => 164.00,
        'items' => ['3M REFLECTIVE', 'NEXJET', 'TRANSPARENT', 'GLOSS LAMINATE', 'MATTE LAMINATE', 'HOLOGRAM', 'PP STKR MATTE 98']
    ],
    'INK TARP' => [
        'uom' => 'bottle', 'track' => 0,
        'items' => ['BLUE', 'RED', 'BLACK', 'YELLOW']
    ],
    'INK L120' => [
        'uom' => 'bottle', 'track' => 0,
        'items' => ['BLUE', 'RED', 'BLACK', 'YELLOW']
    ],
    'INK L130' => [
        'uom' => 'bottle', 'track' => 0,
        'items' => ['BLUE', 'RED', 'BLACK', 'YELLOW']
    ],
    'RUBBER / VINYL / TSHIRT' => [
        'uom' => 'pcs', 'track' => 0,
        'items' => ['PVC ID', 'MUG', 'BOX MUG', 'BLUE', 'RED', 'YELLOW', 'GREEN']
    ],
    'STICKER (Colored)' => [
        'uom' => 'ft', 'track' => 0,
        'items' => ['BLACK', 'WHITE', 'RED', 'BLUE']
    ]
];

foreach ($data as $catName => $setup) {
    // 1. Insert/Get Category
    $conn->query("INSERT IGNORE INTO inv_categories (name) VALUES ('$catName')");
    $catId = $conn->query("SELECT id FROM inv_categories WHERE name = '$catName'")->fetch_assoc()['id'];

    echo "Category: $catName (ID: $catId)\n";

    foreach ($setup['items'] as $itemName) {
        $uom = $setup['uom'];
        $track = $setup['track'];
        $len = isset($setup['length']) ? $setup['length'] : "NULL";

        $sql = "INSERT INTO inv_items (category_id, name, unit_of_measure, track_by_roll, default_roll_length_ft, status) 
                VALUES ($catId, '$itemName', '$uom', $track, $len, 'active')
                ON DUPLICATE KEY UPDATE unit_of_measure = VALUES(unit_of_measure), track_by_roll = VALUES(track_by_roll), default_roll_length_ft = VALUES(default_roll_length_ft)";
        
        if ($conn->query($sql)) {
            echo " - Item: $itemName\n";
        } else {
            echo " - Error: " . $conn->error . "\n";
        }
    }
}

echo "Seeding complete.\n";
?>
