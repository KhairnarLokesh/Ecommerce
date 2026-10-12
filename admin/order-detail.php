<?php
/**
 * Kalika Enterprises - Admin Order Detail & Fulfillment Controller
 * Phase 7: Order status updater, customer delivery address review, line items snapshot, and invoice link.
 */

declare(strict_types=1);

$adminPage = 'orders';
$orderId = (int)($_GET['id'] ?? 0);
$adminTitle = 'Order #' . $orderId;

require_once __DIR__ . '/includes/header.php';

if ($orderId <= 0 || !$pdo) {
    set_flash('error', 'Invalid order ID.');
    redirect(admin_url('orders.php'));
}

// Handle Order Status Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Security token invalid or expired.');
    } else {
        $newStatus = trim($_POST['status'] ?? '');
        if (in_array($newStatus, ['Placed', 'Dispatched', 'Delivered'], true)) {
            $up = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $up->execute([$newStatus, $orderId]);
            set_flash('success', "Order fulfillment status updated to '{$newStatus}'.");
            redirect(admin_url("order-detail.php?id={$orderId}"));
        } else {
            set_flash('error', 'Invalid status specified.');
        }
    }
}

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect(admin_url('orders.php'));
}

// Fetch snapshot line items
$itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
$itemStmt->execute([$orderId]);
$orderItems = $itemStmt->fetchAll();

$status = $order['status'];
$badge = match ($status) {
    'Delivered'  => 'badge-success',
    'Dispatched' => 'badge-info',
    default      => 'badge-warning',
};
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="<?= admin_url('orders.php') ?>" style="font-size: 0.8125rem; color: var(--text-muted); text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
                <span>Back to Orders Pipeline</span>
            </a>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 0.35rem;">
                <h1 style="font-size: 1.75rem; margin-bottom: 0;">Order #<?= e($order['order_no']) ?></h1>
                <span class="badge <?= $badge ?>"><?= e($status) ?></span>
            </div>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0;">
                Placed on <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?>
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <a href="<?= public_url('invoice.php?order=' . (int)$order['id']) ?>" target="_blank" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 0.45rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                View / Print Tax Invoice
            </a>
        </div>
    </div>
</div>

<div class="grid" style="grid-template-columns: 2fr 1fr; align-items: flex-start; gap: 2rem;">
    <!-- Left: Order Line Items Snapshot -->
    <div class="card" style="padding: 1.5rem;">
        <h2 style="font-size: 1.25rem; margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
            Order Line Items (<?= count($orderItems) ?> items)
        </h2>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); font-size: 0.8125rem; color: var(--text-muted); text-transform: uppercase;">
                        <th style="padding: 0.75rem 0.5rem;">Product &amp; Variant</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: right;">Historical Rate</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: center;">Qty</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: center;">GST %</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: right;">Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                        <tr style="border-bottom: 1px solid var(--border-light); font-size: 0.9375rem;">
                            <td style="padding: 0.875rem 0.5rem;">
                                <div style="font-weight: 700; color: var(--color-primary);"><?= e($item['product_name']) ?></div>
                                <div style="font-size: 0.8125rem; color: var(--text-muted);">
                                    <?= e($item['brand_name']) ?> &bull; <?= e($item['variant_label']) ?>
                                </div>
                            </td>
                            <td style="padding: 0.875rem 0.5rem; text-align: right; color: var(--text-main);">
                                <?= format_currency($item['unit_price']) ?>
                            </td>
                            <td style="padding: 0.875rem 0.5rem; text-align: center; font-weight: 600;">
                                <?= (int)$item['quantity'] ?>
                            </td>
                            <td style="padding: 0.875rem 0.5rem; text-align: center;">
                                <span class="badge" style="background: #e0f2fe; color: #0369a1; font-weight: 700;">
                                    <?= number_format((float)$item['gst_percent'], 0) ?>%
                                </span>
                            </td>
                            <td style="padding: 0.875rem 0.5rem; text-align: right; font-weight: 800; color: var(--color-primary);">
                                <?= format_currency($item['line_total']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals Calculation Block -->
        <div style="margin-top: 1.5rem; border-top: 2px solid var(--border-color); padding-top: 1rem; max-width: 320px; margin-left: auto; font-size: 0.9375rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span style="color: var(--text-muted);">Items Subtotal:</span>
                <strong><?= format_currency($order['subtotal']) ?></strong>
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.8125rem;">
                <span style="color: var(--text-muted);">
                    GST Breakdown (<?= (int)$order['prices_include_gst'] === 1 ? 'Included' : 'Extra' ?>):
                </span>
                <span style="color: var(--text-muted); font-weight: 600;"><?= format_currency($order['gst_total']) ?></span>
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem;">
                <span style="color: var(--text-muted);">Delivery Charge:</span>
                <span class="badge" style="background: #ecfdf5; color: #059669; font-weight: 700;">FREE</span>
            </div>

            <div style="display: flex; justify-content: space-between; border-top: 2px solid var(--border-color); padding-top: 0.75rem; font-size: 1.25rem; font-weight: 800; color: var(--color-primary);">
                <span>Total Amount:</span>
                <span><?= format_currency($order['grand_total']) ?></span>
            </div>
        </div>
    </div>

    <!-- Right: Fulfillment Controls & Delivery Information -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Status Advancement Form -->
        <div class="card" style="padding: 1.5rem; border-top: 4px solid var(--color-accent);">
            <h3 style="font-size: 1.1rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
                Update Fulfillment Status
            </h3>

            <form action="<?= admin_url('order-detail.php?id=' . $orderId) ?>" method="POST">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="order_status">Current Status</label>
                    <select id="order_status" name="status" class="form-control" style="font-size: 1rem; font-weight: 600;">
                        <option value="Placed" <?= $status === 'Placed' ? 'selected' : '' ?>>1. Placed (Needs Desk Verification)</option>
                        <option value="Dispatched" <?= $status === 'Dispatched' ? 'selected' : '' ?>>2. Dispatched (En Route in Van)</option>
                        <option value="Delivered" <?= $status === 'Delivered' ? 'selected' : '' ?>>3. Delivered (Settled Offline &amp; Fulfilled)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="padding: 0.75rem; display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <span>Update Fulfillment Status</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </form>
        </div>

        <!-- Customer & Destination Card -->
        <div class="card" style="padding: 1.5rem;">
            <h3 style="font-size: 1.1rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
                Delivery Destination
            </h3>

            <div style="font-size: 0.9375rem;">
                <div style="font-weight: 700; color: var(--color-primary); font-size: 1rem; margin-bottom: 0.25rem;">
                    <?= e($order['customer_name']) ?>
                </div>
                <div style="color: var(--text-muted); margin-bottom: 0.75rem;">
                    Phone: <strong>+91 <?= e($order['mobile']) ?></strong>
                </div>

                <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 0.25rem;">
                    Commercial Address:
                </label>
                <div style="background: #f8fafc; padding: 0.85rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light); line-height: 1.45;">
                    <?= nl2br(e($order['delivery_address'])) ?>
                </div>
            </div>
        </div>

        <!-- Offline Payment Notice -->
        <div class="card" style="padding: 1.25rem; background: #fffbeb; border: 1px solid #fde68a;">
            <strong style="color: #92400e; font-size: 0.875rem; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.25rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    <line x1="2" y1="10" x2="22" y2="10"></line>
                </svg>
                Offline Payment Mode
            </strong>
            <p style="font-size: 0.8125rem; color: #78350f; margin-bottom: 0; line-height: 1.4;">
                Payment is settled offline by the retailer upon physical van arrival as agreed.
            </p>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
