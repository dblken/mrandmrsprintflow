<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'includes/db.php';
$o = db_query("SELECT * FROM orders WHERE order_id = 2255");
$items = db_query("SELECT * FROM order_items WHERE order_id = 2255");
$jobs = db_query("SELECT * FROM job_orders WHERE order_id = 2255");
echo "ORDER:\n"; print_r($o);
echo "ITEMS:\n"; print_r($items);
echo "JOBS:\n"; print_r($jobs);
