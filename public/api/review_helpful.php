<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

function review_helpful_json(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function review_helpful_ensure_schema(): array {
    $table_ready = db_execute("CREATE TABLE IF NOT EXISTS review_helpful (
        id INT AUTO_INCREMENT PRIMARY KEY,
        review_id INT NOT NULL,
        user_id INT NOT NULL,
        customer_id INT NULL,
        user_type VARCHAR(20) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_review_actor (review_id, user_id, user_type, customer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!$table_ready) {
        throw new RuntimeException('Could not prepare review helpful table.');
    }

    $columns = array_flip(array_column(db_query("SHOW COLUMNS FROM review_helpful") ?: [], 'Field'));
    if (!isset($columns['customer_id'])) {
        db_execute("ALTER TABLE review_helpful ADD COLUMN customer_id INT NULL AFTER user_id");
        $columns['customer_id'] = true;
    }
    if (!isset($columns['user_type'])) {
        db_execute("ALTER TABLE review_helpful ADD COLUMN user_type VARCHAR(20) NULL AFTER customer_id");
        $columns['user_type'] = true;
    }

    $indexes = array_column(db_query("SHOW INDEX FROM review_helpful") ?: [], 'Key_name');
    if (in_array('uq_review_user', $indexes, true)) {
        db_execute("ALTER TABLE review_helpful DROP INDEX uq_review_user");
    }
    if (!in_array('uq_review_actor', $indexes, true)) {
        db_execute("ALTER TABLE review_helpful ADD UNIQUE KEY uq_review_actor (review_id, user_id, user_type, customer_id)");
    }

    return $columns;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        review_helpful_json(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    if (!is_logged_in()) {
        review_helpful_json(['success' => false, 'error' => 'Login required'], 401);
    }

    $review_id = (int)($_POST['review_id'] ?? 0);
    if ($review_id < 1) {
        review_helpful_json(['success' => false, 'error' => 'Invalid review'], 422);
    }

    $helpful_columns = review_helpful_ensure_schema();

    $review_exists = db_query("SELECT id FROM reviews WHERE id = ? LIMIT 1", 'i', [$review_id]);
    if (empty($review_exists)) {
        review_helpful_json(['success' => false, 'error' => 'Review not found'], 404);
    }

    $user_id = (int)get_user_id();
    $user_type = (string)($_SESSION['user_type'] ?? '');
    $customer_id = $user_type === 'Customer' ? $user_id : 0;

    if ($customer_id > 0 && isset($helpful_columns['customer_id'], $helpful_columns['user_type'])) {
        $existing = db_query(
            "SELECT id
             FROM review_helpful
             WHERE review_id = ?
              AND (
                    (customer_id = ? AND COALESCE(user_type, 'Customer') = 'Customer')
                    OR (customer_id IS NULL AND user_id = ? AND COALESCE(user_type, 'Customer') = 'Customer')
               )
             LIMIT 1",
            'iii',
            [$review_id, $customer_id, $user_id]
        );
    } else {
        $existing = db_query(
            "SELECT id FROM review_helpful
             WHERE review_id = ?
             AND user_id = ?
             AND COALESCE(user_type, 'User') <> 'Customer'
             LIMIT 1",
            'ii',
            [$review_id, $user_id]
        );
    }

    if (!empty($existing)) {
        $ok = true;
        $voted = true;
    } else {
        if ($customer_id > 0 && isset($helpful_columns['customer_id'], $helpful_columns['user_type'])) {
            $ok = db_execute(
                "INSERT INTO review_helpful (review_id, user_id, customer_id, user_type)
                 VALUES (?, ?, ?, 'Customer')
                 ON DUPLICATE KEY UPDATE
                    customer_id = VALUES(customer_id),
                    user_type = VALUES(user_type)",
                'iii',
                [$review_id, $user_id, $customer_id]
            );
            $voted = true;
        } else {
            $ok = db_execute("INSERT INTO review_helpful (review_id, user_id, user_type) VALUES (?, ?, ?)", 'iis', [$review_id, $user_id, $user_type !== '' ? $user_type : 'User']);
            $voted = true;
        }
    }
    if (!$ok) {
        throw new RuntimeException('Could not update helpful vote.');
    }

    $count = db_query("SELECT COUNT(*) as cnt FROM review_helpful WHERE review_id = ?", 'i', [$review_id]);
    $total = (int)($count[0]['cnt'] ?? 0);

    review_helpful_json(['success' => true, 'voted' => $voted, 'count' => $total]);
} catch (Throwable $e) {
    error_log('review_helpful failed: ' . $e->getMessage());
    review_helpful_json(['success' => false, 'error' => 'Could not update helpful vote.'], 500);
}
