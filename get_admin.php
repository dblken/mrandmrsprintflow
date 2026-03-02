<?php
require_once __DIR__ . '/includes/db.php';
print_r(db_query("SELECT email, role FROM users WHERE role = 'Admin' OR role = 'Superadmin' LIMIT 1"));
?>
