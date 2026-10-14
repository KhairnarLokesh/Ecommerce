<?php
/**
 * Kalika Enterprises - Admin Change Password Portal
 * Handles password updates, enforced on first login (must_change_password = 1)
 * and available on-demand via the admin menu.
 */

declare(strict_types=1);

$adminPage = 'security';
$adminTitle = 'Change Admin Password';

require_once __DIR__ . '/includes/header.php';

$errors = [];
$admin = current_admin();
$mustChange = !empty($admin['must_change_password']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Security token invalid or expired. Please refresh the page.';
    } else {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (empty($currentPassword)) {
            $errors[] = 'Please enter your current administrator password.';
        }

        // Validate password strength according to policy (min 12 chars, upper, lower, digit, symbol)
        $strengthErrors = validate_admin_password_strength($newPassword);
        foreach ($strengthErrors as $serr) {
            $errors[] = $serr;
        }

        if ($newPassword === $currentPassword && !empty($currentPassword)) {
            $errors[] = 'New password cannot be the same as your current password. Please choose a different passphrase.';
        }

        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New password confirmation does not match.';
        }

        if (empty($errors) && $pdo && !empty($admin['id'])) {
            try {
                $stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE id = ? LIMIT 1");
                $stmt->execute([$admin['id']]);
                $hash = $stmt->fetchColumn();

                if (!$hash || !password_verify($currentPassword, (string)$hash)) {
                    $errors[] = 'Current administrator password is incorrect.';
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                    
                    // Update password and clear must_change_password flag & any lockout
                    $upStmt = $pdo->prepare("
                        UPDATE admins 
                        SET password_hash = ?, must_change_password = 0, failed_attempts = 0, locked_until = NULL 
                        WHERE id = ?
                    ");
                    $upStmt->execute([$newHash, $admin['id']]);

                    // Update session state
                    $_SESSION['admin_must_change_password'] = 0;
                    if (isset($_SESSION['admin']) && is_array($_SESSION['admin'])) {
                        $_SESSION['admin']['must_change_password'] = 0;
                    }

                    // Record in audit log
                    admin_audit_log(
                        'password_change',
                        'admin',
                        (string)$admin['id'],
                        $mustChange ? 'first_login_provisional' : 'routine_password_update',
                        'new_password_hash_applied',
                        (int)$admin['id']
                    );

                    set_flash('success', 'Admin password changed successfully. Full administrative privileges unlocked.');
                    redirect(admin_url('index.php'));
                }
            } catch (PDOException $e) {
                error_log("Admin password update failed: " . $e->getMessage());
                $errors[] = 'Database error while updating password. Please try again.';
            }
        }
    }
}
?>

<div style="max-width: 580px; margin: 1.5rem auto;">
    <div style="margin-bottom: 2rem;">
        <div class="eyebrow" style="font-size: 11px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--color-evergreen); margin-bottom: 6px;">
            Security &bull; Access Credentials
        </div>
        <h1 style="font-family: var(--font-editorial); font-size: 24px; font-weight: 700; margin-bottom: 6px; color: var(--color-text-main);">
            <?= $mustChange ? 'First Login: Create Secure Password' : 'Change Administrator Password' ?>
        </h1>
        <p style="font-size: 14px; color: var(--color-text-muted); margin: 0;">
            Managing security credentials for administrator account <strong><?= e($admin['username'] ?? 'admin') ?></strong>.
        </p>
    </div>

    <?php if ($mustChange): ?>
        <div class="alert alert-warning" role="alert" style="margin-bottom: 1.75rem; border-left: 4px solid #f59e0b; background: #fffbeb; padding: 1rem 1.25rem; border-radius: 8px;">
            <strong style="display: block; font-size: 0.95rem; margin-bottom: 0.25rem; color: #92400e;">
                ⚠️ Action Required: Change Default / Initial Password
            </strong>
            <p style="margin: 0; font-size: 0.85rem; color: #b45309; line-height: 1.45;">
                For security reasons, your account is configured to require a new, personal password on first login. Other admin management features will remain locked until this step is completed.
            </p>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert" style="margin-bottom: 1.5rem;">
            <ul style="margin: 0; padding-left: 1.25rem;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card" style="padding: 2rem; background: #ffffff; border-radius: 12px; box-shadow: var(--shadow-soft);">
        <form action="<?= admin_url('change-password.php') ?>" method="POST" novalidate id="changePassForm">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="curr_pass">Current Password *</label>
                <input type="password" id="curr_pass" name="current_password" class="form-control" required autofocus placeholder="Enter current temporary or active password">
            </div>

            <div class="form-group">
                <label class="form-label" for="new_pass">New Strong Password * (min 12 chars)</label>
                <input type="password" id="new_pass" name="new_password" class="form-control" required placeholder="Enter new strong passphrase">
                
                <!-- Live Password Strength Indicator -->
                <div style="margin-top: 0.75rem; background: #f8fafc; border: 1px solid var(--color-border-light); border-radius: 8px; padding: 0.85rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; font-size: 0.75rem;">
                        <span style="font-weight: 600; color: #475569;">Password Strength:</span>
                        <span id="strengthText" style="font-weight: 700; color: #64748b;">Waiting...</span>
                    </div>
                    <div style="height: 6px; width: 100%; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin-bottom: 0.75rem;">
                        <div id="strengthBar" style="height: 100%; width: 0%; background: #ef4444; transition: all 0.3s ease;"></div>
                    </div>
                    <div style="font-size: 0.75rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem; color: #64748b;" id="criteriaList">
                        <span id="c_len">&bull; At least 12 characters</span>
                        <span id="c_upper">&bull; One uppercase letter</span>
                        <span id="c_lower">&bull; One lowercase letter</span>
                        <span id="c_num">&bull; One number (0-9)</span>
                        <span id="c_sym">&bull; One special symbol</span>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.75rem;">
                <label class="form-label" for="confirm_pass">Confirm New Password *</label>
                <input type="password" id="confirm_pass" name="confirm_password" class="form-control" required placeholder="Re-type new passphrase">
                <small id="matchNotice" style="font-size: 0.75rem; color: #64748b; display: block; margin-top: 0.35rem;"></small>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 12px; font-weight: 700;">
                <span>Update Administrator Password</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const newPass = document.getElementById('new_pass');
    const confirmPass = document.getElementById('confirm_pass');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const matchNotice = document.getElementById('matchNotice');

    const cLen = document.getElementById('c_len');
    const cUpper = document.getElementById('c_upper');
    const cLower = document.getElementById('c_lower');
    const cNum = document.getElementById('c_num');
    const cSym = document.getElementById('c_sym');

    function checkCriteria(el, met) {
        if (met) {
            el.style.color = '#10b981';
            el.style.fontWeight = '600';
            el.innerHTML = '&#10003; ' + el.innerText.replace(/^[\u2022\u2713]\s*/, '');
        } else {
            el.style.color = '#64748b';
            el.style.fontWeight = '400';
            el.innerHTML = '&bull; ' + el.innerText.replace(/^[\u2022\u2713]\s*/, '');
        }
    }

    if (newPass) {
        newPass.addEventListener('input', function() {
            const val = this.value;
            let score = 0;

            const isLen = val.length >= 12;
            const isUpper = /[A-Z]/.test(val);
            const isLower = /[a-z]/.test(val);
            const isNum = /[0-9]/.test(val);
            const isSym = /[^a-zA-Z0-9]/.test(val);

            checkCriteria(cLen, isLen);
            checkCriteria(cUpper, isUpper);
            checkCriteria(cLower, isLower);
            checkCriteria(cNum, isNum);
            checkCriteria(cSym, isSym);

            if (isLen) score++;
            if (isUpper) score++;
            if (isLower) score++;
            if (isNum) score++;
            if (isSym) score++;

            if (val.length === 0) {
                strengthBar.style.width = '0%';
                strengthBar.style.background = '#e2e8f0';
                strengthText.innerText = 'Waiting...';
                strengthText.style.color = '#64748b';
            } else if (score <= 2) {
                strengthBar.style.width = '25%';
                strengthBar.style.background = '#ef4444';
                strengthText.innerText = 'Weak';
                strengthText.style.color = '#ef4444';
            } else if (score === 3 || score === 4) {
                strengthBar.style.width = '65%';
                strengthBar.style.background = '#f59e0b';
                strengthText.innerText = 'Moderate (Complete all items)';
                strengthText.style.color = '#d97706';
            } else if (score === 5) {
                strengthBar.style.width = '100%';
                strengthBar.style.background = '#10b981';
                strengthText.innerText = 'Strong & Compliant';
                strengthText.style.color = '#059669';
            }

            checkMatch();
        });
    }

    function checkMatch() {
        if (!confirmPass.value) {
            matchNotice.innerText = '';
            return;
        }
        if (confirmPass.value === newPass.value) {
            matchNotice.innerText = '✓ Passwords match';
            matchNotice.style.color = '#10b981';
        } else {
            matchNotice.innerText = '✗ Passwords do not match';
            matchNotice.style.color = '#ef4444';
        }
    }

    if (confirmPass) {
        confirmPass.addEventListener('input', checkMatch);
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
