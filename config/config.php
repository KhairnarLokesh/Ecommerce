<?php
/**
 * Kalika Enterprises - Active Environment Configuration
 * (This file is ignored by git for security)
 */

declare(strict_types=1);

// Application Environment: 'development' or 'production'
defined('APP_ENV') or define('APP_ENV', 'development');

// Error Reporting
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// Database Credentials (supports local defaults and cloud environment variables)
defined('DB_HOST') or define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
defined('DB_PORT') or define('DB_PORT', getenv('DB_PORT') ?: '3306');
defined('DB_NAME') or define('DB_NAME', getenv('DB_NAME') ?: 'kalika_enterprises');
defined('DB_USER') or define('DB_USER', getenv('DB_USER') ?: 'root');
defined('DB_PASS') or define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'root');
defined('DB_CHARSET') or define('DB_CHARSET', 'utf8mb4');

// Dynamic base path detection
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ? 'https://' : 'http://';
$host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$appBase = rtrim(preg_replace('#/(public|admin|api)(/.*)?$#', '', $scriptDir), '/');

defined('SITE_URL') or define('SITE_URL', $protocol . $host . $appBase);

if (getenv('VERCEL') || !empty($_SERVER['VERCEL']) || (isset($_SERVER['HTTP_HOST']) && str_contains($_SERVER['HTTP_HOST'], 'vercel.app'))) {
    defined('PUBLIC_URL') or define('PUBLIC_URL', SITE_URL);
} else {
    defined('PUBLIC_URL') or define('PUBLIC_URL', SITE_URL . '/public');
}

defined('ADMIN_URL') or define('ADMIN_URL', SITE_URL . '/admin');

// App Paths
defined('ROOT_PATH') or define('ROOT_PATH', dirname(__DIR__));
defined('PUBLIC_PATH') or define('PUBLIC_PATH', ROOT_PATH . '/public');
defined('UPLOAD_PATH') or define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');
defined('INCLUDES_PATH') or define('INCLUDES_PATH', ROOT_PATH . '/includes');

// Business Baseline Constants
defined('BUSINESS_NAME') or define('BUSINESS_NAME', 'Kalika Enterprises');
defined('BUSINESS_TAGLINE') or define('BUSINESS_TAGLINE', 'Wholesale Food & Beverage Distribution');
defined('BUSINESS_ADDRESS') or define('BUSINESS_ADDRESS', 'Shop No. 4, Raghukul Sankul, Jopul Road, Pimpalgaon (Baswant), Tal. Niphad, Dist. Nashik, Maharashtra – 422201');
defined('BUSINESS_EMAIL') or define('BUSINESS_EMAIL', 'deepakpawar4312@gmail.com');
defined('BUSINESS_AREAS') or define('BUSINESS_AREAS', 'Niphad, Dindori, Chandwad, Sinnar, Yeola');

// Hard Business Rules
defined('MIN_ORDER_VALUE') or define('MIN_ORDER_VALUE', 2000.00); // Minimum order Rs 2,000
defined('FREE_DELIVERY_THRESHOLD') or define('FREE_DELIVERY_THRESHOLD', 2000.00); // Delivery is always FREE on valid orders
defined('DEFAULT_PRICES_INCLUDE_GST') or define('DEFAULT_PRICES_INCLUDE_GST', 1); // 1 = Inclusive, 0 = Extra

// Session & Security Settings
defined('SESSION_NAME_USER') or define('SESSION_NAME_USER', 'kalika_user_sess');
defined('SESSION_NAME_ADMIN') or define('SESSION_NAME_ADMIN', 'kalika_admin_sess');
defined('SESSION_LIFETIME') or define('SESSION_LIFETIME', 86400 * 7); // 7 days
