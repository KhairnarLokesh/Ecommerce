<?php
/**
 * Kalika Enterprises - Customer Footer
 * Step 0 & 1: Strict tokens, clean typography, allowed facts only, inline SVG icons.
 */

declare(strict_types=1);
?>
</main>
<!-- Main Content Area Ends -->

<!-- Footer (Navy #14213D, Max 15% Screen Presence) -->
<footer class="site-footer" role="contentinfo">
    <div class="container">
        <div class="footer-grid">
            <!-- Col 1: About -->
            <div>
                <div class="footer-heading"><?= e(get_setting('business_name', 'Kalika Enterprises')) ?></div>
                <p style="font-size: 14px; line-height: 1.5; color: #CBD5E1; margin-bottom: 12px;">
                    Food and beverage distribution serving Niphad, Dindori, Chandwad, Sinnar, and Yeola.
                </p>
                <div style="font-size: 12px; color: #94A3B8; line-height: 1.6;">
                    <strong>Service Areas:</strong> Niphad, Dindori, Chandwad, Sinnar, Yeola<br>
                    <strong>Minimum Order:</strong> Rs 2,000 (Free delivery on Rs 2,000+)
                </div>
            </div>

            <!-- Col 2: Policies -->
            <div>
                <div class="footer-heading">Policies</div>
                <ul class="footer-links-list">
                    <li><a href="<?= public_url('policies.php') ?>">Minimum Order (Rs 2,000)</a></li>
                    <li><a href="<?= public_url('policies.php#delivery') ?>">Delivery (2-3 working days)</a></li>
                    <li><a href="<?= public_url('policies.php#confirmation') ?>">Order Confirmation (24 hours)</a></li>
                    <li><a href="<?= public_url('policies.php#refund') ?>">Refund Policy (2 days)</a></li>
                </ul>
            </div>

            <!-- Col 3: Customer Account -->
            <div>
                <div class="footer-heading">Account</div>
                <ul class="footer-links-list">
                    <li><a href="<?= public_url('cart.php') ?>">Cart</a></li>
                    <li><a href="<?= public_url('orders.php') ?>">My Orders</a></li>
                    <li><a href="<?= public_url('login.php') ?>">Customer Login</a></li>
                    <li><a href="<?= public_url('register.php') ?>">Register</a></li>
                    <li><a href="<?= admin_url('login.php') ?>" style="color: #F3C98B; font-weight: 600;">🔒 Login as Admin</a></li>
                </ul>
            </div>

            <!-- Col 4: Contact -->
            <div>
                <div class="footer-heading">Contact</div>
                <div style="font-size: 14px; line-height: 1.6; color: #CBD5E1;">
                    <p style="margin-bottom: 8px;">
                        <?= e(get_setting('business_address', 'Shop No. 4, Raghukul Sankul, Jopul Road, Pimpalgaon (Baswant), Tal. Niphad, Dist. Nashik, Maharashtra – 422201')) ?>
                    </p>
                    <p>
                        Email: <a href="mailto:<?= e(get_setting('business_email', 'deepakpawar4312@gmail.com')) ?>" style="color: #FFFFFF; text-decoration: underline;"><?= e(get_setting('business_email', 'deepakpawar4312@gmail.com')) ?></a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Sub-footer Legal Bar -->
        <div class="footer-bottom-bar">
            <div>
                &copy; <?= date('Y') ?> <?= e(get_setting('business_name', 'Kalika Enterprises')) ?>. Food and beverage distribution.
            </div>
            <div>
                Offline payment on delivery (Cash / Van UPI) &bull; No online payment &bull; No WhatsApp orders
            </div>
        </div>
    </div>
</footer>

<!-- Core JavaScript -->
<script src="<?= public_url('assets/js/main.js') ?>"></script>
<script src="<?= public_url('assets/js/specular-button.js') ?>"></script>
</body>
</html>
