<?php
/**
 * Kalika Enterprises - Bulk Stock & Inventory Manager
 * Phase 7: Quick table for mass updating variant stock quantities and availability flags.
 */

declare(strict_types=1);

$adminPage = 'stock';
$adminTitle = 'Bulk Stock Manager';

require_once __DIR__ . '/includes/header.php';

$error = '';
$categoryFilter = (int)($_GET['category'] ?? 0);
$brandFilter = (int)($_GET['brand'] ?? 0);
$stockFilter = trim($_GET['stock'] ?? '');

// Handle Bulk Stock Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Security verification failed. Please try again.';
    } else {
        $stockData = $_POST['stock'] ?? [];

        if (is_array($stockData) && $pdo) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    UPDATE product_variants
                    SET stock_qty = ?,
                        stock_status = ?,
                        is_active = ?
                    WHERE id = ?
                ");

                $updatedCount = 0;
                foreach ($stockData as $variantId => $row) {
                    $variantId = (int)$variantId;
                    $qty = max(0, min(99999, (int)($row['qty'] ?? 0)));
                    $status = ($row['status'] ?? 'in_stock') === 'out_of_stock' ? 'out_of_stock' : 'in_stock';
                    
                    // Auto-sync status: if quantity is 0, default to out_of_stock unless overridden
                    if ($qty === 0) {
                        $status = 'out_of_stock';
                    }

                    $isActive = isset($row['is_active']) ? 1 : 0;

                    $stmt->execute([$qty, $status, $isActive, $variantId]);
                    $updatedCount++;
                    admin_audit_log('update', 'stock', (string)$variantId, null, [
                        'stock_qty' => $qty,
                        'stock_status' => $status,
                        'is_active' => $isActive
                    ]);
                }

                $pdo->commit();
                set_flash('success', "Bulk inventory updated! {$updatedCount} variant stock records saved.");
                $redirectUrl = 'stock.php?';
                if ($categoryFilter > 0) $redirectUrl .= 'category=' . $categoryFilter . '&';
                if ($brandFilter > 0) $redirectUrl .= 'brand=' . $brandFilter . '&';
                if (!empty($stockFilter)) $redirectUrl .= 'stock=' . urlencode($stockFilter);
                redirect(admin_url(rtrim($redirectUrl, '?&')));

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Bulk stock update failed: " . $e->getMessage());
                $error = 'Failed to update stock: ' . $e->getMessage();
            }
        }
    }
}

// Fetch categories & brands for filtering
$categories = [];
$brands = [];
if ($pdo) {
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY sort_order ASC, name ASC")->fetchAll();
    $brands = $pdo->query("SELECT id, name FROM brands ORDER BY name ASC")->fetchAll();
}

// Build query
$whereClauses = [];
$params = [];

if ($categoryFilter > 0) {
    $whereClauses[] = "p.category_id = ?";
    $params[] = $categoryFilter;
}

if ($brandFilter > 0) {
    $whereClauses[] = "p.brand_id = ?";
    $params[] = $brandFilter;
}

if ($stockFilter === 'low') {
    $whereClauses[] = "v.stock_qty <= 10";
} elseif ($stockFilter === 'out') {
    $whereClauses[] = "(v.stock_status = 'out_of_stock' OR v.stock_qty = 0)";
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : "";

$variants = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                v.id,
                v.size_label,
                v.price,
                v.stock_qty,
                v.stock_status,
                v.is_active AS variant_active,
                p.id AS product_id,
                p.name AS product_name,
                p.is_active AS product_active,
                b.name AS brand_name,
                c.name AS category_name
            FROM product_variants v
            INNER JOIN products p ON p.id = v.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            INNER JOIN brands b ON b.id = p.brand_id
            {$whereSql}
            ORDER BY c.sort_order ASC, b.name ASC, p.name ASC, v.price ASC
        ");
        $stmt->execute($params);
        $variants = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch variants for stock manager: " . $e->getMessage());
    }
}
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; margin-bottom: 0.25rem;">Bulk Inventory &amp; Stock Manager</h1>
            <p style="font-size: 0.9375rem; color: var(--text-muted); margin-bottom: 0;">
                Quickly adjust crate quantities, restock items, and toggle out-of-stock statuses across all products.
            </p>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-error" role="alert" style="margin-bottom: 1.5rem;">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<!-- Filter Toolbar -->
<div class="card" style="padding: 1.25rem 1.5rem; margin-bottom: 2rem;">
    <form action="<?= admin_url('stock.php') ?>" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
        <label style="font-size: 0.8125rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Filter By:</label>
        
        <select name="category" class="form-control" style="width: auto; min-width: 180px; font-size: 0.875rem;" onchange="this.form.submit()">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $categoryFilter === (int)$c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="brand" class="form-control" style="width: auto; min-width: 180px; font-size: 0.875rem;" onchange="this.form.submit()">
            <option value="0">All Brands</option>
            <?php foreach ($brands as $b): ?>
                <option value="<?= (int)$b['id'] ?>" <?= $brandFilter === (int)$b['id'] ? 'selected' : '' ?>>
                    <?= e($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="stock" class="form-control" style="width: auto; min-width: 180px; font-size: 0.875rem;" onchange="this.form.submit()">
            <option value="">All Inventory Levels</option>
            <option value="low" <?= $stockFilter === 'low' ? 'selected' : '' ?>>Low Stock (&le; 10 units)</option>
            <option value="out" <?= $stockFilter === 'out' ? 'selected' : '' ?>>Out of Stock Only</option>
        </select>

        <?php if ($categoryFilter > 0 || $brandFilter > 0 || !empty($stockFilter)): ?>
            <a href="<?= admin_url('stock.php') ?>" class="btn btn-sm btn-outline">Reset Filters</a>
        <?php endif; ?>
    </form>
</div>

<!-- Bulk Stock Update Form -->
<?php
$currentQuery = http_build_query(array_filter([
    'category' => $categoryFilter ?: null,
    'brand' => $brandFilter ?: null,
    'stock' => $stockFilter ?: null
]));
?>
<form action="<?= admin_url('stock.php' . ($currentQuery ? '?' . $currentQuery : '')) ?>" method="POST">
    <?= csrf_field() ?>

    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-light);">
            <div style="font-weight: 700; color: var(--color-primary); font-size: 1.1rem;">
                Displaying <?= count($variants) ?> Variant(s)
            </div>
            <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.65rem 1.75rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
                Save All Inventory Updates
            </button>
        </div>

        <?php if (empty($variants)): ?>
            <p style="text-align: center; color: var(--text-muted); padding: 3rem 1rem; margin: 0;">
                No variants match the selected filter criteria.
            </p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color); font-size: 0.8125rem; color: var(--text-muted); text-transform: uppercase;">
                            <th style="padding: 0.75rem 0.5rem;">Product, Category &amp; Brand</th>
                            <th style="padding: 0.75rem 0.5rem;">Pack / Variant Size</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: right;">Unit Rate</th>
                            <th style="padding: 0.75rem 0.5rem; width: 140px; text-align: center;">Stock Qty</th>
                            <th style="padding: 0.75rem 0.5rem; width: 170px;">Stock Status</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: center; width: 90px;">Active</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: right;">Product Link</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($variants as $v): ?>
                            <?php 
                            $varId = (int)$v['id'];
                            $isLow = ((int)$v['stock_qty'] <= 10);
                            $isOut = ($v['stock_status'] === 'out_of_stock' || (int)$v['stock_qty'] === 0);
                            ?>
                            <tr style="border-bottom: 1px solid var(--border-light); font-size: 0.9375rem; <?= $isOut ? 'background: #fff1f2;' : ($isLow ? 'background: #fffbeb;' : '') ?>">
                                <!-- Product & Brand -->
                                <td style="padding: 0.75rem 0.5rem;">
                                    <strong style="color: var(--color-primary);"><?= e($v['product_name']) ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.4rem; margin-top: 0.15rem;">
                                        <span><?= e($v['brand_name']) ?></span>
                                        <?php if (!empty($v['category_name'])): ?>
                                            <span>&bull;</span>
                                            <span style="color: var(--color-primary); font-weight: 500;"><?= e($v['category_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Size Label -->
                                <td style="padding: 0.75rem 0.5rem; font-weight: 600;">
                                    <?= e($v['size_label']) ?>
                                </td>

                                <!-- Unit Rate -->
                                <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 700; color: var(--color-primary);">
                                    <?= format_currency($v['price']) ?>
                                </td>

                                <!-- Stock Qty Input -->
                                <td style="padding: 0.75rem 0.5rem; text-align: center;">
                                    <input type="number" min="0" max="99999" name="stock[<?= $varId ?>][qty]" class="form-control" value="<?= (int)$v['stock_qty'] ?>" required style="text-align: center; font-weight: 700; font-size: 0.9375rem; <?= $isLow ? 'border-color: #dc2626; color: #dc2626;' : '' ?>">
                                </td>

                                <!-- Stock Status Dropdown -->
                                <td style="padding: 0.75rem 0.5rem;">
                                    <select name="stock[<?= $varId ?>][status]" class="form-control" style="font-size: 0.875rem; font-weight: 600; <?= $isOut ? 'color: #dc2626;' : 'color: #059669;' ?>">
                                        <option value="in_stock" <?= $v['stock_status'] === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                                        <option value="out_of_stock" <?= $v['stock_status'] === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                                    </select>
                                </td>

                                <!-- Active Checkbox -->
                                <td style="padding: 0.75rem 0.5rem; text-align: center;">
                                    <input type="checkbox" name="stock[<?= $varId ?>][is_active]" value="1" <?= (int)$v['variant_active'] === 1 ? 'checked' : '' ?>>
                                </td>

                                <!-- Edit Product -->
                                <td style="padding: 0.75rem 0.5rem; text-align: right;">
                                    <a href="<?= admin_url('product-edit.php?id=' . (int)$v['product_id']) ?>" class="btn btn-sm btn-outline" style="font-size: 0.75rem;">
                                        Edit SKU
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-light); display: flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.75rem 2rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Save All Inventory Updates
                </button>
            </div>
        <?php endif; ?>
    </div>
</form>

<?php
require_once __DIR__ . '/includes/footer.php';
