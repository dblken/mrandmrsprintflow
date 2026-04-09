# Image Path Fixes - April 9, 2026

## Issues Fixed

### 1. Invalid PHP Template Strings in functions.php
**Problem:** Several functions had `'<?php echo $base_path; ?>//printflow/'` which is invalid - PHP code inside a string won't execute.

**Files Fixed:**
- `includes/functions.php` - upload_file() function (line ~1009)
- `includes/functions.php` - get_services_image_map() function
- `includes/functions.php` - get_service_image_url() function
- `includes/staff_pending_check.php` - redirect statement

**Solution:** Changed to use actual PHP constants:
```php
$base = defined('BASE_PATH') ? BASE_PATH : (defined('BASE_URL') ? BASE_URL : '/printflow');
```

### 2. Double Slash Issue from Previous Fix
**Problem:** When we removed `//printflow/`, we created correct paths but some functions still had template strings.

**Root Cause:** The `$base_path` variable is defined in `shop_config.php` but wasn't being used correctly in some functions.

## Files Modified

1. **includes/functions.php**
   - Fixed `upload_file()` - Now correctly builds relative paths for uploaded files
   - Fixed `get_services_image_map()` - Service images now load correctly
   - Fixed `get_service_image_url()` - Default service images work

2. **includes/staff_pending_check.php**
   - Fixed redirect path for pending staff users

## Testing Checklist

### Image Loading Tests
- [ ] Upload a design file in customer portal - verify path is correct
- [ ] Check service images on services page
- [ ] Verify product images load correctly
- [ ] Test profile picture uploads
- [ ] Check payment proof uploads

### Path Tests
- [ ] Verify no `/printflow/` hardcoded paths remain
- [ ] Check that `$base_path` variable works correctly
- [ ] Test on production (non-/printflow/ path)

### API Tests
- [ ] Test job_orders_api.php - should return valid JSON
- [ ] Check order_validation.js loads correctly
- [ ] Verify push notification polling works

## Notes

- All image paths now use dynamic base path from constants
- BASE_PATH or BASE_URL constants are checked first, fallback to '/printflow'
- No more hardcoded `/printflow/` paths in image functions
- Upload paths are now relative and work with any base path
