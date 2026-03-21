
A comprehensive pickup-only printing shop management system built with PHP, MySQL, and Tailwind CSS.

## 📋 Project Overview

PrintFlow is a Progressive Web App (PWA) designed for a printing shop that handles orders for tarpaulins, t-shirts, stickers, and custom designs. The system supports three user roles: Admin, Staff, and Customer.

## ✨ Features

### Progressive Web App
- ✅ Installable on desktop and mobile browsers
- ✅ Offline support with service worker caching
- ✅ Push notifications for order status updates
- ✅ Responsive design for all devices

### User Roles
- **Admin**: Full system control, analytics, user management
- **Staff**: Order processing, inventory viewing
- **Customer**: Browse products, place orders, track status

### Core Functionality
- Email + password authentication
- Order workflow: Pending → Processing → Ready for Pickup → Completed
- Custom design upload and approval system
- Multiple payment methods (GCash, Maya, Bank, QR codes)
- Real-time notifications (email, SMS optional, in-app)
- POS system for walk-in transactions
- Comprehensive analytics dashboard
- Activity logs and backup system

## 🚀 Installation

### Prerequisites
- XAMPP (Apache + PHP 7.4+ + MySQL)
- Node.js and npm (for Tailwind CSS compilation)
- Web browser (Chrome/Firefox/Edge for PWA features)

### Setup Steps

1. **Clone/Copy Project**
   ```
   Copy the printflow folder to C:\xampp\htdocs\
   ```

2. **Database Setup**
   - Start XAMPP Control Panel
   - Start Apache and MySQL services
   - Open phpMyAdmin: http://localhost/phpmyadmin
   - Create a new database named `printflow`
   - Import or run the database schema SQL file you created

3. **Configure Database Connection**
   - Open `includes/db.php`
   - Update these constants:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');          // Your MySQL username
     define('DB_PASS', '');              // Your MySQL password
     define('DB_NAME', 'printflow');     // Database name
     ```

4. **Install Tailwind CSS (Optional but Recommended)**
   - Open Command Prompt/PowerShell
   - Navigate to project directory:
     ```
     cd C:\xampp\htdocs\printflow
     ```
   - Install Node.js dependencies:
     ```
     npm install
     ```
   - Build Tailwind CSS:
     ```
     npm run build
     ```
   - For development with auto-rebuild:
     ```
     npm run watch
     ```

   **Note:** A minimal pre-compiled CSS file is included, but for full Tailwind features, run the build command.

5. **Create Sample Admin User**
   - Open phpMyAdmin
   - Go to the `users` table
   - Insert a new admin user (use bcrypt password hash):
     ```sql
     INSERT INTO users (first_name, last_name, email, password_hash, role, status) 
     VALUES ('Admin', 'User', 'admin@printflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Activated');
     ```
     Password for this hash is: `password`

6. **Access the Application**
   - Open your web browser
   - Navigate to: http://localhost/printflow/public/index.php
   - Login with the admin credentials created above

## 📁 Project Structure

```
printflow/
│
├── public/                    # Publicly accessible files
│   ├── index.php             # Landing page
│   ├── login.php             # Login page
│   ├── register.php          # Customer registration
│   ├── logout.php            # Logout handler
│   ├── manifest.json         # PWA manifest
│   ├── sw.js                 # Service worker
│   ├── offline.html          # Offline fallback page
│   └── assets/
│       ├── css/              # Stylesheets
│       ├── js/               # JavaScript files
│       └── images/           # Images and icons
│
├── admin/                     # Admin portal
│   ├── dashboard.php         # Admin dashboard with analytics
│   ├── orders_management.php # Order management (CREATE THIS)
│   ├── products_management.php # Product CRUD (CREATE THIS)
│   ├── customers_management.php # Customer management (CREATE THIS)
│   ├── user_staff_management.php # User/staff management (CREATE THIS)
│   ├── payment_methods.php   # Payment method config (CREATE THIS)
│   ├── settings.php          # System settings (CREATE THIS)
│   └── ...                   # Other admin pages
│
├── staff/                     # Staff portal
│   ├── dashboard.php         # Staff dashboard
│   ├── orders.php            # Order viewing/updating (CREATE THIS)
│   ├── products.php          # Inventory viewing (CREATE THIS)
│   └── profile.php           # Staff profile (CREATE THIS)
│
├── customer/                  # Customer portal
│   ├── dashboard.php         # Customer dashboard
│   ├── products.php          # Product browsing (CREATE THIS)
│   ├── orders.php            # Order history (CREATE THIS)
│   ├── upload_design.php     # Design upload (CREATE THIS)
│   └── profile.php           # Customer profile (CREATE THIS)
│
├── includes/                  # Shared PHP includes
│   ├── db.php                # Database connection
│   ├── auth.php              # Authentication system
│   ├── functions.php         # Helper functions
│   ├── header.php            # Header component
│   └── footer.php            # Footer component
│
├── uploads/                   # Uploaded files
│   └── designs/              # Customer design files
│
├── tailwind.config.js        # Tailwind configuration
├── package.json              # NPM dependencies
└── README.md                 # This file
```

## 🎨 Creating Remaining Pages

Many pages are placeholders. Here's how to create them:

### Example: Creating `admin/orders_management.php`

```php
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Admin');

// Get all orders with customer info
$orders = db_query("
    SELECT o.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name 
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.customer_id 
    ORDER BY o.order_date DESC
");

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $order_id = (int)$_POST['order_id'];
        $new_status = $_POST['status'];
        
        // Update order
        db_execute("UPDATE orders SET status = ? WHERE order_id = ?", 'si', [$new_status, $order_id]);
        
        // Log status change
        db_execute("INSERT INTO order_status_history (order_id, old_status, new_status, changed_by) VALUES (?, ?, ?, ?)", 'isss', [
            $order_id, $_POST['old_status'], $new_status, 'Admin'
        ]);
        
        // Send notification to customer
        $order = db_query("SELECT customer_id FROM orders WHERE order_id = ?", 'i', [$order_id]);
        create_notification($order[0]['customer_id'], 'Customer', "Your order #{$order_id} status has been updated to: {$new_status}", 'Order', true, false);
        
        header('Location: orders_management.php?success=1');
        exit();
    }
}

$page_title = 'Orders Management - Admin';
// Include header, create UI with table of orders, modals for details, etc.
?>
```

### Follow This Pattern for Other Pages:
1. Include `auth.php` and `functions.php`
2. Use `require_role()` to enforce access control
3. Fetch data from database using `db_query()`
4. Handle form submissions with CSRF protection
5. Use helper functions for formatting, notifications, etc.
6. Include header and footer

## 🔐 Security Features

- ✅ CSRF protection on all forms
- ✅ Password hashing with bcrypt
- ✅ Prepared statements for SQL injection prevention
- ✅ Role-based access control
- ✅ Session management
- ✅ Input sanitization and validation

## 📱 PWA Features

### Testing PWA Installation
1. Open Chrome/Edge
2. Navigate to http://localhost/printflow/public/
3. Click the install icon in the address bar
4. App should install and open in standalone window

### Service Worker Caching
- Static assets (CSS, JS, images) are cached automatically
- API responses are cached for offline viewing
- Offline fallback page shows when network is unavailable

### Push Notifications
To enable push notifications:
1. Generate VAPID keys (use a VAPID key generator online)
2. Update `public/assets/js/pwa.js` with your VAPID public key
3. Implement server-side push notification sending in `functions.php`

## 📊 Database Schema

Your database includes 22 tables:
- `users`, `customers` - User accounts
- `products` - Products/services
- `orders`, `order_items`,`order_designs`, `order_status_history` - Order management
- `payment_methods` - Payment configurations
- `pos_transactions`, `pos_items` - Walk-in sales
- `notifications`, `notification_templates` - Notification system
- `password_resets` - Password recovery
- `activity_logs` - Audit trail
- And more...

## 🛠️ Tailwind CSS

### If npm is not installed:
A minimal CSS file is provided at `public/assets/css/output.css`. For full Tailwind features:

1. Install Node.js from https://nodejs.org/
2. Run `npm install` in the project directory
3. Run `npm run build` to compile Tailwind CSS

### Development Workflow:
- Run `npm run watch` to auto-rebuild CSS on file changes
- Edit `public/assets/css/input.css` for custom styles
- Modify `tailwind.config.js` for theme customization

## 📧 Email Configuration

For password reset and notifications:

### Option 1: PHP mail() (Simple, may not work on all servers)
- Already configured in `includes/functions.php`
- Should work with XAMPP's default setup

### Option 2: SMTP (Recommended for production)
Update `send_email()` in `includes/functions.php`:
```php
// Use PHPMailer or similar library
// Configure SMTP settings (Gmail, SendGrid, Mailgun, etc.)
```

## 🚧 TODO / Incomplete Features

The following pages still need to be created. Use the existing examples as templates:

### Admin Portal
- [ ] `orders_management.php` - Full CRUD for orders
- [ ] `products_management.php` - Product CRUD with image upload
- [ ] `customers_management.php` - Customer list and management
- [ ] `user_staff_management.php` - Create/manage admin/staff
- [ ] `payment_methods.php` - Add/edit payment options
- [ ] `faq_chatbot_management.php` - FAQ & support chat management
- [ ] `notifications.php` - Notification inbox
- [ ] `settings.php` - System configuration
- [ ] `activity_logs.php` - View audit trail
- [ ] `backup_restore.php` - Database backup/restore
- [ ] `profile.php` - Admin profile

### Staff Portal
- [ ] `orders.php` - View and update orders
- [ ] `products.php` - View inventory
- [ ] `order_details.php` - Order detail page
- [ ] `notifications.php` - Notification inbox
- [ ] `profile.php` - Staff profile

### Customer Portal
- [ ] `products.php` - Product catalog
- [ ] `orders.php` - Order history
- [ ] `upload_design.php` - Design file upload
- [ ] `payment_confirmation.php` - Payment proof upload
- [ ] `order_details.php` - Order tracking
- [ ] `profile.php` - Customer profile
- [ ] `notifications.php` - Notification inbox

### Public Pages
- [ ] `products.php` - Public product catalog
- [ ] `faq.php` - FAQ page
- [ ] `forgot-password.php` - Password reset request
- [ ] `reset-password.php` - Password reset form

## 📞 Support & Documentation

- **Authentication**: See `includes/auth.php` for all auth functions
- **Database**: See `includes/db.php` for database helpers
- **Utilities**: See `includes/functions.php` for formatting, notifications, file uploads, etc.
- **Styling**: Use Tailwind utility classes or the custom component classes defined in `input.css`

## 🎯 Quick Start Guide

1. **First Login**:
   - URL: http://localhost/printflow/public/login.php
   - Email: admin@printflow.com
   - Password: password

2. **Create Sample Products**:
   - Navigate to Admin Dashboard
   - Go to Products Management (create this page)
   - Add products: Tarpaulin, T-Shirt, Stickers, etc.

3. **Register as Customer**:
   - Logout from admin
   - Go to Register page
   - Create a customer account
   - Test the customer portal

4. **Test Order Flow**:
   - As customer: Browse products, place order, upload design
   - As admin: Approve design, update order status
   - Customer receives notifications

## 📝 License

This project is for educational/commercial use. Modify as needed for your printing shop.

## 🤝 Contributing

To extend this project:
1. Follow the existing code structure and patterns
2. Use prepared statements for all database queries
3. Always verify CSRF tokens on form submissions
4. Test responsive design on mobile devices
5. Update this README with new features


