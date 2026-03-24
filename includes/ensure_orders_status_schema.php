<?php
/**
 * Widen `orders.status` and `job_orders.status` when they are ENUM or short VARCHAR/CHAR.
 * Job/customization workflow writes values like Approved, To Pay, In Production, For Revision, VERIFY_PAY —
 * legacy ENUM('Pending','Processing',...) causes "Data truncated for column 'status'".
 */
function printflow_ensure_orders_status_schema(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    global $conn;

    foreach ([['orders', 'Pending'], ['job_orders', 'PENDING']] as $spec) {
        $tableEsc = preg_replace('/[^a-z0-9_]/i', '', (string) $spec[0]);
        $defaultLiteral = (string) $spec[1];
        if ($tableEsc === '') {
            continue;
        }
        $t = @$conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableEsc) . "'");
        if (!$t || $t->num_rows === 0) {
            if ($t) {
                $t->free();
            }
            continue;
        }
        $t->free();

        $res = @$conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE 'status'");
        if (!$res || $res->num_rows === 0) {
            if ($res) {
                $res->free();
            }
            continue;
        }
        $row = $res->fetch_assoc();
        $res->free();
        $type = strtolower((string)($row['Type'] ?? ''));
        $needs = (strpos($type, 'enum(') === 0);
        if (preg_match('/^varchar\((\d+)\)/', $type, $m) && (int) $m[1] < 80) {
            $needs = true;
        }
        if (preg_match('/^char\((\d+)\)/', $type, $m) && (int) $m[1] < 80) {
            $needs = true;
        }
        if (!$needs) {
            continue;
        }

        $def = $conn->real_escape_string($defaultLiteral);
        $sql = "ALTER TABLE `{$tableEsc}` MODIFY COLUMN `status` VARCHAR(100) NOT NULL DEFAULT '{$def}'";
        if (!@$conn->query($sql)) {
            error_log('printflow_ensure_orders_status_schema: ' . $tableEsc . ' — ' . $conn->error);
        }
    }
}
