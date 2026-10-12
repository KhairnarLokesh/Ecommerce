<?php
/**
 * Kalika Enterprises - Customer Login
 * Phase 5: Rate-limited authentication using login_attempts, bcrypt verification, and session regeneration
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cart.php';

require_guest();

$error = '';
$mobileVal = '';
$returnUrl = trim($_GET['return'] ?? $_POST['return'] ?? '');

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Security token invalid or expired. Please refresh the page.';
    } else {
        $mobileVal = trim($_POST['mobile'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($mobileVal) || empty($password)) {
            $error = 'Please enter both your mobile number and password.';
        } elseif ($pdo) {
            try {
                // Rate Limiting Check: 5 attempts per 15 minutes by mobile or IP
                $throttleStmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM login_attempts 
                    WHERE (identifier = ? OR ip = ?)
                      AND attempted_at > (NOW() - INTERVAL 15 MINUTE)
                ");
                $throttleStmt->execute([$mobileVal, $clientIp]);
                $recentAttempts = (int)$throttleStmt->fetchColumn();

                if ($recentAttempts >= 5) {
                    $error = 'Too many failed login attempts. For security, please wait 15 minutes before trying again.';
                } else {
                    // Check user record
                    $userStmt = $pdo->prepare("
                        SELECT id, name, mobile, email, password_hash, address, is_active
                        FROM users
                        WHERE mobile = ?
                        LIMIT 1
                    ");
                    $userStmt->execute([$mobileVal]);
                    $user = $userStmt->fetch();

                    if ($user && (int)$user['is_active'] === 1 && password_verify($password, $user['password_hash'])) {
                        // Successful login! Clear failed attempts for this identifier & IP
                        $clearStmt = $pdo->prepare("
                            DELETE FROM login_attempts 
                            WHERE identifier = ? OR ip = ?
                        ");
                        $clearStmt->execute([$mobileVal, $clientIp]);

                        // Regenerate session and set user authentication state
                        login_user([
                            'id'      => (int)$user['id'],
                            'name'    => $user['name'],
                            'mobile'  => $user['mobile'],
                            'email'   => $user['email'] ?? '',
                            'address' => $user['address'] ?? ''
                        ]);

                        // Merge guest cart items into database
                        cart_merge_on_login($pdo, (int)$user['id']);

                        set_flash('success', 'Signed in successfully. Welcome back, ' . e($user['name']) . '!');

                        // Sanitize return redirect path
                        if (!empty($returnUrl) && preg_match('/^[a-z0-9_-]+\.php(\?[a-z0-9_=&-]+)?$/i', $returnUrl)) {
                            redirect(public_url($returnUrl));
                        } else {
                            redirect(public_url('profile.php'));
                        }
                    } else {
                        // Failed login attempt: record in login_attempts
                        $logStmt = $pdo->prepare("
                            INSERT INTO login_attempts (identifier, ip)
                            VALUES (?, ?)
                        ");
                        $logStmt->execute([$mobileVal, $clientIp]);

                        $remainingAttempts = 4 - $recentAttempts;
                        if ($remainingAttempts > 0) {
                            $error = 'Invalid mobile number or password. (' . $remainingAttempts . ' attempts remaining)';
                        } else {
                            $error = 'Invalid mobile number or password. Account access is temporarily throttled.';
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log("Login query failed: " . $e->getMessage());
                $error = 'A database error occurred. Please try again.';
            }
        }
    }
}

$pageTitle = 'Customer Login | Kalika Enterprises';
$currentPage = 'account';
$pageDescription = 'Sign in to your Kalika Enterprises wholesale retail account to track orders and place new store requests.';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-form-side">
        <div class="auth-form-card">
            <div class="auth-form-card__eyebrow">Retailer Portal</div>
            <h1 class="auth-form-card__title">Sign In</h1>
            <p class="auth-form-card__sub">Access your store catalogue, invoices, and active orders.</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error" role="alert" style="margin-bottom: 20px;">
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="<?= public_url('login.php' . (!empty($returnUrl) ? '?return=' . urlencode($returnUrl) : '')) ?>" method="POST" novalidate>
                <?= csrf_field() ?>
                <?php if (!empty($returnUrl)): ?>
                    <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label" for="login_mobile">10-Digit Mobile Number *</label>
                    <div class="input-prefix">
                        <span class="input-prefix-addon">+91</span>
                        <input type="tel" id="login_mobile" name="mobile" class="form-control" pattern="[6-9][0-9]{9}" placeholder="9876543210" value="<?= e($mobileVal) ?>" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <div class="auth-label-row">
                        <label class="form-label" for="login_password">Password *</label>
                        <a href="<?= public_url('forgot.php') ?>" class="auth-forgot-link">Forgot Password?</a>
                    </div>
                    <input type="password" id="login_password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" 
                        class="specular-button specular-button--lg specular-button--mint" 
                        data-radius="8" 
                        data-line-color="#00E599" 
                        data-base-color="#0F382A" 
                        data-intensity="1.2" 
                        data-idle-intensity="0.45" 
                        data-thickness="1.5" 
                        data-glow="1.3" 
                        style="width: 100%; margin-top: 10px;">
                    <span class="specular-button__label">
                        Sign In to Storefront
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </span>
                </button>
            </form>

            <div class="auth-form-card__footer">
                New store retailer? <a href="<?= public_url('register.php' . (!empty($returnUrl) ? '?return=' . urlencode($returnUrl) : '')) ?>">Register new account</a>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
