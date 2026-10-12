<?php
/**
 * Kalika Enterprises - Reset Account Password
 * Phase 5: Gated password update (accessible only with verified single-use session flag)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_guest();

// Security gate: Must have completed Step 2 in forgot.php
$authorizedUserId = $_SESSION['reset_authorized_user_id'] ?? null;
if (!$authorizedUserId && !empty($_COOKIE['kalika_reset'])) {
    $verifiedId = auth_verify_reset($_COOKIE['kalika_reset']);
    if ($verifiedId) {
        $authorizedUserId = $verifiedId;
        $_SESSION['reset_authorized_user_id'] = $authorizedUserId;
    }
}

if (!$authorizedUserId) {
    set_flash('error', 'Unauthorized access. Please request and verify an OTP first.');
    redirect(public_url('forgot.php'));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Security token invalid or expired. Please refresh the page.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one letter and one number.';
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }

        if (empty($errors)) {
            if ($pdo) {
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $updateStmt->execute([$hash, $authorizedUserId]);

                    // Invalidate all pending OTPs for this user
                    $pdo->prepare("UPDATE password_otps SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")->execute([$authorizedUserId]);
                } catch (PDOException $e) {
                    error_log("Password reset failed: " . $e->getMessage());
                    $errors[] = 'A database error occurred while updating your password. Please try again.';
                }
            }

            if (empty($errors)) {
                // Security requirement: Destroy reset authorization flags immediately after consumption
                unset(
                    $_SESSION['reset_authorized_user_id'],
                    $_SESSION['reset_authorized_token'],
                    $_SESSION['reset_authorized_expires'],
                    $_SESSION['reset_token']
                );
                if (!headers_sent()) {
                    setcookie('kalika_reset', '', ['expires' => time() - 3600, 'path' => '/']);
                }

                set_flash('success', 'Your password has been successfully reset! Please sign in with your new credentials.');
                redirect(public_url('login.php'));
            }
        }
    }
}

$pageTitle = 'Set New Password | Kalika Enterprises';
$currentPage = 'account';
$pageDescription = 'Set a new secure password for your Kalika Enterprises wholesale retail account.';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
    <!-- Form Side -->
    <div class="auth-form-side">
        <div class="auth-form-card">
            <div class="auth-form-card__eyebrow">Identity Verified</div>
            <h1 class="auth-form-card__title">Set New Password</h1>
            <p class="auth-form-card__sub">Create a new strong password for your retail store account.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" role="alert" style="margin-bottom: 20px;">
                    <ul style="margin: 0 0 0 16px;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="<?= public_url('reset.php') ?>" method="POST" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="reset_password">New Password (Min 8 chars, letter + number) *</label>
                    <div style="position: relative;">
                        <input type="password" id="reset_password" name="password" class="form-control" minlength="8" placeholder="••••••••" required autofocus style="padding-right: 36px;">
                        <button type="button" class="btn-toggle-password" data-target="reset_password" aria-label="Toggle password visibility" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 4px; display: inline-flex; align-items: center;">
                            <svg class="eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg class="eye-closed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reset_confirm_password">Confirm New Password *</label>
                    <div style="position: relative;">
                        <input type="password" id="reset_confirm_password" name="confirm_password" class="form-control" minlength="8" placeholder="••••••••" required style="padding-right: 36px;">
                        <button type="button" class="btn-toggle-password" data-target="reset_confirm_password" aria-label="Toggle password visibility" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 4px; display: inline-flex; align-items: center;">
                            <svg class="eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg class="eye-closed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="dd-btn dd-btn--mint" style="width: 100%; justify-content: center; margin-top: 8px;">
                    Update Password &amp; Sign In
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
