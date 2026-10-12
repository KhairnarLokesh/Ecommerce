<?php
/**
 * Kalika Enterprises - Authentication & Session Management
 * Handles secure session configuration, customer & admin authentication states, and access gates.
 */

declare(strict_types=1);

/**
 * Send standard HTTP security headers (nosniff, SAMEORIGIN, CSP, Referrer-Policy).
 */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com;");
}

/**
 * Configure and start session with secure cookie parameters and idle timeout.
 *
 * @param string $sessionName Custom session name (distinguishes user vs admin sessions)
 */
function start_secure_session(string $sessionName = 'kalika_sess'): void
{
    send_security_headers();

    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    // Secure cookie parameters
    session_set_cookie_params([
        'lifetime' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 604800, // 7 days
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_name($sessionName);
    session_start();

    // Idle timeout check: 2 hours (7200 seconds)
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
        session_unset();
        session_destroy();
        session_name($sessionName);
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

// Automatically start user session by default for public requests
if (session_status() === PHP_SESSION_NONE) {
    start_secure_session(defined('SESSION_NAME_USER') ? SESSION_NAME_USER : 'kalika_user_sess');
}

/**
 * Check if a customer is currently authenticated.
 *
 * @return bool
 */
/**
 * Sign user payload with HMAC-SHA256 for stateless serverless auth continuity.
 */
function auth_sign_user(array $user): string
{
    $secret = defined('APP_KEY') ? APP_KEY : 'kalika_auth_secret_key_892348912';
    $json = base64_encode(json_encode([
        'id'      => $user['id'],
        'name'    => $user['name'],
        'mobile'  => $user['mobile'],
        'email'   => $user['email'] ?? '',
        'address' => $user['address'] ?? '',
        'time'    => time(),
    ]));
    $sig = hash_hmac('sha256', $json, $secret);
    return $json . '.' . $sig;
}

/**
 * Verify and decode HMAC-signed user cookie.
 */
function auth_verify_user(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return null;
    }
    [$json, $sig] = $parts;
    $secret = defined('APP_KEY') ? APP_KEY : 'kalika_auth_secret_key_892348912';
    if (!hash_equals(hash_hmac('sha256', $json, $secret), $sig)) {
        return null;
    }
    $data = json_decode(base64_decode($json), true);
    if (!is_array($data) || empty($data['id'])) {
        return null;
    }
    if (!empty($data['time']) && (time() - $data['time'] > 86400 * 7)) {
        return null;
    }
    return $data;
}

/**
 * Sign password reset authorization token (valid for 15 minutes).
 */
function auth_sign_reset(int $userId): string
{
    $secret = defined('APP_KEY') ? APP_KEY : 'kalika_auth_secret_key_892348912';
    $json = base64_encode(json_encode([
        'user_id' => $userId,
        'time'    => time(),
    ]));
    $sig = hash_hmac('sha256', $json, $secret);
    return $json . '.' . $sig;
}

/**
 * Verify HMAC-signed password reset token.
 */
function auth_verify_reset(string $token): ?int
{
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return null;
    }
    [$json, $sig] = $parts;
    $secret = defined('APP_KEY') ? APP_KEY : 'kalika_auth_secret_key_892348912';
    if (!hash_equals(hash_hmac('sha256', $json, $secret), $sig)) {
        return null;
    }
    $data = json_decode(base64_decode($json), true);
    if (!is_array($data) || empty($data['user_id'])) {
        return null;
    }
    // 15-minute validity window for resetting password
    if (!empty($data['time']) && (time() - $data['time'] > 900)) {
        return null;
    }
    return (int)$data['user_id'];
}

/**
 * Check if a customer is currently authenticated.
 *
 * @return bool
 */
function is_logged_in(): bool
{
    if (!empty($_SESSION['user_id'])) {
        return true;
    }

    if (!empty($_COOKIE['kalika_auth'])) {
        $recovered = auth_verify_user($_COOKIE['kalika_auth']);
        if ($recovered) {
            $_SESSION['user_id']      = $recovered['id'];
            $_SESSION['user_name']    = $recovered['name'];
            $_SESSION['user_mobile']  = $recovered['mobile'];
            $_SESSION['user_email']   = $recovered['email'] ?? '';
            $_SESSION['user_address'] = $recovered['address'] ?? '';
            return true;
        }
    }

    return false;
}

/**
 * Get current authenticated customer data.
 *
 * @return array<string, mixed>|null
 */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'      => $_SESSION['user_id'] ?? null,
        'name'    => $_SESSION['user_name'] ?? 'Customer',
        'mobile'  => $_SESSION['user_mobile'] ?? '',
        'email'   => $_SESSION['user_email'] ?? '',
        'address' => $_SESSION['user_address'] ?? '',
    ];
}

/**
 * Authenticate customer into session and regenerate session ID to prevent fixation.
 *
 * @param array<string, mixed> $user User record from database
 */
function login_user(array $user): void
{
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id']      = $user['id'];
    $_SESSION['user_name']    = $user['name'];
    $_SESSION['user_mobile']  = $user['mobile'];
    $_SESSION['user_email']   = $user['email'] ?? '';
    $_SESSION['user_address'] = $user['address'] ?? '';

    $token = auth_sign_user($user);
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if (!headers_sent()) {
        setcookie('kalika_auth', $token, [
            'expires'  => time() + (86400 * 7),
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    $_COOKIE['kalika_auth'] = $token;
}

/**
 * Log out customer and clear user session data.
 */
function logout_user(): void
{
    unset(
        $_SESSION['user_id'],
        $_SESSION['user_name'],
        $_SESSION['user_mobile'],
        $_SESSION['user_email'],
        $_SESSION['user_address'],
        $_SESSION['cart']
    );
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if (!headers_sent()) {
        setcookie('kalika_auth', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    unset($_COOKIE['kalika_auth']);
}

/**
 * Guard that enforces customer login. Redirects to login page if unauthenticated.
 *
 * @param string|null $returnUrl URL to return to after login
 */
function require_login(?string $returnUrl = null): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        if ($returnUrl) {
            $_SESSION['return_to'] = $returnUrl;
            redirect(public_url('login.php?return=' . urlencode($returnUrl)));
        } else {
            redirect(public_url('login.php'));
        }
    }
}

/**
 * Guard that redirects already logged-in users away from guest-only pages (e.g. login, register).
 */
function require_guest(): void
{
    if (is_logged_in()) {
        redirect(public_url('profile.php'));
    }
}

/**
 * Check if admin is currently authenticated.
 *
 * @return bool
 */
function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

/**
 * Alias for is_admin_logged_in.
 */
function is_admin(): bool
{
    return is_admin_logged_in();
}

/**
 * Get current authenticated admin data.
 *
 * @return array<string, mixed>|null
 */
function current_admin(): ?array
{
    if (!is_admin_logged_in()) {
        return null;
    }
    return [
        'id'       => $_SESSION['admin_id'] ?? null,
        'username' => $_SESSION['admin_username'] ?? 'Admin',
    ];
}

/**
 * Authenticate admin into session.
 *
 * @param array<string, mixed> $admin Admin record from database
 */
function login_admin(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id']       = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
}

/**
 * Log out admin.
 */
function logout_admin(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_username']);
    session_regenerate_id(true);
}

/**
 * Guard that enforces admin login.
 */
function require_admin(): void
{
    if (!is_admin_logged_in()) {
        set_flash('error', 'Admin authentication required.');
        redirect(admin_url('login.php'));
    }
}
