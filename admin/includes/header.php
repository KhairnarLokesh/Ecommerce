<?php
/**
 * Kalika Enterprises - Admin Portal Shared Header
 * Aligned with Customer Storefront Design Tokens:
 * Obsidian Dark Global Bar, Clean Surface Pill Navigation, Editorial Merriweather & Open Sans Typography.
 */

declare(strict_types=1);

if (!defined('SITE_URL')) {
    if (file_exists(__DIR__ . '/../../config/config.php')) {
        require_once __DIR__ . '/../../config/config.php';
    } else {
        require_once __DIR__ . '/../../config/config.sample.php';
    }
}
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin();

$admin = current_admin();
$adminPage = $adminPage ?? 'dashboard';
$adminTitle = $adminTitle ?? 'Operations Portal';
$businessName = get_setting('business_name', 'Kalika Enterprises');

// Live counts for navigation badges
$navPlacedOrders = 0;
$navUnreadInquiries = 0;
if ($pdo) {
    try {
        $navPlacedOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Placed'")->fetchColumn();
        $navUnreadInquiries = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
    } catch (Throwable $e) {
        // Graceful degradation
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($adminTitle) ?> | Admin Portal - <?= e($businessName) ?></title>
    
    <!-- Google Fonts matching storefront: Merriweather Editorial + Open Sans UI -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DotGothic16&family=Merriweather:ital,wght@0,400;0,700;1,400&family=Open+Sans:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    
    <!-- Unified Design System Tokens & Admin Styles -->
    <link rel="stylesheet" href="<?= public_url('assets/css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= public_url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= public_url('assets/css/admin.css') ?>">
    <link rel="icon" type="image/png" href="<?= public_url('assets/img/logo.png') ?>">
</head>
<body class="admin-body">

<!-- Global Executive Topbar (Obsidian Forest Theme) -->
<header class="admin-topbar">
    <div class="container admin-topbar__inner">
        <!-- Left: Brand Identity -->
        <a href="<?= admin_url('index.php') ?>" class="admin-brand">
            <?php if (file_exists(__DIR__ . '/../../public/assets/img/logo.png')): ?>
                <img src="<?= public_url('assets/img/logo.png') ?>" alt="Logo" class="admin-brand__logo">
            <?php else: ?>
                <div class="admin-brand__badge">KE</div>
            <?php endif; ?>
            <div class="admin-brand__text">
                <span class="admin-brand__title"><?= e($businessName) ?> Operations</span>
                <span class="admin-brand__subtitle">
                    <span class="admin-status-dot"></span>
                    Wholesale Hub &bull; Live Control
                </span>
            </div>
        </a>

        <!-- Right: Admin Utilities -->
        <div class="admin-tools">
            <a href="<?= public_url('index.php') ?>" class="admin-tool-link" target="_blank" title="Open Storefront in new tab">
                <span>Storefront</span>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="7" y1="17" x2="17" y2="7"></line><polyline points="7 7 17 7 17 17"></polyline></svg>
            </a>

            <span style="color: rgba(255, 255, 255, 0.2);">|</span>

            <div class="admin-user-chip">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <span>Owner: <strong><?= e($admin['username'] === 'admin' ? 'Deepak Pawar (admin)' : $admin['username']) ?></strong></span>
            </div>

            <a href="<?= admin_url('change-password.php') ?>" class="admin-tool-link" title="Account Credentials">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                <span>Security</span>
            </a>

            <a href="<?= admin_url('activity-log.php') ?>" class="admin-tool-link" title="Audit Trail">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <span>Audit</span>
            </a>

            <form action="<?= admin_url('logout.php') ?>" method="POST" style="margin: 0;">
                <?= csrf_field() ?>
                <button type="submit" class="admin-logout-btn" title="Sign out of Admin Portal">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </div>
</header>

<!-- Secondary Navigation Bar (Modern Pill Design) -->
<nav class="admin-nav-bar">
    <div class="container">
        <div class="admin-nav-links">
            <!-- 1. Dashboard -->
            <a href="<?= admin_url('index.php') ?>" class="nav-link <?= $adminPage === 'dashboard' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                <span>Dashboard</span>
            </a>

            <!-- 2. Orders -->
            <a href="<?= admin_url('orders.php') ?>" class="nav-link <?= $adminPage === 'orders' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                <span>Orders</span>
                <?php if ($navPlacedOrders > 0): ?>
                    <span class="badge-count" title="<?= $navPlacedOrders ?> New Orders Pending"><?= $navPlacedOrders ?></span>
                <?php endif; ?>
            </a>

            <!-- 3. Products -->
            <a href="<?= admin_url('products.php') ?>" class="nav-link <?= $adminPage === 'products' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                <span>Products &amp; Variants</span>
            </a>

            <!-- 4. Categories -->
            <a href="<?= admin_url('categories.php') ?>" class="nav-link <?= $adminPage === 'categories' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                <span>Categories</span>
            </a>

            <!-- 5. Brands -->
            <a href="<?= admin_url('brands.php') ?>" class="nav-link <?= $adminPage === 'brands' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                <span>Brands</span>
            </a>

            <!-- 6. Bulk Stock -->
            <a href="<?= admin_url('stock.php') ?>" class="nav-link <?= $adminPage === 'stock' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                <span>Bulk Stock</span>
            </a>

            <!-- 7. Customers -->
            <a href="<?= admin_url('customers.php') ?>" class="nav-link <?= $adminPage === 'customers' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <span>Customers</span>
            </a>

            <!-- 8. Inquiries -->
            <a href="<?= admin_url('messages.php') ?>" class="nav-link <?= $adminPage === 'messages' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                <span>Inquiries</span>
                <?php if ($navUnreadInquiries > 0): ?>
                    <span class="badge-count" title="<?= $navUnreadInquiries ?> Unread Messages"><?= $navUnreadInquiries ?></span>
                <?php endif; ?>
            </a>

            <!-- 9. Settings -->
            <a href="<?= admin_url('settings.php') ?>" class="nav-link <?= $adminPage === 'settings' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                <span>Settings &amp; Policies</span>
            </a>

            <!-- 10. Activity Log -->
            <a href="<?= admin_url('activity-log.php') ?>" class="nav-link <?= $adminPage === 'activity_log' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                <span>Activity Log</span>
            </a>

            <!-- 11. Database Backup -->
            <a href="<?= admin_url('database.php') ?>" class="nav-link <?= $adminPage === 'database' ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                <span>Database</span>
            </a>
        </div>
    </div>
</nav>

<main class="container admin-main">
    <?= render_flash_messages() ?>
