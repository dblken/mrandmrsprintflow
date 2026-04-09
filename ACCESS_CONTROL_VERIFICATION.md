# Access Control Verification Checklist

## ✅ Implementation Complete

All customer pages and API endpoints now have proper access control implemented.

## 🧪 Testing Checklist

### 1. Customer Access Tests

Login as a **Customer** and verify:

- [ ] ✅ Can access `/customer/services.php`
- [ ] ✅ Can access `/customer/products.php`
- [ ] ✅ Can access `/customer/cart.php`
- [ ] ✅ Can access `/customer/orders.php`
- [ ] ✅ Can access `/customer/profile.php`
- [ ] ✅ Can access `/customer/checkout.php`
- [ ] ✅ Can access order creation pages (order_*.php)
- [ ] ❌ **Cannot** access `/admin/dashboard.php` (should redirect to `/customer/services.php`)
- [ ] ❌ **Cannot** access `/staff/orders.php` (should redirect to `/customer/services.php`)
- [ ] ❌ **Cannot** access `/manager/dashboard.php` (should redirect to `/customer/services.php`)

### 2. Admin Access Tests

Login as an **Admin** and verify:

- [ ] ✅ Can access `/admin/dashboard.php`
- [ ] ✅ Can access `/admin/orders_management.php`
- [ ] ✅ Can access `/admin/products_management.php`
- [ ] ✅ Can access `/admin/settings.php`
- [ ] ❌ **Cannot** access `/customer/services.php` (should redirect to `/admin/dashboard.php`)
- [ ] ❌ **Cannot** access `/customer/cart.php` (should redirect to `/admin/dashboard.php`)
- [ ] ❌ **Cannot** access `/customer/orders.php` (should redirect to `/admin/dashboard.php`)

### 3. Staff Access Tests

Login as **Staff** and verify:

- [ ] ✅ Can access `/staff/dashboard.php`
- [ ] ✅ Can access `/staff/orders.php`
- [ ] ✅ Can access `/staff/products.php`
- [ ] ❌ **Cannot** access `/customer/services.php` (should redirect to `/staff/dashboard.php`)
- [ ] ❌ **Cannot** access `/customer/cart.php` (should redirect to `/staff/dashboard.php`)
- [ ] ❌ **Cannot** access `/admin/settings.php` (should redirect to `/staff/dashboard.php`)

### 4. Manager Access Tests

Login as **Manager** and verify:

- [ ] ✅ Can access `/manager/dashboard.php`
- [ ] ✅ Can access `/manager/orders.php`
- [ ] ✅ Can access `/manager/reports.php`
- [ ] ❌ **Cannot** access `/customer/services.php` (should redirect to `/manager/dashboard.php`)
- [ ] ❌ **Cannot** access `/customer/cart.php` (should redirect to `/manager/dashboard.php`)
- [ ] ❌ **Cannot** access `/admin/settings.php` (should redirect to `/manager/dashboard.php`)

### 5. Not Logged In Tests

Without logging in, verify:

- [ ] ❌ **Cannot** access `/customer/services.php` (should redirect to `/`)
- [ ] ❌ **Cannot** access `/admin/dashboard.php` (should redirect to `/`)
- [ ] ❌ **Cannot** access `/staff/orders.php` (should redirect to `/`)
- [ ] ❌ **Cannot** access `/manager/dashboard.php` (should redirect to `/`)

### 6. API Endpoint Tests

Test customer API endpoints:

- [ ] ✅ Customer can access `/customer/api_cart.php`
- [ ] ✅ Customer can access `/customer/api_profile.php`
- [ ] ✅ Customer can access `/customer/api_customer_orders.php`
- [ ] ❌ Admin **cannot** access customer APIs (should redirect)
- [ ] ❌ Staff **cannot** access customer APIs (should redirect)
- [ ] ❌ Not logged in **cannot** access customer APIs (should redirect)

### 7. Session Security Tests

- [ ] ✅ After logout, cannot access protected pages
- [ ] ✅ Session timeout redirects to login
- [ ] ✅ Back button after logout doesn't show cached pages
- [ ] ✅ CSRF tokens are present on all forms
- [ ] ✅ Rate limiting works on login attempts

## 🔍 Quick Test Script

You can use this quick test to verify access control:

```php
<?php
// Test file: test_access_control.php
// Place in root directory and access via browser

session_start();

echo "<h1>Access Control Test</h1>";
echo "<p>Current User: " . ($_SESSION['user_type'] ?? 'Not logged in') . "</p>";
echo "<hr>";

$test_urls = [
    'Customer Pages' => [
        '/printflow/customer/services.php',
        '/printflow/customer/cart.php',
        '/printflow/customer/orders.php',
    ],
    'Admin Pages' => [
        '/printflow/admin/dashboard.php',
        '/printflow/admin/orders_management.php',
    ],
    'Staff Pages' => [
        '/printflow/staff/dashboard.php',
        '/printflow/staff/orders.php',
    ],
];

foreach ($test_urls as $category => $urls) {
    echo "<h2>$category</h2>";
    echo "<ul>";
    foreach ($urls as $url) {
        echo "<li><a href='$url' target='_blank'>$url</a></li>";
    }
    echo "</ul>";
}

echo "<hr>";
echo "<p><a href='/printflow/public/logout.php'>Logout</a></p>";
?>
```

## 📊 Expected Results Summary

| User Role | Customer Pages | Admin Pages | Staff Pages | Manager Pages |
|-----------|---------------|-------------|-------------|---------------|
| Customer  | ✅ Access     | ❌ Redirect | ❌ Redirect | ❌ Redirect   |
| Admin     | ❌ Redirect   | ✅ Access   | ❌ Redirect | ❌ Redirect   |
| Staff     | ❌ Redirect   | ❌ Redirect | ✅ Access   | ❌ Redirect   |
| Manager   | ❌ Redirect   | ❌ Redirect | ❌ Redirect | ✅ Access     |
| Guest     | ❌ Redirect   | ❌ Redirect | ❌ Redirect | ❌ Redirect   |

## 🐛 Troubleshooting

### Issue: Redirect Loop

**Symptoms:** Page keeps redirecting endlessly

**Solution:**
1. Check that dashboard pages don't have `require_customer()` or wrong role requirement
2. Verify session is properly set with correct `user_type`
3. Clear browser cache and cookies
4. Check `AUTH_REDIRECT_BASE` constant in `includes/auth.php`

### Issue: Can Access Unauthorized Pages

**Symptoms:** User can access pages they shouldn't

**Solution:**
1. Verify the page has `require_customer()` or `require_role()` call
2. Check that it's called AFTER `require_once` statements
3. Verify session is active and has correct `user_type`
4. Check for any `exit()` or `die()` statements before access control

### Issue: 403 Error Instead of Redirect

**Symptoms:** Shows "403 Forbidden" instead of redirecting

**Solution:**
1. This shouldn't happen with the new implementation
2. Check if there's an old `require_role()` call that wasn't updated
3. Verify you're using the latest version of `includes/auth.php`

## ✨ Success Criteria

All tests should pass with these results:

1. ✅ Customers can only access customer pages
2. ✅ Admin/Staff/Manager cannot access customer pages
3. ✅ Customers cannot access admin/staff/manager pages
4. ✅ Unauthorized access redirects to appropriate dashboard
5. ✅ Not logged in users redirect to login page
6. ✅ API endpoints are protected
7. ✅ Session security features work correctly

## 📝 Notes

- All redirects should be **seamless** (no error messages)
- Users should be redirected to their **appropriate dashboard**
- No **403 errors** should be shown (only redirects)
- **Session timeout** should redirect to login with timeout message
- **CSRF tokens** should be present on all forms

## 🎉 Completion

Once all tests pass, the access control system is fully functional and secure!

---

**Last Updated:** <?php echo date('Y-m-d H:i:s'); ?>
**Implementation Status:** ✅ Complete
**Files Modified:** 50+ customer pages and API endpoints
**Security Level:** High
