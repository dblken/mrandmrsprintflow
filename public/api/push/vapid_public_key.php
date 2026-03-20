<?php
/**
 * vapid_public_key.php — Return the VAPID public key to the front-end.
 * The public key is not sensitive; the private key stays on the server.
 */
header('Content-Type: application/json');

$cfg_file = __DIR__ . '/../../../includes/vapid_config.php';
$pub = '';
if (file_exists($cfg_file)) {
    $cfg = require $cfg_file;
    $pub = $cfg['public_key'] ?? '';
}

echo json_encode(['public_key' => $pub]);
