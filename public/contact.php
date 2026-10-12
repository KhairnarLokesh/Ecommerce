<?php
/**
 * Kalika Enterprises - Contact Us & Retailer Inquiries Desk
 * Phase 4: Validates and persists contact inquiries into contact_messages table
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$errors = [];
$successMessage = '';

$nameVal = '';
$mobileVal = '';
$areaVal = '';
$messageVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    if (!csrf_verify()) {
        $errors[] = 'Security token invalid or expired. Please refresh and try again.';
    } else {
        $nameVal = trim($_POST['name'] ?? '');
        $mobileVal = trim($_POST['mobile'] ?? '');
        $areaVal = trim($_POST['area'] ?? '');
        $messageVal = trim($_POST['message'] ?? '');

        // Validation
        if (empty($nameVal)) {
            $errors[] = 'Please enter your name or retail shop name.';
        } elseif (mb_strlen($nameVal) > 100) {
            $errors[] = 'Name cannot exceed 100 characters.';
        }

        if (empty($mobileVal) || !preg_match('/^[6-9]\d{9}$/', $mobileVal)) {
            $errors[] = 'Please enter a valid 10-digit Indian mobile number.';
        }

        if (empty($areaVal)) {
            $errors[] = 'Please select your service area / tehsil.';
        }

        if (empty($messageVal)) {
            $errors[] = 'Please enter your message or inquiry.';
        } elseif (mb_strlen($messageVal) > 2000) {
            $errors[] = 'Message cannot exceed 2000 characters.';
        }

        // Insert into contact_messages table
        if (empty($errors)) {
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO `contact_messages` (`name`, `mobile`, `area`, `message`)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$nameVal, $mobileVal, $areaVal, $messageVal]);
                    
                    set_flash('success', 'Thank you! Your inquiry has been submitted. Our dispatch desk will contact you within 24 hours.');
                    redirect(public_url('contact.php'));
                } catch (PDOException $e) {
                    error_log("Contact message insertion failed: " . $e->getMessage());
                    $errors[] = 'A server error occurred while saving your message. Please try again or email us directly.';
                }
            } else {
                $errors[] = 'Database service unavailable. Please email us directly.';
            }
        }
    }
}

$pageTitle = 'Contact Us | Kalika Enterprises';
$currentPage = 'contact';
$pageDescription = 'Contact Kalika Enterprises for wholesale FMCG product supply inquiries, price lists, and distribution delivery in Niphad, Dindori, Chandwad, Sinnar, and Yeola.';

require_once __DIR__ . '/../includes/header.php';

$businessAddress = get_setting('business_address', 'Shop No. 4, Raghukul Sankul, Jopul Road, Pimpalgaon (Baswant), Tal. Niphad, Dist. Nashik, Maharashtra – 422201');
$businessEmail = get_setting('business_email', 'deepakpawar4312@gmail.com');
$serviceAreas = get_setting('service_areas', 'Niphad, Dindori, Chandwad, Sinnar, Yeola');
?>

<!-- Info Hero Section -->
<section class="info-hero">
    <div class="dd-container">
        <div style="max-width: 960px; margin: 0 auto;">
            <div class="info-hero__eyebrow">Trade Inquiries &amp; Support</div>
            <h1 class="info-hero__title">Contact Distribution Desk</h1>
            <p class="info-hero__sub">
                Direct communication desk for local grocery stores, supermarkets, and wholesale procurement across Nashik district.
            </p>
        </div>
    </div>
</section>

<div class="dd-container" style="padding-bottom: 72px;">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert" style="margin-bottom: 24px; max-width: 960px; margin-left: auto; margin-right: auto;">
            <strong>Please correct the following:</strong>
            <ul style="margin: 6px 0 0 18px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="contact-grid" style="max-width: 960px; margin: 0 auto;">
        <!-- Contact Details Card -->
        <div class="info-card" style="margin-bottom: 0;">
            <div class="info-hero__eyebrow" style="color: var(--color-pine); margin-bottom: 8px;">Warehouse Hub</div>
            <h2 class="info-card__title">Distribution Coordinates</h2>
            
            <div class="contact-info-block">
                <div class="contact-info-block__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                </div>
                <div>
                    <div class="contact-info-block__label">Godown &amp; Office</div>
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
                    <div class="contact-info-block__value">Mon – Sat &bull; 9:00 AM – 7:00 PM</div>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <div class="contact-info-block__label" style="margin-bottom: 8px;">Designated Tehsils</div>
                <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                    <?php 
                    $areas = explode(',', $serviceAreas);
                    foreach ($areas as $area): 
                    ?>
                        <span class="area-chip" style="font-size: 11px; padding: 4px 10px;">
                            <?= e(trim($area)) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Inquiries Form Card -->
        <div class="info-card contact-form" style="margin-bottom: 0;">
            <div class="info-hero__eyebrow" style="color: var(--color-pine); margin-bottom: 8px;">Direct Desk Contact</div>
            <h2 class="info-card__title">Send Store Inquiry</h2>
            
            <form action="<?= public_url('contact.php') ?>" method="POST" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="contact_name">Your Name / Retail Shop Name *</label>
                    <input type="text" id="contact_name" name="name" class="form-control" placeholder="e.g. Omkar General Store / Sunil Pawar" value="<?= e($nameVal) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="contact_mobile">10-Digit Mobile Number *</label>
                    <div class="input-prefix">
                        <span class="input-prefix-addon">+91</span>
                        <input type="tel" id="contact_mobile" name="mobile" class="form-control" pattern="[6-9][0-9]{9}" placeholder="9876543210" value="<?= e($mobileVal) ?>" required>
                    </div>
                    <div class="field-hint">Our dispatch desk will contact this number within 24 hours.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="contact_area">Shop Location (Tehsil) *</label>
                    <select id="contact_area" name="area" class="form-control" required>
                        <option value="">-- Choose Tehsil / Service Area --</option>
                        <?php foreach ($areas as $area): $trimmed = trim($area); ?>
                            <option value="<?= e($trimmed) ?>" <?= ($areaVal === $trimmed) ? 'selected' : '' ?>>
                                <?= e($trimmed) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="Other Region" <?= ($areaVal === 'Other Region') ? 'selected' : '' ?>>Other Region in Nashik</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="contact_message">Inquiry / Stock Requirements *</label>
                    <textarea id="contact_message" name="message" class="form-control" rows="4" placeholder="Mention products of interest (e.g. Campa Cola 500ml, Euro Chips cases) or questions..." required><?= e($messageVal) ?></textarea>
                </div>

                <button type="submit" class="dd-btn dd-btn--mint" style="width: 100%; justify-content: center;">
                    Submit Inquiry
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
