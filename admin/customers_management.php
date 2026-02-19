<?php
/**
 * Admin Customers Management  
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$current_user = get_logged_in_user();

// Handle Delete Action
if (isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    db_execute("DELETE FROM customers WHERE customer_id = ?", "i", [$delete_id]);
    $success_msg = "Customer deleted successfully.";
}

// Get all customers
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;

$sql = "SELECT * FROM customers WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

$sql .= " ORDER BY created_at DESC";

// Count total
$count_sql = str_replace("SELECT * FROM customers", "SELECT COUNT(*) as total FROM customers", $sql);
$total_filtered = db_query($count_sql, $types, $params)[0]['total'];
$total_pages = max(1, ceil($total_filtered / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$sql .= " LIMIT $per_page OFFSET $offset";
$customers = db_query($sql, $types, $params);

// Get statistics
$total_customers = db_query("SELECT COUNT(*) as count FROM customers")[0]['count'];

$page_title = 'Customers Management - Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        /* Action Button Style */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            border: 1px solid transparent;
            background: transparent;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-action.teal { color: #14b8a6; border-color: #14b8a6; }
        .btn-action.teal:hover { background: #14b8a6; color: white; }
        .btn-action.blue { color: #3b82f6; border-color: #3b82f6; }
        .btn-action.blue:hover { background: #3b82f6; color: white; }
        .btn-action.red { color: #ef4444; border-color: #ef4444; }
        .btn-action.red:hover { background: #ef4444; color: white; }

        /* Modal Styles */
        .modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:9999; }
        .modal-panel { background:#fff; border-radius:12px; box-shadow:0 25px 50px rgba(0,0,0,0.25); width:100%; max-width:500px; max-height:85vh; overflow-y:auto; margin:16px; position:relative; }

        @keyframes spin { to { transform: rotate(360deg); } }
        [x-cloak] { display: none !important; }

        /* Search Box */
        .search-box { position:relative; }
        .search-box input { padding-left:36px; width:220px; height:38px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; background:#fff; transition: border-color 0.2s; }
        .search-box input:focus { border-color:#3b82f6; outline:none; box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
        .search-box .search-icon { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#9ca3af; pointer-events:none; }

        /* Mobile Header */
        .mobile-header { display: none; }
        @media (max-width: 768px) {
            .mobile-header { display: flex; position: fixed; top: 0; left: 0; right: 0; height: 60px; background: #fff; z-index: 60; padding: 0 20px; align-items: center; justify-content: space-between; border-bottom: 1px solid #e5e7eb; }
            .mobile-menu-btn { font-size: 24px; background: none; border: none; cursor: pointer; color: #1f2937; }
            .search-box input { width: 100%; }
        }

        /* Print Styles */
        @media print {
            .sidebar, .mobile-header, .no-print, header, .search-box { display: none !important; }
            .main-content { margin-left: 0 !important; padding-top: 0 !important; }
            .dashboard-container { display: block !important; }
            .card { border: none !important; box-shadow: none !important; padding: 0 !important; }
            body { background: white !important; }
            table { width: 100% !important; border-collapse: collapse !important; }
            th, td { border: 1px solid #ccc !important; padding: 8px 12px !important; font-size: 12px !important; }
            th { background: #f3f4f6 !important; font-weight: 700 !important; }
            .btn-action { display: none !important; }
            .print-header { display: block !important; text-align: center; margin-bottom: 20px; }
            .print-header h2 { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
            .print-header p { font-size: 12px; color: #6b7280; }
        }
        .print-header { display: none; }
    </style>
</head>
<body x-data="customerModal()">

<div class="dashboard-container">
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="mobile-menu-btn" onclick="document.querySelector('.sidebar').classList.toggle('active')">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <span style="font-weight:600;font-size:18px;">PrintFlow</span>
        </div>
    </div>

    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Customers Management</h1>
            <button class="btn-secondary no-print" onclick="window.print()">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print
            </button>
        </header>

        <main>
            <!-- Print Header (visible only when printing) -->
            <div class="print-header">
                <h2>PrintFlow - Customer List</h2>
                <p>Generated on <?php echo date('F j, Y g:i A'); ?> | Total Customers: <?php echo $total_customers; ?></p>
            </div>

            <!-- Messages -->
            <?php if (isset($success_msg)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 no-print"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if (isset($error_msg)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 no-print"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <!-- Customers Table -->
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
                    <h3 style="font-size:16px; font-weight:700; color:#1f2937; margin:0;">Customers List <span style="font-size:13px; font-weight:400; color:#9ca3af;">(<?php echo $total_filtered; ?>)</span></h3>
                    <div class="search-box no-print">
                        <svg class="search-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" id="searchInput" placeholder="Search name or email..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2">
                                <th class="text-left py-3">ID</th>
                                <th class="text-left py-3">Name</th>
                                <th class="text-left py-3">Email</th>
                                <th class="text-left py-3">Contact</th>
                                <th class="text-left py-3">Registered</th>
                                <th class="text-right py-3 no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="customersTableBody">
                            <?php foreach ($customers as $customer): ?>
                                <tr class="border-b hover:bg-gray-50 customer-row">
                                    <td class="py-3">#<?php echo $customer['customer_id']; ?></td>
                                    <td class="py-3 font-medium name-cell">
                                        <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                    </td>
                                    <td class="py-3 email-cell"><?php echo htmlspecialchars($customer['email']); ?></td>
                                    <td class="py-3"><?php echo htmlspecialchars($customer['contact_number'] ?? 'N/A'); ?></td>
                                    <td class="py-3"><?php echo format_date($customer['created_at']); ?></td>
                                    <td class="py-3 text-right space-x-1 no-print">
                                        <button @click="openModal(<?php echo $customer['customer_id']; ?>)" class="btn-action blue">
                                            View
                                        </button>
                                        <a href="customer_orders.php?id=<?php echo $customer['customer_id']; ?>" class="btn-action teal">
                                            Orders
                                        </a>
                                        <button onclick="confirmDelete(<?php echo $customer['customer_id']; ?>)" class="btn-action red">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php 
                $pagination_params = [];
                if ($search) $pagination_params['search'] = $search;
                echo render_pagination($page, $total_pages, $pagination_params); 
                ?>
            </div>
        </main>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="delete_id" id="deleteInput">
</form>

<!-- Customer Details Modal -->
<div x-show="showModal" x-cloak>
    <div class="modal-overlay" @click.self="showModal = false">
        <div class="modal-panel" @click.stop>
            <div x-show="loading" style="padding:48px;text-align:center;">
                <div style="width:40px;height:40px;border:3px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 12px;"></div>
                <p style="color:#6b7280;font-size:14px;">Loading customer details...</p>
            </div>
            <div x-show="errorMsg && !loading" style="padding:32px;text-align:center;">
                <p style="color:#ef4444;font-size:14px;margin-bottom:12px;" x-text="errorMsg"></p>
                <button @click="showModal = false" class="btn-secondary">Close</button>
            </div>
            <div x-show="customer && !loading">
                <div style="padding:20px 24px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                    <h3 style="font-size:18px;font-weight:700;color:#1f2937;margin:0;">Customer Details</h3>
                    <button @click="showModal = false" style="background:transparent;border:none;cursor:pointer;color:#6b7280;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div style="padding:24px;">
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;">
                        <div x-text="customer?.initial" style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#8b5cf6);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:24px;"></div>
                        <div>
                            <div x-text="customer?.first_name + ' ' + customer?.last_name" style="font-size:18px;font-weight:700;color:#1f2937;"></div>
                            <div x-text="'Member since ' + customer?.created_at" style="font-size:13px;color:#6b7280;"></div>
                        </div>
                    </div>
                    <div style="display:grid;gap:16px;">
                        <div>
                            <label style="font-size:12px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Email</label>
                            <div x-text="customer?.email" style="font-size:14px;color:#1f2937;"></div>
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Phone</label>
                            <div x-text="customer?.phone" style="font-size:14px;color:#1f2937;"></div>
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:4px;">Address</label>
                            <div x-text="customer?.address" style="font-size:14px;color:#1f2937;"></div>
                        </div>
                    </div>
                </div>
                <div style="padding:16px 24px;border-top:1px solid #f3f4f6;display:flex;justify-content:flex-end;gap:8px;">
                    <button @click="showModal = false" class="btn-secondary">Close</button>
                    <a :href="'customer_orders.php?id=' + customer?.customer_id" class="btn-action teal">View Orders</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Real-time Search
    document.getElementById('searchInput').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('.customer-row');
        
        rows.forEach(row => {
            const name = row.querySelector('.name-cell').textContent.toLowerCase();
            const email = row.querySelector('.email-cell').textContent.toLowerCase();
            
            if (name.includes(searchTerm) || email.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // Delete Confirmation
    function confirmDelete(id) {
        if(confirm('Are you sure you want to permanently delete this customer? This action cannot be undone.')) {
            document.getElementById('deleteInput').value = id;
            document.getElementById('deleteForm').submit();
        }
    }

    // Customer Modal (Alpine.js component)
    function customerModal() {
        return {
            showModal: false,
            loading: false,
            errorMsg: '',
            customer: null,
            openModal(id) {
                this.showModal = true;
                this.loading = true;
                this.errorMsg = '';
                this.customer = null;

                fetch('api_customer_details.php?id=' + id)
                    .then(r => r.json())
                    .then(data => {
                        this.loading = false;
                        if(data.success) { 
                            this.customer = data.customer; 
                        } else { 
                            this.errorMsg = data.error || 'Unknown error'; 
                        }
                    })
                    .catch(e => { 
                        this.loading = false; 
                        this.errorMsg = 'Failed to load customer details.'; 
                        console.error('Fetch error:', e);
                    });
            }
        };
    }
</script>

</body>
</html>
