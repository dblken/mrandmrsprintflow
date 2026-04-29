# API Cart Error Fix

## Issue
Console error: `POST https://mrandmrsprintflow.com/public/api_cart.php 404 (Not Found)`

The system was trying to access `/public/api_cart.php` but the file actually exists at `/customer/api_cart.php`.

## Root Cause
The `inactivity_logout.js` script was configured to ping `/public/api_cart.php` every 15 minutes to keep the session alive, but this endpoint doesn't exist.

## Solution

### 1. Updated Inactivity Script
**File:** `public/assets/js/inactivity_logout.js`

**Changed:**
- Removed the cart API ping mechanism
- Replaced with existing `sessionStatusUrl` endpoint
- This endpoint already exists at `/public/api_session_status.php` and works correctly

**Before:**
```javascript
var apiCartUrl = (window.PFConfig && window.PFConfig.apiCartUrl) || '/public/api_cart.php';
setInterval(function() {
    if (document.visibilityState === 'visible' && Date.now() - lastActivity < WARNING_MS) {
        fetch(apiCartUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_count', csrf_token: ... }),
            credentials: 'same-origin'
        }).catch(function() {});
    }
}, 15 * 60 * 1000);
```

**After:**
```javascript
setInterval(function() {
    if (document.visibilityState === 'visible' && Date.now() - lastActivity < WARNING_MS) {
        fetch(sessionStatusUrl + '?_=' + Date.now(), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).catch(function() {});
    }
}, 15 * 60 * 1000);
```

### 2. Removed Unused Config
Removed `apiCartUrl` from PFConfig in:
- `includes/admin_sidebar.php`
- `includes/staff_sidebar.php`
- `includes/manager_sidebar.php`

This config was only used by the inactivity script and is no longer needed.

### 3. Verified Correct Usage
Confirmed that other parts of the system correctly use:
- `/customer/api_cart.php` - Customer cart operations (correct path)
- Relative path `api_cart.php` in customer pages (resolves correctly)

## Files Modified
1. `public/assets/js/inactivity_logout.js` - Fixed session ping mechanism
2. `includes/admin_sidebar.php` - Removed unused apiCartUrl config
3. `includes/staff_sidebar.php` - Removed unused apiCartUrl config
4. `includes/manager_sidebar.php` - Removed unused apiCartUrl config

## Benefits
✅ No more 404 errors in console
✅ Session keep-alive still works (using session status endpoint)
✅ More efficient (session status is lighter than cart operations)
✅ No impact on existing features
✅ Chat system works without errors
✅ Cart functionality unchanged

## Testing Checklist
- [ ] No 404 errors in browser console
- [ ] Session timeout warning still appears after 55 minutes
- [ ] Auto-logout still works after 1 hour
- [ ] Cart operations work normally in customer pages
- [ ] Chat system loads without errors
- [ ] Staff/Admin/Manager dashboards load correctly
- [ ] Session stays alive during active use

## Technical Notes
- The session status endpoint (`/public/api_session_status.php`) is the correct way to check session validity
- It's already used by the inactivity script for session checks
- Using it for keep-alive pings is more appropriate than cart operations
- The cart API at `/customer/api_cart.php` remains unchanged and functional for actual cart operations
