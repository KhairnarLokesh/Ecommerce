<?php
/**
 * Kalika Enterprises - Razorpay Checkout & Payment Launcher
 * Renders Razorpay modal checkout, handles dismissals, displays 30-minute stock reservation timer,
 * allows payment retry, and provides cancellation with immediate stock release.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/razorpay.php';

require_login('orders.php');

$user = current_user();
$orderId = (int)($_GET['order_id'] ?? ($_POST['order_id'] ?? 0));

if ($orderId <= 0 || !$pdo) {
    set_flash('error', 'Invalid order reference.');
    redirect(public_url('orders.php'));
}

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect(public_url('orders.php'));
}

// IDOR Check
if ((int)$order['user_id'] !== (int)$user['id'] && !is_admin()) {
    http_response_code(403);
    set_flash('error', 'Unauthorized access to order.');
    redirect(public_url('orders.php'));
}

// Handle Order Cancellation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    if (!csrf_verify()) {
        set_flash('error', 'Security token invalid.');
    } else {
        if ($order['payment_status'] !== 'paid') {
            order_release_reserved_stock($pdo, $orderId, 'Cancelled by retailer before payment', 'customer', (string)$user['id']);
            set_flash('info', "Order #{$order['order_no']} has been cancelled and stock released.");
            redirect(public_url('cart.php'));
        }
    }
}

// If already paid, redirect straight to confirmed order view
if ($order['payment_status'] === 'paid') {
    redirect(public_url("order.php?id={$orderId}&confirmed=1"));
}

// Check Expiration (30-Minute Reservation Window)
$now = time();
$expiresAt = !empty($order['expires_at']) ? strtotime($order['expires_at']) : 0;
$isExpired = ($expiresAt > 0 && $now >= $expiresAt);

if ($isExpired || $order['status'] === 'Cancelled') {
    if ($order['status'] !== 'Cancelled') {
        order_release_reserved_stock($pdo, $orderId, '30-minute pending payment reservation expired', 'system', 'lazy_checkout');
    }
}

// If not expired, ensure Razorpay order ID exists (REUSE if already created, never duplicate)
$razorpayOrderId = $order['razorpay_order_id'];
$amountPaise = (int)round((float)$order['grand_total'] * 100);

if (!$isExpired && $order['status'] !== 'Cancelled' && empty($razorpayOrderId)) {
    try {
        $rzpOrder = razorpay_create_order($amountPaise, (string)$order['order_no'], [
            'order_id' => (string)$orderId,
            'user_id'  => (string)$user['id']
        ]);
        $razorpayOrderId = $rzpOrder['id'];

        $upStmt = $pdo->prepare("UPDATE orders SET razorpay_order_id = ? WHERE id = ?");
        $upStmt->execute([$razorpayOrderId, $orderId]);
        $order['razorpay_order_id'] = $razorpayOrderId;

        payment_log_audit(
            $orderId,
            null,
            'razorpay_order_created',
            'customer',
            (string)$user['id'],
            $order['payment_status'],
            $order['payment_status'],
            ['razorpay_order_id' => $razorpayOrderId, 'amount_paise' => $amountPaise]
        );
    } catch (Exception $e) {
        error_log("Failed to create Razorpay Order for #{$orderId}: " . $e->getMessage());
        $rzpError = $e->getMessage();
    }
}

// Fetch line items
$itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemsStmt->execute([$orderId]);
$orderItems = $itemsStmt->fetchAll();

$remainingSeconds = max(0, $expiresAt - $now);

$pageTitle = 'Complete Payment - Order #' . e($order['order_no']) . ' | Kalika Enterprises';
$currentPage = 'checkout';
$pageDescription = 'Pay securely online for Kalika Enterprises wholesale order #' . e($order['order_no']) . ' via Razorpay.';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="txn-hero" style="padding: 28px 0 24px;">
    <div class="dd-container">
        <div class="txn-hero__eyebrow">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
            Secure Payment Gateway
        </div>
        <h1 class="txn-hero__title" style="margin-bottom: 4px;">Complete Payment for Order #<?= e($order['order_no']) ?></h1>
        <p class="txn-hero__sub">Wholesale FMCG &bull; Instant Online Settlement via UPI, Cards &amp; NetBanking</p>
    </div>
</section>

<div class="dd-container" style="padding-bottom: 4rem;">

    <?php if ($isExpired || $order['status'] === 'Cancelled'): ?>
        <!-- Order Expired or Cancelled Card -->
        <div class="dd-card" style="text-align: center; padding: 3rem 1.5rem; max-width: 600px; margin: 0 auto;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: #fee2e2; color: #ef4444; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
            </div>
            <h2 style="font-size: 1.5rem; margin-bottom: 0.5rem; color: var(--color-pine);">Order Window Expired</h2>
            <p style="color: var(--color-text-muted); font-size: 0.95rem; line-height: 1.5; margin-bottom: 1.5rem;">
                The 30-minute payment reservation window for order <strong>#<?= e($order['order_no']) ?></strong> has lapsed.
                Reserved crate quantities have been returned to warehouse inventory to prevent stock locking.
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="<?= public_url('cart.php') ?>" class="btn-dd-primary" style="padding: 12px 24px; text-decoration: none;">View Cart &amp; Reorder</a>
                <a href="<?= public_url('products.php') ?>" class="btn-dd-secondary" style="padding: 12px 24px; text-decoration: none;">Browse Catalogue</a>
            </div>
        </div>
    <?php else: ?>

        <!-- Dismissed Alert Banner (Shown via JS if user closes checkout without paying) -->
        <div id="dismiss-notice" class="dd-error-box" style="display: none; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; margin-bottom: 1.5rem;" role="alert">
            <div style="display: flex; align-items: flex-start; gap: 10px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 2px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <div>
                    <strong>Payment not completed.</strong>
                    <span>Your order is held in <em>Awaiting Payment</em> and warehouse stock remains reserved for you. Click <strong>"Pay Now / Retry Payment"</strong> below to proceed.</span>
                </div>
            </div>
        </div>

        <div class="checkout-grid">

            <!-- Left: Payment Action Box -->
            <div class="dd-card">
                <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 16px; margin-bottom: 16px; border-bottom: 1px solid var(--color-border-light); flex-wrap: wrap; gap: 12px;">
                    <img src="<?= public_url('assets/img/logo.svg') ?>" alt="Kalika Enterprises" style="height: 38px; width: auto; max-width: 220px;">
                    <span style="font-size: 11px; font-weight: 700; color: #047857; background: #d1fae5; padding: 4px 10px; border-radius: 999px;">OFFICIAL DISTRIBUTOR CHECKOUT</span>
                </div>
                <div class="dd-card__header">
                    <div>
                        <h2 class="dd-card__title">Online Payment via Razorpay</h2>
                        <span style="font-size: 13px; color: var(--color-text-muted);">Protected by 256-bit bank grade encryption</span>
                    </div>
                    <span class="free-chip" style="font-size: 12px;">Order Status: Awaiting Payment</span>
                </div>

                <!-- 30-Minute Reservation Timer -->
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 8px; color: #1e40af; font-size: 14px; font-weight: 600;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                        <span>Inventory Reserved for:</span>
                    </div>
                    <div id="reservation-timer" style="font-family: var(--font-pixel); font-size: 16px; font-weight: 700; color: #1d4ed8;" data-remaining="<?= $remainingSeconds ?>">
                        --:--
                    </div>
                </div>

                <div style="margin-bottom: 24px;">
                    <label style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--color-text-muted); display: block; margin-bottom: 8px;">
                        Accepted Payment Modes (Zero Extra Charge)
                    </label>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap; font-size: 13px; color: var(--color-pine);">
                        <span style="background: var(--color-bg-alt); padding: 6px 12px; border-radius: var(--radius-sm); border: 1px solid var(--color-border-light); font-weight: 600;">
                            &bull; UPI (GPay, PhonePe, Paytm, BHIM)
                        </span>
                        <span style="background: var(--color-bg-alt); padding: 6px 12px; border-radius: var(--radius-sm); border: 1px solid var(--color-border-light); font-weight: 600;">
                            &bull; NetBanking (All Major Banks)
                        </span>
                        <span style="background: var(--color-bg-alt); padding: 6px 12px; border-radius: var(--radius-sm); border: 1px solid var(--color-border-light); font-weight: 600;">
                            &bull; Credit &amp; Debit Cards (RuPay, Visa, MC)
                        </span>
                    </div>
                </div>

                <!-- Pay Button -->
                <button type="button" id="rzp-pay-button" class="btn-dd-primary" style="width: 100%; padding: 16px; font-size: 16px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 10px; cursor: pointer;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    <span>Pay Now with Razorpay (<?= format_currency($order['grand_total']) ?>)</span>
                </button>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--color-border-light);">
                    <form action="<?= public_url('payment.php') ?>" method="POST" onsubmit="return confirm('Are you sure you want to cancel this order and release the reserved stock?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $orderId ?>">
                        <input type="hidden" name="action" value="cancel_order">
                        <button type="submit" style="background: none; border: none; color: #dc2626; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: underline; padding: 0;">
                            Cancel Order &amp; Release Stock
                        </button>
                    </form>

                    <a href="<?= public_url('orders.php') ?>" style="font-size: 13px; color: var(--color-text-muted); text-decoration: none;">
                        View My Orders &rarr;
                    </a>
                </div>
            </div>

            <!-- Right: Order Snapshot Sidebar -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="summary-panel" style="position: static;">
                    <div class="summary-panel__head">
                        <h3 class="summary-panel__head-title">Order #<?= e($order['order_no']) ?></h3>
                    </div>
                    <div class="summary-panel__body">
                        <div class="order-snapshot__items" style="max-height: 240px; overflow-y: auto;">
                            <?php foreach ($orderItems as $item): ?>
                                <div class="snapshot-item">
                                    <div>
                                        <div class="snapshot-item__name"><?= e($item['product_name']) ?></div>
                                        <div class="snapshot-item__variant"><?= e($item['variant_label']) ?> &bull; <?= $item['quantity'] ?> &times; <?= format_currency($item['unit_price']) ?></div>
                                    </div>
                                    <div class="snapshot-item__total"><?= format_currency($item['line_total']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="border-top: 1px solid var(--color-border-light); margin-top: 12px; padding-top: 12px;">
                            <div class="summary-row">
                                <span class="summary-row__label">Subtotal</span>
                                <strong><?= format_currency($order['subtotal']) ?></strong>
                            </div>
                            <div class="summary-row">
                                <span class="summary-row__label">GST Breakdown</span>
                                <span style="font-size: 13px; color: var(--color-text-muted); font-weight: 600;"><?= format_currency($order['gst_total']) ?></span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-row__label">Delivery Logistics</span>
                                <span class="free-chip">FREE</span>
                            </div>
                            <div class="summary-total">
                                <span class="summary-total__label">Total Amount</span>
                                <span class="summary-total__value"><?= format_currency($order['grand_total']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delivery Destination Preview -->
                <div class="dd-card" style="padding: 18px 22px;">
                    <h3 style="font-size: 14px; font-weight: 700; color: var(--color-pine); margin-bottom: 8px;">Delivery Details</h3>
                    <div style="font-size: 13px; line-height: 1.5; color: var(--color-text-main);">
                        <strong><?= e($order['customer_name']) ?></strong> (+91 <?= e($order['mobile']) ?>)<br>
                        <?= nl2br(e($order['delivery_address'])) ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Hidden Verification Form -->
        <form id="rzp-verify-form" action="<?= public_url('payment-verify.php') ?>" method="POST" style="display: none;">
            <?= csrf_field() ?>
            <input type="hidden" name="order_id" value="<?= $orderId ?>">
            <input type="hidden" name="razorpay_order_id" id="verify_rzp_order_id" value="">
            <input type="hidden" name="razorpay_payment_id" id="verify_rzp_payment_id" value="">
            <input type="hidden" name="razorpay_signature" id="verify_rzp_signature" value="">
        </form>

        <!-- Razorpay Checkout Integration Script -->
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var remaining = parseInt(document.getElementById('reservation-timer').getAttribute('data-remaining') || '0', 10);
            var timerEl = document.getElementById('reservation-timer');

            function updateTimer() {
                if (remaining <= 0) {
                    timerEl.textContent = 'EXPIRED';
                    timerEl.style.color = '#dc2626';
                    var btn = document.getElementById('rzp-pay-button');
                    if (btn) {
                        btn.disabled = true;
                        btn.style.opacity = '0.5';
                        btn.textContent = 'Reservation Expired (Refresh to retry)';
                    }
                    return;
                }
                var mins = Math.floor(remaining / 60);
                var secs = remaining % 60;
                timerEl.textContent = (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs;
                remaining--;
            }
            updateTimer();
            var timerInterval = setInterval(updateTimer, 1000);

            var rzpOptions = {
                key: <?= json_encode(razorpay_get_key_id()) ?>,
                amount: <?= $amountPaise ?>,
                currency: "INR",
                name: "Kalika Enterprises",
                description: "Wholesale Order #<?= e($order['order_no']) ?>",
                image: "<?= public_url('assets/img/logo.png') ?>",
                order_id: <?= json_encode($razorpayOrderId) ?>,
                prefill: {
                    name: <?= json_encode($order['customer_name']) ?>,
                    contact: <?= json_encode($order['mobile']) ?>,
                    email: <?= json_encode($user['email'] ?? '') ?>
                },
                notes: {
                    internal_order_id: "<?= $orderId ?>",
                    order_no: "<?= e($order['order_no']) ?>"
                },
                theme: {
                    color: "#0f172a"
                },
                modal: {
                    ondismiss: function() {
                        var notice = document.getElementById('dismiss-notice');
                        if (notice) {
                            notice.style.display = 'block';
                            notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    }
                },
                handler: function (response) {
                    document.getElementById('verify_rzp_order_id').value = response.razorpay_order_id;
                    document.getElementById('verify_rzp_payment_id').value = response.razorpay_payment_id;
                    document.getElementById('verify_rzp_signature').value = response.razorpay_signature;
                    document.getElementById('rzp-verify-form').submit();
                }
            };

            var rzp = null;
            function openCheckout() {
                if (remaining <= 0) {
                    alert('This order reservation has expired. Please reorder from your cart.');
                    return;
                }
                if (!rzp) {
                    rzp = new Razorpay(rzpOptions);
                }
                rzp.open();
            }

            var payBtn = document.getElementById('rzp-pay-button');
            if (payBtn) {
                payBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    openCheckout();
                });
            }

            // Auto-open on initial arrival if auto=1
            <?php if (isset($_GET['auto']) && $_GET['auto'] === '1'): ?>
                setTimeout(openCheckout, 300);
            <?php endif; ?>
        });
        </script>

    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
