<?php
/**
 * Kalika Enterprises - Products Catalogue
 * Marketplace product listing with Category & Brand filters,
 * mobile bottom sheet, 1:1 image system, variant selection, and strict tokens.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Filter parameters from URL
$pdo = $pdo ?? get_db_connection(false);
$selectedCategorySlug = trim($_GET['category'] ?? '');
$selectedBrandSlug = trim($_GET['brand'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');
$inStockOnly = (isset($_GET['in_stock']) && $_GET['in_stock'] === '1');

$categoriesList = [];
$brandsList = [];
$products = [];
$totalProducts = 0;
$currentCategoryName = '';
$currentBrandName = '';

if ($pdo) {
    try {
        // 1. Fetch active categories that have active products with confirmed GST and variants
        $catStmt = $pdo->query("
            SELECT c.id, c.name, c.slug, COUNT(p.id) as product_count
            FROM categories c
            INNER JOIN products p ON p.category_id = c.id AND p.is_active = 1 AND p.gst_percent IS NOT NULL
            INNER JOIN product_variants v ON v.product_id = p.id AND v.is_active = 1
            WHERE c.is_active = 1
            GROUP BY c.id, c.name, c.slug, c.sort_order
            ORDER BY c.sort_order ASC, c.name ASC
        ");
        $categoriesList = $catStmt->fetchAll();

        foreach ($categoriesList as $c) {
            if ($c['slug'] === $selectedCategorySlug) {
                $currentCategoryName = $c['name'];
                break;
            }
        }

        // 2. Fetch active brands that have active products with confirmed GST and product counts
        $brandStmt = $pdo->query("
            SELECT b.id, b.name, b.slug, COUNT(p.id) as product_count
            FROM brands b
            INNER JOIN products p ON p.brand_id = b.id AND p.is_active = 1 AND p.gst_percent IS NOT NULL
            INNER JOIN product_variants v ON v.product_id = p.id AND v.is_active = 1
            WHERE b.is_active = 1
            GROUP BY b.id, b.name, b.slug, b.sort_order
            ORDER BY b.sort_order ASC, b.name ASC
        ");
        $brandsList = $brandStmt->fetchAll();

        foreach ($brandsList as $b) {
            if ($b['slug'] === $selectedBrandSlug) {
                $currentBrandName = $b['name'];
                break;
            }
        }

        // 3. Build parameterized query for products
        $sql = "
            SELECT 
                p.id, p.name, p.slug, p.image, p.description, p.gst_percent,
                b.id AS brand_id, b.name AS brand_name, b.slug AS brand_slug,
                c.id AS category_id, c.name AS category_name, c.slug AS category_slug
            FROM products p
            INNER JOIN brands b ON b.id = p.brand_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.is_active = 1
              AND p.gst_percent IS NOT NULL
              AND b.is_active = 1
              AND EXISTS (
                  SELECT 1 FROM product_variants v 
                  WHERE v.product_id = p.id AND v.is_active = 1
              )
        ";
        $params = [];

        if (!empty($selectedCategorySlug)) {
            $sql .= " AND c.slug = :category";
            $params[':category'] = $selectedCategorySlug;
        }

        if (!empty($selectedBrandSlug)) {
            $sql .= " AND b.slug = :brand";
            $params[':brand'] = $selectedBrandSlug;
        }

        if ($inStockOnly) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM product_variants v2 
                WHERE v2.product_id = p.id AND v2.is_active = 1 AND v2.stock_status = 'in_stock'
            )";
        }

        if (!empty($searchQuery)) {
            $sql .= " AND (p.name LIKE :q OR b.name LIKE :q OR c.name LIKE :q OR p.description LIKE :q)";
            $params[':q'] = '%' . $searchQuery . '%';
        }

        $sql .= " ORDER BY c.sort_order ASC, b.sort_order ASC, p.name ASC";

        $productStmt = $pdo->prepare($sql);
        $productStmt->execute($params);
        $rawProducts = $productStmt->fetchAll();

        // 4. Fetch active variants for each retrieved product
        if (!empty($rawProducts)) {
            $productIds = array_column($rawProducts, 'id');
            $inClause = implode(',', array_fill(0, count($productIds), '?'));
            
            $varStmt = $pdo->prepare("
                SELECT id, product_id, size_label, price, stock_qty, stock_status
                FROM product_variants
                WHERE is_active = 1 AND product_id IN ($inClause)
                ORDER BY price ASC, id ASC
            ");
            $varStmt->execute($productIds);
            $rawVariants = $varStmt->fetchAll();

            $variantsByProduct = [];
            foreach ($rawVariants as $v) {
                $variantsByProduct[$v['product_id']][] = $v;
            }

            foreach ($rawProducts as $p) {
                $p['variants'] = $variantsByProduct[$p['id']] ?? [];
                if (!empty($p['variants'])) {
                    $products[] = $p;
                }
            }
        }
        $totalProducts = count($products);

    } catch (PDOException $e) {
        error_log("Catalogue query failed: " . $e->getMessage());
    }
}

// Fallback handling if database is down
if (empty($products) && function_exists('get_catalog_fallback')) {
    $fallbackCatalog = get_catalog_fallback();
    if (empty($categoriesList)) {
        $categoriesList = $fallbackCatalog['categories'] ?? [];
    }
    foreach ($categoriesList as $c) {
        if ($c['slug'] === $selectedCategorySlug) {
            $currentCategoryName = $c['name'];
            break;
        }
    }

    if (empty($brandsList)) {
        $brandsList = $fallbackCatalog['brands'] ?? [];
    }
    foreach ($brandsList as $b) {
        if ($b['slug'] === $selectedBrandSlug) {
            $currentBrandName = $b['name'];
            break;
        }
    }

    $rawFallbackProds = $fallbackCatalog['products'] ?? [];
    foreach ($rawFallbackProds as $fp) {
        if (!empty($selectedCategorySlug) && ($fp['category_slug'] ?? '') !== $selectedCategorySlug) {
            continue;
        }
        if (!empty($selectedBrandSlug) && ($fp['brand_slug'] ?? '') !== $selectedBrandSlug) {
            continue;
        }
        if ($inStockOnly) {
            $hasInStock = false;
            foreach ($fp['variants'] ?? [] as $fv) {
                if (($fv['stock_status'] ?? '') === 'in_stock') {
                    $hasInStock = true;
                    break;
                }
            }
            if (!$hasInStock) {
                continue;
            }
        }
        if (!empty($searchQuery)) {
            $haystack = strtolower(($fp['name'] ?? '') . ' ' . ($fp['brand_name'] ?? '') . ' ' . ($fp['category_name'] ?? '') . ' ' . ($fp['description'] ?? ''));
            if (!str_contains($haystack, strtolower($searchQuery))) {
                continue;
            }
        }
        $products[] = $fp;
    }
    $totalProducts = count($products);
}

// Helper for filter links
if (!function_exists('filter_link')) {
    function filter_link(array $changes): string {
        global $selectedCategorySlug, $selectedBrandSlug, $inStockOnly, $searchQuery;
        $state = [
            'category' => $selectedCategorySlug,
            'brand' => $selectedBrandSlug,
            'in_stock' => $inStockOnly ? '1' : '',
            'q' => $searchQuery
        ];
        foreach ($changes as $k => $v) {
            $state[$k] = $v;
        }
        $clean = array_filter($state, fn($val) => $val !== '' && $val !== null);
        return public_url('products.php' . (!empty($clean) ? '?' . http_build_query($clean) : ''));
    }
}

// Meta tags & Titles
if (!empty($currentBrandName) && !empty($currentCategoryName)) {
    $pageTitle = $currentBrandName . ' - ' . $currentCategoryName;
    $displayHeading = e($currentBrandName) . ' &bull; ' . e($currentCategoryName);
} elseif (!empty($currentCategoryName)) {
    $pageTitle = $currentCategoryName . ' Wholesale Catalogue';
    $displayHeading = e($currentCategoryName);
} elseif (!empty($currentBrandName)) {
    $pageTitle = $currentBrandName . ' Range';
    $displayHeading = e($currentBrandName) . ' Range';
} else {
    $pageTitle = 'Wholesale Product Catalogue';
    $displayHeading = 'Wholesale Product Catalogue';
}
$currentPage = 'products';
$pageDescription = 'Browse wholesale distribution catalogue for Cold Drinks, Juices, Water, Biscuits, and Chips across Niphad, Dindori, Chandwad, Sinnar, and Yeola.';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top: var(--space-24); padding-bottom: var(--space-64);">
    <!-- Catalogue Title Banner -->
    <div style="margin-bottom: var(--space-24);">
        <h1 style="font-size: var(--font-size-24); font-weight: var(--font-weight-semibold); color: var(--color-navy); margin-bottom: var(--space-4);">
            <?= $displayHeading ?>
        </h1>
        <p style="font-size: var(--font-size-14); color: var(--text-muted); margin-bottom: 0;">
            Minimum order Rs 2,000 &bull; Free delivery on Rs 2,000+ &bull; Delivery in 2-3 working days
        </p>
    </div>

    <?php if (!$pdo && !empty($GLOBALS['db_connection_error']) && empty($products)): ?>
        <div style="background:#fee2e2; border:1px solid #ef4444; color:#991b1b; padding:12px 16px; margin-bottom:20px; border-radius:6px; font-size:13px; font-family: monospace;">
            <strong>Database Connection Diagnostic:</strong> <?= htmlspecialchars($GLOBALS['db_connection_error']) ?>
        </div>
    <?php endif; ?>

    <!-- Mobile Filter Bar (Visible <= 900px) -->
    <div class="mobile-filter-bar">
        <div>
            <span style="font-size: var(--font-size-14); font-weight: var(--font-weight-semibold); color: var(--text-main);">
                <?= $totalProducts ?> Products
            </span>
            <?php if (!empty($selectedCategorySlug) || !empty($selectedBrandSlug) || $inStockOnly || !empty($searchQuery)): ?>
                <span style="font-size: var(--font-size-12); color: var(--color-orange); margin-left: 6px;">(Filtered)</span>
            <?php endif; ?>
        </div>
        <button type="button" class="btn btn-secondary" id="openMobileFilterBtn" style="padding: 6px 14px; font-size: 14px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
            </svg>
            Filter Options
        </button>
    </div>

    <!-- Main Catalogue Layout (Sidebar + Grid) -->
    <div class="catalogue-layout">
        <!-- Desktop Left Filter Sidebar -->
        <aside class="catalogue-sidebar" aria-label="Product filters">
            <div class="sidebar-heading">
                <span>Filter Products</span>
                <?php if (!empty($selectedCategorySlug) || !empty($selectedBrandSlug) || $inStockOnly || !empty($searchQuery)): ?>
                    <a href="<?= public_url('products.php') ?>" style="font-size: var(--font-size-12); color: var(--color-orange); font-weight: 400;">
                        Reset All
                    </a>
                <?php endif; ?>
            </div>

            <!-- Category Filter -->
            <div class="sidebar-filter-section">
                <div class="filter-group-title">Categories</div>
                <div class="filter-link-list">
                    <a href="<?= filter_link(['category' => '']) ?>" 
                       class="filter-item-link <?= empty($selectedCategorySlug) ? 'active' : '' ?>">
                        <span>All Categories</span>
                    </a>
                    <?php foreach ($categoriesList as $c): 
                        $cUrl = filter_link(['category' => $c['slug']]);
                        $isActive = ($selectedCategorySlug === $c['slug']);
                    ?>
                        <a href="<?= $cUrl ?>" class="filter-item-link <?= $isActive ? 'active' : '' ?>">
                            <span><?= e($c['name']) ?></span>
                            <span class="filter-count"><?= (int)$c['product_count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Brand Filter -->
            <div class="sidebar-filter-section">
                <div class="filter-group-title">Brands</div>
                <div class="filter-link-list">
                    <a href="<?= filter_link(['brand' => '']) ?>" 
                       class="filter-item-link <?= empty($selectedBrandSlug) ? 'active' : '' ?>">
                        <span>All Brands</span>
                    </a>
                    <?php foreach ($brandsList as $b): 
                        $brandUrl = filter_link(['brand' => $b['slug']]);
                        $isActive = ($selectedBrandSlug === $b['slug']);
                    ?>
                        <a href="<?= $brandUrl ?>" class="filter-item-link <?= $isActive ? 'active' : '' ?>">
                            <span><?= e($b['name']) ?></span>
                            <span class="filter-count"><?= (int)$b['product_count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Availability Filter -->
            <div class="sidebar-filter-section">
                <div class="filter-group-title">Availability</div>
                <form action="<?= public_url('products.php') ?>" method="GET" id="stockFilterForm">
                    <?php if (!empty($selectedCategorySlug)): ?>
                        <input type="hidden" name="category" value="<?= e($selectedCategorySlug) ?>">
                    <?php endif; ?>
                    <?php if (!empty($selectedBrandSlug)): ?>
                        <input type="hidden" name="brand" value="<?= e($selectedBrandSlug) ?>">
                    <?php endif; ?>
                    <?php if (!empty($searchQuery)): ?>
                        <input type="hidden" name="q" value="<?= e($searchQuery) ?>">
                    <?php endif; ?>
                    <label class="filter-checkbox-label">
                        <input type="checkbox" name="in_stock" value="1" <?= $inStockOnly ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span>In Stock Only</span>
                    </label>
                </form>
            </div>
        </aside>

        <!-- Product Grid Content -->
        <main class="catalogue-content">
            <!-- Filter Pills / Status Line -->
            <div class="catalogue-header-bar" style="flex-wrap: wrap; gap: 8px;">
                <div style="font-size: var(--font-size-14); color: var(--text-muted);">
                    Showing <strong style="color: var(--text-main);"><?= $totalProducts ?></strong> products
                    <?php if (!empty($currentCategoryName)): ?>
                        in Category <strong style="color: var(--text-main);"><?= e($currentCategoryName) ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($currentBrandName)): ?>
                        from Brand <strong style="color: var(--text-main);"><?= e($currentBrandName) ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($searchQuery)): ?>
                        matching "<strong><?= e($searchQuery) ?></strong>"
                    <?php endif; ?>
                </div>

                <!-- Active Filter Tags -->
                <?php if (!empty($selectedCategorySlug) || !empty($selectedBrandSlug) || !empty($searchQuery)): ?>
                    <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-left: auto;">
                        <?php if (!empty($selectedCategorySlug)): ?>
                            <a href="<?= filter_link(['category' => '']) ?>" class="badge" style="background: #e0e7ff; color: #3730a3; padding: 4px 8px; font-size: 12px; text-decoration: none; border-radius: 4px;">
                                <?= e($currentCategoryName) ?> &times;
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($selectedBrandSlug)): ?>
                            <a href="<?= filter_link(['brand' => '']) ?>" class="badge" style="background: #e2e8f0; color: #1e293b; padding: 4px 8px; font-size: 12px; text-decoration: none; border-radius: 4px;">
                                <?= e($currentBrandName) ?> &times;
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($searchQuery)): ?>
                            <a href="<?= filter_link(['q' => '']) ?>" class="badge" style="background: #fef3c7; color: #92400e; padding: 4px 8px; font-size: 12px; text-decoration: none; border-radius: 4px;">
                                "<?= e($searchQuery) ?>" &times;
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($products)): ?>
                <div class="catalogue-grid">
                    <?php foreach ($products as $product): ?>
                        <?php 
                        $firstVariant = $product['variants'][0];
                        $isInitialInStock = ($firstVariant['stock_status'] === 'in_stock');
                        ?>
                        <div class="product-card" id="product-<?= (int)$product['id'] ?>">
                            <!-- Top section: Image & Info -->
                            <div>
                                <!-- Image System: 1:1 container, contain, 8px padding, white background or grey fallback -->
                                <div class="product-img-box">
                                    <?php if (!empty($product['image']) && file_exists(__DIR__ . '/uploads/products/' . $product['image'])): ?>
                                        <img 
                                            src="<?= public_url('uploads/products/' . rawurlencode($product['image'])) ?>" 
                                            alt="<?= e($product['name']) ?>" 
                                            class="product-img-tag"
                                            loading="lazy"
                                            width="200"
                                            height="200"
                                        >
                                    <?php else: ?>
                                        <div class="product-placeholder-box">
                                            <div class="product-placeholder-brand"><?= e($product['brand_name']) ?></div>
                                            <div class="product-placeholder-sub">Photo coming soon</div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Category & Brand Badges -->
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4); gap: 4px; flex-wrap: wrap;">
                                    <div style="display: flex; gap: 4px; align-items: center; flex-wrap: wrap;">
                                        <?php if (!empty($product['category_name'])): ?>
                                            <span style="font-size: 11px; font-weight: 600; color: #4338ca; background: #e0e7ff; padding: 1px 6px; border-radius: 4px;">
                                                <?= e($product['category_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <span style="font-size: var(--font-size-12); font-weight: var(--font-weight-semibold); color: var(--text-muted);">
                                            <?= e($product['brand_name']) ?>
                                        </span>
                                    </div>
                                    <span class="badge-stock <?= $isInitialInStock ? 'badge-in-stock stock-badge-in' : 'badge-out-of-stock stock-badge-out' ?> stock-badge">
                                        <?= $isInitialInStock ? 'In Stock' : 'Out of Stock' ?>
                                    </span>
                                </div>

                                <!-- Product Name -->
                                <h2 style="font-size: var(--font-size-16); font-weight: var(--font-weight-semibold); color: var(--text-main); margin-bottom: var(--space-4); line-height: var(--line-height-tight);">
                                    <?= e($product['name']) ?>
                                </h2>

                                <!-- GST metadata -->
                                <div class="product-gst-tag">
                                    GST <?= (int)$product['gst_percent'] ?>%
                                </div>
                            </div>

                            <!-- Bottom section: Form with Variant Selector and Add to Cart -->
                            <form action="<?= public_url('cart.php') ?>" method="POST" class="add-to-cart-form" style="margin-top: var(--space-12); border-top: 1px solid var(--border-color); padding-top: var(--space-12);">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="variant_id_fallback" class="selected-variant-id" value="<?= (int)$firstVariant['id'] ?>">

                                <!-- Pack / Bottle Size Selector -->
                                <div style="margin-bottom: var(--space-8);">
                                    <label style="display: block; font-size: var(--font-size-12); color: var(--text-muted); margin-bottom: 2px;">
                                        Pack Size:
                                    </label>
                                    <select name="variant_id" class="form-control variant-selector" style="width: 100%; min-height: 36px; padding: 6px 8px; font-size: var(--font-size-14);" aria-label="Select size for <?= e($product['name']) ?>">
                                        <?php foreach ($product['variants'] as $idx => $variant): ?>
                                            <option value="<?= (int)$variant['id'] ?>"
                                                    data-price="<?= (float)$variant['price'] ?>"
                                                    data-stock="<?= e($variant['stock_status']) ?>"
                                                    <?= $idx === 0 ? 'selected' : '' ?>>
                                                <?= e($variant['size_label']) ?> &ndash; <?= format_currency($variant['price']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Price & Quantity Stepper Row -->
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-8);">
                                    <div>
                                        <span style="font-size: var(--font-size-12); color: var(--text-muted); display: block; line-height: 1;">Rate</span>
                                        <div class="product-price-display" style="font-size: var(--font-size-16); font-weight: var(--font-weight-semibold); color: var(--text-main); margin-top: 2px;">
                                            <?= format_currency($firstVariant['price']) ?>
                                        </div>
                                    </div>
                                    <div class="qty-control" style="flex-shrink: 0;">
                                        <button type="button" class="qty-btn" data-qty="minus" aria-label="Decrease">&minus;</button>
                                        <input type="number" name="quantity" class="qty-input" value="1" min="1" max="999" aria-label="Quantity">
                                        <button type="button" class="qty-btn" data-qty="plus" aria-label="Increase">+</button>
                                    </div>
                                </div>

                                <!-- Full-Width Add to Cart Button -->
                                <button type="submit" 
                                        class="btn-add-cart add-cart-btn" 
                                        <?= !$isInitialInStock ? 'disabled' : '' ?>>
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="9" cy="21" r="1"></circle>
                                        <circle cx="20" cy="21" r="1"></circle>
                                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                    </svg>
                                    <span class="btn-label">
                                        <?= $isInitialInStock ? 'Add to Cart' : 'Out of Stock' ?>
                                    </span>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card" style="text-align: center; padding: var(--space-48) var(--space-24); margin: var(--space-32) auto; max-width: 500px;">
                    <div style="font-size: var(--font-size-20); font-weight: var(--font-weight-semibold); color: var(--color-navy); margin-bottom: var(--space-8);">
                        No Products Found
                    </div>
                    <p style="font-size: var(--font-size-14); color: var(--text-muted); margin-bottom: var(--space-16);">
                        No products match your selected filters.
                    </p>
                    <a href="<?= public_url('products.php') ?>" class="btn btn-secondary">
                        View All Available Products
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Mobile Bottom Sheet for Filters -->
<div class="bottom-sheet-backdrop" id="mobileFilterBackdrop">
    <div class="bottom-sheet-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-16);">
            <div style="font-size: var(--font-size-16); font-weight: var(--font-weight-semibold); color: var(--color-navy);">
                Filter Catalogue
            </div>
            <button type="button" id="closeMobileFilterBtn" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-muted);">&times;</button>
        </div>

        <!-- Mobile Categories -->
        <div style="margin-bottom: var(--space-16);">
            <div class="filter-group-title">Categories</div>
            <div class="filter-link-list">
                <a href="<?= filter_link(['category' => '']) ?>" 
                   class="filter-item-link <?= empty($selectedCategorySlug) ? 'active' : '' ?>">
                    <span>All Categories</span>
                </a>
                <?php foreach ($categoriesList as $c): 
                    $cUrl = filter_link(['category' => $c['slug']]);
                ?>
                    <a href="<?= $cUrl ?>" class="filter-item-link <?= ($selectedCategorySlug === $c['slug']) ? 'active' : '' ?>">
                        <span><?= e($c['name']) ?></span>
                        <span class="filter-count"><?= (int)$c['product_count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Mobile Brands -->
        <div style="margin-bottom: var(--space-16);">
            <div class="filter-group-title">Brands</div>
            <div class="filter-link-list">
                <a href="<?= filter_link(['brand' => '']) ?>" 
                   class="filter-item-link <?= empty($selectedBrandSlug) ? 'active' : '' ?>">
                    <span>All Brands</span>
                </a>
                <?php foreach ($brandsList as $b): 
                    $bUrl = filter_link(['brand' => $b['slug']]);
                ?>
                    <a href="<?= $bUrl ?>" class="filter-item-link <?= ($selectedBrandSlug === $b['slug']) ? 'active' : '' ?>">
                        <span><?= e($b['name']) ?></span>
                        <span class="filter-count"><?= (int)$b['product_count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Mobile Availability -->
        <div style="margin-bottom: var(--space-24);">
            <div class="filter-group-title">Availability</div>
            <form action="<?= public_url('products.php') ?>" method="GET">
                <?php if (!empty($selectedCategorySlug)): ?>
                    <input type="hidden" name="category" value="<?= e($selectedCategorySlug) ?>">
                <?php endif; ?>
                <?php if (!empty($selectedBrandSlug)): ?>
                    <input type="hidden" name="brand" value="<?= e($selectedBrandSlug) ?>">
                <?php endif; ?>
                <?php if (!empty($searchQuery)): ?>
                    <input type="hidden" name="q" value="<?= e($searchQuery) ?>">
                <?php endif; ?>
                <label class="filter-checkbox-label">
                    <input type="checkbox" name="in_stock" value="1" <?= $inStockOnly ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span>In Stock Only</span>
                </label>
            </form>
        </div>

        <button type="button" class="btn btn-primary" id="applyMobileFilterBtn" style="width: 100%;">
            Done
        </button>
    </div>
</div>

<!-- Variant Switching and Bottom Sheet Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Dynamic Variant Switcher
    document.querySelectorAll('.product-card').forEach((card) => {
        const selector = card.querySelector('.variant-selector');
        const priceDisplay = card.querySelector('.product-price-display');
        const stockBadge = card.querySelector('.stock-badge');
        const hiddenId = card.querySelector('.selected-variant-id');
        const addBtn = card.querySelector('.add-cart-btn');

        if (selector && priceDisplay && stockBadge && addBtn) {
            selector.addEventListener('change', () => {
                const selectedOption = selector.options[selector.selectedIndex];
                const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
                const stock = selectedOption.getAttribute('data-stock');
                const variantId = selectedOption.value;

                if (hiddenId) {
                    hiddenId.value = variantId;
                }

                priceDisplay.textContent = '₹' + price.toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                const btnLabel = addBtn.querySelector('.btn-label') || addBtn.querySelector('.specular-button__label') || addBtn;
                if (stock === 'in_stock') {
                    stockBadge.className = 'badge-stock badge-in-stock stock-badge';
                    stockBadge.textContent = 'In Stock';
                    addBtn.disabled = false;
                    btnLabel.textContent = 'Add to Cart';
                } else {
                    stockBadge.className = 'badge-stock badge-out-of-stock stock-badge';
                    stockBadge.textContent = 'Out of Stock';
                    addBtn.disabled = true;
                    btnLabel.textContent = 'Out of Stock';
                }
            });
        }
    });

    // 2. Mobile Filter Bottom Sheet
    const openSheetBtn = document.getElementById('openMobileFilterBtn');
    const closeSheetBtn = document.getElementById('closeMobileFilterBtn');
    const applySheetBtn = document.getElementById('applyMobileFilterBtn');
    const sheetBackdrop = document.getElementById('mobileFilterBackdrop');

    const openSheet = () => {
        if (sheetBackdrop) {
            sheetBackdrop.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    };

    const closeSheet = () => {
        if (sheetBackdrop) {
            sheetBackdrop.classList.remove('open');
            document.body.style.overflow = '';
        }
    };

    if (openSheetBtn) openSheetBtn.addEventListener('click', openSheet);
    if (closeSheetBtn) closeSheetBtn.addEventListener('click', closeSheet);
    if (applySheetBtn) applySheetBtn.addEventListener('click', closeSheet);

    if (sheetBackdrop) {
        sheetBackdrop.addEventListener('click', (e) => {
            if (e.target === sheetBackdrop) closeSheet();
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
