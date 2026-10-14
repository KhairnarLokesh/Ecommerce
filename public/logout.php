<?php
/**
 * Kalika Enterprises - Customer Logout Handler
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Log out customer
logout_user();

// Also log out administrator if active in session
if (is_admin_logged_in()) {
    $admin = current_admin();
    if ($admin && !empty($admin['id'])) {
        admin_audit_log('logout', 'admin', $admin['id'], null, 'Logged out via storefront signout', (int)$admin['id']);
    }
    logout_admin();
}

set_flash('info', 'You have been successfully signed out.');
redirect(public_url('index.php'));
