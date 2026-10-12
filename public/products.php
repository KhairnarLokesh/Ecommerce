<?php
/**
 * Kalika Enterprises - Products Catalogue
 * Step 4 & 5: Standardized marketplace product listing with left filter sidebar,
 * mobile bottom sheet, 1:1 image system, variant selection, and strict tokens.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Filter parameters from URL
$selectedBrandSlug = trim($_GET['brand'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');
$inStockOnly = (isset($_GET['in_stock']) && $_GET['in_stock'] === '1');

$brandsList = [];
$products = [];
$totalProducts = 0;
$currentBrandName = '';

if ($pdo) {
    try {
        // 1. Fetch active brands that have active products with confirmed GST and product counts
        $brandStmt = $pdo->query("
            SELECT b.id, b.name, b.slug, COUNT(p.id) as product_count
            FROM brands b
            INNER JOIN products p ON p.brand_id = b.id AND p.is_active = 1 AND p.gst_percent IS NOT NULL
            INNER JOIN product_variants v ON v.product_id = p.id AND v.is_active = 1
            WHERE b.is_active = 1
            GROUP BY b.id, b.name, b.slug
            ORDER BY b.sort_order ASC, b.name ASC
        ");
        $brandsList = $brandStmt->fetchAll();

        foreach ($brandsList as $b) {
            if ($b['slug'] === $selectedBrandSlug) {
                $currentBrandName = $b['name'];
                break;
            }
        }

        // 2. Build parameterized query for products
        $sql = "
            SELECT 
                p.id, p.name, p.slug, p.image, p.description, p.gst_percent,
                b.id AS brand_id, b.name AS brand_name, b.slug AS brand_slug
            FROM products p
            INNER JOIN brands b ON b.id = p.brand_id
            WHERE p.is_active = 1
              AND p.gst_percent IS NOT NULL
              AND b.is_active = 1
              AND EXISTS (
                  SELECT 1 FROM product_variants v 
                  WHERE v.product_id = p.id AND v.is_active = 1
              )
        ";
        $params = [];

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
            $sql .= " AND (p.name LIKE :q OR b.name LIKE :q OR p.description LIKE :q)";
            $params[':q'] = '%' . $searchQuery . '%';
        }

        $sql .= " ORDER BY b.sort_order ASC, p.name ASC";

        $productStmt = $pdo->prepare($sql);
        $productStmt->execute($params);
        $rawProducts = $productStmt->fetchAll();

        // 3. Fetch active variants for each retrieved product
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

// Meta tags
$pageTitle = !empty($currentBrandName) ? $currentBrandName . ' Range' : 'Wholesale Product Catalogue';
$currentPage = 'products';
$pageDescription = 'Browse wholesale distribution catalogue for Campa, Sundar, and Euro. Minimum order Rs 2,000 with free delivery across Niphad, Dindori, Chandwad, Sinnar, and Yeola.';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top: var(--space-24); padding-bottom: var(--space-64);">
    <!-- Catalogue Title Banner -->
    <div style="margin-bottom: var(--space-24);">
        <h1 style="font-size: var(--font-size-24); font-weight: var(--font-weight-semibold); color: var(--color-navy); margin-bottom: var(--space-4);">
            <?= !empty($currentBrandName) ? e($currentBrandName) . ' Range' : 'Wholesale Product Catalogue' ?>
        </h1>
        <p style="font-size: var(--font-size-14); color: var(--text-muted); margin-bottom: 0;">
            Minimum order Rs 2,000 &bull; Free delivery on Rs 2,000+ &bull; Delivery in 2-3 working days
        </p>
    </div>

    <?php if (!$pdo && !empty($GLOBALS['db_connection_error'])): ?>
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
            <?php if (!empty($selectedBrandSlug) || $inStockOnly || !empty($searchQuery)): ?>
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
                <?php if (!empty($selectedBrandSlug) || $inStockOnly || !empty($searchQuery)): ?>
                    <a href="<?= public_url('products.php') ?>" style="font-size: var(--font-size-12); color: var(--color-orange); font-weight: 400;">
                        Reset All
                    </a>
                <?php endif; ?>
            </div>

            <!-- Brand Filter -->
            <div class="sidebar-filter-section">
                <div class="filter-group-title">Brands</div>
                <div class="filter-link-list">
                    <a href="<?= public_url('products.php' . ($inStockOnly ? '?in_stock=1' : '') . (!empty($searchQuery) ? ($inStockOnly ? '&' : '?') . 'q=' . urlencode($searchQuery) : '')) ?>" 
                       class="filter-item-link <?= empty($selectedBrandSlug) ? 'active' : '' ?>">
                        <span>All Brands</span>
                    </a>
                    <?php foreach ($brandsList as $b): 
                        $brandUrl = public_url('products.php?brand=' . urlencode($b['slug']) . ($inStockOnly ? '&in_stock=1' : '') . (!empty($searchQuery) ? '&q=' . urlencode($searchQuery) : ''));
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
            <div class="catalogue-header-bar">
                <div style="font-size: var(--font-size-14); color: var(--text-muted);">
                    Showing <strong style="color: var(--text-main);"><?= $totalProducts ?></strong> products
                    <?php if (!empty($selectedBrandSlug)): ?>
                        in <strong style="color: var(--text-main);"><?= e($currentBrandName) ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($searchQuery)): ?>
                        matching "<strong><?= e($searchQuery) ?></strong>"
                    <?php endif; ?>
                </div>
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
                                <!-- Step 4 Image System: 1:1 container, contain, 8px padding, white background or grey fallback -->
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

                                <!-- Brand badge & Stock status -->
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4);">
                                    <span style="font-size: var(--font-size-12); font-weight: var(--font-weight-semibold); color: var(--text-muted);">
                                        <?= e($product['brand_name']) ?>
                                    </span>
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
                                <input type="hidden" name="variant_id" class="selected-variant-id" value="<?= (int)$firstVariant['id'] ?>">

                                <!-- Pack / Bottle Size Selector -->
                                <div style="margin-bottom: var(--space-8);">
                                    <label style="display: block; font-size: var(--font-size-12); color: var(--text-muted); margin-bottom: 2px;">
                                        Pack Size:
                                    </label>
                                    <select class="form-control variant-selector" style="width: 100%; min-height: 36px; padding: 6px 8px; font-size: var(--font-size-14);" aria-label="Select size for <?= e($product['name']) ?>">
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

                                <!-- Full-Width Add to Cart Button (SpecularButton) -->
                                <button type="submit" 
                                        class="specular-button specular-button--sm specular-button--cart add-cart-btn" 
                                        data-radius="8" 
                                        data-line-color="#00E599" 
                                        data-base-color="#0F382A" 
                                        data-intensity="1.1" 
                                        data-idle-intensity="0.35" 
                                        data-thickness="1.3" 
                                        data-glow="1.1" 
                                        <?= !$isInitialInStock ? 'disabled' : '' ?>
                                        style="width: 100%; padding: 10px 14px;">
                                    <span class="specular-button__label">
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

        <!-- Mobile Brands -->
        <div style="margin-bottom: var(--space-16);">
            <div class="filter-group-title">Brands</div>
            <div class="filter-link-list">
                <a href="<?= public_url('products.php' . ($inStockOnly ? '?in_stock=1' : '') . (!empty($searchQuery) ? ($inStockOnly ? '&' : '?') . 'q=' . urlencode($searchQuery) : '')) ?>" 
                   class="filter-item-link <?= empty($selectedBrandSlug) ? 'active' : '' ?>">
                    <span>All Brands</span>
                </a>
                <?php foreach ($brandsList as $b): 
                    $bUrl = public_url('products.php?brand=' . urlencode($b['slug']) . ($inStockOnly ? '&in_stock=1' : '') . (!empty($searchQuery) ? '&q=' . urlencode($searchQuery) : ''));
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

        if (selector && priceDisplay && stockBadge && hiddenId && addBtn) {
            selector.addEventListener('change', () => {
                const selectedOption = selector.options[selector.selectedIndex];
                const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
                const stock = selectedOption.getAttribute('data-stock');
                const variantId = selectedOption.value;

                hiddenId.value = variantId;

                priceDisplay.textContent = '₹' + price.toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                const btnLabel = addBtn.querySelector('.specular-button__label') || addBtn;
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
