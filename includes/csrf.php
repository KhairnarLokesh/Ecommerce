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
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['_csrf_token'];
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

    if (empty($token) || empty($_SESSION['_csrf_token'])) {
        return false;
    }

    return hash_equals((string)$_SESSION['_csrf_token'], (string)$token);
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
