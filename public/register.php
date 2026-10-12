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

require_guest();

$errors = [];
$nameVal = '';
$mobileVal = '';
$emailVal = '';
$addressVal = '';
$returnUrl = trim($_GET['return'] ?? $_POST['return'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Security token invalid or expired. Please refresh the page.';
    } else {
        $nameVal = trim($_POST['name'] ?? '');
        $mobileVal = trim($_POST['mobile'] ?? '');
        $emailVal = trim($_POST['email'] ?? '');
        $addressVal = trim($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($nameVal) || mb_strlen($nameVal) > 100) {
            $errors[] = 'Please enter a valid shop or contact name (max 100 characters).';
        }

        if (empty($mobileVal) || !preg_match('/^[6-9]\d{9}$/', $mobileVal)) {
            $errors[] = 'Please enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9.';
        }

        if (empty($emailVal) || !filter_var($emailVal, FILTER_VALIDATE_EMAIL) || mb_strlen($emailVal) > 150) {
            $errors[] = 'Please enter a valid business email address.';
        }

        if (empty($addressVal) || mb_strlen($addressVal) < 10) {
            $errors[] = 'Please enter complete shop delivery address (minimum 10 characters).';
        }

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one letter and one number.';
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }

        if (empty($_POST['terms'])) {
            $errors[] = 'You must accept the terms of service and wholesale delivery policies to create an account.';
        }

        // Check if mobile or email already exists in database
        if (empty($errors)) {
            if ($pdo) {
                try {
                    // Check duplicate mobile
                    $mobileStmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ? LIMIT 1");
                    $mobileStmt->execute([$mobileVal]);
                    if ($mobileStmt->fetch()) {
                        $errors[] = 'A retailer account with this mobile number already exists. Please sign in instead.';
                    }

                    // Check duplicate email
                    if (empty($errors)) {
                        $emailStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                        $emailStmt->execute([$emailVal]);
                        if ($emailStmt->fetch()) {
                            $errors[] = 'A retailer account with this email address already exists. Please sign in instead.';
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
                        
                        // Sanitize return redirect
                        if (!empty($returnUrl) && preg_match('/^[a-z0-9_-]+\.php(\?[a-z0-9_=&-]+)?$/i', $returnUrl)) {
                            redirect(public_url($returnUrl));
                        } else {
                            redirect(public_url('index.php'));
                        }
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000' || (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062)) {
                        if (str_contains($e->getMessage(), 'mobile')) {
                            $errors[] = 'A retailer account with this mobile number already exists. Please sign in instead.';
                        } else {
                            $errors[] = 'A retailer account with this email address already exists. Please sign in instead.';
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
                    redirect(public_url('index.php'));
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
        <div class="auth-form-card" style="max-width: 480px;">
            <div class="auth-form-card__eyebrow">Retailer Registration</div>
            <h1 class="auth-form-card__title">Create Account</h1>
            <p class="auth-form-card__sub">Access wholesale distributor rates and digital ordering.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" role="alert" style="margin-bottom: 20px;">
                    <div>
                        <strong>Registration failed:</strong>
                        <ul style="margin: 6px 0 0 16px;">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form action="<?= public_url('register.php' . (!empty($returnUrl) ? '?return=' . urlencode($returnUrl) : '')) ?>" method="POST" novalidate>
                <?= csrf_field() ?>
                <?php if (!empty($returnUrl)): ?>
                    <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label" for="reg_name">Store Name / Contact Person *</label>
                    <input type="text" id="reg_name" name="name" class="form-control" placeholder="e.g. Mahalakshmi Provision Stores / Nilesh Patil" value="<?= e($nameVal) ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reg_mobile">10-Digit Mobile Number *</label>
                    <div class="input-prefix">
                        <span class="input-prefix-addon">+91</span>
                        <input type="tel" id="reg_mobile" name="mobile" class="form-control" pattern="[6-9][0-9]{9}" placeholder="9876543210" value="<?= e($mobileVal) ?>" required>
                    </div>
                    <div class="field-hint">Your mobile number is your primary login identifier.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reg_email">Email Address *</label>
                    <input type="email" id="reg_email" name="email" class="form-control" placeholder="store@example.com" value="<?= e($emailVal) ?>" required>
                    <div class="field-hint">Used for official wholesale order confirmations and invoice records.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reg_address">Shop Delivery Address *</label>
                    <textarea id="reg_address" name="address" class="form-control" rows="3" placeholder="Shop No., Market Yard, Village/City, PIN code" required><?= e($addressVal) ?></textarea>
                    <div class="field-hint">Must be located in Niphad, Dindori, Chandwad, Sinnar, or Yeola.</div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label" for="reg_password">Password *</label>
                        <div style="position: relative;">
                            <input type="password" id="reg_password" name="password" class="form-control" minlength="8" placeholder="Min 8 chars" required style="padding-right: 36px;">
                            <button type="button" class="btn-toggle-password" data-target="reg_password" aria-label="Toggle password visibility" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 4px; display: inline-flex; align-items: center;">
                                <svg class="eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <svg class="eye-closed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg_confirm_password">Confirm *</label>
                        <div style="position: relative;">
                            <input type="password" id="reg_confirm_password" name="confirm_password" class="form-control" minlength="8" placeholder="Repeat" required style="padding-right: 36px;">
                            <button type="button" class="btn-toggle-password" data-target="reg_confirm_password" aria-label="Toggle password visibility" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 4px; display: inline-flex; align-items: center;">
                                <svg class="eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <svg class="eye-closed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="field-hint" style="margin-top: -6px; margin-bottom: 12px;">Password policy: Minimum 8 characters with at least one letter and one number.</div>

                <div class="form-group" style="margin-top: 14px; margin-bottom: 16px;">
                    <label class="form-check" style="display: flex; align-items: flex-start; gap: 8px; font-size: 13px; line-height: 1.4; color: var(--text-color); cursor: pointer;">
                        <input type="checkbox" id="reg_terms" name="terms" value="1" <?= !empty($_POST['terms']) ? 'checked' : '' ?> required style="margin-top: 3px;">
                        <span>I accept the <a href="<?= public_url('policies.php') ?>" target="_blank" style="color: var(--color-mint); text-decoration: underline;">Terms of Service &amp; Wholesale Delivery Policies</a> *</span>
                    </label>
                </div>

                <button type="submit" id="btnRegisterSubmit" class="dd-btn dd-btn--mint" style="width: 100%; justify-content: center; margin-top: 8px;">
                    Create Retailer Account
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </form>

            <div class="auth-form-card__footer">
                Already registered? <a href="<?= public_url('login.php' . (!empty($returnUrl) ? '?return=' . urlencode($returnUrl) : '')) ?>">Sign in to account</a>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
