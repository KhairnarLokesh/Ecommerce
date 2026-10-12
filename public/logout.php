<?php
/**
 * Kalika Enterprises - Customer Logout Handler
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

logout_user();
set_flash('info', 'You have been successfully signed out.');
redirect(public_url('login.php'));
