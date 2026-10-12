<?php
/**
 * Kalika Enterprises - Customer Order Details & Visual Fulfillment Tracker
 * Phase 6: IDOR authorization guard, 3-step visual tracker (Placed -> Dispatched -> Delivered),
 *          and post-checkout confirmation state.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login('orders.php');

$user = current_user();
$orderId = (int)($_GET['id'] ?? 0);

if ($orderId <= 0 || !$pdo) {
    http_response_code(404);
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="container" style="padding: 4rem 1rem; text-align: center;"><h1>Order Not Found</h1><p>The requested order does not exist.</p><a href="' . public_url('orders.php') . '" class="btn btn-primary">Return to Orders</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="container" style="padding: 4rem 1rem; text-align: center;"><h1>Order Not Found</h1><p>The requested order does not exist.</p><a href="' . public_url('orders.php') . '" class="btn btn-primary">Return to Orders</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// IDOR Security Check: Order must belong to currently logged in customer
$isAdmin = is_admin();
if ((int)$order['user_id'] !== (int)$user['id'] && !$isAdmin) {
    http_response_code(403);
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="container" style="padding: 4rem 1rem; text-align: center;"><h1>403 Forbidden</h1><p>You do not have authorization to view this order.</p><a href="' . public_url('orders.php') . '" class="btn btn-primary">Return to My Orders</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch line items
$itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
$itemStmt->execute([$orderId]);
$orderItems = $itemStmt->fetchAll();

$isConfirmedScreen = isset($_GET['confirmed']) && $_GET['confirmed'] === '1';

// Calculate visual progress states
$status = $order['status'];
$isPlacedDone = true;
$isDispatchedDone = in_array($status, ['Dispatched', 'Delivered'], true);
$isDeliveredDone = ($status === 'Delivered');

$pageTitle = 'Order ' . e($order['order_no']) . ' | Kalika Enterprises';
$currentPage = 'account';
$pageDescription = 'Track wholesale FMCG delivery status and review line items for order ' . e($order['order_no']) . '.';


require_once __DIR__ . '/../includes/header.php';
?>
<!-- TXN HERO -->
<section class="txn-hero" style="padding: 32px 0 28px;">
    <div class="dd-container">
        <div class="txn-hero__eyebrow">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            Order Details
        </div>
        <div class="order-page-header" style="margin-bottom: 0;">
            <div>
                <h1 class="txn-hero__title" style="margin-bottom: 4px;">Order #<?= e($order['order_no']) ?></h1>
                <p class="txn-hero__sub">Placed on <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></p>
            </div>
            <div class="order-page-header__actions">
                <a href="<?= public_url('orders.php') ?>" class="dd-btn dd-btn--ghost" style="color: var(--color-text-inverse); border-color: rgba(255,255,255,0.3); padding: 8px 14px; font-size: 13px; display: inline-flex; align-items: center; gap: 5px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    <span>All Orders</span>
                </a>
                <a href="<?= public_url('invoice.php?order=' . (int)$order['id']) ?>" class="dd-btn dd-btn--mint" target="_blank" style="padding: 8px 16px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    <span>Tax Invoice</span>
                </a>
            </div>
        </div>
    </div>
</section>

<div class="dd-container" style="padding-bottom: 4rem;">

    <!-- Confirmation Banner (initial placement) -->
    <?php if ($isConfirmedScreen): ?>
        <div class="order-confirm-banner">
            <div class="order-confirm-banner__icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
            <div>
                <h2 class="order-confirm-banner__title">Order Placed Successfully!</h2>
                <p class="order-confirm-banner__body">
                    Your wholesale order reference is <strong style="color: white;"><?= e($order['order_no']) ?></strong>.
                    Our dispatch desk will verify crate quantities and destination within <strong style="color: white;">24 hours</strong>.
                    Estimated delivery: <strong style="color: white;">2–3 working days</strong>.
                </p>
                <p class="order-confirm-banner__notice" style="display: flex; align-items: center; gap: 6px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                    Payment is handled offline at delivery as agreed with the distributor.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Visual Tracker -->
    <div class="dd-card" style="margin-bottom: 28px;">
        <h2 class="dd-card__title" style="margin-bottom: 24px; font-size: 16px;">Fulfillment &amp; Logistics Tracker</h2>
        <div class="order-tracker">
            <!-- Step 1: Placed -->
            <div class="tracker-step">
                <div class="tracker-step__node tracker-step__node--done">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
                <div class="tracker-step__label">Order Placed</div>
                <div class="tracker-step__sub"><?= date('d M, h:i A', strtotime($order['created_at'])) ?></div>
                <div class="tracker-step__status tracker-step__status--done">Desk Verification</div>
            </div>

            <!-- Connector 1 -->
            <div class="tracker-connector <?= $isDispatchedDone ? 'tracker-connector--done' : 'tracker-connector--pending' ?>"></div>

            <!-- Step 2: Dispatched -->
            <div class="tracker-step">
                <div class="tracker-step__node <?= $isDispatchedDone ? 'tracker-step__node--done' : 'tracker-step__node--pending' ?>">
                    <?php if ($isDispatchedDone): ?>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <?php else: ?>
                        2
                    <?php endif; ?>
                </div>
                <div class="tracker-step__label <?= $isDispatchedDone ? '' : 'tracker-step__label--muted' ?>">Dispatched</div>
                <div class="tracker-step__sub">Regional Van Route</div>
                <div class="tracker-step__status <?= $isDispatchedDone ? 'tracker-step__status--done' : 'tracker-step__status--pending' ?>">
                    <?= $isDispatchedDone ? 'En Route' : 'Pending Load' ?>
                </div>
            </div>

            <!-- Connector 2 -->
            <div class="tracker-connector <?= $isDeliveredDone ? 'tracker-connector--done' : 'tracker-connector--pending' ?>"></div>

            <!-- Step 3: Delivered -->
            <div class="tracker-step">
                <div class="tracker-step__node <?= $isDeliveredDone ? 'tracker-step__node--done' : 'tracker-step__node--pending' ?>">
                    <?php if ($isDeliveredDone): ?>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <?php else: ?>
                        3
                    <?php endif; ?>
                </div>
                <div class="tracker-step__label <?= $isDeliveredDone ? '' : 'tracker-step__label--muted' ?>">Delivered</div>
                <div class="tracker-step__sub">At Store Premises</div>
                <div class="tracker-step__status <?= $isDeliveredDone ? 'tracker-step__status--done' : 'tracker-step__status--pending' ?>">
                    <?= $isDeliveredDone ? 'Fulfilled' : '2–3 Working Days' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="txn-grid">

        <!-- Left: Line Items -->
        <div class="dd-card" style="padding: 0; overflow: hidden;">
            <div class="dd-card__header" style="padding: 20px 28px;">
                <h2 class="dd-card__title">
                    Purchased Items
                    <span style="font-family: var(--font-pixel); font-size: 14px; color: var(--color-mint); background: var(--bg-dark); padding: 3px 10px; border-radius: var(--radius-full); margin-left: 10px;"><?= count($orderItems) ?></span>
                </h2>
                <?php
                $badgeStyle = match ($status) {
                    'Delivered'  => 'background: #DCFCE7; color: #065F46; border: 1px solid #A7F3D0;',
                    'Dispatched' => 'background: #DBEAFE; color: #1D4ED8; border: 1px solid #93C5FD;',
                    default      => 'background: #FEF9C3; color: #854D0E; border: 1px solid #FDE047;',
                };
                ?>
                <span style="font-size: 12px; font-weight: 700; padding: 5px 12px; border-radius: var(--radius-full); <?= $badgeStyle ?>"><?= e($status) ?></span>
            </div>

            <div style="overflow-x: auto; padding: 0 28px 24px;">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Item Description</th>
                            <th style="text-align: right;">Rate</th>
                            <th style="text-align: center;">Qty</th>
                            <th style="text-align: center;">GST</th>
                            <th style="text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): ?>
                            <tr>
                                <td>
                                    <div class="cart-item__name"><?= e($item['product_name']) ?></div>
                                    <div class="cart-item__meta"><?= e($item['brand_name']) ?> &bull; <?= e($item['variant_label']) ?></div>
                                </td>
                                <td class="cart-item__price"><?= format_currency($item['unit_price']) ?></td>
                                <td style="text-align: center; font-weight: 700; font-size: 15px;"><?= (int)$item['quantity'] ?></td>
                                <td style="text-align: center;">
                                    <span class="gst-chip"><?= number_format((float)$item['gst_percent'], 0) ?>%</span>
                                </td>
                                <td class="cart-item__total"><?= format_currency($item['line_total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Financial Summary -->
            <div style="padding: 0 28px 28px;">
                <div style="max-width: 320px; margin-left: auto; border-top: 2px solid var(--color-border-light); padding-top: 16px;">
                    <div class="summary-row"><span class="summary-row__label">Subtotal</span><strong class="summary-row__value"><?= format_currency($order['subtotal']) ?></strong></div>
                    <div class="summary-row">
                        <span class="summary-row__label">GST <?= (int)$order['prices_include_gst'] === 1 ? '(included)' : '(extra)' ?></span>
                        <span style="font-size: 13px; color: var(--color-text-muted); font-weight: 600;"><?= format_currency($order['gst_total']) ?></span>
                    </div>
                    <div class="summary-row"><span class="summary-row__label">Delivery Charge</span><span class="free-chip">FREE</span></div>
                    <div class="summary-total">
                        <span class="summary-total__label">Total Amount</span>
                        <span class="summary-total__value"><?= format_currency($order['grand_total']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Sidebar -->
        <div style="display: flex; flex-direction: column; gap: 24px;">

            <!-- Delivery Destination -->
            <div class="dd-card">
                <div class="dd-card__header">
                    <h3 class="dd-card__title" style="font-size: 15px;">Delivery Destination</h3>
                </div>
                <div class="dest-card__name"><?= e($order['customer_name']) ?></div>
                <div class="dest-card__phone">Mobile: +91 <?= e($order['mobile']) ?></div>
                <div class="dest-card__address"><?= nl2br(e($order['delivery_address'])) ?></div>
            </div>

            <!-- Distribution Guidelines -->
            <div class="dd-card">
                <h3 style="font-family: var(--font-editorial); font-size: 15px; color: var(--color-pine); margin-bottom: 16px;">Distribution Guidelines</h3>
                <ul class="commitments-list">
                    <li>
                        <div class="commitment-check" style="background: var(--color-pine);">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                        </div>
                        <div>
                            <span class="commitment-title">Payment on Delivery</span>
                            <span class="commitment-sub">Cash or offline bank transfer upon van delivery.</span>
                        </div>
                    </li>
                    <li>
                        <div class="commitment-check">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </div>
                        <div>
                            <span class="commitment-title">2–3 Working Days</span>
                            <span class="commitment-sub">Delivered within 2–3 working days from dispatch.</span>
                        </div>
                    </li>
                    <li>
                        <div class="commitment-check">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                        </div>
                        <div>
                            <span class="commitment-title">2-Day Return Window</span>
                            <span class="commitment-sub">Inquiries eligible within 2 days of physical delivery.</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
