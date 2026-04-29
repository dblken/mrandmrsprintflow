<?php
/**
 * Background push queue worker.
 *
 * Run via scheduler:
 * php cron/process_push_queue.php
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/push_queue_helper.php';
require_once __DIR__ . '/../includes/push_debug_helper.php';

$summary = printflow_process_push_queue(50);
printflow_push_debug_log('push_queue_cli_processed', $summary);

echo "[push_queue] processed={$summary['processed']} sent={$summary['sent']} retry={$summary['retry']} failed={$summary['failed']} no_subscription={$summary['no_subscription']}\n";
