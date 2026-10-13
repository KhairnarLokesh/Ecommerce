<?php
/**
 * Kalika Enterprises - Customer Registration
 * Phase 5: Account creation with mobile uniqueness, password hashing, and auto-login
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cart.php';

// Only redirect GET requests away if already authenticated; always allow POST to create account
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    require_guest();
}

$errors = [];
$fieldErrors = [];
$nameVal = '';
$mobileVal = '';
$emailVal = '';
$addressVal = '';
$returnUrl = trim($_GET['return'] ?? $_POST['return'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Security token invalid or expired. Please refresh the page and try again.';
    } else {
        $nameVal = trim($_POST['name'] ?? '');
        $rawMobile = trim($_POST['mobile'] ?? '');

        // Normalize mobile: strip spaces, hyphens, parentheses, and leading +91 / 91 / 0
        $cleanMobile = preg_replace('/[^\d]/', '', $rawMobile);
        if (str_starts_with($cleanMobile, '91') && strlen($cleanMobile) === 12) {
            $cleanMobile = substr($cleanMobile, 2);
        } elseif (str_starts_with($cleanMobile, '0') && strlen($cleanMobile) === 11) {
            $cleanMobile = substr($cleanMobile, 1);
        }
        $mobileVal = $cleanMobile;

        $emailVal = trim($_POST['email'] ?? '');
        $addressVal = trim($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($nameVal) || mb_strlen($nameVal) > 100) {
            $fieldErrors['name'] = 'Please enter a valid shop or contact person name (max 100 characters).';
            $errors[] = $fieldErrors['name'];
        }

        if (empty($mobileVal) || !preg_match('/^[6-9]\d{9}$/', $mobileVal)) {
            $fieldErrors['mobile'] = 'Please enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9.';
            $errors[] = $fieldErrors['mobile'];
        }

        if (empty($emailVal) || !filter_var($emailVal, FILTER_VALIDATE_EMAIL) || mb_strlen($emailVal) > 150) {
            $fieldErrors['email'] = 'Please enter a valid business email address.';
            $errors[] = $fieldErrors['email'];
        }

        if (empty($addressVal) || mb_strlen($addressVal) < 10) {
            $fieldErrors['address'] = 'Please enter complete shop delivery address (minimum 10 characters).';
            $errors[] = $fieldErrors['address'];
        }

        if (strlen($password) < 8) {
            $fieldErrors['password'] = 'Password must be at least 8 characters long.';
            $errors[] = $fieldErrors['password'];
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $fieldErrors['password'] = 'Password must contain at least one letter and one number.';
            $errors[] = $fieldErrors['password'];
        } elseif ($password !== $confirmPassword) {
            $fieldErrors['confirm_password'] = 'Password confirmation does not match.';
            $errors[] = $fieldErrors['confirm_password'];
        }

        if (empty($_POST['terms'])) {
            $fieldErrors['terms'] = 'You must accept the terms of service and wholesale delivery policies to create an account.';
            $errors[] = $fieldErrors['terms'];
        }

        // Check if mobile or email already exists in database
        if (empty($errors)) {
            if ($pdo) {
                try {
                    // Check duplicate mobile
                    $mobileStmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ? LIMIT 1");
                    $mobileStmt->execute([$mobileVal]);
                    if ($mobileStmt->fetch()) {
                        $fieldErrors['mobile'] = 'A retailer account with this mobile number already exists. Please sign in instead.';
                        $errors[] = $fieldErrors['mobile'];
                    }

                    // Check duplicate email
                    if (empty($errors)) {
                        $emailStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                        $emailStmt->execute([$emailVal]);
                        if ($emailStmt->fetch()) {
                            $fieldErrors['email'] = 'A retailer account with this email address already exists. Please sign in instead.';
                            $errors[] = $fieldErrors['email'];
                        }
                    }

                    if (empty($errors)) {
                        // Insert new user with secure password hash
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $insertStmt = $pdo->prepare("
                            INSERT INTO users (name, mobile, email, password_hash, address)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $insertStmt->execute([
                            $nameVal,
                            $mobileVal,
                            $emailVal,
                            $passwordHash,
                            $addressVal
                        ]);

                        $newUserId = (int)$pdo->lastInsertId();

                        // Auto-authenticate customer session with regenerated ID
                        login_user([
                            'id'      => $newUserId,
                            'name'    => $nameVal,
                            'mobile'  => $mobileVal,
                            'email'   => $emailVal,
                            'address' => $addressVal
                        ]);

                        // Merge guest cart items into newly created user account
                        cart_merge_on_login($pdo, $newUserId);

                        set_flash('success', 'Account created successfully! Welcome to Kalika Enterprises wholesale distribution.');
                        
                        // Sanitize return redirect:
                        // If returning to a designated destination (e.g. cart or checkout), redirect there.
                        // Otherwise, redirect directly to profile.php so the customer immediately sees their registered account!
                        if (!empty($returnUrl) && preg_match('/^[a-z0-9_-]+\.php(\?[a-z0-9_=&-]+)?$/i', $returnUrl)) {
                            redirect(public_url($returnUrl));
                        } else {
                            redirect(public_url('profile.php'));
                        }
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000' || (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062)) {
                        if (str_contains($e->getMessage(), 'mobile')) {
                            $fieldErrors['mobile'] = 'A retailer account with this mobile number already exists. Please sign in instead.';
                            $errors[] = $fieldErrors['mobile'];
                        } else {
                            $fieldErrors['email'] = 'A retailer account with this email address already exists. Please sign in instead.';
                            $errors[] = $fieldErrors['email'];
                        }
                    } else {
                        error_log("Registration error: " . $e->getMessage());
                        $errors[] = 'A database error occurred during registration. Please try again.';
                    }
                }
            } else {
                // Serverless showcase demo registration (when remote MySQL is not yet configured)
                $demoUserId = (int)(substr($mobileVal, -4)) ?: 1001;
                login_user([
                    'id'      => $demoUserId,
                    'name'    => $nameVal,
                    'mobile'  => $mobileVal,
                    'email'   => $emailVal,
                    'address' => $addressVal
                ]);

                // Merge guest cart items
                cart_merge_on_login(null, $demoUserId);

                set_flash('success', 'Account created successfully! Welcome to Kalika Enterprises wholesale distribution.');

                if (!empty($returnUrl) && preg_match('/^[a-z0-9_-]+\.php(\?[a-z0-9_=&-]+)?$/i', $returnUrl)) {
                    redirect(public_url($returnUrl));
                } else {
                    redirect(public_url('profile.php'));
                }
            }
        }
    }
}

$pageTitle = 'Register Retail Account | Kalika Enterprises';
$currentPage = 'account';
$pageDescription = 'Create a wholesale customer account for your retail store with Kalika Enterprises in Niphad, Dindori, Chandwad, Sinnar, or Yeola.';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-form-side">
        <div class="auth-form-card">
            <div class="auth-form-card__eyebrow">Retailer Registration</div>
            <h1 class="auth-form-card__title">Create Account</h1>
            <p class="auth-form-card__sub">Access wholesale distributor rates and digital ordering.</p>

            <?php if (!empty($errors)): ?>
                <div id="registrationErrorAlert" class="alert alert-error" role="alert">
                    <div>
                        <strong>Please resolve the following:</strong>
                        <ul>
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form id="registrationForm" action="<?= public_url('register.php' . (!empty($returnUrl) ? '?return=' . urlencode($returnUrl) : '')) ?>" method="POST">
                <?= csrf_field() ?>
                <?php if (!empty($returnUrl)): ?>
                    <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label" for="reg_name">Store Name / Contact Person *</label>
                    <input type="text" id="reg_name" name="name" class="form-control <?= isset($fieldErrors['name']) ? 'is-invalid' : '' ?>" placeholder="e.g. Mahalakshmi Provision Stores / Nilesh Patil" value="<?= e($nameVal) ?>" required autofocus>
                    <?php if (isset($fieldErrors['name'])): ?>
                        <div class="field-error-text"><?= e($fieldErrors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reg_mobile">10-Digit Mobile Number *</label>
                    <div class="input-prefix">
                        <span class="input-prefix-addon">+91</span>
                        <input type="tel" id="reg_mobile" name="mobile" class="form-control <?= isset($fieldErrors['mobile']) ? 'is-invalid' : '' ?>" pattern="[6-9][0-9]{9}" maxlength="15" placeholder="9876543210" value="<?= e($mobileVal) ?>" required>
                    </div>
                    <?php if (isset($fieldErrors['mobile'])): ?>
                        <div class="field-error-text"><?= e($fieldErrors['mobile']) ?></div>
                    <?php else: ?>
                        <div class="field-hint">Your mobile number is your primary login identifier. Enter 10 digits starting 6, 7, 8, or 9.</div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reg_email">Email Address *</label>
                    <input type="email" id="reg_email" name="email" class="form-control <?= isset($fieldErrors['email']) ? 'is-invalid' : '' ?>" placeholder="store@example.com" value="<?= e($emailVal) ?>" required>
                    <?php if (isset($fieldErrors['email'])): ?>
                        <div class="field-error-text"><?= e($fieldErrors['email']) ?></div>
                    <?php else: ?>
                        <div class="field-hint">Used for official wholesale order confirmations and invoice records.</div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reg_address">Shop Delivery Address *</label>
                    <textarea id="reg_address" name="address" class="form-control <?= isset($fieldErrors['address']) ? 'is-invalid' : '' ?>" rows="3" placeholder="Shop No., Market Yard, Village/City, PIN code" required minlength="10"><?= e($addressVal) ?></textarea>
                    <?php if (isset($fieldErrors['address'])): ?>
                        <div class="field-error-text"><?= e($fieldErrors['address']) ?></div>
                    <?php else: ?>
                        <div class="field-hint" id="addressHint">Must be located in Niphad, Dindori, Chandwad, Sinnar, or Yeola (minimum 10 characters).</div>
                    <?php endif; ?>
                </div>

                <div class="auth-password-row">
                    <div class="form-group">
                        <label class="form-label" for="reg_password">Password *</label>
                        <div class="password-field-wrap">
                            <input type="password" id="reg_password" name="password" class="form-control <?= isset($fieldErrors['password']) ? 'is-invalid' : '' ?>" minlength="8" placeholder="Min 8 chars" required>
                            <button type="button" class="btn-toggle-password" data-target="reg_password" aria-label="Toggle password visibility">
                                <svg class="eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <svg class="eye-closed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg_confirm_password">Confirm *</label>
                        <div class="password-field-wrap">
                            <input type="password" id="reg_confirm_password" name="confirm_password" class="form-control <?= isset($fieldErrors['confirm_password']) ? 'is-invalid' : '' ?>" minlength="8" placeholder="Repeat" required>
                            <button type="button" class="btn-toggle-password" data-target="reg_confirm_password" aria-label="Toggle password visibility">
                                <svg class="eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <svg class="eye-closed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (isset($fieldErrors['password'])): ?>
                    <div class="auth-password-hint is-error"><?= e($fieldErrors['password']) ?></div>
                <?php elseif (isset($fieldErrors['confirm_password'])): ?>
                    <div class="auth-password-hint is-error"><?= e($fieldErrors['confirm_password']) ?></div>
                <?php else: ?>
                    <div id="passwordHint" class="auth-password-hint">Password policy: Minimum 8 characters with at least one letter and one number.</div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="auth-terms-check <?= isset($fieldErrors['terms']) ? 'is-invalid' : '' ?>">
                        <input type="checkbox" id="reg_terms" name="terms" value="1" <?= !empty($_POST['terms']) ? 'checked' : '' ?> required>
                        <span>I accept the <a href="<?= public_url('policies.php') ?>" target="_blank">Terms of Service &amp; Wholesale Delivery Policies</a> *</span>
                    </label>
                    <?php if (isset($fieldErrors['terms'])): ?>
                        <div class="field-error-text"><?= e($fieldErrors['terms']) ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" id="btnRegisterSubmit" class="auth-submit-btn">
                    <span id="btnRegisterText">Create Retailer Account</span>
                    <svg id="btnRegisterIcon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </form>

            <div class="auth-form-card__footer">
                Already registered? <a href="<?= public_url('login.php' . (!empty($returnUrl) ? '?return=' . urlencode($returnUrl) : '')) ?>">Sign in to account</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registrationForm');
    const submitBtn = document.getElementById('btnRegisterSubmit');
    const submitText = document.getElementById('btnRegisterText');
    const submitIcon = document.getElementById('btnRegisterIcon');
    const errorAlert = document.getElementById('registrationErrorAlert');

    // Auto-scroll to error banner if server returned validation errors
    if (errorAlert) {
        errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    const mobileInput = document.getElementById('reg_mobile');
    const addressInput = document.getElementById('reg_address');
    const passwordInput = document.getElementById('reg_password');
    const confirmPasswordInput = document.getElementById('reg_confirm_password');
    const termsInput = document.getElementById('reg_terms');

    // Auto-clean mobile input on user paste or input
    if (mobileInput) {
        mobileInput.addEventListener('input', function () {
            let val = this.value.replace(/[^\d]/g, '');
            if (val.startsWith('91') && val.length > 10) {
                val = val.substring(2);
            } else if (val.startsWith('0') && val.length > 10) {
                val = val.substring(1);
            }
            if (val.length > 10) {
                val = val.substring(0, 10);
            }
            this.value = val;
        });
    }

    // Client-side submit validation and UX feedback
    if (form) {
        form.addEventListener('submit', function (e) {
            let isValid = true;
            let firstInvalidEl = null;

            // Reset field borders
            [mobileInput, addressInput, passwordInput, confirmPasswordInput].forEach(function (el) {
                if (el) {
                    el.style.borderColor = '';
                    el.style.backgroundColor = '';
                }
            });

            // Validate Mobile
            const mobileVal = (mobileInput ? mobileInput.value.trim() : '');
            if (!/^[6-9]\d{9}$/.test(mobileVal)) {
                if (mobileInput) {
                    mobileInput.style.borderColor = '#ef4444';
                    mobileInput.style.backgroundColor = '#fef2f2';
                    if (!firstInvalidEl) firstInvalidEl = mobileInput;
                }
                isValid = false;
            }

            // Validate Address (min 10 chars)
            const addressVal = (addressInput ? addressInput.value.trim() : '');
            if (addressVal.length < 10) {
                if (addressInput) {
                    addressInput.style.borderColor = '#ef4444';
                    addressInput.style.backgroundColor = '#fef2f2';
                    if (!firstInvalidEl) firstInvalidEl = addressInput;
                }
                isValid = false;
            }

            // Validate Password (min 8 chars, letter + number)
            const passVal = (passwordInput ? passwordInput.value : '');
            if (passVal.length < 8 || !/[A-Za-z]/.test(passVal) || !/[0-9]/.test(passVal)) {
                if (passwordInput) {
                    passwordInput.style.borderColor = '#ef4444';
                    passwordInput.style.backgroundColor = '#fef2f2';
                    if (!firstInvalidEl) firstInvalidEl = passwordInput;
                }
                isValid = false;
            }

            // Validate Confirm Password
            const confirmVal = (confirmPasswordInput ? confirmPasswordInput.value : '');
            if (passVal !== confirmVal) {
                if (confirmPasswordInput) {
                    confirmPasswordInput.style.borderColor = '#ef4444';
                    confirmPasswordInput.style.backgroundColor = '#fef2f2';
                    if (!firstInvalidEl) firstInvalidEl = confirmPasswordInput;
                }
                isValid = false;
            }

            // Validate Terms
            if (termsInput && !termsInput.checked) {
                if (!firstInvalidEl) firstInvalidEl = termsInput;
                isValid = false;
            }

            if (!isValid) {
                if (firstInvalidEl) {
                    firstInvalidEl.focus();
                    firstInvalidEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return;
            }

            // Loading state on submit button
            if (submitBtn) {
                submitBtn.disabled = true;
                if (submitText) submitText.textContent = 'Creating Retailer Account...';
                if (submitIcon) submitIcon.style.display = 'none';
                form.submit();
            }
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
