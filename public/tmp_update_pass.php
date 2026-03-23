<?php
require_once __DIR__ . '/../includes/db.php';
$new_hash = password_hash('password123', PASSWORD_BCRYPT);
$res = db_execute("UPDATE users SET password_hash = ? WHERE email = ?", 'ss', [$new_hash, 'admin@printflow.com']);
if ($res) {
    echo "Successfully updated admin@printflow.com password to password123\n";
} else {
    echo "Failed to update password\n";
}
