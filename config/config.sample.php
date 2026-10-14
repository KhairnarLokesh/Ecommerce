<?php
/**
 * Kalika Enterprises - Configuration Template
 * Copy this file to config.php and update credentials for your environment.
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
defined('DB_HOST') or define('DB_HOST', trim(getenv('DB_HOST') ?: 'localhost', " \t\n\r\0\x0B'\""));
defined('DB_PORT') or define('DB_PORT', trim(getenv('DB_PORT') ?: '3306', " \t\n\r\0\x0B'\""));
defined('DB_NAME') or define('DB_NAME', trim(getenv('DB_NAME') ?: 'kalika_enterprises', " \t\n\r\0\x0B'\""));
defined('DB_USER') or define('DB_USER', trim(getenv('DB_USER') ?: 'root', " \t\n\r\0\x0B'\""));
defined('DB_PASS') or define('DB_PASS', getenv('DB_PASS') !== false ? trim(getenv('DB_PASS'), " \t\n\r\0\x0B'\"") : '');
defined('DB_CHARSET') or define('DB_CHARSET', 'utf8mb4');

// Site URL & Paths
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
defined('ADMIN_LOGIN_PATH') or define('ADMIN_LOGIN_PATH', getenv('ADMIN_LOGIN_PATH') ?: '/admin/login.php');
defined('SESSION_NAME_USER') or define('SESSION_NAME_USER', 'kalika_user_sess');
defined('SESSION_NAME_ADMIN') or define('SESSION_NAME_ADMIN', 'kalika_admin_sess');
defined('SESSION_LIFETIME') or define('SESSION_LIFETIME', 86400 * 7); // 7 days
defined('ADMIN_SESSION_IDLE_TIMEOUT') or define('ADMIN_SESSION_IDLE_TIMEOUT', 1800); // 30-minute idle timeout

// Demo OTP Configuration (Disabled by default in production; enabled in dev/test)
defined('DEMO_OTP_ENABLED') or define('DEMO_OTP_ENABLED', (getenv('DEMO_OTP_ENABLED') !== false ? filter_var(getenv('DEMO_OTP_ENABLED'), FILTER_VALIDATE_BOOLEAN) : (APP_ENV !== 'production')));

// Transactional SMTP Email Delivery (for sending real OTPs and notifications)
defined('SMTP_ENABLED') or define('SMTP_ENABLED', (getenv('SMTP_ENABLED') !== false ? filter_var(getenv('SMTP_ENABLED'), FILTER_VALIDATE_BOOLEAN) : false));
defined('SMTP_HOST') or define('SMTP_HOST', getenv('SMTP_HOST') ?: ''); // e.g., 'smtp.gmail.com' or 'mail.kalikaenterprises.com'
defined('SMTP_PORT') or define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587)); // 587 (TLS) or 465 (SSL)
defined('SMTP_SECURE') or define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls'); // 'tls' or 'ssl'
defined('SMTP_USER') or define('SMTP_USER', getenv('SMTP_USER') ?: ''); // e.g., 'your-email@gmail.com'
defined('SMTP_PASS') or define('SMTP_PASS', getenv('SMTP_PASS') ?: ''); // e.g., Google App Password (16 characters)
defined('SMTP_FROM_EMAIL') or define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: '');
defined('SMTP_FROM_NAME') or define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Kalika Enterprises');

