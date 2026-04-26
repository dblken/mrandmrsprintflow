<?php
/**
 * Compatibility DB include.
 *
 * Some production endpoints may expect the DB config at /config/db.php.
 * The canonical connection lives at /includes/db.php.
 */

require_once __DIR__ . '/../includes/db.php';

