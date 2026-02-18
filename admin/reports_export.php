<?php
/**
 * Reports CSV Export Endpoint
 * PrintFlow - Admin Reports
 * Generates CSV downloads for each report type with print-ready layout
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$report = $_GET['report'] ?? '';
$from   = $_GET['from'] ?? date('Y-m-01');
$to     = $_GET['to'] ?? date('Y-m-d');

// Sanitize dates
$from = date('Y-m-d', strtotime($from));
$to   = date('Y-m-d', strtotime($to));

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="printflow_' . $report . '_' . date('Ymd') . '.csv"');

$output = fopen('php://output', 'w');

// ── Report header for print layout ────────────────────────
function writeReportHeader($output, $title, $from, $to) {
    fputcsv($output, ['PRINTFLOW PRINTING SHOP']);
    fputcsv($output, [$title]);
    fputcsv($output, ['Period: ' . date('M d, Y', strtotime($from)) . ' to ' . date('M d, Y', strtotime($to))]);
    fputcsv($output, ['Generated: ' . date('M d, Y h:i A')]);
    fputcsv($output, []); // blank line
}

switch ($report) {

    // ═══════════════════════════════════════════════════════
    // SALES REPORT
    // ═══════════════════════════════════════════════════════
    case 'sales':
        writeReportHeader($output, 'SALES REPORT', $from, $to);

        // Summary
        $summary = db_query(
            "SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN payment_status = 'Paid' THEN total_amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN payment_status = 'Paid' THEN 1 ELSE 0 END) as paid_orders,
                AVG(CASE WHEN payment_status = 'Paid' THEN total_amount ELSE NULL END) as avg_order_value
             FROM orders 
             WHERE order_date BETWEEN ? AND ?",
            'ss', [$from, $to . ' 23:59:59']
        );
        $s = $summary[0] ?? [];

        fputcsv($output, ['SUMMARY']);
        fputcsv($output, ['Total Orders', $s['total_orders'] ?? 0]);
        fputcsv($output, ['Total Revenue', number_format((float)($s['total_revenue'] ?? 0), 2)]);
        fputcsv($output, ['Paid Orders', $s['paid_orders'] ?? 0]);
        fputcsv($output, ['Average Order Value', number_format((float)($s['avg_order_value'] ?? 0), 2)]);
        fputcsv($output, []);

        // Detail
        fputcsv($output, ['ORDER DETAILS']);
        fputcsv($output, ['Order #', 'Customer', 'Email', 'Order Date', 'Total Amount', 'Payment Status', 'Order Status']);

        $orders = db_query(
            "SELECT o.order_id, CONCAT(c.first_name, ' ', c.last_name) as customer_name, c.email,
                    o.order_date, o.total_amount, o.payment_status, o.status
             FROM orders o
             LEFT JOIN customers c ON o.customer_id = c.customer_id
             WHERE o.order_date BETWEEN ? AND ?
             ORDER BY o.order_date DESC",
            'ss', [$from, $to . ' 23:59:59']
        );

        if ($orders) {
            foreach ($orders as $row) {
                fputcsv($output, [
                    '#' . $row['order_id'],
                    $row['customer_name'],
                    $row['email'],
                    date('M d, Y', strtotime($row['order_date'])),
                    number_format((float)$row['total_amount'], 2),
                    $row['payment_status'],
                    $row['status']
                ]);
            }
        }

        fputcsv($output, []);
        fputcsv($output, ['', '', '', 'TOTAL:', number_format((float)($s['total_revenue'] ?? 0), 2)]);
        break;

    // ═══════════════════════════════════════════════════════
    // ORDERS STATUS REPORT
    // ═══════════════════════════════════════════════════════
    case 'orders':
        writeReportHeader($output, 'ORDERS STATUS REPORT', $from, $to);

        $status_counts = db_query(
            "SELECT status, COUNT(*) as cnt, SUM(total_amount) as total
             FROM orders
             WHERE order_date BETWEEN ? AND ?
             GROUP BY status ORDER BY cnt DESC",
            'ss', [$from, $to . ' 23:59:59']
        );

        fputcsv($output, ['STATUS BREAKDOWN']);
        fputcsv($output, ['Status', 'Count', 'Total Amount']);
        if ($status_counts) {
            foreach ($status_counts as $sc) {
                fputcsv($output, [$sc['status'], $sc['cnt'], number_format((float)$sc['total'], 2)]);
            }
        }
        fputcsv($output, []);

        // Daily order breakdown
        fputcsv($output, ['DAILY ORDER SUMMARY']);
        fputcsv($output, ['Date', 'Orders', 'Revenue']);

        $daily = db_query(
            "SELECT DATE(order_date) as day, COUNT(*) as cnt, SUM(total_amount) as total
             FROM orders
             WHERE order_date BETWEEN ? AND ?
             GROUP BY DATE(order_date) ORDER BY day DESC",
            'ss', [$from, $to . ' 23:59:59']
        );

        if ($daily) {
            foreach ($daily as $d) {
                fputcsv($output, [
                    date('M d, Y', strtotime($d['day'])),
                    $d['cnt'],
                    number_format((float)$d['total'], 2)
                ]);
            }
        }
        break;

    // ═══════════════════════════════════════════════════════
    // CUSTOMERS REPORT
    // ═══════════════════════════════════════════════════════
    case 'customers':
        writeReportHeader($output, 'CUSTOMERS REPORT', $from, $to);

        $cust_summary = db_query("SELECT COUNT(*) as total, SUM(CASE WHEN status='Activated' THEN 1 ELSE 0 END) as active FROM customers");
        $cs = $cust_summary[0] ?? [];

        fputcsv($output, ['SUMMARY']);
        fputcsv($output, ['Total Customers', $cs['total'] ?? 0]);
        fputcsv($output, ['Active Customers', $cs['active'] ?? 0]);
        fputcsv($output, []);

        fputcsv($output, ['CUSTOMER LIST']);
        fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Status', 'Registered Date', 'Total Orders', 'Total Spent']);

        $customers = db_query(
            "SELECT c.customer_id, CONCAT(c.first_name, ' ', c.last_name) as name, c.email, c.phone, c.status, c.created_at,
                    COUNT(o.order_id) as order_count, COALESCE(SUM(o.total_amount), 0) as total_spent
             FROM customers c
             LEFT JOIN orders o ON c.customer_id = o.customer_id
             GROUP BY c.customer_id
             ORDER BY total_spent DESC"
        );

        if ($customers) {
            foreach ($customers as $c) {
                fputcsv($output, [
                    '#' . $c['customer_id'],
                    $c['name'],
                    $c['email'],
                    $c['phone'] ?? 'N/A',
                    $c['status'],
                    date('M d, Y', strtotime($c['created_at'])),
                    $c['order_count'],
                    number_format((float)$c['total_spent'], 2)
                ]);
            }
        }
        break;

    // ═══════════════════════════════════════════════════════
    // INVENTORY REPORT
    // ═══════════════════════════════════════════════════════
    case 'inventory':
        writeReportHeader($output, 'INVENTORY & STOCK REPORT', $from, $to);

        fputcsv($output, ['MATERIAL STOCK LEVELS']);
        fputcsv($output, ['Category', 'Material', 'Unit', 'Opening Stock', 'Current Stock', 'Stock Used', 'Status']);

        $materials = db_query(
            "SELECT mc.category_name, m.material_name, m.unit, m.opening_stock, m.current_stock
             FROM materials m
             JOIN material_categories mc ON m.category_id = mc.category_id
             ORDER BY mc.category_name, m.material_name"
        );

        if ($materials) {
            $total_opening = 0;
            $total_current = 0;
            foreach ($materials as $m) {
                $used = (float)$m['opening_stock'] - (float)$m['current_stock'];
                $status = (float)$m['current_stock'] <= 0 ? 'OUT OF STOCK' : ((float)$m['current_stock'] < (float)$m['opening_stock'] * 0.2 ? 'LOW STOCK' : 'In Stock');
                $total_opening += (float)$m['opening_stock'];
                $total_current += (float)$m['current_stock'];
                fputcsv($output, [
                    $m['category_name'],
                    $m['material_name'],
                    $m['unit'],
                    number_format((float)$m['opening_stock'], 2),
                    number_format((float)$m['current_stock'], 2),
                    number_format($used, 2),
                    $status
                ]);
            }
            fputcsv($output, []);
            fputcsv($output, ['', '', 'TOTALS:', number_format($total_opening, 2), number_format($total_current, 2), number_format($total_opening - $total_current, 2)]);
        }

        fputcsv($output, []);

        // Stock movements in the period
        fputcsv($output, ['STOCK MOVEMENTS IN PERIOD']);
        fputcsv($output, ['Date', 'Material', 'Change', 'Notes']);

        $movements = db_query(
            "SELECT msm.movement_date, m.material_name, msm.quantity_change, msm.notes
             FROM material_stock_movements msm
             JOIN materials m ON msm.material_id = m.material_id
             WHERE msm.movement_date BETWEEN ? AND ?
             ORDER BY msm.movement_date DESC",
            'ss', [$from, $to]
        );

        if ($movements) {
            foreach ($movements as $mv) {
                fputcsv($output, [
                    date('M d, Y', strtotime($mv['movement_date'])),
                    $mv['material_name'],
                    number_format((float)$mv['quantity_change'], 2),
                    $mv['notes']
                ]);
            }
        }
        break;

    default:
        fputcsv($output, ['Error: Invalid report type specified.']);
        break;
}

fclose($output);
exit;
