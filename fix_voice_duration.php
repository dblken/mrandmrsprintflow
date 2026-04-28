<?php
/**
 * Fix Voice Message Duration Issue
 * Adds duration column and fixes voice message duration calculation
 */

require_once __DIR__ . '/includes/db.php';

echo "Fixing voice message duration issue...\n";

// 1. Add duration column to order_messages table
echo "Adding duration column to order_messages table...\n";
$add_duration_sql = "ALTER TABLE order_messages ADD COLUMN duration FLOAT DEFAULT NULL AFTER file_name";
try {
    $conn->query($add_duration_sql);
    echo "✓ Duration column added successfully\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "✓ Duration column already exists\n";
    } else {
        echo "✗ Error adding duration column: " . $e->getMessage() . "\n";
    }
}

// 2. Update existing voice messages with estimated duration based on file size
echo "Updating existing voice messages with estimated duration...\n";
$voice_messages = db_query("SELECT id, file_path, message_file FROM order_messages WHERE message_type = 'voice' AND (duration IS NULL OR duration = 0)");

$updated_count = 0;
foreach ($voice_messages as $msg) {
    $file_path = $msg['file_path'] ?: $msg['message_file'];
    if (!$file_path) continue;
    
    // Try to get actual file path
    $full_path = __DIR__ . '/uploads/chat/audio/' . basename($file_path);
    if (!file_exists($full_path)) {
        $full_path = __DIR__ . '/' . ltrim($file_path, '/');
    }
    
    $estimated_duration = 0;
    if (file_exists($full_path)) {
        $file_size = filesize($full_path);
        // Estimate duration: WebM audio is roughly 16KB per second at default quality
        $estimated_duration = max(1, round($file_size / 16000, 1));
    } else {
        // Fallback: estimate 3 seconds for missing files
        $estimated_duration = 3.0;
    }
    
    db_execute("UPDATE order_messages SET duration = ? WHERE id = ?", 'di', [$estimated_duration, $msg['id']]);
    $updated_count++;
}

echo "✓ Updated $updated_count voice messages with estimated duration\n";

echo "\nVoice message duration fix completed!\n";
echo "Next steps:\n";
echo "1. The send_voice.php API has been updated to calculate duration\n";
echo "2. The chat interfaces will now display proper durations\n";
echo "3. New voice messages will have accurate durations\n";
?>