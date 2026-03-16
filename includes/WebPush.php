<?php
/**
 * WebPush.php — Minimal RFC 8030 + RFC 8291 + VAPID (ES256) implementation.
 * No external dependencies. Requires PHP 8.1+ and OpenSSL extension.
 *
 * References:
 *   RFC 8291 — Message Encryption for Web Push
 *   RFC 8292 — Voluntary Application Server Identification (VAPID)
 *   RFC 5869 — HMAC-based Key Derivation Function (HKDF)
 */

class WebPush
{
    private string $subject;
    private string $publicKeyB64u;   // VAPID public key: base64url uncompressed P-256 point
    private string $privateKeyPem;   // VAPID private key PEM

    public function __construct(string $subject, string $publicKeyB64u, string $privateKeyPem)
    {
        $this->subject       = $subject;
        $this->publicKeyB64u = $publicKeyB64u;
        $this->privateKeyPem = $privateKeyPem;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Send a push notification.
     *
     * @param  array $subscription  ['endpoint' => '...', 'p256dh' => '...', 'auth' => '...']
     * @param  array $payload       JSON-serializable notification data
     * @param  int   $ttl           Time-to-live in seconds (default 24 h)
     * @return bool
     * @throws RuntimeException     'subscription_expired' when endpoint is 410/404
     */
    public function send(array $subscription, array $payload, int $ttl = 86400): bool
    {
        $endpoint = $subscription['endpoint'];
        $p256dh   = $subscription['p256dh'];
        $auth     = $subscription['auth'];

        // Encrypt payload → RFC 8291 ciphertext
        $body = $this->encrypt(json_encode($payload), $p256dh, $auth);
        if ($body === false) {
            error_log('[WebPush] Payload encryption failed.');
            return false;
        }

        // VAPID JWT for the push service audience
        $parsed   = parse_url($endpoint);
        $audience = $parsed['scheme'] . '://' . $parsed['host'];
        $jwt      = $this->makeVapidJwt($audience);
        if (!$jwt) {
            error_log('[WebPush] JWT creation failed.');
            return false;
        }

        $headers = [
            'Authorization: vapid t=' . $jwt . ',k=' . $this->publicKeyB64u,
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: ' . $ttl,
            'Content-Length: ' . strlen($body),
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,  // OK for localhost dev
        ]);
        $response = curl_exec($ch);
        $code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log('[WebPush] cURL error: ' . $curlErr);
            return false;
        }

        // 410 Gone / 404 = subscription endpoint is no longer valid
        if ($code === 410 || $code === 404) {
            throw new RuntimeException('subscription_expired');
        }

        if ($code < 200 || $code >= 300) {
            error_log('[WebPush] Push service returned HTTP ' . $code . ': ' . substr((string)$response, 0, 300));
            return false;
        }

        return true;
    }

    /**
     * Generate a new VAPID key pair. Call once via setup_vapid.php.
     *
     * @return array ['public_key' => string (base64url), 'private_key' => string (PEM)]
     */
    public static function generateKeys(): array
    {
        $key = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if (!$key) {
            throw new RuntimeException('openssl_pkey_new failed: ' . openssl_error_string());
        }

        $details = openssl_pkey_get_details($key);
        $pubRaw  = "\x04" . $details['ec']['x'] . $details['ec']['y'];  // Uncompressed point

        openssl_pkey_export($key, $privPem);

        return [
            'public_key'  => self::b64u_encode($pubRaw),
            'private_key' => $privPem,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RFC 8291 Payload Encryption (aes128gcm content encoding)
    // ─────────────────────────────────────────────────────────────────────────

    private function encrypt(string $plaintext, string $p256dhB64u, string $authB64u): string|false
    {
        $p256dh = self::b64u_decode($p256dhB64u);
        $auth   = self::b64u_decode($authB64u);

        if (strlen($p256dh) !== 65 || strlen($auth) !== 16) {
            error_log('[WebPush] Invalid subscription key lengths: p256dh=' . strlen($p256dh) . ' auth=' . strlen($auth));
            return false;
        }

        // Generate ephemeral sender P-256 key pair
        $eph = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if (!$eph) return false;

        $ephDetails = openssl_pkey_get_details($eph);
        $ephPub     = "\x04" . $ephDetails['ec']['x'] . $ephDetails['ec']['y'];  // 65 bytes

        // Convert subscriber's raw public key to an OpenSSL key object
        $subPub = openssl_pkey_get_public(self::rawEcPubKeyToPem($p256dh));
        if (!$subPub) {
            error_log('[WebPush] Could not parse subscriber p256dh key.');
            return false;
        }

        // ECDH: shared secret = x-coordinate of (eph_private * sub_public)
        $sharedSecret = openssl_pkey_derive($subPub, $eph);
        if ($sharedSecret === false) {
            error_log('[WebPush] ECDH derive failed: ' . openssl_error_string());
            return false;
        }

        // RFC 8291 §3.3 key derivation
        $salt = random_bytes(16);

        // PRK_key: HKDF-SHA256(salt=auth, ikm=sharedSecret, info="WebPush: info\0" || ua_pub || as_pub)
        $ikm = self::hkdf($auth, $sharedSecret, "WebPush: info\x00" . $p256dh . $ephPub, 32);

        // CEK + nonce from random salt
        $cek   = self::hkdf($salt, $ikm, "Content-Encoding: aes128gcm\x00", 16);
        $nonce = self::hkdf($salt, $ikm, "Content-Encoding: nonce\x00", 12);

        // AES-128-GCM encryption (append \x02 padding delimiter per RFC 8291)
        $tag        = '';
        $ciphertext = openssl_encrypt(
            $plaintext . "\x02",
            'aes-128-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );
        if ($ciphertext === false) {
            error_log('[WebPush] AES-128-GCM encryption failed.');
            return false;
        }

        // RFC 8291 record header: salt(16) || rs(4 BE uint32) || keylen(1) || sender_pub(65)
        return $salt
            . pack('N', 4096)          // Record size = 4096
            . chr(strlen($ephPub))     // Key ID length = 65
            . $ephPub                  // Ephemeral sender public key
            . $ciphertext
            . $tag;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VAPID JWT (ES256)
    // ─────────────────────────────────────────────────────────────────────────

    private function makeVapidJwt(string $audience): string|false
    {
        $header  = self::b64u_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = self::b64u_encode(json_encode([
            'aud' => $audience,
            'sub' => $this->subject,
            'exp' => time() + 43200,  // 12 hours
        ]));

        $unsigned = $header . '.' . $payload;

        $key = openssl_pkey_get_private($this->privateKeyPem);
        if (!$key) return false;

        // openssl_sign produces DER-encoded ECDSA signature; ES256 needs raw R||S (64 bytes)
        if (!openssl_sign($unsigned, $derSig, $key, OPENSSL_ALGO_SHA256)) return false;

        $rawSig = self::derToRawEcSig($derSig);
        if (!$rawSig) return false;

        return $unsigned . '.' . self::b64u_encode($rawSig);
    }

    /**
     * Convert DER-encoded ECDSA signature to raw R||S (32 + 32 bytes for P-256).
     */
    private static function derToRawEcSig(string $der): string|false
    {
        $offset = 0;
        if (strlen($der) < 4) return false;

        if (ord($der[$offset++]) !== 0x30) return false;  // SEQUENCE

        // Handle both short and long-form DER lengths
        $lenByte = ord($der[$offset++]);
        if ($lenByte & 0x80) {
            $numLen = $lenByte & 0x7f;
            $offset += $numLen;  // Skip multi-byte length (P-256 is always short-form)
        }

        // INTEGER R
        if (ord($der[$offset++]) !== 0x02) return false;
        $rLen = ord($der[$offset++]);
        $r    = substr($der, $offset, $rLen);
        $offset += $rLen;

        // INTEGER S
        if (ord($der[$offset++]) !== 0x02) return false;
        $sLen = ord($der[$offset++]);
        $s    = substr($der, $offset, $sLen);

        // Pad/trim to exactly 32 bytes each
        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Wrap a 65-byte uncompressed EC public key in a PEM SubjectPublicKeyInfo shell.
     */
    private static function rawEcPubKeyToPem(string $raw): string
    {
        // DER SubjectPublicKeyInfo for a P-256 public key (fixed 91-byte wrapper)
        $der = "\x30\x59"                                      // SEQUENCE (89 bytes)
             . "\x30\x13"                                      // SEQUENCE (19 bytes) — AlgorithmIdentifier
             . "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"        // OID id-ecPublicKey
             . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"    // OID prime256v1
             . "\x03\x42\x00"                                  // BIT STRING (66 bytes, 0 unused bits)
             . $raw;                                           // 04 || x || y

        return "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($der), 64, "\n")
             . "-----END PUBLIC KEY-----\n";
    }

    /**
     * HKDF-SHA256: Extract (HMAC) then Expand.
     * Single-function implementation sufficient for ≤ 32-byte outputs.
     */
    private static function hkdf(string $salt, string $ikm, string $info, int $len): string
    {
        $prk = hash_hmac('sha256', $ikm, $salt, true);   // Extract
        $t   = '';
        $okm = '';
        $i   = 1;
        while (strlen($okm) < $len) {
            $t    = hash_hmac('sha256', $t . $info . chr($i++), $prk, true);  // Expand T(i)
            $okm .= $t;
        }
        return substr($okm, 0, $len);
    }

    public static function b64u_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function b64u_decode(string $data): string
    {
        $rem = strlen($data) % 4;
        $pad = $rem ? str_repeat('=', 4 - $rem) : '';
        return base64_decode(strtr($data . $pad, '-_', '+/'));
    }
}
