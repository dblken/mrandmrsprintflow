<?php
// Mock environment for testing inventory_transactions_api.php
$_GET['action'] = 'get_transactions';
$_GET['item_id'] = '';
$_GET['type'] = '';
$_GET['start_date'] = date('Y-m-01');
$_GET['end_date'] = date('Y-m-t');

// Mock session
session_start();
$_SESSION['user_id'] = 1; 
$_SESSION['user_type'] = 'Admin';

try {
    include 'c:/xampp/htdocs/printflow/admin/inventory_transactions_api.php';
} catch (Throwable $e) {
    echo "CAUGHT FATAL: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
}
