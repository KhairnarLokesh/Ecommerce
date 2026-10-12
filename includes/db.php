<?php
/**
 * Kalika Enterprises - Database Connection Helper
 * Uses PDO with prepared statements, utf8mb4, and exception mode.
 */

declare(strict_types=1);

// Load configuration if not already loaded
if (!defined('DB_HOST')) {
    $configFile = __DIR__ . '/../config/config.php';
    if (file_exists($configFile)) {
        require_once $configFile;
    } else {
        require_once __DIR__ . '/../config/config.sample.php';
    }
}

/**
 * Get or initialize the PDO database connection singleton.
 *
 * @param bool $throwOnError Whether to throw PDOException on failure (default: false for graceful degradation)
 * @return PDO|null Returns PDO instance on success, or null on failure
 */
function get_db_connection(bool $throwOnError = false): ?PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
    ];

    if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    // Enable SSL transport for cloud hosts (e.g. TiDB Cloud / Aiven)
    if (defined('DB_HOST') && (str_contains(DB_HOST, 'tidbcloud.com') || str_contains(DB_HOST, 'aivencloud.com'))) {
        $caPaths = [
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/ssl/cert.pem',
        ];
        foreach ($caPaths as $ca) {
            if (file_exists($ca)) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
                break;
            }
        }
        if (!isset($options[PDO::MYSQL_ATTR_SSL_CA]) && defined('PDO::MYSQL_ATTR_SSL_CA')) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = true;
        }
    }

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        if ($throwOnError) {
            throw $e;
        }

        // Log error
        error_log("Database connection error: " . $e->getMessage());

        $userPreview = substr(DB_USER, 0, 8) . '... (len: ' . strlen(DB_USER) . ')';
        $GLOBALS['db_connection_error'] = $e->getMessage() . " [Host: " . DB_HOST . ", Port: " . DB_PORT . ", User: " . $userPreview . ", PassLen: " . strlen(DB_PASS) . ", DB: " . DB_NAME . "]";
        return null;
    }
}

// Global reference for procedural scripts
$pdo = get_db_connection(false);
