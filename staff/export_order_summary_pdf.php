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

$staffId = $_SESSION['user_id'] ?? 0;
$staffData = db_query("SELECT first_name, last_name, email, contact_number, branch_id FROM users WHERE user_id = ?", 'i', [$staffId])[0] ?? [];
$staffName = trim(($staffData['first_name'] ?? '') . ' ' . ($staffData['last_name'] ?? '')) ?: 'Staff Account';
$staffEmail = $staffData['email'] ?? '';
$staffContact = $staffData['contact_number'] ?? '';

$staffBranchId = printflow_branch_filter_for_user() ?? (int)($staffData['branch_id'] ?? 1);
$branchInfo = db_query("SELECT * FROM branches WHERE id = ?", 'i', [$staffBranchId])[0] ?? [];
$branchName = $branchInfo['branch_name'] ?? 'Mr. and Mrs. Print Main';
$branchAddress = trim(($branchInfo['address'] ?? '') . ' ' . ($branchInfo['address_line'] ?? '') . ' ' . ($branchInfo['barangay'] ?? '') . ' ' . ($branchInfo['city'] ?? '') . ' ' . ($branchInfo['province'] ?? ''));
if (empty($branchAddress)) {
    $branchAddress = "#240 corner M.L. Quezon St., Cabuyao, Philippines, 4025";
}
$branchContact = $branchInfo['contact_number'] ?? '0921 212 2293';
$branchEmail = $branchInfo['email'] ?? 'mrandmrsprints@gmail.com';

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

$sessionDate = date('F d, Y');
$generatedAt = date('M d, Y g:i A');

// Logo path
$logoPath = __DIR__ . '/../public/images/logo.jpg';
$logoData = '';
if (file_exists($logoPath)) {
    $type = pathinfo($logoPath, PATHINFO_EXTENSION);
    $data = file_get_contents($logoPath);
    $logoData = 'data:image/' . $type . ';base64,' . base64_encode($data);
}

$rowsHtml = '';
if (empty($orders)) {
    $rowsHtml = '<tr><td colspan="6" style="text-align:center;padding:18px;color:#64748b;">No records found.</td></tr>';
} else {
    foreach ($orders as $o) {
        $rowsHtml .= '<tr>'
            . '<td style="text-align:center;">' . (int)$o['order_id'] . '</td>'
            . '<td style="word-wrap: break-word; overflow-wrap: break-word;">' . htmlspecialchars((string)$o['customer_name']) . '</td>'
            . '<td style="word-wrap: break-word; overflow-wrap: break-word;">' . htmlspecialchars((string)$o['service_type']) . '</td>'
            . '<td style="text-align:center;">' . htmlspecialchars((string)$o['status']) . '</td>'
            . '<td style="text-align:center;">' . date('Y-m-d', strtotime((string)$o['order_date'])) . '</td>'
            . '<td style="text-align:right;">' . number_format((float)$o['total_amount'], 2) . '</td>'
            . '</tr>';
    }
}

$html = '
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px 40px 110px 40px; }
        body { font-family: "Helvetica", "Arial", sans-serif; font-size: 10px; color: #333; margin: 0; padding: 0; line-height: 1.3; }
        
        .header { width: 100%; border-bottom: 1.2px solid #000; padding-bottom: 8px; margin-bottom: 12px; }
        .header-table { width: 100%; border: none; }
        .header-table td { border: none; vertical-align: middle; }
        .logo { width: 60px; height: auto; }
        .business-details { text-align: center; padding-right: 60px; }
        .business-details h1 { margin: 0; font-size: 16px; color: #000; font-weight: bold; }
        .business-details p { margin: 1px 0; font-size: 9px; color: #333; }

        .report-title { color: #0047AB; font-size: 16px; font-weight: bold; margin-bottom: 4px; }
        .meta-info { margin-bottom: 12px; font-size: 10px; line-height: 1.4; }
        
        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; table-layout: fixed; }
        .summary-table td { border: 1.2px solid #000; padding: 5px 10px; font-size: 10px; }
        .summary-label { background-color: #f8fafc; font-weight: bold; width: 22%; }
        .summary-value { text-align: center; width: 28%; font-weight: bold; }
        .summary-total-label { background-color: #f8fafc; font-weight: bold; width: 25%; }
        .summary-total-value { text-align: right; width: 25%; font-weight: bold; font-size: 11px; }

        .orders-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .orders-table th { background-color: #0047AB; color: #ffffff; padding: 6px; border: 1.2px solid #000; font-weight: bold; text-transform: uppercase; font-size: 9px; text-align: center; }
        .orders-table td { border: 1.2px solid #000; padding: 6px; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; }
        .orders-table tr:nth-child(even) { background-color: #f9f9f9; }

        footer { position: fixed; bottom: -90px; left: 0px; right: 0px; height: 90px; border-top: 1px solid #ccc; padding-top: 8px; font-size: 9px; color: #444; }
        .footer-table { width: 100%; border: none; }
        .footer-table td { border: none; padding: 0; vertical-align: top; line-height: 1.3; }
        .footer-right { text-align: right; }
        .rev-code { font-weight: bold; margin-top: 5px; font-size: 9px; }
    </style>
</head>
<body>

<div class="header">
    <table class="header-table">
        <tr>
            <td style="width: 70px;">
                <img src="' . $logoData . '" class="logo">
            </td>
            <td class="business-details">
                <h1>' . htmlspecialchars($branchName) . '</h1>
                <p>' . htmlspecialchars($branchAddress) . '</p>
                <p>Contact: ' . htmlspecialchars($branchContact) . ' | Email: ' . htmlspecialchars($branchEmail) . '</p>
                <p>Facebook: ' . htmlspecialchars($branchName) . '</p>
            </td>
        </tr>
    </table>
</div>

<footer>
    <table class="footer-table">
        <tr>
            <td>
                ' . htmlspecialchars($branchName) . '<br>
                ' . htmlspecialchars($branchAddress) . '<br>
                Website: mrandmrsprintflow.com<br>
                Prepared By: ' . htmlspecialchars($staffName) . ' | Branch: ' . htmlspecialchars($branchName) . '<br>
                Contact: ' . htmlspecialchars($staffContact) . ' | Email: ' . htmlspecialchars($staffEmail) . '
            </td>
            <td class="footer-right">
                Generated On: ' . $generatedAt . ' | <span id="page-placeholder"></span>
                <div class="rev-code">PF-REP-SUMMARY-01-Rev00</div>
            </td>
        </tr>
    </table>
</footer>

<div class="report-title">Order Summary Details</div>
<div class="meta-info">
    Session Date: ' . $sessionDate . '<br>
    Staff Account: ' . htmlspecialchars($staffName) . '<br>
    Contact Info: ' . htmlspecialchars($staffContact) . ' | ' . htmlspecialchars($staffEmail) . '
</div>

<table class="summary-table">
    <tr>
        <td class="summary-label">TOTAL ORDERS</td>
        <td class="summary-value">' . count($orders) . '</td>
        <td class="summary-total-label">TOTAL GROSS SALES</td>
        <td class="summary-total-value">₱ ' . number_format($grandTotal, 2) . '</td>
    </tr>
</table>

<table class="orders-table">
    <thead>
        <tr>
            <th style="width: 8%;">Order #</th>
            <th style="width: 27%;">Customer Name</th>
            <th style="width: 25%;">Service Type</th>
            <th style="width: 15%;">Status</th>
            <th style="width: 13%;">Date Created</th>
            <th style="width: 12%;">Amount</th>
        </tr>
    </thead>
    <tbody>
        ' . $rowsHtml . '
    </tbody>
</table>

</body>
</html>';

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
    
    $canvas = $dompdf->getCanvas();
    $font = $dompdf->getFontMetrics()->get_font("helvetica", "normal");
    $size = 9;
    
    // Explicitly place page text using canvas to ensure it is visible and properly positioned
    $canvas->page_text(460, $canvas->get_height() - 40, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, $size, array(0.2, 0.2, 0.2));

    $file = 'order_summary_' . strtolower($range) . '_' . strtolower($status_filter === 'ALL' ? 'all' : preg_replace('/\s+/', '_', $status_filter)) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $file . '"');
    echo $dompdf->output();
    exit;
}

// Fallback
header('Content-Type: text/html; charset=utf-8');
echo $html;
exit;





