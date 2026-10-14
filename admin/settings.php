<?php
/**
 * Kalika Enterprises - Business Settings & Content Configuration
 * Phase 7: Manage distributor info, legal IDs (GSTIN/FSSAI), min order value, GST mode, and policies.
 */

declare(strict_types=1);

$adminPage = 'settings';
$adminTitle = 'Business Settings & Policies';

require_once __DIR__ . '/includes/header.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Security token invalid or expired. Please refresh the page.';
    } elseif ($pdo) {
        $allowedKeys = [
            'business_name',
            'business_tagline',
            'business_address',
            'business_email',
            'business_phone',
            'business_gstin',
            'business_fssai',
            'service_areas',
            'min_order_value',
            'prices_include_gst',
            'order_confirmation_timeline',
            'delivery_timeline',
            'refund_policy_days',
            'refund_policy_text'
        ];

        try {
            // Fetch current settings to track modifications
            $currentSettings = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);

            $stmt = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");

            foreach ($allowedKeys as $k) {
                $val = trim($_POST[$k] ?? '');
                if ($k === 'prices_include_gst') {
                    $val = isset($_POST['prices_include_gst']) ? '1' : '0';
                }
                $oldVal = $currentSettings[$k] ?? null;
                $stmt->execute([$k, $val]);

                if ($oldVal !== $val) {
                    admin_audit_log('update', 'settings', $k, $oldVal, $val);
                    if ($k === 'prices_include_gst') {
                        admin_audit_log('update', 'gst', 'prices_include_gst', $oldVal, $val);
                    }
                }
            }

            set_flash('success', 'Business settings and policies updated successfully.');
            redirect(admin_url('settings.php'));
        } catch (PDOException $e) {
            error_log("Settings update failed: " . $e->getMessage());
            $error = 'Database error while saving settings: ' . $e->getMessage();
        }
    }
}

// Fetch all current settings
$currentSettings = [];
if ($pdo) {
    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
        foreach ($rows as $r) {
            $currentSettings[$r['setting_key']] = $r['setting_value'];
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch settings: " . $e->getMessage());
    }
}
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; margin-bottom: 0.25rem;">Business Settings &amp; Policies</h1>
            <p style="font-size: 0.9375rem; color: var(--text-muted); margin-bottom: 0;">
                Configure enterprise parameters, minimum order requirements, tax compliance, and public policy texts.
            </p>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-error" role="alert" style="margin-bottom: 1.5rem;">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<form action="<?= admin_url('settings.php') ?>" method="POST">
    <?= csrf_field() ?>

    <div class="grid grid-cols-2" style="gap: 2rem; align-items: flex-start;">
        <!-- Column 1: Business Profile & Legal Compliance -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            <div class="card" style="padding: 1.75rem;">
                <h2 style="font-size: 1.2rem; margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
                    1. Business Profile &amp; Contact
                </h2>

                <div class="form-group">
                    <label class="form-label" for="set_name">Business Name *</label>
                    <input type="text" id="set_name" name="business_name" class="form-control" value="<?= e($currentSettings['business_name'] ?? 'Kalika Enterprises') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="set_tagline">Tagline / Subheading</label>
                    <input type="text" id="set_tagline" name="business_tagline" class="form-control" value="<?= e($currentSettings['business_tagline'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="set_addr">Physical Business &amp; Warehouse Address *</label>
                    <textarea id="set_addr" name="business_address" class="form-control" rows="3" required><?= e($currentSettings['business_address'] ?? '') ?></textarea>
                </div>

                <div class="grid grid-cols-2" style="gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="set_email">Support Email *</label>
                        <input type="email" id="set_email" name="business_email" class="form-control" value="<?= e($currentSettings['business_email'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="set_phone">Support / Landline Contact</label>
                        <input type="text" id="set_phone" name="business_phone" class="form-control" value="<?= e($currentSettings['business_phone'] ?? '') ?>" placeholder="e.g. 02550-XXXXXX">
                    </div>
                </div>
            </div>

            <!-- Legal IDs Card -->
            <div class="card" style="padding: 1.75rem;">
                <h2 style="font-size: 1.2rem; margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
                    2. Legal &amp; Tax Registration Numbers
                </h2>

                <div class="form-group">
                    <label class="form-label" for="set_gstin">GSTIN (Goods and Services Tax Identification Number)</label>
                    <input type="text" id="set_gstin" name="business_gstin" class="form-control" value="<?= e($currentSettings['business_gstin'] ?? '') ?>" placeholder="e.g. 27AAAAA0000A1Z5 (Leave blank if pending confirmation)">
                    <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.25rem;">
                        Appears on formal tax invoices and customer receipts.
                    </span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="set_fssai">FSSAI License Number</label>
                    <input type="text" id="set_fssai" name="business_fssai" class="form-control" value="<?= e($currentSettings['business_fssai'] ?? '') ?>" placeholder="e.g. 115XXXXXXXXXXXXXXXX">
                    <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.25rem;">
                        Food safety registration number displayed on product invoices.
                    </span>
                </div>
            </div>
        </div>

        <!-- Column 2: Commercial Rules & Policies -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            <!-- Commercial Logistics Rules -->
            <div class="card" style="padding: 1.75rem;">
                <h2 style="font-size: 1.2rem; margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
                    3. Wholesale Ordering &amp; Logistics Rules
                </h2>

                <div class="form-group">
                    <label class="form-label" for="set_min">Minimum Order Threshold (₹) *</label>
                    <input type="number" step="1" min="0" id="set_min" name="min_order_value" class="form-control" value="<?= e($currentSettings['min_order_value'] ?? '2000.00') ?>" required>
                    <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.25rem;">
                        Hard rule: The cart engine strictly blocks checkout if subtotal is below this amount.
                    </span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="set_areas">Authorized Delivery Service Areas *</label>
                    <input type="text" id="set_areas" name="service_areas" class="form-control" value="<?= e($currentSettings['service_areas'] ?? 'Niphad, Dindori, Chandwad, Sinnar, Yeola') ?>" required>
                    <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.25rem;">
                        Comma-separated talukas/towns covered with 100% Free Regional Delivery.
                    </span>
                </div>

                <div class="form-group" style="background: #f8fafc; padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light); margin-bottom: 0.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.6rem; font-weight: 700; cursor: pointer; color: var(--color-primary); margin-bottom: 0;">
                        <input type="checkbox" name="prices_include_gst" value="1" <?= (($currentSettings['prices_include_gst'] ?? '1') === '1') ? 'checked' : '' ?>>
                        Product Rates Include GST (Inclusive Mode)
                    </label>
                    <span style="font-size: 0.75rem; color: var(--text-muted); margin-left: 1.6rem; display: block; margin-top: 0.2rem;">
                        When checked, variant price is treated as GST-inclusive. When unchecked, GST is calculated as an extra surcharge.
                    </span>
                </div>
            </div>

            <!-- Public Policy Statements -->
            <div class="card" style="padding: 1.75rem;">
                <h2 style="font-size: 1.2rem; margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
                    4. Operational Guarantees &amp; Policy Texts
                </h2>

                <div class="form-group">
                    <label class="form-label" for="set_confirm_time">Order Confirmation Timeline</label>
                    <input type="text" id="set_confirm_time" name="order_confirmation_timeline" class="form-control" value="<?= e($currentSettings['order_confirmation_timeline'] ?? 'Within 24 hours') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="set_del_time">Delivery Fulfillment Timeline</label>
                    <input type="text" id="set_del_time" name="delivery_timeline" class="form-control" value="<?= e($currentSettings['delivery_timeline'] ?? '2-3 working days') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="set_refund_days">Return Window (Days)</label>
                    <input type="number" min="1" max="30" id="set_refund_days" name="refund_policy_days" class="form-control" value="<?= e($currentSettings['refund_policy_days'] ?? '2') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="set_refund_text">Refund &amp; Return Policy Text</label>
                    <textarea id="set_refund_text" name="refund_policy_text" class="form-control" rows="3" required><?= e($currentSettings['refund_policy_text'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Save Button Dock -->
    <div style="margin-top: 2rem; background: #ffffff; padding: 1.25rem 2rem; border-radius: var(--radius-md); border: 1px solid var(--border-light); display: flex; justify-content: flex-end; box-shadow: var(--shadow-sm);">
        <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.85rem 2.5rem; font-size: 1rem; font-weight: 700;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
            </svg>
            Save All Settings &amp; Policies
        </button>
    </div>
</form>

<?php
require_once __DIR__ . '/includes/footer.php';
