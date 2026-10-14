<?php
/**
 * Kalika Enterprises - Core Helper Functions
 * Escaping, currency formatting, flash messages, redirects, and utilities.
 */

declare(strict_types=1); 

/**
 * Escape output for safe HTML embedding (XSS defense).
 *
 * @param mixed $value String or printable value to escape
 * @return string Escaped string
 */
function e(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format monetary amount in Indian Rupees (INR).
 *
 * @param float|int|string|null $amount Numeric amount
 * @param bool $showDecimals Whether to append .00 if cents are zero
 * @return string Formatted amount (e.g. "₹2,000" or "₹2,000.50")
 */
function format_currency(mixed $amount, bool $showDecimals = true): string
{
    $num = (float)($amount ?? 0);
    if ($showDecimals) {
        return '₹' . number_format($num, 2, '.', ',');
    }
    return '₹' . number_format($num, 0, '.', ',');
}

/**
 * Perform a safe HTTP redirect and terminate script execution.
 *
 * @param string $url Destination URL or relative path
 * @param int $statusCode HTTP status code (default 302)
 */
function redirect(string $url, int $statusCode = 302): void
{
    // Ensure session is written and unlocked before redirecting
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }

    if (!headers_sent()) {
        http_response_code($statusCode);
        header("Location: " . $url, true, $statusCode);
    }

    // Universal fallback: Meta refresh + JavaScript replace guarantees page moves in all serverless environments
    echo "<!DOCTYPE html>\n";
    echo "<html>\n<head>\n";
    echo "<meta charset=\"UTF-8\">\n";
    echo "<meta http-equiv=\"refresh\" content=\"0;url=" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "\">\n";
    echo "<script>window.location.replace(" . json_encode($url) . ");</script>\n";
    echo "</head>\n<body>\n";
    echo "<p>Redirecting to <a href=\"" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "\">" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "</a>...</p>\n";
    echo "<script>window.location.replace(" . json_encode($url) . ");</script>\n";
    echo "</body>\n</html>";
    exit;
}

/**
 * Ensure session is securely started using unified session configuration.
 */
function ensure_session_started(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        if (!function_exists('start_secure_session') && file_exists(__DIR__ . '/auth.php')) {
            require_once __DIR__ . '/auth.php';
        }
        if (function_exists('start_secure_session')) {
            start_secure_session(defined('SESSION_NAME_USER') ? SESSION_NAME_USER : 'kalika_user_sess');
        } else {
            session_start();
        }
    }
}

/**
 * Set a session flash message.
 *
 * @param string $type Message type ('success', 'error', 'warning', 'info')
 * @param string $message Flash message text
 */
function set_flash(string $type, string $message): void
{
    ensure_session_started();
    $_SESSION['_flash_messages'][] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Retrieve and clear all queued flash messages.
 *
 * @return array<int, array{type: string, message: string}>
 */
function get_flash_messages(): array
{
    ensure_session_started();
    $messages = $_SESSION['_flash_messages'] ?? [];
    unset($_SESSION['_flash_messages']);
    return $messages;
}

/**
 * Check if there are active flash messages.
 *
 * @return bool
 */
function has_flash_messages(): bool
{
    return !empty($_SESSION['_flash_messages']);
}

/**
 * Render flash messages as HTML alerts.
 *
 * @return string HTML alert tags
 */
function render_flash_messages(): string
{
    $messages = get_flash_messages();
    if (empty($messages)) {
        return '';
    }

    $html = '<div class="flash-messages-container" id="flash-container">';
    foreach ($messages as $msg) {
        $type = e($msg['type']);
        $text = e($msg['message']);
        $html .= sprintf(
            '<div class="alert alert-%s" role="alert"><span>%s</span><button type="button" class="alert-close" onclick="this.parentElement.remove()" aria-label="Close">&times;</button></div>',
            $type,
            $text
        );
    }
    $html .= '</div>';
    return $html;
}

/**
 * Resolve application relative URL to full public URL.
 *
 * @param string $path Path relative to public root
 * @return string Complete URL
 */
function public_url(string $path = ''): string
{
    $base = defined('PUBLIC_URL') ? PUBLIC_URL : '/public';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * Resolve administrative login URL (configurable path).
 *
 * @return string Complete admin login URL
 */
function admin_login_url(): string
{
    $path = defined('ADMIN_LOGIN_PATH') ? ADMIN_LOGIN_PATH : '/admin/login.php';
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    $base = defined('SITE_URL') ? SITE_URL : '';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * Resolve administrative URL.
 *
 * @param string $path Path relative to admin root
 * @return string Complete admin URL
 */
function admin_url(string $path = ''): string
{
    if ($path === 'login.php' || $path === '/login.php') {
        return admin_login_url();
    }
    $base = defined('ADMIN_URL') ? ADMIN_URL : '/admin';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * Get total quantity of items currently in cart (guest session or DB).
 *
 * @return int Total item quantity
 */
function get_cart_count(): int
{
    ensure_session_started();

    // Authenticated user cart count check (if DB is available and user is logged in)
    if (!empty($_SESSION['user_id']) && function_exists('get_db_connection')) {
        $pdo = get_db_connection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE user_id = ?');
                $stmt->execute([$_SESSION['user_id']]);
                return (int)$stmt->fetchColumn();
            } catch (Exception $e) {
                // Table might not exist yet during Phase 1
            }
        }
    }

    // Fallback to session guest cart or cookie
    $cart = $_SESSION['cart'] ?? [];
    if (empty($cart) && !empty($_COOKIE['kalika_cart'])) {
        $c = json_decode($_COOKIE['kalika_cart'], true);
        if (is_array($c)) {
            $cart = $c;
        }
    }

    $total = 0;
    foreach ($cart as $item) {
        $total += is_array($item) ? (int)($item['quantity'] ?? 0) : (int)$item;
    }
    return $total;
}

/**
 * Look up a setting key from database, with fallback to constant or default.
 *
 * @param string $key Setting key
 * @param string $default Default value if setting not found
 * @return string Setting value
 */
function get_setting(string $key, string $default = ''): string
{
    static $settingsCache = null;

    if ($settingsCache === null && function_exists('get_db_connection')) {
        $pdo = get_db_connection();
        if ($pdo) {
            try {
                $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
                $settingsCache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
            } catch (Exception $e) {
                $settingsCache = [];
            }
        } else {
            $settingsCache = [];
        }
    }

    if (isset($settingsCache[$key])) {
        return (string)$settingsCache[$key];
    }

    // Default constants mapping
    $defaults = [
        'business_name'      => defined('BUSINESS_NAME') ? BUSINESS_NAME : 'Kalika Enterprises',
        'business_address'   => defined('BUSINESS_ADDRESS') ? BUSINESS_ADDRESS : '',
        'business_email'     => defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : 'deepakpawar4312@gmail.com',
        'service_areas'      => defined('BUSINESS_AREAS') ? BUSINESS_AREAS : '',
        'min_order_value'    => (string)(defined('MIN_ORDER_VALUE') ? MIN_ORDER_VALUE : '2000'),
        'prices_include_gst' => (string)(defined('DEFAULT_PRICES_INCLUDE_GST') ? DEFAULT_PRICES_INCLUDE_GST : '1'),
    ];

    return $defaults[$key] ?? $default;
}

/**
 * Load static fallback catalog when database is not connected.
 * Ensures the storefront, brand filters, and products display perfectly.
 *
 * @return array{brands: array, products: array}
 */
function get_catalog_fallback(): array
{
    static $fallback = null;
    if ($fallback !== null) {
        return $fallback;
    }
    $file = dirname(__DIR__) . '/data/catalog_fallback.json';
    if (file_exists($file)) {
        $data = json_decode((string)file_get_contents($file), true);
        if (is_array($data) && !empty($data['products'])) {
            $fallback = $data;
            return $fallback;
        }
    }
    return ['brands' => [], 'products' => []];
}

/**
 * Retrieve client IP address safely, respecting reverse proxies and cloud platforms (e.g. Vercel, Cloudflare).
 *
 * @return string Client IP address
 */
function get_client_ip(): string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ipList = explode(',', (string)$_SERVER[$header]);
            $ip = trim($ipList[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '127.0.0.1';
}

