<?php
/**
 * Database-backed runtime configuration helpers.
 *
 * These settings used to live in tracked JSON files under public/assets/uploads,
 * which made them vulnerable to being overwritten by deploy/sync operations.
 * We now treat the database as the source of truth and only mirror JSON files
 * as a transitional backup for any older code paths.
 */

require_once __DIR__ . '/db.php';

function printflow_runtime_settings_table_ready(): bool
{
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    $sql = "CREATE TABLE IF NOT EXISTS `settings` (
        `setting_id` int NOT NULL AUTO_INCREMENT,
        `key_name` varchar(50) NOT NULL,
        `value` text NOT NULL,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`setting_id`),
        UNIQUE KEY `key_name` (`key_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $ready = db_execute($sql) !== false;
    return $ready;
}

function printflow_runtime_config_map(): array
{
    return [
        'payment_methods' => 'cfg_payment_methods',
        'shop' => 'cfg_shop',
        'footer' => 'cfg_footer',
        'about' => 'cfg_about',
    ];
}

function printflow_runtime_config_key(string $name): string
{
    $map = printflow_runtime_config_map();
    return $map[$name] ?? ('cfg_' . preg_replace('/[^a-z0-9_]/i', '_', strtolower($name)));
}

function printflow_runtime_config_json_flags(): int
{
    $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    return $flags;
}

function printflow_runtime_legacy_load(string $path): array
{
    if ($path === '' || !is_file($path)) {
        return [];
    }

    $json = @file_get_contents($path);
    if ($json === false || trim($json) === '') {
        return [];
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function printflow_runtime_legacy_save(string $path, array $data): bool
{
    if ($path === '') {
        return false;
    }

    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }

    $json = json_encode($data, printflow_runtime_config_json_flags());
    if ($json === false) {
        error_log('Failed to encode legacy runtime config JSON for ' . $path . ': ' . json_last_error_msg());
        return false;
    }

    $tmp = $path . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }

    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }

    return true;
}

function printflow_load_runtime_config(string $name, string $legacyPath = ''): array
{
    $key = printflow_runtime_config_key($name);

    if (printflow_runtime_settings_table_ready()) {
        $rows = db_query("SELECT value FROM settings WHERE key_name = ? LIMIT 1", 's', [$key]);
        if (!empty($rows)) {
            $decoded = json_decode((string)($rows[0]['value'] ?? ''), true);
            if (is_array($decoded)) {
                return $decoded;
            }

            error_log('Invalid JSON stored in settings for key ' . $key . '; falling back to legacy config path.');
        }
    }

    $legacy = printflow_runtime_legacy_load($legacyPath);
    if ($legacy !== [] && printflow_runtime_settings_table_ready()) {
        printflow_save_runtime_config($name, $legacy, $legacyPath);
    }

    return $legacy;
}

function printflow_save_runtime_config(string $name, array $data, string $legacyPath = ''): bool
{
    if (!printflow_runtime_settings_table_ready()) {
        return false;
    }

    $key = printflow_runtime_config_key($name);
    $json = json_encode($data, printflow_runtime_config_json_flags());
    if ($json === false) {
        error_log('Failed to encode runtime config for key ' . $key . ': ' . json_last_error_msg());
        return false;
    }

    $saved = db_execute(
        "INSERT INTO settings (key_name, value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)",
        'ss',
        [$key, $json]
    ) !== false;

    if (!$saved) {
        return false;
    }

    if ($legacyPath !== '' && !printflow_runtime_legacy_save($legacyPath, $data)) {
        error_log('Runtime config saved to DB, but legacy JSON mirror failed for ' . $legacyPath);
    }

    return true;
}
