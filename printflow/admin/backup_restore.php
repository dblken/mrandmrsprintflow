<?php
/**
 * Admin Backup & Restore Page
 * PrintFlow - Printing Shop PWA
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

$success = '';
$error = '';

// Note: Actual backup/restore implementation would require
// server-side scripts and proper permissions. This is a UI placeholder.

$page_title = 'Backup & Restore - Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1 class="page-title">Backup & Restore</h1>
            <button class="btn-primary">
                + Create New Backup
            </button>
        </header>

        <main>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Database Backup -->
                <div class="card">
                    <h3 class="text-lg font-bold mb-4">Database Backup</h3>
                    <p class="text-gray-600 mb-6">Create a backup of your PrintFlow database. Backups include all tables and data.</p>
                    
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <button type="button" onclick="alert('Backup functionality requires server-side implementation')" class="btn-primary w-full">
                            Create Database Backup
                        </button>
                    </form>
                    
                    <div class="mt-6">
                        <h4 class="font-semibold mb-2">What's Included:</h4>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li>✓ All database tables</li>
                            <li>✓ User accounts (Admin, Staff, Customers)</li>
                            <li>✓ Orders and transactions</li>
                            <li>✓ Products and inventory</li>
                            <li>✓ Activity logs</li>
                        </ul>
                    </div>
                </div>

                <!-- File Backup -->
                <div class="card">
                    <h3 class="text-lg font-bold mb-4">File Backup</h3>
                    <p class="text-gray-600 mb-6">Backup uploaded files including customer designs and product images.</p>
                    
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <button type="button" onclick="alert('File backup functionality requires server-side implementation')" class="btn-primary w-full">
                            Create File Backup
                        </button>
                    </form>
                    
                    <div class="mt-6">
                        <h4 class="font-semibold mb-2">What's Included:</h4>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li>✓ Customer design uploads</li>
                            <li>✓ Product images</li>
                            <li>✓ Payment confirmations</li>
                            <li>✓ System files</li>
                        </ul>
                    </div>
                </div>

                <!-- Restore -->
                <div class="card">
                    <h3 class="text-lg font-bold mb-4 text-red-600">⚠️ Restore Database</h3>
                    <p class="text-gray-600 mb-6">Restore your database from a previous backup. <strong>Warning:</strong> This will overwrite all current data!</p>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">Select Backup File (.sql)</label>
                            <input type="file" name="backup_file" class="input-field" accept=".sql">
                        </div>
                        <button type="button" onclick="if(confirm('Are you sure? This will replace ALL current data!')) alert('Restore functionality requires server-side implementation')" class="btn-primary w-full bg-red-600 hover:bg-red-700">
                            Restore Database
                        </button>
                    </form>
                </div>

                <!-- Recent Backups -->
                <div class="card">
                    <h3 class="text-lg font-bold mb-4">Recent Backups</h3>
                    <p class="text-gray-600 mb-4">List of recent backups (requires implementation)</p>
                    
                    <div class="space-y-2 text-sm">
                        <div class="p-3 bg-gray-50 rounded">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="font-medium">backup_2026-02-15.sql</p>
                                    <p class="text-xs text-gray-500">Database • 2.5 MB</p>
                                </div>
                                <button class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">Download</button>
                            </div>
                        </div>
                        
                        <div class="p-3 bg-gray-50 rounded">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="font-medium">files_2026-02-15.zip</p>
                                    <p class="text-xs text-gray-500">Files • 45 MB</p>
                                </div>
                                <button class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">Download</button>
                            </div>
                        </div>
                        
                        <p class="text-xs text-gray-500 mt-4">Note: Actual backup files require server-side implementation</p>
                    </div>
                </div>
            </div>

            <!-- Important Notes -->
            <div class="card mt-6 bg-yellow-50 border border-yellow-200">
                <h3 class="font-bold mb-2">⚠️ Important Notes:</h3>
                <ul class="text-sm text-gray-700 space-y-1">
                    <li>• Regular backups are recommended (daily or weekly)</li>
                    <li>• Store backups in a secure, off-server location</li>
                    <li>• Test restore procedures periodically</li>
                    <li>• Backup before major updates or system changes</li>
                    <li>• Keep multiple backup versions</li>
                </ul>
            </div>
        </main>
    </div>
</div>

</body>
</html>
