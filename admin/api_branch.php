<?php
/**
 * Admin Branch API
 * PrintFlow - Printing Shop PWA
 * Handles Create/Update operations for Branches & Auto-Inventory Initialization
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Access Control: ONLY Owner or Admin
require_role(['Owner', 'Admin']);

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Ensure payload exists
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

// Basic CSRF check (Assuming the dashboard provides `csrf_token` via session or header)
if (!isset($data['csrf_token']) || $data['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$action = $data['action'] ?? '';

try {
    global $conn;
    
    // ==========================================
    // ACTION: CREATE BRANCH
    // ==========================================
    if ($action === 'create') {
        $branch_name    = sanitize($data['branch_name'] ?? '');
        $address        = sanitize($data['address'] ?? '');
        $contact_number = sanitize($data['contact_number'] ?? '');
        
        // Auto-append "Branch" if missing
        if (!empty($branch_name) && !preg_match('/\bBranch$/i', $branch_name)) {
            $branch_name .= ' Branch';
        }
        
        if (empty($branch_name)) {
            echo json_encode(['success' => false, 'error' => 'Branch Name is required']);
            exit;
        }

        // Check Unique Name
        $existing = db_query("SELECT id FROM branches WHERE branch_name = ?", 's', [$branch_name]);
        if (!empty($existing)) {
            echo json_encode(['success' => false, 'error' => 'A Branch with that name already exists']);
            exit;
        }

        $conn->begin_transaction();
        
        // 1. Insert new Branch
        $new_branch_id = db_execute(
            "INSERT INTO branches (branch_name, address, contact_number, status, created_at) VALUES (?, ?, ?, 'Active', NOW())",
            'sss', [$branch_name, $address, $contact_number] // 'status' defaults to Active
        );
        
        if (!$new_branch_id) {
            throw new Exception("Failed to insert branch record.");
        }

        // 2. Initialize Inventory automatically mapping all native materials explicitly to this branch (qty 0)
        // INSERT IGNORE will safely skip constraints if somehow already exists
        $init_success = db_execute("
            INSERT IGNORE INTO branch_inventory (branch_id, material_id, stock_quantity, last_updated)
            SELECT ?, material_id, 0.00, NOW() 
            FROM materials
        ", 'i', [$new_branch_id]);
        
        if ($init_success === false) {
             throw new Exception("Failed to initialize inventory for new branch.");
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Branch '{$branch_name}' successfully created.", 'branch_id' => $new_branch_id]);
        exit;
    } 
    
    // ==========================================
    // ACTION: UPDATE BRANCH
    // ==========================================
    elseif ($action === 'update') {
        $branch_id      = (int)($data['branch_id'] ?? 0);
        $branch_name    = sanitize($data['branch_name'] ?? '');
        $address        = sanitize($data['address'] ?? '');
        $contact_number = sanitize($data['contact_number'] ?? '');
        $status         = sanitize($data['status'] ?? 'Active');
        
        // Auto-append "Branch" if missing
        if (!empty($branch_name) && !preg_match('/\bBranch$/i', $branch_name)) {
            $branch_name .= ' Branch';
        }
        
        if (!$branch_id) {
            echo json_encode(['success' => false, 'error' => 'Branch ID is required']);
            exit;
        }
        
        if (empty($branch_name)) {
            echo json_encode(['success' => false, 'error' => 'Branch Name cannot be empty']);
            exit;
        }
        
        // Ensure status is valid ENUM
        if (!in_array($status, ['Active', 'Inactive'])) {
            $status = 'Active';
        }

        // Verify the ID belongs to an existing branch
        $check = db_query("SELECT id FROM branches WHERE id = ?", 'i', [$branch_id]);
        if (empty($check)) {
            echo json_encode(['success' => false, 'error' => 'Branch not found']);
            exit;
        }
        
        // Make sure a name collision wasn't triggered by modifying the string
        $collision = db_query("SELECT id FROM branches WHERE branch_name = ? AND id != ?", 'si', [$branch_name, $branch_id]);
        if (!empty($collision)) {
            echo json_encode(['success' => false, 'error' => 'Another Branch is already using that Name']);
            exit;
        }
        
        $ok = db_execute(
            "UPDATE branches SET branch_name = ?, address = ?, contact_number = ?, status = ? WHERE id = ?",
            'ssssi',
            [$branch_name, $address, $contact_number, $status, $branch_id]
        );
        
        if ($ok) {
            echo json_encode(['success' => true, 'message' => "Branch successfully updated."]);
        } else {
            echo json_encode(['success' => false, 'error' => "No changes were made."]);
        }
        exit;

    } else {
        echo json_encode(['success' => false, 'error' => 'Unknown Action requested.']);
        exit;
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    error_log("API Branch Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An unexpected server error occurred.']);
}
