<?php
/**
 * Admin Settings Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$success = '';
$error   = '';

// Directories
$qr_dir   = __DIR__ . '/../public/assets/uploads/qr/';
$logo_dir = __DIR__ . '/../public/assets/uploads/';
if (!is_dir($qr_dir)) mkdir($qr_dir, 0755, true);

// Helper: load json config
function load_cfg($path) {
    return file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
}
function save_cfg($path, $data) {
    file_put_contents($path, json_encode($data));
}

$payment_cfg = load_cfg($qr_dir . 'payment_methods.json');
$shop_cfg   = load_cfg($logo_dir . 'shop_config.json');
$footer_cfg = load_cfg($logo_dir . 'footer_config.json');
$about_cfg  = load_cfg($logo_dir . 'about_config.json');

// Load branches for address selector
$branches = db_query("SELECT id, branch_name AS name FROM branches ORDER BY branch_name") ?: [];
// Per-branch addresses stored in footer_cfg['branch_addresses'] = [['branch_id'=>1,'address'=>'...']]

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {

    // Save Payment Methods
    if (isset($_POST['save_payment_methods'])) {
        $pm_cfg = [];
        $providers = $_POST['pm_provider'] ?? [];
        $labels    = $_POST['pm_label'] ?? [];
        $enabled   = $_POST['pm_enabled'] ?? [];
        $cropped_imgs = $_POST['pm_cropped_img'] ?? [];
        $existing     = $_POST['pm_existing_file'] ?? [];

        foreach ($providers as $index => $provider) {
            $provider = sanitize($provider);
            $label    = sanitize($labels[$index] ?? '');
            $is_en    = (int)($enabled[$index] ?? 1);
            $file     = sanitize($existing[$index] ?? '');

            if ($provider !== '' || $label !== '') {
                // handle cropped base64 image
                $b64 = $cropped_imgs[$index] ?? '';
                if (!empty($b64) && strpos($b64, 'data:image') === 0) {
                    $parts = explode(',', $b64);
                    if (count($parts) === 2) {
                        $data = base64_decode($parts[1]);
                        $ext = 'png';
                        if (strpos($parts[0], 'jpeg') !== false) $ext = 'jpg';
                        elseif (strpos($parts[0], 'webp') !== false) $ext = 'webp';
                        
                        $fname = 'pm_' . time() . '_' . $index . '_crop.' . $ext;
                        file_put_contents($qr_dir . $fname, $data);
                        $file = $fname;
                    }
                } 
                // fallback to regular file
                elseif (!empty($_FILES['pm_file']['name'][$index])) {
                    $ext = strtolower(pathinfo($_FILES['pm_file']['name'][$index], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                        $fname = 'pm_' . time() . '_' . $index . '.' . $ext;
                        move_uploaded_file($_FILES['pm_file']['tmp_name'][$index], $qr_dir . $fname);
                        $file = $fname;
                    }
                }
                $pm_cfg[] = [
                    'provider' => $provider,
                    'label' => $label,
                    'enabled' => $is_en,
                    'file' => $file
                ];
            }
        }
        save_cfg($qr_dir . 'payment_methods.json', $pm_cfg);
        $success = 'Payment methods updated!';
        // Reload
        $payment_cfg = load_cfg($qr_dir . 'payment_methods.json');
    }

    // Save general + logo
    if (isset($_POST['save_general'])) {
        $shop_cfg['name']  = sanitize($_POST['shop_name'] ?? 'PrintFlow');
        $shop_cfg['email'] = sanitize($_POST['contact_email'] ?? '');
        $shop_cfg['phone'] = sanitize($_POST['contact_phone'] ?? '');
        if (!empty($_FILES['shop_logo']['name'])) {
            $ext = strtolower(pathinfo($_FILES['shop_logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','svg'])) {
                $fname = 'shop_logo_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['shop_logo']['tmp_name'], $logo_dir . $fname);
                $shop_cfg['logo'] = $fname;
            }
        }
        save_cfg($logo_dir . 'shop_config.json', $shop_cfg);
        $success = 'General settings saved!';
    }

    // Save footer
    if (isset($_POST['save_footer'])) {
        $footer_cfg['tagline'] = sanitize($_POST['footer_tagline'] ?? '');
        $footer_cfg['hours']   = sanitize($_POST['footer_hours'] ?? '');
        $footer_cfg['email']   = sanitize($_POST['footer_email'] ?? '');
        $footer_cfg['phone']   = sanitize($_POST['footer_phone'] ?? '');

        // Per-branch addresses
        $ba_ids  = $_POST['ba_branch_id'] ?? [];
        $ba_addrs = $_POST['ba_address'] ?? [];
        $branch_addresses = [];
        foreach ($ba_ids as $bi => $bid) {
            $bid = (int)$bid;
            $addr = trim($ba_addrs[$bi] ?? '');
            if ($bid > 0 && $addr !== '') {
                $branch_addresses[] = ['branch_id' => $bid, 'address' => sanitize($addr)];
            }
        }
        $footer_cfg['branch_addresses'] = $branch_addresses;

        // Services: raw textarea, one per line
        $svcs_raw = $_POST['footer_services'] ?? '';
        $footer_cfg['services'] = array_values(array_filter(array_map('trim', explode("\n", $svcs_raw))));

        // Social links: URL only — name auto-detected by frontend
        $social_urls = $_POST['social_url'] ?? [];
        $socials = [];
        foreach ($social_urls as $u) {
            $u = trim($u);
            if ($u !== '') $socials[] = ['url' => sanitize($u)];
        }
        $footer_cfg['social_links'] = $socials;

        save_cfg($logo_dir . 'footer_config.json', $footer_cfg);
        $success = 'Footer info saved!';
    }

    // Save About Page Config
    if (isset($_POST['save_about'])) {
        $values = [];
        $v_titles = $_POST['about_value_title'] ?? [];
        $v_descs  = $_POST['about_value_desc'] ?? [];
        $v_icons  = $_POST['about_value_icon'] ?? [];
        foreach ($v_titles as $i => $vt) {
            $vt = sanitize($vt);
            if ($vt !== '') {
                $values[] = ['title' => $vt, 'desc' => sanitize($v_descs[$i] ?? ''), 'icon' => sanitize($v_icons[$i] ?? 'star')];
            }
        }
        $team = [];
        $t_names  = $_POST['about_team_name'] ?? [];
        $t_roles  = $_POST['about_team_role'] ?? [];
        $t_photos = $_POST['about_team_photo'] ?? [];
        foreach ($t_names as $i => $tn) {
            $tn = sanitize($tn);
            if ($tn !== '') {
                $photo = sanitize($t_photos[$i] ?? '');
                if (!empty($_FILES['about_team_photo_upload']['name'][$i])) {
                    $ext = strtolower(pathinfo($_FILES['about_team_photo_upload']['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                        $fname = 'team_' . time() . '_' . $i . '.' . $ext;
                        $tp_dir = $logo_dir . 'team/';
                        if (!is_dir($tp_dir)) mkdir($tp_dir, 0755, true);
                        move_uploaded_file($_FILES['about_team_photo_upload']['tmp_name'][$i], $tp_dir . $fname);
                        $photo = $fname;
                    }
                }
                $team[] = ['name' => $tn, 'role' => sanitize($t_roles[$i] ?? ''), 'photo' => $photo];
            }
        }
        $about_cfg = [
            'tagline'       => sanitize($_POST['about_tagline'] ?? ''),
            'hero_subtitle' => sanitize($_POST['about_hero_subtitle'] ?? ''),
            'mission'       => sanitize($_POST['about_mission'] ?? ''),
            'vision'        => sanitize($_POST['about_vision'] ?? ''),
            'founding_year' => sanitize($_POST['about_founding_year'] ?? ''),
            'team_size'     => sanitize($_POST['about_team_size'] ?? ''),
            'projects_done' => sanitize($_POST['about_projects_done'] ?? ''),
            'happy_clients' => sanitize($_POST['about_happy_clients'] ?? ''),
            'values'        => $values,
            'team_members'  => $team,
        ];
        save_cfg($logo_dir . 'about_config.json', $about_cfg);
        $success = 'About page content saved!';
        $about_cfg = load_cfg($logo_dir . 'about_config.json');
    }
}

$page_title = 'Settings - Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .settings-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        @media(max-width:960px) { .settings-grid { grid-template-columns:1fr; } }
        .settings-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:24px; position:relative; overflow:hidden; }
        .settings-card::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #00232b, #53C5E0); }
        .settings-card-title { font-size:15px; font-weight:700; color:#111827; margin-bottom:18px; padding-bottom:12px; border-bottom:1px solid #f3f4f6; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .settings-card-title svg { width:18px; height:18px; color:#6366f1; flex-shrink:0; }
        .f-group { margin-bottom:16px; }
        .f-group label { display:block; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px; }
        .f-group input, .f-group select, .f-group textarea { width:100%; padding:9px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; color:#111827; background:#fafafa; outline:none; transition:border-color .15s; box-sizing:border-box; }
        .f-group input:focus, .f-group select:focus, .f-group textarea:focus { border-color:#6366f1; background:#fff; }
        .f-group input[type="file"] { background:#fff; padding:7px 12px; }
        .f-group textarea { resize:vertical; min-height:70px; }
        .toggle-row { display:flex; align-items:center; justify-content:space-between; padding:10px 14px; background:#f8fafc; border-radius:10px; border:1px solid #e5e7eb; margin-bottom:12px; gap:10px; }
        .toggle-label { font-size:14px; font-weight:600; color:#111827; }
        .toggle-sub { font-size:12px; color:#9ca3af; }
        .toggle-switch { position:relative; display:inline-block; width:44px; height:24px; flex-shrink:0; }
        .toggle-switch input { opacity:0; width:0; height:0; }
        .toggle-slider { position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background:#e5e7eb; transition:.3s; border-radius:24px; }
        .toggle-slider:before { position:absolute; content:""; height:18px; width:18px; left:3px; bottom:3px; background:white; transition:.3s; border-radius:50%; }
        .toggle-switch input:checked + .toggle-slider { background:#6366f1; }
        .toggle-switch input:checked + .toggle-slider:before { transform:translateX(20px); }
        .qr-preview { width:100px; height:100px; object-fit:contain; border:2px dashed #e5e7eb; border-radius:10px; padding:6px; display:block; margin-bottom:10px; background:#f9fafb; }
        .qr-no-img { width:100px; height:100px; border:2px dashed #e5e7eb; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:10px; background:#f9fafb; color:#d1d5db; font-size:11px; text-align:center; }
        .qr-pair { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        @media(max-width:600px) { .qr-pair { grid-template-columns:1fr; } }
        .qr-slot { background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:14px; }
        .qr-slot-title { font-size:12px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.4px; margin-bottom:10px; }
        .badge-enabled { display:inline-block; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:700; background:#dcfce7; color:#166534; }
        .badge-disabled { display:inline-block; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:700; background:#fee2e2; color:#991b1b; }
        .section-save { display:flex; justify-content:flex-end; margin-top:16px; }
        .btn-save-sm { padding:9px 22px; border:none; border-radius:8px; background:#00232b; color:#fff; font-size:14px; font-weight:600; cursor:pointer; transition:background .15s; }
        .btn-save-sm:hover { background:#003a47; }
        .logo-preview { max-height:60px; max-width:180px; object-fit:contain; border:1px solid #e5e7eb; border-radius:8px; padding:6px; background:#fafafa; margin-bottom:10px; display:block; }
        .f-group textarea { resize:vertical; min-height:70px; font-family:inherit; font-size:14px; line-height:1.5; }
        
        /* Cropper Modal */
        .cropper-modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); display:none; align-items:center; justify-content:center; z-index:9999; }
        .cropper-modal-panel { background:#fff; border-radius:12px; padding:20px; width:90%; max-width:500px; box-shadow:0 10px 25px rgba(0,0,0,0.2); }
        .cropper-container-box { width:100%; height:300px; background:#f3f4f6; margin:15px 0; border:1px dashed #cbd5e1; display:flex; align-items:center; justify-content:center; overflow:hidden; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <header>
            <h1 class="page-title">Settings</h1>
        </header>

        <main>
            <?php if ($success): ?>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="settings-grid">

                <!-- General Settings -->
                <div class="settings-card">
                    <div class="settings-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
                        General Settings
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <!-- Logo -->
                        <div class="f-group">
                            <label>Shop Logo</label>
                            <?php if (!empty($shop_cfg['logo'])): ?>
                                <img src="/printflow/public/assets/uploads/<?php echo htmlspecialchars($shop_cfg['logo']); ?>?t=<?php echo time(); ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #e5e7eb;display:block;margin-bottom:10px;" alt="Shop Logo">
                            <?php endif; ?>
                            <input type="file" name="shop_logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                            <p style="font-size:11px;color:#9ca3af;margin-top:4px;">🔵 Recommended: <strong>500×500 px</strong> square image (PNG/WebP with transparent background). Displayed as a circle.</p>
                        </div>
                        <div class="f-group">
                            <label>Shop Name</label>
                            <input type="text" name="shop_name" value="<?php echo htmlspecialchars($shop_cfg['name'] ?? 'PrintFlow'); ?>">
                        </div>
                        <div class="f-group">
                            <label>Contact Email</label>
                            <input type="email" name="contact_email" value="<?php echo htmlspecialchars($shop_cfg['email'] ?? 'support@printflow.com'); ?>">
                        </div>
                        <div class="f-group">
                            <label>Contact Phone</label>
                            <input type="tel" name="contact_phone" value="<?php echo htmlspecialchars($shop_cfg['phone'] ?? ''); ?>">
                        </div>
                        <div class="f-group">
                            <label>Currency</label>
                            <input type="text" value="Philippine Peso (₱)" disabled style="background:#f3f4f6;color:#9ca3af;">
                            <p style="font-size:11px;color:#9ca3af;margin-top:4px;">Currency is fixed to PHP.</p>
                        </div>
                        <div class="section-save">
                            <button type="submit" name="save_general" class="btn-save-sm">Save Settings</button>
                        </div>
                    </form>
                </div>

                <!-- Payment Methods (Dynamic) -->
                <div class="settings-card">
                    <div class="settings-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        Payment Methods (Dynamic)
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <div id="pm-list" style="display:flex;flex-direction:column;gap:16px;">
                            <?php
                            if (empty($payment_cfg)) $payment_cfg = [['provider'=>'GCash','label'=>'','enabled'=>1,'file'=>'']];
                            foreach ($payment_cfg as $pm):
                            ?>
                            <div class="pm-row" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px;position:relative;">
                                <button type="button" onclick="this.closest('.pm-row').remove()" style="position:absolute;top:10px;right:10px;padding:5px 9px;border:1px solid #fee2e2;background:#fef2f2;color:#b91c1c;border-radius:6px;cursor:pointer;font-size:12px;z-index:10;">✕</button>
                                
                                <div class="toggle-row" style="margin-bottom:10px;">
                                    <div>
                                        <div class="toggle-label">Show Payment Option</div>
                                        <div class="toggle-sub"><?php echo ($pm['enabled'] ?? 1) ? 'Enabled' : 'Disabled'; ?></div>
                                    </div>
                                    <select name="pm_enabled[]" style="padding:4px 8px;border:1px solid #e5e7eb;border-radius:6px;font-size:13px;background:#fff;">
                                        <option value="1" <?php echo ($pm['enabled'] ?? 1) ? 'selected' : ''; ?>>Enabled</option>
                                        <option value="0" <?php echo !($pm['enabled'] ?? 1) ? 'selected' : ''; ?>>Disabled</option>
                                    </select>
                                </div>

                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px;">
                                    <div class="f-group" style="margin-bottom:0;">
                                        <label>Provider Name</label>
                                        <input type="text" name="pm_provider[]" value="<?php echo htmlspecialchars($pm['provider'] ?? ''); ?>" placeholder="e.g. GCash" required>
                                    </div>
                                    <div class="f-group" style="margin-bottom:0;">
                                        <label>Account Name / Label</label>
                                        <input type="text" name="pm_label[]" value="<?php echo htmlspecialchars($pm['label'] ?? ''); ?>" placeholder="e.g. Main Account">
                                    </div>
                                </div>
                                <div class="f-group" style="margin-bottom:0;">
                                    <label>Upload QR Image <span style="font-weight:400;color:#9ca3af;">(Auto-crops to square)</span></label>
                                    <input type="file" name="pm_file[]" accept="image/*" class="pm-file-input">
                                    <input type="hidden" name="pm_existing_file[]" value="<?php echo htmlspecialchars($pm['file'] ?? ''); ?>">
                                    <input type="hidden" name="pm_cropped_img[]" value="">
                                    <div style="margin-top:8px;">
                                        <img src="<?php echo !empty($pm['file']) ? '/printflow/public/assets/uploads/qr/' . htmlspecialchars($pm['file']) . '?t=' . time() : ''; ?>" 
                                             class="pm-preview-img" 
                                             style="height:80px; width:80px; object-fit:cover; border-radius:8px; border:2px solid #e5e7eb; background:#fff; display:<?php echo !empty($pm['file']) ? 'block' : 'none'; ?>;" alt="QR">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="add-pm" style="margin-top:12px;padding:7px 14px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:7px;font-size:13px;cursor:pointer;color:#374151;width:100%;">+ Add Payment Method</button>

                        <div class="section-save" style="margin-top:16px;">
                            <button type="submit" name="save_payment_methods" class="btn-save-sm">Save Payment Methods</button>
                        </div>
                    </form>
                </div>

                <!-- Footer Info -->
                <div class="settings-card" style="grid-column:1/-1;">
                    <div class="settings-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                        Footer Information
                    </div>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">

                            <!-- Col 1: Info -->
                            <div>
                                <div class="f-group">
                                    <label>Tagline / Short Description</label>
                                    <input type="text" name="footer_tagline" value="<?php echo htmlspecialchars($footer_cfg['tagline'] ?? ''); ?>" placeholder="e.g. Your trusted printing partner">
                                </div>
                                <div class="f-group">
                                    <label>Business Hours</label>
                                    <input type="text" name="footer_hours" value="<?php echo htmlspecialchars($footer_cfg['hours'] ?? ''); ?>" placeholder="e.g. Mon–Sat, 8AM–6PM">
                                </div>
                                <div class="f-group">
                                    <label>Contact Email</label>
                                    <input type="email" name="footer_email" value="<?php echo htmlspecialchars($footer_cfg['email'] ?? ''); ?>" placeholder="e.g. support@yourshop.com">
                                </div>
                                <div class="f-group">
                                    <label>Contact Phone</label>
                                    <input type="tel" name="footer_phone" value="<?php echo htmlspecialchars($footer_cfg['phone'] ?? ''); ?>" placeholder="e.g. +63 912 345 6789">
                                </div>
                            </div>

                            <!-- Col 2: Branch Addresses -->
                            <div>
                                <div class="f-group">
                                    <label>Branch Addresses <span style="font-weight:400;color:#9ca3af;">(per branch)</span></label>
                                    <div id="branch-addr-list" style="display:flex;flex-direction:column;gap:10px;margin-bottom:8px;">
                                        <?php
                                        $saved_bas = $footer_cfg['branch_addresses'] ?? [];
                                        if (empty($saved_bas)) $saved_bas = [['branch_id'=>'','address'=>'']];
                                        foreach ($saved_bas as $ba):
                                            $ba_bid  = $ba['branch_id'] ?? '';
                                            $ba_addr = $ba['address'] ?? '';
                                        ?>
                                        <div class="branch-addr-row" style="display:flex;flex-direction:column;gap:6px;background:#f9fafb;padding:10px;border-radius:8px;border:1px solid #e5e7eb;">
                                            <div style="display:flex;gap:8px;align-items:center;">
                                                <select name="ba_branch_id[]" style="flex:1;padding:8px 10px;border:1px solid #e5e7eb;border-radius:7px;font-size:13px;background:#fff;color:#111827;">
                                                    <option value="">-- Select Branch --</option>
                                                    <?php foreach ($branches as $br): ?>
                                                    <option value="<?php echo (int)$br['id']; ?>" <?php echo $ba_bid == $br['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($br['name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="button" onclick="this.closest('.branch-addr-row').remove()" style="padding:5px 9px;border:1px solid #fee2e2;background:#fef2f2;color:#b91c1c;border-radius:6px;cursor:pointer;font-size:13px;flex-shrink:0;">✕</button>
                                            </div>
                                            <textarea name="ba_address[]" rows="2" placeholder="Full address for this branch" style="width:100%;padding:8px 10px;border:1px solid #e5e7eb;border-radius:7px;font-size:13px;font-family:inherit;resize:vertical;background:#fff;color:#111827;"><?php echo htmlspecialchars($ba_addr); ?></textarea>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" id="add-branch-addr" style="padding:7px 14px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:7px;font-size:13px;cursor:pointer;color:#374151;">+ Add Branch Address</button>
                                </div>
                            </div>

                            <!-- Col 3: Services + Socials -->
                            <div>
                                <div class="f-group">
                                    <label>Our Services <span style="font-weight:400;color:#9ca3af;">(one per line)</span></label>
                                    <textarea name="footer_services" rows="5" placeholder="Tarpaulin Printing
T-shirt Printing
Stickers &amp; Decals"><?php
                                        echo htmlspecialchars(implode("\n", $footer_cfg['services'] ?? []));
                                    ?></textarea>
                                    <p style="font-size:11px;color:#9ca3af;margin-top:4px;">Each line becomes a ✓ item in the footer.</p>
                                </div>
                                <div class="f-group">
                                    <label>Social Media Links <span style="font-weight:400;color:#9ca3af;">(URL only — icon auto-detected)</span></label>
                                    <div id="social-list" style="display:flex;flex-direction:column;gap:8px;margin-bottom:8px;">
                                        <?php
                                        $existing_socials = $footer_cfg['social_links'] ?? [];
                                        if (empty($existing_socials)) $existing_socials = [['url'=>'']];
                                        foreach ($existing_socials as $sl):
                                        ?>
                                        <div class="social-row" style="display:flex;gap:8px;align-items:center;">
                                            <input type="url" name="social_url[]" value="<?php echo htmlspecialchars($sl['url'] ?? ''); ?>" placeholder="https://facebook.com/yourpage" style="flex:1;">
                                            <button type="button" onclick="this.closest('.social-row').remove()" style="padding:6px 10px;border:1px solid #fee2e2;background:#fef2f2;color:#b91c1c;border-radius:6px;cursor:pointer;font-size:13px;">✕</button>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" id="add-social" style="padding:7px 14px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:7px;font-size:13px;cursor:pointer;color:#374151;">+ Add Social Link</button>
                                </div>
                            </div>

                        </div>
                        <div class="section-save">
                            <button type="submit" name="save_footer" class="btn-save-sm">Save Footer Info</button>
                        </div>
                    </form>
                </div>

                <!-- About Page Content -->
                <div class="settings-card" style="grid-column:1/-1;">
                    <div class="settings-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/></svg>
                        About Page Content
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>

                        <!-- Hero -->
                        <p style="font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;margin-bottom:12px;">Hero Section</p>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                            <div class="f-group" style="margin-bottom:0;">
                                <label>Tagline (Hero Heading)</label>
                                <input type="text" name="about_tagline" value="<?php echo htmlspecialchars($about_cfg['tagline'] ?? ''); ?>" placeholder="Your Trusted Printing Partner Since Day One">
                            </div>
                            <div class="f-group" style="margin-bottom:0;">
                                <label>Hero Subtitle</label>
                                <input type="text" name="about_hero_subtitle" value="<?php echo htmlspecialchars($about_cfg['hero_subtitle'] ?? ''); ?>" placeholder="Short description under the tagline">
                            </div>
                        </div>

                        <!-- Stats -->
                        <p style="font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;margin-bottom:12px;">Stats Bar</p>
                        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px;">
                            <div class="f-group" style="margin-bottom:0;">
                                <label>Founding Year</label>
                                <input type="text" name="about_founding_year" value="<?php echo htmlspecialchars($about_cfg['founding_year'] ?? ''); ?>" placeholder="e.g. 2018">
                            </div>
                            <div class="f-group" style="margin-bottom:0;">
                                <label>Team Size</label>
                                <input type="text" name="about_team_size" value="<?php echo htmlspecialchars($about_cfg['team_size'] ?? ''); ?>" placeholder="e.g. 25+">
                            </div>
                            <div class="f-group" style="margin-bottom:0;">
                                <label>Projects Done</label>
                                <input type="text" name="about_projects_done" value="<?php echo htmlspecialchars($about_cfg['projects_done'] ?? ''); ?>" placeholder="e.g. 10,000+">
                            </div>
                            <div class="f-group" style="margin-bottom:0;">
                                <label>Happy Clients</label>
                                <input type="text" name="about_happy_clients" value="<?php echo htmlspecialchars($about_cfg['happy_clients'] ?? ''); ?>" placeholder="e.g. 5,000+">
                            </div>
                        </div>

                        <!-- Mission & Vision -->
                        <p style="font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;margin-bottom:12px;">Mission & Vision</p>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                            <div class="f-group" style="margin-bottom:0;">
                                <label>Mission Statement</label>
                                <textarea name="about_mission" rows="4" placeholder="Our mission is to..."><?php echo htmlspecialchars($about_cfg['mission'] ?? ''); ?></textarea>
                            </div>
                            <div class="f-group" style="margin-bottom:0;">
                                <label>Vision Statement</label>
                                <textarea name="about_vision" rows="4" placeholder="Our vision is to..."><?php echo htmlspecialchars($about_cfg['vision'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- Values -->
                        <p style="font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;margin-bottom:12px;">Core Values <span style="font-weight:400;color:#9ca3af;">(up to 6)</span></p>
                        <div id="about-values-list" style="display:flex;flex-direction:column;gap:12px;margin-bottom:10px;">
                            <?php
                            $ab_values = $about_cfg['values'] ?? [['title'=>'','desc'=>'','icon'=>'star']];
                            foreach ($ab_values as $av):
                            ?>
                            <div class="about-value-row" style="display:grid;grid-template-columns:1fr 2fr auto;gap:10px;align-items:start;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px;">
                                <div class="f-group" style="margin-bottom:0;">
                                    <label>Title</label>
                                    <input type="text" name="about_value_title[]" value="<?php echo htmlspecialchars($av['title']??''); ?>" placeholder="e.g. Quality First">
                                    <input type="hidden" name="about_value_icon[]" value="<?php echo htmlspecialchars($av['icon']??'star'); ?>">
                                </div>
                                <div class="f-group" style="margin-bottom:0;">
                                    <label>Description</label>
                                    <input type="text" name="about_value_desc[]" value="<?php echo htmlspecialchars($av['desc']??''); ?>" placeholder="Short description">
                                </div>
                                <button type="button" onclick="this.closest('.about-value-row').remove()" style="margin-top:20px;padding:7px 10px;border:1px solid #fee2e2;background:#fef2f2;color:#b91c1c;border-radius:6px;cursor:pointer;font-size:13px;flex-shrink:0;">✕</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="add-about-value" style="padding:7px 14px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:7px;font-size:13px;cursor:pointer;color:#374151;margin-bottom:20px;">+ Add Value</button>

                        <!-- Team Members -->
                        <p style="font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;margin-bottom:12px;">Team Members <span style="font-weight:400;color:#9ca3af;">(optional)</span></p>
                        <div id="about-team-list" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;margin-bottom:10px;">
                            <?php
                            $ab_team = $about_cfg['team_members'] ?? [];
                            foreach ($ab_team as $i => $tm):
                            ?>
                            <div class="about-team-row" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:14px;position:relative;">
                                <button type="button" onclick="this.closest('.about-team-row').remove()" style="position:absolute;top:8px;right:8px;padding:4px 8px;border:1px solid #fee2e2;background:#fef2f2;color:#b91c1c;border-radius:5px;cursor:pointer;font-size:11px;">✕</button>
                                <div class="f-group">
                                    <label>Full Name</label>
                                    <input type="text" name="about_team_name[]" value="<?php echo htmlspecialchars($tm['name']??''); ?>" placeholder="e.g. Maria Santos">
                                </div>
                                <div class="f-group">
                                    <label>Role / Position</label>
                                    <input type="text" name="about_team_role[]" value="<?php echo htmlspecialchars($tm['role']??''); ?>" placeholder="e.g. Founder & CEO">
                                </div>
                                <div class="f-group" style="margin-bottom:0;">
                                    <label>Photo <span style="font-weight:400;color:#9ca3af;">(optional)</span></label>
                                    <input type="file" name="about_team_photo_upload[<?php echo $i; ?>]" accept="image/*">
                                    <input type="hidden" name="about_team_photo[]" value="<?php echo htmlspecialchars($tm['photo']??''); ?>">
                                    <?php if (!empty($tm['photo'])): ?>
                                        <img src="/printflow/public/assets/uploads/team/<?php echo htmlspecialchars($tm['photo']); ?>" style="width:50px;height:50px;border-radius:50%;object-fit:cover;margin-top:6px;border:2px solid #e5e7eb;">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="add-about-team" style="padding:7px 14px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:7px;font-size:13px;cursor:pointer;color:#374151;margin-bottom:4px;">+ Add Team Member</button>

                        <div class="section-save" style="margin-top:20px;">
                            <button type="submit" name="save_about" class="btn-save-sm">Save About Page</button>
                        </div>
                    </form>
                </div>

            </div>
        </main>
    </div>
</div>

<!-- Cropper Modal -->
<div id="cropperModal" class="cropper-modal-overlay">
    <div class="cropper-modal-panel">
        <h3 style="margin-top:0; font-size:18px; color:#111827;">Crop QR Code</h3>
        <p style="font-size:13px; color:#6b7280; margin-bottom:0;">Please crop the image to a perfect square.</p>
        <div class="cropper-container-box">
            <img id="imageToCrop" style="max-width:100%; display:block;">
        </div>
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:15px;">
            <button type="button" onclick="closeCropper()" style="padding:8px 16px; border:1px solid #e5e7eb; border-radius:6px; background:#fff; cursor:pointer; color:#374151;">Cancel</button>
            <button type="button" id="btnCrop" style="padding:8px 16px; border:none; border-radius:6px; background:#00232b; color:#fff; cursor:pointer;">Crop & Apply</button>
        </div>
    </div>
</div>

<script>
// Add social link row
document.getElementById('add-social')?.addEventListener('click', function() {
    var list = document.getElementById('social-list');
    var row = document.createElement('div');
    row.className = 'social-row';
    row.style.cssText = 'display:flex;gap:8px;align-items:center;';
    row.innerHTML = '<input type="url" name="social_url[]" placeholder="https://facebook.com/yourpage" style="flex:1;">' +
        '<button type="button" onclick="this.closest(\'.social-row\').remove()" style="padding:6px 10px;border:1px solid #fee2e2;background:#fef2f2;color:#b91c1c;border-radius:6px;cursor:pointer;font-size:13px;">✕</button>';
    list.appendChild(row);
    row.querySelector('input').focus();
});

// Add branch address row
var branchOptions = <?php
    $opts = [];
    foreach ($branches as $br) {
        $opts[] = '<option value="' . (int)$br['id'] . '">' . htmlspecialchars($br['name']) . '</option>';
    }
    echo json_encode(implode('', $opts));
?>;
document.getElementById('add-branch-addr')?.addEventListener('click', function() {
    var list = document.getElementById('branch-addr-list');
    var row = document.createElement('div');
    row.className = 'branch-addr-row';
    row.style.cssText = 'display:flex;flex-direction:column;gap:6px;background:#f9fafb;padding:10px;border-radius:8px;border:1px solid #e5e7eb;';
    row.innerHTML =
        '<div style="display:flex;gap:8px;align-items:center;">' +
            '<select name="ba_branch_id[]" style="flex:1;padding:8px 10px;border:1px solid #e5e7eb;border-radius:7px;font-size:13px;background:#fff;color:#111827;">' +
                '<option value="">-- Select Branch --</option>' + branchOptions +
            '</select>' +
            '<button type="button" onclick="this.closest(\'.branch-addr-row\').remove()" style="padding:5px 9px;border:1px solid #fee2e2;background:#fef2f2;color:#b91c1c;border-radius:6px;cursor:pointer;font-size:13px;flex-shrink:0;">✕</button>' +
        '</div>' +
        '<textarea name="ba_address[]" rows="2" placeholder="Full address for this branch" style="width:100%;padding:8px 10px;border:1px solid #e5e7eb;border-radius:7px;font-size:13px;font-family:inherit;resize:vertical;background:#fff;color:#111827;box-sizing:border-box;"></textarea>';
    list.appendChild(row);
    row.querySelector('select').focus();
});

document.getElementById('add-pm')?.addEventListener('click', function() {
    var list = document.getElementById('pm-list');
    var row = document.createElement('div');
    row.className = 'pm-row';
    row.style.cssText = 'background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px;position:relative;';
    row.innerHTML = `<button type="button" onclick="this.closest('.pm-row').remove()" style="position:absolute;top:10px;right:10px;padding:5px 9px;border:1px solid #fee2e2;background:#fef2f2;color:#b91c1c;border-radius:6px;cursor:pointer;font-size:12px;z-index:10;">✕</button>
        <div class="toggle-row" style="margin-bottom:10px;">
            <div>
                <div class="toggle-label">Show Payment Option</div>
                <div class="toggle-sub">Enabled</div>
            </div>
            <select name="pm_enabled[]" style="padding:4px 8px;border:1px solid #e5e7eb;border-radius:6px;font-size:13px;background:#fff;">
                <option value="1" selected>Enabled</option>
                <option value="0">Disabled</option>
            </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px;">
            <div class="f-group" style="margin-bottom:0;">
                <label>Provider Name</label>
                <input type="text" name="pm_provider[]" placeholder="e.g. GCash" required>
            </div>
            <div class="f-group" style="margin-bottom:0;">
                <label>Account Name / Label</label>
                <input type="text" name="pm_label[]" placeholder="e.g. Main Account">
            </div>
        </div>
        <div class="f-group" style="margin-bottom:0;">
            <label>Upload QR Image <span style="font-weight:400;color:#9ca3af;">(Auto-crops to square)</span></label>
            <input type="file" name="pm_file[]" accept="image/*" class="pm-file-input">
            <input type="hidden" name="pm_existing_file[]" value="">
            <input type="hidden" name="pm_cropped_img[]" value="">
            <div style="margin-top:8px;">
                <img src="" class="pm-preview-img" style="height:80px; width:80px; object-fit:cover; border-radius:8px; border:2px solid #e5e7eb; background:#fff; display:none;" alt="QR">
            </div>
        </div>`;
    list.appendChild(row);
});

// Cropper Logic
let currentCropper = null;
let currentFileInput = null;
let currentPreviewImg = null;
let currentHiddenInput = null;

document.addEventListener('change', function(e) {
    if (e.target.matches('.pm-file-input')) {
        const file = e.target.files[0];
        if (file) {
            const row = e.target.closest('.pm-row');
            currentFileInput = e.target;
            currentHiddenInput = row.querySelector('input[name="pm_cropped_img[]"]');
            currentPreviewImg = row.querySelector('.pm-preview-img');
            
            const reader = new FileReader();
            reader.onload = function(event) {
                document.getElementById('imageToCrop').src = event.target.result;
                document.getElementById('cropperModal').style.display = 'flex';
                
                if (currentCropper) {
                    currentCropper.destroy();
                }
                
                currentCropper = new Cropper(document.getElementById('imageToCrop'), {
                    aspectRatio: 1, // perfect square!
                    viewMode: 1,
                    autoCropArea: 0.8
                });
            };
            reader.readAsDataURL(file);
        }
    }
});

function closeCropper() {
    document.getElementById('cropperModal').style.display = 'none';
    if (currentCropper) {
        currentCropper.destroy();
        currentCropper = null;
    }
    if (currentFileInput && !currentHiddenInput.value) {
        currentFileInput.value = ''; // Reset input if they cancelled
    }
}

document.getElementById('btnCrop')?.addEventListener('click', function() {
    if (currentCropper) {
        const canvas = currentCropper.getCroppedCanvas({ width: 500, height: 500 });
        const dataUrl = canvas.toDataURL('image/png');
        currentHiddenInput.value = dataUrl;
        
        if (currentPreviewImg) {
            currentPreviewImg.src = dataUrl;
            currentPreviewImg.style.display = 'block';
        }
        closeCropper();
    }
});

// About Page — Add Value Row
document.getElementById('add-about-value')?.addEventListener('click', function() {
    var list = document.getElementById('about-values-list');
    var row = document.createElement('div');
    row.className = 'about-value-row';
    row.style.cssText = 'display:grid;grid-template-columns:1fr 2fr auto;gap:10px;align-items:start;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px;';
    row.innerHTML = '<div class="f-group" style="margin-bottom:0;"><label>Title</label><input type="text" name="about_value_title[]" placeholder="e.g. Quality First"><input type="hidden" name="about_value_icon[]" value="star"></div>' +
        '<div class="f-group" style="margin-bottom:0;"><label>Description</label><input type="text" name="about_value_desc[]" placeholder="Short description"></div>' +
        '<button type="button" onclick="this.closest(\'.about-value-row\').remove()" style="margin-top:20px;padding:7px 10px;border:1px solid #fee2e2;background:#fef2f2;color:#b91c1c;border-radius:6px;cursor:pointer;font-size:13px;flex-shrink:0;">✕</button>';
    list.appendChild(row);
    row.querySelector('input[type="text"]').focus();
});

// About Page — Add Team Member Row
document.getElementById('add-about-team')?.addEventListener('click', function() {
    var list = document.getElementById('about-team-list');
    var idx = list.querySelectorAll('.about-team-row').length;
    var row = document.createElement('div');
    row.className = 'about-team-row';
    row.style.cssText = 'background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:14px;position:relative;';
    row.innerHTML = '<button type="button" onclick="this.closest(\'.about-team-row\').remove()" style="position:absolute;top:8px;right:8px;padding:4px 8px;border:1px solid #fee2e2;background:#fef2f2;color:#b91c1c;border-radius:5px;cursor:pointer;font-size:11px;">✕</button>' +
        '<div class="f-group"><label>Full Name</label><input type="text" name="about_team_name[]" placeholder="e.g. Maria Santos"></div>' +
        '<div class="f-group"><label>Role / Position</label><input type="text" name="about_team_role[]" placeholder="e.g. Founder & CEO"></div>' +
        '<div class="f-group" style="margin-bottom:0;"><label>Photo <span style="font-weight:400;color:#9ca3af;">(optional)</span></label><input type="file" name="about_team_photo_upload[' + idx + ']" accept="image/*"><input type="hidden" name="about_team_photo[]" value=""></div>';
    list.appendChild(row);
    row.querySelector('input[type="text"]').focus();
});
</script>

</body>
</html>
