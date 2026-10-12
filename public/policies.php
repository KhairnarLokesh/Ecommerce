<?php
/**
 * Kalika Enterprises - Policies, Ordering Terms & Guarantees
 * Phase 4: Dynamic settings-driven operational rules
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$businessName = get_setting('business_name', 'Kalika Enterprises');
$minOrder = (float) get_setting('min_order_value', '2000.00');
$deliveryCharge = (float) get_setting('delivery_charge', '0.00');
$serviceAreas = get_setting('service_areas', 'Niphad, Dindori, Chandwad, Sinnar, Yeola');
$deliveryTimeline = get_setting('delivery_timeline', '2-3 working days');
$confirmationTimeline = get_setting('order_confirmation_timeline', 'Within 24 hours');
$refundDays = get_setting('refund_policy_days', '2');
$refundText = get_setting('refund_policy_text', 'Returns and refunds are accepted within 2 days of delivery for damaged or expired items subject to inspection. Settlement handled offline.');

$pageTitle = 'Distribution Policies & Minimum Order | ' . $businessName;
$currentPage = 'policies';
$pageDescription = 'Review wholesale order policies for ' . $businessName . ': ₹2,000 minimum order, 100% free delivery, 24h confirmation, and 2-day return policy.';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Info Hero Section -->
<section class="info-hero">
    <div class="dd-container">
        <div style="max-width: 860px; margin: 0 auto;">
            <div class="info-hero__eyebrow">Operating Guidelines &amp; Guarantees</div>
            <h1 class="info-hero__title">Wholesale Policies &amp; Ordering Terms</h1>
            <p class="info-hero__sub">
                Clear standards for retail store minimum orders, zero-cost delivery schedules, tax calculations, and
                fulfillment guarantees.
            </p>
        </div>
    </div>
</section>

<div class="dd-container" style="padding-bottom: 72px;">
    <div style="max-width: 860px; margin: 0 auto;">

        <!-- 1. Minimum Order Value -->
        <article class="info-card" id="min-order">
            <div class="info-card__num">01</div>
            <h2 class="info-card__title">Minimum Order Requirement (<?= format_currency($minOrder) ?> Rule)</h2>
            <p>
                To provide bulk wholesale distributor rates and absorb logistics transport costs, all purchases through
                <strong><?= e($businessName) ?></strong> must meet a mandatory minimum order threshold of
                <strong><?= format_currency($minOrder) ?></strong>.
            </p>
            <p style="margin-bottom: 0;">
                The catalogue cart engine strictly enforces this rule: orders below <?= format_currency($minOrder) ?>
                cannot proceed to checkout. The cart shows a live progress meter indicating the exact amount needed to
                unlock fulfillment.
            </p>
        </article>

        <!-- 2. Free Delivery -->
        <article class="info-card" id="delivery">
            <div class="info-card__num">02</div>
            <h2 class="info-card__title">100% Free Doorstep Delivery (Always <?= format_currency($deliveryCharge) ?>)
            </h2>
            <p>
                There are <strong>zero delivery fees or logistics surcharges</strong> on orders meeting the minimum
                order requirement. Delivery charge is always ₹0.00 across our authorized service territories:
            </p>
            <div style="display: flex; flex-wrap: wrap; gap: 8px; margin: 16px 0 18px;">
                <?php
                $areas = explode(',', $serviceAreas);
                foreach ($areas as $area):
                    ?>
                    <span class="area-chip">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <?= e(trim($area)) ?>
                    </span>
                <?php endforeach; ?>
            </div>
            <p style="margin-bottom: 0; font-size: 13px; color: var(--color-text-muted);">
                Our company-owned distribution vehicles service standard merchant routes on scheduled weekly intervals.
            </p>
        </article>

        <!-- 3. Confirmation & Delivery Timeline -->
        <article class="info-card" id="timelines">
            <div class="info-card__num">03</div>
            <h2 class="info-card__title">Order Confirmation &amp; Delivery Timelines</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px;">
                <div class="timeline-block">
                    <div class="timeline-block__label">Desk Confirmation</div>
                    <div class="timeline-block__value"><?= e($confirmationTimeline) ?></div>
                    <div class="timeline-block__sub">Our operations desk reviews product inventory and confirms
                        quantities.</div>
                </div>
                <div class="timeline-block">
                    <div class="timeline-block__label">Doorstep Delivery</div>
                    <div class="timeline-block__value"><?= e($deliveryTimeline) ?></div>
                    <div class="timeline-block__sub">Dispatched goods arrive directly at your store via our scheduled
                        route van.</div>
                </div>
            </div>
        </article>

        <!-- 4. Offline Payment Settlement -->
        <article class="info-card" id="payment">
            <div class="info-card__num">04</div>
            <h2 class="info-card__title">Payment Terms (Offline / Cash on Delivery Only)</h2>
            <p>
                To avoid transaction surcharge deductions and banking delays, <strong><?= e($businessName) ?> does not
                    accept online payment</strong> through cards, payment gateways, or digital wallet links.
            </p>
            <p style="margin-bottom: 0;">
                All order balances are settled offline upon physical delivery of goods, either via Cash on Delivery or
                agreed wholesale retailer trade terms.
            </p>
        </article>

        <!-- 5. Refund & Return Policy -->
        <article class="info-card" id="refund">
            <div class="info-card__num">05</div>
            <h2 class="info-card__title">Refund &amp; Return Policy (<?= e($refundDays) ?>-Day Window)</h2>
            <p>
                <?= e($refundText) ?>
            </p>
            <ul style="margin: 14px 0 0 20px; color: var(--color-text-muted); line-height: 1.7; font-size: 14px;">
                <li>Retailers must visually inspect cartons and bottle cases upon arrival with our delivery driver.</li>
                <li>Claims for transit damage, leaking bottles, or expired stock must be reported within
                    <strong><?= e($refundDays) ?> days</strong> of delivery.</li>
                <li>Approved returns will be credited against your invoice or replaced on the subsequent scheduled route
                    dispatch.</li>
                <li>Opened, broken, or tampered consumer packaging will not be accepted.</li>
            </ul>
        </article>

    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
