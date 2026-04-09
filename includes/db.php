<?php
/**
 * Database Connection
 * PrintFlow - Production Ready (Hostinger + Local Safe)
 */

/**
 * Helper: read env from getenv / $_ENV / $_SERVER
 */
function printflow_env(string $name): string|false {
    if (($v = getenv($name)) !== false) return $v;
    if (isset($_ENV[$name])) return (string) $_ENV[$name];
    if (isset($_SERVER[$name])) return (string) $_SERVER[$name];
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
        $stmt->execute();
        $result = $stmt->get_result();
    }

    if (!$result) {
        error_log("DB Query Error: " . $conn->error);
        return [];
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
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