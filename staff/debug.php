<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>Staff Dashboard Debug</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Current Directory: " . __DIR__ . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";

echo "<h2>Testing Includes:</h2>";

try {
    echo "<p>Loading auth.php...</p>";
    require_once __DIR__ . '/../includes/auth.php';
    echo "<p style='color:green;'>✓ auth.php loaded</p>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>✗ auth.php failed: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}

try {
    echo "<p>Loading functions.php...</p>";
    require_once __DIR__ . '/../includes/functions.php';
    echo "<p style='color:green;'>✓ functions.php loaded</p>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>✗ functions.php failed: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}

try {
    echo "<p>Loading branch_context.php...</p>";
    require_once __DIR__ . '/../includes/branch_context.php';
    echo "<p style='color:green;'>✓ branch_context.php loaded</p>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>✗ branch_context.php failed: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}

echo "<h2>All includes loaded successfully!</h2>";
echo "<p>Session user type: " . ($_SESSION['user_type'] ?? 'not set') . "</p>";
echo "<p>Session user ID: " . ($_SESSION['user_id'] ?? 'not set') . "</p>";
