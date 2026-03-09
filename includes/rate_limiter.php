<?php
/**
 * Rate Limiter
 * PrintFlow - Printing Shop PWA
 *
 * Database-backed sliding-window rate limiter.
 * Uses the `rate_limit_log` table, auto-created on first use.
 *
 * Usage:
 *   RateLimiter::isBlocked('login', $ip, 5, 60)   // blocked after 5 hits in 60 s?
 *   RateLimiter::hit('login', $ip)                 // record one attempt
 *   RateLimiter::clear('login', $ip)               // clear on success
 *
 * Recommended limits (from the security plan):
 *   login        → 5 per 60 s per IP
 *   pwd_reset_ip → 3 per 600 s (10 min) per IP
 *   otp_request  → 3 per 600 s per identifier
 *   payment_verify → 5 per 300 s per order
 */

require_once __DIR__ . '/db.php';

class RateLimiter
{
    /**
     * Check whether the given action + key has exceeded its limit.
     *
     * @param string $action  Logical action name, e.g. 'login', 'pwd_reset_ip'
     * @param string $key     Per-subject identifier (IP, email, order ID, …)
     * @param int    $limit   Maximum allowed attempts in the window
     * @param int    $window  Window length in seconds
     * @return bool  true = blocked (limit exceeded), false = allowed
     */
    public static function isBlocked(string $action, string $key, int $limit, int $window): bool
    {
        self::ensureTable();
        self::maybeCleanup();

        $rows = db_query(
            "SELECT COUNT(*) AS cnt
               FROM rate_limit_log
              WHERE action       = ?
                AND lookup_key   = ?
                AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            'ssi',
            [$action, $key, $window]
        );

        return isset($rows[0]['cnt']) && (int) $rows[0]['cnt'] >= $limit;
    }

    /**
     * Record one attempt for the given action + key.
     */
    public static function hit(string $action, string $key): void
    {
        self::ensureTable();

        db_execute(
            "INSERT INTO rate_limit_log (action, lookup_key, attempted_at) VALUES (?, ?, NOW())",
            'ss',
            [$action, $key]
        );
    }

    /**
     * Remove all recorded attempts for this action + key.
     * Call after a successful login / OTP verification to reset the counter.
     */
    public static function clear(string $action, string $key): void
    {
        self::ensureTable();

        db_execute(
            "DELETE FROM rate_limit_log WHERE action = ? AND lookup_key = ?",
            'ss',
            [$action, $key]
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** Avoid repeated CREATE TABLE calls within a single request. */
    private static bool $tableEnsured = false;

    private static function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        db_execute(
            "CREATE TABLE IF NOT EXISTS rate_limit_log (
                id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                action       VARCHAR(64)  NOT NULL,
                lookup_key   VARCHAR(255) NOT NULL,
                attempted_at DATETIME     NOT NULL,
                INDEX idx_lookup (action, lookup_key, attempted_at)
            )"
        );

        self::$tableEnsured = true;
    }

    /**
     * Probabilistic cleanup of old rows (1-in-100 chance per request).
     * Deletes entries older than 24 hours to prevent table bloat.
     */
    private static function maybeCleanup(): void
    {
        if (rand(1, 100) === 1) {
            db_execute(
                "DELETE FROM rate_limit_log WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
        }
    }
}
