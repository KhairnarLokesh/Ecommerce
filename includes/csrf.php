<?php
/**
 * Kalika Enterprises - CSRF Protection Helpers
 * Provides token generation, form field output, and secure verification.
 */

declare(strict_types=1);

// Ensure session is active
if (session_status() === PHP_SESSION_NONE) {
    // Session should typically be started with secure cookie params in auth.php
    session_start();
}

/**
 * Generate or retrieve the existing CSRF token for the current session.
 *
 * @return string Hex-encoded random 32-byte token
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        if (!empty($_COOKIE['_csrf_token']) && strlen($_COOKIE['_csrf_token']) === 64) {
            $_SESSION['_csrf_token'] = $_COOKIE['_csrf_token'];
        } else {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
    }
    $token = (string)$_SESSION['_csrf_token'];
    if (empty($_COOKIE['_csrf_token']) || $_COOKIE['_csrf_token'] !== $token) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        if (!headers_sent()) {
            setcookie('_csrf_token', $token, [
                'expires'  => 0,
                'path'     => '/',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE['_csrf_token'] = $token;
    }
    return $token;
}

/**
 * Render an HTML hidden input tag containing the CSRF token.
 *
 * @return string HTML input element
 */
function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verify that the submitted CSRF token matches the session token.
 *
 * @param string|null $token Submitted token, or reads from $_POST['csrf_token'] if null
 * @return bool True if valid, false otherwise
 */
function csrf_verify(?string $token = null): bool
{
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }

    if (empty($token) || !is_string($token)) {
        return false;
    }

    // 1. Check against active session token
    if (!empty($_SESSION['_csrf_token']) && hash_equals((string)$_SESSION['_csrf_token'], (string)$token)) {
        return true;
    }

    // 2. Check against double-submit cookie token (handles stateless serverless container switching)
    if (!empty($_COOKIE['_csrf_token']) && hash_equals((string)$_COOKIE['_csrf_token'], (string)$token)) {
        $_SESSION['_csrf_token'] = $token;
        return true;
    }

    return false;
}

/**
 * Require valid CSRF token on POST requests or terminate with HTTP 403.
 */
function require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify()) {
            http_response_code(403);
            die('Invalid or expired security token. Please refresh the page and try again.');
        }
    }
}
