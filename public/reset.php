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
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }

        if (empty($errors) && $pdo) {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $updateStmt->execute([$hash, $authorizedUserId]);

                // Security requirement: Destroy reset authorization flag immediately after use
                unset($_SESSION['reset_authorized_user_id'], $_SESSION['reset_token']);

                set_flash('success', 'Your password has been successfully reset! Please sign in with your new credentials.');
                redirect(public_url('login.php'));
            } catch (PDOException $e) {
                error_log("Password reset failed: " . $e->getMessage());
                $errors[] = 'A database error occurred while updating your password. Please try again.';
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
                    <label class="form-label" for="reset_password">New Password (Min 8 chars) *</label>
                    <input type="password" id="reset_password" name="password" class="form-control" minlength="8" placeholder="••••••••" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reset_confirm_password">Confirm New Password *</label>
                    <input type="password" id="reset_confirm_password" name="confirm_password" class="form-control" minlength="8" placeholder="••••••••" required>
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
