<?php
/**
 * vapid_public_key.php — Return the VAPID public key to the front-end.
 * The public key is not sensitive; the private key stays on the server.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/vapid_bootstrap.php';

$cfg = printflow_vapid_config();
$pub = $cfg['public_key'] ?? '';

echo json_encode(['public_key' => $pub]);
