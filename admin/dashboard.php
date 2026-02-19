<?php
// Enable error display for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('Admin');

// Get dashboard statistics with error handling
try {
    $total_customers_res = db_query("SELECT COUNT(*) as count FROM customers");
    $total_customers = $total_customers_res[0]['count'] ?? 0;
} catch (Exception $e) {
    $total_customers = 0;
}

try {
    $total_revenue_res = db_query("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'Paid'");
    $total_revenue = $total_revenue_res[0]['total'] ?? 0;
} catch (Exception $e) {
    $total_revenue = 0;
}

try {
    $total_orders_res = db_query("SELECT COUNT(*) as count FROM orders");
    $total_orders = $total_orders_res[0]['count'] ?? 0;
} catch (Exception $e) {
    $total_orders = 0;
}

$total_returns = 0; // Placeholder

$page_title = 'Dashboard - Admin | PrintFlow';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        /* KPI Row - matches reports page */
        .kpi-row { display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:24px; }
        @media (max-width:768px) { .kpi-row { grid-template-columns:repeat(2, 1fr); } }
        .kpi-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:18px 20px; position:relative; overflow:hidden; }
        .kpi-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
        .kpi-card.indigo::before { background:linear-gradient(90deg,#6366f1,#818cf8); }
        .kpi-card.emerald::before { background:linear-gradient(90deg,#059669,#34d399); }
        .kpi-card.amber::before { background:linear-gradient(90deg,#f59e0b,#fbbf24); }
        .kpi-card.rose::before { background:linear-gradient(90deg,#e11d48,#fb7185); }
        .kpi-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:#9ca3af; margin-bottom:6px; }
        .kpi-value { font-size:26px; font-weight:800; color:#1f2937; font-variant-numeric:tabular-nums; }
        .kpi-sub { font-size:12px; color:#6b7280; margin-top:4px; }

        /* Charts */
        .chart-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .chart-title { font-size: 18px; font-weight: 600; color: #1f2937; }
        .chart-metric { margin-top: 8px; font-size: 24px; font-weight: 700; color: #1f2937; }
        .chart-metric span { font-size: 14px; color: #10b981; font-weight: 500; margin-left: 8px; }
        .chart-subtitle { font-size: 13px; color: #9ca3af; margin-top: 4px; }
        .chart-legend { display: flex; gap: 16px; font-size: 13px; }
        .legend-item { display: flex; align-items: center; gap: 6px; }
        .legend-dot { width: 8px; height: 8px; border-radius: 50%; }
        
        .sales-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 1024px) { .sales-grid { grid-template-columns: 1fr; } }
        
        .category-list { margin-top: 20px; font-size: 13px; }
        .category-row { display: flex; justify-content: space-between; margin-bottom: 8px; padding: 8px; border-radius: 6px; }
        .category-row:hover { background: #f9fafb; }
        
        .country-list { font-size: 13px; line-height: 2.2; }
        .country-row { display: flex; justify-content: space-between; padding: 4px 0; }
        .country-row span:last-child { font-weight: 600; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Dashboard</h1>
            <select class="time-period input-field" style="width: auto;">
                <option>Time period</option>
                <option>Last 7 days</option>
                <option>Last 30 days</option>
                <option>Last 6 months</option>
            </select>
        </header>

        <main>
            <!-- KPI Summary Row (matches reports page style) -->
            <div class="kpi-row">
                <div class="kpi-card indigo">
                    <div class="kpi-label">Total Customers</div>
                    <div class="kpi-value"><?php echo number_format($total_customers); ?></div>
                    <div class="kpi-sub">All registered users</div>
                </div>
                <div class="kpi-card emerald">
                    <div class="kpi-label">Total Revenue</div>
                    <div class="kpi-value">₱<?php echo $total_revenue > 0 ? number_format($total_revenue, 2) : '0.00'; ?></div>
                    <div class="kpi-sub">From paid orders</div>
                </div>
                <div class="kpi-card amber">
                    <div class="kpi-label">Total Orders</div>
                    <div class="kpi-value"><?php echo number_format($total_orders); ?></div>
                    <div class="kpi-sub">All time orders</div>
                </div>
                <div class="kpi-card rose">
                    <div class="kpi-label">Total Returns</div>
                    <div class="kpi-value"><?php echo number_format($total_returns); ?></div>
                    <div class="kpi-sub">Returned items</div>
                </div>
            </div>
            
            <!-- Product Sales Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <h2 class="chart-title">Product sales</h2>
                        <div class="chart-metric">
                            ₱52,187 <span>↗ 2.6%</span>
                        </div>
                        <div class="chart-subtitle">Gross margin</div>
                    </div>
                    <div class="chart-legend">
                        <div class="legend-item">
                            <div class="legend-dot" style="background: #3b82f6;"></div>
                            Gross margin
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background: #f97316;"></div>
                            Revenue
                        </div>
                    </div>
                </div>
                <canvas id="salesChart" height="80"></canvas>
            </div>
            
            <!-- Bottom Charts -->
            <div class="sales-grid">
                <div class="chart-card">
                    <h2 class="chart-title">Sales by product category</h2>
                    <canvas id="categoryChart" height="200"></canvas>
                    <div class="category-list">
                        <div class="category-row">
                            <span>🪑 Living room</span>
                            <span style="font-weight: 600;">25%</span>
                        </div>
                        <div class="category-row">
                            <span>🧒 Kids</span>
                            <span style="font-weight: 600;">17%</span>
                        </div>
                        <div class="category-row">
                            <span>💼 Office</span>
                            <span style="font-weight: 600;">13%</span>
                        </div>
                        <div class="category-row">
                            <span>🛏️ Bedroom</span>
                            <span style="font-weight: 600;">12%</span>
                        </div>
                        <div class="category-row">
                            <span>🍳 Kitchen</span>
                            <span style="font-weight: 600;">9%</span>
                        </div>
                        <div class="category-row">
                            <span>🚿 Bathroom</span>
                            <span style="font-weight: 600;">8%</span>
                        </div>
                    </div>
                </div>
                
                <div class="chart-card">
                    <h2 class="chart-title">Sales by countries</h2>
                    <div class="country-list">
                        <div class="country-row">
                            <span>🟢 Philippines</span>
                            <span>45%</span>
                        </div>
                        <div class="country-row">
                            <span>🟢 USA</span>
                            <span>25%</span>
                        </div>
                        <div class="country-row">
                            <span>🟢 Japan</span>
                            <span>15%</span>
                        </div>
                        <div class="country-row">
                            <span>🟢 Canada</span>
                            <span>10%</span>
                        </div>
                        <div class="country-row">
                            <span>🟢 Australia</span>
                            <span>5%</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Product Sales Chart
const salesCtx = document.getElementById('salesChart').getContext('2d');
new Chart(salesCtx, {
    type: 'bar',
    data: {
        labels: ['1 Jul', '2 Jul', '3 Jul', '4 Jul', '5 Jul', '6 Jul', '7 Jul', '8 Jul', '9 Jul', '10 Jul', '11 Jul', '12 Jul'],
        datasets: [{
            label: 'Gross margin',
            data: [35000, 32000, 28000, 48000, 55000, 52000, 30000, 38000, 42000, 50000, 33000, 58000],
            backgroundColor: '#3b82f6',
            borderRadius: 4,
            barThickness: 20,
        }, {
            label: 'Revenue',
            data: [38000, 45000, 50000, 42000, 52000, 58000, 35000, 45000, 48000, 55000, 48000, 52000],
            backgroundColor: '#f97316',
            borderRadius: 4,
            barThickness: 20,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            x: {
                grid: { display: false }
            },
            y: {
                beginAtZero: true,
                max: 70000,
                ticks: {
                    callback: function(value) {
                        return (value / 1000) + 'K';
                    }
                },
                grid: {
                    color: '#f3f4f6'
                }
            }
        }
    }
});

// Category Donut Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: ['Living room', 'Kids', 'Office', 'Bedroom', 'Kitchen', 'Bathroom'],
        datasets: [{
            data: [25, 17, 13, 12, 9, 8],
            backgroundColor: [
                '#8b5cf6',
                '#3b82f6',
                '#06b6d4',
                '#10b981',
                '#f59e0b',
                '#ec4899'
            ],
            borderWidth: 0,
            borderRadius: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false }
        },
        cutout: '70%'
    }
});
</script>

</body>
</html>
