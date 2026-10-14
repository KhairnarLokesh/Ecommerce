<?php
/**
 * Kalika Enterprises - Checkout & Order Placement Engine
 * Phase 6: Login enforcement, atomic stock decrements inside PDO transaction,
 *          out-of-stock auto-flagging, immutable order snapshotting, and offline payment notice.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/razorpay.php';

// Checkout strictly requires a logged-in customer
require_login('checkout.php');

// Lazy cleanup of any expired pending reservations before checking cart
if ($pdo) {
    cleanup_expired_pending_orders($pdo);
}

$user = current_user();
$cart = cart_get_details($pdo);
$minOrder = (float)get_setting('min_order_value', '2000.00');

// Hard Rule: Cannot checkout an empty cart or subtotal below minimum threshold
if (empty($cart['items'])) {
    set_flash('info', 'Your cart is empty. Please add items before checking out.');
    redirect(public_url('products.php'));
}

if (!$cart['is_min_reached']) {
    set_flash('error', 'Minimum order requirement not met. Please add ' . format_currency($cart['difference_to_min']) . ' more to meet the Rs 2,000 threshold.');
    redirect(public_url('cart.php'));
}

$errors = [];
$nameVal = $user['name'] ?? '';
$mobileVal = $user['mobile'] ?? '';
$addressVal = $user['address'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Security verification failed or expired. Please submit again.';
    } else {
        $nameVal = trim($_POST['name'] ?? '');
        $mobileVal = trim($_POST['mobile'] ?? '');
        $addressVal = trim($_POST['address'] ?? '');
        $paymentMethod = ($_POST['payment_method'] ?? 'online') === 'cod' ? 'cod' : 'online';

        if (strlen($nameVal) < 2) {
            $errors[] = 'Please enter a valid shop or contact name (at least 2 characters).';
        }

        if (!preg_match('/^[6-9]\d{9}$/', $mobileVal)) {
            $errors[] = 'Please enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9.';
        }

        if (strlen($addressVal) < 10) {
            $errors[] = 'Please enter a complete delivery address with landmark and town (at least 10 characters).';
        }

        if (empty($errors) && $pdo) {
            try {
                // Begin atomic order placement transaction
                $pdo->beginTransaction();

                // 1. Re-validate latest stock, variant active status, and recalculate server-side totals under lock
                $recheckCart = cart_get_details($pdo);
                if (empty($recheckCart['items'])) {
                    throw new Exception('Cart is empty. Please re-add products.');
                }
                if (!$recheckCart['is_min_reached']) {
                    throw new Exception('Cart total has fallen below the Rs 2,000 distributor minimum.');
                }

                foreach ($recheckCart['items'] as $item) {
                    $checkStmt = $pdo->prepare("
                        SELECT v.id, v.stock_qty, v.stock_status, v.is_active, p.name AS product_name, v.size_label
                        FROM product_variants v
                        INNER JOIN products p ON p.id = v.product_id
                        WHERE v.id = ?
                        FOR UPDATE
                    ");
                    $checkStmt->execute([$item['variant_id']]);
                    $vRow = $checkStmt->fetch();

                    if (!$vRow || (int)$vRow['is_active'] !== 1) {
                        throw new Exception("Product '{$item['product_name']} ({$item['size_label']})' is no longer available.");
                    }

                    if ($vRow['stock_status'] !== 'in_stock' || (int)$vRow['stock_qty'] < $item['quantity']) {
                        throw new Exception("Insufficient stock for '{$item['product_name']} ({$item['size_label']})'. Requested: {$item['quantity']}, Available: {$vRow['stock_qty']}.");
                    }
                }

                // 2. Reserve stock: decrement stock for each variant and flag out_of_stock if stock reaches 0
                foreach ($recheckCart['items'] as $item) {
                    $decStmt = $pdo->prepare("
                        UPDATE product_variants 
                        SET stock_qty = stock_qty - ?,
                            stock_status = CASE WHEN (stock_qty - ?) <= 0 THEN 'out_of_stock' ELSE stock_status END
                        WHERE id = ?
                    ");
                    $decStmt->execute([$item['quantity'], $item['quantity'], $item['variant_id']]);
                }

                // 3. Generate unique order_no (KE-YYYYMMDD-XXXX)
                $datePrefix = date('Ymd');
                $orderNo = '';
                $attempts = 0;
                while ($attempts < 10) {
                    $randomSuffix = strtoupper(bin2hex(random_bytes(2))); // 4 chars alphanumeric
                    $candidateNo = "KE-{$datePrefix}-{$randomSuffix}";
                    $noStmt = $pdo->prepare("SELECT id FROM orders WHERE order_no = ? LIMIT 1");
                    $noStmt->execute([$candidateNo]);
                    if (!$noStmt->fetch()) {
                        $orderNo = $candidateNo;
                        break;
                    }
                    $attempts++;
                }

                if (empty($orderNo)) {
                    $orderNo = "KE-{$datePrefix}-" . mt_rand(1000, 9999);
                }

                // 4. Setup statuses and expiration based on payment method
                $isOnline = ($paymentMethod === 'online');
                $orderStatus = $isOnline ? 'Awaiting Payment' : 'Placed';
                $paymentStatus = 'pending';
                $expiresAt = $isOnline ? date('Y-m-d H:i:s', time() + 1800) : null; // 30-minute reservation
                $amountPaise = (int)round((float)$recheckCart['grand_total'] * 100);

                // Create Razorpay Order if paying online
                $razorpayOrderId = null;
                if ($isOnline) {
                    $rzpOrder = razorpay_create_order($amountPaise, $orderNo, [
                        'order_no' => $orderNo,
                        'user_id'  => (string)$user['id']
                    ]);
                    $razorpayOrderId = $rzpOrder['id'] ?? null;
                }

                // 5. Insert orders record snapshot
                $orderInsert = $pdo->prepare("
                    INSERT INTO orders (
                        order_no, user_id, customer_name, mobile, delivery_address,
                        subtotal, gst_total, delivery_charge, grand_total,
                        prices_include_gst, payment_status, payment_method,
                        razorpay_order_id, expires_at, status
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?
                    )
                ");

                $orderInsert->execute([
                    $orderNo,
                    $user['id'],
                    $nameVal,
                    $mobileVal,
                    $addressVal,
                    $recheckCart['subtotal'],
                    $recheckCart['gst_total'],
                    0.00, // Strictly free delivery
                    $recheckCart['grand_total'],
                    $recheckCart['prices_include_gst'] ? 1 : 0,
                    $paymentStatus,
                    $paymentMethod,
                    $razorpayOrderId,
                    $expiresAt,
                    $orderStatus
                ]);

                $orderId = (int)$pdo->lastInsertId();

                // 6. Insert immutable snapshot into order_items
                $itemInsert = $pdo->prepare("
                    INSERT INTO order_items (
                        order_id, product_name, brand_name, variant_label,
                        unit_price, gst_percent, quantity, line_total
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($recheckCart['items'] as $item) {
                    $itemInsert->execute([
                        $orderId,
                        $item['product_name'],
                        $item['brand_name'],
                        $item['size_label'],
                        $item['unit_price'],
                        $item['gst_percent'],
                        $item['quantity'],
                        $item['line_total']
                    ]);
                }

                // 7. Clear customer shopping cart
                cart_clear($pdo);

                // 8. Audit log initial order creation
                payment_log_audit(
                    $orderId,
                    null,
                    $isOnline ? 'online_order_initiated' : 'cod_order_placed',
                    'customer',
                    (string)$user['id'],
                    null,
                    $orderStatus . '|' . $paymentStatus,
                    [
                        'order_no'          => $orderNo,
                        'payment_method'    => $paymentMethod,
                        'grand_total'       => $recheckCart['grand_total'],
                        'razorpay_order_id' => $razorpayOrderId
                    ]
                );

                // Commit transaction
                $pdo->commit();

                // 9. Redirect appropriately
                if ($isOnline) {
                    redirect(public_url("payment.php?order_id={$orderId}&auto=1"));
                } else {
                    set_flash('success', "Order placed successfully! Order #{$orderNo} is confirmed for cash on delivery settlement.");
                    redirect(public_url("order.php?id={$orderId}&confirmed=1"));
                }

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Order checkout failed: " . $e->getMessage());
                $errors[] = $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Order Checkout & Delivery | Kalika Enterprises';
$currentPage = 'checkout';
$pageDescription = 'Confirm wholesale FMCG delivery destination across Niphad, Dindori, Chandwad, Sinnar, and Yeola. Secure online payment or settlement on delivery.';

require_once __DIR__ . '/../includes/header.php';
?>
<!-- TXN HERO -->
<section class="txn-hero">
    <div class="container">
        <div class="txn-hero__eyebrow">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            Checkout &amp; Delivery
        </div>
        <h1 class="txn-hero__title">Confirm &amp; Place Order</h1>
        <p class="txn-hero__sub">Confirm delivery destination &bull; Secure online payment via Razorpay or COD &bull; 24-hr dispatch confirmation</p>
    </div>
</section>

<div class="container" style="padding-bottom: 4rem;">

    <?php if (!empty($errors)): ?>
        <div class="dd-error-box" role="alert">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="checkout-grid">

        <!-- Left: Delivery Form -->
        <div class="dd-card">
            <div class="dd-card__header">
                <h2 class="dd-card__title">Delivery Destination</h2>
                <span style="font-size: 12px; color: var(--color-text-muted);">Pre-filled from your account</span>
            </div>

            <form action="<?= public_url('checkout.php') ?>" method="POST" novalidate class="checkout-form">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="cust_name">Contact Person / Store Name *</label>
                    <input type="text" id="cust_name" name="name" class="form-control" value="<?= e($nameVal) ?>" required autocomplete="name">
                </div>

                <div class="form-group">
                    <label class="form-label" for="cust_mobile">Mobile Number *</label>
                    <div class="input-prefix">
                        <span class="input-prefix-addon">+91</span>
                        <input type="tel" id="cust_mobile" name="mobile" class="form-control" pattern="[6-9][0-9]{9}" value="<?= e($mobileVal) ?>" required placeholder="9876543210" autocomplete="tel">
                    </div>
                    <div class="field-hint">Our dispatch desk contacts this number within 24 hours to confirm load quantities.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="cust_address">Complete Commercial Delivery Address *</label>
                    <textarea id="cust_address" name="address" class="form-control" rows="4" required placeholder="Shop No, Building, Market/Road, Town/Taluka, PIN Code"><?= e($addressVal) ?></textarea>
                    <div class="field-hint">Service areas: Niphad, Dindori, Chandwad, Sinnar, Yeola.</div>
                </div>

                <!-- Payment Method Selection -->
                <div class="form-group" style="margin-top: 20px;">
                    <label class="form-label" style="margin-bottom: 10px;">Select Payment Method *</label>

                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <!-- Option 1: Online via Razorpay -->
                        <label style="display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; border: 2px solid var(--color-mint); border-radius: var(--radius-sm); background: #f0fdf4; cursor: pointer; transition: all 0.15s ease;">
                            <input type="radio" name="payment_method" value="online" checked style="margin-top: 4px; accent-color: var(--color-mint);" onchange="document.getElementById('checkout-submit-text').textContent = 'Proceed to Online Payment';">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
                                    <strong style="color: var(--color-pine); font-size: 15px;">Pay Online via Razorpay</strong>
                                    <span style="font-size: 11px; font-weight: 700; color: #047857; background: #d1fae5; padding: 2px 8px; border-radius: 999px;">RECOMMENDED</span>
                                </div>
                                <div style="font-size: 12.5px; color: var(--color-text-muted); margin-top: 2px;">
                                    Instant confirmation via UPI (Google Pay, PhonePe, Paytm), NetBanking &amp; Cards. Zero convenience surcharge.
                                </div>
                            </div>
                        </label>

                        <!-- Option 2: Cash on Delivery -->
                        <label style="display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; border: 1px solid var(--color-border-light); border-radius: var(--radius-sm); background: #ffffff; cursor: pointer; transition: all 0.15s ease;">
                            <input type="radio" name="payment_method" value="cod" style="margin-top: 4px; accent-color: var(--color-mint);" onchange="document.getElementById('checkout-submit-text').textContent = 'Confirm Wholesale Order (Cash on Delivery)';">
                            <div style="flex: 1;">
                                <strong style="color: var(--color-pine); font-size: 15px;">Cash on Delivery / Offline Settlement</strong>
                                <div style="font-size: 12.5px; color: var(--color-text-muted); margin-top: 2px;">
                                    Settle offline in cash or van transfer when our distribution van arrives at your storefront.
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn-dd-primary" style="width: 100%; padding: 15px; font-size: 15px; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 20px;">
                    <span id="checkout-submit-text">Proceed to Online Payment</span>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </form>
        </div>

        <!-- Right: Sidebar -->
        <div style="display: flex; flex-direction: column; gap: 24px;">

            <!-- Order Snapshot -->
            <div class="summary-panel" style="position: static;">
                <div class="summary-panel__head">
                    <h3 class="summary-panel__head-title">Order Summary &nbsp;
                        <span style="font-family: var(--font-pixel); font-size: 13px; color: var(--color-mint);"><?= $cart['item_count'] ?> units</span>
                    </h3>
                </div>
                <div class="summary-panel__body" style="padding-top: 16px;">
                    <div class="order-snapshot__items">
                        <?php foreach ($cart['items'] as $item): ?>
                            <div class="snapshot-item">
                                <div>
                                    <div class="snapshot-item__name"><?= e($item['product_name']) ?></div>
                                    <div class="snapshot-item__variant"><?= e($item['size_label']) ?> &bull; <?= $item['quantity'] ?> &times; <?= format_currency($item['unit_price']) ?></div>
                                </div>
                                <div class="snapshot-item__total"><?= format_currency($item['line_total']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="border-top: 1px solid var(--color-border-light); margin-top: 12px; padding-top: 12px;">
                        <div class="summary-row">
                            <span class="summary-row__label">Subtotal</span>
                            <strong class="summary-row__value"><?= format_currency($cart['subtotal']) ?></strong>
                        </div>
                        <div class="summary-row">
                            <span class="summary-row__label">GST <?= $cart['prices_include_gst'] ? '(included)' : '(extra)' ?></span>
                            <span style="font-size: 13px; color: var(--color-text-muted); font-weight: 600;"><?= format_currency($cart['gst_total']) ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-row__label">Delivery</span>
                            <span class="free-chip">FREE</span>
                        </div>
                    </div>

                    <div class="summary-total">
                        <span class="summary-total__label">Total Payable</span>
                        <span class="summary-total__value"><?= format_currency($cart['grand_total']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Fulfillment Commitments -->
            <div class="dd-card">
                <h3 style="font-family: var(--font-editorial); font-size: 15px; color: var(--color-pine); margin-bottom: 4px;">Fulfillment Commitments</h3>
                <ul class="commitments-list">
                    <li>
                        <div class="commitment-check">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </div>
                        <div>
                            <span class="commitment-title">24-Hour Desk Confirmation</span>
                            <span class="commitment-sub">Direct call to verify crate count before dispatch.</span>
                        </div>
                    </li>
                    <li>
                        <div class="commitment-check">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </div>
                        <div>
                            <span class="commitment-title">2–3 Working Days Delivery</span>
                            <span class="commitment-sub">Direct unloading at your shop front via distribution van.</span>
                        </div>
                    </li>
                    <li>
                        <div class="commitment-check">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </div>
                        <div>
                            <span class="commitment-title">2-Day Return Window</span>
                            <span class="commitment-sub">Transit damage or expiry claims filed within 2 days.</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
