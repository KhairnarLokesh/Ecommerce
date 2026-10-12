<?php
/**
 * Kalika Enterprises - Products Management Directory
 * Phase 7: Search, brand filters, active/inactive toggles, GST compliance guard, and deletion safety.
 */

declare(strict_types=1);

$adminPage = 'products';
$adminTitle = 'Product Catalogue';

require_once __DIR__ . '/includes/header.php';

$error = '';
$searchQuery = trim($_GET['q'] ?? '');
$brandFilter = (int)($_GET['brand'] ?? 0);
$filterGst = trim($_GET['filter'] ?? '');

// Handle Actions: Toggle Active, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Security token invalid or expired. Please refresh and try again.';
    } else {
        $action = trim($_POST['action'] ?? '');
        $productId = (int)($_POST['product_id'] ?? 0);

        if ($action === 'toggle' && $productId > 0 && $pdo) {
            $stmt = $pdo->prepare("SELECT id, name, gst_percent, is_active FROM products WHERE id = ? LIMIT 1");
            $stmt->execute([$productId]);
            $prod = $stmt->fetch();

            if ($prod) {
                $currentActive = (int)$prod['is_active'];
                $newActive = ($currentActive === 1) ? 0 : 1;

                // HARD BUSINESS CONSTRAINT: Cannot activate if GST is empty or null
                if ($newActive === 1 && $prod['gst_percent'] === null) {
                    set_flash('error', "Cannot activate '{$prod['name']}': Official GST percentage is not confirmed yet. Edit the product to set GST before activation.");
                } else {
                    $up = $pdo->prepare("UPDATE products SET is_active = ? WHERE id = ?");
                    $up->execute([$newActive, $productId]);
                    $label = ($newActive === 1) ? 'activated' : 'deactivated';
                    set_flash('success', "Product '{$prod['name']}' has been {$label}.");
                }
            }
            redirect(admin_url('products.php'));
        } elseif ($action === 'delete' && $productId > 0 && $pdo) {
            // Check if product has past order items (match product name or variant)
            $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ? LIMIT 1");
            $stmt->execute([$productId]);
            $prodName = $stmt->fetchColumn();

            $orderCheck = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_name = ?");
            $orderCheck->execute([$prodName]);
            $orderCount = (int)$orderCheck->fetchColumn();

            if ($orderCount > 0) {
                // Soft delete to protect historical tax invoice records
                $up = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
                $up->execute([$productId]);
                set_flash('warning', "Product '{$prodName}' has {$orderCount} past customer order line(s). To protect historical tax invoices, it has been soft-deleted (deactivated) instead of permanently removed.");
            } else {
                // Safe hard delete (cascades to product_variants)
                $del = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $del->execute([$productId]);
                set_flash('success', "Product '{$prodName}' and its variants have been permanently removed.");
            }
            redirect(admin_url('products.php'));
        }
    }
}

// Fetch all active brands for filter dropdown
$brandsList = [];
if ($pdo) {
    $brandsList = $pdo->query("SELECT id, name FROM brands ORDER BY name ASC")->fetchAll();
}

// Build query
$whereClauses = [];
$params = [];

if (!empty($searchQuery)) {
    $whereClauses[] = "(p.name LIKE ? OR b.name LIKE ?)";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
}

if ($brandFilter > 0) {
    $whereClauses[] = "p.brand_id = ?";
    $params[] = $brandFilter;
}

if ($filterGst === 'pending_gst') {
    $whereClauses[] = "p.gst_percent IS NULL";
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : "";

$products = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.name,
                p.image,
                p.gst_percent,
                p.is_active,
                p.created_at,
                b.name AS brand_name,
                b.is_active AS brand_active,
                COUNT(v.id) AS variant_count,
                COALESCE(SUM(v.stock_qty), 0) AS total_stock,
                MIN(v.price) AS min_price,
                MAX(v.price) AS max_price
            FROM products p
            INNER JOIN brands b ON b.id = p.brand_id
            LEFT JOIN product_variants v ON v.product_id = p.id
            {$whereSql}
            GROUP BY p.id
            ORDER BY b.name ASC, p.name ASC
        ");
        $stmt->execute($params);
        $products = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to query products: " . $e->getMessage());
    }
}
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; margin-bottom: 0.25rem;">Products &amp; Variants Management</h1>
            <p style="font-size: 0.9375rem; color: var(--text-muted); margin-bottom: 0;">
                Manage wholesale FMCG SKUs, GST compliance, size variations, and storefront visibility.
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="<?= admin_url('stock.php') ?>" class="btn btn-outline btn-sm">
                &#9881; Bulk Stock Table
            </a>
            <a href="<?= admin_url('product-edit.php') ?>" class="btn btn-primary btn-sm">
                + Add New Product
            </a>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-error" role="alert" style="margin-bottom: 1.5rem;">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<!-- Filter & Search Toolbar -->
<div class="card" style="padding: 1.25rem 1.5rem; margin-bottom: 2rem;">
    <form action="<?= admin_url('products.php') ?>" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; justify-content: space-between;">
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; flex: 1;">
            <!-- Brand Filter -->
            <select name="brand" class="form-control" style="width: auto; min-width: 180px; font-size: 0.875rem;" onchange="this.form.submit()">
                <option value="0">All Brands</option>
                <?php foreach ($brandsList as $b): ?>
                    <option value="<?= (int)$b['id'] ?>" <?= $brandFilter === (int)$b['id'] ? 'selected' : '' ?>>
                        <?= e($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- GST Status Filter -->
            <select name="filter" class="form-control" style="width: auto; min-width: 160px; font-size: 0.875rem;" onchange="this.form.submit()">
                <option value="">All GST Statuses</option>
                <option value="pending_gst" <?= $filterGst === 'pending_gst' ? 'selected' : '' ?>>Pending GST Only</option>
            </select>

            <?php if (!empty($searchQuery) || $brandFilter > 0 || !empty($filterGst)): ?>
                <a href="<?= admin_url('products.php') ?>" class="btn btn-sm btn-outline" style="color: var(--color-danger); border-color: var(--border-color);">
                    &times; Reset Filters
                </a>
            <?php endif; ?>
        </div>

        <!-- Search Input -->
        <div style="width: 100%; max-width: 300px;">
            <div class="input-group">
                <input type="text" name="q" class="form-control" placeholder="Search product name..." value="<?= e($searchQuery) ?>" style="font-size: 0.875rem;">
                <button type="submit" class="btn btn-sm btn-primary">Search</button>
            </div>
        </div>
    </form>
</div>

<!-- Product Catalogue Table -->
<div class="card" style="padding: 1.5rem;">
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color); font-size: 0.8125rem; color: var(--text-muted); text-transform: uppercase;">
                    <th style="padding: 0.75rem 0.5rem; width: 60px;">Image</th>
                    <th style="padding: 0.75rem 0.5rem;">Product &amp; Brand</th>
                    <th style="padding: 0.75rem 0.5rem; text-align: center;">GST Rate</th>
                    <th style="padding: 0.75rem 0.5rem; text-align: center;">Variants</th>
                    <th style="padding: 0.75rem 0.5rem; text-align: center;">Total Stock</th>
                    <th style="padding: 0.75rem 0.5rem; text-align: right;">Price Range</th>
                    <th style="padding: 0.75rem 0.5rem; text-align: center;">Storefront</th>
                    <th style="padding: 0.75rem 0.5rem; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="8" style="padding: 3rem 1rem; text-align: center; color: var(--text-muted);">
                            No products match the selected filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <?php 
                        $isActive = ((int)$p['is_active'] === 1);
                        $isPendingGst = ($p['gst_percent'] === null);
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-light); font-size: 0.9375rem;">
                            <!-- Thumbnail -->
                            <td style="padding: 0.75rem 0.5rem;">
                                <div style="width: 48px; height: 48px; border-radius: var(--radius-sm); background: #f8fafc; border: 1px solid var(--border-light); display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                    <?php if (!empty($p['image'])): ?>
                                        <img src="<?= public_url('uploads/products/' . e($p['image'])) ?>" alt="<?= e($p['name']) ?>" style="width: 100%; height: 100%; object-fit: contain;">
                                    <?php else: ?>
                                        <span style="font-size: 0.65rem; color: var(--text-muted); text-align: center; font-weight: 700;">No Pic</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Product Name & Brand -->
                            <td style="padding: 0.75rem 0.5rem;">
                                <a href="<?= admin_url('product-edit.php?id=' . (int)$p['id']) ?>" style="font-weight: 700; color: var(--color-primary); text-decoration: none;">
                                    <?= e($p['name']) ?>
                                </a>
                                <div style="font-size: 0.8125rem; color: var(--text-muted); margin-top: 0.15rem;">
                                    <span class="badge" style="background: #f1f5f9; padding: 0.1rem 0.35rem; font-size: 0.75rem;"><?= e($p['brand_name']) ?></span>
                                    <?php if ((int)$p['brand_active'] === 0): ?>
                                        <span class="badge badge-danger" style="font-size: 0.7rem;">Brand Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- GST Rate % -->
                            <td style="padding: 0.75rem 0.5rem; text-align: center;">
                                <?php if ($isPendingGst): ?>
                                    <span class="badge" style="background: #fef3c7; color: #b45309; font-weight: 700;">
                                        Pending
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background: #e0f2fe; color: #0369a1; font-weight: 700;">
                                        <?= number_format((float)$p['gst_percent'], 0) ?>%
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Variants Count -->
                            <td style="padding: 0.75rem 0.5rem; text-align: center; font-weight: 600;">
                                <a href="<?= admin_url('product-edit.php?id=' . (int)$p['id']) ?>" style="color: inherit; text-decoration: underline;">
                                    <?= (int)$p['variant_count'] ?> size(s)
                                </a>
                            </td>

                            <!-- Total Stock -->
                            <td style="padding: 0.75rem 0.5rem; text-align: center;">
                                <span class="badge <?= (int)$p['total_stock'] <= 10 ? 'badge-danger' : 'badge-stock-in' ?>">
                                    <?= (int)$p['total_stock'] ?> units
                                </span>
                            </td>

                            <!-- Price Range -->
                            <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 700; color: var(--color-primary);">
                                <?php if ($p['min_price'] !== null): ?>
                                    <?= format_currency($p['min_price']) ?>
                                    <?php if ($p['max_price'] > $p['min_price']): ?>
                                        &ndash; <?= format_currency($p['max_price']) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.8125rem;">No rates</span>
                                <?php endif; ?>
                            </td>

                            <!-- Public Visibility Toggle -->
                            <td style="padding: 0.75rem 0.5rem; text-align: center;">
                                <span class="badge <?= $isActive ? 'badge-stock-in' : 'badge-stock-out' ?>">
                                    <?= $isActive ? 'Live' : 'Hidden' ?>
                                </span>
                            </td>

                            <!-- Actions -->
                            <td style="padding: 0.75rem 0.5rem; text-align: right; white-space: nowrap;">
                                <a href="<?= admin_url('product-edit.php?id=' . (int)$p['id']) ?>" class="btn btn-sm btn-outline" style="margin-right: 0.25rem;">
                                    Edit
                                </a>

                                <form action="<?= admin_url('products.php') ?>" method="POST" style="display: inline-block; margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-outline' : 'btn-primary' ?>" style="font-size: 0.75rem; margin-right: 0.25rem;" title="<?= $isPendingGst && !$isActive ? 'Cannot activate: GST is pending' : '' ?>">
                                        <?= $isActive ? 'Hide' : 'Activate' ?>
                                    </button>
                                </form>

                                <form action="<?= admin_url('products.php') ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this product?');" style="display: inline-block; margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline" style="color: var(--color-danger); border-color: var(--border-color); font-size: 0.75rem;" title="Delete or soft-delete product">
                                        &times;
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
