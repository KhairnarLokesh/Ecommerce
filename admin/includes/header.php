<?php
/**
 * Kalika Enterprises - Admin Portal Shared Header
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($adminTitle) ?> | Admin Portal - <?= e($businessName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= public_url('assets/css/style.css') ?>">
    <style>
        .admin-topbar {
            background: #0f172a;
            color: #ffffff;
            padding: 0.875rem 0;
            border-bottom: 2px solid var(--color-accent);
        }
        .admin-nav-bar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.5rem 0;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .admin-nav-links {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            white-space: nowrap;
            padding: 0.25rem 0;
        }
        .admin-nav-links .nav-link {
            padding: 0.5rem 0.875rem;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 600;
            color: #475569;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .admin-nav-links .nav-link:hover {
            color: #0f172a;
            background: #f1f5f9;
        }
        .admin-nav-links .nav-link.active {
            color: #0f172a;
            background: #fef3c7;
            border-bottom: 2px solid #d97706;
            font-weight: 700;
        }
        .admin-main {
            padding: 2.25rem 1.25rem 4rem;
            min-height: 80vh;
            background: #f8fafc;
        }
        .badge-count {
            display: inline-block;
            background: #ef4444;
            color: #ffffff;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.1rem 0.4rem;
            border-radius: 999px;
            margin-left: 0.35rem;
        }
    </style>
</head>
<body style="background: #f8fafc; color: #1e293b;">

<!-- Top Global Admin Bar -->
<header class="admin-topbar">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <div style="display: flex; align-items: center; gap: 0.875rem;">
            <div style="width: 38px; height: 38px; border-radius: 8px; background: #1e293b; color: #f59e0b; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.25rem; border: 1px solid #334155;">
                KE
            </div>
            <div>
                <strong style="font-size: 1.05rem; color: #ffffff; letter-spacing: 0.3px; display: block; line-height: 1.2;">
                    <?= e($businessName) ?> Operations
                </strong>
                <span style="font-size: 0.75rem; color: #94a3b8;">Wholesale Distribution Panel</span>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 1.25rem; font-size: 0.8125rem;">
            <a href="<?= public_url('index.php') ?>" style="color: #cbd5e1; text-decoration: none;" target="_blank">
                Storefront &nearr;
            </a>
            <span style="color: #64748b;">|</span>
            <span style="color: #94a3b8;">
                Staff: <strong style="color: #f1f5f9;"><?= e($admin['username']) ?></strong>
            </span>
            <a href="<?= admin_url('change-password.php') ?>" style="color: #cbd5e1; text-decoration: underline;">
                Key &amp; Security
            </a>
            <form action="<?= admin_url('logout.php') ?>" method="POST" style="margin: 0;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm" style="background: #ef4444; color: #ffffff; padding: 0.25rem 0.65rem; font-size: 0.75rem; border: none;">
                    Logout
                </button>
            </form>
        </div>
    </div>
</header>

<!-- Main Admin Navigation Subbar -->
<nav class="admin-nav-bar">
    <div class="container">
        <div class="admin-nav-links">
            <a href="<?= admin_url('index.php') ?>" class="nav-link <?= $adminPage === 'dashboard' ? 'active' : '' ?>">
                Dashboard
            </a>
            <a href="<?= admin_url('orders.php') ?>" class="nav-link <?= $adminPage === 'orders' ? 'active' : '' ?>">
                Orders
            </a>
            <a href="<?= admin_url('products.php') ?>" class="nav-link <?= $adminPage === 'products' ? 'active' : '' ?>">
                Products &amp; Variants
            </a>
            <a href="<?= admin_url('brands.php') ?>" class="nav-link <?= $adminPage === 'brands' ? 'active' : '' ?>">
                Brands
            </a>
            <a href="<?= admin_url('stock.php') ?>" class="nav-link <?= $adminPage === 'stock' ? 'active' : '' ?>">
                Bulk Stock
            </a>
            <a href="<?= admin_url('customers.php') ?>" class="nav-link <?= $adminPage === 'customers' ? 'active' : '' ?>">
                Customers
            </a>
            <a href="<?= admin_url('messages.php') ?>" class="nav-link <?= $adminPage === 'messages' ? 'active' : '' ?>">
                Inquiries
            </a>
            <a href="<?= admin_url('settings.php') ?>" class="nav-link <?= $adminPage === 'settings' ? 'active' : '' ?>">
                Settings &amp; Policies
            </a>
            <a href="<?= admin_url('database.php') ?>" class="nav-link <?= $adminPage === 'database' ? 'active' : '' ?>" style="color: #0284c7;">
                &#9638; Database
            </a>
        </div>
    </div>
</nav>

<main class="container admin-main">
    <?= render_flash_messages() ?>
