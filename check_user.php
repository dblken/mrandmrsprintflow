<?php
require_once __DIR__ . '/includes/db.php';
$email = 'admin@printflow.com';
$res = $conn->query("SELECT * FROM users WHERE email = '$email'");
if ($row = $res->fetch_assoc()) {
    echo "User found: " . $row['email'] . "\n";
    echo "Role: " . $row['role'] . "\n";
    echo "Status: " . ($row['status'] ?? 'N/A') . "\n";
    // Check ifpassword 'password' works
    if (password_verify('password', $row['password_hash'])) {
        echo "Password 'password' logic check: MATCH\n";
    } else {
        echo "Password 'password' logic check: NO MATCH\n";
    }
    // Check if 'admin123' works (which I set earlier)
    if (password_verify('admin123', $row['password_hash'])) {
        echo "Password 'admin123' logic check: MATCH\n";
    } else {
        echo "Password 'admin123' logic check: NO MATCH\n";
    }
} else {
    echo "User not found: $email\n";
}
