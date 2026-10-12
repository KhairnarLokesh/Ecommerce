<?php
/**
 * Kalika Enterprises - Customer Profile Management
 * Phase 5: Account details editing, password change with current password check, authorization checks.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict server-side authorization check: Customer must be logged in
require_login('profile.php');

$currentSessionUser = current_user();
$userId = (int)$currentSessionUser['id'];

$errors = [];
$passwordErrors = [];

// Fetch fresh record from DB
$userRecord = null;
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, mobile, email, address, password_hash, created_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $userRecord = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed to load user profile: " . $e->getMessage());
    }
}

if (!$userRecord) {
    if (!$pdo) {
        $userRecord = [
            'id' => $userId,
            'name' => $currentSessionUser['name'] ?? 'Retailer Store',
            'mobile' => $currentSessionUser['mobile'] ?? '9876543210',
            'email' => $currentSessionUser['email'] ?? '',
            'address' => $currentSessionUser['address'] ?? 'Shop No. 4, Market Road, Pimpalgaon Baswant',
            'password_hash' => password_hash('Password@123', PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s'),
        ];
    } else {
        set_flash('error', 'User account not found or deactivated.');
        logout_user();
        redirect(public_url('login.php'));
    }
}

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Security token invalid or expired. Please refresh the page.';
    } else {
        $action = $_POST['action'] ?? 'update_profile';

        if ($action === 'update_profile') {
            $nameVal = trim($_POST['name'] ?? '');
            $emailVal = trim($_POST['email'] ?? '');
            $addressVal = trim($_POST['address'] ?? '');

            if (empty($nameVal) || mb_strlen($nameVal) > 100) {
                $errors[] = 'Name cannot be empty and must not exceed 100 characters.';
            }

            if (!empty($emailVal) && (!filter_var($emailVal, FILTER_VALIDATE_EMAIL) || mb_strlen($emailVal) > 150)) {
                $errors[] = 'Please enter a valid email address or leave it blank.';
            }

            if (empty($addressVal) || mb_strlen($addressVal) < 10) {
                $errors[] = 'Please provide a complete shop delivery address (min 10 characters).';
            }

            if (empty($errors)) {
                if ($pdo) {
                    try {
                        $updateStmt = $pdo->prepare("
                            UPDATE users 
                            SET name = ?, email = ?, address = ? 
                            WHERE id = ?
                        ");
                        $updateStmt->execute([
                            $nameVal,
                            !empty($emailVal) ? $emailVal : null,
                            $addressVal,
                            $userId
                        ]);

                        // Update session and cookie auth data
                        login_user([
                            'id'      => $userId,
                            'name'    => $nameVal,
                            'mobile'  => $currentSessionUser['mobile'],
                            'email'   => $emailVal,
                            'address' => $addressVal
                        ]);

                        set_flash('success', 'Profile details updated successfully.');
                        redirect(public_url('profile.php'));
                    } catch (PDOException $e) {
                        error_log("Profile update failed: " . $e->getMessage());
                        $errors[] = 'Database error while saving profile.';
                    }
                } else {
                    // Update session and cookie auth data
                    login_user([
                        'id'      => $userId,
                        'name'    => $nameVal,
                        'mobile'  => $currentSessionUser['mobile'],
                        'email'   => $emailVal,
                        'address' => $addressVal
                    ]);
                    set_flash('success', 'Profile details updated successfully.');
                    redirect(public_url('profile.php'));
                }
            }

        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($currentPassword)) {
                $passwordErrors[] = 'Please enter your current password.';
            } elseif (!password_verify($currentPassword, $userRecord['password_hash'])) {
                $passwordErrors[] = 'Current password is incorrect.';
            }

            if (strlen($newPassword) < 8) {
                $passwordErrors[] = 'New password must be at least 8 characters long.';
            } elseif ($newPassword !== $confirmPassword) {
                $passwordErrors[] = 'New password confirmation does not match.';
            }

            if (empty($passwordErrors) && $pdo) {
                try {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $passStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $passStmt->execute([$newHash, $userId]);

                    set_flash('success', 'Your password has been changed successfully.');
                    redirect(public_url('profile.php'));
                } catch (PDOException $e) {
                    error_log("Password change failed: " . $e->getMessage());
                    $passwordErrors[] = 'Database error while changing password.';
                }
            }
        }
    }
}

$pageTitle = 'My Retail Store Profile | Kalika Enterprises';
$currentPage = 'account';
$pageDescription = 'Manage your store address, contact details, and account security credentials with Kalika Enterprises.';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dd-container" style="padding-top: 40px; padding-bottom: 72px;">
    <div style="max-width: 960px; margin: 0 auto;">
        
        <!-- Profile Banner -->
        <div class="profile-banner">
            <div>
                <div class="info-hero__eyebrow" style="color: var(--color-mint); margin-bottom: 6px;">Verified Retail Partner</div>
                <h1 class="profile-banner__name"><?= e($userRecord['name']) ?></h1>
                <div class="profile-banner__meta">
                    Registered Mobile: <strong>+91 <?= e($userRecord['mobile']) ?></strong> &bull; Member since <?= date('M Y', strtotime($userRecord['created_at'])) ?>
                </div>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="<?= public_url('orders.php') ?>" class="dd-btn dd-btn--ghost" style="color: white; border-color: rgba(255,255,255,0.3); padding: 8px 16px; font-size: 13px;">
                    Past Orders
                </a>
                <a href="<?= public_url('logout.php') ?>" class="dd-btn" style="background: rgba(239,68,68,0.2); color: #FCA5A5; border: 1px solid rgba(239,68,68,0.4); padding: 8px 16px; font-size: 13px;">
                    Sign Out
                </a>
            </div>
        </div>

        <div class="profile-grid">
            <!-- Profile Info Form Card -->
            <div class="info-card profile-form" style="margin-bottom: 0;">
                <div class="info-hero__eyebrow" style="color: var(--color-pine); margin-bottom: 8px;">Store Profile</div>
                <h2 class="info-card__title">Store &amp; Delivery Details</h2>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error" role="alert" style="margin-bottom: 18px;">
                        <ul style="margin: 0 0 0 16px;">
                            <?php foreach ($errors as $e): ?>
                                <li><?= e($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="<?= public_url('profile.php') ?>" method="POST" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-group">
                        <label class="form-label" for="prof_name">Store / Contact Person Name *</label>
                        <input type="text" id="prof_name" name="name" class="form-control" value="<?= e($userRecord['name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prof_mobile">Registered Mobile Identifier</label>
                        <input type="tel" id="prof_mobile" class="form-control" value="<?= e($userRecord['mobile']) ?>" readonly>
                        <div class="field-hint">Mobile is your verified identifier and cannot be changed directly.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prof_email">Email Address (Optional)</label>
                        <input type="email" id="prof_email" name="email" class="form-control" value="<?= e($userRecord['email'] ?? '') ?>" placeholder="store@example.com">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prof_address">Default Storefront Delivery Address *</label>
                        <textarea id="prof_address" name="address" class="form-control" rows="4" required><?= e($userRecord['address']) ?></textarea>
                        <div class="field-hint">Used for scheduled distributor route logistics dispatch.</div>
                    </div>

                    <button type="submit" class="dd-btn dd-btn--mint" style="width: 100%; justify-content: center;">
                        Save Profile Changes
                    </button>
                </form>
            </div>

            <!-- Password Change Card -->
            <div class="info-card profile-form" style="margin-bottom: 0;">
                <div class="info-hero__eyebrow" style="color: var(--color-pine); margin-bottom: 8px;">Security</div>
                <h2 class="info-card__title">Change Password</h2>
                <p style="font-size: 13px; color: var(--color-text-muted); margin-bottom: 18px;">Ensure your account uses a secure password with at least 8 characters.</p>

                <?php if (!empty($passwordErrors)): ?>
                    <div class="alert alert-error" role="alert" style="margin-bottom: 18px;">
                        <ul style="margin: 0 0 0 16px;">
                            <?php foreach ($passwordErrors as $pe): ?>
                                <li><?= e($pe) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="<?= public_url('profile.php') ?>" method="POST" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label class="form-label" for="prof_current_password">Current Password *</label>
                        <input type="password" id="prof_current_password" name="current_password" class="form-control" placeholder="••••••••" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prof_new_password">New Password (Min 8 chars) *</label>
                        <input type="password" id="prof_new_password" name="new_password" class="form-control" minlength="8" placeholder="••••••••" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prof_confirm_password">Confirm New Password *</label>
                        <input type="password" id="prof_confirm_password" name="confirm_password" class="form-control" minlength="8" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="dd-btn dd-btn--forest" style="width: 100%; justify-content: center;">
                        Update Account Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
