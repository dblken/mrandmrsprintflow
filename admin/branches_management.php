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
    <script src="/printflow/public/assets/js/alpine.min.js" defer></script>
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        [x-cloak] { display: none !important; }
        .branches-table { width: 100%; border-collapse: collapse; font-size: 13px; table-layout: auto; }
        .branches-table th { padding: 12px 16px; font-size: 13px; font-weight: 600; color: #6b7280; text-align: left; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
        .branches-table td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; color: #374151; }
        .branches-table tbody tr { cursor: pointer; transition: background 0.1s; }
        .branches-table tbody tr:hover td { background: #f9fafb; }
        .branches-table tbody tr:last-child td { border-bottom: none; }
        .modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:9999; }
        .modal-panel { background:#fff; border-radius:12px; box-shadow:0 25px 50px rgba(0,0,0,0.25); width:100%; max-width:500px; max-height:85vh; overflow-y:auto; margin:16px; position:relative; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .form-input { width: 100%; padding: 10px 14px; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: 14px; color: #1f2937; background: #f9fafb; outline: none; transition: all 0.2s; }
        .form-input:focus { border-color: #3b82f6; background: #ffffff; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12); }
        .toolbar-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border: 1px solid #e5e7eb; background: #fff; border-radius: 8px; font-size: 13px; font-weight: 500; color: #374151; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
        .toolbar-btn:hover { border-color: #9ca3af; background: #f9fafb; }
        .btn-action { display: inline-flex; align-items: center; justify-content: center; padding: 5px 12px; min-width: 80px; border: 1px solid transparent; background: transparent; border-radius: 6px; font-size: 12px; font-weight: 500; transition: all 0.2s; cursor: pointer; text-decoration: none; white-space: nowrap; }
        .btn-action.blue { color: #3b82f6; border-color: #3b82f6; }
        .btn-action.blue:hover { background: #3b82f6; color: white; }
        .btn-action.teal { color: #14b8a6; border-color: #14b8a6; }
        .btn-action.teal:hover { background: #14b8a6; color: white; }
    </style>
</head>
<body x-data="branchManagement()">

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Branch Management</h1>
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
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px;">
                    <h3 style="font-size:16px;font-weight:700;color:#1f2937;margin:0;">Branches List</h3>
                    <button @click="openModal('create')" class="toolbar-btn" style="height:38px;border-color:#3b82f6;color:#3b82f6;padding:0 16px;">+ New Branch</button>
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
                                    <td colspan="7" style="padding:40px;text-align:center;color:#6b7280;font-size:14px;">No branches configured yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($branches as $branch): 
                                    $branchData = json_encode(['id'=>$branch['id'],'name'=>$branch['branch_name'],'address'=>$branch['address']??'','contact'=>$branch['contact_number']??'','status'=>$branch['status'],'staff_count'=>$branch['staff_count']]);
                                ?>
                                    <tr @click="openViewModal(<?php echo htmlspecialchars($branchData, ENT_QUOTES, 'UTF-8'); ?>)">
                                        <td style="color:#6b7280;font-size:12px;"><?php echo $branch['id']; ?></td>
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
                                        <td style="text-align:right;white-space:nowrap;" @click.stop>
                                            <button @click="openViewModal(<?php echo htmlspecialchars($branchData, ENT_QUOTES, 'UTF-8'); ?>)" class="btn-action blue">View</button>
                                            <button @click="openModal('update', <?php echo htmlspecialchars($branchData, ENT_QUOTES, 'UTF-8'); ?>)" class="btn-action teal">Edit</button>
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

<!-- View Branch Modal -->
<div x-show="viewModal.isOpen" x-cloak>
    <div class="modal-overlay" @click.self="viewModal.isOpen = false">
        <div class="modal-panel" @click.stop style="max-width:500px;">
            <div style="padding:24px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center;">
                <h2 style="font-size:20px; font-weight:700; color:#111827; margin:0;">Branch Details</h2>
                <button @click="viewModal.isOpen = false" style="background:none; border:none; font-size:24px; color:#6b7280; cursor:pointer;">&times;</button>
            </div>
            <div style="padding:24px;">
                <div style="margin-bottom:16px;">
                    <div style="font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:4px;">Branch Name</div>
                    <div style="font-weight:600; color:#111827; font-size:16px;" x-text="viewModal.data.name || '—'"></div>
                </div>
                <div style="margin-bottom:16px;">
                    <div style="font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:4px;">Address</div>
                    <div style="color:#374151; font-size:13px; white-space:pre-wrap;" x-text="viewModal.data.address || '—'"></div>
                </div>
                <div style="margin-bottom:16px;">
                    <div style="font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:4px;">Contact Number</div>
                    <div style="color:#374151; font-size:13px;" x-text="viewModal.data.contact || '—'"></div>
                </div>
                <div style="margin-bottom:16px;">
                    <div style="font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:4px;">Staff Assignees</div>
                    <div><span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#dbeafe;color:#1e40af;" x-text="(viewModal.data.staff_count || 0) + ' Staff'"></span></div>
                </div>
                <div style="margin-bottom:16px;">
                    <div style="font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; margin-bottom:4px;">Status</div>
                    <div>
                        <span x-show="viewModal.data.status === 'Active'" style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#dcfce7;color:#166534;">Active</span>
                        <span x-show="viewModal.data.status !== 'Active'" style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#fee2e2;color:#991b1b;">Inactive</span>
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px; padding-top:20px; border-top:1px solid #e5e7eb;">
                    <button type="button" @click="viewModal.isOpen = false" style="padding:10px 16px; border:1px solid #e5e7eb; border-radius:8px; background:#fff; cursor:pointer;">Close</button>
                    <button type="button" @click="viewModal.isOpen = false; openModal('update', viewModal.data)" class="btn-action teal">Edit</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Branch Modal -->
<div x-show="modal.isOpen" x-cloak>
    <div class="modal-overlay" @click.self="modal.isOpen = false">
        <div class="modal-panel" @click.stop>
            
            <div style="padding:24px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center;">
                <h2 style="font-size:20px; font-weight:700; color:#111827; margin:0;" x-text="modal.mode === 'create' ? 'Register New Branch' : 'Edit Branch'"></h2>
                <button @click="modal.isOpen = false" style="background:none; border:none; font-size:24px; color:#6b7280; cursor:pointer;">&times;</button>
            </div>

            <form @submit.prevent="submitForm()" style="padding:24px;">
                <div x-show="modal.error" x-text="modal.error" style="background:#fef2f2; color:#b91c1c; padding:12px; border-radius:8px; font-size:14px; margin-bottom:16px;"></div>
                
                <div class="form-group">
                    <label class="form-label">Branch Name <span style="color:#ef4444">*</span></label>
                    <input type="text" x-model="form.branch_name" 
                            @input="form.branch_name = $event.target.value.replace(/^\s+/, '')"
                            @blur="form.branch_name = form.branch_name.split(' ').map(w => w ? w.charAt(0).toUpperCase() + w.slice(1).toLowerCase() : '').join(' ')"
                            class="form-input" placeholder="e.g. Quezon City" required>
                    <p style="font-size:11px; color:#6b7280; margin-top:4px;">"Branch" will be added automatically (e.g. "Quezon City Branch"). Don't type "Branch" — it's appended for you.</p>
                </div>

                <!-- Philippine Address (PSGC API - same as admin profile) -->
                <div class="form-group">
                    <label class="form-label">Province</label>
                    <select x-model="form.address_province" @change="loadCities()" class="form-input" :disabled="!addressProvinces.length">
                        <option value="">Select province</option>
                        <template x-for="p in addressProvinces" :key="p.code">
                            <option :value="p.name" :data-code="p.code" x-text="p.name"></option>
                        </template>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">City / Municipality</label>
                    <select x-model="form.address_city" @change="loadBarangays()" class="form-input" :disabled="!form.address_province || !addressCities.length">
                        <option value="">Select city/municipality</option>
                        <template x-for="c in addressCities" :key="c.code">
                            <option :value="c.name" :data-code="c.code" x-text="c.name"></option>
                        </template>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Barangay</label>
                    <select x-model="form.address_barangay" @change="buildAddress()" class="form-input" :disabled="!form.address_city || !addressBarangays.length">
                        <option value="">Select barangay</option>
                        <template x-for="b in addressBarangays" :key="b.code">
                            <option :value="b.name" :data-code="b.code" x-text="b.name"></option>
                        </template>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Street / House No. (Optional)</label>
                    <input type="text" x-model="form.address_line" @input="buildAddress()" class="form-input" maxlength="120" placeholder="e.g. 123 Rizal St.">
                </div>
                <div class="form-group">
                    <label class="form-label">Address Preview</label>
                    <textarea x-model="form.address" class="form-input" rows="2" readonly placeholder="Select province, city, and barangay"></textarea>
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
        viewModal: {
            isOpen: false,
            data: {}
        },
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
            address_province: '',
            address_city: '',
            address_barangay: '',
            address_line: '',
            contact_number: '',
            status: 'Active'
        },
        addressProvinces: [],
        addressCities: [],
        addressBarangays: [],
        toast: {
            show: false,
            message: '',
            type: 'success'
        },

        async loadProvinces() {
            this.addressProvinces = [];
            this.addressCities = [];
            this.addressBarangays = [];
            try {
                const r = await fetch('/printflow/admin/api_address.php?address_action=provinces');
                const d = await r.json();
                if (d.success && d.data) this.addressProvinces = d.data;
            } catch (e) { console.error('Address load failed:', e); }
        },
        async loadCities() {
            this.addressCities = [];
            this.addressBarangays = [];
            const p = this.addressProvinces.find(x => x.name === this.form.address_province);
            const code = p?.code || '';
            if (!code) return;
            try {
                const r = await fetch('/printflow/admin/api_address.php?address_action=cities&province_code=' + encodeURIComponent(code));
                const d = await r.json();
                if (d.success && d.data) this.addressCities = d.data;
            } catch (e) { console.error('Cities load failed:', e); }
            this.buildAddress();
        },
        async loadBarangays() {
            this.addressBarangays = [];
            const c = this.addressCities.find(x => x.name === this.form.address_city);
            const code = c?.code || '';
            if (!code) return;
            try {
                const r = await fetch('/printflow/admin/api_address.php?address_action=barangays&city_code=' + encodeURIComponent(code));
                const d = await r.json();
                if (d.success && d.data) this.addressBarangays = d.data;
            } catch (e) { console.error('Barangays load failed:', e); }
            this.buildAddress();
        },
        buildAddress() {
            const p = [this.form.address_line, this.form.address_barangay ? 'Brgy. ' + this.form.address_barangay : '', this.form.address_city, this.form.address_province].filter(Boolean);
            this.form.address = p.length ? p.join(', ') + ', Philippines' : '';
        },

        openViewModal(data) {
            if (!data) return;
            this.viewModal.data = data;
            this.viewModal.isOpen = true;
        },
        openModal(mode, data = null) {
            this.modal.mode = mode;
            this.modal.error = '';
            this.addressProvinces = [];
            this.addressCities = [];
            this.addressBarangays = [];
            this.viewModal.isOpen = false;

            if (mode === 'create') {
                this.form = { branch_id: 0, branch_name: '', address: '', address_province: '', address_city: '', address_barangay: '', address_line: '', contact_number: '', status: 'Active' };
                this.loadProvinces();
            } else if (mode === 'update' && data) {
                const branchNameDisplay = (data.name || '').replace(/\s+Branch$/i, '');
                this.form = {
                    branch_id: data.id,
                    branch_name: branchNameDisplay,
                    address: data.address || '',
                    address_province: '',
                    address_city: '',
                    address_barangay: '',
                    address_line: '',
                    contact_number: data.contact || '',
                    status: data.status || 'Active'
                };
                this.loadProvinces();
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
