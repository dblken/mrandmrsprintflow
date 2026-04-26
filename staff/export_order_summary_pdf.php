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

$staffBranchId = printflow_branch_filter_for_user() ?? (int)($_SESSION['branch_id'] ?? 1);
$branchInfo = db_query("SELECT * FROM branches WHERE id = ?", 'i', [$staffBranchId])[0] ?? [];
$branchName = $branchInfo['branch_name'] ?? 'Mr. and Mrs. Print';
$branchAddress = trim(($branchInfo['address'] ?? '') . ' ' . ($branchInfo['address_line'] ?? '') . ' ' . ($branchInfo['barangay'] ?? '') . ' ' . ($branchInfo['city'] ?? '') . ' ' . ($branchInfo['province'] ?? ''));
$branchContact = $branchInfo['contact_number'] ?? '';
$branchEmail = $branchInfo['email'] ?? '';

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

$staffName = $_SESSION['user_name'] ?? 'Staff Account';
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
            . '<td>' . htmlspecialchars((string)$o['customer_name']) . '</td>'
            . '<td>' . htmlspecialchars((string)$o['service_type']) . '</td>'
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
        @page { margin: 100px 25px 50px 25px; }
        header { position: fixed; top: -80px; left: 0px; right: 0px; height: 100px; text-align: center; border-bottom: 2px solid #000; }
        footer { position: fixed; bottom: -30px; left: 0px; right: 0px; height: 50px; border-top: 1px solid #ccc; font-size: 10px; color: #444; }
        body { font-family: "Helvetica", "Arial", sans-serif; font-size: 11px; color: #333; margin-top: 20px; }
        
        .header-table { width: 100%; border: none; }
        .header-table td { border: none; vertical-align: middle; }
        .logo-container { width: 80px; }
        .logo { width: 70px; height: auto; }
        .business-details { text-align: center; }
        .business-details h1 { margin: 0; font-size: 18px; color: #000; font-weight: bold; }
        .business-details p { margin: 1px 0; font-size: 10px; color: #333; }

        .report-title { color: #0047AB; font-size: 18px; font-weight: bold; margin-top: 20px; margin-bottom: 5px; }
        .meta-info { margin-bottom: 20px; font-size: 12px; line-height: 1.5; }
        
        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .summary-table td { border: 1px solid #000; padding: 6px 10px; font-size: 12px; }
        .summary-label { background-color: #f8fafc; font-weight: bold; width: 25%; }
        .summary-value { text-align: center; width: 25%; font-weight: bold; }
        .summary-total-label { background-color: #f8fafc; font-weight: bold; width: 30%; }
        .summary-total-value { text-align: right; width: 20%; font-weight: bold; font-size: 13px; }

        .orders-table { width: 100%; border-collapse: collapse; }
        .orders-table th { background-color: #0047AB; color: #ffffff; padding: 8px; border: 1px solid #000; font-weight: bold; text-transform: uppercase; font-size: 10px; }
        .orders-table td { border: 1px solid #000; padding: 6px; vertical-align: middle; }
        .orders-table tr:nth-child(even) { background-color: #f2f2f2; }

        .footer-table { width: 100%; border: none; }
        .footer-table td { border: none; padding: 2px 0; }
        .footer-right { text-align: right; }
        .rev-code { font-weight: bold; margin-top: 5px; }
    </style>
</head>
<body>

<header>
    <table class="header-table">
        <tr>
            <td class="logo-container">
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
</header>

<footer>
    <table class="footer-table">
        <tr>
            <td>
                ' . htmlspecialchars($branchName) . '<br>
                ' . htmlspecialchars($branchAddress) . '<br>
                Website: mrandmrsprintflow.com<br>
                Prepared By: ' . htmlspecialchars($staffName) . ' | Branch: ' . htmlspecialchars($branchName) . '
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
    Staff Account: ' . htmlspecialchars($staffName) . '
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
            <th style="width: 10%;">Order #</th>
            <th style="width: 25%;">Customer Name</th>
            <th style="width: 20%;">Service Type</th>
            <th style="width: 15%;">Status</th>
            <th style="width: 15%;">Date Created</th>
            <th style="width: 15%;">Amount</th>
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
    $size = 10;
    // Position the page text at the bottom right, aligned with the rest of the footer
    $canvas->page_text(450, $canvas->get_height() - 48, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, $size, array(0.2, 0.2, 0.2));

    $file = 'order_summary_' . strtolower($range) . '_' . strtolower($status_filter === 'ALL' ? 'all' : preg_replace('/\s+/', '_', $status_filter)) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $file . '"');
    echo $dompdf->output();
    exit;
}


// Graceful fallback if PDF dependency is not available.
header('Content-Type: text/html; charset=utf-8');
echo $html;
exit;


