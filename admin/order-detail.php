<?php
/**
 * Kalika Enterprises - Admin Order Detail, Payment Reconcile & Fulfillment Controller
 * Phase 7 + Razorpay: Payment status management, gateway reconciliation, forward-only
 * fulfillment guarding, COD confirmation, and idempotent refunds with inventory restocking.
 */

declare(strict_types=1);

$adminPage = 'orders';
$orderId = (int)($_GET['id'] ?? ($_POST['order_id'] ?? 0));
$adminTitle = 'Order #' . $orderId;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/razorpay.php';
require_once __DIR__ . '/includes/header.php';

$currentAdmin = current_admin();

if ($orderId <= 0 || !$pdo) {
    set_flash('error', 'Invalid order ID.');
    redirect(admin_url('orders.php'));
}

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect(admin_url('orders.php'));
}

// Handle Admin Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Security token invalid or expired. Please refresh.');
        redirect(admin_url("order-detail.php?id={$orderId}"));
    }

    $action = trim($_POST['action'] ?? 'update_status');

    // 1. Update Fulfillment Status
    if ($action === 'update_status') {
        $newStatus = trim($_POST['status'] ?? '');
        $allowedStatuses = ['Awaiting Payment', 'Placed', 'Dispatched', 'Delivered', 'Cancelled'];

        if (in_array($newStatus, $allowedStatuses, true)) {
            $oldStatus = $order['status'];

            // Forward-only validation for fulfillment
            $sequence = ['Awaiting Payment' => 0, 'Placed' => 1, 'Dispatched' => 2, 'Delivered' => 3];
            $currentStep = $sequence[$oldStatus] ?? 1;
            $newStep = $sequence[$newStatus] ?? 1;

            // Guard: Cannot dispatch or deliver unpaid online order
            if (in_array($newStatus, ['Dispatched', 'Delivered'], true) && $order['payment_method'] === 'online' && $order['payment_status'] !== 'paid') {
                set_flash('error', "Cannot set status to '{$newStatus}': Online payment is not yet verified ('{$order['payment_status']}').");
            } elseif ($newStatus !== 'Cancelled' && $newStep < $currentStep) {
                set_flash('error', "Fulfillment progression is forward-only ({$oldStatus} &rarr; {$newStatus} is prohibited).");
            } else {
                $up = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $up->execute([$newStatus, $orderId]);

                admin_audit_log('update', 'order_status', (string)$orderId, $oldStatus, $newStatus);
                payment_log_audit(
                    $orderId,
                    null,
                    'admin_fulfillment_status_changed',
                    'admin',
                    (string)($currentAdmin['id'] ?? 0),
                    $oldStatus,
                    $newStatus
                );

                set_flash('success', "Order fulfillment status updated to '{$newStatus}'.");
            }
        } else {
            set_flash('error', 'Invalid fulfillment status specified.');
        }
        redirect(admin_url("order-detail.php?id={$orderId}"));
    }

    // 2. Re-check Gateway Status with Razorpay API
    if ($action === 'recheck_razorpay') {
        $rzpOrderId = $order['razorpay_order_id'];

        if (empty($rzpOrderId)) {
            set_flash('error', 'No Razorpay Order ID associated with this order.');
            redirect(admin_url("order-detail.php?id={$orderId}"));
        }

        try {
            // Fetch order payments from Razorpay
            $response = razorpay_request('GET', "/v1/orders/" . urlencode($rzpOrderId) . "/payments");
            $items = $response['items'] ?? [];

            $foundPaid = false;
            foreach ($items as $item) {
                if (($item['status'] ?? '') === 'captured') {
                    $item['signature_verified'] = 1;
                    order_mark_paid($pdo, $orderId, $item, 'admin', (string)($currentAdmin['id'] ?? 0));
                    $foundPaid = true;
                    set_flash('success', "Re-check completed: Gateway confirmed payment {$item['id']} captured. Order marked as paid.");
                    break;
                }
            }

            if (!$foundPaid) {
                if (!empty($items)) {
                    $latest = $items[0];
                    set_flash('info', "Razorpay shows payment {$latest['id']} in status '{$latest['status']}'.");
                } else {
                    set_flash('warning', "Razorpay reports no payment attempts received for Order {$rzpOrderId}.");
                }
            }
        } catch (Exception $e) {
            error_log("Admin recheck Razorpay failed: " . $e->getMessage());
            set_flash('error', "Gateway check error: " . $e->getMessage());
        }

        redirect(admin_url("order-detail.php?id={$orderId}"));
    }

    // 3. Confirm Cash Collected (For COD Orders)
    if ($action === 'confirm_cod') {
        if ($order['payment_method'] === 'cod') {
            $oldPayStatus = $order['payment_status'];
            $up = $pdo->prepare("UPDATE orders SET payment_status = 'paid', paid_at = COALESCE(paid_at, NOW()) WHERE id = ?");
            $up->execute([$orderId]);

            admin_audit_log('update', 'cod_payment', (string)$orderId, $oldPayStatus, 'paid');
            payment_log_audit(
                $orderId,
                null,
                'cod_cash_collected_confirmed',
                'admin',
                (string)($currentAdmin['id'] ?? 0),
                $oldPayStatus,
                'paid',
                ['grand_total' => $order['grand_total']]
            );

            set_flash('success', "Cash on Delivery payment confirmed and marked as paid.");
        } else {
            set_flash('error', 'This action is only available for Cash on Delivery orders.');
        }
        redirect(admin_url("order-detail.php?id={$orderId}"));
    }

    // 4. Issue Refund via Razorpay API (Admin-Only, Step 7)
    if ($action === 'issue_refund') {
        if ($order['payment_status'] !== 'paid' && $order['payment_status'] !== 'partially_refunded') {
            set_flash('error', 'Only paid or partially refunded orders are eligible for refunds.');
            redirect(admin_url("order-detail.php?id={$orderId}"));
        }

        $refundAmountRupees = (float)($_POST['refund_amount'] ?? 0);
        $refundReason = trim($_POST['refund_reason'] ?? 'Customer cancellation / return');
        $restockInventory = !empty($_POST['restock_inventory']);

        // Find primary captured payment for this order
        $payStmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? AND status = 'captured' ORDER BY id DESC LIMIT 1");
        $payStmt->execute([$orderId]);
        $paymentRow = $payStmt->fetch();

        if (!$paymentRow) {
            set_flash('error', 'No captured payment record found for this order.');
            redirect(admin_url("order-detail.php?id={$orderId}"));
        }

        // Calculate prior processed/pending refunds
        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paise), 0) FROM refunds WHERE order_id = ? AND status IN ('processed', 'pending')");
        $sumStmt->execute([$orderId]);
        $priorRefundedPaise = (int)$sumStmt->fetchColumn();

        $totalPaidPaise = (int)$paymentRow['amount_paise'];
        $maxRefundablePaise = max(0, $totalPaidPaise - $priorRefundedPaise);
        $reqRefundPaise = (int)round($refundAmountRupees * 100);

        if ($reqRefundPaise <= 0 || $reqRefundPaise > $maxRefundablePaise) {
            set_flash('error', "Invalid refund amount. Maximum refundable amount is " . format_currency($maxRefundablePaise / 100) . ".");
            redirect(admin_url("order-detail.php?id={$orderId}"));
        }

        try {
            // Issue refund via Razorpay API
            $rzpRefund = razorpay_create_refund(
                $paymentRow['razorpay_payment_id'],
                $reqRefundPaise,
                [
                    'reason'   => $refundReason,
                    'order_id' => (string)$orderId,
                    'admin_id' => (string)($currentAdmin['id'] ?? 0)
                ]
            );

            $rzpRefundId = $rzpRefund['id'];
            $refundStatus = $rzpRefund['status'] ?? 'processed';

            // Insert into refunds table with unique constraint guard
            $refInsert = $pdo->prepare("
                INSERT INTO refunds (
                    order_id, payment_id, razorpay_refund_id, amount_paise,
                    status, reason, requested_by_admin_id, created_at
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, NOW()
                )
            ");
            $refInsert->execute([
                $orderId,
                $paymentRow['id'],
                $rzpRefundId,
                $reqRefundPaise,
                $refundStatus,
                $refundReason,
                $currentAdmin['id'] ?? null
            ]);

            // Update order payment status
            $newTotalRefunded = $priorRefundedPaise + $reqRefundPaise;
            $newPayStatus = ($newTotalRefunded >= $totalPaidPaise) ? 'refunded' : 'partially_refunded';

            $upOrder = $pdo->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
            $upOrder->execute([$newPayStatus, $orderId]);

            // Restock inventory if requested and full cancellation/refund
            if ($restockInventory && $newPayStatus === 'refunded') {
                order_release_reserved_stock($pdo, $orderId, 'Inventory restocked upon full order refund', 'admin', (string)($currentAdmin['id'] ?? 0));
            }

            admin_audit_log('create', 'refund', (string)$orderId, null, [
                'refund_id'   => $rzpRefundId,
                'amount_inr'  => $refundAmountRupees,
                'reason'      => $refundReason
            ]);

            payment_log_audit(
                $orderId,
                (int)$paymentRow['id'],
                'admin_refund_issued',
                'admin',
                (string)($currentAdmin['id'] ?? 0),
                $order['payment_status'],
                $newPayStatus,
                [
                    'razorpay_refund_id' => $rzpRefundId,
                    'amount_paise'       => $reqRefundPaise,
                    'reason'             => $refundReason
                ]
            );

            set_flash('success', "Refund of " . format_currency($refundAmountRupees) . " successfully processed with Razorpay (ID: {$rzpRefundId}).");
        } catch (Exception $e) {
            error_log("Refund execution error: " . $e->getMessage());
            set_flash('error', "Refund failed: " . $e->getMessage());
        }

        redirect(admin_url("order-detail.php?id={$orderId}"));
    }
}

// Fetch line items
$itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
$itemStmt->execute([$orderId]);
$orderItems = $itemStmt->fetchAll();

// Fetch payments history
$paymentsStmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC");
$paymentsStmt->execute([$orderId]);
$orderPayments = $paymentsStmt->fetchAll();

// Fetch refunds history
$refundsStmt = $pdo->prepare("SELECT * FROM refunds WHERE order_id = ? ORDER BY created_at DESC");
$refundsStmt->execute([$orderId]);
$orderRefunds = $refundsStmt->fetchAll();

// Fetch payment audit log
$auditStmt = $pdo->prepare("SELECT * FROM payment_audit_log WHERE order_id = ? ORDER BY created_at DESC LIMIT 20");
$auditStmt->execute([$orderId]);
$paymentAudits = $auditStmt->fetchAll();

// Calculate total refunded so far
$totalRefundedPaise = 0;
foreach ($orderRefunds as $ref) {
    if ($ref['status'] === 'processed' || $ref['status'] === 'pending') {
        $totalRefundedPaise += (int)$ref['amount_paise'];
    }
}
$totalPaidPaise = (int)round((float)$order['grand_total'] * 100);
$remainingRefundableRupees = max(0.0, round(($totalPaidPaise - $totalRefundedPaise) / 100, 2));

$status = $order['status'];
$badge = match ($status) {
    'Delivered'        => 'badge-success',
    'Dispatched'       => 'badge-info',
    'Cancelled'        => 'badge-danger',
    'Awaiting Payment' => 'badge-warning',
    default            => 'badge-warning',
};

$payStatus = $order['payment_status'] ?? 'pending';
$payBadgeStyle = match ($payStatus) {
    'paid'               => 'background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;',
    'partially_refunded' => 'background: #fef3c7; color: #b45309; border: 1px solid #fde68a;',
    'refunded'           => 'background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff;',
    'failed'             => 'background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;',
    default              => 'background: #fef9c3; color: #854d0e; border: 1px solid #fde047;',
};
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="<?= admin_url('orders.php') ?>" style="font-size: 0.8125rem; color: var(--text-muted); text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
                <span>Back to Orders Pipeline</span>
            </a>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 0.35rem; flex-wrap: wrap;">
                <h1 style="font-size: 1.75rem; margin-bottom: 0;">Order #<?= e($order['order_no']) ?></h1>
                <span class="badge <?= $badge ?>"><?= e($status) ?></span>
                <span style="font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 999px; text-transform: uppercase; <?= $payBadgeStyle ?>">
                    Payment: <?= e(str_replace('_', ' ', $payStatus)) ?>
                </span>
                <span style="font-size: 12px; font-weight: 600; background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 999px;">
                    <?= $order['payment_method'] === 'online' ? '💳 Online (Razorpay)' : '💵 Cash on Delivery' ?>
                </span>
            </div>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0;">
                Placed on <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?>
                <?php if (!empty($order['paid_at'])): ?>
                    &bull; Paid on <?= date('d M Y, h:i A', strtotime($order['paid_at'])) ?>
                <?php endif; ?>
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <?php if (!empty($order['razorpay_order_id'])): ?>
                <form action="<?= admin_url('order-detail.php?id=' . $orderId) ?>" method="POST" style="margin: 0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="recheck_razorpay">
                    <button type="submit" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.45rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                        <span>Re-check with Razorpay</span>
                    </button>
                </form>
            <?php endif; ?>

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

    <!-- Left: Order Line Items Snapshot & Financial Details -->
    <div style="display: flex; flex-direction: column; gap: 2rem;">
        
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

        <!-- Gateway Payments & Refunds Record -->
        <div class="card" style="padding: 1.5rem;">
            <h2 style="font-size: 1.15rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
                Gateway Payments &amp; Refunds
            </h2>

            <?php if (empty($orderPayments) && empty($orderRefunds)): ?>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 0;">No gateway payment attempts recorded for this order yet.</p>
            <?php else: ?>
                <?php if (!empty($orderPayments)): ?>
                    <h3 style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">Payments</h3>
                    <div style="overflow-x: auto; margin-bottom: 1rem;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-muted);">
                                    <th style="padding: 6px 8px; text-align: left;">Payment ID</th>
                                    <th style="padding: 6px 8px; text-align: right;">Amount</th>
                                    <th style="padding: 6px 8px; text-align: center;">Method</th>
                                    <th style="padding: 6px 8px; text-align: center;">Status</th>
                                    <th style="padding: 6px 8px; text-align: right;">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderPayments as $p): ?>
                                    <tr style="border-bottom: 1px solid var(--border-light);">
                                        <td style="padding: 8px; font-family: monospace; font-weight: 600;"><?= e($p['razorpay_payment_id']) ?></td>
                                        <td style="padding: 8px; text-align: right; font-weight: 700;"><?= format_currency($p['amount_paise'] / 100) ?></td>
                                        <td style="padding: 8px; text-align: center; text-transform: uppercase;"><?= e($p['method'] ?? '-') ?></td>
                                        <td style="padding: 8px; text-align: center;">
                                            <span style="font-size: 11px; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: <?= $p['status'] === 'captured' ? '#dcfce7; color: #15803d;' : '#fee2e2; color: #b91c1c;' ?>">
                                                <?= e($p['status']) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 8px; text-align: right; color: var(--text-muted);"><?= date('d M Y, h:i A', strtotime($p['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (!empty($orderRefunds)): ?>
                    <h3 style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;">Refunds</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-muted);">
                                    <th style="padding: 6px 8px; text-align: left;">Refund ID</th>
                                    <th style="padding: 6px 8px; text-align: right;">Amount</th>
                                    <th style="padding: 6px 8px; text-align: center;">Status</th>
                                    <th style="padding: 6px 8px; text-align: left;">Reason</th>
                                    <th style="padding: 6px 8px; text-align: right;">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderRefunds as $r): ?>
                                    <tr style="border-bottom: 1px solid var(--border-light);">
                                        <td style="padding: 8px; font-family: monospace; font-weight: 600;"><?= e($r['razorpay_refund_id']) ?></td>
                                        <td style="padding: 8px; text-align: right; font-weight: 700; color: #b45309;"><?= format_currency($r['amount_paise'] / 100) ?></td>
                                        <td style="padding: 8px; text-align: center;">
                                            <span style="font-size: 11px; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: #fef3c7; color: #b45309;">
                                                <?= e($r['status']) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 8px; color: var(--text-muted);"><?= e($r['reason'] ?? '-') ?></td>
                                        <td style="padding: 8px; text-align: right; color: var(--text-muted);"><?= date('d M Y, h:i A', strtotime($r['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Payment Audit Log Trail -->
        <div class="card" style="padding: 1.5rem;">
            <h2 style="font-size: 1.15rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
                Payment &amp; Status Activity Trail
            </h2>

            <?php if (empty($paymentAudits)): ?>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 0;">No audit entries logged for this order.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-muted);">
                                <th style="padding: 6px 8px; text-align: left;">Timestamp</th>
                                <th style="padding: 6px 8px; text-align: left;">Action</th>
                                <th style="padding: 6px 8px; text-align: center;">Actor</th>
                                <th style="padding: 6px 8px; text-align: center;">Status Change</th>
                                <th style="padding: 6px 8px; text-align: left;">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paymentAudits as $pa): ?>
                                <tr style="border-bottom: 1px solid var(--border-light);">
                                    <td style="padding: 8px; white-space: nowrap; color: var(--text-muted);"><?= date('d M, h:i A', strtotime($pa['created_at'])) ?></td>
                                    <td style="padding: 8px; font-weight: 600;"><?= e(str_replace('_', ' ', $pa['action'])) ?></td>
                                    <td style="padding: 8px; text-align: center;">
                                        <span style="font-size: 11px; padding: 2px 6px; border-radius: 4px; background: #f1f5f9;">
                                            <?= e($pa['actor_type']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 8px; text-align: center; color: var(--text-muted);">
                                        <?= e($pa['old_status'] ?? '-') ?> &rarr; <strong><?= e($pa['new_status'] ?? '-') ?></strong>
                                    </td>
                                    <td style="padding: 8px; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: monospace; font-size: 11px; color: var(--text-muted);">
                                        <?= e($pa['details'] ?? '') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Right: Fulfillment Controls, Payment Actions, and Refunds -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">

        <!-- Fulfillment Advancement Form -->
        <div class="card" style="padding: 1.5rem; border-top: 4px solid var(--color-accent);">
            <h3 style="font-size: 1.1rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
                Update Fulfillment Status
            </h3>

            <form action="<?= admin_url('order-detail.php?id=' . $orderId) ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_status">

                <div class="form-group">
                    <label class="form-label" for="order_status">Fulfillment Pipeline State</label>
                    <select id="order_status" name="status" class="form-control" style="font-size: 0.95rem; font-weight: 600;">
                        <option value="Awaiting Payment" <?= $status === 'Awaiting Payment' ? 'selected' : '' ?>>Awaiting Payment (Pending Gateway)</option>
                        <option value="Placed" <?= $status === 'Placed' ? 'selected' : '' ?>>1. Placed (Needs Desk Verification)</option>
                        <option value="Dispatched" <?= $status === 'Dispatched' ? 'selected' : '' ?>>2. Dispatched (En Route in Van)</option>
                        <option value="Delivered" <?= $status === 'Delivered' ? 'selected' : '' ?>>3. Delivered (Fulfilled)</option>
                        <option value="Cancelled" <?= $status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <?php if ($order['payment_method'] === 'online' && $order['payment_status'] !== 'paid'): ?>
                    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius-sm); padding: 8px 12px; font-size: 12px; color: #92400e; margin-bottom: 12px;">
                        ⚠️ <strong>Unpaid Online Order:</strong> Dispatched and Delivered statuses are blocked until payment is verified.
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary btn-block" style="padding: 0.75rem; display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <span>Update Fulfillment Status</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </form>
        </div>

        <!-- Payment Settlement & Actions Card -->
        <div class="card" style="padding: 1.5rem;">
            <h3 style="font-size: 1.1rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
                Payment &amp; Gateway Details
            </h3>

            <div style="font-size: 13.5px; line-height: 1.6;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span style="color: var(--text-muted);">Method:</span>
                    <strong><?= $order['payment_method'] === 'online' ? 'Online (Razorpay)' : 'Cash on Delivery (COD)' ?></strong>
                </div>

                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span style="color: var(--text-muted);">Payment Status:</span>
                    <strong style="text-transform: uppercase;"><?= e(str_replace('_', ' ', $order['payment_status'])) ?></strong>
                </div>

                <?php if (!empty($order['razorpay_order_id'])): ?>
                    <div style="margin-bottom: 6px;">
                        <span style="color: var(--text-muted); display: block; font-size: 12px;">Razorpay Order ID:</span>
                        <code style="font-size: 12px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"><?= e($order['razorpay_order_id']) ?></code>
                    </div>
                <?php endif; ?>

                <?php if (!empty($order['paid_at'])): ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span style="color: var(--text-muted);">Paid At:</span>
                        <span><?= date('d M Y, h:i A', strtotime($order['paid_at'])) ?></span>
                    </div>
                <?php endif; ?>

                <!-- COD Confirmation Action -->
                <?php if ($order['payment_method'] === 'cod' && $order['payment_status'] === 'pending'): ?>
                    <div style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border-light);">
                        <form action="<?= admin_url('order-detail.php?id=' . $orderId) ?>" method="POST" onsubmit="return confirm('Confirm that cash for this order has been physically collected?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="confirm_cod">
                            <button type="submit" class="btn btn-primary btn-block" style="padding: 0.65rem; font-size: 13px;">
                                Confirm Cash Collected (Mark Paid)
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Issue Refund Card (Admin-Only, Step 7) -->
        <?php if ($order['payment_method'] === 'online' && in_array($order['payment_status'], ['paid', 'partially_refunded'], true)): ?>
            <div class="card" style="padding: 1.5rem; border-top: 4px solid #f59e0b;">
                <h3 style="font-size: 1.1rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
                    Issue Gateway Refund
                </h3>

                <div style="font-size: 13px; margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span style="color: var(--text-muted);">Total Paid:</span>
                        <strong><?= format_currency($order['grand_total']) ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span style="color: var(--text-muted);">Prior Refunds:</span>
                        <span style="color: #b45309; font-weight: 600;"><?= format_currency($totalRefundedPaise / 100) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: var(--text-muted);">Remaining Refundable:</span>
                        <strong style="color: #15803d;"><?= format_currency($remainingRefundableRupees) ?></strong>
                    </div>
                </div>

                <?php if ($remainingRefundableRupees > 0): ?>
                    <form action="<?= admin_url('order-detail.php?id=' . $orderId) ?>" method="POST" id="refund-form" onsubmit="document.getElementById('refund-submit-btn').disabled = true;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="issue_refund">

                        <div class="form-group">
                            <label class="form-label" for="ref_amount" style="font-size: 12px;">Refund Amount (₹) *</label>
                            <input type="number" step="0.01" min="1" max="<?= $remainingRefundableRupees ?>" id="ref_amount" name="refund_amount" class="form-control" value="<?= $remainingRefundableRupees ?>" required style="font-size: 14px;">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="ref_reason" style="font-size: 12px;">Refund Reason *</label>
                            <input type="text" id="ref_reason" name="refund_reason" class="form-control" placeholder="e.g. Transit bottle breakage, retailer cancellation" required style="font-size: 13px;">
                        </div>

                        <div class="form-group" style="margin-bottom: 14px;">
                            <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer; color: var(--color-pine);">
                                <input type="checkbox" name="restock_inventory" value="1" checked>
                                Return items to warehouse stock (if full refund)
                            </label>
                        </div>

                        <button type="submit" id="refund-submit-btn" class="btn btn-outline btn-block" style="padding: 0.65rem; color: #b45309; border-color: #f59e0b; font-weight: 700; font-size: 13px;">
                            Process Razorpay Refund
                        </button>
                    </form>
                <?php else: ?>
                    <div style="font-size: 12px; color: var(--text-muted); text-align: center; padding: 8px; background: #f8fafc; border-radius: var(--radius-sm);">
                        This order has been fully refunded.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

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

    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
