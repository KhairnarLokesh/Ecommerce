<?php
/**
 * Kalika Enterprises - About Us Page
 * Phase 4: Dynamic settings-driven corporate overview
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$businessName = get_setting('business_name', 'Kalika Enterprises');
$businessAddress = get_setting('business_address', 'Shop No. 4, Raghukul Sankul, Jopul Road, Pimpalgaon (Baswant), Tal. Niphad, Dist. Nashik, Maharashtra – 422201');
$businessEmail = get_setting('business_email', 'deepakpawar4312@gmail.com');
$serviceAreas = get_setting('service_areas', 'Niphad, Dindori, Chandwad, Sinnar, Yeola');
$minOrder = get_setting('min_order_value', '2000.00');

$pageTitle = 'About Us | ' . $businessName;
$currentPage = 'about';
$pageDescription = 'Learn more about ' . $businessName . ', wholesale FMCG food and beverage distributor in Pimpalgaon Baswant serving Niphad, Dindori, Chandwad, Sinnar, and Yeola.';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Info Hero Section -->
<section class="info-hero">
    <div class="dd-container">
        <div style="max-width: 860px; margin: 0 auto;">
            <div class="info-hero__eyebrow">Direct Wholesale Distribution</div>
            <h1 class="info-hero__title">About <?= e($businessName) ?></h1>
            <p class="info-hero__sub">
                Bridging regional grocery stores and retailers directly with verified FMCG manufacturer pipelines across Nashik district.
            </p>
        </div>
    </div>
</section>

<div class="dd-container" style="padding-bottom: 72px;">
    <div style="max-width: 860px; margin: 0 auto;">
        
        <!-- Corporate Profile Card -->
        <article class="info-card">
            <div class="info-card__num">01</div>
            <h2 class="info-card__title">Who We Are &amp; Our Mission</h2>
            <p>
                Headquartered in Pimpalgaon (Baswant), <strong><?= e($businessName) ?></strong> operates as an authorized wholesale distribution partner for confirmed national and regional consumer brands including Campa beverages, Sundar confectionery, and Euro snacks.
            </p>
            <p>
                We bridge regional grocery stores directly with manufacturer pipelines. By cutting out unnecessary middle layers, we offer reliable stock availability, transparent billing with clear GST breakdowns, and consistent door-to-door fulfillment.
            </p>
        </article>

        <!-- Logistics Coverage Card -->
        <article class="info-card">
            <div class="info-card__num">02</div>
            <h2 class="info-card__title">Distribution Network &amp; Service Territories</h2>
            <p>Our dedicated delivery vehicles service five major administrative tehsils in Maharashtra on scheduled weekly routes:</p>
            
            <div style="display: flex; flex-wrap: wrap; gap: 8px; margin: 18px 0 20px;">
                <?php 
                $areas = explode(',', $serviceAreas);
                foreach ($areas as $area): 
                ?>
                    <span class="area-chip">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <?= e(trim($area)) ?>
                    </span>
                <?php endforeach; ?>
            </div>

            <p style="margin-bottom: 0;">
                All verified retailers situated within these designated service areas qualify for <strong>100% Free Doorstep Delivery</strong> on wholesale orders meeting our threshold of <?= format_currency($minOrder) ?>.
            </p>
        </article>

        <!-- Operating Guarantees -->
        <article class="info-card">
            <div class="info-card__num">03</div>
            <h2 class="info-card__title">Operating Guarantees for Retailers</h2>
            <p>Our trade policies are built to protect local store owners with clear fulfillment timelines and transparent terms:</p>
            
            <div class="guarantee-grid">
                <div class="guarantee-item">
                    <div class="guarantee-item__title">24-Hour Desk Confirmation</div>
                    <div class="guarantee-item__body">
                        Every order placed through our catalogue is verified by our desk operations team within 24 hours to confirm batch availability and route dispatch.
                    </div>
                </div>
                <div class="guarantee-item">
                    <div class="guarantee-item__title">2–3 Working Days Delivery</div>
                    <div class="guarantee-item__body">
                        Scheduled route distribution ensures goods arrive directly at your shopfront reliably within 2 to 3 working days.
                    </div>
                </div>
                <div class="guarantee-item">
                    <div class="guarantee-item__title">Offline Settlement</div>
                    <div class="guarantee-item__body">
                        Simple Cash on Delivery or agreed trade credit upon arrival. No payment gateway surcharges or processing fees.
                    </div>
                </div>
                <div class="guarantee-item">
                    <div class="guarantee-item__title">2-Day Inspection &amp; Returns</div>
                    <div class="guarantee-item__body">
                        Damaged or defective stock reported within 2 days of delivery is replaced or credited offline as per distributor policy.
                    </div>
                </div>
            </div>
        </article>

        <!-- Coordinates Card -->
        <article class="info-card">
            <div class="info-card__num">04</div>
            <h2 class="info-card__title">Godown &amp; Office Coordinates</h2>
            
            <div class="contact-info-block">
                <div class="contact-info-block__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                </div>
                <div>
                    <div class="contact-info-block__label">Physical Address</div>
                    <div class="contact-info-block__value"><?= e($businessAddress) ?></div>
                </div>
            </div>

            <div class="contact-info-block">
                <div class="contact-info-block__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                </div>
                <div>
                    <div class="contact-info-block__label">Official Email</div>
                    <div class="contact-info-block__value">
                        <a href="mailto:<?= e($businessEmail) ?>" style="color: var(--color-pine); text-decoration: none; font-weight: 700;"><?= e($businessEmail) ?></a>
                    </div>
                </div>
            </div>

            <div class="contact-info-block">
                <div class="contact-info-block__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                </div>
                <div>
                    <div class="contact-info-block__label">Operating Hours</div>
                    <div class="contact-info-block__value">Monday through Saturday &bull; 9:00 AM – 7:00 PM IST</div>
                </div>
            </div>
        </article>

    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
