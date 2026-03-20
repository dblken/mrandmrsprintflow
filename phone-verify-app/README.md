# Phone Verification — Register Form

Philippine mobile number verification using React (frontend) and PHP (backend).

## Features

- **Philippine numbers only** (+63 or 09)
- **Format validation**: 10–11 digits (local) or 13 digits (+63)
- **APILayer NumVerify API** (API key kept server-side)
- **Strict rules**: PH mobile only; rejects landlines and international
- **Carrier & location** display (Smart, Globe, DITO, etc.)
- **OTP simulation** (6-digit code stored in session)
- **Real-time UX**: green check when valid, red error when invalid

## Setup

### 1. APILayer API Key

Edit `includes/email_sms_config.php`:

```php
define('APILAYER_NUMBER_VERIFICATION_API_KEY', 'your-actual-api-key');
```

Get a key: https://apilayer.com/marketplace/number_verification-api

Without a key, the API runs in **demo mode** (simulates validation).

### 2. Build React app

```bash
cd phone-verify-app
npm install
npm run build
```

Output goes to `public/phone-verify/`.

### 3. Access

- **Built app**: `http://localhost/printflow/public/phone-verify/`
- **Dev server**: `cd phone-verify-app && npm run dev` (port 5173)

## API Endpoints

| Endpoint | Method | Purpose |
|---------|--------|---------|
| `/printflow/public/api/phone_verify.php` | GET/POST | Validate number via APILayer |
| `/printflow/public/api/phone_verify_otp_send.php` | POST | Send OTP (simulation) |
| `/printflow/public/api/phone_verify_otp_check.php` | POST | Verify OTP |

## Format Rules

- **+63 9XX XXX XXXX** (13 digits with country code)
- **09XX XXX XXXX** (11 digits local)
- No spaces or letters in submission; formatting is visual only
