<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP is working!<br>";
echo "Current directory: " . __DIR__ . "<br>";

require_once __DIR__ . '/../includes/auth.php';
echo "Auth loaded!<br>";

require_once __DIR__ . '/../includes/functions.php';
echo "Functions loaded!<br>";

require_once __DIR__ . '/../includes/branch_context.php';
echo "Branch context loaded!<br>";

echo "All includes loaded successfully!";
