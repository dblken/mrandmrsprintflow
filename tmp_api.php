<?php
require 'includes/db.php';
$_SESSION['user_type'] = 'Admin';
$_SESSION['user_id'] = 1;
$_GET['action'] = 'get_regular_order';
$_GET['id'] = 2255;
require 'admin/job_orders_api.php';
