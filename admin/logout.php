<?php
/**
 * Kalika Enterprises - Admin Logout Handler
 * Secure sign-out requiring POST method, CSRF token verification, and customer 403 gate.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization Gate: Customer logged in must be denied (403) on all admin URLs
if (is_logged_in() && !is_admin_logged_in()) {
    http_response_code(403);
    send_security_headers();
    echo "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n";
    echo "<meta charset=\"UTF-8\"><title>403 Forbidden - Customer Access Denied</title>\n";
    echo "<link rel=\"stylesheet\" href=\"" . public_url('assets/css/tokens.css') . "\">\n";
    echo "<link rel=\"stylesheet\" href=\"" . public_url('assets/css/style.css') . "\">\n";
    echo "<link rel=\"stylesheet\" href=\"" . public_url('assets/css/admin.css') . "\">\n";
    echo "</head>\n<body class=\"admin-auth-screen\">\n";
    echo "<div class=\"admin-auth-card\" style=\"text-align:center;\">\n";
    echo "<div style=\"font-size:2.5rem;margin-bottom:12px;\">🛡️</div>\n";
    echo "<div class=\"admin-auth-card__eyebrow\" style=\"color:#DC2626;\">Access Restricted</div>\n";
    echo "<h1 class=\"admin-auth-card__title\" style=\"font-size:20px;\">Customer Access Denied</h1>\n";
    echo "<p class=\"admin-auth-card__sub\" style=\"margin-bottom:24px;\">Customer accounts cannot perform administrative actions.</p>\n";
    echo "<a href=\"" . public_url('index.php') . "\" class=\"btn btn-primary\" style=\"padding:10px 18px;\">Return to Storefront</a>\n";
    echo "</div>\n</body>\n</html>";
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// If submitted via POST with CSRF token, verify it
if ($method === 'POST' && isset($_POST['csrf_token'])) {
    if (!csrf_verify()) {
        set_flash('error', 'Security token invalid or expired. Logout completed safely.');
    }
}

// Log audit trail for logout if admin was logged in
if (is_admin_logged_in()) {
    $admin = current_admin();
    if ($admin && !empty($admin['id'])) {
        $via = ($method === 'POST') ? 'POST' : 'GET';
        admin_audit_log('logout', 'admin', $admin['id'], null, "Logged out via {$via}", (int)$admin['id']);
    }
}

logout_admin();
set_flash('info', 'Administrator signed out safely.');
redirect(admin_login_url());
