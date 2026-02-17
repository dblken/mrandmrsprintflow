<?php
/**
 * Admin Settings Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$success = '';
$error = '';

// Handle settings update (placeholder for now)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $success = 'Settings updated successfully! (Note: Actual implementation needed)';
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
</head>
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Settings</h1>
            <button form="settings-form" type="submit" class="btn-primary">
                Save Changes
            </button>
        </header>

        <main>
            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- General Settings -->
                <div class="card">
                    <h3 class="text-lg font-bold mb-4">General Settings</h3>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">Shop Name</label>
                            <input type="text" name="shop_name" class="input-field" value="PrintFlow" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">Contact Email</label>
                            <input type="email" name="contact_email" class="input-field" value="support@printflow.com" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">Contact Phone</label>
                            <input type="tel" name="contact_phone" class="input-field" value="+63 123 456 7890">
                        </div>
                        
                        <button type="submit" class="btn-primary w-full">Save Settings</button>
                    </form>
                </div>

                <!-- Payment Settings -->
                <div class="card">
                    <h3 class="text-lg font-bold mb-4">Payment Methods</h3>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="enable_gcash" class="mr-2" checked>
                                <span class="text-sm font-medium">Enable GCash</span>
                            </label>
                            <input type="text" name="gcash_number" class="input-field mt-2" placeholder="GCash Number" value="09123456789">
                        </div>
                        
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="enable_maya" class="mr-2" checked>
                                <span class="text-sm font-medium">Enable Maya (Paymaya)</span>
                            </label>
                            <input type="text" name="maya_number" class="input-field mt-2" placeholder="Maya Number" value="09123456789">
                        </div>
                        
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="enable_bank" class="mr-2">
                                <span class="text-sm font-medium">Enable Bank Transfer</span>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn-primary w-full">Update Payment Methods</button>
                    </form>
                </div>

                <!-- Email Settings -->
                <div class="card">
                    <h3 class="text-lg font-bold mb-4">Email Configuration</h3>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">SMTP Host</label>
                            <input type="text" name="smtp_host" class="input-field" placeholder="smtp.gmail.com">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium mb-2">SMTP Port</label>
                                <input type="number" name="smtp_port" class="input-field" placeholder="587">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2">Encryption</label>
                                <select name="smtp_encryption" class="input-field">
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">SMTP Username</label>
                            <input type="text" name="smtp_username" class="input-field">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">SMTP Password</label>
                            <input type="password" name="smtp_password" class="input-field">
                        </div>
                        
                        <button type="submit" class="btn-primary w-full">Save Email Settings</button>
                    </form>
                </div>

                <!-- System Settings -->
                <div class="card">
                    <h3 class="text-lg font-bold mb-4">System Preferences</h3>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">Timezone</label>
                            <select name="timezone" class="input-field">
                                <option value="Asia/Manila" selected>Asia/Manila (PHP)</option>
                                <option value="UTC">UTC</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">Currency</label>
                            <select name="currency" class="input-field">
                                <option value="PHP" selected>Philippine Peso (₱)</option>
                                <option value="USD">US Dollar ($)</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="enable_notifications" class="mr-2" checked>
                                <span class="text-sm font-medium">Enable Push Notifications</span>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn-primary w-full">Save Preferences</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

</body>
</html>
