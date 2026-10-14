<?php
/**
 * Kalika Enterprises - Admin Portal Shared Footer
 */

declare(strict_types=1);
?>
</main>

<footer style="background: #FFFFFF; border-top: 1px solid var(--color-border-light, #E2E8F0); padding: 20px 0; font-size: 13px; color: var(--color-text-muted, #4A5568); margin-top: auto;">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span>&copy; <?= date('Y') ?> <strong><?= e(get_setting('business_name', 'Kalika Enterprises')) ?></strong> &bull; Regional Distribution Network</span>
        </div>
        <div style="display: flex; align-items: center; gap: 12px; font-size: 12px; color: #64748B;">
            <span>Coverage: <strong>Niphad &bull; Dindori &bull; Chandwad &bull; Sinnar &bull; Yeola</strong></span>
            <span>&bull;</span>
            <span style="display: inline-flex; align-items: center; gap: 4px;">
                <span class="admin-status-dot"></span> Operations Portal v1.0.0
            </span>
        </div>
    </div>
</footer>

<script src="<?= public_url('assets/js/main.js') ?>"></script>
</body>
</html>
