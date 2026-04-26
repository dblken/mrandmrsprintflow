<?php
/**
 * Database Connection
 * PrintFlow - Production Ready (Hostinger + Local Safe)
 */

/**
 * Helper: read env from getenv / $_ENV / $_SERVER
 */
function printflow_env(string $name) {
    if (($v = getenv($name)) !== false) return $v;
    if (isset($_ENV[$name])) return (string) $_ENV[$name];
    if (isset($_SERVER[$name])) return (string) $_SERVER[$name];
    return false;
}

/**
 * Heuristic: determine if the current request expects JSON.
 * Used to avoid emitting HTML in API responses on DB failures.
 */
function printflow_expects_json(): bool {
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if ($uri !== '' && preg_match('~/(api_[^/]+\\.php)$~i', $uri)) return true;
    if (stripos($uri, '/api/') !== false) return true;
    $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
    if ($accept !== '' && stripos($accept, 'application/json') !== false) return true;
    $xrw = (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    if ($xrw !== '' && strtolower($xrw) === 'xmlhttprequest') return true;
    return false;
}

/**
 * Load .env file if exists
 */
function printflow_load_dotenv(string $path): array {
    if (!is_readable($path)) return [];

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    $env = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;

        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value, " \t\"'");
    }

    return $env;
}

/**
 * ==========================
 * DEFAULT CONFIG (ENVIRONMENT DETECTION)
 * ==========================
 */

// Detect if running on production
$is_production = (
    isset($_SERVER['HTTP_HOST']) && 
    (strpos($_SERVER['HTTP_HOST'], 'mrandmrsprintflow.com') !== false ||
     strpos($_SERVER['HTTP_HOST'], 'hostinger') !== false)
);

if ($is_production) {
    // Production (Hostinger)
    $db_config = [
        'host' => 'localhost',
        'user' => 'u618446170_user',
        'pass' => 'Mrandmrsprintflow@123',
        'name' => 'u618446170_printflow',
        'port' => 3306,
        'socket' => '',
    ];
} else {
    // Local Development (XAMPP)
    $db_config = [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '',
        'name' => 'printflow',
        'port' => 3306,
        'socket' => '',
    ];
}

/**
 * ==========================
 * LOAD .ENV (IF EXISTS)
 * ==========================
 */
$root = dirname(__DIR__);
$env_file = $root . '/.env';

$env = printflow_load_dotenv($env_file);

$map = [
    'host' => 'PRINTFLOW_DB_HOST',
    'user' => 'PRINTFLOW_DB_USER',
    'pass' => 'PRINTFLOW_DB_PASS',
    'name' => 'PRINTFLOW_DB_NAME',
    'port' => 'PRINTFLOW_DB_PORT',
    'socket' => 'PRINTFLOW_DB_SOCKET',
];

foreach ($map as $key => $envKey) {
    if (!empty($env[$envKey])) {
        $db_config[$key] = $env[$envKey];
    }
}

/**
 * ==========================
 * CONNECT DATABASE
 * ==========================
 */
$conn = @new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['pass'],
    $db_config['name'],
    (int)$db_config['port']
);

/**
 * ==========================
 * ERROR HANDLING
 * ==========================
 */
if ($conn->connect_error) {
    error_log('Database Connection Failed: ' . $conn->connect_error);

    if (printflow_expects_json()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed',
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    die(
        '<div style="font-family:sans-serif;padding:20px;">' .
        '<h2>Database Connection Failed</h2>' .
        '<p><strong>Error:</strong> ' . htmlspecialchars($conn->connect_error) . '</p>' .
        '<p><strong>Host:</strong> ' . htmlspecialchars($db_config['host']) . '</p>' .
        '<p><strong>Database:</strong> ' . htmlspecialchars($db_config['name']) . '</p>' .
        '<p><strong>User:</strong> ' . htmlspecialchars($db_config['user']) . '</p>' .
        '</div>'
    );
}

/**
 * ==========================
 * SET CHARSET
 * ==========================
 */
$conn->set_charset("utf8mb4");

/**
 * Keep MySQL NOW()/CURRENT_TIMESTAMP aligned with the app timezone.
 * Without this, some hosts default the DB session to UTC, which makes new
 * notifications look about 8 hours old when PHP formats them in Manila time.
 */
$conn->query("SET time_zone = '+08:00'");

/**
 * ==========================
 * HELPER FUNCTIONS
 * ==========================
 */

function db_query($sql, $types = '', $params = []) {
    global $conn;
    
    if (empty($types) || empty($params)) {
        $result = $conn->query($sql);
    } else {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("DB Prepare Error: " . $conn->error);
            return [];
        }
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            error_log("DB Execute Error: " . $stmt->error);
            $stmt->close();
            return [];
        }

        // Prefer mysqlnd-powered get_result(), but fall back if unavailable.
        $result = null;
        if (method_exists($stmt, 'get_result')) {
            $result = $stmt->get_result();
            if ($result === false) {
                error_log("DB get_result Error: " . $stmt->error);
                $stmt->close();
                return [];
            }
        } else {
            $meta = $stmt->result_metadata();
            if (!$meta) {
                $stmt->close();
                return [];
            }

            $fields = $meta->fetch_fields();
            $row = [];
            $bind = [];
            foreach ($fields as $field) {
                $row[$field->name] = null;
                $bind[] = &$row[$field->name];
            }

            // bind_result requires references.
            call_user_func_array([$stmt, 'bind_result'], $bind);

            $data = [];
            while ($stmt->fetch()) {
                // Copy since $row values are reused by reference each fetch.
                $data[] = array_map(static fn($v) => $v, $row);
            }

            $stmt->close();
            return $data;
        }
    }

    if (!$result) {
        error_log("DB Query Error: " . $conn->error);
        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
        return [];
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }

    return $data;
}

function db_execute($sql, $types = '', $params = []) {
    global $conn;

    if (empty($types) || empty($params)) {
        if (!$conn->query($sql)) {
            error_log("DB Execute Error: " . $conn->error);
            return false;
        }
        return $conn->insert_id ?: true;
    }
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("DB Prepare Error: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        error_log("DB Execute Error: " . $stmt->error);
        return false;
    }
    
    return $stmt->insert_id ?: true;
}

function db_escape($str) {
    global $conn;
    return $conn->real_escape_string($str);
}

function db_close() {
    global $conn;
    if ($conn) $conn->close();
}
