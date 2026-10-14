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
            'name' => $currentSessionUser['name'] ?? 'Retailer',
            'mobile' => $currentSessionUser['mobile'] ?? '',
            'email' => $currentSessionUser['email'] ?? '',
            'address' => $currentSessionUser['address'] ?? '',
            'password_hash' => '',
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

            if (!empty($emailVal)) {
                if (!filter_var($emailVal, FILTER_VALIDATE_EMAIL) || mb_strlen($emailVal) > 150) {
                    $errors[] = 'Please enter a valid business email address.';
                } elseif ($pdo) {
                    try {
                        $emailCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
                        $emailCheck->execute([$emailVal, $userId]);
                        if ($emailCheck->fetch()) {
                            $errors[] = 'This email address is already in use by another retailer account.';
                        }
                    } catch (PDOException $e) {
                        error_log("Email uniqueness check failed: " . $e->getMessage());
                    }
                }
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
                            $emailVal,
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
            } elseif (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
                $passwordErrors[] = 'New password must contain at least one letter and one number.';
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
    <div style="max-width: 1020px; margin: 0 auto;">
        
        <!-- Profile Banner -->
        <div class="profile-banner">
            <div>
                <div class="info-hero__eyebrow" style="color: var(--color-mint); margin-bottom: 6px;">Verified Retail Partner</div>
                <h1 class="profile-banner__name"><?= e($userRecord['name']) ?></h1>
                <div class="profile-banner__meta">
                    Registered Mobile: <strong>+91 <?= e($userRecord['mobile']) ?></strong> &bull; Member since <?= date('M Y', strtotime($userRecord['created_at'])) ?>
                </div>
            </div>
            <div class="profile-banner-actions">
                <a href="<?= public_url('orders.php') ?>" class="dd-btn dd-btn--ghost">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                    <span>Past Orders</span>
                </a>
                <a href="<?= public_url('logout.php') ?>" class="dd-btn" style="background: rgba(239,68,68,0.2); color: #FCA5A5; border: 1px solid rgba(239,68,68,0.4);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    <span>Sign Out</span>
                </a>
            </div>
        </div>

        <div class="profile-grid">
            <!-- Profile Info Form Card -->
            <div class="profile-card profile-form">
                <div class="profile-card__header">
                    <div class="profile-card__badge profile-card__badge--store">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        <span>Store Profile</span>
                    </div>
                    <h2 class="profile-card__title">Store &amp; Delivery Details</h2>
                    <p class="profile-card__desc">Update your registered business name, delivery address, and dispatch contact details.</p>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error" role="alert" style="margin-bottom: 20px;">
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
                        <input type="text" id="prof_name" name="name" class="form-control" value="<?= e($userRecord['name']) ?>" required placeholder="e.g. Lokesh Stores">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prof_mobile">
                            <span>Registered Mobile Identifier</span>
                            <span style="font-size: 11px; color: var(--color-evergreen); font-weight: 700; text-transform: none; display: inline-flex; align-items: center; gap: 4px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg> Verified
                            </span>
                        </label>
                        <div class="input-icon-wrap">
                            <input type="tel" id="prof_mobile" class="form-control" value="<?= e($userRecord['mobile']) ?>" readonly>
                            <span style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: #94A3B8; display: inline-flex; pointer-events: none;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            </span>
                        </div>
                        <div class="field-hint">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                            <span>Mobile is your permanent login identifier and cannot be edited.</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prof_email">Email Address (Optional)</label>
                        <input type="email" id="prof_email" name="email" class="form-control" value="<?= e($userRecord['email'] ?? '') ?>" placeholder="store@example.com">
                        <div class="field-hint">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                            <span>Used for digital wholesale receipts and route dispatch notices.</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prof_address">Default Storefront Delivery Address *</label>
                        <textarea id="prof_address" name="address" class="form-control" rows="4" required placeholder="Shop No, Building, Landmark, Road, Town/Taluka, PIN Code"><?= e($userRecord['address']) ?></textarea>
                        <div class="field-hint">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            <span>Scheduled distributor route logistics dispatch for Niphad, Dindori, Chandwad, Sinnar, and Yeola.</span>
                        </div>
                    </div>

                    <div class="profile-form-footer">
                        <button type="submit" class="btn-profile-submit btn-profile-submit--save">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                            <span>Save Profile Changes</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Password Change Card -->
            <div class="profile-card profile-form">
                <div class="profile-card__header">
                    <div class="profile-card__badge profile-card__badge--security">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        <span>Account Security</span>
                    </div>
                    <h2 class="profile-card__title">Change Password</h2>
                    <p class="profile-card__desc">Ensure your account uses a secure password with at least 8 characters.</p>
                </div>

                <?php if (!empty($passwordErrors)): ?>
                    <div class="alert alert-error" role="alert" style="margin-bottom: 20px;">
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
                        <div class="input-icon-wrap">
                            <input type="password" id="prof_current_password" name="current_password" class="form-control" placeholder="••••••••" required>
                            <button type="button" class="btn-toggle-password" data-target="prof_current_password" aria-label="Toggle password visibility">
                                <svg class="eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <svg class="eye-closed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prof_new_password">New Password (Min 8 chars, letter + number) *</label>
                        <div class="input-icon-wrap">
                            <input type="password" id="prof_new_password" name="new_password" class="form-control" minlength="8" placeholder="••••••••" required>
                            <button type="button" class="btn-toggle-password" data-target="prof_new_password" aria-label="Toggle password visibility">
                                <svg class="eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <svg class="eye-closed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prof_confirm_password">Confirm New Password *</label>
                        <div class="input-icon-wrap">
                            <input type="password" id="prof_confirm_password" name="confirm_password" class="form-control" minlength="8" placeholder="••••••••" required>
                            <button type="button" class="btn-toggle-password" data-target="prof_confirm_password" aria-label="Toggle password visibility">
                                <svg class="eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <svg class="eye-closed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </div>
                    </div>

                    <div class="profile-form-footer">
                        <button type="submit" class="btn-profile-submit btn-profile-submit--password">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            <span>Update Account Password</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
