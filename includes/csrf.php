<?php
/**
 * Kalika Enterprises - CSRF Protection Helpers
 * Provides token generation, form field output, and secure verification.
 * Built for stateless serverless environments (Vercel) with HMAC cryptographic verification.
 */

declare(strict_types=1);

// Ensure session is active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Secret key for stateless HMAC token verification.
 */
function csrf_get_secret(): string
{
    return defined('APP_KEY') ? APP_KEY : 'kalika_csrf_secret_salt_2026_981273';
}

/**
 * Generate or retrieve the existing CSRF token for the current session.
 * Always produces a 64-character hex string that is HMAC-signed for serverless resilience.
 *
 * @return string Hex-encoded 64-character token
 */
function csrf_token(): string
{
    if (!empty($_SESSION['_csrf_token']) && strlen((string)$_SESSION['_csrf_token']) === 64) {
        $token = (string)$_SESSION['_csrf_token'];
    } elseif (!empty($_COOKIE['_csrf_token']) && strlen((string)$_COOKIE['_csrf_token']) === 64) {
        $token = (string)$_COOKIE['_csrf_token'];
        $_SESSION['_csrf_token'] = $token;
    } else {
        $timeStr = str_pad((string)time(), 10, '0', STR_PAD_LEFT);
        $randHex = bin2hex(random_bytes(6)); // 12 chars
        $payload = $randHex . $timeStr; // 22 chars
        $sig = substr(hash_hmac('sha256', $payload, csrf_get_secret()), 0, 42); // 42 chars
        $token = $payload . $sig; // Exactly 64 chars
        $_SESSION['_csrf_token'] = $token;
    }

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

// Pre-initialize token so cookie is queued before any headers or HTML are sent
csrf_token();

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
 * Verify that the submitted CSRF token matches the session token, cookie token, or HMAC signature.
 *
 * @param string|null $token Submitted token, or reads from $_POST['csrf_token'] if null
 * @return bool True if valid, false otherwise
 */
function csrf_verify(?string $token = null): bool
{
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }

    if (empty($token) || !is_string($token) || strlen($token) !== 64) {
        return false;
    }

    // 1. Check against active session token
    if (!empty($_SESSION['_csrf_token']) && hash_equals((string)$_SESSION['_csrf_token'], $token)) {
        return true;
    }

    // 2. Check against double-submit cookie token (handles stateless serverless container switching)
    if (!empty($_COOKIE['_csrf_token']) && hash_equals((string)$_COOKIE['_csrf_token'], $token)) {
        $_SESSION['_csrf_token'] = $token;
        return true;
    }

    // 3. Cryptographic HMAC validation (100% resilient across stateless Vercel containers)
    $payload = substr($token, 0, 22);
    $sig = substr($token, 22);
    $timestamp = (int)substr($payload, 12, 10);

    // Valid if generated within last 24 hours (86400 seconds)
    if ($timestamp > (time() - 86400) && $timestamp <= (time() + 300)) {
        $expectedSig = substr(hash_hmac('sha256', $payload, csrf_get_secret()), 0, 42);
        if (hash_equals($expectedSig, $sig)) {
            $_SESSION['_csrf_token'] = $token;
            return true;
        }
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

