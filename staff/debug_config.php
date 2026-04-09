<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Config Debug</h2>";

require_once __DIR__ . '/../config.php';

echo "<p>HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'not set') . "</p>";
echo "<p>BASE_PATH: '" . (defined('BASE_PATH') ? BASE_PATH : 'NOT DEFINED') . "'</p>";
echo "<p>BASE_URL: '" . (defined('BASE_URL') ? BASE_URL : 'NOT DEFINED') . "'</p>";
echo "<p>ASSET_PATH: '" . (defined('ASSET_PATH') ? ASSET_PATH : 'NOT DEFINED') . "'</p>";

echo "<h3>Expected on production:</h3>";
echo "<p>BASE_PATH should be: '' (empty string)</p>";
echo "<p>BASE_URL should be: '' (empty string)</p>";
