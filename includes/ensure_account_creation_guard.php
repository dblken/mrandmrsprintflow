<?php
/**
 * Account creation guard.
 *
 * Adds an internal-only created_by_system flag to account tables and enforces
 * inserts through database triggers plus a server-side session variable.
 */

if (!function_exists('printflow_account_guard_column')) {
    function printflow_account_guard_column(): string {
        return 'created_by_system';
    }
}

if (!function_exists('printflow_account_guard_allow_var')) {
    function printflow_account_guard_allow_var(): string {
        return '@printflow_account_insert_allowed';
    }
}

if (!function_exists('printflow_ensure_account_creation_guard')) {
    function printflow_ensure_account_creation_guard(): void {
        static $done = false;
        if ($done || !function_exists('db_query')) {
            return;
        }
        $done = true;

        global $conn;
        if (!$conn instanceof mysqli) {
            return;
        }

        $column = printflow_account_guard_column();
        $allowVar = printflow_account_guard_allow_var();
        $tables = [
            'customers' => 'bi_customers_require_system_origin',
            'users' => 'bi_users_require_system_origin',
        ];

        foreach ($tables as $table => $triggerName) {
            if (!db_table_has_column($table, $column)) {
                $afterClause = db_table_has_column($table, 'updated_at') ? ' AFTER `updated_at`' : '';
                $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` TINYINT(1) NOT NULL DEFAULT 0{$afterClause}";
                if (!$conn->query($sql)) {
                    error_log("account_creation_guard add column {$table}.{$column}: " . $conn->error);
                    continue;
                }
            }

            if (!$conn->query("UPDATE `{$table}` SET `{$column}` = 1 WHERE `{$column}` <> 1")) {
                error_log("account_creation_guard backfill {$table}.{$column}: " . $conn->error);
            }

            $existingTrigger = db_query(
                "SELECT TRIGGER_NAME
                 FROM information_schema.TRIGGERS
                 WHERE TRIGGER_SCHEMA = DATABASE()
                   AND EVENT_OBJECT_TABLE = ?
                   AND TRIGGER_NAME = ?
                 LIMIT 1",
                'ss',
                [$table, $triggerName]
            );

            if (!empty($existingTrigger)) {
                continue;
            }

            $message = addslashes("Direct inserts into {$table} are blocked. Use the application backend.");
            $triggerSql = "CREATE TRIGGER `{$triggerName}`
                BEFORE INSERT ON `{$table}`
                FOR EACH ROW
                BEGIN
                    IF COALESCE({$allowVar}, 0) <> 1 OR COALESCE(NEW.`{$column}`, 0) <> 1 THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = '{$message}';
                    END IF;
                END";

            if (!$conn->query($triggerSql)) {
                error_log("account_creation_guard create trigger {$triggerName}: " . $conn->error);
            }
        }
    }
}

if (!function_exists('printflow_account_creation_guard_begin')) {
    function printflow_account_creation_guard_begin(): void {
        global $conn;
        printflow_ensure_account_creation_guard();
        if ($conn instanceof mysqli) {
            @$conn->query('SET ' . printflow_account_guard_allow_var() . ' = 1');
        }
    }
}

if (!function_exists('printflow_account_creation_guard_end')) {
    function printflow_account_creation_guard_end(): void {
        global $conn;
        if ($conn instanceof mysqli) {
            @$conn->query('SET ' . printflow_account_guard_allow_var() . ' = 0');
        }
    }
}

if (!function_exists('printflow_run_guarded_account_insert')) {
    function printflow_run_guarded_account_insert(callable $callback) {
        printflow_account_creation_guard_begin();
        try {
            return $callback();
        } finally {
            printflow_account_creation_guard_end();
        }
    }
}
