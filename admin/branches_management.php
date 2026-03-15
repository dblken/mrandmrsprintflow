<?php
/**
 * Admin Branch Management
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Access Control: ONLY Owner or Admin
require_role(['Owner', 'Admin']);

$current_user = get_logged_in_user();

// Pagination setup
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;

// Query counting total branches
$total_branches = db_query("SELECT COUNT(*) as total FROM branches")[0]['total'];
$total_pages = max(1, ceil($total_branches / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

// Fetch branches along with their assigned staff count
$branches = db_query("
    SELECT b.*, 
        (SELECT COUNT(*) FROM users u WHERE u.branch_id = b.id AND u.role = 'Staff') as staff_count
    FROM branches b 
    ORDER BY b.created_at ASC 
    LIMIT $per_page OFFSET $offset
");

$page_title = 'Branch Management - Admin';
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
        [x-cloak] { display: none !important; }
        .branches-table { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: auto; }
        .branches-table th { padding: 12px 16px; font-size: 13px; font-weight: 600; color: #6b7280; text-align: left; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
        .branches-table td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; color: #374151; }
        .branches-table tbody tr { transition: background 0.1s; }
        .branches-table tbody tr:hover td { background: #f9fafb; }
        .branches-table tbody tr:last-child td { border-bottom: none; }
        .modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:9999; }
        .modal-panel { background:#fff; border-radius:12px; box-shadow:0 25px 50px rgba(0,0,0,0.25); width:100%; max-width:500px; max-height:85vh; overflow-y:auto; margin:16px; position:relative; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .form-input { width: 100%; padding: 10px 14px; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: 14px; color: #1f2937; background: #f9fafb; outline: none; transition: all 0.2s; }
        .form-input:focus { border-color: #3b82f6; background: #ffffff; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12); }
        .btn-action { display: inline-flex; align-items: center; justify-content: center; padding: 6px 14px; border: 1px solid #14b8a6; color: #14b8a6; background: transparent; border-radius: 9999px; font-size: 13px; font-weight: 500; transition: all 0.2s; cursor: pointer; }
        .btn-action:hover { background: #14b8a6; color: white; }
    </style>
</head>
<body x-data="branchManagement()">

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <div>
                <h1 class="page-title" style="margin-bottom:4px;">Branch Management</h1>
                <p style="font-size:14px;color:#6b7280;">Manage store branches and their assigned staff.</p>
            </div>
        </header>

        <main>
            <!-- Alert message for successful actions -->
            <div x-show="toast.show" x-cloak 
                 style="position:fixed; top:24px; right:24px; padding:16px 24px; border-radius:8px; display:flex; align-items:center; gap:12px; z-index:9999; box-shadow:0 10px 15px -3px rgba(0,0,0,0.1); color:white; font-size:14px; font-weight:500;"
                 :style="toast.type === 'error' ? 'background:#ef4444' : 'background:#10b981'"
                 x-transition.opacity.duration.300ms>
                <span x-text="toast.message"></span>
            </div>

            <!-- Branch Table -->
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h3 style="font-size:16px;font-weight:700;color:#1f2937;margin:0;">Branches List</h3>
                    <button @click="openModal('create')" class="btn-primary" style="height:38px;padding:0 16px;border-radius:10px;font-size:13px;">+ New Branch</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="branches-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Branch Name</th>
                                <th>Address</th>
                                <th>Contact Number</th>
                                <th>Staff Assignees</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($branches)): ?>
                                <tr>
                                    <td colspan="7" style="padding:40px;text-align:center;color:#9ca3af;font-size:14px;">No branches configured yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($branches as $branch): ?>
                                    <tr>
                                        <td style="color:#9ca3af;font-size:12px;"><?php echo $branch['id']; ?></td>
                                        <td style="font-weight:600;color:#111827;"><?php echo htmlspecialchars($branch['branch_name']); ?></td>
                                        <td style="color:#6b7280;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($branch['address'] ?: '—'); ?></td>
                                        <td><?php echo htmlspecialchars($branch['contact_number'] ?: '—'); ?></td>
                                        <td>
                                            <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#dbeafe;color:#1e40af;">
                                                <?php echo $branch['staff_count']; ?> Staff
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($branch['status'] === 'Active'): ?>
                                                <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#dcfce7;color:#166534;">Active</span>
                                            <?php else: ?>
                                                <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#fee2e2;color:#991b1b;">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <button @click="openModal('update', {
                                                id: <?php echo $branch['id']; ?>,
                                                name: '<?php echo addslashes($branch['branch_name']); ?>',
                                                address: '<?php echo addslashes($branch['address']); ?>',
                                                contact: '<?php echo addslashes($branch['contact_number']); ?>',
                                                status: '<?php echo $branch['status']; ?>'
                                            })" class="btn-action">
                                                Edit
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php echo render_pagination($page, $total_pages); ?>
            </div>
        </main>
    </div>
</div>

<!-- Add/Edit Branch Modal -->
<div x-show="modal.isOpen" x-cloak>
    <div class="modal-overlay" @click.self="modal.isOpen = false">
        <div class="modal-panel" @click.stop>
            
            <div style="padding:24px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center;">
                <h2 style="font-size:20px; font-weight:700; color:#111827; margin:0;" x-text="modal.mode === 'create' ? 'Register New Branch' : 'Edit Branch'"></h2>
                <button @click="modal.isOpen = false" style="background:none; border:none; font-size:24px; color:#9ca3af; cursor:pointer;">&times;</button>
            </div>

            <form @submit.prevent="submitForm()" style="padding:24px;">
                <div x-show="modal.error" x-text="modal.error" style="background:#fef2f2; color:#b91c1c; padding:12px; border-radius:8px; font-size:14px; margin-bottom:16px;"></div>
                
                <div class="form-group">
                    <label class="form-label">Branch Name <span style="color:#ef4444">*</span></label>
                    <input type="text" x-model="form.branch_name" class="form-input" placeholder="e.g. Quezon City" required>
                    <p style="font-size:11px; color:#6b7280; margin-top:4px;">"Branch" will be added automatically (e.g. "Quezon City Branch")</p>
                </div>

                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea x-model="form.address" class="form-input" rows="3" placeholder="Full physical address"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="text" x-model="form.contact_number" class="form-input" placeholder="e.g. 0917-123-4567">
                </div>

                <div class="form-group" x-show="modal.mode === 'update'">
                    <label class="form-label">Operating Status</label>
                    <select x-model="form.status" class="form-input">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive (Prevents new orders)</option>
                    </select>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:32px;">
                    <button type="button" @click="modal.isOpen = false" style="padding:10px 16px; border:1px solid #e5e7eb; border-radius:8px; background:#fff; cursor:pointer;">Cancel</button>
                    <button type="submit" 
                            style="padding:10px 16px; border:none; border-radius:8px; background:#4f46e5; color:#fff; font-weight:600; cursor:pointer;"
                            x-text="modal.isSubmitting ? 'Saving...' : (modal.mode === 'create' ? 'Create Branch' : 'Save Changes')"
                            :disabled="modal.isSubmitting"></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function branchManagement() {
    return {
        modal: {
            isOpen: false,
            mode: 'create', // 'create' or 'update'
            isSubmitting: false,
            error: ''
        },
        form: {
            branch_id: 0,
            branch_name: '',
            address: '',
            contact_number: '',
            status: 'Active'
        },
        toast: {
            show: false,
            message: '',
            type: 'success'
        },

        openModal(mode, data = null) {
            this.modal.mode = mode;
            this.modal.error = '';
            
            if (mode === 'create') {
                this.form = { branch_id: 0, branch_name: '', address: '', contact_number: '', status: 'Active' };
            } else if (mode === 'update' && data) {
                this.form = {
                    branch_id: data.id,
                    branch_name: data.name,
                    address: data.address,
                    contact_number: data.contact,
                    status: data.status
                };
            }
            this.modal.isOpen = true;
        },

        showToast(message, type = 'success') {
            this.toast.message = message;
            this.toast.type = type;
            this.toast.show = true;
            setTimeout(() => { this.toast.show = false; }, 3000);
        },

        async submitForm() {
            this.modal.isSubmitting = true;
            this.modal.error = '';

            try {
                const payload = {
                    action: this.modal.mode,
                    branch_name: this.form.branch_name,
                    address: this.form.address,
                    contact_number: this.form.contact_number,
                    csrf_token: '<?php echo $_SESSION["csrf_token"] ?? ""; ?>'
                };

                if (this.modal.mode === 'update') {
                    payload.branch_id = this.form.branch_id;
                    payload.status = this.form.status;
                }

                const response = await fetch('/printflow/admin/api_branch.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (result.success) {
                    this.modal.isOpen = false;
                    this.showToast(result.message, 'success');
                    // Reload the page to reflect newest data after 1 second
                    setTimeout(() => { window.location.reload(); }, 1200);
                } else {
                    this.modal.error = result.error || 'Failed to process request.';
                }

            } catch (err) {
                this.modal.error = 'Network error. Please check your connection and try again.';
                console.error(err);
            } finally {
                this.modal.isSubmitting = false;
            }
        }
    };
}
</script>

</body>
</html>
