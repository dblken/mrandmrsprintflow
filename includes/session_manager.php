<?php
/**
 * Centralized Session Manager
 * PrintFlow - Printing Shop PWA
 *
 * Handles the full secure session lifecycle:
 *   start()      — configure secure cookies, detect fingerprint/timeout, call once per request
 *   regenerate() — bind fingerprint + rotate ID after login/register
 *   destroy()    — full logout including cookie deletion
 *   setNoCacheHeaders() — prevent browser caching of protected pages
 *   wasTimedOut()       — true if this request's session was just expired
 *
 * PRODUCTION NOTE: Change SESSION_HMAC_SECRET to a strong random value unique per deployment.
 */

if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 3600); // 1-hour inactivity timeout (seconds)
}

if (!defined('SESSION_HMAC_SECRET')) {
    define('SESSION_HMAC_SECRET', 'PrintFlow-Session-HMAC-Secret-Change-In-Production-2024');
}

if (!defined('REMEMBER_ME_STAFF_DAYS')) {
    define('REMEMBER_ME_STAFF_DAYS', 30);
}

if (!defined('REMEMBER_ME_CUSTOMER_DAYS')) {
    define('REMEMBER_ME_CUSTOMER_DAYS', 90);
}

class SessionManager
{
    /** True when this request destroyed a session due to inactivity timeout. */
    private static bool $timed_out = false;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Start and validate the session.
     * Must be called before any output and before accessing $_SESSION.
     * Idempotent — safe to call even if session is already active.
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Session already active — just refresh activity timer for logged-in users
            if (isset($_SESSION['user_id'])) {
                $_SESSION['_last_activity'] = time();
            }
            return;
        }

        // Secure session cookie parameters (must be set before session_start)
        session_set_cookie_params([
            'lifetime' => 0,                      // Cookie expires when browser closes
            'path'     => '/',
            'domain'   => self::cookieDomain(),
            'secure'   => self::isHttps(),         // HTTPS-only in production
            'httponly' => true,                    // Inaccessible to JavaScript
            'samesite' => 'Lax',                   // Allow cookies on top-level nav (e.g. after login redirect)
        ]);

        // Prefer longer, harder-to-guess session IDs
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        ini_set('session.use_strict_mode', '1');

        session_start();

        // Validate existing authenticated session integrity
        if (isset($_SESSION['user_id'])) {
            if (!self::validateFingerprint()) {
                // Fingerprint mismatch — possible session hijacking; destroy and restart clean
                error_log(sprintf(
                    '[PrintFlow] Session fingerprint mismatch — user_id=%s IP=%s UA=%s',
                    $_SESSION['user_id'],
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 80)
                ));
                self::destroyAndRestart();
                return;
            }

            if (self::isTimedOut()) {
                self::$timed_out = true;
                self::destroyAndRestart();
                return;
            }

            // Valid session — update last activity
            $_SESSION['_last_activity'] = time();
        }
    }

    /**
     * Call immediately after a successful login or registration.
     * Rotates the session ID (prevents session fixation) and binds the session
     * to the current client fingerprint (IP + User-Agent).
     */
    public static function regenerate(): void
    {
        session_regenerate_id(true); // true = delete old session file on disk
        $_SESSION['_fingerprint']   = self::buildFingerprint();
        $_SESSION['_last_activity'] = time();
        $_SESSION['_created_at']    = time();
    }

    /**
     * Fully destroy the session: clear data, invalidate cookie, call session_destroy().
     * Use this for logout and for forced session termination.
     */
    public static function destroy(): void
    {
        $_SESSION = [];

        // Expire the session cookie immediately
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'] ?: '/',
                'domain'   => $p['domain'] ?? self::cookieDomain(),
                'secure'   => (bool) ($p['secure'] ?? self::isHttps()),
                'httponly' => (bool) ($p['httponly'] ?? true),
                'samesite' => self::cookieSameSite(),
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Set HTTP headers that prevent browsers from caching a protected page.
     * Call at the top of every page that requires authentication, before any output.
     */
    public static function setNoCacheHeaders(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
        }
    }

    /**
     * Returns true if the session for this request was destroyed due to inactivity.
     * Use in require_auth() to distinguish "timeout" from "not logged in".
     */
    public static function wasTimedOut(): bool
    {
        return self::$timed_out;
    }

    /**
     * Extend session cookie lifetime for "Remember Me" functionality.
     * @param int $days Number of days to keep the session alive
     */
    public static function applyRememberMe(int $days): void
    {
        $lifetime = $days * 86400; // Convert days to seconds
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires'  => time() + $lifetime,
            'path'     => $params['path'] ?: '/',
            'domain'   => $params['domain'] ?? self::cookieDomain(),
            'secure'   => (bool) ($params['secure'] ?? self::isHttps()),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => self::cookieSameSite(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private static function validateFingerprint(): bool
    {
        if (!isset($_SESSION['_fingerprint'])) {
            return true;
        }
        
        $current = self::buildFingerprint();
        if (hash_equals($_SESSION['_fingerprint'], $current)) {
            return true;
        }

        // Local development often switches between ::1 and 127.0.0.1
        // We log it but allow it locally to prevent constant session death
        if (($_SERVER['REMOTE_ADDR'] ?? '') === '::1' || ($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1') {
             error_log("[PrintFlow] Local Fingerprint Mismatch (Allowed): " . $_SESSION['_fingerprint'] . " vs " . $current);
             return true;
        }
        
        return false;
    }

    private static function buildFingerprint(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        return hash_hmac('sha256', $host . '|' . $ua . '|' . $accept, SESSION_HMAC_SECRET);
    }

    private static function isTimedOut(): bool
    {
        if (!isset($_SESSION['_last_activity'])) {
            return false;
        }
        return (time() - (int) $_SESSION['_last_activity']) > SESSION_LIFETIME;
    }

    private static function destroyAndRestart(): void
    {
        self::destroy();
        session_start();
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    }

    private static function cookieSameSite(): string
    {
        return 'Lax';
    }

    private static function cookieDomain(): string
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host);

        if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return '';
        }

        if (substr_count($host, '.') < 1) {
            return '';
        }

        if (str_ends_with($host, '.mrandmrsprintflow.com')) {
            return '.mrandmrsprintflow.com';
        }

        if ($host === 'mrandmrsprintflow.com') {
            return '.mrandmrsprintflow.com';
        }

        return $host;
    }

}
