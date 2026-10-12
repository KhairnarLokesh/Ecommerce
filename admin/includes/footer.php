<?php
/**
 * Kalika Enterprises - Admin Portal Shared Footer
 */

declare(strict_types=1);
?>
</main>

<footer style="background: #ffffff; border-top: 1px solid #e2e8f0; padding: 1.5rem 0; font-size: 0.8125rem; color: #64748b; margin-top: auto;">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            &copy; <?= date('Y') ?> <strong><?= e(get_setting('business_name', 'Kalika Enterprises')) ?></strong> &bull; Pimpalgaon Baswant, Nashik
        </div>
        <div>
            System Version: <strong>v1.0.0-PROD</strong> &bull; Regional Distribution Control
        </div>
    </div>
</footer>

<script src="<?= public_url('assets/js/main.js') ?>"></script>
</body>
</html>
