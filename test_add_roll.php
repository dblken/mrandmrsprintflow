<?php
require_once __DIR__ . '/includes/db.php';
echo "Testing add roll manually...\n";
try {
    $item_id = 15; // 5FT Tarpaulin
    $roll_code = 'TEST-ROLL-' . time();
    $width = 5;
    $length = 164;
    
    $stmt = $conn->prepare("INSERT INTO inv_rolls (item_id, roll_code, width_ft, total_length_ft, remaining_length_ft) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo "Prepare FAILED: " . $conn->error . "\n";
        exit(1);
    }
    $stmt->bind_param("isidd", $item_id, $roll_code, $width, $length, $length);
    if ($stmt->execute()) {
        echo "Success! Roll added.\n";
    } else {
        echo "Execute FAILED: " . $stmt->error . "\n";
    }
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
