<?php
/**
 * Kalika Enterprises - Admin Operations Dashboard
 * Phase 7: Live KPIs, order pipeline, inventory alerts, and catalogue compliance.
 */

declare(strict_types=1);

$adminPage = 'dashboard';
$adminTitle = 'Operations Dashboard';

require_once __DIR__ . '/includes/header.php';

// Fetch live metrics
$placedCount = 0;
$dispatchedCount = 0;
$deliveredCount = 0;
$totalCustomers = 0;
$lowStockCount = 0;
$pendingGstCount = 0;
$recentOrders = [];
$lowStockItems = [];
$pendingGstProducts = [];

if ($pdo) {
    try {
        // Orders by status
        $placedCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Placed'")->fetchColumn();
        $dispatchedCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Dispatched'")->fetchColumn();
        $deliveredCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Delivered'")->fetchColumn();

        // Customer count
        $totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

        // Low stock count (stock_qty <= 10)
        $lowStockStmt = $pdo->query("
            SELECT v.id, v.size_label, v.stock_qty, v.stock_status, p.name AS product_name, b.name AS brand_name
            FROM product_variants v
            INNER JOIN products p ON p.id = v.product_id
            INNER JOIN brands b ON b.id = p.brand_id
            WHERE v.stock_qty <= 10 AND v.is_active = 1 AND p.is_active = 1
            ORDER BY v.stock_qty ASC
            LIMIT 5
        ");
        $lowStockItems = $lowStockStmt->fetchAll();
        $lowStockCount = (int)$pdo->query("SELECT COUNT(*) FROM product_variants WHERE stock_qty <= 10 AND is_active = 1")->fetchColumn();

        // Products pending GST (gst_percent IS NULL)
        $pendingGstStmt = $pdo->query("
            SELECT p.id, p.name, b.name AS brand_name, p.is_active
            FROM products p
            INNER JOIN brands b ON b.id = p.brand_id
            WHERE p.gst_percent IS NULL
            ORDER BY p.name ASC
        ");
        $pendingGstProducts = $pendingGstStmt->fetchAll();
        $pendingGstCount = count($pendingGstProducts);

        // 10 latest orders
        $orderStmt = $pdo->query("
            SELECT o.id, o.order_no, o.customer_name, o.mobile, o.grand_total, o.status, o.created_at,
                   (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
            FROM orders o
            ORDER BY o.created_at DESC
            LIMIT 10
        ");
        $recentOrders = $orderStmt->fetchAll();

    } catch (PDOException $e) {
        error_log("Admin dashboard query failed: " . $e->getMessage());
    }
}
?>

<!-- Page Header -->
<div class="admin-header-row">
    <div class="admin-header-title-wrap">
        <div class="eyebrow">
            <span class="admin-status-dot"></span>
            Operational Metrics &bull; Regional Distribution
        </div>
        <h1>Wholesale Operations Dashboard</h1>
        <p>Live order fulfillment, crate stock alerts, and retailer activity across Niphad, Dindori, Chandwad, Sinnar, and Yeola.</p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="<?= admin_url('stock.php') ?>" class="btn btn-outline btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
            <span>Bulk Stock Manager</span>
        </a>
        <a href="<?= admin_url('product-edit.php') ?>" class="btn btn-primary btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            <span>Add New Product</span>
        </a>
    </div>
</div>

<!-- Catalogue Compliance Alert -->
<?php if ($pendingGstCount > 0): ?>
    <div class="alert alert-warning" role="alert" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-radius: 8px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            <div>
                <strong>Catalogue Compliance:</strong>
                <?= $pendingGstCount ?> product(s) pending official GST rate confirmation (hidden from storefront until defined).
            </div>
        </div>
        <a href="<?= admin_url('products.php?filter=pending_gst') ?>" class="btn btn-sm btn-primary">Review GST Rates &rarr;</a>
    </div>
<?php endif; ?>

<!-- 6 KPI Metric Cards Grid -->
<div class="admin-kpi-grid">
    <!-- 1. Placed Orders -->
    <div class="admin-kpi-card" style="border-top: 3px solid #F59E0B;">
        <div class="admin-kpi-card__top">
            <span class="admin-kpi-card__label">New Orders</span>
            <div class="admin-kpi-card__icon-wrap is-amber">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
            </div>
        </div>
        <div class="admin-kpi-card__value"><?= $placedCount ?></div>
        <a href="<?= admin_url('orders.php?status=Placed') ?>" class="admin-kpi-card__link">
            <span>Needs Confirmation</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </a>
    </div>

    <!-- 2. Dispatched Orders -->
    <div class="admin-kpi-card" style="border-top: 3px solid #0284C7;">
        <div class="admin-kpi-card__top">
            <span class="admin-kpi-card__label">Dispatched</span>
            <div class="admin-kpi-card__icon-wrap is-blue">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
            </div>
        </div>
        <div class="admin-kpi-card__value"><?= $dispatchedCount ?></div>
        <a href="<?= admin_url('orders.php?status=Dispatched') ?>" class="admin-kpi-card__link">
            <span>En Route on Vans</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </a>
    </div>

    <!-- 3. Delivered Orders -->
    <div class="admin-kpi-card" style="border-top: 3px solid #0B5D44;">
        <div class="admin-kpi-card__top">
            <span class="admin-kpi-card__label">Delivered</span>
            <div class="admin-kpi-card__icon-wrap is-green">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
        </div>
        <div class="admin-kpi-card__value"><?= $deliveredCount ?></div>
        <a href="<?= admin_url('orders.php?status=Delivered') ?>" class="admin-kpi-card__link">
            <span>Fulfilled Orders</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </a>
    </div>

    <!-- 4. Registered Customers -->
    <div class="admin-kpi-card" style="border-top: 3px solid #6366F1;">
        <div class="admin-kpi-card__top">
            <span class="admin-kpi-card__label">Retail Stores</span>
            <div class="admin-kpi-card__icon-wrap is-indigo">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            </div>
        </div>
        <div class="admin-kpi-card__value"><?= $totalCustomers ?></div>
        <a href="<?= admin_url('customers.php') ?>" class="admin-kpi-card__link">
            <span>Store Directory</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </a>
    </div>

    <!-- 5. Low Stock Alert -->
    <div class="admin-kpi-card" style="border-top: 3px solid <?= $lowStockCount > 0 ? '#DC2626' : '#10B981' ?>;">
        <div class="admin-kpi-card__top">
            <span class="admin-kpi-card__label">Low Stock</span>
            <div class="admin-kpi-card__icon-wrap <?= $lowStockCount > 0 ? 'is-rose' : 'is-green' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
            </div>
        </div>
        <div class="admin-kpi-card__value" style="<?= $lowStockCount > 0 ? 'color: #DC2626;' : '' ?>"><?= $lowStockCount ?></div>
        <a href="<?= admin_url('stock.php') ?>" class="admin-kpi-card__link">
            <span>Restock Variants</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </a>
    </div>

    <!-- 6. Pending GST -->
    <div class="admin-kpi-card" style="border-top: 3px solid var(--color-pine, #16372D);">
        <div class="admin-kpi-card__top">
            <span class="admin-kpi-card__label">Pending GST</span>
            <div class="admin-kpi-card__icon-wrap is-pine">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            </div>
        </div>
        <div class="admin-kpi-card__value"><?= $pendingGstCount ?></div>
        <a href="<?= admin_url('products.php') ?>" class="admin-kpi-card__link">
            <span>Catalogue Rates</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </a>
    </div>
</div>

<!-- Main Operations Grid: Orders Table + Sidebar -->
<div class="admin-dashboard-layout">
    <!-- Left: Latest Customer Orders -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2>Recent Wholesale Orders</h2>
                <span style="font-size: 13px; color: var(--color-text-muted);">Real-time B2B orders placed by regional retailers</span>
            </div>
            <a href="<?= admin_url('orders.php') ?>" class="btn btn-outline btn-sm">
                <span>All Orders</span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
        </div>

        <?php if (empty($recentOrders)): ?>
            <div style="text-align: center; color: var(--color-text-muted); padding: 48px 16px;">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#CBD5E1" stroke-width="1.5" style="margin-bottom: 12px;"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                <p style="margin: 0; font-size: 14.5px;">No wholesale orders recorded yet. Incoming retailer orders will appear here automatically.</p>
            </div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order No</th>
                            <th>Retail Customer</th>
                            <th>Date</th>
                            <th style="text-align: right;">Amount</th>
                            <th style="text-align: center;">Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $ord): ?>
                            <?php
                            $st = $ord['status'];
                            $badge = match ($st) {
                                'Delivered'  => 'badge-success',
                                'Dispatched' => 'badge-info',
                                default      => 'badge-warning',
                            };
                            ?>
                            <tr>
                                <td style="font-weight: 700;">
                                    <a href="<?= admin_url('order-detail.php?id=' . (int)$ord['id']) ?>" style="color: var(--color-pine); text-decoration: none; font-weight: 700;">
                                        <?= e($ord['order_no']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--color-text-main);"><?= e($ord['customer_name']) ?></div>
                                    <div style="font-size: 12px; color: var(--color-text-muted);">+91 <?= e($ord['mobile']) ?></div>
                                </td>
                                <td style="font-size: 12.5px; color: var(--color-text-muted);">
                                    <?= date('d M, h:i A', strtotime($ord['created_at'])) ?>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: var(--color-pine);">
                                    <?= format_currency($ord['grand_total']) ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge <?= $badge ?>"><?= e($st) ?></span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="<?= admin_url('order-detail.php?id=' . (int)$ord['id']) ?>" class="btn btn-sm btn-outline" style="padding: 4px 10px; font-size: 12px;">
                                        Manage
                                    </a>
                                    <a href="<?= public_url('invoice.php?order=' . (int)$ord['id']) ?>" target="_blank" class="btn btn-sm btn-primary" style="padding: 4px 10px; font-size: 12px;">
                                        Invoice &nearr;
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Operational Sidebar (Low Stock & Shortcuts) -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
        <!-- Low Stock Items Card -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 style="font-size: 16px;">Low Stock Items</h3>
                    <span style="font-size: 12px; color: var(--color-text-muted);">&le; 10 units in stock</span>
                </div>
                <a href="<?= admin_url('stock.php') ?>" style="font-size: 12.5px; color: var(--color-pine); font-weight: 600; text-decoration: none;">Restock All</a>
            </div>

            <?php if (empty($lowStockItems)): ?>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: #0B5D44; padding: 10px 0;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span>All active inventory items have healthy stock levels.</span>
                </div>
            <?php else: ?>
                <ul style="list-style: none; margin: 0; padding: 0;">
                    <?php foreach ($lowStockItems as $lsi): ?>
                        <li style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--color-border-light); font-size: 13.5px;">
                            <div>
                                <strong style="color: var(--color-text-main); display: block; line-height: 1.3;"><?= e($lsi['product_name']) ?></strong>
                                <span style="font-size: 12px; color: var(--color-text-muted);">
                                    <?= e($lsi['brand_name']) ?> &bull; <?= e($lsi['size_label']) ?>
                                </span>
                            </div>
                            <span class="badge badge-danger" style="font-size: 11.5px;">
                                <?= (int)$lsi['stock_qty'] ?> left
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Management Shortcuts -->
        <div class="card">
            <div class="card-header">
                <h3 style="font-size: 16px;">Quick Operations</h3>
            </div>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <a href="<?= admin_url('product-edit.php') ?>" class="admin-tool-link" style="color: var(--color-pine); font-weight: 600; padding: 10px 12px; border-radius: 8px; background: #F8FAFC; border: 1px solid var(--color-border-light);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    <span>Add New Catalogue Product</span>
                </a>
                <a href="<?= admin_url('categories.php') ?>" class="admin-tool-link" style="color: var(--color-pine); font-weight: 600; padding: 10px 12px; border-radius: 8px; background: #F8FAFC; border: 1px solid var(--color-border-light);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                    <span>Manage Categories</span>
                </a>
                <a href="<?= admin_url('brands.php') ?>" class="admin-tool-link" style="color: var(--color-pine); font-weight: 600; padding: 10px 12px; border-radius: 8px; background: #F8FAFC; border: 1px solid var(--color-border-light);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                    <span>Manage Brands</span>
                </a>
                <a href="<?= admin_url('stock.php') ?>" class="admin-tool-link" style="color: var(--color-pine); font-weight: 600; padding: 10px 12px; border-radius: 8px; background: #F8FAFC; border: 1px solid var(--color-border-light);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                    <span>Bulk Stock &amp; Availability</span>
                </a>
                <a href="<?= admin_url('settings.php') ?>" class="admin-tool-link" style="color: var(--color-pine); font-weight: 600; padding: 10px 12px; border-radius: 8px; background: #F8FAFC; border: 1px solid var(--color-border-light);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    <span>Store Policies &amp; Min. Order (₹2,000)</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
