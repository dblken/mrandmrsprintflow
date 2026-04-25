<?php
/**
 * Export Order Summary to PDF
 * PrintFlow - Staff Reports
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';

$userType = $_SESSION['user_type'] ?? '';
if (!in_array($userType, ['Staff', 'Admin', 'Manager'], true)) {
    http_response_code(403);
    die('Unauthorized access.');
}

$staffBranchId = null;
if (in_array($userType, ['Staff', 'Manager'], true)) {
    $staffBranchId = printflow_branch_filter_for_user() ?? (int)($_SESSION['branch_id'] ?? 1);
}

$range = $_GET['range'] ?? 'week';
$status_filter = $_GET['status'] ?? 'ALL';

if ($range === 'month') {
    $date_condition = "YEAR(o.order_date) = YEAR(CURDATE()) AND MONTH(o.order_date) = MONTH(CURDATE())";
    $range_label = 'This Month';
} elseif ($range === 'today') {
    $date_condition = "DATE(o.order_date) = CURDATE()";
    $range_label = 'Today';
} else {
    $range = 'week';
    $date_condition = "YEARWEEK(o.order_date, 1) = YEARWEEK(CURDATE(), 1)";
    $range_label = 'This Week';
}

$sql = "
    SELECT
        o.order_id,
        COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), 'Guest') AS customer_name,
        COALESCE((SELECT COALESCE(p.name, 'Custom Service')
                  FROM order_items oi
                  LEFT JOIN products p ON oi.product_id = p.product_id
                  WHERE oi.order_id = o.order_id
                  LIMIT 1), 'General') AS service_type,
        o.order_date,
        o.total_amount,
        o.status
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    WHERE {$date_condition}
";

$params = [];
$types = '';

if ($staffBranchId !== null) {
    $sql .= " AND o.branch_id = ?";
    $params[] = $staffBranchId;
    $types .= 'i';
}

if ($status_filter !== 'ALL' && $status_filter !== '') {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$sql .= " ORDER BY o.order_date DESC";

$orders = db_query($sql, $types ?: null, $params ?: null) ?: [];
$grandTotal = 0.0;
foreach ($orders as $row) {
    $grandTotal += (float)($row['total_amount'] ?? 0);
}

$title = 'PrintFlow Order Summary';
$subtitle = $range_label . ' • Status: ' . ($status_filter ?: 'ALL');
$generatedAt = date('Y-m-d H:i:s');

$rowsHtml = '';
if (empty($orders)) {
    $rowsHtml = '<tr><td colspan="6" style="text-align:center;padding:18px;color:#64748b;">No records found.</td></tr>';
} else {
    foreach ($orders as $o) {
        $rowsHtml .= '<tr>'
            . '<td>#' . (int)$o['order_id'] . '</td>'
            . '<td>' . htmlspecialchars((string)$o['customer_name']) . '</td>'
            . '<td>' . htmlspecialchars((string)$o['service_type']) . '</td>'
            . '<td>' . htmlspecialchars(date('Y-m-d H:i', strtotime((string)$o['order_date']))) . '</td>'
            . '<td style="text-align:right;">' . number_format((float)$o['total_amount'], 2) . '</td>'
            . '<td>' . htmlspecialchars((string)$o['status']) . '</td>'
            . '</tr>';
    }
}

$html = '<!doctype html><html><head><meta charset="utf-8"><style>
body{font-family:Arial,sans-serif;color:#0f172a;font-size:12px;}
h1{font-size:20px;margin:0 0 4px;}
.meta{margin-bottom:14px;color:#475569;}
table{width:100%;border-collapse:collapse;margin-top:8px;}
th,td{border:1px solid #cbd5e1;padding:8px;vertical-align:top;}
th{background:#0f766e;color:#fff;text-align:left;}
.summary{margin-top:12px;font-weight:700;}
</style></head><body>'
    . '<h1>' . htmlspecialchars($title) . '</h1>'
    . '<div class="meta">' . htmlspecialchars($subtitle) . '<br>Generated: ' . htmlspecialchars($generatedAt) . '</div>'
    . '<table><thead><tr><th>Order #</th><th>Customer</th><th>Service</th><th>Date</th><th>Total (PHP)</th><th>Status</th></tr></thead><tbody>'
    . $rowsHtml
    . '</tbody></table>'
    . '<div class="summary">Grand Total: PHP ' . number_format($grandTotal, 2) . '</div>'
    . '</body></html>';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

if (class_exists(\Dompdf\Dompdf::class)) {
    $dompdf = new \Dompdf\Dompdf([
        'isRemoteEnabled' => true,
        'isHtml5ParserEnabled' => true,
    ]);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $file = 'order_summary_' . strtolower($range) . '_' . strtolower($status_filter === 'ALL' ? 'all' : preg_replace('/\s+/', '_', $status_filter)) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    echo $dompdf->output();
    exit;
}

// Graceful fallback if PDF dependency is not available.
header('Content-Type: text/html; charset=utf-8');
echo $html;
exit;

