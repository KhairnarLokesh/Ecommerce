<?php
/**
 * Kalika Enterprises - Shopping Cart
 * Phase 6: Server-side calculated cart, GST breakup, AJAX quantity updates,
 *          persistent DB cart for authenticated users, and strict â‚¹2,000 threshold enforcement.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cart.php';

// Detect if incoming request is an AJAX request
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

// Handle POST actions: add, update, remove, clear
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Security token invalid or expired. Please refresh the page.']);
            exit;
        }
        set_flash('error', 'Security token invalid or expired. Please refresh and try again.');
        redirect(public_url('cart.php'));
    }

    $action = trim($_POST['action'] ?? '');
    $msg = '';
    $success = true;

    if ($action === 'add') {
        $variantId = (int)($_POST['variant_id'] ?? $_POST['variant_id_select'] ?? 0);
        $qty = max(1, min(999, (int)($_POST['quantity'] ?? 1)));
        if ($variantId > 0) {
            $added = cart_add($pdo, $variantId, $qty);
            if ($added) {
                $msg = 'Item added to your wholesale cart.';
            } else {
                $success = false;
                $msg = 'Selected item or variant is currently unavailable or out of stock.';
            }
        } else {
            $success = false;
            $msg = 'Invalid product variant selected.';
        }
    } elseif ($action === 'update') {
        $variantId = (int)($_POST['variant_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);
        if ($variantId > 0) {
            cart_update($pdo, $variantId, $qty);
            $msg = ($qty <= 0) ? 'Item removed from your cart.' : 'Cart quantity updated.';
        }
    } elseif ($action === 'remove') {
        $variantId = (int)($_POST['variant_id'] ?? 0);
        if ($variantId > 0) {
            cart_remove($pdo, $variantId);
            $msg = 'Item removed from your cart.';
        }
    } elseif ($action === 'clear') {
        cart_clear($pdo);
        $msg = 'Your cart has been cleared.';
    }

    if ($isAjax) {
        $cartData = cart_get_details($pdo);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => $success,
            'message' => $msg,
            'cart'    => $cartData,
            'csrf'    => csrf_token()
        ]);
        exit;
    }

    if (!empty($msg)) {
        set_flash($success ? 'success' : 'error', $msg);
    }
    redirect(public_url('cart.php'));
}

// Fetch live calculated cart details
$cart = cart_get_details($pdo);
$minOrder = (float)get_setting('min_order_value', '2000.00');
$progressPercent = min(100, ($cart['subtotal'] > 0 ? round(($cart['subtotal'] / $minOrder) * 100) : 0));

$pageTitle = 'Wholesale Shopping Cart | Kalika Enterprises';
$currentPage = 'cart';
$pageDescription = 'Review selected FMCG food and beverage wholesale items, verify the Rs 2,000 distributor minimum order threshold, and checkout.';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- TXN HERO -->
<section class="txn-hero">
    <div class="container">
        <div class="txn-hero__eyebrow">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            Wholesale Cart
        </div>
        <h1 class="txn-hero__title">Your Wholesale Order</h1>
        <p class="txn-hero__sub">Minimum order <?= format_currency($minOrder) ?> &bull; Free regional delivery &bull; Offline payment on delivery</p>
    </div>
</section>

<div class="container" style="padding-bottom: 4rem;">

    <!-- Threshold Banner -->
    <div id="cartThresholdBanner">
        <?php if (!empty($cart['items'])): ?>
            <?php if (!$cart['is_min_reached']): ?>
                <div class="threshold-banner threshold-banner--warning" role="alert">
                    <div class="threshold-banner__text">
                        <strong>Minimum order not reached.</strong>
                        Your subtotal is <strong><?= format_currency($cart['subtotal']) ?></strong>.
                        Add <strong><?= format_currency($cart['difference_to_min']) ?></strong> more to reach the <?= format_currency($minOrder) ?> threshold.
                    </div>
                    <a href="<?= public_url('products.php') ?>" class="btn-dd-primary" style="white-space: nowrap; padding: 9px 20px; font-size: 13px; display: inline-flex; align-items: center; gap: 4px;">
                        <span>Add Items</span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>
                    <div class="threshold-banner__progress" style="flex-basis: 100%;">
                        <div class="threshold-banner__bar" style="width: <?= $progressPercent ?>%;"></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="threshold-banner threshold-banner--success" role="alert">
                    <div class="threshold-banner__text">
                        <strong style="display: inline-flex; align-items: center; gap: 4px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            Minimum met.
                        </strong>
                        Your subtotal of <strong><?= format_currency($cart['subtotal']) ?></strong> qualifies for free doorstep delivery.
                    </div>
                    <span class="free-chip">Free Delivery Active</span>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if (empty($cart['items'])): ?>
        <!-- Empty Cart -->
        <div class="cart-empty">
            <div class="cart-empty__icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="8" cy="21" r="1"></circle>
                    <circle cx="19" cy="21" r="1"></circle>
                    <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path>
                </svg>
            </div>
            <h2 class="cart-empty__title">Your Cart is Empty</h2>
            <p class="cart-empty__sub">Explore Campa Beverages, Sundar Biscuits, Euro Chips and more at distributor prices.</p>
            <a href="<?= public_url('products.php') ?>" class="btn-dd-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span>Browse Wholesale Catalogue</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
        </div>

    <?php else: ?>
        <!-- Active Cart Layout -->
        <div class="txn-grid">

            <!-- Left: Cart Items -->
            <div class="dd-card" style="padding: 0; overflow: hidden;">
                <div class="dd-card__header" style="padding: 20px 28px;">
                    <h2 class="dd-card__title">
                        Order Items
                        <span style="font-family: var(--font-pixel); font-size: 14px; color: var(--color-mint); background: var(--bg-dark); padding: 3px 10px; border-radius: var(--radius-full); margin-left: 10px;"><?= $cart['item_count'] ?></span>
                    </h2>
                    <form action="<?= public_url('cart.php') ?>" method="POST" onsubmit="return confirm('Clear your entire cart?');" style="margin: 0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="clear">
                        <button type="submit" class="btn-remove" style="display: inline-flex; align-items: center; gap: 5px;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                            Clear Cart
                        </button>
                    </form>
                </div>

                <div style="overflow-x: auto; padding: 0 28px 24px;">
                    <table class="cart-table" id="cartTable">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th style="text-align: right;">Unit Rate</th>
                                <th style="text-align: center; min-width: 110px;">Quantity</th>
                                <th style="text-align: center;">GST</th>
                                <th style="text-align: right;">Total</th>
                                <th style="text-align: center; width: 80px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart['items'] as $item): ?>
                                <tr data-variant-id="<?= (int)$item['variant_id'] ?>">
                                    <!-- Product -->
                                    <td>
                                        <div style="display: flex; gap: 14px; align-items: center;">
                                            <div class="cart-item__thumb">
                                                <?php if (!empty($item['product_image'])): ?>
                                                    <img src="<?= public_url('uploads/products/' . e($item['product_image'])) ?>" alt="<?= e($item['product_name']) ?>" style="width: 100%; height: 100%; object-fit: contain;">
                                                <?php else: ?>
                                                    <span style="font-size: 9px; font-weight: 800; color: var(--color-text-muted); letter-spacing: 0.05em;">KE</span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="cart-item__name"><?= e($item['product_name']) ?></div>
                                                <div class="cart-item__meta">
                                                    <span style="background: #F1F5F9; padding: 2px 6px; border-radius: 3px; font-weight: 600;"><?= e($item['brand_name']) ?></span>
                                                    &nbsp;&bull;&nbsp;<?= e($item['size_label']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Unit Rate -->
                                    <td class="cart-item__price"><?= format_currency($item['unit_price']) ?></td>

                                    <!-- Qty Stepper -->
                                    <td style="text-align: center;">
                                        <form action="<?= public_url('cart.php') ?>" method="POST" class="cart-update-form" style="display: inline-block; margin: 0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="variant_id" value="<?= (int)$item['variant_id'] ?>">
                                            <div class="qty-stepper">
                                                <button type="button" class="qty-stepper__btn btn-cart-dec" aria-label="Decrease">&minus;</button>
                                                <input type="number" name="quantity" class="qty-stepper__input cart-qty-val" value="<?= (int)$item['quantity'] ?>" min="1" max="999" required>
                                                <button type="button" class="qty-stepper__btn btn-cart-inc" aria-label="Increase">+</button>
                                            </div>
                                        </form>
                                    </td>

                                    <!-- GST -->
                                    <td style="text-align: center;">
                                        <span class="gst-chip"><?= number_format((float)$item['gst_percent'], 0) ?>%</span>
                                    </td>

                                    <!-- Line Total -->
                                    <td class="cart-item__total item-line-total"><?= format_currency($item['line_total']) ?></td>

                                    <!-- Remove -->
                                    <td style="text-align: center;">
                                        <form action="<?= public_url('cart.php') ?>" method="POST" class="cart-remove-form" style="margin: 0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="variant_id" value="<?= (int)$item['variant_id'] ?>">
                                            <button type="submit" class="btn-remove btn-remove--icon" title="Remove item" aria-label="Remove item" style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 4px; padding: 0;">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="cart-footer-bar" style="padding: 16px 28px 24px;">
                    <a href="<?= public_url('products.php') ?>" class="btn btn-outline" style="font-size: 13px; padding: 8px 18px; display: inline-flex; align-items: center; gap: 6px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        <span>Continue Shopping</span>
                    </a>
                    <span class="cart-footer-bar__hint">Prices &amp; inventory updated in real-time.</span>
                </div>
            </div>

            <!-- Right: Order Summary Panel -->
            <div class="summary-panel">
                <div class="summary-panel__head">
                    <h2 class="summary-panel__head-title">Order Summary</h2>
                </div>
                <div class="summary-panel__body">
                    <div class="summary-row">
                        <span class="summary-row__label">Items Subtotal</span>
                        <strong class="summary-row__value" id="summarySubtotal"><?= format_currency($cart['subtotal']) ?></strong>
                    </div>
                    <div class="summary-row">
                        <span class="summary-row__label">GST <?= $cart['prices_include_gst'] ? '(included)' : '(extra)' ?></span>
                        <span style="font-size: 13px; color: var(--color-text-muted); font-weight: 600;" id="summaryGst"><?= format_currency($cart['gst_total']) ?></span>
                    </div>
                    <div class="summary-row">
                        <div>
                            <span class="summary-row__label">Delivery Charge</span>
                            <div style="font-size: 11px; color: var(--color-evergreen); margin-top: 2px;">Regional Distribution Van</div>
                        </div>
                        <span class="free-chip">FREE</span>
                    </div>

                    <div class="summary-total">
                        <span class="summary-total__label">Grand Total</span>
                        <span class="summary-total__value" id="summaryGrandTotal"><?= format_currency($cart['grand_total']) ?></span>
                    </div>

                    <div id="checkoutActionWrapper" style="margin-top: 20px;">
                        <?php if ($cart['is_min_reached']): ?>
                            <a href="<?= public_url('checkout.php') ?>" class="btn-dd-primary" style="display: flex; align-items: center; justify-content: center; gap: 6px; text-align: center; padding: 14px;">
                                <span>Proceed to Checkout</span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </a>
                            <p style="font-size: 12px; text-align: center; color: var(--color-evergreen); margin-top: 8px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                Qualified for wholesale dispatch
                            </p>
                        <?php else: ?>
                            <button type="button" class="btn-dd-primary" disabled style="display: block; width: 100%; text-align: center; padding: 14px; opacity: 0.45; cursor: not-allowed;">
                                Proceed to Checkout
                            </button>
                            <div style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: var(--radius-sm); padding: 12px 14px; margin-top: 10px; text-align: center;">
                                <p style="font-size: 13px; color: #B45309; margin: 0; font-weight: 700;">
                                    Add <?= format_currency($cart['difference_to_min']) ?> more to unlock checkout.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="summary-panel__footer">
                    <div class="summary-terms">
                        <p>&bull; <strong>No Online Payment:</strong> Settle offline on delivery.</p>
                        <p>&bull; <strong>24-Hr Confirmation:</strong> Dispatch desk verifies quantities.</p>
                        <p>&bull; <strong>Service Areas:</strong> Niphad, Dindori, Chandwad, Sinnar, Yeola.</p>
                    </div>
                </div>
            </div>

        </div><!-- /.txn-grid -->
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const cartTable = document.getElementById('cartTable');
    if (!cartTable) return;

    document.querySelectorAll('.cart-update-form').forEach(form => {
        const input = form.querySelector('.cart-qty-val');
        const decBtn = form.querySelector('.btn-cart-dec');
        const incBtn = form.querySelector('.btn-cart-inc');

        if (decBtn && incBtn && input) {
            decBtn.addEventListener('click', () => {
                let v = parseInt(input.value, 10) || 1;
                if (v > 1) { input.value = v - 1; submitCartUpdate(form); }
            });
            incBtn.addEventListener('click', () => {
                let v = parseInt(input.value, 10) || 1;
                if (v < 999) { input.value = v + 1; submitCartUpdate(form); }
            });
            input.addEventListener('change', () => {
                let v = parseInt(input.value, 10) || 1;
                if (v < 1) input.value = 1;
                if (v > 999) input.value = 999;
                submitCartUpdate(form);
            });
        }
    });

    async function submitCartUpdate(form) {
        const formData = new FormData(form);
        formData.append('ajax', '1');
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            if (res.ok) {
                const data = await res.json();
                if (data.success && data.cart) {
                    if (data.cart.items.length === 0) { window.location.reload(); return; }
                    data.cart.items.forEach(item => {
                        const row = document.querySelector(`tr[data-variant-id="${item.variant_id}"]`);
                        if (row) {
                            const el = row.querySelector('.item-line-total');
                            if (el) el.textContent = '₹' + parseFloat(item.line_total).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    });
                    const fmt = v => '₹' + parseFloat(v).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    const sS = document.getElementById('summarySubtotal');
                    const sG = document.getElementById('summaryGst');
                    const sT = document.getElementById('summaryGrandTotal');
                    if (sS) sS.textContent = fmt(data.cart.subtotal);
                    if (sG) sG.textContent = fmt(data.cart.gst_total);
                    if (sT) sT.textContent = fmt(data.cart.grand_total);
                    window.location.reload();
                }
            } else { form.submit(); }
        } catch (e) { form.submit(); }
    }
});
</script>


<?php
require_once __DIR__ . '/../includes/footer.php';
