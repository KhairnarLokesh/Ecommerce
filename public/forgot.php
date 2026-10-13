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
require_once __DIR__ . '/../includes/mailer.php';

require_guest();

$error = '';
$step = $_GET['step'] ?? 'request';

// Step 1: User submits email or mobile number to request OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_otp') {
    if (!csrf_verify()) {
        $error = 'Security token invalid or expired. Please refresh the page.';
    } else {
        $identifier = trim($_POST['identifier'] ?? $_POST['email'] ?? $_POST['mobile'] ?? '');
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        $isMobile = preg_match('/^[6-9]\d{9}$/', $identifier);

        if (empty($identifier) || (!$isEmail && !$isMobile)) {
            $error = 'Please enter a valid registered business email address or 10-digit mobile number.';
        } elseif ($pdo) {
            try {
                $stmt = $pdo->prepare("
                    SELECT id, name, email, mobile, is_active 
                    FROM users 
                    WHERE (email = ? OR mobile = ?) 
                    LIMIT 1
                ");
                $stmt->execute([$identifier, $identifier]);
                $user = $stmt->fetch();

                if ($user && (int)$user['is_active'] === 1) {
                    $userId = (int)$user['id'];

                    // Rate Limiting Cooldown: prevent spamming new OTPs within 60 seconds
                    $cooldownStmt = $pdo->prepare("
                        SELECT id, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS elapsed
                        FROM password_otps
                        WHERE user_id = ? AND created_at > (NOW() - INTERVAL 60 SECOND)
                        ORDER BY id DESC LIMIT 1
                    ");
                    $cooldownStmt->execute([$userId]);
                    $recentOtp = $cooldownStmt->fetch();

                    if ($recentOtp) {
                        $waitSec = 60 - max(0, (int)$recentOtp['elapsed']);
                        $error = 'Please wait ' . max(1, $waitSec) . ' seconds before requesting another verification code.';
                    } else {
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

                        // Attempt Real Email Delivery via SMTP if configured
                        $emailSent = false;
                        $userEmail = !empty($user['email']) ? trim($user['email']) : '';
                        $userName = !empty($user['name']) ? trim($user['name']) : 'Retailer Partner';

                        if (!empty($userEmail) && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                            if (is_smtp_configured()) {
                                $mailResult = send_otp_email($userEmail, $userName, $rawOtp);
                                $emailSent = $mailResult['success'];
                                if (!$emailSent) {
                                    error_log("Failed to send OTP email: " . $mailResult['message']);
                                }
                            }
                        }

                        // Demo Mode vs Production Rule:
                        $isDemoEnabled = defined('DEMO_OTP_ENABLED') && DEMO_OTP_ENABLED;

                        $_SESSION['otp_delivery_channel'] = $emailSent ? 'email' : 'demo';
                        $_SESSION['otp_masked_destination'] = !empty($userEmail) ? mask_email($userEmail) : '';
                        // Display demo OTP on-screen ONLY in demo mode when email delivery is not available or failed
                        $_SESSION['demo_otp_display'] = (!$emailSent && $isDemoEnabled) ? $rawOtp : null;
                        $_SESSION['demo_otp_identifier'] = $identifier;
                        $_SESSION['demo_otp_user_id'] = $userId;
                        $_SESSION['demo_otp_expires'] = time() + 300; // 5 mins

                        redirect(public_url('forgot.php?step=verify'));
                    }
                } else {
                    // Production security requirement: Generic response to prevent account enumeration
                    $isDemoEnabled = defined('DEMO_OTP_ENABLED') && DEMO_OTP_ENABLED;
                    if ($isDemoEnabled) {
                        $error = 'No active retailer account found with: ' . htmlspecialchars($identifier) . '.';
                    } else {
                        // In production, pretend request was accepted to prevent account enumeration
                        $_SESSION['demo_otp_display'] = null;
                        $_SESSION['demo_otp_identifier'] = $identifier;
                        $_SESSION['demo_otp_user_id'] = 0;
                        $_SESSION['demo_otp_expires'] = time() + 300;
                        redirect(public_url('forgot.php?step=verify'));
                    }
                }
            } catch (PDOException $e) {
                error_log("OTP generation failed: " . $e->getMessage());
                $error = 'A database error occurred. Please try again.';
            }
        } else {
            $error = 'Database service unavailable. Please ensure database connectivity is established.';
        }
    }
}

// Step 2: User submits 6-digit OTP for verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    if (!csrf_verify()) {
        $error = 'Security token invalid or expired. Please refresh the page.';
    } else {
        $enteredOtp = trim($_POST['otp'] ?? '');
        $userId = (int)($_SESSION['demo_otp_user_id'] ?? 0);

        if (empty($enteredOtp) || !preg_match('/^\d{6}$/', $enteredOtp)) {
            $error = 'Please enter a valid 6-digit verification code.';
        } elseif ($userId <= 0) {
            $error = 'Your session has expired. Please enter your email or mobile number again.';
            $step = 'request';
        } elseif ($pdo) {
            try {
                // Fetch the latest active OTP record for this user inside transaction
                $pdo->beginTransaction();

                $otpStmt = $pdo->prepare("
                    SELECT id, otp_hash, expires_at, used_at, attempts,
                           (NOW() > expires_at) as is_expired
                    FROM password_otps
                    WHERE user_id = ? AND used_at IS NULL
                    ORDER BY id DESC
                    LIMIT 1
                    FOR UPDATE
                ");
                $otpStmt->execute([$userId]);
                $otpRecord = $otpStmt->fetch();

                if (!$otpRecord) {
                    $pdo->rollBack();
                    $error = 'No active verification request found. Please request a new OTP.';
                    $step = 'request';
                } elseif ((int)$otpRecord['is_expired'] === 1) {
                    $pdo->rollBack();
                    $error = 'This OTP has expired (5-minute limit exceeded). Please request a new code.';
                    $step = 'request';
                } elseif ((int)$otpRecord['attempts'] >= 5) {
                    // Invalidate due to excessive failed attempts
                    $pdo->prepare("UPDATE password_otps SET used_at = NOW() WHERE id = ?")->execute([$otpRecord['id']]);
                    $pdo->commit();
                    $error = 'Too many failed attempts. This OTP has been invalidated for security. Please request a new code.';
                    $step = 'request';
                } else {
                    // Verify SHA-256 hash
                    $enteredHash = hash('sha256', $enteredOtp);
                    if (hash_equals($otpRecord['otp_hash'], $enteredHash)) {
                        // SUCCESS! Invalidate OTP immediately (Single-use enforcement)
                        $pdo->prepare("UPDATE password_otps SET used_at = NOW() WHERE id = ?")->execute([$otpRecord['id']]);
                        $pdo->commit();

                        // Establish secure reset authorization session (15 minutes)
                        $resetToken = bin2hex(random_bytes(32));
                        $_SESSION['reset_authorized_user_id'] = $userId;
                        $_SESSION['reset_authorized_token']   = $resetToken;
                        $_SESSION['reset_authorized_expires'] = time() + 900;

                        // Set signed cookie for serverless lambda continuity
                        $resetCookie = auth_sign_reset($userId);
                        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
                        if (!headers_sent()) {
                            setcookie('kalika_reset', $resetCookie, [
                                'expires'  => time() + 900,
                                'path'     => '/',
                                'secure'   => $isHttps,
                                'httponly' => true,
                                'samesite' => 'Lax'
                            ]);
                        }

                        // Clear demo OTP display session
                        unset($_SESSION['demo_otp_display'], $_SESSION['demo_otp_identifier'], $_SESSION['demo_otp_user_id'], $_SESSION['demo_otp_expires']);

                        set_flash('success', 'Identity verified! Please create your new account password.');
                        redirect(public_url('reset.php'));
                    } else {
                        // Increment attempt counter
                        $pdo->prepare("UPDATE password_otps SET attempts = attempts + 1 WHERE id = ?")->execute([$otpRecord['id']]);
                        $pdo->commit();

                        $remaining = 4 - (int)$otpRecord['attempts'];
                        $error = 'Invalid verification code. (' . max(0, $remaining) . ' attempts left)';
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("OTP verification failed: " . $e->getMessage());
                $error = 'Database error during verification. Please try again.';
            }
        } else {
            $error = 'Database service unavailable. Please ensure database connectivity is established.';
        }
    }
}

$pageTitle = 'Forgot Password | Kalika Enterprises';
$currentPage = 'account';
$pageDescription = 'Recover your Kalika Enterprises wholesale retail account using Free Demo OTP verification.';

require_once __DIR__ . '/../includes/header.php';

$demoOtpCode = $_SESSION['demo_otp_display'] ?? null;
$demoTarget  = $_SESSION['demo_otp_identifier'] ?? '';
$deliveryChannel = $_SESSION['otp_delivery_channel'] ?? 'demo';
$maskedDest = $_SESSION['otp_masked_destination'] ?? '';
$isDemoMode  = defined('DEMO_OTP_ENABLED') && DEMO_OTP_ENABLED;

// Real email delivery: ensure demo code is kept private and not displayed on screen
if ($deliveryChannel === 'email') {
    $demoOtpCode = null;
    unset($_SESSION['demo_otp_display']);
}
?>

<div class="auth-wrap">
    <!-- Form Side -->
    <div class="auth-form-side">
        <div class="auth-form-card">
            <?php if ($step === 'verify'): ?>
                <!-- STEP 2: VERIFY OTP -->
                <div class="auth-security-badge">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    <span>Security Verification &bull; Step 2 of 2</span>
                </div>
                <h1 class="auth-form-card__title">Verify 6-Digit Code</h1>
                <p class="auth-form-card__sub" style="margin-bottom: 20px;">
                    Verification requested for 
                    <span class="auth-target-highlight"><?= e($maskedDest ?: ($demoTarget ?: 'your account')) ?></span>
                </p>

                <?php if ($deliveryChannel === 'email' && !empty($maskedDest)): ?>
                    <div class="auth-email-dispatched-card">
                        <div class="auth-email-icon-circle">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        </div>
                        <div class="auth-email-dispatched-text">
                            <strong>OTP Sent via Email!</strong>
                            <p>A 6-digit verification code has been dispatched to <strong><?= e($maskedDest) ?></strong>. Please check your inbox and spam folder.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($deliveryChannel !== 'email' && $isDemoMode && !empty($demoOtpCode)): ?>
                    <!-- FALLBACK DEMO OTP NOTIFICATION DISPLAY BOX (ONLY WHEN EMAIL NOT AVAILABLE) -->
                    <div class="otp-demo-box">
                        <div class="otp-demo-box__label">Demo Verification Code</div>
                        <div class="otp-demo-box__code"><?= e($demoOtpCode) ?></div>
                        <div class="otp-demo-box__note">
                            Valid for 5 minutes &bull; Single-use verification.<br>
                            <em>(Development / Demo Mode: Displayed on-screen because email delivery is offline or not configured).</em>
                        </div>
                    </div>
                <?php elseif ($deliveryChannel !== 'email'): ?>
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px 14px; margin-bottom: 20px; font-size: 13px; color: var(--color-text-main); line-height: 1.4;">
                        <strong>Code Dispatched:</strong> A 6-digit single-use OTP has been dispatched to your registered contact channel. Please enter it below.
                    </div>
                <?php endif; ?>

                <!-- Expiration Countdown Strip -->
                <div class="otp-expiration-strip">
                    <div class="otp-timer-info">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <span>Security Expiration: <strong id="otpCountdown">05:00</strong></span>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error" role="alert" style="margin-bottom: 20px;">
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <form id="otpVerifyForm" action="<?= public_url('forgot.php?step=verify') ?>" method="POST" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="verify_otp">
                    <!-- Hidden Master Input for submitting full 6 digits -->
                    <input type="hidden" id="verify_otp" name="otp" value="" required>

                    <div class="otp-digits-container">
                        <label class="form-label otp-digits-label">Enter 6-Digit Verification Code *</label>
                        <div class="otp-boxes-grid" id="otpBoxesGrid">
                            <input type="text" class="otp-digit-box" inputmode="numeric" pattern="[0-9]" maxlength="1" data-index="0" autofocus autocomplete="one-time-code">
                            <input type="text" class="otp-digit-box" inputmode="numeric" pattern="[0-9]" maxlength="1" data-index="1">
                            <input type="text" class="otp-digit-box" inputmode="numeric" pattern="[0-9]" maxlength="1" data-index="2">
                            <span class="otp-digit-divider">&ndash;</span>
                            <input type="text" class="otp-digit-box" inputmode="numeric" pattern="[0-9]" maxlength="1" data-index="3">
                            <input type="text" class="otp-digit-box" inputmode="numeric" pattern="[0-9]" maxlength="1" data-index="4">
                            <input type="text" class="otp-digit-box" inputmode="numeric" pattern="[0-9]" maxlength="1" data-index="5">
                        </div>
                    </div>

                    <button type="submit" id="btnVerifySubmit" class="auth-submit-btn">
                        <span>Verify Code &amp; Continue</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>
                </form>

                <div class="auth-form-card__footer auth-verify-footer">
                    <div>Didn't receive the code? <a href="<?= public_url('forgot.php?step=request') ?>">Request New Code</a></div>
                    <a href="<?= public_url('login.php') ?>" class="auth-back-link">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        Back to Login
                    </a>
                </div>

                <script>
                (function() {
                    // 1. Countdown Timer
                    let seconds = 300;
                    const countdownEl = document.getElementById('otpCountdown');
                    if (countdownEl) {
                        const timer = setInterval(function() {
                            seconds--;
                            if (seconds <= 0) {
                                clearInterval(timer);
                                countdownEl.textContent = 'EXPIRED';
                                countdownEl.style.color = '#DC2626';
                            } else {
                                const m = String(Math.floor(seconds / 60)).padStart(2, '0');
                                const s = String(seconds % 60).padStart(2, '0');
                                countdownEl.textContent = m + ':' + s;
                                if (seconds < 60) {
                                    countdownEl.style.color = '#DC2626';
                                }
                            }
                        }, 1000);
                    }

                    // 2. 6-Digit Segmented Box Navigation & Paste Support
                    const form = document.getElementById('otpVerifyForm');
                    const hiddenOtp = document.getElementById('verify_otp');
                    const boxes = Array.from(document.querySelectorAll('.otp-digit-box'));
                    if (!boxes.length) return;

                    function syncMasterInput() {
                        const code = boxes.map(b => b.value.trim()).join('');
                        hiddenOtp.value = code;
                        return code;
                    }

                    boxes.forEach((box, idx) => {
                        box.addEventListener('input', (e) => {
                            const val = e.target.value.replace(/\D/g, '');
                            e.target.value = val ? val[0] : '';
                            e.target.classList.toggle('is-filled', !!e.target.value);
                            syncMasterInput();

                            if (e.target.value && idx < boxes.length - 1) {
                                boxes[idx + 1].focus();
                                boxes[idx + 1].select();
                            }
                        });

                        box.addEventListener('keydown', (e) => {
                            if (e.key === 'Backspace') {
                                if (!box.value && idx > 0) {
                                    boxes[idx - 1].focus();
                                    boxes[idx - 1].value = '';
                                    boxes[idx - 1].classList.remove('is-filled');
                                    syncMasterInput();
                                } else {
                                    box.value = '';
                                    box.classList.remove('is-filled');
                                    syncMasterInput();
                                }
                            } else if (e.key === 'ArrowLeft' && idx > 0) {
                                boxes[idx - 1].focus();
                                boxes[idx - 1].select();
                            } else if (e.key === 'ArrowRight' && idx < boxes.length - 1) {
                                boxes[idx + 1].focus();
                                boxes[idx + 1].select();
                            }
                        });

                        box.addEventListener('paste', (e) => {
                            e.preventDefault();
                            const pasteData = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                            if (!pasteData) return;
                            pasteData.split('').forEach((char, i) => {
                                if (boxes[i]) {
                                    boxes[i].value = char;
                                    boxes[i].classList.add('is-filled');
                                }
                            });
                            syncMasterInput();
                            const targetIdx = Math.min(pasteData.length, boxes.length - 1);
                            boxes[targetIdx].focus();
                            boxes[targetIdx].select();
                        });

                        box.addEventListener('focus', () => {
                            box.select();
                        });
                    });

                    if (form) {
                        form.addEventListener('submit', function(e) {
                            const code = syncMasterInput();
                            if (code.length !== 6) {
                                e.preventDefault();
                                const emptyBox = boxes.find(b => !b.value.trim()) || boxes[0];
                                emptyBox.focus();
                                emptyBox.select();
                            }
                        });
                    }
                })();
                </script>

            <?php else: ?>
                <!-- STEP 1: REQUEST OTP -->
                <div class="auth-security-badge">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    <span>Password Recovery &bull; Step 1 of 2</span>
                </div>
                <h1 class="auth-form-card__title">Reset Password</h1>
                <p class="auth-form-card__sub">Enter your registered email address or 10-digit mobile number to verify your identity.</p>

                <?php if ($isDemoMode): ?>
                    <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; padding: 12px 14px; margin-bottom: 20px; font-size: 13px; color: var(--color-evergreen); line-height: 1.4;">
                        <strong>Free Demo Mode:</strong> The generated 6-digit OTP will be presented directly on your screen (no paid SMS gateway needed).
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error" role="alert" style="margin-bottom: 20px;">
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <form action="<?= public_url('forgot.php?step=request') ?>" method="POST" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="request_otp">

                    <div class="form-group">
                        <label class="form-label" for="req_identifier">Registered Email Address or 10-Digit Mobile *</label>
                        <input type="text" id="req_identifier" name="identifier" class="form-control" placeholder="name@store.com or 9876543210" required autofocus>
                        <div class="field-hint">We will generate a 5-minute single-use 6-digit verification code.</div>
                    </div>

                    <button type="submit" id="btnRequestSubmit" class="auth-submit-btn">
                        <span>Generate Verification Code</span>
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
