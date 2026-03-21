<?php
/**
 * Customer Profile Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

$customer_id = get_user_id();
$error = '';
$success = '';

// Ensure address columns exist (one-time migration - add only missing columns)
$existing_cols = [];
foreach (db_query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'") ?: [] as $r) {
    $existing_cols[$r['COLUMN_NAME']] = true;
}
$add_cols = [
    ['region', 100, 'contact_number'],
    ['province', 100, 'region'],
    ['city', 100, 'province'],
    ['barangay', 100, 'city'],
    ['street_address', 255, 'barangay']
];
foreach ($add_cols as list($col, $len, $after)) {
    if (empty($existing_cols[$col])) {
        db_execute("ALTER TABLE customers ADD COLUMN `$col` varchar($len) DEFAULT NULL AFTER `$after`");
    }
}

// Get customer data
$customer = db_query("SELECT * FROM customers WHERE customer_id = ?", 'i', [$customer_id])[0];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $first_name = sanitize($_POST['first_name'] ?? '');
        $middle_name = sanitize($_POST['middle_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $contact_number = sanitize($_POST['contact_number'] ?? '');
        $dob = sanitize($_POST['dob'] ?? '');
        $gender = sanitize($_POST['gender'] ?? '');
        
        if (empty($first_name) || empty($last_name)) {
            $error = 'First name and last name are required';
        } elseif (!empty($dob)) {
            try {
                $bday_date = new DateTime($dob);
                $today = new DateTime();
                $age = $today->diff($bday_date)->y;
                if ($bday_date > $today) {
                    $error = 'Birthday cannot be a future date';
                } elseif ($age < 13) {
                    $error = 'You must be at least 13 years old';
                }
            } catch (Exception $e) {
                $error = 'Invalid birthday format';
            }
        }
        
        if (!$error) {
            $dob_val = trim($dob) !== '' ? $dob : null;
            $gender_val = in_array(trim($gender), ['Male', 'Female', 'Other'], true) ? $gender : null;
            $result = db_execute("UPDATE customers SET first_name = ?, middle_name = ?, last_name = ?, contact_number = ?, dob = ?, gender = ? WHERE customer_id = ?",
                'ssssssi', [$first_name, $middle_name, $last_name, $contact_number, $dob_val, $gender_val, $customer_id]);
            
            $first_name = ucwords(strtolower(trim($first_name)));
            $middle_name = ucwords(strtolower(trim($middle_name)));
            $last_name = ucwords(strtolower(trim($last_name)));
            
            // Philippine Name Regex: letters and single space only, max 3 words
            $nameRegex = '/^[A-Za-z]+( [A-Za-z]+){0,2}$/';
            $contactRegex = '/^\+639\d{9}$/';
            $emailRegex = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';

            // Backend strong validation
            if (empty($first_name) || !preg_match($nameRegex, $first_name)) {
                $error = 'First name must contain only letters and at most 3 words.';
            } elseif (!empty($middle_name) && !preg_match($nameRegex, $middle_name)) {
                $error = 'Middle name must contain only letters and at most 3 words.';
            } elseif (empty($last_name) || !preg_match($nameRegex, $last_name)) {
                $error = 'Last name must contain only letters and at most 3 words.';
            } elseif (empty($contact_number) || !preg_match($contactRegex, $contact_number)) {
                $error = 'Contact number must follow format +639XXXXXXXXX.';
            } elseif (empty($dob) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || (strtotime($dob) > strtotime('-13 years'))) {
                $error = 'Date of birth must be a valid date and you must be at least 13 years old.';
            } else {
                // Ensure no script tags or malicious input
                if (strip_tags($first_name) !== $first_name || strip_tags($last_name) !== $last_name || strip_tags($middle_name) !== $middle_name) {
                    $error = 'Invalid characters detected in name fields.';
                } else {
                    $first_name = sanitize($first_name);
                    $middle_name = sanitize($middle_name);
                    $last_name = sanitize($last_name);
                    $contact_number = sanitize($contact_number);
                    $dob = sanitize($dob);
                    $gender = sanitize($gender);
                    $gender_val = in_array(trim($gender), ['Male', 'Female', 'Other'], true) ? $gender : null;
                    
                    $result = db_execute("UPDATE customers SET first_name = ?, middle_name = ?, last_name = ?, contact_number = ?, dob = ?, gender = ? WHERE customer_id = ?",
                        'ssssssi', [$first_name, $middle_name, $last_name, $contact_number, $dob, $gender_val, $customer_id]);
                    
                    if ($result) {
                        $success = 'Profile updated successfully!';
                        $_SESSION['profile_update_count'] = ($_SESSION['profile_update_count'] ?? 0) + 1;
                        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                        // Refresh customer data
                        $customer = db_query("SELECT * FROM customers WHERE customer_id = ?", 'i', [$customer_id])[0];
                    } else {
                        $error = 'Failed to update profile';
                    }
                }
            }
        }
    }
}

// Handle address update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_address'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $region        = sanitize($_POST['region']         ?? '');
        $province      = sanitize($_POST['province']       ?? '');
        $city          = sanitize($_POST['city']           ?? '');
        $barangay      = sanitize($_POST['barangay']       ?? '');
        $street_address = sanitize($_POST['street_address'] ?? '');

        $result = db_execute(
            "UPDATE customers SET region=?, province=?, city=?, barangay=?, street_address=? WHERE customer_id=?",
            'sssssi',
            [$region, $province, $city, $barangay, $street_address, $customer_id]
        );

        if ($result) {
            $success = 'Address updated successfully!';
            $customer = db_query("SELECT * FROM customers WHERE customer_id = ?", 'i', [$customer_id])[0];
        } else {
            $error = 'Failed to update address';
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $pw_errors = [];
        if (empty($current_password) || empty($new_password)) {
            $error = 'All password fields are required.';
        } elseif (!password_verify($current_password, $customer['password_hash'])) {
            $error = 'Current password is incorrect.';
        } else {
            if (strlen($new_password) < 8 || strlen($new_password) > 64) $pw_errors[] = '8-64 characters';
            if (!preg_match('/[A-Z]/', $new_password)) $pw_errors[] = 'uppercase letter';
            if (!preg_match('/[a-z]/', $new_password)) $pw_errors[] = 'lowercase letter';
            if (!preg_match('/[0-9]/', $new_password)) $pw_errors[] = 'number';
            if (!preg_match('/[^A-Za-z0-9]/', $new_password)) $pw_errors[] = 'special character';

            if (!empty($pw_errors)) {
                $error = 'Password must contain: ' . implode(', ', $pw_errors) . '.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'New passwords do not match.';
            } else {
                $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $result = db_execute("UPDATE customers SET password_hash = ? WHERE customer_id = ?", 'si', [$password_hash, $customer_id]);
                
                if ($result) {
                    $success = 'Password changed successfully!';
                } else {
                    $error = 'Failed to change password.';
                }
            }
        }
    }
}

$max_birthday = date('Y-m-d', strtotime('-13 years'));

$page_title = 'My Profile - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="container mx-auto px-4">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">My Profile</h1>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 gap-8">
            <!-- Profile Information -->
            <div class="card">
                <h2 class="text-xl font-bold mb-4">Profile Information</h2>
                
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="grid grid-cols-3 gap-8 mb-12">
                        <div class="mb-2">
                            <label for="first_name" class="block text-sm font-bold text-gray-800 mb-3">First Name <span class="required-asterisk">*</span></label>
                            <input type="text" id="first_name" name="first_name" class="input-field py-3 validate-advanced-name" placeholder="First Name" required value="<?php echo htmlspecialchars($customer['first_name']); ?>" maxlength="50">
                            <div class="live-indicator mt-1 flex items-center gap-1 text-[11px] font-medium" data-for="first_name"></div>
                        </div>
                        
                        <div class="mb-2">
                            <label for="middle_name" class="block text-sm font-bold text-gray-800 mb-3">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" class="input-field py-3 validate-advanced-name" placeholder="Middle Name" value="<?php echo htmlspecialchars($customer['middle_name'] ?? ''); ?>" maxlength="50">
                            <div class="live-indicator mt-1 flex items-center gap-1 text-[11px] font-medium" data-for="middle_name"></div>
                        </div>

                        <div class="mb-2">
                            <label for="last_name" class="block text-sm font-bold text-gray-800 mb-3">Last Name <span class="required-asterisk">*</span></label>
                            <input type="text" id="last_name" name="last_name" class="input-field py-3 validate-advanced-name" placeholder="Last Name" required value="<?php echo htmlspecialchars($customer['last_name']); ?>" maxlength="50">
                            <div class="live-indicator mt-1 flex items-center gap-1 text-[11px] font-medium" data-for="last_name"></div>
                        </div>
                    </div>

                    <div class="custom-grid-4 mb-12">
                        <div class="mb-2">
                            <label for="email" class="block text-sm font-bold text-gray-800 mb-3">Email address</label>
                            <input type="email" id="email" class="input-field bg-gray-50 border-gray-200 text-gray-400 py-3 validate-advanced-email" placeholder="Email address" value="<?php echo htmlspecialchars($customer['email']); ?>" disabled>
                            <div class="live-indicator mt-1 flex items-center gap-1 text-[11px] font-medium" data-for="email"></div>
                        </div>

                        <div class="mb-2">
                            <label for="contact_number" class="block text-sm font-bold text-gray-800 mb-3">Contact Number <span class="required-asterisk">*</span></label>
                            <input type="tel" id="contact_number" name="contact_number" class="input-field py-3 validate-advanced-contact" placeholder="+639XXXXXXXXX" value="<?php echo htmlspecialchars($customer['contact_number'] ?? ''); ?>" maxlength="13" required>
                            <div class="live-indicator mt-1 flex items-center gap-1 text-[11px] font-medium" data-for="contact_number"></div>
                        </div>

                        <div class="mb-2">
                            <label for="dob" class="block text-sm font-bold text-gray-800 mb-3">Date of Birth</label>
                            <input type="date" id="dob" name="dob" class="input-field py-3 validate-advanced-dob" value="<?php echo htmlspecialchars($customer['dob'] ?? ''); ?>" max="<?php echo $max_birthday; ?>">
                            <div class="live-indicator mt-1 flex items-center gap-1 text-[11px] font-medium" data-for="dob"></div>
                        </div>
                        
                        <div class="mb-2">
                            <label for="gender" class="block text-sm font-bold text-gray-800 mb-3">Gender</label>
                            <select id="gender" name="gender" class="input-field py-3">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo ($customer['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($customer['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($customer['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-start; margin-top: 1.5rem;">
                        <button type="submit" id="btn-update-profile" class="btn-dark" style="width: auto; padding: 0.75rem 2.5rem;">Update Profile</button>
                    </div>
                </form>
            </div>

            <!-- Edit Address -->
            <div class="card" id="address-card">
                <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:1.5rem;">
                    <div style="width:2.25rem;height:2.25rem;background:#f0f7f9;border-radius:0.6rem;display:flex;align-items:center;justify-content:center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#0a2530" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold" style="margin:0;">Edit Address</h2>
                        <p style="font-size:0.78rem;color:#6b7280;margin:2px 0 0;">Select your location from Region down to Barangay</p>
                    </div>
                </div>

                <form method="POST" action="" id="address-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="update_address" value="1">

                    <!-- Alert box -->
                    <div id="addr-alert" style="display:none;padding:0.75rem 1rem;border-radius:0.5rem;margin-bottom:1.25rem;font-size:0.875rem;font-weight:500;"></div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <!-- Region -->
                        <div>
                            <label class="block text-sm font-bold text-gray-800 mb-2" for="addr_region">Region</label>
                            <div class="addr-select-wrap">
                                <select id="addr_region" name="region" class="input-field addr-select" data-level="region">
                                    <option value="">— Select Region —</option>
                                </select>
                                <span class="addr-spinner" id="spin_region"></span>
                            </div>
                        </div>

                        <!-- Province -->
                        <div>
                            <label class="block text-sm font-bold text-gray-800 mb-2" for="addr_province">Province</label>
                            <div class="addr-select-wrap">
                                <select id="addr_province" name="province" class="input-field addr-select" data-level="province" disabled>
                                    <option value="">— Select Province —</option>
                                </select>
                                <span class="addr-spinner" id="spin_province"></span>
                            </div>
                        </div>

                        <!-- City / Municipality -->
                        <div>
                            <label class="block text-sm font-bold text-gray-800 mb-2" for="addr_city">City / Municipality</label>
                            <div class="addr-select-wrap">
                                <select id="addr_city" name="city" class="input-field addr-select" data-level="city" disabled>
                                    <option value="">— Select City / Municipality —</option>
                                </select>
                                <span class="addr-spinner" id="spin_city"></span>
                            </div>
                        </div>

                        <!-- Barangay -->
                        <div>
                            <label class="block text-sm font-bold text-gray-800 mb-2" for="addr_barangay">Barangay</label>
                            <div class="addr-select-wrap">
                                <select id="addr_barangay" name="barangay" class="input-field addr-select" data-level="barangay" disabled>
                                    <option value="">— Select Barangay —</option>
                                </select>
                                <span class="addr-spinner" id="spin_barangay"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Street Address -->
                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-800 mb-2" for="addr_street">Street Address / House No. / Lot / Block</label>
                        <input type="text" id="addr_street" name="street_address" class="input-field"
                               placeholder="e.g. 123 Sampaguita St., Brgy. Poblacion"
                               value="<?php echo htmlspecialchars($customer['street_address'] ?? ''); ?>">
                    </div>

                    <!-- Full assembled address preview -->
                    <div id="addr-preview" style="display:none;background:#f0f7f9;border:1px solid #b0e0ee;border-radius:0.5rem;padding:0.75rem 1rem;margin-bottom:1.25rem;font-size:0.875rem;color:#0a2530;">
                        <strong>📍 Selected Address:</strong> <span id="addr-preview-text"></span>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-dark" style="width:auto;padding:0.75rem 2.5rem;">Save Address</button>
                    </div>
                </form>
            </div>

            <!-- Change Password -->
            <div class="card" id="change-password">
                <h2 class="text-xl font-bold mb-4">Change Password</h2>
                
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div>
                            <label for="current_password" class="block text-sm font-bold text-gray-800 mb-3">Current Password <span class="required-asterisk">*</span></label>
                            <input type="password" id="current_password" name="current_password" class="input-field py-3" placeholder="••••••••" required>
                        </div>

                        <div>
                            <label for="new_password" class="block text-sm font-bold text-gray-800 mb-3">New Password <span class="required-asterisk">*</span></label>
                            <input type="password" id="new_password" name="new_password" class="input-field py-3" placeholder="••••••••" required minlength="8">
                            <p class="text-[11px] text-gray-500 mt-1 pl-1">Minimum 8 characters</p>
                            <ul class="pw-checklist mt-2 hidden grid-cols-2 gap-1 text-[10px]" id="pw-checklist" style="display:none;">
                                <li id="pw-rule-len" class="text-red-500 flex items-center gap-1"><span class="ck font-bold">✗</span> 8–64 characters</li>
                                <li id="pw-rule-upper" class="text-red-500 flex items-center gap-1"><span class="ck font-bold">✗</span> Uppercase</li>
                                <li id="pw-rule-lower" class="text-red-500 flex items-center gap-1"><span class="ck font-bold">✗</span> Lowercase</li>
                                <li id="pw-rule-num" class="text-red-500 flex items-center gap-1"><span class="ck font-bold">✗</span> Number</li>
                                <li id="pw-rule-spec" class="text-red-500 flex items-center gap-1"><span class="ck font-bold">✗</span> Special char</li>
                            </ul>
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-sm font-bold text-gray-800 mb-3">Confirm Password <span class="required-asterisk">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" class="input-field py-3" placeholder="••••••••" required minlength="8">
                            <p class="text-[11px] font-bold mt-2" id="pw-match-indicator"></p>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-dark" style="width:auto; padding:0.6rem 1.6rem; font-size:0.9rem;">Change Password</button>
                    </div>
                </form>
            </div>
        </div>


    </div>
</div>

<style>
/* ── Cascading Address Selector ── */
.addr-select-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.addr-select-wrap select.input-field {
    padding-right: 2.8rem;
}
.addr-select-wrap select:disabled {
    background: #f3f7f9;
    color: #9ca3af;
    cursor: not-allowed;
    border-color: #e5e7eb;
}
.addr-spinner {
    display: none;
    position: absolute;
    right: 2.2rem;
    width: 14px;
    height: 14px;
    border: 2px solid #d1d5db;
    border-top-color: #0a2530;
    border-radius: 50%;
    animation: addr-spin 0.7s linear infinite;
    pointer-events: none;
}
.addr-spinner.spinning { display: block; }
@keyframes addr-spin { to { transform: rotate(360deg); } }

.addr-select-wrap select:disabled + .addr-spinner { display: none !important; }

/* Mobile responsive: stack to 1 column */
@media (max-width: 640px) {
    #address-card .grid-cols-2 {
        grid-template-columns: 1fr !important;
    }
}
/* Custom grid for 4 columns profile row responsive */
.custom-grid-4 {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
}
@media (min-width: 768px) {
    .custom-grid-4 {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}
.required-asterisk {
    color: #dc2626;
    font-weight: 800;
}
/* Live validation indicators */
.live-indicator { font-size: 0.75rem; min-height: 1.25rem; transition: color 0.2s, opacity 0.2s; }
.live-indicator.valid { color: #16a34a; }
.live-indicator.error { color: #dc2626; font-weight: 600; }
.live-indicator .ind-icon { display: inline-block; margin-right: 0.25rem; font-weight: bold; }
.live-indicator .hint { color: #6b7280; font-weight: 400; }
.input-field.input-valid { border-color: #16a34a; box-shadow: 0 0 0 1px rgba(22,163,74,0.3); }
.input-field.input-error { border-color: #dc2626; box-shadow: 0 0 0 1px rgba(220,38,38,0.3); }
</style>

<script>
(function () {
    // ── Saved values from PHP (for pre-selection) ──
    const SAVED = {
        region:        <?php echo json_encode($customer['region']   ?? null); ?>,
        province:      <?php echo json_encode($customer['province'] ?? null); ?>,
        city:          <?php echo json_encode($customer['city']     ?? null); ?>,
        barangay:      <?php echo json_encode($customer['barangay'] ?? null); ?>,
    };

    const API = '/printflow/customer/api_address.php';

    const selRegion   = document.getElementById('addr_region');
    const selProvince = document.getElementById('addr_province');
    const selCity     = document.getElementById('addr_city');
    const selBarangay = document.getElementById('addr_barangay');

    const spinOf = {
        region:   document.getElementById('spin_region'),
        province: document.getElementById('spin_province'),
        city:     document.getElementById('spin_city'),
        barangay: document.getElementById('spin_barangay'),
    };

    const preview     = document.getElementById('addr-preview');
    const previewText = document.getElementById('addr-preview-text');

    // ── Utility: show/hide spinner ──
    function spin(level, on) {
        spinOf[level].classList.toggle('spinning', on);
    }

    // ── Utility: populate a <select> with [{code, name}] or [string] ──
    function populate(sel, items, placeholder, savedCode, levelName) {
        sel.innerHTML = '';
        const ph = document.createElement('option');
        ph.value = '';
        ph.textContent = placeholder;
        sel.appendChild(ph);

        items.forEach(item => {
            const opt = document.createElement('option');
            const code = typeof item === 'string' ? item : item.code;
            const name = typeof item === 'string' ? item : item.name;
            opt.value = name;         // we store the human-readable name in DB
            opt.dataset.code = code;  // keep PSGC code for subsequent API calls
            opt.textContent = name;
            if (savedCode && (name === savedCode || code === savedCode)) {
                opt.selected = true;
            }
            sel.appendChild(opt);
        });

        sel.disabled = (items.length === 0);
    }

    // ── Utility: reset a select back to disabled/empty ──
    function reset(sel, placeholder) {
        sel.innerHTML = `<option value="">${placeholder}</option>`;
        sel.disabled = true;
    }

    // ── Get the PSGC code from the currently selected option ──
    function selectedCode(sel) {
        const opt = sel.options[sel.selectedIndex];
        return opt ? opt.dataset.code || '' : '';
    }

    // ── Update address preview box ──
    function updatePreview() {
        const parts = [
            selBarangay.value, selCity.value,
            selProvince.value, selRegion.value
        ].filter(Boolean);
        const street = document.getElementById('addr_street').value.trim();
        if (street) parts.unshift(street);

        if (parts.length > 1) {
            previewText.textContent = parts.join(', ');
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    }

    // ── FETCH regions ──
    async function loadRegions() {
        spin('region', true);
        selRegion.disabled = true;
        try {
            const res  = await fetch(`${API}?action=regions`);
            const data = await res.json();
            if (data.success) {
                populate(selRegion, data.data, '— Select Region —', SAVED.region, 'region');
                selRegion.disabled = false;
                // Auto-cascade if a saved region exists
                if (SAVED.region && selectedCode(selRegion)) {
                    await loadProvinces(selectedCode(selRegion), true);
                }
            }
        } catch(e) { console.error('Addr: regions', e); }
        spin('region', false);
    }

    // ── FETCH provinces ──
    async function loadProvinces(regionCode, auto) {
        spin('province', true);
        reset(selCity,     '— Select City / Municipality —');
        reset(selBarangay, '— Select Barangay —');
        try {
            const res  = await fetch(`${API}?action=provinces&region=${regionCode}`);
            const data = await res.json();
            if (data.success) {
                populate(selProvince, data.data, '— Select Province —', auto ? SAVED.province : null, 'province');
                if (auto && SAVED.province && selectedCode(selProvince)) {
                    await loadCities(selectedCode(selProvince), true);
                }
            }
        } catch(e) { console.error('Addr: provinces', e); }
        spin('province', false);
        updatePreview();
    }

    // ── FETCH cities ──
    async function loadCities(provinceCode, auto) {
        spin('city', true);
        reset(selBarangay, '— Select Barangay —');
        try {
            const res  = await fetch(`${API}?action=cities&province=${provinceCode}`);
            const data = await res.json();
            if (data.success) {
                populate(selCity, data.data, '— Select City / Municipality —', auto ? SAVED.city : null, 'city');
                if (auto && SAVED.city && selectedCode(selCity)) {
                    await loadBarangays(selectedCode(selCity), true);
                }
            }
        } catch(e) { console.error('Addr: cities', e); }
        spin('city', false);
        updatePreview();
    }

    // ── FETCH barangays ──
    async function loadBarangays(cityCode, auto) {
        spin('barangay', true);
        try {
            const res  = await fetch(`${API}?action=barangays&city=${cityCode}`);
            const data = await res.json();
            if (data.success) {
                populate(selBarangay, data.data, '— Select Barangay —', auto ? SAVED.barangay : null, 'barangay');
            }
        } catch(e) { console.error('Addr: barangays', e); }
        spin('barangay', false);
        updatePreview();
    }

    // ── Event: Region changed ──
    selRegion.addEventListener('change', function () {
        reset(selProvince, '— Select Province —');
        reset(selCity,     '— Select City / Municipality —');
        reset(selBarangay, '— Select Barangay —');
        updatePreview();
        const code = selectedCode(this);
        if (code) loadProvinces(code, false);
    });

    // ── Event: Province changed ──
    selProvince.addEventListener('change', function () {
        reset(selCity,     '— Select City / Municipality —');
        reset(selBarangay, '— Select Barangay —');
        updatePreview();
        const code = selectedCode(this);
        if (code) loadCities(code, false);
    });

    // ── Event: City changed ──
    selCity.addEventListener('change', function () {
        reset(selBarangay, '— Select Barangay —');
        updatePreview();
        const code = selectedCode(this);
        if (code) loadBarangays(code, false);
    });

    // ── Event: Barangay changed ──
    selBarangay.addEventListener('change', updatePreview);

    // ── Event: Street changed ──
    document.getElementById('addr_street').addEventListener('input', updatePreview);

    // ── Boot: load all regions on page load ──
    loadRegions();
})();
</script>

<script>
// Advanced Validations Logic
(function() {
    const indicators = {};
    document.querySelectorAll('.live-indicator').forEach(el => {
        indicators[el.dataset.for] = el;
    });

    const fProfile = document.getElementById('btn-update-profile')?.closest('form');
    const btnSubmit = document.getElementById('btn-update-profile');

    const REGEX = {
        name: /^[A-Za-z]+( [A-Za-z]+){0,2}$/,
        email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    };

    const HINTS = {
        first_name: 'Letters only, max 3 words',
        middle_name: 'Letters only, max 3 words (optional)',
        last_name: 'Letters only, max 3 words',
        contact_number: 'Format: +639XXXXXXXXX',
        dob: 'You must be at least 13 years old'
    };

    function updateIndicator(fieldId, isValid, message) {
        const el = indicators[fieldId];
        const input = document.getElementById(fieldId);
        if (!el || !input) return;
        
        el.classList.remove('valid', 'error');
        input.classList.remove('input-valid', 'input-error');
        
        if (isValid) {
            el.innerHTML = '';
            el.dataset.valid = '1';
        } else {
            el.innerHTML = '<span class="ind-icon">!</span> ' + message;
            el.dataset.valid = '0';
            el.classList.add('error');
            input.classList.add('input-error');
        }
        checkFormValidity();
    }

    function showHint(fieldId) {
        const el = indicators[fieldId];
        if (!el || el.dataset.valid === '1' || el.dataset.valid === '0') return;
        const hint = HINTS[fieldId];
        if (hint) el.innerHTML = '<span class="hint">' + hint + '</span>';
    }

    function clearHint(fieldId) {
        const el = indicators[fieldId];
        if (el && !el.dataset.valid) el.innerHTML = '';
    }

    function checkFormValidity() {
        if (!fProfile || !btnSubmit) return;
        const inputs = fProfile.querySelectorAll('.validate-advanced-name, .validate-advanced-dob, .validate-advanced-contact');
        let allValid = true;
        
        inputs.forEach(input => {
            const el = indicators[input.id];
            if (!el || el.dataset.valid !== '1') {
                if (input.required || (input.value.trim().length > 0)) {
                    // For non-required fields like middle_name or contact_number, only mark as invalid if they have value but failed validation
                    if ((input.id === 'middle_name' || input.id === 'contact_number') && input.value.trim().length === 0) {
                        // skip
                    } else {
                        allValid = false;
                    }
                }
            }
        });

        btnSubmit.disabled = !allValid;
        btnSubmit.style.opacity = allValid ? '1' : '0.5';
        btnSubmit.style.cursor = allValid ? 'pointer' : 'not-allowed';
    }

    function normalizeSpaces(val) {
        return val.replace(/\s+/g, ' ');
    }

    function toTitleCase(str) {
        return str.toLowerCase().replace(/\b\w/g, function(c) { return c.toUpperCase(); });
    }

    // Name Validation
    document.querySelectorAll('.validate-advanced-name').forEach(input => {
        input.addEventListener('input', function() {
            // Prevent numbers and special characters immediately
            let val = this.value.replace(/[^A-Za-z ]/g, '');
            // Prevent multiple consecutive spaces
            val = val.replace(/ +(?= )/g, '');
            // Auto capitalize first letter of each word
            val = toTitleCase(val);
            this.value = val;

            const trimmed = val.trim();
            if (trimmed.length === 0) {
                if (this.required) {
                    updateIndicator(this.id, false, 'This field is required');
                } else {
                    const ind = indicators[this.id];
                    if (ind) {
                        ind.innerHTML = '<span class="hint">' + (HINTS[this.id] || '') + '</span>';
                        ind.dataset.valid = '1';
                    }
                    this.classList.remove('input-valid', 'input-error');
                    checkFormValidity();
                }
                return;
            }

            if (!REGEX.name.test(trimmed)) {
                let msg = 'Use letters only, max 3 words (e.g. Juan Carlos)';
                const words = trimmed.split(/\s+/).filter(Boolean).length;
                if (words > 3) msg = 'Maximum 3 words allowed';
                else if (/[0-9]/.test(trimmed)) msg = 'Numbers not allowed';
                else if (/[^A-Za-z\s]/.test(trimmed)) msg = 'Letters and spaces only';
                updateIndicator(this.id, false, msg);
            } else {
                updateIndicator(this.id, true);
            }
        });

        input.addEventListener('blur', function() {
            this.value = normalizeSpaces(this.value).trim();
            this.dispatchEvent(new Event('input'));
        });
        input.addEventListener('focus', function() {
            if (this.value.trim() === '' && indicators[this.id]) {
                const hint = HINTS[this.id] || (this.required ? 'This field is required' : '');
                if (hint) {
                    indicators[this.id].innerHTML = '<span class="hint">' + hint + '</span>';
                    indicators[this.id].dataset.valid = '';
                    this.classList.remove('input-valid', 'input-error');
                }
            }
        });
    });

    // Contact Number Validation
    document.querySelectorAll('.validate-advanced-contact').forEach(input => {
        const regexContact = /^\+639\d{9}$/;

        input.addEventListener('input', function() {
            let val = this.value;
            
            // Re-enforce +63 if deleted, but allow empty while typing if necessary
            // However, the rule says "Must start with +63"
            if (val.length > 0 && !val.startsWith('+')) {
                val = '+' + val.replace(/\+/g, '');
            }
            if (val.length > 1 && val !== '+' && val.charAt(1) !== '6') {
                val = '+6' + val.substring(1).replace(/6/g, '');
            }
            // Strict digit restriction after +
            val = val.substring(0, 1) + val.substring(1).replace(/[^0-9]/g, '');
            
            // Limit to 13 characters
            if (val.length > 13) val = val.substring(0, 13);
            
            this.value = val;

            const trimmed = val.trim();
            if (trimmed.length === 0) {
                updateIndicator(this.id, false, 'This field is required');
                return;
            }

            if (!regexContact.test(trimmed)) {
                updateIndicator(this.id, false, 'Use format +639XXXXXXXXX (11 digits after +63)');
            } else {
                updateIndicator(this.id, true);
            }
        });

        input.addEventListener('focus', function() {
            if (this.value === '') {
                this.value = '+639';
                this.dispatchEvent(new Event('input'));
            } else if (!regexContact.test(this.value.trim()) && indicators[this.id]) {
                indicators[this.id].innerHTML = '<span class="hint">' + HINTS.contact_number + '</span>';
            }
        });

        input.addEventListener('paste', function(e) {
            e.preventDefault();
            let paste = (e.clipboardData || window.clipboardData).getData('text');
            // Basic normalization: remove spaces, handle 09 -> +639
            paste = paste.replace(/\s/g, '');
            if (paste.startsWith('09')) paste = '+63' + paste.substring(1);
            if (paste.startsWith('9')) paste = '+63' + paste;
            
            this.value = paste;
            this.dispatchEvent(new Event('input'));
        });
    });

    // DOB Validation
    const dobInput = document.querySelector('.validate-advanced-dob');
    if (dobInput) {
        dobInput.addEventListener('focus', function() {
            if (!this.value && indicators[this.id]) {
                indicators[this.id].innerHTML = '<span class="hint">' + HINTS.dob + '</span>';
                indicators[this.id].dataset.valid = '';
            }
        });
        dobInput.addEventListener('input', function() {
            if (!this.value) {
                updateIndicator(this.id, false, 'Select your date of birth');
                return;
            }
            const dob = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const m = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;

            if (dob > today) {
                updateIndicator(this.id, false, 'Date cannot be in the future');
            } else if (age < 13) {
                updateIndicator(this.id, false, 'You must be at least 13 years old');
            } else {
                updateIndicator(this.id, true);
            }
        });
    }

    // Initial check on load - validate filled fields, show hints for empty
    document.querySelectorAll('.validate-advanced-name, .validate-advanced-dob, .validate-advanced-contact').forEach(input => {
        if (input.value.trim()) {
            input.dispatchEvent(new Event('input'));
        } else if (indicators[input.id] && HINTS[input.id]) {
            indicators[input.id].innerHTML = '<span class="hint">' + HINTS[input.id] + '</span>';
        }
    });

    if (fProfile) {
        fProfile.addEventListener('submit', function(e) {
            checkFormValidity();
            if (btnSubmit.disabled) {
                e.preventDefault();
                alert('Please correct all invalid fields before updating.');
            }
        });
    }

    // Password fields (keeping existing logic but integrating with indicators if needed)
    // [Existing password logic continues below...]
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

