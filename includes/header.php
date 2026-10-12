<?php
/**
 * Kalika Enterprises - Header & Navigation (Single Clean Block)
 * Step 0 & 2: Clean tokens, logo min-height 44px/36px, plain text brand links, sticky on scroll.
 */

declare(strict_types=1);

if (!defined('SITE_URL')) {
    if (file_exists(__DIR__ . '/../config/config.php')) {
        require_once __DIR__ . '/../config/config.php';
    } else {
        require_once __DIR__ . '/../config/config.sample.php';
    }
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$currentPage = $currentPage ?? '';
$siteTitle = defined('BUSINESS_NAME') ? BUSINESS_NAME : 'Kalika Enterprises';
$fullTitle = !empty($pageTitle) ? e($pageTitle) . ' | ' . e($siteTitle) : e($siteTitle) . ' - Food and Beverage Distribution';
$metaDescription = !empty($pageDescription) ? e($pageDescription) : 'Kalika Enterprises: Food and beverage distribution across Niphad, Dindori, Chandwad, Sinnar, and Yeola. Minimum order Rs 2,000 with free delivery.';
$cartCount = get_cart_count();
$user = current_user();

$selectedBrand = trim($_GET['brand'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');

// Active verified brands with confirmed GST
$headerBrands = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT b.id, b.name, b.slug
            FROM brands b
            WHERE b.is_active = 1
              AND EXISTS (
                  SELECT 1 FROM products p
                  INNER JOIN product_variants v ON v.product_id = p.id AND v.is_active = 1
                  WHERE p.brand_id = b.id AND p.is_active = 1 AND p.gst_percent IS NOT NULL
              )
            ORDER BY b.sort_order ASC, b.name ASC
        ");
        $headerBrands = $stmt->fetchAll();
    } catch (PDOException $e) {
        $headerBrands = [];
    }
}

if (empty($headerBrands) && function_exists('get_catalog_fallback')) {
    $fallbackCatalog = get_catalog_fallback();
    $headerBrands = $fallbackCatalog['brands'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $fullTitle ?></title>
    <meta name="description" content="<?= $metaDescription ?>">

    <!-- Google Fonts: Merriweather (Editorial Serif), Open Sans (Clean UI/Body), DotGothic16 (8-Bit Retro Numerals) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DotGothic16&family=Merriweather:ital,wght@0,400;0,700;1,400&family=Open+Sans:ital,wght@0,400;0,600;0,700;1,400&display=swap"
        rel="stylesheet">
    <meta name="theme-color" content="#e8e9eb">

    <!-- Design Tokens & Stylesheet -->
    <link rel="stylesheet" href="<?= public_url('assets/css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= public_url('assets/css/style.css') ?>">
    <link rel="icon" type="image/png" href="<?= public_url('assets/img/logo.png') ?>">
</head>

<body>

    <!-- Single Clean Header Block (Sticky on Scroll) -->
    <header class="site-header-block">
        <!-- Row 1: Slim Announcement Bar in Evergreen -->
        <div class="announcement-bar">
            <div class="container">
                <div class="announcement-bar-text">
                    Free delivery on orders of Rs 2,000+ &bull; Scheduled van routes across Niphad, Dindori, Chandwad,
                    Sinnar &amp; Yeola &bull; Delivery in 2-3 working days
                </div>
            </div>
        </div>

        <!-- Row 2: Logo, Search, Account & Cart -->
        <div class="header-main-bar">
            <div class="container">
                <div class="header-main-inner">
                    <!-- Left: Logo (min 44px desktop / 36px mobile with clear space) -->
                    <a href="<?= public_url('index.php') ?>" class="header-logo-link"
                        aria-label="Kalika Enterprises Home">
                        <img src="<?= public_url('assets/img/logo.png') ?>" alt="Kalika Enterprises"
                            class="header-logo-img">
                        <div class="header-brand-lockup">
                            <span class="header-brand-name">KALIKA</span>
                            <span class="header-brand-sub">ENTERPRISES</span>
                        </div>
                    </a>

                    <!-- Center: Search Bar (Desktop) -->
                    <div class="header-search-box">
                        <form action="<?= public_url('products.php') ?>" method="GET" class="search-form" role="search">
                            <select name="brand" class="search-select" aria-label="Filter by Brand">
                                <option value="">All Brands</option>
                                <?php foreach ($headerBrands as $hb): ?>
                                    <option value="<?= e($hb['slug']) ?>" <?= $selectedBrand === $hb['slug'] ? 'selected' : '' ?>>
                                        <?= e($hb['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="search" name="q" class="search-input" placeholder="Search products..."
                                value="<?= e($searchQuery) ?>" aria-label="Search products" autocomplete="off">
                            <button type="submit" class="search-btn" aria-label="Search">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                            </button>
                        </form>
                    </div>

                    <!-- Right: Account, Policy Ghost CTA & Cart -->
                    <div class="header-actions">
                        <a href="<?= public_url('policies.php') ?>" class="btn-header-ghost desktop-only">
                            Wholesale Policies
                        </a>

                        <!-- Account -->
                        <div class="account-menu-wrap">
                            <button type="button" class="account-btn" id="accountMenuTrigger" aria-expanded="false"
                                aria-haspopup="true">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <span><?= $user ? e(explode(' ', $user['name'])[0]) : 'Account' ?></span>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </button>
                            <div class="account-dropdown" id="accountDropdownCard">
                                <?php if ($user): ?>
                                    <a href="<?= public_url('profile.php') ?>">Profile</a>
                                    <a href="<?= public_url('orders.php') ?>">Orders</a>
                                    <div class="account-divider"></div>
                                    <a href="<?= public_url('logout.php') ?>" style="color: var(--color-stock-out);">Sign
                                        Out</a>
                                <?php else: ?>
                                    <a href="<?= public_url('login.php') ?>"
                                        style="font-weight: 600; color: var(--color-pine);">Sign In</a>
                                    <a href="<?= public_url('register.php') ?>">Create Account</a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Cart -->
                        <a href="<?= public_url('cart.php') ?>" class="cart-link" aria-label="Shopping Cart">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                            <span>Cart</span>
                            <span class="cart-count-badge" id="headerCartBadge"><?= $cartCount ?></span>
                        </a>

                        <!-- Mobile Hamburger -->
                        <button type="button" class="mobile-hamburger-btn" id="mobileDrawerOpenBtn"
                            aria-label="Open navigation menu" aria-expanded="false">
                            <span></span>
                            <span></span>
                            <span></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Search Row -->
        <div class="mobile-search-bar">
            <form action="<?= public_url('products.php') ?>" method="GET" class="search-form" role="search">
                <select name="brand" class="search-select" aria-label="Brand">
                    <option value="">All</option>
                    <?php foreach ($headerBrands as $hb): ?>
                        <option value="<?= e($hb['slug']) ?>" <?= $selectedBrand === $hb['slug'] ? 'selected' : '' ?>>
                            <?= e($hb['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="search" name="q" class="search-input" placeholder="Search products..."
                    value="<?= e($searchQuery) ?>" aria-label="Search products" autocomplete="off">
                <button type="submit" class="search-btn" aria-label="Search">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </button>
            </form>
        </div>

        <!-- Row 3: Main Navigation Bar with Active Highlights -->
        <nav class="header-nav-bar" aria-label="Main navigation">
            <div class="container">
                <div class="header-nav-inner">
                    <div class="main-nav-links">
                        <a href="<?= public_url('index.php') ?>"
                            class="nav-item <?= $currentPage === 'home' ? 'active' : '' ?>">
                            Home
                        </a>
                        <a href="<?= public_url('products.php') ?>"
                            class="nav-item <?= $currentPage === 'products' && empty($selectedBrand) ? 'active' : '' ?>">
                            All Products
                        </a>
                        <div class="nav-item-dropdown-wrap">
                            <a href="<?= public_url('products.php') ?>"
                                class="nav-item <?= !empty($selectedBrand) ? 'active' : '' ?>">
                                Brands
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.5">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </a>
                            <div class="nav-dropdown-menu">
                                <?php foreach ($headerBrands as $hb): ?>
                                    <a href="<?= public_url('products.php?brand=' . urlencode($hb['slug'])) ?>"
                                        class="nav-dropdown-link <?= $selectedBrand === $hb['slug'] ? 'active' : '' ?>">
                                        <?= e($hb['name']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <a href="<?= public_url('policies.php') ?>"
                            class="nav-item <?= $currentPage === 'policies' ? 'active' : '' ?>">
                            Policies &amp; Minimums
                        </a>
                        <a href="<?= public_url('about.php') ?>"
                            class="nav-item <?= $currentPage === 'about' ? 'active' : '' ?>">
                            About Us
                        </a>
                        <a href="<?= public_url('contact.php') ?>"
                            class="nav-item <?= $currentPage === 'contact' ? 'active' : '' ?>">
                            Contact
                        </a>
                    </div>

                    <div class="header-quick-contact">
                        <span class="quick-contact-badge">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="1" y="3" width="15" height="13" rx="1"></rect>
                                <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                                <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                <circle cx="18.5" cy="18.5" r="2.5"></circle>
                            </svg>
                            <span>Free Van Delivery (&#8377;2,000+)</span>
                        </span>
                        <a href="<?= public_url('contact.php') ?>" class="quick-desk-link">
                            Dispatch Desk
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align: -1px; margin-left: 2px;"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- Mobile Slide-out Drawer Navigation Overlay -->
    <div class="mobile-drawer-backdrop" id="mobileDrawerOverlay" aria-hidden="true">
        <div class="mobile-drawer-sheet" id="mobileDrawerPanel">
            <div class="drawer-top">
                <span style="font-weight: 600; font-size: 16px; color: var(--color-navy);">Kalika Enterprises</span>
                <button type="button" id="mobileDrawerCloseBtn"
                    style="background:none; border:none; font-size:20px; cursor:pointer;"
                    aria-label="Close menu">&times;</button>
            </div>
            <div class="drawer-links-wrap">
                <a href="<?= public_url('index.php') ?>"
                    class="drawer-link-item <?= $currentPage === 'home' ? 'active' : '' ?>">Home</a>
                <a href="<?= public_url('products.php') ?>"
                    class="drawer-link-item <?= $currentPage === 'products' ? 'active' : '' ?>">All Products</a>
                <div
                    style="padding: 12px 16px 4px; font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">
                    Brands
                </div>
                <?php foreach ($headerBrands as $hb): ?>
                    <a href="<?= public_url('products.php?brand=' . urlencode($hb['slug'])) ?>" class="drawer-link-item"
                        style="padding-left: 24px; font-size: 14px;">
                        <?= e($hb['name']) ?>
                    </a>
                <?php endforeach; ?>
                <div style="height:1px; background:var(--border-color); margin: 8px 0;"></div>
                <a href="<?= public_url('about.php') ?>"
                    class="drawer-link-item <?= $currentPage === 'about' ? 'active' : '' ?>">About Us</a>
                <a href="<?= public_url('policies.php') ?>"
                    class="drawer-link-item <?= $currentPage === 'policies' ? 'active' : '' ?>">Policies</a>
                <a href="<?= public_url('contact.php') ?>"
                    class="drawer-link-item <?= $currentPage === 'contact' ? 'active' : '' ?>">Contact</a>
                <div style="height:1px; background:var(--border-color); margin: 8px 0;"></div>
                <?php if ($user): ?>
                    <a href="<?= public_url('profile.php') ?>" class="drawer-link-item">My Profile</a>
                    <a href="<?= public_url('orders.php') ?>" class="drawer-link-item">My Orders</a>
                    <a href="<?= public_url('logout.php') ?>" class="drawer-link-item"
                        style="color: var(--color-stock-out);">Sign Out</a>
                <?php else: ?>
                    <a href="<?= public_url('login.php') ?>" class="drawer-link-item">Login</a>
                    <a href="<?= public_url('register.php') ?>" class="drawer-link-item">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation Bar (5 Tabs) -->
    <nav class="bottom-tab-bar" aria-label="Mobile Navigation">
        <a href="<?= public_url('index.php') ?>" class="bottom-tab-item <?= $currentPage === 'home' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span>Home</span>
        </a>
        <a href="<?= public_url('products.php') ?>"
            class="bottom-tab-item <?= $currentPage === 'products' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span>Brands</span>
        </a>
        <a href="<?= public_url('cart.php') ?>" class="bottom-tab-item <?= $currentPage === 'cart' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="9" cy="21" r="1"></circle>
                <circle cx="20" cy="21" r="1"></circle>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
            <span>Cart</span>
            <span class="bottom-tab-badge" id="bottomNavCartBadge"
                style="<?= $cartCount > 0 ? '' : 'display:none;' ?>"><?= $cartCount ?></span>
        </a>
        <a href="<?= $user ? public_url('orders.php') : public_url('login.php?redirect=orders.php') ?>"
            class="bottom-tab-item <?= $currentPage === 'orders' ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <path d="M16 10a4 4 0 0 1-8 0"></path>
            </svg>
            <span>Orders</span>
        </a>
        <a href="<?= $user ? public_url('profile.php') : public_url('login.php') ?>"
            class="bottom-tab-item <?= in_array($currentPage, ['profile', 'login', 'register']) ? 'active' : '' ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <span>Account</span>
        </a>
    </nav>

    <!-- Global Toast Container -->
    <div class="toast-box" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

    <!-- Flash Alerts Container -->
    <div class="container flash-box">
        <?= render_flash_messages() ?>
    </div>

    <!-- Main Content Area Starts -->
    <main class="main-content" id="mainContent">