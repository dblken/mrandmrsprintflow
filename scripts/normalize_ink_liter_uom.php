<?php
require_once __DIR__ . '/../includes/db.php';

$targets = ['INK L120', 'INK L130'];
$placeholders = implode(',', array_fill(0, count($targets), '?'));
$types = str_repeat('s', count($targets));

$updatedCategories = db_execute(
    "UPDATE inv_categories
     SET default_uom = 'l'
     WHERE UPPER(TRIM(name)) IN ($placeholders)",
    $types,
    $targets
);

$updatedItems = db_execute(
    "UPDATE inv_items i
     INNER JOIN inv_categories c ON c.id = i.category_id
     SET i.unit_of_measure = 'l'
     WHERE UPPER(TRIM(c.name)) IN ($placeholders)",
    $types,
    $targets
);

echo "Updated ink category UOM to liters.\n";
echo "Category rows affected: " . (int)$updatedCategories . "\n";
echo "Item rows affected: " . (int)$updatedItems . "\n";
