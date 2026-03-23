<?php
/**
 * Stock Card API - Returns rolls, ledger, usage stats for inventory item view
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/InventoryManager.php';

require_role(['Admin', 'Manager']);
header('Content-Type: application/json; charset=utf-8');

$item_id = (int)($_GET['item_id'] ?? 0);
if (!$item_id) {
    echo json_encode(['success' => false, 'message' => 'item_id required']);
    exit;
}

$item = InventoryManager::getItem($item_id);
if (!$item) {
    echo json_encode(['success' => false, 'message' => 'Item not found']);
    exit;
}

$uom = strtolower($item['unit_of_measure'] ?? 'pcs');
$isPcs = ($uom === 'pcs');

function fmtQty($val, $isPcs) {
    return $isPcs ? (string)(int)$val : number_format((float)$val, 2);
}

// Rolls (for roll-based items)
$rolls = [];
if ($item['track_by_roll']) {
    $rollRows = db_query("SELECT id, roll_code, total_length_ft as original_length, remaining_length_ft as current_length FROM inv_rolls WHERE item_id = ? AND status = 'OPEN' ORDER BY received_at ASC", 'i', [$item_id]);
    foreach ($rollRows ?: [] as $r) {
        $rolls[] = [
            'roll_code'       => $r['roll_code'],
            'original_length' => (float)$r['original_length'],
            'current_length'  => (float)$r['current_length'],
        ];
    }
}

// Ledger (recent 10 with running balance) - fetch chronological, build balance
$txnsAsc = db_query("SELECT id, direction, ref_type, quantity, transaction_date FROM inventory_transactions WHERE item_id = ? ORDER BY transaction_date ASC, id ASC", 'i', [$item_id]) ?: [];
$running = 0;
$ledgerAsc = [];
foreach ($txnsAsc as $t) {
    $qty = (float)$t['quantity'];
    $running += ($t['direction'] === 'IN' ? $qty : -$qty);
    $ledgerAsc[] = [
        'id' => $t['id'],
        'transaction_date' => $t['transaction_date'],
        'direction' => $t['direction'],
        'ref_type' => $t['ref_type'] ?? $t['direction'],
        'quantity' => $qty,
        'balance_after' => $running,
    ];
}
$ledger = array_slice(array_reverse($ledgerAsc), 0, 5);

// Map ref_type to display Action
$actionMap = [
    'purchase' => 'Stock In',
    'stock_in' => 'Stock In',
    'opening_balance' => 'Stock In',
    'return' => 'Stock In',
    'transfer_in' => 'Stock In',
    'adjustment_up' => 'Adjustment',
    'joborder' => 'Stock Out',
    'stock_out' => 'Stock Out',
    'transfer_out' => 'Stock Out',
    'adjustment_down' => 'Adjustment',
];
foreach ($ledger as &$l) {
    $key = strtolower($l['ref_type'] ?? '');
    $l['action_display'] = $actionMap[$key] ?? ucfirst(str_replace('_', ' ', $l['ref_type'] ?? 'Movement'));
}
unset($l);

// Usage stats for chart (last 7 days)
$usageLabels = [];
$usageValues = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $usageLabels[] = date('M j', strtotime($d));
    $row = db_query("SELECT COALESCE(SUM(quantity), 0) as outq FROM inventory_transactions WHERE item_id = ? AND direction = 'OUT' AND DATE(transaction_date) = ?", 'is', [$item_id, $d]);
    $usageValues[] = (float)($row[0]['outq'] ?? 0);
}
$usage_stats = ['labels' => $usageLabels, 'values' => $usageValues];

echo json_encode([
    'success'    => true,
    'rolls'      => $rolls,
    'ledger'     => $ledger,
    'usage_stats' => $usage_stats,
    'is_pcs'     => $isPcs,
]);
