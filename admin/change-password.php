<?php
/**
 * Kalika Enterprises - Admin Change Password
 */

declare(strict_types=1);

$adminPage = 'security';
$adminTitle = 'Change Admin Password';

require_once __DIR__ . '/includes/header.php';

$errors = [];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Security token invalid or expired. Please refresh the page.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword)) {
            $errors[] = 'Please enter your current administrator password.';
        }

        if (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New password confirmation does not match.';
        }

        if (empty($errors) && $pdo) {
            try {
                $stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE id = ? LIMIT 1");
                $stmt->execute([$admin['id']]);
                $hash = $stmt->fetchColumn();

                if (!$hash || !password_verify($currentPassword, $hash)) {
                    $errors[] = 'Current password is incorrect.';
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $upStmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
                    $upStmt->execute([$newHash, $admin['id']]);

                    set_flash('success', 'Admin password changed successfully.');
                    redirect(admin_url('index.php'));
                }
            } catch (PDOException $e) {
                error_log("Admin password update failed: " . $e->getMessage());
                $errors[] = 'Database error while updating password.';
            }
        }
    }
}
?>

<div style="max-width: 520px; margin: 1rem auto;">
    <div style="margin-bottom: 2rem;">
        <h1 style="font-size: 1.6rem; margin-bottom: 0.35rem;">Staff Access &amp; Security</h1>
        <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0;">
            Update credentials for admin account <strong><?= e($admin['username']) ?></strong>.
        </p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert" style="margin-bottom: 1.5rem;">
            <ul style="margin: 0; padding-left: 1.25rem;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card" style="padding: 2rem;">
        <form action="<?= admin_url('change-password.php') ?>" method="POST" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="curr_pass">Current Password *</label>
                <input type="password" id="curr_pass" name="current_password" class="form-control" required autofocus placeholder="••••••••">
            </div>

            <div class="form-group">
                <label class="form-label" for="new_pass">New Strong Password * (min 8 chars)</label>
                <input type="password" id="new_pass" name="new_password" class="form-control" required placeholder="••••••••">
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm_pass">Confirm New Password *</label>
                <input type="password" id="confirm_pass" name="confirm_password" class="form-control" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                Update Administrator Password &rarr;
            </button>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
