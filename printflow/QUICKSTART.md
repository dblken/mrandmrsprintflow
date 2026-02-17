# PrintFlow PWA - Quick Start Guide

## 🚀 Getting Started in 5 Minutes

Follow these steps to get PrintFlow up and running quickly:

### Step 1: Database Setup (2 minutes)

1. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start Apache and MySQL

2. **Create Database**
   - Open phpMyAdmin: http://localhost/phpmyadmin
   - Create new database named `printflow`
   - Import your SQL schema (the 22-table schema you created)

3. **Create Admin User**
   ```sql
   -- Run this in phpMyAdmin SQL tab:
   INSERT INTO users (first_name, last_name, email, password_hash, role, status, created_at, updated_at) 
   VALUES ('Admin', 'User', 'admin@printflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Activated', NOW(), NOW());
   ```
   **Login credentials**: 
   - Email: `admin@printflow.com`
   - Password: `password`

### Step 2: Configure Database Connection (30 seconds)

Open `c:\xampp\htdocs\printflow\includes\db.php` and verify:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Your MySQL username
define('DB_PASS', '');           // Your MySQL password (usually empty for XAMPP)
define('DB_NAME', 'printflow');  // Database name
```

### Step 3: Test the Application (1 minute)

1. Open your browser
2. Navigate to: **http://localhost/printflow/public/index.php**
3. Click "Login" and use the admin credentials above
4. You should be redirected to the admin dashboard!

### Step 4: Install Tailwind CSS (Optional - 2 minutes)

For full Tailwind CSS features:

1. **Install Node.js** (if not installed): https://nodejs.org/
2. Open Command Prompt and run:
   ```
   cd C:\xampp\htdocs\printflow
   npm install
   npm run build
   ```

> **Note**: A minimal CSS file is included, so this step is optional for initial testing.

---

## 📱 Test PWA Features

### Desktop Installation
1. Open Chrome or Edge
2. Visit http://localhost/printflow/public/
3. Look for install icon in address bar
4. Click to install as app

### Mobile Testing (on local network)
1. Get your computer's local IP (run `ipconfig` in Command Prompt)
2. On mobile browser: http://YOUR_IP/printflow/public/
3. Add to home screen

---

## ✅ What Works Right Now

### Authentication
- ✅ Login (Admin/Staff/Customer)
- ✅ Customer Registration
- ✅ Password Reset Flow
- ✅ Role-Based Access Control

### Public Pages
- ✅ Landing Page
- ✅ Product Browsing (with filter/search)
- ✅ FAQ Page
- ✅ Login/Register/Password Reset

### Admin Portal
- ✅ Dashboard (with charts)
- ✅ Orders Management (with filters)

### Staff Portal
- ✅ Dashboard
- ✅ Profile Management

### Customer Portal
- ✅ Dashboard
- ✅ Profile Management

### PWA Features
- ✅ Installable
- ✅ Offline Support
- ✅ Service Worker Caching

---

## 🛠️ Quick Testing Checklist

- [ ] Access landing page
- [ ] Register a new customer account
- [ ] Login as customer
- [ ] View customer dashboard
- [ ] Update customer profile
- [ ] Logout
- [ ] Login as admin (admin@printflow.com / password)
- [ ] View admin dashboard  
- [ ] Check orders management page
- [ ] Test PWA installation
- [ ] Test password reset flow

---

## 🎯 Next Development Steps

To complete the project, you need to create:

### High Priority
1. **Products Management (Admin)** - CRUD for products with image upload
2. **Order Placement (Customer)** - Shopping cart and checkout
3. **Design Upload (Customer)** - File upload for custom designs
4. **Order Details Page** - View full order information with status history

### Medium Priority
5. **Customer Management (Admin)** - View/manage customer accounts
6. **Staff Orders Page** - View and update order statuses
7. **Payment Methods (Admin)** - Configure GCash/Maya/Bank options
8. **Notifications System** - Display in-app notifications

### Additional Features
9. **User/Staff Management (Admin)** - Create admin/staff accounts
10. **Settings Page** - System configuration
11. **Activity Logs** - Audit trail viewing
12. **Reports** - Generate sales and inventory reports

---

## 📖 Development Pattern

Use this pattern for creating new pages:

```php
<?php
// 1. Include required files
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// 2. Enforce access control
require_role('Admin'); // or 'Staff' or 'Customer'

// 3. Get data from database
$data = db_query("SELECT * FROM table_name WHERE condition = ?", 's', [$param]);

// 4. Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        // Process form
        db_execute("INSERT INTO...", 'ss', [$value1, $value2]);
    }
}

// 5. Include header and render UI
$page_title = 'Page Title';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Your HTML here -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
```

---

## 🔧 Common Issues & Solutions

### Issue: "Database connection failed"
**Solution**: Check `includes/db.php` credentials and ensure MySQL is running

### Issue: "Page not found" or styling broken
**Solution**: Ensure you're accessing via http://localhost/printflow/public/

### Issue: CSS not loading or looks basic
**Solution**: Run `npm install && npm run build` for full Tailwind CSS

### Issue: Login redirects to wrong dashboard
**Solution**: Check the `role` field in database matches expected values (Admin/Staff/Customer)

### Issue: CSRF token errors
**Solution**: Ensure forms include `<?php echo csrf_field(); ?>`

---

## 📞 Need Help?

- Check `README.md` for detailed documentation
- Check `walkthrough.md` for feature status
- Review existing working pages as templates
- Database helper functions are in `includes/functions.php`

---

## 🎉 You're All Set!

Your PrintFlow PWA foundation is ready. The core infrastructure, authentication, PWA features, and basic portals are all working. Now you can focus on building out the remaining business logic features!

**Happy Coding! 🚀**
