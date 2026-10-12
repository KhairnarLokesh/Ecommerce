<?php
/**
 * Kalika Enterprises - Customer Orders History
 * Phase 6: Strict user authorization, listing customer's wholesale orders with status badges and invoice links.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login('orders.php');

$user = current_user();
$orders = [];

if ($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                o.id,
                o.order_no,
                o.subtotal,
                o.gst_total,
                o.delivery_charge,
                o.grand_total,
                o.status,
                o.created_at,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
                (SELECT SUM(quantity) FROM order_items oi WHERE oi.order_id = o.id) AS total_units
            FROM orders o
            WHERE o.user_id = ?
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$user['id']]);
        $orders = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to load customer orders: " . $e->getMessage());
    }
}

$pageTitle = 'My Orders | Kalika Enterprises';
$currentPage = 'account';
$pageDescription = 'Track wholesale FMCG distribution orders, view fulfillment status, and print tax invoices.';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="info-hero">
    <div class="dd-container">
        <div class="info-hero__eyebrow">Merchant Account</div>
        <h1 class="info-hero__title">Wholesale Order History</h1>
        <p class="info-hero__sub">
            Track dispatch progress from warehouse to store premises and download official GST tax invoices.
        </p>
    </div>
</section>

<div class="dd-container" style="padding-bottom: 72px;">
    <?php if (empty($orders)): ?>
        <div class="info-card order-empty" style="max-width: 580px; margin: 0 auto;">
            <div class="order-empty__icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
            </div>
            <h2 class="order-empty__title">No Orders Placed Yet</h2>
            <p class="order-empty__sub">
                You haven't placed any wholesale distributor orders under this account. Minimum order is <?= format_currency(get_setting('min_order_value', '2000.00')) ?> with free doorstep delivery.
            </p>
            <a href="<?= public_url('products.php') ?>" class="dd-btn dd-btn--mint" style="display: inline-flex;">
                Browse FMCG Catalogue
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
        </div>
    <?php else: ?>
        <div class="info-card" style="padding: 0; overflow: hidden;">
            <div style="overflow-x: auto;">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order Reference</th>
                            <th>Date Placed</th>
                            <th style="text-align: center;">Items / Units</th>
                            <th style="text-align: right;">Total Amount</th>
                            <th style="text-align: center;">Fulfillment Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $ord): ?>
                            <?php
                            $status = $ord['status'];
                            $chipClass = match ($status) {
                                'Delivered'  => 'order-status-chip--delivered',
                                'Dispatched' => 'order-status-chip--dispatched',
                                default      => 'order-status-chip--placed',
                            };
                            ?>
                            <tr>
                                <td>
                                    <a href="<?= public_url('order.php?id=' . (int)$ord['id']) ?>" class="order-no-link">
                                        <?= e($ord['order_no']) ?>
                                    </a>
                                </td>
                                <td style="color: var(--color-text-muted); font-size: 13px;">
                                    <?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?>
                                </td>
                                <td style="text-align: center; font-size: 13px; color: var(--color-text-muted);">
                                    <?= (int)$ord['item_count'] ?> items (<?= (int)$ord['total_units'] ?> units)
                                </td>
                                <td style="text-align: right;">
                                    <span class="order-amount"><?= format_currency($ord['grand_total']) ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="order-status-chip <?= $chipClass ?>">
                                        <?= e($status) ?>
                                    </span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="<?= public_url('order.php?id=' . (int)$ord['id']) ?>" class="dd-btn dd-btn--ghost" style="padding: 6px 12px; font-size: 12px; margin-right: 6px;">
                                        View Details
                                    </a>
                                    <a href="<?= public_url('invoice.php?order=' . (int)$ord['id']) ?>" class="dd-btn dd-btn--mint" style="padding: 6px 14px; font-size: 12px;">
                                        Tax Invoice
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
