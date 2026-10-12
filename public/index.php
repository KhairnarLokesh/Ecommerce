<?php
/**
 * Kalika Enterprises - Landing Page
 * Digital Darts Design System Implementation
 * Editorial Typography (Merriweather), Clean UI (Open Sans), 8-Bit Pixel Accents (DotGothic16),
 * Alternating Canvas -> Evergreen Trust Bar -> Obsidian B2B Matrix -> Warm Sand Authority Quote.
 */

declare(strict_types=1);

$pageTitle = 'Wholesale Food and Beverage Distribution';
$currentPage = 'home';
$pageDescription = 'Kalika Enterprises: Food and beverage distribution for Niphad, Dindori, Chandwad, Sinnar, and Yeola. Minimum order Rs 2,000 with free delivery.';

require_once __DIR__ . '/../includes/header.php';

// Fetch active categories and active brands with product count
$activeCategories = [];
$activeBrands = [];
$brandProducts = [];

if ($pdo) {
    try {
        // Categories query with featured product preview image
        $catStmt = $pdo->query("
            SELECT c.id, c.name, c.slug, c.description, COUNT(p.id) as product_count,
                   (
                       SELECT p2.image 
                       FROM products p2 
                       WHERE p2.category_id = c.id AND p2.is_active = 1 AND p2.image IS NOT NULL AND p2.image != ''
                       ORDER BY p2.id ASC 
                       LIMIT 1
                   ) AS sample_image
            FROM categories c
            INNER JOIN products p ON p.category_id = c.id AND p.is_active = 1 AND p.gst_percent IS NOT NULL
            INNER JOIN product_variants v ON v.product_id = p.id AND v.is_active = 1
            WHERE c.is_active = 1
            GROUP BY c.id, c.name, c.slug, c.description, c.sort_order
            ORDER BY c.sort_order ASC, c.name ASC
        ");
        $activeCategories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

        // Brands query with featured product preview image
        $stmt = $pdo->prepare("
            SELECT b.id, b.name, b.slug, COUNT(p.id) as product_count,
                   (
                       SELECT p2.image 
                       FROM products p2 
                       WHERE p2.brand_id = b.id AND p2.is_active = 1 AND p2.image IS NOT NULL AND p2.image != ''
                       ORDER BY p2.id ASC 
                       LIMIT 1
                   ) AS sample_image
            FROM brands b
            INNER JOIN products p ON p.brand_id = b.id AND p.is_active = 1 AND p.gst_percent IS NOT NULL
            INNER JOIN product_variants v ON v.product_id = p.id AND v.is_active = 1
            WHERE b.is_active = 1
            GROUP BY b.id, b.name, b.slug, b.sort_order
            ORDER BY b.sort_order ASC, b.name ASC
        ");
        $stmt->execute();
        $activeBrands = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Products by brand with minimum variant price
        $prodStmt = $pdo->query("
            SELECT p.id, p.name, p.slug, p.image, p.gst_percent, 
                   b.id as brand_id, b.name as brand_name, b.slug as brand_slug,
                   c.name as category_name, c.slug as category_slug,
                   MIN(v.price) as min_price,
                   MIN(v.id) as default_variant_id
            FROM products p
            INNER JOIN brands b ON b.id = p.brand_id
            LEFT JOIN categories c ON c.id = p.category_id
            INNER JOIN product_variants v ON v.product_id = p.id AND v.is_active = 1
            WHERE p.is_active = 1 
              AND p.gst_percent IS NOT NULL 
              AND b.is_active = 1
            GROUP BY p.id, p.name, p.slug, p.image, p.gst_percent, b.id, b.name, b.slug, b.sort_order, c.name, c.slug
            ORDER BY b.sort_order ASC, p.name ASC
        ");
        while ($row = $prodStmt->fetch(PDO::FETCH_ASSOC)) {
            $brandProducts[$row['brand_slug']][] = $row;
        }
    } catch (PDOException $e) {
        error_log("Error fetching home data: " . $e->getMessage());
    }
}

if ((empty($activeBrands) || empty($activeCategories)) && function_exists('get_catalog_fallback')) {
    $fallbackCatalog = get_catalog_fallback();
    if (empty($activeCategories)) {
        $activeCategories = $fallbackCatalog['categories'] ?? [];
        foreach ($activeCategories as &$ac) {
            foreach ($fallbackCatalog['products'] ?? [] as $fp) {
                if (($fp['category_id'] ?? 0) == $ac['id'] && !empty($fp['image'])) {
                    $ac['sample_image'] = $fp['image'];
                    break;
                }
            }
        }
        unset($ac);
    }
    if (empty($activeBrands)) {
        $activeBrands = $fallbackCatalog['brands'] ?? [];
        foreach ($activeBrands as &$ab) {
            foreach ($fallbackCatalog['products'] ?? [] as $fp) {
                if ($fp['brand_id'] == $ab['id'] && !empty($fp['image'])) {
                    $ab['sample_image'] = $fp['image'];
                    break;
                }
            }
        }
        unset($ab);
    }

    foreach ($fallbackCatalog['products'] ?? [] as $fp) {
        $minPrice = null;
        $defVarId = null;
        foreach ($fp['variants'] ?? [] as $fv) {
            if ($minPrice === null || (float)$fv['price'] < $minPrice) {
                $minPrice = (float)$fv['price'];
                $defVarId = $fv['id'];
            }
        }
        $brandProducts[$fp['brand_slug']][] = [
            'id' => $fp['id'],
            'name' => $fp['name'],
            'slug' => $fp['slug'],
            'image' => $fp['image'],
            'gst_percent' => $fp['gst_percent'],
            'brand_id' => $fp['brand_id'],
            'brand_name' => $fp['brand_name'],
            'brand_slug' => $fp['brand_slug'],
            'min_price' => $minPrice,
            'default_variant_id' => $defVarId,
        ];
    }
}
?>

<!-- 1. Hero Section: Editorial Authority & Dual CTAs (Digital Darts Style) -->
<section class="dd-hero-section">
    <!-- Ambient Pixel Mosaic Clusters (Top-Left & Bottom-Right) -->
    <div class="dd-pixel-cluster dd-pixel-tl" aria-hidden="true">
        <span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span>
    </div>
    <div class="dd-pixel-cluster dd-pixel-br" aria-hidden="true">
        <span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span>
    </div>

    <div class="container">
        <div class="dd-hero-content">
            <h1 class="dd-hero-title">
                The Wholesale FMCG Supply Regional Stores Depend On For Consistent Profit
            </h1>
            <p class="dd-hero-subtitle">
                Reliable food and beverage distribution serving Niphad, Dindori, Chandwad, Sinnar, and Yeola. 
                Free delivery on orders of Rs 2,000+, 100% compliant GST invoicing, and dispatch in 2&ndash;3 working days.
            </p>
            <div class="dd-hero-cta-group">
                <a href="<?= public_url('products.php') ?>" class="btn-dd-primary">
                    <span>ORDER WHOLESALE STOCK</span>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-left: 6px;"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </a>
                <a href="<?= public_url('policies.php') ?>" class="btn-dd-ghost">
                    DISTRIBUTION POLICIES
                </a>
            </div>
        </div>
    </div>
</section>

<!-- 2. Trust Bar: Full-Bleed Evergreen Strip with Moving Brand Marks -->
<?php
$brandLogosList = [
    ['name' => 'Campa Beverages', 'slug' => 'campa', 'logo' => 'campa.svg'],
    ['name' => 'Sundar Biscuits', 'slug' => 'sundar-biscuits', 'logo' => 'sundar.svg'],
    ['name' => 'Euro Crispy Chips', 'slug' => 'euro-chips', 'logo' => 'euro-chips.svg'],
    ['name' => 'Thums Up', 'slug' => 'coca-cola-range', 'logo' => 'thums-up.svg'],
    ['name' => 'Coca-Cola', 'slug' => 'coca-cola-range', 'logo' => 'coca-cola.svg'],
];
?>
<section class="dd-trust-bar" aria-label="Confirmed brands distribution">
    <div class="dd-trust-eyebrow">
        Confirmed Brands In Direct Regional Distribution
    </div>
    <div class="dd-trust-marquee">
        <div class="dd-trust-track">
            <?php for ($loop = 1; $loop <= 3; $loop++): ?>
                <?php foreach ($brandLogosList as $bLogo): ?>
                    <a href="<?= public_url('products.php?brand=' . urlencode($bLogo['slug'])) ?>" 
                       class="dd-brand-badge-pill--logo"
                       title="<?= e($bLogo['name']) ?>"
                       <?= $loop > 1 ? 'aria-hidden="true"' : '' ?>>
                        <img src="<?= public_url('assets/img/brands/' . $bLogo['logo']) ?>" 
                             alt="<?= e($bLogo['name']) ?>" 
                             class="marquee-brand-logo"
                             loading="lazy">
                    </a>
                <?php endforeach; ?>
            <?php endfor; ?>
        </div>
    </div>
</section>

<div class="container">
    <!-- 2.5 Shop by Category Grid -->
    <section class="landing-section" style="padding-top: var(--space-48);">
        <div class="section-header">
            <div>
                <h2 class="section-title">Shop by Category</h2>
                <p class="section-sub-copy">Direct distribution across cold drinks, fruit beverages, mineral water, biscuits &amp; savory snacks</p>
            </div>
            <a href="<?= public_url('products.php') ?>" class="section-link">
                View All Categories
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align: -1px; margin-left: 2px;"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
        </div>
        <div class="brand-tiles-grid" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));">
            <?php foreach ($activeCategories as $cat): ?>
                <a href="<?= public_url('products.php?category=' . urlencode($cat['slug'])) ?>" class="brand-tile-card">
                    <div class="brand-tile-media" style="background: #ffffff; padding: 12px; display: flex; align-items: center; justify-content: center; height: 160px;">
                        <?php if (!empty($cat['sample_image']) && file_exists(__DIR__ . '/uploads/products/' . $cat['sample_image'])): ?>
                            <img 
                                src="<?= public_url('uploads/products/' . rawurlencode($cat['sample_image'])) ?>" 
                                alt="<?= e($cat['name']) ?>" 
                                class="brand-tile-img"
                                style="max-height: 130px; object-fit: contain;"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <div class="brand-tile-icon-square" aria-hidden="true">
                                <?= strtoupper(substr($cat['name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="brand-tile-body">
                        <div class="brand-tile-name" style="font-size: 15px; font-weight: 700; color: var(--color-navy);"><?= e($cat['name']) ?></div>
                        <div class="brand-tile-count"><?= (int)$cat['product_count'] ?> Products In Stock</div>
                        <span class="brand-tile-cta" style="margin-top: 6px;">
                            Browse Category
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align: -1px; margin-left: 2px;"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 3. Shop by Brand Grid (Clean White Cards) -->
    <section class="landing-section" style="padding-top: var(--space-32);">
        <div class="section-header">
            <div>
                <h2 class="section-title">Shop by Brand</h2>
                <p class="section-sub-copy">Confirmed manufacturer product ranges ready for carton dispatch</p>
            </div>
            <a href="<?= public_url('products.php') ?>" class="section-link">
                View All Brands
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align: -1px; margin-left: 2px;"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
        </div>
        <div class="brand-tiles-grid">
            <?php foreach ($activeBrands as $brand): ?>
                <a href="<?= public_url('products.php?brand=' . urlencode($brand['slug'])) ?>" class="brand-tile-card">
                    <div class="brand-tile-media">
                        <?php if (!empty($brand['sample_image']) && file_exists(__DIR__ . '/uploads/products/' . $brand['sample_image'])): ?>
                            <img 
                                src="<?= public_url('uploads/products/' . rawurlencode($brand['sample_image'])) ?>" 
                                alt="<?= e($brand['name']) ?>" 
                                class="brand-tile-img"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <div class="brand-tile-icon-square" aria-hidden="true">
                                <?= strtoupper(substr($brand['name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="brand-tile-body">
                        <div class="brand-tile-name"><?= e($brand['name']) ?></div>
                        <div class="brand-tile-count"><?= (int)$brand['product_count'] ?> Products In Stock</div>
                        <span class="brand-tile-cta">
                            View Products
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align: -1px; margin-left: 2px;"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<!-- 4. The Wholesale Operations Matrix (Dark Obsidian #09120E Container, identical to Digital Darts Course Matrix) -->
<section class="dd-obsidian-section" aria-label="Wholesale distribution advantages">
    <div class="container">
        <div class="dd-obsidian-layout">
            <!-- Left Sticky Column -->
            <div class="dd-obsidian-sticky-left">
                <h2 class="dd-obsidian-title">
                    Wholesale Operations Built For Store Profit
                </h2>
                <p class="dd-obsidian-copy">
                    Every element of Kalika Enterprises is structured around retailer margin protection, timely inventory delivery, and zero freight penalties.
                </p>
                <div style="margin-top: 32px;">
                    <a href="<?= public_url('products.php') ?>" class="btn-dd-inverted-ghost">
                        <span>BROWSE FULL CATALOGUE</span>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-left: 6px;"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>
                </div>
            </div>

            <!-- Right 8-Item Framed Matrix (DotGothic16 Pixel Numbers) -->
            <div class="dd-obsidian-card-right">
                <div class="dd-matrix-grid">
                    <!-- 01 -->
                    <div class="dd-matrix-item">
                        <div class="dd-pixel-num">01.</div>
                        <div class="dd-matrix-text">
                            <h3 class="dd-matrix-heading">100% Compliant GST Invoicing</h3>
                            <p class="dd-matrix-desc">Every order is billed with verified GST rates and valid HSN codes so your retail store claims complete input tax credit.</p>
                        </div>
                    </div>

                    <!-- 02 -->
                    <div class="dd-matrix-item">
                        <div class="dd-pixel-num">02.</div>
                        <div class="dd-matrix-text">
                            <h3 class="dd-matrix-heading">Free Delivery On Orders Rs 2,000+</h3>
                            <p class="dd-matrix-desc">Low wholesale threshold keeps your working capital liquid while eliminating delivery deductions from your store margin.</p>
                        </div>
                    </div>

                    <!-- 03 -->
                    <div class="dd-matrix-item">
                        <div class="dd-pixel-num">03.</div>
                        <div class="dd-matrix-text">
                            <h3 class="dd-matrix-heading">Scheduled Route Van Delivery</h3>
                            <p class="dd-matrix-desc">Direct dispatch vans running predictable schedules across Niphad, Dindori, Chandwad, Sinnar, and Yeola in 2&ndash;3 working days.</p>
                        </div>
                    </div>

                    <!-- 04 -->
                    <div class="dd-matrix-item">
                        <div class="dd-pixel-num">04.</div>
                        <div class="dd-matrix-text">
                            <h3 class="dd-matrix-heading">High-Demand Brand Inventory</h3>
                            <p class="dd-matrix-desc">Bulk cartons of Campa Cola, Orange, Lemon, Sundar Marie biscuits, and Euro chips with verified expiry dates.</p>
                        </div>
                    </div>

                    <!-- 05 -->
                    <div class="dd-matrix-item">
                        <div class="dd-pixel-num">05.</div>
                        <div class="dd-matrix-text">
                            <h3 class="dd-matrix-heading">24-Hour Order Confirmation</h3>
                            <p class="dd-matrix-desc">Orders are promptly reviewed, validated against real warehouse stock, and assigned to delivery manifests within 24 hours.</p>
                        </div>
                    </div>

                    <!-- 06 -->
                    <div class="dd-matrix-item">
                        <div class="dd-pixel-num">06.</div>
                        <div class="dd-matrix-text">
                            <h3 class="dd-matrix-heading">2-Day Return &amp; Refund Policy</h3>
                            <p class="dd-matrix-desc">Any defective or transit-damaged items reported within 2 working days are resolved with credit or replacement subject to conditions.</p>
                        </div>
                    </div>

                    <!-- 07 -->
                    <div class="dd-matrix-item">
                        <div class="dd-pixel-num">07.</div>
                        <div class="dd-matrix-text">
                            <h3 class="dd-matrix-heading">Transparent Wholesale Slabs</h3>
                            <p class="dd-matrix-desc">Clean pricing structure without middleman markups so retail shops earn competitive margins on fast-moving goods.</p>
                        </div>
                    </div>

                    <!-- 08 -->
                    <div class="dd-matrix-item">
                        <div class="dd-pixel-num">08.</div>
                        <div class="dd-matrix-text">
                            <h3 class="dd-matrix-heading">Pay On Delivery (Cash or Driver UPI)</h3>
                            <p class="dd-matrix-desc">Inspect your physical stock cartons at your shop counter before completing payment. No online card surcharges.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 5. Authority & Founder Commitment (Warm Sand #FEF3E2 Section) -->
<section class="dd-sand-section" aria-label="Distribution commitment">
    <div class="container">
        <div class="dd-sand-card">
            <!-- Amber Mosaic Decorative Grid -->
            <div class="dd-amber-mosaic" aria-hidden="true">
                <span></span><span></span><span></span>
                <span></span><span></span><span></span>
                <span></span><span></span><span></span>
            </div>

            <div class="dd-sand-content">
                <div class="dd-quote-symbol">&ldquo;</div>
                <blockquote class="dd-quote-text">
                    Your retail store grows when you have dependable, scheduled supply without unexpected stockouts or inflated distributor markups. Kalika Enterprises was established to bring verified food and beverage brands directly to regional stores on predictable van routes. We measure our success by your store&rsquo;s steady shelf turnover.
                </blockquote>
                <div class="dd-quote-attribution">
                    <div class="dd-author-name">Deepak Pawar</div>
                    <div class="dd-author-role">Proprietor, Kalika Enterprises &bull; Regional Distribution Depot, Pimpalgaon (Baswant)</div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container">
    <!-- 6. Product Showcase Rows by Brand (Horizontally Scrollable Strips) -->
    <?php foreach ($activeBrands as $brand): 
        $slug = $brand['slug'];
        $items = $brandProducts[$slug] ?? [];
        if (empty($items)) continue;
    ?>
        <section class="landing-section">
            <div class="section-header">
                <div>
                    <h2 class="section-title"><?= e($brand['name']) ?></h2>
                    <p class="section-sub-copy">Direct manufacturer packs in stock</p>
                </div>
                <div class="section-header-actions">
                    <div class="row-scroll-nav">
                        <button type="button" class="btn-scroll-arrow btn-scroll-prev" data-target="scroll-<?= $slug ?>" aria-label="Previous products">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        </button>
                        <button type="button" class="btn-scroll-arrow btn-scroll-next" data-target="scroll-<?= $slug ?>" aria-label="Next products">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </button>
                    </div>
                    <a href="<?= public_url('products.php?brand=' . urlencode($slug)) ?>" class="section-link">
                        View All
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align: -1px; margin-left: 2px;"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>
                </div>
            </div>
            <div class="product-row-scroll" id="scroll-<?= $slug ?>">
                <?php foreach ($items as $item): ?>
                    <div class="product-card">
                        <!-- Image Container: 1:1, object-fit: contain, 8px padding -->
                        <div class="product-img-box">
                            <?php if (!empty($item['image']) && file_exists(__DIR__ . '/uploads/products/' . $item['image'])): ?>
                                <img 
                                    src="<?= public_url('uploads/products/' . rawurlencode($item['image'])) ?>" 
                                    alt="<?= e($item['name']) ?>" 
                                    class="product-img-tag"
                                    loading="lazy"
                                    width="160"
                                    height="160"
                                >
                            <?php else: ?>
                                <div class="product-placeholder-box">
                                    <div class="product-placeholder-brand"><?= e($brand['name']) ?></div>
                                    <div class="product-placeholder-sub">Photo coming soon</div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Product Details -->
                        <div>
                            <div class="product-info-title"><?= e($item['name']) ?></div>
                            <div class="product-price-line">
                                from <?= format_currency((float)$item['min_price']) ?>
                            </div>
                            <div class="product-gst-tag">
                                GST <?= (int)$item['gst_percent'] ?>%
                            </div>
                            <div style="margin-bottom: 8px;">
                                <span class="badge-stock badge-in-stock">In Stock</span>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <form action="<?= public_url('cart.php') ?>" method="POST" class="add-to-cart-form" style="margin-top: 10px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="variant_id" value="<?= (int)$item['default_variant_id'] ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" 
                                    class="btn-add-cart" 
                                    aria-label="Add <?= e($item['name']) ?> to wholesale cart">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="9" cy="21" r="1"></circle>
                                    <circle cx="20" cy="21" r="1"></circle>
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                </svg>
                                <span>Add to Cart</span>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <!-- 7. Service Areas & Warehouse Dispatch Block (Digital Darts Framed Section) -->
    <section class="landing-section">
        <div class="dd-lead-frame-card">
            <!-- Decorative Pixel Squares -->
            <div class="dd-pixel-cluster dd-pixel-tl" aria-hidden="true">
                <span></span><span></span><span></span><span></span>
            </div>
            <div class="dd-pixel-cluster dd-pixel-br" aria-hidden="true">
                <span></span><span></span><span></span><span></span>
            </div>

            <h2 class="dd-lead-heading">REGIONAL SERVICE AREAS &amp; DISPATCH</h2>
            <p class="dd-lead-sub">
                Scheduled delivery routes operating across the following talukas in Nashik district:
            </p>
            <div class="service-chips-row" style="margin-bottom: 24px;">
                <?php 
                $areas = explode(',', get_setting('service_areas', 'Niphad, Dindori, Chandwad, Sinnar, Yeola'));
                foreach ($areas as $area): 
                ?>
                    <span class="service-area-chip"><?= e(trim($area)) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="dd-warehouse-info">
                <strong>Depot Address:</strong> <?= e(get_setting('business_address', 'Shop No. 4, Raghukul Sankul, Jopul Road, Pimpalgaon (Baswant), Tal. Niphad, Dist. Nashik, Maharashtra – 422201')) ?><br>
                <strong>Inquiries:</strong> <a href="mailto:<?= e(get_setting('business_email', 'deepakpawar4312@gmail.com')) ?>" style="color: var(--color-pine); font-weight: 600;"><?= e(get_setting('business_email', 'deepakpawar4312@gmail.com')) ?></a> &bull; Minimum Order Rs 2,000 &bull; Free Delivery
            </div>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.btn-scroll-arrow').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const row = document.getElementById(targetId);
            if (row) {
                const scrollAmount = row.clientWidth * 0.75;
                if (btn.classList.contains('btn-scroll-prev')) {
                    row.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
                } else {
                    row.scrollBy({ left: scrollAmount, behavior: 'smooth' });
                }
            }
        });
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
