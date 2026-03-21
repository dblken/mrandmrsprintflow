<?php
/**
 * PrintFlow — Formatted Excel Export
 * Orders Status Report & Customers Report
 * Professional layout: alignment, column widths, proper date formatting
 */

error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/branch_context.php';
require_once __DIR__ . '/../vendor/autoload.php';

require_role(['Admin', 'Manager']);

$report   = $_GET['report'] ?? 'orders';
$from     = $_GET['from'] ?? date('Y-m-01');
$to       = $_GET['to'] ?? date('Y-m-d');
$branchId = isset($_GET['branch_id']) ? ($_GET['branch_id'] === 'all' ? 'all' : (int)$_GET['branch_id']) : 'all';

$from = date('Y-m-d', strtotime($from));
$to   = date('Y-m-d', strtotime($to));
$toEnd = $to . ' 23:59:59';

[$bSql, $bTypes, $bParams] = branch_where_parts('o', $branchId);
$branchName = 'All Branches';
if ($branchId !== 'all') {
    $branches = get_all_branches();
    foreach ($branches as $b) {
        if ((int)$b['id'] === (int)$branchId) { $branchName = $b['branch_name']; break; }
    }
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

if ($report === 'orders') {
    $sheet->setTitle('Orders Status Report');
    buildOrdersReport($sheet, $from, $to, $branchName, $branchId, $bSql, $bTypes, $bParams, $toEnd);
    $filename = 'PrintFlow_Orders_Status_' . date('Y-m-d') . '.xlsx';
} elseif ($report === 'customers') {
    $sheet->setTitle('Customers Report');
    buildCustomersReport($sheet, $from, $to, $branchName, $branchId);
    $filename = 'PrintFlow_Customers_' . date('Y-m-d') . '.xlsx';
} else {
    header('HTTP/1.1 400 Bad Request');
    exit('Excel export supports report=orders or report=customers only.');
}

// ─── COLUMN WIDTHS (apply to all) ───────────────────────────
$sheet->getColumnDimension('A')->setWidth(25);
$sheet->getColumnDimension('B')->setWidth(20);
$sheet->getColumnDimension('C')->setWidth(20);
$sheet->getColumnDimension('D')->setWidth(18);
$sheet->getColumnDimension('E')->setWidth(18);
$sheet->getColumnDimension('F')->setWidth(18);
$sheet->getColumnDimension('G')->setWidth(14);
$sheet->getColumnDimension('H')->setWidth(18);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

/**
 * Orders Status Report layout
 */
function buildOrdersReport($sheet, $from, $to, $branchName, $branchId, $bSql, $bTypes, $bParams, $toEnd) {
    $params = array_merge([$from, $toEnd], $bParams);

    $summary = db_query(
        "SELECT COUNT(*) as total_orders, SUM(o.total_amount) as total_revenue,
                AVG(o.total_amount) as avg_order_value
         FROM orders o WHERE o.order_date BETWEEN ? AND ?$bSql",
        'ss' . $bTypes, $params
    );
    $sum = $summary[0] ?? [];
    $grandTotalOrd = (int)($sum['total_orders'] ?? 0);
    $grandTotalRev = (float)($sum['total_revenue'] ?? 0);
    $avgOrderVal = (float)($sum['avg_order_value'] ?? 0);

    $status_counts = db_query(
        "SELECT o.status, COUNT(*) as cnt, SUM(o.total_amount) as total
         FROM orders o WHERE o.order_date BETWEEN ? AND ?$bSql
         GROUP BY o.status ORDER BY cnt DESC",
        'ss' . $bTypes, $params
    ) ?: [];

    $daily = db_query(
        "SELECT DATE(o.order_date) as day, COUNT(*) as cnt, SUM(o.total_amount) as total
         FROM orders o WHERE o.order_date BETWEEN ? AND ?$bSql
         GROUP BY DATE(o.order_date) ORDER BY day DESC",
        'ss' . $bTypes, $params
    ) ?: [];

    $row = 1;

    // ─── 1. TITLE (A1:H1) ───────────────────────────────────
    $sheet->setCellValue('A1', 'PrintFlow Sales & Analytics Report');
    $sheet->mergeCells('A1:H1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // ─── 2. METADATA (Rows 3–6) ─────────────────────────────
    $sheet->setCellValue('A3', 'Report Type');
    $sheet->setCellValue('B3', 'Orders Status Report');
    $sheet->setCellValue('A4', 'Branch');
    $sheet->setCellValue('B4', $branchName);
    $sheet->setCellValue('A5', 'Date Range');
    $sheet->setCellValue('B5', date('F j, Y', strtotime($from)) . ' – ' . date('F j, Y', strtotime($to)));
    $sheet->setCellValue('A6', 'Generated On');
    $sheet->setCellValue('B6', date('F j, Y, g:i A'));
    $sheet->getStyle('A3:A6')->getFont()->setBold(true);
    $sheet->getStyle('B3:B6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    // ─── 3. SUMMARY (Rows 8–11) ──────────────────────────────
    $row = 8;
    $sheet->setCellValue('A' . $row, 'SUMMARY');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11);
    $row++;

    $sheet->setCellValue('A' . $row, 'Total Orders');
    $sheet->setCellValue('B' . $row, $grandTotalOrd);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $row++;

    $sheet->setCellValue('A' . $row, 'Total Revenue');
    $sheet->setCellValue('B' . $row, $grandTotalRev);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    $row++;

    $sheet->setCellValue('A' . $row, 'Average Order');
    $sheet->setCellValue('B' . $row, $avgOrderVal);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    $row += 2;

    // ─── 4. STATUS BREAKDOWN TABLE (from row 13) ───────────────
    $statusStartRow = $row;
    $sheet->setCellValue('A' . $row, 'Status');
    $sheet->setCellValue('B' . $row, 'Total Orders');
    $sheet->setCellValue('C' . $row, 'Total Amount (₱)');
    $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
    $sheet->getStyle('A' . $row . ':C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A' . $row . ':C' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
    $row++;

    foreach ($status_counts as $sc) {
        $sheet->setCellValue('A' . $row, (string)($sc['status'] ?? ''));
        $sheet->setCellValue('B' . $row, (int)$sc['cnt']);
        $sheet->setCellValue('C' . $row, (float)$sc['total']);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('B' . $row . ':C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $row++;
    }

    // TOTAL row
    $sheet->setCellValue('A' . $row, 'TOTAL');
    $sheet->setCellValue('B' . $row, $grandTotalOrd);
    $sheet->setCellValue('C' . $row, $grandTotalRev);
    $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle('B' . $row . ':C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('A' . $row . ':C' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
    $statusEndRow = $row;
    $row += 2;

    // Borders for Status table
    $sheet->getStyle('A' . $statusStartRow . ':C' . $statusEndRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // ─── 5. DAILY ORDER SUMMARY TABLE ────────────────────────
    $dailyStartRow = $row;
    $sheet->setCellValue('A' . $row, 'Date');
    $sheet->setCellValue('B' . $row, 'Orders');
    $sheet->setCellValue('C' . $row, 'Revenue');
    $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
    $sheet->getStyle('A' . $row . ':C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A' . $row . ':C' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
    $row++;

    $dayCount = count($daily);
    $dayTotalOrd = 0;
    $dayTotalRev = 0;

    foreach ($daily as $d) {
        $dayStr = $d['day'];
        $ts = strtotime($dayStr);
        $excelDate = SpreadsheetDate::PHPToExcel($ts);

        $sheet->setCellValue('A' . $row, $excelDate);
        $sheet->getStyle('A' . $row)->getNumberFormat()->setFormatCode('mmm d, yyyy');
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('B' . $row, (int)$d['cnt']);
        $sheet->setCellValue('C' . $row, (float)$d['total']);
        $sheet->getStyle('B' . $row . ':C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

        $dayTotalOrd += (int)$d['cnt'];
        $dayTotalRev += (float)$d['total'];
        $row++;
    }

    if ($dayCount > 0) {
        $sheet->setCellValue('A' . $row, 'DAILY AVERAGE');
        $sheet->setCellValue('B' . $row, round($dayTotalOrd / $dayCount, 1));
        $sheet->setCellValue('C' . $row, $dayTotalRev / $dayCount);
        $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('B' . $row . ':C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('A' . $row . ':C' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
        $row++;
    }
    $dailyEndRow = $row - 1;
    $dailyEndRow = max($dailyEndRow, $dailyStartRow);
    $sheet->getStyle('A' . $dailyStartRow . ':C' . $dailyEndRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Freeze panes at first data row of Status table
    $sheet->freezePane('A' . ($statusStartRow + 1));
}

/**
 * Customers Report layout
 */
function buildCustomersReport($sheet, $from, $to, $branchName, $branchId) {
    $cust_summary = db_query("SELECT COUNT(*) as total, SUM(CASE WHEN status='Activated' THEN 1 ELSE 0 END) as active FROM customers");
    $cs = $cust_summary[0] ?? [];
    $totalCust = (int)($cs['total'] ?? 0);
    $activeCust = (int)($cs['active'] ?? 0);

    if ($branchId !== 'all') {
        $customers = db_query(
            "SELECT c.customer_id, CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,'')) as name,
                    COALESCE(c.email,'') as email, COALESCE(c.contact_number,'') as contact_number, c.status, c.created_at,
                    COUNT(o.order_id) as order_count, COALESCE(SUM(o.total_amount), 0) as total_spent
             FROM customers c
             INNER JOIN orders o ON c.customer_id = o.customer_id AND o.branch_id = ?
             GROUP BY c.customer_id
             ORDER BY total_spent DESC",
            'i', [$branchId]
        ) ?: [];
    } else {
        $customers = db_query(
            "SELECT c.customer_id, CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,'')) as name,
                    COALESCE(c.email,'') as email, COALESCE(c.contact_number,'') as contact_number, c.status, c.created_at,
                    COUNT(o.order_id) as order_count, COALESCE(SUM(o.total_amount), 0) as total_spent
             FROM customers c
             LEFT JOIN orders o ON c.customer_id = o.customer_id
             GROUP BY c.customer_id
             ORDER BY total_spent DESC"
        ) ?: [];
    }

    $row = 1;

    // Title
    $sheet->setCellValue('A1', 'PrintFlow Sales & Analytics Report');
    $sheet->mergeCells('A1:H1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Metadata
    $sheet->setCellValue('A3', 'Report Type');
    $sheet->setCellValue('B3', 'Customers Report');
    $sheet->setCellValue('A4', 'Branch');
    $sheet->setCellValue('B4', $branchName);
    $sheet->setCellValue('A5', 'Date Range');
    $sheet->setCellValue('B5', date('F j, Y', strtotime($from)) . ' – ' . date('F j, Y', strtotime($to)));
    $sheet->setCellValue('A6', 'Generated On');
    $sheet->setCellValue('B6', date('F j, Y, g:i A'));
    $sheet->getStyle('A3:A6')->getFont()->setBold(true);

    // Summary
    $row = 8;
    $sheet->setCellValue('A' . $row, 'SUMMARY');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11);
    $row++;
    $sheet->setCellValue('A' . $row, 'Total Customers');
    $sheet->setCellValue('B' . $row, $totalCust);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $row++;
    $sheet->setCellValue('A' . $row, 'Active Customers');
    $sheet->setCellValue('B' . $row, $activeCust);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $row += 2;

    // Customer list headers
    $headerRow = $row;
    $headers = ['Customer ID', 'Name', 'Email', 'Contact Number', 'Status', 'Registered Date', 'Total Orders', 'Total Spent (₱)'];
    foreach ($headers as $i => $h) {
        $col = chr(65 + $i);
        $sheet->setCellValue($col . $row, $h);
        $sheet->getStyle($col . $row)->getFont()->setBold(true);
        $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }
    $sheet->getStyle('A' . $row . ':H' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
    $row++;

    $totalSpentSum = 0;
    foreach ($customers as $c) {
        $totalSpent = (float)($c['total_spent'] ?? 0);
        $totalSpentSum += $totalSpent;
        $regDate = $c['created_at'] ?? '';
        $excelDate = ($regDate && strtotime($regDate)) ? SpreadsheetDate::PHPToExcel(strtotime($regDate)) : '';

        $sheet->setCellValue('A' . $row, (int)$c['customer_id']);
        $sheet->setCellValue('B' . $row, trim($c['name'] ?? ''));
        $sheet->setCellValue('C' . $row, trim($c['email'] ?? ''));
        $sheet->setCellValue('D' . $row, trim($c['contact_number'] ?? ''));
        $sheet->setCellValue('E' . $row, trim($c['status'] ?? ''));
        $sheet->setCellValue('F' . $row, $excelDate);
        $sheet->setCellValue('G' . $row, (int)($c['order_count'] ?? 0));
        $sheet->setCellValue('H' . $row, $totalSpent);

        $sheet->getStyle('A' . $row . ':E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        if ($excelDate) $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('mmm d, yyyy');
        $sheet->getStyle('G' . $row . ':H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $row++;
    }

    // TOTAL row
    $sheet->setCellValue('A' . $row, 'TOTAL');
    $sheet->setCellValue('H' . $row, $totalSpentSum);
    $sheet->getStyle('A' . $row . ':H' . $row)->getFont()->setBold(true);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('A' . $row . ':H' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
    $lastRow = $row;

    $sheet->getStyle('A' . $headerRow . ':H' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->freezePane('A' . ($headerRow + 1));
}
