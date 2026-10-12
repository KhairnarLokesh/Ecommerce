<?php
/**
 * Kalika Enterprises - Forgot Password with Free Demo OTP
 * Phase 5: 6-digit expiring OTP, SHA-256 hashed storage, single-use enforcement, demo display
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_guest();

$error = '';
$step = $_GET['step'] ?? 'request';

// Step 1: User submits mobile number to request OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_otp') {
    if (!csrf_verify()) {
        $error = 'Security token invalid or expired. Please refresh the page.';
    } else {
        $mobile = trim($_POST['mobile'] ?? '');

        if (empty($mobile) || !preg_match('/^[6-9]\d{9}$/', $mobile)) {
            $error = 'Please enter a valid 10-digit Indian mobile number.';
        } elseif ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT id, name, is_active FROM users WHERE mobile = ? LIMIT 1");
                $stmt->execute([$mobile]);
                $user = $stmt->fetch();

                if ($user && (int)$user['is_active'] === 1) {
                    $userId = (int)$user['id'];

                    // Invalidate any older unused OTPs for this user
                    $invalidateStmt = $pdo->prepare("
                        UPDATE password_otps 
                        SET used_at = NOW() 
                        WHERE user_id = ? AND used_at IS NULL
                    ");
                    $invalidateStmt->execute([$userId]);

                    // Generate cryptographically secure 6-digit OTP
                    $rawOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    $otpHash = hash('sha256', $rawOtp);

                    // Insert hashed OTP valid for 5 minutes
                    $insertStmt = $pdo->prepare("
                        INSERT INTO password_otps (user_id, otp_hash, expires_at, attempts)
                        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), 0)
                    ");
                    $insertStmt->execute([$userId, $otpHash]);

                    // Store in session for Free Demo mode display
                    $_SESSION['demo_otp_display'] = $rawOtp;
                    $_SESSION['demo_otp_mobile']  = $mobile;
                    $_SESSION['demo_otp_user_id'] = $userId;
                    $_SESSION['demo_otp_expires'] = time() + 300; // 5 mins

                    redirect(public_url('forgot.php?step=verify'));
                } else {
                    // NOTE: Generic message prevents account enumeration. In demo mode we guide clearly.
                    $error = 'No active retailer account found with mobile number +91 ' . htmlspecialchars($mobile) . '.';
                }
            } catch (PDOException $e) {
                error_log("OTP generation failed: " . $e->getMessage());
                $error = 'A database error occurred. Please try again.';
            }
        }
    }
}

// Step 2: User submits 6-digit OTP for verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    if (!csrf_verify()) {
        $error = 'Security token invalid or expired. Please refresh the page.';
    } else {
        $enteredOtp = trim($_POST['otp'] ?? '');
        $userId = $_SESSION['demo_otp_user_id'] ?? null;

        if (empty($enteredOtp) || !preg_match('/^\d{6}$/', $enteredOtp)) {
            $error = 'Please enter a valid 6-digit verification code.';
        } elseif (!$userId || !$pdo) {
            $error = 'Your session has expired. Please enter your mobile number again.';
            $step = 'request';
        } else {
            try {
                // Fetch the latest active OTP record for this user
                $otpStmt = $pdo->prepare("
                    SELECT id, otp_hash, expires_at, used_at, attempts,
                           (NOW() > expires_at) as is_expired
                    FROM password_otps
                    WHERE user_id = ?
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $otpStmt->execute([$userId]);
                $otpRecord = $otpStmt->fetch();

                if (!$otpRecord) {
                    $error = 'No verification request found. Please request a new OTP.';
                    $step = 'request';
                } elseif (!empty($otpRecord['used_at'])) {
                    $error = 'This OTP has already been used. Please generate a new code.';
                    $step = 'request';
                } elseif ((int)$otpRecord['is_expired'] === 1) {
                    $error = 'This OTP has expired (5-minute limit exceeded). Please request a new code.';
                    $step = 'request';
                } elseif ((int)$otpRecord['attempts'] >= 5) {
                    // Invalidate due to excessive attempts
                    $pdo->prepare("UPDATE password_otps SET used_at = NOW() WHERE id = ?")->execute([$otpRecord['id']]);
                    $error = 'Too many failed attempts. This OTP has been invalidated for security. Please request a new code.';
                    $step = 'request';
                } else {
                    // Increment attempt count
                    $pdo->prepare("UPDATE password_otps SET attempts = attempts + 1 WHERE id = ?")->execute([$otpRecord['id']]);

                    // Verify SHA-256 hash
                    $enteredHash = hash('sha256', $enteredOtp);
                    if (hash_equals($otpRecord['otp_hash'], $enteredHash)) {
                        // SUCCESS! Mark OTP used immediately (Single-use enforcement)
                        $pdo->prepare("UPDATE password_otps SET used_at = NOW() WHERE id = ?")->execute([$otpRecord['id']]);

                        // Set reset authorization token in session
                        $_SESSION['reset_authorized_user_id'] = $userId;
                        $_SESSION['reset_token'] = bin2hex(random_bytes(16));

                        // Clear demo OTP display session
                        unset($_SESSION['demo_otp_display'], $_SESSION['demo_otp_mobile'], $_SESSION['demo_otp_user_id'], $_SESSION['demo_otp_expires']);

                        set_flash('success', 'Mobile identity verified! Please create your new account password.');
                        redirect(public_url('reset.php'));
                    } else {
                        $remaining = 4 - (int)$otpRecord['attempts'];
                        $error = 'Invalid verification code. Please check the 6-digit OTP. (' . max(0, $remaining) . ' attempts left)';
                    }
                }
            } catch (PDOException $e) {
                error_log("OTP verification failed: " . $e->getMessage());
                $error = 'Database error during verification. Please try again.';
            }
        }
    }
}

$pageTitle = 'Forgot Password | Kalika Enterprises';
$currentPage = 'account';
$pageDescription = 'Recover your Kalika Enterprises wholesale retail account using Free Demo OTP verification.';

require_once __DIR__ . '/../includes/header.php';

$demoOtpCode = $_SESSION['demo_otp_display'] ?? null;
$demoMobile  = $_SESSION['demo_otp_mobile'] ?? null;
?>

<div class="auth-wrap">
    <!-- Form Side -->
    <div class="auth-form-side">
        <div class="auth-form-card">
            <?php if ($step === 'verify' && !empty($demoOtpCode)): ?>
                <!-- STEP 2: VERIFY OTP -->
                <div class="auth-form-card__eyebrow">Security Verification &bull; Step 2 of 2</div>
                <h1 class="auth-form-card__title">Verify Mobile Code</h1>
                <p class="auth-form-card__sub">Verification requested for <strong>+91 <?= e($demoMobile) ?></strong></p>

                <!-- FREE DEMO OTP NOTIFICATION DISPLAY BOX -->
                <div class="otp-demo-box">
                    <div class="otp-demo-box__label">Demo Verification Code</div>
                    <div class="otp-demo-box__code"><?= e($demoOtpCode) ?></div>
                    <div class="otp-demo-box__note">
                        Valid for 5 minutes &bull; Single-use verification.<br>
                        <em>(No paid SMS gateway configured. Displayed on-screen for demonstration).</em>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error" role="alert" style="margin-bottom: 20px;">
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <form action="<?= public_url('forgot.php?step=verify') ?>" method="POST" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="verify_otp">

                    <div class="form-group">
                        <label class="form-label" for="verify_otp">Enter 6-Digit OTP *</label>
                        <input type="text" id="verify_otp" name="otp" class="form-control otp-input" maxlength="6" pattern="[0-9]{6}" placeholder="123456" required autofocus>
                    </div>

                    <button type="submit" class="dd-btn dd-btn--mint" style="width: 100%; justify-content: center; margin-top: 8px;">
                        Verify Code &amp; Continue
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>
                </form>

                <div class="auth-form-card__footer" style="display: flex; justify-content: space-between; align-items: center;">
                    <a href="<?= public_url('forgot.php?step=request') ?>">&larr; Change Mobile</a>
                    <a href="<?= public_url('login.php') ?>">Back to Login</a>
                </div>

            <?php else: ?>
                <!-- STEP 1: REQUEST OTP -->
                <div class="auth-form-card__eyebrow">Password Recovery &bull; Step 1 of 2</div>
                <h1 class="auth-form-card__title">Reset Password</h1>
                <p class="auth-form-card__sub">Enter your registered mobile number to receive a 6-digit verification code.</p>

                <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: var(--radius-sm); padding: 12px 14px; margin-bottom: 20px; font-size: 13px; color: var(--color-evergreen); line-height: 1.4;">
                    <strong>Free Demo Mode:</strong> The generated 6-digit OTP will be presented directly on your screen (no paid SMS gateway needed).
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error" role="alert" style="margin-bottom: 20px;">
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <form action="<?= public_url('forgot.php?step=request') ?>" method="POST" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="request_otp">

                    <div class="form-group">
                        <label class="form-label" for="req_mobile">Registered 10-Digit Mobile Number *</label>
                        <div class="input-prefix">
                            <span class="input-prefix-addon">+91</span>
                            <input type="tel" id="req_mobile" name="mobile" class="form-control" pattern="[6-9][0-9]{9}" placeholder="9876543210" required autofocus>
                        </div>
                    </div>

                    <button type="submit" class="dd-btn dd-btn--mint" style="width: 100%; justify-content: center; margin-top: 8px;">
                        Generate Demo OTP
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>
                </form>

                <div class="auth-form-card__footer">
                    Remembered password? <a href="<?= public_url('login.php') ?>">Back to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
