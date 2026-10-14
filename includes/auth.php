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
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com; script-src 'self' 'unsafe-inline' https://checkout.razorpay.com; frame-src 'self' https://api.razorpay.com https://checkout.razorpay.com; connect-src 'self' https://lumberjack.razorpay.com https://api.razorpay.com; img-src 'self' data: https: https://*.razorpay.com; font-src 'self' https://fonts.gstatic.com;");
}

/**
 * Configure and start session with secure cookie parameters and idle timeout.
 *
 * @param string $sessionName Custom session name (distinguishes user vs admin sessions)
 */
function start_secure_session(?string $sessionName = null): void
{
    send_security_headers();

    if ($sessionName === null) {
        $sessionName = defined('SESSION_NAME_USER') ? SESSION_NAME_USER : 'kalika_user_sess';
    }

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
    $candidateId = $_SESSION['user_id'] ?? null;

    if (!$candidateId && !empty($_COOKIE['kalika_auth'])) {
        $recovered = auth_verify_user($_COOKIE['kalika_auth']);
        if ($recovered && !empty($recovered['id'])) {
            $candidateId = (int)$recovered['id'];
            $_SESSION['user_id']      = $candidateId;
            $_SESSION['user_name']    = $recovered['name'];
            $_SESSION['user_mobile']  = $recovered['mobile'];
            $_SESSION['user_email']   = $recovered['email'] ?? '';
            $_SESSION['user_address'] = $recovered['address'] ?? '';
        }
    }

    if (!$candidateId) {
        return false;
    }

    // Verify against DB if DB connection is active
    if (function_exists('get_db_connection')) {
        $pdo = get_db_connection();
        if ($pdo) {
            static $validated = [];
            if (!isset($validated[$candidateId])) {
                try {
                    $chk = $pdo->prepare("SELECT id, name, mobile, email, address, is_active FROM users WHERE id = ? LIMIT 1");
                    $chk->execute([$candidateId]);
                    $userRow = $chk->fetch();
                    if ($userRow && (int)$userRow['is_active'] === 1) {
                        $validated[$candidateId] = true;
                        $_SESSION['user_name']    = $userRow['name'];
                        $_SESSION['user_mobile']  = $userRow['mobile'];
                        $_SESSION['user_email']   = $userRow['email'] ?? '';
                        $_SESSION['user_address'] = $userRow['address'] ?? '';
                    } else {
                        // User does not exist or suspended in live DB: clean up immediately!
                        $validated[$candidateId] = false;
                        logout_user();
                        return false;
                    }
                } catch (Exception $e) {
                    // Fallback gracefully on query error
                }
            } elseif ($validated[$candidateId] === false) {
                return false;
            }
        }
    }

    return true;
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

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if (!headers_sent()) {
        setcookie('kalika_auth', '', [
            'expires'  => time() - 86400,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        setcookie('kalika_auth', '', time() - 86400, '/');
    }
    unset($_COOKIE['kalika_auth']);

    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
    }
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
 * Enforces 30-minute idle timeout.
 *
 * @return bool
 */
function is_admin_logged_in(): bool
{
    if (empty($_SESSION['admin_id'])) {
        return false;
    }

    $timeout = defined('ADMIN_SESSION_IDLE_TIMEOUT') ? ADMIN_SESSION_IDLE_TIMEOUT : 1800; // 30 minutes
    if (!empty($_SESSION['admin_last_activity']) && (time() - (int)$_SESSION['admin_last_activity'] > $timeout)) {
        logout_admin();
        set_flash('error', 'Your administrator session has expired due to 30 minutes of inactivity. Please log in again.');
        return false;
    }

    // Refresh last activity on active admin interaction
    $_SESSION['admin_last_activity'] = time();
    if (isset($_SESSION['admin']) && is_array($_SESSION['admin'])) {
        $_SESSION['admin']['last_activity'] = time();
    }

    return true;
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
        'id'                   => (int)($_SESSION['admin_id'] ?? 0),
        'username'             => (string)($_SESSION['admin_username'] ?? 'Admin'),
        'must_change_password' => (int)($_SESSION['admin_must_change_password'] ?? 0),
    ];
}

/**
 * Authenticate admin into isolated session namespace.
 *
 * @param array<string, mixed> $admin Admin record from database
 */
function login_admin(array $admin): void
{
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
    }

    $adminId = (int)$admin['id'];
    $adminUser = (string)$admin['username'];
    $mustChange = (int)($admin['must_change_password'] ?? 0);
    $now = time();

    $_SESSION['admin_id']                   = $adminId;
    $_SESSION['admin_username']             = $adminUser;
    $_SESSION['admin_must_change_password'] = $mustChange;
    $_SESSION['admin_last_activity']        = $now;

    // Distinct admin namespace
    $_SESSION['admin'] = [
        'id'                   => $adminId,
        'username'             => $adminUser,
        'must_change_password' => $mustChange,
        'last_activity'        => $now,
    ];
}

/**
 * Log out admin and terminate admin session namespace.
 */
function logout_admin(): void
{
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_username'],
        $_SESSION['admin_must_change_password'],
        $_SESSION['admin_last_activity'],
        $_SESSION['admin']
    );
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
    }
}

/**
 * Validate administrator password strength.
 * Policy: Min 12 chars, uppercase, lowercase, number, and special character.
 *
 * @param string $password
 * @return array<int, string> Error messages, or empty array if valid.
 */
function validate_admin_password_strength(string $password): array
{
    $errors = [];
    if (strlen($password) < 12) {
        $errors[] = 'Password must be at least 12 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must include at least one uppercase letter (A-Z).';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must include at least one lowercase letter (a-z).';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must include at least one number (0-9).';
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = 'Password must include at least one special character (e.g. !@#$%^&*).';
    }
    return $errors;
}

/**
 * Guard that enforces admin login.
 * Rejects logged-in customers with HTTP 403 Forbidden.
 * Enforces forced password change on first login.
 */
function require_admin(): void
{
    // 1. Strict Authorization Gate: Logged-in customer denied (403) on all admin URLs
    if (is_logged_in() && !is_admin_logged_in()) {
        http_response_code(403);
        send_security_headers();
        echo "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n";
        echo "<meta charset=\"UTF-8\"><title>403 Forbidden - Customer Access Denied</title>\n";
        echo "<link rel=\"stylesheet\" href=\"" . public_url('assets/css/style.css') . "\">\n";
        echo "</head>\n<body style=\"background:#0f172a;color:#f8fafc;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;font-family:sans-serif;\">\n";
        echo "<div style=\"max-width:520px;background:#1e293b;border:1px solid #ef4444;border-radius:12px;padding:2.25rem;text-align:center;box-shadow:0 10px 25px rgba(0,0,0,0.5);\">\n";
        echo "<div style=\"font-size:3rem;margin-bottom:1rem;\">🚫</div>\n";
        echo "<h1 style=\"color:#ef4444;font-size:1.5rem;margin-bottom:0.75rem;\">403 Forbidden: Customer Access Denied</h1>\n";
        echo "<p style=\"color:#cbd5e1;font-size:0.95rem;line-height:1.5;margin-bottom:1.5rem;\">You are authenticated with a customer account. Customer credentials do not have permission to access administration pages or operational management endpoints.</p>\n";
        echo "<div style=\"display:flex;gap:0.75rem;justify-content:center;flex-wrap:wrap;\">\n";
        echo "<a href=\"" . public_url('index.php') . "\" class=\"btn btn-primary\" style=\"text-decoration:none;padding:0.65rem 1.25rem;background:#f59e0b;color:#0f172a;font-weight:700;border-radius:6px;\">Return to Storefront</a>\n";
        echo "<a href=\"" . public_url('logout.php') . "\" class=\"btn\" style=\"text-decoration:none;padding:0.65rem 1.25rem;background:#334155;color:#f8fafc;border-radius:6px;\">Logout Customer Account</a>\n";
        echo "</div>\n</div>\n</body>\n</html>";
        exit;
    }

    // 2. Unauthenticated check
    if (!is_admin_logged_in()) {
        set_flash('error', 'Admin authentication required.');
        redirect(admin_login_url());
        exit;
    }

    // 3. First login: Enforce must_change_password screen
    if (!empty($_SESSION['admin_must_change_password'])) {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script !== 'change-password.php' && $script !== 'logout.php') {
            set_flash('error', 'First login: You must change your initial administrator password before accessing operational controls.');
            redirect(admin_url('change-password.php'));
            exit;
        }
    }
}

/**
 * Record an entry into admin_audit_log table.
 */
function admin_audit_log(
    string $action,
    string $entity,
    string|int|null $entityId = null,
    mixed $oldValue = null,
    mixed $newValue = null,
    ?int $adminId = null
): bool {
    global $pdo;
    if (!$pdo && function_exists('get_db_connection')) {
        $pdo = get_db_connection();
    }
    if (!$pdo) {
        return false;
    }

    $admin = current_admin();
    $adminId = $adminId ?? ($admin['id'] ?? null);
    if (!$adminId) {
        return false;
    }

    $ip = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

    $oldStr = is_array($oldValue) || is_object($oldValue) ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : ($oldValue !== null ? (string)$oldValue : null);
    $newStr = is_array($newValue) || is_object($newValue) ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : ($newValue !== null ? (string)$newValue : null);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_audit_log (admin_id, action, entity, entity_id, old_value, new_value, ip, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([
            $adminId,
            $action,
            $entity,
            $entityId !== null ? (string)$entityId : null,
            $oldStr,
            $newStr,
            $ip
        ]);
    } catch (PDOException $e) {
        error_log("Failed to insert admin audit log: " . $e->getMessage());
        return false;
    }
}

/**
 * Record an admin authentication attempt into admin_login_log table.
 */
function admin_log_login(
    string $username,
    string $status,
    ?int $adminId = null,
    ?string $ip = null,
    ?string $userAgent = null
): bool {
    global $pdo;
    if (!$pdo && function_exists('get_db_connection')) {
        $pdo = get_db_connection();
    }
    if (!$pdo) {
        return false;
    }

    $ip = $ip ?? (function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
    $userAgent = $userAgent ?? substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_login_log (admin_id, username_attempted, ip, status, user_agent, attempted_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([
            $adminId,
            $username,
            $ip,
            $status,
            $userAgent
        ]);
    } catch (PDOException $e) {
        error_log("Failed to insert admin login log: " . $e->getMessage());
        return false;
    }
}
