<?php
/**
 * Kalika Enterprises - Admin Logout Handler
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify(); // verify if token provided
}

logout_admin();
set_flash('info', 'Administrator signed out safely.');
redirect(admin_url('login.php'));
