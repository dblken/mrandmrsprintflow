<?php
/**
 * Phone Number Verification API
 * ==============================
 * Validates Philippine mobile numbers via APILayer NumVerify.
 * Only accepts PH mobile numbers; rejects landlines and international.
 *
 * GET/POST: ?number=639XXXXXXXXX or 09XXXXXXXXX
 * Response: JSON { valid, carrier, location, error?, otp_sent? }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../includes/email_sms_config.php';

// ─── Helpers ───────────────────────────────────────────────────────────────

function json_out(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}

function normalize_ph_number(string $raw): string {
    $digits = preg_replace('/\D/', '', $raw);
    if (preg_match('/^0(\d{10})$/', $digits, $m)) {
        return '63' . $m[1];
    }
    if (preg_match('/^63(\d{10})$/', $digits, $m)) {
        return '63' . $m[1];
    }
    return $digits;
}

function validate_ph_format(string $number): ?string {
    $digits = preg_replace('/\D/', '', $number);
    $len = strlen($digits);

    // 09XXXXXXXXX → 11 digits
    if (preg_match('/^09\d{9}$/', $number) || preg_match('/^09\d{9}$/', $digits) || ($len === 11 && substr($digits, 0, 2) === '09')) {
        return '63' . substr($digits, 1);
    }
    // +639XXXXXXXXX → 13 digits
    if (preg_match('/^\+?63\d{10}$/', $number) || ($len === 12 && substr($digits, 0, 2) === '63')) {
        return $digits;
    }
    if ($len === 10 && substr($digits, 0, 1) === '9') {
        return '63' . $digits;
    }
    return null;
}

// ─── Request handling ──────────────────────────────────────────────────────

$number = trim($_GET['number'] ?? $_POST['number'] ?? '');
if ($number === '') {
    json_out(['valid' => false, 'error' => 'Phone number is required']);
    exit;
}

// Strip spaces, allow +63 or 09
$number = preg_replace('/\s+/', '', $number);
$canonical = normalize_ph_number($number);

// Client-side format validation (PHP mirror)
$format_valid = validate_ph_format($number);
if (!$format_valid) {
    json_out([
        'valid' => false,
        'error' => 'Invalid format. Use +63XXXXXXXXXX or 09XXXXXXXXX (10–11 digits).',
    ]);
    exit;
}

$api_key = defined('APILAYER_NUMBER_VERIFICATION_API_KEY') ? APILAYER_NUMBER_VERIFICATION_API_KEY : '';
if ($api_key === '' || $api_key === 'your-apilayer-api-key') {
    // Demo mode: simulate validation for testing without API key
    $is_demo = true;
} else {
    $is_demo = false;
}

if ($is_demo) {
    // Demo: accept 639XXXXXXXXX patterns, simulate carrier
    $ok = preg_match('/^63[89]\d{9}$/', $canonical);
    $carriers = ['Smart', 'Globe', 'DITO', 'TNT'];
    $locations = ['Manila', 'Cebu', 'Davao', 'Cabuyao', 'Calamba'];
    json_out([
        'valid' => $ok,
        'carrier' => $ok ? $carriers[array_rand($carriers)] : null,
        'location' => $ok ? $locations[array_rand($locations)] : null,
        'international_format' => $ok ? '+' . $canonical : null,
        'error' => $ok ? null : 'Invalid phone number',
        'demo' => true,
    ]);
    exit;
}

// Call APILayer NumVerify
$url = 'https://api.apilayer.com/number_verification/validate?number=' . urlencode($canonical);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . $api_key,
    ],
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    json_out(['valid' => false, 'error' => 'Verification service unavailable. Please try again.']);
    exit;
}

$data = json_decode($response, true);
if (!is_array($data)) {
    json_out(['valid' => false, 'error' => 'Invalid response from verification service.']);
    exit;
}

// Enforce: country_code = PH, line_type = mobile
$country = $data['country_code'] ?? '';
$line_type = strtolower($data['line_type'] ?? '');

if ($country !== 'PH') {
    json_out(['valid' => false, 'error' => 'Only Philippine (+63) numbers are allowed.']);
    exit;
}

if ($line_type !== 'mobile') {
    json_out(['valid' => false, 'error' => 'Landline numbers are not accepted. Please use a mobile number.']);
    exit;
}

$valid = !empty($data['valid']);

json_out([
    'valid' => $valid,
    'carrier' => $data['carrier'] ?? null,
    'location' => $data['location'] ?? null,
    'international_format' => $data['international_format'] ?? null,
    'error' => $valid ? null : ($data['error'] ?? 'Invalid phone number'),
]);
