<?php
/**
 * Staff Profile Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Staff');

$user_id = get_user_id();
$error = '';
$success = '';

$user = db_query("SELECT * FROM users WHERE user_id = ?", 'i', [$user_id])[0];
$is_pending = ($user['status'] ?? '') === 'Pending';
$needs_id = $is_pending && empty($user['id_validation_image'] ?? '');

// Parse address for province/city/barangay
$addressProvince = $addressCity = $addressBarangay = $addressLine = '';
if (!empty($user['address'])) {
    $parts = array_values(array_filter(array_map('trim', explode(',', $user['address'])), static fn($p) => $p !== ''));
    if (count($parts) >= 4 && strcasecmp(end($parts), 'Philippines') === 0) {
        $addressProvince = $parts[count($parts) - 2] ?? '';
        $addressCity = $parts[count($parts) - 3] ?? '';
        $addressBarangay = preg_replace('/^Brgy\.?\s*/i', '', (string)($parts[count($parts) - 4] ?? ''));
        $addressLine = implode(', ', array_slice($parts, 0, -4));
    } else {
        $addressLine = $user['address'];
    }
}

function is_valid_name_value($value) {
    return (bool)preg_match('/^[a-zA-Z\s\.\'-]{2,60}$/', $value);
}

function is_valid_contact_value($value) {
    if ($value === '') return true;
    return (bool)preg_match('/^(\+63|0)?9\d{9}$/', preg_replace('/\s+/', '', $value));
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $first_name = sanitize($_POST['first_name'] ?? '');
        $middle_name = sanitize($_POST['middle_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
<<<<<<< HEAD
        $contact_number = sanitize($_POST['contact_number'] ?? '');
        
        if (empty($first_name) || empty($last_name)) {
            $error = 'First name and last name are required';
        } elseif (!is_valid_name_value($first_name) || !is_valid_name_value($last_name)) {
            $error = 'Names must be 2-60 characters and contain letters only';
        } elseif (!empty($middle_name) && !is_valid_name_value($middle_name)) {
            $error = 'Middle name format is invalid';
        } elseif (!is_valid_contact_value($contact_number)) {
            $error = 'Contact number must be a valid Philippine mobile number';
        } else {
            $region        = sanitize($_POST['region']         ?? '');
            $province      = sanitize($_POST['province']       ?? '');
            $city          = sanitize($_POST['city']           ?? '');
            $barangay      = sanitize($_POST['barangay']       ?? '');
            $street_address = sanitize($_POST['street_address'] ?? '');

            $result = db_execute("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, contact_number = ?, region = ?, province = ?, city = ?, barangay = ?, street_address = ? WHERE user_id = ?",
                'sssssssssi', [$first_name, $middle_name, $last_name, $contact_number, $region, $province, $city, $barangay, $street_address, $user_id]);
            
            if ($result) {
                $success = 'Profile updated successfully!';
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $user = db_query("SELECT * FROM users WHERE user_id = ?", 'i', [$user_id])[0];
            } else {
                $error = 'Failed to update profile';
=======
        $contact_number = preg_replace('/[^0-9]/', '', trim($_POST['contact_number'] ?? ''));
        $address_province = trim($_POST['address_province'] ?? '');
        $address_city = trim($_POST['address_city'] ?? '');
        $address_barangay = trim($_POST['address_barangay'] ?? '');
        $address_line = trim($_POST['address_line'] ?? '');
        $gender = trim($_POST['gender'] ?? '');

        $addressParts = [];
        if ($address_line !== '') $addressParts[] = $address_line;
        if ($address_barangay !== '') $addressParts[] = 'Brgy. ' . $address_barangay;
        if ($address_city !== '') $addressParts[] = $address_city;
        if ($address_province !== '') $addressParts[] = $address_province;
        $addressParts[] = 'Philippines';
        $address = implode(', ', $addressParts);

        if (empty($first_name) || empty($last_name)) {
            $error = 'First name and last name are required';
        } elseif (empty($contact_number) || !preg_match('/^09\d{9}$/', $contact_number)) {
            $error = 'Valid contact number required (09XXXXXXXXX).';
        } elseif (strlen($address) < 10) {
            $error = 'Please complete the address (province, city, barangay).';
        } elseif ($needs_id && (empty($_FILES['id_image']['tmp_name']) || $_FILES['id_image']['error'] !== UPLOAD_ERR_OK)) {
            $error = 'Please upload a clear, valid ID image.';
        } else {
            $id_filename = $user['id_validation_image'] ?? null;
            if (!empty($_FILES['id_image']['tmp_name']) && $_FILES['id_image']['error'] === UPLOAD_ERR_OK) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($_FILES['id_image']['tmp_name']);
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($mime, $allowed)) {
                    $error = 'ID image must be JPG, PNG, GIF, or WEBP.';
                } elseif ($_FILES['id_image']['size'] > 5 * 1024 * 1024) {
                    $error = 'ID image must be under 5MB.';
                } else {
                    $ext = pathinfo($_FILES['id_image']['name'], PATHINFO_EXTENSION) ?: 'jpg';
                    $id_filename = 'id_user_' . $user_id . '_' . time() . '.' . $ext;
                    $upload_dir = __DIR__ . '/../uploads/ids/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    if (!move_uploaded_file($_FILES['id_image']['tmp_name'], $upload_dir . $id_filename)) {
                        $error = 'Failed to save ID image. Please try again.';
                        $id_filename = null;
                    }
                }
            }
            if (!$error) {
                $id_to_save = $id_filename ?? $user['id_validation_image'] ?? null;
                $result = db_execute(
                    "UPDATE users SET first_name=?, middle_name=?, last_name=?, contact_number=?, address=?, gender=?, id_validation_image=?, updated_at=NOW() WHERE user_id=?",
                    'sssssssi',
                    [$first_name, $middle_name, $last_name, $contact_number, $address, $gender, $id_to_save, $user_id]
                );
                if ($result) {
                    $success = 'Profile updated successfully!';
                    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                    $user = db_query("SELECT * FROM users WHERE user_id = ?", 'i', [$user_id])[0];
                    $needs_id = false;
                    if ($is_pending && $id_filename) {
                        $full_name = trim($first_name . ' ' . ($middle_name ?? '') . ' ' . $last_name);
                        $msg = $full_name . ' (' . $user['email'] . ') has completed their profile and is ready for admin review.';
                        $admins = db_query("SELECT user_id FROM users WHERE role = 'Admin' AND status = 'Activated'");
                        foreach ($admins as $a) {
                            create_notification((int)$a['user_id'], 'User', $msg, 'System', true, false, $user_id);
                        }
                    }
                } else {
                    $error = 'Failed to update profile';
                }
>>>>>>> 04d53d75d5323397db2238c2717dfa1e7e2e79fe
            }
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
        
        if (!password_verify($current_password, $user['password_hash'])) {
            $error = 'Current password is incorrect';
        } elseif (strlen($new_password) < 8 || !preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/\d/', $new_password)) {
            $error = 'New password must be at least 8 characters and include uppercase, lowercase, and a number';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match';
        } else {
            $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $result = db_execute("UPDATE users SET password_hash = ? WHERE user_id = ?", 'si', [$password_hash, $user_id]);
            
            if ($result !== false) {
                $success = 'Password changed successfully!';
                log_activity($user_id, 'Password Change', 'Staff member changed password');
            } else {
                $error = 'Failed to change password';
            }
        }
    }
}

$page_title = 'My Profile - Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .alert-error { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:14px; }
        .alert-success { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:14px; }
        .profile-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
        @media (max-width: 900px) { .profile-grid { grid-template-columns:1fr; } }
        .info-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; }
        @media (max-width: 768px) { .info-grid { grid-template-columns:1fr; } }
        .info-item p:first-child { font-size:12px; color:#9ca3af; margin-bottom:4px; }
        .info-item p:last-child { font-size:14px; font-weight:600; color:#1f2937; }
        textarea.input-field { resize: vertical; min-height: 80px; }
        .required-asterisk { color:#dc2626; font-weight:700; }
        .field-hint { font-size:11px; margin-top:4px; min-height:16px; color:#9ca3af; }
        .field-hint.error { color:#dc2626; }

        /* Cascading Address Selector */
        .addr-select-wrap { position: relative; display: flex; align-items: center; }
        .addr-select-wrap select.input-field { padding-right: 2.8rem; }
        .addr-select-wrap select:disabled { background: #f3f7f9; color: #9ca3af; cursor: not-allowed; border-color: #e5e7eb; }
        .addr-spinner { display: none; position: absolute; right: 2.2rem; width: 14px; height: 14px; border: 2px solid #d1d5db; border-top-color: #0a2530; border-radius: 50%; animation: addr-spin 0.7s linear infinite; pointer-events: none; }
        .addr-spinner.spinning { display: block; }
        @keyframes addr-spin { to { transform: rotate(360deg); } }
        .addr-select-wrap select:disabled + .addr-spinner { display: none !important; }
    </style>
</head>
<body data-turbo="false">

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">My Profile</h1>
        </header>

        <main>
            <?php if (isset($_SESSION['user_status']) && $_SESSION['user_status'] === 'Pending'): ?>
                <div style="background:linear-gradient(135deg, #fef3c7, #fde68a); border:1px solid #f59e0b; border-radius:12px; padding:20px 24px; margin-bottom:20px; display:flex; align-items:center; gap:16px;">
                    <div style="font-size:32px;">⏳</div>
                    <div>
                        <h3 style="font-weight:700; color:#92400e; margin-bottom:4px; font-size:16px;">Account Pending Approval</h3>
                        <p style="font-size:13px; color:#92400e; line-height:1.5;">Please complete your profile information below. Once submitted, an administrator will review and approve your account. You'll then have full access to the staff panel.</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="profile-grid">
                <!-- Profile Information -->
                <div class="card">
                    <h2 style="font-size:18px; font-weight:600; margin-bottom:20px;">Profile Information</h2>
                    
                    <form method="POST" action="" enctype="multipart/form-data" id="profileForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:16px;">
                            <div>
                                <label>First Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="first_name" id="first_name" class="input-field" required value="<?php echo htmlspecialchars($user['first_name']); ?>">
                                <div id="first_name_hint" class="field-hint"></div>
                            </div>
                            <div>
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" id="middle_name" class="input-field" value="<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>">
                                <div id="middle_name_hint" class="field-hint"></div>
                            </div>
                        </div>

                        <div style="margin-bottom:16px;">
                            <label>Last Name <span class="required-asterisk">*</span></label>
                            <input type="text" name="last_name" id="last_name" class="input-field" required value="<?php echo htmlspecialchars($user['last_name']); ?>">
                            <div id="last_name_hint" class="field-hint"></div>
                        </div>

                        <div style="margin-bottom:16px;">
                            <label>Email</label>
                            <input type="email" class="input-field" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="background:#f3f4f6; cursor:not-allowed;">
                            <p style="font-size:11px; color:#9ca3af; margin-top:4px;">Email cannot be changed</p>
                        </div>

                        <div style="margin-bottom:16px;">
<<<<<<< HEAD
                            <label>Contact Number</label>
                            <input type="tel" name="contact_number" id="contact_number" class="input-field" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>">
                            <div id="contact_number_hint" class="field-hint"></div>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                            <div>
                                <label>Region</label>
                                <div class="addr-select-wrap">
                                    <select id="addr_region" name="region" class="input-field addr-select" data-level="region">
                                        <option value="">— Select Region —</option>
                                    </select>
                                    <span class="addr-spinner" id="spin_region"></span>
                                </div>
                            </div>
                            <div>
                                <label>Province</label>
                                <div class="addr-select-wrap">
                                    <select id="addr_province" name="province" class="input-field addr-select" data-level="province" disabled>
                                        <option value="">— Select Province —</option>
                                    </select>
                                    <span class="addr-spinner" id="spin_province"></span>
                                </div>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                            <div>
                                <label>City / Municipality</label>
                                <div class="addr-select-wrap">
                                    <select id="addr_city" name="city" class="input-field addr-select" data-level="city" disabled>
                                        <option value="">— Select City / Municipality —</option>
                                    </select>
                                    <span class="addr-spinner" id="spin_city"></span>
                                </div>
                            </div>
                            <div>
                                <label>Barangay</label>
                                <div class="addr-select-wrap">
                                    <select id="addr_barangay" name="barangay" class="input-field addr-select" data-level="barangay" disabled>
                                        <option value="">— Select Barangay —</option>
                                    </select>
                                    <span class="addr-spinner" id="spin_barangay"></span>
                                </div>
                            </div>
                        </div>

                        <div style="margin-bottom:20px;">
                            <label>Street Address / House No. / Lot / Block</label>
                            <input type="text" id="addr_street" name="street_address" class="input-field" placeholder="e.g. 123 Sampaguita St., Brgy. Poblacion" value="<?php echo htmlspecialchars($user['street_address'] ?? ''); ?>">
=======
                            <label>Contact Number *</label>
                            <input type="tel" name="contact_number" id="profile_contact" class="input-field" placeholder="e.g. 09171234567" maxlength="11" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>">
                        </div>

                        <div style="margin-bottom:16px;">
                            <label>Province *</label>
                            <select name="address_province" id="profile_province" class="input-field" required>
                                <option value="">Select province</option>
                            </select>
>>>>>>> 04d53d75d5323397db2238c2717dfa1e7e2e79fe
                        </div>
                        <div style="margin-bottom:16px;">
                            <label>City / Municipality *</label>
                            <select name="address_city" id="profile_city" class="input-field" required disabled>
                                <option value="">Select city/municipality</option>
                            </select>
                        </div>
                        <div style="margin-bottom:16px;">
                            <label>Barangay *</label>
                            <select name="address_barangay" id="profile_barangay" class="input-field" required disabled>
                                <option value="">Select barangay</option>
                            </select>
                        </div>
                        <div style="margin-bottom:16px;">
                            <label>Street / House No. (Optional)</label>
                            <input type="text" name="address_line" id="profile_address_line" class="input-field" maxlength="120" placeholder="e.g. 123 Rizal St." value="<?php echo htmlspecialchars($addressLine); ?>">
                        </div>
                        <input type="hidden" name="address" id="profile_address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">

                        <div style="margin-bottom:16px;">
                            <label>Gender</label>
                            <select name="gender" class="input-field">
                                <option value="">-- Select --</option>
                                <option value="Male" <?php echo ($user['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($user['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($user['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <?php if ($needs_id): ?>
                        <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:16px; margin-bottom:16px;">
                            <h3 style="font-size:13px; font-weight:600; color:#374151; margin:0 0 8px 0;">ID Photo Reference</h3>
                            <img src="/printflow/uploads/id_validation.png" alt="Valid vs Invalid ID" style="max-width:100%; height:auto; border-radius:6px; margin-bottom:12px;">
                            <label>Upload Valid ID *</label>
                            <label style="display:block; border:2px dashed #e5e7eb; border-radius:8px; padding:24px; text-align:center; cursor:pointer;">
                                <input type="file" name="id_image" accept="image/*" required>
                                <span id="profile_id_label">Click to select ID image (JPG, PNG, max 5MB)</span>
                            </label>
                        </div>
                        <?php elseif (!empty($user['id_validation_image'])): ?>
                        <div style="margin-bottom:16px;">
                            <label>Uploaded ID</label>
                            <div><a href="/printflow/uploads/ids/<?php echo htmlspecialchars($user['id_validation_image']); ?>" target="_blank" rel="noopener" style="color:#0d9488; font-weight:600;">View ID Image</a></div>
                            <label style="display:block; margin-top:8px; border:2px dashed #e5e7eb; border-radius:8px; padding:16px; text-align:center; cursor:pointer;">
                                <input type="file" name="id_image" accept="image/*">
                                <span id="profile_id_label">Replace ID image (optional)</span>
                            </label>
                        </div>
                        <?php endif; ?>

                        <button type="submit" class="btn-primary">Update Profile</button>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="card">
                    <h2 style="font-size:18px; font-weight:600; margin-bottom:20px;">Change Password</h2>
                    
                    <form method="POST" action="">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="change_password" value="1">
                        
                        <div style="margin-bottom:16px;">
                            <label>Current Password <span class="required-asterisk">*</span></label>
                            <input type="password" name="current_password" class="input-field" required>
                        </div>

                        <div style="margin-bottom:16px;">
                            <label>New Password <span class="required-asterisk">*</span></label>
                            <input type="password" name="new_password" id="new_password" class="input-field" required minlength="8">
                            <p style="font-size:11px; color:#9ca3af; margin-top:4px;">Min 8 chars, include upper/lowercase and number</p>
                            <div id="new_password_hint" class="field-hint"></div>
                        </div>

                        <div style="margin-bottom:20px;">
                            <label>Confirm New Password *</label>
                            <input type="password" name="confirm_password" class="input-field" required minlength="8">
                        </div>

                        <button type="submit" class="btn-primary">Change Password</button>
                    </form>
                </div>
            </div>

            <!-- Staff Information Hidden -->
        </main>
    </div>
</div>

<script>
<<<<<<< HEAD
function setHint(id, message) {
    const el = document.getElementById(id + '_hint');
    if (!el) return;
    el.textContent = message || '';
    el.classList.toggle('error', !!message);
}

function validateName(id) {
    const el = document.getElementById(id);
    if (!el) return true;
    const v = el.value.trim();
    const ok = /^[a-zA-Z\s.'-]{2,60}$/.test(v);
    setHint(id, ok || v === '' ? '' : 'Use 2-60 letters only');
    return ok || v === '';
}

function validatePhone() {
    const el = document.getElementById('contact_number');
    if (!el) return true;
    const v = el.value.replace(/\s+/g, '');
    const ok = v === '' || /^(\+63|0)?9\d{9}$/.test(v);
    setHint('contact_number', ok ? '' : 'Invalid Philippine mobile number');
    return ok;
}

function validatePasswordStrength() {
    const el = document.getElementById('new_password');
    if (!el) return true;
    const v = el.value;
    const ok = v.length >= 8 && /[A-Z]/.test(v) && /[a-z]/.test(v) && /\d/.test(v);
    setHint('new_password', ok || v === '' ? '' : 'Must include upper/lowercase and a number');
    return ok || v === '';
}

['first_name','middle_name','last_name'].forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', () => validateName(id));
});
document.getElementById('contact_number')?.addEventListener('input', validatePhone);
document.getElementById('new_password')?.addEventListener('input', validatePasswordStrength);

// Cascading Address Selector Logic
(function () {
    const SAVED = {
        region: <?php echo json_encode($user['region'] ?? null); ?>,
        province: <?php echo json_encode($user['province'] ?? null); ?>,
        city: <?php echo json_encode($user['city'] ?? null); ?>,
        barangay: <?php echo json_encode($user['barangay'] ?? null); ?>,
    };

    const API = '/printflow/customer/api_address.php';

    const selRegion = document.getElementById('addr_region');
    const selProvince = document.getElementById('addr_province');
    const selCity = document.getElementById('addr_city');
    const selBarangay = document.getElementById('addr_barangay');

    const spinOf = {
        region: document.getElementById('spin_region'),
        province: document.getElementById('spin_province'),
        city: document.getElementById('spin_city'),
        barangay: document.getElementById('spin_barangay'),
    };

    function spin(level, on) {
        if (spinOf[level]) spinOf[level].classList.toggle('spinning', on);
    }

    function populate(sel, items, placeholder, savedCode) {
        sel.innerHTML = '';
        const ph = document.createElement('option');
        ph.value = '';
        ph.textContent = placeholder;
        sel.appendChild(ph);

        items.forEach(item => {
            const opt = document.createElement('option');
            const code = typeof item === 'string' ? item : item.code;
            const name = typeof item === 'string' ? item : item.name;
            opt.value = name;
            opt.dataset.code = code;
            opt.textContent = name;
            if (savedCode && (name === savedCode || code === savedCode)) {
                opt.selected = true;
            }
            sel.appendChild(opt);
        });

        sel.disabled = (items.length === 0);
    }

    function reset(sel, placeholder) {
        sel.innerHTML = `<option value="">${placeholder}</option>`;
        sel.disabled = true;
    }

    function selectedCode(sel) {
        const opt = sel.options[sel.selectedIndex];
        return opt ? opt.dataset.code || '' : '';
    }

    async function loadRegions() {
        spin('region', true);
        selRegion.disabled = true;
        try {
            const res = await fetch(`${API}?action=regions`);
            const data = await res.json();
            if (data.success) {
                populate(selRegion, data.data, '— Select Region —', SAVED.region);
                selRegion.disabled = false;
                if (SAVED.region && selectedCode(selRegion)) {
                    await loadProvinces(selectedCode(selRegion), true);
                }
            }
        } catch (e) { console.error('Addr: regions', e); }
        spin('region', false);
    }

    async function loadProvinces(regionCode, auto) {
        spin('province', true);
        reset(selCity, '— Select City / Municipality —');
        reset(selBarangay, '— Select Barangay —');
        try {
            const res = await fetch(`${API}?action=provinces&region=${regionCode}`);
            const data = await res.json();
            if (data.success) {
                populate(selProvince, data.data, '— Select Province —', auto ? SAVED.province : null);
                if (auto && SAVED.province && selectedCode(selProvince)) {
                    await loadCities(selectedCode(selProvince), true);
                }
            }
        } catch (e) { console.error('Addr: provinces', e); }
        spin('province', false);
    }

    async function loadCities(provinceCode, auto) {
        spin('city', true);
        reset(selBarangay, '— Select Barangay —');
        try {
            const res = await fetch(`${API}?action=cities&province=${provinceCode}`);
            const data = await res.json();
            if (data.success) {
                populate(selCity, data.data, '— Select City / Municipality —', auto ? SAVED.city : null);
                if (auto && SAVED.city && selectedCode(selCity)) {
                    await loadBarangays(selectedCode(selCity), true);
                }
            }
        } catch (e) { console.error('Addr: cities', e); }
        spin('city', false);
    }

    async function loadBarangays(cityCode, auto) {
        spin('barangay', true);
        try {
            const res = await fetch(`${API}?action=barangays&city=${cityCode}`);
            const data = await res.json();
            if (data.success) {
                populate(selBarangay, data.data, '— Select Barangay —', auto ? SAVED.barangay : null);
            }
        } catch (e) { console.error('Addr: barangays', e); }
        spin('barangay', false);
    }

    selRegion.addEventListener('change', function () {
        reset(selProvince, '— Select Province —');
        reset(selCity, '— Select City / Municipality —');
        reset(selBarangay, '— Select Barangay —');
        const code = selectedCode(this);
        if (code) loadProvinces(code, false);
    });

    selProvince.addEventListener('change', function () {
        reset(selCity, '— Select City / Municipality —');
        reset(selBarangay, '— Select Barangay —');
        const code = selectedCode(this);
        if (code) loadCities(code, false);
    });

    selCity.addEventListener('change', function () {
        reset(selBarangay, '— Select Barangay —');
        const code = selectedCode(this);
        if (code) loadBarangays(code, false);
    });

    loadRegions();
})();
</script>

=======
(function() {
    const addrApi = '/printflow/public/api_address_public.php';
    const prov = document.getElementById('profile_province');
    const city = document.getElementById('profile_city');
    const brgy = document.getElementById('profile_barangay');
    const line = document.getElementById('profile_address_line');
    const addrHidden = document.getElementById('profile_address');
    if (!prov) return;

    function buildAddress() {
        const p = [line?.value?.trim(), brgy?.value ? 'Brgy. ' + brgy.value : '', city?.value, prov?.value].filter(Boolean);
        if (addrHidden) addrHidden.value = p.length ? p.join(', ') + ', Philippines' : '';
    }

    const selProv = '<?php echo addslashes($addressProvince); ?>';
    const selCity = '<?php echo addslashes($addressCity); ?>';
    const selBrgy = '<?php echo addslashes($addressBarangay); ?>';

    async function loadProvinces() {
        const r = await fetch(addrApi + '?address_action=provinces');
        const d = await r.json();
        if (d.success && d.data) {
            prov.innerHTML = '<option value="">Select province</option>' + d.data.map(x => '<option value="' + x.name + '" data-code="' + x.code + '">' + x.name + '</option>').join('');
            if (selProv) {
                prov.value = selProv;
                const opt = prov.options[prov.selectedIndex];
                await loadCities(opt ? opt.getAttribute('data-code') || '' : '');
            }
        }
    }
    async function loadCities(provinceCode) {
        if (!provinceCode) { city.innerHTML = '<option value="">Select city/municipality</option>'; city.disabled = true; brgy.innerHTML = '<option value="">Select barangay</option>'; brgy.disabled = true; buildAddress(); return; }
        const r = await fetch(addrApi + '?address_action=cities&province_code=' + encodeURIComponent(provinceCode));
        const d = await r.json();
        if (d.success && d.data) {
            city.innerHTML = '<option value="">Select city/municipality</option>' + d.data.map(x => '<option value="' + x.name + '" data-code="' + x.code + '">' + x.name + '</option>').join('');
            city.disabled = false;
            brgy.innerHTML = '<option value="">Select barangay</option>';
            brgy.disabled = true;
            if (selCity) {
                city.value = selCity;
                const cOpt = city.options[city.selectedIndex];
                await loadBarangays(cOpt ? cOpt.getAttribute('data-code') || '' : '');
            }
        }
        buildAddress();
    }
    async function loadBarangays(cityCode) {
        if (!cityCode) { brgy.innerHTML = '<option value="">Select barangay</option>'; brgy.disabled = true; buildAddress(); return; }
        const r = await fetch(addrApi + '?address_action=barangays&city_code=' + encodeURIComponent(cityCode));
        const d = await r.json();
        if (d.success && d.data) {
            brgy.innerHTML = '<option value="">Select barangay</option>' + d.data.map(x => '<option value="' + x.name + '">' + x.name + '</option>').join('');
            brgy.disabled = false;
            if (selBrgy) brgy.value = selBrgy;
        }
        buildAddress();
    }

    loadProvinces();
    prov.addEventListener('change', function() {
        const opt = prov.options[prov.selectedIndex];
        loadCities(opt?.value ? opt.getAttribute('data-code') : '');
    });
    city.addEventListener('change', function() {
        const opt = city.options[city.selectedIndex];
        loadBarangays(opt?.value ? opt.getAttribute('data-code') : '');
    });
    brgy.addEventListener('change', buildAddress);
    if (line) line.addEventListener('input', buildAddress);

    const contactInput = document.getElementById('profile_contact');
    if (contactInput) {
        contactInput.addEventListener('input', function() {
            let v = this.value.replace(/\D/g, '');
            if (v.length > 0 && !v.startsWith('09')) v = '09' + v.replace(/^0+/, '');
            this.value = v.slice(0, 11);
        });
    }

    const idInput = document.querySelector('input[name="id_image"]');
    if (idInput) {
        idInput.addEventListener('change', function() {
            const lbl = document.getElementById('profile_id_label');
            if (lbl) lbl.textContent = this.files[0] ? this.files[0].name : 'Click to select ID image (JPG, PNG, max 5MB)';
        });
    }

    const form = document.getElementById('profileForm');
    if (form) {
        form.addEventListener('submit', function() {
            buildAddress();
        });
    }
})();
</script>
>>>>>>> 04d53d75d5323397db2238c2717dfa1e7e2e79fe
</body>
</html>
