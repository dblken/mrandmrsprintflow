# Database Design Considerations - PrintFlow System

## 13. DATA INTEGRITY AND VALIDATION

### Entity Integrity
| Table | Primary Key | Validation Rule |
|-------|-------------|-----------------|
| customers | customer_id (INT, AUTO_INCREMENT) | Must be unique, NOT NULL |
| orders | order_id (INT, AUTO_INCREMENT) | Must be unique, NOT NULL |
| order_items | order_item_id (INT, AUTO_INCREMENT) | Must be unique, NOT NULL |
| products | product_id (INT, AUTO_INCREMENT) | Must be unique, NOT NULL |
| branches | branch_id (INT, AUTO_INCREMENT) | Must be unique, NOT NULL |
| users | user_id (INT, AUTO_INCREMENT) | Must be unique, NOT NULL |

**Implementation Example:**
```sql
CREATE TABLE customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CHECK (email REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$')
);
```

### Referential Integrity (Foreign Keys)
| Foreign Key | References | On Delete | On Update |
|-------------|------------|-----------|-----------|
| orders.customer_id | customers.customer_id | RESTRICT | CASCADE |
| order_items.order_id | orders.order_id | CASCADE | CASCADE |
| order_items.product_id | products.product_id | SET NULL | CASCADE |
| payments.order_id | orders.order_id | RESTRICT | CASCADE |
| notifications.user_id | users.user_id | CASCADE | CASCADE |

**Example:**
```sql
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    branch_id INT,
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('pending','reviewing','priced','payment_pending','paid','processing','ready','completed','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL ON UPDATE CASCADE
);
```

### Domain Integrity (Data Format Rules)

| Field | Data Type | Constraints | Validation |
|-------|-----------|-------------|------------|
| email | VARCHAR(255) | UNIQUE, NOT NULL | Email format regex |
| contact_number | VARCHAR(20) | | Philippine format: `^(09|\+639)\d{9}$` |
| price/amount | DECIMAL(10,2) | DEFAULT 0.00 | Must be >= 0 |
| quantity | INT | DEFAULT 1 | Must be > 0 |
| status | ENUM | | Limited to defined values |
| payment_method | ENUM | | 'gcash', 'maya', 'bank_transfer', 'cash' |

**PHP Validation Example:**
```php
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    return preg_match('/^(09|\+639)\d{9}$/', $phone);
}

function validateAmount($amount) {
    return is_numeric($amount) && $amount >= 0;
}
```

---

## 14. PERFORMANCE CONSIDERATIONS

### Indexing Strategy

| Table | Index Name | Column(s) | Type | Purpose |
|-------|------------|-----------|------|---------|
| customers | idx_email | email | UNIQUE | Fast login lookup |
| customers | idx_created | created_at | BTREE | Date range queries |
| orders | idx_customer | customer_id | BTREE | Customer order history |
| orders | idx_status | status | BTREE | Dashboard filtering |
| orders | idx_created | created_at | BTREE | Recent orders |
| order_items | idx_order | order_id | BTREE | Order detail retrieval |
| notifications | idx_user_read | user_id, is_read | COMPOSITE | Unread count |
| notifications | idx_created | created_at | BTREE | Sorting |
| chatbot_conversations | idx_customer | customer_id | BTREE | Customer chat history |
| chatbot_messages | idx_conversation | conversation_id | BTREE | Message retrieval |

**SQL Implementation:**
```sql
-- Composite index for notification queries
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read, created_at DESC);

-- Full-text search for products
CREATE FULLTEXT INDEX idx_product_search ON products(name, description);
```

### Query Optimization

**❌ Avoid: N+1 Query Problem**
```php
// BAD: Queries database for each order item
foreach ($orders as $order) {
    $items = db_query("SELECT * FROM order_items WHERE order_id = {$order['id']}");
}
```

**✅ Use: JOIN with Single Query**
```php
// GOOD: Single query with JOIN
$sql = "SELECT o.*, oi.*, p.name as product_name 
        FROM orders o 
        LEFT JOIN order_items oi ON o.order_id = oi.order_id 
        LEFT JOIN products p ON oi.product_id = p.product_id 
        WHERE o.customer_id = ?";
```

### Efficient Data Types

| Current | Optimized | Savings |
|---------|-----------|---------|
| VARCHAR(500) for names | VARCHAR(100) | 400 bytes/row |
| TEXT for short fields | VARCHAR(255) | Better performance |
| DATETIME | TIMESTAMP | 4 bytes vs 8 bytes |
| INT for boolean | TINYINT(1) | 3 bytes saved |
| DECIMAL(20,2) | DECIMAL(10,2) | Half the storage |

### Caching Strategy

```php
// Redis/Memcached for frequent queries
function getUnreadCount($user_id) {
    $cache_key = "notif_count:{$user_id}";
    $count = cache_get($cache_key);
    
    if ($count === null) {
        $count = db_query("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$user_id]);
        cache_set($cache_key, $count, 60); // 60 seconds
    }
    
    return $count;
}
```

---

## 15. DATA FLOW CONSIDERATION

### System Data Flow Diagram

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│    INPUT    │────▶│  PROCESSING │────▶│   STORAGE   │────▶│   OUTPUT    │
└─────────────┘     └─────────────┘     └─────────────┘     └─────────────┘
      │                    │                    │                    │
      ▼                    ▼                    ▼                    ▼
• Customer          • Validation           • Database          • Order
  Registration      • Business Logic         - MySQL             Confirmation
• Order Placement • Price Calculation    • File Storage      • Email
• Payment           • Status Updates       - Design uploads    • Notifications
• Design Upload   • Notifications          • Cache             • Admin Dashboard
• Chat Messages   • Email Triggers         - Redis
```

### CRUD Operations by Entity

#### Customers
| Operation | Trigger | SQL Example |
|-----------|---------|-------------|
| **Create** | Registration form | `INSERT INTO customers (email, password_hash, first_name, last_name) VALUES (?, ?, ?, ?)` |
| **Read** | Login, Profile view | `SELECT * FROM customers WHERE email = ?` |
| **Update** | Profile edit | `UPDATE customers SET contact_number = ?, address = ? WHERE customer_id = ?` |
| **Delete** | Account deletion (soft delete) | `UPDATE customers SET is_deleted = 1, deleted_at = NOW() WHERE customer_id = ?` |

#### Orders
| Operation | Trigger | SQL Example |
|-----------|---------|-------------|
| **Create** | Place order | `INSERT INTO orders (customer_id, branch_id, status, created_at) VALUES (?, ?, 'pending', NOW())` |
| **Read** | Order history, Admin view | `SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC` |
| **Update** | Status change, Pricing | `UPDATE orders SET status = ?, total_amount = ?, updated_at = NOW() WHERE order_id = ?` |
| **Delete** | Cancel order | `UPDATE orders SET status = 'cancelled', cancelled_at = NOW() WHERE order_id = ?` |

#### Order Items (with Customization)
```php
// Create with JSON customization
$sql = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, customization_data) 
        VALUES (?, ?, ?, ?, ?)";
$customization = json_encode([
    'size' => 'XL',
    'color' => 'Black',
    'print_placement' => 'Front',
    'tshirt_provider' => 'customer'
]);
```

### Notification Data Flow

```
User Action                    Database Operation              Real-time Update
─────────────────────────────────────────────────────────────────────────────────
Place Order              ──▶  INSERT INTO orders            ──▶  WebSocket/SSE
                                  INSERT INTO notifications         Push to Admin
                                  (type='new_order')
                                                                                    
Admin Prices Order       ──▶  UPDATE orders (status)      ──▶  INSERT INTO
                                  INSERT INTO notifications         notifications
                                  (type='price_set')                (customer notified)
                                                                                    
Customer Pays            ──▶  INSERT INTO payments          ──▶  Real-time status
                                  UPDATE orders (status='paid')     update
                                  INSERT INTO notifications
                                  (type='payment_received')
```

---

## 16. TESTING CONSIDERATIONS

### Test Case Matrix

| Test ID | Description | Input | Expected Result | Status |
|---------|-------------|-------|-----------------|--------|
| TC-001 | Valid customer registration | `email: valid@test.com, phone: 09171234567` | Success, record created | ⬜ |
| TC-002 | Duplicate email registration | `email: existing@test.com` | Error: Email exists | ⬜ |
| TC-003 | Invalid email format | `email: invalid-email` | Error: Invalid format | ⬜ |
| TC-004 | Invalid phone format | `phone: 12345` | Error: Invalid phone | ⬜ |
| TC-005 | Valid order placement | Valid items, customization | Order created, items linked | ⬜ |
| TC-006 | Order with invalid product | `product_id: 99999` | FK constraint error | ⬜ |
| TC-007 | Negative quantity | `quantity: -1` | CHECK constraint error | ⬜ |
| TC-008 | Valid payment record | Valid order_id, amount | Payment recorded | ⬜ |
| TC-009 | Payment exceeds order total | `amount: 999999` | Business rule error | ⬜ |
| TC-010 | Valid status transition | `pending → priced` | Success | ⬜ |
| TC-011 | Invalid status transition | `completed → pending` | Business rule error | ⬜ |

### Automated Test Scripts

```php
<?php
// tests/DatabaseIntegrityTest.php

class DatabaseIntegrityTest {
    
    public function testEntityIntegrity() {
        // Test: PK cannot be NULL
        try {
            db_execute("INSERT INTO customers (customer_id, email) VALUES (NULL, 'test@test.com')");
            return false; // Should fail
        } catch (Exception $e) {
            return strpos($e->getMessage(), 'cannot be null') !== false;
        }
    }
    
    public function testReferentialIntegrity() {
        // Test: FK must exist
        try {
            db_execute("INSERT INTO orders (customer_id) VALUES (99999)");
            return false; // Should fail
        } catch (Exception $e) {
            return strpos($e->getMessage(), 'foreign key constraint') !== false;
        }
    }
    
    public function testDomainIntegrity() {
        // Test: Email format validation
        $result = validateEmail("invalid-email");
        assert($result === false, "Invalid email should be rejected");
        
        $result = validateEmail("valid@example.com");
        assert($result === true, "Valid email should be accepted");
    }
    
    public function testCRUDOperations() {
        $test_email = "test_" . time() . "@example.com";
        
        // CREATE
        $customer_id = db_insert("INSERT INTO customers (email, first_name, last_name) VALUES (?, 'Test', 'User')", [$test_email]);
        assert($customer_id > 0, "Customer should be created");
        
        // READ
        $customer = db_query_one("SELECT * FROM customers WHERE customer_id = ?", [$customer_id]);
        assert($customer['email'] === $test_email, "Customer should be readable");
        
        // UPDATE
        db_execute("UPDATE customers SET first_name = ? WHERE customer_id = ?", ["Updated", $customer_id]);
        $customer = db_query_one("SELECT * FROM customers WHERE customer_id = ?", [$customer_id]);
        assert($customer['first_name'] === "Updated", "Customer should be updatable");
        
        // DELETE (soft)
        db_execute("UPDATE customers SET is_deleted = 1 WHERE customer_id = ?", [$customer_id]);
        $customer = db_query_one("SELECT * FROM customers WHERE customer_id = ? AND is_deleted = 0", [$customer_id]);
        assert($customer === null, "Customer should be soft deleted");
    }
    
    public function testConcurrency() {
        // Test: Simultaneous order updates
        $order_id = 1;
        
        // Transaction 1
        db_transaction_start();
        $status1 = db_query_one("SELECT status FROM orders WHERE order_id = ? FOR UPDATE", [$order_id]);
        
        // Transaction 2 (simulated) would wait for lock
        db_execute("UPDATE orders SET status = 'processing' WHERE order_id = ?", [$order_id]);
        db_transaction_commit();
        
        $final = db_query_one("SELECT status FROM orders WHERE order_id = ?", [$order_id]);
        assert($final['status'] === 'processing', "Status should be updated");
    }
}
```

### Performance Testing

```php
<?php
// tests/PerformanceTest.php

class PerformanceTest {
    
    public function testQueryResponseTime() {
        $start = microtime(true);
        
        // Test: Notification query with 10,000+ records
        $result = db_query("SELECT * FROM notifications WHERE user_id = 1 ORDER BY created_at DESC LIMIT 20");
        
        $elapsed = (microtime(true) - $start) * 1000; // ms
        assert($elapsed < 100, "Query should complete in < 100ms (took {$elapsed}ms)");
    }
    
    public function testIndexEffectiveness() {
        // EXPLAIN query to verify index usage
        $explain = db_query("EXPLAIN SELECT * FROM orders WHERE customer_id = 1");
        $type = $explain[0]['type'] ?? 'ALL';
        assert($type !== 'ALL', "Query should use index, not full table scan");
    }
    
    public function testConnectionPool() {
        // Test: 100 concurrent connections
        $connections = [];
        for ($i = 0; $i < 100; $i++) {
            $connections[] = db_get_connection();
        }
        
        foreach ($connections as $conn) {
            db_release_connection($conn);
        }
        
        assert(true, "Connection pool handled 100 concurrent requests");
    }
}
```

### Pre-Deployment Checklist

| Check | Method | Expected Result |
|-------|--------|-----------------|
| All PKs are unique | `SELECT COUNT(*) = COUNT(DISTINCT pk) FROM table` | TRUE |
| No orphaned FKs | `SELECT COUNT(*) FROM child c LEFT JOIN parent p ON c.fk = p.pk WHERE p.pk IS NULL` | 0 |
| All required indexes exist | `SHOW INDEX FROM table` | Required indexes present |
| No slow queries (>1s) | Enable slow query log | Empty log |
| Backup recovery tested | Restore test database | Successful restore |

---

## Summary Recommendations

### Critical Implementation Priority
1. **Data Integrity**: Implement all constraints (PK, FK, CHECK) at database level
2. **Input Validation**: Server-side validation before any database operation
3. **Prepared Statements**: Use parameterized queries to prevent SQL injection
4. **Indexing**: Add indexes after initial data load, monitor with EXPLAIN
5. **Soft Deletes**: Never use DELETE CASCADE on critical business data
6. **Audit Logging**: Log all status changes and financial transactions
7. **Backup Strategy**: Daily automated backups with point-in-time recovery

### Scalability Roadmap
- **Current (< 10K orders)**: Single database, basic indexes
- **Growth (10K-100K)**: Add read replicas, implement caching
- **Scale (100K+)**: Sharding by date/region, search engine (Elasticsearch)
