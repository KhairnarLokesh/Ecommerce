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

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; margin-bottom: 0.25rem;">Wholesale Operations Dashboard</h1>
            <p style="font-size: 0.9375rem; color: var(--text-muted); margin-bottom: 0;">
                Live distribution metrics across Niphad, Dindori, Chandwad, Sinnar, and Yeola.
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="<?= admin_url('stock.php') ?>" class="btn btn-outline btn-sm">
                &#9881; Bulk Stock Manager
            </a>
            <a href="<?= admin_url('product-edit.php') ?>" class="btn btn-primary btn-sm">
                + Add New Product
            </a>
        </div>
    </div>
</div>

<!-- Attention Warnings / Alerts if any -->
<?php if ($pendingGstCount > 0): ?>
    <div class="alert alert-warning" role="alert" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <strong>&#9888; Catalogue Compliance Alert:</strong>
            <?= $pendingGstCount ?> product(s) pending official GST rate confirmation (e.g. 
            <?= implode(', ', array_map(fn($p) => e($p['name']), array_slice($pendingGstProducts, 0, 3))) ?>).
            These items are safely hidden from the public storefront until GST is defined.
        </div>
        <a href="<?= admin_url('products.php?filter=pending_gst') ?>" class="btn btn-sm btn-primary">Review GST Rates &rarr;</a>
    </div>
<?php endif; ?>

<!-- Metric Cards Grid (6 KPIs) -->
<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
    <!-- Placed Orders -->
    <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--color-warning);">
        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
            New Placed Orders
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--color-primary); margin: 0.25rem 0;">
            <?= $placedCount ?>
        </div>
        <a href="<?= admin_url('orders.php?status=Placed') ?>" style="font-size: 0.75rem; color: #b45309; font-weight: 600; text-decoration: none;">
            View Needs Confirmation &rarr;
        </a>
    </div>

    <!-- Dispatched Orders -->
    <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--color-info);">
        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
            Dispatched En Route
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: #0284c7; margin: 0.25rem 0;">
            <?= $dispatchedCount ?>
        </div>
        <a href="<?= admin_url('orders.php?status=Dispatched') ?>" style="font-size: 0.75rem; color: #0369a1; font-weight: 600; text-decoration: none;">
            On Distribution Vans &rarr;
        </a>
    </div>

    <!-- Delivered Orders -->
    <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--color-success);">
        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
            Delivered Orders
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: #059669; margin: 0.25rem 0;">
            <?= $deliveredCount ?>
        </div>
        <a href="<?= admin_url('orders.php?status=Delivered') ?>" style="font-size: 0.75rem; color: #047857; font-weight: 600; text-decoration: none;">
            Fulfilled Successfully &rarr;
        </a>
    </div>

    <!-- Registered Customers -->
    <div class="card" style="padding: 1.25rem; border-left: 4px solid #6366f1;">
        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
            Retail Customers
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: #4338ca; margin: 0.25rem 0;">
            <?= $totalCustomers ?>
        </div>
        <a href="<?= admin_url('customers.php') ?>" style="font-size: 0.75rem; color: #4f46e5; font-weight: 600; text-decoration: none;">
            Retailer Directory &rarr;
        </a>
    </div>

    <!-- Low Stock Alert -->
    <div class="card" style="padding: 1.25rem; border-left: 4px solid <?= $lowStockCount > 0 ? '#dc2626' : '#cbd5e1' ?>;">
        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
            Low Stock (&le;10)
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: <?= $lowStockCount > 0 ? '#dc2626' : 'var(--color-primary)' ?>; margin: 0.25rem 0;">
            <?= $lowStockCount ?>
        </div>
        <a href="<?= admin_url('stock.php') ?>" style="font-size: 0.75rem; color: #b91c1c; font-weight: 600; text-decoration: none;">
            Restock Inventory &rarr;
        </a>
    </div>

    <!-- Pending GST Confirmation -->
    <div class="card" style="padding: 1.25rem; border-left: 4px solid #f59e0b;">
        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
            Pending GST
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: #b45309; margin: 0.25rem 0;">
            <?= $pendingGstCount ?>
        </div>
        <a href="<?= admin_url('products.php') ?>" style="font-size: 0.75rem; color: #b45309; font-weight: 600; text-decoration: none;">
            Catalogue Settings &rarr;
        </a>
    </div>
</div>

<div class="grid" style="grid-template-columns: 2.2fr 1fr; align-items: flex-start; gap: 2rem;">
    <!-- Left: 10 Latest Wholesale Orders -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-light);">
            <h2 style="font-size: 1.25rem; margin-bottom: 0;">Latest Customer Orders</h2>
            <a href="<?= admin_url('orders.php') ?>" class="btn btn-outline btn-sm">
                View All Orders &rarr;
            </a>
        </div>

        <?php if (empty($recentOrders)): ?>
            <p style="text-align: center; color: var(--text-muted); padding: 2.5rem 1rem; margin: 0;">
                No wholesale orders recorded yet. Orders placed by retailers will appear here automatically.
            </p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color); font-size: 0.8125rem; color: var(--text-muted); text-transform: uppercase;">
                            <th style="padding: 0.75rem 0.5rem;">Order No</th>
                            <th style="padding: 0.75rem 0.5rem;">Customer</th>
                            <th style="padding: 0.75rem 0.5rem;">Date</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: right;">Amount</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: center;">Status</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: right;">Actions</th>
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
                            <tr style="border-bottom: 1px solid var(--border-light); font-size: 0.9375rem;">
                                <td style="padding: 0.875rem 0.5rem; font-weight: 700;">
                                    <a href="<?= admin_url('order-detail.php?id=' . (int)$ord['id']) ?>" style="color: inherit; text-decoration: underline;">
                                        <?= e($ord['order_no']) ?>
                                    </a>
                                </td>
                                <td style="padding: 0.875rem 0.5rem;">
                                    <div style="font-weight: 600;"><?= e($ord['customer_name']) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">+91 <?= e($ord['mobile']) ?></div>
                                </td>
                                <td style="padding: 0.875rem 0.5rem; font-size: 0.8125rem; color: var(--text-muted);">
                                    <?= date('d M, h:i A', strtotime($ord['created_at'])) ?>
                                </td>
                                <td style="padding: 0.875rem 0.5rem; text-align: right; font-weight: 800; color: var(--color-primary);">
                                    <?= format_currency($ord['grand_total']) ?>
                                </td>
                                <td style="padding: 0.875rem 0.5rem; text-align: center;">
                                    <span class="badge <?= $badge ?>"><?= e($st) ?></span>
                                </td>
                                <td style="padding: 0.875rem 0.5rem; text-align: right; white-space: nowrap;">
                                    <a href="<?= admin_url('order-detail.php?id=' . (int)$ord['id']) ?>" class="btn btn-sm btn-outline">
                                        Manage
                                    </a>
                                    <a href="<?= public_url('invoice.php?order=' . (int)$ord['id']) ?>" target="_blank" class="btn btn-sm btn-primary">
                                        Invoice
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Operational Sidebar (Low Stock & Quick Links) -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Low Stock Items Card -->
        <div class="card" style="padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
                <h3 style="font-size: 1.1rem; margin-bottom: 0;">Low Stock Items</h3>
                <a href="<?= admin_url('stock.php') ?>" style="font-size: 0.75rem; color: var(--color-accent); font-weight: 600;">Edit All</a>
            </div>

            <?php if (empty($lowStockItems)): ?>
                <p style="font-size: 0.8125rem; color: var(--color-success); margin-bottom: 0;">
                    &#10003; All active product variants have healthy inventory levels (&gt;10 units).
                </p>
            <?php else: ?>
                <ul style="list-style: none; margin: 0; padding: 0;">
                    <?php foreach ($lowStockItems as $lsi): ?>
                        <li style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0; border-bottom: 1px solid var(--border-light); font-size: 0.875rem;">
                            <div>
                                <strong style="color: var(--color-primary);"><?= e($lsi['product_name']) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    <?= e($lsi['brand_name']) ?> &bull; <?= e($lsi['size_label']) ?>
                                </div>
                            </div>
                            <span class="badge badge-danger" style="font-size: 0.75rem;">
                                <?= (int)$lsi['stock_qty'] ?> left
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Quick Administration Links -->
        <div class="card" style="padding: 1.5rem; background: #ffffff;">
            <h3 style="font-size: 1.1rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
                Management Shortcuts
            </h3>
            <ul style="list-style: none; margin: 0; padding: 0; font-size: 0.875rem; display: flex; flex-direction: column; gap: 0.75rem;">
                <li>
                    <a href="<?= admin_url('product-edit.php') ?>" style="color: var(--color-primary); font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 0.6rem;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        <span>Add New Catalogue Product</span>
                    </a>
                </li>
                <li>
                    <a href="<?= admin_url('brands.php') ?>" style="color: var(--color-primary); font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 0.6rem;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                        <span>Manage Brands &amp; Brand Status</span>
                    </a>
                </li>
                <li>
                    <a href="<?= admin_url('stock.php') ?>" style="color: var(--color-primary); font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 0.6rem;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                        <span>Bulk Stock &amp; Availability Update</span>
                    </a>
                </li>
                <li>
                    <a href="<?= admin_url('settings.php') ?>" style="color: var(--color-primary); font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 0.6rem;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                        <span>Edit Minimum Order (₹2,000) &amp; Policies</span>
                    </a>
                </li>
                <li>
                    <a href="<?= admin_url('messages.php') ?>" style="color: var(--color-primary); font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 0.6rem;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        <span>Review Store Inquiries &amp; Messages</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
