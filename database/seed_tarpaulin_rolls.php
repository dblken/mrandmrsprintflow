<?php
/**
 * Seed Test Tarpaulin Rolls
 */
require_once __DIR__ . '/../includes/db.php';

$rolls = [
    ['item_id' => 2, 'roll_code' => 'RL3-1001', 'width_ft' => 3, 'length' => 164.00, 'cost' => 1500],
    ['item_id' => 2, 'roll_code' => 'RL3-1002', 'width_ft' => 3, 'length' => 164.00, 'cost' => 1500],
    ['item_id' => 2, 'roll_code' => 'RL3-1003', 'width_ft' => 3, 'length' => 80.50, 'cost' => 1500] // Partially used
];

foreach ($rolls as $r) {
    $sql = "INSERT INTO inv_tarp_rolls (item_id, roll_code, width_ft, total_length_ft, remaining_length_ft, unit_cost) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE roll_code = roll_code";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isiddd", $r['item_id'], $r['roll_code'], $r['width_ft'], $r['length'], $r['length'], $r['cost']);
    if ($stmt->execute()) {
        echo "Inserted roll: {$r['roll_code']}\n";
    } else {
        echo "Error: " . $stmt->error . "\n";
    }
    $stmt->close();
}

echo "Seeding complete.\n";
?>
